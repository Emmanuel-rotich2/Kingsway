<?php
/**
 * Manage Communications Hub - Landing Page
 * Modern dashboard showing overview of all communication channels.
 * Embedded via home.php?route=manage_communications.
 */
?>

<div class="row g-3">
    <div class="col-12">
        <div class="app-page-header d-flex flex-wrap align-items-center justify-content-between gap-3">
            <div class="d-flex align-items-center gap-3">
                <div class="app-page-icon bg-primary-subtle text-primary-emphasis"><i class="bi bi-chat-square-text"></i></div>
                <div>
                    <h4 class="app-page-title mb-0">Communications Hub</h4>
                    <span class="app-page-subtitle">Manage messages, announcements, and notifications across all channels</span>
                </div>
            </div>
            <div class="d-flex gap-2">
                <button class="btn btn-outline-primary btn-sm" id="createInternalRequestBtn">
                    <i class="bi bi-plus-lg me-1"></i>New Request
                </button>
                <button class="btn btn-success btn-sm" id="composeMessageBtn">
                    <i class="bi bi-envelope me-1"></i>Compose
                </button>
                <button class="btn btn-outline-success btn-sm" id="aiDraftMessageBtn">
                    <i class="bi bi-stars me-1"></i>Draft with assistant
                </button>
            </div>
        </div>
    </div>

    <div class="col-12">
        <div class="app-panel border-success-subtle">
            <div class="d-flex align-items-center justify-content-between mb-3">
                <div>
                    <h6 class="mb-1"><i class="bi bi-stars me-2 text-success"></i>Communication assistant</h6>
                    <small class="text-muted">AI drafts are saved into the existing communications workflow and require a second staff approval.</small>
                </div>
                <button class="btn btn-sm btn-outline-secondary" id="refreshAiCommunicationDrafts">Refresh</button>
            </div>
            <div id="aiCommunicationDrafts" class="row g-3">
                <div class="col-12 text-muted small">No assistant drafts loaded.</div>
            </div>
        </div>
    </div>

    <!-- Stat cards -->
    <div class="col-6 col-md-3">
        <div class="app-stat-card">
            <div class="app-stat-icon text-primary bg-primary-subtle"><i class="bi bi-chat-left-text"></i></div>
            <div>
                <div class="app-stat-value" id="totalCommunications">0</div>
                <div class="app-stat-label">Messages</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="app-stat-card">
            <div class="app-stat-icon text-success bg-success-subtle"><i class="bi bi-bell"></i></div>
            <div>
                <div class="app-stat-value" id="totalAnnouncements">0</div>
                <div class="app-stat-label">Announcements</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="app-stat-card">
            <div class="app-stat-icon text-warning bg-warning-subtle"><i class="bi bi-audio-track"></i></div>
            <div>
                <div class="app-stat-value" id="unreadConversations">0</div>
                <div class="app-stat-label">Unread</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="app-stat-card">
            <div class="app-stat-icon text-info bg-info-subtle"><i class="bi bi-calendar-event"></i></div>
            <div>
                <div class="app-stat-value" id="scheduledCount">0</div>
                <div class="app-stat-label">Scheduled</div>
            </div>
        </div>
    </div>

    <!-- Channel cards -->
    <div class="col-12">
        <div class="app-panel">
            <h6 class="mb-3"><i class="bi bi-grid me-2 text-primary"></i>Communication Channels</h6>
            <div class="row g-3">
                <div class="col-md-6 col-lg-4">
                    <a href="<?= $appBase ?>/home.php?route=communications/messages_inbox" class="text-decoration-none">
                        <div class="card h-100 border-0 shadow-sm channel-card" data-channel="messages">
                            <div class="card-body">
                                <div class="d-flex align-items-start justify-content-between mb-2">
                                    <h6 class="card-title mb-0"><i class="bi bi-chat-text me-2"></i>Messages</h6>
                                    <span class="badge bg-light text-dark border" id="messagesCount">0</span>
                                </div>
                                <p class="card-text text-muted small">Internal staff-to-staff forum</p>
                                <small class="text-muted"><i class="bi bi-arrow-right-circle"></i> Go to messages</small>
                            </div>
                        </div>
                    </a>
                </div>
                <div class="col-md-6 col-lg-4">
                    <a href="<?= $appBase ?>/home.php?route=manage_announcements" class="text-decoration-none">
                        <div class="card h-100 border-0 shadow-sm channel-card" data-channel="announcements">
                            <div class="card-body">
                                <div class="d-flex align-items-start justify-content-between mb-2">
                                    <h6 class="card-title mb-0"><i class="bi bi-megaphone me-2"></i>Announcements</h6>
                                    <span class="badge bg-success-subtle text-success-emphasis" id="announcementsCount">0</span>
                                </div>
                                <p class="card-text text-muted small">School-wide public notices</p>
                                <small class="text-muted"><i class="bi bi-arrow-right-circle"></i> Go to announcements</small>
                            </div>
                        </div>
                    </a>
                </div>
                <div class="col-md-6 col-lg-4">
                    <a href="<?= $appBase ?>/home.php?route=manage_sms" class="text-decoration-none">
                        <div class="card h-100 border-0 shadow-sm channel-card" data-channel="sms">
                            <div class="card-body">
                                <div class="d-flex align-items-start justify-content-between mb-2">
                                    <h6 class="card-title mb-0"><i class="bi bi-chat-dots me-2"></i> SMS</i></h6>
                                    <span class="badge bg-info text-white border" id="smsCount">0</span>
                                </div>
                                <p class="card-text text-muted small">Parent and staff SMS messages</p>
                                <small class="text-muted"><i class="bi bi-arrow-right-circle"></i> Go to SMS</small>
                            </div>
                        </div>
                    </a>
                </div>
                <div class="col-md-6 col-lg-4">
                    <a href="<?= $appBase ?>/home.php?route=manage_email" class="text-decoration-none">
                        <div class="card h-100 border-0 shadow-sm channel-card" data-channel="email">
                            <div class="card-body">
                                <div class="d-flex align-items-start justify-content-between mb-2">
                                    <h6 class="card-title mb-0"><i class="bi bi-envelope me-2"></i>Email</h6>
                                    <span class="badge bg-secondary text-white border" id="emailCount">0</span>
                                </div>
                                <p class="card-text text-muted small">Official school email communications</p>
                                <small class="text-muted"><i class="bi bi-arrow-right-circle"></i> Go to email</small>
                            </div>
                        </div>
                    </a>
                </div>
                <div class="col-md-6 col-lg-4">
                    <a href="<?= $appBase ?>/home.php?route=manage_whatsapp" class="text-decoration-none">
                        <div class="card h-100 border-0 shadow-sm channel-card" data-channel="whatsapp">
                            <div class="card-body">
                                <div class="d-flex align-items-start justify-content-between mb-2">
                                    <h6 class="card-title mb-0"><i class="bi bi-brushes me-2"></i>WhatsApp</h6>
                                    <span class="badge bg-success text-white border" id="whatsappCount">0</span>
                                </div>
                                <p class="card-text text-muted small">Parent portal WhatsApp messages</p>
                                <small class="text-muted"><i class="bi bi-arrow-right-circle"></i> Go to WhatsApp</small>
                            </div>
                        </div>
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Communication Templates -->
    <div class="col-12 mb-3">
        <div class="app-panel">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h6 class="mb-0"><i class="bi bi-file-earmark-text me-2 text-primary"></i>Communication Templates</h6>
                <button class="btn btn-sm btn-primary" id="newTemplateBtn"><i class="bi bi-plus-lg"></i> New Template</button>
            </div>
            <div class="row mb-3">
                <div class="col-md-3">
                    <select class="form-select form-select-sm" id="templateFilterChannel">
                        <option value="">All Channels</option>
                        <option value="sms">SMS</option>
                        <option value="whatsapp">WhatsApp</option>
                        <option value="email">Email</option>
                        <option value="portal">Portal</option>
                        <option value="in_app">In-App</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <select class="form-select form-select-sm" id="templateFilterStatus">
                        <option value="">All Statuses</option>
                        <option value="active">Active</option>
                        <option value="draft">Draft</option>
                        <option value="inactive">Inactive</option>
                        <option value="archived">Archived</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <input type="text" class="form-control form-control-sm" id="templateFilterSearch" placeholder="Search code, name, purpose...">
                </div>
                <div class="col-md-2">
                    <button class="btn btn-outline-secondary btn-sm w-100" id="templateClearFilters"><i class="bi bi-x-circle"></i> Clear</button>
                </div>
            </div>
            <div class="table-responsive">
                <table class="table table-hover table-sm align-middle mb-0" id="templatesTable">
                    <thead class="table-light">
                        <tr>
                            <th>Code</th>
                            <th>Name</th>
                            <th>Purpose</th>
                            <th>Channels</th>
                            <th>Status</th>
                            <th>Version</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody id="templateManagerBody">
                        <tr><td colspan="7" class="text-center py-4 text-muted">Loading templates…</td></tr>
                    </tbody>
                </table>
            </div>
            <nav aria-label="Templates pagination" class="mt-2">
                <ul class="pagination pagination-sm justify-content-center" id="templatePagination"></ul>
            </nav>
        </div>
    </div>

    <!-- Recent activity -->
    <div class="col-12">
        <div class="app-panel">
            <div class="d-flex align-items-center justify-content-between mb-3">
                <h6 class="mb-0"><i class="bi bi-clock-history me-2 text-primary"></i>Recent Activity</h6>
                <button class="btn btn-sm btn-outline-primary" id="refreshActivities">
                    <i class="bi bi-arrow-clockwise"></i> Refresh
                </button>
            </div>
            <div id="recentActivities">
                <div class="table-responsive">
                    <table class="table table-hover table-sm mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Subject</th>
                                <th>Channel</th>
                                <th>Status</th>
                                <th class="d-none d-md-table-cell">Created</th>
                            </tr>
                        </thead>
                        <tbody id="activitiesTablebody"></tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="aiCommunicationDraftModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-stars text-success me-2"></i>Prepare communication draft</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-info small">Provide approved facts only. Do not enter learner names, phone numbers, email addresses, medical information, or confidential case details.</div>
                <form id="aiCommunicationDraftForm" class="row g-3">
                    <div class="col-md-6"><label class="form-label">Purpose</label><input class="form-control" name="purpose" maxlength="200" placeholder="e.g. term opening reminder" required></div>
                    <div class="col-md-6"><label class="form-label">Audience</label><select class="form-select" name="audience" required><option value="all_parents">All parents</option><option value="selected_parents">Selected parents</option><option value="selected_class">Selected class</option><option value="all_staff">All staff</option><option value="selected_staff">Selected staff</option></select></div>
                    <div class="col-md-4"><label class="form-label">Channel</label><select class="form-select" name="channel"><option value="email">Email</option><option value="sms">SMS</option><option value="whatsapp">WhatsApp</option><option value="internal">Internal</option></select></div>
                    <div class="col-md-4"><label class="form-label">Tone</label><select class="form-select" name="tone"><option value="professional">Professional</option><option value="warm">Warm</option><option value="urgent">Urgent</option><option value="brief">Brief</option></select></div>
                    <div class="col-md-4"><label class="form-label">Deadline</label><input class="form-control" name="deadline" maxlength="120" placeholder="Optional date or deadline"></div>
                    <div class="col-12"><label class="form-label">Approved facts</label><textarea class="form-control" name="facts" rows="6" maxlength="4000" placeholder="Facts the assistant may use; no identifying or sensitive data." required></textarea></div>
                </form>
            </div>
            <div class="modal-footer"><button class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button><button class="btn btn-success" id="queueAiCommunicationDraft"><i class="bi bi-stars me-1"></i>Queue draft</button></div>
        </div>
    </div>
