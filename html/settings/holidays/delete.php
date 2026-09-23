<?php
declare(strict_types=1);
/** Action `holiday_delete` (log `holiday.delete`, confirm, agent approval deletion). HR staff. Pattern C: the row disappears. */
require_once dirname(__DIR__, 3) . '/app/bootstrap.php';
require_once dirname(__DIR__, 3) . '/app/features/settings/queries.php';
require_post();
verify_csrf();
require_hr();
$pdo = db();
$id = request_integer('holiday') ?? request_integer('id');
$h = $id === null ? null : find_holiday($pdo, $id);
if ($h === null) {
    refuse(404, 'No such holiday.');
}
delete_holiday($pdo, $id);
log_activity($pdo, 'holiday.delete', 'holiday', $id, ['before' => ['holiday_date' => $h['holiday_date'], 'name' => $h['name']]]);
emit_action_status(true, ['id' => $id, 'did' => 'Removed the holiday ' . $h['name'] . ' on ' . format_date($h['holiday_date']), 'refresh' => 'holidayChanged']);
if (wants_json()) {
    respond_saved(['did' => 'Removed the holiday ' . $h['name'], 'location' => '/settings/holidays/']);
}
hx_trigger('holidayChanged');
if (is_htmx_request()) {
    http_response_code(200);
    exit;                                   // hx-target="closest tr" hx-swap="delete"
}
header('Location: /settings/holidays/', true, 302);
exit;
