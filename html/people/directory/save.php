<?php
declare(strict_types=1);

/** Action `person_directory_update` (log `person.directory_update`, agent approval other): PATCH members.php?id= as the acting person. */
require_once dirname(__DIR__, 3) . '/app/bootstrap.php';
require_once dirname(__DIR__, 3) . '/app/features/people/queries.php';
require_once dirname(__DIR__, 3) . '/app/features/people/present.php';
require_once dirname(__DIR__, 3) . '/app/features/directory/queries.php';

require_post();
verify_csrf();
require_login();
$pdo = db();
$id = request_integer('person') ?? request_integer('member_id');
$person = $id === null ? null : find_person($pdo, $id);
if ($person === null) {
    refuse(404, 'No such person here.');
}
require_can_admin_member($id);
if ($person['member_kind'] !== 'human' || $person['business_role'] === 'super_admin') {
    refuse(403, $person['member_kind'] !== 'human' ? 'Agents are the operating system\'s to change.' : 'A super-admin is changed only from the operating system.');
}
$staff = hr_is_staff();
$body = [];
$errors = [];
foreach (['display_name' => 120, 'job_title' => 120, 'phone' => 60] as $k => $max) {
    if (array_key_exists($k, $_POST)) {
        $v = request_string($k);
        if ($k === 'display_name' && $v === '') { $errors[] = 'A name is required.'; }
        if (mb_strlen($v) > $max) { $errors[] = ucfirst(str_replace('_', ' ', $k)) . " is up to {$max} characters."; }
        $body[$k] = $v;
    }
}
if (array_key_exists('timezone', $_POST)) {
    $tz = request_string('timezone');
    if (!in_array($tz, timezone_identifiers_list(), true)) { $errors[] = 'That is not a time zone.'; }
    $body['timezone'] = $tz;
}
if (array_key_exists('is_external', $_POST) || !is_action_authed()) {
    $body['is_external'] = request_bool('is_external');
}
if (array_key_exists('status', $_POST)) {
    $status = request_string('status');
    if (!in_array($status, ['active', 'suspended'], true)) { $errors[] = 'Status is active or suspended.'; }
    $body['status'] = $status;
}
if (array_key_exists('business_role', $_POST)) {
    $role = request_string('business_role');
    if (!in_array($role, ['user', 'dept_admin'], true)) { $errors[] = 'The role is user or department admin.'; }
    if (!$staff && $role !== $person['business_role']) { $errors[] = 'Only HR staff change a role.'; }
    $body['business_role'] = $role;
}
if ($body === []) { $errors[] = 'Nothing to change.'; }

$renderForm = function (array $errs) use ($pdo, $person, $body, $staff): never {
    emit_action_status(false, ['errors' => $errs]);
    if (wants_json()) { respond_invalid($errs); }
    http_response_code(422);
    header('HX-Retarget: #page-content');
    header('HX-Reswap: innerHTML');
    echo view('people/partials/directory-form.php', ['person' => $person, 'values' => $body + $person, 'errors' => $errs, 'staff' => $staff]);
    exit;
};
if ($errors !== []) {
    $renderForm($errors);
}
$before = array_intersect_key($person, $body);
try {
    directory_write($pdo, 'PATCH', 'members.php?id=' . $id, $body, 'person.directory_update', 'member', $id, ['before' => $before, 'after' => $body]);
} catch (DirectoryRefused $ex) {
    $renderForm([$ex->getMessage()]);
}
emit_action_status(true, ['id' => $id, 'did' => 'Updated the directory record of ' . ($body['display_name'] ?? $person['display_name']), 'refresh' => 'memberChanged']);
saved_go('/people/' . $id, 'memberChanged');
