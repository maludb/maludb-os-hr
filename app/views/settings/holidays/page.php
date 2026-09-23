<?php /** Data: rows, year */ ?>
<div class="page-header" id="holidays-header">
    <div class="page-header-left d-flex align-items-center">
        <div class="page-header-title"><h5 class="m-b-10">Holidays <?= (int) $year ?></h5></div>
        <ul class="breadcrumb"><li class="breadcrumb-item"><a href="/settings/" hx-get="/settings/" hx-target="#page-content" hx-push-url="/settings/">Settings</a></li><li class="breadcrumb-item">Holidays</li></ul>
    </div>
    <div class="page-header-right ms-auto">
        <div class="page-header-right-items">
            <div class="d-flex d-md-none"><a href="javascript:void(0)" class="page-header-right-close-toggle"><i class="feather-arrow-left me-2"></i><span>Back</span></a></div>
            <div class="d-flex align-items-center gap-2 page-header-right-items-wrapper">
                <form id="holidays-filters" class="d-flex align-items-center gap-2" method="get" action="/settings/holidays/" hx-get="/settings/holidays/" hx-target="#page-content" hx-swap="innerHTML" hx-trigger="change" hx-push-url="/settings/holidays/">
                    <input type="number" name="year" id="holidays-filter-year" class="form-control" style="width:6.5rem" value="<?= (int) $year ?>" min="2000" max="2200">
                </form>
                <a href="/settings/holidays/new" id="holidays-add-btn" class="btn btn-primary" hx-get="/settings/holidays/new" hx-target="#page-content" hx-push-url="/settings/holidays/new"><i class="feather-plus me-2"></i><span>Add a holiday</span></a>
            </div>
        </div>
        <div class="d-md-none d-flex align-items-center"><a href="javascript:void(0)" class="page-header-right-open-toggle"><i class="feather-align-right fs-20"></i></a></div>
    </div>
</div>
<div class="main-content" id="holidays-content">
    <div class="row"><div class="col-lg-8"><div class="card stretch stretch-full" id="holidays-card">
        <div class="card-body custom-card-action p-0"><div class="table-responsive">
            <table class="table table-hover mb-0" id="holidays-table">
                <thead class="thead-light"><tr><th>Date</th><th>Holiday</th><th class="text-end">Actions</th></tr></thead>
                <tbody id="holidays-tbody">
                <?php if ($rows === []): ?><tr><td colspan="3" class="text-center text-muted py-4">No holidays in <?= (int) $year ?>.</td></tr><?php endif; ?>
                <?php foreach ($rows as $h): $id = (int) $h['holiday_id']; $u = '/settings/holidays/' . $id . '/edit'; ?>
                    <tr id="holiday-row-<?= $id ?>">
                        <td><?= e(format_date($h['holiday_date'])) ?> <small class="text-muted"><?= e((new DateTimeImmutable($h['holiday_date']))->format('l')) ?></small></td>
                        <td><?= e($h['name']) ?></td>
                        <td class="text-end"><div class="hstack gap-2 justify-content-end">
                            <a href="<?= e($u) ?>" class="avatar-text avatar-md" title="Edit" id="holiday-row-<?= $id ?>-edit-btn" hx-get="<?= e($u) ?>" hx-target="#page-content" hx-push-url="<?= e($u) ?>"><i class="feather-edit"></i></a>
                            <form method="post" action="/settings/holidays/delete.php" hx-post="/settings/holidays/delete.php" hx-target="closest tr" hx-swap="delete" hx-confirm="Remove the holiday <?= e($h['name']) ?>?"><?= csrf_field() ?><input type="hidden" name="holiday" value="<?= $id ?>"><button type="submit" class="avatar-text avatar-md border-0" title="Remove" id="holiday-row-<?= $id ?>-delete-btn"><i class="feather-trash-2"></i></button></form>
                        </div></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div></div>
    </div></div></div>
</div>
