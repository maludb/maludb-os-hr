<?php /** Data: person, values, errors, current */ $id = (int) $person['member_id']; $v = $values; ?>
<div class="page-header" id="compensation-form-header">
    <div class="page-header-left d-flex align-items-center">
        <div class="page-header-title"><h5 class="m-b-10">Pay change · <?= e($person['display_name']) ?></h5></div>
        <ul class="breadcrumb">
            <li class="breadcrumb-item"><a href="/people/" hx-get="/people/" hx-target="#page-content" hx-push-url="/people/">People</a></li>
            <li class="breadcrumb-item"><a href="/people/<?= $id ?>?tab=pay" hx-get="/people/<?= $id ?>?tab=pay" hx-target="#page-content" hx-push-url="/people/<?= $id ?>?tab=pay"><?= e($person['display_name']) ?></a></li>
            <li class="breadcrumb-item">Pay change</li>
        </ul>
    </div>
    <div class="page-header-right ms-auto">
        <div class="page-header-right-items">
            <div class="d-flex d-md-none"><a href="javascript:void(0)" class="page-header-right-close-toggle"><i class="feather-arrow-left me-2"></i><span>Back</span></a></div>
            <div class="d-flex align-items-center gap-2 page-header-right-items-wrapper">
                <a href="/people/<?= $id ?>?tab=pay" id="compensation-form-cancel-btn" class="btn btn-light-brand" hx-get="/people/<?= $id ?>?tab=pay" hx-target="#page-content" hx-push-url="/people/<?= $id ?>?tab=pay"><i class="feather-x me-2"></i><span>Cancel</span></a>
                <button type="submit" form="compensation-form" id="compensation-form-save-btn" class="btn btn-primary"><i class="feather-save me-2"></i><span>Record the change</span></button>
            </div>
        </div>
        <div class="d-md-none d-flex align-items-center"><a href="javascript:void(0)" class="page-header-right-open-toggle"><i class="feather-align-right fs-20"></i></a></div>
    </div>
</div>
<div class="main-content" id="compensation-form-content">
    <div class="row"><div class="col-lg-8"><div class="card stretch stretch-full" id="compensation-form-container"><div class="card-body">
        <?php if ($current !== null): ?><p class="text-muted fs-12">Now: <?= e(pay_type_label($current['pay_type'])) ?> <?= e(money_label($current['pay_rate'], $current['currency'])) ?> since <?= e(format_date($current['effective_date'])) ?>. A change dated today or earlier takes effect at once; a future date waits for its day.</p><?php endif; ?>
        <?= view('shared/validation-errors.php', ['errors' => $errors]) ?>
        <form method="post" action="/people/compensation/save.php" id="compensation-form" hx-post="/people/compensation/save.php" hx-target="#page-content" hx-swap="innerHTML" hx-confirm="Record this pay change?">
            <?= csrf_field() ?><input type="hidden" name="person" value="<?= $id ?>">
            <?php $field = static fn (string $name, string $label, string $input): string => '<div class="row mb-3" id="compensation-form-field-' . $name . '-row"><label for="compensation-form-field-' . $name . '" class="col-lg-4 col-form-label fs-12 text-muted">' . e($label) . '</label><div class="col-lg-8">' . $input . '</div></div>'; ?>
            <?= $field('effective_date', 'Effective from', '<input type="date" name="effective_date" id="compensation-form-field-effective_date" class="form-control" required value="' . e($v['effective_date']) . '">') ?>
            <?php $to = ''; foreach (PAY_TYPES as $t) { $to .= '<option value="' . $t . '"' . ($v['pay_type'] === $t ? ' selected' : '') . '>' . e(pay_type_label($t)) . '</option>'; } ?>
            <?= $field('pay_type', 'Pay type', '<select name="pay_type" id="compensation-form-field-pay_type" class="form-select">' . $to . '</select>') ?>
            <?= $field('pay_rate', 'Rate', '<div class="input-group"><input type="text" name="currency" id="compensation-form-field-currency" class="form-control" style="max-width:5.5rem" maxlength="3" value="' . e($v['currency']) . '"><input type="number" step="0.01" min="0" name="pay_rate" id="compensation-form-field-pay_rate" class="form-control" required value="' . e($v['pay_rate']) . '"></div><div class="fs-11 text-muted mt-1">A salary is per year; hourly and daily rates are per hour and per day; per invoice records a contractor\'s rate and puts nobody on a pay run.</div>') ?>
            <?= $field('reason', 'Reason', '<input type="text" name="reason" id="compensation-form-field-reason" class="form-control" maxlength="200" value="' . e($v['reason']) . '" placeholder="annual review, promotion, new hire…">') ?>
        </form>
    </div></div></div></div>
</div>
