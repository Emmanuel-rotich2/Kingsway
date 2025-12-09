/**
 * Student Profile Page - Complete Example
 * Can be accessed via:
 * 1. Direct URL: /pages/student_profile.php?student_id=123
 * 2. PageNavigator drill-down from class details
 * 3. Search results click
 * 
 * Uses TabNavigator for profile sections (Bio, Academic, Attendance, Finance)
 */

let profileTabNav = null;
let studentData = null;

document.addEventListener('DOMContentLoaded', async () => {
    if (!AuthContext.isAuthenticated()) {
        window.location.href = '/Kingsway/index.php';
        return;
    }

    // Get student ID from URL or passed data
    const urlParams = new URLSearchParams(window.location.search);
    const studentId = urlParams.get('student_id') || window.currentStudentId;

    if (!studentId) {
        alert('No student specified');
        window.history.back();
        return;
    }

    await loadStudentProfile(studentId);
});

async function loadStudentProfile(studentId) {
    try {
        // Load student basic info
        studentData = await window.API.apiCall(`/students/student/${studentId}`, 'GET');
        
        if (!studentData) {
            alert('Student not found');
            window.history.back();
            return;
        }

        // Update header
        renderProfileHeader(studentData);

        // Initialize tab navigation
        initializeProfileTabs(studentId);

    } catch (error) {
        console.error('Failed to load student profile:', error);
        alert('Failed to load student profile');
    }
}

function renderProfileHeader(student) {
    const headerContainer = document.getElementById('profileHeader');
    if (!headerContainer) return;

    headerContainer.innerHTML = `
        <div class="row align-items-center mb-4">
            <div class="col-auto">
                <img src="${student.photo_url || '/Kingsway/images/default-avatar.png'}" 
                     class="rounded-circle" 
                     width="100" 
                     height="100"
                     alt="${student.full_name}">
            </div>
            <div class="col">
                <h2 class="mb-1">${student.full_name}</h2>
                <p class="text-muted mb-1">
                    <i class="bi bi-hash"></i> ${student.admission_number} | 
                    <i class="bi bi-book"></i> ${student.class_name} |
                    <i class="bi bi-gender-${student.gender === 'M' ? 'male' : 'female'}"></i> ${student.gender}
                </p>
                <div class="d-flex gap-2 mt-2">
                    ${ActionButtons.createButton({
                        id: 'editProfileBtn',
                        label: 'Edit Profile',
                        icon: 'bi-pencil',
                        variant: 'primary',
                        size: 'sm',
                        permission: 'students_edit',
                        onClick: () => openEditProfileModal(student)
                    })}
                    ${ActionButtons.createButton({
                        id: 'printIdCardBtn',
                        label: 'Print ID Card',
                        icon: 'bi-printer',
                        variant: 'outline-secondary',
                        size: 'sm',
                        permission: 'students_view',
                        onClick: () => printIdCard(student.id)
                    })}
                    ${ActionButtons.createButton({
                        id: 'sendMessageBtn',
                        label: 'Send Message',
                        icon: 'bi-envelope',
                        variant: 'outline-secondary',
                        size: 'sm',
                        permission: 'communications_send',
                        onClick: () => openMessageModal(student)
                    })}
                </div>
            </div>
            <div class="col-auto text-end">
                <div class="card">
                    <div class="card-body">
                        <h6 class="text-muted mb-2">Overall Performance</h6>
                        <h2 class="mb-0">${student.overall_avg || 'N/A'}%</h2>
                        <span class="badge bg-${getPerformanceBadgeColor(student.overall_avg)} mt-2">
                            ${student.overall_grade || 'N/A'}
                        </span>
                    </div>
                </div>
            </div>
        </div>
    `;
}

