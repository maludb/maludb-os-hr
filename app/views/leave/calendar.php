<?php /** Data: monday, days, people, off, workDays, departmentId, departments */
$cells = [];
foreach ($off['requests'] as $req) { $cells[(int) $req['member_id']][] = $req; }
$holidays = [];
foreach ($off['holidays'] as $h) { $holidays[$h['holiday_date']] = $h['name']; }
$prev = $monday->modify('-14 days')->format('Y-m-d'); $next = $monday->modify('+14 days')->format('Y-m-d');
$q = static fn (string $from) => '/leave/calendar?' . http_build_query(array_filter(['from' => $from, 'department' => $departmentId]));
?>
<div class="page-header" id="leave-calendar-header">
    <div class="page-header-left d-flex align-items-center">
        <div class="page-header-title"><h5 class="m-b-10">Who is off</h5></div>
        <ul class="breadcrumb"><li class="breadcrumb-item"><a href="/leave/" hx-get="/leave/" hx-target="#page-content" hx-push-url="/leave/">Leave</a></li><li class="breadcrumb-item">Calendar</li></ul>
    </div>
    <div class="page-header-right ms-auto">
        <div class="page-header-right-items">
            <div class="d-flex d-md-none"><a href="javascript:void(0)" class="page-header-right-close-toggle"><i class="feather-arrow-left me-2"></i><span>Back</span></a></div>
            <div class="d-flex align-items-center gap-2 page-header-right-items-wrapper">
                <form id="leave-calendar-filters" class="d-flex align-items-center gap-2" method="get" action="/leave/calendar" hx-get="/leave/calendar" hx-target="#page-content" hx-swap="innerHTML" hx-trigger="change" hx-push-url="/leave/calendar">
                    <input type="hidden" name="from" value="<?= e($monday->format('Y-m-d')) ?>">
                    <select name="department" id="leave-calendar-filter-department" class="form-select"><option value="">Everyone I can see</option>
                        <?php foreach ($departments as $d): ?><option value="<?= (int) $d['department_id'] ?>" <?= (int) ($departmentId ?? 0) === (int) $d['department_id'] ? 'selected' : '' ?>><?= e($d['name']) ?></option><?php endforeach; ?>
                    </select>
                </form>
                <a href="<?= e($q($prev)) ?>" class="btn btn-light-brand btn-icon" id="leave-calendar-prev-btn" hx-get="<?= e($q($prev)) ?>" hx-target="#page-content" hx-push-url="<?= e($q($prev)) ?>"><i class="feather-chevron-left"></i></a>
                <a href="<?= e($q($next)) ?>" class="btn btn-light-brand btn-icon" id="leave-calendar-next-btn" hx-get="<?= e($q($next)) ?>" hx-target="#page-content" hx-push-url="<?= e($q($next)) ?>"><i class="feather-chevron-right"></i></a>
            </div>
        </div>
        <div class="d-md-none d-flex align-items-center"><a href="javascript:void(0)" class="page-header-right-open-toggle"><i class="feather-align-right fs-20"></i></a></div>
    </div>
</div>
<div class="main-content" id="leave-calendar-content">
    <div class="row"><div class="col-lg-12"><div class="card stretch stretch-full" id="leave-calendar-card">
        <div class="card-header"><h5 class="card-title"><?= e($monday->format('M j')) ?> – <?= e($monday->modify('+' . ($days - 1) . ' days')->format('M j, Y')) ?></h5></div>
        <div class="card-body custom-card-action p-0"><div class="table-responsive">
            <table class="table table-bordered mb-0 fs-11" id="leave-calendar-table" style="min-width:900px">
                <thead class="thead-light"><tr><th class="text-nowrap">Person</th>
                    <?php for ($i = 0; $i < $days; $i++): $d = $monday->modify("+{$i} days"); $ds = $d->format('Y-m-d'); $work = in_array((int) $d->format('N'), $workDays, true); $hol = $holidays[$ds] ?? null; ?>
                        <th class="text-center <?= (!$work || $hol) ? 'bg-light text-muted' : '' ?> <?= $ds === date('Y-m-d') ? 'text-primary' : '' ?>" title="<?= e($hol ?? $d->format('l')) ?>" id="leave-calendar-col-<?= e($ds) ?>"><?= e($d->format('D')) ?><br><?= e($d->format('j')) ?><?= $hol ? '<br><i class="feather-flag"></i>' : '' ?></th>
                    <?php endfor; ?>
                </tr></thead>
                <tbody>
                <?php if ($people === []): ?><tr><td colspan="<?= $days + 1 ?>" class="text-center text-muted py-4">Nobody to show.</td></tr><?php endif; ?>
                <?php foreach ($people as $p): $mid = (int) $p['member_id']; ?>
                    <tr id="leave-calendar-row-<?= $mid ?>"><td class="text-nowrap"><a href="/people/<?= $mid ?>" hx-get="/people/<?= $mid ?>" hx-target="#page-content" hx-push-url="/people/<?= $mid ?>"><?= e($p['display_name']) ?></a></td>
                    <?php for ($i = 0; $i < $days; $i++): $d = $monday->modify("+{$i} days"); $ds = $d->format('Y-m-d'); $work = in_array((int) $d->format('N'), $workDays, true); $hol = isset($holidays[$ds]); $hit = null;
                        foreach ($cells[$mid] ?? [] as $req) { if ($req['start_date'] <= $ds && $req['end_date'] >= $ds) { $hit = $req; break; } } ?>
                        <td class="text-center p-1 <?= (!$work || $hol) ? 'bg-light' : '' ?>"><?php if ($hit !== null && $work && !$hol): $c = leave_status_color($hit['status']); ?><a href="/leave/<?= (int) $hit['leave_request_id'] ?>" hx-get="/leave/<?= (int) $hit['leave_request_id'] ?>" hx-target="#page-content" hx-push-url="/leave/<?= (int) $hit['leave_request_id'] ?>" class="badge bg-soft-<?= e($c) ?> text-<?= e($c) ?> d-block" title="<?= e($hit['leave_type']) ?> · <?= e($hit['status']) ?>"><?= e(mb_substr((string) $hit['leave_type'], 0, 3)) ?></a><?php endif; ?></td>
                    <?php endfor; ?></tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div></div>
        <div class="card-footer fs-11 text-muted"><span class="badge bg-soft-success text-success">approved</span> <span class="badge bg-soft-warning text-warning">requested</span> · shaded: not a work day, or a holiday.</div>
    </div></div></div>
</div>
