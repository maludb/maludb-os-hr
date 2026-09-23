<?php
declare(strict_types=1);

/** Compensation — the history is the record; the rate in force is hr_pay_as_of(). Reads through the pay-gated views. */

const PAY_TYPES = ['salary', 'hourly', 'daily', 'per_invoice'];

/** Current pay + every change, newest first, for one person (empty when the caller may not see pay). */
function pay_history(PDO $pdo, int $memberId): array
{
    $st = $pdo->prepare('SELECT * FROM mcp_current_pay WHERE member_id = :m');
    $st->execute(['m' => $memberId]);
    $current = $st->fetch() ?: null;
    $st = $pdo->prepare('SELECT * FROM mcp_compensation_changes WHERE member_id = :m ORDER BY effective_date DESC');
    $st->execute(['m' => $memberId]);
    return ['current' => $current, 'changes' => $st->fetchAll()];
}

function find_compensation_change(PDO $pdo, int $id): ?array
{
    $st = $pdo->prepare('SELECT * FROM mcp_compensation_changes WHERE compensation_change_id = :id');
    $st->execute(['id' => $id]);
    return $st->fetch() ?: null;
}

function insert_compensation_change(PDO $pdo, int $memberId, string $date, string $type, string $rate, string $currency, ?string $reason, int $actorId): array
{
    $st = $pdo->prepare('INSERT INTO compensation_changes (member_id, effective_date, pay_type, pay_rate, currency, reason, created_by)
                         VALUES (:m, :d, :t, :r, :c, :reason, :a) RETURNING *');
    $st->execute(['m' => $memberId, 'd' => $date, 't' => $type, 'r' => $rate, 'c' => $currency, 'reason' => $reason, 'a' => $actorId]);
    return $st->fetch();
}

/** Only a future-dated change may go: the past is the record. */
function delete_compensation_change(PDO $pdo, int $id): bool
{
    $st = $pdo->prepare('DELETE FROM compensation_changes WHERE id = :id AND effective_date > current_date');
    $st->execute(['id' => $id]);
    return $st->rowCount() === 1;
}

function pay_type_label(?string $t): string
{
    return match ($t) { 'salary' => 'Salary (per year)', 'hourly' => 'Hourly', 'daily' => 'Daily', 'per_invoice' => 'Per invoice', default => '—' };
}

function money_label($amount, string $currency): string
{
    return $currency . ' ' . number_format((float) $amount, 2);
}
