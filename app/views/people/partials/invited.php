<?php /** Data: email, expires_at */ ?>
<div class="page-header" id="invited-header">
    <div class="page-header-left d-flex align-items-center">
        <div class="page-header-title"><h5 class="m-b-10">Invitation sent</h5></div>
        <ul class="breadcrumb"><li class="breadcrumb-item"><a href="/people/" hx-get="/people/" hx-target="#page-content" hx-push-url="/people/">People</a></li><li class="breadcrumb-item">Invite</li></ul>
    </div>
</div>
<div class="main-content" id="invited-content">
    <div class="row"><div class="col-lg-8"><div class="card"><div class="card-body text-center py-5" id="invited-message">
        <div class="avatar-text avatar-xl rounded mx-auto mb-3 bg-soft-success text-success"><i class="feather-check"></i></div>
        <h5 class="mb-2">Invited <?= e($email) ?></h5>
        <p class="text-muted mb-3">They appear here once they register<?= $expires_at ? '; the invitation is good until ' . e(format_date($expires_at)) : '' ?>.</p>
        <a href="/people/" class="btn btn-primary" hx-get="/people/" hx-target="#page-content" hx-push-url="/people/">Back to people</a>
        <a href="/people/new" class="btn btn-light-brand ms-2" hx-get="/people/new" hx-target="#page-content" hx-push-url="/people/new">Invite another</a>
    </div></div></div></div>
</div>
