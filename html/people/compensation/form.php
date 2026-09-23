<?php
declare(strict_types=1);
/** /people/compensation/new?person= — record a pay change (screen compensation-add). HR staff. */
require_once dirname(__DIR__, 3) . '/app/bootstrap.php';
require_once dirname(__DIR__, 3) . '/app/features/people/queries.php';
require_once dirname(__DIR__, 3) . '/app/features/people/present.php';
require_once dirname(__DIR__, 3) . '/app/features/compensation/queries.php';
require_hr();
$pdo = db();
$id = request_integer('person') ?? request_integer('id');
$person = $id === null ? null : find_person($pdo, $id);
if ($person === null) {
    respond_not_found('No such person here.');
}
if ($person['member_kind'] !== 'human') {
    refuse(422, 'An agent is not an employee: employment, pay and leave are for people.');
}
$settings = $pdo->query('SELECT currency FROM mcp_hr_settings')->fetch();
$history = pay_history($pdo, $id);
$values = ['effective_date' => request_date('effective_date') ?: date('Y-m-d'), 'pay_type' => $history['current']['pay_type'] ?? 'salary', 'pay_rate' => '',
    'currency' => $history['current']['currency'] ?? ($settings['currency'] ?? 'USD'), 'reason' => ''];
log_screen_view($pdo, 'compensation-add');
render_screen('Pay change · ' . $person['display_name'], view('people/partials/compensation-form.php', ['person' => $person, 'values' => $values, 'errors' => [], 'current' => $history['current']]),
    ['activeNav' => 'people-list', 'screen' => 'compensation-add', 'entity' => 'member', 'recordId' => (string) $id]);
