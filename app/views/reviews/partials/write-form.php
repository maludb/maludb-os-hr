<?php /** Write form. Data: r, values, errors */ $id = (int) $r['review_id']; $v = $values; ?>
<div class="page-header" id="review-write-header">
    <div class="page-header-left d-flex align-items-center">
        <div class="page-header-title"><h5 class="m-b-10">Write · <?= e($r['display_name']) ?></h5></div>
        <ul class="breadcrumb"><li class="breadcrumb-item"><a href="/reviews/" hx-get="/reviews/" hx-target="#page-content" hx-push-url="/reviews/">Reviews</a></li><li class="breadcrumb-item"><a href="/reviews/<?= $id ?>" hx-get="/reviews/<?= $id ?>" hx-target="#page-content" hx-push-url="/reviews/<?= $id ?>">Review #<?= $id ?></a></li><li class="breadcrumb-item">Write</li></ul>
    </div>
    <div class="page-header-right ms-auto">
        <div class="page-header-right-items">
            <div class="d-flex d-md-none"><a href="javascript:void(0)" class="page-header-right-close-toggle"><i class="feather-arrow-left me-2"></i><span>Back</span></a></div>
            <div class="d-flex align-items-center gap-2 page-header-right-items-wrapper">
                <a href="/reviews/<?= $id ?>" id="review-write-cancel-btn" class="btn btn-light-brand" hx-get="/reviews/<?= $id ?>" hx-target="#page-content" hx-push-url="/reviews/<?= $id ?>"><i class="feather-x me-2"></i><span>Cancel</span></a>
                <button type="submit" form="review-write-form" id="review-write-save-btn" class="btn btn-primary"><i class="feather-save me-2"></i><span>Save</span></button>
            </div>
        </div>
        <div class="d-md-none d-flex align-items-center"><a href="javascript:void(0)" class="page-header-right-open-toggle"><i class="feather-align-right fs-20"></i></a></div>
    </div>
</div>
<div class="main-content" id="review-write-content">
    <div class="row"><div class="col-lg-10"><div class="card stretch stretch-full" id="review-write-container"><div class="card-body">
        <p class="text-muted fs-12"><?= e(ucfirst($r['kind'])) ?> review, due <?= e(format_date($r['due_date'])) ?><?= $r['period_start'] ? ', covering ' . e(format_date($r['period_start'])) . ' – ' . e(format_date($r['period_end'])) : '' ?>. Saving keeps it in progress; complete it from its page.</p>
        <?= view('shared/validation-errors.php', ['errors' => $errors]) ?>
        <form method="post" action="/reviews/save.php" id="review-write-form" hx-post="/reviews/save.php" hx-target="#page-content" hx-swap="innerHTML">
            <?= csrf_field() ?><input type="hidden" name="review" value="<?= $id ?>">
            <div class="row mb-3" id="review-write-field-rating-row"><label class="col-lg-3 col-form-label fs-12 text-muted">Rating</label><div class="col-lg-9"><div id="review-write-field-rating">
                <?php for ($i = 1; $i <= 5; $i++): ?><div class="form-check form-check-inline"><input type="radio" name="rating" value="<?= $i ?>" id="review-write-field-rating-<?= $i ?>" class="form-check-input" <?= (int) ($v['rating'] ?? 0) === $i ? 'checked' : '' ?>><label class="form-check-label" for="review-write-field-rating-<?= $i ?>"><?= $i ?></label></div><?php endfor; ?>
                <span class="fs-11 text-muted ms-2">1 = needs attention · 5 = exceptional</span></div></div></div>
            <?php foreach (['summary' => 'Summary', 'strengths' => 'Strengths', 'improvements' => 'To improve', 'goals' => 'Goals for the next period'] as $k => $label): ?>
                <div class="row mb-3" id="review-write-field-<?= $k ?>-row"><label for="review-write-field-<?= $k ?>" class="col-lg-3 col-form-label fs-12 text-muted"><?= e($label) ?></label><div class="col-lg-9"><textarea name="<?= $k ?>" id="review-write-field-<?= $k ?>" class="form-control" rows="<?= $k === 'summary' ? 4 : 3 ?>" maxlength="5000"><?= e($v[$k] ?? '') ?></textarea></div></div>
            <?php endforeach; ?>
        </form>
    </div></div></div></div>
</div>
