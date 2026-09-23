<?php
declare(strict_types=1);

/**
 * Action `employment_save` (log `employment_profile.save`). HR staff. Creates or changes the record;
 * a new active record with a start date grants the year's leave. The log records WHICH fields
 * changed, never home contact values.
 */
require_once dirname(__DIR__, 3) . '/app/bootstrap.php';
require_once dirname(__DIR__, 3) . '/app/features/people/queries.php';
require_once dirname(__DIR__, 3) . '/app/features/people/present.php';

require_post();
verify_csrf();
require_hr();
$pdo = db();

$id = request_integer('person') ?? request_integer('member_id');
$person = $id === null ? null : find_person($pdo, $id);
if ($person === null) {
    refuse(404, 'No such person here.');
}
$before = find_employment($pdo, $id);
$isNew = $before === null;

$errors = [];
$f = [];
$f['employee_no'] = request_string('employee_no') ?: null;
if ($f['employee_no'] !== null && mb_strlen($f['employee_no']) > 30) { $errors[] = 'An employee number is up to 30 characters.'; }
$f['employment_type'] = request_string('employment_type', 'employee');
if (!in_array($f['employment_type'], EMPLOYMENT_TYPES, true)) { $errors[] = 'Employment type is employee, contractor or intern.'; }
$f['manager_member_id'] = request_integer('manager') ?? request_integer('manager_member_id');   // the form's name, or the column's (a partial update)
if ($f['manager_member_id'] !== null) {
    $mgr = find_member_by_id($pdo, $f['manager_member_id']);
    if ($mgr === null || $mgr['member_kind'] !== 'human' || $mgr['status'] !== 'active') { $errors[] = 'The manager must be an active person here.'; }
    if ($f['manager_member_id'] === $id) { $errors[] = 'A person cannot be their own manager.'; }
}
$f['work_location'] = request_string('work_location') ?: null;
if ($f['work_location'] !== null && mb_strlen($f['work_location']) > 120) { $errors[] = 'Work location is up to 120 characters.'; }
foreach (['start_date', 'probation_end_date', 'birth_date'] as $d) {
    $v = request_date($d);
    if ($v === false) { $errors[] = ucfirst(str_replace('_', ' ', $d)) . ' is not a date.'; $v = null; }
    $f[$d] = $v;
}
if ($f['probation_end_date'] !== null && $f['start_date'] !== null && $f['probation_end_date'] < $f['start_date']) { $errors[] = 'Probation ends after the start date.'; }
if ($f['birth_date'] !== null && $f['birth_date'] >= date('Y-m-d')) { $errors[] = 'A birth date is in the past.'; }
$f['status'] = request_string('status', 'active');
// An ended record stays ended through this form (ending, and un-ending, are their own decisions).
if (($before['status'] ?? '') === 'ended' && $f['status'] === 'ended') {
    // keep
} elseif (!in_array($f['status'], ['candidate', 'active', 'on_leave'], true)) {
    $errors[] = 'Status is candidate, active or on leave; ending employment is its own action.';
}
$hours = request_string('weekly_hours');
$f['weekly_hours'] = $hours === '' ? null : (is_numeric($hours) && (float) $hours > 0 && (float) $hours <= 168 ? number_format((float) $hours, 2, '.', '') : false);
if ($f['weekly_hours'] === false) { $errors[] = 'Weekly hours is a number above 0.'; $f['weekly_hours'] = null; }
$f['pay_schedule'] = request_string('pay_schedule') ?: null;
if ($f['pay_schedule'] !== null && !in_array($f['pay_schedule'], PAY_SCHEDULES, true)) { $errors[] = 'Pay schedule is weekly, biweekly, semimonthly or monthly.'; }
$f['personal_email'] = strtolower(request_string('personal_email')) ?: null;
if ($f['personal_email'] !== null && filter_var($f['personal_email'], FILTER_VALIDATE_EMAIL) === false) { $errors[] = 'Personal email is not a valid address.'; }
$f['address'] = request_string('address') ?: null;
$f['emergency_contact'] = request_string('emergency_contact') ?: null;
$f['national_id_last4'] = request_string('national_id_last4') ?: null;
if ($f['national_id_last4'] !== null && !preg_match('/^[0-9A-Za-z]{2,4}$/', $f['national_id_last4'])) { $errors[] = 'The id fragment is the last 2 to 4 characters only.'; }
$f['notes'] = request_string('notes') ?: null;
if ($person['member_kind'] !== 'human') { $errors[] = 'An agent is not an employee: employment, pay and leave are for people.'; }

$renderForm = function (array $errs) use ($pdo, $id, $person, $f, $isNew): never {
    emit_action_status(false, ['errors' => $errs]);
    if (wants_json()) { respond_invalid($errs); }
    http_response_code(422);
    header('HX-Retarget: #page-content');
    header('HX-Reswap: innerHTML');
    echo view('people/partials/employment-form.php', ['person' => $person, 'record' => $f + ['member_id' => $id], 'isNew' => $isNew,
        'errors' => $errs, 'managers' => manager_options($pdo, $id)]);
    exit;
};
if ($errors !== []) {
    $renderForm($errors);
}

$pdo->beginTransaction();
try {
    $row = upsert_employment($pdo, $id, $f, (int) current_member_id());
    $changed = [];
    foreach ($f as $k => $v) {
        if ((string) ($before[$k] ?? '') !== (string) ($v ?? '')) { $changed[] = $k; }
    }
    $granted = 0;
    if ($row['status'] === 'active' && $row['start_date'] !== null) {
        foreach (array_unique([(int) substr((string) $row['start_date'], 0, 4), (int) date('Y')]) as $year) {
            $granted += (int) $pdo->query('SELECT hr_grant_annual_leave(' . (int) $id . ', ' . $year . ')')->fetchColumn();
        }
    }
    // Public facts only: the log says which fields changed; home contact values never enter it.
    $public = array_intersect_key($f, array_flip(['employee_no', 'employment_type', 'manager_member_id', 'work_location', 'start_date', 'probation_end_date', 'status', 'weekly_hours', 'pay_schedule']));
    log_activity($pdo, 'employment_profile.save', 'employment_profile', $id, [
        'before' => $before === null ? null : array_intersect_key($before, $public),
        'after' => $public + ['changed' => $changed, 'created' => $isNew, 'leave_balances_granted' => $granted],
    ]);
    $pdo->commit();
} catch (PDOException $ex) {
    $pdo->rollBack();
    if ($ex->getCode() === '23505') { $renderForm(['That employee number belongs to someone else.']); }
    $renderForm([db_message($ex, 'The employment record could not be saved.')]);
}
emit_action_status(true, ['id' => $id, 'did' => ($isNew ? 'Employed ' : 'Updated the employment record of ') . $person['display_name'], 'refresh' => 'employmentProfileChanged']);
saved_go('/people/' . $id . '?tab=employment', 'employmentProfileChanged');
