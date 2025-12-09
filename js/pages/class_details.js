/**
 * Class Details Page - Drill-Down Navigation Example
 * Flow: Classes List → Class Details → Student Profile → Learning Area Progress
 * Uses PageNavigator for in-page navigation
 */

let pageNav = null;

document.addEventListener('DOMContentLoaded', async () => {
    if (!AuthContext.isAuthenticated()) {
        window.location.href = '/Kingsway/index.php';
        return;
    }

    initializePageNavigation();
});

function initializePageNavigation() {
    // Create page navigator
    pageNav = new PageNavigator('mainContent', {
        breadcrumbContainer: 'breadcrumbContainer'
    });

    // Register page hierarchy
    pageNav.registerPage('classList', {
        title: 'Classes',
        render: renderClassList,
        onLoad: loadClasses
    });

    pageNav.registerPage('classDetails', {
        title: 'Class Details',
        parent: 'classList',
        render: renderClassDetails,
        onLoad: loadClassDetails
    });

    pageNav.registerPage('studentProfile', {
        title: 'Student Profile',
        parent: 'classDetails',
        render: renderStudentProfile,
        onLoad: loadStudentProfile
    });

    pageNav.registerPage('learningAreaProgress', {
        title: 'Learning Area Progress',
        parent: 'studentProfile',
        render: renderLearningAreaProgress,
        onLoad: loadLearningAreaProgress
    });

    // Start with class list
    pageNav.navigateTo('classList');
}

// ============ PAGE 1: CLASS LIST ============
function renderClassList() {
    return `
        <div class="row mb-4">
            <div class="col-12">
                <div class="d-flex justify-content-between align-items-center">
                    <h2>Classes</h2>
                    <button class="btn btn-primary" id="createClassBtn">
                        <i class="bi bi-plus"></i> Create Class
                    </button>
                </div>
            </div>
        </div>

        <div class="row mb-4">
            <div class="col-md-3">
                <div class="card">
                    <div class="card-body">
                        <h6 class="text-muted">Total Classes</h6>
                        <h3 id="totalClasses">0</h3>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card">
                    <div class="card-body">
                        <h6 class="text-muted">Total Students</h6>
                        <h3 id="totalStudents">0</h3>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card">
                    <div class="card-body">
                        <h6 class="text-muted">Average Size</h6>
                        <h3 id="avgClassSize">0</h3>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card">
                    <div class="card-body">
                        <h6 class="text-muted">Active Teachers</h6>
                        <h3 id="activeTeachers">0</h3>
                    </div>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <input type="text" class="form-control" id="classSearch" placeholder="Search classes...">
            </div>
            <div class="card-body p-0">
                <div id="classListContainer"></div>
            </div>
        </div>
    `;
}

async function loadClasses() {
    // Load statistics
    const stats = await window.API.apiCall('/reports/class-stats', 'GET');
    if (stats) {
        document.getElementById('totalClasses').textContent = stats.total_classes || 0;
        document.getElementById('totalStudents').textContent = stats.total_students || 0;
        document.getElementById('avgClassSize').textContent = stats.avg_class_size || 0;
        document.getElementById('activeTeachers').textContent = stats.active_teachers || 0;
    }

    // Create table
    const table = new DataTable('classListContainer', {
        apiEndpoint: '/academic/classes',
        pageSize: 10,
        columns: [
            { field: 'id', label: '#' },
            { field: 'name', label: 'Class Name', sortable: true },
            { field: 'form_level', label: 'Form', sortable: true },
            { field: 'teacher_name', label: 'Class Teacher' },
            { field: 'student_count', label: 'Students', type: 'number' },
            { 
                field: 'avg_performance', 
                label: 'Avg Performance',
                type: 'custom',
                formatter: (value) => value ? `${value.toFixed(1)}%` : 'N/A'
            }
        ],
        searchFields: ['name', 'teacher_name'],
        rowActions: [
            { 
                id: 'view', 
                label: 'View Details', 
                icon: 'bi-arrow-right-circle', 
                variant: 'primary',
                permission: 'classes_view' 
            }
        ],
        onRowAction: (action, data) => {
            if (action === 'view') {
                pageNav.navigateTo('classDetails', { classId: data.id, className: data.name });
            }
        }
    });

    document.getElementById('classSearch')?.addEventListener('keyup', (e) => {
        table.search(e.target.value);
    });

    document.getElementById('createClassBtn')?.addEventListener('click', () => {
        // Open modal for creating class (not shown here)
    });
}

