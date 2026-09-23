<?php
declare(strict_types=1);

/** Whitelist presenters — what a JSON reader of these screens gets. Never a raw row. */
function present_person_card(array $r): array
{
    return [
        'member_id' => (int) $r['member_id'], 'display_name' => (string) $r['display_name'], 'email' => $r['email'],
        'member_kind' => $r['member_kind'], 'is_external' => !empty($r['is_external']), 'job_title' => $r['job_title'],
        'primary_department' => $r['primary_department'], 'employee_no' => $r['employee_no'] ?? null,
        'employment_type' => $r['employment_type'] ?? null, 'employment_status' => $r['employment_status'] ?? null,
        'start_date' => $r['start_date'] ?? null,
    ];
}

function present_person(array $p): array
{
    $e = $p['employment'];
    return [
        'member_id' => (int) $p['member_id'], 'display_name' => $p['display_name'], 'email' => $p['email'], 'member_kind' => $p['member_kind'],
        'business_role' => $p['business_role'], 'is_external' => !empty($p['is_external']), 'status' => $p['status'],
        'job_title' => $p['job_title'], 'phone' => $p['phone'], 'timezone' => $p['timezone'],
        'departments' => array_map(static fn (array $d): array => ['department_id' => (int) $d['department_id'], 'name' => $d['department_name'],
            'is_admin' => !empty($d['is_admin']), 'is_primary' => !empty($d['is_primary'])], $p['departments']),
        'employment' => $e === null ? null : [
            'employee_no' => $e['employee_no'], 'employment_type' => $e['employment_type'], 'status' => $e['status'],
            'manager_member_id' => $e['manager_member_id'] !== null ? (int) $e['manager_member_id'] : null, 'manager_name' => $e['manager_name'],
            'work_location' => $e['work_location'], 'start_date' => $e['start_date'], 'probation_end_date' => $e['probation_end_date'],
            'end_date' => $e['end_date'], 'end_reason' => $e['end_reason'], 'weekly_hours' => $e['weekly_hours'], 'pay_schedule' => $e['pay_schedule'],
        ],
        'reports' => array_map(static fn (array $r): array => ['member_id' => (int) $r['member_id'], 'display_name' => $r['display_name'], 'job_title' => $r['job_title']], $p['reports']),
    ];
}

/** The status badge colour vocabulary, locked (design-decisions.md). */
function employment_status_color(?string $status): string
{
    return match ($status) {
        'candidate' => 'info', 'active' => 'success', 'on_leave' => 'warning', 'ended' => 'secondary', default => 'dark',
    };
}

function employment_status_label(?string $status): string
{
    return match ($status) {
        'candidate' => 'Candidate', 'active' => 'Active', 'on_leave' => 'On leave', 'ended' => 'Ended', default => 'Not employed',
    };
}

function initials(string $name): string
{
    $parts = preg_split('/\s+/', trim($name)) ?: [];
    $s = '';
    foreach (array_slice($parts, 0, 2) as $p) {
        $s .= mb_strtoupper(mb_substr($p, 0, 1));
    }
    return $s === '' ? '?' : $s;
}
