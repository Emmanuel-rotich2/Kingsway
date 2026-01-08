<?php

/**
 * Manage Communications Page
 * 
 * HTML structure only - all logic in js/pages/communications.js (communicationsController)
 * 
 * Role-based access:
 * - Secretary: Send messages to parents, view sent messages
 * - Headteacher: All communications, school-wide announcements
 * - Class Teacher: Send to own class parents only
 * - Accountant: Send fee reminders
 * - Admin: Full access, manage templates
 * - Director: View all, approve campaigns
 * 
 * Embedded in app_layout.php
 */

?>

<div class="card shadow-sm">
    <div class="card-header bg-gradient bg-info text-white">
        <div class="d-flex justify-content-between align-items-center">
            <h4 class="mb-0"><i class="bi bi-envelope-fill"></i> Communications Management</h4>
            <div class="btn-group">
                <!-- New Message - Most staff can send -->
                <button class="btn btn-light btn-sm" onclick="communicationsController.showComposeModal()"
                    data-permission="communications_create"
                    data-role="secretary,headteacher,class_teacher,accountant,bursar,admin">
                    <i class="bi bi-plus-circle"></i> New Message
                </button>
                <!-- Templates - Admin and Secretary -->
                <button class="btn btn-outline-light btn-sm" onclick="communicationsController.showTemplatesModal()"
                    data-permission="communications_templates"
                    data-role="secretary,headteacher,admin">
                    <i class="bi bi-file-text"></i> Templates
                </button>
                <!-- Campaigns - Leadership only -->
                <button class="btn btn-outline-light btn-sm" onclick="communicationsController.showCampaignModal()"
                    data-permission="communications_campaign"
                    data-role="headteacher,admin,director">
                    <i class="bi bi-megaphone"></i> Campaign
                </button>
                <!-- Bulk Export - Admin only -->
                <button class="btn btn-outline-light btn-sm" onclick="communicationsController.exportMessages()"
                    data-permission="communications_export"
                    data-role="admin,director">
                    <i class="bi bi-download"></i> Export
                </button>
            </div>
        </div>
    </div>

    <div class="card-body">
        <!-- Statistics Cards -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="card border-info">
                    <div class="card-body text-center">
                        <h6 class="text-muted mb-2">Total Sent</h6>
                        <h3 class="text-info mb-0" id="totalSentCount">0</h3>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card border-warning">
                    <div class="card-body text-center">
                        <h6 class="text-muted mb-2">Scheduled</h6>
                        <h3 class="text-warning mb-0" id="scheduledCount">0</h3>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card border-secondary">
                    <div class="card-body text-center">
                        <h6 class="text-muted mb-2">Drafts</h6>
                        <h3 class="text-secondary mb-0" id="draftsCount">0</h3>
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
        
        <!-- Cost Stats - Finance and Admin only -->
        <div class="row mb-4" data-role="accountant,bursar,director,admin">
            <div class="col-md-4">
                <div class="card border-primary">
                    <div class="card-body text-center">
                        <h6 class="text-muted mb-2">SMS Credits Used</h6>
                        <h3 class="text-primary mb-0" id="smsCreditsUsed">0</h3>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card border-success">
                    <div class="card-body text-center">
                        <h6 class="text-muted mb-2">Credits Remaining</h6>
                        <h3 class="text-success mb-0" id="creditsRemaining">0</h3>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card border-dark">
                    <div class="card-body text-center">
                        <h6 class="text-muted mb-2">This Month Cost</h6>
                        <h3 class="text-dark mb-0" id="monthCost">KES 0</h3>
                    </div>
                </div>
            </div>
        </div>

        <!-- Tab Navigation -->
        <ul class="nav nav-tabs mb-3" role="tablist">
            <li class="nav-item">
                <a class="nav-link active" data-bs-toggle="tab" href="#allMessages"
                    onclick="communicationsController.loadMessages('all')">
                    <i class="bi bi-list"></i> All Messages
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" data-bs-toggle="tab" href="#smsTab"
                    onclick="communicationsController.loadMessages('sms')">
                    <i class="bi bi-chat-dots"></i> SMS
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" data-bs-toggle="tab" href="#emailTab"
                    onclick="communicationsController.loadMessages('email')">
                    <i class="bi bi-envelope"></i> Email
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" data-bs-toggle="tab" href="#whatsappTab"
                    onclick="communicationsController.loadMessages('whatsapp')">
                    <i class="bi bi-whatsapp"></i> WhatsApp
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" data-bs-toggle="tab" href="#pushTab"
                    onclick="communicationsController.loadMessages('push')">
                    <i class="bi bi-bell"></i> Push Notifications
                </a>
            </li>
        </ul>

        <!-- Tab Content -->
        <div class="tab-content">
            <!-- All Messages Tab -->
            <div class="tab-pane fade show active" id="allMessages">
                <!-- Filters and Search -->
                <div class="row mb-3">
                    <div class="col-md-4">
                        <div class="input-group">
                            <span class="input-group-text"><i class="bi bi-search"></i></span>
                            <input type="text" id="searchMessages" class="form-control" placeholder="Search messages..."
                                onkeyup="communicationsController.searchMessages(this.value)">
                        </div>
                    </div>
                    <div class="col-md-2">
                        <select id="channelFilter" class="form-select"
                            onchange="communicationsController.filterByChannel(this.value)">
                            <option value="">All Channels</option>
                            <option value="sms">SMS</option>
                            <option value="email">Email</option>
                            <option value="whatsapp">WhatsApp</option>
                            <option value="push">Push</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <select id="statusFilter" class="form-select"
                            onchange="communicationsController.filterByStatus(this.value)">
                            <option value="">All Status</option>
                            <option value="sent">Sent</option>
                            <option value="scheduled">Scheduled</option>
                            <option value="draft">Draft</option>
                            <option value="failed">Failed</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <select id="recipientFilter" class="form-select"
                            onchange="communicationsController.filterByRecipient(this.value)">
                            <option value="">All Recipients</option>
                            <option value="all_parents">All Parents</option>
                            <option value="all_students">All Students</option>
                            <option value="all_staff">All Staff</option>
                            <option value="custom">Custom</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <input type="date" id="dateFilter" class="form-control"
                            onchange="communicationsController.filterByDate(this.value)">
                    </div>
                </div>

                <!-- Messages Table -->
                <div class="table-responsive" id="messagesTableContainer">
                    <table class="table table-hover table-striped">
                        <thead class="table-light">
                            <tr>
                                <th>
                                    <input type="checkbox" onclick="communicationsController.toggleSelectAll(this)">
                                </th>
                                <th>Subject/Message</th>
                                <th>Channel</th>
                                <th>Recipients</th>
                                <th>Scheduled/Sent</th>
                                <th>Status</th>
                                <th>Delivery Rate</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="messagesTableBody">
                            <tr>
                                <td colspan="8" class="text-center py-4">
                                    <div class="spinner-border text-info" role="status">
                                        <span class="visually-hidden">Loading...</span>
                                    </div>
                                    <p class="text-muted mt-2">Loading messages...</p>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <div class="d-flex justify-content-between align-items-center mt-3">
                    <div>
                        <span class="text-muted">Showing <span id="msgShowingFrom">0</span> to <span
                                id="msgShowingTo">0</span> of <span id="msgTotalRecords">0</span> messages</span>
                    </div>
                    <nav>
                        <ul class="pagination mb-0" id="messagesPagination"></ul>
                    </nav>
                </div>
            </div>

            <!-- SMS Tab -->
            <div class="tab-pane fade" id="smsTab">
                <div id="smsContainer">
                    <p class="text-muted">Loading SMS messages...</p>
                </div>
            </div>

            <!-- Email Tab -->
            <div class="tab-pane fade" id="emailTab">
                <div id="emailContainer">
                    <p class="text-muted">Loading emails...</p>
                </div>
            </div>

            <!-- WhatsApp Tab -->
            <div class="tab-pane fade" id="whatsappTab">
                <div id="whatsappContainer">
                    <p class="text-muted">Loading WhatsApp messages...</p>
                </div>
            </div>

            <!-- Push Notifications Tab -->
            <div class="tab-pane fade" id="pushTab">
                <div id="pushContainer">
                    <p class="text-muted">Loading push notifications...</p>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Compose Message Modal -->
