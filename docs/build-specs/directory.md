# Build spec: directory slice — the kernel-write pattern

Exemplar to replicate: people (screens, files, ids); this slice adds the ONE new pattern every directory
write uses: `directory_call()` to the kernel as the acting person, then the local mirror updated from the
answer, then the local log with the kernel's request id.
Schema tables: `members`, `departments`, `department_members` (mirror). HR writes them only from the kernel's answer or the feed.

## The pattern (`app/directory.php`)
```php
$answer = directory_call('PATCH', '/api/v1/directory/members.php?id=' . $id, $fields, current_member_id());
// → ['status' => 200, 'body' => [...], 'request_id' => '…'] ; throws DirectoryRefused($status, $message) on 4xx
mirror_apply_member($pdo, $answer['body']['member']);        // upsert from the kernel's row
log_activity($pdo, 'person.directory_update', 'member', $id, ['before' => …, 'after' => …, 'kernel_request_id' => $answer['request_id']]);
```
- Headers: `Authorization: Bearer OS_APPLICATION_TOKEN`, `X-Acting-Member`, `Content-Type: application/json`, `X-Request-Id: <ours>`; 15 s timeout.
- The kernel's refusal (403 the people rule, 422 validation) is shown as it came — its sentence is the truth; HR adds nothing.
- The kernel unreachable → 503 "The directory is not reachable right now; nothing was changed." — no local write.

## Screens
| Screen id | Canonical URL | Purpose |
|---|---|---|
| `person-invite` | `/people/new` | Invite form (email, role, department, message) |
| `person-directory-edit` | `/people/directory/{id}/edit` | Directory facts form |
| `departments-list` | `/departments/` | Cards: name, manager, people/agents counts, parent, archived badge |
| `department-view` | `/departments/{id}` | Members (cards, admin/primary marks), sub-departments, who is off (leave slice fills it), **Add member** inline form (`membership_save`), remove per member (Pattern C, confirm) |
| `department-add` | `/departments/new` | Create in the kernel |
| `department-edit` | `/departments/{id}/edit` | Rename, re-parent, manager, description |

## Forms
- Invite: email (required, valid), business_role (select user/dept_admin; `dept_admin` only offered to `hr`/`super`), department (select2 of departments the caller administers, or all for `hr`), message (textarea ≤ 500). ids `invite-form-field-*`. Success: "Invitation sent to … — they appear here once they register." `person.invite` logged with `after.email, after.department_id, after.invitation_id` and the kernel request id. Agent approval `external_send`.
- Directory edit: display_name, job_title, phone, timezone (select of `DateTimeZone::listIdentifiers()`), is_external (checkbox), status (select active/suspended), business_role (select user/dept_admin — `hr` only). ids `directory-form-field-*`. The form refuses to load for a super-admin or an agent (the kernel would refuse too; say so).
- Department form: name (required ≤ 80), description, parent (select2; not itself or a descendant), manager (select2 of active humans). ids `department-form-field-*`.
- Membership inline form on the department view: person (select2 of active members not in it), is_admin, is_primary. ids `membership-form-field-*`.

## Files
- `html/people/form.php` (invite form) · `invite.php` · `directory/form.php` · `directory/save.php` · `membership-save.php` · `membership-remove.php`
- `html/departments/index.php` · `view.php` · `form.php` · `save.php`
- `app/features/directory/queries.php` (mirror reads for forms; `mirror_apply_member`, `mirror_apply_department`, `mirror_apply_membership` shared with `bin/directory_sync.php`) · `app/directory.php`
- `app/views/people/partials/invite-form.php` · `directory-form.php` · `app/views/departments/page.php` · `view.php` · `partials/cards.php` · `card.php` · `form.php` · `members.php` · `membership-form.php` · `saved.php`

## Handlers (each: `require_post(); verify_csrf();` gate; `directory_call`; mirror; log; `emit_action_status`)
- `invite.php` — gate `hr` or `app_is_admin_of(department)`; POST members.php; 202 → "invited".
- `directory/save.php` — gate `hr` or `app_can_admin_member(person)`; PATCH members.php?id=; `location` `/people/{id}`.
- `membership-save.php` — gate `hr` or admin of that department; POST memberships.php. `membership-remove.php` — DELETE memberships.php; log `membership.remove`.
- `departments/save.php` — create: `super`/`hr` → POST departments.php (201; `location` `/departments/{id}`); update: `hr` or admin of it → PATCH departments.php?id=; `partial` supported.

## Action manifest entries
`person_invite`, `person_directory_update`, `membership_save`, `membership_remove`, `department_create`, `department_update`; screens above.

## Activity log events
`person.invite`, `person.directory_update`, `membership.save`, `membership.remove`, `department.create`, `department.update`, `screen.view` — each directory write carries `kernel_request_id`.

## Out of scope
Archiving a department (kernel only); granting HR or any application; anything about agents; `super_admin`.

## Open Questions
- (none)
