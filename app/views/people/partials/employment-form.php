<?php /** The employment form — a full screen (page header + main content). Data: person, record, isNew, errors, managers */
$id = (int) $person['member_id']; $r = $record;
$field = static function (string $name, string $label, string $input, string $for = '') : string {
    return '<div class="row mb-3" id="employment-form-field-' . $name . '-row"><label for="employment-form-field-' . $name . $for . '" class="col-lg-4 col-form-label fs-12 text-muted" id="employment-form-field-' . $name . '-label">' . e($label) . '</label><div class="col-lg-8">' . $input . '</div></div>';
};
$text = static fn (string $name, $value, string $type = 'text', string $extra = ''): string =>
    '<input type="' . $type . '" name="' . $name . '" id="employment-form-field-' . $name . '" class="form-control" value="' . e($value ?? '') . '" ' . $extra . '>';
$select = static function (string $name, array $options, $current, bool $blank = false): string {
    $h = '<select name="' . $name . '" id="employment-form-field-' . $name . '" class="form-select">';
    if ($blank) { $h .= '<option value="">—</option>'; }
    foreach ($options as $v => $l) { $h .= '<option value="' . e((string) $v) . '"' . ((string) $current === (string) $v ? ' selected' : '') . '>' . e($l) . '</option>'; }
    return $h . '</select>';
};
?>
<div class="page-header" id="employment-form-header">
    <div class="page-header-left d-flex align-items-center">
        <div class="page-header-title"><h5 class="m-b-10"><?= $isNew ? 'Employ' : 'Employment' ?> · <?= e($person['display_name']) ?></h5></div>
        <ul class="breadcrumb">
            <li class="breadcrumb-item"><a href="/people/" hx-get="/people/" hx-target="#page-content" hx-push-url="/people/">People</a></li>
            <li class="breadcrumb-item"><a href="/people/<?= $id ?>" hx-get="/people/<?= $id ?>" hx-target="#page-content" hx-push-url="/people/<?= $id ?>"><?= e($person['display_name']) ?></a></li>
            <li class="breadcrumb-item">Employment</li>
        </ul>
    </div>
    <div class="page-header-right ms-auto">
        <div class="page-header-right-items">
            <div class="d-flex d-md-none"><a href="javascript:void(0)" class="page-header-right-close-toggle"><i class="feather-arrow-left me-2"></i><span>Back</span></a></div>
            <div class="d-flex align-items-center gap-2 page-header-right-items-wrapper">
                <a href="/people/<?= $id ?>?tab=employment" id="employment-form-cancel-btn" class="btn btn-light-brand" hx-get="/people/<?= $id ?>?tab=employment" hx-target="#page-content" hx-push-url="/people/<?= $id ?>?tab=employment"><i class="feather-x me-2"></i><span>Cancel</span></a>
                <button type="submit" form="employment-form" id="employment-form-save-btn" class="btn btn-primary"><i class="feather-save me-2"></i><span><?= $isNew ? 'Employ' : 'Save' ?></span></button>
            </div>
        </div>
        <div class="d-md-none d-flex align-items-center"><a href="javascript:void(0)" class="page-header-right-open-toggle"><i class="feather-align-right fs-20"></i></a></div>
    </div>
</div>
<div class="main-content" id="employment-form-content">
    <div class="row">
        <div class="col-lg-12">
            <div class="card stretch stretch-full" id="employment-form-container">
                <div class="card-body">
                    <?= view('shared/validation-errors.php', ['errors' => $errors]) ?>
                    <form method="post" action="/people/employment/save.php" id="employment-form" hx-post="/people/employment/save.php" hx-target="#page-content" hx-swap="innerHTML">
                        <?= csrf_field() ?>
                        <input type="hidden" name="person" value="<?= $id ?>">
                        <div class="row">
                            <div class="col-xl-6">
                                <h6 class="fw-bold text-muted text-uppercase fs-12 mb-3">Employment</h6>
                                <?= $field('employee_no', 'Employee no.', $text('employee_no', $r['employee_no'] ?? '', 'text', 'maxlength="30"')) ?>
                                <?= $field('employment_type', 'Type', $select('employment_type', ['employee' => 'Employee', 'contractor' => 'Contractor', 'intern' => 'Intern'], $r['employment_type'] ?? 'employee')) ?>
                                <?= $field('status', 'Status', $select('status', ['candidate' => 'Candidate', 'active' => 'Active', 'on_leave' => 'On leave'] + (($r['status'] ?? '') === 'ended' ? ['ended' => 'Ended (' . format_date($r['end_date'] ?? null) . ')'] : []), $r['status'] ?? 'active')) ?>
                                <?= $field('manager', 'Manager', picker_field(['id' => 'employment-form-field-manager', 'name' => 'manager', 'source' => 'manager', 'value' => $r['manager_member_id'] ?? '',
                    'title' => 'Managers', 'placeholder' => 'No manager', 'search' => 'Search people…', 'params' => ['except' => $id], 'labelledby' => 'employment-form-field-manager-label', 'invalid' => false]), '-open') ?>
                                <?= $field('work_location', 'Work location', $text('work_location', $r['work_location'] ?? '', 'text', 'maxlength="120" placeholder="Main Street, Remote…"')) ?>
                                <?= $field('start_date', 'Start date', $text('start_date', $r['start_date'] ?? '', 'date')) ?>
                                <?= $field('probation_end_date', 'Probation ends', $text('probation_end_date', $r['probation_end_date'] ?? '', 'date')) ?>
                                <?= $field('weekly_hours', 'Weekly hours', $text('weekly_hours', $r['weekly_hours'] ?? '', 'number', 'step="0.25" min="0.25" max="168"')) ?>
                                <?= $field('pay_schedule', 'Pay schedule', $select('pay_schedule', ['weekly' => 'Weekly', 'biweekly' => 'Every two weeks', 'semimonthly' => 'Twice a month', 'monthly' => 'Monthly'], $r['pay_schedule'] ?? '', true)) ?>
                            </div>
                            <div class="col-xl-6">
                                <h6 class="fw-bold text-muted text-uppercase fs-12 mb-3">Home contact <small class="text-muted text-lowercase fw-normal">— HR staff only, in no view</small></h6>
                                <?= $field('personal_email', 'Personal email', $text('personal_email', $r['personal_email'] ?? '', 'email')) ?>
                                <?= $field('address', 'Address', '<textarea name="address" id="employment-form-field-address" class="form-control" rows="2">' . e($r['address'] ?? '') . '</textarea>') ?>
                                <?= $field('emergency_contact', 'Emergency contact', $text('emergency_contact', $r['emergency_contact'] ?? '', 'text', 'placeholder="name and phone"')) ?>
                                <?= $field('birth_date', 'Birth date', $text('birth_date', $r['birth_date'] ?? '', 'date')) ?>
                                <?= $field('national_id_last4', 'Id (last 2–4)', $text('national_id_last4', $r['national_id_last4'] ?? '', 'text', 'maxlength="4"')) ?>
                                <?= $field('notes', 'Notes', '<textarea name="notes" id="employment-form-field-notes" class="form-control" rows="3">' . e($r['notes'] ?? '') . '</textarea>') ?>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
