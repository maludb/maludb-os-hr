<?php
declare(strict_types=1);
/** /settings/leave-types/ — the leave types (screen leave-types). HR staff. */
require_once dirname(__DIR__, 3) . '/app/bootstrap.php';
require_once dirname(__DIR__, 3) . '/app/features/settings/queries.php';
require_hr();
$pdo = db();
log_screen_view($pdo, 'leave-types');
render_screen('Leave types', view('settings/leave-types/page.php', ['rows' => find_leave_types($pdo, true)]), ['activeNav' => 'leave-types', 'screen' => 'leave-types', 'entity' => 'leave_type']);
