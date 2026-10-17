<?php
/**
 * Manage SMS / Messaging Page
 * HTML structure only - logic will be in js/pages/manage_sms.js
 * Embedded in app_layout.php
 *
 * Provider-agnostic messaging console: SMS, WhatsApp, Voice, MMS, Hashed SMS,
 * Campaigns, Contact Groups, Contacts and the provider message log. Transport
 * is configured through SystemCommsConfigService (SMS_PROVIDER etc.).
 */
?>

<div class="card shadow-sm">
    <div class="card-header bg-gradient bg-info text-white">
        <div class="d-flex justify-content-between align-items-center">
            <h4 class="mb-0"><i class="bi bi-chat-dots"></i> Messaging Management</h4>
            <div class="btn-group">
                <button class="btn btn-light btn-sm" id="composeBtn" data-permission="sms_send">
                    <i class="bi bi-plus-circle"></i> Compose Message
                </button>
            </div>
        </div>
    </div>

    <div class="card-body">
        <ul class="nav nav-tabs mb-3" id="messagingTabs" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active" id="tab-messages" data-bs-toggle="tab" data-bs-target="#pane-messages" type="button" role="tab">
                    <i class="bi bi-chat-left-text"></i> Messages
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="tab-contacts" data-bs-toggle="tab" data-bs-target="#pane-contacts" type="button" role="tab">
                    <i class="bi bi-people"></i> Contacts &amp; Groups
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="tab-gateway" data-bs-toggle="tab" data-bs-target="#pane-gateway" type="button" role="tab">
                    <i class="bi bi-hdd-network"></i> Gateway
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="tab-log" data-bs-toggle="tab" data-bs-target="#pane-log" type="button" role="tab">
                    <i class="bi bi-journal-text"></i> Provider Log
                </button>
            </li>
        </ul>

        <div class="tab-content">
            <!-- Messages -->
            <div class="tab-pane fade show active" id="pane-messages" role="tabpanel">
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
                        <div class="card border-primary">
                            <div class="card-body text-center">
                                <h6 class="text-muted mb-2">SMS Balance</h6>
                                <h3 class="text-primary mb-0" id="smsBalance">0</h3>
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
                        <input type="text" class="form-control form-control-sm" id="recipientFilter" placeholder="Recipient...">
                    </div>
                    <div class="col-md-1">
                        <input type="date" class="form-control form-control-sm" id="dateFilter">
                    </div>
                    <div class="col-md-2">
                        <div class="btn-group btn-group-sm w-100">
                            <button class="btn btn-outline-secondary" id="clearFiltersBtn" title="Clear filters"><i class="bi bi-x-circle"></i></button>
                            <button class="btn btn-outline-success" id="exportSmsBtn" title="Export CSV"><i class="bi bi-filetype-csv"></i> CSV</button>
                            <button class="btn btn-outline-dark" id="printSmsBtn" title="Print"><i class="bi bi-printer"></i></button>
                        </div>
                    </div>
                </div>

                <!-- SMS Messages Table -->
                <div class="table-responsive">
                    <table class="table table-hover table-sm" id="messagesTable">
                        <thead class="table-light">
                            <tr>
                                <th scope="col">Date/Time</th>
                                <th scope="col">Type</th>
                                <th scope="col">Recipients</th>
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

            <!-- Contacts & Groups -->
            <div class="tab-pane fade" id="pane-contacts" role="tabpanel">
                <div class="row g-3">
                    <div class="col-lg-4">
                        <div class="card h-100">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <span class="fw-semibold"><i class="bi bi-collection"></i> Contact Groups</span>
                                <div class="btn-group btn-group-sm">
                                    <button class="btn btn-outline-secondary" id="syncGroupsBtn" title="Pull groups from provider"><i class="bi bi-arrow-down-circle"></i></button>
                                    <button class="btn btn-outline-secondary" id="provisionGroupsBtn" title="Create missing groups at provider"><i class="bi bi-cloud-upload"></i></button>
                                    <button class="btn btn-primary" id="addGroupBtn" title="New group"><i class="bi bi-plus-lg"></i></button>
                                </div>
                            </div>
                            <div class="list-group list-group-flush" id="groupsList">
                                <div class="text-center text-muted py-3 small">Loading groups...</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-8">
                        <div class="card h-100">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <span class="fw-semibold"><i class="bi bi-person-lines-fill"></i> Contacts <small class="text-muted" id="activeGroupLabel"></small></span>
                                <div class="btn-group btn-group-sm">
                                    <input type="text" class="form-control form-control-sm" id="contactFilter" placeholder="Search contacts..." style="max-width:200px;">
                                    <button class="btn btn-outline-secondary" id="importContactsBtn" title="Import members from the school directory"><i class="bi bi-download"></i> Import</button>
                                    <button class="btn btn-outline-secondary" id="syncContactsBtn" title="Push contacts to provider"><i class="bi bi-cloud-upload"></i></button>
                                    <button class="btn btn-primary" id="addContactBtn"><i class="bi bi-person-plus"></i> Add</button>
                                </div>
                            </div>
                            <div class="table-responsive">
                                <table class="table table-hover table-sm mb-0" id="contactsTable">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Name</th>
                                            <th>Phone</th>
                                            <th>Status</th>
                                            <th class="text-end">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr><td colspan="4" class="text-center text-muted py-4">Select a group to view contacts.</td></tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Gateway -->
            <div class="tab-pane fade" id="pane-gateway" role="tabpanel">
                <div class="row g-3">
                    <div class="col-md-6">
                        <div class="card h-100">
                            <div class="card-header fw-semibold"><i class="bi bi-hdd-network"></i> Provider</div>
                            <div class="card-body">
                                <dl class="row mb-0" id="gatewayInfo">
                                    <dd class="text-muted">Loading...</dd>
                                </dl>
                                <div class="d-flex gap-2 mt-3">
                                    <button class="btn btn-outline-primary btn-sm" id="refreshGatewayBtn"><i class="bi bi-arrow-clockwise"></i> Refresh</button>
                                    <button class="btn btn-outline-secondary btn-sm" id="refreshProfileBtn"><i class="bi bi-person-badge"></i> Load Profile</button>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card h-100">
                            <div class="card-header fw-semibold"><i class="bi bi-wallet2"></i> Balance &amp; Account</div>
                            <div class="card-body">
                                <div class="display-6" id="gatewayBalance">—</div>
                                <p class="text-muted small mb-2" id="gatewayBalanceMsg">Balance is read from the configured provider.</p>
                                <button class="btn btn-outline-primary btn-sm" id="refreshBalanceBtn"><i class="bi bi-arrow-clockwise"></i> Refresh Balance</button>
                                <div class="mt-3" id="profilePanel"></div>
                            </div>
                        </div>
                    </div>
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header fw-semibold"><i class="bi bi-cash-coin"></i> Top Up SMS Balance</div>
                            <div class="card-body">
                                <div class="row g-3">
                                    <div class="col-md-4">
                                        <div class="text-muted small">M-Pesa Paybill</div>
                                        <div class="fs-5 fw-semibold" id="smsTopupPaybill">—</div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="text-muted small">Account Number</div>
                                        <div class="fs-5 fw-semibold" id="smsTopupAccount">—</div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="text-muted small">Top-up Instructions</div>
                                        <div class="small" id="smsTopupNotes">Units are credited automatically after confirmation.</div>
                                    </div>
                                </div>
                                <p class="text-muted small mb-0 mt-3"><i class="bi bi-info-circle"></i> Pay through the provider's M-Pesa Paybill (or its STK option) using the account number above; the units are credited to this SMS account automatically. Refresh the balance to see the new units.</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Provider Log -->
            <div class="tab-pane fade" id="pane-log" role="tabpanel">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <span class="fw-semibold"><i class="bi bi-journal-text"></i> Provider Message Log</span>
                    <button class="btn btn-outline-primary btn-sm" id="refreshRemoteMsgsBtn"><i class="bi bi-arrow-clockwise"></i> Refresh</button>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover table-sm" id="remoteMessagesTable">
                        <thead class="table-light">
                            <tr>
                                <th>UID</th>
                                <th>Recipient</th>
                                <th>Status</th>
                                <th>Type</th>
                                <th>Sent</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr><td colspan="5" class="text-center text-muted py-4">Open this tab to load the provider log.</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Message Compose Modal -->
