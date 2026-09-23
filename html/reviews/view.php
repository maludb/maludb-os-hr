<?php
declare(strict_types=1);
/** /reviews/{id} — one review, and the person's comment once shared (screen review-view). */
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_once dirname(__DIR__, 2) . '/app/features/reviews/queries.php';
require_login();
$pdo = db();
$me = (int) current_member_id();
$id = request_integer('id');
$r = $id === null ? null : find_review($pdo, $id);
if ($r === null) {
    respond_not_found('No such review here.');
}
$staff = hr_is_staff();
$isReviewer = (int) $r['reviewer_member_id'] === $me;
$isSubject = (int) $r['member_id'] === $me;
$open = in_array($r['status'], ['scheduled', 'in_progress'], true);
log_activity($pdo, 'screen.view', 'review', $id, ['screen' => 'review-view']);
if (wants_json()) {
    respond_screen(['review' => present_review($r) + ['summary' => $r['summary'], 'strengths' => $r['strengths'], 'improvements' => $r['improvements'], 'goals' => $r['goals'], 'employee_comment' => $r['employee_comment']]]);
}
render_screen('Review · ' . $r['display_name'], view('reviews/view.php', ['r' => $r, 'canWrite' => $open && ($isReviewer || $staff), 'canCancel' => $open && ($isReviewer || $staff),
    'canComment' => $isSubject && $r['shared_at'] !== null && $r['employee_comment'] === null, 'tz' => member_timezone()]),
    ['activeNav' => 'reviews-list', 'screen' => 'review-view', 'entity' => 'review', 'recordId' => (string) $id]);
