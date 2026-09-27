<?php
declare(strict_types=1);
/** Action `compensation_change_delete` (log `compensation_change.delete`, confirm, agent approval deletion). HR staff; a future-dated change only. */
require_once dirname(__DIR__, 3) . '/app/bootstrap.php';
require_once dirname(__DIR__, 3) . '/app/features/compensation/queries.php';
require_post();
verify_csrf();
require_pay();
$pdo = db();
$id = request_integer('compensation_change') ?? request_integer('id');
$c = $id === null ? null : find_compensation_change($pdo, $id);
if ($c === null) {
    refuse(404, 'No such pay change.');
}
if ($c['effective_date'] <= date('Y-m-d')) {
    refuse(409, 'A change already in force is the record; record a new change instead.');
}
if (!delete_compensation_change($pdo, $id)) {
    refuse(409, 'That change could not be deleted.');
}
log_activity($pdo, 'compensation_change.delete', 'compensation_change', $id, ['before' => ['member_id' => (int) $c['member_id'], 'effective_date' => $c['effective_date'], 'pay_type' => $c['pay_type'], 'currency' => $c['currency']]]);
emit_action_status(true, ['id' => $id, 'did' => 'Deleted the pay change of ' . $c['display_name'] . ' dated ' . format_date($c['effective_date']), 'refresh' => 'compensationChangeChanged']);
saved_go('/people/' . (int) $c['member_id'] . '?tab=pay', 'compensationChangeChanged');
