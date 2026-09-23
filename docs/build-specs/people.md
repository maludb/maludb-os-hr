# Build spec: people slice — THE EXEMPLAR

Built by the planning-class model. Every later slice replicates its files, ids, gates and flow.
Schema tables: `members`, `department_members`, `departments` (mirror, read only here), `employment_profiles`. Never modify them.

## Screens

| Screen id | Canonical URL | Purpose |
|---|---|---|
| `people-list` | `/people/` | The directory with employment beside it, cards per the owner's preference for lists of named things |
| `person-view` | `/people/{id}` | One person, tabs: Overview, Employment, Pay (slice 4), Leave (slice 3), Reviews (slice 6), History |
| `employment-edit` | `/people/employment/{id}/edit` | Create or change the employment record (`{id}` = member id) |

(`person-invite` and `person-directory-edit` belong to the directory slice.)

## List screen
- Cards (`RecordCard` pattern from the design system: avatar initials, name, job title, primary department, status dot, employment badge), 24 per page, server-rendered pagination.
- Search matches display_name, email, employee_no; filters `department`, `status` (employment status or `unemployed`), `type`, `employed`; sort allowlist name / start_date / department; default name.
- Card link → `person-view`. Agents are listed (badge "agent") but have no employment; external members badge "external".
- Page-header actions: **Invite** (directory slice) for `hr`/`admin`; nothing else.

## Person view
- Overview tab: directory facts (mirror), departments with admin/primary marks, manager, reports (people whose manager this is), HR capability; buttons **Edit directory** (directory slice; `hr`/`admin` of one of their departments), **Employment** (`hr`).
- Employment tab: the record as a read-only detail card, or "No employment record" with **Create** for `hr`; **End employment** (Pattern C confirm) for `hr` when active.
- History tab: `record_history` rows for `member` + `employment_profile` of this person, from `mcp_activity_log`.
- Tabs are the card header (design system). `data-screen="person-view" data-entity="member" data-record-id="{id}"`.

## Form (employment)
| Field | Input | Required | Rule | id |
|---|---|---|---|---|
| employee_no | text | no | unique; blank → `E-` + next sequence | `employment-form-field-employee-no` |
| employment_type | select employee/contractor/intern | yes | allowlist | `employment-form-field-employment-type` |
| manager | select2 of active humans except the person | no | not self; defaults to the primary department's manager on create | `employment-form-field-manager` |
| work_location | text | no | ≤ 120 chars | `employment-form-field-work-location` |
| start_date | date | no | | `employment-form-field-start-date` |
| probation_end_date | date | no | ≥ start_date | `employment-form-field-probation-end-date` |
| status | select candidate/active/on_leave | yes | `ended` only through `employment_end` | `employment-form-field-status` |
| weekly_hours | number step 0.25 | no | > 0 | `employment-form-field-weekly-hours` |
| pay_schedule | select weekly/biweekly/semimonthly/monthly | no | allowlist | `employment-form-field-pay-schedule` |
| personal_email | email | no | valid | `employment-form-field-personal-email` |
| address | textarea | no | | `employment-form-field-address` |
| emergency_contact | text | no | | `employment-form-field-emergency-contact` |
| birth_date | date | no | in the past | `employment-form-field-birth-date` |
| national_id_last4 | text | no | 2–4 alphanumerics | `employment-form-field-national-id-last4` |
| notes | textarea | no | | `employment-form-field-notes` |
- On create with status `active` and a start date: `hr_grant_annual_leave(member, year(start_date))` and for the current year — the slice calls it; leave's own screens come in slice 3.
- Save/cancel in the page header. Validation errors re-render the form container `#employment-form-container`.

## Files (exactly these)
- `html/people/index.php` · `view.php` · `employment/form.php` · `employment/save.php` · `employment/end.php`
- `app/features/people/queries.php` · `app/features/people/present.php`
- `app/views/people/page.php` · `view.php` · `partials/cards.php` · `card.php` · `overview.php` · `employment.php` · `employment-form.php` · `history.php` · `saved.php`

## Query functions (signatures fixed)
- `find_people(PDO, array $filters, string $sort = 'name', int $page = 1, int $per = 24): array` (rows from `mcp_members` joined to `mcp_employment_profiles`; total for pagination)
- `find_person(PDO, int $memberId): ?array` (mirror + employment + departments + manager + reports)
- `find_employment(PDO, int $memberId): ?array` (base table, `hr` only — the form's source)
- `upsert_employment(PDO, int $memberId, array $fields, int $actorId): array`
- `end_employment(PDO, int $memberId, string $endDate, ?string $reason): array`
- `next_employee_no(PDO): string`

## Handlers
- `employment/save.php`: `require_post(); verify_csrf(); require_hr();` the person must be an active human in the mirror (else 422 "Only a person in the directory can have an employment record"); validate; transaction: upsert + `log_activity('employment_profile.save', 'employment_profile', $memberId, ['before' => changed, 'after' => changed])` (never home contact values in the log — record which fields changed only); `emit_action_status(true, ['id' => $memberId, 'location' => "/people/$memberId"])`; `HX-Trigger: employmentProfileChanged`; render `saved.php`.
- `employment/end.php`: `require_hr()`; status → `ended`, `end_date`, `end_reason`; open leave requests after the end date are cancelled by the handler (logged `leave_request.cancel` with `after.by = 'employment_end'`); log `employment_profile.end`.

## Action manifest entries
- Screens: `people-list`, `person-view`, `employment-edit` as in the manifest.
- Actions: `employment_save`, `employment_end`.

## Activity log events
- `screen.view` (people-list, person-view, employment-edit), `employment_profile.save`, `employment_profile.end`.

## Status vocabulary
- candidate → info · active → success · on_leave → warning · ended → secondary · (no record) → dark; member status inactive → danger dot.

## Out of scope for this slice
- Inviting, directory edits, memberships (directory slice); pay (compensation slice); leave and reviews tabs (their slices render into the tabs this slice leaves as empty states).

## Open Questions
- (none)
