<?php
declare(strict_types=1);

/** /leave/calendar — who is off, by day, two weeks from a Monday (screen leave-calendar). */
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_once dirname(__DIR__, 2) . '/app/features/leave/queries.php';
require_once dirname(__DIR__, 2) . '/app/features/people/queries.php';

require_login();
$pdo = db();
$from = request_date('from');
$monday = new DateTimeImmutable(is_string($from) ? $from : 'today');
$monday = $monday->modify('monday this week');
$days = 14;
$to = $monday->modify('+' . ($days - 1) . ' days');
$departmentId = request_integer('department');
$settings = $pdo->query('SELECT * FROM mcp_hr_settings')->fetch();
$workDays = array_map('intval', explode(',', trim((string) ($settings['work_days'] ?? '{1,2,3,4,5}'), '{}')));
$off = whos_off($pdo, $monday->format('Y-m-d'), $to->format('Y-m-d'), $departmentId, true);
$people = calendar_people($pdo, $departmentId);
log_screen_view($pdo, 'leave-calendar');
if (wants_json()) {
    respond_screen(['from' => $monday->format('Y-m-d'), 'to' => $to->format('Y-m-d'), 'requests' => array_map('present_leave_request', $off['requests']), 'holidays' => $off['holidays']]);
}
render_screen('Leave calendar', view('leave/calendar.php', ['monday' => $monday, 'days' => $days, 'people' => $people, 'off' => $off, 'workDays' => $workDays,
    'departmentId' => $departmentId, 'departments' => department_options($pdo)]),
    ['activeNav' => 'leave-calendar', 'screen' => 'leave-calendar', 'entity' => 'leave_request']);
