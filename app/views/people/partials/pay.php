<?php /** The Pay tab. Data: person, staff, pay (current, changes). Changes are Payroll's or HR staff's (can_run_pay(), db/012). */ $staff = can_run_pay(); $id = (int) $person['member_id']; $cur = $pay['current']; ?>
<div id="person-pay" hx-get="/people/<?= $id ?>?tab=pay" hx-trigger="compensationChangeChanged from:body" hx-target="#page-content" hx-swap="innerHTML">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h6 class="fw-bold text-muted text-uppercase fs-12 mb-0">Current pay</h6>
        <?php if ($staff && $person['member_kind'] === 'human'): ?><a href="/people/compensation/new?person=<?= $id ?>" class="btn btn-sm btn-primary" id="person-pay-change-btn" hx-get="/people/compensation/new?person=<?= $id ?>" hx-target="#page-content" hx-push-url="/people/compensation/new?person=<?= $id ?>"><i class="feather-plus me-1"></i>Record a change</a><?php endif; ?>
    </div>
    <?php if ($cur === null): ?><p class="text-muted fs-12" id="person-pay-none">No pay recorded<?= $person['employment'] === null ? ' — and no employment record' : '' ?>.</p><?php else: ?>
    <div class="row mb-4" id="person-pay-current">
        <div class="col-md-4 mb-2"><div class="border rounded p-3"><div class="fs-12 text-muted"><?= e(pay_type_label($cur['pay_type'])) ?></div><div class="fs-20 fw-bold"><?= e(money_label($cur['pay_rate'], $cur['currency'])) ?></div><div class="fs-11 text-muted">since <?= e(format_date($cur['effective_date'])) ?></div></div></div>
        <div class="col-md-4 mb-2"><div class="border rounded p-3"><div class="fs-12 text-muted">Monthly base (estimate)</div><div class="fs-20 fw-bold"><?= $cur['monthly_base_estimate'] !== null ? e(money_label($cur['monthly_base_estimate'], $cur['currency'])) : '—' ?></div><div class="fs-11 text-muted"><?= e($cur['pay_schedule'] ? 'paid ' . $cur['pay_schedule'] : 'no pay schedule') ?><?= $cur['weekly_hours'] ? ' · ' . e(days_label($cur['weekly_hours'])) . ' h/week' : '' ?></div></div></div>
    </div>
    <?php endif; ?>
    <h6 class="fw-bold text-muted text-uppercase fs-12 mb-2">History</h6>
    <div class="table-responsive"><table class="table table-hover mb-0 fs-12" id="person-pay-history">
        <thead class="thead-light"><tr><th>Effective</th><th>Type</th><th class="text-end">Rate</th><th>Reason</th><th>Recorded by</th><?php if ($staff): ?><th></th><?php endif; ?></tr></thead>
        <tbody>
        <?php if ($pay['changes'] === []): ?><tr><td colspan="6" class="text-center text-muted py-3">No changes yet.</td></tr><?php endif; ?>
        <?php foreach ($pay['changes'] as $c): $cid = (int) $c['compensation_change_id']; $future = $c['effective_date'] > date('Y-m-d'); ?>
            <tr id="compensation-row-<?= $cid ?>">
                <td><?= e(format_date($c['effective_date'])) ?><?= $future ? ' <span class="badge bg-soft-info text-info">future</span>' : '' ?></td>
                <td><?= e(pay_type_label($c['pay_type'])) ?></td>
                <td class="text-end"><?= e(money_label($c['pay_rate'], $c['currency'])) ?></td>
                <td><?= e($c['reason'] ?? '') ?></td>
                <td><?= e($c['created_by_name'] ?? '') ?> <small class="text-muted"><?= e(format_date($c['created_at'])) ?></small></td>
                <?php if ($staff): ?><td class="text-end"><?php if ($future): ?><form method="post" action="/people/compensation/delete.php" hx-post="/people/compensation/delete.php" hx-confirm="Delete this future pay change?" hx-target="#page-content" hx-swap="innerHTML"><?= csrf_field() ?><input type="hidden" name="compensation_change" value="<?= $cid ?>"><button type="submit" class="avatar-text avatar-sm border-0" title="Delete" id="compensation-row-<?= $cid ?>-delete-btn"><i class="feather-trash-2"></i></button></form><?php endif; ?></td><?php endif; ?>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table></div>
</div>
