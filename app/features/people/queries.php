<?php
declare(strict_types=1);

/**
 * People — the directory as HR shows it, plus the employment record (HR's own fact).
 * Reads go through the mcp_* views (the caller sees what the views allow); the employment form
 * alone reads the base table, behind the HR gate, because the views hide home contact details.
 */

const PEOPLE_SORT_ALLOWED = ['name', 'start_date', 'department'];
const PEOPLE_PER_PAGE = 24;
const EMPLOYMENT_TYPES = ['employee', 'contractor', 'intern'];
const EMPLOYMENT_STATUSES = ['candidate', 'active', 'on_leave', 'ended'];
const PAY_SCHEDULES = ['weekly', 'biweekly', 'semimonthly', 'monthly'];

/**
 * @param array $filters q, department (id), status (employment status or 'unemployed'), type, employed ('yes'|'no'|''), kind
 * @return array{rows: array, total: int}
 */
function find_people(PDO $pdo, array $filters, string $sort = 'name', int $page = 1, int $per = PEOPLE_PER_PAGE): array
{
    $where = ["m.status = 'active' OR :inactive"];
    $args = ['inactive' => !empty($filters['include_inactive']) ? 't' : 'f'];
    $q = trim((string) ($filters['q'] ?? ''));
    if ($q !== '') {
        $where[] = '(m.display_name ILIKE :q OR m.email ILIKE :q OR e.employee_no ILIKE :q)';
        $args['q'] = '%' . mb_substr($q, 0, 100) . '%';
    }
    if (!empty($filters['department'])) {
        $where[] = 'EXISTS (SELECT 1 FROM mcp_department_members dm WHERE dm.member_id = m.member_id AND dm.department_id = :dept AND dm.left_at IS NULL)';
        $args['dept'] = (int) $filters['department'];
    }
    $status = (string) ($filters['status'] ?? '');
    if ($status === 'unemployed') {
        $where[] = 'e.member_id IS NULL';
    } elseif (in_array($status, EMPLOYMENT_STATUSES, true)) {
        $where[] = 'e.status = :status';
        $args['status'] = $status;
    }
    if (in_array($filters['type'] ?? '', EMPLOYMENT_TYPES, true)) {
        $where[] = 'e.employment_type = :type';
        $args['type'] = $filters['type'];
    }
    if (($filters['employed'] ?? '') === 'yes') {
        $where[] = 'e.member_id IS NOT NULL';
    } elseif (($filters['employed'] ?? '') === 'no') {
        $where[] = 'e.member_id IS NULL';
    }
    if (in_array($filters['kind'] ?? '', ['human', 'agent'], true)) {
        $where[] = 'm.member_kind = :kind';
        $args['kind'] = $filters['kind'];
    }
    $order = match (in_array($sort, PEOPLE_SORT_ALLOWED, true) ? $sort : 'name') {
        'start_date' => 'e.start_date DESC NULLS LAST, m.display_name',
        'department' => 'm.primary_department NULLS LAST, m.display_name',
        default => 'm.display_name',
    };
    $sql = <<<SQL
        FROM mcp_members m
        LEFT JOIN mcp_employment_profiles e ON e.member_id = m.member_id
        WHERE (%s)
    SQL;
    $from = sprintf($sql, implode(') AND (', $where));
    $count = $pdo->prepare('SELECT count(*) ' . $from);
    $count->execute($args);
    $total = (int) $count->fetchColumn();
    $st = $pdo->prepare(<<<SQL
        SELECT m.member_id, m.display_name, m.email, m.member_kind, m.business_role, m.is_external, m.status AS member_status,
               m.job_title, m.primary_department, m.primary_department_id, m.hr_capability,
               e.employee_no, e.employment_type, e.status AS employment_status, e.start_date, e.end_date, e.manager_name
        {$from}
        ORDER BY {$order}
        LIMIT :lim OFFSET :off
    SQL);
    foreach ($args as $k => $v) {
        $st->bindValue($k, $v);
    }
    $st->bindValue('lim', $per, PDO::PARAM_INT);
    $st->bindValue('off', max(0, $page - 1) * $per, PDO::PARAM_INT);
    $st->execute();
    return ['rows' => $st->fetchAll(), 'total' => $total];
}

