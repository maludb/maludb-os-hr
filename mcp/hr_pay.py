"""Pay — read tools (docs/hr-mcp-tool-surface.md, "Pay"). Reads mcp_current_pay,
mcp_compensation_changes, mcp_pay_runs, mcp_pay_run_items, mcp_pay_run_lines. The views admit a
person to their own pay, a manager to their reports', HR staff to all — and never an agent.
"""
from __future__ import annotations

import json

from pydantic import Field

import db
from business_common import RO, _Base, _period_bounds


class PayHistoryIn(_Base):
    member_id: int | None = Field(None, description="The person; omit for the caller")


class FindPayRunsIn(_Base):
    status: str | None = Field(None, description="draft, approved, paid or void")
    period: str | None = Field(None, description="'YYYY-MM' of the period end; omit for all")
    unpaid_only: bool = Field(False, description="Only runs approved but not yet paid, and drafts")
    q: str | None = Field(None, description="A run number fragment (PR-0001)")
    limit: int = Field(25, ge=1, le=100)


class GetPayRunIn(_Base):
    pay_run_id: int | None = None
    run_no: str | None = Field(None, description="e.g. PR-0001, instead of the id")


class MyPayslipIn(_Base):
    run_no: str | None = Field(None, description="A specific run; omit for the newest")


class PayrollSummaryIn(_Base):
    period: str | None = Field("this_month", description="this_month, last_month, this_quarter, this_year, last_year or YYYY-MM (by pay date)")
    date_from: str | None = None
    date_to: str | None = None
    group_by: str = Field("department", description="'department', 'member', 'month' or 'run'")
    include_drafts: bool = Field(False, description="Count draft runs too (default: approved and paid only)")


