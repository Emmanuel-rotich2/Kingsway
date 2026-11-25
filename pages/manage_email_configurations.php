<?php
/** Kingsway System Administrator: Email Provider Configuration (system domain). */

?>
<div class="container-fluid py-3">
    <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
        <div>
            <h2 class="mb-1"><i class="bi bi-envelope-gear me-2" aria-hidden="true"></i>Email mailbox profiles</h2>
            <p class="text-muted mb-0">Configure group email accounts (@kingswaypreparatoryschool.sc.ke) and their SMTP/IMAP connections. Assign staff separately on each account from Kingsway Mail.</p>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <button type="button" class="btn btn-outline-secondary btn-sm" id="emailExportCsv"><i class="bi bi-filetype-csv me-1" aria-hidden="true"></i>Export CSV</button>
            <button type="button" class="btn btn-outline-secondary btn-sm" id="emailPrint"><i class="bi bi-printer me-1" aria-hidden="true"></i>Print / PDF</button>
            <button type="button" class="btn btn-success btn-sm" id="emailNewProfile"><i class="bi bi-plus-circle me-1" aria-hidden="true"></i>New mailbox</button>
            <button type="button" class="btn btn-outline-success btn-sm" id="emailRefresh"><i class="bi bi-arrow-clockwise me-1" aria-hidden="true"></i>Refresh</button>
        </div>
    </div>
    <div id="emailState" class="alert alert-info" role="status" aria-live="polite"><i class="bi bi-hourglass-split me-1" aria-hidden="true"></i>Loading email profiles…</div>

    <div class="card mb-4">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h3 class="h6 mb-0">Mailbox profiles</h3>
        </div>
        <div class="card-body">
            <div class="table-responsive"><table class="table table-hover table-sm mb-0" id="emailProfilesTable">
                <caption class="visually-hidden">Email mailbox profiles</caption>
                <thead><tr><th scope="col">Label</th><th scope="col">Email address</th><th scope="col">Display name</th><th scope="col">Status</th><th scope="col">Default</th><th scope="col">Staff access</th><th scope="col">Actions</th></tr></thead>
                <tbody id="emailProfilesBody"><tr id="emailProfilesState"><td colspan="7" class="text-center text-muted py-3">Loading mailboxes…</td></tr></tbody>
            </table></div>
            <template id="emailProfileRowTemplate"><tr data-email-profile-row><td class="align-middle"><strong data-fill="label"></strong></td><td class="align-middle"><a data-fill="email"></a></td><td class="align-middle" data-fill="display-name"></td><td class="align-middle"><span class="badge" data-fill="status"></span></td><td class="align-middle"><span data-fill="default"></span></td><td class="align-middle"><span class="badge bg-info text-dark">Per-mailbox</span></td><td class="align-middle"><div class="d-flex flex-wrap gap-1"><button class="btn btn-sm btn-outline-primary" data-action="view" type="button" title="View inbox"><i aria-hidden="true" class="bi bi-envelope-open"></i></button><button class="btn btn-sm btn-outline-success" data-action="edit" type="button" title="Edit mailbox"><i aria-hidden="true" class="bi bi-pencil"></i></button><button class="btn btn-sm btn-outline-warning" data-action="set_default" type="button" title="Set as default"><i aria-hidden="true" class="bi bi-star"></i></button><button class="btn btn-sm btn-outline-info" data-action="test_smtp" type="button" title="Test SMTP"><i aria-hidden="true" class="bi bi-lightning"></i></button><button class="btn btn-sm btn-outline-info" data-action="test_imap" type="button" title="Test IMAP"><i aria-hidden="true" class="bi bi-inbox"></i></button><button class="btn btn-sm btn-outline-danger" data-action="delete" type="button" title="Delete mailbox"><i aria-hidden="true" class="bi bi-trash"></i></button></div></td></tr></template>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h3 class="h6 mb-0">Audit BCC (watchdog copy of user-sent email)</h3>
        </div>
        <div class="card-body">
            <div class="row g-3 align-items-end">
                <div class="col-md-4">
                    <label class="form-label" for="auditBccEmail">Audit BCC email</label>
                    <input type="email" class="form-control" id="auditBccEmail" placeholder="preparatoryschoolkingsway@gmail.com">
                </div>
                <div class="col-md-3">
                    <div class="form-check form-switch mt-4">
                        <input class="form-check-input" type="checkbox" id="auditBccEnabled" checked>
                        <label class="form-check-label" for="auditBccEnabled">Enabled</label>
                    </div>
                </div>
                <div class="col-md-2"><button type="button" class="btn btn-success btn-sm w-100" id="auditBccSaveBtn"><i class="bi bi-check-lg me-1" aria-hidden="true"></i>Save</button></div>
                <div class="col-md-3"><div id="auditBccResult" class="text-muted small" role="status" aria-live="polite"></div></div>
            </div>
            <p class="text-muted small mt-2 mb-0"><i aria-hidden="true" class="bi bi-info-circle me-1"></i>When enabled, emails a staff member composes and sends through the Communications Hub are also BCC'd to the watchdog address (default prepapartoryschoolkingsway@gmail.com). System-generated emails (OTP codes, password resets, invitation links, payment confirmations) are private between the recipient and the system and are never BCC'd.</p>
        </div>
    </div>

    <div class="mt-3 d-flex flex-wrap align-items-center gap-2"><button type="button" class="btn btn-outline-success btn-sm" id="emailTestBtn"><i class="bi bi-lightning me-1" aria-hidden="true"></i>Test global SMTP</button> <span id="emailTestResult" class="text-muted small" role="status" aria-live="polite"></span></div>
</div>

<!-- Profile create/edit modal -->
<div class="modal fade" id="emailProfileModal" tabindex="-1"><div class="modal-dialog modal-lg modal-dialog-scrollable"><div class="modal-content">
    <form id="emailProfileForm" novalidate>
        <div class="modal-header"><h5 class="modal-title" id="emailProfileModalTitle">New email mailbox</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
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
                <div class="col-md-4">
                    <label class="form-label fw-semibold">Staff access</label>
                    <div class="form-control bg-light" aria-readonly="true">Managed per mailbox in Kingsway Mail</div>
                    <small class="form-text">Save this mailbox, then open Kingsway Mail and use its shield button to add or remove staff for this account. Repeat for each group mailbox.</small>
                </div>
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-outline-success btn-sm me-2" id="emailProfileTestSmtpBtn"><i class="bi bi-lightning me-1" aria-hidden="true"></i>Test SMTP</button>
            <button type="button" class="btn btn-outline-success btn-sm me-auto" id="emailProfileTestImapBtn"><i class="bi bi-inbox me-1" aria-hidden="true"></i>Test IMAP</button>
            <span id="emailProfileTestResult" class="text-muted small me-auto" role="status" aria-live="polite"></span>
            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
            <button type="submit" class="btn btn-success" id="emailProfileSaveBtn"><i class="bi bi-check-lg me-1" aria-hidden="true"></i>Save mailbox</button>
        </div>
    </form>
</div></div></div>

<script src="<?= htmlspecialchars($appBase) ?>/js/pages/system/manage_email_configurations.js?v=<?= asset_version('js/pages/system/manage_email_configurations.js') ?>"></script>
