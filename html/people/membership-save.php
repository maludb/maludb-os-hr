<?php
declare(strict_types=1);

/** Action `membership_save` (log `membership.save`, agent approval other): POST memberships.php as the acting person. */
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_once dirname(__DIR__, 2) . '/app/features/directory/queries.php';

require_post();
verify_csrf();
require_login();
$pdo = db();
$memberId = request_integer('person') ?? request_integer('member_id');
$departmentId = request_integer('department') ?? request_integer('department_id');
if ($memberId === null || find_member_by_id($pdo, $memberId) === null) {
    refuse(404, 'No such person here.');
}
if ($departmentId === null) {
    refuse(422, 'Choose a department.');
}
require_admin_of($departmentId);
$body = ['member_id' => $memberId, 'department_id' => $departmentId, 'is_admin' => request_bool('is_admin'), 'is_primary' => request_bool('is_primary')];
try {
    directory_write($pdo, 'POST', 'memberships.php', $body, 'membership.save', 'member', $memberId,
        ['after' => ['department_id' => $departmentId, 'is_admin' => $body['is_admin'], 'is_primary' => $body['is_primary']], 'department_id' => $departmentId]);
} catch (DirectoryRefused $ex) {
    refuse($ex->status, $ex->getMessage());
}
// The kernel answers the membership row; the feed confirms within a minute. Apply it now so the screen is right at once.
$pdo->prepare('INSERT INTO department_members (member_id, department_id, is_admin, is_primary, joined_at, left_at, synced_at) VALUES (:m, :d, :a, :p, now(), NULL, now())
               ON CONFLICT (member_id, department_id) DO UPDATE SET is_admin = EXCLUDED.is_admin, is_primary = EXCLUDED.is_primary, left_at = NULL, synced_at = now()')
    ->execute(['m' => $memberId, 'd' => $departmentId, 'a' => $body['is_admin'] ? 't' : 'f', 'p' => $body['is_primary'] ? 't' : 'f']);
$name = find_member_by_id($pdo, $memberId)['display_name'] ?? '';
emit_action_status(true, ['id' => $memberId, 'did' => 'Put ' . $name . ' in the department' . ($body['is_admin'] ? ' as an administrator' : ''), 'refresh' => 'memberChanged']);
$back = request_string('back') ?: '/departments/' . $departmentId;
saved_go(preg_match('#^/[a-z0-9/?=&_-]*$#i', $back) ? $back : '/departments/' . $departmentId, 'memberChanged');