</div>

<div class="modal fade" id="templateManagerModal" tabindex="-1" aria-labelledby="templateManagerModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="templateManagerModalLabel"><i class="bi bi-file-text"></i> <span id="templateModalTitle">New Template</span></h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="templateForm">
                <div class="modal-body">
                    <input type="hidden" name="catalog_id" id="inputCatalogId">
                    <input type="hidden" name="version_id" id="inputVersionId">

                    <div class="row mb-3">
                        <div class="col-md-4">
                            <label class="form-label required">Code <span class="text-muted small">(channel.category)</span></label>
                            <input type="text" class="form-control" name="code" id="inputCode" placeholder="sms.fees" required pattern="^[a-z]+\.[a-z_]+$" title="Format: channel.category (e.g., sms.fees)">
                            <div class="form-text">Format: <code>channel.category</code> (e.g., <code>sms.fees</code>, <code>email.results</code>)</div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label required">Name</label>
                            <input type="text" class="form-control" name="name" id="inputName" placeholder="Fees Payment Reminder" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Purpose</label>
                            <select class="form-select" name="purpose" id="inputPurpose">
                                <option value="general">General</option>
                                <option value="fees">Fees</option>
                                <option value="results">Results</option>
                                <option value="announcement">Announcement</option>
                                <option value="notification">Notification</option>
                                <option value="parent_event">Parent Event</option>
                                <option value="inquiry_reply">Inquiry Reply</option>
                                <option value="otp">OTP/2FA</option>
                                <option value="receipt">Payment Receipt</option>
                                <option value="invitation">Invitation</option>
                            </select>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold">Channel Variants</label>
                        <div id="channelVariantsContainer"></div>
                        <button type="button" class="btn btn-outline-secondary btn-sm mt-2" id="addChannelBtn">
                            <i class="bi bi-plus-circle"></i> Add Channel Variant
                        </button>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-outline-primary" id="previewBtn"><i class="bi bi-eye"></i> Preview</button>
                    <button type="submit" class="btn btn-primary" id="saveTemplateBtn"><i class="bi bi-save"></i> Save</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Channel Variant Editor Modal -->
