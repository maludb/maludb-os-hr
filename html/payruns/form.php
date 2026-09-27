<?php
declare(strict_types=1);
/** /payruns/new — start a pay run: the period, who is eligible and who is not and why (screen pay-run-add). HR staff. */
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_once dirname(__DIR__, 2) . '/app/features/payruns/queries.php';
require_once dirname(__DIR__, 2) . '/app/features/compensation/queries.php';
require_pay();
$pdo = db();
$settings = $pdo->query('SELECT * FROM mcp_hr_settings')->fetch();
$start = request_date('period_start') ?: date('Y-m-01');
$end = request_date('period_end') ?: date('Y-m-t');
$values = ['period_start' => $start, 'period_end' => $end, 'pay_date' => request_date('pay_date') ?: date('Y-m-d', strtotime($end . ' +1 day')), 'currency' => strtoupper(request_string('currency')) ?: $settings['currency'], 'note' => ''];
log_screen_view($pdo, 'pay-run-add');
render_screen('New pay run', view('payruns/partials/form.php', ['values' => $values, 'errors' => [], 'people' => eligible_people($pdo, $values['period_end'], $values['currency'])]),
    ['activeNav' => 'pay-runs', 'screen' => 'pay-run-add', 'entity' => 'pay_run']);
