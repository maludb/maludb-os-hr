<?php
declare(strict_types=1);

/**
 * Directory slice — reads for the forms (from the views) and the ONE new pattern: a write goes to
 * the kernel as the acting person (directory_call), the mirror takes the kernel's answer, the local
 * log carries the kernel's request id. Never a mirror row written from a form.
 */

const DEPARTMENTS_PER_PAGE = 24;

/** @return array{rows: array, total: int} */
function find_departments_list(PDO $pdo, array $filters, int $page = 1, int $per = DEPARTMENTS_PER_PAGE): array
{
    $where = ['1 = 1'];
    $args = [];
    if (empty($filters['archived'])) {
        $where[] = 'd.archived_at IS NULL';
    }
    $q = trim((string) ($filters['q'] ?? ''));
    if ($q !== '') {
        $where[] = '(d.name ILIKE :q OR d.description ILIKE :q)';
        $args['q'] = '%' . mb_substr($q, 0, 100) . '%';
    }
    $from = 'FROM mcp_departments d WHERE ' . implode(' AND ', $where);
    $count = $pdo->prepare('SELECT count(*) ' . $from);
    $count->execute($args);
    $st = $pdo->prepare("SELECT d.* {$from} ORDER BY d.archived_at NULLS FIRST, d.name LIMIT :lim OFFSET :off");
    foreach ($args as $k => $v) {
        $st->bindValue($k, $v);
    }
    $st->bindValue('lim', $per, PDO::PARAM_INT);
    $st->bindValue('off', max(0, $page - 1) * $per, PDO::PARAM_INT);
    $st->execute();
    return ['rows' => $st->fetchAll(), 'total' => (int) $count->fetchColumn()];
}

function find_department_view(PDO $pdo, int $id): ?array
{
    $st = $pdo->prepare('SELECT * FROM mcp_departments WHERE department_id = :id');
    $st->execute(['id' => $id]);
    $d = $st->fetch();
    if ($d === false) {
        return null;
    }
    $st = $pdo->prepare(<<<'SQL'
        SELECT dm.member_id, dm.display_name, dm.member_kind, dm.is_admin, dm.is_primary, dm.joined_at, m.job_title, m.status, m.is_external
          FROM mcp_department_members dm JOIN mcp_members m ON m.member_id = dm.member_id
         WHERE dm.department_id = :id AND dm.left_at IS NULL
         ORDER BY dm.is_admin DESC, dm.display_name
    SQL);
    $st->execute(['id' => $id]);
    $d['members'] = $st->fetchAll();
    $st = $pdo->prepare('SELECT department_id, name, manager_name, people, agents FROM mcp_departments WHERE parent_id = :id AND archived_at IS NULL ORDER BY name');
    $st->execute(['id' => $id]);
    $d['children'] = $st->fetchAll();
    $st = $pdo->prepare(<<<'SQL'
        SELECT lr.leave_request_id, lr.member_id, lr.display_name, lr.leave_type, lr.start_date, lr.end_date, lr.days, lr.status
          FROM mcp_leave_requests lr
          JOIN mcp_department_members dm ON dm.member_id = lr.member_id AND dm.department_id = :id AND dm.left_at IS NULL
         WHERE lr.status = 'approved' AND lr.end_date >= current_date AND lr.start_date <= current_date + 7
         ORDER BY lr.start_date
    SQL);
    $st->execute(['id' => $id]);
    $d['off'] = $st->fetchAll();
    return $d;
}

/** Departments the caller may invite into / change memberships of: theirs, or all for HR staff. */
function administered_departments(PDO $pdo, bool $staff): array
{
    $sql = $staff ? 'SELECT department_id, name FROM mcp_departments WHERE archived_at IS NULL ORDER BY name'
                  : 'SELECT department_id, name FROM mcp_departments WHERE archived_at IS NULL AND i_administer ORDER BY name';
    return $pdo->query($sql)->fetchAll();
}

/** Active members not yet in the department (the Add member select). */
function addable_members(PDO $pdo, int $departmentId): array
{
    $st = $pdo->prepare(<<<'SQL'
        SELECT m.member_id, m.display_name, m.member_kind FROM mcp_members m
         WHERE m.status = 'active' AND m.business_role <> 'super_admin'
           AND NOT EXISTS (SELECT 1 FROM mcp_department_members dm WHERE dm.member_id = m.member_id AND dm.department_id = :d AND dm.left_at IS NULL)
         ORDER BY m.member_kind, m.display_name
    SQL);
    $st->execute(['d' => $departmentId]);
    return $st->fetchAll();
}

/** Departments a department may be parented to: not itself, not its descendants. */
function parent_options(PDO $pdo, ?int $exceptId): array
{
    $st = $pdo->prepare(<<<'SQL'
        WITH RECURSIVE below AS (
            SELECT department_id FROM mcp_departments WHERE department_id = :x
            UNION ALL
            SELECT d.department_id FROM mcp_departments d JOIN below b ON d.parent_id = b.department_id
        )
        SELECT department_id, name FROM mcp_departments
         WHERE archived_at IS NULL AND department_id NOT IN (SELECT department_id FROM below)
         ORDER BY name
    SQL);
    $st->execute(['x' => $exceptId ?? 0]);
    return $st->fetchAll();
}

function present_department(array $d): array
{
    return ['department_id' => (int) $d['department_id'], 'name' => $d['name'], 'description' => $d['description'],
        'parent_id' => $d['parent_id'] !== null ? (int) $d['parent_id'] : null, 'parent_name' => $d['parent_name'] ?? null,
        'manager_member_id' => $d['manager_member_id'] !== null ? (int) $d['manager_member_id'] : null, 'manager_name' => $d['manager_name'],
        'is_system' => !empty($d['is_system']), 'system_key' => $d['system_key'], 'archived_at' => json_ts($d['archived_at']),
        'people' => (int) $d['people'], 'agents' => (int) $d['agents']];
}

/**
 * The write pattern, in one place: call the kernel as the acting person, apply its answer to the
 * mirror, log with the kernel's request id. Returns the kernel's body. Throws DirectoryRefused.
 */
function directory_write(PDO $pdo, string $method, string $path, array $body, string $logAction, string $entityType, ?int $entityId, array $logExtra = []): array
{
    $answer = directory_call($method, $path, $body, (int) current_member_id());
    $doc = $answer['body'] ?? [];
    if (isset($doc['member']) && is_array($doc['member'])) {
        mirror_apply_member($pdo, $doc['member']);
        $entityId ??= (int) $doc['member']['id'];
    }
    if (isset($doc['department']) && is_array($doc['department'])) {
        mirror_apply_department($pdo, $doc['department']);
        $entityId ??= (int) $doc['department']['id'];
    }
    if (isset($doc['membership']) && is_array($doc['membership'])) {
        mirror_apply_membership($pdo, $doc['membership']);
    }
    log_activity($pdo, $logAction, $entityType, $entityId, $logExtra + ['kernel_request_id' => $answer['request_id']]);
    return $doc;
}