// ============ PAGE 2: CLASS DETAILS ============
function renderClassDetails(data) {
    return `
        <div class="row mb-4">
            <div class="col-12">
                <button class="btn btn-outline-secondary btn-sm mb-3" onclick="pageNav.goBack()">
                    <i class="bi bi-arrow-left"></i> Back to Classes
                </button>
                <h2>${data.className || 'Class Details'}</h2>
            </div>
        </div>

        <!-- Class Overview Cards -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="card">
                    <div class="card-body">
                        <h6 class="text-muted">Total Students</h6>
                        <h3 id="classStudentCount">0</h3>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card">
                    <div class="card-body">
                        <h6 class="text-muted">Avg Attendance</h6>
                        <h3 id="classAvgAttendance">0%</h3>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card">
                    <div class="card-body">
                        <h6 class="text-muted">Avg Performance</h6>
                        <h3 id="classAvgPerformance">0%</h3>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card">
                    <div class="card-body">
                        <h6 class="text-muted">Class Teacher</h6>
                        <h6 id="classTeacher">-</h6>
                    </div>
                </div>
            </div>
        </div>

        <!-- Tab Navigation for Class Sections -->
        <div id="classTabsContainer"></div>
    `;
}

async function loadClassDetails(data) {
    const classId = data.classId;

    // Load class overview
    const classInfo = await window.API.apiCall(`/academic/class/${classId}`, 'GET');
    if (classInfo) {
        document.getElementById('classStudentCount').textContent = classInfo.student_count || 0;
        document.getElementById('classAvgAttendance').textContent = (classInfo.avg_attendance || 0).toFixed(1) + '%';
        document.getElementById('classAvgPerformance').textContent = (classInfo.avg_performance || 0).toFixed(1) + '%';
        document.getElementById('classTeacher').textContent = classInfo.teacher_name || 'Not Assigned';
    }

    // Create tab navigation for class sections
    const tabNav = new TabNavigator('classTabsContainer');

    // Students tab
    tabNav.registerTab('students', {
        label: 'Students',
        icon: 'bi-people',
        render: () => renderStudentsTab(classId),
        onActivate: () => loadStudentsTab(classId)
    });

    // Timetable tab
    tabNav.registerTab('timetable', {
        label: 'Timetable',
        icon: 'bi-calendar3',
        render: () => renderTimetableTab(classId),
        onActivate: () => loadTimetableTab(classId)
    });

    // Performance tab
    tabNav.registerTab('performance', {
        label: 'Performance',
        icon: 'bi-graph-up',
        render: () => renderPerformanceTab(classId),
        onActivate: () => loadPerformanceTab(classId)
    });

    // Attendance tab
    tabNav.registerTab('attendance', {
        label: 'Attendance',
        icon: 'bi-clipboard-check',
        render: () => renderAttendanceTab(classId),
        onActivate: () => loadAttendanceTab(classId)
    });

    tabNav.renderTabs();
}

// ============ CLASS DETAILS - STUDENTS TAB ============
function renderStudentsTab(classId) {
    return `
        <div class="card">
            <div class="card-header">
                <div class="row">
                    <div class="col-md-8">
                        <input type="text" class="form-control" id="studentSearch" placeholder="Search students...">
                    </div>
                    <div class="col-md-4 text-end">
                        <button class="btn btn-sm btn-primary" id="addStudentBtn">
                            <i class="bi bi-plus"></i> Add Student
                        </button>
                    </div>
                </div>
            </div>
            <div class="card-body p-0">
                <div id="studentsTableContainer"></div>
            </div>
        </div>
    `;
}

