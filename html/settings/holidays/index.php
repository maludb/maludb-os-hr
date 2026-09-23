<?php
declare(strict_types=1);
/** /settings/holidays/ — the public holidays of a year (screen holidays). HR staff. */
require_once dirname(__DIR__, 3) . '/app/bootstrap.php';
require_once dirname(__DIR__, 3) . '/app/features/settings/queries.php';
require_hr();
$pdo = db();
$year = max(2000, min(2200, request_integer('year') ?? (int) date('Y')));
log_screen_view($pdo, 'holidays');
render_screen('Holidays', view('settings/holidays/page.php', ['rows' => find_holidays($pdo, $year), 'year' => $year]), ['activeNav' => 'holidays', 'screen' => 'holidays', 'entity' => 'holiday']);
