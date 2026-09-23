# HR — MCP tool surface

2026-09-23 · the read tools every question in `docs/hr-design.md` §4 is answered by. Writes are never
here: they are the action tools the kernel's Actions MCP builds from `docs/hr-action-manifest.md`.

Conventions (mcp-and-api.md): Python 3 + FastMCP, streamable HTTP at `/mcp`, loopback, one systemd unit
each; `db.py` and `server_common.py` copied from the kernel; every query runs with `app.member_id` set
transaction-locally from the verified bearer, so the `mcp_*` views decide the rows. Tool names are
snake_case; every list tool takes `limit` (1–100, default 25); every tool carries `readOnlyHint: true`,
`openWorldHint: false`; results are JSON strings. Gates below are what the view already enforces — the
tool only trims.

Two token shapes, one key: a person's own `mcp_` token (`mcp_resolve_token`), or the tenant's signed
run token (`ACTION_TOKEN_KEY`), for which the server asks the kernel's run-facts call once per run and
offers exactly the tools granted — fail closed, and no tool acts when `is_eval`.

## Records server — `hr_records_mcp` (role `hr_records_ro`, `MCP_RECORDS_PORT`)

### Directory and employment
| Tool | Call it when | Answers | Reads | Key params | Gate |
|---|---|---|---|---|---|
| `find_people` | Looking up anyone by name, email or employee number; listing who is in a department, employed or not, by type or status; who reports to X. **Call before any action that needs a person** | HR1, HR2, HR4, HR9 | `mcp_members`, `mcp_employment_profiles`, `mcp_department_members` | `q?`, `department_id?`, `employed?` (true/false/any), `employment_type?`, `employment_status?`, `member_kind?`, `manager_member_id?`, `include_inactive?` | all |
| `get_person` | Everything about one person the caller may see: directory row, departments, employment, manager and reports, current pay, this year's leave balances and requests, reviews | HR3, HR4, HR10, HR16, HR18, HR23 | `mcp_members`, `mcp_department_members`, `mcp_employment_profiles`, `mcp_current_pay`, `mcp_leave_balances`, `mcp_leave_requests`, `mcp_reviews` | `member_id?` (default: the caller) | all (each part by its own view) |
| `starters_and_leavers` | Who starts, leaves, ends probation or is still a candidate in a period | HR5 | `mcp_employment_profiles` | `period` (this_month / next_month / YYYY-MM / from,to), `kind?` (starting/leaving/probation/candidates/all) | hr, admin |
| `headcount` | How many people, grouped | HR6 | `mcp_employment_profiles`, `mcp_department_members` | `group_by` (department/employment_type/status), `as_of?` | hr, admin |
| `find_departments` | The department tree, who manages each, headcounts. **Call before an action that needs a department** | HR7 | `mcp_departments` | `q?`, `include_archived?` | all |
| `get_department` | One department: manager, admins, members (human and agent), sub-departments, who is off today and this week | HR8 | `mcp_departments`, `mcp_department_members`, `mcp_leave_requests` | `department_id` | all (who is off: only rows the caller may see) |

### Pay
| Tool | Call it when | Answers | Reads | Key params | Gate |
|---|---|---|---|---|---|
| `pay_history` | What someone is paid now and every change, with who recorded it | HR10 | `mcp_current_pay`, `mcp_compensation_changes` | `member_id?` (default: the caller) | own, manager, hr |
| `find_pay_runs` | Pay runs by period or status; the unapproved and the unpaid | HR11, HR12 | `mcp_pay_runs` | `status?`, `period?`, `unpaid_only?` | hr |
| `get_pay_run` | One run with every item and line, and each note explaining a missing base line | HR13 | `mcp_pay_runs`, `mcp_pay_run_items`, `mcp_pay_run_lines` | `pay_run_id` or `run_no` | hr (a person: their own item on an approved run) |
| `my_payslip` | The caller's item on the newest approved or paid run, with its lines | HR15 | `mcp_pay_run_items`, `mcp_pay_run_lines` | `run_no?` | own |
| `payroll_summary` | What people cost over a period, grouped, including employer costs; only approved and paid runs | HR14 | `mcp_pay_runs`, `mcp_pay_run_items` | `period`, `group_by?` (department/member/month/run), `include_drafts?` (default false) | hr |

### Leave
| Tool | Call it when | Answers | Reads | Key params | Gate |
|---|---|---|---|---|---|
| `leave_balance` | Days left per type for a person or for everyone visible; low or unused balances | HR16, HR21 | `mcp_leave_balances` | `member_id?`, `year?`, `department_id?`, `below_days?`, `above_unused_days?` | own, manager, admin, hr |
| `find_leave_requests` | Requests by person, department, status or dates; what awaits the caller's decision; over-balance or overlapping ones | HR17, HR18 | `mcp_leave_requests` | `awaiting_me?`, `member_id?`, `department_id?`, `status?`, `period?`, `flagged_only?` | all (rows the caller may see) |
| `whos_off` | Who is off between two dates, by day, in a department | HR19 | `mcp_leave_requests`, `mcp_holidays` | `from`, `to`, `department_id?`, `include_requested?` (default false) | admin, manager, hr |
| `find_leave_types` | The leave types, allowances and approval rules, and the holidays | HR20 | `mcp_leave_types`, `mcp_holidays` | `include_archived?` | all |

