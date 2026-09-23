<?php
declare(strict_types=1);

/** /people/new — invite someone into the business; the kernel sends the invitation (screen person-invite). */
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_once dirname(__DIR__, 2) . '/app/features/directory/queries.php';

require_login();
$pdo = db();
$staff = hr_is_staff();
$departments = administered_departments($pdo, $staff);
if ($departments === []) {
    refuse(403, 'You invite people into the departments you administer; you administer none.');
}
log_screen_view($pdo, 'person-invite');
render_screen('Invite', view('people/partials/invite-form.php', ['errors' => [], 'staff' => $staff, 'departments' => $departments,
    'values' => ['email' => request_string('email'), 'department_id' => request_integer('department'), 'business_role' => request_string('business_role', 'user'), 'message' => '']]),
    ['activeNav' => 'people-list', 'screen' => 'person-invite', 'entity' => 'member']);
