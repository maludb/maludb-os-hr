<?php
declare(strict_types=1);

/** /leave/balances/new — adjust a balance or carry days over (screen leave-balance-adjust). HR staff. */
require_once dirname(__DIR__, 3) . '/app/bootstrap.php';
require_once dirname(__DIR__, 3) . '/app/features/leave/queries.php';

require_hr();
$pdo = db();
$values = ['member_id' => request_integer('person'), 'leave_type_id' => request_integer('leave_type'), 'year' => request_integer('year') ?? (int) date('Y'), 'accrued_days' => '', 'carried_days' => '', 'reason' => ''];
if ($values['member_id'] !== null && $values['leave_type_id'] !== null) {
    $st = $pdo->prepare('SELECT * FROM mcp_leave_balances WHERE member_id = :m AND leave_type_id = :t AND year = :y');
    $st->execute(['m' => $values['member_id'], 't' => $values['leave_type_id'], 'y' => $values['year']]);
    if ($b = $st->fetch()) {
        $values['accrued_days'] = days_label($b['accrued_days']);
        $values['carried_days'] = days_label($b['carried_days']);
    }
}
log_screen_view($pdo, 'leave-balance-adjust');
render_screen('Adjust a balance', view('leave/partials/balance-form.php', ['values' => $values, 'errors' => [], 'types' => leave_types_active($pdo), 'people' => leave_people_options($pdo)]),
    ['activeNav' => 'leave-balances', 'screen' => 'leave-balance-adjust', 'entity' => 'leave_balance']);