### Reviews and settings
| Tool | Call it when | Answers | Reads | Key params | Gate |
|---|---|---|---|---|---|
| `find_reviews` | Reviews due, overdue, in progress or completed; by person or reviewer; who has had none in twelve months | HR22, HR24 | `mcp_reviews`, `mcp_employment_profiles` | `member_id?`, `reviewer_member_id?`, `status?`, `due_before?`, `overdue_only?`, `none_since_months?` | hr, reviewer, manager (own: shared only) |
| `get_review` | One review in full | HR23 | `mcp_reviews` | `review_id` | as the view |
| `hr_settings` | The currency, timezone, work days and holidays | HR25 | `mcp_hr_settings`, `mcp_holidays` | `year?` | all |
| `records_search` | The long tail: one SELECT over the views named in its docstring (`mcp_members`, `mcp_departments`, `mcp_department_members`, `mcp_employment_profiles`, `mcp_compensation_changes`, `mcp_current_pay`, `mcp_pay_runs`, `mcp_pay_run_items`, `mcp_pay_run_lines`, `mcp_leave_types`, `mcp_leave_balances`, `mcp_leave_requests`, `mcp_holidays`, `mcp_hr_settings`, `mcp_reviews`) | any | all views | `sql` | as the views; 5 s timeout; 200 rows |

## Activity server — `hr_activity_mcp` (role `hr_activity_ro`, `MCP_ACTIVITY_PORT`)

| Tool | Call it when | Answers | Reads | Key params | Gate |
|---|---|---|---|---|---|
| `record_history` | Everything that happened to one record, oldest first | HA1 | `mcp_activity_log` | `entity_type`, `entity_id`, `limit?` | as the view |
| `actor_timeline` | What one person or agent did in HR over a period | HA2 | `mcp_activity_log` | `member_id?` (default: the caller), `period?`, `action_prefix?` | own, hr |
| `who_touched` | Who viewed or changed a person's employment or pay records | HA3 | `mcp_activity_log` | `member_id`, `period?`, `include_views?` (default true) | hr |
| `directory_changes` | Directory writes made through HR — invites, directory edits, memberships, departments — with the kernel's request ids | HA4 | `mcp_activity_log` (actions `person.invite`, `person.directory_update`, `membership.*`, `department.*`) | `period?`, `department_id?` | hr, admin |
| `sign_ons` | Sign-ons and refused sign-on links | HA5 | `mcp_activity_log` (`member.sign_on`, `member.sign_on.refused`, `member.sign_out`) | `member_id?`, `period?`, `refused_only?` | hr |
| `agent_activity` | What agents did in HR, by run, and what paused for approval | HA6 | `mcp_activity_log` (`source = 'agent'`) | `agent_member_id?`, `run_id?`, `period?`, `paused_only?` | hr |
| `system_jobs` | The year's leave grant, the directory sync, the ingest — when they last ran and what they did | HA7 | `mcp_activity_log` (`cron.*`, `directory.sync`, `leave_balance.grant_year`) | `job?`, `period?` | hr |
| `activity_search` | The long tail: one SELECT over `mcp_activity_log` (and `mcp_members`, `mcp_departments` for names) | any | those views | `sql` | as the view; 5 s; 200 rows |

## Question → tool map

HR1 `find_people` · HR2 `find_people` · HR3 `get_person` · HR4 `find_people` + `get_person` · HR5 `starters_and_leavers` ·
HR6 `headcount` · HR7 `find_departments` · HR8 `get_department` · HR9 `find_people` + `sign_ons` · HR10 `pay_history` + `record_history` ·
HR11 `find_pay_runs` · HR12 `find_pay_runs` · HR13 `get_pay_run` · HR14 `payroll_summary` · HR15 `my_payslip` · HR16 `leave_balance` ·
HR17 `find_leave_requests` · HR18 `find_leave_requests` · HR19 `whos_off` · HR20 `find_leave_types` · HR21 `leave_balance` ·
HR22 `find_reviews` · HR23 `get_review` · HR24 `find_reviews` · HR25 `hr_settings` · HA1 `record_history` · HA2 `actor_timeline` ·
HA3 `who_touched` · HA4 `directory_changes` · HA5 `sign_ons` · HA6 `agent_activity` · HA7 `system_jobs`.

## Entity resolution for the action tools (the registry's `resolve` block)

| Entity param | Tool | query param | id field | label field |
|---|---|---|---|---|
| `person`, `member`, `manager`, `reviewer` | `find_people` | `q` | `member_id` | `display_name` |
| `department`, `parent` | `find_departments` | `q` | `department_id` | `name` |
| `pay_run` | `find_pay_runs` | `q` (matches `run_no`) | `pay_run_id` | `run_no` |
| `pay_run_item` | `get_pay_run` items | — (an id; the screen supplies it) | `pay_run_item_id` | `display_name` |
| `leave_request` | `find_leave_requests` | `q` (person name) | `leave_request_id` | `display_name` + dates |
| `leave_type` | `find_leave_types` | `q` | `leave_type_id` | `name` |
| `review` | `find_reviews` | `q` (person name) | `review_id` | `display_name` + kind |
| `holiday` | `hr_settings` holidays | `q` | `holiday_id` | `name` |
| `compensation_change` | `pay_history` | — (an id from the person's page) | `compensation_change_id` | `effective_date` |
