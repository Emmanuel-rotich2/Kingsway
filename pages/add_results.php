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
            <h4 class="mb-1"><i class="fas fa-plus-circle me-2"></i>Add Student Result</h4>
            <p class="text-muted mb-0">Quick entry for individual student marks</p>
        </div>
        <a href="<?= $appBase ?>home.php?route=enter_results" class="btn btn-outline-primary">
            <i class="fas fa-th me-1"></i>Bulk Entry
        </a>
    </div>

    <div class="row g-4">
        <!-- Entry Form -->
        <div class="col-md-7">
            <div class="card shadow-sm">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0"><i class="fas fa-edit me-2"></i>Result Entry Form</h5>
                </div>
                <div class="card-body">
                    <form id="addResultForm">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Academic Year</label>
                                <select id="yearSelect" class="form-select" required>
                                    <option value="">-- Select Year --</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Term</label>
                                <select id="termSelect" class="form-select" required>
                                    <option value="">-- Select Term --</option>
                                    <option value="Term 1">Term 1</option>
                                    <option value="Term 2">Term 2</option>
                                    <option value="Term 3">Term 3</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Class</label>
                                <select id="classSelect" class="form-select" required onchange="addResultsController.loadStudents()">
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
                                    <option value="CAT">CAT</option>
                                    <option value="Exam">End of Term Exam</option>
                                    <option value="Assignment">Assignment</option>
                                    <option value="Project">Project</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Marks (0-100)</label>
                                <input type="number" id="marksInput" class="form-control" min="0" max="100" step="0.5" required placeholder="0-100" oninput="addResultsController.previewGrade()">
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
                                <textarea id="remarksInput" class="form-control" rows="2" placeholder="Teacher's remarks"></textarea>
                            </div>
                            <div class="col-12">
                                <button type="submit" class="btn btn-primary btn-lg w-100">
                                    <i class="fas fa-save me-2"></i>Save Result
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
                    <h6 class="mb-0"><i class="fas fa-history me-2"></i>Recently Added</h6>
                </div>
                <div class="card-body p-0" id="recentEntries">
                    <div class="text-center text-muted py-4">No entries yet this session</div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="<?= $appBase ?>js/pages/add_results.js"></script>
