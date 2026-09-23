<?php /** Data: rows, total, page, pages, filters, me */ ?>
<div id="leave-requests-results" hx-get="/leave/" hx-trigger="leaveRequestChanged from:body" hx-include="#leave-requests-filters" hx-swap="outerHTML">
    <div class="row"><div class="col-lg-12">
        <div class="card stretch stretch-full" id="leave-requests-card">
            <div class="card-header"><h5 class="card-title"><?= (int) $total ?> request<?= $total === 1 ? '' : 's' ?></h5></div>
            <div class="card-body custom-card-action p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0" id="leave-requests-table">
                        <thead class="thead-light"><tr>
                            <th id="leave-requests-col-person">Person</th><th id="leave-requests-col-type">Type</th><th id="leave-requests-col-dates">Dates</th>
                            <th id="leave-requests-col-days" class="text-end">Days</th><th id="leave-requests-col-status">Status</th><th id="leave-requests-col-decided">Decided by</th>
                        </tr></thead>
                        <tbody id="leave-requests-tbody">
                        <?php if ($rows === []): ?><tr><td colspan="6" class="text-center text-muted py-4">No requests match.</td></tr><?php endif; ?>
                        <?php foreach ($rows as $r): $id = (int) $r['leave_request_id']; $c = leave_status_color($r['status']); $u = '/leave/' . $id; ?>
                            <tr id="leave-request-row-<?= $id ?>">
                                <td><a href="<?= e($u) ?>" hx-get="<?= e($u) ?>" hx-target="#page-content" hx-push-url="<?= e($u) ?>"><span class="wd-10 ht-10 bg-<?= e($c) ?> me-2 d-inline-block rounded-circle"></span><?= e($r['display_name']) ?></a><?= (int) $r['member_id'] === $me ? ' <small class="text-muted">(you)</small>' : '' ?><?= $r['primary_department'] ? '<br><small class="text-muted">' . e($r['primary_department']) . '</small>' : '' ?></td>
                                <td><?= e($r['leave_type']) ?></td>
                                <td><?= e(format_date($r['start_date'])) ?><?= $r['end_date'] !== $r['start_date'] ? ' – ' . e(format_date($r['end_date'])) : ($r['half_day'] ? ' <small class="text-muted">half day</small>' : '') ?></td>
                                <td class="text-end"><?= e(days_label($r['days'])) ?></td>
                                <td><span class="badge bg-soft-<?= e($c) ?> text-<?= e($c) ?>"><?= e(ucfirst($r['status'])) ?></span>
                                    <?php if ($r['over_balance']): ?><span class="badge bg-soft-danger text-danger" title="beyond the balance">over</span><?php endif; ?>
                                    <?php if ($r['has_overlap']): ?><span class="badge bg-soft-warning text-warning" title="overlaps another request">overlap</span><?php endif; ?>
                                    <?php if ($r['awaiting_me']): ?><span class="badge bg-soft-primary text-primary">yours to decide</span><?php endif; ?></td>
                                <td><?= e($r['decided_by_name'] ?? '') ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php if ($pages > 1): ?>
                <nav class="p-3" id="leave-requests-pagination"><ul class="pagination mb-0">
                    <?php for ($p = 1; $p <= $pages; $p++): $u = '/leave/?' . http_build_query(array_filter(['status' => $filters['status'], 'department' => $filters['department'], 'awaiting_me' => $filters['awaiting_me'] ? 1 : null, 'mine' => $filters['mine'] ? 1 : null, 'flagged' => $filters['flagged'] ? 1 : null, 'page' => $p > 1 ? $p : null])); ?>
                        <li class="page-item <?= $p === $page ? 'active' : '' ?>"><a class="page-link" href="<?= e($u) ?>" hx-get="<?= e($u) ?>" hx-target="#leave-requests-results" hx-swap="outerHTML" hx-push-url="<?= e($u) ?>"><?= $p ?></a></li>
                    <?php endfor; ?>
                </ul></nav>
                <?php endif; ?>
            </div>
        </div>
    </div></div>
</div>
