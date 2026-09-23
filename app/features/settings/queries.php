<?php
declare(strict_types=1);

/** HR settings (one row), leave types and holidays. Reads through the views; the forms read the base rows behind the HR gate. */

function hr_settings(PDO $pdo): array
{
    return $pdo->query('SELECT * FROM hr_settings WHERE id = 1')->fetch();
}

function save_hr_settings(PDO $pdo, array $f, int $actorId): array
{
    $st = $pdo->prepare('UPDATE hr_settings SET currency = :c, timezone = :tz, work_days = :wd, employee_no_prefix = :p, updated_by = :u WHERE id = 1 RETURNING *');
    $st->execute(['c' => $f['currency'], 'tz' => $f['timezone'], 'wd' => '{' . implode(',', $f['work_days']) . '}', 'p' => $f['employee_no_prefix'], 'u' => $actorId]);
    return $st->fetch();
}

function find_leave_types(PDO $pdo, bool $includeArchived): array
{
    return $pdo->query('SELECT * FROM mcp_leave_types' . ($includeArchived ? '' : ' WHERE archived_at IS NULL') . ' ORDER BY archived_at NULLS FIRST, name')->fetchAll();
}

function find_leave_type(PDO $pdo, int $id): ?array
{
    $st = $pdo->prepare('SELECT * FROM leave_types WHERE id = :id');
    $st->execute(['id' => $id]);
    return $st->fetch() ?: null;
}

function upsert_leave_type(PDO $pdo, ?int $id, array $f): array
{
    if ($id === null) {
        $st = $pdo->prepare('INSERT INTO leave_types (name, is_paid, accrual_days_per_year, carry_over_days, requires_approval, archived_at)
                             VALUES (:n, :p, :a, :c, :r, :arch) RETURNING *');
    } else {
        $st = $pdo->prepare('UPDATE leave_types SET name = :n, is_paid = :p, accrual_days_per_year = :a, carry_over_days = :c, requires_approval = :r,
                             archived_at = :arch WHERE id = :id RETURNING *');
    }
    $args = ['n' => $f['name'], 'p' => $f['is_paid'] ? 't' : 'f', 'a' => $f['accrual_days_per_year'], 'c' => $f['carry_over_days'],
             'r' => $f['requires_approval'] ? 't' : 'f', 'arch' => $f['archived'] ? date('c') : null];
    if ($id !== null) {
        $args['id'] = $id;
        if (!$f['archived']) {
            // un-archiving keeps null; archiving an already archived type keeps its date
            $cur = find_leave_type($pdo, $id);
            $args['arch'] = null;
        } else {
            $cur = find_leave_type($pdo, $id);
            $args['arch'] = $cur['archived_at'] ?? date('c');
        }
    }
    $st->execute($args);
    return $st->fetch();
}

function find_holidays(PDO $pdo, int $year): array
{
    $st = $pdo->prepare('SELECT * FROM mcp_holidays WHERE EXTRACT(year FROM holiday_date) = :y ORDER BY holiday_date');
    $st->execute(['y' => $year]);
    return $st->fetchAll();
}

function find_holiday(PDO $pdo, int $id): ?array
{
    $st = $pdo->prepare('SELECT * FROM holidays WHERE id = :id');
    $st->execute(['id' => $id]);
    return $st->fetch() ?: null;
}

function upsert_holiday(PDO $pdo, ?int $id, string $date, string $name, int $actorId): array
{
    if ($id === null) {
        $st = $pdo->prepare('INSERT INTO holidays (holiday_date, name, created_by) VALUES (:d, :n, :a) RETURNING *');
        $st->execute(['d' => $date, 'n' => $name, 'a' => $actorId]);
    } else {
        $st = $pdo->prepare('UPDATE holidays SET holiday_date = :d, name = :n WHERE id = :id RETURNING *');
        $st->execute(['d' => $date, 'n' => $name, 'id' => $id]);
    }
    return $st->fetch();
}

function delete_holiday(PDO $pdo, int $id): bool
{
    $st = $pdo->prepare('DELETE FROM holidays WHERE id = :id');
    $st->execute(['id' => $id]);
    return $st->rowCount() === 1;
}
