# Build spec: leave slice

Exemplar to replicate: people. Schema tables: `leave_types`, `leave_balances`, `leave_requests`, `holidays`, `hr_settings` (functions `hr_leave_days`, `hr_grant_annual_leave`, `hr_leave_committed`, `hr_annual_allowance`). Never modify them.

## Screens
| Screen id | Canonical URL | Purpose |
|---|---|---|
| `leave-requests` | `/leave/` | Table (a ledger of requests — table, not cards): person, type, dates, days, status, flags, decided by; filters status/person/department/period/awaiting_me; default = awaiting me, then mine |
| `leave-request-add` | `/leave/new` | Request form; `person` offered only to manager/admin/hr |
| `leave-request-view` | `/leave/{id}` | The request, the balance it draws on (remaining, committed), flags, decision buttons (Pattern C: Approve / Reject with note; Override checkbox for `hr` when over balance), Cancel |
| `leave-calendar` | `/leave/calendar` | Week grid, one row per visible person, a cell per day (type badge; holidays and non-work days shaded); prev/next week, department filter |
| `leave-balances` | `/leave/balances/` | Table per person × type for a year: accrued, carried, used, committed, remaining; **Grant the year** button (`hr`, confirm) |
| `leave-balance-adjust` | `/leave/balances/new` | Adjust form |
| `leave-types` · `leave-type-add` · `leave-type-edit` | `/settings/leave-types/…` | Settings, table + form |
| `holidays` · `holiday-add` | `/settings/holidays/…` | Settings, table + form; delete per row (Pattern C, confirm) |

## Forms
- Request: leave_type (select active types), start_date, end_date (required; same calendar year), half_day (checkbox → end = start), reason (textarea), person (select2, gated). Days are computed server-side by `hr_leave_days()` and shown back on the view. Over the balance → saved with `over_balance = true` and a warning; overlapping a live request → `has_overlap = true` and a warning. A type with `requires_approval = false` is approved on creation (`decided_by` = the creator, note "approved automatically") and the balance drawn down.
- Decide: decision (approve/reject), note, override (`hr` only). Approve beyond the balance without override → 422 "Beyond the balance by N days; HR can override." Approve draws `used_days` up by `days` on the year's balance (created by `hr_grant_annual_leave` if missing). Reject changes nothing else.
- Cancel: allowed while requested, or approved and `start_date >= today`; an approved one gives the days back.
- Adjust: person, leave_type, year, accrued_days, carried_days (≤ type's carry_over_days unless `hr` confirms), reason (required). Before/after in the log.
- Grant the year: `hr_grant_annual_leave()` for every active human with employment `status IN ('active','on_leave')`; returns how many balances were created; log `leave_balance.grant_year` with `after.year, after.granted`. `bin/grant_annual_leave.php` runs the same on 1 January (cron, `source = 'cron'`).
- Leave type: name, is_paid, accrual_days_per_year (blank = unlimited), carry_over_days, requires_approval, archived. Holiday: holiday_date, name.

## Files
- `html/leave/index.php` · `view.php` · `form.php` · `save.php` · `decide.php` · `cancel.php` · `calendar.php` · `balances/index.php` · `balances/form.php` · `balances/save.php` · `balances/grant-year.php`
- `html/settings/leave-types/index.php` · `form.php` · `save.php` · `html/settings/holidays/index.php` · `form.php` · `save.php` · `delete.php`
- `bin/grant_annual_leave.php`
- `app/features/leave/queries.php` · `present.php` · `app/features/settings/queries.php` (types, holidays, settings)
- `app/views/leave/page.php` · `view.php` · `calendar.php` · `balances.php` · `partials/table.php` · `row.php` · `form.php` · `decision.php` · `balance-form.php` · `calendar-grid.php` · `saved.php` · `app/views/settings/leave-types/…` · `holidays/…`

## Query functions
- `find_leave_requests(PDO, array $filters, int $page): array` · `find_leave_request(PDO, int $id): ?array` · `insert_leave_request(PDO, int $memberId, int $typeId, string $start, string $end, bool $half, ?string $reason, int $actorId): array` · `decide_leave_request(PDO, int $id, string $decision, ?string $note, bool $override, int $actorId): array` · `cancel_leave_request(PDO, int $id, int $actorId): array`
- `leave_balances(PDO, array $filters): array` · `adjust_leave_balance(PDO, …): array` · `grant_year(PDO, int $year): int`
- `whos_off(PDO, string $from, string $to, ?int $departmentId): array`
- `find_leave_types(PDO, bool $includeArchived)`, `upsert_leave_type`, `find_holidays(PDO, int $year)`, `upsert_holiday`, `delete_holiday`

## Gates
- Create: own always; for another person: manager / `app_can_admin_member` / `hr`.
- Decide: `hr`, or manager of the person, or `app_can_admin_member(person)` — and never `member_id = current`.
- Balances, adjust, grant, types, holidays: `hr` (balances list also shows a manager/admin their people).

## Action manifest entries
`leave_request_create`, `leave_request_decide`, `leave_request_cancel`, `leave_balance_adjust`, `leave_grant_year`, `leave_type_save`, `holiday_save`, `holiday_delete`; the screens above.

## Activity log events
`leave_request.create` (after: type, dates, days, flags), `leave_request.decide` (after: decision, override), `leave_request.cancel`, `leave_balance.adjust` (before/after days), `leave_balance.grant_year`, `leave_type.save`, `holiday.save`, `holiday.delete`, `cron.run` (the January job), `screen.view`.

## Status vocabulary
requested → warning · approved → success · rejected → danger · cancelled → secondary; flags: over balance → danger badge, overlap → warning badge.

## Out of scope
Calendar blocks in any other application; notifications by mail (the kernel notifies through its own channels when it has them); accrual per month.

## Built and proven — 2026-09-23
- Settings saved (currency, time zone, work days Mon–Sat, prefix); a holiday added and removed (Pattern C row delete); a leave type added, then archived through its form.
- Priya (capability `read`, employed, PTO 16 + sick 8 for 2026): a request over a holiday counted the work days only (Oct 12–16 with Monday a holiday → 4 days); she could not decide her own (403); the owner approved and `used_days` rose by 4. A 23-day request was recorded and flagged `over_balance`; approving it → 422 "Beyond the balance by 11 days; HR staff can override"; with `override` → approved (used 27); her cancellation of that approved, future request gave the days back (used 4). Sick leave (no approval) was approved as recorded, a half day, flagged `has_overlap` where it met the PTO. The list shows hers under "Mine"; the calendar shades the holiday and non-work days and links each day to its request; the balances page and the person's Leave tab read the same views; an adjustment set 3 carried days with a reason in the log; "Grant the year" for 2027 created balances for everyone employed and the January job found nothing left to grant (idempotent).
- The agents' door: `leave_request_create` for Priya through an action token answered `record_id`-able JSON.
- 375 px and 1280 px: no horizontal scroll on the seven screens (the calendar table scrolls inside its card).
- Left behind as fixtures: Priya's leave requests 1 (approved, Oct 12–16, "SMOKE trip"), 2 (cancelled), 3 (sick, half day), 4 (requested by the agents' door, Dec 21–23); her 2026 PTO balance 16 accrued + 3 carried; the archived type `SMOKE unpaid`; settings Mon–Sat, America/New_York.

## Open Questions
- (none)
