<?php
declare(strict_types=1);
/** Action `pay_run_create` (log `pay_run.create` — the number, the period, the head-count; never a total). HR staff. */
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_once dirname(__DIR__, 2) . '/app/features/payruns/queries.php';
require_once dirname(__DIR__, 2) . '/app/features/compensation/queries.php';
require_post();
verify_csrf();
require_hr();
$pdo = db();
$f = ['period_start' => request_date('period_start'), 'period_end' => request_date('period_end'), 'pay_date' => request_date('pay_date'),
      'currency' => strtoupper(request_string('currency')) ?: (string) $pdo->query('SELECT currency FROM mcp_hr_settings')->fetchColumn(), 'note' => request_string('note') ?: null];
$people = $_POST['people'] ?? [];
if (is_string($people)) { $people = array_filter(array_map('trim', explode(',', $people))); }
$memberIds = array_values(array_filter(array_map('intval', (array) $people)));
$errors = [];
foreach (['period_start', 'period_end', 'pay_date'] as $k) { if (!is_string($f[$k])) { $errors[] = ucfirst(str_replace('_', ' ', $k)) . ' is required.'; } }
if (is_string($f['period_start']) && is_string($f['period_end']) && $f['period_end'] < $f['period_start']) { $errors[] = 'The period ends after it starts.'; }
if (!preg_match('/^[A-Z]{3}$/', $f['currency'])) { $errors[] = 'The currency is a three-letter code.'; }
if ($f['note'] !== null && mb_strlen($f['note']) > 500) { $errors[] = 'Keep the note under 500 characters.'; }
$renderForm = function (array $errs) use ($pdo, $f): never {
    emit_action_status(false, ['errors' => $errs]);
    if (wants_json()) { respond_invalid($errs); }
    http_response_code(422);
    header('HX-Retarget: #page-content');
    header('HX-Reswap: innerHTML');
    $v = ['period_start' => is_string($f['period_start']) ? $f['period_start'] : '', 'period_end' => is_string($f['period_end']) ? $f['period_end'] : '', 'pay_date' => is_string($f['pay_date']) ? $f['pay_date'] : '', 'currency' => $f['currency'], 'note' => $f['note'] ?? ''];
    echo view('payruns/partials/form.php', ['values' => $v, 'errors' => $errs, 'people' => is_string($f['period_end']) ? eligible_people($pdo, $f['period_end'], $f['currency']) : []]);
    exit;
};
if ($errors !== []) {
    $renderForm($errors);
}
$pdo->beginTransaction();
try {
    $run = create_pay_run($pdo, $f, $memberIds, (int) current_member_id());
    $count = (int) $pdo->query('SELECT count(*) FROM pay_run_items WHERE pay_run_id = ' . (int) $run['id'])->fetchColumn();
    log_activity($pdo, 'pay_run.create', 'pay_run', (int) $run['id'], ['after' => ['run_no' => $run['run_no'], 'period_start' => $f['period_start'], 'period_end' => $f['period_end'], 'pay_date' => $f['pay_date'], 'currency' => $f['currency'], 'people' => $count]]);
    $pdo->commit();
} catch (PDOException $ex) {
    $pdo->rollBack();
    $renderForm([db_message($ex, 'The pay run could not be created.')]);
}
emit_action_status(true, ['id' => (int) $run['id'], 'did' => 'Drafted pay run ' . $run['run_no'] . ' for ' . $count . ' people, ' . format_date($f['period_start']) . ' to ' . format_date($f['period_end']), 'refresh' => 'payRunChanged']);
saved_go('/payruns/' . (int) $run['id'], 'payRunChanged');
