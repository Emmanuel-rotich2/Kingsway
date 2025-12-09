<?php
/**
 * Complete Academic Management System
 * Uses ALL available endpoints from AcademicAPI
 * Green/White Professional Design
 */
?>

<style>
    /* Custom Green/White Theme for Academics */
    :root {
        --academic-green: #198754;
        --academic-green-light: #d1e7dd;
        --academic-green-dark: #146c43;
        --academic-white: #ffffff;
        --academic-gray-light: #f8f9fa;
    }

    .academic-header {
        background: linear-gradient(135deg, var(--academic-green) 0%, var(--academic-green-dark) 100%);
        color: var(--academic-white);
        border-radius: 8px;
        padding: 1.5rem;
        margin-bottom: 1.5rem;
        box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
    }

    .academic-card {
        border-left: 4px solid var(--academic-green);
        border-radius: 8px;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
        transition: all 0.3s ease;
    }

    .academic-card:hover {
        box-shadow: 0 4px 12px rgba(25, 135, 84, 0.15);
        transform: translateY(-2px);
    }

    .nav-tabs .nav-link {
        color: var(--academic-green-dark);
        border: none;
        border-bottom: 3px solid transparent;
        font-weight: 500;
    }

    .nav-tabs .nav-link:hover {
        border-bottom-color: var(--academic-green-light);
    }

    .nav-tabs .nav-link.active {
        color: var(--academic-green);
        border-bottom-color: var(--academic-green);
        background: transparent;
    }

    .btn-academic-primary {
        background: var(--academic-green);
        border-color: var(--academic-green);
        color: white;
    }

    .btn-academic-primary:hover {
        background: var(--academic-green-dark);
        border-color: var(--academic-green-dark);
        color: white;
    }

    .stat-card {
        background: var(--academic-green-light);
        border-left: 4px solid var(--academic-green);
        padding: 1rem;
        border-radius: 6px;
        margin-bottom: 1rem;
    }

    .stat-number {
        font-size: 2rem;
        font-weight: bold;
        color: var(--academic-green-dark);
    }

    .table-academic thead {
        background: var(--academic-green);
        color: white;
    }

    .badge-academic {
        background: var(--academic-green);
        color: white;
    }
</style>

<div class="academic-header">
    <div class="d-flex justify-content-between align-items-center">
        <div>
            <h2 class="mb-1"><i class="bi bi-mortarboard-fill"></i> Academic Management System</h2>
            <p class="mb-0 opacity-75">Comprehensive management of all academic operations</p>
        </div>
        <button class="btn btn-light btn-lg" onclick="academicsManager.showQuickActions()">
            <i class="bi bi-lightning-fill"></i> Quick Actions
        </button>
    </div>
</div>

<!-- Statistics Dashboard -->
<div class="row mb-4" id="academicStats">
    <div class="col-md-3">
        <div class="stat-card">
            <div class="stat-number" id="totalClasses">0</div>
            <div class="text-muted">Total Classes</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-card">
            <div class="stat-number" id="totalSubjects">0</div>
            <div class="text-muted">Learning Areas</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-card">
            <div class="stat-number" id="totalStudents">0</div>
            <div class="text-muted">Total Students</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-card">
            <div class="stat-number" id="activeYear">-</div>
            <div class="text-muted">Current Year</div>
        </div>
    </div>
</div>

<!-- Main Content Tabs -->
<ul class="nav nav-tabs mb-4" role="tablist">
    <li class="nav-item">
        <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#yearsTab">
            <i class="bi bi-calendar3"></i> Academic Years
        </button>
    </li>
    <li class="nav-item">
        <button class="nav-link" data-bs-toggle="tab" data-bs-target="#termsTab">
            <i class="bi bi-calendar-week"></i> Terms
        </button>
    </li>
    <li class="nav-item">
        <button class="nav-link" data-bs-toggle="tab" data-bs-target="#classesTab">
            <i class="bi bi-journal-bookmark"></i> Classes
        </button>
    </li>
    <li class="nav-item">
        <button class="nav-link" data-bs-toggle="tab" data-bs-target="#subjectsTab">
            <i class="bi bi-book"></i> Learning Areas
        </button>
    </li>
    <li class="nav-item">
        <button class="nav-link" data-bs-toggle="tab" data-bs-target="#streamsTab">
            <i class="bi bi-diagram-3"></i> Streams
        </button>
    </li>
    <li class="nav-item">
        <button class="nav-link" data-bs-toggle="tab" data-bs-target="#schedules Tab">
            <i class="bi bi-calendar-event"></i> Timetable
        </button>
    </li>
    <li class="nav-item">
        <button class="nav-link" data-bs-toggle="tab" data-bs-target="#curriculumTab">
            <i class="bi bi-list-check"></i> Curriculum
        </button>
    </li>
    <li class="nav-item">
        <button class="nav-link" data-bs-toggle="tab" data-bs-target="#teachersTab">
            <i class="bi bi-person-workspace"></i> Teachers
        </button>
    </li>
