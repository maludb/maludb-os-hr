<?php /** Data: run, addable, tz */ $id = (int) $run['pay_run_id']; $c = pay_run_status_color($run['status']); $cur = $run['currency']; $draft = $run['status'] === 'draft'; ?>
<div class="page-header" id="pay-run-view-header">
    <div class="page-header-left d-flex align-items-center">
        <div class="page-header-title"><h5 class="m-b-10"><?= e($run['run_no']) ?></h5></div>
        <ul class="breadcrumb"><li class="breadcrumb-item"><a href="/payruns/" hx-get="/payruns/" hx-target="#page-content" hx-push-url="/payruns/">Pay runs</a></li><li class="breadcrumb-item"><?= e($run['run_no']) ?></li></ul>
    </div>
    <div class="page-header-right ms-auto">
        <div class="page-header-right-items">
            <div class="d-flex d-md-none"><a href="javascript:void(0)" class="page-header-right-close-toggle"><i class="feather-arrow-left me-2"></i><span>Back</span></a></div>
            <div class="d-flex align-items-center gap-2 page-header-right-items-wrapper flex-wrap">
                <?php $btn = static fn (string $file, string $label, string $icon, string $cls, string $confirm, string $extra = ''): string => '<form method="post" action="/payruns/' . $file . '.php" hx-post="/payruns/' . $file . '.php" hx-confirm="' . e($confirm) . '" hx-target="#page-content" hx-swap="innerHTML" class="d-flex align-items-center gap-2">' . csrf_field() . '<input type="hidden" name="pay_run" value="' . $id . '">' . $extra . '<button type="submit" class="btn ' . $cls . '" id="pay-run-view-' . $file . '-btn"><i class="feather-' . $icon . ' me-2"></i>' . e($label) . '</button></form>'; ?>
                <?php if ($draft): ?>
                    <?= $btn('delete', 'Delete draft', 'trash-2', 'btn-outline-danger', 'Delete this draft pay run?') ?>
                    <?= $btn('approve', 'Approve', 'check', 'btn-primary', 'Approve ' . $run['run_no'] . '? It is frozen once approved.') ?>
                <?php elseif ($run['status'] === 'approved'): ?>
                    <?= $btn('unapprove', 'Back to draft', 'rotate-ccw', 'btn-light-brand', 'Return ' . $run['run_no'] . ' to draft?') ?>
                    <?= $btn('void', 'Void', 'slash', 'btn-outline-danger', 'Void ' . $run['run_no'] . '? This cannot be undone.', '<input type="text" name="reason" class="form-control form-control-sm" placeholder="reason" required style="max-width:10rem">') ?>
                    <?= $btn('paid', 'Mark as paid', 'dollar-sign', 'btn-primary', 'Record that ' . $run['run_no'] . ' was paid? No money moves here.', '<input type="date" name="paid_at" class="form-control form-control-sm" value="' . e(date('Y-m-d')) . '" style="max-width:10rem">') ?>
                <?php endif; ?>
            </div>
        </div>
        <div class="d-md-none d-flex align-items-center"><a href="javascript:void(0)" class="page-header-right-open-toggle"><i class="feather-align-right fs-20"></i></a></div>
    </div>
