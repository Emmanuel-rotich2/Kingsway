/**
 * report_cards.js — Report Cards Controller
 * Kingsway Preparatory School Management System
 * Integrates with AcademicContext for academic year awareness
 *
 * API Endpoints:
 *   GET  /academic/years-list         → data[]  {id, year_name, is_current}
 *   GET  /academic/terms-list         → data[]  {id, name, term_number, status, year_name}
 *   GET  /academic/classes-list       → data[]  {id, name, level_name, level_code}
 *   GET  /students/student            → data.data.students[] + data.data.pagination (DOUBLE-WRAPPED)
 *   POST /academic/reports-start-workflow
 *   POST /academic/reports-compile-data
 *   POST /academic/reports-generate-student-reports
 */

const reportCardsCtrl = (() => {

    /* ─── State ─────────────────────────────────────────────── */
    const state = {
        years: [], terms: [], classes: [], streams: [], students: [],
        currentYear: null, currentTerm: null,
        pagination: { page: 1, limit: 15, total: 0, total_pages: 1 },
        summary: { total: 0, generated: 0, pending: 0, downloaded: 0 },
        currentAcademicYear: null,
        currentTermFromContext: null
    };
    const filters = { year_id: '', term_id: '', class_id: '', stream_id: '', search: '' };
    let searchTimeout = null;

    /* ─── API helper ─────────────────────────────────────────── */
    async function api(route, method = 'GET', body = null) {
        const endpoint = `/${String(route).replace(/^\/+/, '')}`;
        const data = await window.API.apiCall(endpoint, method, body);
        return { status: 'success', data };
    }

    /* ─── Toast ──────────────────────────────────────────────── */
    function toast(msg, type = 'success') {
        const el  = document.getElementById('rcToast');
        const bod = document.getElementById('rcToastBody');
        if (!el || !bod) return;
        el.className = `toast align-items-center border-0 text-white bg-${type === 'error' ? 'danger' : type === 'primary' ? 'primary' : 'success'}`;
        bod.textContent = msg;
        bootstrap.Toast.getOrCreateInstance(el, { delay: 3500 }).show();
    }

    /* ─── Init ───────────────────────────────────────────────── */
    async function init() {
        try {
            await window.AuthContext?.ready();
            if (typeof AuthContext !== 'undefined' && !AuthContext.isAuthenticated()) {
                window.location.href = (window.APP_BASE || '') + '/index.php';
                return;
            }
            await GradingScale.preload();
            
            // Initialize Academic Context if available
            if (window.AcademicContext) {
                // Subscribe to context changes
                window.AcademicContext.subscribe((context, event, data) => {
                    if (event === 'yearChanged' || event === 'termChanged' || event === 'initialized' || event === 'refreshed') {
                        // Reload years, terms, and data when academic year or term changes
                        loadYears();
                        loadTerms();
                        updateContextSelects();
                        loadData();
                    }
                });
                
                // Ensure context is loaded
                if (!window.AcademicContext.isLoaded()) {
                    await window.AcademicContext.init();
                }
                
                // Get current academic context
                state.currentAcademicYear = window.AcademicContext.getAcademicYearId();
                state.currentTermFromContext = window.AcademicContext.getTermId();
            }

            await Promise.all([loadYears(), loadTerms(), loadClasses()]);
            updateContextSelects();
            loadData();
            bindEvents();
            bindStreamEvents();
        } catch (e) {
            console.error('[reportCardsCtrl] init error', e);
            toast('Failed to initialise: ' + e.message, 'error');
        }
    }

    async function loadYears() {
        const r = await api('academic/years-list');
        state.years = r.data || [];
        state.currentYear = state.years.find(y => y.is_current == 1) || state.years[0] || null;
    }
    async function loadTerms() {
        const r = await api('academic/terms-list');
        state.terms = r.data || [];
        state.currentTerm = state.terms.find(t => t.status === 'current') || state.terms[0] || null;
    }
    async function loadClasses() {
        const r = await api('academic/classes-list');
        state.classes = r.data || [];
    }

    async function loadStreams(classId) {
        if (!classId) { state.streams = []; updateStreamFilter(); return; }
        try {
            const r = await api(`students/student/streams?class_id=${classId}`);
            const inner = r.data?.data || r.data || [];
            state.streams = Array.isArray(inner) ? inner : (inner.streams || []);
        } catch (e) {
            state.streams = [];
        }
        updateStreamFilter();
    }

    function updateStreamFilter() {
        const el = document.getElementById('streamFilter');
        if (!el) return;
        el.innerHTML = '<option value="">All Streams</option>' +
            state.streams.map(s => `<option value="${s.id}">${esc(s.name || s.stream_name || '—')}</option>`).join('');
        if (filters.stream_id) el.value = filters.stream_id;
    }

    function updateContextSelects() {
        /* Year filters */
        ['yearFilter','wfRcYear'].forEach(id => {
            const el = document.getElementById(id);
            if (!el) return;
            el.innerHTML = `<option value="">All Years</option>` +
                state.years.map(y => `<option value="${y.id}"${y.is_current == 1 ? ' selected' : ''}>${y.year_name}</option>`).join('');
            if (id === 'wfRcYear') el.innerHTML = el.innerHTML.replace('<option value="">All Years</option>', '<option value="">Select Year</option>');
        });

        /* Term filters */
        ['termFilter','wfRcTerm'].forEach(id => {
            const el = document.getElementById(id);
            if (!el) return;
            el.innerHTML = `<option value="">All Terms</option>` +
                state.terms.map(t =>
                    `<option value="${t.id}"${t.status === 'current' ? ' selected' : ''}>${t.name} — ${t.year_name || ''}</option>`
                ).join('');
            if (id === 'wfRcTerm') el.innerHTML = el.innerHTML.replace('<option value="">All Terms</option>', '<option value="">Select Term</option>');
            /* Preselect current term in filter */
            if (id === 'termFilter' && state.currentTerm) {
                el.value = state.currentTerm.id;
                filters.term_id = String(state.currentTerm.id);
            }
        });

        /* Class filters */
        ['classFilter','wfRcClass'].forEach(id => {
            const el = document.getElementById(id);
            if (!el) return;
            el.innerHTML = `<option value="">All Classes</option>` +
                state.classes.map(c => `<option value="${c.id}">${c.name} (${c.level_code || c.level_name})</option>`).join('');
        });
    }

    /* ─── Load students ──────────────────────────────────────── */
    async function loadData(page = 1) {
        state.pagination.page = page;
        const tbody   = document.getElementById('reportCardsTbody');
        const pgMeta  = document.getElementById('paginationMeta');
        const tblMeta = document.getElementById('tableMeta');
        if (!tbody) return;

        tbody.innerHTML = '<tr><td colspan="11" class="rc-loading"><div class="spinner-border"></div></td></tr>';

        try {
            const params = new URLSearchParams({ page, limit: state.pagination.limit });
            if (filters.class_id)   params.append('class_id',   filters.class_id);
            if (filters.stream_id)  params.append('stream_id',  filters.stream_id);
            if (filters.term_id)    params.append('term_id',    filters.term_id);
            if (filters.search)     params.append('search',     filters.search);

            const r = await api(`students/student?${params}`);
            /* Double-wrapped response: data.data.students[] */
            const inner  = r.data?.data || r.data || {};
            const students = inner.students || (Array.isArray(r.data) ? r.data : []);
            const pg       = inner.pagination || { total: students.length, total_pages: 1 };

            const releaseParams = new URLSearchParams();
            if (filters.term_id) releaseParams.set('term_id', filters.term_id);
            if (filters.class_id) releaseParams.set('class_id', filters.class_id);
            const releaseResponse = await api(`academic/report-card-releases?${releaseParams}`);
            const releases = releaseResponse.data?.data || releaseResponse.data || [];
            const releasesByStudent = new Map((Array.isArray(releases) ? releases : []).map(item => [Number(item.student_id), item]));
            students.forEach(student => {
                const release = releasesByStudent.get(Number(student.id));
                if (!release) return;
                student.release_id = Number(release.release_id);
                student.card_status = release.status;
                student.download_url = release.download_url;
                student.overall_pct = release.overall_percentage;
                student.rank = release.class_position;
                student.class_population = release.class_population;
            });

            state.students   = students;
            state.pagination = { ...state.pagination, ...pg };

            /* Summary */
            const generated  = students.filter(s => s.release_id).length;
            const downloaded = students.filter(s => s.card_status === 'released').length;
            const pending    = students.length - generated;
            state.summary = { total: pg.total, generated, pending, downloaded };
            renderSummary();

            if (!students.length) {
                tbody.innerHTML = `<tr><td colspan="11" class="rc-empty">
                    <i class="bi bi-file-earmark-text"></i>
                    No students found for the selected filters
                </td></tr>`;
                if (tblMeta) tblMeta.textContent = '0 students';
                if (pgMeta)  pgMeta.textContent  = '';
                renderPagination([]);
                return;
            }

            tbody.innerHTML = students.map(s => {
                const name      = [s.first_name, s.middle_name, s.last_name].filter(Boolean).join(' ');
                const cbcGrade  = s.cbc_grade || deriveCBCGrade(s.overall_pct);
                const cardSt    = s.card_status || 'pending';
                const termName  = s.term_name || (state.currentTerm?.name) || '—';
                const canDownload = Boolean(s.download_url);

                return `
                    <tr>
                        <td><input type="checkbox" class="student-select" value="${s.id}"></td>
                        <td>
                            <div class="fw-semibold">${esc(name)}</div>
                            <small class="text-muted">${s.gender ? s.gender : ''}</small>
                        </td>
                        <td><code>${esc(s.admission_no || '—')}</code></td>
                        <td>${esc(s.class_name || '—')}</td>
                        <td>${esc(s.stream_name || '—')}</td>
                        <td>${gradeBadge(cbcGrade)}</td>
                        <td>${s.overall_pct != null ? s.overall_pct + '%' : '—'}</td>
                        <td>${s.rank || s.position || '—'}</td>
                        <td>${cardStatusPill(cardSt)}</td>
                        <td>${esc(termName)}</td>
                        <td>
                            <div class="btn-group btn-group-sm">
                                <button class="btn btn-outline-success" title="${s.release_id ? 'Generate corrected version' : 'Generate report card'}"
                                    onclick="reportCardsCtrl.generateCard(${s.id})">
                                    <i class="bi bi-file-earmark-plus"></i>
                                </button>
                                ${cardSt === 'pending_approval' ? `<button class="btn btn-outline-success" title="Approve official PDF" onclick="reportCardsCtrl.approveCard(${s.release_id})"><i class="bi bi-check2-circle"></i></button>` : ''}
                                ${cardSt === 'approved' ? `<button class="btn btn-outline-warning" title="Release by SMS, email and WhatsApp" onclick="reportCardsCtrl.releaseCard(${s.release_id})"><i class="bi bi-send-check"></i></button>` : ''}
                                <button class="btn btn-outline-primary" title="Download"
                                    onclick="reportCardsCtrl.downloadCard(${s.id})"
                                    ${s.download_url ? '' : 'disabled'}>
                                    <i class="bi bi-download"></i>
                                </button>
                                <button class="btn btn-outline-secondary" title="Print"
                                    onclick="reportCardsCtrl.printCard(${s.id})"
                                    ${canDownload ? '' : 'disabled'}>
                                    <i class="bi bi-printer"></i>
                                </button>
                            </div>
                        </td>
                    </tr>`;
            }).join('');

            if (tblMeta) tblMeta.textContent = `${pg.total} student(s)`;
            if (pgMeta)  pgMeta.textContent  = `Page ${page} of ${pg.total_pages} · ${pg.total} records`;
            renderPagination(pg);

        } catch (e) {
            console.error('[loadData]', e);
            tbody.innerHTML = `<tr><td colspan="11" class="text-center text-danger p-4"><i class="bi bi-exclamation-triangle me-2"></i>${esc(e.message)}</td></tr>`;
        }
    }

    function renderSummary() {
        const set = (id, v) => { const el = document.getElementById(id); if (el) el.textContent = v; };
        set('totalStudents',  state.summary.total);
        set('cardsGenerated', state.summary.generated);
        set('cardsPending',   state.summary.pending);
        set('cardsDownloaded',state.summary.downloaded);
    }

    function renderPagination(pg) {
        const c = document.getElementById('pagination');
        if (!c) return;
        const totalPages = pg.total_pages || 1;
        const cur        = state.pagination.page;
        if (totalPages <= 1) { c.innerHTML = ''; return; }
        let h = '';
        for (let p = Math.max(1, cur - 2); p <= Math.min(totalPages, cur + 2); p++)
            h += `<li class="page-item${p === cur ? ' active' : ''}">
                <a class="page-link" href="#" onclick="event.preventDefault();reportCardsCtrl.loadPage(${p})">${p}</a>
            </li>`;
        c.innerHTML = `<li class="page-item${cur <= 1 ? ' disabled' : ''}">
            <a class="page-link" href="#" onclick="event.preventDefault();reportCardsCtrl.loadPage(${cur - 1})">&lsaquo;</a>
        </li>${h}<li class="page-item${cur >= totalPages ? ' disabled' : ''}">
            <a class="page-link" href="#" onclick="event.preventDefault();reportCardsCtrl.loadPage(${cur + 1})">&rsaquo;</a>
        </li>`;
    }

    /* ─── Actions ─────────────────────────────────────────────── */
    async function generateCard(studentId) {
        const termId = filters.term_id || state.currentTerm?.id;
        if (!termId) { toast('Select a term first', 'error'); return; }
        try {
            const response = await api('academic/reports-generate-student-reports', 'POST', {
                student_ids: [studentId],
                term_id: termId,
                academic_year_id: state.currentYear?.id,
                result_mode: document.getElementById('reportResultMode')?.value || 'both'
            });
            const result = response.data?.data || response.data || {};
            if (Number(result.failed_count || 0) > 0) {
                throw new Error(result.failed?.[0]?.message || 'The report card could not be generated');
            }
            toast('Report card generated for approval', 'success');
            loadData(state.pagination.page);
        } catch (e) { toast('Generate failed: ' + e.message, 'error'); }
    }

    async function generateAll() {
        const termId  = document.getElementById('termFilter')?.value  || state.currentTerm?.id;
        const classId = document.getElementById('classFilter')?.value || '';
        if (!termId) { toast('Select a term first', 'error'); return; }
        if (!(await window.confirmAction('Confirm', 'Generate report cards for all students in the selected filter?'))) return;
        try {
            const response = await api('academic/reports-generate-student-reports', 'POST', {
                term_id: termId, class_id: classId || undefined,
                academic_year_id: state.currentYear?.id,
                result_mode: document.getElementById('reportResultMode')?.value || 'both'
            });
            const result = response.data?.data || response.data || {};
            const generated = Number(result.generated_count || 0);
            const failed = Number(result.failed_count || 0);
            toast(failed ? `Generated ${generated}; ${failed} need attention` : `Generated ${generated} report card(s) for approval`, failed ? 'primary' : 'success');
            loadData(1);
        } catch (e) { toast('Failed: ' + e.message, 'error'); }
    }

    async function approveCard(releaseId) {
        if (!(await window.confirmAction('Approve report card', 'Approve this immutable PDF as the official report card?'))) return;
        try {
            await api('academic/reports-review-and-approve', 'POST', { release_id: releaseId });
            toast('Report card approved', 'success');
            await loadData(state.pagination.page);
        } catch (e) { toast('Approval failed: ' + e.message, 'error'); }
    }

    async function releaseCard(releaseId) {
        if (!(await window.confirmAction('Release report card', 'Queue the parent SMS and send the PDF by email and WhatsApp?'))) return;
        try {
            await api('academic/reports-distribute', 'POST', { release_id: releaseId, channels: ['sms', 'email', 'whatsapp'] });
            toast('Report card queued for guardian delivery', 'success');
            await loadData(state.pagination.page);
        } catch (e) { toast('Release failed: ' + e.message, 'error'); }
    }

    function buildReportCardHtml(student, summary, subjects, termLabel) {
        const studentName = [student.first_name, student.middle_name, student.last_name].filter(Boolean).join(' ') || 'Student';
        const overallPct = summary.percentage != null ? Number(summary.percentage).toFixed(2) : (student.year_average != null ? Number(student.year_average).toFixed(2) : '—');
        const overallGrade = (summary.grade || student.overall_grade || deriveCBCGrade(summary.percentage ?? student.year_average)).toString().toUpperCase();
        const attendance = summary.attendance_percentage != null ? `${Number(summary.attendance_percentage).toFixed(1)}%` : '—';
        const classRank = summary.class_rank != null ? summary.class_rank : '—';
        const streamRank = summary.stream_rank != null ? summary.stream_rank : '—';
        const daysPresent = summary.days_present != null ? summary.days_present : '—';
        const daysAbsent = summary.days_absent != null ? summary.days_absent : '—';

        const subjectRows = (subjects || []).map((row) => {
            const formative = row.formative_percentage ?? row.formative_pct;
            const summative = row.summative_percentage ?? row.summative_pct;
            const percentage = row.percentage ?? row.overall_percentage;
            const score = row.score ?? row.overall_score;
            const grade = (row.grade || row.overall_grade || deriveCBCGrade(percentage)).toString().toUpperCase();
            return `
                <tr>
                    <td>${esc(row.subject_name || '—')}</td>
                    <td>${formative != null ? `${Number(formative).toFixed(1)}%` : '—'}</td>
                    <td>${summative != null ? `${Number(summative).toFixed(1)}%` : '—'}</td>
                    <td>${percentage != null ? `${Number(percentage).toFixed(1)}%` : '—'}</td>
                    <td>${score != null ? Number(score).toFixed(1) : '—'}</td>
                    <td>${esc(grade)}</td>
                </tr>
            `;
        }).join('');

        return `
<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <title>Report Card - ${esc(studentName)}</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 24px; color: #1f2937; }
        h2 { margin: 0 0 4px; }
        .meta { margin: 0 0 14px; color: #4b5563; font-size: 13px; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th, td { border: 1px solid #d1d5db; padding: 8px; font-size: 13px; }
        th { background: #f3f4f6; text-align: left; }
        .summary { margin-top: 14px; }
        .summary td { width: 50%; }
    </style>
</head>
<body>
    <h2>Kingsway Preparatory School Report Card</h2>
    <p class="meta">Term: ${esc(termLabel || '—')} | Generated: ${new Date().toLocaleString()}</p>
    <table>
        <tr><th>Student</th><td>${esc(studentName)}</td><th>Admission No</th><td>${esc(student.admission_no || '—')}</td></tr>
        <tr><th>Class</th><td>${esc(student.class_name || '—')}</td><th>Stream</th><td>${esc(student.stream_name || '—')}</td></tr>
        <tr><th>Overall %</th><td>${overallPct === '—' ? '—' : `${overallPct}%`}</td><th>Overall Grade</th><td>${esc(overallGrade)}</td></tr>
    </table>

    <table>
        <thead>
            <tr>
                <th>Subject</th>
                <th>Formative %</th>
                <th>Summative %</th>
                <th>Overall %</th>
                <th>Score</th>
                <th>Grade</th>
            </tr>
        </thead>
        <tbody>
            ${subjectRows || '<tr><td colspan="6" style="text-align:center;color:#6b7280">No subject scores available for the selected term.</td></tr>'}
        </tbody>
    </table>

    <table class="summary">
        <tr><th>Class Rank</th><td>${esc(classRank)}</td><th>Stream Rank</th><td>${esc(streamRank)}</td></tr>
        <tr><th>Attendance</th><td>${esc(attendance)}</td><th>Days Present / Absent</th><td>${esc(daysPresent)} / ${esc(daysAbsent)}</td></tr>
    </table>
</body>
</html>
        `.trim();
    }

    function downloadTextFile(content, filename, mimeType = 'text/html;charset=utf-8') {
        KingswayFileLifecycle.exportText(content, filename, mimeType);
    }

    async function downloadCard(studentId) {
        const release = state.students.find((row) => Number(row.id) === Number(studentId));
        if (release?.download_url) {
            window.PrintManager?.openDocument(release.download_url, { title: 'Student report card' });
            return;
        }
        toast('Generate and approve the official PDF before downloading it', 'error');
    }

    function printCard(studentId) {
        const student = state.students.find((row) => Number(row.id) === Number(studentId));
        if (!student?.download_url) {
            toast('Generate and approve the official PDF before printing it', 'error');
            return;
        }
        window.PrintManager?.openDocument(student.download_url, { title: 'Student report card' });
    }

    async function downloadAll() {
        const termId  = document.getElementById('termFilter')?.value;
        const classId = document.getElementById('classFilter')?.value;
        if (!termId || !classId) { toast('Select both term and class to download all', 'error'); return; }
        if (!state.students.length) {
            toast('No report card data loaded for export', 'error');
            return;
        }

        // Use PrintManager for CSV export if available
        if (window.PrintManager) {
            const columns = [
                { key: 'name', label: 'Name' },
                { key: 'admission_no', label: 'Admission No' },
                { key: 'class_name', label: 'Class' },
                { key: 'stream_name', label: 'Stream' },
                { key: 'cbc_grade', label: 'CBC Grade' },
                { key: 'overall_pct', label: 'Overall %' },
                { key: 'rank', label: 'Rank' },
                { key: 'card_status', label: 'Card Status' }
            ];

            const rows = state.students.map((s) => ({
                name: [s.first_name, s.middle_name, s.last_name].filter(Boolean).join(' '),
                admission_no: s.admission_no || '',
                class_name: s.class_name || '',
                stream_name: s.stream_name || '',
                cbc_grade: s.cbc_grade || deriveCBCGrade(s.overall_pct),
                overall_pct: s.overall_pct != null ? `${s.overall_pct}%` : '',
                rank: s.rank || s.position || '',
                card_status: s.card_status || ''
            }));

            window.PrintManager.exportToCSV({
                filename: `report_cards_${new Date().toISOString().slice(0,10)}.csv`,
                columns: columns,
                rows: rows
            });
        } else {
            toast('PrintManager not available', 'error');
            return;
        }
        toast('Bulk report card export completed', 'success');
    }

    async function printAll() {
        const termId  = document.getElementById('termFilter')?.value;
        const classId = document.getElementById('classFilter')?.value;
        if (!termId || !classId) { toast('Select both term and class to print summary', 'error'); return; }
        if (!state.students.length) {
            toast('No report card data loaded for printing', 'error');
            return;
        }

        if (!window.PrintManager) {
            toast('PrintManager not available', 'error');
            return;
        }

        window.PrintManager.printTable({
            title: 'Bulk Report Card Summary',
            columns: reportCardColumns(),
            rows: reportCardRows(),
            orientation: 'landscape',
            paperSize: 'A4'
        });
    }

    function getSelectedStudents() {
        return state.students.filter(s => {
            const cb = document.querySelector(`.student-select[value="${s.id}"]`);
            return cb && cb.checked;
        });
    }

    async function printCards(selectedIds) {
        const termId = filters.term_id || state.currentTerm?.id;
        if (!termId) { toast('Select a term first', 'error'); return; }

        let studentsToPrint = state.students;
        if (selectedIds && selectedIds.length) {
            studentsToPrint = state.students.filter(s => selectedIds.includes(String(s.id)));
        }
        if (!studentsToPrint.length) { toast('No students to print', 'error'); return; }

        if (!window.PrintManager) { toast('PrintManager not available', 'error'); return; }

        try {
            toast('Building report cards…', 'primary');

            const termLabel = state.terms.find(t => String(t.id) === String(termId))?.name || state.currentTerm?.name || '';
            const allCards = [];

            for (const student of studentsToPrint) {
                const params = new URLSearchParams({ student_id: String(student.id), term_id: String(termId) });
                const resp = await api(`academic/student-results?${params}`);
                const payload = resp.data || {};
                const summary = payload.summary || {};
                const subjects = Array.isArray(payload.subjects) ? payload.subjects : [];
                const cardHtml = buildReportCardHtml(student, summary, subjects, termLabel);
                allCards.push(cardHtml);
            }

            if (!allCards.length) { toast('No cards could be generated', 'error'); return; }

            const combinedHtml = allCards.map((html, i) => {
                const bodyMatch = html.match(/<body[^>]*>([\s\S]*?)<\/body>/i);
                const bodyContent = bodyMatch ? bodyMatch[1] : html;
                const sep = i < allCards.length - 1 ? '<div style="page-break-after:always"></div>' : '';
                return bodyContent + sep;
            }).join('');

            const styleMatch = allCards[0].match(/<style[^>]*>([\s\S]*?)<\/style>/i);
            const styles = styleMatch ? styleMatch[1] : '';

            const fullDoc = `<!DOCTYPE html>
<html lang="en">
<head><meta charset="UTF-8"><style>${styles}
    body { font-family: Arial, sans-serif; margin: 20px; color: #1f2937; }
    @page { margin: 20mm 15mm; }
</style></head><body>${combinedHtml}</body></html>`;

            await window.PrintManager.printHtml({
                html: fullDoc,
                isFullDocument: true,
                title: 'Report Cards',
                filename: `report_cards_${new Date().toISOString().slice(0,10)}`,
                orientation: 'portrait',
                paperSize: 'A4',
                reportCode: 'RC-BULK-' + new Date().toISOString().slice(0,10).replace(/-/g, ''),
            });

            toast(`Printed ${allCards.length} report card(s)`, 'success');
        } catch (e) {
            toast('Print failed: ' + e.message, 'error');
        }
    }

    async function printSelected() {
        const selected = getSelectedStudents();
        if (!selected.length) { toast('Select students using checkboxes first', 'error'); return; }
        await printCards(selected.map(s => String(s.id)));
    }

    async function startWorkflow(e) {
        e.preventDefault();
        const form = e.target;
        const data = Object.fromEntries(new FormData(form));
        const btn  = form.querySelector('[type=submit]');
        if (btn) { btn.disabled = true; btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span>'; }
        try {
            await api('academic/reports-start-workflow', 'POST', {
                academic_year_id: data.academic_year_id,
                term_id: data.term_id,
                class_id: data.class_id || undefined
            });
            bootstrap.Modal.getInstance(document.getElementById('startReportWorkflowModal'))?.hide();
            form.reset();
            toast('Report workflow started', 'success');
            loadData(1);
        } catch (err) {
            toast('Failed: ' + err.message, 'error');
        } finally {
            if (btn) { btn.disabled = false; btn.innerHTML = '<i class="bi bi-play-circle me-1"></i>Start Workflow'; }
        }
    }

    function exportCSV() {
        if (!state.students.length) { toast('No data to export', 'error'); return; }
        if (!window.PrintManager) { toast('PrintManager not available', 'error'); return; }
        window.PrintManager.exportToCSV({
            filename: `report_cards_${new Date().toISOString().slice(0,10)}.csv`,
            columns: reportCardColumns(),
            rows: reportCardRows()
        });
        toast('CSV exported', 'success');
    }

    function reportCardColumns() {
        return [
            { key: 'name', label: 'Name' },
            { key: 'admission_no', label: 'Admission No' },
            { key: 'class_name', label: 'Class' },
            { key: 'stream_name', label: 'Stream' },
            { key: 'cbc_grade', label: 'CBC Grade' },
            { key: 'overall_pct', label: 'Overall %' },
            { key: 'rank', label: 'Rank' },
            { key: 'card_status', label: 'Card Status' }
        ];
    }

    function reportCardRows() {
        return state.students.map((s) => ({
            name: [s.first_name, s.middle_name, s.last_name].filter(Boolean).join(' '),
            admission_no: s.admission_no || '',
            class_name: s.class_name || '',
            stream_name: s.stream_name || '',
            cbc_grade: s.cbc_grade || deriveCBCGrade(s.overall_pct),
            overall_pct: s.overall_pct != null ? `${s.overall_pct}%` : '',
            rank: s.rank || s.position || '',
            card_status: s.card_status || ''
        }));
    }

    /* ─── Events ─────────────────────────────────────────────── */
    function bindEvents() {
        document.getElementById('loadBtn')?.addEventListener('click', () => loadData(1));
        document.getElementById('clearBtn')?.addEventListener('click', () => {
            filters.year_id = ''; filters.term_id = ''; filters.class_id = ''; filters.stream_id = ''; filters.search = '';
            ['yearFilter','termFilter','classFilter','streamFilter'].forEach(id => { const el = document.getElementById(id); if (el) el.value = ''; });
            const sb = document.getElementById('searchBox'); if (sb) sb.value = '';
            loadData(1);
        });
        document.getElementById('generateAllBtn')?.addEventListener('click', generateAll);
        document.getElementById('downloadAllBtn')?.addEventListener('click', downloadAll);
        document.getElementById('printCardsBtn')?.addEventListener('click', () => printCards());
        document.getElementById('printAllBtn')?.addEventListener('click', printAll);
        document.getElementById('yearFilter')?.addEventListener('change', e => { filters.year_id = e.target.value; loadData(1); });
        document.getElementById('termFilter')?.addEventListener('change', e => { filters.term_id = e.target.value; loadData(1); });
        document.getElementById('classFilter')?.addEventListener('change', e => { filters.class_id = e.target.value; loadStreams(e.target.value); loadData(1); });
        document.getElementById('streamFilter')?.addEventListener('change', e => { filters.stream_id = e.target.value; loadData(1); });
        document.getElementById('searchBox')?.addEventListener('keyup', e => {
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(() => { filters.search = e.target.value.trim(); loadData(1); }, 400);
        });
        document.getElementById('selectAll')?.addEventListener('change', e => {
            document.querySelectorAll('.student-select').forEach(cb => {
                cb.checked = e.target.checked;
            });
            togglePrintSelectedBtn();
        });
    }

    function togglePrintSelectedBtn() {
        const btn = document.getElementById('printSelectedBtn');
        if (!btn) return;
        const checked = document.querySelectorAll('.student-select:checked').length;
        btn.style.display = checked ? 'inline-block' : 'none';
    }

    function bindStreamEvents() {
        document.addEventListener('change', e => {
            if (e.target.matches('.student-select')) togglePrintSelectedBtn();
        });
    }

    /* ─── Helpers ─────────────────────────────────────────────── */
    function esc(s) { return s == null ? '' : String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;'); }
    function gradeBadge(g) { const u = (g||'').toUpperCase(); const band = GradingScale.band(u); return ['EE','ME','AE','BE'].includes(band) ? `<span class="grade-${band}">${u}</span>` : `<span class="badge bg-secondary">${u||'—'}</span>`; }
    function cardStatusPill(s) {
        const m = { generated:'rc-generated', pending:'rc-pending', pending_approval:'rc-pending', approved:'rc-approved', released:'rc-distributed', distributed:'rc-distributed', downloaded:'rc-approved', not_generated:'rc-pending' };
        return `<span class="rc-pill ${m[s]||'rc-pending'}">${esc(String(s).replaceAll('_', ' '))}</span>`;
    }
    function deriveCBCGrade(p) { if (p==null) return '—'; return GradingScale.grade(p) || '—'; }
    function deriveCBCLevel(className) {
        const c = (className || '').toLowerCase();
        if (/pp|pre.?prim|play|nursery|kg/.test(c)) return 'PP';
        if (/\b(1|2|3)\b/.test(c) && /grade|class/.test(c)) return 'LowerPrimary';
        if (/\b(4|5|6)\b/.test(c) && /grade|class/.test(c)) return 'UpperPrimary';
        if (/\b(7|8|9)\b/.test(c) && /grade|class/.test(c)) return 'JuniorSecondary';
        return 'LowerPrimary';
    }

    /* ─── DOMContentLoaded ────────────────────────────────────── */
    document.addEventListener('DOMContentLoaded', init);

    /* ─── Public ─────────────────────────────────────────────── */
    return {
        init, loadPage: loadData,
        generateCard, approveCard, releaseCard, downloadCard, printCard, generateAll,
        startWorkflow, exportCSV,
        printCards, printSelected
    };
})();

window.reportCardsCtrl = reportCardsCtrl;
window.APIRealtime?.register?.(reportCardsCtrl, reportCardsCtrl);
