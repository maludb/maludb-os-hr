<?php /** Data: rows, total, page, pages, filters */ ?>
<div class="page-header" id="pay-runs-header">
    <div class="page-header-left d-flex align-items-center">
        <div class="page-header-title"><h5 class="m-b-10">Pay runs</h5></div>
        <ul class="breadcrumb"><li class="breadcrumb-item"><a href="/" hx-get="/" hx-target="#page-content" hx-push-url="/">Home</a></li><li class="breadcrumb-item">Pay runs</li></ul>
    </div>
    <div class="page-header-right ms-auto">
        <div class="page-header-right-items">
            <div class="d-flex d-md-none"><a href="javascript:void(0)" class="page-header-right-close-toggle"><i class="feather-arrow-left me-2"></i><span>Back</span></a></div>
            <div class="d-flex align-items-center gap-2 page-header-right-items-wrapper">
                <form id="pay-runs-filters" class="d-flex align-items-center gap-2" method="get" action="/payruns/" hx-get="/payruns/" hx-target="#page-content" hx-swap="innerHTML" hx-trigger="change" hx-push-url="/payruns/">
                    <select name="status" id="pay-runs-filter-status" class="form-select"><?php foreach (['' => 'Any status', 'draft' => 'Draft', 'approved' => 'Approved', 'paid' => 'Paid', 'void' => 'Void'] as $v => $l): ?><option value="<?= e($v) ?>" <?= $filters['status'] === $v ? 'selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?></select>
                    <input type="month" name="period" id="pay-runs-filter-period" class="form-control" value="<?= e($filters['period']) ?>">
                </form>
                <a href="/payruns/new" id="pay-runs-add-btn" class="btn btn-primary" hx-get="/payruns/new" hx-target="#page-content" hx-push-url="/payruns/new"><i class="feather-plus me-2"></i><span>New pay run</span></a>
            </div>
        </div>
        <div class="d-md-none d-flex align-items-center"><a href="javascript:void(0)" class="page-header-right-open-toggle"><i class="feather-align-right fs-20"></i></a></div>
    </div>
</div>
<div class="main-content" id="pay-runs-content">
    <div class="row"><div class="col-lg-12"><div class="card stretch stretch-full" id="pay-runs-card" hx-get="/payruns/" hx-trigger="payRunChanged from:body" hx-target="#page-content" hx-swap="innerHTML">
        <div class="card-header"><h5 class="card-title"><?= (int) $total ?> run<?= $total === 1 ? '' : 's' ?></h5></div>
        <div class="card-body custom-card-action p-0"><div class="table-responsive">
            <table class="table table-hover mb-0" id="pay-runs-table">
                <thead class="thead-light"><tr><th>Run</th><th>Period</th><th>Pay date</th><th>Status</th><th class="text-end">People</th><th class="text-end">Gross</th><th class="text-end">Net</th></tr></thead>
                <tbody>
                <?php if ($rows === []): ?><tr><td colspan="7" class="text-center text-muted py-4">No pay runs yet.</td></tr><?php endif; ?>
                <?php foreach ($rows as $r): $id = (int) $r['pay_run_id']; $c = pay_run_status_color($r['status']); $u = '/payruns/' . $id; ?>
                    <tr id="pay-run-row-<?= $id ?>">
                        <td><a href="<?= e($u) ?>" hx-get="<?= e($u) ?>" hx-target="#page-content" hx-push-url="<?= e($u) ?>"><span class="wd-10 ht-10 bg-<?= e($c) ?> me-2 d-inline-block rounded-circle"></span><?= e($r['run_no']) ?></a></td>
                        <td><?= e(format_date($r['period_start'])) ?> – <?= e(format_date($r['period_end'])) ?></td>
                        <td><?= e(format_date($r['pay_date'])) ?></td>
                        <td><span class="badge bg-soft-<?= e($c) ?> text-<?= e($c) ?>"><?= e(ucfirst($r['status'])) ?></span></td>
                        <td class="text-end"><?= (int) $r['people'] ?></td>
                        <td class="text-end"><?= e(money_label($r['gross_total'], $r['currency'])) ?></td>
                        <td class="text-end fw-bold"><?= e(money_label($r['net_total'], $r['currency'])) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div></div>
    </div></div></div>
</div>
