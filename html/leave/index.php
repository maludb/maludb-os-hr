<?php
declare(strict_types=1);

/** /leave/ — leave requests: what awaits me, mine, my people's (screen leave-requests). A ledger: a table. */
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_once dirname(__DIR__, 2) . '/app/features/leave/queries.php';
require_once dirname(__DIR__, 2) . '/app/features/people/queries.php';

require_login();
$pdo = db();
$me = (int) current_member_id();
$filters = ['status' => request_string('status'), 'person' => request_integer('person'), 'department' => request_integer('department'),
    'awaiting_me' => request_bool('awaiting_me'), 'mine' => request_bool('mine') ? $me : null, 'flagged' => request_bool('flagged'),
    'from' => request_date('from') ?: null, 'to' => request_date('to') ?: null];
$page = max(1, (int) (request_integer('page') ?? 1));
$result = find_leave_requests($pdo, $filters, $page);
$pages = max(1, (int) ceil($result['total'] / LEAVE_PER_PAGE));
if (wants_json()) {
    log_screen_view($pdo, 'leave-requests');
    respond_screen(['requests' => array_map('present_leave_request', $result['rows']), 'total' => $result['total'], 'page' => $page, 'pages' => $pages]);
}
$resultsHtml = view('leave/partials/table.php', ['rows' => $result['rows'], 'total' => $result['total'], 'page' => $page, 'pages' => $pages, 'filters' => $filters, 'me' => $me]);
if (is_htmx_request() && ($_SERVER['HTTP_HX_TARGET'] ?? '') === 'leave-requests-results') {
    header('Vary: HX-Request');
    echo $resultsHtml;
    exit;
}
log_screen_view($pdo, 'leave-requests');
render_screen('Leave', view('leave/page.php', ['resultsHtml' => $resultsHtml, 'filters' => $filters, 'departments' => department_options($pdo),
    'awaiting' => (int) $pdo->query('SELECT count(*) FROM mcp_leave_requests WHERE awaiting_me')->fetchColumn()]),
    ['activeNav' => 'leave-requests', 'screen' => 'leave-requests', 'entity' => 'leave_request']);
