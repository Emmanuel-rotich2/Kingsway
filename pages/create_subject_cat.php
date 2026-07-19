<?php
/**
 * Create Subject CAT - Subject Teacher CAT Creation
 * Role: Subject Teacher (8)
 * Purpose: Create CATs for their assigned subjects
 * Shows only subjects the teacher is assigned to
 */
?>
<div class="container-fluid py-4">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">
                        <i class="bi bi-plus-circle me-2"></i>Create Subject CAT
                    </h5>
                    <small class="text-muted">Create Continuous Assessment Tests for your subjects</small>
                </div>
                <div class="card-body">
                    <div id="catLoading" class="text-center py-4">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                        <p class="mt-2 text-muted">Loading subject data...</p>
                    </div>
                    <div id="catContent" style="display: none;">
                        <form id="createCatForm">
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">CAT Name *</label>
                                    <input type="text" class="form-control" id="catName" required placeholder="e.g., Mathematics Assignment 1">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">CAT Type *</label>
                                    <select class="form-select" id="catType" required>
                                        <option value="">Select Type</option>
                                        <option value="assignment">Assignment</option>
                                        <option value="homework">Homework</option>
                                        <option value="quiz">Quiz</option>
                                        <option value="project">Project</option>
                                        <option value="oral">Oral</option>
                                        <option value="portfolio">Portfolio</option>
                                        <option value="observation">Observation</option>
                                    </select>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Subject *</label>
                                    <select class="form-select" id="catSubject" required>
                                        <option value="">Select Subject</option>
                                    </select>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Class *</label>
                                    <select class="form-select" id="catClass" required>
                                        <option value="">Select Class</option>
                                    </select>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">Academic Year *</label>
                                    <select class="form-select" id="catYear" required>
                                        <option value="">Select Year</option>
                                    </select>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">Term *</label>
                                    <select class="form-select" id="catTerm" required>
                                        <option value="">Select Term</option>
                                    </select>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">CAT Date *</label>
                                    <input type="date" class="form-control" id="catDate" required>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Max Marks *</label>
                                    <input type="number" class="form-control" id="catMaxMarks" value="20" required min="1">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Status</label>
                                    <select class="form-select" id="catStatus">
                                        <option value="draft">Draft</option>
                                        <option value="active">Active</option>
                                        <option value="completed">Completed</option>
                                    </select>
                                </div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Description</label>
                                <textarea class="form-control" id="catDescription" rows="3" placeholder="Enter assessment description..."></textarea>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Instructions</label>
                                <textarea class="form-control" id="catInstructions" rows="2" placeholder="Enter instructions for students..."></textarea>
                            </div>
                            <div class="d-flex gap-2">
                                <button type="submit" class="btn btn-primary" data-permission="assessments_create">
                                    <i class="bi bi-save me-1"></i>Create CAT
                                </button>
                                <button type="button" class="btn btn-outline-secondary" id="resetBtn">
                                    <i class="bi bi-arrow-counterclockwise me-1"></i>Reset Form
                                </button>
                                <button type="button" class="btn btn-outline-info" id="viewMyCatsBtn">
                                    <i class="bi bi-list me-1"></i>View My CATs
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="js/pages/create_subject_cat.js"></script>