def register(mcp, q) -> None:
    @mcp.tool(name="pay_history", annotations={"title": "What someone is paid, and how it changed", **RO})
    async def pay_history(params: PayHistoryIn) -> str:
        """What a person is paid now (type, rate, currency, since when, a monthly estimate) and every
        change to it with who recorded it. Shown to the person, their manager and HR staff; an empty
        answer means the caller may not see this person's pay, or none is recorded. Never for agents."""
        mid = params.member_id or db.request_member_id.get()
        pool = await q.__globals__["_pool"]()
        cur = await db.fetch_scoped(pool, "SELECT * FROM mcp_current_pay WHERE member_id = $1", mid)
        hist = await db.fetch_scoped(pool, "SELECT compensation_change_id, effective_date, pay_type, pay_rate, currency, reason, created_by_name, created_at FROM mcp_compensation_changes WHERE member_id = $1 ORDER BY effective_date DESC", mid)
        if not cur and not hist:
            return json.dumps({"member_id": mid, "current": None, "changes": [], "note": "No pay you may see for this person."})
        return db.to_json({"member_id": mid, "current": cur[0] if cur else None, "changes": hist})

    @mcp.tool(name="find_pay_runs", annotations={"title": "Find pay runs", **RO})
    async def find_pay_runs(params: FindPayRunsIn) -> str:
        """Pay runs by period, status or number; the unapproved and the unpaid. HR staff only. Call
        before get_pay_run when you have a period or a number rather than an id."""
        w, a = ["1 = 1"], []
        if params.status in ("draft", "approved", "paid", "void"):
            a.append(params.status); w.append(f"status = ${len(a)}")
        if params.unpaid_only:
            w.append("status IN ('draft', 'approved')")
        if params.period and len(params.period) == 7:
            a.append(params.period); w.append(f"to_char(period_end, 'YYYY-MM') = ${len(a)}")
        if params.q:
            a.append(f"%{params.q[:20]}%"); w.append(f"run_no ILIKE ${len(a)}")
        a.append(params.limit)
        return await q(f"SELECT pay_run_id, run_no, period_start, period_end, pay_date, currency, status, people, gross_total, deductions_total, employer_cost_total, reimbursements_total, net_total, approved_by_name, approved_at, paid_at FROM mcp_pay_runs WHERE {' AND '.join(w)} ORDER BY period_end DESC LIMIT ${len(a)}", *a)

    @mcp.tool(name="get_pay_run", annotations={"title": "One pay run, whole", **RO})
    async def get_pay_run(params: GetPayRunIn) -> str:
        """One run with every person on it, each line (base and keyed), and each note explaining a
        missing base line. HR staff; a person sees only their own item on an approved or paid run."""
        pool = await q.__globals__["_pool"]()
        if params.pay_run_id:
            runs = await db.fetch_scoped(pool, "SELECT * FROM mcp_pay_runs WHERE pay_run_id = $1", params.pay_run_id)
        elif params.run_no:
            runs = await db.fetch_scoped(pool, "SELECT * FROM mcp_pay_runs WHERE run_no = $1", params.run_no.strip().upper())
        else:
            return json.dumps({"error": "Give pay_run_id or run_no."})
        if not runs:
            return json.dumps({"error": "No such pay run that you may see; find_pay_runs lists them."})
        run = runs[0]
        items = await db.fetch_scoped(pool, "SELECT * FROM mcp_pay_run_items WHERE pay_run_id = $1 ORDER BY display_name", run["pay_run_id"])
        lines = await db.fetch_scoped(pool, "SELECT * FROM mcp_pay_run_lines WHERE pay_run_id = $1 ORDER BY pay_run_item_id, is_base DESC, kind", run["pay_run_id"])
        by_item: dict = {}
        for l in lines:
            by_item.setdefault(l["pay_run_item_id"], []).append(l)
        for i in items:
            i["lines"] = by_item.get(i["pay_run_item_id"], [])
        return db.to_json({"run": run, "items": items})

    @mcp.tool(name="my_payslip", annotations={"title": "My payslip", **RO})
    async def my_payslip(params: MyPayslipIn) -> str:
        """The caller's own item on the newest approved or paid run (or a named run), with its lines.
        Call for "what was on my last payslip"."""
        pool = await q.__globals__["_pool"]()
        mid = db.request_member_id.get()
        if params.run_no:
            items = await db.fetch_scoped(pool, "SELECT * FROM mcp_pay_run_items WHERE member_id = $1 AND run_no = $2 AND run_status IN ('approved', 'paid')", mid, params.run_no.strip().upper())
        else:
            items = await db.fetch_scoped(pool, "SELECT * FROM mcp_pay_run_items WHERE member_id = $1 AND run_status IN ('approved', 'paid') ORDER BY pay_date DESC LIMIT 1", mid)
        if not items:
            return json.dumps({"payslip": None, "note": "No approved or paid run with you on it yet."})
        item = items[0]
        item["lines"] = await db.fetch_scoped(pool, "SELECT kind, code, description, quantity, rate, amount, is_base FROM mcp_pay_run_lines WHERE pay_run_item_id = $1 ORDER BY is_base DESC, kind", item["pay_run_item_id"])
        return db.to_json({"payslip": item})

    @mcp.tool(name="payroll_summary", annotations={"title": "What people cost", **RO})
    async def payroll_summary(params: PayrollSummaryIn) -> str:
        """What people cost over a period (by pay date): gross, deductions, employer costs,
        reimbursements and net, grouped by department, person, month or run. Approved and paid runs
        only unless include_drafts. HR staff only."""
        s, e = _period_bounds(params.period, params.date_from, params.date_to)
        statuses = "('draft', 'approved', 'paid')" if params.include_drafts else "('approved', 'paid')"
        key = {"department": "COALESCE(e.primary_department, '(no department)')", "member": "i.display_name", "month": "to_char(i.pay_date, 'YYYY-MM')", "run": "i.run_no"}.get(params.group_by, "COALESCE(e.primary_department, '(no department)')")
        return await q(f"""SELECT {key} AS group_key, count(DISTINCT i.member_id) AS people, count(DISTINCT i.pay_run_id) AS runs,
                                  sum(i.gross_amount) AS gross, sum(i.deductions) AS deductions, sum(i.employer_cost) AS employer_cost,
                                  sum(i.reimbursements) AS reimbursements, sum(i.net_amount) AS net, min(i.currency) AS currency
                             FROM mcp_pay_run_items i LEFT JOIN mcp_employment_profiles e ON e.member_id = i.member_id
                            WHERE i.run_status IN {statuses} AND i.pay_date BETWEEN $1 AND $2
                            GROUP BY 1 ORDER BY 1""", s, e)
