<?php
declare(strict_types=1);
/** /settings/tokens/ — the caller's own access tokens, and the two MCP URLs to connect their AI to (screen tokens). */
require_once dirname(__DIR__, 3) . '/app/bootstrap.php';
require_login();
require_human();
$pdo = db();
$me = (int) current_member_id();
$st = $pdo->prepare('SELECT id, label, scope, created_at, last_used_at, revoked_at, expires_at FROM mcp_access_tokens WHERE member_id = :m ORDER BY revoked_at NULLS FIRST, created_at DESC');
$st->execute(['m' => $me]);
log_screen_view($pdo, 'tokens');
render_screen('Tokens', view('settings/tokens/page.php', ['rows' => $st->fetchAll(), 'raw' => $_SESSION['minted_token'] ?? null, 'staff' => hr_is_staff(), 'tz' => member_timezone()]),
    ['activeNav' => 'tokens', 'screen' => 'tokens', 'entity' => 'mcp_access_token']);
unset($_SESSION['minted_token']);
