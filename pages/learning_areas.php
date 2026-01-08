<?php
/**
 * Learning Areas / Subjects Management Page
 * 
 * Purpose: Manage subjects, curriculum, and schemes of work
 * Features:
 * - View and manage all subjects
 * - Assign subjects to teachers
 * - Manage CBC curriculum
 * - Handle schemes of work
 */
?>

<div class="container-fluid py-4">
    <!-- Page Header -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h4 class="mb-1"><i class="fas fa-layer-group me-2"></i>Learning Areas</h4>
                    <p class="text-muted mb-0">Manage subjects, curriculum, and teaching assignments</p>
                </div>
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addSubjectModal">
                    <i class="fas fa-plus me-1"></i> Add Subject
                </button>
            </div>
        </div>
    </div>

    <!-- Quick Stats -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card bg-primary text-white">
                <div class="card-body">
                    <h6 class="card-title">Total Subjects</h6>
                    <h2 id="totalSubjects">--</h2>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-success text-white">
                <div class="card-body">
                    <h6 class="card-title">Active Subjects</h6>
                    <h2 id="activeSubjects">--</h2>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-info text-white">
                <div class="card-body">
                    <h6 class="card-title">Teachers Assigned</h6>
                    <h2 id="teachersAssigned">--</h2>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-warning text-dark">
                <div class="card-body">
                    <h6 class="card-title">Pending SOW</h6>
                    <h2 id="pendingSow">--</h2>
                </div>
            </div>
        </div>
    </div>

    <!-- Tabs Navigation -->
    <ul class="nav nav-tabs mb-4" id="learningAreasTabs" role="tablist">
        <li class="nav-item">
            <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#allSubjects">
                <i class="fas fa-book me-1"></i> All Subjects
            </button>
        </li>
        <li class="nav-item">
            <button class="nav-link" data-bs-toggle="tab" data-bs-target="#teacherAssignments">
                <i class="fas fa-user-tag me-1"></i> Teacher Assignments
            </button>
        </li>
        <li class="nav-item">
            <button class="nav-link" data-bs-toggle="tab" data-bs-target="#curriculum">
                <i class="fas fa-sitemap me-1"></i> Curriculum (CBC)
            </button>
        </li>
        <li class="nav-item">
            <button class="nav-link" data-bs-toggle="tab" data-bs-target="#schemesOfWork">
                <i class="fas fa-file-alt me-1"></i> Schemes of Work
            </button>
        </li>
    </ul>

    <!-- Tab Content -->
    <div class="tab-content">
        <!-- All Subjects Tab -->
        <div class="tab-pane fade show active" id="allSubjects">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Subjects List</h5>
                    <input type="text" class="form-control form-control-sm w-25" placeholder="Search subjects..."
                        id="searchSubjects">
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover" id="subjectsTable">
                            <thead>
                                <tr>
                                    <th>Subject Code</th>
                                    <th>Subject Name</th>
                                    <th>Category</th>
                                    <th>Classes</th>
                                    <th>Teachers</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td colspan="7" class="text-center">Loading subjects...</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Teacher Assignments Tab -->
        <div class="tab-pane fade" id="teacherAssignments">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Subject-Teacher Assignments</h5>
                </div>
                <div class="card-body">
                    <div class="row mb-3">
                        <div class="col-md-4">
                            <select class="form-select" id="filterByClass">
                                <option value="">All Classes</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <select class="form-select" id="filterBySubject">
                                <option value="">All Subjects</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <button class="btn btn-primary w-100" data-bs-toggle="modal"
                                data-bs-target="#assignTeacherModal">
                                <i class="fas fa-plus me-1"></i> New Assignment
                            </button>
                        </div>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-hover" id="assignmentsTable">
                            <thead>
                                <tr>
                                    <th>Teacher</th>
                                    <th>Subject</th>
                                    <th>Class</th>
                                    <th>Lessons/Week</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td colspan="6" class="text-center">Loading assignments...</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Curriculum Tab -->
        <div class="tab-pane fade" id="curriculum">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Competency Based Curriculum (CBC)</h5>
                </div>
                <div class="card-body">
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>
                        Manage CBC curriculum strands, sub-strands, and learning outcomes for each subject.
                    </div>
                    <div id="curriculumTree">
                        <p class="text-muted">Loading curriculum structure...</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Schemes of Work Tab -->
        <div class="tab-pane fade" id="schemesOfWork">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Schemes of Work</h5>
                    <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#uploadSowModal">
                        <i class="fas fa-upload me-1"></i> Upload SOW
                    </button>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover" id="sowTable">
                            <thead>
                                <tr>
                                    <th>Subject</th>
                                    <th>Class</th>
                                    <th>Term</th>
                                    <th>Teacher</th>
                                    <th>Status</th>
                                    <th>Last Updated</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td colspan="7" class="text-center">Loading schemes...</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Add Subject Modal -->
<div class="modal fade" id="addSubjectModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add New Subject</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="addSubjectForm">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Subject Code</label>
                        <input type="text" class="form-control" name="code" placeholder="e.g., MATH" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Subject Name</label>
                        <input type="text" class="form-control" name="name" placeholder="e.g., Mathematics" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Category</label>
                        <select class="form-select" name="category" required>
                            <option value="">Select Category</option>
                            <option value="core">Core Subject</option>
                            <option value="elective">Elective</option>
                            <option value="co-curricular">Co-curricular</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Applicable Levels</label>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="levels[]" value="junior">
                            <label class="form-check-label">Junior School</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="levels[]" value="senior">
                            <label class="form-check-label">Senior School</label>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Add Subject</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="js/pages/learning_areas.js"></script>