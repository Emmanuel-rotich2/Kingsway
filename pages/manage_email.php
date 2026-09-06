<?php
/**
 * Manage Email Page
 * HTML structure only - logic will be in js/pages/manage_email.js
 * Embedded in app_layout.php
 */
?>

<div class="card shadow-sm">
    <div class="card-header bg-gradient bg-primary text-white">
        <div class="d-flex justify-content-between align-items-center">
            <h4 class="mb-0"><i class="bi bi-envelope"></i> Email Management</h4>
            <div class="btn-group">
                <button class="btn btn-light btn-sm" id="composeBtn" data-permission="email_send">
                    <i class="bi bi-plus-circle"></i> Compose Email
                </button>
                <button class="btn btn-outline-light btn-sm" id="templatesBtn">
                    <i class="bi bi-file-text"></i> Templates
                </button>
            </div>
        </div>
    </div>

    <div class="card-body">
        <!-- Email Stats -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="card border-success">
                    <div class="card-body text-center">
                        <h6 class="text-muted mb-2">Sent Today</h6>
                        <h3 class="text-success mb-0" id="sentToday">0</h3>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card border-warning">
                    <div class="card-body text-center">
                        <h6 class="text-muted mb-2">Pending</h6>
                        <h3 class="text-warning mb-0" id="pendingCount">0</h3>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card border-info">
                    <div class="card-body text-center">
                        <h6 class="text-muted mb-2">Scheduled</h6>
                        <h3 class="text-info mb-0" id="scheduledCount">0</h3>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card border-danger">
                    <div class="card-body text-center">
                        <h6 class="text-muted mb-2">Failed</h6>
                        <h3 class="text-danger mb-0" id="failedCount">0</h3>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filters -->
        <div class="row mb-3 g-2 align-items-end">
            <div class="col-md-3">
                <input type="text" class="form-control form-control-sm" id="searchFilter" placeholder="Search emails...">
            </div>
            <div class="col-md-2">
                <select class="form-select form-select-sm" id="statusFilter">
                    <option value="">All Status</option>
                    <option value="sent">Sent</option>
                    <option value="pending">Pending</option>
                    <option value="failed">Failed</option>
                    <option value="scheduled">Scheduled</option>
                    <option value="draft">Draft</option>
                </select>
            </div>
            <div class="col-md-2">
                <select class="form-select form-select-sm" id="categoryFilter">
                    <option value="">All Types</option>
                    <option value="announcement">Announcement</option>
                    <option value="reminder">Reminder</option>
                    <option value="invitation">Invitation</option>
                    <option value="results">Results</option>
                    <option value="fee_alert">Fee Alert</option>
                    <option value="emergency">Emergency</option>
                    <option value="report_card">Report Card</option>
                    <option value="general">General</option>
                </select>
            </div>
            <div class="col-md-2">
                <select class="form-select form-select-sm" id="recipientFilter">
                    <option value="">All Recipients</option>
                    <option value="parents">Parents</option>
                    <option value="staff">Staff</option>
                    <option value="students">Students</option>
                </select>
            </div>
            <div class="col-md-2">
                <input type="date" class="form-control form-control-sm" id="dateFilter">
            </div>
            <div class="col-md-1">
                <button class="btn btn-outline-secondary btn-sm w-100" id="clearFiltersBtn" title="Clear filters"><i class="bi bi-x-circle"></i></button>
            </div>
        </div>

        <!-- Email Messages Table -->
        <div class="table-responsive">
            <table class="table table-hover table-sm" id="messagesTable">
                <thead class="table-light">
                    <tr>
                        <th scope="col">Date/Time</th>
                        <th scope="col">Type</th>
                        <th scope="col">Recipients</th>
                        <th scope="col">Subject</th>
                        <th scope="col">Status</th>
                        <th scope="col">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <!-- Dynamic content -->
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <nav>
            <ul class="pagination justify-content-center pagination-sm" id="pagination"></ul>
        </nav>
    </div>
</div>

