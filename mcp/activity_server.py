"""hr_activity_mcp — HR's activity-memory server (activity_log through mcp_activity_log, read-only).

Tool surface: docs/hr-mcp-tool-surface.md, "Activity server". Every query runs as the asking member;
the view decides which rows they see (HR staff everything; others their own and their people's,
pay rows only where they may see pay).
"""
from __future__ import annotations

from mcp.server.fastmcp import FastMCP
from pydantic import Field

import db
from business_common import RO, _Base, _period_bounds

mcp = FastMCP("hr_activity_mcp")


async def _pool():
    return await db.get_pool(db.ENV["MCP_ACTIVITY_DB_USER"], db.ENV["MCP_ACTIVITY_DB_PASSWORD"])


async def _q(sql: str, *args) -> str:
    return db.to_json(await db.fetch_scoped(await _pool(), sql, *args))


_COLS = "activity_id, occurred_at, actor_member_id, actor_name, actor_kind, source, action, screen, entity_type, entity_id, subject_member_id, before, after, request_id, agent_run_id, kernel_request_id"


class RecordHistoryIn(_Base):
    entity_type: str = Field(..., description="member, employment_profile, compensation_change, pay_run, pay_run_item, leave_request, leave_balance, review, department, leave_type, holiday, hr_settings")
    entity_id: int = Field(..., ge=1)
    limit: int = Field(50, ge=1, le=100)


@mcp.tool(name="record_history", annotations={"title": "Record history", **RO})
async def record_history(params: RecordHistoryIn) -> str:
    """Everything that happened to one record, oldest first: who did what, when, from where, and
    what changed (before/after hold the changed fields only — never a pay figure). Call for "what
    happened to Priya's leave request", "who changed this employment record". A person's history as
    a whole: entity_type 'member'."""
    return await _q(f"SELECT {_COLS} FROM mcp_activity_log WHERE entity_type = $1 AND entity_id = $2 ORDER BY occurred_at LIMIT $3",
                    params.entity_type, params.entity_id, params.limit)


class ActorTimelineIn(_Base):
    member_id: int | None = Field(None, description="The person or agent; omit for the caller")
    period: str | None = Field("this_month", description="today, this_week, last_week, this_month, last_month, this_quarter, this_year, or YYYY-MM")
    date_from: str | None = None
    date_to: str | None = None
    action_prefix: str | None = Field(None, description="e.g. 'leave_request.' or 'person.'")
    limit: int = Field(100, ge=1, le=200)


@mcp.tool(name="actor_timeline", annotations={"title": "What someone did", **RO})
async def actor_timeline(params: ActorTimelineIn) -> str:
    """What one person or agent did in HR over a period, newest first. Call for "what did I do last
    week", "what has the leave desk agent done today". HR staff may ask about anyone; others see
    their own trail and their people's."""
    mid = params.member_id or db.request_member_id.get()
    s, e = _period_bounds(params.period, params.date_from, params.date_to)
    w = ["actor_member_id = $1", "occurred_at >= $2::date", "occurred_at < ($3::date + 1)"]
    a: list = [mid, s, e]
    if params.action_prefix:
        a.append(params.action_prefix + "%"); w.append(f"action LIKE ${len(a)}")
    a.append(params.limit)
    return await _q(f"SELECT {_COLS} FROM mcp_activity_log WHERE {' AND '.join(w)} ORDER BY occurred_at DESC LIMIT ${len(a)}", *a)


class WhoTouchedIn(_Base):
    member_id: int = Field(..., ge=1, description="The person whose records are asked about")
    period: str | None = Field("this_month", description="A period word or YYYY-MM")
    date_from: str | None = None
    date_to: str | None = None
    include_views: bool = Field(True, description="Include screen views of the person's record (the Pay tab counts as looking at pay)")


@mcp.tool(name="who_touched", annotations={"title": "Who viewed or changed a person's records", **RO})
async def who_touched(params: WhoTouchedIn) -> str:
    """Who looked at or changed a person's employment, pay, leave or review records in a period —
    the audit question (HR staff). Rows about pay are shown only to those who may see that person's pay."""
    s, e = _period_bounds(params.period, params.date_from, params.date_to)
    views = "" if params.include_views else " AND action <> 'screen.view'"
    return await _q(f"""SELECT {_COLS} FROM mcp_activity_log
                        WHERE (subject_member_id = $1 OR (entity_type = 'member' AND entity_id = $1))
                          AND occurred_at >= $2::date AND occurred_at < ($3::date + 1){views}
                        ORDER BY occurred_at DESC LIMIT 200""", params.member_id, s, e)


class DirectoryChangesIn(_Base):
    period: str | None = Field("this_month", description="A period word or YYYY-MM")
    date_from: str | None = None
    date_to: str | None = None
    department_id: int | None = Field(None, description="Only changes stamped with this department")


@mcp.tool(name="directory_changes", annotations={"title": "Directory changes made through HR", **RO})
async def directory_changes(params: DirectoryChangesIn) -> str:
    """The directory writes HR made through the kernel — invitations, name and title changes,
    memberships, departments — with the kernel's request id so the two trails join. Call for "what
    did we change in the directory this week", "who was invited".""" 
    s, e = _period_bounds(params.period, params.date_from, params.date_to)
    w = ["(action LIKE 'person.%' OR action LIKE 'membership.%' OR action LIKE 'department.%')", "occurred_at >= $1::date", "occurred_at < ($2::date + 1)"]
    a: list = [s, e]
    if params.department_id:
        a.append(params.department_id); w.append(f"department_id = ${len(a)}")
    return await _q(f"SELECT {_COLS} FROM mcp_activity_log WHERE {' AND '.join(w)} ORDER BY occurred_at DESC LIMIT 200", *a)


