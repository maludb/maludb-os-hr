<?php
declare(strict_types=1);
/** Action `pay_run_delete` (log `pay_run.delete`, confirm, agent approval deletion). HR staff; a draft only. */
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_once dirname(__DIR__, 2) . '/app/features/payruns/queries.php';
require_once dirname(__DIR__, 2) . '/app/features/compensation/queries.php';
require_post();
verify_csrf();
require_hr();
$pdo = db();
$id = request_integer('pay_run') ?? request_integer('id');
$run = $id === null ? null : find_pay_run($pdo, $id);
if ($run === null) {
    refuse(404, 'No such pay run here.');
}
if ($run['status'] !== 'draft') {
    refuse(409, 'Only a draft is deleted; a ' . $run['status'] . ' run is voided.');
}
delete_pay_run($pdo, $id);
log_activity($pdo, 'pay_run.delete', 'pay_run', $id, ['before' => ['run_no' => $run['run_no'], 'period_start' => $run['period_start'], 'period_end' => $run['period_end'], 'people' => (int) $run['people']]]);
emit_action_status(true, ['id' => $id, 'did' => 'Deleted the draft pay run ' . $run['run_no'], 'refresh' => 'payRunChanged']);
saved_go('/payruns/', 'payRunChanged');
