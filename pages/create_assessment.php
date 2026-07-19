<?php
/**
 * Create Assessment - School Admin Assessment Creation
 * Role: School Administrator (4)
 * Purpose: Create assessments for all classes and subjects
 * Full access to all classes and subjects for assessment creation
 */
?>
<div class="container-fluid py-4">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">
                        <i class="bi bi-plus-circle me-2"></i>Create Assessment
                    </h5>
                    <small class="text-muted">Create assessments for all classes and subjects</small>
                </div>
                <div class="card-body">
                    <div id="assessmentLoading" class="text-center py-4">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                        <p class="mt-2 text-muted">Loading assessment data...</p>
                    </div>
                    <div id="assessmentContent" style="display: none;">
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label">Assessment Name *</label>
                                <input type="text" class="form-control" id="assessmentName" placeholder="Enter assessment name" data-permission="assessments_create">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Assessment Type *</label>
                                <select class="form-select" id="assessmentType" data-permission="assessments_create">
                                    <option value="">Select Type</option>
                                    <option value="cat">CAT (Continuous Assessment Test)</option>
                                    <option value="exam">Examination</option>
                                    <option value="assignment">Assignment</option>
                                    <option value="project">Project</option>
                                    <option value="quiz">Quiz</option>
                                    <option value="practical">Practical</option>
                                    <option value="oral">Oral</option>
                                </select>
                            </div>
                        </div>
                        <div class="row mb-3">
                            <div class="col-md-4">
                                <label class="form-label">Academic Year *</label>
                                <select class="form-select" id="yearFilter" data-permission="academic_view">
                                    <option value="">Select Year</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Term *</label>
                                <select class="form-select" id="termFilter" data-permission="academic_view">
                                    <option value="">Select Term</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Class *</label>
                                <select class="form-select" id="classFilter" data-permission="academic_view">
                                    <option value="">Select Class</option>
                                </select>
                            </div>
                        </div>
                        <div class="row mb-3">
                            <div class="col-md-4">
                                <label class="form-label">Subject</label>
                                <select class="form-select" id="subjectFilter" data-permission="academic_view">
                                    <option value="">All Subjects</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Max Marks *</label>
                                <input type="number" class="form-control" id="maxMarks" placeholder="Enter max marks" min="1" data-permission="assessments_create">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Assessment Date *</label>
                                <input type="date" class="form-control" id="assessmentDate" data-permission="assessments_create">
                            </div>
                        </div>
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label">Description</label>
                                <textarea class="form-control" id="assessmentDescription" rows="3" placeholder="Enter assessment description" data-permission="assessments_create"></textarea>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Instructions</label>
                                <textarea class="form-control" id="assessmentInstructions" rows="3" placeholder="Enter assessment instructions" data-permission="assessments_create"></textarea>
                            </div>
                        </div>
                        <div class="row mb-3">
                            <div class="col-md-4">
                                <label class="form-label">Duration (minutes)</label>
                                <input type="number" class="form-control" id="assessmentDuration" placeholder="Enter duration" min="0" data-permission="assessments_create">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Venue</label>
                                <input type="text" class="form-control" id="assessmentVenue" placeholder="Enter venue" data-permission="assessments_create">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Status</label>
                                <select class="form-select" id="assessmentStatus" data-permission="assessments_create">
                                    <option value="draft">Draft</option>
                                    <option value="active">Active</option>
                                    <option value="completed">Completed</option>
                                </select>
                            </div>
                        </div>

                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <div class="d-flex gap-2">
                                <button id="createAssessmentBtn" class="btn btn-primary" data-permission="assessments_create">
                                    <i class="bi bi-plus-circle me-1"></i>Create Assessment
                                </button>
                                <button id="resetBtn" class="btn btn-outline-secondary">
                                    <i class="bi bi-arrow-counterclockwise me-1"></i>Reset Form
                                </button>
                            </div>
                            <div id="statsContainer" class="d-flex gap-3">
                                <span class="badge bg-primary">This Year: <span id="yearAssessments">0</span></span>
                                <span class="badge bg-success">This Term: <span id="termAssessments">0</span></span>
                            </div>
                        </div>

                        <div class="table-responsive">
                            <table class="table table-hover" id="recentAssessmentsTable">
                                <thead>
                                    <tr>
                                        <th>Assessment Name</th>
                                        <th>Type</th>
                                        <th>Class</th>
                                        <th>Subject</th>
                                        <th>Date</th>
                                        <th>Max Marks</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody id="recentAssessmentsBody">
                                    <tr>
                                        <td colspan="8" class="text-center text-muted py-4">
                                            No recent assessments
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="js/pages/create_assessment.js"></script>
