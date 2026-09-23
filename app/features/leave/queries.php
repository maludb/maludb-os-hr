<?php
declare(strict_types=1);

/**
 * Leave — the rules are in db/007 (hr_leave_days, hr_annual_allowance, hr_grant_annual_leave,
 * hr_leave_committed); these functions apply them. Reads through mcp_* views; writes to the base
 * tables inside the handler's transaction.
 */

const LEAVE_PER_PAGE = 30;
const LEAVE_STATUSES = ['requested', 'approved', 'rejected', 'cancelled'];

/** @return array{rows: array, total: int} */
function find_leave_requests(PDO $pdo, array $filters, int $page = 1, int $per = LEAVE_PER_PAGE): array
{
    $where = ['1 = 1'];
    $args = [];
    if (!empty($filters['awaiting_me'])) {
        $where[] = 'lr.awaiting_me';
    }
    if (!empty($filters['mine'])) {
        $where[] = 'lr.member_id = :me';
        $args['me'] = (int) $filters['mine'];
    }
    if (!empty($filters['person'])) {
        $where[] = 'lr.member_id = :person';
        $args['person'] = (int) $filters['person'];
    }
    if (!empty($filters['department'])) {
        $where[] = 'EXISTS (SELECT 1 FROM mcp_department_members dm WHERE dm.member_id = lr.member_id AND dm.department_id = :dept AND dm.left_at IS NULL)';
        $args['dept'] = (int) $filters['department'];
    }
    if (in_array($filters['status'] ?? '', LEAVE_STATUSES, true)) {
        $where[] = 'lr.status = :status';
        $args['status'] = $filters['status'];
    }
    if (!empty($filters['from'])) {
        $where[] = 'lr.end_date >= :from';
        $args['from'] = $filters['from'];
    }
    if (!empty($filters['to'])) {
        $where[] = 'lr.start_date <= :to';
        $args['to'] = $filters['to'];
    }
    if (!empty($filters['flagged'])) {
        $where[] = '(lr.over_balance OR lr.has_overlap)';
    }
    $from = 'FROM mcp_leave_requests lr WHERE ' . implode(' AND ', $where);
    $count = $pdo->prepare('SELECT count(*) ' . $from);
    $count->execute($args);
    $st = $pdo->prepare("SELECT lr.* {$from} ORDER BY (lr.status = 'requested') DESC, lr.start_date DESC LIMIT :lim OFFSET :off");
    foreach ($args as $k => $v) {
        $st->bindValue($k, $v);
    }
    $st->bindValue('lim', $per, PDO::PARAM_INT);
    $st->bindValue('off', max(0, $page - 1) * $per, PDO::PARAM_INT);
    $st->execute();
    return ['rows' => $st->fetchAll(), 'total' => (int) $count->fetchColumn()];
}

function find_leave_request(PDO $pdo, int $id): ?array
{
    $st = $pdo->prepare('SELECT * FROM mcp_leave_requests WHERE leave_request_id = :id');
    $st->execute(['id' => $id]);
    $r = $st->fetch();
    if ($r === false) {
        return null;
    }
    $st = $pdo->prepare('SELECT * FROM mcp_leave_balances WHERE member_id = :m AND leave_type_id = :t AND year = :y');
    $st->execute(['m' => $r['member_id'], 't' => $r['leave_type_id'], 'y' => (int) substr((string) $r['start_date'], 0, 4)]);
    $r['balance'] = $st->fetch() ?: null;
    return $r;
}

function leave_types_active(PDO $pdo): array
{
    return $pdo->query('SELECT * FROM mcp_leave_types WHERE archived_at IS NULL ORDER BY name')->fetchAll();
}

