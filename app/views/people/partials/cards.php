<?php
/** The results region: cards + pagination. Data: rows, total, page, pages, filters, sort */
$qs = static function (int $p) use ($filters, $sort): string {
    return '/people/?' . http_build_query(array_filter(['q' => $filters['q'], 'department' => $filters['department'], 'status' => $filters['status'],
        'type' => $filters['type'], 'employed' => $filters['employed'], 'sort' => $sort !== 'name' ? $sort : null, 'page' => $p > 1 ? $p : null]));
};
?>
<div id="people-list-results" hx-get="/people/" hx-trigger="employmentProfileChanged from:body, memberChanged from:body" hx-include="#people-list-filters" hx-swap="outerHTML">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <span class="text-muted fs-12" id="people-list-count"><?= (int) $total ?> <?= $total === 1 ? 'person' : 'people' ?></span>
    </div>
    <div class="row" id="people-list-cards">
        <?php if ($rows === []): ?>
            <div class="col-12"><div class="card"><div class="card-body text-center text-muted py-5" id="people-list-empty">Nobody matches. The directory fills from the kernel; invite someone with the button above.</div></div></div>
        <?php endif; ?>
        <?php foreach ($rows as $r) { echo view('people/partials/card.php', ['r' => $r]); } ?>
    </div>
    <?php if ($pages > 1): ?>
    <nav id="people-list-pagination" aria-label="Pages"><ul class="pagination justify-content-center">
        <?php for ($p = max(1, $page - 3); $p <= min($pages, $page + 3); $p++): ?>
            <li class="page-item <?= $p === $page ? 'active' : '' ?>"><a class="page-link" href="<?= e($qs($p)) ?>" hx-get="<?= e($qs($p)) ?>" hx-target="#people-list-results" hx-swap="outerHTML" hx-push-url="<?= e($qs($p)) ?>"><?= $p ?></a></li>
        <?php endfor; ?>
    </ul></nav>
    <?php endif; ?>
</div>
