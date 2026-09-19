<?php
/**
 * Manage Email (Mailbox Workspace)
 * School staff mailboxes — role-scoped view of assigned IMAP profiles.
 * System Admin (role 2) is NOT routed here.
 */
?>
<div id="mailboxes-root">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h4 class="mb-0"><i class="fas fa-envelope me-2"></i>Mailboxes</h4>
        <div>
            <button class="btn btn-outline-secondary btn-sm" id="mailRefreshBtn" title="Refresh folders"><i class="fas fa-sync-alt"></i></button>
            <button class="btn btn-primary btn-sm" id="mailComposeBtn"><i class="fas fa-pen me-1"></i>Compose</button>
        </div>
    </div>

    <!-- Mailbox tabs -->
    <ul class="nav nav-tabs mb-3" id="mailboxTabs" role="tablist"></ul>

    <!-- Folder sidebar -->
    <div class="row g-3">
        <div class="col-md-2" id="folderPane">
            <div class="list-group" id="folderList">
                <div class="list-group-item text-muted small">Select a mailbox</div>
            </div>
        </div>

        <!-- Messages + viewer -->
        <div class="col-md-10">
            <div id="folderBreadcrumb" class="mb-2 small text-muted" style="display:none;">
                <i class="fas fa-inbox me-1"></i><span id="folderBreadcrumbLabel"></span>
            </div>

            <div id="emptyFolderNotice" class="text-center text-muted py-5" style="display:none;">
                <i class="fas fa-envelope-open fa-3x mb-3 d-block opacity-25"></i>
                No messages in this folder.
            </div>

            <!-- Message list -->
            <div id="messageList"></div>

            <!-- Message list pagination -->
            <div id="msgPagination" class="d-flex justify-content-between align-items-center mt-3" style="display:none;">
                <small class="text-muted" id="msgPaginationInfo"></small>
                <div>
                    <button class="btn btn-outline-secondary btn-sm" id="msgPrevPage" disabled><i class="fas fa-chevron-left"></i></button>
                    <small class="mx-2" id="msgPageLabel">1 / 1</small>
                    <button class="btn btn-outline-secondary btn-sm" id="msgNextPage" disabled><i class="fas fa-chevron-right"></i></button>
                </div>
            </div>

            <!-- Message viewer -->
            <div id="messageViewer" class="border rounded p-3 bg-white mt-2" style="display:none;">
                <div class="d-flex justify-content-between align-items-start mb-2">
                    <h5 id="mvSubject" class="mb-0"></h5>
                    <button class="btn btn-outline-secondary btn-sm" id="mvBackBtn" title="Back to list"><i class="fas fa-arrow-left"></i> Back</button>
                </div>
                <div class="small text-muted mb-3">
                    <div><strong>From:</strong> <span id="mvFrom"></span></div>
                    <div><strong>To:</strong> <span id="mvTo"></span></div>
                    <div><strong>Date:</strong> <span id="mvDate"></span></div>
                </div>
                <div id="mvAttachments" class="mb-3" style="display:none;"></div>
                <div id="mvBody" class="border rounded p-3 bg-light"></div>
            </div>

            <!-- Loading state -->
            <div id="loadingMessages" class="text-center py-5" style="display:none;">
                <div class="spinner-border text-primary" role="status"></div>
            </div>
        </div>
    </div>
</div>

<!-- Compose Modal (school staff — compose sends via platform SMTP) -->
<div class="modal fade" id="composeModal" tabindex="-1">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title"><i class="fas fa-pen me-1"></i>Compose Email</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="composeForm" autocomplete="off">
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label fw-semibold">To <span class="text-danger">*</span></label>
                            <input type="email" class="form-control" id="composeTo" required placeholder="recipient@example.com">
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-semibold">Subject <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="composeSubject" required>
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-semibold">Message <span class="text-danger">*</span></label>
                            <textarea class="form-control" id="composeBody" rows="10" required></textarea>
                            <small class="text-muted">HTML formatting is supported.</small>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Attachments</label>
                            <input type="file" class="form-control form-control-sm" id="composeAttachments" multiple>
                            <small class="text-muted">Maximum 5 files, 10 MB each.</small>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button class="btn btn-primary" id="composeSendBtn"><i class="fas fa-paper-plane me-1"></i>Send</button>
            </div>
        </div>
    </div>
</div>

<script src="<?= $appBase ?>/js/pages/manage_email.js?v=<?= time() ?>"></script>
