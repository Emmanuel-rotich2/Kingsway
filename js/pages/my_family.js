/**
 * My Family — full in-shell staff family workspace controller.
 *
 * Runs inside the authenticated app shell (home.php?route=my_family) and
 * authenticates every request with the active staff JWT through FamilyController
 * (/api/family/*). It provides the full parent-portal functionality (linked
 * learners: fees, payments, attendance, learning, report cards, messages,
 * portfolio, statements) but with its own design and WITHOUT ever redirecting
 * away from the staff panel or showing the parent login.
 */
const MyFamilyController = {
    initialized: false,
    state: {
        children: [],
        parent: {},
        selectedStudentId: null,
        activeDetailTab: 'overview',
        _mpesaPolling: null,
    },

    async init() {
        if (this.initialized) return;
        await window.AuthContext?.ready();
        if (!window.AuthContext?.isAuthenticated?.()) {
            window.showNotification?.('Your session could not be restored. Please sign in.', 'warning');
            return;
        }
        this.bindEvents();
        await this.loadDashboard();
        this.initialized = true;
    },

    bindEvents() {
        this.on('mfRefresh', 'click', () => this.loadDashboard());
        this.on('mfChildSwitcher', 'change', (e) => {
            const id = e.target.value;
            const child = this.state.children.find((c) => String(c.id) === String(id));
            if (child) this.openStudent(child.id, child.first_name + ' ' + child.last_name, child.class_name || '');
        });
        this.on('mfHelp', 'click', () => {
            if (window.bootstrap) {
                bootstrap.Modal.getOrCreateInstance(document.getElementById('mfHelpModal')).show();
            }
        });

        // Delegate tab clicks inside the detail panel.
        const detail = document.getElementById('mfDetail');
        if (detail) {
            detail.addEventListener('click', (e) => {
                const tabBtn = e.target.closest('[data-mf-tab]');
                if (tabBtn) this.activateStudentTab(tabBtn.dataset.mfTab);
                const payBtn = e.target.closest('.pay-now-btn');
                if (payBtn) this.openMpesaModal(payBtn.dataset.amount);
                const btn = e.target.closest('[data-mf-action]');
                if (btn) this.handleDetailAction(btn.dataset.mfAction, e);
            });
        }
    },

    handleDetailAction(action, event) {
        if (action === 'print-report') {
            const btn = event.target.closest('[data-mf-action]');
            const url = btn && btn.dataset.downloadUrl;
            if (url) window.open(url, '_blank', 'noopener');
        }
    },

    // ---- API helper: staff JWT through /api/family/* ----
    apiFetch(path, method, body) {
        const opts = { noRedirect: true, skipAuthRefresh: false };
        return apiCall('/family' + path, method || 'GET', body, null, opts)
            .then((data) => data);
    },

    // ---- Dashboard ----
    async loadDashboard() {
        this.setLoading(true);
        try {
            const resp = await this.apiFetch('/dashboard', 'GET');
            const d = resp.data || resp;
            this.state.parent = d.parent || {};
            this.state.children = d.children || [];
            this.renderKpis(this.state.children);
            this.renderChildren(this.state.children);
            const detail = document.getElementById('mfDetail');
            if (detail) detail.innerHTML = this.emptyDetail();
        } catch (e) {
            const errEl = document.getElementById('mfError');
            if (errEl) {
                errEl.textContent = this.apiErrorMsg(e);
                errEl.classList.remove('d-none');
            }
            document.getElementById('mfChildrenCards').innerHTML = '';
        } finally {
            this.setLoading(false);
        }
    },

    renderKpis(children) {
        const total = children.reduce((sum, c) => sum + parseFloat(c.current_balance || 0), 0);
        const cleared = children.filter((c) => parseFloat(c.current_balance || 0) <= 0).length;
        const cards = [
            this.kpiCard('bi-people-fill', 'Linked learners', String(children.length), 'success'),
            this.kpiCard('bi-wallet2', 'Family balance', 'KES ' + total.toLocaleString(), 'danger'),
            this.kpiCard('bi-check-circle-fill', 'Accounts cleared', cleared + ' of ' + children.length, 'primary'),
            this.kpiCard('bi-chat-dots-fill', 'School connection', 'Open', 'info'),
        ];
        const el = document.getElementById('mfKpis');
        if (el) el.innerHTML = cards;
    },

    kpiCard(icon, label, value, color) {
        const dsc = { success: 'dsc-green', danger: 'dsc-red', primary: 'dsc-blue', info: 'dsc-cyan', warning: 'dsc-amber' };
        return '<div class="col-md-3 col-sm-6"><div class="dash-stat ' + (dsc[color] || 'dsc-slate') + ' h-100">' +
            '<div class="dash-stat-icon"><i class="bi ' + icon + '"></i></div>' +
            '<div class="dash-stat-value">' + value + '</div>' +
            '<div class="dash-stat-label">' + label + '</div>' +
            '</div></div>';
    },

    renderChildren(children) {
        const el = document.getElementById('mfChildrenCards');
        const countEl = document.getElementById('mfChildCount');
        if (countEl) countEl.textContent = String(children.length);
        if (!el) return;
        if (!children.length) {
            el.innerHTML = '<div class="alert alert-info text-center mb-0"><i class="bi bi-info-circle me-2"></i>' +
                'No learners are linked to this staff/guardian profile. Contact the school office to link a dependant.</div>';
            return;
        }
        el.innerHTML = '<div class="list-group list-group-flush">' + children.map((c) => {
            const balance = parseFloat(c.current_balance || 0);
            const bc = balance <= 0 ? 'success' : (balance < 5000 ? 'warning' : 'danger');
            const balTxt = balance <= 0 ? 'Fees Cleared' : 'KES ' + balance.toLocaleString() + ' due';
            const active = String(c.id) === String(this.state.selectedStudentId);
            return '<div class="list-group-item list-group-item-action border-0 border-bottom px-3 py-3 ' + (active ? 'active' : '') + ' mf-child-card" data-mf-child="' + c.id + '">' +
                '<div class="d-flex align-items-center">' +
                '<div class="rounded-circle bg-' + bc + '-subtle text-' + bc + ' d-flex align-items-center justify-content-center me-3" style="width:44px;height:44px;font-weight:700;font-size:1.15rem;flex:0 0 44px">' +
                this.esc((c.first_name || '?')[0].toUpperCase()) + '</div>' +
                '<div class="min-w-0 flex-grow-1"><div class="fw-bold text-truncate">' + this.esc(c.first_name + ' ' + c.last_name) + '</div>' +
                '<small class="text-muted text-truncate d-block">' + this.esc(c.class_name || 'Unassigned') + (c.admission_no ? ' · ' + this.esc(c.admission_no) : '') + '</small></div>' +
                '<span class="badge bg-' + bc + ' ms-2">' + balTxt + '</span>' +
                '</div>' +
                (c.last_payment_date ? '<small class="text-muted d-block mt-1 ps-5">Last payment: ' + this.esc(c.last_payment_date.substring(0, 10)) + '</small>' : '') +
                '</div>';
        }).join('') + '</div>';
        // Bind child selection.
        el.querySelectorAll('.mf-child-card').forEach((card) => {
            card.addEventListener('click', () => {
                const id = card.dataset.mfChild;
                const child = this.state.children.find((c) => String(c.id) === String(id));
                if (child) this.openStudent(child.id, child.first_name + ' ' + child.last_name, child.class_name || '');
            });
        });
    },

    openStudent(studentId, studentName, className) {
        this.state.selectedStudentId = studentId;
        this.setText('mfDetailTitle', studentName + (className ? ' — ' + className : ''));
        const switcher = document.getElementById('mfChildSwitcher');
        if (switcher) {
            switcher.innerHTML = this.state.children.map((c) =>
                '<option value="' + c.id + '">' + this.esc(c.first_name + ' ' + c.last_name) + '</option>').join('');
            switcher.value = String(studentId);
            switcher.classList.remove('d-none');
        }
        // Re-render list to highlight the active child.
        this.renderChildren(this.state.children);
        this.activateStudentTab('overview');
    },

    activateStudentTab(tab) {
        this.state.activeDetailTab = tab;
        this.loadStudentTab(tab);
    },

    loadStudentTab(tab) {
        const id = this.state.selectedStudentId;
        const content = document.getElementById('mfDetail');
        if (!content || !id) return;
        this.setStateVal(content, '<div class="text-center py-5"><div class="spinner-border text-success" role="status"></div></div>');
        const nav = this.renderDetailTabs(tab);
        if (tab === 'overview') { this.renderOverview(); return; }
        const self = this;
        if (tab === 'fees') {
            this.apiFetch('/student-fees/' + id, 'GET')
                .then((resp) => this.renderSection(nav + this.renderFeeHistory(resp.data || resp)))
                .catch((e) => this.renderSectionErr(nav, 'fees', e));
        } else if (tab === 'payments') {
            this.apiFetch('/student-payment-history/' + id, 'GET')
                .then((resp) => this.renderSection(nav + this.renderPaymentHistory(resp.data || resp)))
                .catch((e) => this.renderSectionErr(nav, 'payments', e));
        } else if (tab === 'attendance') {
            this.apiFetch('/student-attendance/' + id, 'GET')
                .then((resp) => this.renderSection(nav + this.renderAttendance(resp.data || resp)))
                .catch((e) => this.renderSectionErr(nav, 'attendance', e));
        } else if (tab === 'performance') {
            this.apiFetch('/student-performance/' + id, 'GET')
                .then((resp) => this.renderSection(nav + this.renderPerformance(resp.data || resp)))
                .catch((e) => this.renderSectionErr(nav, 'performance', e));
        } else if (tab === 'report-card') {
            this.apiFetch('/student-report-card/' + id, 'GET')
                .then((resp) => this.renderSection(nav + this.renderReportCard(resp.data || resp)))
                .catch((e) => this.renderSectionErr(nav, 'report card', e));
        } else if (tab === 'messages') {
            this.apiFetch('/messages/' + id, 'GET')
                .then((resp) => this.renderSection(nav + this.renderMessages(resp.data || resp)))
                .catch((e) => this.renderSectionErr(nav, 'messages', e));
        } else if (tab === 'portfolio') {
            this.apiFetch('/portfolio/' + id, 'GET')
                .then((resp) => this.renderSection(nav + this.renderPortfolio(resp.data || resp)))
                .catch((e) => this.renderSectionErr(nav, 'portfolio', e));
        } else if (tab === 'statement') {
            this.renderSection(nav + this.renderStatement());
        }
    },

    renderDetailTabs(active) {
        const tabs = [
            ['overview', 'Overview', 'bi-grid'],
            ['fees', 'Fees', 'bi-receipt'],
            ['payments', 'Payments', 'bi-credit-card'],
            ['attendance', 'Attendance', 'bi-calendar-check'],
            ['performance', 'Learning', 'bi-graph-up'],
            ['report-card', 'Report Card', 'bi-award'],
            ['messages', 'Messages', 'bi-chat-dots'],
            ['portfolio', 'Portfolio', 'bi-folder2-open'],
            ['statement', 'Statement', 'bi-file-earmark-text'],
        ];
        return '<ul class="nav nav-tabs mb-3 flex-wrap" role="tablist">' +
            tabs.map((t) => '<li class="nav-item" role="presentation">' +
                '<button class="nav-link ' + (t[0] === active ? 'active' : '') + '" type="button" data-mf-tab="' + t[0] + '" role="tab">' +
                '<i class="bi ' + t[2] + ' me-1"></i>' + t[1] + '</button></li>').join('') +
            '</ul>';
    },

    // ---- Overview ----
    async renderOverview() {
        const id = this.state.selectedStudentId;
        const content = document.getElementById('mfDetail');
        if (!id || !content) return;
        const nav = this.renderDetailTabs('overview');
        try {
            const [fees, att, perf, msgs] = await Promise.all([
                this.apiFetch('/student-fees/' + id, 'GET').catch(() => null),
                this.apiFetch('/student-attendance/' + id, 'GET').catch(() => null),
                this.apiFetch('/student-performance/' + id, 'GET').catch(() => null),
                this.apiFetch('/messages/' + id, 'GET').catch(() => null),
            ]);
            const feeData = (fees && fees.data) || {};
            const attData = (att && att.data) || {};
            const perfData = (perf && perf.data) || {};
            const msgsData = (msgs && msgs.data) || [];
            // Fee balance summary
            let balance = 0;
            try { const b = await this.apiFetch('/fee-balance/' + id, 'GET'); if (b && b.data) balance = parseFloat(b.data.total_balance || 0); } catch (_) {}
            const attPct = attData.percentage || 0;
            const scores = (perfData.scores || []).length;
            const msgsCount = (Array.isArray(msgsData) ? msgsData : []).length;
            const html =
                '<div class="row g-3 mb-3">' +
                this.ovrCard('wallet2', 'Fee balance', balance <= 0 ? 'KES 0' : 'KES ' + balance.toLocaleString(), balance <= 0 ? 'success' : 'danger') +
                this.ovrCard('calendar-check', 'Attendance', String(attPct || '—') + '%', attPct >= 90 ? 'success' : (attPct >= 75 ? 'warning' : 'danger')) +
                this.ovrCard('graph-up', 'Scores recorded', String(scores), 'primary') +
                this.ovrCard('chat-dots', 'Messages', String(msgsCount), 'info') +
                '</div>' +
                (this.state.children.length ? '<div class="alert alert-light border small mb-0"><i class="bi bi-info-circle me-2 text-success"></i>' +
                    'Use the tabs above to review this child’s full school journey, or select a different linked person on the left.</div>' : '');
            content.innerHTML = nav + html;
        } catch (e) {
            content.innerHTML = nav + '<div class="alert alert-danger">Unable to load overview: ' + this.apiErrorMsg(e) + '</div>';
        }
    },

    ovrCard(icon, label, value, color) {
        const dsc = { success: 'dsc-green', danger: 'dsc-red', primary: 'dsc-blue', info: 'dsc-cyan', warning: 'dsc-amber' };
        return '<div class="col-md-3 col-sm-6"><div class="dash-stat ' + (dsc[color] || 'dsc-slate') + ' h-100"><div class="dash-stat-icon"><i class="bi bi-' + icon + '"></i></div>' +
            '<div class="dash-stat-value">' + value + '</div><div class="dash-stat-label">' + label + '</div></div></div>';
    },

    // ---- Fees ----
    renderFeeHistory(data) {
        const years = data.academic_years || data || [];
        if (!years.length) return '<div class="alert alert-info">No fee history found.</div>';
        const sid = this.state.selectedStudentId;
        return years.map((yr) =>
            '<div class="card mb-3 border-0 shadow-sm"><div class="card-header bg-success text-white fw-bold">Academic Year ' + yr.year + '</div><div class="card-body">' +
            (yr.terms || []).map((term) => {
                const rows = (term.obligations || []).map((o) => {
                    const sc = o.payment_status === 'paid' ? 'success' : (o.payment_status === 'partial' ? 'warning' : 'danger');
                    return '<tr><td>' + this.esc(o.fee_type_name || '') + '</td>' +
                        '<td>KES ' + Number(o.amount_due || 0).toLocaleString() + '</td>' +
                        '<td>KES ' + Number(o.amount_paid || 0).toLocaleString() + '</td>' +
                        '<td><strong>KES ' + Number(o.balance || 0).toLocaleString() + '</strong></td>' +
                        '<td><span class="badge bg-' + sc + '">' + this.esc(o.payment_status || 'pending') + '</span></td></tr>';
                }).join('');
                const payBtn = term.balance > 0
                    ? '<button class="btn btn-sm btn-success pay-now-btn ms-2" data-amount="' + term.balance + '"><i class="bi bi-phone me-1"></i>Pay Now</button>'
                    : '';
                return '<h6 class="text-muted mb-2">' + this.esc(term.term_name || '') + '</h6>' +
                    '<table class="table table-sm table-bordered mb-3"><thead class="table-light"><tr><th>Fee Type</th><th>Billed</th><th>Paid</th><th>Balance</th><th>Status</th></tr></thead><tbody>' + rows + '</tbody>' +
                    '<tfoot class="fw-bold table-light"><tr><td>Total</td><td>KES ' + Number(term.total_due || 0).toLocaleString() + '</td><td>KES ' + Number(term.total_paid || 0).toLocaleString() + '</td><td>KES ' + Number(term.balance || 0).toLocaleString() + '</td>' +
                    '<td>' + payBtn + '</td></tr></tfoot></table>';
            }).join('') +
            '</div></div>').join('');
    },

    renderPaymentHistory(payments) {
        if (!payments || !payments.length) return '<div class="alert alert-info">No payment records found.</div>';
        const rows = payments.map((p) =>
            '<tr><td>' + (p.payment_date || '').substring(0, 10) + '</td>' +
            '<td><span class="badge bg-secondary">' + this.esc(p.payment_method || '') + '</span></td>' +
            '<td>KES ' + Number(p.amount_paid || 0).toLocaleString() + '</td>' +
            '<td>' + this.esc(p.receipt_no || '') + '</td>' +
            '<td>' + this.esc(p.reference_no || '') + '</td>' +
            '<td>' + this.esc(p.term_name || '') + '</td></tr>').join('');
        return '<div class="table-responsive"><table class="table table-hover"><thead class="table-light"><tr>' +
            '<th>Date</th><th>Method</th><th>Amount</th><th>Receipt #</th><th>Reference</th><th>Term</th>' +
            '</tr></thead><tbody>' + rows + '</tbody></table></div>';
    },

    renderStatement() {
        return '<div class="text-center py-3"><p class="text-muted">Generate a printable fee statement for this learner.</p>' +
            '<button class="btn btn-success" id="btnGenStmt"><i class="bi bi-file-earmark-text me-2"></i>Generate Statement</button></div>';
    },

    renderAttendance(data) {
        const summary = data.summary || {};
        const recent = data.recent || [];
        const monthly = data.monthly || [];
        const pct = data.percentage || 0;
        const total = parseInt(summary.total_days || 0);
        const present = parseInt(summary.days_present || 0);
        const absent = parseInt(summary.days_absent || 0);
        const late = parseInt(summary.days_late || 0);
        const pctClass = pct >= 90 ? 'success' : (pct >= 75 ? 'warning' : 'danger');
        let html = '<div class="row g-3 mb-4">' +
            this.ovrCard('percent', 'Attendance', String(pct) + '%', pctClass) +
            this.ovrCard('check-circle', 'Present', String(present), 'success') +
            this.ovrCard('x-circle', 'Absent', String(absent), 'danger') +
            this.ovrCard('clock', 'Late', String(late), 'warning') +
            '</div>';
        if (monthly.length) {
            html += '<h6 class="text-muted mb-2">Monthly breakdown</h6><div class="table-responsive mb-4"><table class="table table-sm table-bordered"><thead class="table-light"><tr><th>Month</th><th>Total</th><th>Present</th><th>Absent</th><th>Late</th><th>%</th></tr></thead><tbody>';
            monthly.forEach((m) => {
                const mpct = m.total_days > 0 ? Math.round(100 * m.days_present / m.total_days) : 0;
                html += '<tr><td>' + m.month + '</td><td>' + m.total_days + '</td><td>' + m.days_present + '</td><td>' + m.days_absent + '</td><td>' + m.days_late + '</td><td><span class="badge bg-' + (mpct >= 90 ? 'success' : (mpct >= 75 ? 'warning' : 'danger')) + '">' + mpct + '%</span></td></tr>';
            });
            html += '</tbody></table></div>';
        }
        if (recent.length) {
            html += '<h6 class="text-muted mb-2">Recent activity</h6><div class="table-responsive"><table class="table table-sm table-bordered"><thead class="table-light"><tr><th>Date</th><th>Status</th><th>Reason</th></tr></thead><tbody>';
            recent.forEach((r) => {
                const sc = r.status === 'present' ? 'success' : (r.status === 'late' ? 'warning' : 'danger');
                html += '<tr><td>' + (r.date || '').substring(0, 10) + '</td><td><span class="badge bg-' + sc + '">' + this.esc(r.status || '') + '</span></td><td>' + this.esc(r.absence_reason || '-') + '</td></tr>';
            });
            html += '</tbody></table></div>';
        }
        if (!total) html = '<div class="alert alert-info">No attendance records found for the current term.</div>';
        return html;
    },

    renderPerformance(data) {
        const student = data.student || {};
        const term = data.term || {};
        const scores = data.scores || [];
        const competencies = data.competencies || [];
        const values = data.values || [];
        const attendance = data.attendance || {};
        const attPct = attendance.total_days > 0 ? Math.round(100 * attendance.days_present / attendance.total_days) : 0;
        let html = '<div class="mb-3"><h5 class="fw-bold">' + this.esc(student.first_name + ' ' + student.last_name) + '</h5>' +
            '<small class="text-muted">' + this.esc(student.class_name || '') + ' · ' + this.esc(term.name || 'Current Term') + '</small></div>';
        if (scores.length) {
            html += '<h6 class="text-muted mb-2">Subject scores</h6><div class="table-responsive mb-4"><table class="table table-sm table-bordered"><thead class="table-light"><tr><th>Subject</th><th>Score</th><th>Grade</th></tr></thead><tbody>';
            scores.forEach((s) => {
                const score = parseFloat(s.score || s.total_score || 0);
                const grade = s.grade || s.letter_grade || GradingScale.grade(score);
                html += '<tr><td>' + this.esc(s.subject_name || '') + '</td><td>' + score + '</td><td><span class="badge bg-primary">' + this.esc(grade) + '</span></td></tr>';
            });
            html += '</tbody></table></div>';
        }
        if (competencies.length) {
            html += '<h6 class="text-muted mb-2">Core competencies</h6><div class="table-responsive mb-4"><table class="table table-sm table-bordered"><thead class="table-light"><tr><th>Competency</th><th>Level</th><th>Notes</th></tr></thead><tbody>';
            competencies.forEach((c) => {
                html += '<tr><td><strong>' + this.esc(c.code || '') + '</strong> ' + this.esc(c.competency_name || '') + '</td>' +
                    '<td><span class="badge bg-info">' + this.esc(c.level_name || c.level_code || '-') + '</span></td>' +
                    '<td><small>' + this.esc(c.notes || '') + '</small></td></tr>';
            });
            html += '</tbody></table></div>';
        }
        if (values.length) {
            html += '<h6 class="text-muted mb-2">Core values</h6><div class="table-responsive mb-4"><table class="table table-sm table-bordered"><thead class="table-light"><tr><th>Value</th><th>Rating</th></tr></thead><tbody>';
            values.forEach((v) => html += '<tr><td>' + this.esc(v.value_name || '') + '</td><td>' + this.esc(v.rating || '-') + '</td></tr>');
            html += '</tbody></table></div>';
        }
        if (attendance.total_days) {
            html += '<h6 class="text-muted mb-2">Term attendance</h6><div class="row g-2 mb-3">' +
                '<div class="col-auto"><span class="badge bg-success fs-6">' + attPct + '%</span></div>' +
                '<div class="col-auto text-muted small lh-lg">' + attendance.days_present + '/' + attendance.total_days + ' days present</div></div>';
        }
        if (!scores.length && !competencies.length) html = '<div class="alert alert-info">No performance data available for the current term.</div>';
        return html;
    },

    renderReportCard(data) {
        if (!data || data.released === false) {
            return '<div class="alert alert-info"><i class="bi bi-lock me-2"></i>' +
                this.esc((data && data.message) || 'The school has not released a report card for this learner yet.') + '</div>';
        }
        const s = data.student || {};
        const term = data.term || {};
        const scores = data.scores || [];
        const comps = data.competencies || [];
        const vals = data.values || [];
        const att = data.attendance || {};
        const enr = data.enrollment || {};
        const school = data.school || {};
        const official = data.official_release || {};
        const attPct = att.total_days > 0 ? Math.round(100 * att.days_present / att.total_days) : 0;
        let html = '<div class="p-3 border rounded-3 bg-white">' +
            '<div class="alert alert-success py-2"><i class="bi bi-patch-check-fill me-2"></i>Official school release' +
            (official.version_no ? ' · Version ' + this.esc(official.version_no) : '') +
            (official.released_at ? ' · ' + this.esc(official.released_at) : '') + '</div>' +
            '<div class="text-center mb-3 border-bottom pb-3">' +
            '<h4 class="fw-bold mb-0">' + this.esc(school.name || 'Kingsway Preparatory School') + '</h4>' +
            '<small class="text-muted">' + this.esc(school.address || '') + ' | ' + this.esc(school.phone || '') + '</small>' +
            '<h5 class="mt-2">School Report Card</h5>' +
            '<small class="text-muted">' + this.esc(term.name || '') + ' · ' + this.esc(data.year || term.year_code || '') + '</small></div>' +
            '<div class="row mb-3 small"><div class="col-6"><strong>Name:</strong> ' + this.esc(s.first_name + ' ' + s.last_name) + '</div>' +
            '<div class="col-3"><strong>Adm:</strong> ' + this.esc(s.admission_no || '') + '</div>' +
            '<div class="col-3"><strong>Class:</strong> ' + this.esc(s.class_name || '') + ' ' + this.esc(s.stream_name || '') + '</div></div>';
        if (scores.length) {
            html += '<h6 class="text-muted border-bottom pb-1">Academic performance</h6>' +
                '<div class="table-responsive mb-3"><table class="table table-sm table-bordered"><thead class="table-light"><tr><th>Subject</th><th>Formative</th><th>Summative</th><th>Overall</th><th>Grade</th></tr></thead><tbody>';
            scores.forEach((sc) => {
                const fmt = parseFloat(sc.formative_percentage || sc.formative_score || 0);
                const sum = parseFloat(sc.summative_percentage || sc.summative_score || 0);
                const ovr = parseFloat(sc.overall_percentage || sc.total_score || sc.score || 0);
                const grd = sc.overall_grade || sc.grade || sc.cbc_grade || GradingScale.grade(ovr);
                html += '<tr><td>' + this.esc(sc.subject_name || '') + '</td><td>' + fmt + '</td><td>' + sum + '</td><td><strong>' + ovr + '</strong></td><td><span class="badge bg-primary">' + this.esc(grd) + '</span></td></tr>';
            });
            html += '</tbody></table></div>';
        }
        if (comps.length) {
            html += '<h6 class="text-muted border-bottom pb-1">Core competencies</h6>' +
                '<div class="table-responsive mb-3"><table class="table table-sm table-bordered"><thead class="table-light"><tr><th>Competency</th><th>Level</th><th>Notes</th></tr></thead><tbody>';
            comps.forEach((c) => html += '<tr><td><strong>' + this.esc(c.code || '') + '</strong> ' + this.esc(c.competency_name || '') + '</td>' +
                '<td><span class="badge bg-info">' + this.esc(c.level_name || c.level_code || '-') + '</span></td>' +
                '<td><small>' + this.esc(c.notes || '') + '</small></td></tr>');
            html += '</tbody></table></div>';
        }
        if (vals.length) {
            html += '<h6 class="text-muted border-bottom pb-1">Core values</h6>' +
                '<div class="table-responsive mb-3"><table class="table table-sm table-bordered"><thead class="table-light"><tr><th>Value</th><th>Rating</th></tr></thead><tbody>';
            vals.forEach((v) => html += '<tr><td>' + this.esc(v.value_name || '') + '</td><td>' + this.esc(v.rating || '-') + '</td></tr>');
            html += '</tbody></table></div>';
        }
        if (att.total_days) {
            html += '<h6 class="text-muted border-bottom pb-1">Attendance</h6>' +
                '<div class="row g-2 mb-3"><div class="col-auto"><span class="badge bg-success fs-6">' + attPct + '%</span></div>' +
                '<div class="col-auto text-muted small lh-lg">' + (att.days_present || 0) + '/' + (att.total_days || 0) + ' days present | ' +
                (att.days_absent || 0) + ' absent | ' + (att.days_late || 0) + ' late</div></div>';
        }
        if (enr.teacher_comments) {
            html += '<div class="card bg-light mb-2"><div class="card-body py-2"><small class="text-muted">Class Teacher</small>' +
                '<p class="mb-0 fst-italic">" ' + this.esc(enr.teacher_comments) + ' "</p>' +
                (enr.class_teacher_name ? '<small class="text-muted">— ' + this.esc(enr.class_teacher_name) + '</small>' : '') + '</div></div>';
        }
        if (enr.head_teacher_comments) {
            html += '<div class="card bg-light mb-2"><div class="card-body py-2"><small class="text-muted">Head Teacher</small>' +
                '<p class="mb-0 fst-italic">" ' + this.esc(enr.head_teacher_comments) + ' "</p></div></div>';
        }
        html += '<div class="text-center mt-3"><button class="btn btn-success btn-sm" data-mf-action="print-report"' +
            (official.download_url ? ' data-download-url="' + this.esc(official.download_url) + '"' : ' disabled') + '><i class="bi bi-file-earmark-pdf me-1"></i>Open official PDF</button></div></div>';
        return html;
    },

    renderMessages(data) {
        const msgs = data || [];
        let html = '<div class="d-flex justify-content-between align-items-center mb-3">' +
            '<h6 class="mb-0 text-muted">Messages with school</h6>' +
            '<button class="btn btn-success btn-sm" id="btnComposeMessage"><i class="bi bi-pencil me-1"></i>Compose</button></div>';
        if (!msgs.length) html += '<div class="alert alert-info">No messages yet. Click "Compose" to send a message to the school.</div>';
        else {
            html += '<div class="list-group mb-3">';
            msgs.forEach((m) => {
                const isParent = m.sender_type === 'parent';
                html += '<div class="list-group-item list-group-item-action ' + (isParent ? '' : 'bg-light') + '">' +
                    '<div class="d-flex justify-content-between"><small class="fw-bold">' + this.esc(m.sender_name || (isParent ? 'You' : 'School')) + '</small>' +
                    '<small class="text-muted">' + (m.created_at || '').substring(0, 16) + '</small></div>' +
                    '<strong class="d-block small">' + this.esc(m.subject || '') + '</strong>' +
                    '<p class="mb-0 small">' + this.esc(m.message || '') + '</p></div>';
            });
            html += '</div>';
        }
        html += '<div id="composeForm" style="display:none" class="card border-0 shadow-sm p-3">' +
            '<h6 class="text-muted mb-3">Send message to school</h6>' +
            '<div class="mb-2"><input type="text" id="msgSubject" class="form-control form-control-sm" placeholder="Subject"></div>' +
            '<div class="mb-2"><textarea id="msgBody" class="form-control" rows="3" placeholder="Your message..."></textarea></div>' +
            '<div id="msgError" class="alert alert-danger d-none"></div>' +
            '<div><button class="btn btn-success btn-sm" id="btnSendMessage"><i class="bi bi-send me-1"></i>Send</button>' +
            '<button class="btn btn-outline-secondary btn-sm ms-2" id="btnCancelMessage">Cancel</button></div></div>';
        return html;
    },

    sendMessage() {
        const subject = (document.getElementById('msgSubject') || {}).value?.trim?.() || '';
        const message = (document.getElementById('msgBody') || {}).value?.trim?.() || '';
        const errEl = document.getElementById('msgError');
        const self = this;
        if (errEl) errEl.classList.add('d-none');
        if (!subject || !message) { if (errEl) { errEl.textContent = 'Subject and message are required'; errEl.classList.remove('d-none'); } return; }
        this.apiFetch('/send-message', 'POST', { student_id: this.state.selectedStudentId, subject, message })
            .then(() => {
                if (document.getElementById('msgSubject')) document.getElementById('msgSubject').value = '';
                if (document.getElementById('msgBody')) document.getElementById('msgBody').value = '';
                this.setDisplay('composeForm', false);
                this.loadStudentTab('messages');
                window.showNotification?.('Message sent to the school.', 'success');
            })
            .catch((err) => { if (errEl) { errEl.textContent = err.message || 'Failed to send message'; errEl.classList.remove('d-none'); } });
    },

    renderPortfolio(data) {
        const portfolio = data.portfolio || {};
        const artifacts = data.artifacts || [];
        if (!portfolio) return '<div class="alert alert-info">No portfolio found for this learner. Portfolios are created by the teacher.</div>';
        let html = '<div class="d-flex justify-content-between align-items-center mb-2 flex-wrap gap-2">' +
            '<h6 class="mb-0 text-muted">' + this.esc(portfolio.title || 'Portfolio') + '</h6>' +
            '<span class="badge bg-secondary">' + (portfolio.portfolio_type || 'digital') + '</span></div>';
        if (portfolio.description) html += '<p class="small text-muted mb-3">' + this.esc(portfolio.description) + '</p>';
        if (portfolio.theme) html += '<p class="small"><strong>Theme:</strong> ' + this.esc(portfolio.theme) + '</p>';
        if (!artifacts.length) html += '<div class="alert alert-info">No artifacts have been added yet.</div>';
        else {
            html += '<div class="row g-2">';
            artifacts.forEach((a) => {
                const typeIcon = a.artifact_type === 'photo' || a.artifact_type === 'video' ? 'bi-file-image' :
                    a.artifact_type === 'document' ? 'bi-file-text' :
                    a.artifact_type === 'project' ? 'bi-diagram-3' : 'bi-file';
                html += '<div class="col-12"><div class="card border-0 shadow-sm"><div class="card-body py-2">' +
                    '<div class="d-flex justify-content-between align-items-start">' +
                    '<div><i class="bi ' + typeIcon + ' me-2 text-primary"></i><strong>' + this.esc(a.artifact_title || '') + '</strong>' +
                    ' <span class="badge bg-light text-muted">' + this.esc(a.artifact_type || '') + '</span></div>' +
                    '<small class="text-muted">' + (a.upload_date || '').substring(0, 10) + '</small></div>';
                if (a.description) html += '<p class="small mb-1 mt-1">' + this.esc(a.description) + '</p>';
                if (a.competency_name) html += '<small class="text-muted d-block"><strong>C:</strong> ' + this.esc(a.competency_name) + '</small>';
                if (a.value_name) html += '<small class="text-muted d-block"><strong>V:</strong> ' + this.esc(a.value_name) + '</small>';
                if (a.rating) html += '<small class="text-muted d-block"><strong>Rating:</strong> ' + a.rating + '/5</small>';
                if (a.learner_reflection) html += '<div class="bg-light rounded p-2 mt-1"><small class="text-muted"><em>Student:</em> ' + this.esc(a.learner_reflection) + '</small></div>';
                if (a.teacher_feedback) html += '<div class="bg-info bg-opacity-10 rounded p-2 mt-1"><small class="text-primary"><em>Teacher:</em> ' + this.esc(a.teacher_feedback) + '</small></div>';
                if (a.file_path) html += '<a href="' + this.esc(a.file_path) + '" target="_blank" rel="noopener" class="btn btn-outline-primary btn-sm mt-1"><i class="bi bi-eye me-1"></i>View</a>';
                html += '</div></div></div>';
            });
            html += '</div>';
        }
        return html;
    },

    renderSection(html) {
        const content = document.getElementById('mfDetail');
        if (!content) return;
        content.innerHTML = html;
        // Wire buttons rendered inside the section.
        const genBtn = document.getElementById('btnGenStmt');
        if (genBtn) genBtn.addEventListener('click', () => this.generateStatement());
        const compose = document.getElementById('btnComposeMessage');
        if (compose) {
            compose.addEventListener('click', () => this.setDisplay('composeForm', true));
            const cancel = document.getElementById('btnCancelMessage');
            if (cancel) cancel.addEventListener('click', () => { this.setDisplay('composeForm', false); const e = document.getElementById('msgError'); if (e) e.classList.add('d-none'); });
            const send = document.getElementById('btnSendMessage');
            if (send) send.addEventListener('click', () => this.sendMessage());
        }
    },

    renderSectionErr(nav, label, err) {
        const content = document.getElementById('mfDetail');
        if (content) content.innerHTML = nav + '<div class="alert alert-danger">Failed to load ' + label + ': ' + this.apiErrorMsg(err) + '</div>';
    },

    generateStatement() {
        if (!window.PrintManager) { window.showNotification?.('Print service not available.', 'warning'); return; }
        const id = this.state.selectedStudentId;
        const btn = document.getElementById('btnGenStmt');
        if (btn) { btn.disabled = true; btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Loading...'; }
        this.apiFetch('/student-statement/' + id, 'GET')
            .then((resp) => PrintManager.printHtml({ html: this.buildStatementHTML(resp.data || resp), title: 'Fee Statement' }))
            .catch((e) => window.showNotification?.(this.apiErrorMsg(e), 'danger'))
            .finally(() => { if (btn) { btn.disabled = false; btn.innerHTML = '<i class="bi bi-file-earmark-text me-2"></i>Generate Statement'; } });
    },

    buildStatementHTML(data) {
        const s = data.student || {};
        const fees = (data.fees && data.fees.academic_years) || [];
        const pmts = data.payments || [];
        const feeRows = fees.map((yr) =>
            '<h5>Academic Year ' + yr.year + '</h5>' +
            (yr.terms || []).map((t) =>
                '<p><strong>' + this.esc(t.term_name || '') + '</strong></p>' +
                '<table border="1" cellpadding="4" style="border-collapse:collapse;width:100%"><thead><tr><th>Fee Type</th><th>Amount Due</th><th>Paid</th><th>Balance</th></tr></thead><tbody>' +
                (t.obligations || []).map((o) =>
                    '<tr><td>' + this.esc(o.fee_type_name || '') + '</td><td>KES ' + Number(o.amount_due || 0).toLocaleString() + '</td>' +
                    '<td>KES ' + Number(o.amount_paid || 0).toLocaleString() + '</td><td>KES ' + Number(o.balance || 0).toLocaleString() + '</td></tr>').join('') +
                '</tbody></table>').join('')).join('');
        const pmtRows = pmts.map((p) =>
            '<tr><td>' + (p.payment_date || '').substring(0, 10) + '</td><td>' + this.esc(p.payment_method || '') + '</td>' +
            '<td>KES ' + Number(p.amount_paid || 0).toLocaleString() + '</td><td>' + this.esc(p.receipt_no || '') + '</td></tr>').join('');
        return '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Fee Statement</title>' +
            '<link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css" rel="stylesheet">' +
            '</head><body class="p-4"><h3 class="text-center">Kingsway Preparatory School</h3>' +
            '<h5 class="text-center text-muted">Fee Statement</h5><hr>' +
            '<p><strong>Student:</strong> ' + this.esc(s.first_name + ' ' + s.last_name) +
            ' &nbsp; <strong>Adm No:</strong> ' + this.esc(s.admission_no || '') +
            ' &nbsp; <strong>Class:</strong> ' + this.esc(s.class_name || '') + '</p>' +
            '<p><strong>Generated:</strong> ' + this.esc(data.generated_at || '') + '</p><hr>' + feeRows +
            (pmtRows ? '<h5 class="mt-4">Payment History</h5><table border="1" cellpadding="4" style="border-collapse:collapse;width:100%"><thead><tr><th>Date</th><th>Method</th><th>Amount</th><th>Receipt #</th></tr></thead><tbody>' + pmtRows + '</tbody></table>' : '') +
            '</body></html>';
    },

    // ---- M-Pesa ----
    openMpesaModal(amount) {
        const modalEl = document.getElementById('mfMpesaModal');
        if (!modalEl) {
            this.injectMpesaModal();
        }
        const m = document.getElementById('mfMpesaModal');
        if (!m || !window.bootstrap) return;
        this.setValue('mfMpesaAmount', amount || '');
        this.setValue('mfMpesaPhone', '');
        this.setDisplay('mfMpesaForm', true);
        this.setDisplay('mfMpesaWaiting', false);
        const err = document.getElementById('mfMpesaError');
        if (err) err.classList.add('d-none');
        bootstrap.Modal.getOrCreateInstance(m).show();
    },

    injectMpesaModal() {
        const host = document.getElementsByTagName('body')[0];
        if (!host || document.getElementById('mfMpesaModal')) return;
        const div = document.createElement('div');
        div.className = 'modal fade';
        div.id = 'mfMpesaModal';
        div.tabIndex = '-1';
        div.setAttribute('aria-hidden', 'true');
        div.innerHTML =
            '<div class="modal-dialog modal-dialog-scrollable modal-dialog-centered"><div class="modal-content border-0 shadow">' +
            '<div class="modal-header bg-success text-white"><h5 class="modal-title"><i class="bi bi-phone me-2"></i>M-Pesa Payment</h5>' +
            '<button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button></div>' +
            '<div class="modal-body p-4">' +
            '<div id="mfMpesaForm">' +
            '<div class="mb-3"><label class="form-label fw-semibold">Amount (KES)</label>' +
            '<input type="number" id="mfMpesaAmount" class="form-control" min="1" step="any"></div>' +
            '<div class="mb-3"><label class="form-label fw-semibold">M-Pesa Phone Number</label>' +
            '<input type="tel" id="mfMpesaPhone" class="form-control" placeholder="2547XXXXXXXX">' +
            '<div class="form-text">Enter the phone number registered with M-Pesa</div></div>' +
            '<div id="mfMpesaError" class="alert alert-danger d-none"></div>' +
            '<button class="btn btn-success w-100 py-2 fw-semibold" id="mfMpesaPay"><i class="bi bi-send me-2"></i>Pay with M-Pesa</button>' +
            '</div>' +
            '<div id="mfMpesaWaiting" style="display:none" class="text-center py-4">' +
            '<div class="spinner-border text-success mb-3" style="width:3rem;height:3rem"></div>' +
            '<h6>STK Push Sent!</h6>' +
            '<p class="text-muted small">Check your phone and enter your M-Pesa PIN to complete the payment.</p>' +
            '<div id="mfMpesaPollingStatus" class="text-muted small">Waiting for confirmation...</div></div>' +
            '</div></div></div>';
        host.appendChild(div);
        const pay = div.querySelector('#mfMpesaPay');
        if (pay) pay.addEventListener('click', () => this.initiateMpesaPayment());
        div.addEventListener('hidden.bs.modal', () => this.resetMpesaModal());
    },

    initiateMpesaPayment() {
        const amount = this.value('mfMpesaAmount');
        const phone = this.value('mfMpesaPhone');
        const errEl = document.getElementById('mfMpesaError');
        const self = this;
        if (errEl) errEl.classList.add('d-none');
        if (!amount || parseFloat(amount) <= 0) { if (errEl) { errEl.textContent = 'Invalid amount'; errEl.classList.remove('d-none'); } return; }
        if (!phone) { if (errEl) { errEl.textContent = 'Phone number is required'; errEl.classList.remove('d-none'); } return; }
        this.apiFetch('/initiate-mpesa-payment', 'POST', {
            student_id: this.state.selectedStudentId,
            amount: parseFloat(amount),
            phone,
            provider: 'daraja',
        }).then((resp) => {
            const d = resp.data || resp;
            if (d.checkout_request_id) {
                this.setDisplay('mfMpesaForm', false);
                this.setDisplay('mfMpesaWaiting', true);
                this.startPolling(d.checkout_request_id);
            } else {
                if (errEl) { errEl.textContent = d.message || 'Failed to initiate payment'; errEl.classList.remove('d-none'); }
            }
        }).catch((err) => { if (errEl) { errEl.textContent = err.message || 'Payment initiation failed'; errEl.classList.remove('d-none'); } });
    },

    startPolling(checkoutRequestId) {
        const self = this;
        let attempts = 0;
        const maxAttempts = 30;
        this.state._mpesaPolling = setInterval(() => {
            attempts++;
            const statusEl = document.getElementById('mfMpesaPollingStatus');
            if (statusEl) statusEl.textContent = 'Checking status... (' + attempts + '/' + maxAttempts + ')';
            if (attempts >= maxAttempts) {
                clearInterval(self.state._mpesaPolling);
                if (statusEl) statusEl.textContent = 'Payment confirmation timed out. Check your M-Pesa messages.';
                return;
            }
            self.checkMpesaStatus(checkoutRequestId, (done) => {
                if (done) {
                    clearInterval(self.state._mpesaPolling);
                    const el = document.getElementById('mfMpesaModal');
                    const modal = el && window.bootstrap ? bootstrap.Modal.getInstance(el) : null;
                    if (modal) modal.hide();
                    window.showNotification?.('Payment confirmed. Refreshing...', 'success');
                    this.loadStudentTab('fees');
                }
            });
        }, 4000);
    },

    checkMpesaStatus(checkoutRequestId, callback) {
        this.apiFetch('/mpesa-status/' + checkoutRequestId, 'GET')
            .then((resp) => {
                const d = resp.data || resp;
                if (d.ResultCode === '0' || d.resultCode === '0' || d.status === 'completed') callback(true);
            }).catch(() => {});
    },

    resetMpesaModal() {
        if (this.state._mpesaPolling) { clearInterval(this.state._mpesaPolling); this.state._mpesaPolling = null; }
        this.setDisplay('mfMpesaForm', true);
        this.setDisplay('mfMpesaWaiting', false);
    },

    // ---- Helpers ----
    emptyDetail() {
        return '<div class="text-center py-5 text-muted"><i class="bi bi-mouse fs-1 d-block mb-2"></i>' +
            'Choose a linked person on the left to view their fees, payments, attendance, learning, report cards, messages and portfolio.</div>';
    },

    setLoading(on) {
        const el = document.getElementById('mfLoading');
        if (el) el.style.display = on ? 'block' : 'none';
    },

    apiErrorMsg(e) {
        return (e && e.message) ? e.message : 'An error occurred.';
    },

    setText(id, txt) { const el = document.getElementById(id); if (el) el.textContent = txt; },
    setStateVal(el, html) { if (el) el.innerHTML = html; },
    setDisplay(id, visible) { const el = document.getElementById(id); if (el) el.style.display = visible ? 'block' : 'none'; },
    setValue(id, val) { const el = document.getElementById(id); if (el) el.value = val; },
    value(id) { const el = document.getElementById(id); return (el && el.value) ? el.value : ''; },
    on(id, ev, fn) {
        const el = document.getElementById(id);
        if (el && ev) el.addEventListener(ev, fn);
    },

    esc(s) {
        return String(s || '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    },
};

// Auto-init once the shell page is ready.
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => MyFamilyController.init().catch(() => {}));
} else {
    MyFamilyController.init().catch(() => {});
}
window.MyFamilyController = MyFamilyController;
