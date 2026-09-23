<?php /** Data: me, staff (bool), balances, awaiting_me, my_requests, next_review, hr[] (HR staff's counts) */ ?>
<div class="page-header" id="dashboard-header">
    <div class="page-header-left d-flex align-items-center">
        <div class="page-header-title"><h5 class="m-b-10">Dashboard</h5></div>
        <ul class="breadcrumb">
            <li class="breadcrumb-item">Home</li>
            <li class="breadcrumb-item"><?= e($me['display_name'] ?? '') ?></li>
        </ul>
    </div>
    <div class="page-header-right ms-auto">
        <div class="page-header-right-items">
            <div class="d-flex d-md-none"><a href="javascript:void(0)" class="page-header-right-close-toggle"><i class="feather-arrow-left me-2"></i><span>Back</span></a></div>
            <div class="d-flex align-items-center gap-2 page-header-right-items-wrapper">
                <a href="/leave/new" class="btn btn-primary" id="dashboard-request-leave-btn" hx-get="/leave/new" hx-target="#page-content" hx-push-url="/leave/new"><i class="feather-sun me-2"></i><span>Request leave</span></a>
            </div>
        </div>
        <div class="d-md-none d-flex align-items-center"><a href="javascript:void(0)" class="page-header-right-open-toggle"><i class="feather-align-right fs-20"></i></a></div>
    </div>
</div>
<div class="main-content" id="dashboard-content">
    <div class="row">
        <?php
        $stat = static function (string $id, string $icon, string $label, string $value, string $url): string {
            return '<div class="col-xxl-3 col-md-6"><div class="card stretch stretch-full" id="dashboard-stat-' . e($id) . '"><div class="card-body">'
                . '<div class="d-flex align-items-center gap-3"><div class="avatar-text avatar-xl rounded"><i class="' . e($icon) . '"></i></div>'
                . '<a href="' . e($url) . '" class="fw-bold d-block" hx-get="' . e($url) . '" hx-target="#page-content" hx-push-url="' . e($url) . '">'
                . '<span class="text-truncate-1-line">' . e($label) . '</span><span class="fs-24 fw-bolder d-block">' . e($value) . '</span></a></div></div></div></div>';
        };
        $left = $balances === [] ? '—' : implode(' · ', array_map(static fn (array $b): string => rtrim(rtrim(number_format((float) $b['remaining_days'], 1), '0'), '.') . ' ' . $b['leave_type'], $balances));
        echo $stat('leave-left', 'feather-sun', 'Leave left this year', $left, '/leave/balances/');
        echo $stat('awaiting-me', 'feather-inbox', 'Waiting for my decision', (string) $awaiting_me, '/leave/?awaiting_me=1');
        echo $stat('my-requests', 'feather-clock', 'My open requests', (string) $my_requests, '/leave/');
        echo $stat('next-review', 'feather-star', 'Next review', $next_review ? ucfirst((string) $next_review['kind']) . ' · ' . format_date($next_review['due_date']) : 'None scheduled', $next_review ? '/reviews/' . (int) $next_review['review_id'] : '/reviews/');
        ?>
    </div>
    <?php if ($staff): ?>
    <div class="row" id="dashboard-staff">
        <div class="col-lg-12"><h6 class="fw-bold text-muted text-uppercase fs-12 mt-2 mb-3">HR this month</h6></div>
        <?php
        echo $stat('people', 'feather-users', 'People employed', (string) $hr['people'] . ' of ' . $hr['directory'], '/people/');
        echo $stat('starting', 'feather-user-plus', 'Starting this month', (string) $hr['starting'], '/people/?status=candidate');
        echo $stat('leaving', 'feather-user-minus', 'Leaving this month', (string) $hr['leaving'], '/people/?status=ended');
        echo $stat('runs', 'feather-dollar-sign', 'Pay runs to approve / pay', $hr['unapproved_runs'] . ' / ' . $hr['unpaid_runs'], '/payruns/');
        echo $stat('overdue-reviews', 'feather-alert-triangle', 'Overdue reviews', (string) $hr['overdue_reviews'], '/reviews/?overdue=1');
        ?>
    </div>
    <?php endif; ?>
</div>