async function loadStudentsTab(classId) {
    const table = new DataTable('studentsTableContainer', {
        apiEndpoint: `/academic/class/${classId}/students`,
        pageSize: 15,
        columns: [
            { field: 'admission_number', label: 'Adm #', sortable: true },
            { field: 'full_name', label: 'Name', sortable: true },
            { field: 'gender', label: 'Gender' },
            { 
                field: 'avg_score', 
                label: 'Avg Score',
                type: 'custom',
                formatter: (value) => value ? `${value.toFixed(1)}%` : 'N/A'
            },
            { 
                field: 'attendance_rate', 
                label: 'Attendance',
                type: 'custom',
                formatter: (value) => {
                    const rate = value || 0;
                    const color = rate >= 90 ? 'success' : rate >= 75 ? 'warning' : 'danger';
                    return `<span class="badge bg-${color}">${rate.toFixed(1)}%</span>`;
                }
            }
        ],
        searchFields: ['full_name', 'admission_number'],
        rowActions: [
            { 
                id: 'viewProfile', 
                label: 'View Profile', 
                icon: 'bi-person-circle', 
                variant: 'primary',
                permission: 'students_view' 
            },
            { 
                id: 'viewProgress', 
                label: 'View Progress', 
                icon: 'bi-graph-up', 
                variant: 'info',
                permission: 'students_view' 
            }
        ],
        onRowAction: (action, studentData) => {
            if (action === 'viewProfile') {
                pageNav.navigateTo('studentProfile', { 
                    studentId: studentData.id,
                    studentName: studentData.full_name,
                    classId: classId
                });
            } else if (action === 'viewProgress') {
                // Show progress modal or navigate to progress page
            }
        }
    });

    document.getElementById('studentSearch')?.addEventListener('keyup', (e) => {
        table.search(e.target.value);
    });
}

// ============ PAGE 3: STUDENT PROFILE ============
function renderStudentProfile(data) {
    return `
        <div class="row mb-4">
            <div class="col-12">
                <button class="btn btn-outline-secondary btn-sm mb-3" onclick="pageNav.goBack()">
                    <i class="bi bi-arrow-left"></i> Back to Class
                </button>
                <h2>${data.studentName || 'Student Profile'}</h2>
            </div>
        </div>

        <!-- Student Profile Tabs -->
        <div id="studentProfileTabsContainer"></div>
    `;
}

async function loadStudentProfile(data) {
    const studentId = data.studentId;

    const tabNav = new TabNavigator('studentProfileTabsContainer');

    // Bio tab
    tabNav.registerTab('bio', {
        label: 'Bio Data',
        icon: 'bi-person',
        render: () => renderBioTab(studentId),
        onActivate: () => loadBioTab(studentId)
    });

    // Academic tab
    tabNav.registerTab('academic', {
        label: 'Academic Performance',
        icon: 'bi-book',
        render: () => renderAcademicTab(studentId),
        onActivate: () => loadAcademicTab(studentId, data.classId)
    });

    // Attendance tab
    tabNav.registerTab('studentAttendance', {
        label: 'Attendance',
        icon: 'bi-calendar-check',
        render: () => renderStudentAttendanceTab(studentId),
        onActivate: () => loadStudentAttendanceTab(studentId)
    });

    // Finance tab
    tabNav.registerTab('finance', {
        label: 'Finance',
        icon: 'bi-cash-stack',
        render: () => renderFinanceTab(studentId),
        onActivate: () => loadFinanceTab(studentId),
        permission: 'finance_view'
    });

    tabNav.renderTabs();
}

// ============ STUDENT PROFILE - ACADEMIC TAB ============
function renderAcademicTab(studentId) {
    return `
        <div class="card mb-3">
            <div class="card-header">
                <h5>Learning Areas Performance</h5>
            </div>
            <div class="card-body">
                <div id="learningAreasContainer"></div>
            </div>
        </div>
    `;
}

async function loadAcademicTab(studentId, classId) {
    const learningAreas = await window.API.apiCall(`/academic/student/${studentId}/learning-areas`, 'GET');
    
    const container = document.getElementById('learningAreasContainer');
    if (!container) return;

    container.innerHTML = learningAreas.map(area => `
        <div class="card mb-2 cursor-pointer hover-shadow" 
             onclick="pageNav.navigateTo('learningAreaProgress', {
                 studentId: ${studentId},
                 learningAreaId: ${area.id},
                 learningAreaName: '${area.name}',
                 studentName: '${pageNav.getCurrentData().studentName}'
             })">
            <div class="card-body">
                <div class="row align-items-center">
                    <div class="col-md-6">
                        <h6 class="mb-0">${area.name}</h6>
                        <small class="text-muted">Teacher: ${area.teacher_name}</small>
                    </div>
                    <div class="col-md-3">
                        <div class="progress" style="height: 25px;">
                            <div class="progress-bar ${getProgressColor(area.avg_score)}" 
                                 role="progressbar" 
                                 style="width: ${area.avg_score || 0}%">
                                ${(area.avg_score || 0).toFixed(1)}%
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 text-end">
                        <span class="badge bg-${getGradeColor(area.grade)} fs-5">${area.grade || 'N/A'}</span>
                        <i class="bi bi-arrow-right-circle ms-2"></i>
                    </div>
                </div>
            </div>
        </div>
    `).join('');
}

