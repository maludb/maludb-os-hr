<?php /** The person's Leave tab. Data: person, leave (balances, requests), me, canRequestFor */ $id = (int) $person['member_id']; ?>
<div id="person-leave" hx-get="/people/<?= $id ?>?tab=leave" hx-trigger="leaveRequestChanged from:body, leaveBalanceChanged from:body" hx-target="#page-content" hx-swap="innerHTML">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h6 class="fw-bold text-muted text-uppercase fs-12 mb-0">Balances <?= (int) date('Y') ?></h6>
        <?php if ($canRequestFor): ?><a href="/leave/new?person=<?= $id ?>" class="btn btn-sm btn-primary" id="person-leave-request-btn" hx-get="/leave/new?person=<?= $id ?>" hx-target="#page-content" hx-push-url="/leave/new?person=<?= $id ?>"><i class="feather-plus me-1"></i>Request leave</a><?php endif; ?>
    </div>
    <div class="row mb-4" id="person-leave-balances">
        <?php if ($leave['balances'] === []): ?><div class="col-12 text-muted fs-12">No balances this year.</div><?php endif; ?>
        <?php foreach ($leave['balances'] as $b): ?>
            <div class="col-md-4 mb-2"><div class="border rounded p-3" id="person-leave-balance-<?= (int) $b['leave_balance_id'] ?>">
                <div class="fs-12 text-muted"><?= e($b['leave_type']) ?></div>
                <div class="fs-20 fw-bold"><?= e(days_label($b['remaining_days'])) ?> <small class="fs-11 fw-normal text-muted">left</small></div>
                <div class="fs-11 text-muted"><?= e(days_label($b['used_days'])) ?> used · <?= e(days_label($b['committed_days'])) ?> committed</div>
            </div></div>
        <?php endforeach; ?>
    </div>
    <h6 class="fw-bold text-muted text-uppercase fs-12 mb-2">Requests</h6>
    <div class="table-responsive"><table class="table table-hover mb-0 fs-12" id="person-leave-requests">
        <thead class="thead-light"><tr><th>Type</th><th>Dates</th><th class="text-end">Days</th><th>Status</th></tr></thead>
        <tbody>
        <?php if ($leave['requests'] === []): ?><tr><td colspan="4" class="text-center text-muted py-3">No requests.</td></tr><?php endif; ?>
        <?php foreach ($leave['requests'] as $r): $c = leave_status_color($r['status']); $u = '/leave/' . (int) $r['leave_request_id']; ?>
            <tr id="person-leave-request-<?= (int) $r['leave_request_id'] ?>">
                <td><a href="<?= e($u) ?>" hx-get="<?= e($u) ?>" hx-target="#page-content" hx-push-url="<?= e($u) ?>"><?= e($r['leave_type']) ?></a></td>
                <td><?= e(format_date($r['start_date'])) ?><?= $r['end_date'] !== $r['start_date'] ? ' – ' . e(format_date($r['end_date'])) : '' ?></td>
                <td class="text-end"><?= e(days_label($r['days'])) ?></td>
                <td><span class="badge bg-soft-<?= e($c) ?> text-<?= e($c) ?>"><?= e(ucfirst($r['status'])) ?></span><?= $r['over_balance'] ? ' <span class="badge bg-soft-danger text-danger">over</span>' : '' ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table></div>
</div>
