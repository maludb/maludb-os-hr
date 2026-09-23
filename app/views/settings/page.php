<?php /** Data: s (hr_settings row), errors */ $wd = is_array($s['work_days']) ? $s['work_days'] : array_map('intval', explode(',', trim((string) $s['work_days'], '{}'))); ?>
<div class="page-header" id="settings-header">
    <div class="page-header-left d-flex align-items-center">
        <div class="page-header-title"><h5 class="m-b-10">Settings</h5></div>
        <ul class="breadcrumb"><li class="breadcrumb-item"><a href="/" hx-get="/" hx-target="#page-content" hx-push-url="/">Home</a></li><li class="breadcrumb-item">Settings</li></ul>
    </div>
    <div class="page-header-right ms-auto">
        <div class="page-header-right-items">
            <div class="d-flex d-md-none"><a href="javascript:void(0)" class="page-header-right-close-toggle"><i class="feather-arrow-left me-2"></i><span>Back</span></a></div>
            <div class="d-flex align-items-center gap-2 page-header-right-items-wrapper">
                <button type="submit" form="settings-form" id="settings-form-save-btn" class="btn btn-primary"><i class="feather-save me-2"></i><span>Save</span></button>
            </div>
        </div>
        <div class="d-md-none d-flex align-items-center"><a href="javascript:void(0)" class="page-header-right-open-toggle"><i class="feather-align-right fs-20"></i></a></div>
    </div>
</div>
<div class="main-content" id="settings-content">
    <div class="row"><div class="col-lg-8"><div class="card stretch stretch-full" id="settings-form-container"><div class="card-body">
        <?= view('shared/validation-errors.php', ['errors' => $errors]) ?>
        <form method="post" action="/settings/save.php" id="settings-form" hx-post="/settings/save.php" hx-target="#page-content" hx-swap="innerHTML">
            <?= csrf_field() ?>
            <?php $field = static fn (string $name, string $label, string $input): string => '<div class="row mb-3" id="settings-form-field-' . $name . '-row"><label for="settings-form-field-' . $name . '" class="col-lg-4 col-form-label fs-12 text-muted">' . e($label) . '</label><div class="col-lg-8">' . $input . '</div></div>'; ?>
            <?= $field('currency', 'Currency', '<input type="text" name="currency" id="settings-form-field-currency" class="form-control" maxlength="3" required value="' . e($s['currency']) . '" style="max-width:8rem">') ?>
            <?php $tzo = ''; foreach (timezone_identifiers_list() as $tz) { $tzo .= '<option value="' . e($tz) . '"' . ($s['timezone'] === $tz ? ' selected' : '') . '>' . e($tz) . '</option>'; } ?>
            <?= $field('timezone', 'Time zone', '<select name="timezone" id="settings-form-field-timezone" class="form-select">' . $tzo . '</select>') ?>
            <?php $wdHtml = ''; foreach ([1 => 'Mon', 2 => 'Tue', 3 => 'Wed', 4 => 'Thu', 5 => 'Fri', 6 => 'Sat', 7 => 'Sun'] as $n => $l) { $wdHtml .= '<div class="form-check form-check-inline"><input type="checkbox" name="work_days[]" value="' . $n . '" id="settings-form-field-work_days-' . $n . '" class="form-check-input"' . (in_array($n, $wd, true) ? ' checked' : '') . '><label class="form-check-label fs-12" for="settings-form-field-work_days-' . $n . '">' . $l . '</label></div>'; } ?>
            <?= $field('work_days', 'Work days', '<div id="settings-form-field-work_days">' . $wdHtml . '</div><div class="fs-11 text-muted mt-1">Leave is counted on these days, less holidays.</div>') ?>
            <?= $field('employee_no_prefix', 'Employee no. prefix', '<input type="text" name="employee_no_prefix" id="settings-form-field-employee_no_prefix" class="form-control" maxlength="10" required value="' . e($s['employee_no_prefix']) . '" style="max-width:8rem">') ?>
        </form>
    </div></div></div></div>
</div>