function initializeProfileTabs(studentId) {
    profileTabNav = new TabNavigator('profileTabsContainer');

    // Bio Tab
    profileTabNav.registerTab('bio', {
        label: 'Bio Data',
        icon: 'bi-person-vcard',
        render: () => renderBioTab(),
        onActivate: () => loadBioData(studentId)
    });

    // Academic Tab
    profileTabNav.registerTab('academic', {
        label: 'Academic Performance',
        icon: 'bi-graph-up-arrow',
        render: () => renderAcademicTab(),
        onActivate: () => loadAcademicData(studentId)
    });

    // Attendance Tab
    profileTabNav.registerTab('attendance', {
        label: 'Attendance',
        icon: 'bi-calendar-check',
        render: () => renderAttendanceTab(),
        onActivate: () => loadAttendanceData(studentId)
    });

    // Finance Tab (permission-based)
    profileTabNav.registerTab('finance', {
        label: 'Finance',
        icon: 'bi-cash-stack',
        render: () => renderFinanceTab(),
        onActivate: () => loadFinanceData(studentId),
        permission: 'finance_view'
    });

    // Discipline Tab
    profileTabNav.registerTab('discipline', {
        label: 'Discipline',
        icon: 'bi-exclamation-triangle',
        render: () => renderDisciplineTab(),
        onActivate: () => loadDisciplineData(studentId),
        permission: 'discipline_view'
    });

    // Health Tab
    profileTabNav.registerTab('health', {
        label: 'Health Records',
        icon: 'bi-heart-pulse',
        render: () => renderHealthTab(),
        onActivate: () => loadHealthData(studentId),
        permission: 'health_view'
    });

    // Documents Tab
    profileTabNav.registerTab('documents', {
        label: 'Documents',
        icon: 'bi-file-earmark-text',
        render: () => renderDocumentsTab(),
        onActivate: () => loadDocuments(studentId),
        permission: 'students_view'
    });

    profileTabNav.renderTabs();
}

// ============ BIO TAB ============
function renderBioTab() {
    return `
        <div class="row">
            <!-- Personal Information -->
            <div class="col-md-6">
                <div class="card mb-3">
                    <div class="card-header">
                        <h5 class="mb-0">Personal Information</h5>
                    </div>
                    <div class="card-body" id="personalInfoContainer">
                        <!-- Will be populated dynamically -->
                    </div>
                </div>
            </div>

            <!-- Contact Information -->
            <div class="col-md-6">
                <div class="card mb-3">
                    <div class="card-header">
                        <h5 class="mb-0">Contact Information</h5>
                    </div>
                    <div class="card-body" id="contactInfoContainer">
                        <!-- Will be populated dynamically -->
                    </div>
                </div>
            </div>

            <!-- Guardian Information -->
            <div class="col-md-6">
                <div class="card mb-3">
                    <div class="card-header">
                        <h5 class="mb-0">Guardian Information</h5>
                    </div>
                    <div class="card-body" id="guardianInfoContainer">
                        <!-- Will be populated dynamically -->
                    </div>
                </div>
            </div>

            <!-- Emergency Contact -->
            <div class="col-md-6">
                <div class="card mb-3">
                    <div class="card-header">
                        <h5 class="mb-0">Emergency Contact</h5>
                    </div>
                    <div class="card-body" id="emergencyContactContainer">
                        <!-- Will be populated dynamically -->
                    </div>
                </div>
            </div>
        </div>
    `;
}

