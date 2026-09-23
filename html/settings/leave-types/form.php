<?php
declare(strict_types=1);
/** /settings/leave-types/new and /{id}/edit (screens leave-type-add, leave-type-edit). HR staff. */
require_once dirname(__DIR__, 3) . '/app/bootstrap.php';
require_once dirname(__DIR__, 3) . '/app/features/settings/queries.php';
require_hr();
$pdo = db();
$id = request_integer('id') ?? request_integer('leave_type');
$t = $id === null ? null : find_leave_type($pdo, $id);
if ($id !== null && $t === null) {
    respond_not_found('No such leave type.');
}
$values = $t ?? ['name' => '', 'is_paid' => true, 'accrual_days_per_year' => '', 'carry_over_days' => '0', 'requires_approval' => true, 'archived_at' => null];
log_screen_view($pdo, $id === null ? 'leave-type-add' : 'leave-type-edit');
render_screen($id === null ? 'New leave type' : $t['name'], view('settings/leave-types/form.php', ['t' => $t, 'values' => $values, 'errors' => []]),
    ['activeNav' => 'leave-types', 'screen' => $id === null ? 'leave-type-add' : 'leave-type-edit', 'entity' => 'leave_type', 'recordId' => (string) ($id ?? '')]);
