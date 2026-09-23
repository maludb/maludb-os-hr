<?php /** Data: resultsHtml, filters, canSchedule */ ?>
<div class="page-header" id="reviews-list-header">
    <div class="page-header-left d-flex align-items-center">
        <div class="page-header-title"><h5 class="m-b-10">Reviews</h5></div>
        <ul class="breadcrumb"><li class="breadcrumb-item"><a href="/" hx-get="/" hx-target="#page-content" hx-push-url="/">Home</a></li><li class="breadcrumb-item">Reviews</li></ul>
    </div>
    <div class="page-header-right ms-auto">
        <div class="page-header-right-items">
            <div class="d-flex d-md-none"><a href="javascript:void(0)" class="page-header-right-close-toggle"><i class="feather-arrow-left me-2"></i><span>Back</span></a></div>
            <div class="d-flex align-items-center gap-2 page-header-right-items-wrapper">
                <form id="reviews-list-filters" class="d-flex align-items-center gap-2" method="get" action="/reviews/" hx-get="/reviews/" hx-target="#reviews-list-results" hx-swap="outerHTML" hx-trigger="change" hx-push-url="false">
                    <select name="status" id="reviews-list-filter-status" class="form-select">
                        <?php foreach (['' => 'Any status', 'scheduled' => 'Scheduled', 'in_progress' => 'In progress', 'completed' => 'Completed', 'cancelled' => 'Cancelled'] as $v => $l): ?><option value="<?= e($v) ?>" <?= $filters['status'] === $v ? 'selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?>
                    </select>
                    <div class="form-check mb-0 text-nowrap"><input type="checkbox" name="overdue" value="1" id="reviews-list-filter-overdue" class="form-check-input" <?= $filters['overdue'] ? 'checked' : '' ?>><label class="form-check-label fs-12" for="reviews-list-filter-overdue">Overdue</label></div>
                </form>
                <?php if ($canSchedule): ?><a href="/reviews/new" id="reviews-list-add-btn" class="btn btn-primary" hx-get="/reviews/new" hx-target="#page-content" hx-push-url="/reviews/new"><i class="feather-plus me-2"></i><span>Schedule a review</span></a><?php endif; ?>
            </div>
        </div>
        <div class="d-md-none d-flex align-items-center"><a href="javascript:void(0)" class="page-header-right-open-toggle"><i class="feather-align-right fs-20"></i></a></div>
    </div>
</div>
<div class="main-content" id="reviews-list-content"><?= $resultsHtml ?></div>
