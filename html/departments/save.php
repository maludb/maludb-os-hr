<?php
declare(strict_types=1);

/** Actions `department_create` / `department_update` (logs `department.create` / `department.update`, agent approval other): the kernel's departments.php. */
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_once dirname(__DIR__, 2) . '/app/features/directory/queries.php';
require_once dirname(__DIR__, 2) . '/app/features/people/queries.php';

require_post();
verify_csrf();
require_login();
$pdo = db();
$id = request_integer('department') ?? request_integer('id');
$d = null;
if ($id !== null) {
    $d = find_department_view($pdo, $id);
    if ($d === null) {
        refuse(404, 'No such department here.');
    }
    require_admin_of($id);
} elseif (!is_super_admin() && !hr_is_staff()) {
    refuse(403, 'Creating a department needs the super-admin or HR staff.');
}
$errors = [];
$body = [];
if ($id === null || array_key_exists('name', $_POST)) {
    $body['name'] = request_string('name');
    if ($body['name'] === '' || mb_strlen($body['name']) > 80) { $errors[] = 'A department needs a name (up to 80 characters).'; }
}
if (array_key_exists('description', $_POST)) {
    $body['description'] = request_string('description');
    if (mb_strlen($body['description']) > 2000) { $errors[] = 'Keep the description under 2,000 characters.'; }
}
if (array_key_exists('parent', $_POST) || array_key_exists('parent_id', $_POST)) {
    $parent = request_integer('parent') ?? request_integer('parent_id');
    if ($parent !== null && ($parent === $id || !in_array($parent, array_map('intval', array_column(parent_options($pdo, $id), 'department_id')), true))) {
        $errors[] = 'A department cannot report to itself or to one below it.';
    }
    $body['parent_id'] = $parent;
}
if (array_key_exists('manager', $_POST) || array_key_exists('manager_member_id', $_POST)) {
    $manager = request_integer('manager') ?? request_integer('manager_member_id');
    if ($manager !== null) {
        $m = find_member_by_id($pdo, $manager);
        if ($m === null || $m['member_kind'] !== 'human' || $m['status'] !== 'active') { $errors[] = 'The manager must be an active person here.'; }
    }
    $body['manager_member_id'] = $manager;
}
if ($id !== null && $body === []) { $errors[] = 'Nothing to change.'; }

$renderForm = function (array $errs) use ($pdo, $id, $d, $body): never {
    emit_action_status(false, ['errors' => $errs]);
    if (wants_json()) { respond_invalid($errs); }
    http_response_code(422);
    header('HX-Retarget: #page-content');
    header('HX-Reswap: innerHTML');
    echo view('departments/partials/form.php', ['d' => $d, 'values' => $body + ($d ?? []), 'errors' => $errs, 'parents' => parent_options($pdo, $id), 'managers' => manager_options($pdo, null)]);
    exit;
};
if ($errors !== []) {
    $renderForm($errors);
}
try {
    if ($id === null) {
        $doc = directory_write($pdo, 'POST', 'departments.php', $body, 'department.create', 'department', null, ['after' => $body]);
        $id = (int) ($doc['department']['id'] ?? 0);
        $did = 'Created the department ' . $body['name'];
    } else {
        $before = array_intersect_key($d, $body);
        $doc = directory_write($pdo, 'PATCH', 'departments.php?id=' . $id, $body, 'department.update', 'department', $id, ['before' => $before, 'after' => $body, 'department_id' => $id]);
        $did = 'Updated the department ' . ($body['name'] ?? $d['name']);
    }
} catch (DirectoryRefused $ex) {
    $renderForm([$ex->getMessage()]);
}
if ($id < 1) {
    $renderForm(['The kernel did not return the department.']);
}
emit_action_status(true, ['id' => $id, 'did' => $did, 'refresh' => 'departmentChanged']);
saved_go('/departments/' . $id, 'departmentChanged');
