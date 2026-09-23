<?php
declare(strict_types=1);

/** Action `person_invite` (log `person.invite`, confirm, agent approval external_send): POST members.php on the kernel as the acting person. */
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_once dirname(__DIR__, 2) . '/app/features/directory/queries.php';

require_post();
verify_csrf();
require_login();
require_human();
$pdo = db();
$staff = hr_is_staff();

$email = strtolower(request_string('email'));
$role = request_string('business_role', 'user');
$departmentId = request_integer('department') ?? request_integer('department_id');
$message = request_string('message');
$errors = [];
if ($email === '' || filter_var($email, FILTER_VALIDATE_EMAIL) === false) { $errors[] = 'Enter a valid email address.'; }
if (!in_array($role, ['user', 'dept_admin'], true)) { $errors[] = 'The role is user or department admin.'; }
if ($role === 'dept_admin' && !$staff) { $errors[] = 'Only HR staff invite a department admin.'; }
if ($departmentId === null) { $errors[] = 'Choose the department they join.'; }
if (mb_strlen($message) > 500) { $errors[] = 'Keep the message under 500 characters.'; }
if ($departmentId !== null && !$staff && !is_admin_of($departmentId)) { $errors[] = 'You invite people into the departments you administer.'; }

$renderForm = function (array $errs) use ($pdo, $staff, $email, $role, $departmentId, $message): never {
    emit_action_status(false, ['errors' => $errs]);
    if (wants_json()) { respond_invalid($errs); }
    http_response_code(422);
    header('HX-Retarget: #page-content');
    header('HX-Reswap: innerHTML');
    echo view('people/partials/invite-form.php', ['errors' => $errs, 'staff' => $staff, 'departments' => administered_departments($pdo, $staff),
        'values' => ['email' => $email, 'department_id' => $departmentId, 'business_role' => $role, 'message' => $message]]);
    exit;
};
if ($errors !== []) {
    $renderForm($errors);
}
try {
    $doc = directory_write($pdo, 'POST', 'members.php', ['email' => $email, 'business_role' => $role, 'department_id' => $departmentId, 'message' => $message],
        'person.invite', 'invitation', null, ['after' => ['email' => $email, 'business_role' => $role, 'department_id' => $departmentId, 'invitation_id' => null]]);
} catch (DirectoryRefused $ex) {
    $renderForm([$ex->getMessage()]);
}
$did = 'Invited ' . $email . ' — they appear here once they register.';
emit_action_status(true, ['did' => $did, 'invitation_id' => $doc['invitation_id'] ?? null, 'refresh' => 'memberChanged']);
if (wants_json()) {
    respond_saved(['did' => $did, 'invitation_id' => $doc['invitation_id'] ?? null]);
}
hx_trigger('memberChanged');
render_screen('Invited', view('people/partials/invited.php', ['email' => $email, 'expires_at' => $doc['expires_at'] ?? null]),
    ['activeNav' => 'people-list', 'screen' => 'person-invite']);
