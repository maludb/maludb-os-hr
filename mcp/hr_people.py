"""People and departments — read tools (docs/hr-mcp-tool-surface.md, "Directory and employment").
Reads mcp_members, mcp_department_members, mcp_departments, mcp_employment_profiles, mcp_current_pay,
mcp_leave_balances, mcp_leave_requests, mcp_reviews. Every query runs as the asking member.
"""
from __future__ import annotations

import json

from pydantic import Field

import db
from business_common import RO, _Base, _period_bounds


class FindPeopleIn(_Base):
    q: str | None = Field(None, description="A name, email or employee number fragment, as the user said it")
    department_id: int | None = Field(None, description="Only people in this department (find_departments gives ids)")
    employed: str | None = Field("any", description="'yes' = has an employment record, 'no' = in the directory only, 'any'")
    employment_type: str | None = Field(None, description="employee, contractor or intern")
    employment_status: str | None = Field(None, description="candidate, active, on_leave or ended")
    member_kind: str | None = Field(None, description="'human' or 'agent'; omit for both")
    manager_member_id: int | None = Field(None, description="Only the direct reports of this person")
    include_inactive: bool = Field(False, description="Also people the kernel deactivated")
    limit: int = Field(25, ge=1, le=100)


class GetPersonIn(_Base):
    member_id: int | None = Field(None, description="The person; omit for the caller")


class StartersLeaversIn(_Base):
    period: str | None = Field("this_month", description="this_month, next_month, last_month, this_quarter, this_year, next_30_days or YYYY-MM")
    date_from: str | None = None
    date_to: str | None = None
    kind: str = Field("all", description="'starting', 'leaving', 'probation' (probation ending), 'candidates' (not started yet) or 'all'")


class HeadcountIn(_Base):
    group_by: str = Field("department", description="'department', 'employment_type' or 'status'")
    as_of: str | None = Field(None, description="ISO date; omit for today")


class FindDepartmentsIn(_Base):
    q: str | None = Field(None, description="A name fragment")
    include_archived: bool = False
    limit: int = Field(50, ge=1, le=100)


class GetDepartmentIn(_Base):
    department_id: int


