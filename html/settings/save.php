<?php
declare(strict_types=1);

/** Action `settings_save` (log `settings.save`). HR staff. */
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_once dirname(__DIR__, 2) . '/app/features/settings/queries.php';

require_post();
verify_csrf();
require_hr();
$pdo = db();
$before = hr_settings($pdo);
$f = ['currency' => strtoupper(request_string('currency', (string) $before['currency'])), 'timezone' => request_string('timezone', (string) $before['timezone']),
      'employee_no_prefix' => request_string('employee_no_prefix', (string) $before['employee_no_prefix'])];
$wd = $_POST['work_days'] ?? null;
if (is_string($wd)) { $wd = array_map('trim', explode(',', $wd)); }
$f['work_days'] = is_array($wd) ? array_values(array_unique(array_filter(array_map('intval', $wd), static fn (int $d): bool => $d >= 1 && $d <= 7))) : array_map('intval', explode(',', trim((string) $before['work_days'], '{}')));
sort($f['work_days']);
$errors = [];
if (!preg_match('/^[A-Z]{3}$/', $f['currency'])) { $errors[] = 'The currency is a three-letter code (USD, EUR).'; }
if (!in_array($f['timezone'], timezone_identifiers_list(), true)) { $errors[] = 'That is not a time zone.'; }
if ($f['work_days'] === []) { $errors[] = 'At least one work day.'; }
if ($f['employee_no_prefix'] === '' || mb_strlen($f['employee_no_prefix']) > 10) { $errors[] = 'The employee number prefix is 1 to 10 characters.'; }
if ($errors !== []) {
    emit_action_status(false, ['errors' => $errors]);
    if (wants_json()) { respond_invalid($errors); }
    http_response_code(422);
    header('HX-Retarget: #page-content');
    header('HX-Reswap: innerHTML');
    echo view('settings/page.php', ['s' => $f + $before, 'errors' => $errors]);
    exit;
}
$after = save_hr_settings($pdo, $f, (int) current_member_id());
log_activity($pdo, 'settings.save', 'hr_settings', 1, ['before' => array_intersect_key($before, $f), 'after' => $f]);
emit_action_status(true, ['did' => 'Saved the HR settings', 'refresh' => 'settingsChanged']);
saved_go('/settings/', 'settingsChanged');
