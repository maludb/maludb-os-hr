# HR Action Manifest

2026-09-23 · Edward Honour · **Phase 1, for the checkpoint**

> The registry the command bar (through the kernel's chat endpoint), the kernel's Actions MCP and the
> screens resolve against. A screen or action missing here cannot be reached by voice or by an agent —
> unfinished design, like a question with no tool. Pairs with `db/` (tables) and
> `docs/hr-mcp-tool-surface.md` (read tools). Generated into `mcp/action_registry.json` by
> `bin/build_action_registry.php`; an action whose file does not exist yet is registered but not exposed.

## Conventions

### Screens
- **Ids** are kebab-case `{entity}-list`, `{entity}-add`, `{entity}-view`, `{entity}-edit`, plus named screens.
- **Canonical URLs** need the rewrites in `deploy/apache-hr.conf`: `/{x}/new` → `form.php`, `/{x}/{id}/edit` → `form.php?id=`, `/{x}/{id}` → `view.php?id=`.
- **Prefill params** become query-string values the GET controller reads.
- **Every screen partial stamps** `data-screen`, `data-entity`, `data-record-id` on `#page-content`, so "that", "this request" and "add a change here" resolve against the page the user is on.

### Actions
- **Name** `{entity}_{verb}`; the **log event** `{entity}.{verb}` is what the handler writes with `log_activity()` and what the kernel's approval policies match. `.delete` destroys a record; `.remove` detaches something.
- **Endpoints** are POST to `/{base}/{file}` with `require_post()`, `verify_csrf()` (or the relayed action token), the gate, and `log_activity()`. Handlers report through `emit_action_status()`; a create's `location` ends in the new record's id.
- **Params:** **bold** = required; `[]` = repeated; parentheses = a hint (no comma or semicolon inside; no note after the last parameter). A param named for a record (`person`, `department`, `pay_run`, `leave_request`, `review`) accepts an id or a name, resolved through HR's own `find_*` tool (`docs/hr-mcp-tool-surface.md`, resolution table). `any field of X` = X's fields, all optional, a partial update.
- **Who** is enforced in the endpoint: `all` = anyone admitted to HR; `own` = the record's person; `manager` = the person's manager on their employment record; `admin` = a dept-admin or named manager of a department the person is in (for department actions: of that department); `hr` = HR staff (capability `admin`) or the super-admin; `super` = the super-admin only. Directory writes are additionally judged by the kernel as the acting person.
- **Undo** is the inverse `undo_last` applies; — means it cannot be undone and the reply says so.
- **Confirm (✔)** = the command bar asks first (destructive, or something leaves the building).
- **Agent approval** names the kernel's category that pauses the action when an **agent** performs it: `other`, `deletion`, `external_send`. People are never paused.
- **Refresh:** a data action returns `HX-Trigger: {entity}Changed` (camelCase entity).

### Result contract
```json
{"status": "success", "did": "Recorded 5 days of Paid time off for Wes, 5–9 Oct", "record_id": 12, "undo_id": "act_31", "refresh": "leaveRequestChanged"}
{"status": "pending_approval", "did": "Approving Wes's leave waits for Mia", "approval_request_id": 17}
{"status": "error", "message": "Found 2 people named Chris: Chris Adams (Kitchen), Chris Bell (Bar). Which one?"}
```

## Home

Screens:

| Screen id | URL | When the user wants… |
| --- | --- | --- |
| `dashboard` | `/` | their HR home: leave left this year, requests waiting for them, their next review, and for HR staff who starts and leaves this month, unapproved runs and overdue reviews |
| `activity` | `/activity` | recent activity in HR, or one record's history (params: `entity_type`, `entity_id`) |
| `settings` | `/settings/` | the business's HR settings: currency, timezone, work days |
| `tokens` | `/settings/tokens/` | their own access tokens for the two MCP servers and the API |

Actions (base `/settings/`):

| Action | File | Params | Undo | Confirm | Agent approval | Log | Who |
| --- | --- | --- | --- | --- | --- | --- | --- |
| `settings_save` | `save.php` | currency, timezone, work_days (ISO weekdays 1 to 7 comma-separated), employee_no_prefix | restore prior | | | `settings.save` | hr |
| `token_mint` | `tokens/mint.php` | **label**, scope (mcp or api) | revoke it | | | `token.mint` | all (own) |
| `token_revoke` | `tokens/revoke.php` | **token** | — | ✔ | | `token.revoke` | own |

## People

Screens:

| Screen id | URL | When the user wants… |
| --- | --- | --- |
| `people-list` | `/people/` | who works here: the directory with employment beside it (params: `q`, `department`, `status`, `type`, `employed`) |
| `person-view` | `/people/{id}` | one person: directory, employment, pay, leave, reviews, history (params: `tab`) |
| `person-invite` | `/people/new` | to invite someone into the business: the kernel sends the invitation (params: `email`, `department`, `business_role`) |
| `person-directory-edit` | `/people/directory/{id}/edit` | to change a person's directory facts: name, job title, phone, timezone, external, status, role |
| `employment-edit` | `/people/employment/{id}/edit` | to create or change a person's employment record |
| `compensation-add` | `/people/compensation/new` | to record a pay change for someone (params: `person`, `effective_date`) |

Actions (base `/people/`):

| Action | File | Params | Undo | Confirm | Agent approval | Log | Who |
| --- | --- | --- | --- | --- | --- | --- | --- |
| `person_invite` | `invite.php` | **email**, **business_role** (user or dept_admin), **department**, message | — (the kernel sent the invitation) | ✔ | external_send | `person.invite` | hr, admin |
| `person_directory_update` | `directory/save.php` | **person**, display_name, job_title, phone, timezone, is_external, status (active or suspended), business_role (user or dept_admin) | restore prior | | other | `person.directory_update` | hr, admin |
| `membership_save` | `membership-save.php` | **person**, **department**, is_admin, is_primary | remove it again | | other | `membership.save` | hr, admin |
| `membership_remove` | `membership-remove.php` | **person**, **department** | add back | ✔ | other | `membership.remove` | hr, admin |
| `employment_save` | `employment/save.php` | **person**, employment_type (employee or contractor or intern), employee_no, manager, work_location, start_date, probation_end_date, status (candidate or active or on_leave), weekly_hours, pay_schedule (weekly or biweekly or semimonthly or monthly), personal_email, address, emergency_contact, birth_date, national_id_last4, notes | restore prior | | | `employment_profile.save` | hr |
| `employment_end` | `employment/end.php` | **person**, **end_date**, reason (keeps the record and ends the employment) | restore prior | ✔ | other | `employment_profile.end` | hr |
| `compensation_change_save` | `compensation/save.php` | **person**, **effective_date**, **pay_type** (salary or hourly or daily or per_invoice — a salary is per year), **pay_rate**, currency, reason | delete the change | ✔ | other | `compensation_change.create` | hr |
| `compensation_change_delete` | `compensation/delete.php` | **compensation_change** (a future-dated change only) | — | ✔ | deletion | `compensation_change.delete` | hr |

## Departments

Screens:

| Screen id | URL | When the user wants… |
| --- | --- | --- |
| `departments-list` | `/departments/` | the departments, who manages each and how many are in each (params: `q`, `archived`) |
| `department-view` | `/departments/{id}` | one department: its people and agents, admins, sub-departments, who is off |
| `department-add` | `/departments/new` | to create a department in the kernel (params: `name`, `parent`) |
| `department-edit` | `/departments/{id}/edit` | to rename a department, re-parent it, name its manager or describe it |

Actions (base `/departments/`):

| Action | File | Params | Undo | Confirm | Agent approval | Log | Who |
| --- | --- | --- | --- | --- | --- | --- | --- |
| `department_create` | `save.php` | **name**, description, parent, manager | — (archive it in the kernel) | | other | `department.create` | super, hr |
| `department_update` | `save.php` | **department**, any field of department_create | restore prior | | other | `department.update` | hr, admin |

## Pay runs

Screens:

| Screen id | URL | When the user wants… |
| --- | --- | --- |
| `pay-runs` | `/payruns/` | the pay runs: drafts, approved, paid, void (params: `status`, `period`) |
| `pay-run-add` | `/payruns/new` | to start a pay run for a period (params: `period_start`, `period_end`, `pay_date`) |
| `pay-run-view` | `/payruns/{id}` | one run: every person, their lines, the totals, what is missing a base line |
| `pay-run-item-edit` | `/payruns/items/{id}/edit` | to key one person's hours and lines on a draft run |
| `payslip` | `/payruns/mine` | their own payslips (params: `run`) |

Actions (base `/payruns/`):

| Action | File | Params | Undo | Confirm | Agent approval | Log | Who |
| --- | --- | --- | --- | --- | --- | --- | --- |
| `pay_run_create` | `save.php` | **period_start**, **period_end**, **pay_date**, currency, people[] (defaults to everyone active with a pay basis in that currency), note | delete the draft | | | `pay_run.create` | hr |
| `pay_run_item_save` | `items/save.php` | **pay_run_item**, hours, days, lines (kind:code:amount:description each — kinds are earning deduction employer_cost reimbursement — comma-separated and replacing the keyed lines), note | restore prior | | | `pay_run_item.save` | hr |
| `pay_run_add_person` | `items/add.php` | **pay_run**, **person** | remove again | | | `pay_run_item.add` | hr |
| `pay_run_remove_person` | `items/remove.php` | **pay_run_item** | add back | ✔ | | `pay_run_item.remove` | hr |
| `pay_run_approve` | `approve.php` | **pay_run** | un-approve while unpaid | ✔ | other | `pay_run.approve` | hr |
| `pay_run_unapprove` | `unapprove.php` | **pay_run** (approved and unpaid) | approve again | | | `pay_run.unapprove` | hr |
| `pay_run_mark_paid` | `paid.php` | **pay_run**, paid_at (no money moves — it records that the business paid) | — | ✔ | other | `pay_run.pay` | hr |
| `pay_run_void` | `void.php` | **pay_run** (unpaid only), **reason** | — | ✔ | other | `pay_run.void` | hr |
| `pay_run_delete` | `delete.php` | **pay_run** (a draft only) | — | ✔ | deletion | `pay_run.delete` | hr |

## Leave

Screens:

| Screen id | URL | When the user wants… |
| --- | --- | --- |
| `leave-requests` | `/leave/` | leave requests: theirs, their people's, what awaits them (params: `status`, `person`, `department`, `period`, `awaiting_me`) |
| `leave-request-add` | `/leave/new` | to request leave, or record it for someone (params: `type`, `start`, `end`, `person`) |
| `leave-request-view` | `/leave/{id}` | one request: the days, the balance it draws on, its flags, its decision |
| `leave-calendar` | `/leave/calendar` | who is off, by day (params: `from`, `to`, `department`) |
| `leave-balances` | `/leave/balances/` | balances per person and type for a year (params: `year`, `department`, `person`) |
| `leave-balance-adjust` | `/leave/balances/new` | to adjust someone's balance or carry days over (params: `person`, `leave_type`, `year`) |

Actions (base `/leave/`):

| Action | File | Params | Undo | Confirm | Agent approval | Log | Who |
| --- | --- | --- | --- | --- | --- | --- | --- |
| `leave_request_create` | `save.php` | **leave_type**, **start_date**, **end_date**, half_day, reason, person (defaults to the caller) | cancel it | | | `leave_request.create` | all (own), manager, admin, hr |
| `leave_request_decide` | `decide.php` | **leave_request**, **decision** (approve or reject), note, override (approve beyond the balance — HR only) | restore prior | | other | `leave_request.decide` | manager, admin, hr — never the requester |
| `leave_request_cancel` | `cancel.php` | **leave_request** (requested or approved and not yet started) | request again | ✔ | | `leave_request.cancel` | own, hr |
| `leave_balance_adjust` | `balances/save.php` | **person**, **leave_type**, **year**, accrued_days, carried_days, **reason** | restore prior | ✔ | other | `leave_balance.adjust` | hr |
| `leave_grant_year` | `balances/grant-year.php` | **year** (grants the allowance to everyone active who has none for it yet) | — | ✔ | other | `leave_balance.grant_year` | hr |

## Reviews

Screens:

| Screen id | URL | When the user wants… |
| --- | --- | --- |
| `reviews-list` | `/reviews/` | reviews due, in progress or done — theirs to write, or their own (params: `status`, `person`, `reviewer`, `overdue`) |
| `review-add` | `/reviews/new` | to schedule a review for someone (params: `person`, `kind`, `due_date`) |
| `review-view` | `/reviews/{id}` | one review, and to comment on their own |
| `review-edit` | `/reviews/{id}/edit` | to write a review: rating, summary, strengths, improvements, goals |

Actions (base `/reviews/`):

| Action | File | Params | Undo | Confirm | Agent approval | Log | Who |
| --- | --- | --- | --- | --- | --- | --- | --- |
| `review_schedule` | `save.php` | **person**, **kind** (annual or probation or quarterly or other), **due_date**, reviewer (defaults to the person's manager), period_start, period_end | cancel it | | | `review.schedule` | hr, manager |
| `review_save` | `save.php` | **review**, rating (1 to 5), summary, strengths, improvements, goals | restore prior | | | `review.save` | reviewer, hr |
| `review_complete` | `complete.php` | **review**, share (also share it with the person) | reopen it | ✔ | other | `review.complete` | reviewer, hr |
| `review_comment` | `comment.php` | **review**, **comment** | restore prior | | | `review.comment` | own (once shared) |
| `review_cancel` | `cancel.php` | **review**, reason | schedule again | ✔ | | `review.cancel` | hr, reviewer |

## Settings — leave types and holidays

Screens:

| Screen id | URL | When the user wants… |
| --- | --- | --- |
| `leave-types` | `/settings/leave-types/` | the leave types, allowances and approval rules |
| `leave-type-add` | `/settings/leave-types/new` | to add a leave type |
| `leave-type-edit` | `/settings/leave-types/{id}/edit` | to change or archive a leave type |
| `holidays` | `/settings/holidays/` | the public holidays (params: `year`) |
| `holiday-add` | `/settings/holidays/new` | to add a holiday (params: `date`, `name`) |

Actions (base `/settings/`):

| Action | File | Params | Undo | Confirm | Agent approval | Log | Who |
| --- | --- | --- | --- | --- | --- | --- | --- |
| `leave_type_save` | `leave-types/save.php` | **name**, is_paid, accrual_days_per_year, carry_over_days, requires_approval, archived, leave_type | restore prior / delete new | | | `leave_type.save` | hr |
| `holiday_save` | `holidays/save.php` | **holiday_date**, **name**, holiday | restore prior / delete new | | | `holiday.save` | hr |
| `holiday_delete` | `holidays/delete.php` | **holiday** | add back | ✔ | deletion | `holiday.delete` | hr |

## Counts

Screens: 34. Actions: 35. Agent-approval actions: 18 (`other` 14; `deletion` 3 — pay-run delete, compensation-change delete, holiday delete; `external_send` 1 — the invitation).
