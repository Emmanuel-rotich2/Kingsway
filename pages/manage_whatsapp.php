<?php
/**
 * Manage WhatsApp messaging console.
 * HTML fragment loaded into app_layout.php; behaviour lives in
 * js/pages/messaging_console.js (shared engine) via js/pages/manage_whatsapp.js.
 */
$whatsappSendingEnabled = filter_var(\App\Config\Config::get('WHATSAPP_ENABLED', false), FILTER_VALIDATE_BOOLEAN);
?>

<link rel="stylesheet" href="<?= htmlspecialchars($appBase) ?>/css/pages/messaging-console.css?v=<?= asset_version('css/pages/messaging-console.css') ?>">

<div class="kw-console" data-channel="whatsapp" data-whatsapp-enabled="<?= $whatsappSendingEnabled ? 'true' : 'false' ?>" id="kwConsole">
    <?php if (!$whatsappSendingEnabled): ?>
        <div class="alert alert-warning m-3 mb-0" role="status"><i class="bi bi-pause-circle me-1"></i>WhatsApp sending is paused while provider configuration is pending. Use SMS or email for now. Existing WhatsApp delivery history remains available.</div>
    <?php endif; ?>
    <header class="kw-console-top">
        <div class="kw-console-brand">
            <span class="kw-console-icon"><i class="bi bi-whatsapp"></i></span>
            <div>
                <h1 class="kw-console-title">WhatsApp Console</h1>
                <span class="kw-console-sub">Broadcasts, schedules &amp; delivery receipts</span>
            </div>
        </div>
        <div class="kw-console-actions">
            <span class="kw-chip" id="smsBalanceChip" title="SMS balance from the configured provider">
                <i class="bi bi-wallet2"></i> <span id="smsBalance">—</span>
            </span>
            <button class="btn btn-sm" id="refreshBtn" title="Refresh messages"><i class="bi bi-arrow-clockwise"></i></button>
            <button class="btn btn-sm" id="exportBtn" title="Export CSV"><i class="bi bi-filetype-csv"></i> CSV</button>
            <button class="btn btn-sm" id="printBtn" title="Print / save as PDF"><i class="bi bi-printer"></i></button>
            <button class="btn btn-sm kw-btn-primary" id="composeBtn" <?= $whatsappSendingEnabled ? '' : 'disabled title="WhatsApp sending is paused; use SMS or email for now"' ?>>
                <i class="bi bi-pencil-square"></i> <?= $whatsappSendingEnabled ? 'New Message' : 'WhatsApp paused' ?>
            </button>
        </div>
    </header>

    <div class="kw-console-body">
        <!-- LEFT RAIL -->
        <aside class="kw-console-rail">
            <section class="kw-rail-section">
                <h2 class="kw-rail-title">Folders</h2>
                <nav class="kw-folders">
                    <button type="button" class="kw-folder active" data-folder="all">
                        <i class="bi bi-inbox"></i> All <span class="kw-folder-count" id="folderCountAll">0</span>
                    </button>
                    <button type="button" class="kw-folder" data-folder="sent">
                        <i class="bi bi-check2-circle"></i> Sent <span class="kw-folder-count" id="folderCountSent">0</span>
                    </button>
                    <button type="button" class="kw-folder" data-folder="scheduled">
                        <i class="bi bi-clock"></i> Scheduled <span class="kw-folder-count" id="folderCountScheduled">0</span>
                    </button>
                    <button type="button" class="kw-folder" data-folder="pending">
                        <i class="bi bi-hourglass-split"></i> Pending <span class="kw-folder-count" id="folderCountPending">0</span>
                    </button>
                    <button type="button" class="kw-folder" data-folder="failed">
                        <i class="bi bi-x-octagon"></i> Failed <span class="kw-folder-count" id="folderCountFailed">0</span>
                    </button>
                </nav>
            </section>

            <section class="kw-rail-section">
                <div class="kw-rail-head">
                    <h2>Templates</h2>
                    <button type="button" class="kw-rail-add" id="newTemplateBtn" title="New template"><i class="bi bi-plus-lg"></i></button>
                </div>
                <div id="templatesList" class="kw-rail-list">
                    <div class="kw-rail-empty">Loading templates…</div>
                </div>
            </section>

            <section class="kw-rail-section">
                <div class="kw-rail-head">
                    <h2>Groups</h2>
                    <span class="kw-rail-tools">
                        <button type="button" id="syncGroupsBtn" title="Pull groups from provider"><i class="bi bi-arrow-down-circle"></i></button>
                        <button type="button" id="provisionGroupsBtn" title="Create missing groups at provider"><i class="bi bi-cloud-upload"></i></button>
                        <button type="button" id="addGroupBtn" title="New group"><i class="bi bi-plus-lg"></i></button>
                    </span>
                </div>
                <div id="groupsList" class="kw-rail-list">
                    <div class="kw-rail-empty">Loading groups…</div>
                </div>
            </section>

            <section class="kw-rail-section">
                <button type="button" class="kw-rail-toggle" id="optOutsToggle" data-bs-toggle="collapse" data-bs-target="#optOutsCollapse" aria-expanded="false">
                    <h2>Opt-outs</h2>
                    <span class="kw-folder-count" id="optOutCount">0</span>
                    <i class="bi bi-chevron-down"></i>
                </button>
                <div class="collapse" id="optOutsCollapse">
                    <div id="optOutsList" class="kw-rail-list">
                        <div class="kw-rail-empty">Expand to load opted-out contacts.</div>
                    </div>
                </div>
            </section>

            <section class="kw-rail-section" id="gatewaySection">
                <div class="kw-balance-block">
                    <span class="kw-muted" style="font-size:.72rem;">SMS balance</span>
                    <strong id="railBalance">—</strong>
                    <span class="kw-muted" style="font-size:.72rem;" id="gatewayBalanceMsg">Balance is read from the configured provider.</span>
                </div>
                <div class="kw-topup">
                    <div>
                        <div class="kw-muted" style="font-size:.7rem;">M-Pesa Paybill</div>
                        <div id="smsTopupPaybill">—</div>
                    </div>
                    <div>
                        <div class="kw-muted" style="font-size:.7rem;">Account Number</div>
                        <div id="smsTopupAccount">—</div>
                    </div>
                </div>
                <p class="kw-rail-empty" style="text-align:left;padding-left:0;" id="smsTopupNotes">Units are credited automatically after confirmation.</p>
                <span class="kw-rail-tools" style="margin-bottom:8px;">
                    <button type="button" id="refreshBalanceBtn">Balance</button>
                    <button type="button" id="loadProfileBtn">Profile</button>
                </span>
                <div id="profilePanel"></div>
            </section>
        </aside>

        <!-- CENTER LIST -->
        <main class="kw-console-list">
            <div class="kw-filterbar">
                <span class="kw-scope" id="folderScopeLabel"><i class="bi bi-inbox"></i> All messages</span>
                <input type="text" class="form-control form-control-sm" id="searchFilter" placeholder="Search subject, body or recipients…">
                <select class="form-select form-select-sm" id="statusFilter" style="max-width:150px;">
                    <option value="">All Status</option>
                    <option value="sent">Sent</option>
                    <option value="delivered">Delivered</option>
                    <option value="pending">Pending</option>
                    <option value="queued">Queued</option>
                    <option value="failed">Failed</option>
                    <option value="scheduled">Scheduled</option>
                    <option value="draft">Draft</option>
                </select>
                <select class="form-select form-select-sm" id="categoryFilter" style="max-width:160px;">
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
                <select class="form-select form-select-sm" id="dateFilter" style="max-width:150px;">
                    <option value="1">Today</option>
                    <option value="7">Last 7 days</option>
                    <option value="30" selected>Last 30 days</option>
                    <option value="90">Last 90 days</option>
                    <option value="all">All time</option>
                </select>
                <button type="button" class="btn btn-sm btn-outline-secondary" id="clearFiltersBtn" title="Clear filters"><i class="bi bi-x-circle"></i></button>
            </div>

            <div class="kw-stats">
                <div class="kw-stat kw-stat-ok">
                    <span class="kw-stat-label">Sent today</span>
                    <span class="kw-stat-value" id="sentToday">0</span>
                </div>
                <div class="kw-stat kw-stat-warn">
                    <span class="kw-stat-label">Pending / Scheduled</span>
                    <span class="kw-stat-value" id="pendingCount">0</span>
                </div>
                <div class="kw-stat kw-stat-err">
                    <span class="kw-stat-label">Failed</span>
                    <span class="kw-stat-value" id="failedCount">0</span>
                </div>
                <div class="kw-stat">
                    <span class="kw-stat-label">In view</span>
                    <span class="kw-stat-value" id="totalCount">0</span>
                </div>
            </div>

            <div class="kw-table-wrap">
                <table class="kw-table" id="messagesTable">
                    <thead>
                        <tr>
                            <th scope="col">Subject / Type</th>
                            <th scope="col">Preview</th>
                            <th scope="col">Recipients</th>
                            <th scope="col">Status</th>
                            <th scope="col">Created</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr class="kw-empty-row"><td colspan="5">Loading messages…</td></tr>
                    </tbody>
                </table>
            </div>

            <nav>
                <ul class="pagination pagination-sm kw-pagination" id="pagination"></ul>
            </nav>
        </main>

        <!-- RIGHT DETAIL -->
        <aside class="kw-console-detail" id="detailPane" hidden>
            <div class="kw-detail-head">
                <h2>Message</h2>
                <button type="button" class="kw-icon-btn" id="closeDetailBtn" title="Back to messages" aria-label="Back to messages"><i class="bi bi-arrow-left"></i></button>
            </div>
            <div class="kw-detail-empty" id="detailEmpty">
                <i class="bi bi-chat-square-text"></i>
                <p>Select a message from the list to see its content and per-recipient delivery status.</p>
            </div>
            <div class="kw-detail-content" id="detailContent" hidden>
                <div class="kw-detail-block">
                    <div class="d-flex justify-content-between align-items-start gap-2">
                        <h3 id="detailSubject">—</h3>
                        <span class="kw-badge" id="detailStatus">—</span>
                    </div>
                    <div class="kw-detail-meta" id="detailMeta">—</div>
                </div>
                <div class="kw-detail-block">
                    <span class="kw-detail-label">Message</span>
                    <p class="kw-detail-body" id="detailBody">—</p>
                </div>
                <div class="kw-detail-block">
                    <span class="kw-detail-label">Audience</span>
                    <p class="kw-detail-body" id="detailAudience">—</p>
                </div>
                <div class="kw-detail-block" id="scheduleEditWrap" hidden>
                    <span class="kw-detail-label">Reschedule</span>
                    <div class="input-group input-group-sm">
                        <input type="datetime-local" class="form-control" id="detailScheduleTime">
                        <button type="button" class="btn btn-outline-primary" id="updateScheduleBtn">Update</button>
                    </div>
                </div>
                <div class="kw-detail-block">
                    <span class="kw-detail-label">Delivery <span class="kw-muted" id="deliveryCount"></span></span>
                    <div class="kw-delivery" id="deliveryList">
                        <div class="kw-rail-empty">Select a message to load delivery status.</div>
                    </div>
                </div>
                <div class="kw-detail-actions">
                    <button type="button" class="btn btn-sm btn-primary" id="resendBtn" disabled><i class="bi bi-arrow-repeat"></i> Resend selected</button>
                    <button type="button" class="btn btn-sm btn-outline-secondary" id="copyBtn"><i class="bi bi-clipboard"></i> Copy</button>
                </div>
            </div>
        </aside>
    </div>
