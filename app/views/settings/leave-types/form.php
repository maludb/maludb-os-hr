<?php /** Data: t (null on create), values, errors */ $isNew = $t === null; $id = $isNew ? null : (int) $t['id']; $v = $values; ?>
<div class="page-header" id="leave-type-form-header">
    <div class="page-header-left d-flex align-items-center">
        <div class="page-header-title"><h5 class="m-b-10"><?= $isNew ? 'New leave type' : e($t['name']) ?></h5></div>
        <ul class="breadcrumb"><li class="breadcrumb-item"><a href="/settings/leave-types/" hx-get="/settings/leave-types/" hx-target="#page-content" hx-push-url="/settings/leave-types/">Leave types</a></li><li class="breadcrumb-item"><?= $isNew ? 'New' : 'Edit' ?></li></ul>
    </div>
    <div class="page-header-right ms-auto">
        <div class="page-header-right-items">
            <div class="d-flex d-md-none"><a href="javascript:void(0)" class="page-header-right-close-toggle"><i class="feather-arrow-left me-2"></i><span>Back</span></a></div>
            <div class="d-flex align-items-center gap-2 page-header-right-items-wrapper">
                <a href="/settings/leave-types/" id="leave-type-form-cancel-btn" class="btn btn-light-brand" hx-get="/settings/leave-types/" hx-target="#page-content" hx-push-url="/settings/leave-types/"><i class="feather-x me-2"></i><span>Cancel</span></a>
                <button type="submit" form="leave-type-form" id="leave-type-form-save-btn" class="btn btn-primary"><i class="feather-save me-2"></i><span>Save</span></button>
            </div>
        </div>
        <div class="d-md-none d-flex align-items-center"><a href="javascript:void(0)" class="page-header-right-open-toggle"><i class="feather-align-right fs-20"></i></a></div>
    </div>
</div>
<div class="main-content" id="leave-type-form-content">
    <div class="row"><div class="col-lg-8"><div class="card stretch stretch-full" id="leave-type-form-container"><div class="card-body">
        <?= view('shared/validation-errors.php', ['errors' => $errors]) ?>
        <form method="post" action="/settings/leave-types/save.php" id="leave-type-form" hx-post="/settings/leave-types/save.php" hx-target="#page-content" hx-swap="innerHTML">
            <?= csrf_field() ?>
            <?php if (!$isNew): ?><input type="hidden" name="leave_type" value="<?= $id ?>"><?php endif; ?>
            <?php $field = static fn (string $name, string $label, string $input): string => '<div class="row mb-3" id="leave-type-form-field-' . $name . '-row"><label for="leave-type-form-field-' . $name . '" class="col-lg-4 col-form-label fs-12 text-muted">' . e($label) . '</label><div class="col-lg-8">' . $input . '</div></div>'; ?>
            <?= $field('name', 'Name', '<input type="text" name="name" id="leave-type-form-field-name" class="form-control" required maxlength="60" value="' . e($v['name']) . '">') ?>
            <?= $field('accrual_days_per_year', 'Days a year', '<input type="number" step="0.5" min="0" name="accrual_days_per_year" id="leave-type-form-field-accrual_days_per_year" class="form-control" value="' . e($v['accrual_days_per_year'] === null ? '' : days_label($v['accrual_days_per_year'])) . '" placeholder="blank = unlimited">') ?>
            <?= $field('carry_over_days', 'Carry-over cap', '<input type="number" step="0.5" min="0" name="carry_over_days" id="leave-type-form-field-carry_over_days" class="form-control" value="' . e(days_label($v['carry_over_days'])) . '">') ?>
            <?= $field('is_paid', 'Paid', '<div class="form-check mt-2"><input type="checkbox" name="is_paid" value="1" id="leave-type-form-field-is_paid" class="form-check-input"' . (!empty($v['is_paid']) ? ' checked' : '') . '><label class="form-check-label fs-12" for="leave-type-form-field-is_paid">Paid leave</label></div>') ?>
            <?= $field('requires_approval', 'Approval', '<div class="form-check mt-2"><input type="checkbox" name="requires_approval" value="1" id="leave-type-form-field-requires_approval" class="form-check-input"' . (!empty($v['requires_approval']) ? ' checked' : '') . '><label class="form-check-label fs-12" for="leave-type-form-field-requires_approval">Needs a decision (unticked: approved as it is recorded, like sick leave)</label></div>') ?>
            <?php if (!$isNew): ?><?= $field('archived', 'Archived', '<div class="form-check mt-2"><input type="checkbox" name="archived" value="1" id="leave-type-form-field-archived" class="form-check-input"' . (!empty($v['archived_at']) ? ' checked' : '') . '><label class="form-check-label fs-12" for="leave-type-form-field-archived">Not offered any more</label></div>') ?><?php endif; ?>
        </form>
    </div></div></div></div>
</div>
