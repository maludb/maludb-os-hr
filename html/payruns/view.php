<?php
declare(strict_types=1);
/** /payruns/{id} — one run: people, lines, totals, what is missing a base line, the lifecycle buttons (screen pay-run-view). HR staff. */
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_once dirname(__DIR__, 2) . '/app/features/payruns/queries.php';
require_once dirname(__DIR__, 2) . '/app/features/compensation/queries.php';
require_hr();
$pdo = db();
$id = request_integer('id');
$run = $id === null ? null : find_pay_run($pdo, $id);
if ($run === null) {
    respond_not_found('No such pay run here.');
}
log_activity($pdo, 'screen.view', 'pay_run', $id, ['screen' => 'pay-run-view']);
if (wants_json()) {
    respond_screen(['pay_run' => present_pay_run($run), 'items' => array_map(static fn (array $i): array => ['pay_run_item_id' => (int) $i['pay_run_item_id'], 'member_id' => (int) $i['member_id'], 'display_name' => $i['display_name'], 'base_note' => $i['base_note'], 'gross' => (float) $i['gross_amount'], 'net' => (float) $i['net_amount']], $run['items'])]);
}
$addable = [];
if ($run['status'] === 'draft') {
    $on = array_map('intval', array_column($run['items'], 'member_id'));
    $addable = array_values(array_filter(eligible_people($pdo, $run['period_end'], $run['currency']), static fn (array $p): bool => !in_array((int) $p['member_id'], $on, true)));
}
render_screen($run['run_no'], view('payruns/view.php', ['run' => $run, 'addable' => $addable, 'tz' => member_timezone()]),
    ['activeNav' => 'pay-runs', 'screen' => 'pay-run-view', 'entity' => 'pay_run', 'recordId' => (string) $id]);
