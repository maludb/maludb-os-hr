<?php
declare(strict_types=1);

/** /leave/new — request leave, or record it for someone you may act for (screen leave-request-add). */
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_once dirname(__DIR__, 2) . '/app/features/leave/queries.php';

require_login();
require_human();
$pdo = db();
$me = (int) current_member_id();
$people = leave_people_options($pdo);
$values = ['leave_type_id' => request_integer('type'), 'start_date' => request_date('start') ?: '', 'end_date' => request_date('end') ?: '',
    'member_id' => request_integer('person') ?? $me, 'half_day' => false, 'reason' => ''];
log_screen_view($pdo, 'leave-request-add');
render_screen('Request leave', view('leave/partials/form.php', ['values' => $values, 'errors' => [], 'types' => leave_types_active($pdo), 'people' => $people, 'me' => $me,
    'settings' => $pdo->query('SELECT * FROM mcp_hr_settings')->fetch()]),
    ['activeNav' => 'leave-requests', 'screen' => 'leave-request-add', 'entity' => 'leave_request']);
