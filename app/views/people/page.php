<?php /** Data: resultsHtml, filters, sort, departments, canInvite, staff */ ?>
<div class="page-header" id="people-list-header">
    <div class="page-header-left d-flex align-items-center">
        <div class="page-header-title"><h5 class="m-b-10">People</h5></div>
        <ul class="breadcrumb">
            <li class="breadcrumb-item"><a href="/" hx-get="/" hx-target="#page-content" hx-push-url="/">Home</a></li>
            <li class="breadcrumb-item">People</li>
        </ul>
    </div>
    <div class="page-header-right ms-auto">
        <div class="page-header-right-items">
            <div class="d-flex d-md-none"><a href="javascript:void(0)" class="page-header-right-close-toggle"><i class="feather-arrow-left me-2"></i><span>Back</span></a></div>
            <div class="d-flex align-items-center gap-2 page-header-right-items-wrapper">
                <form id="people-list-filters" class="d-flex align-items-center gap-2 flex-wrap" method="get" action="/people/"
                      hx-get="/people/" hx-target="#people-list-results" hx-swap="outerHTML" hx-trigger="change, keyup changed delay:400ms from:#people-list-search, search from:#people-list-search"
                      hx-push-url="false">
                    <div class="input-group">
                        <span class="input-group-text"><i class="feather-search"></i></span>
                        <input type="search" name="q" id="people-list-search" class="form-control" placeholder="Name, email, number…" value="<?= e($filters['q']) ?>" aria-label="Search people" />
                    </div>
                    <select name="department" id="people-list-filter-department" class="form-select" aria-label="Department">
                        <option value="">Any department</option>
                        <?php foreach ($departments as $d): ?>
                            <option value="<?= (int) $d['department_id'] ?>" <?= (int) ($filters['department'] ?? 0) === (int) $d['department_id'] ? 'selected' : '' ?>><?= e($d['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <select name="status" id="people-list-filter-status" class="form-select" aria-label="Status">
                        <?php foreach (['' => 'Any status', 'active' => 'Active', 'candidate' => 'Candidate', 'on_leave' => 'On leave', 'ended' => 'Ended', 'unemployed' => 'Not employed'] as $v => $l): ?>
                            <option value="<?= e($v) ?>" <?= $filters['status'] === $v ? 'selected' : '' ?>><?= e($l) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <select name="type" id="people-list-filter-type" class="form-select" aria-label="Type">
                        <?php foreach (['' => 'Any type', 'employee' => 'Employee', 'contractor' => 'Contractor', 'intern' => 'Intern'] as $v => $l): ?>
                            <option value="<?= e($v) ?>" <?= $filters['type'] === $v ? 'selected' : '' ?>><?= e($l) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <select name="sort" id="people-list-sort" class="form-select" aria-label="Sort">
                        <?php foreach (['name' => 'By name', 'department' => 'By department', 'start_date' => 'By start date'] as $v => $l): ?>
                            <option value="<?= e($v) ?>" <?= $sort === $v ? 'selected' : '' ?>><?= e($l) ?></option>
                        <?php endforeach; ?>
                    </select>
                </form>
                <?php if ($canInvite): ?>
                <a href="/people/new" id="people-list-invite-btn" class="btn btn-primary" hx-get="/people/new" hx-target="#page-content" hx-swap="innerHTML" hx-push-url="/people/new">
                    <i class="feather-user-plus me-2"></i><span>Invite</span>
                </a>
                <?php endif; ?>
            </div>
        </div>
        <div class="d-md-none d-flex align-items-center"><a href="javascript:void(0)" class="page-header-right-open-toggle"><i class="feather-align-right fs-20"></i></a></div>
    </div>
</div>
<div class="main-content" id="people-list-content">
    <?= $resultsHtml ?>
</div>
