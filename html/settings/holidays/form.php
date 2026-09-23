<?php
declare(strict_types=1);
/** /settings/holidays/new and /{id}/edit (screen holiday-add). HR staff. */
require_once dirname(__DIR__, 3) . '/app/bootstrap.php';
require_once dirname(__DIR__, 3) . '/app/features/settings/queries.php';
require_hr();
$pdo = db();
$id = request_integer('id') ?? request_integer('holiday');
$h = $id === null ? null : find_holiday($pdo, $id);
if ($id !== null && $h === null) {
    respond_not_found('No such holiday.');
}
$values = $h ?? ['holiday_date' => request_date('date') ?: '', 'name' => request_string('name')];
log_screen_view($pdo, 'holiday-add');
render_screen($id === null ? 'New holiday' : $h['name'], view('settings/holidays/form.php', ['h' => $h, 'values' => $values, 'errors' => []]),
    ['activeNav' => 'holidays', 'screen' => 'holiday-add', 'entity' => 'holiday', 'recordId' => (string) ($id ?? '')]);
