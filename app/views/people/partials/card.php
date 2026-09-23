<?php /** One person card. Data: r (a find_people row) */
$id = (int) $r['member_id'];
$url = '/people/' . $id;
$color = $r['member_kind'] === 'agent' ? 'info' : employment_status_color($r['employment_status'] ?? null);
$label = $r['member_kind'] === 'agent' ? 'Agent' : employment_status_label($r['employment_status'] ?? null);
?>
<div class="col-xxl-3 col-lg-4 col-md-6" id="person-card-<?= $id ?>">
    <div class="card stretch stretch-full">
        <div class="card-body">
            <a href="<?= e($url) ?>" class="d-flex align-items-center gap-3 text-reset" hx-get="<?= e($url) ?>" hx-target="#page-content" hx-swap="innerHTML" hx-push-url="<?= e($url) ?>" id="person-card-<?= $id ?>-link">
                <div class="avatar-text avatar-lg rounded"><?= e(initials((string) $r['display_name'])) ?></div>
                <div class="flex-grow-1" style="min-width:0">
                    <div class="fw-bold text-truncate-1-line" id="person-card-<?= $id ?>-name"><?= e($r['display_name']) ?>
                        <?php if (!empty($r['is_external'])): ?><span class="badge bg-soft-secondary text-secondary ms-1">external</span><?php endif; ?>
                        <?php if (($r['member_status'] ?? 'active') !== 'active'): ?><span class="badge bg-soft-danger text-danger ms-1">inactive</span><?php endif; ?>
                    </div>
                    <div class="fs-12 text-muted text-truncate-1-line"><?= e(implode(' · ', array_filter([$r['job_title'] ?? '', $r['primary_department'] ?? '']))) ?: '&nbsp;' ?></div>
                </div>
            </a>
            <div class="mt-3 d-flex justify-content-between align-items-center">
                <span class="badge bg-soft-<?= e($color) ?> text-<?= e($color) ?>" id="person-card-<?= $id ?>-status"><span class="wd-10 ht-10 bg-<?= e($color) ?> me-1 d-inline-block rounded-circle"></span><?= e($label) ?></span>
                <small class="text-muted" id="person-card-<?= $id ?>-no"><?= e($r['employee_no'] ?? '') ?><?= !empty($r['start_date']) ? ' · from ' . e(format_date($r['start_date'])) : '' ?></small>
            </div>
        </div>
    </div>
</div>
