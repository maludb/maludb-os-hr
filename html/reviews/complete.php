<?php
declare(strict_types=1);
/** Action `review_complete` (log `review.complete`, confirm, agent approval other). The reviewer or HR staff. */
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
    refuse(403, 'A review is completed by its reviewer or HR staff.');
}
if (!in_array($r['status'], ['scheduled', 'in_progress'], true)) {
    refuse(409, 'This review is ' . $r['status'] . '.');
}
if ($r['rating'] === null && ($r['summary'] ?? '') === '') {
    refuse(422, 'Write the review first: a rating or a summary.');
}
$share = request_bool('share');
$row = complete_review($pdo, $id, $share);
log_activity($pdo, 'review.complete', 'review', $id, ['after' => ['shared' => $row['shared_at'] !== null, 'rating' => $row['rating'], 'member_id' => (int) $row['member_id']]]);
emit_action_status(true, ['id' => $id, 'did' => 'Completed the review of ' . $r['display_name'] . ($share ? ' and shared it with them' : ''), 'refresh' => 'reviewChanged']);
saved_go('/reviews/' . $id, 'reviewChanged');
