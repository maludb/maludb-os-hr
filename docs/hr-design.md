# HR — design (Phase 0 and Phase 1 of the new-app workflow)

2026-09-23 · the first application from us beside the Business OS kernel (build plan phase 7, A8).
Repository `/srv/apps/hr`, served as `hr.<domain>`. Built with `htmx-php-builder` (how it is built) and
fitted to `maludb-os-integration` 0.2.x (how it fits). The kernel's design: `docs/business-os-integration.md`
on the platform.

> **Checkpoint.** This document, the schema in `db/`, `docs/hr-mcp-tool-surface.md`, `docs/hr-action-manifest.md`
> and the slice specs in `docs/build-specs/` are the Phase 0–1 deliverable. No PHP is written until the
> owner approves them together.

## 1. What HR is, and is not

HR **owns employment**: who is employed here, on what terms, reporting to whom, what they are paid, what
leave they have, and how they are reviewed. The kernel **owns the directory**: who exists, their role and
status, their departments, who manages each department. HR shows the directory (a mirror with the
kernel's ids), and HR is the one application allowed to **change** it — through the kernel's directory
API, as the signed-on person, never through shared tables.

People reach HR from the launcher at `app.<domain>`; there is no login form and no password. Agents reach
it through its two read MCP servers and, for writes, through the kernel's Actions MCP running HR's
action registry. HR ships its expert (`os/expert.md`) and a leave desk agent (`os/leave_desk.md`), which
the kernel hires and runs; HR never calls a model.

Out of scope, by the owner's standing decisions: statutory payroll (tax tables, withholding, filing);
money movement and bank files; books (the accounting application, optional, pulls paid runs from HR's
token API); time tracking (a later application; hourly pay takes hours keyed on the pay run); HR
documents and e-signature (later applications); onboarding checklists; bank details (no secrets store in
HR — the accountant's system holds them).

## 2. Memory model — what HR remembers

| Entity | Owned by | Key facts | Relationships |
|---|---|---|---|
| **member** (mirror) | kernel | kind, name, email, role, external, status, HR capability, job title, phone, timezone | in departments; may have one employment record |
| **department** (mirror) | kernel | name, parent, manager, system key, archived | members; sub-departments |
| **department membership** (mirror) | kernel | is_admin, is_primary, joined, left | member × department |
| **employment record** | HR | employee no, type (employee/contractor/intern), manager, work location, start, probation end, end + reason, status (candidate/active/on_leave/ended), weekly hours, pay schedule, home contact details, notes | one per person; the manager is a member |
| **compensation change** | HR | effective date, pay type (salary/hourly/daily/per_invoice), rate, currency, reason, who recorded it | many per person; the newest on or before a day is the rate in force |
| **pay run** | HR | number, period, pay date, currency, status (draft/approved/paid/void), totals, who approved/paid/voided | items |
| **pay run item** | HR | person, hours, base note, totals | lines |
| **pay run line** | HR | kind (earning/deduction/employer_cost/reimbursement), code, description, quantity, rate, amount, is_base | — |
| **leave type** | HR | name, paid, allowance per year, carry-over cap, needs approval, archived | balances, requests |
| **leave balance** | HR | person, type, year, accrued, carried, used | derived remaining and committed |
| **leave request** | HR | person, type, dates, days, half day, reason, status, flags (over balance, overlap), decision | — |
| **holiday** | HR | date, name | reduces leave days |
| **review** | HR | person, reviewer, kind, period, due, status, rating, summary, strengths, improvements, goals, employee comment, shared, completed | — |
| **HR settings** | HR | currency, timezone, work days, employee-number prefix | one row |
| **access token** | HR | owner, label, scope (mcp/api), hash, revoked | for a person's own AI tools and the accounting feed |
| **activity** | HR | who did what to which record, when, from where; the kernel's request id for a directory write | shipped to the tenant's MaluDB as episodes, `application: hr` |

Identity facts hold across the estate: `app.member_id` in HR is the kernel's member id, so MaluDB
namespaces (`member:<id>`, `dept:<id>`) and the kernel's ledger line up with HR's activity.

## 3. Who sees what — the rules, in one place

The kernel decides who may open HR at all (an application access grant, carried as `capability`:
`read`, `write` or `admin`). Inside HR:

| Who | Meaning | May |
|---|---|---|
| **all** | any active member admitted to HR (humans need a capability; agents by their grant) | read the directory, departments, leave types, holidays, settings; their own employment, pay, leave, shared reviews |
| **manager** | the person's `manager_member_id` on their employment record | see their reports' employment, pay and leave; decide their leave; write their reviews |
| **admin** | a dept-admin (`is_admin` in the mirror) or the named manager of a department | see employment (no pay) and leave of the people in those departments; decide their leave; invite into and change memberships of those departments; change the department's directory facts |
| **hr** — HR staff | a human holding HR at capability `admin`, or a super-admin | everything: employment, compensation, pay runs, leave types and balances, every review, every directory write the kernel allows |
| **super** | the kernel's super-admin | as HR staff, plus creating departments |
| **agent** | a kernel agent with an access grant | employment (no pay, no home contact details), departments, leave, holidays — never pay, never reviews; writes only through granted action tools, approval-category actions pause |

Enforced in SQL by `hr_can_see_person()`, `hr_can_see_pay()`, `hr_can_see_review()`, `hr_is_staff()`,
`app_is_admin_of()`, `app_can_admin_member()` (db/001, db/005, db/009); PHP mirrors them in its gates. The
kernel's own rules still apply to every directory write (the people rule; never a super-admin, never an
agent, never a grant).

**Pay is the tightest data.** Home contact details, birth date and the id fragment are in no view. An
activity row about pay never carries an amount.

## 4. The question inventory — what HR exists to answer

R = record question (records MCP); A = activity question (activity MCP). Gates as in §3.

### Directory and employment
| # | Question | Kind | Who |
|---|---|---|---|
| HR1 | Who works here — human or agent — in which department, reporting to whom? | R | all |
| HR2 | Who is employed here (employee, contractor, intern) and who is in the directory but not employed? | R | hr, admin |
| HR3 | What are my employment details? | R | own |
| HR4 | Who is X's manager, and who reports to X? | R | own, manager, admin, hr |
| HR5 | Who starts or leaves in a period, whose probation ends, and who is still a candidate? | R | hr, admin |
| HR6 | How many people do we have, by department, type and status? | R | hr, admin |
| HR7 | What departments exist, who manages each, and how do they report to one another? | R | all |
| HR8 | Who is in department X, who administers it, and who there is off right now? | R | all (who is off: admin, hr) |
| HR9 | Who in the directory is external, inactive, or has never signed in to HR? | R+A | hr |

### Pay
| # | Question | Kind | Who |
|---|---|---|---|
| HR10 | What is X paid now, how has it changed, and who recorded each change? | R+A | own, manager, hr |
| HR11 | Which pay runs exist for a period, and what is each one's status and total? | R | hr |
| HR12 | Which pay runs are unapproved, or approved but not yet paid? | R | hr |
| HR13 | What is in this pay run, per person and per line, and why has someone no base line? | R | hr; own item once approved |
| HR14 | What did people cost in a period, by department or person, including employer costs? | R | hr |
| HR15 | What was on my last payslip? | R | own |

### Leave
| # | Question | Kind | Who |
|---|---|---|---|
| HR16 | How much leave do I (or X) have left this year, per type, counting what is already requested? | R | own, manager, admin, hr |
| HR17 | What leave is waiting for my decision? | R | manager, admin, hr |
| HR18 | What leave has X taken or requested this year, and was any over the balance? | R | own, manager, admin, hr |
| HR19 | Who is off in a date range, in a department? | R | admin, manager, hr |
| HR20 | What leave types exist, which need approval, and which days are holidays? | R | all |
| HR21 | Who has fewer than N days left, or more than N unused as the year ends? | R | hr |

### Reviews
| # | Question | Kind | Who |
|---|---|---|---|
| HR22 | Which reviews are due, overdue or in progress, and who is the reviewer? | R | hr, reviewer, manager |
| HR23 | What did X's last review say, and what was the rating? | R | own (once shared), reviewer, manager, hr |
| HR24 | Who has had no review in the last twelve months? | R | hr |
| HR25 | What currency, working days and holidays does the business use? | R | all |

### Activity
| # | Question | Kind | Who |
|---|---|---|---|
| HA1 | What happened to this record — employment, leave request, pay run, review — when, and by whom? | A | those who may see the record |
| HA2 | What did X do in HR in a period? | A | own, hr |
| HA3 | Who viewed or changed an employment or pay record? | A | hr |
| HA4 | What changed in the directory through HR — invites, titles, memberships, departments — and under which kernel request ids? | A | hr, admin |
| HA5 | Who signed in to HR, and who was refused, when? | A | hr |
| HA6 | What did an agent do in HR, run by run, and what was paused for approval? | A | hr |
| HA7 | When did the year's leave get granted, and what did the directory sync last do? | A | hr |

Every question maps to a named tool in `docs/hr-mcp-tool-surface.md`; every screen and action a person
can use is in `docs/hr-action-manifest.md`.

## 5. Features, in the order they are built (Phase 3 slices)

Phase 2 first: the sign-on receivers, the directory mirror and its timer, the shell with the command
bar, activity logging (`docs/build-specs/sso-shell.md`). Then:

| # | Slice | Spec | Exemplar? |
|---|---|---|---|
| 1 | **People** — the directory as HR shows it, one person's page, the employment record, ending employment | `build-specs/people.md` | **Yes** — built by the planning-class model; every later slice replicates it |
| 2 | **Directory writes** — invite, directory edit, memberships, departments (the kernel-write pattern) | `build-specs/directory.md` | second exemplar: the call to the kernel |
| 3 | **Leave** — types and holidays, requests, decisions, balances, who is off, the year's grant | `build-specs/leave.md` | |
| 4 | **Compensation** — pay history, a change, current pay on the person's page | `build-specs/compensation.md` | |
| 5 | **Pay runs** — draft from the pay bases, items and lines, approve / pay / void, the feed for accounting | `build-specs/pay-runs.md` | |
| 6 | **Reviews** — schedule, write, complete, share, comment | `build-specs/reviews.md` | |

Phase 4: the records and activity MCP servers, the action registry handed to the kernel, the tokens
screen that publishes the two MCP URLs, the health endpoint, `maludb-os.json` verified end to end.

## 6. Decisions taken in this design (the owner may overturn any)

| Decision | Chosen | Why |
|---|---|---|
| Repository and install path | `/srv/apps/hr` (`/srv/apps/<catalog_key>` for every application from us) | The integration contract's own vhost example uses it; outside the kernel's `/var/www`; readable by Apache |
| Accounts | None. Sign-on only by the kernel's hand-off token; sessions listed in `member_sessions` so the kernel's sign-out notice ends them all | `maludb-os-integration` non-negotiable; overrides `htmx-php-builder`'s login/Google/TOTP scaffolding, which is not built |
| Job title, phone, timezone, departments | Directory facts — shown from the mirror, changed only through the kernel's directory API | HR owns employment, not the directory |
| Where pay lives | Only in `compensation_changes`; the rate in force is `hr_pay_as_of()` | No denormalised rate to drift; history has no gaps |
| Home department | The primary membership in the mirror | One source of truth for departments |
| Manager | `manager_member_id` on the employment record, defaulting to the primary department's manager when the record is created | The reporting line is an employment fact; the department manager is a directory fact |
| Hourly pay | Hours keyed on the pay run item | No Time application yet; never paid a guess |
| Base line | Salary ÷ periods of the schedule (52/26/24/12); hourly = hours × rate; daily = days keyed × rate; per_invoice, another currency, or no basis → on the run with **no base line and a note** | The old People spec's rule, minus the books |
| Books | Nothing posted. Paid runs are pulled by the accounting application through `GET /api/v1/pay-runs.php` (document `hr.pay-runs/1`) with an `api` token | The kernel exports token accounting per period the same way; the accounting system is optional |
| Leave allowance | Granted up front each year, pro-rated from the start date, rounded down to the half day; drawn down on approval; carry-over by manual adjustment | The owner's decision 12 (2026-09-19) |
| Leave days | Work days (`hr_settings.work_days`, ISO weekdays) less holidays; a half day is 0.5 on one date; a request stays inside one calendar year | Restaurants work weekends: the work days are a setting |
| Beyond the balance | A request is accepted and **flagged**; approving beyond the balance is refused unless HR passes `override` | The old rule, kept |
| Who decides leave | The person's manager, an admin of their department, HR staff, the super-admin — never the requester | As designed for the kernel; the design doc names dept-admins approving leave |
| Agents and leave | The leave desk agent may **record** and **recommend**; `leave_request_decide` carries approval category `other`, so an agent's decision pauses for a person | An agent never decides for people by default |
| Reviews | One table; a review is shared explicitly; the person then reads it and may comment once | Simple, auditable |
| Bank details, national id | Not stored (id: last 2–4 characters only, no view) | No secrets store in HR; least data |
| Employee numbers | `E-0001` from a sequence, editable | A restaurant may have its own numbering |
| Token API | `GET /api/v1/pay-runs.php` and `/api/v1/health` only; `api` and `mcp` scopes not interchangeable | The contract's §6 |

## 7. Open questions for the owner (none block the build; defaults stated)

1. **Should managers see their reports' pay?** Default here: yes (the direct manager, not a dept-admin).
2. **Should an agent see leave requests' reasons?** Default: the reason is in the view; agents see it.
3. **Employee numbers**: prefix `E-` — or the restaurant's own scheme?
4. **Pay run currency**: one currency per run (default); people paid in another currency go on a run of their own.

## 8. State

- 2026-09-23: Phase 0 and Phase 1 written; the nine migrations apply clean on an empty database and the
  gate proof passes (`docs/hr-design.md` §3 rules, tested as the three roles). Awaiting the checkpoint.
