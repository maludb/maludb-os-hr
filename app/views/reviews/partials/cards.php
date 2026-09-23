<?php /** Data: rows, total, page, pages, filters, me */ ?>
<div id="reviews-list-results" hx-get="/reviews/" hx-trigger="reviewChanged from:body" hx-include="#reviews-list-filters" hx-swap="outerHTML">
    <div class="text-muted fs-12 mb-3" id="reviews-list-count"><?= (int) $total ?> review<?= $total === 1 ? '' : 's' ?></div>
    <div class="row" id="reviews-list-cards">
        <?php if ($rows === []): ?><div class="col-12"><div class="card"><div class="card-body text-center text-muted py-5" id="reviews-list-empty">No reviews you can see.</div></div></div><?php endif; ?>
        <?php foreach ($rows as $r) { echo view('reviews/partials/card.php', ['r' => $r, 'me' => $me]); } ?>
    </div>
    <?php if ($pages > 1): ?>
    <nav id="reviews-list-pagination"><ul class="pagination justify-content-center">
        <?php for ($p = 1; $p <= $pages; $p++): $u = '/reviews/?' . http_build_query(array_filter(['status' => $filters['status'], 'overdue' => $filters['overdue'] ? 1 : null, 'page' => $p > 1 ? $p : null])); ?>
            <li class="page-item <?= $p === $page ? 'active' : '' ?>"><a class="page-link" href="<?= e($u) ?>" hx-get="<?= e($u) ?>" hx-target="#reviews-list-results" hx-swap="outerHTML" hx-push-url="<?= e($u) ?>"><?= $p ?></a></li>
        <?php endfor; ?>
    </ul></nav>
    <?php endif; ?>
</div>