<!-- Email Compose Modal -->
<div class="modal fade" id="composeModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-scrollable modal-xl">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title"><i class="bi bi-envelope"></i> Compose Email</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="composeForm">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label fw-semibold">Message Type</label>
                                <select class="form-select" id="messageType">
                                    <option value="">Select type...</option>
                                    <option value="announcement">Announcement</option>
                                    <option value="reminder">Reminder</option>
                                    <option value="invitation">Invitation</option>
                                    <option value="results">Results</option>
                                    <option value="fee_alert">Fee Alert</option>
                                    <option value="emergency">Emergency</option>
                                    <option value="report_card">Report Card</option>
                                    <option value="general">General</option>
                                </select>
                            </div>

                            <div class="mb-3">
                                <label class="form-label fw-semibold">Recipients</label>
                                <select class="form-select" id="recipientType" required>
                                    <option value="">Select recipients...</option>
                                    <option value="all_parents">All Parents</option>
                                    <option value="all_staff">All Staff</option>
                                    <option value="all_students">All Students</option>
                                    <option value="specific_class">Specific Class</option>
                                    <option value="contact_group">Contact Group</option>
                                    <option value="specific_parents">Specific Parents</option>
                                    <option value="custom_emails">Custom Email Addresses</option>
                                </select>
                            </div>

                            <div id="recipientSelector" class="mb-3" style="display:none;">
                                <label class="form-label">Select Recipients</label>
                                <input type="text" class="form-control form-control-sm mb-2" id="contactSearch" placeholder="Search contacts...">
                                <select class="form-select" id="specificRecipients" multiple size="4"></select>
                            </div>

                            <div id="groupSelector" class="mb-3" style="display:none;">
                                <label class="form-label">Select Group</label>
                                <select class="form-select" id="groupSelect"></select>
                            </div>

                            <div id="customEmailsDiv" class="mb-3" style="display:none;">
                                <label class="form-label">Email Addresses (comma separated)</label>
                                <textarea class="form-control" id="customEmails" rows="2"
                                    placeholder="e.g., email1@example.com, email2@example.com"></textarea>
                            </div>

                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label fw-semibold">Schedule (optional)</label>
                                    <input type="datetime-local" class="form-control" id="scheduleTime">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Priority</label>
                                    <select class="form-select" id="emailPriority">
                                        <option value="normal">Normal</option>
                                        <option value="high">High</option>
                                        <option value="urgent">Urgent</option>
                                    </select>
                                </div>
                            </div>

                            <div class="mb-3">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="enableReminder">
                                    <label class="form-check-label fw-semibold" for="enableReminder">Send follow-up reminder</label>
                                </div>
                                <div id="reminderOptions" style="display:none;" class="mt-2">
                                    <div class="input-group input-group-sm">
                                        <input type="number" class="form-control" id="reminderAfter" value="24" min="1">
                                        <select class="form-select" id="reminderUnit" style="max-width:100px;">
                                            <option value="hours">Hours</option>
                                            <option value="days">Days</option>
                                        </select>
                                        <span class="input-group-text">after</span>
                                    </div>
                                </div>
                            </div>

                            <div class="form-check mb-3">
                                <input class="form-check-input" type="checkbox" id="trackOpens">
                                <label class="form-check-label" for="trackOpens">Track email opens</label>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label fw-semibold">Template <small class="text-muted">(optional)</small></label>
                                <select class="form-select" id="templateSelect">
                                    <option value="">No template</option>
                                </select>
                            </div>

                            <div class="mb-3">
                                <label class="form-label fw-semibold">Subject <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="emailSubject" required>
                            </div>

                            <div class="mb-3">
                                <label class="form-label fw-semibold">Message <span class="text-danger">*</span></label>
                                <textarea class="form-control" id="messageBody" rows="8" required></textarea>
                                <small class="text-muted">You can use HTML for formatting</small>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Attachments</label>
                                <input type="file" class="form-control form-control-sm" id="emailAttachments" multiple>
                                <small class="text-muted">Maximum 5 files, 10MB each</small>
                            </div>

                            <div class="mb-3">
                                <label class="form-label fw-semibold">Sender Signature</label>
                                <input type="text" class="form-control" id="senderSignature" placeholder="e.g. Kingsway Preparatory School">
                            </div>

                            <div class="alert alert-info mb-0 py-2">
                                <small><i class="bi bi-info-circle"></i> Emails are sent via configured SMTP. HTML formatting supported.</small>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-outline-primary" id="saveDraftBtn">Save Draft</button>
                <button type="button" class="btn btn-primary" id="sendBtn"><i class="bi bi-send"></i> Send Email</button>
            </div>
        </div>
    </div>
</div>

<script src="<?= $appBase ?>/js/pages/manage_email.js?v=<?php echo time(); ?>"></script>
