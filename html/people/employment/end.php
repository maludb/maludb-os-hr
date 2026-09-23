<?php
declare(strict_types=1);

/** Action `employment_end` (log `employment_profile.end`, confirm, agent approval other). HR staff. */
require_once dirname(__DIR__, 3) . '/app/bootstrap.php';
require_once dirname(__DIR__, 3) . '/app/features/people/queries.php';

require_post();
verify_csrf();
require_hr();
$pdo = db();

$id = request_integer('person') ?? request_integer('member_id');
$before = $id === null ? null : find_employment($pdo, $id);
if ($before === null) {
    refuse(404, 'This person has no employment record to end.');
}
if ($before['status'] === 'ended') {
    refuse(409, 'This employment already ended on ' . format_date($before['end_date']) . '.');
}
$endDate = request_date('end_date');
if ($endDate === null || $endDate === false) {
    refuse(422, 'An end date is required.');
}
if ($before['start_date'] !== null && $endDate < $before['start_date']) {
    refuse(422, 'The end date is on or after the start date.');
}
$reason = request_string('reason') ?: null;

$pdo->beginTransaction();
try {
    $row = end_employment($pdo, $id, $endDate, $reason);
    $cancelled = cancel_leave_after($pdo, $id, $endDate, (int) current_member_id());
    foreach ($cancelled as $lr) {
        log_activity($pdo, 'leave_request.cancel', 'leave_request', (int) $lr['id'], ['after' => ['by' => 'employment_end', 'was' => $lr['status']]]);
    }
    log_activity($pdo, 'employment_profile.end', 'employment_profile', $id, [
        'before' => ['status' => $before['status'], 'end_date' => $before['end_date']],
        'after' => ['status' => 'ended', 'end_date' => $endDate, 'reason' => $reason, 'leave_requests_cancelled' => count($cancelled)],
    ]);
    $pdo->commit();
} catch (Throwable $ex) {
    $pdo->rollBack();
    refuse(500, db_message($ex, 'The employment could not be ended.'));
}
$name = find_member_by_id($pdo, $id)['display_name'] ?? ('#' . $id);
emit_action_status(true, ['id' => $id, 'did' => 'Ended the employment of ' . $name . ' on ' . format_date($endDate), 'refresh' => 'employmentProfileChanged']);
saved_go('/people/' . $id . '?tab=employment', 'employmentProfileChanged');