</div>
<div class="main-content" id="pay-run-view-content">
    <div class="row">
        <div class="col-xl-4"><div class="card stretch stretch-full" id="pay-run-view-summary"><div class="card-body">
            <span class="badge bg-soft-<?= e($c) ?> text-<?= e($c) ?> fs-12 mb-3" id="pay-run-view-status"><?= e(ucfirst($run['status'])) ?></span>
            <table class="table table-borderless table-sm mb-0 fs-12"><tbody>
                <tr><th class="text-muted fw-medium">Period</th><td><?= e(format_date($run['period_start'])) ?> – <?= e(format_date($run['period_end'])) ?></td></tr>
                <tr><th class="text-muted fw-medium">Pay date</th><td><?= e(format_date($run['pay_date'])) ?></td></tr>
                <tr><th class="text-muted fw-medium">People</th><td><?= (int) $run['people'] ?></td></tr>
                <tr><th class="text-muted fw-medium">Gross</th><td><?= e(money_label($run['gross_total'], $cur)) ?></td></tr>
                <tr><th class="text-muted fw-medium">Deductions</th><td><?= e(money_label($run['deductions_total'], $cur)) ?></td></tr>
                <tr><th class="text-muted fw-medium">Reimbursements</th><td><?= e(money_label($run['reimbursements_total'], $cur)) ?></td></tr>
                <tr><th class="text-muted fw-medium">Net</th><td class="fw-bold" id="pay-run-view-net"><?= e(money_label($run['net_total'], $cur)) ?></td></tr>
                <tr><th class="text-muted fw-medium">Employer cost</th><td><?= e(money_label($run['employer_cost_total'], $cur)) ?></td></tr>
                <?php if ($run['approved_at']): ?><tr><th class="text-muted fw-medium">Approved</th><td><?= e($run['approved_by_name'] ?? '') ?>, <?= e(format_ts($run['approved_at'], $tz)) ?></td></tr><?php endif; ?>
                <?php if ($run['paid_at']): ?><tr><th class="text-muted fw-medium">Paid</th><td><?= e(format_ts($run['paid_at'], $tz, 'M j, Y')) ?></td></tr><?php endif; ?>
                <?php if ($run['voided_at']): ?><tr><th class="text-muted fw-medium">Void</th><td><?= e(format_ts($run['voided_at'], $tz)) ?><?= $run['void_reason'] ? ' · ' . e($run['void_reason']) : '' ?></td></tr><?php endif; ?>
                <?php if ($run['note']): ?><tr><th class="text-muted fw-medium">Note</th><td><?= e($run['note']) ?></td></tr><?php endif; ?>
            </tbody></table>
        </div></div></div>
        <div class="col-xl-8"><div class="card stretch stretch-full" id="pay-run-view-items-card" hx-get="/payruns/<?= $id ?>" hx-trigger="payRunChanged from:body" hx-target="#page-content" hx-swap="innerHTML">
            <div class="card-header"><h5 class="card-title">People</h5></div>
            <div class="card-body custom-card-action p-0"><div class="table-responsive"><table class="table table-hover mb-0 fs-12" id="pay-run-view-items">
                <thead class="thead-light"><tr><th>Person</th><th>Base</th><th class="text-end">Gross</th><th class="text-end">Deductions</th><th class="text-end">Net</th><th class="text-end"></th></tr></thead>
                <tbody>
                <?php if ($run['items'] === []): ?><tr><td colspan="6" class="text-center text-muted py-4">Nobody on this run.</td></tr><?php endif; ?>
                <?php foreach ($run['items'] as $i): $iid = (int) $i['pay_run_item_id']; ?>
                    <tr id="pay-run-item-row-<?= $iid ?>">
                        <td><a href="/people/<?= (int) $i['member_id'] ?>" hx-get="/people/<?= (int) $i['member_id'] ?>" hx-target="#page-content" hx-push-url="/people/<?= (int) $i['member_id'] ?>"><?= e($i['display_name']) ?></a><?= $i['note'] ? '<br><small class="text-muted">' . e($i['note']) . '</small>' : '' ?></td>
                        <td><?php if ($i['base_note']): ?><span class="badge bg-soft-warning text-warning"><?= e($i['base_note']) ?></span><?php else: foreach ($i['lines'] as $l) { if ($l['is_base']) { echo e($l['description'] ?? 'base'); break; } } endif; ?>
                            <?php $keyed = count(array_filter($i['lines'], static fn (array $l): bool => !$l['is_base'])); if ($keyed > 0): ?><br><small class="text-muted"><?= $keyed ?> keyed line<?= $keyed === 1 ? '' : 's' ?></small><?php endif; ?></td>
                        <td class="text-end"><?= e(money_label($i['gross_amount'], $cur)) ?></td>
                        <td class="text-end"><?= e(money_label($i['deductions'], $cur)) ?></td>
                        <td class="text-end fw-bold"><?= e(money_label($i['net_amount'], $cur)) ?></td>
                        <td class="text-end"><?php if ($draft): ?><div class="hstack gap-1 justify-content-end">
                            <a href="/payruns/items/<?= $iid ?>/edit" class="avatar-text avatar-md" title="Key hours and lines" id="pay-run-item-row-<?= $iid ?>-edit-btn" hx-get="/payruns/items/<?= $iid ?>/edit" hx-target="#page-content" hx-push-url="/payruns/items/<?= $iid ?>/edit"><i class="feather-edit"></i></a>
                            <form method="post" action="/payruns/items/remove.php" hx-post="/payruns/items/remove.php" hx-confirm="Take <?= e($i['display_name']) ?> off this run?" hx-target="#page-content" hx-swap="innerHTML"><?= csrf_field() ?><input type="hidden" name="pay_run_item" value="<?= $iid ?>"><button type="submit" class="avatar-text avatar-md border-0" title="Remove" id="pay-run-item-row-<?= $iid ?>-remove-btn"><i class="feather-x"></i></button></form>
                        </div><?php endif; ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table></div></div>
            <?php if ($draft && $addable !== []): ?>
            <div class="card-footer">
                <form method="post" action="/payruns/items/add.php" class="d-flex align-items-center gap-2" id="pay-run-add-form" hx-post="/payruns/items/add.php" hx-target="#page-content" hx-swap="innerHTML"><?= csrf_field() ?><input type="hidden" name="pay_run" value="<?= $id ?>">
                    <select name="person" id="pay-run-add-field-person" class="form-select form-select-sm" required><option value="">Add a person…</option><?php foreach ($addable as $p): ?><option value="<?= (int) $p['member_id'] ?>"><?= e($p['display_name']) ?><?= $p['base_note'] ? ' — ' . e($p['base_note']) : '' ?></option><?php endforeach; ?></select>
                    <button type="submit" class="btn btn-sm btn-light-brand" id="pay-run-add-btn"><i class="feather-user-plus"></i></button>
                </form>
            </div>
            <?php endif; ?>
        </div></div>
    </div>
</div>
