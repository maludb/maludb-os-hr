<?php
declare(strict_types=1);

/** / — the dashboard: my HR at a glance, and for HR staff what needs them. Reads the mcp_* views only. */
require_once dirname(__DIR__) . '/app/bootstrap.php';
require_once dirname(__DIR__) . '/app/features/home/queries.php';

require_login();
$pdo = db();
log_screen_view($pdo, 'dashboard');
$me = current_member();
$data = home_summary($pdo, (int) $me['id'], hr_is_staff());
render_screen('Dashboard', view('home/page.php', ['me' => $me, 'staff' => hr_is_staff()] + $data), ['activeNav' => 'dashboard', 'screen' => 'dashboard']);
