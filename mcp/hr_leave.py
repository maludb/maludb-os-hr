"""Leave — read tools (docs/hr-mcp-tool-surface.md, "Leave"). Reads mcp_leave_balances,
mcp_leave_requests, mcp_leave_types, mcp_holidays. Visible wherever the person is visible.
"""
from __future__ import annotations

from pydantic import Field

import db
from business_common import RO, _Base, _iso_date, _period_bounds


class LeaveBalanceIn(_Base):
    member_id: int | None = Field(None, description="One person; omit with department_id for everyone visible, or alone for the caller")
    year: int | None = Field(None, ge=2000, le=2200, description="Omit for this year")
    department_id: int | None = None
    below_days: float | None = Field(None, description="Only balances with fewer days left than this")
    above_unused_days: float | None = Field(None, description="Only balances with more than this many days left")
    limit: int = Field(50, ge=1, le=200)


class FindLeaveRequestsIn(_Base):
    awaiting_me: bool = Field(False, description="Only requests the caller is to decide")
    member_id: int | None = None
    department_id: int | None = None
    status: str | None = Field(None, description="requested, approved, rejected or cancelled")
    period: str | None = Field(None, description="Requests touching this period: this_week, this_month, next_month, YYYY-MM…; omit for all")
    date_from: str | None = None
    date_to: str | None = None
    flagged_only: bool = Field(False, description="Only over-balance or overlapping requests")
    limit: int = Field(25, ge=1, le=100)


class WhosOffIn(_Base):
    date_from: str = Field(..., description="ISO date")
    date_to: str = Field(..., description="ISO date")
    department_id: int | None = None
    include_requested: bool = Field(False, description="Also requests not yet decided")


class FindLeaveTypesIn(_Base):
    include_archived: bool = False
    year: int | None = Field(None, description="Holidays of this year; omit for this year")


