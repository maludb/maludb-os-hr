<?php
declare(strict_types=1);
/** Action `pay_run_remove_person` (log `pay_run_item.remove`, confirm). HR staff; a draft only. */
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
remove_pay_run_item($pdo, $id);
log_activity($pdo, 'pay_run_item.remove', 'pay_run_item', $id, ['before' => ['pay_run_id' => (int) $item['pay_run_id'], 'member_id' => (int) $item['member_id']]]);
emit_action_status(true, ['id' => $id, 'did' => 'Removed ' . $item['display_name'] . ' from ' . $item['run_no'], 'refresh' => 'payRunChanged']);
saved_go('/payruns/' . (int) $item['pay_run_id'], 'payRunChanged');
