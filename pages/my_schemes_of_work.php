<?php
/**
 * My Schemes of Work Page
 * Purpose: Class Teacher-specific view of their own schemes of work
 * Features: Personal scheme management, curriculum coverage tracking
 * Block 4: Teaching Delivery
 * Role: Class Teacher (7)
 */

?>

<div class="card shadow-sm">
    <div class="card-header bg-gradient bg-primary text-white">
        <div class="d-flex justify-content-between align-items-center">
            <h4 class="mb-0">
                <i class="bi bi-book"></i> My Schemes of Work
            </h4>
            <div class="btn-group">
                <button class="btn btn-light btn-sm" data-kw-onclick="MySchemesOfWorkController.refresh()">
                    <i class="bi bi-arrow-clockwise"></i> Refresh
                </button>
                <button class="btn btn-outline-light btn-sm" data-kw-onclick="MySchemesOfWorkController.createScheme()" data-year-write>
                    <i class="bi bi-plus-circle"></i> Create Scheme
                </button>
                <button class="btn btn-outline-light btn-sm" data-kw-onclick="MySchemesOfWorkController.exportSchemes()">
                    <i class="bi bi-download"></i> Export
                </button>
            </div>
        </div>
    </div>
    
    <div class="card-body">
        <!-- Statistics Cards -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="card border-primary">
                    <div class="card-body text-center">
                        <h6 class="text-muted mb-2">Total Schemes</h6>
                        <h3 class="text-primary mb-0" id="totalSchemesCount">0</h3>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card border-success">
                    <div class="card-body text-center">
                        <h6 class="text-muted mb-2">Approved</h6>
                        <h3 class="text-success mb-0" id="approvedSchemesCount">0</h3>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card border-warning">
                    <div class="card-body text-center">
                        <h6 class="text-muted mb-2">Pending Review</h6>
                        <h3 class="text-warning mb-0" id="pendingSchemesCount">0</h3>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card border-danger">
                    <div class="card-body text-center">
                        <h6 class="text-muted mb-2">Overdue</h6>
                        <h3 class="text-danger mb-0" id="overdueSchemesCount">0</h3>
                    </div>
                </div>
            </div>
        </div>

        <div class="alert alert-light border mb-3" id="currentPlanningContext"><i class="bi bi-calendar2-check text-success me-2"></i>Loading current academic year and term…</div>

        <!-- Schemes Table -->
        <div class="table-responsive">
            <table class="table table-hover table-striped" id="schemesTable">
                <thead class="table-light">
                    <tr>
                        <th scope="col">#</th>
                        <th scope="col">Subject</th>
                        <th scope="col">Class / Stream</th>
                        <th scope="col">Planned Weeks</th>
                        <th scope="col">Planned Coverage</th>
                        <th scope="col">Status</th>
                        <th scope="col">Progress</th>
                        <th scope="col">Last Updated</th>
                        <th scope="col">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td colspan="9" class="text-center py-4">
                            <div class="spinner-border text-primary" role="status">
                                <span class="visually-hidden">Loading...</span>
                            </div>
                            <p class="text-muted mt-2">Loading your schemes of work...</p>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="modal fade" id="teacherSchemeBuilder" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-fullscreen-xl-down modal-xl modal-dialog-scrollable"><div class="modal-content">
    <div class="modal-header bg-success text-white"><div><h5 class="modal-title mb-1"><i class="bi bi-calendar-week me-2"></i>Term Scheme Workspace <span id="tsPeriod" class="fw-normal"></span></h5><small>Week is the planning base. Add multiple strands and sub-strands inside every week.</small></div><button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button></div>
    <div class="modal-body">
      <div class="alert alert-light border small"><i class="bi bi-info-circle text-success me-1"></i>The academic year, term and calendar weeks come from the year and term selected at the top of the page (the running term, or the next one, by default). Select your authorised stream and learning area once, then complete any number of weeks. You can save and resume before submitting.</div>
      <form id="teacherSchemeForm"><div class="row g-3 mb-3">
        <div class="col-md-5"><label class="form-label">Class / Stream</label><select id="tsStream" class="form-select" required><option value="">Select stream</option></select></div>
        <div class="col-md-5"><label class="form-label">Learning Area</label><select id="tsArea" class="form-select" required><option value="">Select learning area</option></select></div>
        <div class="col-md-2"><label class="form-label">Draft title</label><input id="tsTitle" class="form-control" placeholder="Term plan"></div>
      </div></form>
      <div id="tsWeeks" class="d-grid gap-3"><div class="alert alert-secondary">Select a stream and learning area to load the term weeks.</div></div>
    </div><div class="modal-footer"><span id="tsSaveStatus" class="small text-muted me-auto"></span><button class="btn btn-outline-secondary" data-bs-dismiss="modal">Close</button><button class="btn btn-outline-success" id="saveTeacherScheme" data-year-write><i class="bi bi-save me-1"></i>Save progress</button><button class="btn btn-success" id="submitTeacherScheme" data-year-write><i class="bi bi-send me-1"></i>Submit completed scheme</button></div>
  </div></div>
</div>

<?php asset_script($appBase, 'js/pages/my_schemes_of_work.js'); ?>