async function loadBioData(studentId) {
    const bioData = await window.API.apiCall(`/students/student/${studentId}/bio`, 'GET');
    
    if (!bioData) return;

    // Personal Info
    document.getElementById('personalInfoContainer').innerHTML = `
        <dl class="row mb-0">
            <dt class="col-sm-4">Full Name:</dt>
            <dd class="col-sm-8">${bioData.full_name}</dd>

            <dt class="col-sm-4">Date of Birth:</dt>
            <dd class="col-sm-8">${bioData.date_of_birth}</dd>

            <dt class="col-sm-4">Gender:</dt>
            <dd class="col-sm-8">${bioData.gender === 'M' ? 'Male' : 'Female'}</dd>

            <dt class="col-sm-4">Nationality:</dt>
            <dd class="col-sm-8">${bioData.nationality || 'N/A'}</dd>

            <dt class="col-sm-4">Religion:</dt>
            <dd class="col-sm-8">${bioData.religion || 'N/A'}</dd>

            <dt class="col-sm-4">Blood Group:</dt>
            <dd class="col-sm-8">${bioData.blood_group || 'N/A'}</dd>
        </dl>
    `;

    // Contact Info
    document.getElementById('contactInfoContainer').innerHTML = `
        <dl class="row mb-0">
            <dt class="col-sm-4">Phone:</dt>
            <dd class="col-sm-8">${bioData.phone || 'N/A'}</dd>

            <dt class="col-sm-4">Email:</dt>
            <dd class="col-sm-8">${bioData.email || 'N/A'}</dd>

            <dt class="col-sm-4">Address:</dt>
            <dd class="col-sm-8">${bioData.address || 'N/A'}</dd>

            <dt class="col-sm-4">County:</dt>
            <dd class="col-sm-8">${bioData.county || 'N/A'}</dd>
        </dl>
    `;

    // Guardian Info
    document.getElementById('guardianInfoContainer').innerHTML = `
        <dl class="row mb-0">
            <dt class="col-sm-4">Name:</dt>
            <dd class="col-sm-8">${bioData.guardian_name || 'N/A'}</dd>

            <dt class="col-sm-4">Relationship:</dt>
            <dd class="col-sm-8">${bioData.guardian_relationship || 'N/A'}</dd>

            <dt class="col-sm-4">Phone:</dt>
            <dd class="col-sm-8">${bioData.guardian_phone || 'N/A'}</dd>

            <dt class="col-sm-4">Email:</dt>
            <dd class="col-sm-8">${bioData.guardian_email || 'N/A'}</dd>

            <dt class="col-sm-4">Occupation:</dt>
            <dd class="col-sm-8">${bioData.guardian_occupation || 'N/A'}</dd>
        </dl>
    `;

    // Emergency Contact
    document.getElementById('emergencyContactContainer').innerHTML = `
        <dl class="row mb-0">
            <dt class="col-sm-4">Name:</dt>
            <dd class="col-sm-8">${bioData.emergency_contact_name || 'N/A'}</dd>

            <dt class="col-sm-4">Phone:</dt>
            <dd class="col-sm-8">${bioData.emergency_contact_phone || 'N/A'}</dd>

            <dt class="col-sm-4">Relationship:</dt>
            <dd class="col-sm-8">${bioData.emergency_contact_relationship || 'N/A'}</dd>
        </dl>
    `;
}

// ============ ACADEMIC TAB ============
function renderAcademicTab() {
    return `
        <!-- Current Term Performance -->
        <div class="card mb-3">
            <div class="card-header">
                <h5 class="mb-0">Current Term Performance</h5>
            </div>
            <div class="card-body">
                <div class="row mb-3">
                    <div class="col-md-3">
                        <div class="text-center">
                            <h6 class="text-muted">Overall Average</h6>
                            <h2 id="currentTermAvg">0%</h2>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="text-center">
                            <h6 class="text-muted">Grade</h6>
                            <h2 id="currentTermGrade">-</h2>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="text-center">
                            <h6 class="text-muted">Class Rank</h6>
                            <h2 id="currentTermRank">-</h2>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="text-center">
                            <h6 class="text-muted">Stream Rank</h6>
                            <h2 id="currentStreamRank">-</h2>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Subject Performance -->
        <div class="card mb-3">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Subject Performance</h5>
                <select class="form-select form-select-sm w-auto" id="termSelector">
                    <option value="current">Current Term</option>
                    <option value="previous">Previous Term</option>
                    <option value="all">All Terms</option>
                </select>
            </div>
            <div class="card-body p-0">
                <div id="subjectPerformanceContainer"></div>
            </div>
        </div>

        <!-- Performance Trend -->
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">Performance Trend</h5>
            </div>
            <div class="card-body">
                <canvas id="performanceTrendChart" height="80"></canvas>
            </div>
        </div>
    `;
}

