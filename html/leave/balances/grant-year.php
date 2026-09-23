<?php
declare(strict_types=1);

/** Action `leave_grant_year` (log `leave_balance.grant_year`, confirm, agent approval other). HR staff. Idempotent. */
require_once dirname(__DIR__, 3) . '/app/bootstrap.php';
require_once dirname(__DIR__, 3) . '/app/features/leave/queries.php';

require_post();
verify_csrf();
require_hr();
$pdo = db();
$year = request_integer('year');
if ($year === null || $year < 2000 || $year > 2200) {
    refuse(422, 'The year is a four-digit year.');
}
$pdo->beginTransaction();
try {
    [$people, $granted] = grant_leave_year($pdo, $year);
    log_activity($pdo, 'leave_balance.grant_year', null, null, ['after' => ['year' => $year, 'people' => $people, 'granted' => $granted]]);
    $pdo->commit();
} catch (PDOException $ex) {
    $pdo->rollBack();
    refuse(500, db_message($ex, 'The year could not be granted.'));
}
emit_action_status(true, ['did' => "Granted {$year}: {$granted} new balance" . ($granted === 1 ? '' : 's') . " across {$people} people", 'refresh' => 'leaveBalanceChanged']);
saved_go('/leave/balances/?year=' . $year, 'leaveBalanceChanged');
