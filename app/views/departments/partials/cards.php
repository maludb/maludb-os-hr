<?php /** Data: rows, total, page, pages, filters */ ?>
<div id="departments-list-results" hx-get="/departments/" hx-trigger="departmentChanged from:body, memberChanged from:body" hx-include="#departments-list-filters" hx-swap="outerHTML">
    <div class="text-muted fs-12 mb-3" id="departments-list-count"><?= (int) $total ?> department<?= $total === 1 ? '' : 's' ?></div>
    <div class="row" id="departments-list-cards">
        <?php if ($rows === []): ?><div class="col-12"><div class="card"><div class="card-body text-center text-muted py-5" id="departments-list-empty">No departments match.</div></div></div><?php endif; ?>
        <?php foreach ($rows as $r) { echo view('departments/partials/card.php', ['r' => $r]); } ?>
    </div>
    <?php if ($pages > 1): ?>
    <nav id="departments-list-pagination"><ul class="pagination justify-content-center">
        <?php for ($p = 1; $p <= $pages; $p++): $u = '/departments/?' . http_build_query(array_filter(['q' => $filters['q'], 'archived' => $filters['archived'] ? 1 : null, 'page' => $p > 1 ? $p : null])); ?>
            <li class="page-item <?= $p === $page ? 'active' : '' ?>"><a class="page-link" href="<?= e($u) ?>" hx-get="<?= e($u) ?>" hx-target="#departments-list-results" hx-swap="outerHTML" hx-push-url="<?= e($u) ?>"><?= $p ?></a></li>
        <?php endfor; ?>
    </ul></nav>
    <?php endif; ?>
</div>
