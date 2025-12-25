<?php
/**
 * Manage Email Page
 * HTML structure only - logic will be in js/pages/email.js
 * Embedded in app_layout.php
 */
?>

<div class="card shadow-sm">
    <div class="card-header bg-gradient bg-primary text-white">
        <div class="d-flex justify-content-between align-items-center">
            <h4 class="mb-0"><i class="fas fa-envelope"></i> Email Management</h4>
            <div class="btn-group">
                <button class="btn btn-light btn-sm" id="composeEmailBtn" data-permission="email_send">
                    <i class="bi bi-plus-circle"></i> Compose Email
                </button>
                <button class="btn btn-outline-light btn-sm" id="emailTemplatesBtn">
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
                        <h3 class="text-warning mb-0" id="pendingEmails">0</h3>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card border-info">
                    <div class="card-body text-center">
                        <h6 class="text-muted mb-2">Scheduled</h6>
                        <h3 class="text-info mb-0" id="scheduledEmails">0</h3>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card border-danger">
                    <div class="card-body text-center">
                        <h6 class="text-muted mb-2">Failed</h6>
                        <h3 class="text-danger mb-0" id="failedEmails">0</h3>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filters -->
        <div class="row mb-3">
            <div class="col-md-3">
                <input type="text" class="form-control" id="emailSearch" placeholder="Search emails...">
            </div>
            <div class="col-md-2">
                <select class="form-select" id="statusFilter">
                    <option value="">All Status</option>
                    <option value="sent">Sent</option>
                    <option value="pending">Pending</option>
                    <option value="failed">Failed</option>
                    <option value="scheduled">Scheduled</option>
                </select>
            </div>
            <div class="col-md-2">
                <select class="form-select" id="recipientTypeFilter">
                    <option value="">All Recipients</option>
                    <option value="students">Students</option>
                    <option value="parents">Parents</option>
                    <option value="staff">Staff</option>
                </select>
            </div>
            <div class="col-md-2">
                <input type="date" class="form-control" id="dateFilter">
            </div>
        </div>

        <!-- Email Messages Table -->
        <div class="table-responsive">
            <table class="table table-hover" id="emailTable">
                <thead class="table-light">
                    <tr>
                        <th>Date/Time</th>
                        <th>Recipients</th>
                        <th>Subject</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <!-- Dynamic content -->
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <nav>
            <ul class="pagination justify-content-center" id="emailPagination">
                <!-- Dynamic pagination -->
            </ul>
        </nav>
    </div>
</div>

<!-- Email Compose Modal -->
<div class="modal fade" id="emailModal" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Compose Email</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="emailForm">
                    <div class="row mb-3">
                        <div class="col-md-8">
                            <label class="form-label">Recipient Type*</label>
                            <select class="form-select" id="recipient_type" required>
                                <option value="">Select Recipients</option>
                                <option value="all_students">All Students</option>
                                <option value="all_parents">All Parents</option>
                                <option value="all_staff">All Staff</option>
                                <option value="specific_class">Specific Class</option>
                                <option value="specific_students">Specific Students</option>
                                <option value="custom_emails">Custom Email Addresses</option>
                            </select>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label">Template (Optional)</label>
                            <select class="form-select" id="email_template">
                                <option value="">No Template</option>
                                <option value="welcome">Welcome Email</option>
                                <option value="fee_reminder">Fee Reminder</option>
                                <option value="exam_notification">Exam Notification</option>
                                <option value="report_card">Report Card</option>
                            </select>
                        </div>
                    </div>

                    <div id="specificRecipientsDiv" style="display: none;">
                        <div class="mb-3">
                            <label class="form-label">Select Recipients</label>
                            <select class="form-select" id="specific_recipients" multiple></select>
                        </div>
                    </div>

                    <div id="customEmailsDiv" style="display: none;">
                        <div class="mb-3">
                            <label class="form-label">Email Addresses (comma separated)</label>
                            <textarea class="form-control" id="custom_emails" rows="2"
                                placeholder="e.g., email1@example.com, email2@example.com"></textarea>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Subject*</label>
                        <input type="text" class="form-control" id="email_subject" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Message*</label>
                        <textarea class="form-control" id="email_message" rows="10" required></textarea>
                        <small class="text-muted">You can use HTML for formatting</small>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Attachments</label>
                        <input type="file" class="form-control" id="email_attachments" multiple>
                        <small class="text-muted">Maximum 5 files, 10MB each</small>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Schedule (Optional)</label>
                            <input type="datetime-local" class="form-control" id="schedule_time">
                            <small class="text-muted">Leave empty to send immediately</small>
                        </div>

                        <div class="col-md-6 mb-3">
                            <label class="form-label">Priority</label>
                            <select class="form-select" id="email_priority">
                                <option value="normal">Normal</option>
                                <option value="high">High</option>
                                <option value="urgent">Urgent</option>
                            </select>
                        </div>
                    </div>

                    <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" id="track_opens">
                        <label class="form-check-label" for="track_opens">
                            Track email opens
                        </label>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-outline-primary" id="saveDraftBtn">Save Draft</button>
                <button type="button" class="btn btn-primary" id="sendEmailBtn">Send Email</button>
            </div>
        </div>
    </div>
</div>

<script>
    // Initialize email management when page loads
    document.addEventListener('DOMContentLoaded', function () {
        // TODO: Implement emailManagementController in js/pages/email.js
        console.log('Email Management page loaded');
    });
</script>