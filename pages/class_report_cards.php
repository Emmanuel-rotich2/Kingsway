<?php
/** Class-teacher view of assessment results used to prepare report cards. */
?>
<div class="container-fluid py-4">
    <div class="card">
        <div class="card-header">
            <h5 class="card-title mb-1"><i class="bi bi-file-earmark-text me-2"></i>Class Report Card Results</h5>
            <small class="text-muted">Review assessment results for classes assigned to you.</small>
        </div>
        <div class="card-body">
            <div id="reportCardsError" class="alert alert-danger d-none" role="alert"></div>
            <div id="reportCardsLoading" class="text-center py-4" role="status">
                <div class="spinner-border text-primary" aria-hidden="true"></div>
                <p class="mt-2 text-muted">Loading assessment results…</p>
            </div>
            <div id="reportCardsContent" style="display:none">
                <div class="row g-3 mb-3">
                    <div class="col-md-3"><label class="form-label" for="yearFilter">Academic Year</label><select id="yearFilter" class="form-select"><option value="">All Years</option></select></div>
                    <div class="col-md-3"><label class="form-label" for="termFilter">Term</label><select id="termFilter" class="form-select"><option value="">All Terms</option></select></div>
                    <div class="col-md-3"><label class="form-label" for="classFilter">Class</label><select id="classFilter" class="form-select"><option value="">All Assigned Classes</option></select></div>
                    <div class="col-md-3"><label class="form-label" for="studentFilter">Find Student</label><input id="studentFilter" class="form-control" type="search" placeholder="Name or admission number"></div>
                </div>
                <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
                    <div class="d-flex gap-2"><button id="exportBtn" type="button" class="btn btn-outline-secondary"><i class="bi bi-download me-1"></i>Export CSV</button><button id="printBtn" type="button" class="btn btn-outline-secondary"><i class="bi bi-printer me-1"></i>Print</button></div>
                    <div class="d-flex gap-2"><span class="badge text-bg-primary">Students: <span id="totalStudents">0</span></span><span class="badge text-bg-info">Assessment results: <span id="generatedCount">0</span></span></div>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover" id="studentsTable">
                        <thead><tr><th scope="col">Student</th><th scope="col">Adm No</th><th scope="col">Class</th><th scope="col">Learning Area</th><th scope="col">Assessment</th><th scope="col">Score %</th><th scope="col">Grade</th></tr></thead>
                        <tbody id="studentsTableBody"><tr><td colspan="7" class="text-center text-muted py-4">Loading assessment results…</td></tr></tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
<style>@media print { .btn, select, input { display:none !important; } }</style>
<?php asset_script($appBase, 'js/pages/class_report_cards.js'); ?>
