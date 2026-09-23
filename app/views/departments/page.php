<?php /** Data: resultsHtml, filters, canCreate */ ?>
<div class="page-header" id="departments-list-header">
    <div class="page-header-left d-flex align-items-center">
        <div class="page-header-title"><h5 class="m-b-10">Departments</h5></div>
        <ul class="breadcrumb"><li class="breadcrumb-item"><a href="/" hx-get="/" hx-target="#page-content" hx-push-url="/">Home</a></li><li class="breadcrumb-item">Departments</li></ul>
    </div>
    <div class="page-header-right ms-auto">
        <div class="page-header-right-items">
            <div class="d-flex d-md-none"><a href="javascript:void(0)" class="page-header-right-close-toggle"><i class="feather-arrow-left me-2"></i><span>Back</span></a></div>
            <div class="d-flex align-items-center gap-2 page-header-right-items-wrapper">
                <form id="departments-list-filters" class="d-flex align-items-center gap-2" method="get" action="/departments/" hx-get="/departments/" hx-target="#departments-list-results" hx-swap="outerHTML" hx-trigger="change, keyup changed delay:400ms from:#departments-list-search, search from:#departments-list-search" hx-push-url="false">
                    <div class="input-group"><span class="input-group-text"><i class="feather-search"></i></span><input type="search" name="q" id="departments-list-search" class="form-control" placeholder="Search…" value="<?= e($filters['q']) ?>"></div>
                    <div class="form-check mb-0 text-nowrap"><input type="checkbox" name="archived" value="1" id="departments-list-filter-archived" class="form-check-input" <?= !empty($filters['archived']) ? 'checked' : '' ?>><label class="form-check-label fs-12" for="departments-list-filter-archived">Archived too</label></div>
                </form>
                <?php if ($canCreate): ?><a href="/departments/new" id="departments-list-add-btn" class="btn btn-primary" hx-get="/departments/new" hx-target="#page-content" hx-push-url="/departments/new"><i class="feather-plus me-2"></i><span>New department</span></a><?php endif; ?>
            </div>
        </div>
        <div class="d-md-none d-flex align-items-center"><a href="javascript:void(0)" class="page-header-right-open-toggle"><i class="feather-align-right fs-20"></i></a></div>
    </div>
</div>
<div class="main-content" id="departments-list-content"><?= $resultsHtml ?></div>
