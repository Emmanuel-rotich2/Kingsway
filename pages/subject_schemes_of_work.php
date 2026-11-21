<?php
// Canonical teacher schemes page. Class-teacher and subject-teacher schemes
// are one capability and must not produce two different UIs or menu entries.
require __DIR__ . '/my_schemes_of_work.php';
return;
?>

<div class="card shadow-sm">
    <div class="card-header bg-gradient bg-info text-white">
        <div class="d-flex justify-content-between align-items-center">
            <h4 class="mb-0">
                <i class="bi bi-book"></i> Subject Schemes of Work
            </h4>
            <div class="btn-group">
                <button class="btn btn-light btn-sm" data-kw-onclick="SubjectSchemesOfWorkController.refresh()">
                    <i class="bi bi-arrow-clockwise"></i> Refresh
                </button>
                <button class="btn btn-outline-light btn-sm" data-kw-onclick="SubjectSchemesOfWorkController.createScheme()" data-year-write>
                    <i class="bi bi-plus-circle"></i> Create Scheme
                </button>
                <button class="btn btn-outline-light btn-sm" data-kw-onclick="SubjectSchemesOfWorkController.exportSchemes()">
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

        <!-- Academic Year/Term Selector -->
        <div class="row mb-3">
            <div class="col-md-6">
                <select id="academicYearSelect" class="form-select" aria-label="Academic year"></select>
            </div>
            <div class="col-md-6">
                <select id="termSelect" class="form-select" aria-label="Term"></select>
            </div>
        </div>

        <!-- Subject Filter -->
        <div class="row mb-3">
            <div class="col-md-12">
                <select id="subjectSelect" class="form-select">
                    <option value="">All Subjects</option>
                </select>
            </div>
        </div>

        <!-- Schemes Table -->
        <div class="table-responsive">
            <table class="table table-hover table-striped" id="schemesTable">
                <thead class="table-light">
                    <tr>
                        <th scope="col">#</th>
                        <th scope="col">Subject</th>
                        <th scope="col">Class</th>
                        <th scope="col">Term</th>
                        <th scope="col">Status</th>
                        <th scope="col">Progress</th>
                        <th scope="col">Last Updated</th>
                        <th scope="col">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td colspan="8" class="text-center py-4">
                            <div class="spinner-border text-primary" role="status">
                                <span class="visually-hidden">Loading...</span>
                            </div>
                            <p class="text-muted mt-2">Loading subject schemes of work...</p>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php asset_script($appBase, 'js/pages/subject_schemes_of_work.js'); ?>
