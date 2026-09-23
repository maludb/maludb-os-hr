<?php
declare(strict_types=1);
/** /reviews/ — reviews to write, and my own (screen reviews-list). Cards. */
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_once dirname(__DIR__, 2) . '/app/features/reviews/queries.php';
require_once dirname(__DIR__, 2) . '/app/features/people/present.php';
require_login();
$pdo = db();
$me = (int) current_member_id();
$filters = ['status' => request_string('status'), 'person' => request_integer('person'), 'reviewer' => request_integer('reviewer'), 'overdue' => request_bool('overdue')];
$page = max(1, (int) (request_integer('page') ?? 1));
$result = find_reviews($pdo, $filters, $page);
$pages = max(1, (int) ceil($result['total'] / REVIEWS_PER_PAGE));
if (wants_json()) {
    log_screen_view($pdo, 'reviews-list');
    respond_screen(['reviews' => array_map('present_review', $result['rows']), 'total' => $result['total'], 'page' => $page, 'pages' => $pages]);
}
$resultsHtml = view('reviews/partials/cards.php', ['rows' => $result['rows'], 'total' => $result['total'], 'page' => $page, 'pages' => $pages, 'filters' => $filters, 'me' => $me]);
if (is_htmx_request() && ($_SERVER['HTTP_HX_TARGET'] ?? '') === 'reviews-list-results') {
    header('Vary: HX-Request');
    echo $resultsHtml;
    exit;
}
log_screen_view($pdo, 'reviews-list');
$canSchedule = hr_is_staff() || (bool) $pdo->query('SELECT count(*) FROM mcp_employment_profiles WHERE manager_member_id = ' . $me)->fetchColumn();
render_screen('Reviews', view('reviews/page.php', ['resultsHtml' => $resultsHtml, 'filters' => $filters, 'canSchedule' => $canSchedule]),
    ['activeNav' => 'reviews-list', 'screen' => 'reviews-list', 'entity' => 'review']);
