<?php
declare(strict_types=1);

/** /departments/ — the departments as cards (screen departments-list). */
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_once dirname(__DIR__, 2) . '/app/features/directory/queries.php';

require_login();
$pdo = db();
$filters = ['q' => request_string('q'), 'archived' => request_bool('archived')];
$page = max(1, (int) (request_integer('page') ?? 1));
$result = find_departments_list($pdo, $filters, $page);
$pages = max(1, (int) ceil($result['total'] / DEPARTMENTS_PER_PAGE));
if (wants_json()) {
    log_screen_view($pdo, 'departments-list');
    respond_screen(['departments' => array_map('present_department', $result['rows']), 'total' => $result['total'], 'page' => $page, 'pages' => $pages]);
}
$resultsHtml = view('departments/partials/cards.php', ['rows' => $result['rows'], 'total' => $result['total'], 'page' => $page, 'pages' => $pages, 'filters' => $filters]);
if (is_htmx_request() && ($_SERVER['HTTP_HX_TARGET'] ?? '') === 'departments-list-results') {
    header('Vary: HX-Request');
    echo $resultsHtml;
    exit;
}
log_screen_view($pdo, 'departments-list');
render_screen('Departments', view('departments/page.php', ['resultsHtml' => $resultsHtml, 'filters' => $filters, 'canCreate' => is_super_admin() || hr_is_staff()]),
    ['activeNav' => 'departments-list', 'screen' => 'departments-list', 'entity' => 'department']);
