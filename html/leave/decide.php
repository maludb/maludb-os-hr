<?php
declare(strict_types=1);

/** Action `leave_request_decide` (log `leave_request.decide`, agent approval other). The manager, an admin of the department, HR — never the requester. */
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
$decision = request_string('decision');
if (!in_array($decision, ['approve', 'reject'], true)) {
    refuse(422, 'The decision is approve or reject.');
}
if ((int) $r['member_id'] === $me) {
    refuse(403, 'Nobody decides their own leave.');
}
if (!hr_is_staff() && !is_manager_of((int) $r['member_id']) && !can_admin_member((int) $r['member_id'])) {
    refuse(403, 'This is decided by the person\'s manager, an administrator of their department, or HR staff.');
}
$override = request_bool('override') && hr_is_staff();
$note = request_string('note') ?: null;
$pdo->beginTransaction();
try {
    [$row, $message] = decide_leave_request($pdo, $id, $decision, $note, $override, $me);
    if ($row === null) {
        $pdo->rollBack();
        refuse($message !== null && str_starts_with($message, 'Beyond') ? 422 : 409, (string) $message);
    }
    log_activity($pdo, 'leave_request.decide', 'leave_request', $id, ['before' => ['status' => 'requested'],
        'after' => ['status' => $row['status'], 'decision' => $decision, 'override' => $override, 'days' => (float) $row['days'], 'member_id' => (int) $row['member_id']]]);
    $pdo->commit();
} catch (PDOException $ex) {
    $pdo->rollBack();
    refuse(500, db_message($ex, 'The decision could not be saved.'));
}
$did = ($decision === 'approve' ? 'Approved ' : 'Rejected ') . days_label($row['days']) . ' day' . ((float) $row['days'] == 1 ? '' : 's') . ' of ' . $r['leave_type'] . ' for ' . $r['display_name'];
emit_action_status(true, ['id' => $id, 'did' => $did, 'refresh' => 'leaveRequestChanged']);
saved_go('/leave/' . $id, 'leaveRequestChanged');
