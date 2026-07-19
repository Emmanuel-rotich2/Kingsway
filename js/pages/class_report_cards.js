/**
 * Class Report Cards Controller - Class Teacher Specific Report Card Generation
 * Role: Class Teacher (7)
 * Shows only classes the teacher is assigned to
 * Integrates with AcademicContext for academic year awareness
 */

const classReportCardsCtrl = (() => {
    const state = {
        students: [],
        classes: [],
        years: [],
        terms: [],
        currentAcademicYear: null,
        currentTerm: null,
        generatedCount: 0
    };

    function toast(msg, type = 'info') {
        const el = document.getElementById('reportCardsToast');
        if (!el) {
            const toast = document.createElement('div');
            toast.id = 'reportCardsToast';
            toast.className = `toast align-items-center text-bg-${type} border-0 position-fixed top-0 end-0 m-3`;
            toast.style.zIndex = '9999';
            toast.innerHTML = `
                <div class="d-flex">
                    <div class="toast-body">${msg}</div>
                    <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
                </div>
            `;
            document.body.appendChild(toast);
            new bootstrap.Toast(toast, { delay: 3000 }).show();
        } else {
            el.className = `toast align-items-center text-bg-${type} border-0 position-fixed top-0 end-0 m-3`;
            el.querySelector('.toast-body').textContent = msg;
            new bootstrap.Toast(el, { delay: 3000 }).show();
        }
    }

    async function apiCall(endpoint, method = 'GET', data = null) {
        try {
            return await window.API.apiCall(endpoint, method, data, null, { checkPermission: false });
        } catch (error) {
            console.error('API call failed:', error);
            throw error;
        }
    }

    function cbcGrade(score) {
        const n = Number(score);
        if (!Number.isFinite(n)) return null;
        if (n >= 80) return 'EE';
        if (n >= 50) return 'ME';
        if (n >= 25) return 'AE';
        return 'BE';
    }

    async function loadYears() {
        try {
            const response = await apiCall('academic/years-list');
            state.years = response.data || [];
            const select = document.getElementById('yearFilter');
            if (select) {
                select.innerHTML = '<option value="">All Years</option>';
                state.years.forEach(year => {
                    const option = document.createElement('option');
                    option.value = year.id;
                    option.textContent = year.year_name;
                    if (year.is_current) option.selected = true;
                    select.appendChild(option);
                });
            }
        } catch (error) {
            console.error('Failed to load years:', error);
        }
    }

    async function loadTerms() {
        try {
            const response = await apiCall('academic/terms-list');
            state.terms = response.data || [];
            const select = document.getElementById('termFilter');
            if (select) {
                select.innerHTML = '<option value="">All Terms</option>';
                state.terms.forEach(term => {
                    const option = document.createElement('option');
                    option.value = term.id;
                    option.textContent = `${term.name} (${term.year_code || ''})`;
                    if (term.status === 'current') option.selected = true;
                    select.appendChild(option);
                });
            }
        } catch (error) {
            console.error('Failed to load terms:', error);
        }
    }

    async function loadClasses() {
        try {
            // Load only classes assigned to this teacher
            const response = await apiCall('academic/classes-list', 'GET', {
                class_teacher_only: true
            });
            state.classes = response.data || [];
            const select = document.getElementById('classFilter');
            if (select) {
                select.innerHTML = '<option value="">All Classes</option>';
                state.classes.forEach(cls => {
                    const option = document.createElement('option');
                    option.value = cls.id;
                    option.textContent = cls.class_name;
                    select.appendChild(option);
                });
            }
        } catch (error) {
            console.error('Failed to load classes:', error);
        }
    }

    async function loadStudents() {
        try {
            const yearId = document.getElementById('yearFilter')?.value || '';
            const termId = document.getElementById('termFilter')?.value || '';
            const classId = document.getElementById('classFilter')?.value || '';

            if (!classId) {
                toast('Please select a class first', 'warning');
                return;
            }

            const response = await apiCall('academic/class-students', 'GET', {
                year_id: yearId,
                term_id: termId,
                class_id: classId,
                class_teacher_only: true // Only show students for this teacher's classes
            });

            state.students = response.data || [];
            renderStudents();
            updateStats();
            populateStudentFilter();
        } catch (error) {
            console.error('Failed to load students:', error);
            toast('Failed to load students', 'error');
        }
    }

    function populateStudentFilter() {
        const select = document.getElementById('studentFilter');
        if (select) {
            select.innerHTML = '<option value="">All Students</option>';
            state.students.forEach(student => {
                const option = document.createElement('option');
                option.value = student.id;
                option.textContent = `${student.first_name || ''} ${student.last_name || ''} (${student.admission_no || '—'})`;
                select.appendChild(option);
            });
        }
    }

    function renderStudents() {
        const tbody = document.getElementById('studentsTableBody');
        if (!tbody) return;

        if (!state.students.length) {
            tbody.innerHTML = `
                <tr>
                    <td colspan="7" class="text-center text-muted py-4">
                        No students found for your classes
                    </td>
                </tr>
            `;
            return;
        }

        tbody.innerHTML = state.students.map((student, index) => {
            const grade = cbcGrade(student.term_average || 0);
            const gradeColor = grade === 'EE' ? 'success' : grade === 'ME' ? 'primary' : grade === 'AE' ? 'warning' : 'danger';

            return `
                <tr data-student-id="${student.id}">
                    <td>
                        <input type="checkbox" class="student-checkbox" value="${student.id}">
                    </td>
                    <td>
                        <strong>${student.first_name || ''} ${student.last_name || ''}</strong>
                        ${student.middle_name ? `(${student.middle_name})` : ''}
                    </td>
                    <td>${student.admission_no || '—'}</td>
                    <td>${student.class_name || '—'}</td>
                    <td><strong>${student.term_average || 0}%</strong></td>
                    <td><span class="badge bg-${gradeColor}">${grade || '—'}</span></td>
                    <td>
                        <div class="btn-group btn-group-sm">
                            <button class="btn btn-outline-primary" onclick="classReportCardsCtrl.generateReportCard(${student.id})">
                                <i class="bi bi-file-earmark-text"></i> Generate
                            </button>
                            <button class="btn btn-outline-success" onclick="classReportCardsCtrl.viewReportCard(${student.id})">
                                <i class="bi bi-eye"></i> View
                            </button>
                        </div>
                    </td>
                </tr>
            `;
        }).join('');
    }

    function updateStats() {
        document.getElementById('totalStudents').textContent = state.students.length;
        document.getElementById('generatedCount').textContent = state.generatedCount;
    }

    async function generateReportCard(studentId) {
        try {
            const student = state.students.find(s => s.id === studentId);
            if (!student) {
                toast('Student not found', 'error');
                return;
            }

            const yearId = document.getElementById('yearFilter')?.value || '';
            const termId = document.getElementById('termFilter')?.value || '';
            const classId = document.getElementById('classFilter')?.value || '';

            // Get detailed report card data
            const response = await apiCall('academic/report-cards', 'GET', {
                student_id: studentId,
                year_id: yearId,
                term_id: termId,
                class_id: classId
            });

            const reportData = response.data || {};

            // Use PrintManager to generate report card
            if (window.PrintManager) {
                const yearText = document.getElementById('yearFilter')?.options[document.getElementById('yearFilter').selectedIndex]?.text || 'All Years';
                const termText = document.getElementById('termFilter')?.options[document.getElementById('termFilter').selectedIndex]?.text || 'All Terms';
                const classText = document.getElementById('classFilter')?.options[document.getElementById('classFilter').selectedIndex]?.text || 'All Classes';

                window.PrintManager.printTable({
                    title: 'Student Report Card',
                    subtitle: `${student.first_name} ${student.last_name} - ${classText} - ${yearText} - ${termText}`,
                    columns: [
                        { key: 'subject', label: 'Subject' },
                        { key: 'marks', label: 'Marks' },
                        { key: 'grade', label: 'Grade' },
                        { key: 'remarks', label: 'Remarks' }
                    ],
                    rows: reportData.subjects || [],
                    summary: {
                        'Student Name': `${student.first_name} ${student.last_name} ${student.middle_name || ''}`,
                        'Admission No': student.admission_no || '—',
                        'Class': student.class_name || '—',
                        'Term Average': student.term_average || 0,
                        'Overall Grade': cbcGrade(student.term_average || 0),
                        'Attendance': reportData.attendance || '—',
                        'Conduct': reportData.conduct || '—',
                        'Generated Date': new Date().toLocaleDateString()
                    },
                    studentInfo: {
                        'Name': `${student.first_name} ${student.last_name} ${student.middle_name || ''}`,
                        'Adm No': student.admission_no || '—',
                        'Class': student.class_name || '—',
                        'Term': termText,
                        'Year': yearText
                    },
                    orientation: 'portrait',
                    paperSize: 'A4',
                    reportCode: 'RC-' + student.admission_no + '-' + new Date().toISOString().slice(0, 10).replace(/-/g, ''),
                    signatureSection: [
                        { label: 'Class Teacher' },
                        { label: 'Principal' },
                        { label: 'Parent/Guardian' }
                    ]
                });

                state.generatedCount++;
                updateStats();
                toast('Report card generated successfully', 'success');
            } else {
                toast('PrintManager not available', 'error');
            }
        } catch (error) {
            console.error('Failed to generate report card:', error);
            toast('Failed to generate report card', 'error');
        }
    }

    function viewReportCard(studentId) {
        // Navigate to detailed report card view
        window.location.href = `?route=report_cards&student_id=${studentId}`;
    }

    async function generateBatchReportCards() {
        const selectedStudents = Array.from(document.querySelectorAll('.student-checkbox:checked'))
            .map(checkbox => parseInt(checkbox.value));

        if (!selectedStudents.length) {
            toast('Please select at least one student', 'warning');
            return;
        }

        try {
            toast(`Generating ${selectedStudents.length} report cards...`, 'info');

            for (const studentId of selectedStudents) {
                await generateReportCard(studentId);
                // Small delay between generations
                await new Promise(resolve => setTimeout(resolve, 500));
            }

            toast(`Successfully generated ${selectedStudents.length} report cards`, 'success');
        } catch (error) {
            console.error('Failed to generate batch report cards:', error);
            toast('Failed to generate some report cards', 'error');
        }
    }

    function selectAllStudents() {
        const selectAll = document.getElementById('selectAllStudents');
        const checkboxes = document.querySelectorAll('.student-checkbox');
        checkboxes.forEach(checkbox => {
            checkbox.checked = selectAll.checked;
        });
    }

    function bindEvents() {
        document.getElementById('loadStudentsBtn')?.addEventListener('click', loadStudents);
        document.getElementById('generateSingleBtn')?.addEventListener('click', () => {
            const studentId = document.getElementById('studentFilter')?.value;
            if (studentId) {
                generateReportCard(parseInt(studentId));
            } else {
                toast('Please select a student first', 'warning');
            }
        });
        document.getElementById('generateBatchBtn')?.addEventListener('click', generateBatchReportCards);
        document.getElementById('selectAllStudents')?.addEventListener('change', selectAllStudents);
        
        document.getElementById('classFilter')?.addEventListener('change', () => {
            // Auto-load students when class is selected
            if (document.getElementById('classFilter').value) {
                loadStudents();
            }
        });
    }

    async function init() {
        if (typeof AuthContext !== 'undefined' && !AuthContext.isAuthenticated()) {
            window.location.href = (window.APP_BASE || '') + '/index.php';
            return;
        }

        // Initialize Academic Context if available
        if (window.AcademicContext) {
            window.AcademicContext.subscribe((context, event, data) => {
                console.log('AcademicContext changed in class_report_cards:', event, data);
                if (event === 'yearChanged' || event === 'termChanged' || event === 'initialized' || event === 'refreshed') {
                    loadYears();
                    loadTerms();
                    if (document.getElementById('classFilter').value) {
                        loadStudents();
                    }
                }
            });

            if (!window.AcademicContext.isLoaded()) {
                await window.AcademicContext.init();
            }

            state.currentAcademicYear = window.AcademicContext.getAcademicYearId();
            state.currentTerm = window.AcademicContext.getTermId();
        }

        document.getElementById('reportCardsLoading').style.display = 'none';
        document.getElementById('reportCardsContent').style.display = 'block';

        await Promise.all([loadYears(), loadTerms(), loadClasses()]);
        bindEvents();
    }

    return {
        init,
        generateReportCard,
        viewReportCard
    };
})();

document.addEventListener('DOMContentLoaded', classReportCardsCtrl.init);
