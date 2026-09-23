"""hr_records_mcp — HR's record-memory server (PostgreSQL, the mcp_* views, read-only).

Tool surface: docs/hr-mcp-tool-surface.md. Every tool reads the views as the asking member, so a
person sees what they see on the screens; an agent sees employment, departments and leave and
never pay or reviews (the views), and only the tools the kernel granted it (server_common).
"""
from __future__ import annotations

from mcp.server.fastmcp import FastMCP
from pydantic import Field

import db
from business_common import RO, _Base

mcp = FastMCP("hr_records_mcp")


async def _pool():
    return await db.get_pool(db.ENV["MCP_RECORDS_DB_USER"], db.ENV["MCP_RECORDS_DB_PASSWORD"])


async def _q(sql: str, *args) -> str:
    return db.to_json(await db.fetch_scoped(await _pool(), sql, *args))


class SearchIn(_Base):
    sql: str = Field(..., description="A single read-only SELECT over the mcp_* views (see below).")


@mcp.tool(name="records_search", annotations={"title": "Records search (SQL)", **RO})
async def records_search(params: SearchIn) -> str:
    """The long tail: one read-only SELECT over HR's views — the directory (mcp_members: member_id,
    display_name, email, member_kind, business_role, is_external, status, job_title, phone, timezone,
    primary_department, has_employment, employment_status; mcp_departments; mcp_department_members),
    employment (mcp_employment_profiles: member_id, display_name, employee_no, employment_type, status,
    manager_member_id, manager_name, work_location, start_date, probation_end_date, end_date,
    weekly_hours, pay_schedule, primary_department), pay (mcp_current_pay, mcp_compensation_changes,
    mcp_pay_runs, mcp_pay_run_items, mcp_pay_run_lines — those who may see pay only), leave
    (mcp_leave_types, mcp_leave_balances, mcp_leave_requests, mcp_holidays), mcp_reviews and
    mcp_hr_settings. Already scoped to what you may see. One statement, 5 s timeout, 200 rows."""
    return await db.run_search(await _pool(), params.sql)


import hr_people
import hr_pay
import hr_leave
import hr_reviews

hr_people.register(mcp, _q)
hr_pay.register(mcp, _q)
hr_leave.register(mcp, _q)
hr_reviews.register(mcp, _q)


if __name__ == "__main__":
    import server_common
    server_common.run(mcp, "MCP_RECORDS_DB_USER", "MCP_RECORDS_DB_PASSWORD", port=int(db.ENV["MCP_RECORDS_PORT"]), endpoint_name="Records MCP")
