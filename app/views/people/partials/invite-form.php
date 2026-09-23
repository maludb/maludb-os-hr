<?php /** Data: errors, staff, departments, values */ $v = $values; ?>
<div class="page-header" id="invite-form-header">
    <div class="page-header-left d-flex align-items-center">
        <div class="page-header-title"><h5 class="m-b-10">Invite someone</h5></div>
        <ul class="breadcrumb">
            <li class="breadcrumb-item"><a href="/people/" hx-get="/people/" hx-target="#page-content" hx-push-url="/people/">People</a></li>
            <li class="breadcrumb-item">Invite</li>
        </ul>
    </div>
    <div class="page-header-right ms-auto">
        <div class="page-header-right-items">
            <div class="d-flex d-md-none"><a href="javascript:void(0)" class="page-header-right-close-toggle"><i class="feather-arrow-left me-2"></i><span>Back</span></a></div>
            <div class="d-flex align-items-center gap-2 page-header-right-items-wrapper">
                <a href="/people/" id="invite-form-cancel-btn" class="btn btn-light-brand" hx-get="/people/" hx-target="#page-content" hx-push-url="/people/"><i class="feather-x me-2"></i><span>Cancel</span></a>
                <button type="submit" form="invite-form" id="invite-form-save-btn" class="btn btn-primary"><i class="feather-send me-2"></i><span>Send the invitation</span></button>
            </div>
        </div>
        <div class="d-md-none d-flex align-items-center"><a href="javascript:void(0)" class="page-header-right-open-toggle"><i class="feather-align-right fs-20"></i></a></div>
    </div>
</div>
<div class="main-content" id="invite-form-content">
    <div class="row"><div class="col-lg-8">
        <div class="card stretch stretch-full" id="invite-form-container">
            <div class="card-body">
                <p class="text-muted fs-12">The kernel sends the invitation from the business; the person appears in HR once they register. A department admin invites into the departments they administer.</p>
                <?= view('shared/validation-errors.php', ['errors' => $errors]) ?>
                <form method="post" action="/people/invite.php" id="invite-form" hx-post="/people/invite.php" hx-target="#page-content" hx-swap="innerHTML" hx-confirm="Send the invitation?">
                    <?= csrf_field() ?>
                    <div class="row mb-3" id="invite-form-field-email-row">
                        <label for="invite-form-field-email" class="col-lg-4 col-form-label fs-12 text-muted">Email</label>
                        <div class="col-lg-8"><div class="input-group"><span class="input-group-text"><i class="feather-mail"></i></span><input type="email" name="email" id="invite-form-field-email" class="form-control" required value="<?= e($v['email']) ?>"></div></div>
                    </div>
                    <div class="row mb-3" id="invite-form-field-department-row">
                        <label for="invite-form-field-department" class="col-lg-4 col-form-label fs-12 text-muted">Department</label>
                        <div class="col-lg-8"><select name="department" id="invite-form-field-department" class="form-select" required>
                            <option value="">Choose…</option>
                            <?php foreach ($departments as $d): ?><option value="<?= (int) $d['department_id'] ?>" <?= (int) ($v['department_id'] ?? 0) === (int) $d['department_id'] ? 'selected' : '' ?>><?= e($d['name']) ?></option><?php endforeach; ?>
                        </select></div>
                    </div>
                    <div class="row mb-3" id="invite-form-field-business_role-row">
                        <label for="invite-form-field-business_role" class="col-lg-4 col-form-label fs-12 text-muted">Role</label>
                        <div class="col-lg-8"><select name="business_role" id="invite-form-field-business_role" class="form-select">
                            <option value="user" <?= $v['business_role'] === 'user' ? 'selected' : '' ?>>User</option>
                            <?php if ($staff): ?><option value="dept_admin" <?= $v['business_role'] === 'dept_admin' ? 'selected' : '' ?>>Department admin</option><?php endif; ?>
                        </select></div>
                    </div>
                    <div class="row mb-3" id="invite-form-field-message-row">
                        <label for="invite-form-field-message" class="col-lg-4 col-form-label fs-12 text-muted">Message</label>
                        <div class="col-lg-8"><textarea name="message" id="invite-form-field-message" class="form-control" rows="3" maxlength="500" placeholder="A line for the invitation email"><?= e($v['message']) ?></textarea></div>
                    </div>
                </form>
            </div>
        </div>
    </div></div>
</div>
