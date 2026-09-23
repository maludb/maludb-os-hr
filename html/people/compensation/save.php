<?php
declare(strict_types=1);
/** Action `compensation_change_save` (log `compensation_change.create` — never the rate; confirm; agent approval other). HR staff. */
require_once dirname(__DIR__, 3) . '/app/bootstrap.php';
require_once dirname(__DIR__, 3) . '/app/features/people/queries.php';
require_once dirname(__DIR__, 3) . '/app/features/people/present.php';
require_once dirname(__DIR__, 3) . '/app/features/compensation/queries.php';
require_post();
verify_csrf();
require_hr();
$pdo = db();
$id = request_integer('person') ?? request_integer('member_id');
$person = $id === null ? null : find_person($pdo, $id);
if ($person === null) {
    refuse(404, 'No such person here.');
}
$date = request_date('effective_date');
$type = request_string('pay_type');
$rate = request_string('pay_rate');
$currency = strtoupper(request_string('currency')) ?: (string) $pdo->query('SELECT currency FROM mcp_hr_settings')->fetchColumn();
$reason = request_string('reason') ?: null;
$errors = [];
if (!is_string($date)) { $errors[] = 'An effective date is required.'; }
if (!in_array($type, PAY_TYPES, true)) { $errors[] = 'Pay type is salary, hourly, daily or per invoice.'; }
if ($rate === '' || !is_numeric($rate) || (float) $rate < 0 || (float) $rate > 99999999) { $errors[] = 'The rate is a number (a salary is per year).'; }
if (!preg_match('/^[A-Z]{3}$/', $currency)) { $errors[] = 'The currency is a three-letter code.'; }
if ($person['member_kind'] !== 'human') { $errors[] = 'An agent is not an employee.'; }
$renderForm = function (array $errs) use ($pdo, $id, $person, $date, $type, $rate, $currency, $reason): never {
    emit_action_status(false, ['errors' => $errs]);
    if (wants_json()) { respond_invalid($errs); }
    http_response_code(422);
    header('HX-Retarget: #page-content');
    header('HX-Reswap: innerHTML');
    echo view('people/partials/compensation-form.php', ['person' => $person, 'values' => ['effective_date' => is_string($date) ? $date : '', 'pay_type' => $type, 'pay_rate' => $rate, 'currency' => $currency, 'reason' => $reason ?? ''],
        'errors' => $errs, 'current' => pay_history($pdo, $id)['current']]);
    exit;
};
if ($errors !== []) {
    $renderForm($errors);
}
try {
    $row = insert_compensation_change($pdo, $id, $date, $type, number_format((float) $rate, 2, '.', ''), $currency, $reason, (int) current_member_id());
} catch (PDOException $ex) {
    if ($ex->getCode() === '23505') { $renderForm(['A change on that date already exists; delete it first.']); }
    $renderForm([db_message($ex, 'The pay change could not be recorded.')]);
}
// The log says that pay changed, when and what kind — never the figure.
log_activity($pdo, 'compensation_change.create', 'compensation_change', (int) $row['id'], ['after' => ['member_id' => $id, 'effective_date' => $date, 'pay_type' => $type, 'currency' => $currency, 'reason' => $reason]]);
emit_action_status(true, ['id' => (int) $row['id'], 'did' => 'Recorded a ' . pay_type_label($type) . ' change for ' . $person['display_name'] . ' from ' . format_date($date), 'refresh' => 'compensationChangeChanged']);
saved_go('/people/' . $id . '?tab=pay', 'compensationChangeChanged');
