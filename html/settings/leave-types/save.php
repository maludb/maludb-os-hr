<?php
declare(strict_types=1);
/** Action `leave_type_save` (log `leave_type.save`). HR staff. */
require_once dirname(__DIR__, 3) . '/app/bootstrap.php';
require_once dirname(__DIR__, 3) . '/app/features/settings/queries.php';
require_post();
verify_csrf();
require_hr();
$pdo = db();
$id = request_integer('leave_type') ?? request_integer('id');
$t = $id === null ? null : find_leave_type($pdo, $id);
if ($id !== null && $t === null) {
    refuse(404, 'No such leave type.');
}
$f = ['name' => request_string('name'), 'is_paid' => request_bool('is_paid'), 'requires_approval' => request_bool('requires_approval'), 'archived' => request_bool('archived')];
$acc = request_string('accrual_days_per_year');
$f['accrual_days_per_year'] = $acc === '' ? null : $acc;
$f['carry_over_days'] = request_string('carry_over_days', '0');
$errors = [];
if ($f['name'] === '' || mb_strlen($f['name']) > 60) { $errors[] = 'A leave type needs a name (up to 60 characters).'; }
if ($f['accrual_days_per_year'] !== null && (!is_numeric($acc) || (float) $acc < 0 || (float) $acc > 365)) { $errors[] = 'The allowance is a number of days per year, or blank for unlimited.'; }
if (!is_numeric($f['carry_over_days']) || (float) $f['carry_over_days'] < 0) { $errors[] = 'Carry-over is a number of days.'; }
$renderForm = function (array $errs) use ($t, $f, $id): never {
    emit_action_status(false, ['errors' => $errs]);
    if (wants_json()) { respond_invalid($errs); }
    http_response_code(422);
    header('HX-Retarget: #page-content');
    header('HX-Reswap: innerHTML');
    echo view('settings/leave-types/form.php', ['t' => $t, 'values' => ['name' => $f['name'], 'is_paid' => $f['is_paid'], 'accrual_days_per_year' => $f['accrual_days_per_year'] ?? '', 'carry_over_days' => $f['carry_over_days'],
        'requires_approval' => $f['requires_approval'], 'archived_at' => $f['archived'] ? 'x' : null] + ($t ?? []), 'errors' => $errs]);
    exit;
};
if ($errors !== []) {
    $renderForm($errors);
}
try {
    $row = upsert_leave_type($pdo, $id, $f);
} catch (PDOException $ex) {
    if ($ex->getCode() === '23505') { $renderForm(['A leave type with that name exists.']); }
    $renderForm([db_message($ex, 'The leave type could not be saved.')]);
}
log_activity($pdo, 'leave_type.save', 'leave_type', (int) $row['id'], ['before' => $t === null ? null : array_intersect_key($t, array_flip(['name', 'is_paid', 'accrual_days_per_year', 'carry_over_days', 'requires_approval', 'archived_at'])),
    'after' => ['name' => $row['name'], 'is_paid' => (bool) $row['is_paid'], 'accrual_days_per_year' => $row['accrual_days_per_year'], 'carry_over_days' => $row['carry_over_days'], 'requires_approval' => (bool) $row['requires_approval'], 'archived' => $row['archived_at'] !== null]]);
emit_action_status(true, ['id' => (int) $row['id'], 'did' => ($id === null ? 'Added the leave type ' : 'Saved the leave type ') . $row['name'], 'refresh' => 'leaveTypeChanged']);
saved_go('/settings/leave-types/', 'leaveTypeChanged');
