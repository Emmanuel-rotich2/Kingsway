<?php
/**
 * My Subject CATs - Subject Teacher Specific CAT Management
 * Role: Subject Teacher (8)
 * Shows only CATs for the teacher's assigned subjects
 * Provides focused interface for Subject Teachers to manage their assessments
 */
?>
<div class="container-fluid py-4">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">
                        <i class="bi bi-clipboard-data me-2"></i>My Subject CATs
                    </h5>
                    <small class="text-muted">Continuous Assessment Tests for Your Subjects</small>
                </div>
                <div class="card-body">
                    <div id="myCatsLoading" class="text-center py-4">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                        <p class="mt-2 text-muted">Loading your CATs...</p>
                    </div>
                    <div id="myCatsContent" style="display: none;">
                        <div class="row mb-3">
                            <div class="col-md-4">
                                <label class="form-label">Academic Year</label>
                                <select id="yearFilter" class="form-select" data-permission="academic_view">
                                    <option value="">All Years</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Term</label>
                                <select id="termFilter" class="form-select" data-permission="academic_view">
                                    <option value="">All Terms</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Subject</label>
                                <select id="subjectFilter" class="form-select" data-permission="academic_view">
                                    <option value="">All Subjects</option>
                                </select>
                            </div>
                        </div>
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <div class="d-flex gap-2">
                                <button id="createCatBtn" class="btn btn-primary" data-permission="assessments_create">
                                    <i class="bi bi-plus-lg me-1"></i>Create CAT
                                </button>
                                <button id="refreshBtn" class="btn btn-outline-secondary">
                                    <i class="bi bi-arrow-clockwise me-1"></i>Refresh
                                </button>
                            </div>
                            <div id="statsContainer" class="d-flex gap-3">
                                <span class="badge bg-primary">Total: <span id="totalCats">0</span></span>
                                <span class="badge bg-success">Active: <span id="activeCats">0</span></span>
                                <span class="badge bg-warning">Draft: <span id="draftCats">0</span></span>
                            </div>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-hover" id="catsTable">
                                <thead>
                                    <tr>
                                        <th>CAT Name</th>
                                        <th>Subject</th>
                                        <th>Class</th>
                                        <th>Type</th>
                                        <th>Date</th>
                                        <th>Status</th>
                                        <th>Students</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody id="catsTableBody">
                                    <tr>
                                        <td colspan="8" class="text-center text-muted py-4">
                                            No CATs found for your subjects
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

<!-- Create/Edit CAT Modal -->
<div class="modal fade" id="catModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="catModalTitle">Create CAT</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="catForm">
                    <input type="hidden" id="catId">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">CAT Name *</label>
                            <input type="text" class="form-control" id="catName" required>
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
                        <div class="col-md-6 mb-3">
                            <label class="form-label">CAT Date *</label>
                            <input type="date" class="form-control" id="catDate" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Max Marks *</label>
                            <input type="number" class="form-control" id="catMaxMarks" value="20" required>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Description</label>
                        <textarea class="form-control" id="catDescription" rows="3"></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Status</label>
                        <select class="form-select" id="catStatus">
                            <option value="draft">Draft</option>
                            <option value="active">Active</option>
                            <option value="completed">Completed</option>
                        </select>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="saveCatBtn">Save CAT</button>
            </div>
        </div>
    </div>
</div>

<script src="js/pages/my_subject_cats.js"></script>
