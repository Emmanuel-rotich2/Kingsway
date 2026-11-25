<?php
/** Kingsway System Administrator: System Announcements (system domain). */

?>
<div class="container-fluid py-3">
    <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
        <div>
            <h2 class="mb-1"><i class="bi bi-megaphone me-2" aria-hidden="true"></i>System announcements</h2>
            <p class="text-muted mb-0">Create and manage system-wide announcements and notices.</p>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <button type="button" class="btn btn-warning btn-sm" id="annCreateBtn"><i class="bi bi-plus-circle me-1" aria-hidden="true"></i>New announcement</button>
            <button type="button" class="btn btn-outline-secondary btn-sm" id="annExportCsv"><i class="bi bi-filetype-csv me-1" aria-hidden="true"></i>Export CSV</button>
            <button type="button" class="btn btn-outline-secondary btn-sm" id="annPrint"><i class="bi bi-printer me-1" aria-hidden="true"></i>Print / PDF</button>
            <button type="button" class="btn btn-outline-secondary btn-sm" id="annRefresh"><i class="bi bi-arrow-clockwise me-1" aria-hidden="true"></i>Refresh</button>
        </div>
    </div>
    <div class="row g-3 mb-4">
        <div class="col-md-2"><div class="card h-100"><div class="card-body text-center py-3"><div class="h4 mb-0" id="annStatTotal">0</div><small class="text-muted">Total</small></div></div></div>
        <div class="col-md-2"><div class="card h-100"><div class="card-body text-center py-3"><div class="h4 mb-0" id="annStatPublished">0</div><small class="text-muted">Published</small></div></div></div>
        <div class="col-md-2"><div class="card h-100"><div class="card-body text-center py-3"><div class="h4 mb-0" id="annStatDraft">0</div><small class="text-muted">Drafts</small></div></div></div>
        <div class="col-md-2"><div class="card h-100"><div class="card-body text-center py-3"><div class="h4 mb-0" id="annStatScheduled">0</div><small class="text-muted">Scheduled</small></div></div></div>
        <div class="col-md-2"><div class="card h-100"><div class="card-body text-center py-3"><div class="h4 mb-0" id="annStatExpiring">0</div><small class="text-muted">Expiring</small></div></div></div>
        <div class="col-md-2"><div class="card h-100"><div class="card-body text-center py-3"><div class="h4 mb-0" id="annStatArchived">0</div><small class="text-muted">Archived</small></div></div></div>
    </div>
    <div id="annState" class="alert alert-info" role="status" aria-live="polite"><i class="bi bi-hourglass-split me-1" aria-hidden="true"></i><span data-ann-state-message>Loading announcements…</span></div>
    <div class="row mb-3 g-2 align-items-end">
        <div class="col-md-3"><label class="visually-hidden" for="annSearch">Search announcements</label><input type="search" class="form-control form-control-sm" id="annSearch" placeholder="Search announcements..." autocomplete="off"></div>
        <div class="col-md-2"><label class="visually-hidden" for="annStatusFilter">Filter by status</label><select class="form-select form-select-sm" id="annStatusFilter"><option value="">All statuses</option><option value="draft">Draft</option><option value="scheduled">Scheduled</option><option value="published">Published</option><option value="archived">Archived</option><option value="expired">Expired</option></select></div>
        <div class="col-md-2"><label class="visually-hidden" for="annTypeFilter">Filter by type</label><select class="form-select form-select-sm" id="annTypeFilter"><option value="">All types</option><option value="general">General</option><option value="academic">Academic</option><option value="administrative">Administrative</option><option value="event">Event</option><option value="emergency">Emergency</option><option value="maintenance">Maintenance</option></select></div>
        <div class="col-md-2"><label class="visually-hidden" for="annPriorityFilter">Filter by priority</label><select class="form-select form-select-sm" id="annPriorityFilter"><option value="">All priorities</option><option value="critical">Critical</option><option value="high">High</option><option value="normal">Normal</option><option value="low">Low</option></select></div>
        <div class="col-md-1"><button type="button" class="btn btn-outline-secondary btn-sm w-100" id="annClearFilters" aria-label="Clear filters" title="Clear filters"><i class="bi bi-x-circle" aria-hidden="true"></i></button></div>
    </div>
    <div class="table-responsive"><table class="table table-hover table-sm" id="annTable">
        <caption class="visually-hidden">System announcements</caption>
        <thead><tr><th scope="col">Title</th><th scope="col">Type</th><th scope="col">Priority</th><th scope="col">Audience</th><th scope="col">Status</th><th scope="col">Created</th><th scope="col">Actions</th></tr></thead>
        <tbody id="annTableBody">
            <tr id="annTableState"><td colspan="7" class="text-center text-muted py-4">Loading announcements…</td></tr>
        </tbody>
    </table></div>
    <nav aria-label="Announcement pages"><ul class="pagination justify-content-center pagination-sm" id="annPagination"></ul></nav>
    <template id="annRowTemplate">
        <tr data-announcement-row>
            <td class="fw-semibold" data-field="title"></td>
            <td><span class="badge" data-field="type"></span></td>
            <td><span class="badge" data-field="priority"></span></td>
            <td data-field="audience"></td>
            <td><span class="badge" data-field="status"></span></td>
            <td><small data-field="created"></small></td>
            <td>
                <button type="button" class="btn btn-sm btn-outline-info me-1" data-action="view" title="View"><i aria-hidden="true" class="bi bi-eye"></i></button>
                <button type="button" class="btn btn-sm btn-outline-primary me-1" data-action="edit" title="Edit"><i aria-hidden="true" class="bi bi-pencil"></i></button>
                <button type="button" class="btn btn-sm btn-outline-danger" data-action="delete" title="Delete"><i aria-hidden="true" class="bi bi-trash"></i></button>
            </td>
        </tr>
    </template>
    <template id="annPageNumberTemplate"><li class="page-item"><a class="page-link" href="#" data-page></a></li></template>
    <template id="annPagePreviousTemplate"><li class="page-item"><a class="page-link" href="#" data-page aria-label="Previous">&laquo;</a></li></template>
    <template id="annPageNextTemplate"><li class="page-item"><a class="page-link" href="#" data-page aria-label="Next">&raquo;</a></li></template>
    <template id="annPageEllipsisTemplate"><li class="page-item disabled"><span class="page-link">...</span></li></template>