async function loadAcademicData(studentId) {
    const academicData = await window.API.apiCall(`/students/student/${studentId}/academic`, 'GET');
    
    if (!academicData) return;

    // Current term stats
    document.getElementById('currentTermAvg').textContent = (academicData.current_term_avg || 0).toFixed(1) + '%';
    document.getElementById('currentTermGrade').textContent = academicData.current_term_grade || 'N/A';
    document.getElementById('currentTermRank').textContent = academicData.class_rank || 'N/A';
    document.getElementById('currentStreamRank').textContent = academicData.stream_rank || 'N/A';

    // Subject performance table
    const subjectTable = new DataTable('subjectPerformanceContainer', {
        apiEndpoint: `/students/student/${studentId}/subject-performance`,
        pageSize: 15,
        columns: [
            { field: 'subject_name', label: 'Subject', sortable: true },
            { field: 'teacher_name', label: 'Teacher' },
            { 
                field: 'avg_score', 
                label: 'Average',
                type: 'custom',
                formatter: (value) => {
                    const score = value || 0;
                    const color = score >= 75 ? 'success' : score >= 50 ? 'warning' : 'danger';
                    return `<span class="badge bg-${color}">${score.toFixed(1)}%</span>`;
                }
            },
            { 
                field: 'grade', 
                label: 'Grade',
                type: 'badge',
                badgeMap: { 'A': 'success', 'B': 'info', 'C': 'warning', 'D': 'danger', 'E': 'dark' }
            },
            { field: 'assessments_count', label: 'Assessments', type: 'number' }
        ],
        rowActions: [
            { 
                id: 'viewDetails', 
                label: 'View Details', 
                icon: 'bi-arrow-right-circle',
                permission: 'students_view'
            }
        ],
        onRowAction: (action, data) => {
            if (action === 'viewDetails') {
                // Could navigate to subject details page or open detailed modal
                viewSubjectDetails(studentId, data.subject_id);
            }
        }
    });

    // Term selector
    document.getElementById('termSelector')?.addEventListener('change', (e) => {
        subjectTable.applyFilters({ term: e.target.value });
    });
}

// ============ ATTENDANCE TAB ============
function renderAttendanceTab() {
    return `
        <!-- Attendance Summary -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="card">
                    <div class="card-body text-center">
                        <h6 class="text-muted">Attendance Rate</h6>
                        <h2 id="attendanceRate" class="text-success">0%</h2>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card">
                    <div class="card-body text-center">
                        <h6 class="text-muted">Days Present</h6>
                        <h2 id="daysPresent">0</h2>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card">
                    <div class="card-body text-center">
                        <h6 class="text-muted">Days Absent</h6>
                        <h2 id="daysAbsent" class="text-danger">0</h2>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card">
                    <div class="card-body text-center">
                        <h6 class="text-muted">Days Late</h6>
                        <h2 id="daysLate" class="text-warning">0</h2>
                    </div>
                </div>
            </div>
        </div>

        <!-- Attendance Records -->
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Attendance Records</h5>
                <div class="d-flex gap-2">
                    <input type="date" class="form-control form-control-sm" id="dateFrom" placeholder="From">
                    <input type="date" class="form-control form-control-sm" id="dateTo" placeholder="To">
                    <button class="btn btn-sm btn-secondary" id="filterAttendanceBtn">Filter</button>
                </div>
            </div>
            <div class="card-body p-0">
                <div id="attendanceRecordsContainer"></div>
            </div>
        </div>
    `;
}

async function loadAttendanceData(studentId) {
    const attendanceStats = await window.API.apiCall(`/students/student/${studentId}/attendance-stats`, 'GET');
    
    if (attendanceStats) {
        document.getElementById('attendanceRate').textContent = (attendanceStats.attendance_rate || 0).toFixed(1) + '%';
        document.getElementById('daysPresent').textContent = attendanceStats.days_present || 0;
        document.getElementById('daysAbsent').textContent = attendanceStats.days_absent || 0;
        document.getElementById('daysLate').textContent = attendanceStats.days_late || 0;
    }

    const attendanceTable = new DataTable('attendanceRecordsContainer', {
        apiEndpoint: `/students/student/${studentId}/attendance`,
        pageSize: 20,
        columns: [
            { field: 'date', label: 'Date', type: 'date', sortable: true },
            { 
                field: 'status', 
                label: 'Status',
                type: 'badge',
                badgeMap: { 'present': 'success', 'absent': 'danger', 'late': 'warning', 'excused': 'info' }
            },
            { field: 'remarks', label: 'Remarks' }
        ]
    });

    document.getElementById('filterAttendanceBtn')?.addEventListener('click', () => {
        const dateFrom = document.getElementById('dateFrom').value;
        const dateTo = document.getElementById('dateTo').value;
        attendanceTable.applyFilters({ date_from: dateFrom, date_to: dateTo });
    });
}

