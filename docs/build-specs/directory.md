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

## Built and proven — 2026-09-23 (against the live kernel, as member 1)
- `department_create` made department 18 in the kernel (parent Front Office, manager the owner); the mirror held it at once; the feed's next pass applied it once more without doubling. Re-parenting to itself → 422 from HR's own check; the description update reached the kernel and the mirror.
- `membership_save` put Priya (26) in it as an admin in the kernel and the mirror; the department view listed her; `membership_remove` dated her `left_at` in both.
- `person_directory_update` changed her job title in the kernel; the mirror followed from the kernel's answer; HR's row carries the kernel's request id and the kernel's own row for that id reads `member.update by 1 via hr` — the two trails join. The kernel's refusal for a super-admin's record is shown verbatim (403). Through the action-token door (JSON, partial) one field changed and the rest held.
- `person_invite` → the kernel answered 202 and wrote the invitation (a fresh `example.invalid` address, department 18); HR shows the confirmation screen.
- A `read` person: the directory form of someone she does not administer, the invite form (she administers no department) and the department form → 403.
- 375 px and 1280 px: no horizontal scroll on the four screens.
- Kernel change this slice needed: `X-Request-Id` on every JSON answer (`app/http.php`, `json_response()`), which the contract had promised.
- Left behind: department 18 `SMOKE 0923-030459 Dept` (archiving is the kernel's; a super-admin archives it there) and the invitation to `smoke-0923-030459@example.invalid`.

## Open Questions
- (none)
