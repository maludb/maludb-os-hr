<?php
declare(strict_types=1);
/** /payruns/ — the pay runs (screen pay-runs). HR staff. A ledger: a table. */
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_once dirname(__DIR__, 2) . '/app/features/payruns/queries.php';
require_once dirname(__DIR__, 2) . '/app/features/compensation/queries.php';
require_pay();
$pdo = db();
$filters = ['status' => request_string('status'), 'period' => request_string('period')];
$page = max(1, (int) (request_integer('page') ?? 1));
$result = find_pay_runs($pdo, $filters, $page);
$pages = max(1, (int) ceil($result['total'] / 30));
log_screen_view($pdo, 'pay-runs');
if (wants_json()) {
    respond_screen(['pay_runs' => array_map('present_pay_run', $result['rows']), 'total' => $result['total'], 'page' => $page, 'pages' => $pages]);
}
render_screen('Pay runs', view('payruns/page.php', ['rows' => $result['rows'], 'total' => $result['total'], 'page' => $page, 'pages' => $pages, 'filters' => $filters]),
    ['activeNav' => 'pay-runs', 'screen' => 'pay-runs', 'entity' => 'pay_run']);
