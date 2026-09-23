<?php
declare(strict_types=1);

/** The dashboard's numbers — every one from an mcp_* view, so a person sees what they may see. */
function home_summary(PDO $pdo, int $memberId, bool $staff): array
{
    $year = (int) date('Y');
    $out = ['balances' => [], 'awaiting_me' => 0, 'my_requests' => 0, 'next_review' => null, 'hr' => []];

    $st = $pdo->prepare('SELECT leave_type, remaining_days, committed_days FROM mcp_leave_balances WHERE member_id = :m AND year = :y ORDER BY leave_type');
    $st->execute(['m' => $memberId, 'y' => $year]);
    $out['balances'] = $st->fetchAll();

    $out['awaiting_me'] = (int) $pdo->query('SELECT count(*) FROM mcp_leave_requests WHERE awaiting_me')->fetchColumn();
    $st = $pdo->prepare("SELECT count(*) FROM mcp_leave_requests WHERE member_id = :m AND status = 'requested'");
    $st->execute(['m' => $memberId]);
    $out['my_requests'] = (int) $st->fetchColumn();

    $st = $pdo->prepare("SELECT review_id, kind, due_date, reviewer_name, status FROM mcp_reviews
                          WHERE (member_id = :m OR reviewer_member_id = :m) AND status IN ('scheduled', 'in_progress')
                          ORDER BY due_date LIMIT 1");
    $st->execute(['m' => $memberId]);
    $out['next_review'] = $st->fetch() ?: null;

    if ($staff) {
        $out['hr'] = [
            'starting' => (int) $pdo->query("SELECT count(*) FROM mcp_employment_profiles WHERE start_date >= date_trunc('month', current_date) AND start_date < date_trunc('month', current_date) + interval '1 month'")->fetchColumn(),
            'leaving' => (int) $pdo->query("SELECT count(*) FROM mcp_employment_profiles WHERE end_date >= date_trunc('month', current_date) AND end_date < date_trunc('month', current_date) + interval '1 month'")->fetchColumn(),
            'unapproved_runs' => (int) $pdo->query("SELECT count(*) FROM mcp_pay_runs WHERE status = 'draft'")->fetchColumn(),
            'unpaid_runs' => (int) $pdo->query("SELECT count(*) FROM mcp_pay_runs WHERE status = 'approved'")->fetchColumn(),
            'overdue_reviews' => (int) $pdo->query("SELECT count(*) FROM mcp_reviews WHERE status IN ('scheduled', 'in_progress') AND due_date < current_date")->fetchColumn(),
            'people' => (int) $pdo->query("SELECT count(*) FROM mcp_employment_profiles WHERE status IN ('active', 'on_leave')")->fetchColumn(),
            'directory' => (int) $pdo->query("SELECT count(*) FROM mcp_members WHERE member_kind = 'human' AND status = 'active'")->fetchColumn(),
        ];
    }
    return $out;
}
