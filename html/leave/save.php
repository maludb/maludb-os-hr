<?php
declare(strict_types=1);

/** Action `leave_request_create` (log `leave_request.create`). Own always; for another person: their manager, an admin of their department, HR. */
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_once dirname(__DIR__, 2) . '/app/features/leave/queries.php';

require_post();
verify_csrf();
require_login();
$pdo = db();
$me = (int) current_member_id();
$memberId = request_integer('person') ?? request_integer('member_id') ?? $me;
$typeId = request_integer('leave_type') ?? request_integer('leave_type_id');
$start = request_date('start_date');
$end = request_date('end_date');
$half = request_bool('half_day');
$reason = request_string('reason') ?: null;
$errors = [];
if ($typeId === null) { $errors[] = 'Choose the leave type.'; }
if ($start === null || $start === false) { $errors[] = 'A start date is required.'; }
if ($half) { $end = $start; }
if ($end === null || $end === false) { $errors[] = 'An end date is required.'; }
if (is_string($start) && is_string($end) && $end < $start) { $errors[] = 'The end date is on or after the start date.'; }
if (is_string($start) && is_string($end) && substr($start, 0, 4) !== substr($end, 0, 4)) { $errors[] = 'A request stays within one calendar year; make two.'; }
if ($reason !== null && mb_strlen($reason) > 500) { $errors[] = 'Keep the reason under 500 characters.'; }
$target = find_member_by_id($pdo, $memberId);
if ($target === null || $target['member_kind'] !== 'human') { $errors[] = 'Leave is for people.'; }
if ($memberId !== $me && !hr_is_staff() && !is_manager_of($memberId) && !can_admin_member($memberId)) {
    $errors[] = 'You can request leave for yourself, your reports, or the people in departments you administer.';
}
$renderForm = function (array $errs) use ($pdo, $memberId, $typeId, $start, $end, $half, $reason, $me): never {
    emit_action_status(false, ['errors' => $errs]);
    if (wants_json()) { respond_invalid($errs); }
    http_response_code(422);
    header('HX-Retarget: #page-content');
    header('HX-Reswap: innerHTML');
    echo view('leave/partials/form.php', ['values' => ['leave_type_id' => $typeId, 'start_date' => is_string($start) ? $start : '', 'end_date' => is_string($end) ? $end : '',
        'member_id' => $memberId, 'half_day' => $half, 'reason' => $reason ?? ''], 'errors' => $errs, 'types' => leave_types_active($pdo), 'people' => leave_people_options($pdo), 'me' => $me,
        'settings' => $pdo->query('SELECT * FROM mcp_hr_settings')->fetch()]);
    exit;
};
if ($errors !== []) {
    $renderForm($errors);
}
$pdo->beginTransaction();
try {
    $row = insert_leave_request($pdo, $memberId, $typeId, $start, $end, $half, $reason, $me);
    log_activity($pdo, 'leave_request.create', 'leave_request', (int) $row['id'], ['after' => ['member_id' => $memberId, 'leave_type_id' => $typeId,
        'start_date' => $start, 'end_date' => $end, 'days' => (float) $row['days'], 'status' => $row['status'], 'over_balance' => !empty($row['over_balance']), 'has_overlap' => !empty($row['has_overlap'])]]);
    $pdo->commit();
} catch (RuntimeException $ex) {
    $pdo->rollBack();
    $renderForm([$ex->getMessage()]);
} catch (PDOException $ex) {
    $pdo->rollBack();
    $renderForm([db_message($ex, 'The request could not be saved.')]);
}
$who = $memberId === $me ? 'you' : $target['display_name'];
$did = sprintf('%s %s of leave for %s, %s%s%s', $row['status'] === 'approved' ? 'Recorded' : 'Requested', days_label($row['days']) . ' day' . ((float) $row['days'] == 1 ? '' : 's'), $who,
    format_date($start), $end !== $start ? ' to ' . format_date($end) : '', !empty($row['over_balance']) ? ' — beyond the balance' : '');
emit_action_status(true, ['id' => (int) $row['id'], 'did' => $did, 'refresh' => 'leaveRequestChanged']);
saved_go('/leave/' . (int) $row['id'], 'leaveRequestChanged');
