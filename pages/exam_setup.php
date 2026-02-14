<?php
/**
 * Exam Setup Page
 * Purpose: Create and manage exam configurations, grading systems, and schedules
 * Features: Exam CRUD, grading scale setup, subject selection, weight configuration
 */
?>

<div>
    <!-- Page Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="mb-1"><i class="bi bi-pencil-square"></i> Exam Setup</h4>
            <small class="text-muted">Configure exams, grading systems, and assessment weights</small>
        </div>
        <div class="btn-group">
            <button class="btn btn-outline-primary btn-sm" id="exportExamsBtn">
                <i class="bi bi-download"></i> Export
            </button>
            <button class="btn btn-success btn-sm" id="addExamBtn">
                <i class="bi bi-plus-circle"></i> Create Exam
            </button>
        </div>
    </div>

    <!-- KPI Summary Cards -->
    <div class="row g-3 mb-4">
        <div class="col-md-3">
            <div class="card border-primary">
                <div class="card-body text-center">
                    <h6 class="text-muted mb-2">Total Exams</h6>
                    <h3 class="text-primary mb-0" id="totalExams">0</h3>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-success">
                <div class="card-body text-center">
                    <h6 class="text-muted mb-2">Active</h6>
                    <h3 class="text-success mb-0" id="activeExams">0</h3>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-warning">
                <div class="card-body text-center">
                    <h6 class="text-muted mb-2">Upcoming</h6>
                    <h3 class="text-warning mb-0" id="upcomingExams">0</h3>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-info">
                <div class="card-body text-center">
                    <h6 class="text-muted mb-2">Completed</h6>
                    <h3 class="text-info mb-0" id="completedExams">0</h3>
                </div>
            </div>
        </div>
    </div>

    <!-- Filter / Search -->
    <div class="card mb-4">
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-3">
                    <select class="form-select" id="termFilterExam">
                        <option value="">All Terms</option>
                        <option value="1">Term 1</option>
                        <option value="2">Term 2</option>
                        <option value="3">Term 3</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <select class="form-select" id="typeFilterExam">
                        <option value="">All Types</option>
                        <option value="midterm">Mid-Term</option>
                        <option value="endterm">End-Term</option>
                        <option value="cat">CAT</option>
                        <option value="assignment">Assignment</option>
                        <option value="practical">Practical</option>
                        <option value="mock">Mock</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <select class="form-select" id="statusFilterExam">
                        <option value="">All Status</option>
                        <option value="active">Active</option>
                        <option value="upcoming">Upcoming</option>
                        <option value="completed">Completed</option>
                        <option value="draft">Draft</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <input type="text" class="form-control" id="searchExams" placeholder="Search exams...">
                </div>
            </div>
        </div>
    </div>

    <!-- Main Data Table -->
    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover table-bordered" id="examsTable">
                    <thead class="table-light">
                        <tr>
                            <th>#</th>
                            <th>Exam Name</th>
                            <th>Type</th>
                            <th>Term</th>
                            <th>Max Marks</th>
                            <th>Weight %</th>
                            <th>Grading System</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody id="examsTableBody">
                        <!-- Dynamic content -->
                    </tbody>
                </table>
            </div>
            <div class="d-flex justify-content-between align-items-center mt-3">
                <small class="text-muted">
                    Showing <span id="showingFrom">0</span> to <span id="showingTo">0</span> of <span
                        id="totalRecords">0</span> exams
                </small>
                <nav>
                    <ul class="pagination pagination-sm mb-0" id="pagination"></ul>
                </nav>
            </div>
        </div>
    </div>
</div>

