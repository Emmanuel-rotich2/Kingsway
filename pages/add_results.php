<?php
/**
 * Add Results Page (Quick Single-Student Entry)
 * Modern REST API version - all logic in js/pages/add_results.js
 * Embedded in app_layout.php
 */
?>

<div>
    <!-- Page Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="mb-1"><i class="bi bi-plus-lg-circle me-2"></i>Add Student Result</h4>
            <p class="text-muted mb-0">Quick entry for individual student marks</p>
        </div>
        <a href="<?= $appBase ?>/home.php?route=enter_results" class="btn btn-outline-primary">
            <i class="bi bi-grid-3x3-gap me-1"></i>Bulk Entry
        </a>
    </div>

    <div class="row g-4">
        <!-- Entry Form -->
        <div class="col-md-7">
            <div class="card shadow-sm">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0"><i class="bi bi-pencil me-2"></i>Result Entry Form</h5>
                </div>
                <div class="card-body">
                    <form id="addResultForm">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Academic Year</label>
                                <select id="yearSelect" class="form-select" required aria-label="Academic year"></select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Term</label>
                                <select id="termSelect" class="form-select" required aria-label="Term"></select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Class</label>
                                <select id="classSelect" class="form-select" required data-kw-onchange="addResultsController.loadStudents()">
                                    <option value="">-- Select Class --</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Student</label>
                                <select id="studentSelect" class="form-select" required>
                                    <option value="">-- Select Student --</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Subject</label>
                                <select id="subjectSelect" class="form-select" required>
                                    <option value="">-- Select Subject --</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Assessment Type</label>
                                <select id="assessmentType" class="form-select" required>
                                    <option value="">-- Select Type --</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Marks (0-100)</label>
                                <input type="number" id="marksInput" class="form-control" min="0" max="100" step="0.5" required placeholder="0-100" data-kw-oninput="addResultsController.previewGrade()" data-year-write>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Auto Grade</label>
                                <div class="form-control bg-light text-center" id="gradePreview">
                                    <span class="badge bg-secondary">--</span>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Points</label>
                                <div class="form-control bg-light text-center" id="pointsPreview">--</div>
                            </div>
                            <div class="col-12">
                                <label class="form-label">Remarks (Optional)</label>
                                <textarea id="remarksInput" class="form-control" rows="2" placeholder="Teacher's remarks" data-year-write></textarea>
                            </div>
                            <div class="col-12">
                                <button type="submit" class="btn btn-primary btn-lg w-100" data-year-write>
                                    <i class="bi bi-check-lg me-2"></i>Save Result
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Recent entries sidebar -->
        <div class="col-md-5">
            <div class="card shadow-sm">
                <div class="card-header bg-light">
                    <h6 class="mb-0"><i class="bi bi-clock-history me-2"></i>Recently Added</h6>
                </div>
                <div class="card-body p-0" id="recentEntries">
                    <div class="text-center text-muted py-4">No entries yet this session</div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php asset_script($appBase, 'js/pages/add_results.js'); ?>
