<?php /** The employment record, read-only. Data: person, staff */ $e = $person['employment']; $id = (int) $person['member_id']; ?>
<div id="person-employment" hx-get="/people/<?= $id ?>?tab=employment" hx-trigger="employmentProfileChanged from:body" hx-target="#page-content" hx-swap="innerHTML">
<?php if ($e === null): ?>
    <div class="text-center py-5" id="person-employment-empty">
        <div class="text-muted mb-3">No employment record<?= $person['member_kind'] === 'agent' ? ' — an agent is not an employee.' : '.' ?></div>
        <?php if ($staff && $person['member_kind'] === 'human'): ?>
            <a href="/people/employment/<?= $id ?>/edit" class="btn btn-primary" id="person-employment-create-btn" hx-get="/people/employment/<?= $id ?>/edit" hx-target="#page-content" hx-push-url="/people/employment/<?= $id ?>/edit"><i class="feather-briefcase me-2"></i>Create the record</a>
        <?php endif; ?>
    </div>
<?php else: ?>
    <?php $color = employment_status_color($e['status']); $rows = [
        'Employee no.' => $e['employee_no'], 'Type' => ucfirst((string) $e['employment_type']),
        'Status' => '<span class="badge bg-soft-' . $color . ' text-' . $color . '">' . e(employment_status_label($e['status'])) . '</span>',
        'Manager' => $e['manager_member_id'] !== null ? '<a href="/people/' . (int) $e['manager_member_id'] . '" hx-get="/people/' . (int) $e['manager_member_id'] . '" hx-target="#page-content" hx-push-url="/people/' . (int) $e['manager_member_id'] . '">' . e($e['manager_name']) . '</a>' : '—',
        'Primary department' => e($e['primary_department'] ?? '—'), 'Work location' => e($e['work_location'] ?? '—'),
        'Start date' => e(format_date($e['start_date']) ?: '—'), 'Probation ends' => e(format_date($e['probation_end_date']) ?: '—'),
        'End date' => e(format_date($e['end_date']) ?: '—') . ($e['end_reason'] ? ' <small class="text-muted">' . e($e['end_reason']) . '</small>' : ''),
        'Weekly hours' => e($e['weekly_hours'] !== null ? rtrim(rtrim((string) $e['weekly_hours'], '0'), '.') : '—'),
        'Pay schedule' => e($e['pay_schedule'] ? ucfirst((string) $e['pay_schedule']) : '—'),
        'Notes' => nl2br(e($e['notes'] ?? '—')),
    ]; ?>
    <div class="table-responsive">
        <table class="table table-borderless mb-0" id="person-employment-table">
            <tbody>
            <?php foreach ($rows as $label => $value): ?>
                <tr><th class="text-muted fw-medium fs-12" style="width:40%"><?= e($label) ?></th><td><?= in_array($label, ['Employee no.', 'Type'], true) ? e($value) : $value ?></td></tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php if ($staff && $e['status'] !== 'ended'): ?>
        <hr>
        <form method="post" action="/people/employment/end.php" class="row g-2 align-items-end" id="person-employment-end-form"
              hx-post="/people/employment/end.php" hx-confirm="End the employment of <?= e($person['display_name']) ?>? Leave after the end date is cancelled.">
            <?= csrf_field() ?>
            <input type="hidden" name="person" value="<?= $id ?>">
            <div class="col-md-4">
                <label class="form-label fs-12 text-muted" for="person-employment-end-date">End date</label>
                <input type="date" name="end_date" id="person-employment-end-date" class="form-control" required value="<?= e(date('Y-m-d')) ?>">
            </div>
            <div class="col-md-5">
                <label class="form-label fs-12 text-muted" for="person-employment-end-reason">Reason</label>
                <input type="text" name="reason" id="person-employment-end-reason" class="form-control" maxlength="200" placeholder="resigned, contract ended…">
            </div>
            <div class="col-md-3">
                <button type="submit" class="btn btn-outline-danger w-100" id="person-employment-end-btn"><i class="feather-user-minus me-2"></i>End employment</button>
            </div>
        </form>
    <?php endif; ?>
<?php endif; ?>
</div>