</ul>

<div class="tab-content">
    <!-- Academic Years Tab -->
    <div class="tab-pane fade show active" id="yearsTab">
        <div class="card academic-card">
            <div class="card-header bg-white">
                <div class="d-flex justify-content-between">
                    <h5 class="mb-0"><i class="bi bi-calendar3"></i> Academic Years</h5>
                    <button class="btn btn-academic-primary btn-sm" onclick="academicsManager.showYearModal()">
                        <i class="bi bi-plus-circle"></i> Add Year
                    </button>
                </div>
            </div>
            <div class="card-body">
                <div id="yearsContainer">
                    <div class="text-center text-muted py-4">
                        <div class="spinner-border text-success" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                        <p class="mt-2">Loading academic years...</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Terms Tab -->
    <div class="tab-pane fade" id="termsTab">
        <div class="card academic-card">
            <div class="card-header bg-white">
                <div class="d-flex justify-content-between">
                    <h5 class="mb-0"><i class="bi bi-calendar-week"></i> Academic Terms</h5>
                    <button class="btn btn-academic-primary btn-sm" onclick="academicsManager.showTermModal()">
                        <i class="bi bi-plus-circle"></i> Add Term
                    </button>
                </div>
            </div>
            <div class="card-body">
                <div class="row mb-3">
                    <div class="col-md-6">
                        <select class="form-select" id="filterTermsByYear"
                            onchange="academicsManager.filterTermsByYear(this.value)">
                            <option value="">All Academic Years</option>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <select class="form-select" id="filterTermsByStatus"
                            onchange="academicsManager.filterTermsByStatus(this.value)">
                            <option value="">All Status</option>
                            <option value="upcoming">Upcoming</option>
                            <option value="current">Current</option>
                            <option value="completed">Completed</option>
                        </select>
                    </div>
                </div>
                <div id="termsContainer" class="table-responsive"></div>
            </div>
        </div>
    </div>

    <!-- Classes Tab -->
    <div class="tab-pane fade" id="classesTab">
        <div class="card academic-card">
            <div class="card-header bg-white">
                <div class="d-flex justify-content-between">
                    <h5 class="mb-0"><i class="bi bi-journal-bookmark"></i> Classes</h5>
                    <button class="btn btn-academic-primary btn-sm" onclick="academicsManager.showClassModal()">
                        <i class="bi bi-plus-circle"></i> Add Class
                    </button>
                </div>
            </div>
            <div class="card-body">
                <div class="row mb-3">
                    <div class="col-md-6">
                        <input type="text" class="form-control" placeholder="🔍 Search classes..." id="searchClasses"
                            onkeyup="academicsManager.searchClasses(this.value)">
                    </div>
                    <div class="col-md-6">
                        <select class="form-select" id="filterByLevel"
                            onchange="academicsManager.filterClassesByLevel(this.value)">
                            <option value="">All Levels</option>
                        </select>
                    </div>
                </div>
                <div id="classesContainer" class="table-responsive"></div>
            </div>
        </div>
    </div>

    <!-- Learning Areas Tab -->
    <div class="tab-pane fade" id="subjectsTab">
        <div class="card academic-card">
            <div class="card-header bg-white">
                <div class="d-flex justify-content-between">
                    <h5 class="mb-0"><i class="bi bi-book"></i> Learning Areas (Subjects)</h5>
                    <button class="btn btn-academic-primary btn-sm" onclick="academicsManager.showSubjectModal()">
                        <i class="bi bi-plus-circle"></i> Add Learning Area
                    </button>
                </div>
            </div>
            <div class="card-body">
                <div class="row mb-3">
                    <div class="col-md-6">
                        <input type="text" class="form-control" placeholder="🔍 Search subjects..." id="searchSubjects"
                            onkeyup="academicsManager.searchSubjects(this.value)">
                    </div>
                    <div class="col-md-6">
                        <select class="form-select" id="filterSubjectsByStatus"
                            onchange="academicsManager.filterSubjectsByStatus(this.value)">
                            <option value="">All Status</option>
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                        </select>
                    </div>
                </div>
                <div id="subjectsContainer" class="table-responsive"></div>
            </div>
        </div>
    </div>

    <!-- Streams Tab -->
    <div class="tab-pane fade" id="streamsTab">
        <div class="card academic-card">
            <div class="card-header bg-white">
                <div class="d-flex justify-content-between">
                    <h5 class="mb-0"><i class="bi bi-diagram-3"></i> Class Streams</h5>
                    <button class="btn btn-academic-primary btn-sm" onclick="academicsManager.showStreamModal()">
                        <i class="bi bi-plus-circle"></i> Add Stream
                    </button>
                </div>
            </div>
            <div class="card-body">
                <div id="streamsContainer" class="table-responsive"></div>
            </div>
        </div>
    </div>

    <!-- Schedules Tab -->
    <div class="tab-pane fade" id="schedulesTab">
        <div class="card academic-card">
            <div class="card-header bg-white">
                <div class="d-flex justify-content-between">
                    <h5 class="mb-0"><i class="bi bi-calendar-event"></i> Class Timetable</h5>
                    <button class="btn btn-academic-primary btn-sm" onclick="academicsManager.showScheduleModal()">
                        <i class="bi bi-plus-circle"></i> Add Schedule
                    </button>
                </div>
            </div>
            <div class="card-body">
                <div id="schedulesContainer"></div>
            </div>
        </div>
    </div>

    <!-- Curriculum Tab -->
    <div class="tab-pane fade" id="curriculumTab">
        <div class="card academic-card">
            <div class="card-header bg-white">
                <div class="d-flex justify-content-between">
                    <h5 class="mb-0"><i class="bi bi-list-check"></i> Curriculum Management</h5>
                    <div class="btn-group btn-group-sm">
                        <button class="btn btn-academic-primary" onclick="academicsManager.showCurriculumUnitModal()">
                            <i class="bi bi-plus"></i> Add Unit
                        </button>
                        <button class="btn btn-outline-success" onclick="academicsManager.showTopicModal()">
                            <i class="bi bi-plus"></i> Add Topic
                        </button>
                        <button class="btn btn-outline-success" onclick="academicsManager.showLessonPlanModal()">
                            <i class="bi bi-plus"></i> Add Lesson Plan
                        </button>
                    </div>
                </div>
            </div>
            <div class="card-body">
                <ul class="nav nav-pills mb-3">
                    <li class="nav-item">
                        <button class="nav-link active" data-bs-toggle="tab"
                            data-bs-target="#currUnitsTab">Units</button>
                    </li>
                    <li class="nav-item">
                        <button class="nav-link" data-bs-toggle="tab" data-bs-target="#topicsTab">Topics</button>
                    </li>
                    <li class="nav-item">
                        <button class="nav-link" data-bs-toggle="tab" data-bs-target="#lessonPlansTab">Lesson
                            Plans</button>
                    </li>
                </ul>
                <div class="tab-content">
                    <div class="tab-pane fade show active" id="currUnitsTab">
                        <div id="curriculumUnitsContainer"></div>
                    </div>
                    <div class="tab-pane fade" id="topicsTab">
                        <div id="topicsContainer"></div>
                    </div>
                    <div class="tab-pane fade" id="lessonPlansTab">
                        <div id="lessonPlansContainer"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Teachers Tab -->
    <div class="tab-pane fade" id="teachersTab">
        <div class="card academic-card">
            <div class="card-header bg-white">
                <h5 class="mb-0"><i class="bi bi-person-workspace"></i> Teacher Assignments & Schedules</h5>
            </div>
            <div class="card-body">
                <div class="row mb-3">
                    <div class="col-md-6">
                        <select class="form-select" id="selectTeacher"
                            onchange="academicsManager.loadTeacherDetails(this.value)">
                            <option value="">Select Teacher...</option>
                        </select>
                    </div>
                </div>
                <div id="teacherDetailsContainer"></div>
            </div>
        </div>
    </div>
</div>

<!-- Toast Container -->
<div class="position-fixed top-0 end-0 p-3" style="z-index: 11000">
    <div id="academicToast" class="toast" role="alert">
        <div class="toast-header">
            <i class="bi bi-info-circle me-2" id="toastIcon"></i>
            <strong class="me-auto" id="toastTitle">Notification</strong>
            <button type="button" class="btn-close" data-bs-dismiss="toast"></button>
        </div>
        <div class="toast-body" id="toastBody"></div>
    </div>
</div>

<script src="/Kingsway/js/pages/academicsManager.js"></script>