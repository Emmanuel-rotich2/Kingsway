<?php
/** Canonical learning-area register. Curriculum edits are governed proposals. */
?>
<div class="card shadow-sm">
    <div class="card-header bg-primary text-white d-flex flex-wrap justify-content-between align-items-center gap-2">
        <div>
            <h4 class="mb-1"><i class="bi bi-book-fill me-2"></i>Learning Areas</h4>
            <p class="mb-0">View the school’s canonical CBC learning areas. Propose changes through curriculum governance.</p>
        </div>
        <div class="d-flex gap-2">
            <a class="btn btn-light btn-sm" href="home.php?route=curriculum_proposals"><i class="bi bi-lightbulb me-1"></i>Curriculum Proposals</a>
            <button class="btn btn-outline-light btn-sm" id="printSubjects" type="button"><i class="bi bi-printer me-1"></i>Print</button>
            <button class="btn btn-outline-light btn-sm" onclick="ManageSubjectsController.exportSubjects()" type="button"><i class="bi bi-download me-1"></i>Export CSV</button>
        </div>
    </div>
    <div class="card-body">
        <div class="alert alert-danger d-none" id="msError" role="alert"></div>
        <div class="row g-3 mb-4">
            <div class="col-md-4"><div class="card border-primary"><div class="card-body text-center"><div class="text-muted">Total Learning Areas</div><strong class="fs-3 text-primary" id="totalSubjectsCount">0</strong></div></div></div>
            <div class="col-md-4"><div class="card border-success"><div class="card-body text-center"><div class="text-muted">Active</div><strong class="fs-3 text-success" id="coreSubjectsCount">0</strong></div></div></div>
            <div class="col-md-4"><div class="card border-secondary"><div class="card-body text-center"><div class="text-muted">Inactive</div><strong class="fs-3 text-secondary" id="optionalSubjectsCount">0</strong></div></div></div>
        </div>
        <div class="row g-2 mb-3">
            <div class="col-md-8"><label for="searchSubjects" class="form-label">Search</label><input type="search" id="searchSubjects" class="form-control" placeholder="Name or code"></div>
            <div class="col-md-4"><label for="subjectStatusFilter" class="form-label">Status</label><select id="subjectStatusFilter" class="form-select"><option value="">All statuses</option><option value="active">Active</option><option value="inactive">Inactive</option></select></div>
        </div>
        <div class="table-responsive">
            <table class="table table-hover table-striped" id="subjectsTable">
                <thead class="table-light"><tr><th scope="col">#</th><th scope="col">Learning Area</th><th scope="col">Code</th><th scope="col">Status</th></tr></thead>
                <tbody id="msSubjectsBody"><tr><td colspan="4" class="text-center py-4">Loading learning areas…</td></tr></tbody>
            </table>
        </div>
        <div class="text-muted small">Showing <span id="subjShowingFrom">0</span>–<span id="subjShowingTo">0</span> of <span id="subjTotalRecords">0</span></div>
    </div>
</div>
<?php asset_script($appBase, 'js/pages/manage_subjects.js'); ?>
