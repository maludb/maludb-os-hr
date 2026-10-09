<?php
declare(strict_types=1);

/**
 * app/pickers.php — the registry of what an HR form can pick (app/picker.php has the contract; design-system
 * reference record-picker.md). One entry per kind of record.
 *
 * HR differs from the reference in two ways, both on purpose:
 *   - `gate` (a closure returning bool) replaces `roles`: HR has no role column to test, its rights are SQL
 *     functions (hr_is_staff(), hr_can_run_pay(), is_manager_of()…), so each source asks the same gate the screens
 *     that use the field ask. A source's gate is never wider than its screens' — and every `search` reads the
 *     feature's own list query, which reads the mcp_* views, so the PEOPLE RULE (a manager sees their reports, HR
 *     everyone) is the view's, not a copy of it here.
 *   - the feature queries are un-paged lists (a few hundred people at most), so `search` filters and pages the
 *     list they return (picker_page()) rather than adding a second query.
 * Rows: id, label, detail?, badge?, color?.
 */

/** Filter a list by a case-insensitive substring of label + detail and cut one page of 25. */
function picker_page(array $rows, string $q, int $page, int $size = 25): array
{
    if ($q !== '') {
        $rows = array_values(array_filter($rows, static fn (array $r): bool => mb_stripos($r['label'] . ' ' . ($r['detail'] ?? ''), $q) !== false));
    }
    $total = count($rows);
    $pages = max(1, (int) ceil($total / $size));
    $page = min(max(1, $page), $pages);
    return ['rows' => array_slice($rows, ($page - 1) * $size, $size), 'total' => $total, 'page' => $page, 'page_size' => $size, 'pages' => $pages];
}

/** The caller manages at least one person (the reviews form's own test). */
function picker_manages_anyone(): bool
{
    static $v = null;
    return $v ??= db_bool(db(), 'SELECT EXISTS (SELECT 1 FROM mcp_employment_profiles e WHERE hr_is_manager_of(e.member_id))');
}

/** Reviews: the people the caller may schedule one for — HR staff everyone, a manager their reports. */
function picker_reviewees(PDO $pdo): array
{
    require_once __DIR__ . '/features/reviews/queries.php';
    return array_values(array_filter(review_people_options($pdo), static fn (array $p): bool => hr_is_staff() || is_manager_of((int) $p['member_id'])));
}

