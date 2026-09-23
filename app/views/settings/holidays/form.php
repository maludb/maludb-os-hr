<?php /** Data: h (null on create), values, errors */ $isNew = $h === null; $id = $isNew ? null : (int) $h['id']; $v = $values; ?>
<div class="page-header" id="holiday-form-header">
    <div class="page-header-left d-flex align-items-center">
        <div class="page-header-title"><h5 class="m-b-10"><?= $isNew ? 'New holiday' : e($h['name']) ?></h5></div>
        <ul class="breadcrumb"><li class="breadcrumb-item"><a href="/settings/holidays/" hx-get="/settings/holidays/" hx-target="#page-content" hx-push-url="/settings/holidays/">Holidays</a></li><li class="breadcrumb-item"><?= $isNew ? 'New' : 'Edit' ?></li></ul>
    </div>
    <div class="page-header-right ms-auto">
        <div class="page-header-right-items">
            <div class="d-flex d-md-none"><a href="javascript:void(0)" class="page-header-right-close-toggle"><i class="feather-arrow-left me-2"></i><span>Back</span></a></div>
            <div class="d-flex align-items-center gap-2 page-header-right-items-wrapper">
                <a href="/settings/holidays/" id="holiday-form-cancel-btn" class="btn btn-light-brand" hx-get="/settings/holidays/" hx-target="#page-content" hx-push-url="/settings/holidays/"><i class="feather-x me-2"></i><span>Cancel</span></a>
                <button type="submit" form="holiday-form" id="holiday-form-save-btn" class="btn btn-primary"><i class="feather-save me-2"></i><span>Save</span></button>
            </div>
        </div>
        <div class="d-md-none d-flex align-items-center"><a href="javascript:void(0)" class="page-header-right-open-toggle"><i class="feather-align-right fs-20"></i></a></div>
    </div>
</div>
<div class="main-content" id="holiday-form-content">
    <div class="row"><div class="col-lg-6"><div class="card stretch stretch-full" id="holiday-form-container"><div class="card-body">
        <?= view('shared/validation-errors.php', ['errors' => $errors]) ?>
        <form method="post" action="/settings/holidays/save.php" id="holiday-form" hx-post="/settings/holidays/save.php" hx-target="#page-content" hx-swap="innerHTML">
            <?= csrf_field() ?>
            <?php if (!$isNew): ?><input type="hidden" name="holiday" value="<?= $id ?>"><?php endif; ?>
            <div class="row mb-3" id="holiday-form-field-holiday_date-row"><label for="holiday-form-field-holiday_date" class="col-lg-4 col-form-label fs-12 text-muted">Date</label><div class="col-lg-8"><input type="date" name="holiday_date" id="holiday-form-field-holiday_date" class="form-control" required value="<?= e($v['holiday_date']) ?>"></div></div>
            <div class="row mb-3" id="holiday-form-field-name-row"><label for="holiday-form-field-name" class="col-lg-4 col-form-label fs-12 text-muted">Name</label><div class="col-lg-8"><input type="text" name="name" id="holiday-form-field-name" class="form-control" required maxlength="80" value="<?= e($v['name']) ?>"></div></div>
        </form>
    </div></div></div></div>
</div>
