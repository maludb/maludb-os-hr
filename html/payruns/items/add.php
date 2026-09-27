<?php
declare(strict_types=1);
/** Action `pay_run_add_person` (log `pay_run_item.add`). HR staff; a draft only. */
require_once dirname(__DIR__, 3) . '/app/bootstrap.php';
require_once dirname(__DIR__, 3) . '/app/features/payruns/queries.php';
require_once dirname(__DIR__, 3) . '/app/features/compensation/queries.php';
require_post();
verify_csrf();
require_pay();
$pdo = db();
$runId = request_integer('pay_run') ?? request_integer('id');
$memberId = request_integer('person') ?? request_integer('member_id');
$run = $runId === null ? null : find_pay_run($pdo, $runId);
if ($run === null) {
    refuse(404, 'No such pay run here.');
}
if ($run['status'] !== 'draft') {
    refuse(409, 'This pay run is ' . $run['status'] . '; only a draft can be changed.');
}
$person = null;
foreach (eligible_people($pdo, $run['period_end'], $run['currency']) as $p) {
    if ((int) $p['member_id'] === $memberId) { $person = $p; }
}
if ($person === null) {
    refuse(422, 'That person is not employed now.');
}
try {
    $item = add_pay_run_item($pdo, $runId, $person, $run['currency']);
} catch (PDOException $ex) {
    if ($ex->getCode() === '23505') { refuse(409, $person['display_name'] . ' is on this run already.'); }
    refuse(500, db_message($ex, 'The person could not be added.'));
}
log_activity($pdo, 'pay_run_item.add', 'pay_run_item', (int) $item['id'], ['after' => ['pay_run_id' => $runId, 'member_id' => $memberId, 'base_note' => $person['base_note']]]);
emit_action_status(true, ['id' => (int) $item['id'], 'did' => 'Added ' . $person['display_name'] . ' to ' . $run['run_no'] . ($person['base_note'] ? ' — ' . $person['base_note'] : ''), 'refresh' => 'payRunChanged']);
saved_go('/payruns/' . $runId, 'payRunChanged');
