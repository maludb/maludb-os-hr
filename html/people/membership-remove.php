<?php
declare(strict_types=1);

/** Action `membership_remove` (log `membership.remove`, confirm, agent approval other): DELETE memberships.php as the acting person. */
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_once dirname(__DIR__, 2) . '/app/features/directory/queries.php';

require_post();
verify_csrf();
require_login();
$pdo = db();
$memberId = request_integer('person') ?? request_integer('member_id');
$departmentId = request_integer('department') ?? request_integer('department_id');
if ($memberId === null || $departmentId === null) {
    refuse(422, 'Name the person and the department.');
}
require_admin_of($departmentId);
try {
    directory_write($pdo, 'DELETE', 'memberships.php', ['member_id' => $memberId, 'department_id' => $departmentId], 'membership.remove', 'member', $memberId,
        ['after' => ['department_id' => $departmentId], 'department_id' => $departmentId]);
} catch (DirectoryRefused $ex) {
    refuse($ex->status, $ex->getMessage());
}
$pdo->prepare('UPDATE department_members SET left_at = now(), synced_at = now() WHERE member_id = :m AND department_id = :d AND left_at IS NULL')
    ->execute(['m' => $memberId, 'd' => $departmentId]);
$name = find_member_by_id($pdo, $memberId)['display_name'] ?? '';
emit_action_status(true, ['id' => $memberId, 'did' => 'Removed ' . $name . ' from the department', 'refresh' => 'memberChanged']);
saved_go('/departments/' . $departmentId, 'memberChanged');
