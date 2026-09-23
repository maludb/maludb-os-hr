<?php
declare(strict_types=1);

/** Reviews — scheduled by HR or the manager, written by the reviewer, completed and shared, commented once by the person. */

const REVIEW_KINDS = ['annual', 'probation', 'quarterly', 'other'];
const REVIEWS_PER_PAGE = 24;

/** @return array{rows: array, total: int} */
function find_reviews(PDO $pdo, array $filters, int $page = 1, int $per = REVIEWS_PER_PAGE): array
{
    $where = ['1 = 1'];
    $args = [];
    if (in_array($filters['status'] ?? '', ['scheduled', 'in_progress', 'completed', 'cancelled'], true)) {
        $where[] = 'r.status = :status';
        $args['status'] = $filters['status'];
    }
    if (!empty($filters['person'])) {
        $where[] = 'r.member_id = :p';
        $args['p'] = (int) $filters['person'];
    }
    if (!empty($filters['reviewer'])) {
        $where[] = 'r.reviewer_member_id = :rv';
        $args['rv'] = (int) $filters['reviewer'];
    }
    if (!empty($filters['overdue'])) {
        $where[] = "r.status IN ('scheduled', 'in_progress') AND r.due_date < current_date";
    }
    $from = 'FROM mcp_reviews r WHERE ' . implode(' AND ', $where);
    $count = $pdo->prepare('SELECT count(*) ' . $from);
    $count->execute($args);
    $st = $pdo->prepare("SELECT r.* {$from} ORDER BY (r.status IN ('scheduled', 'in_progress')) DESC, r.due_date LIMIT :lim OFFSET :off");
    foreach ($args as $k => $v) {
        $st->bindValue($k, $v);
    }
    $st->bindValue('lim', $per, PDO::PARAM_INT);
    $st->bindValue('off', max(0, $page - 1) * $per, PDO::PARAM_INT);
    $st->execute();
    return ['rows' => $st->fetchAll(), 'total' => (int) $count->fetchColumn()];
}

function find_review(PDO $pdo, int $id): ?array
{
    $st = $pdo->prepare('SELECT * FROM mcp_reviews WHERE review_id = :id');
    $st->execute(['id' => $id]);
    return $st->fetch() ?: null;
}

/** People a review can be scheduled for: employed humans the caller may see. */
function review_people_options(PDO $pdo): array
{
    return $pdo->query("SELECT e.member_id, e.display_name, e.manager_member_id FROM mcp_employment_profiles e JOIN mcp_members m ON m.member_id = e.member_id
                         WHERE e.status IN ('active', 'on_leave') AND m.member_kind = 'human' AND m.status = 'active' ORDER BY e.display_name")->fetchAll();
}

function schedule_review(PDO $pdo, int $memberId, int $reviewerId, string $kind, string $dueDate, ?string $periodStart, ?string $periodEnd, int $actorId): array
{
    $st = $pdo->prepare('INSERT INTO reviews (member_id, reviewer_member_id, kind, due_date, period_start, period_end, created_by)
                         VALUES (:m, :r, :k, :d, :ps, :pe, :a) RETURNING *');
    $st->execute(['m' => $memberId, 'r' => $reviewerId, 'k' => $kind, 'd' => $dueDate, 'ps' => $periodStart, 'pe' => $periodEnd, 'a' => $actorId]);
    return $st->fetch();
}

/** The reviewer writes: the five fields; a scheduled review becomes in_progress. */
function save_review(PDO $pdo, int $id, array $f): array
{
    $st = $pdo->prepare("UPDATE reviews SET rating = :rating, summary = :summary, strengths = :strengths, improvements = :improvements, goals = :goals,
                         status = CASE WHEN status = 'scheduled' THEN 'in_progress' ELSE status END WHERE id = :id RETURNING *");
    $st->execute(['rating' => $f['rating'], 'summary' => $f['summary'], 'strengths' => $f['strengths'], 'improvements' => $f['improvements'], 'goals' => $f['goals'], 'id' => $id]);
    return $st->fetch();
}

function complete_review(PDO $pdo, int $id, bool $share): array
{
    $st = $pdo->prepare("UPDATE reviews SET status = 'completed', completed_at = now(), shared_at = CASE WHEN :share THEN COALESCE(shared_at, now()) ELSE shared_at END WHERE id = :id RETURNING *");
    $st->execute(['share' => $share ? 't' : 'f', 'id' => $id]);
    return $st->fetch();
}

function comment_review(PDO $pdo, int $id, string $comment): array
{
    $st = $pdo->prepare('UPDATE reviews SET employee_comment = :c, employee_commented_at = now() WHERE id = :id RETURNING *');
    $st->execute(['c' => $comment, 'id' => $id]);
    return $st->fetch();
}

function cancel_review(PDO $pdo, int $id, ?string $reason): array
{
    $st = $pdo->prepare("UPDATE reviews SET status = 'cancelled', cancelled_at = now(), cancel_reason = :r WHERE id = :id RETURNING *");
    $st->execute(['r' => $reason, 'id' => $id]);
    return $st->fetch();
}

/** Base row, for the gates (who is the reviewer, is it shared) — after the view said the caller may see it. */
function review_base(PDO $pdo, int $id): ?array
{
    $st = $pdo->prepare('SELECT * FROM reviews WHERE id = :id');
    $st->execute(['id' => $id]);
    return $st->fetch() ?: null;
}

function person_reviews(PDO $pdo, int $memberId): array
{
    $st = $pdo->prepare('SELECT * FROM mcp_reviews WHERE member_id = :m ORDER BY due_date DESC LIMIT 20');
    $st->execute(['m' => $memberId]);
    return $st->fetchAll();
}

function review_status_color(string $status, ?string $due = null): string
{
    if (in_array($status, ['scheduled', 'in_progress'], true) && $due !== null && $due < date('Y-m-d')) {
        return 'danger';
    }
    return match ($status) { 'scheduled' => 'info', 'in_progress' => 'warning', 'completed' => 'success', default => 'secondary' };
}

function review_status_label(string $status, ?string $due = null): string
{
    if (in_array($status, ['scheduled', 'in_progress'], true) && $due !== null && $due < date('Y-m-d')) {
        return 'Overdue';
    }
    return match ($status) { 'scheduled' => 'Scheduled', 'in_progress' => 'In progress', 'completed' => 'Completed', default => 'Cancelled' };
}

function present_review(array $r): array
{
    return ['review_id' => (int) $r['review_id'], 'member_id' => (int) $r['member_id'], 'display_name' => $r['display_name'], 'reviewer_member_id' => (int) $r['reviewer_member_id'],
        'reviewer_name' => $r['reviewer_name'], 'kind' => $r['kind'], 'due_date' => $r['due_date'], 'status' => $r['status'], 'rating' => $r['rating'] !== null ? (int) $r['rating'] : null,
        'shared' => $r['shared_at'] !== null, 'completed_at' => json_ts($r['completed_at'])];
}