</div>

<!-- Compose Offcanvas -->
<div class="offcanvas offcanvas-end kw-compose kw-compose-whatsapp" tabindex="-1" id="composeOffcanvas" aria-labelledby="composeTitle">
    <div class="offcanvas-header">
        <h2 class="offcanvas-title" id="composeTitle"><i class="bi bi-whatsapp"></i> Compose WhatsApp</h2>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close"></button>
    </div>
    <div class="offcanvas-body">
        <form id="composeForm">
            <div class="kw-compose-grid kw-wa-composer-layout">
                <div class="kw-wa-audience-column">
                    <div class="mb-3">
                        <label class="form-label" for="messageType">Message Type</label>
                        <select class="form-select" id="messageType">
                            <option value="">Select type…</option>
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
                        <label class="form-label" for="recipientType">Audience</label>
                        <select class="form-select" id="recipientType" required>
                            <option value="">Choose an audience…</option>
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
                        <small class="form-text text-muted">Choose a selected audience to load matching records from the school directory.</small>
                    </div>
                    <div id="recipientPickerDiv" class="mb-3" style="display:none;">
                        <label class="form-label">Select Recipients</label>
                        <div id="recipientPickerMount"></div>
                    </div>
                    <div id="recipientSelector" class="mb-3" style="display:none;">
                        <label class="form-label">Select Recipients</label>
                        <input type="text" class="form-control form-control-sm mb-2" id="contactSearch" placeholder="Filter list…">
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
                        <label class="form-label" for="scheduleTime">Schedule (optional)</label>
                        <input type="datetime-local" class="form-control" id="scheduleTime">
                    </div>
                    <div class="mb-3">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="enableReminder">
                            <label class="form-check-label" for="enableReminder">Send follow-up reminder</label>
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
                <div class="kw-wa-content-column">
                    <div class="kw-wa-content-grid">
                    <div class="kw-wa-message-controls">
                    <div class="mb-3">
                        <label class="form-label" for="templateSelect">Template <small class="text-muted">(optional)</small></label>
                        <select class="form-select" id="templateSelect">
                            <option value="">No template</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <div class="d-flex justify-content-between align-items-center">
                            <label class="form-label mb-0" for="messageBody">Message <span class="text-danger">*</span></label>
                            <div class="dropdown">
                                <button class="btn btn-link btn-sm p-0 dropdown-toggle" type="button" data-bs-toggle="dropdown">Insert variable</button>
                                <ul class="dropdown-menu dropdown-menu-end" id="messageVariableMenu"></ul>
                            </div>
                        </div>
                        <textarea class="form-control" id="messageBody" rows="7" maxlength="1200" placeholder="Type a WhatsApp message…"></textarea>
                        <small class="kw-compose-hint">
                            <span id="charCount">0</span> characters
                            <span id="smsMetrics"> · <span id="smsCount">0</span> SMS · Est. cost: KES <span id="estimatedCost">0.00</span></span>
                        </small>
                    </div>
                    <section class="kw-wa-media" aria-labelledby="waMediaTitle">
                        <div class="d-flex align-items-center justify-content-between mb-2">
                            <label class="form-label mb-0" id="waMediaTitle">Media message <small class="text-muted">(optional)</small></label>
                            <span class="small text-muted">One media item per message</span>
                        </div>
                        <div class="btn-group w-100 mb-3" role="group" aria-label="Choose media source">
                            <button type="button" class="btn btn-sm btn-outline-success active" data-wa-source="upload"><i class="bi bi-upload"></i> Device</button>
                            <button type="button" class="btn btn-sm btn-outline-success" data-wa-source="link"><i class="bi bi-link-45deg"></i> Public link</button>
                            <button type="button" class="btn btn-sm btn-outline-success" data-wa-source="generated"><i class="bi bi-file-earmark-text"></i> Kingsway document</button>
                        </div>
                        <div data-wa-source-panel="upload">
                            <input type="file" class="form-control form-control-sm" id="mediaAttachment" accept="image/jpeg,image/png,application/pdf,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document,application/vnd.ms-excel,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,text/csv,text/plain,application/zip">
                            <small class="form-text text-muted">Choose an image or document from this device.</small>
                        </div>
                        <div data-wa-source-panel="link" hidden>
                            <input type="url" class="form-control form-control-sm" id="mediaPublicUrl" placeholder="https://example.com/file.pdf" inputmode="url">
                            <small class="form-text text-muted">The link must be public, use HTTPS, and point directly to the media file.</small>
                        </div>
                        <div data-wa-source-panel="generated" hidden>
                            <label class="form-label form-label-sm" for="generatedDocumentSelect">Document for each recipient</label>
                            <select class="form-select form-select-sm" id="generatedDocumentSelect">
                                <option value="">Choose a document…</option>
                                <option value="fee_statement">Fee statement for each linked learner</option>
                                <option value="payslip">Payslip for each staff member</option>
                                <option value="report_card">Latest released report card for each linked learner</option>
                                <option value="student_id_card">Student ID card for each linked learner</option>
                                <option value="portfolio">Portfolio for each linked learner</option>
                                <option value="certificate">Issued award certificates for each linked learner</option>
                                <option value="staff_security_pass">Existing security pass for each staff recipient</option>
                            </select>
                            <div id="waFeeStatementPeriod">
                            <label class="form-label form-label-sm mt-2" for="waDocumentAcademicYear">Academic year</label>
                            <select class="form-select form-select-sm" id="waDocumentAcademicYear">
                                <option value="">Current academic year</option>
                            </select>
                            </div>
                            <div id="waPayslipPeriod" hidden>
                                <div class="row g-2 mt-1">
                                    <div class="col-6"><label class="form-label form-label-sm" for="waPayrollMonth">Payroll month</label><select class="form-select form-select-sm" id="waPayrollMonth"><?php for ($m = 1; $m <= 12; $m++): ?><option value="<?= $m ?>" <?= $m === (int)date('n') ? 'selected' : '' ?>><?= date('F', mktime(0, 0, 0, $m, 1)) ?></option><?php endfor; ?></select></div>
                                    <div class="col-6"><label class="form-label form-label-sm" for="waPayrollYear">Payroll year</label><input type="number" class="form-control form-control-sm" id="waPayrollYear" min="2000" max="<?= (int)date('Y') + 1 ?>" value="<?= (int)date('Y') ?>"></div>
                                </div>
                            </div>
                            <small class="form-text text-muted" id="generatedDocumentHelp">Choose the system document to generate separately for each recipient.</small>
                        </div>
                        <div class="mt-2" id="waMediaPreview" hidden></div>
                    </section>
                    <div class="alert alert-info mb-0 py-2">
                        <small><i class="bi bi-info-circle"></i> WhatsApp messages are sent via the configured provider. Standard rates apply.</small>
                    </div>
                    </div>
                    <aside class="kw-wa-live-preview" aria-label="WhatsApp message preview">
                        <div class="kw-wa-preview-device">
                            <div class="kw-wa-preview-topbar">
                                <span class="kw-wa-preview-avatar"><i class="bi bi-building"></i></span>
                                <span><strong>Kingsway Preparatory School</strong><small>WhatsApp preview</small></span>
                            </div>
                            <div class="kw-wa-preview-thread">
                                <div class="kw-wa-preview-date">Today</div>
                                <div class="kw-wa-preview-bubble">
                                    <div class="kw-wa-preview-media" id="waChatMediaPreview" hidden></div>
                                    <p id="waChatMessagePreview">Your message will appear here as parents and staff receive it.</p>
                                    <time>now</time>
                                </div>
                            </div>
                            <div class="kw-wa-preview-compose"><i class="bi bi-emoji-smile"></i><span>Message</span><i class="bi bi-paperclip"></i><i class="bi bi-mic-fill"></i></div>
                        </div>
                        <p class="kw-wa-preview-caption">Preview updates as you write. Personal fields are filled separately for each recipient.</p>
                    </aside>
                    </div>
                </div>
            </div>
        </form>
    </div>
    <div class="kw-compose-footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="offcanvas">Cancel</button>
        <button type="button" class="btn btn-primary" id="sendBtn"><i class="bi bi-send"></i> Send Now</button>
    </div>
