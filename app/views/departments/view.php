<?php /** Data: d, canAdmin, addable, tz */ $id = (int) $d['department_id']; ?>
<div class="page-header" id="department-view-header">
    <div class="page-header-left d-flex align-items-center">
        <div class="page-header-title"><h5 class="m-b-10"><?= e($d['name']) ?></h5></div>
        <ul class="breadcrumb"><li class="breadcrumb-item"><a href="/departments/" hx-get="/departments/" hx-target="#page-content" hx-push-url="/departments/">Departments</a></li><li class="breadcrumb-item"><?= e($d['name']) ?></li></ul>
    </div>
    <div class="page-header-right ms-auto">
        <div class="page-header-right-items">
            <div class="d-flex d-md-none"><a href="javascript:void(0)" class="page-header-right-close-toggle"><i class="feather-arrow-left me-2"></i><span>Back</span></a></div>
            <div class="d-flex align-items-center gap-2 page-header-right-items-wrapper">
                <?php if ($canAdmin && $d['archived_at'] === null): ?><a href="/departments/<?= $id ?>/edit" id="department-view-edit-btn" class="btn btn-primary" hx-get="/departments/<?= $id ?>/edit" hx-target="#page-content" hx-push-url="/departments/<?= $id ?>/edit"><i class="feather-edit-3 me-2"></i><span>Edit</span></a><?php endif; ?>
            </div>
        </div>
        <div class="d-md-none d-flex align-items-center"><a href="javascript:void(0)" class="page-header-right-open-toggle"><i class="feather-align-right fs-20"></i></a></div>
    </div>
