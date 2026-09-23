<?php /** New pay run. Data: values, errors, people (eligible_people rows) */ $v = $values; ?>
<div class="page-header" id="pay-run-form-header">
    <div class="page-header-left d-flex align-items-center">
        <div class="page-header-title"><h5 class="m-b-10">New pay run</h5></div>
        <ul class="breadcrumb"><li class="breadcrumb-item"><a href="/payruns/" hx-get="/payruns/" hx-target="#page-content" hx-push-url="/payruns/">Pay runs</a></li><li class="breadcrumb-item">New</li></ul>
    </div>
    <div class="page-header-right ms-auto">
        <div class="page-header-right-items">
            <div class="d-flex d-md-none"><a href="javascript:void(0)" class="page-header-right-close-toggle"><i class="feather-arrow-left me-2"></i><span>Back</span></a></div>
            <div class="d-flex align-items-center gap-2 page-header-right-items-wrapper">
                <a href="/payruns/" id="pay-run-form-cancel-btn" class="btn btn-light-brand" hx-get="/payruns/" hx-target="#page-content" hx-push-url="/payruns/"><i class="feather-x me-2"></i><span>Cancel</span></a>
                <button type="submit" form="pay-run-form" id="pay-run-form-save-btn" class="btn btn-primary"><i class="feather-file-plus me-2"></i><span>Draft the run</span></button>
            </div>
        </div>
        <div class="d-md-none d-flex align-items-center"><a href="javascript:void(0)" class="page-header-right-open-toggle"><i class="feather-align-right fs-20"></i></a></div>
    </div>
</div>
<div class="main-content" id="pay-run-form-content">
    <div class="row">
        <div class="col-xl-5"><div class="card stretch stretch-full" id="pay-run-form-container"><div class="card-body">
            <?= view('shared/validation-errors.php', ['errors' => $errors]) ?>
            <form method="post" action="/payruns/save.php" id="pay-run-form" hx-post="/payruns/save.php" hx-target="#page-content" hx-swap="innerHTML">
                <?= csrf_field() ?>
                <?php $field = static fn (string $name, string $label, string $input): string => '<div class="row mb-3" id="pay-run-form-field-' . $name . '-row"><label for="pay-run-form-field-' . $name . '" class="col-lg-4 col-form-label fs-12 text-muted">' . e($label) . '</label><div class="col-lg-8">' . $input . '</div></div>'; ?>
                <?= $field('period_start', 'Period from', '<input type="date" name="period_start" id="pay-run-form-field-period_start" class="form-control" required value="' . e($v['period_start']) . '" hx-get="/payruns/new" hx-target="#page-content" hx-include="#pay-run-form" hx-trigger="change" hx-push-url="false">') ?>
                <?= $field('period_end', 'Period to', '<input type="date" name="period_end" id="pay-run-form-field-period_end" class="form-control" required value="' . e($v['period_end']) . '" hx-get="/payruns/new" hx-target="#page-content" hx-include="#pay-run-form" hx-trigger="change" hx-push-url="false">') ?>
                <?= $field('pay_date', 'Pay date', '<input type="date" name="pay_date" id="pay-run-form-field-pay_date" class="form-control" required value="' . e($v['pay_date']) . '">') ?>
                <?= $field('currency', 'Currency', '<input type="text" name="currency" id="pay-run-form-field-currency" class="form-control" maxlength="3" value="' . e($v['currency']) . '" style="max-width:6rem" hx-get="/payruns/new" hx-target="#page-content" hx-include="#pay-run-form" hx-trigger="change" hx-push-url="false">') ?>
                <?= $field('note', 'Note', '<input type="text" name="note" id="pay-run-form-field-note" class="form-control" maxlength="500" value="' . e($v['note']) . '">') ?>
                <p class="fs-12 text-muted mb-0">No tax is calculated and no money moves: the run records what the business decides to pay. The accountant's figures are keyed as lines afterwards.</p>
            </form>
        </div></div></div>
        <div class="col-xl-7"><div class="card stretch stretch-full" id="pay-run-form-people"><div class="card-header"><h5 class="card-title">Who goes on it</h5></div>
            <div class="card-body custom-card-action p-0"><div class="table-responsive"><table class="table table-hover mb-0 fs-12" id="pay-run-form-people-table">
                <thead class="thead-light"><tr><th style="width:2rem"></th><th>Person</th><th>Pay basis at <?= e(format_date($v['period_end'])) ?></th><th>Base line</th></tr></thead>
                <tbody>
                <?php if ($people === []): ?><tr><td colspan="4" class="text-center text-muted py-4">Nobody is employed now.</td></tr><?php endif; ?>
                <?php foreach ($people as $p): $mid = (int) $p['member_id']; ?>
                    <tr id="pay-run-form-person-<?= $mid ?>">
                        <td><input type="checkbox" name="people[]" value="<?= $mid ?>" form="pay-run-form" class="form-check-input" id="pay-run-form-field-people-<?= $mid ?>" <?= $p['eligible'] ? 'checked' : '' ?>></td>
                        <td><?= e($p['display_name']) ?> <small class="text-muted"><?= e($p['employment_type']) ?></small></td>
                        <td><?= $p['pay_type'] ? e(pay_type_label($p['pay_type'])) . ' ' . e(money_label($p['pay_rate'], $p['currency'])) . ($p['pay_schedule'] ? ' <small class="text-muted">' . e($p['pay_schedule']) . '</small>' : '') : '<span class="text-muted">none</span>' ?></td>
                        <td><?= $p['base_note'] === null ? '<span class="badge bg-soft-success text-success">computed</span>' : '<span class="badge bg-soft-' . (in_array($p['pay_type'], ['hourly', 'daily'], true) ? 'warning text-warning' : 'secondary text-secondary') . '">' . e($p['base_note']) . '</span>' ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table></div></div>
            <div class="card-footer fs-11 text-muted">Unticked people are left off. Someone with no base line still goes on the run — with the note saying why — so nothing is paid by guess.</div>
        </div></div>
    </div>
</div>
