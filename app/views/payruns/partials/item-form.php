<?php /** Key one person's item. Data: item (with lines), basis, errors, keyed? */ $iid = (int) $item['pay_run_item_id']; $cur = $item['currency'];
$keyed = $keyed ?? array_values(array_filter($item['lines'], static fn (array $l): bool => !$l['is_base']));
$base = array_values(array_filter($item['lines'], static fn (array $l): bool => (bool) $l['is_base']))[0] ?? null;
$hourly = ($basis['pay_type'] ?? null) === 'hourly'; $daily = ($basis['pay_type'] ?? null) === 'daily'; ?>
<div class="page-header" id="pay-run-item-form-header">
    <div class="page-header-left d-flex align-items-center">
        <div class="page-header-title"><h5 class="m-b-10"><?= e($item['display_name']) ?> · <?= e($item['run_no']) ?></h5></div>
        <ul class="breadcrumb"><li class="breadcrumb-item"><a href="/payruns/" hx-get="/payruns/" hx-target="#page-content" hx-push-url="/payruns/">Pay runs</a></li><li class="breadcrumb-item"><a href="/payruns/<?= (int) $item['pay_run_id'] ?>" hx-get="/payruns/<?= (int) $item['pay_run_id'] ?>" hx-target="#page-content" hx-push-url="/payruns/<?= (int) $item['pay_run_id'] ?>"><?= e($item['run_no']) ?></a></li><li class="breadcrumb-item"><?= e($item['display_name']) ?></li></ul>
    </div>
    <div class="page-header-right ms-auto">
        <div class="page-header-right-items">
            <div class="d-flex d-md-none"><a href="javascript:void(0)" class="page-header-right-close-toggle"><i class="feather-arrow-left me-2"></i><span>Back</span></a></div>
            <div class="d-flex align-items-center gap-2 page-header-right-items-wrapper">
                <a href="/payruns/<?= (int) $item['pay_run_id'] ?>" id="pay-run-item-form-cancel-btn" class="btn btn-light-brand" hx-get="/payruns/<?= (int) $item['pay_run_id'] ?>" hx-target="#page-content" hx-push-url="/payruns/<?= (int) $item['pay_run_id'] ?>"><i class="feather-x me-2"></i><span>Cancel</span></a>
                <button type="submit" form="pay-run-item-form" id="pay-run-item-form-save-btn" class="btn btn-primary"><i class="feather-save me-2"></i><span>Save</span></button>
            </div>
        </div>
        <div class="d-md-none d-flex align-items-center"><a href="javascript:void(0)" class="page-header-right-open-toggle"><i class="feather-align-right fs-20"></i></a></div>
    </div>
</div>
<div class="main-content" id="pay-run-item-form-content">
    <div class="row"><div class="col-lg-10"><div class="card stretch stretch-full" id="pay-run-item-form-container"><div class="card-body">
        <p class="text-muted fs-12">Pay basis at <?= e(format_date($item['period_end'])) ?>: <?= $basis ? e(pay_type_label($basis['pay_type'])) . ' ' . e(money_label($basis['pay_rate'], $basis['currency'])) : 'none recorded' ?>.
            <?= $base ? 'Base line: ' . e($base['description'] ?? '') . ' = ' . e(money_label($base['amount'], $cur)) . '.' : ($item['base_note'] ? 'No base line: ' . e($item['base_note']) . '.' : '') ?></p>
        <?= view('shared/validation-errors.php', ['errors' => $errors]) ?>
        <form method="post" action="/payruns/items/save.php" id="pay-run-item-form" hx-post="/payruns/items/save.php" hx-target="#page-content" hx-swap="innerHTML">
            <?= csrf_field() ?><input type="hidden" name="pay_run_item" value="<?= $iid ?>">
            <?php if ($hourly || $daily): ?>
            <div class="row mb-3" id="pay-run-item-form-field-<?= $hourly ? 'hours' : 'days' ?>-row"><label for="pay-run-item-form-field-<?= $hourly ? 'hours' : 'days' ?>" class="col-lg-3 col-form-label fs-12 text-muted"><?= $hourly ? 'Hours worked' : 'Days worked' ?></label><div class="col-lg-9"><input type="number" step="0.25" min="0" name="<?= $hourly ? 'hours' : 'days' ?>" id="pay-run-item-form-field-<?= $hourly ? 'hours' : 'days' ?>" class="form-control" style="max-width:10rem" value="<?= e($item['hours'] !== null ? days_label($item['hours']) : '') ?>"><div class="fs-11 text-muted mt-1">The base line is computed from this at the rate in force.</div></div></div>
            <?php endif; ?>
            <div class="row mb-3" id="pay-run-item-form-field-note-row"><label for="pay-run-item-form-field-note" class="col-lg-3 col-form-label fs-12 text-muted">Note</label><div class="col-lg-9"><input type="text" name="note" id="pay-run-item-form-field-note" class="form-control" maxlength="200" value="<?= e($item['note'] ?? '') ?>"></div></div>
            <h6 class="fw-bold text-muted text-uppercase fs-12 mb-2">Keyed lines</h6>
            <p class="fs-11 text-muted">The accountant's figures: tax and pension as deductions, bonuses as earnings, expenses as reimbursements, the employer's contributions as employer cost. Blank rows are ignored.</p>
            <div class="table-responsive"><table class="table table-sm mb-0" id="pay-run-item-form-lines">
                <thead class="thead-light"><tr><th>Kind</th><th>Code</th><th>Description</th><th class="text-end">Amount (<?= e($cur) ?>)</th></tr></thead>
                <tbody>
                <?php for ($i = 0; $i < max(6, count($keyed) + 3); $i++): $l = $keyed[$i] ?? null; ?>
                    <tr id="pay-run-item-form-line-<?= $i ?>">
                        <td><select name="line_kind[]" class="form-select form-select-sm" id="pay-run-item-form-field-line_kind-<?= $i ?>"><option value="">—</option><?php foreach (PAY_LINE_KINDS as $k): ?><option value="<?= $k ?>" <?= ($l['kind'] ?? '') === $k ? 'selected' : '' ?>><?= e(ucfirst(str_replace('_', ' ', $k))) ?></option><?php endforeach; ?></select></td>
                        <td><input type="text" name="line_code[]" class="form-control form-control-sm" maxlength="40" value="<?= e($l['code'] ?? '') ?>" placeholder="tax" id="pay-run-item-form-field-line_code-<?= $i ?>"></td>
                        <td><input type="text" name="line_description[]" class="form-control form-control-sm" maxlength="200" value="<?= e($l['description'] ?? '') ?>" id="pay-run-item-form-field-line_description-<?= $i ?>"></td>
                        <td><input type="number" step="0.01" min="0" name="line_amount[]" class="form-control form-control-sm text-end" value="<?= e(isset($l['amount']) ? number_format((float) $l['amount'], 2, '.', '') : '') ?>" id="pay-run-item-form-field-line_amount-<?= $i ?>"></td>
                    </tr>
                <?php endfor; ?>
                </tbody>
            </table></div>
        </form>
    </div></div></div></div>
</div>
