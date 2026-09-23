<?php
declare(strict_types=1);

/** /departments/new and /departments/{id}/edit — create in the kernel (super-admin or HR staff) or change (an admin of it). */
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_once dirname(__DIR__, 2) . '/app/features/directory/queries.php';
require_once dirname(__DIR__, 2) . '/app/features/people/queries.php';

require_login();
$pdo = db();
$id = request_integer('id') ?? request_integer('department');
$d = null;
if ($id !== null) {
    $d = find_department_view($pdo, $id);
    if ($d === null) {
        respond_not_found('No such department here.');
    }
    require_admin_of($id);
} elseif (!is_super_admin() && !hr_is_staff()) {
    refuse(403, 'Creating a department needs the super-admin or HR staff.');
}
$values = $d ?? ['name' => request_string('name'), 'description' => '', 'parent_id' => request_integer('parent'), 'manager_member_id' => null];
log_screen_view($pdo, $id === null ? 'department-add' : 'department-edit');
render_screen($id === null ? 'New department' : $d['name'], view('departments/partials/form.php', ['d' => $d, 'values' => $values, 'errors' => [],
    'parents' => parent_options($pdo, $id), 'managers' => manager_options($pdo, null)]),
    ['activeNav' => 'departments-list', 'screen' => $id === null ? 'department-add' : 'department-edit', 'entity' => 'department', 'recordId' => (string) ($id ?? '')]);
