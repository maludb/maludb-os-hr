<?php /** Data: r, canWrite, canCancel, canComment, tz */ $id = (int) $r['review_id']; $c = review_status_color($r['status'], $r['due_date']); ?>
<div class="page-header" id="review-view-header">
    <div class="page-header-left d-flex align-items-center">
        <div class="page-header-title"><h5 class="m-b-10"><?= e(ucfirst($r['kind'])) ?> review · <?= e($r['display_name']) ?></h5></div>
        <ul class="breadcrumb"><li class="breadcrumb-item"><a href="/reviews/" hx-get="/reviews/" hx-target="#page-content" hx-push-url="/reviews/">Reviews</a></li><li class="breadcrumb-item">Review #<?= $id ?></li></ul>
    </div>
    <div class="page-header-right ms-auto">
        <div class="page-header-right-items">
            <div class="d-flex d-md-none"><a href="javascript:void(0)" class="page-header-right-close-toggle"><i class="feather-arrow-left me-2"></i><span>Back</span></a></div>
            <div class="d-flex align-items-center gap-2 page-header-right-items-wrapper">
                <?php if ($canCancel): ?><form method="post" action="/reviews/cancel.php" hx-post="/reviews/cancel.php" hx-confirm="Cancel this review?" hx-target="#page-content" hx-swap="innerHTML"><?= csrf_field() ?><input type="hidden" name="review" value="<?= $id ?>"><button type="submit" class="btn btn-outline-danger" id="review-view-cancel-btn"><i class="feather-x-circle me-2"></i>Cancel</button></form><?php endif; ?>
                <?php if ($canWrite): ?>
                <a href="/reviews/<?= $id ?>/edit" id="review-view-write-btn" class="btn btn-light-brand" hx-get="/reviews/<?= $id ?>/edit" hx-target="#page-content" hx-push-url="/reviews/<?= $id ?>/edit"><i class="feather-edit-3 me-2"></i><span>Write</span></a>
                <form method="post" action="/reviews/complete.php" id="review-complete-form" class="d-flex align-items-center gap-2" hx-post="/reviews/complete.php" hx-confirm="Complete this review?" hx-target="#page-content" hx-swap="innerHTML"><?= csrf_field() ?><input type="hidden" name="review" value="<?= $id ?>">
                    <div class="form-check mb-0 text-nowrap"><input type="checkbox" name="share" value="1" id="review-complete-field-share" class="form-check-input" checked><label class="form-check-label fs-12" for="review-complete-field-share">Share with <?= e(explode(' ', (string) $r['display_name'])[0]) ?></label></div>
                    <button type="submit" class="btn btn-primary" id="review-view-complete-btn"><i class="feather-check me-2"></i>Complete</button>
                </form>
                <?php endif; ?>
            </div>
        </div>
        <div class="d-md-none d-flex align-items-center"><a href="javascript:void(0)" class="page-header-right-open-toggle"><i class="feather-align-right fs-20"></i></a></div>
    </div>
</div>
<div class="main-content" id="review-view-content">
    <div class="row">
        <div class="col-xl-4">
            <div class="card stretch stretch-full" id="review-view-facts"><div class="card-body">
                <span class="badge bg-soft-<?= e($c) ?> text-<?= e($c) ?> fs-12 mb-3" id="review-view-status"><?= e(review_status_label($r['status'], $r['due_date'])) ?></span>
                <table class="table table-borderless table-sm mb-0 fs-12"><tbody>
                    <tr><th class="text-muted fw-medium">Person</th><td><a href="/people/<?= (int) $r['member_id'] ?>" hx-get="/people/<?= (int) $r['member_id'] ?>" hx-target="#page-content" hx-push-url="/people/<?= (int) $r['member_id'] ?>"><?= e($r['display_name']) ?></a></td></tr>
                    <tr><th class="text-muted fw-medium">Reviewer</th><td><?= e($r['reviewer_name']) ?></td></tr>
                    <tr><th class="text-muted fw-medium">Due</th><td><?= e(format_date($r['due_date'])) ?></td></tr>
                    <?php if ($r['period_start']): ?><tr><th class="text-muted fw-medium">Period</th><td><?= e(format_date($r['period_start'])) ?> – <?= e(format_date($r['period_end'])) ?></td></tr><?php endif; ?>
                    <tr><th class="text-muted fw-medium">Rating</th><td id="review-view-rating"><?= $r['rating'] !== null ? str_repeat('★', (int) $r['rating']) . str_repeat('☆', 5 - (int) $r['rating']) : '—' ?></td></tr>
                    <?php if ($r['completed_at']): ?><tr><th class="text-muted fw-medium">Completed</th><td><?= e(format_ts($r['completed_at'], $tz)) ?></td></tr><?php endif; ?>
                    <tr><th class="text-muted fw-medium">Shared</th><td><?= $r['shared_at'] ? e(format_ts($r['shared_at'], $tz)) : '<span class="text-muted">not yet</span>' ?></td></tr>
                    <?php if ($r['cancelled_at']): ?><tr><th class="text-muted fw-medium">Cancelled</th><td><?= e(format_ts($r['cancelled_at'], $tz)) ?><?= $r['cancel_reason'] ? ' · ' . e($r['cancel_reason']) : '' ?></td></tr><?php endif; ?>
                </tbody></table>
            </div></div>
        </div>
        <div class="col-xl-8">
            <div class="card stretch stretch-full" id="review-view-body"><div class="card-body">
                <?php foreach (['summary' => 'Summary', 'strengths' => 'Strengths', 'improvements' => 'To improve', 'goals' => 'Goals'] as $k => $label): ?>
                    <h6 class="fw-bold text-muted text-uppercase fs-12 mb-2"><?= e($label) ?></h6>
                    <p class="fs-13 mb-4" id="review-view-<?= $k ?>"><?= $r[$k] ? nl2br(e($r[$k])) : '<span class="text-muted">—</span>' ?></p>
                <?php endforeach; ?>
                <h6 class="fw-bold text-muted text-uppercase fs-12 mb-2">The person's comment</h6>
                <?php if ($r['employee_comment']): ?><p class="fs-13 mb-0" id="review-view-comment"><?= nl2br(e($r['employee_comment'])) ?> <small class="text-muted">— <?= e(format_ts($r['employee_commented_at'], $tz)) ?></small></p>
                <?php elseif ($canComment): ?>
                    <form method="post" action="/reviews/comment.php" id="review-comment-form" hx-post="/reviews/comment.php" hx-target="#page-content" hx-swap="innerHTML"><?= csrf_field() ?><input type="hidden" name="review" value="<?= $id ?>">
                        <textarea name="comment" id="review-comment-field-comment" class="form-control mb-2" rows="3" maxlength="5000" required placeholder="Your one comment on this review"></textarea>
                        <button type="submit" class="btn btn-primary btn-sm" id="review-comment-save-btn"><i class="feather-message-square me-1"></i>Add my comment</button>
                    </form>
                <?php else: ?><p class="text-muted fs-12 mb-0">—</p><?php endif; ?>
            </div></div>
        </div>
    </div>
</div>
