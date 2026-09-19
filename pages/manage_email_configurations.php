<?php
/** Kingsway System Administrator: Email Provider Configuration (system domain). */
if (!isset($appBase)) {
    $appBase = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '')), '/');
    if ($appBase === '.') { $appBase = ''; }
}
?>
<div class="container-fluid py-4">
    <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
        <div>
            <h2 class="h2 fw-bold mb-1"><i class="bi bi-envelope-gear me-2 text-success"></i>Email Mailbox Profiles</h2>
            <p class="text-muted mb-0">Configure per-department email mailboxes (@kingswaypreparatoryschool.sc.ke), SMTP/IMAP credentials, and role access.</p>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <button class="btn btn-success btn-sm" id="emailExportCsv"><i class="bi bi-filetype-csv me-1"></i>Export CSV</button>
            <button class="btn btn-outline-secondary btn-sm" id="emailPrint"><i class="bi bi-printer me-1"></i>Print/PDF</button>
            <button class="btn btn-success" id="emailNewProfile"><i class="bi bi-plus-circle me-1"></i>New Mailbox</button>
            <button class="btn btn-outline-success btn-sm" id="emailRefresh"><i class="bi bi-arrow-clockwise me-1"></i>Refresh</button>
        </div>
    </div>
    <div id="emailState" class="alert alert-info" role="status"><i class="bi bi-hourglass-split me-1"></i>Loading email profiles...</div>

    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-white d-flex justify-content-between align-items-center">
            <h6 class="mb-0 fw-semibold">Mailbox Profiles</h6>
        </div>
        <div class="card-body">
            <div class="table-responsive"><table class="table table-hover table-sm mb-0" id="emailProfilesTable">
                <thead class="table-light"><tr><th>Label</th><th>Email Address</th><th>Display Name</th><th>Status</th><th>Default</th><th>Roles</th><th>Actions</th></tr></thead>
                <tbody id="emailProfilesBody"><tr><td colspan="7" class="text-muted">Loading...</td></tr></tbody>
            </table></div>
        </div>
    </div>

    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-white d-flex justify-content-between align-items-center">
            <h6 class="mb-0 fw-semibold">Audit BCC (watchdog copy of user-sent email)</h6>
        </div>
        <div class="card-body">
            <div class="row g-3 align-items-end">
                <div class="col-md-4">
                    <label class="form-label fw-semibold">Audit BCC Email</label>
                    <input type="email" class="form-control" id="auditBccEmail" placeholder="preparatoryschoolkingsway@gmail.com">
                </div>
                <div class="col-md-3">
                    <div class="form-check form-switch mt-4">
                        <input class="form-check-input" type="checkbox" id="auditBccEnabled" checked>
                        <label class="form-check-label" for="auditBccEnabled">Enabled</label>
                    </div>
                </div>
                <div class="col-md-2"><button class="btn btn-success w-100" id="auditBccSaveBtn"><i class="bi bi-check-lg me-1"></i>Save</button></div>
                <div class="col-md-3"><div id="auditBccResult" class="text-muted small"></div></div>
            </div>
            <p class="text-muted small mt-2 mb-0"><i class="bi bi-info-circle me-1"></i>When enabled, emails a staff member composes and sends through the Communications Hub are also BCC'd to the watchdog address (default prepapartoryschoolkingsway@gmail.com). System-generated emails (OTP codes, password resets, invitation links, payment confirmations) are private between the recipient and the system and are never BCC'd.</p>
        </div>
    </div>

    <div class="mt-3"><button class="btn btn-success" id="emailTestBtn"><i class="bi bi-lightning me-1"></i>Test Global SMTP</button> <span id="emailTestResult" class="text-muted small ms-2"></span></div>
</div>

