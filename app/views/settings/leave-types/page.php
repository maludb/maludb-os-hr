<?php /** Data: rows */ ?>
<div class="page-header" id="leave-types-header">
    <div class="page-header-left d-flex align-items-center">
        <div class="page-header-title"><h5 class="m-b-10">Leave types</h5></div>
        <ul class="breadcrumb"><li class="breadcrumb-item"><a href="/settings/" hx-get="/settings/" hx-target="#page-content" hx-push-url="/settings/">Settings</a></li><li class="breadcrumb-item">Leave types</li></ul>
    </div>
    <div class="page-header-right ms-auto">
        <div class="page-header-right-items">
            <div class="d-flex d-md-none"><a href="javascript:void(0)" class="page-header-right-close-toggle"><i class="feather-arrow-left me-2"></i><span>Back</span></a></div>
            <div class="d-flex align-items-center gap-2 page-header-right-items-wrapper">
                <a href="/settings/leave-types/new" id="leave-types-add-btn" class="btn btn-primary" hx-get="/settings/leave-types/new" hx-target="#page-content" hx-push-url="/settings/leave-types/new"><i class="feather-plus me-2"></i><span>Add a leave type</span></a>
            </div>
        </div>
        <div class="d-md-none d-flex align-items-center"><a href="javascript:void(0)" class="page-header-right-open-toggle"><i class="feather-align-right fs-20"></i></a></div>
    </div>
</div>
<div class="main-content" id="leave-types-content">
    <div class="row"><div class="col-lg-12"><div class="card stretch stretch-full" id="leave-types-card" hx-get="/settings/leave-types/" hx-trigger="leaveTypeChanged from:body" hx-target="#page-content" hx-swap="innerHTML">
        <div class="card-body custom-card-action p-0"><div class="table-responsive">
            <table class="table table-hover mb-0" id="leave-types-table">
                <thead class="thead-light"><tr><th>Name</th><th>Paid</th><th class="text-end">Days a year</th><th class="text-end">Carry-over</th><th>Approval</th><th class="text-end">Actions</th></tr></thead>
                <tbody>
                <?php foreach ($rows as $t): $id = (int) $t['leave_type_id']; $u = '/settings/leave-types/' . $id . '/edit'; ?>
                    <tr id="leave-type-row-<?= $id ?>">
                        <td><span class="wd-10 ht-10 bg-<?= $t['archived_at'] ? 'secondary' : 'success' ?> me-2 d-inline-block rounded-circle"></span><?= e($t['name']) ?><?= $t['archived_at'] ? ' <span class="badge bg-soft-secondary text-secondary">archived</span>' : '' ?></td>
                        <td><?= $t['is_paid'] ? 'Paid' : 'Unpaid' ?></td>
                        <td class="text-end"><?= $t['accrual_days_per_year'] === null ? '<span class="text-muted">unlimited</span>' : e(days_label($t['accrual_days_per_year'])) ?></td>
                        <td class="text-end"><?= e(days_label($t['carry_over_days'])) ?></td>
                        <td><?= $t['requires_approval'] ? 'Needs approval' : '<span class="text-muted">Approved as made</span>' ?></td>
                        <td class="text-end"><a href="<?= e($u) ?>" class="avatar-text avatar-md" title="Edit" id="leave-type-row-<?= $id ?>-edit-btn" hx-get="<?= e($u) ?>" hx-target="#page-content" hx-push-url="<?= e($u) ?>"><i class="feather-edit"></i></a></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div></div>
    </div></div></div>
</div>