// ============ FINANCE TAB ============
function renderFinanceTab() {
    return `
        <!-- Fee Summary -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="card">
                    <div class="card-body text-center">
                        <h6 class="text-muted">Total Fees</h6>
                        <h3 id="totalFees">KES 0</h3>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card">
                    <div class="card-body text-center">
                        <h6 class="text-muted">Amount Paid</h6>
                        <h3 id="amountPaid" class="text-success">KES 0</h3>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card">
                    <div class="card-body text-center">
                        <h6 class="text-muted">Balance</h6>
                        <h3 id="balance" class="text-danger">KES 0</h3>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card">
                    <div class="card-body text-center">
                        <h6 class="text-muted">Payment Status</h6>
                        <h5 id="paymentStatus">-</h5>
                    </div>
                </div>
            </div>
        </div>

        <!-- Payment History -->
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Payment History</h5>
                <button class="btn btn-sm btn-primary" id="recordPaymentBtn">
                    <i class="bi bi-plus"></i> Record Payment
                </button>
            </div>
            <div class="card-body p-0">
                <div id="paymentHistoryContainer"></div>
            </div>
        </div>
    `;
}

async function loadFinanceData(studentId) {
    const financeData = await window.API.apiCall(`/students/student/${studentId}/finance`, 'GET');
    
    if (financeData) {
        document.getElementById('totalFees').textContent = `KES ${financeData.total_fees?.toLocaleString() || 0}`;
        document.getElementById('amountPaid').textContent = `KES ${financeData.amount_paid?.toLocaleString() || 0}`;
        document.getElementById('balance').textContent = `KES ${financeData.balance?.toLocaleString() || 0}`;
        
        const status = financeData.balance === 0 ? 'Paid' : financeData.balance > 0 ? 'Owing' : 'Overpaid';
        const statusColor = status === 'Paid' ? 'success' : status === 'Owing' ? 'danger' : 'warning';
        document.getElementById('paymentStatus').innerHTML = `<span class="badge bg-${statusColor}">${status}</span>`;
    }

    const paymentTable = new DataTable('paymentHistoryContainer', {
        apiEndpoint: `/students/student/${studentId}/payments`,
        pageSize: 15,
        columns: [
            { field: 'payment_date', label: 'Date', type: 'date', sortable: true },
            { field: 'amount', label: 'Amount', type: 'currency' },
            { field: 'payment_method', label: 'Method' },
            { field: 'reference_number', label: 'Reference' },
            { field: 'recorded_by', label: 'Recorded By' }
        ],
        rowActions: [
            { id: 'viewReceipt', label: 'View Receipt', icon: 'bi-receipt', permission: 'finance_view' }
        ]
    });

    document.getElementById('recordPaymentBtn')?.addEventListener('click', () => {
        // Open payment modal
        openPaymentModal(studentId);
    });
}

// Helper functions
function getPerformanceBadgeColor(avg) {
    if (avg >= 75) return 'success';
    if (avg >= 50) return 'warning';
    return 'danger';
}

async function openEditProfileModal(student) {
    // Modal implementation
}

async function printIdCard(studentId) {
    window.open(`/Kingsway/api/students/print-id-card?student_id=${studentId}`, '_blank');
}

async function openMessageModal(student) {
    // Messaging modal implementation
}

async function viewSubjectDetails(studentId, subjectId) {
    // Could use PageNavigator or open detailed modal
}

async function openPaymentModal(studentId) {
    // Payment modal implementation
}
