<?php
declare(strict_types=1);

/** Action `leave_balance_adjust` (log `leave_balance.adjust`, confirm, agent approval other). HR staff. */
require_once dirname(__DIR__, 3) . '/app/bootstrap.php';
require_once dirname(__DIR__, 3) . '/app/features/leave/queries.php';

require_post();
verify_csrf();
require_hr();
$pdo = db();
$memberId = request_integer('person') ?? request_integer('member_id');
$typeId = request_integer('leave_type') ?? request_integer('leave_type_id');
$year = request_integer('year');
$accrued = request_string('accrued_days');
$carried = request_string('carried_days');
$reason = request_string('reason');
$errors = [];
if ($memberId === null || ($t = find_member_by_id($pdo, $memberId)) === null || $t['member_kind'] !== 'human') { $errors[] = 'Choose a person.'; }
if ($typeId === null) { $errors[] = 'Choose the leave type.'; }
if ($year === null || $year < 2000 || $year > 2200) { $errors[] = 'The year is a four-digit year.'; }
foreach (['accrued_days' => $accrued, 'carried_days' => $carried] as $k => $v) {
    if ($v === '' || !is_numeric($v) || (float) $v < 0 || (float) $v > 400 || fmod((float) $v * 2, 1) != 0) { $errors[] = ucfirst(str_replace('_', ' ', $k)) . ' is a number of days, in half days.'; }
}
if ($reason === '') { $errors[] = 'A reason is required: it is the audit trail.'; }
$renderForm = function (array $errs) use ($pdo, $memberId, $typeId, $year, $accrued, $carried, $reason): never {
    emit_action_status(false, ['errors' => $errs]);
    if (wants_json()) { respond_invalid($errs); }
    http_response_code(422);
    header('HX-Retarget: #page-content');
    header('HX-Reswap: innerHTML');
    echo view('leave/partials/balance-form.php', ['values' => ['member_id' => $memberId, 'leave_type_id' => $typeId, 'year' => $year ?? (int) date('Y'), 'accrued_days' => $accrued, 'carried_days' => $carried, 'reason' => $reason],
        'errors' => $errs, 'types' => leave_types_active($pdo), 'people' => leave_people_options($pdo)]);
    exit;
};
if ($errors !== []) {
    $renderForm($errors);
}
$pdo->beginTransaction();
try {
    [$before, $after] = adjust_leave_balance($pdo, $memberId, $typeId, $year, number_format((float) $accrued, 2, '.', ''), number_format((float) $carried, 2, '.', ''));
    log_activity($pdo, 'leave_balance.adjust', 'leave_balance', (int) $after['id'], ['before' => ['accrued_days' => (float) $before['accrued_days'], 'carried_days' => (float) $before['carried_days']],
        'after' => ['accrued_days' => (float) $after['accrued_days'], 'carried_days' => (float) $after['carried_days'], 'reason' => $reason, 'member_id' => $memberId, 'year' => $year]]);
    $pdo->commit();
} catch (PDOException $ex) {
    $pdo->rollBack();
    $renderForm([db_message($ex, 'The balance could not be adjusted.')]);
}
emit_action_status(true, ['id' => (int) $after['id'], 'did' => 'Set the ' . $year . ' balance of ' . $t['display_name'] . ' to ' . days_label($after['accrued_days']) . ' accrued and ' . days_label($after['carried_days']) . ' carried', 'refresh' => 'leaveBalanceChanged']);
saved_go('/leave/balances/?year=' . $year . '&person=' . $memberId, 'leaveBalanceChanged');
