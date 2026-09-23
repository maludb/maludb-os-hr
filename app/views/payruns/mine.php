<?php /** Data: items (my_payslips rows with lines), run */ ?>
<div class="page-header" id="payslip-header">
    <div class="page-header-left d-flex align-items-center">
        <div class="page-header-title"><h5 class="m-b-10">My payslips</h5></div>
        <ul class="breadcrumb"><li class="breadcrumb-item"><a href="/" hx-get="/" hx-target="#page-content" hx-push-url="/">Home</a></li><li class="breadcrumb-item">My payslips</li></ul>
    </div>
</div>
<div class="main-content" id="payslip-content">
    <div class="row">
        <?php if ($items === []): ?><div class="col-12"><div class="card"><div class="card-body text-center text-muted py-5" id="payslip-empty">No payslips yet: they appear once a pay run with you on it is approved.</div></div></div><?php endif; ?>
        <?php foreach ($items as $i): $iid = (int) $i['pay_run_item_id']; $cur = $i['currency']; ?>
        <div class="col-xl-6" id="payslip-<?= $iid ?>"><div class="card stretch stretch-full">
            <div class="card-header"><h5 class="card-title"><?= e($i['run_no']) ?> · paid <?= e(format_date($i['pay_date'])) ?></h5><span class="badge bg-soft-<?= e(pay_run_status_color($i['run_status'])) ?> text-<?= e(pay_run_status_color($i['run_status'])) ?>"><?= e(ucfirst($i['run_status'])) ?></span></div>
            <div class="card-body">
                <div class="fs-12 text-muted mb-2"><?= e(format_date($i['period_start'])) ?> – <?= e(format_date($i['period_end'])) ?><?= $i['base_note'] ? ' · ' . e($i['base_note']) : '' ?></div>
                <table class="table table-sm mb-2 fs-12"><tbody>
                    <?php foreach ($i['lines'] as $l): ?><tr><td><?= e(ucfirst(str_replace('_', ' ', $l['kind']))) ?></td><td><?= e($l['description'] ?? $l['code']) ?></td><td class="text-end"><?= $l['kind'] === 'deduction' ? '−' : '' ?><?= e(money_label($l['amount'], $cur)) ?></td></tr><?php endforeach; ?>
                </tbody></table>
                <div class="d-flex justify-content-between fs-12"><span>Gross <?= e(money_label($i['gross_amount'], $cur)) ?> · deductions <?= e(money_label($i['deductions'], $cur)) ?> · reimbursements <?= e(money_label($i['reimbursements'], $cur)) ?></span><span class="fw-bold">Net <?= e(money_label($i['net_amount'], $cur)) ?></span></div>
            </div>
        </div></div>
        <?php endforeach; ?>
    </div>
</div>