<!-- Create/Edit Exam Modal -->
<div class="modal fade" id="examModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="examModalLabel">Create Exam</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="examForm">
                    <input type="hidden" id="examId">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Exam Name *</label>
                            <input type="text" class="form-control" id="examName" required
                                placeholder="e.g., End of Term 1 2026">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Exam Type *</label>
                            <select class="form-select" id="examType" required>
                                <option value="">Select Type</option>
                                <option value="midterm">Mid-Term</option>
                                <option value="endterm">End-Term</option>
                                <option value="cat">CAT</option>
                                <option value="assignment">Assignment</option>
                                <option value="practical">Practical</option>
                                <option value="mock">Mock</option>
                            </select>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Term *</label>
                            <select class="form-select" id="examTerm" required>
                                <option value="1">Term 1</option>
                                <option value="2">Term 2</option>
                                <option value="3">Term 3</option>
                            </select>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Max Marks *</label>
                            <input type="number" class="form-control" id="examMaxMarks" required value="100" min="1">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Weight % *</label>
                            <input type="number" class="form-control" id="examWeight" required value="100" min="1"
                                max="100">
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Start Date</label>
                            <input type="date" class="form-control" id="examStartDate">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">End Date</label>
                            <input type="date" class="form-control" id="examEndDate">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Grading System *</label>
                        <select class="form-select" id="examGrading" required>
                            <option value="standard">Standard (A-E)</option>
                            <option value="cbc">CBC Rubric (EE, ME, AE, BE)</option>
                            <option value="percentage">Percentage Only</option>
                            <option value="custom">Custom</option>
                        </select>
                    </div>
                    <div class="mb-3" id="gradingScaleSection">
                        <label class="form-label">Grading Scale</label>
                        <div class="table-responsive">
                            <table class="table table-sm table-bordered" id="gradingScaleTable">
                                <thead class="table-light">
                                    <tr>
                                        <th>Grade</th>
                                        <th>Min Score</th>
                                        <th>Max Score</th>
                                        <th>Remarks</th>
                                    </tr>
                                </thead>
                                <tbody id="gradingScaleBody">
                                    <tr>
                                        <td>A</td>
                                        <td><input type="number" class="form-control form-control-sm" value="80" min="0"
                                                max="100"></td>
                                        <td><input type="number" class="form-control form-control-sm" value="100"
                                                min="0" max="100"></td>
                                        <td>Excellent</td>
                                    </tr>
                                    <tr>
                                        <td>B</td>
                                        <td><input type="number" class="form-control form-control-sm" value="70" min="0"
                                                max="100"></td>
                                        <td><input type="number" class="form-control form-control-sm" value="79" min="0"
                                                max="100"></td>
                                        <td>Good</td>
                                    </tr>
                                    <tr>
                                        <td>C</td>
                                        <td><input type="number" class="form-control form-control-sm" value="60" min="0"
                                                max="100"></td>
                                        <td><input type="number" class="form-control form-control-sm" value="69" min="0"
                                                max="100"></td>
                                        <td>Average</td>
                                    </tr>
                                    <tr>
                                        <td>D</td>
                                        <td><input type="number" class="form-control form-control-sm" value="50" min="0"
                                                max="100"></td>
                                        <td><input type="number" class="form-control form-control-sm" value="59" min="0"
                                                max="100"></td>
                                        <td>Below Average</td>
                                    </tr>
                                    <tr>
                                        <td>E</td>
                                        <td><input type="number" class="form-control form-control-sm" value="0" min="0"
                                                max="100"></td>
                                        <td><input type="number" class="form-control form-control-sm" value="49" min="0"
                                                max="100"></td>
                                        <td>Fail</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Applicable Subjects</label>
                        <select class="form-select" id="examSubjects" multiple size="5">
                            <!-- Dynamic subject options -->
                        </select>
                        <small class="text-muted">Hold Ctrl/Cmd to select multiple. Leave empty for all
                            subjects.</small>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Status</label>
                        <select class="form-select" id="examStatus">
                            <option value="draft">Draft</option>
                            <option value="active">Active</option>
                            <option value="upcoming">Upcoming</option>
                            <option value="completed">Completed</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Description / Instructions</label>
                        <textarea class="form-control" id="examDescription" rows="3"
                            placeholder="Exam instructions or notes..."></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="saveExamBtn">Save Exam</button>
            </div>
        </div>
    </div>
</div>

<script src="/Kingsway/js/pages/exam_setup.js?v=<?php echo time(); ?>"></script>