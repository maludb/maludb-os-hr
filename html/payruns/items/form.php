<?php
declare(strict_types=1);
/** /payruns/items/{id}/edit — key one person's hours or days and lines on a draft run (screen pay-run-item-edit). HR staff. */
require_once dirname(__DIR__, 3) . '/app/bootstrap.php';
require_once dirname(__DIR__, 3) . '/app/features/payruns/queries.php';
require_once dirname(__DIR__, 3) . '/app/features/compensation/queries.php';
require_hr();
$pdo = db();
$id = request_integer('id') ?? request_integer('pay_run_item');
$item = $id === null ? null : find_pay_run_item($pdo, $id);
if ($item === null) {
    respond_not_found('No such pay run item.');
}
if ($item['run_status'] !== 'draft') {
    refuse(409, 'This pay run is ' . $item['run_status'] . '; only a draft can be changed.');
}
$basis = pay_basis_for($pdo, (int) $item['member_id'], (string) $item['period_end']);
if ($basis['pay_type'] === null) { $basis = null; }
log_screen_view($pdo, 'pay-run-item-edit');
render_screen($item['display_name'] . ' · ' . $item['run_no'], view('payruns/partials/item-form.php', ['item' => $item, 'basis' => $basis, 'errors' => []]),
    ['activeNav' => 'pay-runs', 'screen' => 'pay-run-item-edit', 'entity' => 'pay_run_item', 'recordId' => (string) $id]);
