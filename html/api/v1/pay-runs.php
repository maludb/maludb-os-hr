<?php
declare(strict_types=1);
/** GET /api/v1/pay-runs — the accounting application's feed (document hr.pay-runs/1): paid runs, or ?status=approved; ?period=YYYY-MM (pay date), ?run=PR-0001. An `api` token. */
require_once dirname(__DIR__, 3) . '/app/api/bootstrap.php';
require_once dirname(__DIR__, 3) . '/app/features/payruns/queries.php';
require_once dirname(__DIR__, 3) . '/app/features/compensation/queries.php';
api_cors();
api_require_get();
$member = api_authenticate();
if (!hr_is_staff()) {
    api_error('forbidden', 'This feed is read with an HR staff member\'s token.', 403);
}
$filters = ['status' => request_string('status'), 'period' => request_string('period'), 'run' => request_string('run')];
$runs = pay_runs_feed(db(), $filters);
log_activity(db(), 'api.pay_runs.read', 'member', (int) $member['id'], ['source' => 'api', 'after' => ['runs' => count($runs)] + array_filter($filters)]);
api_json(['schema' => 'hr.pay-runs/1', 'generated_at' => gmdate('Y-m-d\TH:i:s\Z'), 'currency' => (string) db()->query('SELECT currency FROM mcp_hr_settings')->fetchColumn(), 'runs' => $runs]);
