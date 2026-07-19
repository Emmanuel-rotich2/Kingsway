/**
 * Subject Grade Entry Controller - Subject Teacher Grade Entry
 * Role: Subject Teacher (8)
 * Shows only students in classes where they teach the subject
 * Integrates with AcademicContext for academic year awareness
 */

const subjectGradeEntryCtrl = (() => {
    const state = {
        assessments: [],
        students: [],
        marks: [],
        subjects: [],
        years: [],
        terms: [],
        currentAssessment: null,
        currentAcademicYear: null,
        currentTerm: null
    };

    function toast(msg, type = 'info') {
        const el = document.getElementById('marksToast');
        if (!el) {
            const toast = document.createElement('div');
            toast.id = 'marksToast';
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

    function cbcGrade(score, maxMarks = 20) {
        const percentage = (score / maxMarks) * 100;
        if (percentage >= 80) return 'EE';
        if (percentage >= 50) return 'ME';
        if (percentage >= 25) return 'AE';
        return 'BE';
    }

    function getRemarks(score, maxMarks = 20) {
        const percentage = (score / maxMarks) * 100;
        if (percentage >= 80) return 'Excellent';
        if (percentage >= 50) return 'Meeting Expectations';
        if (percentage >= 25) return 'Approaching Expectations';
        return 'Below Expectations';
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

    async function loadSubjects() {
        try {
            // Load only subjects assigned to this teacher
            const response = await apiCall('academic/subjects-list', 'GET', {
                subject_teacher_only: true
            });
            state.subjects = response.data || [];
            const select = document.getElementById('subjectFilter');
            if (select) {
                select.innerHTML = '<option value="">All Subjects</option>';
                state.subjects.forEach(subject => {
                    const option = document.createElement('option');
                    option.value = subject.id;
                    option.textContent = subject.subject_name;
                    select.appendChild(option);
                });
            }
        } catch (error) {
            console.error('Failed to load subjects:', error);
        }
    }

    async function loadAssessments() {
        try {
            const yearId = document.getElementById('yearFilter')?.value || '';
            const termId = document.getElementById('termFilter')?.value || '';
            const subjectId = document.getElementById('subjectFilter')?.value || '';

            const response = await apiCall('academic/formative-assessments', 'GET', {
                year_id: yearId,
                term_id: termId,
                subject_id: subjectId,
                subject_teacher_only: true // Only show assessments for this teacher's subjects
            });

            state.assessments = response.data || [];
            const select = document.getElementById('assessmentFilter');
            if (select) {
                select.innerHTML = '<option value="">Select Assessment</option>';
                state.assessments.forEach(assessment => {
                    const option = document.createElement('option');
                    option.value = assessment.id;
                    option.textContent = `${assessment.name} (${assessment.subject_name || 'All Subjects'})`;
                    select.appendChild(option);
                });
            }
        } catch (error) {
            console.error('Failed to load assessments:', error);
            toast('Failed to load assessments', 'error');
        }
    }

    async function loadStudentsForAssessment(assessmentId) {
        try {
            const assessment = state.assessments.find(a => a.id === assessmentId);
            if (!assessment) return;

            state.currentAssessment = assessment;

            // Show assessment info
            document.getElementById('assessmentInfo').style.display = 'block';
            document.getElementById('assessmentName').textContent = assessment.name || 'Unnamed';
            document.getElementById('assessmentSubject').textContent = assessment.subject_name || '—';
            document.getElementById('assessmentType').textContent = assessment.type || '—';
            document.getElementById('assessmentMaxMarks').textContent = assessment.max_marks || 20;
            document.getElementById('assessmentDate').textContent = assessment.cat_date || '—';

            // Load students for the assessment's class and subject
            const response = await apiCall('academic/class-students', 'GET', {
                class_id: assessment.class_id,
                subject_id: assessment.subject_id,
                year_id: assessment.year_id,
                term_id: assessment.term_id
            });

            state.students = response.data || [];

            // Load existing marks
            await loadExistingMarks(assessmentId);

            renderMarksTable();
            updateStats();
        } catch (error) {
            console.error('Failed to load students:', error);
            toast('Failed to load students', 'error');
        }
    }

    async function loadExistingMarks(assessmentId) {
        try {
            const response = await apiCall('academic/grading-results', 'GET', {
                assessment_id: assessmentId
            });

            state.marks = response.data?.items || [];
        } catch (error) {
            console.error('Failed to load existing marks:', error);
            state.marks = [];
        }
    }

    function renderMarksTable() {
        const tbody = document.getElementById('marksTableBody');
        if (!tbody) return;

        if (!state.students.length) {
            tbody.innerHTML = `
                <tr>
                    <td colspan="8" class="text-center text-muted py-4">
                        No students found for this assessment
                    </td>
                </tr>
            `;
            return;
        }

        const maxMarks = state.currentAssessment?.max_marks || 20;

        tbody.innerHTML = state.students.map((student, index) => {
            const existingMark = state.marks.find(m => m.student_id === student.id);
            const marks = existingMark?.marks || '';
            const grade = existingMark?.grade || '';
            const remarks = existingMark?.remarks || '';
            const lastUpdated = existingMark?.updated_at || '—';

            return `
                <tr data-student-id="${student.id}">
                    <td>${index + 1}</td>
                    <td>${student.admission_no || '—'}</td>
                    <td>
                        <strong>${student.first_name || ''} ${student.last_name || ''}</strong>
                        ${student.middle_name ? `(${student.middle_name})` : ''}
                    </td>
                    <td>${student.class_name || '—'}</td>
                    <td>
                        <input type="number" 
                               class="form-control marks-input" 
                               min="0" 
                               max="${maxMarks}" 
                               step="0.5"
                               value="${marks}"
                               data-student-id="${student.id}"
                               data-max-marks="${maxMarks}"
                               onchange="subjectGradeEntryCtrl.calculateGrade(this)">
                    </td>
                    <td>
                        <span class="badge bg-secondary grade-display" data-student-id="${student.id}">
                            ${grade || '—'}
                        </span>
                    </td>
                    <td>
                        <span class="remarks-display text-muted" data-student-id="${student.id}">
                            ${remarks || '—'}
                        </span>
                    </td>
                    <td class="text-muted small">${lastUpdated}</td>
                </tr>
            `;
        }).join('');
    }

    function calculateGrade(input) {
        const marks = parseFloat(input.value) || 0;
        const maxMarks = parseFloat(input.dataset.maxMarks) || 20;
        const studentId = input.dataset.studentId;

        const grade = cbcGrade(marks, maxMarks);
        const remarks = getRemarks(marks, maxMarks);

        // Update grade display
        const gradeDisplay = document.querySelector(`.grade-display[data-student-id="${studentId}"]`);
        if (gradeDisplay) {
            gradeDisplay.textContent = grade;
            gradeDisplay.className = `badge bg-${grade === 'EE' ? 'success' : grade === 'ME' ? 'primary' : grade === 'AE' ? 'warning' : 'danger'}`;
        }

        // Update remarks display
        const remarksDisplay = document.querySelector(`.remarks-display[data-student-id="${studentId}"]`);
        if (remarksDisplay) {
            remarksDisplay.textContent = remarks;
        }

        updateStats();
    }

    function updateStats() {
        const total = state.students.length;
        const marked = state.students.filter(student => {
            const input = document.querySelector(`.marks-input[data-student-id="${student.id}"]`);
            return input && input.value !== '';
        }).length;
        const pending = total - marked;

        document.getElementById('totalStudents').textContent = total;
        document.getElementById('markedStudents').textContent = marked;
        document.getElementById('pendingStudents').textContent = pending;
    }

    async function saveAllMarks() {
        const assessmentId = state.currentAssessment?.id;
        if (!assessmentId) {
            toast('Please select an assessment first', 'warning');
            return;
        }

        const scores = [];
        const inputs = document.querySelectorAll('.marks-input');

        inputs.forEach(input => {
            const studentId = input.dataset.studentId;
            const marks = parseFloat(input.value) || 0;
            const maxMarks = parseFloat(input.dataset.maxMarks) || 20;

            if (marks > 0) {
                scores.push({
                    student_id: studentId,
                    marks_obtained: marks,
                    grade: cbcGrade(marks, maxMarks),
                    remarks: getRemarks(marks, maxMarks)
                });
            }
        });

        if (!scores.length) {
            toast('No marks to save', 'warning');
            return;
        }

        try {
            const saveBtn = document.getElementById('saveAllBtn');
            saveBtn.disabled = true;
            saveBtn.textContent = 'Saving...';

            await apiCall('academic/formative-assessment-marks', 'POST', {
                assessment_id: assessmentId,
                marks: scores
            });

            toast('Marks saved successfully', 'success');
            await loadExistingMarks(assessmentId);
            renderMarksTable();
        } catch (error) {
            console.error('Failed to save marks:', error);
            toast('Failed to save marks', 'error');
        } finally {
            const saveBtn = document.getElementById('saveAllBtn');
            saveBtn.disabled = false;
            saveBtn.textContent = 'Save All Marks';
        }
    }

    function autoCalculate() {
        const inputs = document.querySelectorAll('.marks-input');
        inputs.forEach(input => {
            calculateGrade(input);
        });
        toast('Grades calculated automatically', 'success');
    }

    async function exportMarks() {
        const assessmentId = state.currentAssessment?.id;
        if (!assessmentId) {
            toast('Please select an assessment first', 'warning');
            return;
        }

        // Use PrintManager for CSV export if available
        if (window.PrintManager) {
            const columns = [
                { key: 'adm_no', label: 'Adm No' },
                { key: 'student_name', label: 'Student Name' },
                { key: 'class_name', label: 'Class' },
                { key: 'marks', label: 'Marks' },
                { key: 'grade', label: 'Grade' },
                { key: 'remarks', label: 'Remarks' }
            ];

            const maxMarks = state.currentAssessment?.maxMarks || 20;
            const rows = state.students.map(student => {
                const input = document.querySelector(`.marks-input[data-student-id="${student.id}"]`);
                const marks = input ? parseFloat(input.value) || 0 : 0;
                const gradeDisplay = document.querySelector(`.grade-display[data-student-id="${student.id}"]`);
                const remarksDisplay = document.querySelector(`.remarks-display[data-student-id="${student.id}"]`);

                return {
                    adm_no: student.admission_no || '—',
                    student_name: `${student.first_name || ''} ${student.last_name || ''}`,
                    class_name: student.class_name || '—',
                    marks: marks,
                    grade: gradeDisplay ? gradeDisplay.textContent : '—',
                    remarks: remarksDisplay ? remarksDisplay.textContent : '—'
                };
            });

            window.PrintManager.exportToCSV({
                filename: `subject_marks_${state.currentAssessment.name}_${new Date().toISOString().slice(0,10)}.csv`,
                columns: columns,
                rows: rows
            });
        } else {
            toast('PrintManager not available', 'error');
        }
    }

    function bindEvents() {
        document.getElementById('yearFilter')?.addEventListener('change', loadAssessments);
        document.getElementById('termFilter')?.addEventListener('change', loadAssessments);
        document.getElementById('subjectFilter')?.addEventListener('change', loadAssessments);
        document.getElementById('assessmentFilter')?.addEventListener('change', (e) => {
            if (e.target.value) {
                loadStudentsForAssessment(parseInt(e.target.value));
            } else {
                document.getElementById('assessmentInfo').style.display = 'none';
                document.getElementById('marksTableBody').innerHTML = `
                    <tr>
                        <td colspan="8" class="text-center text-muted py-4">
                            Select an assessment to enter marks
                        </td>
                    </tr>
                `;
                updateStats();
            }
        });
        document.getElementById('saveAllBtn')?.addEventListener('click', saveAllMarks);
        document.getElementById('autoCalculateBtn')?.addEventListener('click', autoCalculate);
        document.getElementById('exportBtn')?.addEventListener('click', exportMarks);
    }

    async function init() {
        if (typeof AuthContext !== 'undefined' && !AuthContext.isAuthenticated()) {
            window.location.href = (window.APP_BASE || '') + '/index.php';
            return;
        }

        // Initialize Academic Context if available
        if (window.AcademicContext) {
            window.AcademicContext.subscribe((context, event, data) => {
                console.log('AcademicContext changed in subject_grade_entry:', event, data);
                if (event === 'yearChanged' || event === 'termChanged' || event === 'initialized' || event === 'refreshed') {
                    loadYears();
                    loadTerms();
                    loadAssessments();
                }
            });

            if (!window.AcademicContext.isLoaded()) {
                await window.AcademicContext.init();
            }

            state.currentAcademicYear = window.AcademicContext.getAcademicYearId();
            state.currentTerm = window.AcademicContext.getTermId();
        }

        document.getElementById('marksLoading').style.display = 'none';
        document.getElementById('marksContent').style.display = 'block';

        await Promise.all([loadYears(), loadTerms(), loadSubjects()]);
        await loadAssessments();
        bindEvents();
    }

    return {
        init,
        calculateGrade
    };
})();

document.addEventListener('DOMContentLoaded', subjectGradeEntryCtrl.init);
