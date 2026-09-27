<?php
declare(strict_types=1);
/** Actions `review_schedule` (no `review`; log `review.schedule`) and `review_save` (log `review.save` — which fields changed, never the text). */
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_once dirname(__DIR__, 2) . '/app/features/reviews/queries.php';
require_once dirname(__DIR__, 2) . '/app/features/people/queries.php';
require_once dirname(__DIR__, 2) . '/app/features/people/present.php';
require_post();
verify_csrf();
require_login();
$pdo = db();
$me = (int) current_member_id();
$id = request_integer('review') ?? request_integer('id');

if ($id !== null) {
    $r = find_review($pdo, $id);
    if ($r === null) {
        refuse(404, 'No such review here.');
    }
    if ((int) $r['reviewer_member_id'] !== $me && !hr_is_staff()) {
        refuse(403, 'A review is written by its reviewer or HR staff.');
    }
    if (in_array($r['status'], ['completed', 'cancelled'], true)) {
        refuse(409, 'This review is ' . $r['status'] . '.');
    }
    $f = [];
    $errors = [];
    $rating = request_string('rating');
    $f['rating'] = $rating === '' ? null : (ctype_digit($rating) && (int) $rating >= 1 && (int) $rating <= 5 ? (int) $rating : false);
    if ($f['rating'] === false) { $errors[] = 'The rating is 1 to 5.'; $f['rating'] = null; }
    foreach (['summary', 'strengths', 'improvements', 'goals'] as $k) {
        $f[$k] = array_key_exists($k, $_POST) ? (request_string($k) ?: null) : $r[$k];
        if ($f[$k] !== null && mb_strlen($f[$k]) > 5000) { $errors[] = ucfirst($k) . ' is up to 5,000 characters.'; }
    }
    if (!array_key_exists('rating', $_POST)) { $f['rating'] = $r['rating']; }
    if ($errors !== []) {
        emit_action_status(false, ['errors' => $errors]);
        if (wants_json()) { respond_invalid($errors); }
        http_response_code(422);
        header('HX-Retarget: #page-content');
        header('HX-Reswap: innerHTML');
        echo view('reviews/partials/write-form.php', ['r' => $r, 'values' => $f + $r, 'errors' => $errors]);
        exit;
    }
    $row = save_review($pdo, $id, $f);
    $changed = array_keys(array_filter($f, static fn ($v, string $k): bool => (string) ($r[$k] ?? '') !== (string) ($v ?? ''), ARRAY_FILTER_USE_BOTH));
    log_activity($pdo, 'review.save', 'review', $id, ['after' => ['changed' => $changed, 'rating' => $f['rating'], 'status' => $row['status'], 'member_id' => (int) $row['member_id']]]);
    emit_action_status(true, ['id' => $id, 'did' => 'Saved the review of ' . $r['display_name'], 'refresh' => 'reviewChanged']);
    saved_go('/reviews/' . $id, 'reviewChanged');
}

// Schedule.
$memberId = request_integer('person') ?? request_integer('member_id');
$kind = request_string('kind', 'annual');
$due = request_date('due_date');
$reviewerId = request_integer('reviewer') ?? request_integer('reviewer_member_id');
$ps = request_date('period_start');
$pe = request_date('period_end');
$errors = [];
$target = $memberId === null ? null : find_member_by_id($pdo, $memberId);
if ($target === null || $target['member_kind'] !== 'human') { $errors[] = 'Choose the person.'; }
if (!in_array($kind, REVIEW_KINDS, true)) { $errors[] = 'The kind is annual, probation, quarterly or other.'; }
if (!is_string($due)) { $errors[] = 'A due date is required.'; }
if ($ps === false || $pe === false) { $errors[] = 'The period dates are dates.'; }
if (is_string($ps) && is_string($pe) && $pe < $ps) { $errors[] = 'The period ends after it starts.'; }
if ($memberId !== null && !hr_is_staff() && !is_manager_of($memberId)) { $errors[] = 'You schedule reviews for your reports; HR staff for everyone.'; }
if ($reviewerId === null && $memberId !== null) {
    $st = $pdo->prepare('SELECT manager_member_id FROM mcp_employment_profiles WHERE member_id = :m');
    $st->execute(['m' => $memberId]);
    $reviewerId = ($v = $st->fetchColumn()) ? (int) $v : null;
    if ($reviewerId === null) { $errors[] = 'Name a reviewer: this person has no manager.'; }
}
if ($reviewerId !== null) {
    $rv = find_member_by_id($pdo, $reviewerId);
    if ($rv === null || $rv['member_kind'] !== 'human' || $rv['status'] !== 'active') { $errors[] = 'The reviewer must be an active person here.'; }
    if ($reviewerId === $memberId) { $errors[] = 'Nobody reviews themself.'; }
}
$renderForm = function (array $errs) use ($pdo, $me, $memberId, $kind, $due, $reviewerId, $ps, $pe): never {
    emit_action_status(false, ['errors' => $errs]);
    if (wants_json()) { respond_invalid($errs); }
    http_response_code(422);
    header('HX-Retarget: #page-content');
    header('HX-Reswap: innerHTML');
    $people = array_values(array_filter(review_people_options($pdo), static fn (array $p): bool => hr_is_staff() || is_manager_of((int) $p['member_id'])));
    echo view('reviews/partials/form.php', ['values' => ['member_id' => $memberId, 'kind' => $kind, 'due_date' => is_string($due) ? $due : '', 'reviewer_member_id' => $reviewerId,
        'period_start' => is_string($ps) ? $ps : '', 'period_end' => is_string($pe) ? $pe : ''], 'errors' => $errs, 'people' => $people, 'reviewers' => manager_options($pdo, null), 'me' => $me]);
    exit;
};
if ($errors !== []) {
    $renderForm($errors);
}
try {
    $row = schedule_review($pdo, $memberId, $reviewerId, $kind, $due, is_string($ps) ? $ps : null, is_string($pe) ? $pe : null, $me);
} catch (PDOException $ex) {
    $renderForm([db_message($ex, 'The review could not be scheduled.')]);
}
log_activity($pdo, 'review.schedule', 'review', (int) $row['id'], ['after' => ['member_id' => $memberId, 'reviewer_member_id' => $reviewerId, 'kind' => $kind, 'due_date' => $due]]);
emit_action_status(true, ['id' => (int) $row['id'], 'did' => 'Scheduled ' . (in_array($kind, ['annual', 'other'], true) ? 'an ' : 'a ') . $kind . ' review of ' . $target['display_name'] . ' due ' . format_date($due), 'refresh' => 'reviewChanged']);
saved_go('/reviews/' . (int) $row['id'], 'reviewChanged');
