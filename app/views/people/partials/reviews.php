<?php /** The Reviews tab. Data: person, reviews, staff, me */ $id = (int) $person['member_id']; ?>
<div id="person-reviews" hx-get="/people/<?= $id ?>?tab=reviews" hx-trigger="reviewChanged from:body" hx-target="#page-content" hx-swap="innerHTML">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h6 class="fw-bold text-muted text-uppercase fs-12 mb-0">Reviews</h6>
        <?php if (($staff || (($person['employment']['manager_member_id'] ?? null) !== null && (int) $person['employment']['manager_member_id'] === (int) $me)) && $person['member_kind'] === 'human' && $person['employment'] !== null): ?>
            <a href="/reviews/new?person=<?= $id ?>" class="btn btn-sm btn-primary" id="person-reviews-schedule-btn" hx-get="/reviews/new?person=<?= $id ?>" hx-target="#page-content" hx-push-url="/reviews/new?person=<?= $id ?>"><i class="feather-plus me-1"></i>Schedule a review</a>
        <?php endif; ?>
    </div>
    <div class="table-responsive"><table class="table table-hover mb-0 fs-12" id="person-reviews-table">
        <thead class="thead-light"><tr><th>Kind</th><th>Due</th><th>Reviewer</th><th>Status</th><th class="text-end">Rating</th></tr></thead>
        <tbody>
        <?php if ($reviews === []): ?><tr><td colspan="5" class="text-center text-muted py-3">No reviews you can see.</td></tr><?php endif; ?>
        <?php foreach ($reviews as $r): $c = review_status_color($r['status'], $r['due_date']); $u = '/reviews/' . (int) $r['review_id']; ?>
            <tr id="person-review-<?= (int) $r['review_id'] ?>">
                <td><a href="<?= e($u) ?>" hx-get="<?= e($u) ?>" hx-target="#page-content" hx-push-url="<?= e($u) ?>"><?= e(ucfirst($r['kind'])) ?></a></td>
                <td><?= e(format_date($r['due_date'])) ?></td><td><?= e($r['reviewer_name']) ?></td>
                <td><span class="badge bg-soft-<?= e($c) ?> text-<?= e($c) ?>"><?= e(review_status_label($r['status'], $r['due_date'])) ?></span></td>
                <td class="text-end"><?= $r['rating'] !== null ? str_repeat('★', (int) $r['rating']) . str_repeat('☆', 5 - (int) $r['rating']) : '' ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table></div>
</div>