<div class="modal fade" id="channelModal" tabindex="-1" aria-labelledby="channelModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header bg-secondary text-white">
                <h5 class="modal-title" id="channelModalLabel"><i class="bi bi-device-hdd"></i> Channel Variant Editor</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="channelForm">
                <input type="hidden" name="channel_id" id="inputChannelId">
                <input type="hidden" name="catalog_id" id="channelCatalogId">
                <input type="hidden" name="version_id" id="channelVersionId">
                <div class="modal-body">
                    <div class="row mb-3">
                        <div class="col-md-4">
                            <label class="form-label required">Channel</label>
                            <select class="form-select" name="channel" id="inputChannel" required>
                                <option value="sms">SMS</option>
                                <option value="whatsapp">WhatsApp</option>
                                <option value="email">Email</option>
                                <option value="portal">Portal</option>
                                <option value="in_app">In-App</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Language</label>
                            <select class="form-select" name="language_code" id="inputLanguageCode">
                                <option value="en">English</option>
                                <option value="sw">Kiswahili</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Body Format</label>
                            <select class="form-select" name="body_format" id="inputBodyFormat">
                                <option value="plain">Plain Text</option>
                                <option value="html">HTML</option>
                            </select>
                        </div>
                    </div>

                    <div class="mb-3" id="subjectGroup">
                        <label class="form-label">Subject <span class="text-muted small">(Email/WhatsApp)</span></label>
                        <input type="text" class="form-control" name="subject" id="inputSubject" placeholder="Fees Reminder - {{student_name}}">
                        <div class="form-text">Use <code>@variable_name</code> or <code>{{variable_name}}</code> for personalization.</div>
                    </div>

                    <div class="mb-3" id="headerGroup">
                        <label class="form-label">Header <span class="text-muted small">(Email only - branding/logo area)</span></label>
                        <textarea class="form-control" name="header" id="inputHeader" rows="4" placeholder="<div style='text-align:center;padding:20px;background:#f8f9fa;'><img src='{{school_logo}}' alt='{{school_name}}' style='max-height:80px;'></div>"></textarea>
                        <div class="form-text">HTML allowed. Use <code>@variable_name</code> for dynamic content.</div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label required">Message Body</label>
                        <div class="btn-toolbar mb-2" role="toolbar">
                            <div class="btn-group me-2" role="group" id="insertTokenGroup">
                                <button type="button" class="btn btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">
                                    <i class="bi bi-at"></i> Insert Token
                                </button>
                                <ul class="dropdown-menu" id="tokenDropdown">
                                    <li><h6 class="dropdown-header">Common Tokens</h6></li>
                                    <li><a class="dropdown-item insert-token" href="#" data-token="@first_name">@first_name</a></li>
                                    <li><a class="dropdown-item insert-token" href="#" data-token="@last_name">@last_name</a></li>
                                    <li><a class="dropdown-item insert-token" href="#" data-token="@student_name">@student_name</a></li>
                                    <li><a class="dropdown-item insert-token" href="#" data-token="@parent_name">@parent_name</a></li>
                                    <li><a class="dropdown-item insert-token" href="#" data-token="@class_name">@class_name</a></li>
                                    <li><a class="dropdown-item insert-token" href="#" data-token="@amount_due">@amount_due</a></li>
                                    <li><a class="dropdown-item insert-token" href="#" data-token="@due_date">@due_date</a></li>
                                    <li><a class="dropdown-item insert-token" href="#" data-token="@event_title">@event_title</a></li>
                                    <li><a class="dropdown-item insert-token" href="#" data-token="@event_date">@event_date</a></li>
                                    <li><a class="dropdown-item insert-token" href="#" data-token="@event_time">@event_time</a></li>
                                    <li><a class="dropdown-item insert-token" href="#" data-token="@event_venue">@event_venue</a></li>
                                    <li><hr class="dropdown-divider"></li>
                                    <li><h6 class="dropdown-header">School Info</h6></li>
                                    <li><a class="dropdown-item insert-token" href="#" data-token="@school_name">@school_name</a></li>
                                    <li><a class="dropdown-item insert-token" href="#" data-token="@school_address">@school_address</a></li>
                                    <li><a class="dropdown-item insert-token" href="#" data-token="@school_phone">@school_phone</a></li>
                                    <li><a class="dropdown-item insert-token" href="#" data-token="@school_email">@school_email</a></li>
                                    <li><a class="dropdown-item insert-token" href="#" data-token="@school_motto">@school_motto</a></li>
                                    <li><a class="dropdown-item insert-token" href="#" data-token="@school_logo">@school_logo</a></li>
                                </ul>
                            </div>
                            <div class="btn-group me-2" role="group">
                                <button type="button" class="btn btn-outline-secondary" id="boldBtn" title="Bold (HTML only)"><i class="bi bi-type-bold"></i></button>
                                <button type="button" class="btn btn-outline-secondary" id="italicBtn" title="Italic (HTML only)"><i class="bi bi-type-italic"></i></button>
                                <button type="button" class="btn btn-outline-secondary" id="brBtn" title="Line Break"><i class="bi bi-arrow-return-left"></i></button>
                            </div>
                            <div class="btn-group ms-auto" role="group">
                                <span class="btn btn-outline-info disabled" id="segmentEstimate">Segments: 1</span>
                            </div>
                        </div>
                        <textarea class="form-control font-monospace" name="body" id="inputBody" rows="10" placeholder="Type your message here. Press Enter for new lines. Use @first_name for personalization.&#10;&#10;Example:&#10;Dear @first_name,&#10;&#10;Your fees of @amount_due are due on @due_date.&#10;&#10;Thank you,&#10;@school_name" required></textarea>
                        <div class="form-text">
                            <span id="charCount">0</span> characters | 
                            <span id="segmentInfo">1 segment (GSM-7)</span> |
                            <kbd>Enter</kbd> = new line | <kbd>@</kbd> = insert token | Legacy <code>{{var}}</code> also works
                        </div>
                    </div>

                    <div class="mb-3" id="footerGroup">
                        <label class="form-label">Footer / Signature <span class="text-muted small">(Email only)</span></label>
                        <textarea class="form-control" name="footer" id="inputFooter" rows="4" placeholder="<div style='padding:20px;background:#f8f9fa;text-align:center;font-size:12px;color:#6c757d;'><p>{{school_name}}<br>{{school_address}}<br>{{school_phone}} | {{school_email}}</p></div>"></textarea>
                        <div class="form-text">HTML allowed. This serves as the email signature.</div>
                    </div>

                    <div class="mb-3" id="mediaGroup">
                        <label class="form-label">Media Attachments <span class="text-muted small">(WhatsApp - images/documents)</span></label>
                        <div id="mediaList"></div>
                        <button type="button" class="btn btn-outline-secondary btn-sm" id="addMediaBtn">
                            <i class="bi bi-plus-circle"></i> Add Media URL
                        </button>
                        <div class="form-text">WhatsApp supports images (jpg, png) and documents (pdf). URLs must be public HTTPS.</div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Provider Template ID <span class="text-muted small">(Talksasa)</span></label>
                        <input type="text" class="form-control" name="provider_template_id" id="inputProviderTemplateId" placeholder="Optional - auto-filled after provider approval">
                        <div class="form-text">Leave blank for draft. Set after Talksasa approves the template.</div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-outline-primary" id="channelPreviewBtn"><i class="bi bi-eye"></i> Preview</button>
                    <button type="submit" class="btn btn-primary" id="saveChannelBtn"><i class="bi bi-save"></i> Save Channel</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Preview Modal -->
<div class="modal fade" id="previewModal" tabindex="-1" aria-labelledby="previewModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header bg-info text-white">
                <h5 class="modal-title" id="previewModalLabel"><i class="bi bi-eye"></i> Preview</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label">Test Variables (JSON)</label>
                    <textarea class="form-control font-monospace" id="previewVariables" rows="6" placeholder='{"first_name": "John", "amount_due": "KES 5,000", "due_date": "2025-12-31"}'></textarea>
                </div>
                <hr>
                <div id="previewOutput"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-primary" id="testSendBtn"><i class="bi bi-send"></i> Test Send</button>
            </div>
        </div>
    </div>
</div>

<!-- Conversation view modal -->
<div class="modal fade" id="conversationModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-chat-text me-2 text-primary"></i>Message Thread</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div id="conversationContent" class="mb-3"></div>
                <div class="d-flex gap-2">
                    <input type="text" class="form-control" id="newMessageInput" placeholder="Type a message...">
                    <button class="btn btn-success btn-sm" id="sendThreadReply"><i class="bi bi-send"></i></button>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="<?= $appBase ?>/js/pages/manage_communications.js?v=<?php echo asset_version('js/pages/manage_communications.js'); ?>"></script>