<div class="modal fade" id="composeModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-scrollable modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-info text-white">
                <h5 class="modal-title"><i class="bi bi-chat-dots"></i> Compose Message</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="composeForm">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label fw-semibold">Channel</label>
                                <select class="form-select" id="messageChannel">
                                    <option value="sms">SMS</option>
                                    <option value="whatsapp">WhatsApp</option>
                                    <option value="voice">Voice</option>
                                    <option value="mms">MMS</option>
                                    <option value="hashed">Hashed SMS</option>
                                    <option value="campaign">SMS Campaign</option>
                                </select>
                            </div>

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
                                <input type="text" class="form-control form-control-sm mb-2" id="contactSearch" placeholder="Search contacts...">
                                <select class="form-select" id="specificRecipients" multiple size="4"></select>
                            </div>

                            <div id="groupSelector" class="mb-3" style="display:none;">
                                <label class="form-label">Select Group</label>
                                <select class="form-select" id="groupSelect"></select>
                            </div>

                            <div id="customNumbersDiv" class="mb-3" style="display:none;">
                                <label class="form-label">Phone Numbers (comma separated)</label>
                                <textarea class="form-control" id="customNumbers" rows="2" placeholder="e.g. 0712345678, 0722345678"></textarea>
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
                                <textarea class="form-control" id="messageBody" rows="5" maxlength="1000" required></textarea>
                                <small class="text-muted">
                                    <span id="charCount">0</span>/160 characters |
                                    <span id="smsCount">0</span> SMS |
                                    Est. cost: KES <span id="estimatedCost">0.00</span>
                                </small>
                            </div>

                            <div id="mediaUrlDiv" class="mb-3" style="display:none;">
                                <label class="form-label fw-semibold">Media URL <span class="text-danger">*</span></label>
                                <input type="url" class="form-control" id="mediaUrl" placeholder="https://...">
                            </div>

                            <div id="voiceOptionsDiv" class="mb-3" style="display:none;">
                                <label class="form-label fw-semibold">Voice Options</label>
                                <div class="row g-2">
                                    <div class="col-6">
                                        <select class="form-select form-select-sm" id="voiceLanguage">
                                            <option value="en">English</option>
                                            <option value="sw">Swahili</option>
                                        </select>
                                    </div>
                                    <div class="col-6">
                                        <select class="form-select form-select-sm" id="voiceGender">
                                            <option value="female">Female</option>
                                            <option value="male">Male</option>
                                        </select>
                                    </div>
                                </div>
                            </div>

                            <div id="hashedOptionsDiv" class="mb-3" style="display:none;">
                                <label class="form-label fw-semibold">Recipient Format</label>
                                <select class="form-select form-select-sm" id="recipientFormat">
                                    <option value="hashed">Hashed</option>
                                    <option value="plain">Plain</option>
                                </select>
                            </div>

                            <div class="mb-3">
                                <label class="form-label fw-semibold">Sender Signature</label>
                                <input type="text" class="form-control" id="senderSignature" placeholder="e.g. Kingsway Preparatory School">
                            </div>

                            <div class="alert alert-info mb-0 py-2">
                                <small><i class="bi bi-info-circle"></i> Messages are delivered through the configured messaging provider. Standard rates apply.</small>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-info" id="sendBtn"><i class="bi bi-send"></i> Send Message</button>
            </div>
        </div>
    </div>
