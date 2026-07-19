/**
 * Enter Exam Results Controller - School Admin Exam Results Entry
 * Role: School Administrator (4)
 * Full access to all classes and subjects for exam results entry
 * Integrates with AcademicContext for academic year awareness
 */

const enterExamResultsCtrl = (() => {
    const state = {
        exams: [],
        students: [],
        results: [],
        classes: [],
        subjects: [],
        years: [],
        terms: [],
        currentExam: null,
        currentAcademicYear: null,
        currentTerm: null
    };

    function toast(msg, type = 'info') {
        const el = document.getElementById('resultsToast');
        if (!el) {
            const toast = document.createElement('div');
            toast.id = 'resultsToast';
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

    function cbcGrade(score, maxMarks = 100) {
        const percentage = (score / maxMarks) * 100;
        if (percentage >= 80) return 'EE';
        if (percentage >= 50) return 'ME';
        if (percentage >= 25) return 'AE';
        return 'BE';
    }

    function getRemarks(score, maxMarks = 100) {
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

    async function loadClasses() {
        try {
            const response = await apiCall('academic/classes-list');
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

    async function loadSubjects() {
        try {
            const response = await apiCall('academic/subjects-list');
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

    async function loadExams() {
        try {
            const yearId = document.getElementById('yearFilter')?.value || '';
            const classId = document.getElementById('classFilter')?.value || '';
            const subjectId = document.getElementById('subjectFilter')?.value || '';

            const response = await apiCall('academic/exam-schedule', 'GET', {
                year_id: yearId,
                class_id: classId,
                subject_id: subjectId
            });

            state.exams = response.data?.exams || [];
            const select = document.getElementById('examFilter');
            if (select) {
                select.innerHTML = '<option value="">Select Exam</option>';
                state.exams.forEach(exam => {
                    const option = document.createElement('option');
                    option.value = exam.id;
                    option.textContent = `${exam.name} (${exam.subject_name || 'All Subjects'} - ${exam.class_name || 'All Classes'})`;
                    select.appendChild(option);
                });
            }
        } catch (error) {
            console.error('Failed to load exams:', error);
            toast('Failed to load exams', 'error');
        }
    }

    async function loadStudentsForExam(examId) {
        try {
            const exam = state.exams.find(e => e.id === examId);
            if (!exam) return;

            state.currentExam = exam;

            // Show exam info
            document.getElementById('examInfo').style.display = 'block';
            document.getElementById('examName').textContent = exam.name || 'Unnamed';
            document.getElementById('examSubject').textContent = exam.subject_name || '—';
            document.getElementById('examType').textContent = exam.type || '—';
            document.getElementById('examMaxMarks').textContent = exam.max_marks || 100;
            document.getElementById('examDate').textContent = exam.exam_date || '—';

            // Load students for the exam's class and subject
            const response = await apiCall('academic/class-students', 'GET', {
                class_id: exam.class_id,
                subject_id: exam.subject_id,
                year_id: exam.year_id,
                term_id: exam.term_id
            });

            state.students = response.data || [];

            // Resolve the assessment linked to this exam (class/subject/term) so marks
            // persist into assessment_results. Exams and assessments are separate
            // entities; we map by matching class_id/subject_id/term_id.
            state.currentAssessmentId = await resolveExamAssessment(exam);

            // Load existing results
            await loadExistingResults(examId);

            renderResultsTable();
            updateStats();
        } catch (error) {
            console.error('Failed to load students:', error);
            toast('Failed to load students', 'error');
        }
    }

    async function resolveExamAssessment(exam) {
        try {
            const response = await apiCall('academic/assessments-list', 'GET', {
                class_id: exam.class_id,
                subject_id: exam.subject_id,
                term_id: exam.term_id
            });
            const items = response.data || [];
            return items.length ? items[0].id : null;
        } catch (error) {
            console.error('Failed to resolve exam assessment:', error);
            return null;
        }
    }

    async function loadExistingResults(examId) {
        try {
            if (!state.currentAssessmentId) {
                state.results = [];
                return;
            }
            const response = await apiCall('academic/grading-results', 'GET', {
                assessment_id: state.currentAssessmentId
            });

            state.results = response.data?.items || [];
        } catch (error) {
            console.error('Failed to load existing results:', error);
            state.results = [];
        }
    }

    function renderResultsTable() {
        const tbody = document.getElementById('resultsTableBody');
        if (!tbody) return;

        if (!state.students.length) {
            tbody.innerHTML = `
                <tr>
                    <td colspan="8" class="text-center text-muted py-4">
                        No students found for this exam
                    </td>
                </tr>
            `;
            return;
        }

        const maxMarks = state.currentExam?.max_marks || 100;

        tbody.innerHTML = state.students.map((student, index) => {
            const existingResult = state.results.find(r => r.student_id === student.id);
            const marks = existingResult?.marks || '';
            const grade = existingResult?.grade || '';
            const remarks = existingResult?.remarks || '';
            const lastUpdated = existingResult?.updated_at || '—';

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
                               class="form-control results-input" 
                               min="0" 
                               max="${maxMarks}" 
                               step="0.5"
                               value="${marks}"
                               data-student-id="${student.id}"
                               data-max-marks="${maxMarks}"
                               onchange="enterExamResultsCtrl.calculateGrade(this)">
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
        const maxMarks = parseFloat(input.dataset.maxMarks) || 100;
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
        const entered = state.students.filter(student => {
            const input = document.querySelector(`.results-input[data-student-id="${student.id}"]`);
            return input && input.value !== '';
        }).length;
        const pending = total - entered;

        document.getElementById('totalStudents').textContent = total;
        document.getElementById('enteredStudents').textContent = entered;
        document.getElementById('pendingStudents').textContent = pending;
    }

    async function saveAllResults() {
        const examId = state.currentExam?.id;
        if (!examId) {
            toast('Please select an exam first', 'warning');
            return;
        }
        if (!state.currentAssessmentId) {
            toast('No matching assessment for this exam — cannot save marks', 'warning');
            return;
        }

        const gradingData = [];
        const inputs = document.querySelectorAll('.results-input');

        inputs.forEach(input => {
            const studentId = input.dataset.studentId;
            const marks = parseFloat(input.value) || 0;
            const maxMarks = parseFloat(input.dataset.maxMarks) || 100;

            if (marks > 0) {
                gradingData.push({
                    student_id: studentId,
                    marks_obtained: marks,
                    grade: cbcGrade(marks, maxMarks),
                    remarks: getRemarks(marks, maxMarks)
                });
            }
        });

        if (!gradingData.length) {
            toast('No results to save', 'warning');
            return;
        }

        try {
            const saveBtn = document.getElementById('saveAllBtn');
            saveBtn.disabled = true;
            saveBtn.textContent = 'Saving...';

            await apiCall('academic/assessments-mark-and-grade', 'POST', {
                assessment_id: state.currentAssessmentId,
                grading_data: gradingData
            });

            toast('Results saved successfully', 'success');
            await loadExistingResults(examId);
            renderResultsTable();
        } catch (error) {
            console.error('Failed to save results:', error);
            toast('Failed to save results', 'error');
        } finally {
            const saveBtn = document.getElementById('saveAllBtn');
            saveBtn.disabled = false;
            saveBtn.textContent = 'Save All Results';
        }
    }

    function autoCalculate() {
        const inputs = document.querySelectorAll('.results-input');
        inputs.forEach(input => {
            calculateGrade(input);
        });
        toast('Grades calculated automatically', 'success');
    }

    async function exportResults() {
        const examId = state.currentExam?.id;
        if (!examId) {
            toast('Please select an exam first', 'warning');
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

            const maxMarks = state.currentExam?.max_marks || 100;
            const rows = state.students.map(student => {
                const input = document.querySelector(`.results-input[data-student-id="${student.id}"]`);
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
                filename: `exam_results_${state.currentExam.name}_${new Date().toISOString().slice(0,10)}.csv`,
                columns: columns,
                rows: rows
            });
        } else {
            toast('PrintManager not available', 'error');
        }
    }

    function bindEvents() {
        document.getElementById('yearFilter')?.addEventListener('change', loadExams);
        document.getElementById('classFilter')?.addEventListener('change', loadExams);
        document.getElementById('subjectFilter')?.addEventListener('change', loadExams);
        document.getElementById('examFilter')?.addEventListener('change', (e) => {
            if (e.target.value) {
                loadStudentsForExam(parseInt(e.target.value));
            } else {
                document.getElementById('examInfo').style.display = 'none';
                document.getElementById('resultsTableBody').innerHTML = `
                    <tr>
                        <td colspan="8" class="text-center text-muted py-4">
                            Select an exam to enter results
                        </td>
                    </tr>
                `;
                updateStats();
            }
        });
        document.getElementById('saveAllBtn')?.addEventListener('click', saveAllResults);
        document.getElementById('autoCalculateBtn')?.addEventListener('click', autoCalculate);
        document.getElementById('exportBtn')?.addEventListener('click', exportResults);
    }

    async function init() {
        if (typeof AuthContext !== 'undefined' && !AuthContext.isAuthenticated()) {
            window.location.href = (window.APP_BASE || '') + '/index.php';
            return;
        }

        // Initialize Academic Context if available
        if (window.AcademicContext) {
            window.AcademicContext.subscribe((context, event, data) => {
                console.log('AcademicContext changed in enter_exam_results:', event, data);
                if (event === 'yearChanged' || event === 'termChanged' || event === 'initialized' || event === 'refreshed') {
                    loadYears();
                    loadTerms();
                    loadExams();
                }
            });

            if (!window.AcademicContext.isLoaded()) {
                await window.AcademicContext.init();
            }

            state.currentAcademicYear = window.AcademicContext.getAcademicYearId();
            state.currentTerm = window.AcademicContext.getTermId();
        }

        document.getElementById('resultsLoading').style.display = 'none';
        document.getElementById('resultsContent').style.display = 'block';

        await Promise.all([loadYears(), loadTerms(), loadClasses(), loadSubjects()]);
        await loadExams();
        bindEvents();
    }

    return {
        init,
        calculateGrade
    };
})();

document.addEventListener('DOMContentLoaded', enterExamResultsCtrl.init);
