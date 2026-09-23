# Build spec: pay runs slice

Exemplar to replicate: people (list/view/form flow) with a ledger-style view. Schema tables: `pay_runs`, `pay_run_items`, `pay_run_lines` (functions `hr_recompute_*`, the frozen-run guard). Never modify them.

## Screens
| Screen id | Canonical URL | Purpose |
|---|---|---|
| `pay-runs` | `/payruns/` | Table: run no, period, pay date, status, people, gross, net, currency; filters status/period; **New pay run** |
| `pay-run-add` | `/payruns/new` | period_start, period_end, pay_date, currency, note, people (select2 multi; default everyone eligible), preview of who is eligible and who is not and why |
| `pay-run-view` | `/payruns/{id}` | Header card (status, totals, approved/paid/void facts); items table: person, hours/days, base note, gross, deductions, employer cost, reimbursements, net; per item **Edit** (draft) → `pay-run-item-edit`; **Add person**, **Approve**, **Un-approve**, **Mark paid**, **Void**, **Delete** as the status allows (Pattern C, confirms) |
| `pay-run-item-edit` | `/payruns/items/{id}/edit` | hours, days, the keyed lines (an editable rows table: kind, code, description, quantity, rate, amount; add/remove row; base lines shown read-only), note |
| `payslip` | `/payruns/mine` | The caller's items on approved/paid runs, newest first, each with its lines |

## Drafting a run (`save.php` create)
- People: those given, else every active human with employment `status IN ('active','on_leave')`, `employment_type <> 'contractor'` or pay type not `per_invoice`, and a pay basis (`hr_pay_as_of(member, period_end)`) in the run's currency.
- Base line per person (`is_base = true`, `code = 'base'`): salary → `pay_rate / periods(pay_schedule)` rounded to 2 dp (52 / 26 / 24 / 12; no schedule → no base line, note "no pay schedule"); hourly → needs `hours` (none yet → no base line, note "hours not keyed"; keyed later → the base line is created/updated by `items/save.php`); daily → needs `days` on the item (same rule); per_invoice → note "paid on invoices"; another currency → note "paid in {currency}"; no basis → note "no pay recorded". **Never paid a guess.**
- Totals recompute by trigger.

## Lifecycle (`approve` / `unapprove` / `paid` / `void` / `delete`)
draft → approved (`hr`; sets approved_by/at) → paid (`hr`; `paid_at` default now; **no money moves**) ; approved → draft (unapprove, while unpaid); draft or approved → void (unpaid only; reason required); draft → deleted. The guard refuses edits to a run that is not a draft. Each logs its event with `after: {run_no, period_start, period_end, people}` — never totals.

## Files
- `html/payruns/index.php` · `view.php` · `form.php` · `save.php` · `approve.php` · `unapprove.php` · `paid.php` · `void.php` · `delete.php` · `mine.php` · `items/form.php` · `items/save.php` · `items/add.php` · `items/remove.php`
- `html/api/v1/pay-runs.php` (token API, scope `api`: `?period=YYYY-MM` or `?run=PR-0001`; paid runs only unless `?status=approved`; document `hr.pay-runs/1`: `{schema, generated_at, currency, runs:[{run_no, period_start, period_end, pay_date, status, paid_at, totals:{gross, deductions, employer_cost, reimbursements, net}, items:[{member_id, display_name, employee_no, department, lines:[{kind, code, description, amount}]}]}]}`; logged `api.pay_runs.read`)
- `app/features/payruns/queries.php` · `present.php` · `app/api/bootstrap.php`
- `app/views/payruns/page.php` · `view.php` · `mine.php` · `partials/table.php` · `row.php` · `form.php` · `items.php` · `item-form.php` · `lines-editor.php` · `saved.php`

## Query functions
`find_pay_runs(PDO, array $filters, int $page)` · `find_pay_run(PDO, int $id)` (run + items + lines) · `create_pay_run(PDO, array $fields, array $memberIds, int $actorId): array` · `eligible_people(PDO, string $periodEnd, string $currency): array` · `add_pay_run_item(PDO, int $runId, int $memberId)` · `save_pay_run_item(PDO, int $itemId, ?string $hours, ?string $days, array $lines, ?string $note)` · `remove_pay_run_item(PDO, int $itemId)` · `set_pay_run_status(PDO, int $id, string $status, int $actorId, array $extra)` · `delete_pay_run(PDO, int $id)` · `my_payslips(PDO, int $memberId)` · `pay_runs_feed(PDO, array $filters)`

## Gates
Everything `hr`, except `payslip` (own) and the feed (an `api` token whose owner is `hr` — the accounting application's token is minted by an HR staff member or the super-admin).

## Action manifest entries
`pay_run_create`, `pay_run_item_save`, `pay_run_add_person`, `pay_run_remove_person`, `pay_run_approve`, `pay_run_unapprove`, `pay_run_mark_paid`, `pay_run_void`, `pay_run_delete`; screens above. `lines` reaches the agents' door as flat text `kind:code:amount:description` comma-separated (the kernel's rule for repeated structured params).

## Activity log events
`pay_run.create`, `pay_run_item.save` (after: hours, days, line count — no amounts), `pay_run_item.add`, `pay_run_item.remove`, `pay_run.approve`, `pay_run.unapprove`, `pay_run.pay`, `pay_run.void`, `pay_run.delete`, `api.pay_runs.read`, `screen.view`.

## Status vocabulary
draft → warning · approved → info · paid → success · void → secondary.

## Out of scope
Tax, filings, bank files, journal entries, payment recording per person, time entries.

## Open Questions
- (none)
