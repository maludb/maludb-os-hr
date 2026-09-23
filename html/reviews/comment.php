<?php
declare(strict_types=1);
/** Action `review_comment` (log `review.comment`). The person reviewed, once it is shared, once. */
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
if ((int) $r['member_id'] !== $me) {
    refuse(403, 'Only the person reviewed comments.');
}
if ($r['shared_at'] === null) {
    refuse(409, 'This review has not been shared with you yet.');
}
if ($r['employee_comment'] !== null) {
    refuse(409, 'You have commented on this review already.');
}
$comment = request_string('comment');
if ($comment === '' || mb_strlen($comment) > 5000) {
    refuse(422, 'A comment is 1 to 5,000 characters.');
}
comment_review($pdo, $id, $comment);
log_activity($pdo, 'review.comment', 'review', $id, ['after' => ['length' => mb_strlen($comment)]]);
emit_action_status(true, ['id' => $id, 'did' => 'Added your comment to the review', 'refresh' => 'reviewChanged']);
saved_go('/reviews/' . $id, 'reviewChanged');
