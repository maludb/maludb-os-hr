<?php /** Data: person, values, errors, staff */ $id = (int) $person['member_id']; $v = $values; ?>
<div class="page-header" id="directory-form-header">
    <div class="page-header-left d-flex align-items-center">
        <div class="page-header-title"><h5 class="m-b-10">Directory · <?= e($person['display_name']) ?></h5></div>
        <ul class="breadcrumb">
            <li class="breadcrumb-item"><a href="/people/" hx-get="/people/" hx-target="#page-content" hx-push-url="/people/">People</a></li>
            <li class="breadcrumb-item"><a href="/people/<?= $id ?>" hx-get="/people/<?= $id ?>" hx-target="#page-content" hx-push-url="/people/<?= $id ?>"><?= e($person['display_name']) ?></a></li>
            <li class="breadcrumb-item">Directory</li>
        </ul>
    </div>
    <div class="page-header-right ms-auto">
        <div class="page-header-right-items">
            <div class="d-flex d-md-none"><a href="javascript:void(0)" class="page-header-right-close-toggle"><i class="feather-arrow-left me-2"></i><span>Back</span></a></div>
            <div class="d-flex align-items-center gap-2 page-header-right-items-wrapper">
                <a href="/people/<?= $id ?>" id="directory-form-cancel-btn" class="btn btn-light-brand" hx-get="/people/<?= $id ?>" hx-target="#page-content" hx-push-url="/people/<?= $id ?>"><i class="feather-x me-2"></i><span>Cancel</span></a>
                <button type="submit" form="directory-form" id="directory-form-save-btn" class="btn btn-primary"><i class="feather-save me-2"></i><span>Save in the directory</span></button>
            </div>
        </div>
        <div class="d-md-none d-flex align-items-center"><a href="javascript:void(0)" class="page-header-right-open-toggle"><i class="feather-align-right fs-20"></i></a></div>
    </div>
</div>
<div class="main-content" id="directory-form-content">
    <div class="row"><div class="col-lg-8">
        <div class="card stretch stretch-full" id="directory-form-container">
            <div class="card-body">
                <p class="text-muted fs-12">These are the kernel's facts about the person. HR changes them through the directory as you, and the kernel's own rules decide.</p>
                <?= view('shared/validation-errors.php', ['errors' => $errors]) ?>
                <form method="post" action="/people/directory/save.php" id="directory-form" hx-post="/people/directory/save.php" hx-target="#page-content" hx-swap="innerHTML">
                    <?= csrf_field() ?>
                    <input type="hidden" name="person" value="<?= $id ?>">
                    <?php $field = static fn (string $name, string $label, string $input): string => '<div class="row mb-3" id="directory-form-field-' . $name . '-row"><label for="directory-form-field-' . $name . '" class="col-lg-4 col-form-label fs-12 text-muted">' . e($label) . '</label><div class="col-lg-8">' . $input . '</div></div>'; ?>
                    <?= $field('display_name', 'Name', '<input type="text" name="display_name" id="directory-form-field-display_name" class="form-control" required maxlength="120" value="' . e($v['display_name'] ?? '') . '">') ?>
                    <?= $field('job_title', 'Job title', '<input type="text" name="job_title" id="directory-form-field-job_title" class="form-control" maxlength="120" value="' . e($v['job_title'] ?? '') . '">') ?>
                    <?= $field('phone', 'Phone', '<input type="text" name="phone" id="directory-form-field-phone" class="form-control" maxlength="60" value="' . e($v['phone'] ?? '') . '">') ?>
                    <?php $tzOpts = ''; foreach (timezone_identifiers_list() as $tz) { $tzOpts .= '<option value="' . e($tz) . '"' . (($v['timezone'] ?? 'UTC') === $tz ? ' selected' : '') . '>' . e($tz) . '</option>'; } ?>
                    <?= $field('timezone', 'Time zone', '<select name="timezone" id="directory-form-field-timezone" class="form-select">' . $tzOpts . '</select>') ?>
                    <?= $field('status', 'Status', '<select name="status" id="directory-form-field-status" class="form-select"><option value="active"' . (($v['status'] ?? 'active') === 'active' ? ' selected' : '') . '>Active</option><option value="suspended"' . (($v['status'] ?? '') !== 'active' ? ' selected' : '') . '>Suspended</option></select>') ?>
                    <?= $field('is_external', 'External', '<div class="form-check mt-2"><input type="checkbox" name="is_external" value="1" id="directory-form-field-is_external" class="form-check-input"' . (!empty($v['is_external']) ? ' checked' : '') . '><label class="form-check-label fs-12" for="directory-form-field-is_external">Outside the business (an accountant, a customer)</label></div>') ?>
                    <?php if ($staff): ?>
                        <?= $field('business_role', 'Role', '<select name="business_role" id="directory-form-field-business_role" class="form-select"><option value="user"' . (($v['business_role'] ?? 'user') === 'user' ? ' selected' : '') . '>User</option><option value="dept_admin"' . (($v['business_role'] ?? '') === 'dept_admin' ? ' selected' : '') . '>Department admin</option></select>') ?>
                    <?php endif; ?>
                </form>
            </div>
        </div>
    </div></div>
</div>