/** People the caller may request leave for: those whose employment they can see and who are employed now. */
function leave_people_options(PDO $pdo): array
{
    return $pdo->query("SELECT e.member_id, e.display_name FROM mcp_employment_profiles e JOIN mcp_members m ON m.member_id = e.member_id
                         WHERE e.status IN ('active', 'on_leave') AND m.member_kind = 'human' AND m.status = 'active' ORDER BY e.display_name")->fetchAll();
}

/** The balance row for a person, type and year — created from the allowance when missing (accruing types only). */
function ensure_leave_balance(PDO $pdo, int $memberId, int $typeId, int $year): ?array
{
    $st = $pdo->prepare('SELECT * FROM leave_balances WHERE member_id = :m AND leave_type_id = :t AND year = :y');
    $st->execute(['m' => $memberId, 't' => $typeId, 'y' => $year]);
    $row = $st->fetch();
    if ($row !== false) {
        return $row;
    }
    $accrues = $pdo->prepare('SELECT accrual_days_per_year IS NOT NULL FROM leave_types WHERE id = :t');
    $accrues->execute(['t' => $typeId]);
    if (!(bool) $accrues->fetchColumn()) {
        return null;                                                // an unlimited type has no balance
    }
    $pdo->prepare('SELECT hr_grant_annual_leave(:m, :y)')->execute(['m' => $memberId, 'y' => $year]);
    $st->execute(['m' => $memberId, 't' => $typeId, 'y' => $year]);
    return $st->fetch() ?: null;
}

/**
 * Record a request: the days from the business's calendar, the flags, and — for a type that needs
 * no approval — the approval itself. Returns the row.
 */
function insert_leave_request(PDO $pdo, int $memberId, int $typeId, string $start, string $end, bool $half, ?string $reason, int $actorId): array
{
    $type = $pdo->prepare('SELECT * FROM leave_types WHERE id = :t AND archived_at IS NULL');
    $type->execute(['t' => $typeId]);
    $type = $type->fetch();
    if ($type === false) {
        throw new RuntimeException('That leave type is not offered.');
    }
    $days = (float) $pdo->query('SELECT hr_leave_days(' . $pdo->quote($start) . ', ' . $pdo->quote($end) . ', ' . ($half ? 'true' : 'false') . ')')->fetchColumn();
    if ($days <= 0) {
        throw new RuntimeException('Those dates hold no working days.');
    }
    $year = (int) substr($start, 0, 4);
    $balance = ensure_leave_balance($pdo, $memberId, $typeId, $year);
    $over = false;
    if ($balance !== null) {
        $committed = (float) $pdo->query('SELECT hr_leave_committed(' . $memberId . ', ' . $typeId . ', ' . $year . ')')->fetchColumn();
        $remaining = (float) $balance['accrued_days'] + (float) $balance['carried_days'] - (float) $balance['used_days'];
        $over = $days > $remaining - $committed;
    }
    $ov = $pdo->prepare("SELECT 1 FROM leave_requests WHERE member_id = :m AND status IN ('requested', 'approved') AND start_date <= :e AND end_date >= :s LIMIT 1");
    $ov->execute(['m' => $memberId, 's' => $start, 'e' => $end]);
    $overlap = $ov->fetchColumn() !== false;
    $auto = !$type['requires_approval'];
    $st = $pdo->prepare(<<<'SQL'
        INSERT INTO leave_requests (member_id, leave_type_id, start_date, end_date, days, half_day, reason, status, over_balance, has_overlap,
                                    decided_by, decided_at, decision_note, created_by)
        VALUES (:m, :t, :s, :e, :d, :h, :r, :status, :over, :overlap, :db, :dat, :note, :actor)
        RETURNING *
    SQL);
    $st->execute(['m' => $memberId, 't' => $typeId, 's' => $start, 'e' => $end, 'd' => $days, 'h' => $half ? 't' : 'f', 'r' => $reason,
        'status' => $auto ? 'approved' : 'requested', 'over' => $over ? 't' : 'f', 'overlap' => $overlap ? 't' : 'f',
        'db' => $auto ? $actorId : null, 'dat' => $auto ? date('c') : null, 'note' => $auto ? 'approved automatically: this type needs no approval' : null, 'actor' => $actorId]);
    $row = $st->fetch();
    if ($auto && $balance !== null) {
        $pdo->prepare('UPDATE leave_balances SET used_days = used_days + :d WHERE id = :id')->execute(['d' => $days, 'id' => $balance['id']]);
    }
    return $row;
}

/** Approve or reject. Approving beyond the balance needs $override. Returns [row, message|null]. */
function decide_leave_request(PDO $pdo, int $id, string $decision, ?string $note, bool $override, int $actorId): array
{
    $st = $pdo->prepare('SELECT * FROM leave_requests WHERE id = :id FOR UPDATE');
    $st->execute(['id' => $id]);
    $r = $st->fetch();
    if ($r === false) {
        return [null, 'No such request.'];
    }
    if ($r['status'] !== 'requested') {
        return [null, 'This request was already ' . $r['status'] . '.'];
    }
    if ($decision === 'approve') {
        $year = (int) substr((string) $r['start_date'], 0, 4);
        $balance = ensure_leave_balance($pdo, (int) $r['member_id'], (int) $r['leave_type_id'], $year);
        if ($balance !== null) {
            $remaining = (float) $balance['accrued_days'] + (float) $balance['carried_days'] - (float) $balance['used_days'];
            if ((float) $r['days'] > $remaining && !$override) {
                $short = (float) $r['days'] - $remaining;
                return [null, sprintf('Beyond the balance by %s day%s; HR staff can override.', rtrim(rtrim(number_format($short, 1), '0'), '.'), $short == 1 ? '' : 's')];
            }
            $pdo->prepare('UPDATE leave_balances SET used_days = used_days + :d WHERE id = :id')->execute(['d' => $r['days'], 'id' => $balance['id']]);
        }
    }
    $upd = $pdo->prepare("UPDATE leave_requests SET status = :s, decided_by = :a, decided_at = now(), decision_note = :n WHERE id = :id RETURNING *");
    $upd->execute(['s' => $decision === 'approve' ? 'approved' : 'rejected', 'a' => $actorId, 'n' => $note, 'id' => $id]);
    return [$upd->fetch(), null];
}

/** Cancel: while requested, or approved and not yet started (the days go back). Returns [row, message|null]. */
function cancel_leave_request(PDO $pdo, int $id, int $actorId, string $note = 'cancelled'): array
{
    $st = $pdo->prepare('SELECT * FROM leave_requests WHERE id = :id FOR UPDATE');
    $st->execute(['id' => $id]);
    $r = $st->fetch();
    if ($r === false) {
        return [null, 'No such request.'];
    }
    if ($r['status'] === 'requested') {
        // nothing drawn yet
    } elseif ($r['status'] === 'approved' && $r['start_date'] >= date('Y-m-d')) {
        $pdo->prepare('UPDATE leave_balances SET used_days = GREATEST(0, used_days - :d) WHERE member_id = :m AND leave_type_id = :t AND year = :y')
            ->execute(['d' => $r['days'], 'm' => $r['member_id'], 't' => $r['leave_type_id'], 'y' => (int) substr((string) $r['start_date'], 0, 4)]);
    } else {
        return [null, $r['status'] === 'approved' ? 'Leave that has started is not cancelled here; HR adjusts the balance.' : 'This request is ' . $r['status'] . '.'];
    }
    $upd = $pdo->prepare("UPDATE leave_requests SET status = 'cancelled', decided_by = :a, decided_at = now(), decision_note = :n WHERE id = :id RETURNING *");
    $upd->execute(['a' => $actorId, 'n' => $note, 'id' => $id]);
    return [$upd->fetch(), null];
}

function leave_balances(PDO $pdo, array $filters): array
{
    $where = ['b.year = :y'];
    $args = ['y' => (int) ($filters['year'] ?? date('Y'))];
    if (!empty($filters['person'])) {
        $where[] = 'b.member_id = :p';
        $args['p'] = (int) $filters['person'];
    }
    if (!empty($filters['department'])) {
        $where[] = 'EXISTS (SELECT 1 FROM mcp_department_members dm WHERE dm.member_id = b.member_id AND dm.department_id = :d AND dm.left_at IS NULL)';
        $args['d'] = (int) $filters['department'];
    }
    $st = $pdo->prepare('SELECT b.* FROM mcp_leave_balances b WHERE ' . implode(' AND ', $where) . ' ORDER BY b.display_name, b.leave_type LIMIT 500');
    $st->execute($args);
    return $st->fetchAll();
}

function person_leave_summary(PDO $pdo, int $memberId, int $year): array
{
    $st = $pdo->prepare('SELECT * FROM mcp_leave_balances WHERE member_id = :m AND year = :y ORDER BY leave_type');
    $st->execute(['m' => $memberId, 'y' => $year]);
    $balances = $st->fetchAll();
    $st = $pdo->prepare('SELECT * FROM mcp_leave_requests WHERE member_id = :m ORDER BY start_date DESC LIMIT 30');
    $st->execute(['m' => $memberId]);
    return ['balances' => $balances, 'requests' => $st->fetchAll()];
}

/** HR adjusts a balance: accrued and carried days set outright. Returns [before, after]. */
function adjust_leave_balance(PDO $pdo, int $memberId, int $typeId, int $year, string $accrued, string $carried): array
{
    $before = ensure_leave_balance($pdo, $memberId, $typeId, $year);
    if ($before === null) {
        $pdo->prepare('INSERT INTO leave_balances (member_id, leave_type_id, year) VALUES (:m, :t, :y) ON CONFLICT DO NOTHING')->execute(['m' => $memberId, 't' => $typeId, 'y' => $year]);
        $st = $pdo->prepare('SELECT * FROM leave_balances WHERE member_id = :m AND leave_type_id = :t AND year = :y');
        $st->execute(['m' => $memberId, 't' => $typeId, 'y' => $year]);
        $before = $st->fetch();
    }
    $st = $pdo->prepare('UPDATE leave_balances SET accrued_days = :a, carried_days = :c WHERE id = :id RETURNING *');
    $st->execute(['a' => $accrued, 'c' => $carried, 'id' => $before['id']]);
    return [$before, $st->fetch()];
}

/** The year's allowance for everyone employed now who has none yet. Returns [people, balances]. */
function grant_leave_year(PDO $pdo, int $year): array
{
    $people = $pdo->query("SELECT e.member_id FROM employment_profiles e JOIN members m ON m.id = e.member_id
                            WHERE e.status IN ('active', 'on_leave') AND m.member_kind = 'human' AND m.status = 'active'")->fetchAll(PDO::FETCH_COLUMN);
    $granted = 0;
    $st = $pdo->prepare('SELECT hr_grant_annual_leave(:m, :y)');
    foreach ($people as $m) {
        $st->execute(['m' => (int) $m, 'y' => $year]);
        $granted += (int) $st->fetchColumn();
    }
    return [count($people), $granted];
}

/** Who is off between two dates (approved; requested when asked), one row per request, plus the holidays. */
function whos_off(PDO $pdo, string $from, string $to, ?int $departmentId, bool $includeRequested = false): array
{
    $where = ['lr.end_date >= :from', 'lr.start_date <= :to', $includeRequested ? "lr.status IN ('approved', 'requested')" : "lr.status = 'approved'"];
    $args = ['from' => $from, 'to' => $to];
    if ($departmentId !== null) {
        $where[] = 'EXISTS (SELECT 1 FROM mcp_department_members dm WHERE dm.member_id = lr.member_id AND dm.department_id = :d AND dm.left_at IS NULL)';
        $args['d'] = $departmentId;
    }
    $st = $pdo->prepare('SELECT lr.* FROM mcp_leave_requests lr WHERE ' . implode(' AND ', $where) . ' ORDER BY lr.display_name, lr.start_date');
    $st->execute($args);
    $hol = $pdo->prepare('SELECT holiday_date, name FROM mcp_holidays WHERE holiday_date BETWEEN :from AND :to');
    $hol->execute(['from' => $from, 'to' => $to]);
    return ['requests' => $st->fetchAll(), 'holidays' => $hol->fetchAll()];
}

/** The people a calendar shows: employed now and visible, in a department when asked. */
function calendar_people(PDO $pdo, ?int $departmentId): array
{
    $sql = "SELECT e.member_id, e.display_name, e.primary_department FROM mcp_employment_profiles e JOIN mcp_members m ON m.member_id = e.member_id
             WHERE e.status IN ('active', 'on_leave') AND m.member_kind = 'human'";
    $args = [];
    if ($departmentId !== null) {
        $sql .= ' AND EXISTS (SELECT 1 FROM mcp_department_members dm WHERE dm.member_id = e.member_id AND dm.department_id = :d AND dm.left_at IS NULL)';
        $args['d'] = $departmentId;
    }
    $st = $pdo->prepare($sql . ' ORDER BY e.display_name LIMIT 200');
    $st->execute($args);
    return $st->fetchAll();
}

function leave_status_color(string $status): string
{
    return match ($status) { 'requested' => 'warning', 'approved' => 'success', 'rejected' => 'danger', default => 'secondary' };
}

function present_leave_request(array $r): array
{
    return ['leave_request_id' => (int) $r['leave_request_id'], 'member_id' => (int) $r['member_id'], 'display_name' => $r['display_name'],
        'leave_type' => $r['leave_type'], 'start_date' => $r['start_date'], 'end_date' => $r['end_date'], 'days' => (float) $r['days'],
        'half_day' => !empty($r['half_day']), 'status' => $r['status'], 'over_balance' => !empty($r['over_balance']), 'has_overlap' => !empty($r['has_overlap']),
        'decided_by_name' => $r['decided_by_name'], 'decision_note' => $r['decision_note'], 'awaiting_me' => !empty($r['awaiting_me'])];
}