</div>
<div class="main-content" id="department-view-content">
    <div class="row">
        <div class="col-xxl-4 col-xl-5">
            <div class="card stretch stretch-full" id="department-view-summary"><div class="card-body">
                <h6 class="fw-bold text-muted text-uppercase fs-12 mb-3">About</h6>
                <p class="fs-12 mb-3"><?= $d['description'] ? nl2br(e($d['description'])) : '<span class="text-muted">No description.</span>' ?></p>
                <div class="fs-12 mb-1"><span class="text-muted">Manager:</span> <?= $d['manager_member_id'] ? '<a href="/people/' . (int) $d['manager_member_id'] . '" hx-get="/people/' . (int) $d['manager_member_id'] . '" hx-target="#page-content" hx-push-url="/people/' . (int) $d['manager_member_id'] . '" id="department-view-manager">' . e($d['manager_name']) . '</a>' : '—' ?></div>
                <div class="fs-12 mb-1"><span class="text-muted">Reports to:</span> <?= $d['parent_id'] ? '<a href="/departments/' . (int) $d['parent_id'] . '" hx-get="/departments/' . (int) $d['parent_id'] . '" hx-target="#page-content" hx-push-url="/departments/' . (int) $d['parent_id'] . '">' . e($d['parent_name']) . '</a>' : '— (the root)' ?></div>
                <?php if ($d['is_system']): ?><div class="fs-12 mb-1"><span class="badge bg-soft-primary text-primary">standing department · <?= e($d['system_key']) ?></span></div><?php endif; ?>
                <?php if ($d['archived_at'] !== null): ?><div class="fs-12 text-muted">Archived <?= e(format_date($d['archived_at'])) ?></div><?php endif; ?>
                <?php if ($d['children'] !== []): ?>
                    <h6 class="fw-bold text-muted text-uppercase fs-12 mt-4 mb-2">Sub-departments</h6>
                    <ul class="list-unstyled mb-0 fs-12" id="department-view-children">
                    <?php foreach ($d['children'] as $c): ?><li class="mb-1"><a href="/departments/<?= (int) $c['department_id'] ?>" hx-get="/departments/<?= (int) $c['department_id'] ?>" hx-target="#page-content" hx-push-url="/departments/<?= (int) $c['department_id'] ?>"><?= e($c['name']) ?></a> <span class="text-muted">· <?= (int) $c['people'] ?> people</span></li><?php endforeach; ?>
                    </ul>
                <?php endif; ?>
                <h6 class="fw-bold text-muted text-uppercase fs-12 mt-4 mb-2">Off this week</h6>
                <?php if ($d['off'] === []): ?><div class="fs-12 text-muted" id="department-view-off-empty">Nobody you can see.</div><?php else: ?>
                <ul class="list-unstyled mb-0 fs-12" id="department-view-off">
                    <?php foreach ($d['off'] as $o): ?><li class="mb-1"><a href="/leave/<?= (int) $o['leave_request_id'] ?>" hx-get="/leave/<?= (int) $o['leave_request_id'] ?>" hx-target="#page-content" hx-push-url="/leave/<?= (int) $o['leave_request_id'] ?>"><?= e($o['display_name']) ?></a> <span class="text-muted">· <?= e($o['leave_type']) ?>, <?= e(format_date($o['start_date'])) ?><?= $o['end_date'] !== $o['start_date'] ? ' – ' . e(format_date($o['end_date'])) : '' ?></span></li><?php endforeach; ?>
                </ul>
                <?php endif; ?>
            </div></div>
        </div>
        <div class="col-xxl-8 col-xl-7">
            <div class="card stretch stretch-full" id="department-view-members-card" hx-get="/departments/<?= $id ?>" hx-trigger="memberChanged from:body" hx-target="#page-content" hx-swap="innerHTML">
                <div class="card-header"><h5 class="card-title">Members (<?= count($d['members']) ?>)</h5></div>
                <div class="card-body">
                    <div class="row" id="department-view-members">
                        <?php if ($d['members'] === []): ?><div class="col-12 text-muted fs-12">Nobody yet.</div><?php endif; ?>
                        <?php foreach ($d['members'] as $m): $mid = (int) $m['member_id']; ?>
                        <div class="col-md-6 mb-3" id="department-member-<?= $mid ?>">
                            <div class="d-flex align-items-center gap-2">
                                <a href="/people/<?= $mid ?>" class="d-flex align-items-center gap-2 text-reset flex-grow-1" style="min-width:0" hx-get="/people/<?= $mid ?>" hx-target="#page-content" hx-push-url="/people/<?= $mid ?>">
                                    <div class="avatar-text avatar-md rounded"><?= $m['member_kind'] === 'agent' ? '<i class="feather-cpu"></i>' : e(initials((string) $m['display_name'])) ?></div>
                                    <div style="min-width:0"><div class="fw-semibold text-truncate-1-line fs-13"><?= e($m['display_name']) ?></div>
                                        <div class="fs-11 text-muted text-truncate-1-line"><?= e($m['job_title'] ?? ($m['member_kind'] === 'agent' ? 'agent' : '')) ?>
                                        <?php if (!empty($m['is_admin'])): ?><span class="badge bg-soft-warning text-warning">admin</span><?php endif; ?><?php if (!empty($m['is_primary'])): ?><span class="badge bg-soft-primary text-primary">primary</span><?php endif; ?></div></div>
                                </a>
                                <?php if ($canAdmin): ?>
                                <form method="post" action="/people/membership-remove.php" hx-post="/people/membership-remove.php" hx-confirm="Remove <?= e($m['display_name']) ?> from <?= e($d['name']) ?>?" hx-target="#page-content" hx-swap="innerHTML">
                                    <?= csrf_field() ?><input type="hidden" name="person" value="<?= $mid ?>"><input type="hidden" name="department" value="<?= $id ?>">
                                    <button type="submit" class="avatar-text avatar-sm border-0" data-bs-toggle="tooltip" title="Remove" id="department-member-<?= $mid ?>-remove-btn"><i class="feather-x"></i></button>
                                </form>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php if ($canAdmin && $d['archived_at'] === null): ?>
                    <hr>
                    <form method="post" action="/people/membership-save.php" class="row g-2 align-items-end" id="membership-form" hx-post="/people/membership-save.php" hx-target="#page-content" hx-swap="innerHTML">
                        <?= csrf_field() ?><input type="hidden" name="department" value="<?= $id ?>">
                        <div class="col-md-6">
                            <label class="form-label fs-12 text-muted" id="membership-form-field-person-label" for="membership-form-field-person-open">Add a member</label>
                            <?= picker_field(['id' => 'membership-form-field-person', 'name' => 'person', 'source' => 'department_candidate', 'value' => '', 'required' => true,
                                'title' => 'Add a member', 'placeholder' => 'Choose a person or agent', 'search' => 'Search people and agents…',
                                'params' => ['department_id' => $id], 'labelledby' => 'membership-form-field-person-label']) ?>
                        </div>
                        <div class="col-md-2"><div class="form-check"><input type="checkbox" name="is_admin" value="1" id="membership-form-field-is_admin" class="form-check-input"><label class="form-check-label fs-12" for="membership-form-field-is_admin">Admin</label></div></div>
                        <div class="col-md-2"><div class="form-check"><input type="checkbox" name="is_primary" value="1" id="membership-form-field-is_primary" class="form-check-input"><label class="form-check-label fs-12" for="membership-form-field-is_primary">Primary</label></div></div>
                        <div class="col-md-2"><button type="submit" class="btn btn-primary w-100" id="membership-form-save-btn"><i class="feather-user-plus"></i></button></div>
                    </form>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>
