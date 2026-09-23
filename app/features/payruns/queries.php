<?php
declare(strict_types=1);

/**
 * Pay runs as records (docs/build-specs/pay-runs.md). A draft takes everyone eligible and computes a
 * base line from each person's pay basis — or puts them on the run with NO base line and a note
 * saying why. Totals are derived by triggers (db/006); a run that is not a draft is frozen.
 */

const PAY_RUN_STATUSES = ['draft', 'approved', 'paid', 'void'];
const PAY_LINE_KINDS = ['earning', 'deduction', 'employer_cost', 'reimbursement'];
const PAY_PERIODS = ['weekly' => 52, 'biweekly' => 26, 'semimonthly' => 24, 'monthly' => 12];

/** @return array{rows: array, total: int} */
function find_pay_runs(PDO $pdo, array $filters, int $page = 1, int $per = 30): array
{
    $where = ['1 = 1'];
    $args = [];
    if (in_array($filters['status'] ?? '', PAY_RUN_STATUSES, true)) {
        $where[] = 'r.status = :status';
        $args['status'] = $filters['status'];
    }
    if (!empty($filters['period']) && preg_match('/^\d{4}-\d{2}$/', (string) $filters['period'])) {
        $where[] = "to_char(r.period_end, 'YYYY-MM') = :period";
        $args['period'] = $filters['period'];
    }
    $from = 'FROM mcp_pay_runs r WHERE ' . implode(' AND ', $where);
    $count = $pdo->prepare('SELECT count(*) ' . $from);
    $count->execute($args);
    $st = $pdo->prepare("SELECT r.* {$from} ORDER BY r.period_end DESC, r.pay_run_id DESC LIMIT :lim OFFSET :off");
    foreach ($args as $k => $v) {
        $st->bindValue($k, $v);
    }
    $st->bindValue('lim', $per, PDO::PARAM_INT);
    $st->bindValue('off', max(0, $page - 1) * $per, PDO::PARAM_INT);
    $st->execute();
    return ['rows' => $st->fetchAll(), 'total' => (int) $count->fetchColumn()];
}

/** One run with its items and every item's lines (through the views). */
function find_pay_run(PDO $pdo, int $id): ?array
{
    $st = $pdo->prepare('SELECT * FROM mcp_pay_runs WHERE pay_run_id = :id');
    $st->execute(['id' => $id]);
    $run = $st->fetch();
    if ($run === false) {
        return null;
    }
    $st = $pdo->prepare('SELECT * FROM mcp_pay_run_items WHERE pay_run_id = :id ORDER BY display_name');
    $st->execute(['id' => $id]);
    $run['items'] = $st->fetchAll();
    $st = $pdo->prepare('SELECT * FROM mcp_pay_run_lines WHERE pay_run_id = :id ORDER BY pay_run_item_id, is_base DESC, kind, pay_run_line_id');
    $st->execute(['id' => $id]);
    $lines = [];
    foreach ($st->fetchAll() as $l) {
        $lines[(int) $l['pay_run_item_id']][] = $l;
    }
    foreach ($run['items'] as &$i) {
        $i['lines'] = $lines[(int) $i['pay_run_item_id']] ?? [];
    }
    return $run;
}

function find_pay_run_item(PDO $pdo, int $itemId): ?array
{
    $st = $pdo->prepare('SELECT * FROM mcp_pay_run_items WHERE pay_run_item_id = :id');
    $st->execute(['id' => $itemId]);
    $item = $st->fetch();
    if ($item === false) {
        return null;
    }
    $st = $pdo->prepare('SELECT * FROM mcp_pay_run_lines WHERE pay_run_item_id = :id ORDER BY is_base DESC, kind, pay_run_line_id');
    $st->execute(['id' => $itemId]);
    $item['lines'] = $st->fetchAll();
    return $item;
}

/**
 * Who a run for this period and currency takes, and why some cannot be paid on it: every active
 * human employed now, with their pay basis at the period's end. `eligible` = has a base to compute.
 */
