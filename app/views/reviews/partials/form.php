<?php /** Schedule form. Data: values, errors, people, reviewers, me */ $v = $values; ?>
<div class="page-header" id="review-form-header">
    <div class="page-header-left d-flex align-items-center">
        <div class="page-header-title"><h5 class="m-b-10">Schedule a review</h5></div>
        <ul class="breadcrumb"><li class="breadcrumb-item"><a href="/reviews/" hx-get="/reviews/" hx-target="#page-content" hx-push-url="/reviews/">Reviews</a></li><li class="breadcrumb-item">New</li></ul>
    </div>
    <div class="page-header-right ms-auto">
        <div class="page-header-right-items">
            <div class="d-flex d-md-none"><a href="javascript:void(0)" class="page-header-right-close-toggle"><i class="feather-arrow-left me-2"></i><span>Back</span></a></div>
            <div class="d-flex align-items-center gap-2 page-header-right-items-wrapper">
                <a href="/reviews/" id="review-form-cancel-btn" class="btn btn-light-brand" hx-get="/reviews/" hx-target="#page-content" hx-push-url="/reviews/"><i class="feather-x me-2"></i><span>Cancel</span></a>
                <button type="submit" form="review-form" id="review-form-save-btn" class="btn btn-primary"><i class="feather-calendar me-2"></i><span>Schedule</span></button>
            </div>
        </div>
        <div class="d-md-none d-flex align-items-center"><a href="javascript:void(0)" class="page-header-right-open-toggle"><i class="feather-align-right fs-20"></i></a></div>
    </div>
</div>
<div class="main-content" id="review-form-content">
    <div class="row"><div class="col-lg-8"><div class="card stretch stretch-full" id="review-form-container"><div class="card-body">
        <?= view('shared/validation-errors.php', ['errors' => $errors]) ?>
        <form method="post" action="/reviews/save.php" id="review-form" hx-post="/reviews/save.php" hx-target="#page-content" hx-swap="innerHTML">
            <?= csrf_field() ?>
            <?php $field = static fn (string $name, string $label, string $input): string => '<div class="row mb-3" id="review-form-field-' . $name . '-row"><label for="review-form-field-' . $name . '" class="col-lg-4 col-form-label fs-12 text-muted">' . e($label) . '</label><div class="col-lg-8">' . $input . '</div></div>'; ?>
            <?php $po = '<option value="">Choose…</option>'; foreach ($people as $p) { $po .= '<option value="' . (int) $p['member_id'] . '"' . ((int) ($v['member_id'] ?? 0) === (int) $p['member_id'] ? ' selected' : '') . '>' . e($p['display_name']) . '</option>'; } ?>
            <?= $field('person', 'Person', '<select name="person" id="review-form-field-person" class="form-select" required>' . $po . '</select>') ?>
            <?php $ko = ''; foreach (REVIEW_KINDS as $k) { $ko .= '<option value="' . $k . '"' . (($v['kind'] ?? 'annual') === $k ? ' selected' : '') . '>' . e(ucfirst($k)) . '</option>'; } ?>
            <?= $field('kind', 'Kind', '<select name="kind" id="review-form-field-kind" class="form-select">' . $ko . '</select>') ?>
            <?= $field('due_date', 'Due', '<input type="date" name="due_date" id="review-form-field-due_date" class="form-control" required value="' . e($v['due_date']) . '">') ?>
            <?php $ro = '<option value="">The person\'s manager</option>'; foreach ($reviewers as $m) { $ro .= '<option value="' . (int) $m['member_id'] . '"' . ((int) ($v['reviewer_member_id'] ?? 0) === (int) $m['member_id'] ? ' selected' : '') . '>' . e($m['display_name']) . '</option>'; } ?>
            <?= $field('reviewer', 'Reviewer', '<select name="reviewer" id="review-form-field-reviewer" class="form-select">' . $ro . '</select>') ?>
            <?= $field('period_start', 'Period', '<div class="d-flex gap-2"><input type="date" name="period_start" id="review-form-field-period_start" class="form-control" value="' . e($v['period_start']) . '"><input type="date" name="period_end" id="review-form-field-period_end" class="form-control" value="' . e($v['period_end']) . '"></div>') ?>
        </form>
    </div></div></div></div>
</div>