<!-- Profile create/edit modal -->
<div class="modal fade" id="emailProfileModal" tabindex="-1"><div class="modal-dialog modal-lg modal-dialog-scrollable"><div class="modal-content">
    <form id="emailProfileForm" novalidate>
        <div class="modal-header bg-success text-white"><h5 class="modal-title" id="emailProfileModalTitle">New Email Mailbox</h5><button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button></div>
        <div class="modal-body">
            <input type="hidden" id="emailProfileId">
            <div class="row g-3">
                <div class="col-md-4"><label class="form-label fw-semibold">Label <span class="text-danger">*</span></label><input type="text" class="form-control" id="emailProfileLabel" required maxlength="50" placeholder="Finance"></div>
                <div class="col-md-4"><label class="form-label fw-semibold">Email Address <span class="text-danger">*</span></label><input type="email" class="form-control" id="emailProfileAddress" required placeholder="finance@kingswaypreparatoryschool.sc.ke"></div>
                <div class="col-md-4"><label class="form-label fw-semibold">Display Name <span class="text-danger">*</span></label><input type="text" class="form-control" id="emailProfileDisplayName" required maxlength="100" placeholder="Kingsway Finance"></div>

                <div class="col-12"><hr class="my-1"><strong>SMTP (outgoing mail) — HostAfrica mail server</strong></div>
                <div class="col-md-6"><label class="form-label fw-semibold">SMTP Host</label><input type="text" class="form-control" id="emailProfileSmtpHost" placeholder="mail.kingswaypreparatoryschool.sc.ke"></div>
                <div class="col-md-6"><label class="form-label fw-semibold">SMTP Port</label><input type="number" class="form-control" id="emailProfileSmtpPort" value="587" min="1" max="65535"></div>
                <div class="col-md-6"><label class="form-label fw-semibold">SMTP Username (full email) <span class="text-danger">*</span></label><input type="text" class="form-control" id="emailProfileSmtpUsername" required placeholder="finance@kingswaypreparatoryschool.sc.ke"></div>
                <div class="col-md-6"><label class="form-label fw-semibold">SMTP Password <span class="text-danger">*</span></label><input type="password" class="form-control" id="emailProfileSmtpPassword" placeholder="Mailbox password"></div>

                <div class="col-12"><hr class="my-1"><strong>IMAP (incoming mail — inbox viewer)</strong></div>
                <div class="col-md-6"><label class="form-label fw-semibold">IMAP Host</label><input type="text" class="form-control" id="emailProfileImapHost" placeholder="mail.kingswaypreparatoryschool.sc.ke"></div>
                <div class="col-md-6"><label class="form-label fw-semibold">IMAP Port</label><input type="number" class="form-control" id="emailProfileImapPort" value="993" min="1" max="65535"></div>
                <div class="col-md-6"><label class="form-label fw-semibold">IMAP Username (full email)</label><input type="text" class="form-control" id="emailProfileImapUsername" placeholder="finance@kingswaypreparatoryschool.sc.ke"></div>
                <div class="col-md-6"><label class="form-label fw-semibold">IMAP Password</label><input type="password" class="form-control" id="emailProfileImapPassword" placeholder="Leave blank = use SMTP password"></div>

                <div class="col-12"><hr class="my-1"><strong>Settings</strong></div>
                <div class="col-md-4"><label class="form-label fw-semibold">Status</label><select class="form-select" id="emailProfileActive"><option value="1">Active</option><option value="0">Inactive</option></select></div>
                <div class="col-md-4"><label class="form-label fw-semibold">Set as Default</label><select class="form-select" id="emailProfileDefault"><option value="0">No</option><option value="1">Yes</option></select></div>
                <div class="col-md-4"><label class="form-label fw-semibold">Role Access (staff who view this inbox)</label><input type="text" class="form-control" id="emailProfileRoles" placeholder="Role IDs comma-separated, e.g. 10,11. Leave blank = all staff"></div>
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-outline-success btn-sm me-2" id="emailProfileTestSmtpBtn"><i class="bi bi-lightning me-1"></i>Test SMTP</button>
            <button type="button" class="btn btn-outline-success btn-sm me-auto" id="emailProfileTestImapBtn"><i class="bi bi-inbox me-1"></i>Test IMAP</button>
            <span id="emailProfileTestResult" class="text-muted small me-auto"></span>
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
            <button type="submit" class="btn btn-success" id="emailProfileSaveBtn"><i class="bi bi-check-lg me-1"></i>Save Mailbox</button>
        </div>
    </form>
</div></div></div>

<script src="<?= htmlspecialchars($appBase) ?>/js/pages/system/manage_email_configurations.js?v=<?= asset_version('js/pages/system/manage_email_configurations.js') ?>"></script>