<div class="modal fade" id="composeModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-info text-white">
                <h5 class="modal-title">Compose New Message</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form id="composeForm" enctype="multipart/form-data" onsubmit="communicationsController.sendMessage(event)">
                <div class="modal-body">
                    <input type="hidden" id="messageId">

                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label">Channel <span class="text-danger">*</span></label>
                            <select id="messageChannel" class="form-select" required>
                                <option value="">Select Channel</option>
                                <option value="sms">SMS</option>
                                <option value="email">Email</option>
                                <option value="whatsapp">WhatsApp</option>
                                <option value="push">Push Notification</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Recipients <span class="text-danger">*</span></label>
                            <select id="messageRecipients" class="form-select" required>
                                <option value="">Select Recipients</option>
                                <option value="all_parents">All Parents</option>
                                <option value="all_students">All Students</option>
                                <option value="all_staff">All Staff</option>
                                <option value="class">Specific Class</option>
                                <option value="custom">Custom List</option>
                            </select>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Subject</label>
                        <input type="text" id="messageSubject" class="form-control">
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Message <span class="text-danger">*</span></label>
                        <textarea id="messageBody" class="form-control" rows="6" required maxlength="1000"></textarea>
                        <small class="text-muted">Characters: <span id="charCount">0</span>/1000</small>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Attachments (Optional)</label>
                        <input type="file" id="messageAttachments" class="form-control" multiple accept=".pdf,.doc,.docx,.xls,.xlsx,.jpg,.jpeg,.png,.gif">
                        <small class="text-muted">Allowed: PDF, Word, Excel, Images. Max 5MB per file.</small>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Schedule Send (Optional)</label>
                        <input type="datetime-local" id="scheduledTime" class="form-control">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="communicationsController.saveDraft()">
                        <i class="bi bi-floppy"></i> Save Draft
                    </button>
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-info">
                        <i class="bi bi-send"></i> Send Now
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- View Message Details Modal -->
<div class="modal fade" id="viewMessageModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-secondary text-white">
                <h5 class="modal-title">Message Details</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="viewMessageContent">
                <!-- Dynamic content loaded here -->
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- Templates Modal -->
<div class="modal fade" id="templatesModal" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title">Message Templates</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="templatesContent">
                <div class="mb-3">
                    <button class="btn btn-primary btn-sm" onclick="communicationsController.showNewTemplateForm()">
                        <i class="bi bi-plus"></i> New Template
                    </button>
                </div>
                <div id="templatesListContainer">
                    <!-- Templates loaded here -->
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Campaign Modal -->
<div class="modal fade" id="campaignModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-warning text-white">
                <h5 class="modal-title">Create Campaign</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form id="campaignForm" onsubmit="communicationsController.createCampaign(event)">
                <div class="modal-body">
                    <div class="alert alert-info">
                        <i class="bi bi-info-circle"></i> Campaigns allow you to send messages to multiple channels at
                        once.
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Campaign Name</label>
                        <input type="text" id="campaignName" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Select Channels</label>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="campaignSMS" value="sms">
                            <label class="form-check-label" for="campaignSMS">SMS</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="campaignEmail" value="email">
                            <label class="form-check-label" for="campaignEmail">Email</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="campaignWhatsApp" value="whatsapp">
                            <label class="form-check-label" for="campaignWhatsApp">WhatsApp</label>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Target Audience</label>
                        <select id="campaignAudience" class="form-select" required>
                            <option value="">Select Audience</option>
                            <option value="all">Everyone</option>
                            <option value="parents">All Parents</option>
                            <option value="students">All Students</option>
                            <option value="staff">All Staff</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Message</label>
                        <textarea id="campaignMessage" class="form-control" rows="5" required></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-warning">
                        <i class="bi bi-megaphone"></i> Launch Campaign
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Link Controller Script -->
<script src="/Kingsway/js/pages/communications.js"></script>