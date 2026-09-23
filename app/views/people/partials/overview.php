<?php /** Data: person, staff, me */ $id = (int) $person['member_id']; ?>
<div class="row" id="person-overview">
    <div class="col-md-6 mb-4">
        <h6 class="fw-bold text-muted text-uppercase fs-12 mb-3">Departments</h6>
        <?php if ($person['departments'] === []): ?><div class="text-muted fs-12">In no department.</div><?php endif; ?>
        <ul class="list-unstyled mb-0" id="person-overview-departments">
            <?php foreach ($person['departments'] as $d): $did = (int) $d['department_id']; ?>
                <li class="mb-2" id="person-overview-department-<?= $did ?>">
                    <a href="/departments/<?= $did ?>" hx-get="/departments/<?= $did ?>" hx-target="#page-content" hx-push-url="/departments/<?= $did ?>" class="fw-semibold"><?= e($d['department_name']) ?></a>
                    <?php if (!empty($d['is_primary'])): ?><span class="badge bg-soft-primary text-primary ms-1">primary</span><?php endif; ?>
                    <?php if (!empty($d['is_admin'])): ?><span class="badge bg-soft-warning text-warning ms-1">admin</span><?php endif; ?>
                </li>
            <?php endforeach; ?>
        </ul>
        <?php if ($person['manages'] !== []): ?>
            <h6 class="fw-bold text-muted text-uppercase fs-12 mt-4 mb-3">Manages</h6>
            <ul class="list-unstyled mb-0" id="person-overview-manages">
                <?php foreach ($person['manages'] as $d): ?><li class="mb-1"><a href="/departments/<?= (int) $d['department_id'] ?>" hx-get="/departments/<?= (int) $d['department_id'] ?>" hx-target="#page-content" hx-push-url="/departments/<?= (int) $d['department_id'] ?>"><?= e($d['name']) ?></a></li><?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>
    <div class="col-md-6 mb-4">
        <h6 class="fw-bold text-muted text-uppercase fs-12 mb-3">Reporting line</h6>
        <?php $e = $person['employment']; ?>
        <div class="mb-2 fs-12"><span class="text-muted">Manager:</span>
            <?php if ($e !== null && $e['manager_member_id'] !== null): ?>
                <a href="/people/<?= (int) $e['manager_member_id'] ?>" hx-get="/people/<?= (int) $e['manager_member_id'] ?>" hx-target="#page-content" hx-push-url="/people/<?= (int) $e['manager_member_id'] ?>" id="person-overview-manager"><?= e($e['manager_name']) ?></a>
            <?php else: ?><span id="person-overview-manager">—</span><?php endif; ?>
        </div>
        <div class="fs-12 text-muted mb-1">Reports (<?= count($person['reports']) ?>)</div>
        <ul class="list-unstyled mb-0" id="person-overview-reports">
            <?php foreach ($person['reports'] as $r): ?>
                <li class="mb-1"><a href="/people/<?= (int) $r['member_id'] ?>" hx-get="/people/<?= (int) $r['member_id'] ?>" hx-target="#page-content" hx-push-url="/people/<?= (int) $r['member_id'] ?>"><?= e($r['display_name']) ?></a> <small class="text-muted"><?= e($r['job_title'] ?? '') ?></small></li>
            <?php endforeach; ?>
        </ul>
    </div>
</div>
