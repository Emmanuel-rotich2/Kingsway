<?php
/**
 * Manage Classes Page
 * HTML structure - logic in js/pages/academics.js (academicsController)
 * Embedded in app_layout.php
 * Block 1: Academic Setup - Classes, Streams, Class Teachers
 */
?>

<!-- Classes Management Page -->
<div class="card shadow-sm">
    <div class="card-header bg-gradient bg-primary text-white">
        <div class="d-flex justify-content-between align-items-center">
            <h4 class="mb-0">
                <i class="bi bi-building"></i> Classes Management
            </h4>
            <div class="d-flex align-items-center gap-2 flex-wrap">
            <select class="form-select form-select-sm" id="academicYearFilter" aria-label="Academic year" style="min-width: 13rem"><option>Loading…</option></select>
            <div class="btn-group">
                <button class="btn btn-light btn-sm" data-kw-onclick="academicsController.showClassModal()" data-permission="academic_create">
                    <i class="bi bi-plus-circle"></i> Add Class
                </button>
                <button class="btn btn-light btn-sm" data-kw-onclick="window.location.href='<?= $appBase ?>/home.php?route=class_streams'" data-permission="academic_create">
                    <i class="bi bi-diagram-3"></i> Manage Streams
                </button>
                <button class="btn btn-outline-light btn-sm" data-kw-onclick="academicsController.exportClasses()">
                    <i class="bi bi-download"></i> Export
                </button>
            </div>
            </div>
        </div>
    </div>
    
    <div class="card-body">
        <!-- Statistics Cards -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="card border-primary">
                    <div class="card-body text-center">
                        <h6 class="text-muted mb-2">Total Classes</h6>
                        <h3 class="text-primary mb-0" id="totalClassesCount">0</h3>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card border-success">
                    <div class="card-body text-center">
                        <h6 class="text-muted mb-2">Active Streams</h6>
                        <h3 class="text-success mb-0" id="activeStreamsCount">0</h3>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card border-info">
                    <div class="card-body text-center">
                        <h6 class="text-muted mb-2">Students Enrolled</h6>
                        <h3 class="text-info mb-0" id="studentsEnrolledCount">0</h3>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card border-warning">
                    <div class="card-body text-center">
                        <h6 class="text-muted mb-2">Teachers Assigned</h6>
                        <h3 class="text-warning mb-0" id="teachersAssignedCount">0</h3>
                    </div>
                </div>
            </div>
        </div>

        <!-- Tabs Navigation -->
        <ul class="nav nav-tabs mb-3" id="classesTabs" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active" id="all-tab" data-bs-toggle="tab" data-bs-target="#all-classes" 
                        type="button" role="tab">
                    <i class="bi bi-grid"></i> All Classes
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="streams-tab" data-bs-toggle="tab" data-bs-target="#streams" 
                        type="button" role="tab">
                    <i class="bi bi-diagram-3"></i> Streams
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="teachers-tab" data-bs-toggle="tab" data-bs-target="#class-teachers" 
                        type="button" role="tab">
                    <i class="bi bi-person-badge"></i> Class Teachers
                </button>
            </li>
        </ul>

        <!-- Tab Content -->
        <div class="tab-content" id="classesTabContent">
            <!-- All Classes Tab -->
            <div class="tab-pane fade show active" id="all-classes" role="tabpanel">
                <!-- Classes Table -->
                <div class="table-responsive">
                    <table class="table table-hover table-striped" id="classesTable">
                        <thead class="table-light">
                            <tr>
                                <th scope="col">#</th>
                                <th scope="col">Class Name</th>
                                <th scope="col">Grade Level</th>
                                <th scope="col">Streams</th>
                                <th scope="col">Students</th>
                                <th scope="col">Teachers</th>
                                <th scope="col">Capacity</th>
                                <th scope="col">Status</th>
                                <th scope="col">Actions</th>
                            </tr>
                        </thead>
                        <tbody id="classesTableBody">
                            <tr>
                                <td colspan="8" class="text-center py-4">
                                    <div class="spinner-border text-primary" role="status">
                                        <span class="visually-hidden">Loading...</span>
                                    </div>
                                    <p class="text-muted mt-2">Loading classes...</p>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <div class="d-flex justify-content-between align-items-center mt-3">
                    <div>
                        <span class="text-muted">Showing <span id="classShowingFrom">0</span> to <span id="classShowingTo">0</span> of <span id="classTotalRecords">0</span> classes</span>
                    </div>
                    <nav>
                        <ul class="pagination mb-0" id="classesPagination"></ul>
                    </nav>
                </div>
            </div>

            <!-- Streams Tab -->
            <div class="tab-pane fade" id="streams" role="tabpanel">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead class="table-light">
                            <tr>
                                <th scope="col">#</th>
                                <th scope="col">Stream Name</th>
                                <th scope="col">Class</th>
                                <th scope="col">Students</th>
                                <th scope="col">Teacher</th>
                                <th scope="col">Capacity</th>
                                <th scope="col">Status</th>
                            </tr>
                        </thead>
                        <tbody id="streamsTableBody">
                            <tr>
                                <td colspan="7" class="text-center py-4">
                                    <div class="spinner-border text-success" role="status"></div>
                                    <p class="text-muted mt-2">Loading streams...</p>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Class Teachers Tab -->
            <div class="tab-pane fade" id="class-teachers" role="tabpanel">
                <p class="text-muted small mb-2">
                    Class teachers for this year. To assign or change one, use
                    <a href="<?= $appBase ?>/home.php?route=assign_class_teachers">Class Teachers</a>.
                </p>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead class="table-light">
                            <tr>
                                <th scope="col">#</th>
                                <th scope="col">Teacher Name</th>
                                <th scope="col">Class Assigned</th>
                                <th scope="col">Stream</th>
                                <th scope="col">Students Count</th>
                                <th scope="col">Subject</th>
                                <th scope="col">Contact</th>
                                <th scope="col">Actions</th>
                            </tr>
                        </thead>
                        <tbody id="classTeachersTableBody">
                            <tr>
                                <td colspan="8" class="text-center py-4">
                                    <div class="spinner-border text-info" role="status"></div>
                                    <p class="text-muted mt-2">Loading class teachers...</p>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

        </div>
    </div>
