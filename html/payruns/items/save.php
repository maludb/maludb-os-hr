<?php
declare(strict_types=1);
/** Action `pay_run_item_save` (log `pay_run_item.save` — hours, days, line count; no amounts). HR staff; a draft only. */
require_once dirname(__DIR__, 3) . '/app/bootstrap.php';
require_once dirname(__DIR__, 3) . '/app/features/payruns/queries.php';
require_once dirname(__DIR__, 3) . '/app/features/compensation/queries.php';
require_post();
verify_csrf();
require_hr();
$pdo = db();
$id = request_integer('pay_run_item') ?? request_integer('id');
$item = $id === null ? null : find_pay_run_item($pdo, $id);
if ($item === null) {
    refuse(404, 'No such pay run item.');
}
if ($item['run_status'] !== 'draft') {
    refuse(409, 'This pay run is ' . $item['run_status'] . '; only a draft can be changed.');
}
$hours = request_string('hours');
$days = request_string('days');
$note = request_string('note') ?: null;
$errors = [];
foreach (['hours' => $hours, 'days' => $days] as $k => $v) {
    if ($v !== '' && (!is_numeric($v) || (float) $v < 0 || (float) $v > 1000)) { $errors[] = ucfirst($k) . ' is a number.'; }
}
$lines = [];
if (isset($_POST['lines']) && is_string($_POST['lines'])) {
    [$lines, $lineErrors] = parse_lines_text($_POST['lines']);
    $errors = array_merge($errors, $lineErrors);
} else {
    $kinds = (array) ($_POST['line_kind'] ?? []);
    foreach ($kinds as $i => $kind) {
        $code = trim((string) ($_POST['line_code'][$i] ?? ''));
        $amount = trim((string) ($_POST['line_amount'][$i] ?? ''));
        if ($kind === '' && $code === '' && $amount === '') { continue; }
        if (!in_array($kind, PAY_LINE_KINDS, true)) { $errors[] = 'Line ' . ($i + 1) . ': the kind is earning, deduction, employer cost or reimbursement.'; continue; }
        if ($code === '') { $errors[] = 'Line ' . ($i + 1) . ' needs a code (tax, pension, bonus…).'; continue; }
        if (!is_numeric($amount) || (float) $amount < 0) { $errors[] = 'Line ' . ($i + 1) . ': the amount is a number.'; continue; }
        $q = trim((string) ($_POST['line_quantity'][$i] ?? ''));
        $r = trim((string) ($_POST['line_rate'][$i] ?? ''));
        $lines[] = ['kind' => $kind, 'code' => mb_substr($code, 0, 40), 'description' => trim((string) ($_POST['line_description'][$i] ?? '')) ?: null,
            'quantity' => is_numeric($q) ? $q : null, 'rate' => is_numeric($r) ? $r : null, 'amount' => number_format((float) $amount, 2, '.', '')];
    }
}
if ($errors !== []) {
    emit_action_status(false, ['errors' => $errors]);
    if (wants_json()) { respond_invalid($errors); }
    http_response_code(422);
    header('HX-Retarget: #page-content');
    header('HX-Reswap: innerHTML');
    $basis = pay_basis_for($pdo, (int) $item['member_id'], (string) $item['period_end']);
    echo view('payruns/partials/item-form.php', ['item' => ['hours' => $hours, 'note' => $note] + $item, 'basis' => $basis['pay_type'] === null ? null : $basis, 'errors' => $errors, 'keyed' => $lines]);
    exit;
}
$pdo->beginTransaction();
try {
    $row = save_pay_run_item($pdo, $id, $hours === '' ? null : number_format((float) $hours, 2, '.', ''), $days === '' ? null : number_format((float) $days, 2, '.', ''), $lines, $note);
    log_activity($pdo, 'pay_run_item.save', 'pay_run_item', $id, ['after' => ['pay_run_id' => (int) $item['pay_run_id'], 'member_id' => (int) $item['member_id'], 'hours' => $hours ?: null, 'days' => $days ?: null, 'lines' => count($lines), 'base_note' => $row['base_note']]]);
    $pdo->commit();
} catch (RuntimeException $ex) {
    $pdo->rollBack();
    refuse(409, $ex->getMessage());
} catch (PDOException $ex) {
    $pdo->rollBack();
    refuse(500, db_message($ex, 'The item could not be saved.'));
}
emit_action_status(true, ['id' => $id, 'did' => 'Keyed ' . $item['display_name'] . ' on ' . $item['run_no'] . ($row['base_note'] ? ' — ' . $row['base_note'] : ''), 'refresh' => 'payRunChanged']);
saved_go('/payruns/' . (int) $item['pay_run_id'], 'payRunChanged');
