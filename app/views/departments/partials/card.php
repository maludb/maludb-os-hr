<?php /** Data: r (an mcp_departments row) */ $id = (int) $r['department_id']; $url = '/departments/' . $id; $archived = $r['archived_at'] !== null; ?>
<div class="col-xxl-3 col-lg-4 col-md-6" id="department-card-<?= $id ?>">
    <div class="card stretch stretch-full">
        <div class="card-body">
            <a href="<?= e($url) ?>" class="d-flex align-items-center gap-3 text-reset" hx-get="<?= e($url) ?>" hx-target="#page-content" hx-swap="innerHTML" hx-push-url="<?= e($url) ?>" id="department-card-<?= $id ?>-link">
                <div class="avatar-text avatar-lg rounded"><i class="feather-<?= $r['is_system'] ? 'star' : 'grid' ?>"></i></div>
                <div class="flex-grow-1" style="min-width:0">
                    <div class="fw-bold text-truncate-1-line" id="department-card-<?= $id ?>-name"><?= e($r['name']) ?><?php if ($archived): ?> <span class="badge bg-soft-secondary text-secondary ms-1">archived</span><?php endif; ?></div>
                    <div class="fs-12 text-muted text-truncate-1-line"><?= e($r['manager_name'] ? 'Managed by ' . $r['manager_name'] : 'No manager') ?><?= $r['parent_name'] ? ' · under ' . e($r['parent_name']) : '' ?></div>
                </div>
            </a>
            <div class="mt-3 d-flex justify-content-between align-items-center fs-12 text-muted">
                <span id="department-card-<?= $id ?>-counts"><i class="feather-users me-1"></i><?= (int) $r['people'] ?> people<?= (int) $r['agents'] > 0 ? ' · <i class="feather-cpu me-1"></i>' . (int) $r['agents'] . ' agents' : '' ?></span>
                <?php if (!empty($r['i_administer'])): ?><span class="badge bg-soft-warning text-warning">you administer</span><?php endif; ?>
            </div>
        </div>
    </div>
</div>