function eligible_people(PDO $pdo, string $periodEnd, string $currency): array
{
    $st = $pdo->prepare(<<<'SQL'
        SELECT e.member_id, m.display_name, e.employment_type, e.pay_schedule, e.weekly_hours, p.pay_type, p.pay_rate, p.currency
          FROM employment_profiles e
          JOIN members m ON m.id = e.member_id
          LEFT JOIN LATERAL hr_pay_as_of(e.member_id, :d) p ON true
         WHERE e.status IN ('active', 'on_leave') AND m.member_kind = 'human' AND m.status = 'active'
         ORDER BY m.display_name
    SQL);
    $st->execute(['d' => $periodEnd]);
    $out = [];
    foreach ($st->fetchAll() as $r) {
        $r['base_note'] = pay_base_note($r, $currency);
        $r['eligible'] = $r['base_note'] === null || in_array($r['pay_type'], ['hourly', 'daily'], true);
        $out[] = $r;
    }
    return $out;
}

/** One person's pay basis at a date, with the employment facts the base line needs (schedule, hours). */
function pay_basis_for(PDO $pdo, int $memberId, string $date): array
{
    $p = $pdo->prepare('SELECT p.pay_type, p.pay_rate, p.currency, e.pay_schedule, e.weekly_hours FROM employment_profiles e
                        LEFT JOIN LATERAL hr_pay_as_of(e.member_id, :d) p ON true WHERE e.member_id = :m');
    $p->execute(['m' => $memberId, 'd' => $date]);
    return $p->fetch() ?: ['pay_type' => null, 'pay_rate' => null, 'currency' => null, 'pay_schedule' => null, 'weekly_hours' => null];
}

/** Why a person has no base line — null when a base can be computed (or keyed later for hourly/daily). */
function pay_base_note(array $p, string $currency): ?string
{
    if ($p['pay_type'] === null) {
        return 'no pay recorded';
    }
    if ($p['currency'] !== $currency) {
        return 'paid in ' . $p['currency'];
    }
    if ($p['pay_type'] === 'per_invoice') {
        return 'paid on invoices';
    }
    if ($p['pay_type'] === 'salary' && !isset(PAY_PERIODS[$p['pay_schedule'] ?? ''])) {
        return 'no pay schedule';
    }
    if ($p['pay_type'] === 'hourly') {
        return 'hours not keyed';
    }
    if ($p['pay_type'] === 'daily') {
        return 'days not keyed';
    }
    return null;
}

/** Create the draft: the run, an item per person, and each computable base line. Returns the run row. */
function create_pay_run(PDO $pdo, array $f, array $memberIds, int $actorId): array
{
    $st = $pdo->prepare('INSERT INTO pay_runs (period_start, period_end, pay_date, currency, note, created_by) VALUES (:s, :e, :p, :c, :n, :a) RETURNING *');
    $st->execute(['s' => $f['period_start'], 'e' => $f['period_end'], 'p' => $f['pay_date'], 'c' => $f['currency'], 'n' => $f['note'], 'a' => $actorId]);
    $run = $st->fetch();
    $people = eligible_people($pdo, $f['period_end'], $f['currency']);
    foreach ($people as $p) {
        if ($memberIds !== [] && !in_array((int) $p['member_id'], $memberIds, true)) {
            continue;
        }
        add_pay_run_item($pdo, (int) $run['id'], $p, $f['currency']);
    }
    return $run;
}

/** One person onto a draft, with the base line when it can be computed. */
function add_pay_run_item(PDO $pdo, int $runId, array $p, string $currency): array
{
    $st = $pdo->prepare('INSERT INTO pay_run_items (pay_run_id, member_id, base_note) VALUES (:r, :m, :n) RETURNING *');
    $st->execute(['r' => $runId, 'm' => (int) $p['member_id'], 'n' => $p['base_note']]);
    $item = $st->fetch();
    if ($p['base_note'] === null && $p['pay_type'] === 'salary') {
        $amount = round((float) $p['pay_rate'] / PAY_PERIODS[$p['pay_schedule']], 2);
        $pdo->prepare("INSERT INTO pay_run_lines (pay_run_item_id, kind, code, description, quantity, rate, amount, is_base) VALUES (:i, 'earning', 'base', :d, 1, :rate, :a, true)")
            ->execute(['i' => (int) $item['id'], 'd' => 'Salary, 1 of ' . PAY_PERIODS[$p['pay_schedule']] . ' periods', 'rate' => $amount, 'a' => number_format($amount, 2, '.', '')]);
    }
    return $item;
}

/**
 * Key hours or days and the hand-entered lines on a draft item; the base line for an hourly or daily
 * person is (re)computed from the quantity keyed. $lines: [{kind, code, description, quantity, rate, amount}].
 */
function save_pay_run_item(PDO $pdo, int $itemId, ?string $hours, ?string $days, array $lines, ?string $note): array
{
    $st = $pdo->prepare('SELECT i.*, r.period_end, r.currency AS run_currency FROM pay_run_items i JOIN pay_runs r ON r.id = i.pay_run_id WHERE i.id = :id FOR UPDATE');
    $st->execute(['id' => $itemId]);
    $item = $st->fetch();
    if ($item === false) {
        throw new RuntimeException('No such item.');
    }
    $basis = pay_basis_for($pdo, (int) $item['member_id'], (string) $item['period_end']);
    $note2 = pay_base_note($basis, (string) $item['run_currency']);
    $pdo->prepare('DELETE FROM pay_run_lines WHERE pay_run_item_id = :id')->execute(['id' => $itemId]);
    $ins = $pdo->prepare('INSERT INTO pay_run_lines (pay_run_item_id, kind, code, description, quantity, rate, amount, is_base) VALUES (:i, :k, :c, :d, :q, :r, :a, :b)');
    $baseNote = $note2;
    if ($basis['pay_type'] === 'salary' && $note2 === null) {
        $periods = PAY_PERIODS[(string) $basis['pay_schedule']];
        $amount = round((float) $basis['pay_rate'] / $periods, 2);
        $ins->execute(['i' => $itemId, 'k' => 'earning', 'c' => 'base', 'd' => 'Salary, 1 of ' . $periods . ' periods', 'q' => 1, 'r' => $amount, 'a' => number_format($amount, 2, '.', ''), 'b' => 't']);
    } elseif ($basis['pay_type'] === 'hourly' && $basis['currency'] === $item['run_currency'] && $hours !== null && (float) $hours > 0) {
        $amount = round((float) $hours * (float) $basis['pay_rate'], 2);
        $ins->execute(['i' => $itemId, 'k' => 'earning', 'c' => 'base', 'd' => days_label($hours) . ' hours at ' . money_label($basis['pay_rate'], (string) $basis['currency']), 'q' => $hours, 'r' => $basis['pay_rate'], 'a' => number_format($amount, 2, '.', ''), 'b' => 't']);
        $baseNote = null;
    } elseif ($basis['pay_type'] === 'daily' && $basis['currency'] === $item['run_currency'] && $days !== null && (float) $days > 0) {
        $amount = round((float) $days * (float) $basis['pay_rate'], 2);
        $ins->execute(['i' => $itemId, 'k' => 'earning', 'c' => 'base', 'd' => days_label($days) . ' days at ' . money_label($basis['pay_rate'], (string) $basis['currency']), 'q' => $days, 'r' => $basis['pay_rate'], 'a' => number_format($amount, 2, '.', ''), 'b' => 't']);
        $baseNote = null;
    }
    foreach ($lines as $l) {
        $ins->execute(['i' => $itemId, 'k' => $l['kind'], 'c' => $l['code'], 'd' => $l['description'], 'q' => $l['quantity'], 'r' => $l['rate'], 'a' => $l['amount'], 'b' => 'f']);
    }
    $upd = $pdo->prepare('UPDATE pay_run_items SET hours = :h, note = :n, base_note = :bn WHERE id = :id RETURNING *');
    $upd->execute(['h' => $hours ?? $days, 'n' => $note, 'bn' => $baseNote, 'id' => $itemId]);
    $pdo->prepare('SELECT hr_recompute_pay_run_item(:id)')->execute(['id' => $itemId]);
    return $upd->fetch();
}

function remove_pay_run_item(PDO $pdo, int $itemId): bool
{
    $st = $pdo->prepare('DELETE FROM pay_run_items WHERE id = :id');
    $st->execute(['id' => $itemId]);
    return $st->rowCount() === 1;
}

/** draft → approved → paid; approved → draft; draft|approved → void; the guard in SQL keeps the rest frozen. */
function set_pay_run_status(PDO $pdo, int $id, string $to, int $actorId, array $extra = []): array
{
    $st = $pdo->prepare('SELECT * FROM pay_runs WHERE id = :id FOR UPDATE');
    $st->execute(['id' => $id]);
    $run = $st->fetch();
    if ($run === false) {
        throw new RuntimeException('No such pay run.');
    }
    $allowed = ['draft' => ['approved', 'void'], 'approved' => ['paid', 'draft', 'void'], 'paid' => [], 'void' => []];
    if (!in_array($to, $allowed[$run['status']] ?? [], true)) {
        throw new RuntimeException('A ' . $run['status'] . ' run cannot become ' . $to . '.');
    }
    $sql = match ($to) {
        'approved' => 'UPDATE pay_runs SET status = :s, approved_by = :a, approved_at = now() WHERE id = :id RETURNING *',
        'draft' => 'UPDATE pay_runs SET status = :s, approved_by = NULL, approved_at = NULL WHERE id = :id RETURNING *',
        'paid' => 'UPDATE pay_runs SET status = :s, paid_by = :a, paid_at = :paid_at WHERE id = :id RETURNING *',
        'void' => 'UPDATE pay_runs SET status = :s, voided_by = :a, voided_at = now(), void_reason = :reason WHERE id = :id RETURNING *',
    };
    $args = ['s' => $to, 'id' => $id];
    if ($to !== 'draft') {
        $args['a'] = $actorId;
    }
    if ($to === 'paid') {
        $args['paid_at'] = $extra['paid_at'] ?? date('c');
    }
    if ($to === 'void') {
        $args['reason'] = $extra['reason'] ?? null;
    }
    $u = $pdo->prepare($sql);
    $u->execute($args);
    return $u->fetch();
}

function delete_pay_run(PDO $pdo, int $id): bool
{
    $st = $pdo->prepare("DELETE FROM pay_runs WHERE id = :id AND status = 'draft'");
    $st->execute(['id' => $id]);
    return $st->rowCount() === 1;
}

/** The caller's items on approved and paid runs (the view already limits them). */
function my_payslips(PDO $pdo, int $memberId): array
{
    $st = $pdo->prepare("SELECT * FROM mcp_pay_run_items WHERE member_id = :m AND run_status IN ('approved', 'paid') ORDER BY pay_date DESC LIMIT 36");
    $st->execute(['m' => $memberId]);
    $items = $st->fetchAll();
    foreach ($items as &$i) {
        $l = $pdo->prepare('SELECT * FROM mcp_pay_run_lines WHERE pay_run_item_id = :id ORDER BY is_base DESC, kind, pay_run_line_id');
        $l->execute(['id' => (int) $i['pay_run_item_id']]);
        $i['lines'] = $l->fetchAll();
    }
    return $items;
}

/** The feed for the accounting application: hr.pay-runs/1. */
function pay_runs_feed(PDO $pdo, array $filters): array
{
    $where = [in_array($filters['status'] ?? '', ['approved', 'paid'], true) ? 'r.status = :status' : "r.status = 'paid'"];
    $args = [];
    if (isset($filters['status']) && in_array($filters['status'], ['approved', 'paid'], true)) {
        $args['status'] = $filters['status'];
    }
    if (!empty($filters['period']) && preg_match('/^\d{4}-\d{2}$/', (string) $filters['period'])) {
        $where[] = "to_char(r.pay_date, 'YYYY-MM') = :period";
        $args['period'] = $filters['period'];
    }
    if (!empty($filters['run'])) {
        $where[] = 'r.run_no = :run';
        $args['run'] = $filters['run'];
    }
    $st = $pdo->prepare('SELECT r.* FROM mcp_pay_runs r WHERE ' . implode(' AND ', $where) . ' ORDER BY r.pay_date, r.pay_run_id LIMIT 100');
    $st->execute($args);
    $runs = [];
    foreach ($st->fetchAll() as $r) {
        $full = find_pay_run($pdo, (int) $r['pay_run_id']);
        $items = [];
        foreach ($full['items'] as $i) {
            $ep = $pdo->prepare('SELECT employee_no, primary_department FROM mcp_employment_profiles WHERE member_id = :m');
            $ep->execute(['m' => (int) $i['member_id']]);
            $emp = $ep->fetch() ?: [];
            $items[] = ['member_id' => (int) $i['member_id'], 'display_name' => $i['display_name'], 'employee_no' => $emp['employee_no'] ?? null, 'department' => $emp['primary_department'] ?? null,
                'hours' => $i['hours'] !== null ? (float) $i['hours'] : null, 'base_note' => $i['base_note'],
                'totals' => ['gross' => (float) $i['gross_amount'], 'deductions' => (float) $i['deductions'], 'employer_cost' => (float) $i['employer_cost'], 'reimbursements' => (float) $i['reimbursements'], 'net' => (float) $i['net_amount']],
                'lines' => array_map(static fn (array $l): array => ['kind' => $l['kind'], 'code' => $l['code'], 'description' => $l['description'], 'quantity' => $l['quantity'] !== null ? (float) $l['quantity'] : null, 'rate' => $l['rate'] !== null ? (float) $l['rate'] : null, 'amount' => (float) $l['amount'], 'is_base' => (bool) $l['is_base']], $i['lines'])];
        }
        $runs[] = ['run_no' => $r['run_no'], 'period_start' => $r['period_start'], 'period_end' => $r['period_end'], 'pay_date' => $r['pay_date'], 'status' => $r['status'], 'currency' => $r['currency'],
            'approved_at' => json_ts($r['approved_at']), 'paid_at' => json_ts($r['paid_at']),
            'totals' => ['gross' => (float) $r['gross_total'], 'deductions' => (float) $r['deductions_total'], 'employer_cost' => (float) $r['employer_cost_total'], 'reimbursements' => (float) $r['reimbursements_total'], 'net' => (float) $r['net_total']],
            'items' => $items];
    }
    return $runs;
}

function pay_run_status_color(string $s): string
{
    return match ($s) { 'draft' => 'warning', 'approved' => 'info', 'paid' => 'success', default => 'secondary' };
}

/** "kind:code:amount:description" comma-separated (the agents' door) → line rows; errors are sentences. */
function parse_lines_text(string $text): array
{
    $lines = [];
    $errors = [];
    foreach (array_filter(array_map('trim', explode(',', $text))) as $piece) {
        $parts = array_map('trim', explode(':', $piece, 4));
        if (count($parts) < 3) { $errors[] = "A line is kind:code:amount:description — '{$piece}' is not."; continue; }
        [$kind, $code, $amount] = $parts;
        $desc = $parts[3] ?? null;
        if (!in_array($kind, PAY_LINE_KINDS, true)) { $errors[] = "'{$kind}' is not a line kind (earning, deduction, employer_cost, reimbursement)."; continue; }
        if (!is_numeric($amount) || (float) $amount < 0) { $errors[] = "'{$amount}' is not an amount."; continue; }
        $lines[] = ['kind' => $kind, 'code' => mb_substr($code, 0, 40), 'description' => $desc !== null && $desc !== '' ? mb_substr($desc, 0, 200) : null, 'quantity' => null, 'rate' => null, 'amount' => number_format((float) $amount, 2, '.', '')];
    }
    return [$lines, $errors];
}

function present_pay_run(array $r): array
{
    return ['pay_run_id' => (int) $r['pay_run_id'], 'run_no' => $r['run_no'], 'period_start' => $r['period_start'], 'period_end' => $r['period_end'], 'pay_date' => $r['pay_date'],
        'currency' => $r['currency'], 'status' => $r['status'], 'people' => (int) $r['people'], 'gross_total' => (float) $r['gross_total'], 'net_total' => (float) $r['net_total']];
}
