<?php /** Data: rows, raw (just minted, once), staff, tz */ $base = rtrim((string) env('APP_URL', ''), '/'); ?>
<div class="page-header" id="tokens-header">
    <div class="page-header-left d-flex align-items-center">
        <div class="page-header-title"><h5 class="m-b-10">Tokens</h5></div>
        <ul class="breadcrumb"><li class="breadcrumb-item"><a href="/" hx-get="/" hx-target="#page-content" hx-push-url="/">Home</a></li><li class="breadcrumb-item">Tokens</li></ul>
    </div>
</div>
<div class="main-content" id="tokens-content">
    <?php if ($raw): ?>
    <div class="row"><div class="col-lg-12"><div class="alert alert-success" id="tokens-minted">
        <div class="fw-bold mb-1">Your new <?= e($raw['scope']) ?> token "<?= e($raw['label']) ?>" — copy it now; it is shown once.</div>
        <code class="user-select-all" id="tokens-minted-value"><?= e($raw['raw']) ?></code>
    </div></div></div>
    <?php endif; ?>
    <div class="row">
        <div class="col-xl-5">
            <div class="card stretch stretch-full" id="tokens-connect"><div class="card-header"><h5 class="card-title">Connect your AI to HR</h5></div><div class="card-body fs-12">
                <p>Your own AI tools (Claude Desktop, Claude Code, an agent of yours) read HR's two memories with an <strong>mcp</strong> token as a Bearer token. They see exactly what you see.</p>
                <div class="mb-2"><span class="text-muted">Records:</span> <code id="tokens-url-records"><?= e($base) ?>/mcp/records</code></div>
                <div class="mb-3"><span class="text-muted">Activity:</span> <code id="tokens-url-activity"><?= e($base) ?>/mcp/activity</code></div>
                <?php if ($staff): ?><p class="mb-3"><span class="text-muted">The accounting feed</span> (an <strong>api</strong> token): <code id="tokens-url-feed"><?= e($base) ?>/api/v1/pay-runs</code></p><?php endif; ?>
                <hr>
                <form method="post" action="/settings/tokens/mint.php" id="token-form" class="row g-2 align-items-end" hx-post="/settings/tokens/mint.php" hx-target="#page-content" hx-swap="innerHTML">
                    <?= csrf_field() ?>
                    <div class="col-7"><label class="form-label fs-12 text-muted" for="token-form-field-label">Label</label><input type="text" name="label" id="token-form-field-label" class="form-control" required maxlength="80" placeholder="Claude Desktop"></div>
                    <div class="col-3"><label class="form-label fs-12 text-muted" for="token-form-field-scope">Scope</label><select name="scope" id="token-form-field-scope" class="form-select"><option value="mcp">mcp</option><?php if ($staff): ?><option value="api">api</option><?php endif; ?></select></div>
                    <div class="col-2"><button type="submit" class="btn btn-primary w-100" id="token-form-save-btn"><i class="feather-key"></i></button></div>
                </form>
            </div></div>
        </div>
        <div class="col-xl-7">
            <div class="card stretch stretch-full" id="tokens-card" hx-get="/settings/tokens/" hx-trigger="tokenChanged from:body" hx-target="#page-content" hx-swap="innerHTML">
                <div class="card-header"><h5 class="card-title">Your tokens</h5></div>
                <div class="card-body custom-card-action p-0"><div class="table-responsive"><table class="table table-hover mb-0 fs-12" id="tokens-table">
                    <thead class="thead-light"><tr><th>Label</th><th>Scope</th><th>Created</th><th>Last used</th><th class="text-end"></th></tr></thead>
                    <tbody>
                    <?php if ($rows === []): ?><tr><td colspan="5" class="text-center text-muted py-4">No tokens yet.</td></tr><?php endif; ?>
                    <?php foreach ($rows as $t): $id = (int) $t['id']; $live = $t['revoked_at'] === null; ?>
                        <tr id="token-row-<?= $id ?>" class="<?= $live ? '' : 'text-muted' ?>">
                            <td><span class="wd-10 ht-10 bg-<?= $live ? 'success' : 'secondary' ?> me-2 d-inline-block rounded-circle"></span><?= e($t['label']) ?><?= $live ? '' : ' <span class="badge bg-soft-secondary text-secondary">revoked</span>' ?></td>
                            <td><?= e($t['scope']) ?></td><td><?= e(format_ts($t['created_at'], $tz, 'M j, Y')) ?></td><td><?= e(format_ts($t['last_used_at'], $tz) ?: 'never') ?></td>
                            <td class="text-end"><?php if ($live): ?><form method="post" action="/settings/tokens/revoke.php" hx-post="/settings/tokens/revoke.php" hx-confirm="Revoke the token <?= e($t['label']) ?>? Anything using it stops working." hx-target="#page-content" hx-swap="innerHTML"><?= csrf_field() ?><input type="hidden" name="token" value="<?= $id ?>"><button type="submit" class="avatar-text avatar-sm border-0" title="Revoke" id="token-row-<?= $id ?>-revoke-btn"><i class="feather-x"></i></button></form><?php endif; ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table></div></div>
            </div>
        </div>
    </div>
</div>
