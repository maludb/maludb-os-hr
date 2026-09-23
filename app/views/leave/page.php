<?php /** Data: resultsHtml, filters, departments, awaiting */ ?>
<div class="page-header" id="leave-requests-header">
    <div class="page-header-left d-flex align-items-center">
        <div class="page-header-title"><h5 class="m-b-10">Leave</h5></div>
        <ul class="breadcrumb"><li class="breadcrumb-item"><a href="/" hx-get="/" hx-target="#page-content" hx-push-url="/">Home</a></li><li class="breadcrumb-item">Requests</li></ul>
    </div>
    <div class="page-header-right ms-auto">
        <div class="page-header-right-items">
            <div class="d-flex d-md-none"><a href="javascript:void(0)" class="page-header-right-close-toggle"><i class="feather-arrow-left me-2"></i><span>Back</span></a></div>
            <div class="d-flex align-items-center gap-2 page-header-right-items-wrapper">
                <form id="leave-requests-filters" class="d-flex align-items-center gap-2 flex-wrap" method="get" action="/leave/" hx-get="/leave/" hx-target="#leave-requests-results" hx-swap="outerHTML" hx-trigger="change" hx-push-url="false">
                    <select name="status" id="leave-requests-filter-status" class="form-select">
                        <?php foreach (['' => 'Any status', 'requested' => 'Requested', 'approved' => 'Approved', 'rejected' => 'Rejected', 'cancelled' => 'Cancelled'] as $v => $l): ?><option value="<?= e($v) ?>" <?= $filters['status'] === $v ? 'selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?>
                    </select>
                    <select name="department" id="leave-requests-filter-department" class="form-select">
                        <option value="">Any department</option>
                        <?php foreach ($departments as $d): ?><option value="<?= (int) $d['department_id'] ?>" <?= (int) ($filters['department'] ?? 0) === (int) $d['department_id'] ? 'selected' : '' ?>><?= e($d['name']) ?></option><?php endforeach; ?>
                    </select>
                    <div class="form-check mb-0 text-nowrap"><input type="checkbox" name="awaiting_me" value="1" id="leave-requests-filter-awaiting" class="form-check-input" <?= $filters['awaiting_me'] ? 'checked' : '' ?>><label class="form-check-label fs-12" for="leave-requests-filter-awaiting">Waiting for me<?= $awaiting > 0 ? ' (' . $awaiting . ')' : '' ?></label></div>
                    <div class="form-check mb-0 text-nowrap"><input type="checkbox" name="mine" value="1" id="leave-requests-filter-mine" class="form-check-input" <?= $filters['mine'] ? 'checked' : '' ?>><label class="form-check-label fs-12" for="leave-requests-filter-mine">Mine</label></div>
                    <div class="form-check mb-0 text-nowrap"><input type="checkbox" name="flagged" value="1" id="leave-requests-filter-flagged" class="form-check-input" <?= $filters['flagged'] ? 'checked' : '' ?>><label class="form-check-label fs-12" for="leave-requests-filter-flagged">Flagged</label></div>
                </form>
                <a href="/leave/new" id="leave-requests-add-btn" class="btn btn-primary" hx-get="/leave/new" hx-target="#page-content" hx-push-url="/leave/new"><i class="feather-plus me-2"></i><span>Request leave</span></a>
            </div>
        </div>
        <div class="d-md-none d-flex align-items-center"><a href="javascript:void(0)" class="page-header-right-open-toggle"><i class="feather-align-right fs-20"></i></a></div>
    </div>
</div>
<div class="main-content" id="leave-requests-content"><?= $resultsHtml ?></div>
