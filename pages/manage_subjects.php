<?php
/**
 * Manage Subjects Page  
 * HTML structure only - all logic in js/pages/academics.js (academicsController)
 * Embedded in app_layout.php
 */
?>

<div class="card shadow-sm">
    <div class="card-header bg-gradient bg-primary text-white">
        <div class="d-flex justify-content-between align-items-center">
            <h4 class="mb-0"><i class="bi bi-book-fill"></i> Subjects & Curriculum Management</h4>
            <div class="btn-group">
                <button class="btn btn-light btn-sm" onclick="academicsController.showSubjectModal()" data-permission="academic_create">
                    <i class="bi bi-plus-circle"></i> Add Subject
                </button>
                <button class="btn btn-outline-light btn-sm" onclick="academicsController.showCurriculumUnitModal()" data-permission="academic_create">
                    <i class="bi bi-journal-text"></i> Add Curriculum Unit
                </button>
                <button class="btn btn-outline-light btn-sm" onclick="academicsController.exportSubjects()">
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
                        <h6 class="text-muted mb-2">Total Subjects</h6>
                        <h3 class="text-primary mb-0" id="totalSubjectsCount">0</h3>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card border-success">
                    <div class="card-body text-center">
                        <h6 class="text-muted mb-2">Core Subjects</h6>
                        <h3 class="text-success mb-0" id="coreSubjectsCount">0</h3>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card border-info">
                    <div class="card-body text-center">
                        <h6 class="text-muted mb-2">Optional Subjects</h6>
                        <h3 class="text-info mb-0" id="optionalSubjectsCount">0</h3>
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

        <!-- Filters and Search -->
        <div class="row mb-3">
            <div class="col-md-4">
                <div class="input-group">
                    <span class="input-group-text"><i class="bi bi-search"></i></span>
                    <input type="text" id="searchSubjects" class="form-control" 
                           placeholder="Search subjects..." 
                           onkeyup="academicsController.searchSubjects(this.value)">
                </div>
            </div>
            <div class="col-md-3">
                <select id="categoryFilter" class="form-select" onchange="academicsController.filterByCategory(this.value)">
                    <option value="">All Categories</option>
                    <option value="core">Core</option>
                    <option value="optional">Optional</option>
                    <option value="extra-curricular">Extra-Curricular</option>
                </select>
            </div>
            <div class="col-md-3">
                <select id="levelFilter" class="form-select" onchange="academicsController.filterByLevel(this.value)">
                    <option value="">All Levels</option>
                    <option value="primary">Primary</option>
                    <option value="secondary">Secondary</option>
                </select>
            </div>
            <div class="col-md-2">
                <select id="subjectStatusFilter" class="form-select" onchange="academicsController.filterByStatus(this.value)">
                    <option value="">All Status</option>
                    <option value="active">Active</option>
                    <option value="inactive">Inactive</option>
                </select>
            </div>
        </div>

        <!-- Subjects Table -->
        <div class="table-responsive" id="subjectsTableContainer">
            <table class="table table-hover table-striped">
                <thead class="table-light">
                    <tr>
                        <th>#</th>
                        <th>Code</th>
                        <th>Subject Name</th>
                        <th>Category</th>
                        <th>Level</th>
                        <th>Teachers</th>
                        <th>Classes</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody id="subjectsTableBody">
                    <tr>
                        <td colspan="9" class="text-center py-4">
                            <div class="spinner-border text-primary" role="status">
                                <span class="visually-hidden">Loading...</span>
                            </div>
                            <p class="text-muted mt-2">Loading subjects...</p>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <div class="d-flex justify-content-between align-items-center mt-3">
            <div>
                <span class="text-muted">Showing <span id="subjShowingFrom">0</span> to <span id="subjShowingTo">0</span> of <span id="subjTotalRecords">0</span> subjects</span>
            </div>
            <nav>
                <ul class="pagination mb-0" id="subjectsPagination"></ul>
            </nav>
        </div>
    </div>
</div>

