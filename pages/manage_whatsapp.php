<?php
/**
 * Manage WhatsApp Page
 * HTML structure only - logic in js/pages/manage_whatsapp.js
 */
?>
<div class="card shadow-sm">
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
        <div class="row mb-3 g-2 align-items-end">
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
            <div class="col-md-2">
                <input type="date" class="form-control form-control-sm" id="dateFilter">
            </div>
            <div class="col-md-1">
                <button class="btn btn-outline-secondary btn-sm w-100" id="clearFiltersBtn" title="Clear filters"><i class="bi bi-x-circle"></i></button>
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
<div class="modal fade" id="composeModal" tabindex="-1">
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
                                    <option value="custom_numbers">Custom Numbers</option>
                                </select>
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
                                <label class="form-label fw-semibold">Message <span class="text-danger">*</span></label>
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
                                <small><i class="bi bi-info-circle"></i> WhatsApp messages are sent via Africa's Talking. Standard rates apply.</small>
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

<script src="<?= $appBase ?>/js/pages/manage_whatsapp.js?v=<?php echo time(); ?>"></script>