// ============ PAGE 4: LEARNING AREA PROGRESS (DEEPEST DRILL-DOWN) ============
function renderLearningAreaProgress(data) {
    return `
        <div class="row mb-4">
            <div class="col-12">
                <button class="btn btn-outline-secondary btn-sm mb-3" onclick="pageNav.goBack()">
                    <i class="bi bi-arrow-left"></i> Back to Profile
                </button>
                <h2>${data.learningAreaName || 'Learning Area'} Progress</h2>
                <p class="text-muted">Student: ${data.studentName}</p>
            </div>
        </div>

        <!-- Progress Overview -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="card">
                    <div class="card-body">
                        <h6 class="text-muted">Current Grade</h6>
                        <h3 id="currentGrade">-</h3>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card">
                    <div class="card-body">
                        <h6 class="text-muted">Average Score</h6>
                        <h3 id="avgScore">0%</h3>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card">
                    <div class="card-body">
                        <h6 class="text-muted">Class Rank</h6>
                        <h3 id="classRank">-</h3>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card">
                    <div class="card-body">
                        <h6 class="text-muted">Assessments</h6>
                        <h3 id="assessmentCount">0</h3>
                    </div>
                </div>
            </div>
        </div>

        <!-- Assessment Results -->
        <div class="card mb-3">
            <div class="card-header">
                <h5>Assessment Results</h5>
            </div>
            <div class="card-body p-0">
                <div id="assessmentResultsContainer"></div>
            </div>
        </div>

        <!-- Performance Trend Chart -->
        <div class="card">
            <div class="card-header">
                <h5>Performance Trend</h5>
            </div>
            <div class="card-body">
                <canvas id="performanceTrendChart"></canvas>
            </div>
        </div>
    `;
}

async function loadLearningAreaProgress(data) {
    const { studentId, learningAreaId } = data;

    // Load progress data
    const progress = await window.API.apiCall(
        `/academic/student/${studentId}/learning-area/${learningAreaId}/progress`, 
        'GET'
    );

    if (progress) {
        document.getElementById('currentGrade').textContent = progress.current_grade || 'N/A';
        document.getElementById('avgScore').textContent = (progress.avg_score || 0).toFixed(1) + '%';
        document.getElementById('classRank').textContent = progress.class_rank || 'N/A';
        document.getElementById('assessmentCount').textContent = progress.assessment_count || 0;
    }

    // Load assessment results table
    const table = new DataTable('assessmentResultsContainer', {
        apiEndpoint: `/academic/student/${studentId}/learning-area/${learningAreaId}/assessments`,
        pageSize: 10,
        columns: [
            { field: 'assessment_title', label: 'Assessment', sortable: true },
            { field: 'assessment_date', label: 'Date', type: 'date', sortable: true },
            { field: 'marks_obtained', label: 'Marks', type: 'number' },
            { field: 'total_marks', label: 'Out of', type: 'number' },
            { 
                field: 'percentage', 
                label: 'Percentage',
                type: 'custom',
                formatter: (value) => `${(value || 0).toFixed(1)}%`
            },
            { 
                field: 'grade', 
                label: 'Grade',
                type: 'badge',
                badgeMap: {
                    'A': 'success',
                    'B': 'info',
                    'C': 'warning',
                    'D': 'danger',
                    'E': 'dark'
                }
            }
        ]
    });

    // Render performance trend chart (simplified - would use Chart.js)
    // renderPerformanceChart(progress.trend_data);
}

// Helper functions
function getProgressColor(score) {
    if (score >= 75) return 'bg-success';
    if (score >= 50) return 'bg-warning';
    return 'bg-danger';
}

function getGradeColor(grade) {
    const gradeColors = {
        'A': 'success',
        'B': 'info',
        'C': 'warning',
        'D': 'danger',
        'E': 'dark'
    };
    return gradeColors[grade] || 'secondary';
}