/** One person: the mirror row, departments, employment (as the view shows it), manager, reports. */
function find_person(PDO $pdo, int $memberId): ?array
{
    $st = $pdo->prepare('SELECT * FROM mcp_members WHERE member_id = :id');
    $st->execute(['id' => $memberId]);
    $person = $st->fetch();
    if ($person === false) {
        return null;
    }
    $st = $pdo->prepare('SELECT department_id, department_name, is_admin, is_primary, joined_at FROM mcp_department_members WHERE member_id = :id AND left_at IS NULL ORDER BY is_primary DESC, department_name');
    $st->execute(['id' => $memberId]);
    $person['departments'] = $st->fetchAll();
    $st = $pdo->prepare('SELECT * FROM mcp_employment_profiles WHERE member_id = :id');
    $st->execute(['id' => $memberId]);
    $person['employment'] = $st->fetch() ?: null;
    $st = $pdo->prepare('SELECT member_id, display_name, job_title, status FROM mcp_employment_profiles WHERE manager_member_id = :id ORDER BY display_name');
    $st->execute(['id' => $memberId]);
    $person['reports'] = $st->fetchAll();
    $st = $pdo->prepare('SELECT department_id, name FROM mcp_departments WHERE manager_member_id = :id AND archived_at IS NULL ORDER BY name');
    $st->execute(['id' => $memberId]);
    $person['manages'] = $st->fetchAll();
    return $person;
}

/** The employment record from the BASE table — the form's source, HR staff only (the caller checks). */
function find_employment(PDO $pdo, int $memberId): ?array
{
    $st = $pdo->prepare('SELECT * FROM employment_profiles WHERE member_id = :id');
    $st->execute(['id' => $memberId]);
    return $st->fetch() ?: null;
}

function next_employee_no(PDO $pdo): string
{
    $prefix = (string) $pdo->query('SELECT employee_no_prefix FROM hr_settings WHERE id = 1')->fetchColumn();
    return $prefix . str_pad((string) $pdo->query("SELECT nextval('employee_no_seq')")->fetchColumn(), 4, '0', STR_PAD_LEFT);
}

/** Active humans for the manager select (never the person themself). */
function manager_options(PDO $pdo, ?int $exceptMemberId): array
{
    $st = $pdo->prepare("SELECT member_id, display_name, job_title FROM mcp_members WHERE member_kind = 'human' AND status = 'active' AND member_id <> :x ORDER BY display_name");
    $st->execute(['x' => $exceptMemberId ?? 0]);
    return $st->fetchAll();
}

function department_options(PDO $pdo): array
{
    return $pdo->query('SELECT department_id, name FROM mcp_departments WHERE archived_at IS NULL ORDER BY name')->fetchAll();
}

