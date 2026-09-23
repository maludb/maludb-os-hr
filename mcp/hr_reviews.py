"""Reviews and settings — read tools (docs/hr-mcp-tool-surface.md). Reads mcp_reviews,
mcp_employment_profiles, mcp_hr_settings, mcp_holidays. Reviews are never shown to agents (the view).
"""
from __future__ import annotations

import json

from pydantic import Field

import db
from business_common import RO, _Base


class FindReviewsIn(_Base):
    member_id: int | None = None
    reviewer_member_id: int | None = None
    status: str | None = Field(None, description="scheduled, in_progress, completed or cancelled")
    due_before: str | None = Field(None, description="ISO date: only reviews due before it")
    overdue_only: bool = False
    none_since_months: int | None = Field(None, ge=1, le=60, description="Instead: the employed people with NO completed review in this many months")
    limit: int = Field(25, ge=1, le=100)


class GetReviewIn(_Base):
    review_id: int


class HrSettingsIn(_Base):
    year: int | None = Field(None, description="Holidays of this year; omit for this year")


def register(mcp, q) -> None:
    @mcp.tool(name="find_reviews", annotations={"title": "Find reviews", **RO})
    async def find_reviews(params: FindReviewsIn) -> str:
        """Reviews due, overdue, in progress or completed, by person or reviewer — or, with
        none_since_months, who has had no completed review in that long. HR staff, reviewers and
        managers; a person sees their own once shared. Not shown to agents."""
        if params.none_since_months:
            a = [params.none_since_months, params.limit]
            return await q("""SELECT e.member_id, e.display_name, e.primary_department, e.start_date, e.manager_name,
                                     (SELECT max(r.completed_at) FROM mcp_reviews r WHERE r.member_id = e.member_id AND r.status = 'completed') AS last_completed
                                FROM mcp_employment_profiles e
                               WHERE e.status IN ('active', 'on_leave')
                                 AND NOT EXISTS (SELECT 1 FROM mcp_reviews r WHERE r.member_id = e.member_id AND r.status = 'completed' AND r.completed_at > now() - make_interval(months => $1))
                               ORDER BY e.display_name LIMIT $2""", *a)
        w, a = ["1 = 1"], []
        if params.member_id:
            a.append(params.member_id); w.append(f"member_id = ${len(a)}")
        if params.reviewer_member_id:
            a.append(params.reviewer_member_id); w.append(f"reviewer_member_id = ${len(a)}")
        if params.status in ("scheduled", "in_progress", "completed", "cancelled"):
            a.append(params.status); w.append(f"status = ${len(a)}")
        if params.due_before:
            a.append(params.due_before); w.append(f"due_date < ${len(a)}::date")
        if params.overdue_only:
            w.append("status IN ('scheduled', 'in_progress') AND due_date < current_date")
        a.append(params.limit)
        return await q(f"SELECT review_id, member_id, display_name, reviewer_member_id, reviewer_name, kind, period_start, period_end, due_date, status, rating, shared_at, completed_at FROM mcp_reviews WHERE {' AND '.join(w)} ORDER BY (status IN ('scheduled', 'in_progress')) DESC, due_date LIMIT ${len(a)}", *a)

    @mcp.tool(name="get_review", annotations={"title": "One review", **RO})
    async def get_review(params: GetReviewIn) -> str:
        """One review in full: rating, summary, strengths, improvements, goals, the person's comment,
        and its dates. Only for those who may see it (the reviewer, HR staff, the manager; the person
        once shared)."""
        rows = await db.fetch_scoped(await q.__globals__["_pool"](), "SELECT * FROM mcp_reviews WHERE review_id = $1", params.review_id)
        if not rows:
            return json.dumps({"error": "No such review that you may see."})
        return db.to_json(rows[0])

    @mcp.tool(name="hr_settings", annotations={"title": "HR settings and holidays", **RO})
    async def hr_settings(params: HrSettingsIn) -> str:
        """The business's currency, time zone, work days (ISO weekdays, 1 = Monday) and the holidays of
        a year — what leave days are counted on."""
        pool = await q.__globals__["_pool"]()
        s = await db.fetch_scoped(pool, "SELECT currency, timezone, work_days, leave_year_start_month FROM mcp_hr_settings")
        year = params.year or __import__("datetime").date.today().year
        hol = await db.fetch_scoped(pool, "SELECT holiday_date, name FROM mcp_holidays WHERE EXTRACT(year FROM holiday_date) = $1 ORDER BY holiday_date", year)
        return db.to_json({"settings": s[0] if s else None, "holidays": hol, "year": year})