</div>

<!-- Group Modal -->
<div class="modal fade" id="groupModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-info text-white">
                <h5 class="modal-title" id="groupModalTitle"><i class="bi bi-collection"></i> Contact Group</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="groupEditId">
                <div class="mb-3">
                    <label class="form-label fw-semibold">Group Name <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="groupName" maxlength="120" required>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-info" id="saveGroupBtn">Save Group</button>
            </div>
        </div>
    </div>
</div>

<!-- Contact Modal -->
<div class="modal fade" id="contactModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-info text-white">
                <h5 class="modal-title" id="contactModalTitle"><i class="bi bi-person-plus"></i> Contact</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="contactEditId">
                <input type="hidden" id="contactGroupId">
                <div class="mb-3">
                    <label class="form-label fw-semibold">Phone <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="contactPhone" placeholder="07XXXXXXXX" required>
                </div>
                <div class="row g-2">
                    <div class="col-6">
                        <label class="form-label fw-semibold">First Name</label>
                        <input type="text" class="form-control" id="contactFirstName">
                    </div>
                    <div class="col-6">
                        <label class="form-label fw-semibold">Last Name</label>
                        <input type="text" class="form-control" id="contactLastName">
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-info" id="saveContactBtn">Save Contact</button>
            </div>
        </div>
    </div>
</div>

<script src="<?= $appBase ?>/js/pages/manage_sms.js?v=<?php echo asset_version('js/pages/manage_sms.js'); ?>"></script>
