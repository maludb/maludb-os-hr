<?php
declare(strict_types=1);
/** Action `token_mint` (log `token.mint`): a personal token, shown once on the next screen. `api` scope is HR staff's (the feed). */
require_once dirname(__DIR__, 3) . '/app/bootstrap.php';
require_post();
verify_csrf();
require_login();
require_human();
$pdo = db();
$me = (int) current_member_id();
$label = request_string('label');
$scope = request_string('scope', 'mcp');
if ($label === '' || mb_strlen($label) > 80) {
    refuse(422, 'A label is required (up to 80 characters) — what will use this token.');
}
if (!in_array($scope, ['mcp', 'api'], true)) {
    refuse(422, 'The scope is mcp or api.');
}
if ($scope === 'api' && !hr_is_staff()) {
    refuse(403, 'An api token reads the pay runs feed: HR staff only.');
}
$raw = 'mcp_' . bin2hex(random_bytes(24));
$st = $pdo->prepare('INSERT INTO mcp_access_tokens (member_id, label, token_hash, scope) VALUES (:m, :l, :h, :s) RETURNING id');
$st->execute(['m' => $me, 'l' => $label, 'h' => hash('sha256', $raw), 's' => $scope]);
$id = (int) $st->fetchColumn();
log_activity($pdo, 'token.mint', 'mcp_access_token', $id, ['after' => ['label' => $label, 'scope' => $scope]]);
emit_action_status(true, ['id' => $id, 'did' => 'Minted the ' . $scope . ' token "' . $label . '" — shown once on the Tokens screen', 'refresh' => 'tokenChanged']);
if (wants_json()) {
    respond_saved(['id' => $id, 'token' => $raw, 'location' => '/settings/tokens/']);   // the agents' door: the value, once
}
$_SESSION['minted_token'] = ['id' => $id, 'raw' => $raw, 'scope' => $scope, 'label' => $label];
saved_go('/settings/tokens/', 'tokenChanged');
