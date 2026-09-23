<?php
declare(strict_types=1);

/** /people/{id} — one person (screen person-view), tabs overview | employment | pay | leave | reviews | history. */
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_once dirname(__DIR__, 2) . '/app/features/people/queries.php';
require_once dirname(__DIR__, 2) . '/app/features/people/present.php';
require_once dirname(__DIR__, 2) . '/app/features/leave/queries.php';
require_once dirname(__DIR__, 2) . '/app/features/compensation/queries.php';
require_once dirname(__DIR__, 2) . '/app/features/reviews/queries.php';

require_login();
$pdo = db();
$id = request_integer('id');
$person = $id === null ? null : find_person($pdo, $id);
if ($person === null) {
    respond_not_found('No such person here.');
}
$tabs = ['overview', 'employment', 'pay', 'leave', 'reviews', 'history'];
$tab = request_string('tab', 'overview');
if (!in_array($tab, $tabs, true)) {
    $tab = 'overview';
}
$me = current_member_id();
$staff = hr_is_staff();
$canSeePay = can_see_pay($id);
$canSeePerson = can_see_person($id);      // employment, leave and reviews live behind this
$history = $tab === 'history' ? person_history($pdo, $id) : [];
$leave = $tab === 'leave' && $canSeePerson ? person_leave_summary($pdo, $id, (int) date('Y')) : ['balances' => [], 'requests' => []];
$pay = $tab === 'pay' && $canSeePay ? pay_history($pdo, $id) : ['current' => null, 'changes' => []];
$reviews = $tab === 'reviews' ? person_reviews($pdo, $id) : [];
$canRequestFor = $canSeePerson && $person['member_kind'] === 'human' && ($id === $me || $staff || is_manager_of($id) || can_admin_member($id));

log_activity($pdo, 'screen.view', 'member', $id, ['screen' => 'person-view', 'after' => ['tab' => $tab]]);
if (wants_json()) {
    respond_screen(['person' => present_person($person), 'tab' => $tab]);
}
render_screen($person['display_name'], view('people/view.php', [
    'person' => $person, 'tab' => $tab, 'tabs' => $tabs, 'staff' => $staff, 'me' => $me, 'canSeePay' => $canSeePay,
    'canSeePerson' => $canSeePerson, 'canAdmin' => $staff || can_admin_member($id), 'history' => $history, 'tz' => member_timezone(),
    'leave' => $leave, 'canRequestFor' => $canRequestFor, 'pay' => $pay, 'reviews' => $reviews,
]), ['activeNav' => 'people-list', 'screen' => 'person-view', 'entity' => 'member', 'recordId' => (string) $id]);
