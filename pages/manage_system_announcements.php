<?php
/** Kingsway System Administrator: System Announcements (system domain). */
if (!isset($appBase)) {
    $appBase = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '')), '/');
    if ($appBase === '.') { $appBase = ''; }
}
?>
<div class="container-fluid py-4">
    <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
        <div>
            <h2 class="h2 fw-bold mb-1"><i class="bi bi-megaphone me-2 text-warning"></i>System Announcements</h2>
            <p class="text-muted mb-0">Create and manage system-wide announcements and notices.</p>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <button class="btn btn-warning" id="annCreateBtn"><i class="bi bi-plus-circle me-1"></i>New Announcement</button>
            <button class="btn btn-outline-secondary btn-sm" id="annExportCsv"><i class="bi bi-filetype-csv me-1"></i>Export CSV</button>
            <button class="btn btn-outline-secondary btn-sm" id="annPrint"><i class="bi bi-printer me-1"></i>Print/PDF</button>
            <button class="btn btn-outline-secondary btn-sm" id="annRefresh"><i class="bi bi-arrow-clockwise me-1"></i>Refresh</button>
        </div>
    </div>
    <div class="row g-3 mb-4">
        <div class="col-md-2"><div class="card border-0 shadow-sm h-100"><div class="card-body text-center py-3"><h5 class="text-primary mb-0" id="annStatTotal">0</h5><small class="text-muted">Total</small></div></div></div>
        <div class="col-md-2"><div class="card border-0 shadow-sm h-100"><div class="card-body text-center py-3"><h5 class="text-success mb-0" id="annStatPublished">0</h5><small class="text-muted">Published</small></div></div></div>
        <div class="col-md-2"><div class="card border-0 shadow-sm h-100"><div class="card-body text-center py-3"><h5 class="text-secondary mb-0" id="annStatDraft">0</h5><small class="text-muted">Drafts</small></div></div></div>
        <div class="col-md-2"><div class="card border-0 shadow-sm h-100"><div class="card-body text-center py-3"><h5 class="text-info mb-0" id="annStatScheduled">0</h5><small class="text-muted">Scheduled</small></div></div></div>
        <div class="col-md-2"><div class="card border-0 shadow-sm h-100"><div class="card-body text-center py-3"><h5 class="text-warning mb-0" id="annStatExpiring">0</h5><small class="text-muted">Expiring</small></div></div></div>
        <div class="col-md-2"><div class="card border-0 shadow-sm h-100"><div class="card-body text-center py-3"><h5 class="text-danger mb-0" id="annStatArchived">0</h5><small class="text-muted">Archived</small></div></div></div>
    </div>
    <div id="annState" class="alert alert-info"><i class="bi bi-hourglass-split me-1"></i>Loading announcements...</div>
    <div class="row mb-3 g-2 align-items-end">
        <div class="col-md-3"><input type="text" class="form-control form-control-sm" id="annSearch" placeholder="Search announcements..."></div>
        <div class="col-md-2"><select class="form-select form-select-sm" id="annStatusFilter"><option value="">All Status</option><option value="draft">Draft</option><option value="scheduled">Scheduled</option><option value="published">Published</option><option value="archived">Archived</option><option value="expired">Expired</option></select></div>
        <div class="col-md-2"><select class="form-select form-select-sm" id="annTypeFilter"><option value="">All Types</option><option value="general">General</option><option value="academic">Academic</option><option value="administrative">Administrative</option><option value="event">Event</option><option value="emergency">Emergency</option><option value="maintenance">Maintenance</option></select></div>
        <div class="col-md-2"><select class="form-select form-select-sm" id="annPriorityFilter"><option value="">All Priority</option><option value="critical">Critical</option><option value="high">High</option><option value="normal">Normal</option><option value="low">Low</option></select></div>
        <div class="col-md-1"><button class="btn btn-outline-secondary btn-sm w-100" id="annClearFilters"><i class="bi bi-x-circle"></i></button></div>
    </div>
    <div class="table-responsive"><table class="table table-hover table-sm" id="annTable">
        <thead class="table-light"><tr><th>Title</th><th>Type</th><th>Priority</th><th>Audience</th><th>Status</th><th>Created</th><th>Actions</th></tr></thead>
        <tbody id="annTableBody"><tr><td colspan="7" class="text-center text-muted py-4">Loading...</td></tr></tbody>
    </table></div>
    <nav><ul class="pagination justify-content-center pagination-sm" id="annPagination"></ul></nav>
</div>

<!-- Create/Edit Modal -->
<div class="modal fade" id="annModal" tabindex="-1"><div class="modal-dialog modal-lg modal-dialog-scrollable"><div class="modal-content">
    <form id="annForm" novalidate>
        <div class="modal-header bg-warning"><h5 class="modal-title" id="annModalTitle">New System Announcement</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body">
            <input type="hidden" id="annEditId">
            <div class="row g-3">
                <div class="col-12"><label class="form-label fw-semibold">Title <span class="text-danger">*</span></label><input type="text" class="form-control" id="annTitle" required maxlength="255"></div>
                <div class="col-md-4"><label class="form-label fw-semibold">Type</label><select class="form-select" id="annType"><option value="general">General</option><option value="academic">Academic</option><option value="administrative">Administrative</option><option value="event">Event</option><option value="emergency">Emergency</option><option value="maintenance">Maintenance</option></select></div>
                <div class="col-md-4"><label class="form-label fw-semibold">Priority</label><select class="form-select" id="annPriority"><option value="normal">Normal</option><option value="low">Low</option><option value="high">High</option><option value="critical">Critical</option></select></div>
                <div class="col-md-4"><label class="form-label fw-semibold">Target Audience</label><select class="form-select" id="annAudience"><option value="all">All</option><option value="staff">Staff</option><option value="students">Students</option><option value="parents">Parents</option></select></div>
                <div class="col-12"><label class="form-label fw-semibold">Content <span class="text-danger">*</span></label><textarea class="form-control" id="annContent" rows="5" required></textarea></div>
                <div class="col-md-4"><label class="form-label fw-semibold">Status</label><select class="form-select" id="annStatus"><option value="draft">Draft</option><option value="scheduled">Scheduled</option><option value="published">Publish Now</option></select></div>
                <div class="col-md-4"><label class="form-label fw-semibold">Schedule At</label><input type="datetime-local" class="form-control" id="annScheduledAt"></div>
                <div class="col-md-4"><label class="form-label fw-semibold">Expires At</label><input type="datetime-local" class="form-control" id="annExpiresAt"></div>
            </div>
        </div>
        <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button><button type="submit" class="btn btn-warning" id="annSaveBtn"><i class="bi bi-check-lg me-1"></i>Save</button></div>
    </form>
</div></div></div>

<!-- View Modal -->
<div class="modal fade" id="annViewModal" tabindex="-1"><div class="modal-dialog modal-lg modal-dialog-scrollable"><div class="modal-content">
    <div class="modal-header bg-warning"><h5 class="modal-title" id="annViewTitle">Announcement</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
    <div class="modal-body" id="annViewBody"></div>
</div></div></div>

<script src="<?= htmlspecialchars($appBase) ?>/js/pages/system/manage_system_announcements.js?v=<?= asset_version('js/pages/system/manage_system_announcements.js') ?>"></script>