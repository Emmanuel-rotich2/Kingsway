/**
 * term_reports.js — Term Reports Controller
 * Uses live student + academic endpoints and real report workflow actions.
 */
const termReportsCtrl = (() => {
    let currentPage = 1;
    let totalPages = 1;
    let selectedIds = new Set();
    let currentRows = [];
    let termsById = {};

    function toast(msg, type = 'info') {
        const el = document.getElementById('trToast');
        if (!el) return;
        el.className = `toast align-items-center text-bg-${type} border-0`;
        const body = document.getElementById('trToastBody');
        if (body) body.textContent = msg;
        bootstrap.Toast.getOrCreateInstance(el, { delay: 3500 }).show();
    }

    async function apiGet(route, params = {}) {
        const endpoint = `/${String(route).replace(/^\/+/, '')}`;
        return window.API.apiCall(endpoint, 'GET', null, params);
    }

    async function apiPost(route, body = {}) {
        const endpoint = `/${String(route).replace(/^\/+/, '')}`;
        return window.API.apiCall(endpoint, 'POST', body);
    }

    function cbcGrade(pct) {
        const n = parseFloat(pct);
        if (n >= 80) return '<span class="grade-EE">EE</span>';
        if (n >= 50) return '<span class="grade-ME">ME</span>';
        if (n >= 25) return '<span class="grade-AE">AE</span>';
        return '<span class="grade-BE">BE</span>';
    }

    function statusBadge(status) {
        const map = {
            generated: 'success',
            printed: 'primary',
            distributed: 'info',
            pending: 'warning',
            not_generated: 'secondary'
        };
        const normalized = String(status || 'pending').toLowerCase();
        const cls = map[normalized] || 'secondary';
        return `<span class="badge bg-${cls}">${normalized.replace(/_/g, ' ')}</span>`;
    }

    function getTermNumber() {
        const termId = document.getElementById('term')?.value || '';
        if (!termId || !termsById[termId]) return null;
        const number = parseInt(termsById[termId].term_number, 10);
        return Number.isFinite(number) ? number : null;
    }

    function resolvePercentage(student) {
        const termNumber = getTermNumber();
        if (termNumber === 1 && student.term1_average != null) return Number(student.term1_average);
        if (termNumber === 2 && student.term2_average != null) return Number(student.term2_average);
        if (termNumber === 3 && student.term3_average != null) return Number(student.term3_average);
        if (student.year_average != null) return Number(student.year_average);
        if (student.overall_percentage != null) return Number(student.overall_percentage);
        return null;
    }

    function resolveStatus(student, pct) {
        if (student.report_status) return String(student.report_status).toLowerCase();
        if (pct === null || Number.isNaN(pct)) return 'not_generated';
        return 'generated';
    }

    function findStudent(studentId) {
        return currentRows.find((row) => Number(row.id) === Number(studentId)) || null;
    }

    function ensurePreviewModal() {
        let modal = document.getElementById('trPreviewModal');
        if (modal) return modal;
        const wrapper = document.createElement('div');
        wrapper.innerHTML = `
            <div class="modal fade" id="trPreviewModal" tabindex="-1">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title">Term Report Preview</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body" id="trPreviewBody"></div>
                    </div>
                </div>
            </div>
        `;
        document.body.appendChild(wrapper.firstElementChild);
        return document.getElementById('trPreviewModal');
    }

    async function loadYears() {
        try {
            const years = await apiGet('academic/years-list');
            const select = document.getElementById('academicYear');
            if (!select) return;
            select.innerHTML = '<option value="">All Years</option>';
            (Array.isArray(years) ? years : []).forEach((year) => {
                const option = document.createElement('option');
                option.value = year.id;
                option.textContent = year.year_name;
                if (year.is_current) option.selected = true;
                select.appendChild(option);
            });
        } catch (error) {
            console.warn('loadYears failed:', error.message);
        }
    }

    async function loadTerms() {
        try {
            const terms = await apiGet('academic/terms-list');
            const select = document.getElementById('term');
            if (!select) return;
            select.innerHTML = '<option value="">All Terms</option>';
            termsById = {};
            (Array.isArray(terms) ? terms : []).forEach((term) => {
                termsById[String(term.id)] = term;
                const option = document.createElement('option');
                option.value = term.id;
                option.textContent = `${term.name} (${term.year_code || ''})`;
                if (term.status === 'current') option.selected = true;
                select.appendChild(option);
            });
        } catch (error) {
            console.warn('loadTerms failed:', error.message);
        }
    }

    async function loadClasses() {
        try {
            const classes = await apiGet('academic/classes-list');
            const select = document.getElementById('classFilter');
            if (!select) return;
            select.innerHTML = '<option value="">All Classes</option>';
            (Array.isArray(classes) ? classes : []).forEach((klass) => {
                const option = document.createElement('option');
                option.value = klass.id;
                option.textContent = `${klass.name} (${klass.level_code || ''})`;
                select.appendChild(option);
            });
        } catch (error) {
            console.warn('loadClasses failed:', error.message);
        }
    }

    function setKpi(id, value) {
        const el = document.getElementById(id);
        if (el) el.textContent = value;
    }

    function buildPagination(page, total) {
        const pagination = document.getElementById('trPagination');
        if (!pagination) return;
        let html = '';
        html += `<li class="page-item${page === 1 ? ' disabled' : ''}"><a class="page-link" href="#" onclick="termReportsCtrl.loadReports(${page - 1});return false">«</a></li>`;
        const start = Math.max(1, page - 2);
        const end = Math.min(total, page + 2);
        for (let i = start; i <= end; i += 1) {
            html += `<li class="page-item${i === page ? ' active' : ''}"><a class="page-link" href="#" onclick="termReportsCtrl.loadReports(${i});return false">${i}</a></li>`;
        }
        html += `<li class="page-item${page === total ? ' disabled' : ''}"><a class="page-link" href="#" onclick="termReportsCtrl.loadReports(${page + 1});return false">»</a></li>`;
        pagination.innerHTML = html;
    }

    function updateSelectAllState() {
        const all = document.querySelectorAll('.tr-chk');
        const checked = document.querySelectorAll('.tr-chk:checked');
        const selectAll = document.getElementById('trSelectAll') || document.getElementById('selectAll');
        if (!selectAll) return;
        selectAll.indeterminate = checked.length > 0 && checked.length < all.length;
        selectAll.checked = checked.length > 0 && checked.length === all.length;
    }

    function selectAll(toggle) {
        document.querySelectorAll('.tr-chk').forEach((checkbox) => {
            checkbox.checked = toggle.checked;
            const id = Number(checkbox.dataset.id);
            if (toggle.checked) selectedIds.add(id);
            else selectedIds.delete(id);
        });
    }

    async function loadReports(page = 1) {
        currentPage = page;
        const tbody = document.getElementById('reportsTbody');
        if (!tbody) return;

        tbody.innerHTML = `<tr><td colspan="10" class="text-center py-4"><div class="spinner-border spinner-border-sm" style="color:var(--tr-primary)"></div> Loading…</td></tr>`;

        try {
            const classId = document.getElementById('classFilter')?.value || '';
            const params = { page, limit: 10 };
            if (classId) params.class_id = classId;

            const raw = await apiGet('students/student', params);
            let list = [];
            let pagination = {};

            if (raw && Array.isArray(raw.students)) {
                list = raw.students;
                pagination = raw.pagination || {};
            } else if (raw && raw.data && Array.isArray(raw.data.students)) {
                list = raw.data.students;
                pagination = raw.data.pagination || {};
            } else if (Array.isArray(raw)) {
                list = raw;
            }

            currentRows = list;
            totalPages = Math.ceil((pagination.total || list.length) / 10) || 1;
            const total = pagination.total || list.length;

            const generated = list.filter((s) => resolveStatus(s, resolvePercentage(s)) === 'generated').length;
            const printed = list.filter((s) => resolveStatus(s, resolvePercentage(s)) === 'printed').length;
            const pending = list.filter((s) => resolveStatus(s, resolvePercentage(s)) !== 'generated').length;

            setKpi('totalStudents', total);
            setKpi('reportsGenerated', generated);
            setKpi('reportsPrinted', printed);
            setKpi('pendingRemarks', pending);

            const meta = document.getElementById('trMeta');
            if (meta) meta.textContent = `${list.length} of ${total} students`;
            const pgMeta = document.getElementById('trPgMeta');
            if (pgMeta) pgMeta.textContent = `Page ${page} of ${totalPages}`;

            if (!list.length) {
                tbody.innerHTML = `<tr><td colspan="10" class="text-center py-4 text-muted">No students found</td></tr>`;
                buildPagination(page, totalPages);
                return;
            }

            tbody.innerHTML = list.map((student) => {
                const name = `${student.first_name || ''} ${student.last_name || ''}`.trim();
                const pct = resolvePercentage(student);
                const pctText = pct !== null && Number.isFinite(pct) ? `${pct.toFixed(1)}%` : '—';
                const status = resolveStatus(student, pct);
                return `
                    <tr>
                        <td><input type="checkbox" class="form-check-input tr-chk" data-id="${student.id}" ${selectedIds.has(Number(student.id)) ? 'checked' : ''}></td>
                        <td><strong>${name || '—'}</strong></td>
                        <td>${student.admission_no || '—'}</td>
                        <td>${student.class_name || '—'}</td>
                        <td>${student.stream_name || '—'}</td>
                        <td>${pct !== null ? cbcGrade(pct) : '—'}</td>
                        <td>${pctText}</td>
                        <td>${student.class_rank || student.class_position || '—'}</td>
                        <td>${statusBadge(status)}</td>
                        <td>
                            <div class="btn-group btn-group-sm">
                                <button class="btn btn-outline-primary" title="View" onclick="termReportsCtrl.viewReport(${student.id})"><i class="bi bi-eye"></i></button>
                                <button class="btn btn-outline-success" title="Print" onclick="termReportsCtrl.printReport(${student.id})"><i class="bi bi-printer"></i></button>
                                <button class="btn btn-outline-secondary" title="Download" onclick="termReportsCtrl.downloadReport(${student.id})"><i class="bi bi-download"></i></button>
                            </div>
                        </td>
                    </tr>
                `;
            }).join('');

            tbody.querySelectorAll('.tr-chk').forEach((checkbox) => {
                checkbox.addEventListener('change', (event) => {
                    const id = Number(event.target.dataset.id);
                    if (event.target.checked) selectedIds.add(id);
                    else selectedIds.delete(id);
                    updateSelectAllState();
                });
            });

            updateSelectAllState();
            buildPagination(page, totalPages);
        } catch (error) {
            tbody.innerHTML = `<tr><td colspan="10" class="text-center text-danger py-4">Error loading data</td></tr>`;
            toast(error.message, 'danger');
        }
    }

    async function generateReports() {
        const ids = [...selectedIds];
        if (!ids.length) {
            toast('Select at least one student', 'warning');
            return;
        }
        const termId = document.getElementById('term')?.value;
        if (!termId) {
            toast('Select a term before generating reports', 'warning');
            return;
        }
        try {
            toast(`Generating ${ids.length} report(s)…`, 'info');
            await apiPost('academic/reports-generate-student-reports', {
                student_ids: ids,
                term_id: Number(termId)
            });
            toast('Report generation queued successfully', 'success');
            selectedIds.clear();
            await loadReports(currentPage);
        } catch (error) {
            toast(`Generation failed: ${error.message}`, 'danger');
        }
    }

    function viewReport(studentId) {
        const student = findStudent(studentId);
        if (!student) {
            toast('Student record not found', 'warning');
            return;
        }
        const pct = resolvePercentage(student);
        const status = resolveStatus(student, pct);
        const modalEl = ensurePreviewModal();
        const body = document.getElementById('trPreviewBody');
        if (body) {
            body.innerHTML = `
                <div class="mb-2"><strong>Name:</strong> ${student.first_name || ''} ${student.last_name || ''}</div>
                <div class="mb-2"><strong>Admission No:</strong> ${student.admission_no || '—'}</div>
                <div class="mb-2"><strong>Class/Stream:</strong> ${student.class_name || '—'} / ${student.stream_name || '—'}</div>
                <div class="mb-2"><strong>Score:</strong> ${pct !== null ? `${pct.toFixed(1)}%` : 'No score recorded'}</div>
                <div class="mb-2"><strong>Status:</strong> ${statusBadge(status)}</div>
            `;
        }
        bootstrap.Modal.getOrCreateInstance(modalEl).show();
    }

    function printReport(studentId) {
        const student = findStudent(studentId);
        if (!student) {
            toast('Student record not found', 'warning');
            return;
        }
        const pct = resolvePercentage(student);
        const status = resolveStatus(student, pct);
        const popup = window.open('', '_blank', 'width=820,height=620');
        if (!popup) {
            toast('Could not open print window', 'warning');
            return;
        }
        popup.document.write(`
            <html><head><title>Term Report</title></head><body>
            <h2>Term Report Summary</h2>
            <p><strong>Name:</strong> ${student.first_name || ''} ${student.last_name || ''}</p>
            <p><strong>Admission No:</strong> ${student.admission_no || '—'}</p>
            <p><strong>Class:</strong> ${student.class_name || '—'} / ${student.stream_name || '—'}</p>
            <p><strong>Score:</strong> ${pct !== null ? `${pct.toFixed(1)}%` : 'No score recorded'}</p>
            <p><strong>Status:</strong> ${status}</p>
            </body></html>
        `);
        popup.document.close();
        popup.focus();
        popup.print();
    }

    function downloadReport(studentId) {
        const student = findStudent(studentId);
        if (!student) {
            toast('Student record not found', 'warning');
            return;
        }
        const pct = resolvePercentage(student);
        const status = resolveStatus(student, pct);
        const rows = [
            ['Student', 'Admission No', 'Class', 'Stream', 'Score (%)', 'Status'],
            [
                `${student.first_name || ''} ${student.last_name || ''}`.trim(),
                student.admission_no || '',
                student.class_name || '',
                student.stream_name || '',
                pct !== null ? pct.toFixed(1) : '',
                status
            ]
        ];
        const csv = rows.map((r) => r.map((c) => `"${String(c).replace(/"/g, '""')}"`).join(',')).join('\n');
        const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
        const url = URL.createObjectURL(blob);
        const link = document.createElement('a');
        link.href = url;
        link.download = `term_report_${student.admission_no || student.id}.csv`;
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
        URL.revokeObjectURL(url);
        toast('Report exported', 'success');
    }

    function bulkPrint() {
        const ids = [...selectedIds];
        if (!ids.length) {
            toast('No students selected', 'warning');
            return;
        }
        const printableRows = ids
            .map((id) => findStudent(id))
            .filter(Boolean)
            .map((student) => {
                const pct = resolvePercentage(student);
                return `<tr><td>${student.first_name || ''} ${student.last_name || ''}</td><td>${student.admission_no || '—'}</td><td>${student.class_name || '—'}</td><td>${pct !== null ? `${pct.toFixed(1)}%` : '—'}</td></tr>`;
            })
            .join('');

        const popup = window.open('', '_blank', 'width=900,height=650');
        if (!popup) {
            toast('Could not open print window', 'warning');
            return;
        }
        popup.document.write(`
            <html><head><title>Bulk Term Reports</title></head><body>
            <h2>Bulk Term Report Summary</h2>
            <table border="1" cellspacing="0" cellpadding="6">
                <thead><tr><th>Student</th><th>Admission</th><th>Class</th><th>Score</th></tr></thead>
                <tbody>${printableRows}</tbody>
            </table>
            </body></html>
        `);
        popup.document.close();
        popup.focus();
        popup.print();
    }

    function exportCSV() {
        if (!currentRows.length) {
            toast('No data to export', 'warning');
            return;
        }
        const rows = [
            ['Student', 'Admission', 'Class', 'Stream', 'Score (%)', 'Status'],
            ...currentRows.map((student) => {
                const pct = resolvePercentage(student);
                const status = resolveStatus(student, pct);
                return [
                    `${student.first_name || ''} ${student.last_name || ''}`.trim(),
                    student.admission_no || '',
                    student.class_name || '',
                    student.stream_name || '',
                    pct !== null ? pct.toFixed(1) : '',
                    status
                ];
            })
        ];
        const csv = rows.map((r) => r.map((c) => `"${String(c).replace(/"/g, '""')}"`).join(',')).join('\n');
        const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
        const url = URL.createObjectURL(blob);
        const link = document.createElement('a');
        link.href = url;
        link.download = `term_reports_${new Date().toISOString().slice(0, 10)}.csv`;
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
        URL.revokeObjectURL(url);
        toast('Exported to CSV', 'success');
    }

    async function init() {
        if (typeof AuthContext !== 'undefined' && !AuthContext.isAuthenticated()) {
            window.location.href = (window.APP_BASE || '') + '/index.php';
            return;
        }

        await Promise.all([loadYears(), loadTerms(), loadClasses()]);

        const selectAllEl = document.getElementById('trSelectAll') || document.getElementById('selectAll');
        if (selectAllEl) {
            selectAllEl.addEventListener('change', (event) => selectAll(event.target));
        }

        document.getElementById('classFilter')?.addEventListener('change', () => loadReports(1));
        document.getElementById('term')?.addEventListener('change', () => loadReports(1));

        await loadReports(1);
    }

    return {
        init,
        loadReports,
        generateReports,
        bulkPrint,
        exportCSV,
        viewReport,
        printReport,
        downloadReport,
    };
})();

document.addEventListener('DOMContentLoaded', termReportsCtrl.init);