</div>

<!-- Create/Edit Class Modal -->
<div class="modal fade" id="classModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-scrollable modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title">
                    <i class="bi bi-building"></i> <span id="classModalAction">Add</span> Class
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form id="classForm" data-kw-onsubmit="academicsController.saveClass(event)">
                <div class="modal-body">
                    <input type="hidden" id="classId">
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Class Name <span class="text-danger">*</span></label>
                            <input type="text" id="className" class="form-control" required 
                                   placeholder="e.g., Grade 5">
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Grade Level <span class="text-danger">*</span></label>
                            <select id="classGradeLevel" class="form-select" required>
                                <option value="">Select Grade Level</option>
                                <optgroup label="Primary">
                                    <option value="grade_1">Grade 1</option>
                                    <option value="grade_2">Grade 2</option>
                                    <option value="grade_3">Grade 3</option>
                                    <option value="grade_4">Grade 4</option>
                                    <option value="grade_5">Grade 5</option>
                                    <option value="grade_6">Grade 6</option>
                                </optgroup>
                                <optgroup label="Secondary">
                                    <option value="form_1">Form 1</option>
                                    <option value="form_2">Form 2</option>
                                    <option value="form_3">Form 3</option>
                                    <option value="form_4">Form 4</option>
                                </optgroup>
                            </select>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Capacity</label>
                            <input type="number" id="classCapacity" class="form-control" min="1" 
                                   placeholder="e.g., 40">
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Room Number</label>
                            <input type="text" id="classRoom" class="form-control" 
                                   placeholder="e.g., A-101">
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Class Teacher</label>
                            <select id="classTeacher" class="form-select">
                                <option value="">Select Class Teacher</option>
                                <!-- Teachers loaded dynamically -->
                            </select>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Academic Year</label>
                            <input type="text" id="classAcademicYear" class="form-control" readonly
                                   title="Classes are created in the academic year selected at the top of the page">
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Status</label>
                        <select id="classStatus" class="form-select">
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="bi bi-x-circle"></i> Cancel
                    </button>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-check-circle"></i> Save Class
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Create/Edit Stream Modal -->
<div class="modal fade" id="streamModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title">
                    <i class="bi bi-diagram-3"></i> <span id="streamModalAction">Add</span> Stream
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form id="streamForm" data-kw-onsubmit="academicsController.saveStream(event)">
                <div class="modal-body">
                    <input type="hidden" id="streamId">
                    
                    <div class="mb-3">
                        <label class="form-label">Class <span class="text-danger">*</span></label>
                        <select id="streamClass" class="form-select" required>
                            <option value="">Select Class</option>
                            <!-- Classes loaded dynamically -->
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Stream Name <span class="text-danger">*</span></label>
                        <input type="text" id="streamName" class="form-control" required 
                               placeholder="e.g., East, West, North">
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Stream Teacher</label>
                        <select id="streamTeacher" class="form-select">
                            <option value="">Select Teacher</option>
                            <!-- Teachers loaded dynamically -->
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Capacity</label>
                        <input type="number" id="streamCapacity" class="form-control" min="1" 
                               placeholder="e.g., 35">
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Status</label>
                        <select id="streamStatus" class="form-select">
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="bi bi-x-circle"></i> Cancel
                    </button>
                    <button type="submit" class="btn btn-success">
                        <i class="bi bi-check-circle"></i> Save Stream
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Link to Controller -->
<?php asset_script($appBase, 'js/pages/academics.js'); ?>
