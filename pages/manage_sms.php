<?php
/**
 * Manage SMS Page
 * HTML structure only - logic will be in js/pages/sms.js
 * Embedded in app_layout.php
 */
?>

<div class="card shadow-sm">
    <div class="card-header bg-gradient bg-info text-white">
        <div class="d-flex justify-content-between align-items-center">
            <h4 class="mb-0"><i class="fas fa-sms"></i> SMS Management</h4>
            <div class="btn-group">
                <button class="btn btn-light btn-sm" id="composeSMSBtn" data-permission="sms_send">
                    <i class="bi bi-plus-circle"></i> Compose SMS
                </button>
                <button class="btn btn-outline-light btn-sm" id="checkBalanceBtn">
                    <i class="bi bi-wallet2"></i> Check Balance
                </button>
            </div>
        </div>
    </div>

    <div class="card-body">
        <!-- SMS Stats -->
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
                        <h3 class="text-warning mb-0" id="pendingSMS">0</h3>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card border-danger">
                    <div class="card-body text-center">
                        <h6 class="text-muted mb-2">Failed</h6>
                        <h3 class="text-danger mb-0" id="failedSMS">0</h3>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card border-primary">
                    <div class="card-body text-center">
                        <h6 class="text-muted mb-2">SMS Balance</h6>
                        <h3 class="text-primary mb-0" id="smsBalance">0</h3>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filters -->
        <div class="row mb-3">
            <div class="col-md-3">
                <input type="text" class="form-control" id="smsSearch" placeholder="Search messages...">
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

        <!-- SMS Messages Table -->
        <div class="table-responsive">
            <table class="table table-hover" id="smsTable">
                <thead class="table-light">
                    <tr>
                        <th>Date/Time</th>
                        <th>Recipient Type</th>
                        <th>Recipients</th>
                        <th>Message</th>
                        <th>Status</th>
                        <th>Cost</th>
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
            <ul class="pagination justify-content-center" id="smsPagination">
                <!-- Dynamic pagination -->
            </ul>
        </nav>
    </div>
</div>

<!-- SMS Compose Modal -->
<div class="modal fade" id="smsModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Compose SMS</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="smsForm">
                    <div class="mb-3">
                        <label class="form-label">Recipient Type*</label>
                        <select class="form-select" id="recipient_type" required>
                            <option value="">Select Recipients</option>
                            <option value="all_students">All Students</option>
                            <option value="all_parents">All Parents</option>
                            <option value="all_staff">All Staff</option>
                            <option value="specific_class">Specific Class</option>
                            <option value="specific_students">Specific Students</option>
                            <option value="custom_numbers">Custom Numbers</option>
                        </select>
                    </div>

                    <div id="specificRecipientsDiv" style="display: none;">
                        <div class="mb-3">
                            <label class="form-label">Select Recipients</label>
                            <select class="form-select" id="specific_recipients" multiple></select>
                        </div>
                    </div>

                    <div id="customNumbersDiv" style="display: none;">
                        <div class="mb-3">
                            <label class="form-label">Phone Numbers (comma separated)</label>
                            <textarea class="form-control" id="custom_numbers" rows="3"
                                placeholder="e.g., 0712345678, 0722345678"></textarea>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Message*</label>
                        <textarea class="form-control" id="sms_message" rows="4" maxlength="160" required></textarea>
                        <small class="text-muted">
                            <span id="charCount">0</span>/160 characters |
                            <span id="smsCount">0</span> SMS |
                            Estimated cost: KES <span id="estimatedCost">0.00</span>
                        </small>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Schedule (Optional)</label>
                        <input type="datetime-local" class="form-control" id="schedule_time">
                        <small class="text-muted">Leave empty to send immediately</small>
                    </div>

                    <div class="alert alert-info">
                        <strong>Note:</strong> SMS will be sent from the school's registered sender ID.
                        Standard SMS rates apply.
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="sendSMSBtn">Send SMS</button>
            </div>
        </div>
    </div>
</div>

<script>
    // Initialize SMS management when page loads
    document.addEventListener('DOMContentLoaded', function () {
        // TODO: Implement smsManagementController in js/pages/sms.js
        console.log('SMS Management page loaded');
    });
</script>