return [
    // Employees the caller can see (leave request "For", balance "Person"): the view decides who — a manager their
    // reports and themself, HR staff everyone. Label marks the caller "(you)" as the select did.
    'employee' => [
        'title' => 'People', 'noun' => 'person', 'params' => [],
        'gate' => static fn (): bool => (current_member()['member_kind'] ?? '') === 'human',
        'search' => static function (PDO $pdo, string $q, array $params, int $page): array {
            require_once __DIR__ . '/features/leave/queries.php';
            $me = (int) current_member_id();
            return picker_page(array_map(static fn (array $p): array => [
                'id' => (int) $p['member_id'], 'label' => $p['display_name'] . ((int) $p['member_id'] === $me ? ' (you)' : ''),
            ], leave_people_options($pdo)), $q, $page);
        },
        'label' => static function (PDO $pdo, int $id): ?string {
            require_once __DIR__ . '/features/leave/queries.php';
            foreach (leave_people_options($pdo) as $p) {
                if ((int) $p['member_id'] === $id) {
                    return $p['display_name'] . ($id === (int) current_member_id() ? ' (you)' : '');
                }
            }
            return null;
        },
    ],
    // The review form's Person: HR staff everyone, a manager only their reports (is_manager_of, as the form filtered).
    'reviewee' => [
        'title' => 'People', 'noun' => 'person', 'params' => [],
        'gate' => static fn (): bool => (current_member()['member_kind'] ?? '') === 'human' && (hr_is_staff() || picker_manages_anyone()),
        'search' => static fn (PDO $pdo, string $q, array $params, int $page): array => picker_page(array_map(static fn (array $p): array => [
            'id' => (int) $p['member_id'], 'label' => $p['display_name'],
        ], picker_reviewees($pdo)), $q, $page),
        'label' => static function (PDO $pdo, int $id): ?string {
            foreach (picker_reviewees($pdo) as $p) {
                if ((int) $p['member_id'] === $id) {
                    return $p['display_name'];
                }
            }
            return null;
        },
    ],
    // Active people who may manage or review: a department's manager, a person's manager, a review's reviewer
    // (manager_options — the select's own list; `except` leaves the person themself out of their own manager field).
    'manager' => [
        'title' => 'People', 'noun' => 'person', 'params' => ['except'],
        'gate' => static fn (): bool => (current_member()['member_kind'] ?? '') === 'human'
            && (hr_is_staff() || is_dept_admin() || is_super_admin() || picker_manages_anyone()),
        'search' => static function (PDO $pdo, string $q, array $params, int $page): array {
            require_once __DIR__ . '/features/people/queries.php';
            return picker_page(array_map(static fn (array $m): array => [
                'id' => (int) $m['member_id'], 'label' => $m['display_name'], 'detail' => $m['job_title'] ?: null,
            ], manager_options($pdo, $params['except'] ?? null)), $q, $page);
        },
        'label' => static function (PDO $pdo, int $id): ?string {
            $st = $pdo->prepare("SELECT display_name FROM mcp_members WHERE member_id = :id AND member_kind = 'human' AND status = 'active'");
            $st->execute(['id' => $id]);
            return $st->fetchColumn() ?: null;
        },
    ],
    // A draft pay run's "Add a person": eligible people not yet on the run (Payroll or HR staff).
    'payrun_person' => [
        'title' => 'People', 'noun' => 'person', 'params' => ['run_id'],
        'gate' => static fn (): bool => can_run_pay(),
        'search' => static function (PDO $pdo, string $q, array $params, int $page): array {
            return picker_page(array_map(static fn (array $p): array => [
                'id' => (int) $p['member_id'], 'label' => $p['display_name'], 'detail' => $p['base_note'] ?: null,
            ], picker_payrun_addable($pdo, (int) ($params['run_id'] ?? 0))), $q, $page);
        },
        'label' => static function (PDO $pdo, int $id): ?string {
            $st = $pdo->prepare("SELECT display_name FROM mcp_members WHERE member_id = :id AND member_kind = 'human'");
            $st->execute(['id' => $id]);
            return $st->fetchColumn() ?: null;
        },
    ],
    // A department's "Add a member": active members not yet in it (HR staff or an administrator of that department).
    'department_candidate' => [
        'title' => 'People and agents', 'noun' => 'member', 'params' => ['department_id'],
        'gate' => static fn (): bool => (current_member()['member_kind'] ?? '') === 'human' && (hr_is_staff() || is_dept_admin()),
        'search' => static function (PDO $pdo, string $q, array $params, int $page): array {
            require_once __DIR__ . '/features/directory/queries.php';
            $d = (int) ($params['department_id'] ?? 0);
            if ($d === 0 || !(hr_is_staff() || is_admin_of($d))) {
                return picker_page([], $q, $page);
            }
            return picker_page(array_map(static fn (array $a): array => [
                'id' => (int) $a['member_id'], 'label' => $a['display_name'],
                'badge' => $a['member_kind'] === 'agent' ? 'Agent' : null, 'color' => 'info',
            ], addable_members($pdo, $d)), $q, $page);
        },
        'label' => static function (PDO $pdo, int $id): ?string {
            $st = $pdo->prepare('SELECT display_name FROM mcp_members WHERE member_id = :id');
            $st->execute(['id' => $id]);
            return $st->fetchColumn() ?: null;
        },
    ],
];

function picker_payrun_addable(PDO $pdo, int $runId): array
{
    require_once __DIR__ . '/features/payruns/queries.php';
    $run = $runId > 0 ? find_pay_run($pdo, $runId) : null;
    if ($run === null || $run['status'] !== 'draft') {
        return [];
    }
    $on = array_map('intval', array_column($run['items'], 'member_id'));
    return array_values(array_filter(eligible_people($pdo, $run['period_end'], $run['currency']), static fn (array $p): bool => !in_array((int) $p['member_id'], $on, true)));
}
