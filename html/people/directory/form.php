<?php
declare(strict_types=1);

/** /people/directory/{id}/edit — a person's directory facts (screen person-directory-edit). HR staff, or an admin of one of their departments. */
require_once dirname(__DIR__, 3) . '/app/bootstrap.php';
require_once dirname(__DIR__, 3) . '/app/features/people/queries.php';
require_once dirname(__DIR__, 3) . '/app/features/people/present.php';

require_login();
$pdo = db();
$id = request_integer('id') ?? request_integer('person');
$person = $id === null ? null : find_person($pdo, $id);
if ($person === null) {
    respond_not_found('No such person here.');
}
require_can_admin_member($id);
if ($person['member_kind'] !== 'human') {
    refuse(403, 'Agents are the operating system\'s to change.');
}
if ($person['business_role'] === 'super_admin') {
    refuse(403, 'A super-admin is changed only from the operating system.');
}
log_screen_view($pdo, 'person-directory-edit');
render_screen('Directory · ' . $person['display_name'], view('people/partials/directory-form.php', ['person' => $person, 'values' => $person, 'errors' => [], 'staff' => hr_is_staff()]),
    ['activeNav' => 'people-list', 'screen' => 'person-directory-edit', 'entity' => 'member', 'recordId' => (string) $id]);
