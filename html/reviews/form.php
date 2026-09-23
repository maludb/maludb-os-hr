<?php
declare(strict_types=1);
/** /reviews/new — schedule (screen review-add); /reviews/{id}/edit — write (screen review-edit). */
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_once dirname(__DIR__, 2) . '/app/features/reviews/queries.php';
require_once dirname(__DIR__, 2) . '/app/features/people/queries.php';
require_once dirname(__DIR__, 2) . '/app/features/people/present.php';
require_login();
require_human();
$pdo = db();
$me = (int) current_member_id();
$id = request_integer('id') ?? request_integer('review');
if ($id !== null) {
    $r = find_review($pdo, $id);
    if ($r === null) {
        respond_not_found('No such review here.');
    }
    if ((int) $r['reviewer_member_id'] !== $me && !hr_is_staff()) {
        refuse(403, 'A review is written by its reviewer or HR staff.');
    }
    if (in_array($r['status'], ['completed', 'cancelled'], true)) {
        refuse(409, 'This review is ' . $r['status'] . '.');
    }
    log_screen_view($pdo, 'review-edit');
    render_screen('Write · ' . $r['display_name'], view('reviews/partials/write-form.php', ['r' => $r, 'values' => $r, 'errors' => []]),
        ['activeNav' => 'reviews-list', 'screen' => 'review-edit', 'entity' => 'review', 'recordId' => (string) $id]);
}
$people = array_values(array_filter(review_people_options($pdo), static fn (array $p): bool => hr_is_staff() || (int) ($p['manager_member_id'] ?? 0) === $me));
if ($people === []) {
    refuse(403, 'You schedule reviews for your reports; HR staff for everyone.');
}
$values = ['member_id' => request_integer('person'), 'kind' => request_string('kind', 'annual'), 'due_date' => request_date('due_date') ?: '', 'reviewer_member_id' => null, 'period_start' => '', 'period_end' => ''];
log_screen_view($pdo, 'review-add');
render_screen('Schedule a review', view('reviews/partials/form.php', ['values' => $values, 'errors' => [], 'people' => $people, 'reviewers' => manager_options($pdo, null), 'me' => $me]),
    ['activeNav' => 'reviews-list', 'screen' => 'review-add', 'entity' => 'review']);
