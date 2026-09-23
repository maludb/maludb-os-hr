<?php
declare(strict_types=1);

/** /leave/{id} — one request: days, the balance it draws on, flags, the decision (screen leave-request-view). */
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_once dirname(__DIR__, 2) . '/app/features/leave/queries.php';

require_login();
$pdo = db();
$id = request_integer('id');
$r = $id === null ? null : find_leave_request($pdo, $id);
if ($r === null) {
    respond_not_found('No such leave request here.');
}
$me = (int) current_member_id();
$canDecide = !empty($r['awaiting_me']);
$canCancel = ($r['member_id'] == $me || hr_is_staff()) && ($r['status'] === 'requested' || ($r['status'] === 'approved' && $r['start_date'] >= date('Y-m-d')));
log_activity($pdo, 'screen.view', 'leave_request', $id, ['screen' => 'leave-request-view']);
if (wants_json()) {
    respond_screen(['request' => present_leave_request($r), 'balance' => $r['balance']]);
}
render_screen('Leave request', view('leave/view.php', ['r' => $r, 'me' => $me, 'canDecide' => $canDecide, 'canCancel' => $canCancel, 'staff' => hr_is_staff(), 'tz' => member_timezone()]),
    ['activeNav' => 'leave-requests', 'screen' => 'leave-request-view', 'entity' => 'leave_request', 'recordId' => (string) $id]);
