<?php
/**
 * Manage Announcements Page
 * Modern UI — HTML structure only; logic lives in js/pages/manage_announcements.js.
 * Embedded in the app shell via home.php?route=manage_announcements.
 */
?>

<div class="row g-3">
    <div class="col-12">
        <!-- Page header -->
        <div class="app-page-header d-flex flex-wrap align-items-center justify-content-between gap-3">
            <div class="d-flex align-items-center gap-3">
                <div class="app-page-icon bg-warning-subtle text-warning-emphasis">
                    <i class="bi bi-megaphone-fill"></i>
                </div>
                <div>
                    <h4 class="app-page-title mb-0">Announcements</h4>
                    <span class="app-page-subtitle">Create, schedule and broadcast notices to your school community</span>
                </div>
            </div>
            <div class="d-flex gap-2">
                <button class="btn btn-outline-secondary btn-sm" id="exportAnnouncementsBtn">
                    <i class="bi bi-download me-1"></i>Export
                </button>
                <button class="btn btn-success btn-sm" id="createAnnouncementBtn" data-permission="announcements_create">
                    <i class="bi bi-plus-circle me-1"></i>New Announcement
                </button>
            </div>
        </div>
    </div>

    <!-- Stat cards -->
    <div class="col-6 col-md-3">
        <div class="app-stat-card">
            <div class="app-stat-icon text-primary bg-primary-subtle"><i class="bi bi-megaphone"></i></div>
            <div>
                <div class="app-stat-value" id="totalAnnouncements">0</div>
                <div class="app-stat-label">Total</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="app-stat-card">
            <div class="app-stat-icon text-success bg-success-subtle"><i class="bi bi-check-circle"></i></div>
            <div>
                <div class="app-stat-value" id="publishedAnnouncements">0</div>
                <div class="app-stat-label">Published</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="app-stat-card">
            <div class="app-stat-icon text-warning bg-warning-subtle"><i class="bi bi-pencil-square"></i></div>
            <div>
                <div class="app-stat-value" id="draftAnnouncements">0</div>
                <div class="app-stat-label">Drafts</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="app-stat-card">
            <div class="app-stat-icon text-info bg-info-subtle"><i class="bi bi-clock-history"></i></div>
            <div>
                <div class="app-stat-value" id="scheduledAnnouncements">0</div>
                <div class="app-stat-label">Scheduled</div>
            </div>
        </div>
    </div>

    <!-- Filters -->
    <div class="col-12">
        <div class="app-panel">
            <div class="row g-2 align-items-end">
                <div class="col-md-4">
                    <label class="form-label small mb-1 text-muted">Search</label>
                    <input type="text" class="form-control form-control-sm" id="announcementSearch" placeholder="Search announcements...">
                </div>
                <div class="col-md-3">
                    <label class="form-label small mb-1 text-muted">Status</label>
                    <select class="form-select form-select-sm" id="statusFilter">
                        <option value="">All Status</option>
                        <option value="published">Published</option>
                        <option value="draft">Draft</option>
                        <option value="scheduled">Scheduled</option>
                        <option value="archived">Archived</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label small mb-1 text-muted">Category</label>
                    <select class="form-select form-select-sm" id="categoryFilter">
                        <option value="">All Categories</option>
                        <option value="academic">Academic</option>
                        <option value="events">Events</option>
                        <option value="general">General</option>
                        <option value="urgent">Urgent</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label small mb-1 text-muted">Audience</label>
                    <select class="form-select form-select-sm" id="audienceFilter">
                        <option value="">All Audience</option>
                        <option value="all">All Users</option>
                        <option value="students">Students</option>
                        <option value="parents">Parents</option>
                        <option value="staff">Staff</option>
                    </select>
                </div>
            </div>
        </div>
    </div>

    <!-- Announcement cards -->
    <div class="col-12">
        <div class="row g-3" id="announcementsList"></div>
    </div>

    <!-- Pagination -->
    <div class="col-12">
        <nav aria-label="Announcements navigation">
            <ul class="pagination pagination-sm justify-content-center" id="announcementsPagination"></ul>
        </nav>
    </div>
</div>

<!-- Announcement modal (create / edit) -->
<div class="modal fade" id="announcementModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-megaphone me-2 text-warning"></i>Announcement</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="announcementForm" novalidate>
                    <input type="hidden" id="announcement_id">

                    <div class="mb-3">
                        <label class="form-label">Title <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="title" maxlength="255" placeholder="e.g. Staff Meeting Friday 2PM" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Content <span class="text-danger">*</span></label>
                        <textarea class="form-control" id="content" rows="5" placeholder="Write the announcement details..." required></textarea>
                    </div>

                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label">Category</label>
                            <select class="form-select" id="category">
                                <option value="general">General</option>
                                <option value="academic">Academic</option>
                                <option value="events">Events</option>
                                <option value="urgent">Urgent</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Priority</label>
                            <select class="form-select" id="priority">
                                <option value="low">Low</option>
                                <option value="medium" selected>Normal</option>
                                <option value="high">High</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Target Audience</label>
                            <select class="form-select" id="audience">
                                <option value="all">All Users</option>
                                <option value="students">Students</option>
                                <option value="parents">Parents</option>
                                <option value="staff">Staff</option>
                                <option value="specific">Specific Groups</option>
                            </select>
                        </div>
                    </div>

                    <div class="row g-3 mt-0">
                        <div class="col-md-6">
                            <label class="form-label">Publish Date <small class="text-muted">(empty = now)</small></label>
                            <input type="datetime-local" class="form-control" id="publish_date">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Expiry Date</label>
                            <input type="datetime-local" class="form-control" id="expiry_date">
                        </div>
                    </div>

                    <div class="mb-3 mt-3">
                        <label class="form-label">Attachment <small class="text-muted">(optional)</small></label>
                        <input type="file" class="form-control" id="attachment">
                    </div>

                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" id="send_notification">
                        <label class="form-check-label" for="send_notification">
                            Send push notification
                        </label>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-outline-warning" id="saveDraftBtn">
                    <i class="bi bi-save me-1"></i>Save as Draft
                </button>
                <button type="button" class="btn btn-success" id="publishAnnouncementBtn">
                    <i class="bi bi-send me-1"></i>Publish
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Announcement view modal -->
<div class="modal fade" id="viewAnnouncementModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="viewAnnouncementTitle"><i class="bi bi-megaphone me-2 text-warning"></i>Announcement</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="d-flex flex-wrap gap-2 mb-3" id="viewAnnouncementMeta"></div>
                <div class="p-3 bg-light rounded-3" id="viewAnnouncementContent"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<script src="<?= $appBase ?>/js/pages/manage_announcements.js?v=<?php echo asset_version('js/pages/manage_announcements.js'); ?>"></script>
