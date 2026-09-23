<?php
declare(strict_types=1);

/** /departments/{id} — one department: people, admins, sub-departments, who is off (screen department-view). */
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_once dirname(__DIR__, 2) . '/app/features/directory/queries.php';
require_once dirname(__DIR__, 2) . '/app/features/people/present.php';

require_login();
$pdo = db();
$id = request_integer('id');
$d = $id === null ? null : find_department_view($pdo, $id);
if ($d === null) {
    respond_not_found('No such department here.');
}
$canAdmin = hr_is_staff() || is_admin_of($id);
log_activity($pdo, 'screen.view', 'department', $id, ['screen' => 'department-view']);
if (wants_json()) {
    respond_screen(['department' => present_department($d), 'members' => array_map(static fn (array $m): array => ['member_id' => (int) $m['member_id'],
        'display_name' => $m['display_name'], 'member_kind' => $m['member_kind'], 'is_admin' => !empty($m['is_admin']), 'is_primary' => !empty($m['is_primary']), 'job_title' => $m['job_title']], $d['members'])]);
}
render_screen($d['name'], view('departments/view.php', ['d' => $d, 'canAdmin' => $canAdmin, 'addable' => $canAdmin ? addable_members($pdo, $id) : [], 'tz' => member_timezone()]),
    ['activeNav' => 'departments-list', 'screen' => 'department-view', 'entity' => 'department', 'recordId' => (string) $id]);
