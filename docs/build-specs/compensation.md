# Build spec: compensation slice

Exemplar to replicate: people (the form and save flow), rendered into the person view's **Pay** tab.
Schema tables: `compensation_changes` (function `hr_pay_as_of`; view `mcp_current_pay`). Never modify them.

## Screens
| Screen id | Canonical URL | Purpose |
|---|---|---|
| `compensation-add` | `/people/compensation/new` (params `person`, `effective_date`) | The change form |
| (tab) | `/people/{id}?tab=pay` | Current pay card (rate, type, currency, schedule, monthly estimate), the history table (effective date, type, rate, currency, reason, recorded by), **Record a change** for `hr`; delete per future-dated row (Pattern C, confirm) |

## Form
person (select2, or fixed from the param), effective_date (required; default today), pay_type (salary/hourly/daily/per_invoice; a salary is per year), pay_rate (required, ≥ 0, 2 dp), currency (default `hr_settings.currency`), reason. ids `compensation-form-field-*`. A change on an existing effective date → 422 "A change on that date already exists; delete it first."

## Files
- `html/people/compensation/form.php` · `save.php` · `delete.php`
- `app/features/compensation/queries.php` · `present.php`
- `app/views/people/partials/pay.php` · `compensation-form.php` · `compensation-row.php` · `saved.php`

## Query functions
`pay_history(PDO, int $memberId): array` (from `mcp_compensation_changes` + `mcp_current_pay`) · `insert_compensation_change(PDO, int $memberId, string $date, string $type, string $rate, string $currency, ?string $reason, int $actorId): array` · `delete_compensation_change(PDO, int $id): bool` (future-dated only)

## Gates
Read: the Pay tab renders only when `hr_can_see_pay(person)` (own, manager, hr). Write: `hr` only. Agents: the view refuses; the tool returns nothing.

## Action manifest entries
`compensation_change_save` (confirm; agent approval `other`), `compensation_change_delete` (confirm; `deletion`); screen `compensation-add`.

## Activity log events
`compensation_change.create` — `after: {effective_date, pay_type, currency}` **and never the rate**; `compensation_change.delete` — the same shape; `screen.view` with `screen = 'person-view'` and `after.tab = 'pay'` (that is HA3's evidence).

## Out of scope
Bank details; approval workflows for a change (a person with `hr` records it; the log is the trail); currency conversion.

## Open Questions
- (none)
