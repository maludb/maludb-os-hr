<?php
declare(strict_types=1);
/** Action `token_revoke` (log `token.revoke`, confirm). The token's owner. */
require_once dirname(__DIR__, 3) . '/app/bootstrap.php';
require_post();
verify_csrf();
require_login();
$pdo = db();
$me = (int) current_member_id();
$id = request_integer('token') ?? request_integer('id');
$st = $pdo->prepare('UPDATE mcp_access_tokens SET revoked_at = now() WHERE id = :id AND member_id = :m AND revoked_at IS NULL RETURNING label, scope');
$st->execute(['id' => $id ?? 0, 'm' => $me]);
$row = $st->fetch();
if ($row === false) {
    refuse(404, 'No live token of yours with that id.');
}
log_activity($pdo, 'token.revoke', 'mcp_access_token', $id, ['after' => ['label' => $row['label'], 'scope' => $row['scope']]]);
emit_action_status(true, ['id' => $id, 'did' => 'Revoked the token "' . $row['label'] . '"', 'refresh' => 'tokenChanged']);
saved_go('/settings/tokens/', 'tokenChanged');
