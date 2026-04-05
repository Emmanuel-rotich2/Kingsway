/**
 * view_results.js — Student Result Viewer
 * Controller: viewResultsCtrl
 * Page: pages/view_results.php
 *
 * API:
 *   GET /academic/terms-list   → terms
 *   GET /academic/classes-list → classes
 *   GET /students/student?class_id=X → students (double-wrapped: data.data.students[])
 */
const viewResultsCtrl = (() => {
    function toast(msg, type = 'info') {
        const el = document.getElementById('vrToast');
        if (!el) return;
        const body = document.getElementById('vrToastBody');
        el.className = `toast align-items-center text-bg-${type} border-0`;
        body.textContent = msg;
        bootstrap.Toast.getOrCreateInstance(el, { delay: 3500 }).show();
    }

    async function apiGet(route, params = {}) {
        const endpoint = `/${String(route).replace(/^\/+/, '')}`;
        return window.API.apiCall(endpoint, 'GET', null, params);
    }

    function cbcGrade(pct) {
        const n = parseFloat(pct);
        if (n >= 80) return { label: 'EE', cls: 'grade-EE' };
        if (n >= 50) return { label: 'ME', cls: 'grade-ME' };
        if (n >= 25) return { label: 'AE', cls: 'grade-AE' };
        return { label: 'BE', cls: 'grade-BE' };
    }

    async function loadTerms() {
        try {
            const terms = await apiGet('academic/terms-list');
            const sel = document.getElementById('vrTermSelect');
            sel.innerHTML = '<option value="">— Select Term —</option>';
            (Array.isArray(terms) ? terms : []).forEach(t => {
                const opt = document.createElement('option');
                opt.value = t.id;
                opt.textContent = `${t.name} (${t.year_code || ''})`;
                if (t.status === 'current') opt.selected = true;
                sel.appendChild(opt);
            });
        } catch (e) { toast('Failed to load terms: ' + e.message, 'danger'); }
    }

    async function loadClasses() {
        try {
            const classes = await apiGet('academic/classes-list');
            const sel = document.getElementById('vrClassSelect');
            sel.innerHTML = '<option value="">— Select Class —</option>';
            (Array.isArray(classes) ? classes : []).forEach(c => {
                const opt = document.createElement('option');
                opt.value = c.id;
                opt.textContent = `${c.name} (${c.level_code || ''})`;
                sel.appendChild(opt);
            });
        } catch (e) { toast('Failed to load classes: ' + e.message, 'danger'); }
    }

    async function loadStudents() {
        const classId  = document.getElementById('vrClassSelect')?.value;
        const studentSel = document.getElementById('vrStudentSelect');
        if (!studentSel) return;
        studentSel.innerHTML = '<option value="">Loading…</option>';
        studentSel.disabled = true;
        try {
            const params = { limit: 200 };
            if (classId) params.class_id = classId;
            const raw = await apiGet('students/student', params);
            /* Handle double-wrap: data.data.students[] */
            let list = [];
            if (raw && Array.isArray(raw.students)) {
                list = raw.students;
            } else if (raw && raw.data && Array.isArray(raw.data.students)) {
                list = raw.data.students;
            } else if (Array.isArray(raw)) {
                list = raw;
            }
            studentSel.innerHTML = '<option value="">— Select Student —</option>';
            list.forEach(s => {
                const opt = document.createElement('option');
                opt.value = s.id;
                opt.textContent = `${s.first_name} ${s.last_name} (${s.admission_no || '—'})`;
                studentSel.appendChild(opt);
            });
            studentSel.disabled = false;
        } catch (e) {
            studentSel.innerHTML = '<option value="">Error loading students</option>';
            toast('Failed to load students: ' + e.message, 'danger');
        }
    }

    function buildStudentProfile(student) {
        const fullName = [student.first_name, student.middle_name, student.last_name].filter(Boolean).join(' ');
        const photoUrl = student.photo_url || (window.APP_BASE || '') + '/images/students/default.png';
        return `<div class="student-profile-card">
            <div class="d-flex align-items-center gap-3 mb-3">
                <div class="profile-avatar"><img src="${photoUrl}" alt="photo" onerror="this.src=(window.APP_BASE || '') + '/images/students/default.png'"></div>
                <div>
                    <h5 class="mb-0">${fullName}</h5>
                    <small class="text-muted">Adm: ${student.admission_no || '—'} &bull; ${student.class_name || '—'} / ${student.stream_name || '—'}</small>
                </div>
            </div>
        </div>`;
    }

    function buildSubjectRow(subject) {
        const pct  = subject.percentage || subject.score || 0;
        const g    = cbcGrade(pct);
        const bar  = Math.min(parseFloat(pct), 100);
        return `<div class="subject-row">
            <div class="d-flex justify-content-between align-items-center mb-1">
                <span class="fw-semibold">${subject.subject_name || subject.name || '—'}</span>
                <div class="d-flex gap-2 align-items-center">
                    <span>${parseFloat(pct).toFixed(1)}%</span>
                    <span class="${g.cls}">${g.label}</span>
                </div>
            </div>
            <div class="perf-bar"><div class="perf-bar-fill" style="width:${bar}%"></div></div>
        </div>`;
    }

    async function loadResults() {
        const termId    = document.getElementById('vrTermSelect')?.value;
        const studentId = document.getElementById('vrStudentSelect')?.value;
        const container = document.getElementById('resultsContainer');
        if (!container) return;
        if (!studentId) { toast('Please select a student', 'warning'); return; }
        container.innerHTML = `<div class="vr-loading"><div class="spinner-border" role="status"></div></div>`;
        try {
            const params = {};
            if (termId)    params.term_id  = termId;
            if (studentId) params.student_id = studentId;
            const data = await apiGet('academic/student-results', params);
            const student  = data.student  || {};
            const subjects = data.subjects || data.results || [];
            const summary  = data.summary  || {};
            if (!subjects.length) {
                container.innerHTML = `<div class="vr-empty"><i class="bi bi-inbox"></i>No results found for this student in the selected term.</div>`;
                return;
            }
            const overall = summary.percentage || (subjects.reduce((a, s) => a + parseFloat(s.percentage || s.score || 0), 0) / subjects.length);
            const grade   = cbcGrade(overall);
            container.innerHTML = `
                ${buildStudentProfile(student)}
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h6 class="mb-0">Results by Subject</h6>
                    <div class="d-flex align-items-center gap-2">
                        <span>Overall: <strong>${parseFloat(overall).toFixed(1)}%</strong></span>
                        <span class="${grade.cls}">${grade.label}</span>
                    </div>
                </div>
                <div>${subjects.map(buildSubjectRow).join('')}</div>
                <div class="mt-3 d-flex gap-2">
                    <button class="btn btn-sm btn-outline-primary" onclick="viewResultsCtrl.printResults()"><i class="bi bi-printer me-1"></i>Print</button>
                    <button class="btn btn-sm btn-outline-success" onclick="viewResultsCtrl.exportCSV()"><i class="bi bi-download me-1"></i>Export</button>
                </div>`;
        } catch (e) {
            toast('Failed to load results: ' + e.message, 'danger');
            container.innerHTML = `<div class="vr-empty"><i class="bi bi-exclamation-triangle"></i>Error loading results</div>`;
        }
    }

    function printResults() { window.print(); }

    function exportCSV() {
        const container = document.getElementById('resultsContainer');
        if (!container || container.querySelector('.vr-empty')) { toast('No results to export', 'warning'); return; }

        const subjectRows = [...container.querySelectorAll('.subject-row')];
        if (!subjectRows.length) {
            toast('No subject rows available for export', 'warning');
            return;
        }

        const csvRows = [['Subject', 'Percentage', 'Grade']];
        subjectRows.forEach((row) => {
            const subject = row.querySelector('.fw-semibold')?.textContent?.trim() || '';
            const metricEls = row.querySelectorAll('.d-flex.gap-2.align-items-center span');
            const pct = metricEls[0]?.textContent?.trim() || '';
            const grade = metricEls[1]?.textContent?.trim() || '';
            csvRows.push([subject, pct, grade]);
        });

        const csv = csvRows
            .map((r) => r.map((c) => `"${String(c).replace(/"/g, '""')}"`).join(','))
            .join('\n');
        const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
        const url = URL.createObjectURL(blob);
        const link = document.createElement('a');
        link.href = url;
        link.download = `student_results_${new Date().toISOString().slice(0,10)}.csv`;
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
        URL.revokeObjectURL(url);
        toast('Results exported to CSV', 'success');
    }

    async function init() {
        if (typeof AuthContext !== 'undefined' && !AuthContext.isAuthenticated()) {
            window.location.href = (window.APP_BASE || '') + '/index.php';
            return;
        }
        await Promise.all([loadTerms(), loadClasses()]);
        const classEl = document.getElementById('vrClassSelect');
        if (classEl) classEl.addEventListener('change', loadStudents);
        toast('Select a term and class to view student results', 'info');
    }

    return { init, loadStudents, loadResults, printResults, exportCSV };
})();

document.addEventListener('DOMContentLoaded', viewResultsCtrl.init);
