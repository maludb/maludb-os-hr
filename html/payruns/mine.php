<?php
declare(strict_types=1);
/** /payruns/mine — the caller's own payslips: their items on approved and paid runs (screen payslip). */
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_once dirname(__DIR__, 2) . '/app/features/payruns/queries.php';
require_once dirname(__DIR__, 2) . '/app/features/compensation/queries.php';
require_login();
require_human();
$pdo = db();
$me = (int) current_member_id();
$items = my_payslips($pdo, $me);
log_screen_view($pdo, 'payslip');
if (wants_json()) {
    respond_screen(['payslips' => array_map(static fn (array $i): array => ['run_no' => $i['run_no'], 'pay_date' => $i['pay_date'], 'gross' => (float) $i['gross_amount'], 'net' => (float) $i['net_amount'], 'currency' => $i['currency']], $items)]);
}
render_screen('My payslips', view('payruns/mine.php', ['items' => $items, 'run' => request_string('run')]), ['activeNav' => 'payslip', 'screen' => 'payslip', 'entity' => 'pay_run_item']);
