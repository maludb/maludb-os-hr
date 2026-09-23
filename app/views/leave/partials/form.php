<?php /** Data: values, errors, types, people, me, settings */ $v = $values; $forSelf = count($people) <= 1; ?>
<div class="page-header" id="leave-form-header">
    <div class="page-header-left d-flex align-items-center">
        <div class="page-header-title"><h5 class="m-b-10">Request leave</h5></div>
        <ul class="breadcrumb"><li class="breadcrumb-item"><a href="/leave/" hx-get="/leave/" hx-target="#page-content" hx-push-url="/leave/">Leave</a></li><li class="breadcrumb-item">New request</li></ul>
    </div>
    <div class="page-header-right ms-auto">
        <div class="page-header-right-items">
            <div class="d-flex d-md-none"><a href="javascript:void(0)" class="page-header-right-close-toggle"><i class="feather-arrow-left me-2"></i><span>Back</span></a></div>
            <div class="d-flex align-items-center gap-2 page-header-right-items-wrapper">
                <a href="/leave/" id="leave-form-cancel-btn" class="btn btn-light-brand" hx-get="/leave/" hx-target="#page-content" hx-push-url="/leave/"><i class="feather-x me-2"></i><span>Cancel</span></a>
                <button type="submit" form="leave-form" id="leave-form-save-btn" class="btn btn-primary"><i class="feather-send me-2"></i><span>Request</span></button>
            </div>
        </div>
        <div class="d-md-none d-flex align-items-center"><a href="javascript:void(0)" class="page-header-right-open-toggle"><i class="feather-align-right fs-20"></i></a></div>
    </div>
</div>
<div class="main-content" id="leave-form-content">
    <div class="row"><div class="col-lg-8"><div class="card stretch stretch-full" id="leave-form-container"><div class="card-body">
        <?= view('shared/validation-errors.php', ['errors' => $errors]) ?>
        <form method="post" action="/leave/save.php" id="leave-form" hx-post="/leave/save.php" hx-target="#page-content" hx-swap="innerHTML">
            <?= csrf_field() ?>
            <?php $field = static fn (string $name, string $label, string $input): string => '<div class="row mb-3" id="leave-form-field-' . $name . '-row"><label for="leave-form-field-' . $name . '" class="col-lg-4 col-form-label fs-12 text-muted">' . e($label) . '</label><div class="col-lg-8">' . $input . '</div></div>'; ?>
            <?php if ($forSelf): ?><input type="hidden" name="person" value="<?= (int) $me ?>"><?php else:
                $po = ''; foreach ($people as $p) { $po .= '<option value="' . (int) $p['member_id'] . '"' . ((int) ($v['member_id'] ?? $me) === (int) $p['member_id'] ? ' selected' : '') . '>' . e($p['display_name']) . ((int) $p['member_id'] === (int) $me ? ' (you)' : '') . '</option>'; }
                echo $field('person', 'For', '<select name="person" id="leave-form-field-person" class="form-select">' . $po . '</select>');
            endif; ?>
            <?php $to = '<option value="">Choose…</option>'; foreach ($types as $t) { $to .= '<option value="' . (int) $t['leave_type_id'] . '"' . ((int) ($v['leave_type_id'] ?? 0) === (int) $t['leave_type_id'] ? ' selected' : '') . '>' . e($t['name']) . ($t['requires_approval'] ? '' : ' (no approval needed)') . ($t['accrual_days_per_year'] !== null ? ' · ' . e(days_label($t['accrual_days_per_year'])) . ' days a year' : '') . '</option>'; } ?>
            <?= $field('leave_type', 'Leave type', '<select name="leave_type" id="leave-form-field-leave_type" class="form-select" required>' . $to . '</select>') ?>
            <?= $field('start_date', 'First day', '<input type="date" name="start_date" id="leave-form-field-start_date" class="form-control" required value="' . e($v['start_date'] ?? '') . '">') ?>
            <?= $field('end_date', 'Last day', '<input type="date" name="end_date" id="leave-form-field-end_date" class="form-control" value="' . e($v['end_date'] ?? '') . '"><div class="form-check mt-2"><input type="checkbox" name="half_day" value="1" id="leave-form-field-half_day" class="form-check-input"' . (!empty($v['half_day']) ? ' checked' : '') . '><label class="form-check-label fs-12" for="leave-form-field-half_day">Half a day (the first day only)</label></div>') ?>
            <?= $field('reason', 'Reason', '<textarea name="reason" id="leave-form-field-reason" class="form-control" rows="2" maxlength="500">' . e($v['reason'] ?? '') . '</textarea>') ?>
            <p class="fs-12 text-muted mb-0">Days are counted on the business's work days (<?= e(implode(', ', array_map(static fn (int $d): string => ['', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'][$d], array_map('intval', explode(',', trim((string) ($settings['work_days'] ?? '{1,2,3,4,5}'), '{}')))))) ?>) less holidays. A request beyond the balance is recorded and flagged, not refused.</p>
        </form>
    </div></div></div></div>
</div>
