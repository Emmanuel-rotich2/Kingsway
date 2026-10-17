<?php
/**
 * Manage WhatsApp Page
 * HTML structure only - logic in js/pages/manage_whatsapp.js
 */
?>
<style>
  .kw-wa {
    --wa-green: #008069;
    --kw-wa-hdr: #008069;
    --wa-teal: #00a884;
    --wa-bg: #efeae2;
    --kw-wa-border: #d1d7db;
    border: none;
    overflow: hidden;
  }
  .kw-wa .kw-wa-header {
    background: #008069;
    color: #fff;
    border-radius: 12px 12px 0 0;
    padding: 14px 18px;
  }
  .kw-wa .kw-wa-header h4 { font-weight: 500; }
  .kw-wa .kw-wa-body { background: #f0f2f5; }
  .kw-wa .kw-wa-stat { border: none; border-radius: 10px; background: #fff; box-shadow: 0 1px 2px rgba(11,20,26,.1); }
  .kw-wa .kw-wa-stat .card-body { padding: .85rem 1rem; }
  .kw-wa .kw-wa-stat h3 { font-weight: 600; }
  .kw-wa .form-control, .kw-wa .form-select { border-radius: 8px; }
  .kw-wa #messagesTable thead th { background: #f0f2f5; color: #54656f; font-size: .78rem; text-transform: uppercase; letter-spacing: .04em; border-bottom: 1px solid #e9edef; }
  .kw-wa #messagesTable td { vertical-align: middle; }
  .kw-wa .btn-success { background-color: #008069; border-color: #008069; }
  .kw-wa .btn-outline-success { color: #008069; border-color: #008069; }
  .kw-wa .kw-wa-search .form-control,
  .kw-wa .kw-wa-search .form-select { border-radius: 20px; }
  .kw-wa-compose textarea#messageBody { background: #efeae2; border-radius: 12px; }
</style>
<div class="card shadow-sm kw-wa">
    <div class="card-header bg-gradient bg-success text-white">
        <div class="d-flex justify-content-between align-items-center">
            <h4 class="mb-0"><i class="bi bi-whatsapp"></i> WhatsApp Messaging</h4>
            <div class="btn-group">
                <button class="btn btn-light btn-sm" id="composeBtn" data-permission="whatsapp_send">
                    <i class="bi bi-plus-circle"></i> New Message
                </button>
            </div>
        </div>
    </div>

    <div class="card-body">
        <!-- Stats -->
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
                <div class="card border-danger">
                    <div class="card-body text-center">
                        <h6 class="text-muted mb-2">Failed</h6>
                        <h3 class="text-danger mb-0" id="failedCount">0</h3>
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
        </div>

        <!-- Filters -->
        <div class="row mb-3 g-2 align-items-end kw-wa-search">
            <div class="col-md-3">
                <input type="text" class="form-control form-control-sm" id="searchFilter" placeholder="Search messages...">
            </div>
            <div class="col-md-2">
                <select class="form-select form-select-sm" id="statusFilter">
                    <option value="">All Status</option>
                    <option value="sent">Sent</option>
                    <option value="delivered">Delivered</option>
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
            <div class="col-md-1">
                <input type="date" class="form-control form-control-sm" id="dateFilter">
            </div>
            <div class="col-md-2">
                <div class="btn-group btn-group-sm w-100">
                    <button class="btn btn-outline-secondary" id="clearFiltersBtn" title="Clear filters"><i class="bi bi-x-circle"></i></button>
                    <button class="btn btn-outline-success" id="exportWaBtn" title="Export CSV"><i class="bi bi-filetype-csv"></i> CSV</button>
                    <button class="btn btn-outline-dark" id="printWaBtn" title="Print"><i class="bi bi-printer"></i></button>
                </div>
            </div>
        </div>

        <!-- Messages Table -->
        <div class="table-responsive">
            <table class="table table-hover table-sm" id="messagesTable">
                <thead class="table-light">
                    <tr>
                        <th scope="col">Date/Time</th>
                        <th scope="col">Type</th>
                        <th scope="col">Recipient</th>
                        <th scope="col">Message</th>
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

<!-- Compose Modal -->
<div class="modal fade kw-wa-compose" id="composeModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-scrollable modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title"><i class="bi bi-whatsapp"></i> Send WhatsApp Message</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="composeForm">
                    <div class="row">
                        <!-- Left column: recipient info -->
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label fw-semibold">Message Type</label>
                                <select class="form-select" id="messageType" required>
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
                                    <option value="selected_staff">Selected Teachers/Staff</option>
                                    <option value="selected_students">Parents of Selected Learners</option>
                                    <option value="selected_class">Parents in Selected Class</option>
                                    <option value="student_type">Parents of Selected Student Type</option>
                                    <option value="school_level">Parents in Selected School Level</option>
                                    <option value="contact_group">Contact Group</option>
                                    <option value="selected_parents">Selected Parents</option>
                                    <option value="selected_vendors">Selected Vendors</option>
                                    <option value="all_vendors">All Vendors</option>
                                    <option value="custom_selection">Custom Selection (mix learners, parents, staff, classes…)</option>
                                    <option value="custom_numbers">Custom Numbers</option>
                                </select>
                            </div>

                            <div id="recipientPickerDiv" class="mb-3" style="display:none;">
                                <label class="form-label">Select Recipients</label>
                                <div id="recipientPicker"></div>
                            </div>

                            <div id="recipientSelector" class="mb-3" style="display:none;">
                                <label class="form-label">Select Recipients</label>
                                <input type="text" class="form-control mb-2" id="contactSearch" placeholder="Search contacts...">
                                <select class="form-select" id="specificRecipients" multiple size="4"></select>
                            </div>

                            <div id="groupSelector" class="mb-3" style="display:none;">
                                <label class="form-label">Select Group</label>
                                <select class="form-select" id="groupSelect"></select>
                            </div>

                            <div id="customNumbersDiv" class="mb-3" style="display:none;">
                                <label class="form-label">Phone Numbers (comma separated, include country code)</label>
                                <textarea class="form-control" id="customNumbers" rows="2" placeholder="e.g. 254712345678, 254723456789"></textarea>
                            </div>

                            <div class="mb-3">
                                <label class="form-label fw-semibold">Schedule (optional)</label>
                                <input type="datetime-local" class="form-control" id="scheduleTime">
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
                        </div>

                        <!-- Right column: message content -->
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label fw-semibold">Template <small class="text-muted">(optional)</small></label>
                                <select class="form-select" id="templateSelect">
                                    <option value="">No template</option>
                                </select>
                            </div>

                            <div class="mb-3">
                                <div class="d-flex justify-content-between align-items-center">
                                    <label class="form-label fw-semibold mb-0">Message <span class="text-danger">*</span></label>
                                    <div class="dropdown">
                                        <button class="btn btn-link btn-sm p-0 dropdown-toggle" type="button" data-bs-toggle="dropdown">Insert variable</button>
                                        <ul class="dropdown-menu dropdown-menu-end" id="messageVariableMenu"></ul>
                                    </div>
                                </div>
                                <textarea class="form-control" id="messageBody" rows="6" required></textarea>
                                <small class="text-muted"><span id="charCount">0</span> characters</small>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Media Attachment <small class="text-muted">(optional)</small></label>
                                <input type="file" class="form-control form-control-sm" id="mediaAttachment" accept="image/*,application/pdf">
                            </div>

                            <div class="mb-3">
                                <label class="form-label fw-semibold">Sender Signature</label>
                                <input type="text" class="form-control" id="senderSignature" placeholder="e.g. Kingsway Preparatory School">
                            </div>

                            <div class="alert alert-info mb-0 py-2">
                                <small><i class="bi bi-info-circle"></i> WhatsApp messages are sent via Talksasa. Standard rates apply.</small>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-success" id="sendBtn"><i class="bi bi-send"></i> Send Now</button>
            </div>
        </div>
    </div>
</div>

<script src="<?= $appBase ?>/js/pages/manage_whatsapp.js?v=<?php echo asset_version('js/pages/manage_whatsapp.js'); ?>"></script>
