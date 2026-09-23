<?php
declare(strict_types=1);

/** /leave/balances/ — balances per person and type for a year (screen leave-balances). */
require_once dirname(__DIR__, 3) . '/app/bootstrap.php';
require_once dirname(__DIR__, 3) . '/app/features/leave/queries.php';
require_once dirname(__DIR__, 3) . '/app/features/people/queries.php';

require_login();
$pdo = db();
$year = request_integer('year') ?? (int) date('Y');
$year = max(2000, min(2200, $year));
$filters = ['year' => $year, 'department' => request_integer('department'), 'person' => request_integer('person')];
$rows = leave_balances($pdo, $filters);
log_screen_view($pdo, 'leave-balances');
if (wants_json()) {
    respond_screen(['year' => $year, 'balances' => array_map(static fn (array $b): array => ['member_id' => (int) $b['member_id'], 'display_name' => $b['display_name'], 'leave_type' => $b['leave_type'],
        'accrued_days' => (float) $b['accrued_days'], 'carried_days' => (float) $b['carried_days'], 'used_days' => (float) $b['used_days'], 'remaining_days' => (float) $b['remaining_days'], 'committed_days' => (float) $b['committed_days']], $rows)]);
}
render_screen('Leave balances', view('leave/balances.php', ['rows' => $rows, 'year' => $year, 'filters' => $filters, 'departments' => department_options($pdo), 'staff' => hr_is_staff()]),
    ['activeNav' => 'leave-balances', 'screen' => 'leave-balances', 'entity' => 'leave_balance']);
