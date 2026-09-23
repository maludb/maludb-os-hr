<?php
declare(strict_types=1);

/** Action `leave_request_cancel` (log `leave_request.cancel`, confirm). The person, or HR staff. */
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_once dirname(__DIR__, 2) . '/app/features/leave/queries.php';

require_post();
verify_csrf();
require_login();
$pdo = db();
$me = (int) current_member_id();
$id = request_integer('leave_request') ?? request_integer('id');
$r = $id === null ? null : find_leave_request($pdo, $id);
if ($r === null) {
    refuse(404, 'No such leave request here.');
}
if ((int) $r['member_id'] !== $me && !hr_is_staff()) {
    refuse(403, 'A request is cancelled by the person or by HR staff.');
}
$pdo->beginTransaction();
try {
    [$row, $message] = cancel_leave_request($pdo, $id, $me, $me === (int) $r['member_id'] ? 'cancelled by the person' : 'cancelled by HR');
    if ($row === null) {
        $pdo->rollBack();
        refuse(409, (string) $message);
    }
    log_activity($pdo, 'leave_request.cancel', 'leave_request', $id, ['before' => ['status' => $r['status']], 'after' => ['status' => 'cancelled', 'by' => $me === (int) $r['member_id'] ? 'person' : 'hr']]);
    $pdo->commit();
} catch (PDOException $ex) {
    $pdo->rollBack();
    refuse(500, db_message($ex, 'The request could not be cancelled.'));
}
emit_action_status(true, ['id' => $id, 'did' => 'Cancelled the ' . $r['leave_type'] . ' request of ' . $r['display_name'] . ' for ' . format_date($r['start_date']), 'refresh' => 'leaveRequestChanged']);
saved_go('/leave/' . $id, 'leaveRequestChanged');
