<?php /** Data: rows, year, filters, departments, staff */ ?>
<div class="page-header" id="leave-balances-header">
    <div class="page-header-left d-flex align-items-center">
        <div class="page-header-title"><h5 class="m-b-10">Leave balances <?= (int) $year ?></h5></div>
        <ul class="breadcrumb"><li class="breadcrumb-item"><a href="/leave/" hx-get="/leave/" hx-target="#page-content" hx-push-url="/leave/">Leave</a></li><li class="breadcrumb-item">Balances</li></ul>
    </div>
    <div class="page-header-right ms-auto">
        <div class="page-header-right-items">
            <div class="d-flex d-md-none"><a href="javascript:void(0)" class="page-header-right-close-toggle"><i class="feather-arrow-left me-2"></i><span>Back</span></a></div>
            <div class="d-flex align-items-center gap-2 page-header-right-items-wrapper">
                <form id="leave-balances-filters" class="d-flex align-items-center gap-2" method="get" action="/leave/balances/" hx-get="/leave/balances/" hx-target="#page-content" hx-swap="innerHTML" hx-trigger="change" hx-push-url="/leave/balances/">
                    <input type="number" name="year" id="leave-balances-filter-year" class="form-control" style="width:6.5rem" value="<?= (int) $year ?>" min="2000" max="2200">
                    <select name="department" id="leave-balances-filter-department" class="form-select"><option value="">Any department</option>
                        <?php foreach ($departments as $d): ?><option value="<?= (int) $d['department_id'] ?>" <?= (int) ($filters['department'] ?? 0) === (int) $d['department_id'] ? 'selected' : '' ?>><?= e($d['name']) ?></option><?php endforeach; ?>
                    </select>
                </form>
                <?php if ($staff): ?>
                <a href="/leave/balances/new" class="btn btn-light-brand" id="leave-balances-adjust-btn" hx-get="/leave/balances/new" hx-target="#page-content" hx-push-url="/leave/balances/new"><i class="feather-edit-2 me-2"></i><span>Adjust</span></a>
                <form method="post" action="/leave/balances/grant-year.php" hx-post="/leave/balances/grant-year.php" hx-confirm="Grant the <?= (int) $year ?> allowance to everyone employed who has none yet?" hx-target="#page-content" hx-swap="innerHTML"><?= csrf_field() ?><input type="hidden" name="year" value="<?= (int) $year ?>"><button type="submit" class="btn btn-primary" id="leave-balances-grant-btn"><i class="feather-gift me-2"></i>Grant the year</button></form>
                <?php endif; ?>
            </div>
        </div>
        <div class="d-md-none d-flex align-items-center"><a href="javascript:void(0)" class="page-header-right-open-toggle"><i class="feather-align-right fs-20"></i></a></div>
    </div>
</div>
<div class="main-content" id="leave-balances-content">
    <div class="row"><div class="col-lg-12"><div class="card stretch stretch-full" id="leave-balances-card" hx-get="/leave/balances/?year=<?= (int) $year ?>" hx-trigger="leaveBalanceChanged from:body, leaveRequestChanged from:body" hx-target="#page-content" hx-swap="innerHTML">
        <div class="card-body custom-card-action p-0"><div class="table-responsive">
            <table class="table table-hover mb-0" id="leave-balances-table">
                <thead class="thead-light"><tr><th>Person</th><th>Type</th><th class="text-end">Accrued</th><th class="text-end">Carried</th><th class="text-end">Used</th><th class="text-end">Committed</th><th class="text-end">Left</th><?php if ($staff): ?><th></th><?php endif; ?></tr></thead>
                <tbody>
                <?php if ($rows === []): ?><tr><td colspan="8" class="text-center text-muted py-4">No balances for <?= (int) $year ?><?= $staff ? ' — grant the year, or employ someone.' : '.' ?></td></tr><?php endif; ?>
                <?php foreach ($rows as $b): $left = (float) $b['remaining_days'] - (float) $b['committed_days']; ?>
                    <tr id="leave-balance-row-<?= (int) $b['leave_balance_id'] ?>">
                        <td><a href="/people/<?= (int) $b['member_id'] ?>?tab=leave" hx-get="/people/<?= (int) $b['member_id'] ?>?tab=leave" hx-target="#page-content" hx-push-url="/people/<?= (int) $b['member_id'] ?>?tab=leave"><?= e($b['display_name']) ?></a></td>
                        <td><?= e($b['leave_type']) ?></td>
                        <td class="text-end"><?= e(days_label($b['accrued_days'])) ?></td><td class="text-end"><?= e(days_label($b['carried_days'])) ?></td>
                        <td class="text-end"><?= e(days_label($b['used_days'])) ?></td><td class="text-end"><?= e(days_label($b['committed_days'])) ?></td>
                        <td class="text-end fw-bold <?= $left < 0 ? 'text-danger' : '' ?>"><?= e(days_label($b['remaining_days'])) ?></td>
                        <?php if ($staff): ?><td class="text-end"><a href="/leave/balances/new?person=<?= (int) $b['member_id'] ?>&amp;leave_type=<?= (int) $b['leave_type_id'] ?>&amp;year=<?= (int) $b['year'] ?>" class="avatar-text avatar-md" title="Adjust" hx-get="/leave/balances/new?person=<?= (int) $b['member_id'] ?>&amp;leave_type=<?= (int) $b['leave_type_id'] ?>&amp;year=<?= (int) $b['year'] ?>" hx-target="#page-content" hx-push-url="/leave/balances/new?person=<?= (int) $b['member_id'] ?>&amp;leave_type=<?= (int) $b['leave_type_id'] ?>&amp;year=<?= (int) $b['year'] ?>"><i class="feather-edit"></i></a></td><?php endif; ?>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div></div>
    </div></div></div>
</div>
