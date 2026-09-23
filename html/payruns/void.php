<?php
declare(strict_types=1);
/** Action `pay_run_void` (log `pay_run.void`). HR staff. No money moves here. */
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
$extra = [];
$extra['reason'] = request_string('reason');
if ($extra['reason'] === '') {
    refuse(422, 'A reason is required to void a pay run.');
}
$pdo->beginTransaction();
try {
    $row = set_pay_run_status($pdo, $id, 'void', (int) current_member_id(), $extra);
    log_activity($pdo, 'pay_run.void', 'pay_run', $id, ['before' => ['status' => $run['status']], 'after' => ['status' => 'void', 'run_no' => $run['run_no'], 'period_start' => $run['period_start'], 'period_end' => $run['period_end'], 'people' => (int) $run['people']] + array_intersect_key($extra, ['reason' => 1, 'paid_at' => 1])]);
    $pdo->commit();
} catch (RuntimeException $ex) {
    $pdo->rollBack();
    refuse(409, $ex->getMessage());
} catch (PDOException $ex) {
    $pdo->rollBack();
    refuse(500, db_message($ex, 'The pay run could not be changed.'));
}
emit_action_status(true, ['id' => $id, 'did' => 'Voided pay run ' . $run['run_no'], 'refresh' => 'payRunChanged']);
saved_go('/payruns/' . $id, 'payRunChanged');