</div>

<!-- Group Modal -->
<div class="modal fade" id="groupModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="groupModalTitle"><i class="bi bi-collection"></i> Contact Group</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="groupEditId">
                <div class="mb-0">
                    <label class="form-label fw-semibold">Group Name <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="groupName" maxlength="120" required>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="saveGroupBtn">Save Group</button>
            </div>
        </div>
    </div>
</div>

<!-- Contacts Modal -->
<div class="modal fade" id="contactsModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-person-lines-fill"></i> Contacts — <span id="contactsModalTitle">—</span></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="kw-contacts-toolbar">
                    <input type="text" class="form-control form-control-sm" id="contactFilter" placeholder="Search contacts…">
                    <button type="button" class="btn btn-sm btn-outline-secondary" id="importContactsBtn" title="Import members from the school directory"><i class="bi bi-download"></i> Import</button>
                    <button type="button" class="btn btn-sm btn-outline-secondary" id="syncContactsBtn" title="Push contacts to provider"><i class="bi bi-cloud-upload"></i> Sync</button>
                    <button type="button" class="btn btn-sm btn-primary ms-auto" id="addContactBtn"><i class="bi bi-person-plus"></i> Add</button>
                </div>
                <form class="kw-contact-form" id="contactForm" hidden>
                    <input type="hidden" id="contactEditId">
                    <input type="hidden" id="contactGroupId">
                    <div class="row g-2 align-items-end">
                        <div class="col-md-3">
                            <label class="form-label form-label-sm">Phone <span class="text-danger">*</span></label>
                            <input type="text" class="form-control form-control-sm" id="contactPhone" placeholder="254XXXXXXXXX" required>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label form-label-sm">First Name</label>
                            <input type="text" class="form-control form-control-sm" id="contactFirstName">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label form-label-sm">Last Name</label>
                            <input type="text" class="form-control form-control-sm" id="contactLastName">
                        </div>
                        <div class="col-md-3">
                            <button type="button" class="btn btn-sm btn-primary" id="saveContactBtn">Save Contact</button>
                            <button type="button" class="btn btn-sm btn-outline-secondary" id="cancelContactBtn">Cancel</button>
                        </div>
                    </div>
                </form>
                <div class="table-responsive">
                    <table class="table table-hover table-sm mb-0" id="contactsTable">
                        <thead class="table-light">
                            <tr>
                                <th>Name</th>
                                <th>Phone</th>
                                <th class="text-end">Status / Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr><td colspan="3" class="text-center text-muted py-4">Loading contacts…</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Template Modal -->
<div class="modal fade" id="templateModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="templateModalTitle"><i class="bi bi-file-earmark-text"></i> Template</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="templateEditId">
                <div class="mb-3">
                    <label class="form-label fw-semibold">Name <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="templateName" maxlength="150" required>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold">Type</label>
                    <select class="form-select" id="templateType">
                        <?php if ($whatsappSendingEnabled): ?><option value="whatsapp">WhatsApp</option><?php endif; ?>
                        <option value="sms">SMS</option>
                        <option value="announcement">Announcement</option>
                    </select>
                </div>
                <div class="mb-0">
                    <label class="form-label fw-semibold">Body <span class="text-danger">*</span></label>
                    <textarea class="form-control" id="templateBody" rows="5" required></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="saveTemplateBtn">Save Template</button>
            </div>
        </div>
    </div>
</div>

<script src="<?= $appBase ?>/js/pages/messaging_console.js?v=<?= asset_version('js/pages/messaging_console.js') ?>"></script>
<script src="<?= $appBase ?>/js/pages/manage_whatsapp.js?v=<?= asset_version('js/pages/manage_whatsapp.js') ?>"></script>