</div>

<!-- Create/Edit Modal -->
<div class="modal fade" id="annModal" tabindex="-1"><div class="modal-dialog modal-lg modal-dialog-scrollable"><div class="modal-content">
    <form id="annForm" novalidate>
        <div class="modal-header"><h5 class="modal-title" id="annModalTitle">New system announcement</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
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
        <div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button><button type="submit" class="btn btn-warning" id="annSaveBtn"><i class="bi bi-check-lg me-1" aria-hidden="true"></i>Save</button></div>
    </form>
</div></div></div>

<!-- View Modal -->
<div class="modal fade" id="annViewModal" tabindex="-1"><div class="modal-dialog modal-lg modal-dialog-scrollable"><div class="modal-content">
    <div class="modal-header"><h5 class="modal-title" id="annViewTitle">Announcement</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
    <div class="modal-body" id="annViewBody">
        <div class="mb-3">
            <span class="badge bg-secondary me-1" data-view="type"></span>
            <span class="badge bg-primary me-1" data-view="priority"></span>
            <span class="badge bg-success me-1" data-view="status"></span>
        </div>
        <p><strong>Target:</strong> <span data-view="audience"></span> &bull; <strong>Views:</strong> <span data-view="views"></span></p>
        <div class="p-3 bg-light rounded mb-3 text-break" style="white-space:pre-wrap" data-view="content"></div>
        <small class="text-muted">Created: <span data-view="created"></span> | Updated: <span data-view="updated"></span></small>
    </div>
</div></div></div>

<script src="<?= htmlspecialchars($appBase) ?>/js/pages/system/manage_system_announcements.js?v=<?= asset_version('js/pages/system/manage_system_announcements.js') ?>"></script>
