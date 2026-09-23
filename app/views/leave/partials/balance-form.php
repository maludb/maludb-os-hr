<?php /** Data: values, errors, types, people */ $v = $values; ?>
<div class="page-header" id="balance-form-header">
    <div class="page-header-left d-flex align-items-center">
        <div class="page-header-title"><h5 class="m-b-10">Adjust a balance</h5></div>
        <ul class="breadcrumb"><li class="breadcrumb-item"><a href="/leave/balances/" hx-get="/leave/balances/" hx-target="#page-content" hx-push-url="/leave/balances/">Balances</a></li><li class="breadcrumb-item">Adjust</li></ul>
    </div>
    <div class="page-header-right ms-auto">
        <div class="page-header-right-items">
            <div class="d-flex d-md-none"><a href="javascript:void(0)" class="page-header-right-close-toggle"><i class="feather-arrow-left me-2"></i><span>Back</span></a></div>
            <div class="d-flex align-items-center gap-2 page-header-right-items-wrapper">
                <a href="/leave/balances/" id="balance-form-cancel-btn" class="btn btn-light-brand" hx-get="/leave/balances/" hx-target="#page-content" hx-push-url="/leave/balances/"><i class="feather-x me-2"></i><span>Cancel</span></a>
                <button type="submit" form="balance-form" id="balance-form-save-btn" class="btn btn-primary"><i class="feather-save me-2"></i><span>Set the balance</span></button>
            </div>
        </div>
        <div class="d-md-none d-flex align-items-center"><a href="javascript:void(0)" class="page-header-right-open-toggle"><i class="feather-align-right fs-20"></i></a></div>
    </div>
</div>
<div class="main-content" id="balance-form-content">
    <div class="row"><div class="col-lg-8"><div class="card stretch stretch-full" id="balance-form-container"><div class="card-body">
        <p class="text-muted fs-12">Carry-over and corrections happen here, by hand, with a reason. Nothing carries by itself.</p>
        <?= view('shared/validation-errors.php', ['errors' => $errors]) ?>
        <form method="post" action="/leave/balances/save.php" id="balance-form" hx-post="/leave/balances/save.php" hx-target="#page-content" hx-swap="innerHTML" hx-confirm="Set this balance?">
            <?= csrf_field() ?>
            <?php $field = static fn (string $name, string $label, string $input): string => '<div class="row mb-3" id="balance-form-field-' . $name . '-row"><label for="balance-form-field-' . $name . '" class="col-lg-4 col-form-label fs-12 text-muted">' . e($label) . '</label><div class="col-lg-8">' . $input . '</div></div>'; ?>
            <?php $po = '<option value="">Choose…</option>'; foreach ($people as $p) { $po .= '<option value="' . (int) $p['member_id'] . '"' . ((int) ($v['member_id'] ?? 0) === (int) $p['member_id'] ? ' selected' : '') . '>' . e($p['display_name']) . '</option>'; } ?>
            <?= $field('person', 'Person', '<select name="person" id="balance-form-field-person" class="form-select" required>' . $po . '</select>') ?>
            <?php $to = '<option value="">Choose…</option>'; foreach ($types as $t) { if ($t['accrual_days_per_year'] === null) { continue; } $to .= '<option value="' . (int) $t['leave_type_id'] . '"' . ((int) ($v['leave_type_id'] ?? 0) === (int) $t['leave_type_id'] ? ' selected' : '') . '>' . e($t['name']) . '</option>'; } ?>
            <?= $field('leave_type', 'Leave type', '<select name="leave_type" id="balance-form-field-leave_type" class="form-select" required>' . $to . '</select>') ?>
            <?= $field('year', 'Year', '<input type="number" name="year" id="balance-form-field-year" class="form-control" min="2000" max="2200" required value="' . e($v['year']) . '">') ?>
            <?= $field('accrued_days', 'Accrued days', '<input type="number" step="0.5" min="0" name="accrued_days" id="balance-form-field-accrued_days" class="form-control" required value="' . e($v['accrued_days']) . '">') ?>
            <?= $field('carried_days', 'Carried over', '<input type="number" step="0.5" min="0" name="carried_days" id="balance-form-field-carried_days" class="form-control" required value="' . e($v['carried_days']) . '">') ?>
            <?= $field('reason', 'Reason', '<input type="text" name="reason" id="balance-form-field-reason" class="form-control" required maxlength="300" value="' . e($v['reason']) . '">') ?>
        </form>
    </div></div></div></div>
</div>
