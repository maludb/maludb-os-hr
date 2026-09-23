<?php /** Data: person, history, tz */ ?>
<div class="table-responsive" id="person-history">
    <table class="table table-hover mb-0" id="person-history-table">
        <thead class="thead-light"><tr><th>When</th><th>Who</th><th>What</th><th>Record</th></tr></thead>
        <tbody>
        <?php if ($history === []): ?><tr><td colspan="4" class="text-center text-muted py-4">Nothing yet.</td></tr><?php endif; ?>
        <?php foreach ($history as $r): ?>
            <tr id="person-history-row-<?= (int) $r['activity_id'] ?>">
                <td class="fs-12"><?= e(format_ts($r['occurred_at'], $tz)) ?></td>
                <td class="fs-12"><?= e($r['actor_name'] ?? 'system') ?><?= ($r['actor_kind'] ?? '') === 'agent' ? ' <span class="badge bg-soft-info text-info">agent</span>' : '' ?></td>
                <td class="fs-12"><code><?= e($r['action']) ?></code><?= $r['action'] === 'screen.view' && !empty($r['after']['tab'] ?? null) ? ' <small class="text-muted">' . e(json_decode((string) $r['after'], true)['tab'] ?? '') . '</small>' : '' ?></td>
                <td class="fs-12"><?= $r['entity_type'] ? e(str_replace('_', ' ', $r['entity_type'])) . ' #' . (int) $r['entity_id'] : '' ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<div class="mt-2 text-end"><a href="/activity?entity_type=member&amp;entity_id=<?= (int) $person['member_id'] ?>" class="fs-12" hx-get="/activity?entity_type=member&amp;entity_id=<?= (int) $person['member_id'] ?>" hx-target="#page-content" hx-push-url="/activity?entity_type=member&amp;entity_id=<?= (int) $person['member_id'] ?>">All activity</a></div>