class SignOnsIn(_Base):
    member_id: int | None = None
    period: str | None = Field("this_week", description="A period word or YYYY-MM")
    date_from: str | None = None
    date_to: str | None = None
    refused_only: bool = False


@mcp.tool(name="sign_ons", annotations={"title": "Sign-ons and refusals", **RO})
async def sign_ons(params: SignOnsIn) -> str:
    """Who signed in to HR and when, and which sign-on links were refused (expired, replayed, the
    wrong application). Call for "has Priya used HR", "any refused sign-ons today"."""
    s, e = _period_bounds(params.period, params.date_from, params.date_to)
    w = ["action IN ('member.sign_on', 'member.sign_on.refused', 'member.sign_out', 'member.refused')", "occurred_at >= $1::date", "occurred_at < ($2::date + 1)"]
    a: list = [s, e]
    if params.member_id:
        a.append(params.member_id); w.append(f"(actor_member_id = ${len(a)} OR (entity_type = 'member' AND entity_id = ${len(a)}))")
    if params.refused_only:
        w.append("action LIKE '%.refused'")
    return await _q(f"SELECT {_COLS} FROM mcp_activity_log WHERE {' AND '.join(w)} ORDER BY occurred_at DESC LIMIT 200", *a)


class AgentActivityIn(_Base):
    agent_member_id: int | None = None
    run_id: int | None = Field(None, description="One kernel run")
    period: str | None = Field("this_week", description="A period word or YYYY-MM")
    date_from: str | None = None
    date_to: str | None = None
    paused_only: bool = Field(False, description="Only actions that paused for approval")


@mcp.tool(name="agent_activity", annotations={"title": "What agents did in HR", **RO})
async def agent_activity(params: AgentActivityIn) -> str:
    """What the kernel's agents did in HR, by run: every action taken under a run token (source
    'agent'), with the run id and the run's request id that joins to the kernel's prompt ledger."""
    s, e = _period_bounds(params.period, params.date_from, params.date_to)
    w = ["source = 'agent'", "occurred_at >= $1::date", "occurred_at < ($2::date + 1)"]
    a: list = [s, e]
    if params.agent_member_id:
        a.append(params.agent_member_id); w.append(f"actor_member_id = ${len(a)}")
    if params.run_id:
        a.append(params.run_id); w.append(f"agent_run_id = ${len(a)}")
    if params.paused_only:
        w.append("action LIKE '%.paused'")
    return await _q(f"SELECT {_COLS} FROM mcp_activity_log WHERE {' AND '.join(w)} ORDER BY occurred_at DESC LIMIT 200", *a)


class SystemJobsIn(_Base):
    job: str | None = Field(None, description="'directory.sync', 'leave_balance.grant_year', 'cron.run' or omit for all")
    period: str | None = Field("this_month", description="A period word or YYYY-MM")
    date_from: str | None = None
    date_to: str | None = None


@mcp.tool(name="system_jobs", annotations={"title": "The jobs: directory sync, the year's leave grant", **RO})
async def system_jobs(params: SystemJobsIn) -> str:
    """When the directory mirror last synced and what it applied, when the year's leave was granted
    and to how many, and any failures. Call for "is the directory up to date", "did the January grant run"."""
    s, e = _period_bounds(params.period, params.date_from, params.date_to)
    w = ["(action LIKE 'directory.sync%' OR action LIKE 'leave_balance.grant_year%' OR action LIKE 'cron.%')", "occurred_at >= $1::date", "occurred_at < ($2::date + 1)"]
    a: list = [s, e]
    if params.job:
        a.append(params.job + "%"); w.append(f"action LIKE ${len(a)}")
    return await _q(f"SELECT {_COLS} FROM mcp_activity_log WHERE {' AND '.join(w)} ORDER BY occurred_at DESC LIMIT 200", *a)


class SearchIn(_Base):
    sql: str = Field(..., description="A single read-only SELECT over mcp_activity_log (and mcp_members, mcp_departments for names).")


@mcp.tool(name="activity_search", annotations={"title": "Activity search (SQL)", **RO})
async def activity_search(params: SearchIn) -> str:
    """The long tail: one read-only SELECT over mcp_activity_log (activity_id, occurred_at,
    actor_member_id, actor_name, actor_kind, source, action, screen, route, entity_type, entity_id,
    subject_member_id, before, after, request_id, agent_run_id, department_id, kernel_request_id) —
    joined to mcp_members (member_id, display_name, …) or mcp_departments (department_id, name, …)
    for names. Already scoped to what you may see. One statement, 5 s timeout, 200 rows."""
    return await db.run_search(await _pool(), params.sql)


if __name__ == "__main__":
    import server_common
    server_common.run(mcp, "MCP_ACTIVITY_DB_USER", "MCP_ACTIVITY_DB_PASSWORD", port=int(db.ENV["MCP_ACTIVITY_PORT"]), endpoint_name="Activity MCP")
