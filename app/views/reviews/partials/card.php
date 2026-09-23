<?php /** Data: r (an mcp_reviews row), me */ $id = (int) $r['review_id']; $u = '/reviews/' . $id; $c = review_status_color($r['status'], $r['due_date']); ?>
<div class="col-xxl-3 col-lg-4 col-md-6" id="review-card-<?= $id ?>">
    <div class="card stretch stretch-full"><div class="card-body">
        <a href="<?= e($u) ?>" class="d-flex align-items-center gap-3 text-reset" hx-get="<?= e($u) ?>" hx-target="#page-content" hx-swap="innerHTML" hx-push-url="<?= e($u) ?>" id="review-card-<?= $id ?>-link">
            <div class="avatar-text avatar-lg rounded"><?= e(initials((string) $r['display_name'])) ?></div>
            <div class="flex-grow-1" style="min-width:0">
                <div class="fw-bold text-truncate-1-line" id="review-card-<?= $id ?>-name"><?= e($r['display_name']) ?></div>
                <div class="fs-12 text-muted text-truncate-1-line"><?= e(ucfirst($r['kind'])) ?> · due <?= e(format_date($r['due_date'])) ?></div>
            </div>
        </a>
        <div class="mt-3 d-flex justify-content-between align-items-center">
            <span class="badge bg-soft-<?= e($c) ?> text-<?= e($c) ?>"><?= e(review_status_label($r['status'], $r['due_date'])) ?></span>
            <small class="text-muted"><?= $r['rating'] !== null ? str_repeat('★', (int) $r['rating']) : 'by ' . e($r['reviewer_name']) . ((int) $r['reviewer_member_id'] === (int) $me ? ' (you)' : '') ?></small>
        </div>
    </div></div>
</div>