<!-- Create/Edit Subject Modal -->
<div class="modal fade" id="subjectModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="subjectModalTitle">
                    <i class="bi bi-book"></i> <span id="subjectModalAction">Add</span> Subject
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form id="subjectForm" onsubmit="academicsController.saveSubject(event)">
                <div class="modal-body">
                    <input type="hidden" id="subjectId">
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Subject Code <span class="text-danger">*</span></label>
                            <input type="text" id="subjectCode" class="form-control" required 
                                   placeholder="e.g., MATH101">
                            <small class="form-text text-muted">Unique identifier for this subject</small>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Subject Name <span class="text-danger">*</span></label>
                            <input type="text" id="subjectName" class="form-control" required 
                                   placeholder="e.g., Mathematics">
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Category <span class="text-danger">*</span></label>
                            <select id="subjectCategory" class="form-select" required>
                                <option value="">Select Category</option>
                                <option value="core">Core Subject</option>
                                <option value="optional">Optional Subject</option>
                                <option value="extra-curricular">Extra-Curricular</option>
                            </select>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Grade Level <span class="text-danger">*</span></label>
                            <select id="subjectGradeLevel" class="form-select" required>
                                <option value="">Select Level</option>
                                <option value="primary">Primary</option>
                                <option value="secondary">Secondary</option>
                                <option value="both">Both</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Department</label>
                            <select id="subjectDepartment" class="form-select">
                                <option value="">Select Department</option>
                                <option value="languages">Languages</option>
                                <option value="sciences">Sciences</option>
                                <option value="mathematics">Mathematics</option>
                                <option value="humanities">Humanities</option>
                                <option value="arts">Arts</option>
                                <option value="physical_education">Physical Education</option>
                            </select>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Status</label>
                            <select id="subjectStatus" class="form-select">
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Assigned Teachers</label>
                        <select id="subjectTeachers" class="form-select" multiple size="3">
                            <!-- Teachers loaded dynamically -->
                        </select>
                        <small class="form-text text-muted">Hold Ctrl/Cmd to select multiple teachers</small>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Description</label>
                        <textarea id="subjectDescription" class="form-control" rows="3" 
                                  placeholder="Brief description of the subject"></textarea>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Applicable Classes</label>
                        <div id="subjectClassesCheckboxes" class="border rounded p-3" style="max-height: 200px; overflow-y: auto;">
                            <!-- Class checkboxes loaded dynamically -->
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="bi bi-x-circle"></i> Cancel
                    </button>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-check-circle"></i> Save Subject
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Curriculum Unit Modal -->
<div class="modal fade" id="curriculumUnitModal" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title">
                    <i class="bi bi-journal-text"></i> <span id="unitModalAction">Add</span> Curriculum Unit
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form id="curriculumUnitForm" onsubmit="academicsController.saveCurriculumUnit(event)">
                <div class="modal-body">
                    <input type="hidden" id="unitId">
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Unit Name <span class="text-danger">*</span></label>
                            <input type="text" id="unitName" class="form-control" required 
                                   placeholder="e.g., Introduction to Algebra">
                        </div>
                        
                        <div class="col-md-3 mb-3">
                            <label class="form-label">Unit Code</label>
                            <input type="text" id="unitCode" class="form-control" 
                                   placeholder="e.g., UNIT-01">
                        </div>
                        
                        <div class="col-md-3 mb-3">
                            <label class="form-label">Sequence Order</label>
                            <input type="number" id="unitSequence" class="form-control" min="1" value="1">
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Subject <span class="text-danger">*</span></label>
                            <select id="unitSubject" class="form-select" required>
                                <option value="">Select Subject</option>
                                <!-- Subjects loaded dynamically -->
                            </select>
                        </div>
                        
                        <div class="col-md-3 mb-3">
                            <label class="form-label">Term/Semester</label>
                            <select id="unitTerm" class="form-select">
                                <option value="">Not Specified</option>
                                <option value="1">Term 1</option>
                                <option value="2">Term 2</option>
                                <option value="3">Term 3</option>
                            </select>
                        </div>
                        
                        <div class="col-md-3 mb-3">
                            <label class="form-label">Duration (Hours)</label>
                            <input type="number" id="unitDuration" class="form-control" min="1" 
                                   placeholder="e.g., 20">
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Learning Objectives</label>
                        <textarea id="unitObjectives" class="form-control" rows="3" 
                                  placeholder="What students should learn in this unit..."></textarea>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Topics Covered</label>
                        <textarea id="unitTopics" class="form-control" rows="4" 
                                  placeholder="List the main topics covered in this unit (one per line)"></textarea>
                        <small class="form-text text-muted">Enter each topic on a new line</small>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Assessment Methods</label>
                            <select id="unitAssessmentMethods" class="form-select" multiple size="3">
                                <option value="written_exam">Written Exam</option>
                                <option value="practical">Practical Assessment</option>
                                <option value="project">Project Work</option>
                                <option value="presentation">Presentation</option>
                                <option value="quiz">Quiz</option>
                                <option value="assignment">Assignment</option>
                            </select>
                            <small class="form-text text-muted">Hold Ctrl/Cmd to select multiple</small>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Resources Required</label>
                            <textarea id="unitResources" class="form-control" rows="3" 
                                      placeholder="Textbooks, materials, equipment needed..."></textarea>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Status</label>
                        <select id="unitStatus" class="form-select">
                            <option value="draft">Draft</option>
                            <option value="active">Active</option>
                            <option value="archived">Archived</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="bi bi-x-circle"></i> Cancel
                    </button>
                    <button type="submit" class="btn btn-success">
                        <i class="bi bi-check-circle"></i> Save Unit
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Link to Controller -->
<script src="/Kingsway/js/pages/academics.js"></script>