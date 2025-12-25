<?php
/**
 * Manage Announcements Page
 * HTML structure only - logic will be in js/pages/announcements.js
 * Embedded in app_layout.php
 */
?>

<div class="card shadow-sm">
    <div class="card-header bg-gradient bg-warning text-dark">
        <div class="d-flex justify-content-between align-items-center">
            <h4 class="mb-0"><i class="fas fa-bullhorn"></i> Announcements Management</h4>
            <div class="btn-group">
                <button class="btn btn-dark btn-sm" id="createAnnouncementBtn" data-permission="announcements_create">
                    <i class="bi bi-plus-circle"></i> New Announcement
                </button>
                <button class="btn btn-outline-dark btn-sm" id="exportAnnouncementsBtn">
                    <i class="bi bi-download"></i> Export
                </button>
            </div>
        </div>
    </div>

    <div class="card-body">
        <!-- Announcement Stats -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="card border-primary">
                    <div class="card-body text-center">
                        <h6 class="text-muted mb-2">Total Announcements</h6>
                        <h3 class="text-primary mb-0" id="totalAnnouncements">0</h3>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card border-success">
                    <div class="card-body text-center">
                        <h6 class="text-muted mb-2">Published</h6>
                        <h3 class="text-success mb-0" id="publishedAnnouncements">0</h3>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card border-warning">
                    <div class="card-body text-center">
                        <h6 class="text-muted mb-2">Draft</h6>
                        <h3 class="text-warning mb-0" id="draftAnnouncements">0</h3>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card border-info">
                    <div class="card-body text-center">
                        <h6 class="text-muted mb-2">Scheduled</h6>
                        <h3 class="text-info mb-0" id="scheduledAnnouncements">0</h3>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filters -->
        <div class="row mb-3">
            <div class="col-md-3">
                <input type="text" class="form-control" id="announcementSearch" placeholder="Search announcements...">
            </div>
            <div class="col-md-2">
                <select class="form-select" id="statusFilter">
                    <option value="">All Status</option>
                    <option value="published">Published</option>
                    <option value="draft">Draft</option>
                    <option value="scheduled">Scheduled</option>
                    <option value="archived">Archived</option>
                </select>
            </div>
            <div class="col-md-2">
                <select class="form-select" id="categoryFilter">
                    <option value="">All Categories</option>
                    <option value="academic">Academic</option>
                    <option value="events">Events</option>
                    <option value="general">General</option>
                    <option value="urgent">Urgent</option>
                </select>
            </div>
            <div class="col-md-2">
                <select class="form-select" id="audienceFilter">
                    <option value="">All Audience</option>
                    <option value="all">All Users</option>
                    <option value="students">Students</option>
                    <option value="parents">Parents</option>
                    <option value="staff">Staff</option>
                </select>
            </div>
        </div>

        <!-- Announcements List -->
        <div class="row" id="announcementsList">
            <!-- Dynamic announcement cards -->
        </div>

        <!-- Pagination -->
        <nav>
            <ul class="pagination justify-content-center" id="announcementsPagination">
                <!-- Dynamic pagination -->
            </ul>
        </nav>
    </div>
</div>

<!-- Announcement Modal -->
<div class="modal fade" id="announcementModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Announcement Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="announcementForm">
                    <input type="hidden" id="announcement_id">

                    <div class="mb-3">
                        <label class="form-label">Title*</label>
                        <input type="text" class="form-control" id="title" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Content*</label>
                        <textarea class="form-control" id="content" rows="5" required></textarea>
                    </div>

                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Category*</label>
                            <select class="form-select" id="category" required>
                                <option value="">Select Category</option>
                                <option value="academic">Academic</option>
                                <option value="events">Events</option>
                                <option value="general">General</option>
                                <option value="urgent">Urgent</option>
                            </select>
                        </div>

                        <div class="col-md-4 mb-3">
                            <label class="form-label">Priority*</label>
                            <select class="form-select" id="priority" required>
                                <option value="normal">Normal</option>
                                <option value="high">High</option>
                                <option value="urgent">Urgent</option>
                            </select>
                        </div>

                        <div class="col-md-4 mb-3">
                            <label class="form-label">Target Audience*</label>
                            <select class="form-select" id="audience" required>
                                <option value="all">All Users</option>
                                <option value="students">Students</option>
                                <option value="parents">Parents</option>
                                <option value="staff">Staff</option>
                                <option value="specific">Specific Groups</option>
                            </select>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Publish Date</label>
                            <input type="datetime-local" class="form-control" id="publish_date">
                        </div>

                        <div class="col-md-6 mb-3">
                            <label class="form-label">Expiry Date</label>
                            <input type="datetime-local" class="form-control" id="expiry_date">
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Attachment</label>
                        <input type="file" class="form-control" id="attachment">
                    </div>

                    <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" id="send_notification">
                        <label class="form-check-label" for="send_notification">
                            Send push notification
                        </label>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-outline-primary" id="saveDraftBtn">Save as Draft</button>
                <button type="button" class="btn btn-primary" id="publishAnnouncementBtn">Publish</button>
            </div>
        </div>
    </div>
</div>

<script>
    // Initialize announcements management when page loads
    document.addEventListener('DOMContentLoaded', function () {
        // TODO: Implement announcementsManagementController in js/pages/announcements.js
        console.log('Announcements Management page loaded');
    });
</script>