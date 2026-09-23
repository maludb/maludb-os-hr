<?php
declare(strict_types=1);
/** Action `holiday_save` (log `holiday.save`). HR staff. */
require_once dirname(__DIR__, 3) . '/app/bootstrap.php';
require_once dirname(__DIR__, 3) . '/app/features/settings/queries.php';
require_post();
verify_csrf();
require_hr();
$pdo = db();
$id = request_integer('holiday') ?? request_integer('id');
$h = $id === null ? null : find_holiday($pdo, $id);
if ($id !== null && $h === null) {
    refuse(404, 'No such holiday.');
}
$date = request_date('holiday_date') ?: request_date('date');
$name = request_string('name');
$errors = [];
if (!is_string($date)) { $errors[] = 'The holiday needs a date.'; }
if ($name === '' || mb_strlen($name) > 80) { $errors[] = 'The holiday needs a name (up to 80 characters).'; }
$renderForm = function (array $errs) use ($h, $date, $name): never {
    emit_action_status(false, ['errors' => $errs]);
    if (wants_json()) { respond_invalid($errs); }
    http_response_code(422);
    header('HX-Retarget: #page-content');
    header('HX-Reswap: innerHTML');
    echo view('settings/holidays/form.php', ['h' => $h, 'values' => ['holiday_date' => is_string($date) ? $date : '', 'name' => $name] + ($h ?? []), 'errors' => $errs]);
    exit;
};
if ($errors !== []) {
    $renderForm($errors);
}
try {
    $row = upsert_holiday($pdo, $id, $date, $name, (int) current_member_id());
} catch (PDOException $ex) {
    if ($ex->getCode() === '23505') { $renderForm(['That date is already a holiday.']); }
    $renderForm([db_message($ex, 'The holiday could not be saved.')]);
}
log_activity($pdo, 'holiday.save', 'holiday', (int) $row['id'], ['before' => $h === null ? null : ['holiday_date' => $h['holiday_date'], 'name' => $h['name']], 'after' => ['holiday_date' => $row['holiday_date'], 'name' => $row['name']]]);
emit_action_status(true, ['id' => (int) $row['id'], 'did' => ($id === null ? 'Added the holiday ' : 'Saved the holiday ') . $row['name'] . ' on ' . format_date($row['holiday_date']), 'refresh' => 'holidayChanged']);
saved_go('/settings/holidays/?year=' . substr((string) $row['holiday_date'], 0, 4), 'holidayChanged');