def register(mcp, q) -> None:
    @mcp.tool(name="leave_balance", annotations={"title": "Leave balances", **RO})
    async def leave_balance(params: LeaveBalanceIn) -> str:
        """Days left per leave type for a person (the caller by default) or for everyone visible in a
        department: accrued, carried over, used, committed (requested or approved), remaining. Call for
        "how much leave do I have", "who has more than ten days left". An unlimited type has no balance."""
        w, a = ["b.year = $1"], [params.year or __import__("datetime").date.today().year]
        if params.member_id or not params.department_id:
            a.append(params.member_id or db.request_member_id.get()); w.append(f"b.member_id = ${len(a)}")
        if params.department_id:
            a.append(params.department_id); w.append(f"EXISTS (SELECT 1 FROM mcp_department_members dm WHERE dm.member_id = b.member_id AND dm.department_id = ${len(a)} AND dm.left_at IS NULL)")
        if params.below_days is not None:
            a.append(params.below_days); w.append(f"(b.remaining_days - b.committed_days) < ${len(a)}")
        if params.above_unused_days is not None:
            a.append(params.above_unused_days); w.append(f"(b.remaining_days - b.committed_days) > ${len(a)}")
        a.append(params.limit)
        return await q(f"SELECT b.member_id, b.display_name, b.leave_type, b.year, b.accrued_days, b.carried_days, b.used_days, b.committed_days, b.remaining_days FROM mcp_leave_balances b WHERE {' AND '.join(w)} ORDER BY b.display_name, b.leave_type LIMIT ${len(a)}", *a)

    @mcp.tool(name="find_leave_requests", annotations={"title": "Find leave requests", **RO})
    async def find_leave_requests(params: FindLeaveRequestsIn) -> str:
        """Leave requests by person, department, status or dates; what awaits the caller's decision;
        the flagged ones (beyond the balance, overlapping). Call for "what leave is waiting for me",
        "Priya's leave this year". Each row says whether the caller may decide it (awaiting_me)."""
        w, a = ["1 = 1"], []
        if params.awaiting_me:
            w.append("awaiting_me")
        if params.member_id:
            a.append(params.member_id); w.append(f"member_id = ${len(a)}")
        if params.department_id:
            a.append(params.department_id); w.append(f"EXISTS (SELECT 1 FROM mcp_department_members dm WHERE dm.member_id = lr.member_id AND dm.department_id = ${len(a)} AND dm.left_at IS NULL)")
        if params.status in ("requested", "approved", "rejected", "cancelled"):
            a.append(params.status); w.append(f"status = ${len(a)}")
        if params.period or params.date_from or params.date_to:
            s, e = _period_bounds(params.period, params.date_from, params.date_to)
            a.append(s); w.append(f"end_date >= ${len(a)}")
            a.append(e); w.append(f"start_date <= ${len(a)}")
        if params.flagged_only:
            w.append("(over_balance OR has_overlap)")
        a.append(params.limit)
        return await q(f"SELECT leave_request_id, member_id, display_name, primary_department, leave_type, requires_approval, start_date, end_date, days, half_day, reason, status, over_balance, has_overlap, decided_by_name, decided_at, decision_note, awaiting_me, created_at FROM mcp_leave_requests lr WHERE {' AND '.join(w)} ORDER BY (status = 'requested') DESC, start_date DESC LIMIT ${len(a)}", *a)

    @mcp.tool(name="whos_off", annotations={"title": "Who is off", **RO})
    async def whos_off(params: WhosOffIn) -> str:
        """Who is off between two dates (approved leave; requested too when asked), in a department if
        given, plus the holidays in the range. Call for "who is off next week", "is anyone in the
        Kitchen off on Friday". Only people the caller may see."""
        pool = await q.__globals__["_pool"]()
        d_from, d_to = _iso_date(params.date_from), _iso_date(params.date_to)
        if d_from is None or d_to is None:
            return db.to_json({"error": "date_from and date_to are ISO dates (YYYY-MM-DD)."})
        w, a = ["end_date >= $1", "start_date <= $2", "status IN ('approved', 'requested')" if params.include_requested else "status = 'approved'"], [d_from, d_to]
        if params.department_id:
            a.append(params.department_id); w.append(f"EXISTS (SELECT 1 FROM mcp_department_members dm WHERE dm.member_id = lr.member_id AND dm.department_id = ${len(a)} AND dm.left_at IS NULL)")
        reqs = await db.fetch_scoped(pool, f"SELECT leave_request_id, member_id, display_name, primary_department, leave_type, start_date, end_date, days, half_day, status FROM mcp_leave_requests lr WHERE {' AND '.join(w)} ORDER BY display_name, start_date", *a)
        hol = await db.fetch_scoped(pool, "SELECT holiday_date, name FROM mcp_holidays WHERE holiday_date BETWEEN $1 AND $2 ORDER BY holiday_date", d_from, d_to)
        return db.to_json({"from": params.date_from, "to": params.date_to, "off": reqs, "holidays": hol})

    @mcp.tool(name="find_leave_types", annotations={"title": "Leave types and holidays", **RO})
    async def find_leave_types(params: FindLeaveTypesIn) -> str:
        """The leave types (paid or not, days a year, carry-over cap, whether a decision is needed) and
        the public holidays of the year. Call before leave_request_create to get the type's id, and for
        "is Monday a holiday"."""
        pool = await q.__globals__["_pool"]()
        types = await db.fetch_scoped(pool, "SELECT leave_type_id, name, is_paid, accrual_days_per_year, carry_over_days, requires_approval, archived_at FROM mcp_leave_types" + ("" if params.include_archived else " WHERE archived_at IS NULL") + " ORDER BY name")
        year = params.year or __import__("datetime").date.today().year
        hol = await db.fetch_scoped(pool, "SELECT holiday_id, holiday_date, name FROM mcp_holidays WHERE EXTRACT(year FROM holiday_date) = $1 ORDER BY holiday_date", year)
        return db.to_json({"leave_types": types, "holidays": hol, "year": year})
