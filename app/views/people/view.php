<?php /** Data: person, tab, tabs, staff, me, canSeePay, canSeePerson, canAdmin, history, tz */
$id = (int) $person['member_id'];
$e = $person['employment'];
$tabLabels = ['overview' => 'Overview', 'employment' => 'Employment', 'pay' => 'Pay', 'leave' => 'Leave', 'reviews' => 'Reviews', 'history' => 'History'];
$visible = ['overview' => true, 'employment' => $canSeePerson, 'pay' => $canSeePay, 'leave' => $canSeePerson, 'reviews' => $canSeePerson || $id === $me, 'history' => true];
$color = employment_status_color($e['status'] ?? null);
?>
<div class="page-header" id="person-view-header">
    <div class="page-header-left d-flex align-items-center">
        <div class="page-header-title"><h5 class="m-b-10"><?= e($person['display_name']) ?></h5></div>
        <ul class="breadcrumb">
            <li class="breadcrumb-item"><a href="/people/" hx-get="/people/" hx-target="#page-content" hx-push-url="/people/">People</a></li>
            <li class="breadcrumb-item"><?= e($person['display_name']) ?></li>
        </ul>
    </div>
    <div class="page-header-right ms-auto">
        <div class="page-header-right-items">
            <div class="d-flex d-md-none"><a href="javascript:void(0)" class="page-header-right-close-toggle"><i class="feather-arrow-left me-2"></i><span>Back</span></a></div>
            <div class="d-flex align-items-center gap-2 page-header-right-items-wrapper">
                <?php if ($canAdmin && $person['member_kind'] === 'human' && $person['business_role'] !== 'super_admin'): ?>
                <a href="/people/directory/<?= $id ?>/edit" id="person-view-directory-btn" class="btn btn-light-brand" hx-get="/people/directory/<?= $id ?>/edit" hx-target="#page-content" hx-push-url="/people/directory/<?= $id ?>/edit"><i class="feather-edit-3 me-2"></i><span>Edit directory</span></a>
                <?php endif; ?>
                <?php if ($staff && $person['member_kind'] === 'human'): ?>
                <a href="/people/employment/<?= $id ?>/edit" id="person-view-employment-btn" class="btn btn-primary" hx-get="/people/employment/<?= $id ?>/edit" hx-target="#page-content" hx-push-url="/people/employment/<?= $id ?>/edit"><i class="feather-briefcase me-2"></i><span><?= $e === null ? 'Employ' : 'Employment' ?></span></a>
                <?php endif; ?>
            </div>
        </div>
        <div class="d-md-none d-flex align-items-center"><a href="javascript:void(0)" class="page-header-right-open-toggle"><i class="feather-align-right fs-20"></i></a></div>
    </div>
</div>
<div class="main-content" id="person-view-content">
    <div class="row">
        <div class="col-xxl-3 col-xl-4">
            <div class="card stretch stretch-full" id="person-view-summary">
                <div class="card-body text-center">
                    <div class="avatar-text avatar-xl rounded mx-auto mb-3"><?= e(initials((string) $person['display_name'])) ?></div>
                    <h5 class="fw-bold mb-1"><?= e($person['display_name']) ?></h5>
                    <div class="text-muted fs-12 mb-2"><?= e($person['job_title'] ?? '') ?></div>
                    <span class="badge bg-soft-<?= e($color) ?> text-<?= e($color) ?>" id="person-view-status"><?= e($person['member_kind'] === 'agent' ? 'Agent' : employment_status_label($e['status'] ?? null)) ?></span>
                    <?php if (!empty($person['is_external'])): ?><span class="badge bg-soft-secondary text-secondary">external</span><?php endif; ?>
                    <?php if ($person['status'] !== 'active'): ?><span class="badge bg-soft-danger text-danger">inactive</span><?php endif; ?>
                    <hr>
                    <div class="text-start fs-12">
                        <div class="mb-1"><i class="feather-mail me-2 text-muted"></i><?= e($person['email'] ?? '—') ?></div>
                        <div class="mb-1"><i class="feather-phone me-2 text-muted"></i><?= e($person['phone'] ?? '—') ?></div>
                        <div class="mb-1"><i class="feather-clock me-2 text-muted"></i><?= e($person['timezone']) ?></div>
                        <div class="mb-1"><i class="feather-shield me-2 text-muted"></i><?= e(match ($person['business_role']) { 'super_admin' => 'Super-admin', 'dept_admin' => 'Department admin', default => 'User' }) ?><?= $person['hr_capability'] ? ' · HR ' . e($person['hr_capability']) : '' ?></div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xxl-9 col-xl-8">
            <div class="card border-top-0" id="person-view-tabs-card">
                <div class="card-header p-0">
                    <ul class="nav nav-tabs flex-wrap w-100 text-center" role="tablist" id="person-view-tabs">
                        <?php foreach ($tabs as $t): if (!$visible[$t]) { continue; } ?>
                            <li class="nav-item flex-fill border-top" role="presentation">
                                <a href="/people/<?= $id ?>?tab=<?= e($t) ?>" class="nav-link <?= $t === $tab ? 'active' : '' ?>" id="person-view-tab-<?= e($t) ?>"
                                   hx-get="/people/<?= $id ?>?tab=<?= e($t) ?>" hx-target="#page-content" hx-swap="innerHTML" hx-push-url="/people/<?= $id ?>?tab=<?= e($t) ?>"><?= e($tabLabels[$t]) ?></a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                <div class="card-body" id="person-view-tab-content">
                    <?php
                    $tabView = $visible[$tab] ? $tab : 'overview';
                    $partial = match ($tabView) {
                        'employment' => 'people/partials/employment.php',
                        'history' => 'people/partials/history.php',
                        'pay', 'leave', 'reviews' => is_file(dirname(__DIR__) . '/people/partials/' . $tabView . '.php') ? 'people/partials/' . $tabView . '.php' : null,
                        default => 'people/partials/overview.php',
                    };
                    if ($partial === null) {
                        echo '<div class="text-center text-muted py-5" id="person-view-' . e($tabView) . '-empty">' . e(ucfirst($tabView)) . ' arrives with its slice.</div>';
                    } else {
                        echo view($partial, ['person' => $person, 'staff' => $staff, 'me' => $me, 'history' => $history, 'tz' => $tz, 'canSeePay' => $canSeePay]);
                    }
                    ?>
                </div>
            </div>
        </div>
    </div>
</div>