def register(mcp, q) -> None:
    @mcp.tool(name="find_people", annotations={"title": "Find people", **RO})
    async def find_people(params: FindPeopleIn) -> str:
        """Look up anyone in the business by name, email or employee number, or list who is in a
        department, who is employed (or in the directory only), by type or status, and who reports to
        whom. CALL THIS FIRST whenever an action needs a person — pass the name, never invent an id.
        Not for pay (pay_history) or leave (leave_balance, find_leave_requests). Agents are listed with
        member_kind 'agent' and never have an employment record."""
        w, a = ["(m.status = 'active' OR $1)"], [params.include_inactive]
        if params.q:
            a.append(f"%{params.q[:100]}%"); w.append(f"(m.display_name ILIKE ${len(a)} OR m.email ILIKE ${len(a)} OR e.employee_no ILIKE ${len(a)})")
        if params.department_id:
            a.append(params.department_id); w.append(f"EXISTS (SELECT 1 FROM mcp_department_members dm WHERE dm.member_id = m.member_id AND dm.department_id = ${len(a)} AND dm.left_at IS NULL)")
        if params.employed == "yes":
            w.append("e.member_id IS NOT NULL")
        elif params.employed == "no":
            w.append("e.member_id IS NULL")
        if params.employment_type in ("employee", "contractor", "intern"):
            a.append(params.employment_type); w.append(f"e.employment_type = ${len(a)}")
        if params.employment_status in ("candidate", "active", "on_leave", "ended"):
            a.append(params.employment_status); w.append(f"e.status = ${len(a)}")
        if params.member_kind in ("human", "agent"):
            a.append(params.member_kind); w.append(f"m.member_kind = ${len(a)}")
        if params.manager_member_id:
            a.append(params.manager_member_id); w.append(f"e.manager_member_id = ${len(a)}")
        a.append(params.limit)
        return await q(f"""SELECT m.member_id, m.display_name, m.email, m.member_kind, m.business_role, m.is_external, m.status AS member_status,
                                  m.job_title, m.primary_department, m.primary_department_id, e.employee_no, e.employment_type,
                                  e.status AS employment_status, e.start_date, e.end_date, e.manager_member_id, e.manager_name
                             FROM mcp_members m LEFT JOIN mcp_employment_profiles e ON e.member_id = m.member_id
                            WHERE {' AND '.join(w)} ORDER BY m.display_name LIMIT ${len(a)}""", *a)

    @mcp.tool(name="get_person", annotations={"title": "One person, whole", **RO})
    async def get_person(params: GetPersonIn) -> str:
        """Everything the caller may see about one person: the directory row, departments, the
        employment record, manager and reports, current pay (own, manager, HR staff only), this year's
        leave balances and requests, reviews. Call for "tell me about X", "what are my details". Each
        part is empty when the caller may not see it — an empty pay block is not "no pay"."""
        mid = params.member_id or db.request_member_id.get()
        parts = {}
        rows = await db.fetch_scoped(await _pool_from(q), "SELECT * FROM mcp_members WHERE member_id = $1", mid)
        if not rows:
            return json.dumps({"error": f"No person #{mid} that you can see; find_people looks them up by name."})
        parts["person"] = rows[0]
        parts["departments"] = await db.fetch_scoped(await _pool_from(q), "SELECT department_id, department_name, is_admin, is_primary, joined_at FROM mcp_department_members WHERE member_id = $1 AND left_at IS NULL ORDER BY is_primary DESC, department_name", mid)
        emp = await db.fetch_scoped(await _pool_from(q), "SELECT * FROM mcp_employment_profiles WHERE member_id = $1", mid)
        parts["employment"] = emp[0] if emp else None
        parts["reports"] = await db.fetch_scoped(await _pool_from(q), "SELECT member_id, display_name, job_title, status FROM mcp_employment_profiles WHERE manager_member_id = $1 ORDER BY display_name", mid)
        pay = await db.fetch_scoped(await _pool_from(q), "SELECT pay_type, pay_rate, currency, effective_date, pay_schedule, monthly_base_estimate FROM mcp_current_pay WHERE member_id = $1", mid)
        parts["current_pay"] = pay[0] if pay else None
        parts["leave_balances"] = await db.fetch_scoped(await _pool_from(q), "SELECT leave_type, year, accrued_days, carried_days, used_days, remaining_days, committed_days FROM mcp_leave_balances WHERE member_id = $1 AND year = EXTRACT(year FROM current_date) ORDER BY leave_type", mid)
        parts["leave_requests"] = await db.fetch_scoped(await _pool_from(q), "SELECT leave_request_id, leave_type, start_date, end_date, days, status, over_balance, has_overlap FROM mcp_leave_requests WHERE member_id = $1 ORDER BY start_date DESC LIMIT 12", mid)
        parts["reviews"] = await db.fetch_scoped(await _pool_from(q), "SELECT review_id, kind, due_date, status, rating, reviewer_name, shared_at FROM mcp_reviews WHERE member_id = $1 ORDER BY due_date DESC LIMIT 6", mid)
        return db.to_json(parts)

    @mcp.tool(name="starters_and_leavers", annotations={"title": "Who starts, leaves or ends probation", **RO})
    async def starters_and_leavers(params: StartersLeaversIn) -> str:
        """Who starts, leaves, or ends probation in a period, and who is still a candidate (not
        started). Call for "who is new this month", "anyone leaving in October", "whose probation ends soon"."""
        s, e = _period_bounds(params.period, params.date_from, params.date_to)
        out = {"period": {"from": s.isoformat(), "to": e.isoformat()}}
        cols = "member_id, display_name, job_title, primary_department, employment_type, status, start_date, probation_end_date, end_date, end_reason, manager_name"
        if params.kind in ("starting", "all"):
            out["starting"] = await db.fetch_scoped(await _pool_from(q), f"SELECT {cols} FROM mcp_employment_profiles WHERE start_date BETWEEN $1 AND $2 ORDER BY start_date", s, e)
        if params.kind in ("leaving", "all"):
            out["leaving"] = await db.fetch_scoped(await _pool_from(q), f"SELECT {cols} FROM mcp_employment_profiles WHERE end_date BETWEEN $1 AND $2 ORDER BY end_date", s, e)
        if params.kind in ("probation", "all"):
            out["probation_ending"] = await db.fetch_scoped(await _pool_from(q), f"SELECT {cols} FROM mcp_employment_profiles WHERE probation_end_date BETWEEN $1 AND $2 ORDER BY probation_end_date", s, e)
        if params.kind in ("candidates", "all"):
            out["candidates"] = await db.fetch_scoped(await _pool_from(q), f"SELECT {cols} FROM mcp_employment_profiles WHERE status = 'candidate' ORDER BY start_date NULLS LAST")
        return db.to_json(out)

    @mcp.tool(name="headcount", annotations={"title": "Headcount", **RO})
    async def headcount(params: HeadcountIn) -> str:
        """How many people are employed, grouped by department, employment type or status. Counts
        people with an employment record the caller may see (active and on leave, unless grouped by
        status). Not for agents (the kernel counts those)."""
        key = {"department": "COALESCE(primary_department, '(no department)')", "employment_type": "employment_type", "status": "status"}.get(params.group_by, "COALESCE(primary_department, '(no department)')")
        where = "" if params.group_by == "status" else "WHERE status IN ('active', 'on_leave')"
        return await q(f"SELECT {key} AS group_key, count(*) AS people FROM mcp_employment_profiles {where} GROUP BY 1 ORDER BY 2 DESC, 1")

    @mcp.tool(name="find_departments", annotations={"title": "Find departments", **RO})
    async def find_departments(params: FindDepartmentsIn) -> str:
        """The departments: name, manager, parent, people and agents in each, and whether the caller
        administers it. Call before an action that needs a department; pass the name."""
        w, a = ["1 = 1"], []
        if not params.include_archived:
            w.append("archived_at IS NULL")
        if params.q:
            a.append(f"%{params.q[:100]}%"); w.append(f"name ILIKE ${len(a)}")
        a.append(params.limit)
        return await q(f"SELECT department_id, name, description, parent_id, parent_name, manager_member_id, manager_name, is_system, system_key, archived_at, people, agents, i_administer FROM mcp_departments WHERE {' AND '.join(w)} ORDER BY name LIMIT ${len(a)}", *a)

    @mcp.tool(name="get_department", annotations={"title": "One department, whole", **RO})
    async def get_department(params: GetDepartmentIn) -> str:
        """One department: its manager and administrators, every member (people and agents),
        sub-departments, and who there is off today and in the next seven days (only people the
        caller may see)."""
        pool = await _pool_from(q)
        rows = await db.fetch_scoped(pool, "SELECT * FROM mcp_departments WHERE department_id = $1", params.department_id)
        if not rows:
            return json.dumps({"error": f"No department #{params.department_id}; find_departments looks them up by name."})
        out = {"department": rows[0]}
        out["members"] = await db.fetch_scoped(pool, "SELECT dm.member_id, dm.display_name, dm.member_kind, dm.is_admin, dm.is_primary, m.job_title, m.status FROM mcp_department_members dm JOIN mcp_members m ON m.member_id = dm.member_id WHERE dm.department_id = $1 AND dm.left_at IS NULL ORDER BY dm.is_admin DESC, dm.display_name", params.department_id)
        out["children"] = await db.fetch_scoped(pool, "SELECT department_id, name, manager_name, people, agents FROM mcp_departments WHERE parent_id = $1 AND archived_at IS NULL ORDER BY name", params.department_id)
        out["off_this_week"] = await db.fetch_scoped(pool, """SELECT lr.leave_request_id, lr.member_id, lr.display_name, lr.leave_type, lr.start_date, lr.end_date, lr.days, lr.status
                                                             FROM mcp_leave_requests lr JOIN mcp_department_members dm ON dm.member_id = lr.member_id AND dm.department_id = $1 AND dm.left_at IS NULL
                                                            WHERE lr.status = 'approved' AND lr.end_date >= current_date AND lr.start_date <= current_date + 7 ORDER BY lr.start_date""", params.department_id)
        return db.to_json(out)


async def _pool_from(q):
    """The records pool (q closes over it); modules that need several queries in one answer use this."""
    return await q.__globals__["_pool"]()
