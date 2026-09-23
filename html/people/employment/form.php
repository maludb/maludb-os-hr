<?php
declare(strict_types=1);

/** /people/employment/{id}/edit — create or change the employment record (screen employment-edit). HR staff. */
require_once dirname(__DIR__, 3) . '/app/bootstrap.php';
require_once dirname(__DIR__, 3) . '/app/features/people/queries.php';
require_once dirname(__DIR__, 3) . '/app/features/people/present.php';

require_hr();
$pdo = db();
$id = request_integer('id') ?? request_integer('person');
$person = $id === null ? null : find_person($pdo, $id);
if ($person === null) {
    respond_not_found('No such person here.');
}
if ($person['member_kind'] !== 'human') {
    refuse(422, 'An agent is not an employee: employment, pay and leave are for people.');
}
$record = find_employment($pdo, $id);
$isNew = $record === null;
$record ??= ['employee_no' => next_employee_no($pdo), 'employment_type' => 'employee', 'manager_member_id' => default_manager_for($pdo, $id),
    'status' => 'active', 'start_date' => date('Y-m-d')];
log_screen_view($pdo, 'employment-edit');
render_screen(($isNew ? 'Employ ' : 'Employment of ') . $person['display_name'], view('people/partials/employment-form.php', [
    'person' => $person, 'record' => $record, 'isNew' => $isNew, 'errors' => [], 'managers' => manager_options($pdo, $id),
]), ['activeNav' => 'people-list', 'screen' => 'employment-edit', 'entity' => 'member', 'recordId' => (string) $id]);
