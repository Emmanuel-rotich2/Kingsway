<!-- Classes Management Page -->
<div class="card shadow-sm">
    <div class="card-header bg-gradient bg-primary text-white">
        <div class="d-flex justify-content-between align-items-center">
            <h4 class="mb-0">
                <i class="bi bi-building"></i> Classes Management
            </h4>
            <div class="btn-group">
                <button class="btn btn-light btn-sm" onclick="academicsController.showClassModal()" data-permission="create_class">
                    <i class="bi bi-plus-circle"></i> Add Class
                </button>
                <button class="btn btn-light btn-sm" onclick="academicsController.showStreamModal()" data-permission="create_stream">
                    <i class="bi bi-diagram-3"></i> Add Stream
                </button>
                <button class="btn btn-outline-light btn-sm" onclick="academicsController.exportClasses()">
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
                        type="button" role="tab" onclick="academicsController.loadClasses('all')">
                    <i class="bi bi-grid"></i> All Classes
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="streams-tab" data-bs-toggle="tab" data-bs-target="#streams" 
                        type="button" role="tab" onclick="academicsController.loadStreams()">
                    <i class="bi bi-diagram-3"></i> Streams
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="teachers-tab" data-bs-toggle="tab" data-bs-target="#class-teachers" 
                        type="button" role="tab" onclick="academicsController.loadClassTeachers()">
                    <i class="bi bi-person-badge"></i> Class Teachers
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="timetable-tab" data-bs-toggle="tab" data-bs-target="#timetables" 
                        type="button" role="tab" onclick="academicsController.loadTimetables()">
                    <i class="bi bi-calendar3"></i> Timetables
                </button>
            </li>
        </ul>

        <!-- Tab Content -->
        <div class="tab-content" id="classesTabContent">
            <!-- All Classes Tab -->
            <div class="tab-pane fade show active" id="all-classes" role="tabpanel">
                <!-- Filters -->
                <div class="row mb-3">
                    <div class="col-md-4">
                        <div class="input-group">
                            <span class="input-group-text"><i class="bi bi-search"></i></span>
                            <input type="text" id="searchClasses" class="form-control" 
                                   placeholder="Search classes..." 
                                   onkeyup="academicsController.searchClasses(this.value)">
                        </div>
                    </div>
                    <div class="col-md-3">
                        <select id="gradeLevelFilter" class="form-select" onchange="academicsController.filterByGradeLevel(this.value)">
                            <option value="">All Grade Levels</option>
                            <option value="grade_1">Grade 1</option>
                            <option value="grade_2">Grade 2</option>
                            <option value="grade_3">Grade 3</option>
                            <option value="grade_4">Grade 4</option>
                            <option value="grade_5">Grade 5</option>
                            <option value="grade_6">Grade 6</option>
                            <option value="form_1">Form 1</option>
                            <option value="form_2">Form 2</option>
                            <option value="form_3">Form 3</option>
                            <option value="form_4">Form 4</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <select id="sectionFilter" class="form-select" onchange="academicsController.filterBySection(this.value)">
                            <option value="">All Sections</option>
                            <option value="primary">Primary</option>
                            <option value="secondary">Secondary</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <select id="classStatusFilter" class="form-select" onchange="academicsController.filterByClassStatus(this.value)">
                            <option value="">All Status</option>
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                        </select>
                    </div>
                </div>

                <!-- Classes Table -->
                <div class="table-responsive">
                    <table class="table table-hover table-striped">
                        <thead class="table-light">
                            <tr>
                                <th>#</th>
                                <th>Class Name</th>
                                <th>Grade Level</th>
                                <th>Section</th>
                                <th>Streams</th>
                                <th>Students</th>
                                <th>Class Teacher</th>
                                <th>Capacity</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="classesTableBody">
                            <tr>
                                <td colspan="10" class="text-center py-4">
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
                                <th>#</th>
                                <th>Stream Name</th>
                                <th>Class</th>
                                <th>Students</th>
                                <th>Teacher</th>
                                <th>Capacity</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="streamsTableBody">
                            <tr>
                                <td colspan="8" class="text-center py-4">
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
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead class="table-light">
                            <tr>
                                <th>#</th>
                                <th>Teacher Name</th>
                                <th>Class Assigned</th>
                                <th>Stream</th>
                                <th>Students Count</th>
                                <th>Subject</th>
                                <th>Contact</th>
                                <th>Actions</th>
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

            <!-- Timetables Tab -->
            <div class="tab-pane fade" id="timetables" role="tabpanel">
                <div class="row mb-3">
                    <div class="col-md-4">
                        <select id="timetableClassFilter" class="form-select" onchange="academicsController.loadTimetableForClass(this.value)">
                            <option value="">Select Class</option>
                            <!-- Classes loaded dynamically -->
                        </select>
                    </div>
                    <div class="col-md-4">
                        <select id="timetableTermFilter" class="form-select">
                            <option value="">Current Term</option>
                            <option value="1">Term 1</option>
                            <option value="2">Term 2</option>
                            <option value="3">Term 3</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <button class="btn btn-primary" onclick="academicsController.generateTimetable()" data-permission="manage_timetable">
                            <i class="bi bi-gear"></i> Generate Timetable
                        </button>
                    </div>
                </div>
                <div id="timetableContainer" class="border rounded p-3">
                    <p class="text-muted text-center">Select a class to view timetable</p>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Create/Edit Class Modal -->
