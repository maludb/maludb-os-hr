# The record picker in HR (2026-10-09)

The owner's decision (the builder plugin 0.8.0, `design-system/references/record-picker.md`; D1–D12 as recommended): a form that chooses a
**record** — a person here — does it through one searchable modal, never a `<select>` that lists every row. ProcessCore's purchase order
was the exemplar and the cidery the first retrofit; HR is the second, mechanical from the reference. This is the record to imitate for
the next application.

## Added
`app/picker.php` (verbatim; required by `app/bootstrap.php`), `app/pickers.php` (the registry), `html/pick/index.php`, `app/views/shared/picker-rows.php`,
`html/assets/js/record-picker.js`, the RECORD PICKER section of `app-overrides.css`, the one `#record-picker` modal and the script in `layout.php`.
HR has no unsaved-changes guard in its shell, so nothing there admits `data-picker-value`. HR writes its form rows by hand, so the fields call
`picker_field()` inside the existing row markup and the label's `for` points at `{id}-open` (each form's `$field` closure took a `$for` suffix).

## Two deliberate differences from the reference
- **`gate`, not `roles`.** HR has no role column to hand to `user_can()`; its rights are SQL functions. Each source carries `'gate' => fn(): bool` and the endpoint
  refuses with the usual 403 fragment when it is false. A gate is never wider than the screens that use the field.
- **The people rule is the view's.** Every `search` reads the feature's own list query, which reads the `mcp_*` views (`hr_can_see_person()`): a manager's
  list holds themself and their reports, an employee only themself, HR staff everyone — there is no second copy of the rule in the picker. The feature queries are
  un-paged lists, so `picker_page()` filters (case-insensitive, name + detail) and pages (25) the list they return.

## Sources
| Source | Used by | Reads | Gate |
|---|---|---|---|
| `employee` | leave request "For"; balance "Person" | `leave_people_options()` (view-scoped; the caller is "(you)") | a human |
| `reviewee` | review "Person" | `review_people_options()` kept for HR staff, or `is_manager_of()` | HR staff or a manager of someone |
| `manager` (param `except`) | employment "Manager"; department "Manager"; review "Reviewer" | `manager_options()` — active humans, with job title | HR staff, a dept-admin, the super-admin, or a manager of someone |
| `payrun_person` (param `run_id`) | a draft pay run's "Add a person" | `eligible_people()` minus who is on the run; the note why no base line | Payroll or HR staff (`can_run_pay()`) |
| `department_candidate` (param `department_id`) | a department's "Add a member" | `addable_members()`; agents badged; nothing for a department the caller does not administer | HR staff or a dept-admin (then `is_admin_of()` per department) |

## Converted (hidden input keeps each select's name and id; no handler, `hx-include`, smoke or manifest row changed)
Leave request: person. Leave balance adjust: person. Schedule a review: person, reviewer. Employment: manager. Department new/edit: manager.
Department view: add a member. Pay run view: add a person.

## Still selects, by the rule (D7)
Department parent ("Reports to") and every department filter and the invite's department — a handful of departments, a configuration list; move to a picker if a
business has dozens. Leave type, review kind, employment type, status, pay type, pay schedule, time zone, sort and every other filter are enumerations.
The invite form's role select is an enumeration.

## Proof
`scripts/prove-picker.sh` on a scratch copy (`hr_dev`; it refuses a database not named `*_dev`/`*_prove*`): the endpoint's refusals, the people rule, search,
escaping, paging, the params, every converted form, 422s keeping the label, a pay run and a review saved through the hidden inputs. `scripts/dev-router.php` is the
`php -S` router (the rewrites of `deploy/apache-hr.conf`) and `scripts/dev-sso.php` mints a hand-off token with the scratch key — HR has no login form.
`scripts/picker-browser.mjs` is the headless-Chromium pass (keyboard, Show more, Clear, a manager's modal, 375 px, dark).
