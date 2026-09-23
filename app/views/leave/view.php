<?php /** Data: r, me, canDecide, canCancel, staff, tz */ $id = (int) $r['leave_request_id']; $c = leave_status_color($r['status']); $b = $r['balance']; ?>
<div class="page-header" id="leave-view-header">
    <div class="page-header-left d-flex align-items-center">
        <div class="page-header-title"><h5 class="m-b-10"><?= e($r['leave_type']) ?> · <?= e($r['display_name']) ?></h5></div>
        <ul class="breadcrumb"><li class="breadcrumb-item"><a href="/leave/" hx-get="/leave/" hx-target="#page-content" hx-push-url="/leave/">Leave</a></li><li class="breadcrumb-item">Request #<?= $id ?></li></ul>
    </div>
    <div class="page-header-right ms-auto">
        <div class="page-header-right-items">
            <div class="d-flex d-md-none"><a href="javascript:void(0)" class="page-header-right-close-toggle"><i class="feather-arrow-left me-2"></i><span>Back</span></a></div>
            <div class="d-flex align-items-center gap-2 page-header-right-items-wrapper">
                <?php if ($canCancel): ?>
                <form method="post" action="/leave/cancel.php" hx-post="/leave/cancel.php" hx-confirm="Cancel this leave request?" hx-target="#page-content" hx-swap="innerHTML"><?= csrf_field() ?><input type="hidden" name="leave_request" value="<?= $id ?>"><button type="submit" class="btn btn-outline-danger" id="leave-view-cancel-btn"><i class="feather-x-circle me-2"></i>Cancel the request</button></form>
                <?php endif; ?>
            </div>
        </div>
        <div class="d-md-none d-flex align-items-center"><a href="javascript:void(0)" class="page-header-right-open-toggle"><i class="feather-align-right fs-20"></i></a></div>
    </div>
</div>
<div class="main-content" id="leave-view-content">
    <div class="row">
        <div class="col-xl-7">
            <div class="card stretch stretch-full" id="leave-view-request"><div class="card-body">
                <div class="d-flex align-items-center gap-3 mb-3">
                    <span class="badge bg-soft-<?= e($c) ?> text-<?= e($c) ?> fs-12" id="leave-view-status"><?= e(ucfirst($r['status'])) ?></span>
                    <?php if ($r['over_balance']): ?><span class="badge bg-soft-danger text-danger">beyond the balance</span><?php endif; ?>
                    <?php if ($r['has_overlap']): ?><span class="badge bg-soft-warning text-warning">overlaps another request</span><?php endif; ?>
                </div>
                <table class="table table-borderless mb-0" id="leave-view-table"><tbody>
                    <tr><th class="text-muted fw-medium fs-12" style="width:35%">Person</th><td><a href="/people/<?= (int) $r['member_id'] ?>" hx-get="/people/<?= (int) $r['member_id'] ?>" hx-target="#page-content" hx-push-url="/people/<?= (int) $r['member_id'] ?>"><?= e($r['display_name']) ?></a><?= $r['primary_department'] ? ' <small class="text-muted">· ' . e($r['primary_department']) . '</small>' : '' ?></td></tr>
                    <tr><th class="text-muted fw-medium fs-12">Type</th><td><?= e($r['leave_type']) ?><?= $r['requires_approval'] ? '' : ' <small class="text-muted">(no approval needed)</small>' ?></td></tr>
                    <tr><th class="text-muted fw-medium fs-12">Dates</th><td><?= e(format_date($r['start_date'])) ?><?= $r['end_date'] !== $r['start_date'] ? ' – ' . e(format_date($r['end_date'])) : '' ?><?= $r['half_day'] ? ' <small class="text-muted">half day</small>' : '' ?></td></tr>
                    <tr><th class="text-muted fw-medium fs-12">Days</th><td><?= e(days_label($r['days'])) ?></td></tr>
                    <tr><th class="text-muted fw-medium fs-12">Reason</th><td><?= e($r['reason'] ?? '—') ?></td></tr>
                    <tr><th class="text-muted fw-medium fs-12">Requested</th><td><?= e(format_ts($r['created_at'], $tz)) ?></td></tr>
                    <?php if ($r['decided_at']): ?><tr><th class="text-muted fw-medium fs-12">Decided</th><td><?= e($r['decided_by_name'] ?? '') ?>, <?= e(format_ts($r['decided_at'], $tz)) ?><?= $r['decision_note'] ? '<br><small class="text-muted">' . e($r['decision_note']) . '</small>' : '' ?></td></tr><?php endif; ?>
                </tbody></table>
            </div></div>
        </div>
        <div class="col-xl-5">
            <div class="card stretch stretch-full" id="leave-view-balance"><div class="card-body">
                <h6 class="fw-bold text-muted text-uppercase fs-12 mb-3">Balance <?= e(substr((string) $r['start_date'], 0, 4)) ?></h6>
                <?php if ($b === null): ?><p class="text-muted fs-12 mb-0" id="leave-view-balance-none">This type is not limited.</p><?php else: ?>
                <div class="row text-center mb-2">
                    <div class="col-4"><div class="fs-20 fw-bold" id="leave-view-balance-remaining"><?= e(days_label($b['remaining_days'])) ?></div><div class="fs-11 text-muted">left</div></div>
                    <div class="col-4"><div class="fs-20 fw-bold"><?= e(days_label($b['committed_days'])) ?></div><div class="fs-11 text-muted">committed</div></div>
                    <div class="col-4"><div class="fs-20 fw-bold"><?= e(days_label($b['used_days'])) ?></div><div class="fs-11 text-muted">used</div></div>
                </div>
                <p class="fs-12 text-muted mb-0"><?= e(days_label($b['accrued_days'])) ?> accrued + <?= e(days_label($b['carried_days'])) ?> carried over.</p>
                <?php endif; ?>
                <?php if ($canDecide): ?>
                <hr>
                <form method="post" action="/leave/decide.php" id="leave-decision-form" hx-post="/leave/decide.php" hx-target="#page-content" hx-swap="innerHTML">
                    <?= csrf_field() ?><input type="hidden" name="leave_request" value="<?= $id ?>">
                    <div class="mb-2"><label class="form-label fs-12 text-muted" for="leave-decision-field-note">Note</label><input type="text" name="note" id="leave-decision-field-note" class="form-control" maxlength="300"></div>
                    <?php if ($staff && $r['over_balance']): ?><div class="form-check mb-2"><input type="checkbox" name="override" value="1" id="leave-decision-field-override" class="form-check-input"><label class="form-check-label fs-12" for="leave-decision-field-override">Approve beyond the balance (HR override)</label></div><?php endif; ?>
                    <div class="d-flex gap-2">
                        <button type="submit" name="decision" value="approve" class="btn btn-success flex-fill" id="leave-decision-approve-btn"><i class="feather-check me-1"></i>Approve</button>
                        <button type="submit" name="decision" value="reject" class="btn btn-outline-danger flex-fill" id="leave-decision-reject-btn" hx-confirm="Reject this request?"><i class="feather-x me-1"></i>Reject</button>
                    </div>
                </form>
                <?php endif; ?>
            </div></div>
        </div>
    </div>
</div>