<div class="modal fade" id="classModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title">
                    <i class="bi bi-building"></i> <span id="classModalAction">Add</span> Class
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form id="classForm" onsubmit="academicsController.saveClass(event)">
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
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Section</label>
                            <select id="classSection" class="form-select">
                                <option value="primary">Primary</option>
                                <option value="secondary">Secondary</option>
                            </select>
                        </div>
                        
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Capacity</label>
                            <input type="number" id="classCapacity" class="form-control" min="1" 
                                   placeholder="e.g., 40">
                        </div>
                        
                        <div class="col-md-4 mb-3">
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
                            <input type="text" id="classAcademicYear" class="form-control" 
                                   value="2024" placeholder="e.g., 2024">
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Description</label>
                        <textarea id="classDescription" class="form-control" rows="2" 
                                  placeholder="Optional description"></textarea>
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
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title">
                    <i class="bi bi-diagram-3"></i> <span id="streamModalAction">Add</span> Stream
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form id="streamForm" onsubmit="academicsController.saveStream(event)">
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

<!-- Assign Teacher Modal -->
<div class="modal fade" id="assignTeacherModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-info text-white">
                <h5 class="modal-title">
                    <i class="bi bi-person-badge"></i> Assign Teacher
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form id="assignTeacherForm" onsubmit="academicsController.assignTeacher(event)">
                <div class="modal-body">
                    <input type="hidden" id="assignmentId">
                    
                    <div class="mb-3">
                        <label class="form-label">Class <span class="text-danger">*</span></label>
                        <select id="assignClass" class="form-select" required>
                            <option value="">Select Class</option>
                            <!-- Classes loaded dynamically -->
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Stream</label>
                        <select id="assignStream" class="form-select">
                            <option value="">No specific stream</option>
                            <!-- Streams loaded based on selected class -->
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Teacher <span class="text-danger">*</span></label>
                        <select id="assignTeacher" class="form-select" required>
                            <option value="">Select Teacher</option>
                            <!-- Teachers loaded dynamically -->
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Subject</label>
                        <select id="assignSubject" class="form-select">
                            <option value="">Not subject-specific</option>
                            <!-- Subjects loaded dynamically -->
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Assignment Type</label>
                        <select id="assignmentType" class="form-select">
                            <option value="class_teacher">Class Teacher</option>
                            <option value="subject_teacher">Subject Teacher</option>
                            <option value="assistant">Assistant Teacher</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="bi bi-x-circle"></i> Cancel
                    </button>
                    <button type="submit" class="btn btn-info">
                        <i class="bi bi-check-circle"></i> Assign Teacher
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Link to Controller -->
<script src="/Kingsway/js/pages/academics.js"></script>
