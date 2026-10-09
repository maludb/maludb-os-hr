<?php
// Prints the /sso query (token + claims) for a member, signed with config/.env's ACTION_TOKEN_KEY — the kernel's hand-off, minted by hand
// for the scratch install (CLAUDE.md "Proving sign-on by hand"). php scripts/dev-sso.php <member_id> "<display name>" <capability> <business_role> <roles,comma> [<dept_id>:<name>:<is_admin 0|1>]
[$_, $mid, $name, $cap, $brole, $roles] = $argv + [null, null, null, null, null, ''];
$dept = $argv[6] ?? null;
$key = '';
foreach (file(dirname(__DIR__) . '/config/.env', FILE_IGNORE_NEW_LINES) as $l) { if (str_starts_with($l, 'ACTION_TOKEN_KEY=')) { $key = substr($l, 17); } }
if (strlen($key) < 32) { fwrite(STDERR, "no ACTION_TOKEN_KEY\n"); exit(1); }
$exp = time() + 50; $nonce = bin2hex(random_bytes(16));
$token = "$mid.$exp.hr.$nonce." . hash_hmac('sha256', "sso:$mid.$exp.hr.$nonce", $key);
$departments = [];
if ($dept) { [$did, $dname, $adm] = explode(':', $dept); $departments[] = ['id' => (int) $did, 'name' => $dname, 'is_admin' => $adm === '1', 'is_primary' => true]; }
$claims = ['member_id' => (int) $mid, 'status' => 'active', 'capability' => $cap, 'display_name' => $name, 'email' => "picker-$mid@example.test", 'business_role' => $brole,
    'roles' => $roles === '' ? [] : explode(',', $roles), 'departments' => $departments];
$text = rtrim(strtr(base64_encode(json_encode($claims)), '+/', '-_'), '=');
echo 'token=' . rawurlencode($token) . '&claims=' . rawurlencode($text . '.' . hash_hmac('sha256', $text, $key)) . "\n";
