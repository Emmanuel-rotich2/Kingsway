<?php
/**
 * Manage Holidays
 *
 * Purpose: UI-managed HOLIDAY REGISTRY - the single source of truth for ALL
 * holidays (national, religious, inter-term April/August/December, school).
 * The academic calendar generator (sp_generate_year_calendar) reads from this
 * registry the same way it picks up weekends and school days, so nothing is
 * hardcoded: re-date Idd-ul-Fitr when the moon is sighted, change holiday
 * spans, add or remove holidays, then click "Apply to Calendar".
 * Features:
 * - Data display and filtering by type / year / search
 * - Add, edit (re-date), delete holidays
 * - Apply the registry to the academic calendar + events
 * - Search and export
 */
?>

<div>
    <div class="row mb-4">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                <div>
                    <h4 class="mb-1"><i class="bi bi-calendar-heart me-2"></i>Manage Holidays</h4>
                    <p class="text-muted mb-0">Master holiday registry - national, religious (moon-based), inter-term (April/August/December) and school holidays</p>
                </div>
                <div class="d-flex gap-2">
                    <button class="btn btn-outline-success" onclick="ManageHolidaysController.applyToCalendar()" title="Regenerate the academic calendar + events from this registry"><i class="bi bi-lightning-charge me-1"></i> Apply to Calendar</button>
                    <button class="btn btn-primary" onclick="ManageHolidaysController.showAddModal()"><i class="bi bi-plus-lg me-1"></i> Add Holiday</button>
                </div>
            </div>
        </div>
    </div>

    <div class="row mb-4">
        <div class="col-md-3 mb-3">
            <div class="card shadow-sm border-0"><div class="card-body"><div class="d-flex align-items-center">
                <div class="rounded-circle bg-primary bg-opacity-10 p-3 me-3"><i class="bi bi-calendar text-primary fa-lg"></i></div>
                <div><h6 class="text-muted mb-1">Total Holidays</h6><h4 class="mb-0" id="statTotal">0</h4></div>
            </div></div></div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="card shadow-sm border-0"><div class="card-body"><div class="d-flex align-items-center">
                <div class="rounded-circle bg-warning bg-opacity-10 p-3 me-3"><i class="bi bi-flag text-warning fa-lg"></i></div>
                <div><h6 class="text-muted mb-1">National</h6><h4 class="mb-0" id="statNational">0</h4></div>
            </div></div></div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="card shadow-sm border-0"><div class="card-body"><div class="d-flex align-items-center">
                <div class="rounded-circle bg-info bg-opacity-10 p-3 me-3"><i class="bi bi-moon-stars text-info fa-lg"></i></div>
                <div><h6 class="text-muted mb-1">Religious</h6><h4 class="mb-0" id="statReligious">0</h4></div>
            </div></div></div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="card shadow-sm border-0"><div class="card-body"><div class="d-flex align-items-center">
                <div class="rounded-circle bg-secondary bg-opacity-10 p-3 me-3"><i class="bi bi-pause-circle text-secondary fa-lg"></i></div>
                <div><h6 class="text-muted mb-1">Inter-Term / Inactive</h6><h4 class="mb-0" id="statOther">0</h4></div>
            </div></div></div>
        </div>
    </div>

    <div class="card shadow-sm mb-4">
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-3"><input type="text" class="form-control" id="searchInput" placeholder="Search holidays..."></div>
                <div class="col-md-3"><select class="form-select" id="typeFilter">
                    <option value="">All Types</option>
                    <option value="national">National</option>
                    <option value="religious">Religious</option>
                    <option value="inter_term">Inter-Term (April/August/December)</option>
                    <option value="school">School</option>
                </select></div>
                <div class="col-md-2"><input type="date" class="form-control" id="dateFilter"></div>
                <div class="col-md-2"><button class="btn btn-outline-secondary w-100" onclick="ManageHolidaysController.refresh()"><i class="bi bi-arrow-clockwise"></i></button></div>
                <div class="col-md-2"><button class="btn btn-outline-success w-100" onclick="ManageHolidaysController.exportCSV()"><i class="bi bi-file-csv me-1"></i> Export</button></div>
            </div>
        </div>
    </div>

    <div class="card shadow-sm">
        <div class="card-header bg-white d-flex justify-content-between align-items-center">
            <h6 class="mb-0"><i class="bi bi-table me-2"></i>Holiday Registry</h6>
            <span class="text-muted small"><i class="bi bi-info-circle me-1"></i>Edit a holiday (e.g. re-date Idd-ul-Fitr) then click <strong>Apply to Calendar</strong> to update the whole year.</span>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0" id="dataTable">
                    <thead class="table-light"><tr><th scope="col">#</th><th scope="col">Holiday</th><th scope="col">Type</th><th scope="col">Start Date</th><th scope="col">End Date</th><th scope="col">Days</th><th scope="col">Status</th><th scope="col">Actions</th></tr></thead>
                    <tbody><tr><td colspan="8" class="text-center text-muted py-4">Loading...</td></tr></tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="formModal" tabindex="-1"><div class="modal-dialog modal-dialog-scrollable modal-lg"><div class="modal-content">
    <div class="modal-header"><h5 class="modal-title" id="formModalTitle"><i class="bi bi-calendar-plus me-2"></i>Add Holiday</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
    <div class="modal-body"><form id="recordForm"><input type="hidden" id="recordId">
        <div class="mb-3"><label class="form-label">Holiday Name <span class="text-danger">*</span></label><input type="text" class="form-control" id="recordName" required placeholder="e.g., Idd-ul-Fitr, April Holiday, Diwali"></div>
        <div class="row g-3">
            <div class="col-md-6 mb-3"><label class="form-label">Holiday Type</label><select class="form-select" id="recordType">
                <option value="national">National</option>
                <option value="religious">Religious (moon-based)</option>
                <option value="inter_term">Inter-Term (April / August / December)</option>
                <option value="school">School</option>
            </select></div>
            <div class="col-md-6 mb-3"><label class="form-label">Status</label><select class="form-select" id="recordActive"><option value="1">Active</option><option value="0">Inactive</option></select></div>
        </div>
        <div class="row g-3">
            <div class="col-md-6 mb-3"><label class="form-label">Start Date <span class="text-danger">*</span></label><input type="date" class="form-control" id="recordStartDate" required></div>
            <div class="col-md-6 mb-3"><label class="form-label">End Date <span class="text-muted small">(same day = single-day holiday)</span></label><input type="date" class="form-control" id="recordEndDate"></div>
        </div>
        <div class="mb-3"><label class="form-label">Description / Notes</label><textarea class="form-control" id="recordDescription" rows="2" placeholder="e.g., Approximate date - subject to moon sighting"></textarea></div>
    </form></div>
    <div class="modal-footer">
        <button type="button" class="btn btn-outline-success me-auto" onclick="ManageHolidaysController.saveAndApply()"><i class="bi bi-lightning-charge me-1"></i> Save &amp; Apply to Calendar</button>
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-primary" onclick="ManageHolidaysController.saveRecord()"><i class="bi bi-check-lg me-1"></i> Save</button>
    </div>
</div></div></div>

<script src="<?= $appBase ?>/js/pages/manage_holidays.js?v=<?php echo time(); ?>"></script>
