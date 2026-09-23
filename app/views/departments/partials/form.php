<?php /** Data: d (null on create), values, errors, parents, managers */ $isNew = $d === null; $id = $isNew ? null : (int) $d['department_id']; $v = $values; ?>
<div class="page-header" id="department-form-header">
    <div class="page-header-left d-flex align-items-center">
        <div class="page-header-title"><h5 class="m-b-10"><?= $isNew ? 'New department' : e($d['name']) ?></h5></div>
        <ul class="breadcrumb"><li class="breadcrumb-item"><a href="/departments/" hx-get="/departments/" hx-target="#page-content" hx-push-url="/departments/">Departments</a></li><li class="breadcrumb-item"><?= $isNew ? 'New' : 'Edit' ?></li></ul>
    </div>
    <div class="page-header-right ms-auto">
        <div class="page-header-right-items">
            <div class="d-flex d-md-none"><a href="javascript:void(0)" class="page-header-right-close-toggle"><i class="feather-arrow-left me-2"></i><span>Back</span></a></div>
            <div class="d-flex align-items-center gap-2 page-header-right-items-wrapper">
                <?php $back = $isNew ? '/departments/' : '/departments/' . $id; ?>
                <a href="<?= e($back) ?>" id="department-form-cancel-btn" class="btn btn-light-brand" hx-get="<?= e($back) ?>" hx-target="#page-content" hx-push-url="<?= e($back) ?>"><i class="feather-x me-2"></i><span>Cancel</span></a>
                <button type="submit" form="department-form" id="department-form-save-btn" class="btn btn-primary"><i class="feather-save me-2"></i><span><?= $isNew ? 'Create in the directory' : 'Save in the directory' ?></span></button>
            </div>
        </div>
        <div class="d-md-none d-flex align-items-center"><a href="javascript:void(0)" class="page-header-right-open-toggle"><i class="feather-align-right fs-20"></i></a></div>
    </div>
</div>
<div class="main-content" id="department-form-content">
    <div class="row"><div class="col-lg-8"><div class="card stretch stretch-full" id="department-form-container"><div class="card-body">
        <?= view('shared/validation-errors.php', ['errors' => $errors]) ?>
        <form method="post" action="/departments/save.php" id="department-form" hx-post="/departments/save.php" hx-target="#page-content" hx-swap="innerHTML">
            <?= csrf_field() ?>
            <?php if (!$isNew): ?><input type="hidden" name="department" value="<?= $id ?>"><?php endif; ?>
            <?php $field = static fn (string $name, string $label, string $input): string => '<div class="row mb-3" id="department-form-field-' . $name . '-row"><label for="department-form-field-' . $name . '" class="col-lg-4 col-form-label fs-12 text-muted">' . e($label) . '</label><div class="col-lg-8">' . $input . '</div></div>'; ?>
            <?= $field('name', 'Name', '<input type="text" name="name" id="department-form-field-name" class="form-control" required maxlength="80" value="' . e($v['name'] ?? '') . '">') ?>
            <?= $field('description', 'Description', '<textarea name="description" id="department-form-field-description" class="form-control" rows="3">' . e($v['description'] ?? '') . '</textarea>') ?>
            <?php $po = '<option value="">— (the Front Office)</option>'; foreach ($parents as $p) { $po .= '<option value="' . (int) $p['department_id'] . '"' . ((int) ($v['parent_id'] ?? 0) === (int) $p['department_id'] ? ' selected' : '') . '>' . e($p['name']) . '</option>'; } ?>
            <?= $field('parent', 'Reports to', '<select name="parent" id="department-form-field-parent" class="form-select">' . $po . '</select>') ?>
            <?php $mo = '<option value="">—</option>'; foreach ($managers as $m) { $mo .= '<option value="' . (int) $m['member_id'] . '"' . ((int) ($v['manager_member_id'] ?? 0) === (int) $m['member_id'] ? ' selected' : '') . '>' . e($m['display_name']) . ($m['job_title'] ? ' · ' . e($m['job_title']) : '') . '</option>'; } ?>
            <?= $field('manager', 'Manager', '<select name="manager" id="department-form-field-manager" class="form-select">' . $mo . '</select>') ?>
        </form>
    </div></div></div></div>
</div>
