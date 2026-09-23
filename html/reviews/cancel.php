<?php
declare(strict_types=1);
/** Action `review_cancel` (log `review.cancel`, confirm). HR staff or the reviewer. */
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_once dirname(__DIR__, 2) . '/app/features/reviews/queries.php';
require_post();
verify_csrf();
require_login();
$pdo = db();
$me = (int) current_member_id();
$id = request_integer('review') ?? request_integer('id');
$r = $id === null ? null : find_review($pdo, $id);
if ($r === null) {
    refuse(404, 'No such review here.');
}
if ((int) $r['reviewer_member_id'] !== $me && !hr_is_staff()) {
    refuse(403, 'A review is cancelled by its reviewer or HR staff.');
}
if (!in_array($r['status'], ['scheduled', 'in_progress'], true)) {
    refuse(409, 'This review is ' . $r['status'] . '.');
}
$reason = request_string('reason') ?: null;
cancel_review($pdo, $id, $reason);
log_activity($pdo, 'review.cancel', 'review', $id, ['before' => ['status' => $r['status']], 'after' => ['status' => 'cancelled', 'reason' => $reason]]);
emit_action_status(true, ['id' => $id, 'did' => 'Cancelled the review of ' . $r['display_name'], 'refresh' => 'reviewChanged']);
saved_go('/reviews/' . $id, 'reviewChanged');
