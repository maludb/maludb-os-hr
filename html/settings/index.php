<?php
declare(strict_types=1);

/** /settings/ — the business's HR settings (screen settings). HR staff. */
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_once dirname(__DIR__, 2) . '/app/features/settings/queries.php';

require_hr();
$pdo = db();
log_screen_view($pdo, 'settings');
render_screen('Settings', view('settings/page.php', ['s' => hr_settings($pdo), 'errors' => []]), ['activeNav' => 'settings', 'screen' => 'settings']);