/** The manager a new record defaults to: the primary department's manager, unless that is the person. */
function default_manager_for(PDO $pdo, int $memberId): ?int
{
    $st = $pdo->prepare('SELECT d.manager_member_id FROM mcp_department_members dm JOIN mcp_departments d ON d.department_id = dm.department_id
                          WHERE dm.member_id = :id AND dm.left_at IS NULL ORDER BY dm.is_primary DESC LIMIT 1');
    $st->execute(['id' => $memberId]);
    $m = $st->fetchColumn();
    return $m === false || $m === null || (int) $m === $memberId ? null : (int) $m;
}

/** Create or change the record. $fields are validated and named as the columns. Returns the row. */
function upsert_employment(PDO $pdo, int $memberId, array $fields, int $actorId): array
{
    $st = $pdo->prepare(<<<'SQL'
        INSERT INTO employment_profiles (member_id, employee_no, employment_type, manager_member_id, work_location, start_date,
            probation_end_date, status, weekly_hours, pay_schedule, personal_email, address, emergency_contact, birth_date,
            national_id_last4, notes, created_by)
        VALUES (:member_id, :employee_no, :employment_type, :manager_member_id, :work_location, :start_date,
            :probation_end_date, :status, :weekly_hours, :pay_schedule, :personal_email, :address, :emergency_contact, :birth_date,
            :national_id_last4, :notes, :created_by)
        ON CONFLICT (member_id) DO UPDATE SET employee_no = EXCLUDED.employee_no, employment_type = EXCLUDED.employment_type,
            manager_member_id = EXCLUDED.manager_member_id, work_location = EXCLUDED.work_location, start_date = EXCLUDED.start_date,
            probation_end_date = EXCLUDED.probation_end_date, status = EXCLUDED.status, weekly_hours = EXCLUDED.weekly_hours,
            pay_schedule = EXCLUDED.pay_schedule, personal_email = EXCLUDED.personal_email, address = EXCLUDED.address,
            emergency_contact = EXCLUDED.emergency_contact, birth_date = EXCLUDED.birth_date, national_id_last4 = EXCLUDED.national_id_last4,
            notes = EXCLUDED.notes
        RETURNING *
    SQL);
    $st->execute([
        'member_id' => $memberId, 'employee_no' => $fields['employee_no'], 'employment_type' => $fields['employment_type'],
        'manager_member_id' => $fields['manager_member_id'], 'work_location' => $fields['work_location'], 'start_date' => $fields['start_date'],
        'probation_end_date' => $fields['probation_end_date'], 'status' => $fields['status'], 'weekly_hours' => $fields['weekly_hours'],
        'pay_schedule' => $fields['pay_schedule'], 'personal_email' => $fields['personal_email'], 'address' => $fields['address'],
        'emergency_contact' => $fields['emergency_contact'], 'birth_date' => $fields['birth_date'], 'national_id_last4' => $fields['national_id_last4'],
        'notes' => $fields['notes'], 'created_by' => $actorId,
    ]);
    return $st->fetch();
}

function end_employment(PDO $pdo, int $memberId, string $endDate, ?string $reason): array
{
    $st = $pdo->prepare("UPDATE employment_profiles SET status = 'ended', end_date = :d, end_reason = :r WHERE member_id = :id RETURNING *");
    $st->execute(['d' => $endDate, 'r' => $reason, 'id' => $memberId]);
    $row = $st->fetch();
    if ($row === false) {
        throw new RuntimeException('No employment record.');
    }
    return $row;
}

/** Leave requests still live after an end date: cancelled by the ending, and listed for the log. */
function cancel_leave_after(PDO $pdo, int $memberId, string $endDate, int $actorId): array
{
    $st = $pdo->prepare("UPDATE leave_requests SET status = 'cancelled', decided_by = :a, decided_at = now(), decision_note = 'employment ended'
                          WHERE member_id = :m AND status IN ('requested', 'approved') AND start_date > :d RETURNING id, status");
    $st->execute(['a' => $actorId, 'm' => $memberId, 'd' => $endDate]);
    return $st->fetchAll();
}

/** History rows for the person from the activity view (HA1), newest first. */
function person_history(PDO $pdo, int $memberId, int $limit = 50): array
{
    $st = $pdo->prepare(<<<'SQL'
        SELECT activity_id, occurred_at, actor_name, actor_kind, source, action, screen, entity_type, entity_id, after, kernel_request_id
          FROM mcp_activity_log
         WHERE (entity_type = 'member' AND entity_id = :m) OR subject_member_id = :m
         ORDER BY occurred_at DESC LIMIT :lim
    SQL);
    $st->bindValue('m', $memberId, PDO::PARAM_INT);
    $st->bindValue('lim', $limit, PDO::PARAM_INT);
    $st->execute();
    return $st->fetchAll();
}
