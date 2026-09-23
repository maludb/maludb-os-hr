<?php
declare(strict_types=1);

/** /people/ — the directory with employment beside it (screen people-list). Pattern B; the results region alone on search/paging. */
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_once dirname(__DIR__, 2) . '/app/features/people/queries.php';
require_once dirname(__DIR__, 2) . '/app/features/people/present.php';

require_login();
$pdo = db();
$filters = [
    'q' => request_string('q'), 'department' => request_integer('department'), 'status' => request_string('status'),
    'type' => request_string('type'), 'employed' => request_string('employed'), 'kind' => request_string('kind'),
    'include_inactive' => request_bool('inactive'),
];
$sort = request_string('sort', 'name');
$page = max(1, (int) (request_integer('page') ?? 1));
$result = find_people($pdo, $filters, $sort, $page);
$pages = max(1, (int) ceil($result['total'] / PEOPLE_PER_PAGE));

if (wants_json()) {
    log_screen_view($pdo, 'people-list');
    respond_screen(['people' => array_map('present_person_card', $result['rows']), 'total' => $result['total'], 'page' => $page, 'pages' => $pages]);
}
$resultsHtml = view('people/partials/cards.php', ['rows' => $result['rows'], 'total' => $result['total'], 'page' => $page, 'pages' => $pages,
    'filters' => $filters, 'sort' => $sort]);
if (is_htmx_request() && ($_SERVER['HTTP_HX_TARGET'] ?? '') === 'people-list-results') {
    header('Vary: HX-Request');
    echo $resultsHtml;
    exit;
}
log_screen_view($pdo, 'people-list');
render_screen('People', view('people/page.php', ['resultsHtml' => $resultsHtml, 'filters' => $filters, 'sort' => $sort,
    'departments' => department_options($pdo), 'canInvite' => hr_is_staff() || is_dept_admin(), 'staff' => hr_is_staff()]),
    ['activeNav' => 'people-list', 'screen' => 'people-list', 'entity' => 'member']);
