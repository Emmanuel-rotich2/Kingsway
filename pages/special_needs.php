<?php
/**
 * Special Needs Page
 * HTML structure only - logic in js/pages/special_needs.js
 * Embedded in app_layout.php
 *
 * Role-based access:
 * - Class Teacher: View and manage records for own class students
 * - Headteacher: View all records, approve IEPs
 * - Admin: Full access
 */
?>

<div class="container-fluid py-4">
    <!-- Page Header -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h4 class="mb-1"><i class="fas fa-hand-holding-heart me-2"></i>Special Needs</h4>
                    <p class="text-muted mb-0">Track and manage students with special educational needs and IEPs</p>
                </div>
                <div class="btn-group">
                    <button class="btn btn-primary btn-sm" id="addRecordBtn"
                            data-role="class_teacher,headteacher,admin">
                        <i class="bi bi-plus-circle me-1"></i> Add Record
                    </button>
                    <button class="btn btn-outline-primary btn-sm" id="exportRecordsBtn"
                            data-role="headteacher,admin">
                        <i class="bi bi-download me-1"></i> Export
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- KPI Summary Cards -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card border-primary">
                <div class="card-body text-center">
                    <h6 class="text-muted mb-2">Total Students</h6>
                    <h3 class="text-primary mb-0" id="totalSNStudents">0</h3>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-success">
                <div class="card-body text-center">
                    <h6 class="text-muted mb-2">With IEP</h6>
                    <h3 class="text-success mb-0" id="withIEP">0</h3>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-warning">
                <div class="card-body text-center">
                    <h6 class="text-muted mb-2">Under Review</h6>
                    <h3 class="text-warning mb-0" id="underReview">0</h3>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-info">
                <div class="card-body text-center">
                    <h6 class="text-muted mb-2">Support Active</h6>
                    <h3 class="text-info mb-0" id="supportActive">0</h3>
                </div>
            </div>
        </div>
    </div>

    <!-- Filter / Search Row -->
    <div class="card mb-4">
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-3">
                    <label class="form-label">Class</label>
                    <select class="form-select" id="classFilter">
                        <option value="">All Classes</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Category</label>
                    <select class="form-select" id="categoryFilter">
                        <option value="">All Categories</option>
                        <option value="learning_disability">Learning Disability</option>
                        <option value="physical_disability">Physical Disability</option>
                        <option value="visual_impairment">Visual Impairment</option>
                        <option value="hearing_impairment">Hearing Impairment</option>
                        <option value="speech_disorder">Speech Disorder</option>
                        <option value="autism">Autism Spectrum</option>
                        <option value="adhd">ADHD</option>
                        <option value="emotional_behavioral">Emotional/Behavioral</option>
                        <option value="gifted">Gifted & Talented</option>
                        <option value="other">Other</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Status</label>
                    <select class="form-select" id="statusFilter">
                        <option value="">All Status</option>
                        <option value="active">Active IEP</option>
                        <option value="under_review">Under Review</option>
                        <option value="pending">Pending Assessment</option>
                        <option value="graduated">Graduated/Exited</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Search</label>
                    <input type="text" class="form-control" id="searchBox"
                           placeholder="Search student name...">
                </div>
            </div>
        </div>
    </div>

    <!-- Main Data Table -->
    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover" id="specialNeedsTable">
                    <thead class="table-light">
                        <tr>
                            <th>Student</th>
                            <th>Class</th>
                            <th>Category</th>
                            <th>IEP Status</th>
                            <th>Support Plan</th>
                            <th>Last Review</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <!-- Dynamic content -->
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <nav>
                <ul class="pagination justify-content-center" id="pagination">
                    <!-- Dynamic pagination -->
                </ul>
            </nav>
        </div>
    </div>
</div>

<!-- Add / Edit Special Needs Record Modal -->
<div class="modal fade" id="snRecordModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="snRecordModalTitle">Add Special Needs Record</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="snRecordForm">
                    <input type="hidden" id="recordId">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Student*</label>
                            <select class="form-select" id="recordStudent" required>
                                <option value="">Select Student</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Category*</label>
                            <select class="form-select" id="recordCategory" required>
                                <option value="">Select Category</option>
                                <option value="learning_disability">Learning Disability</option>
                                <option value="physical_disability">Physical Disability</option>
                                <option value="visual_impairment">Visual Impairment</option>
                                <option value="hearing_impairment">Hearing Impairment</option>
                                <option value="speech_disorder">Speech Disorder</option>
                                <option value="autism">Autism Spectrum</option>
                                <option value="adhd">ADHD</option>
                                <option value="emotional_behavioral">Emotional/Behavioral</option>
                                <option value="gifted">Gifted & Talented</option>
                                <option value="other">Other</option>
                            </select>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Diagnosis / Assessment Details*</label>
                        <textarea class="form-control" id="recordDiagnosis" rows="3" required
                                  placeholder="Describe the diagnosis or assessment findings..."></textarea>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">IEP Status*</label>
                            <select class="form-select" id="recordIEPStatus" required>
                                <option value="pending">Pending Assessment</option>
                                <option value="under_review">Under Review</option>
                                <option value="active">Active</option>
                                <option value="graduated">Graduated/Exited</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Next Review Date</label>
                            <input type="date" class="form-control" id="recordReviewDate">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Support Plan*</label>
                        <textarea class="form-control" id="recordSupportPlan" rows="4" required
                                  placeholder="Describe the accommodations and support strategies..."></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Goals / Objectives</label>
                        <textarea class="form-control" id="recordGoals" rows="3"
                                  placeholder="List the IEP goals and objectives..."></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Parent/Guardian Notes</label>
                        <textarea class="form-control" id="recordParentNotes" rows="2"
                                  placeholder="Notes from parent/guardian communication..."></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="saveRecordBtn">Save Record</button>
            </div>
        </div>
    </div>
</div>

<!-- View Record Details Modal -->
<div class="modal fade" id="viewRecordModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-light">
                <h5 class="modal-title">Special Needs Record Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="row mb-3">
                    <div class="col-md-6">
                        <p><strong>Student:</strong> <span id="viewSNStudent"></span></p>
                        <p><strong>Class:</strong> <span id="viewSNClass"></span></p>
                        <p><strong>Category:</strong> <span id="viewSNCategory"></span></p>
                    </div>
                    <div class="col-md-6">
                        <p><strong>IEP Status:</strong> <span id="viewSNIEPStatus"></span></p>
                        <p><strong>Last Review:</strong> <span id="viewSNLastReview"></span></p>
                        <p><strong>Next Review:</strong> <span id="viewSNNextReview"></span></p>
                    </div>
                </div>
                <div class="mb-3">
                    <strong>Diagnosis:</strong>
                    <p id="viewSNDiagnosis" class="mt-2 p-3 bg-light rounded"></p>
                </div>
                <div class="mb-3">
                    <strong>Support Plan:</strong>
                    <p id="viewSNSupportPlan" class="mt-2 p-3 bg-light rounded"></p>
                </div>
                <div class="mb-3">
                    <strong>Goals:</strong>
                    <p id="viewSNGoals" class="mt-2 p-3 bg-light rounded"></p>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-primary" id="editFromViewBtn">
                    <i class="bi bi-pencil me-1"></i> Edit
                </button>
                <button type="button" class="btn btn-info" id="printRecordBtn">
                    <i class="bi bi-printer me-1"></i> Print
                </button>
            </div>
        </div>
    </div>
</div>

<script src="/Kingsway/js/pages/special_needs.js"></script>
