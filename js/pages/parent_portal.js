function obfuscate(str) {
    return btoa(String(str).split('').map(function (c, i) {
        return String.fromCharCode(c.charCodeAt(0) + ((i % 7) + 1));
    }).join(''));
}

function deobfuscate(str) {
    try {
        return atob(String(str)).split('').map(function (c, i) {
            return String.fromCharCode(c.charCodeAt(0) - ((i % 7) + 1));
        }).join('');
    } catch (_) { return null; }
}

const parentPortalController = {
    BASE: window.APP_BASE || '',
    state: {
        token: null,
        otpSessionId: null,
        selectedStudentId: null,
        activeDetailTab: 'fees',
        children: [],
        parent: {},
        initialRouteApplied: false,
    },

    async init() {
        // Only the explicit staff-family mode uses the internal AuthContext.
        // Normal parents authenticate exclusively with the parent portal token.
        if (window.FAMILY_STAFF_MODE && window.AuthContext?.ready) await window.AuthContext.ready();
        this.bindEvents();
        const forceParentLogin = new URLSearchParams(window.location.search).get('login') === '1';
        if (forceParentLogin && !window.FAMILY_STAFF_MODE) this.clearAuth();
        var stored = window.FAMILY_STAFF_MODE
            ? (window.AuthContext?.getToken?.() || null)
            : (forceParentLogin ? null : deobfuscate(sessionStorage.getItem('pp_token')));
        var expires = sessionStorage.getItem('pp_expires');
        if (window.FAMILY_STAFF_MODE && stored && window.AuthContext?.isAuthenticated?.()) {
            this.state.token = stored;
            await this.loadDashboard(true);
        } else if (stored && expires && new Date(expires) > new Date()) {
            this.state.token = stored;
            // A timestamp in sessionStorage is not proof of authentication.
            // Keep the login view visible until the backend validates the token.
            await this.loadDashboard(true);
        } else {
            this.clearAuth();
            this.showView('auth');
        }
    },

    showView(name) {
        document.querySelectorAll('.view').forEach(function (v) { v.classList.remove('active'); });
        var el = document.getElementById('view-' + name);
        if (el) el.classList.add('active');
        document.body.classList.toggle('portal-authenticated', name === 'dashboard' || name === 'student');
    },

    preloadGradingScale() {
        var self = this;
        if (!window.GradingScale?.preload) return;
        GradingScale.preload(function () {
            return self.apiFetch('/grading-scale', 'GET').then(function (res) {
                var data = res && res.data !== undefined ? res.data : res;
                return { scale: data && data.scale ? data.scale : null, rules: data && data.rules ? data.rules : [] };
            });
        });
    },

    apiFetch(path, method, body) {
        // Thin pass-through: the unified api.js transport owns token attach,
        // parent 401 redirects and CSRF. Parent sessions never trigger the
        // staff refresh flow (no staff refresh cookie exists for them).
        var opts = { noRedirect: true, skipAuthRefresh: !window.FAMILY_STAFF_MODE };
        var base = window.FAMILY_STAFF_MODE ? '/family' : '/parent-portal';
        return Promise.resolve(apiCall(base + path, method || 'GET', body, null, opts))
            .then(function (data) { return data; });
    },

    bindEvents() {
        var self = this;
        document.querySelectorAll('#loginTabs .nav-link').forEach(function (btn) {
            btn.addEventListener('click', function () {
                document.querySelectorAll('#loginTabs .nav-link').forEach(function (b) { b.classList.remove('active'); });
                btn.classList.add('active');
                self.setView('tab-email', btn.dataset.tab === 'email');
                self.setView('tab-otp', btn.dataset.tab === 'otp');
            });
        });
        this.on('togglePwd', 'click', function () {
            var pwd = document.getElementById('loginPassword');
            var icon = document.querySelector('#togglePwd i');
            if (pwd.type === 'password') { pwd.type = 'text'; icon.className = 'bi bi-eye-slash'; }
            else { pwd.type = 'password'; icon.className = 'bi bi-eye'; }
        });
        this.on('btnEmailLogin', 'click', function () { self.submitEmailLogin(); });
        this.on('loginPassword', 'keydown', function (e) { if (e.key === 'Enter') self.submitEmailLogin(); });
        this.on('btnRequestOtp', 'click', function () { self.requestOTP(); });
        this.on('btnVerifyOtp', 'click', function () { self.verifyOTP(); });
        this.on('btnResendOtp', 'click', function () {
            self.state.otpSessionId = null;
            document.getElementById('loginPassword').value = '';
            self.setView('tab-otp', false);
            self.setView('tab-email', true);
            document.getElementById('loginPassword')?.focus();
        });
        this.on('btnLogout', 'click', function () { self.logout(); });
        var backToDashboard = document.getElementById('btnBackToDashboard');
        if (backToDashboard && !backToDashboard.getAttribute('href')) {
            backToDashboard.addEventListener('click', function () { self.showView('dashboard'); });
        }
        this.on('btnApplyAdmission', 'click', function () { self.openApplyAdmissionModal(); });
        this.on('parent-ai-assistant-form', 'submit', function (e) { e.preventDefault(); self.askAiAssistant(); });
        this.on('portalMenuToggle', 'click', function () { document.getElementById('portalSidebar')?.classList.toggle('open'); });
        this.on('childSwitcher', 'change', function () { var child = self.state.children.find(function (c) { return String(c.id) === String(document.getElementById('childSwitcher').value); }); if (child) self.openStudent(child.id, child.first_name + ' ' + child.last_name, child.class_name || '', self.state.activeDetailTab); });
        document.querySelectorAll('[data-portal-section]').forEach(function (link) {
            if (!link.getAttribute('href')) {
                link.addEventListener('click', function () { self.navigateSection(link.dataset.portalSection); });
            }
        });
        document.querySelectorAll('#view-student [data-tab]').forEach(function (link) {
            link.addEventListener('click', function () { self.activateStudentTab(link.dataset.tab); });
        });
        document.querySelectorAll('#studentDetailTabs .nav-link').forEach(function (btn) {
            btn.addEventListener('click', function () {
                document.querySelectorAll('#studentDetailTabs .nav-link').forEach(function (b) { b.classList.remove('active'); });
                btn.classList.add('active');
                self.state.activeDetailTab = btn.dataset.tab;
                self.loadStudentTab(btn.dataset.tab);
            });
        });
        var planningTabs = document.getElementById('studentDetailTabs');
        if (planningTabs && !planningTabs.querySelector('[data-tab="learning-plan"]')) {
            var planningLi = document.createElement('li');
            planningLi.className = 'nav-item';
            planningLi.innerHTML = '<button class="nav-link" data-tab="learning-plan"><i class="bi bi-journal-text me-1"></i>Term learning plan</button>';
            planningTabs.appendChild(planningLi);
            planningLi.querySelector('button').addEventListener('click', function () {
                planningTabs.querySelectorAll('.nav-link').forEach(function (b) { b.classList.remove('active'); });
                this.classList.add('active');
                self.state.activeDetailTab = 'learning-plan';
                self.loadStudentTab('learning-plan');
            });
        }
        // Pay Now button delegation (rendered dynamically in fee history)
        document.getElementById('studentDetailContent').addEventListener('click', function (e) {
            var btn = e.target.closest('.pay-now-btn');
            if (btn) {
                var amount = parseFloat(btn.dataset.amount || 0);
                self.state.pendingAmount = amount;
                self.openMpesaModal(amount);
            }
        });
        this.on('btnMpesaPay', 'click', function () { self.initiateMpesaPayment(); });
        this.on('btnMpesaDone', 'click', function () { self.resetMpesaModal(); });
        // Reset modal on hide
        var mpesaModal = document.getElementById('mpesaPaymentModal');
        if (mpesaModal) {
            mpesaModal.addEventListener('hidden.bs.modal', function () { self.resetMpesaModal(); });
        }
    },

    askAiAssistant() {
        var self = this, input = document.getElementById('parent-ai-question'), answer = document.getElementById('parent-ai-answer');
        if (!input || !answer || !input.value.trim()) return;
        answer.innerHTML = '<div class="text-muted small"><span class="spinner-border spinner-border-sm me-2"></span>Preparing a family-safe answer…</div>';
        this.apiFetch('/ai-assistant', 'POST', { question: input.value.trim() }).then(function (resp) {
            var d = resp.data || resp;
            var steps = Array.isArray(d.next_steps) && d.next_steps.length ? '<ul class="small">' + d.next_steps.map(function (s) { return '<li>' + self.escapeHtml(s) + '</li>'; }).join('') + '</ul>' : '';
            answer.innerHTML = '<div class="alert alert-success small"><strong>' + self.escapeHtml(d.title || 'Assistant answer') + '</strong><p class="mb-2 mt-2">' + self.escapeHtml(d.body || '') + '</p>' + steps + '</div>';
        }).catch(function (e) { answer.innerHTML = '<div class="alert alert-warning small">' + self.escapeHtml(e.message || 'The assistant is temporarily unavailable.') + '</div>'; });
    },

    escapeHtml(value) { var div = document.createElement('div'); div.textContent = String(value == null ? '' : value); return div.innerHTML; },

    submitEmailLogin() {
        var email = this.val('loginEmail');
        var password = this.val('loginPassword');
        var errEl = document.getElementById('loginError');
        var spinner = document.getElementById('loginSpinner');
        var self = this;
        errEl.classList.add('d-none');
        if (!email || !password) { this.showErr(errEl, 'Email and password are required'); return; }
        spinner.classList.remove('d-none');
        this.apiFetch('/login', 'POST', { email: email, password: password })
            .then(function (resp) {
                var d = resp.data || resp;
                if (d.requires_otp) {
                    self.state.otpSessionId = d.otp_session_id;
                    var otpEmail = document.getElementById('otpEmail');
                    if (otpEmail) otpEmail.value = email;
                    document.querySelectorAll('#loginTabs .nav-link').forEach(function (button) {
                        button.classList.toggle('active', button.dataset.tab === 'otp');
                    });
                    self.setView('tab-email', false);
                    self.setView('tab-otp', true);
                    self.setView('otp-step-1', false);
                    self.setView('otp-step-2', true);
                    return;
                }
                self.storeAuth(d.token, d.expires_at, d.parent, d.csrf_token);
                return self.loadDashboard(true);
            })
            .catch(function (err) { self.showErr(errEl, err.message || 'Login failed'); })
            .finally(function () { spinner.classList.add('d-none'); });
    },

    requestOTP() {
        var email = this.val('otpEmail');
        var errEl = document.getElementById('otpRequestError');
        var self = this;
        errEl.classList.add('d-none');
        if (!email) { this.showErr(errEl, 'Email address required'); return; }
        this.apiFetch('/login-otp-request', 'POST', { email: email })
            .then(function (resp) {
                var d = resp.data || resp;
                if (!d.otp_session_id) throw new Error('If the account exists, check its email for a verification code.');
                self.state.otpSessionId = d.otp_session_id;
                self.setView('otp-step-1', false);
                self.setView('otp-step-2', true);
            })
            .catch(function (err) { self.showErr(errEl, err.message || 'Failed to send OTP'); });
    },

    verifyOTP() {
        var code = this.val('otpCode');
        var errEl = document.getElementById('otpVerifyError');
        var self = this;
        errEl.classList.add('d-none');
        if (!code || !this.state.otpSessionId) { this.showErr(errEl, 'Enter the OTP code'); return; }
        this.apiFetch('/login-otp-verify', 'POST', { otp_session_id: this.state.otpSessionId, otp_code: code })
            .then(function (resp) {
                var d = resp.data || resp;
                self.storeAuth(d.token, d.expires_at, d.parent, d.csrf_token);
                return self.loadDashboard(true);
            })
            .catch(function (err) { self.showErr(errEl, err.message || 'Invalid OTP'); });
    },

    logout() {
        this.apiFetch('/logout', 'POST').catch(function () {});
        this.clearAuth();
        this.showView('auth');
    },

    loadDashboard(validateBeforeReveal = false) {
        var self = this;
        this.setLoading(true);
        return this.apiFetch('/dashboard', 'GET')
            .then(function (resp) {
                var d = resp.data || resp;
                var parent = d.parent || {};
                if (validateBeforeReveal) self.showView('dashboard');
                self.preloadGradingScale();
                self.state.parent = parent;
                var titleEl = document.getElementById('portalPageTitle');
                if (titleEl) titleEl.textContent = 'Family dashboard';
                var nameEl = document.getElementById('parentName');
                if (nameEl) nameEl.textContent = parent.first_name || 'Parent';
                self.state.children = d.children || [];
                self.renderChildren(self.state.children);
                self.renderKpis(self.state.children);
                self.stashGuardianForAdmission(parent);
                self.applyInitialRoute();
            })
            .catch(function (err) {
                if (validateBeforeReveal) {
                    self.clearAuth();
                    self.showView('auth');
                    return;
                }
                document.getElementById('childrenCards').innerHTML =
                    '<div class="col-12"><div class="alert alert-danger">Failed to load dashboard: ' + self.esc(err.message) + '</div></div>';
            })
            .finally(function () { self.setLoading(false); });
    },

    renderKpis(children) {
        var total = children.reduce(function (sum, child) { return sum + parseFloat(child.current_balance || 0); }, 0);
        var paid = children.filter(function (child) { return parseFloat(child.current_balance || 0) <= 0; }).length;
        var el = document.getElementById('portalKpis');
        if (!el) return;
        el.innerHTML = [
            ['bi-people-fill','Children linked',children.length,'primary'],
            ['bi-wallet2','Family fee balance','KES ' + total.toLocaleString(),'warning'],
            ['bi-check-circle-fill','Accounts cleared',paid + ' of ' + children.length,'success'],
            ['bi-chat-dots-fill','School connection','Open','info']
        ].map(function (k) { return '<div class="col-6 col-xl-3"><div class="card portal-kpi"><div class="card-body d-flex align-items-center gap-3"><div class="kpi-icon bg-' + k[3] + '-subtle text-' + k[3] + '"><i class="bi ' + k[0] + '"></i></div><div><div class="small text-muted">' + k[1] + '</div><div class="fw-bold">' + k[2] + '</div></div></div></div></div>'; }).join('');
    },

    applyInitialRoute() {
        if (this.state.initialRouteApplied) return;
        this.state.initialRouteApplied = true;
        var section = window.PARENT_INITIAL_SECTION || 'overview';
        document.querySelectorAll('[data-portal-section]').forEach(function (link) {
            link.classList.toggle('active', link.dataset.portalSection === section);
        });
        if (section !== 'overview') this.navigateSection(section);
    },

    navigateSection(section) {
        var self = this;
        var pageTitles = {
            overview: 'Family dashboard',
            children: 'My children',
            fees: 'Fees & payments',
            attendance: 'Attendance',
            academics: 'Results & academics',
            messages: 'Messages',
            transport: 'School transport',
            documents: 'Documents & reports',
        };
        var pageTitle = document.getElementById('portalPageTitle');
        if (pageTitle && pageTitles[section]) pageTitle.textContent = pageTitles[section];
        document.querySelectorAll('[data-portal-section]').forEach(function (link) { link.classList.toggle('active', link.dataset.portalSection === section); });
        document.getElementById('portalSidebar')?.classList.remove('open');
        if (section === 'uniforms') { window.open((this.BASE || '') + '/index.php?route=r39d07ccbcf8a', '_blank'); return; }
        if (section === 'settings') { this.showAccountSettings(); return; }
        if (section === 'pta') { this.showCommunity(); return; }
        if (section === 'overview' || section === 'children') { this.showView('dashboard'); document.getElementById('childrenHeading').textContent = section === 'children' ? 'All my children' : 'My children'; return; }
        var first = this.state.children[0];
        if (!first) { this.showView('dashboard'); return; }
        var tab = section === 'fees' ? 'fees' : section === 'attendance' ? 'attendance' : section === 'academics' ? 'performance' : section === 'messages' ? 'messages' : section === 'documents' ? 'portfolio' : section === 'transport' ? 'transport' : 'fees';
        this.openStudent(first.id, first.first_name + ' ' + first.last_name, first.class_name || '', tab);
    },

    activateStudentTab(tab) {
        this.state.activeDetailTab = tab;
        document.querySelectorAll('#studentDetailTabs [data-tab], #view-student [data-tab]').forEach(function (button) { button.classList.toggle('active', button.dataset.tab === tab); });
        this.loadStudentTab(tab);
    },

    showAccountSettings() {
        var parent = this.state.parent || {};
        this.showView('dashboard');
        document.getElementById('portalPageTitle').textContent = 'Parent account';
        document.getElementById('childrenHeading').textContent = 'Profile and account security';
        document.getElementById('childrenCards').innerHTML = '<div class="col-lg-7"><div class="card portal-kpi"><div class="card-body p-4"><h5 class="fw-bold mb-3">Contact details</h5><dl class="row mb-0"><dt class="col-sm-4">Name</dt><dd class="col-sm-8">' + this.esc([parent.first_name,parent.last_name].filter(Boolean).join(' ')) + '</dd><dt class="col-sm-4">Email</dt><dd class="col-sm-8">' + this.esc(parent.email || 'Not provided') + '</dd><dt class="col-sm-4">Phone</dt><dd class="col-sm-8">' + this.esc(parent.phone_1 || parent.phone || 'Not provided') + '</dd></dl><div class="alert alert-light border mt-3 mb-0 small">For safeguarding, identity and contact changes are verified by the school office.</div></div></div></div><div class="col-lg-5 mt-3 mt-lg-0"><div class="card portal-kpi"><div class="card-body p-4"><h5 class="fw-bold">Security</h5><p class="text-muted small">This portal uses a time-limited family session. Sign out on shared devices.</p><button class="btn btn-outline-danger" onclick="parentPortalController.logout()"><i class="bi bi-box-arrow-right me-1"></i>Sign out securely</button></div></div></div>';
    },

    showCommunity() {
        var self = this;
        this.showView('dashboard');
        document.getElementById('portalPageTitle').textContent = 'PTA & parent community';
        document.getElementById('childrenHeading').textContent = 'Meetings and school community';
        this.apiFetch('/community', 'GET').then(function (resp) {
            var d = resp.data || resp;
            var meetings = d.meetings || [];
            var representative = d.is_representative ? '<div class="col-12"><div class="alert alert-success"><i class="bi bi-person-hearts me-2"></i>You are registered as a PTA representative: <strong>' + self.esc((d.memberships || []).map(function (m) { return m.role; }).join(', ')) + '</strong></div></div>' : '';
            document.getElementById('childrenCards').innerHTML = representative + (meetings.length ? meetings.map(function (m) { return '<div class="col-md-6 col-xl-4 mb-3"><div class="card portal-kpi h-100"><div class="card-body"><span class="badge bg-success-subtle text-success mb-2">' + self.esc(m.type || 'parent meeting') + '</span><h5 class="fw-bold">' + self.esc(m.title) + '</h5><p class="small text-muted mb-2"><i class="bi bi-calendar3 me-1"></i>' + self.esc(m.meeting_date) + ' ' + self.esc(m.start_time || '') + '</p><p class="small mb-0">' + self.esc(m.venue || 'Venue to be confirmed') + '</p><p class="small text-muted mt-2 mb-0">' + self.esc(m.description || m.purpose || '') + '</p></div></div></div>'; }).join('') : '<div class="col-12"><div class="alert alert-info">There are no upcoming PTA or parent meetings at the moment. School notices and invitations will appear here.</div></div>');
        }).catch(function (e) { document.getElementById('childrenCards').innerHTML = '<div class="col-12"><div class="alert alert-danger">Unable to load parent community information: ' + self.esc(e.message) + '</div></div>'; });
    },

    renderChildren(children) {
        var container = document.getElementById('childrenCards');
        if (!children.length) {
            container.innerHTML = '<div class="col-12"><div class="alert alert-info text-center">No children linked to this account. Contact the school office.</div></div>';
            return;
        }
        container.innerHTML = children.map(function (c) {
            var balance = parseFloat(c.current_balance || 0);
            var bc = balance <= 0 ? 'success' : (balance < 5000 ? 'warning' : 'danger');
            var balTxt = balance <= 0 ? 'Fees Cleared' : 'KES ' + balance.toLocaleString() + ' Due';
            return '<div class="col-md-6 col-lg-4 mb-3">' +
                '<div class="card border-0 shadow-sm rounded-4 child-card h-100" onclick="parentPortalController.openStudent(' + c.id + ',\'' + parentPortalController.esc(c.first_name + ' ' + c.last_name) + '\',\'' + parentPortalController.esc(c.class_name || '') + '\')">' +
                '<div class="card-body p-4">' +
                '<div class="d-flex align-items-center mb-3">' +
                '<div class="rounded-circle bg-primary bg-opacity-10 text-primary d-flex align-items-center justify-content-center me-3" style="width:48px;height:48px;font-size:1.4rem;font-weight:700">' +
                parentPortalController.esc((c.first_name || '?')[0].toUpperCase()) + '</div>' +
                '<div><h6 class="mb-0 fw-bold">' + parentPortalController.esc(c.first_name + ' ' + c.last_name) + '</h6>' +
                '<small class="text-muted">' + parentPortalController.esc(c.class_name || '') + ' ' + parentPortalController.esc(c.admission_no || '') + '</small></div></div>' +
                '<div class="d-flex justify-content-between align-items-center">' +
                '<span class="text-muted small">Current Balance</span>' +
                '<span class="badge bg-' + bc + ' px-3 py-2">' + balTxt + '</span></div>' +
                (c.last_payment_date ? '<small class="text-muted d-block mt-2">Last payment: ' + c.last_payment_date.substring(0, 10) + '</small>' : '') +
                '</div></div></div>';
        }).join('');
    },

    openStudent(studentId, studentName, className, initialTab) {
        this.state.selectedStudentId = studentId;
        this.state.currentStudentId = studentId;
        this.setText('studentDetailName', studentName);
        this.setText('studentDetailClass', className);
        var switcher = document.getElementById('childSwitcher');
        if (switcher && this.state.children.length) switcher.innerHTML = this.state.children.map(function (child) { return '<option value="' + child.id + '">' + parentPortalController.esc(child.first_name + ' ' + child.last_name) + '</option>'; }).join('');
        if (switcher) switcher.value = String(studentId);
        this.showView('student');
        this.loadBalanceSummary(studentId);
        document.querySelectorAll('#studentDetailTabs .nav-link').forEach(function (b) { b.classList.remove('active'); });
        var selectedTab = initialTab || 'fees';
        document.querySelectorAll('#view-student [data-tab]').forEach(function (button) {
            button.classList.toggle('active', button.dataset.tab === selectedTab);
        });
        var selectedButton = document.querySelector('#studentDetailTabs .nav-link[data-tab="' + selectedTab + '"]');
        if (selectedButton) selectedButton.classList.add('active');
        this.state.activeDetailTab = selectedTab;
        this.loadStudentTab(selectedTab);
    },

    loadBalanceSummary(studentId) {
        var self = this;
        this.apiFetch('/fee-balance/' + studentId, 'GET')
            .then(function (resp) {
                var d = resp.data || resp;
                var rows = d.per_term || [];
                var cur = rows[0] || {};
                var total = parseFloat(d.total_balance || 0);
                document.getElementById('balanceSummaryCards').innerHTML = [
                    self.summCard('Total Outstanding', 'KES ' + total.toLocaleString(), total > 0 ? 'danger' : 'success', 'fas fa-wallet'),
                    self.summCard('Current Term Due', 'KES ' + Number(cur.total_due || 0).toLocaleString(), 'primary', 'fas fa-calendar'),
                    self.summCard('Current Term Paid', 'KES ' + Number(cur.total_paid || 0).toLocaleString(), 'info', 'fas fa-check-circle'),
                ].join('');
            })
            .catch(function () {});
    },

    summCard(label, value, color, icon) {
        return '<div class="col-md-4 mb-2"><div class="card border-0 shadow-sm rounded-4 kw-balance-card bg-' + color + ' bg-opacity-10">' +
            '<div class="card-body p-3 d-flex align-items-center">' +
            '<div class="me-3 fs-3 text-' + color + '"><i class="' + icon + '"></i></div>' +
            '<div><div class="text-muted small">' + label + '</div><div class="fw-bold fs-6">' + value + '</div></div>' +
            '</div></div></div>';
    },

    loadStudentTab(tab) {
        var id = this.state.selectedStudentId;
        var content = document.getElementById('studentDetailContent');
        var self = this;
        content.innerHTML = '<div class="text-center py-4"><div class="spinner-border text-primary"></div></div>';
        if (tab === 'fees') {
            this.apiFetch('/student-fees/' + id, 'GET')
                .then(function (resp) { self.renderFeeHistory(resp.data || resp); })
                .catch(function (e) { content.innerHTML = '<div class="alert alert-danger">Failed to load fees: ' + self.esc(e.message) + '</div>'; });
        } else if (tab === 'payments') {
            this.apiFetch('/student-payment-history/' + id, 'GET')
                .then(function (resp) { self.renderPaymentHistory(resp.data || resp); })
                .catch(function () { content.innerHTML = '<div class="alert alert-danger">Failed to load payments</div>'; });
        } else if (tab === 'statement') {
            this.renderStatementView(id);
        } else if (tab === 'attendance') {
            this.apiFetch('/student-attendance/' + id, 'GET')
                .then(function (resp) { self.renderAttendance(resp.data || resp); })
                .catch(function (e) { content.innerHTML = '<div class="alert alert-danger">Failed to load attendance: ' + self.esc(e.message) + '</div>'; });
        } else if (tab === 'performance') {
            this.apiFetch('/student-performance/' + id, 'GET')
                .then(function (resp) { self.renderPerformance(resp.data || resp); })
                .catch(function (e) { content.innerHTML = '<div class="alert alert-danger">Failed to load performance: ' + self.esc(e.message) + '</div>'; });
        } else if (tab === 'transport') {
            this.apiFetch('/student-transport/' + id, 'GET')
                .then(function (resp) { self.renderTransport(resp.data || resp); })
                .catch(function (e) { content.innerHTML = '<div class="alert alert-danger">Failed to load transport: ' + self.esc(e.message) + '</div>'; });
        } else if (tab === 'report-card') {
            this.apiFetch('/student-report-card/' + id, 'GET')
                .then(function (resp) { self.renderReportCard(resp.data || resp); })
                .catch(function (e) { content.innerHTML = '<div class="alert alert-danger">Failed to load report card: ' + self.esc(e.message) + '</div>'; });
        } else if (tab === 'messages') {
            this.apiFetch('/messages/' + id, 'GET')
                .then(function (resp) { self.renderMessages(resp.data || resp); })
                .catch(function (e) { content.innerHTML = '<div class="alert alert-danger">Failed to load messages: ' + self.esc(e.message) + '</div>'; });
        } else if (tab === 'portfolio') {
            this.apiFetch('/portfolio/' + id, 'GET')
                .then(function (resp) { self._portfolioData = resp.data || resp; self.renderPortfolio(self._portfolioData); })
                .catch(function (e) { content.innerHTML = '<div class="alert alert-danger">Failed to load portfolio: ' + self.esc(e.message) + '</div>'; });
        } else if (tab === 'learning-plan') {
            this.apiFetch('/student-learning-plan/' + id, 'GET')
                .then(function (resp) { self.renderLearningPlan(resp.data || resp); })
                .catch(function (e) { content.innerHTML = '<div class="alert alert-danger">Failed to load learning plan: ' + self.esc(e.message) + '</div>'; });
        }
    },

    renderTransport(data) {
        var content = document.getElementById('studentDetailContent');
        if (!data || !data.route_name) { content.innerHTML = '<div class="alert alert-info">This learner has no active transport assignment.</div>'; return; }
        content.innerHTML = '<div class="row g-3"><div class="col-md-7"><div class="card border-0 bg-success-subtle h-100"><div class="card-body"><span class="badge bg-success mb-2">' + this.esc(data.status || 'active') + '</span><h4 class="fw-bold">' + this.esc(data.route_name) + '</h4><div class="row g-3 mt-1"><div class="col-6"><small class="text-muted d-block">Pickup point</small><strong>' + this.esc(data.pickup_stop || 'Not set') + '</strong><div class="small">' + this.esc(data.pickup_time || '—') + '</div></div><div class="col-6"><small class="text-muted d-block">Drop-off point</small><strong>' + this.esc(data.dropoff_stop || 'Not set') + '</strong><div class="small">' + this.esc(data.dropoff_time || '—') + '</div></div></div></div></div></div><div class="col-md-5"><div class="card border h-100"><div class="card-body"><h6 class="fw-bold">Vehicle and driver</h6><p class="mb-2"><i class="bi bi-bus-front me-2 text-success"></i>' + this.esc(data.registration_number || 'Vehicle pending') + '</p><p class="mb-0"><i class="bi bi-person-badge me-2 text-success"></i>' + this.esc(data.driver_name || 'Driver pending') + '</p></div></div></div></div>';
    },

    renderLearningPlan(data) {
        var content = document.getElementById('studentDetailContent');
        var ctx = data.context || {};
        var schemes = data.schemes || [];
        var plans = data.lesson_plans || [];
        var weeks = {};
        schemes.forEach(function (s) { if (!weeks[s.week_number]) weeks[s.week_number] = []; weeks[s.week_number].push(s); });
        var html = '<div class="alert alert-success"><strong>' + this.esc(ctx.term_name || 'Current term') + '</strong> · ' + this.esc(ctx.class_name || '') + ' ' + this.esc(ctx.stream_name || '') + '<br><small>Approved schemes and delivered lesson plans for this child’s current stream.</small></div>';
        Object.keys(weeks).sort(function (a, b) { return Number(a) - Number(b); }).forEach(function (week) {
            html += '<div class="card border-0 shadow-sm mb-3"><div class="card-header fw-bold">Week ' + week + '</div><div class="card-body">';
            weeks[week].forEach(function (s) {
                var related = plans.filter(function (p) { return String(p.scheme_of_work_id) === String(s.id); });
                html += '<div class="border-bottom pb-2 mb-2"><strong>' + parentPortalController.esc(s.learning_area || '') + '</strong> · ' + parentPortalController.esc(s.title || '') + '<div class="small text-muted">' + parentPortalController.esc(s.strand_name || '') + (s.sub_strand_name ? ' / ' + parentPortalController.esc(s.sub_strand_name) : '') + '</div>' + (related.length ? '<div class="small mt-2">Delivered: ' + related.map(function (p) { return parentPortalController.esc(p.lesson_date + ' · ' + (p.title || 'Lesson')); }).join(', ') + '</div>' : '<div class="small text-muted mt-2">No delivered lesson recorded yet.</div>') + '</div>';
            });
            html += '</div></div>';
        });
        content.innerHTML = html || '<div class="alert alert-info">No approved scheme rows are available for this term yet.</div>';
    },

    renderFeeHistory(data) {
        var content = document.getElementById('studentDetailContent');
        var years = data.academic_years || data || [];
        var sid = this.state.selectedStudentId;
        if (!years.length) { content.innerHTML = '<div class="alert alert-info">No fee history found.</div>'; return; }
        content.innerHTML = years.map(function (yr) {
            return '<div class="card mb-3 border-0 shadow-sm">' +
                '<div class="card-header bg-primary text-white fw-bold">Academic Year ' + yr.year + '</div>' +
                '<div class="card-body">' +
                (yr.terms || []).map(function (term) {
                    var rows = (term.obligations || []).map(function (o) {
                        var sc = o.payment_status === 'paid' ? 'success' : (o.payment_status === 'partial' ? 'warning' : 'danger');
                        return '<tr><td>' + parentPortalController.esc(o.fee_type_name || '') + '</td>' +
                            '<td>KES ' + Number(o.amount_due || 0).toLocaleString() + '</td>' +
                            '<td>KES ' + Number(o.amount_paid || 0).toLocaleString() + '</td>' +
                            '<td><strong>KES ' + Number(o.balance || 0).toLocaleString() + '</strong></td>' +
                            '<td><span class="badge bg-' + sc + '">' + parentPortalController.esc(o.payment_status || 'pending') + '</span></td></tr>';
                    }).join('');
                    var payBtn = term.balance > 0
                        ? '<button class="btn btn-sm btn-success pay-now-btn ms-2" data-amount="' + term.balance + '"><i class="bi bi-phone me-1"></i>Pay Now</button>'
                        : '';
                    return '<h6 class="text-muted mb-2">' + parentPortalController.esc(term.term_name || '') + '</h6>' +
                        '<table class="table table-sm table-bordered mb-3"><thead class="table-light"><tr><th>Fee Type</th><th>Billed</th><th>Paid</th><th>Balance</th><th>Status</th></tr></thead>' +
                        '<tbody>' + rows + '</tbody>' +
                        '<tfoot class="fw-bold table-light"><tr><td>Total</td>' +
                        '<td>KES ' + Number(term.total_due || 0).toLocaleString() + '</td>' +
                        '<td>KES ' + Number(term.total_paid || 0).toLocaleString() + '</td>' +
                        '<td>KES ' + Number(term.balance || 0).toLocaleString() + '</td>' +
                        '<td>' + payBtn + '</td></tr></tfoot></table>';
                }).join('') +
                '</div></div>';
        }).join('');
    },

    renderPaymentHistory(payments) {
        var content = document.getElementById('studentDetailContent');
        if (!payments || !payments.length) { content.innerHTML = '<div class="alert alert-info">No payment records found.</div>'; return; }
        var rows = payments.map(function (p) {
            return '<tr><td>' + (p.payment_date || '').substring(0, 10) + '</td>' +
                '<td><span class="badge bg-secondary">' + parentPortalController.esc(p.payment_method || '') + '</span></td>' +
                '<td>KES ' + Number(p.amount_paid || 0).toLocaleString() + '</td>' +
                '<td>' + parentPortalController.esc(p.receipt_no || '') + '</td>' +
                '<td>' + parentPortalController.esc(p.reference_no || '') + '</td>' +
                '<td>' + parentPortalController.esc(p.term_name || '') + '</td></tr>';
        }).join('');
        document.getElementById('studentDetailContent').innerHTML =
            '<div class="table-responsive"><table class="table table-hover"><thead class="table-light"><tr>' +
            '<th>Date</th><th>Method</th><th>Amount</th><th>Receipt #</th><th>Reference</th><th>Term</th>' +
            '</tr></thead><tbody>' + rows + '</tbody></table></div>';
    },

    renderStatementView(studentId) {
        var content = document.getElementById('studentDetailContent');
        var self = this;
        content.innerHTML = '<div class="text-center py-3">' +
            '<p class="text-muted">Generate a printable fee statement for this student.</p>' +
            '<button class="btn btn-primary" id="btnGenStmt"><i class="fas fa-file-invoice me-2"></i>Generate Statement</button></div>';
        document.getElementById('btnGenStmt').addEventListener('click', function () {
            var btn = this;
            btn.disabled = true;
            btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Loading...';
            self.apiFetch('/student-statement/' + studentId, 'GET')
                .then(function (resp) {
                    var d = resp.data || resp;
                    PrintManager.printHtml(self.buildStatementHTML(d), { title: 'Parent Statement' });
                })
                .catch(function (e) {
                    content.innerHTML = '<div class="alert alert-danger">Failed to generate statement: ' + self.esc(e.message) + '</div>';
                })
                .finally(function () { btn.disabled = false; btn.innerHTML = '<i class="fas fa-file-invoice me-2"></i>Generate Statement'; });
        });
    },

    buildStatementHTML(data) {
        var s = data.student || {};
        var fees = (data.fees && data.fees.academic_years) || [];
        var pmts = data.payments || [];
        var self = this;
        var feeRows = fees.map(function (yr) {
            return '<h5>Academic Year ' + yr.year + '</h5>' +
                (yr.terms || []).map(function (t) {
                    return '<p><strong>' + self.esc(t.term_name || '') + '</strong></p>' +
                        '<table border="1" cellpadding="4" style="border-collapse:collapse;width:100%"><thead><tr><th>Fee Type</th><th>Amount Due</th><th>Paid</th><th>Balance</th></tr></thead><tbody>' +
                        (t.obligations || []).map(function (o) {
                            return '<tr><td>' + self.esc(o.fee_type_name || '') + '</td>' +
                                '<td>KES ' + Number(o.amount_due || 0).toLocaleString() + '</td>' +
                                '<td>KES ' + Number(o.amount_paid || 0).toLocaleString() + '</td>' +
                                '<td>KES ' + Number(o.balance || 0).toLocaleString() + '</td></tr>';
                        }).join('') +
                        '</tbody></table>';
                }).join('');
        }).join('');
        var pmtRows = pmts.map(function (p) {
            return '<tr><td>' + (p.payment_date || '').substring(0, 10) + '</td>' +
                '<td>' + self.esc(p.payment_method || '') + '</td>' +
                '<td>KES ' + Number(p.amount_paid || 0).toLocaleString() + '</td>' +
                '<td>' + self.esc(p.receipt_no || '') + '</td></tr>';
        }).join('');
        return '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Fee Statement</title>' +
            '<link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css" rel="stylesheet">' +
            '</head><body class="p-4">' +
            '<h3 class="text-center">Kingsway Preparatory School</h3>' +
            '<h5 class="text-center text-muted">Fee Statement</h5><hr>' +
            '<p><strong>Student:</strong> ' + self.esc(s.first_name + ' ' + s.last_name) +
            ' &nbsp; <strong>Adm No:</strong> ' + self.esc(s.admission_no || '') +
            ' &nbsp; <strong>Class:</strong> ' + self.esc(s.class_name || '') + '</p>' +
            '<p><strong>Generated:</strong> ' + self.esc(data.generated_at || '') + '</p><hr>' +
            feeRows +
            (pmtRows ? '<h5 class="mt-4">Payment History</h5><table border="1" cellpadding="4" style="border-collapse:collapse;width:100%"><thead><tr><th>Date</th><th>Method</th><th>Amount</th><th>Receipt #</th></tr></thead><tbody>' + pmtRows + '</tbody></table>' : '') +
            '</body></html>';
    },

    renderAttendance(data) {
        var content = document.getElementById('studentDetailContent');
        var summary = data.summary || {};
        var recent = data.recent || [];
        var monthly = data.monthly || [];
        var pct = data.percentage || 0;
        var total = parseInt(summary.total_days || 0);
        var present = parseInt(summary.days_present || 0);
        var absent = parseInt(summary.days_absent || 0);
        var late = parseInt(summary.days_late || 0);
        var pctClass = pct >= 90 ? 'success' : (pct >= 75 ? 'warning' : 'danger');
        var html = '<div class="row g-3 mb-4">' +
            '<div class="col-md-3"><div class="card border-0 shadow-sm rounded-4 text-center p-3 bg-success bg-opacity-10"><h2 class="text-success fw-bold mb-0">' + pct + '%</h2><small class="text-muted">Attendance</small></div></div>' +
            '<div class="col-md-3"><div class="card border-0 shadow-sm rounded-4 text-center p-3 bg-primary bg-opacity-10"><h2 class="text-primary fw-bold mb-0">' + present + '</h2><small class="text-muted">Present</small></div></div>' +
            '<div class="col-md-3"><div class="card border-0 shadow-sm rounded-4 text-center p-3 bg-danger bg-opacity-10"><h2 class="text-danger fw-bold mb-0">' + absent + '</h2><small class="text-muted">Absent</small></div></div>' +
            '<div class="col-md-3"><div class="card border-0 shadow-sm rounded-4 text-center p-3 bg-warning bg-opacity-10"><h2 class="text-warning fw-bold mb-0">' + late + '</h2><small class="text-muted">Late</small></div></div>' +
            '</div>';
        if (monthly.length) {
            html += '<h6 class="text-muted mb-2">Monthly Breakdown</h6><div class="table-responsive mb-4"><table class="table table-sm table-bordered"><thead class="table-light"><tr><th>Month</th><th>Total</th><th>Present</th><th>Absent</th><th>Late</th><th>%</th></tr></thead><tbody>';
            monthly.forEach(function (m) {
                var mpct = m.total_days > 0 ? Math.round(100 * m.days_present / m.total_days) : 0;
                html += '<tr><td>' + m.month + '</td><td>' + m.total_days + '</td><td>' + m.days_present + '</td><td>' + m.days_absent + '</td><td>' + m.days_late + '</td><td><span class="badge bg-' + (mpct >= 90 ? 'success' : (mpct >= 75 ? 'warning' : 'danger')) + '">' + mpct + '%</span></td></tr>';
            });
            html += '</tbody></table></div>';
        }
        if (recent.length) {
            html += '<h6 class="text-muted mb-2">Recent Activity</h6><div class="table-responsive"><table class="table table-sm table-bordered"><thead class="table-light"><tr><th>Date</th><th>Status</th><th>Reason</th></tr></thead><tbody>';
            recent.forEach(function (r) {
                var sc = r.status === 'present' ? 'success' : (r.status === 'late' ? 'warning' : 'danger');
                html += '<tr><td>' + (r.date || '').substring(0, 10) + '</td><td><span class="badge bg-' + sc + '">' + parentPortalController.esc(r.status || '') + '</span></td><td>' + parentPortalController.esc(r.absence_reason || '-') + '</td></tr>';
            });
            html += '</tbody></table></div>';
        }
        if (!total) html = '<div class="alert alert-info">No attendance records found for the current term.</div>';
        content.innerHTML = html;
    },

    renderPerformance(data) {
        var content = document.getElementById('studentDetailContent');
        var student = data.student || {};
        var term = data.term || {};
        var scores = data.scores || [];
        var competencies = data.competencies || [];
        var values = data.values || [];
        var attendance = data.attendance || {};
        var attPct = attendance.total_days > 0 ? Math.round(100 * attendance.days_present / attendance.total_days) : 0;
        var html = '<div class="mb-3"><h5 class="fw-bold">' + parentPortalController.esc(student.first_name + ' ' + student.last_name) + '</h5>' +
            '<small class="text-muted">' + parentPortalController.esc(student.class_name || '') + ' · ' + parentPortalController.esc(term.name || 'Current Term') + '</small></div>';
        if (scores.length) {
            html += '<h6 class="text-muted mb-2">Subject Scores</h6><div class="table-responsive mb-4"><table class="table table-sm table-bordered"><thead class="table-light"><tr><th>Subject</th><th>Score</th><th>Grade</th></tr></thead><tbody>';
            scores.forEach(function (s) {
                var score = parseFloat(s.score || s.total_score || 0);
                var grade = s.grade || s.letter_grade || GradingScale.grade(score);
                html += '<tr><td>' + parentPortalController.esc(s.subject_name || '') + '</td><td>' + score + '</td><td><span class="badge bg-primary">' + parentPortalController.esc(grade) + '</span></td></tr>';
            });
            html += '</tbody></table></div>';
        }
        if (competencies.length) {
            html += '<h6 class="text-muted mb-2">Core Competencies</h6><div class="table-responsive mb-4"><table class="table table-sm table-bordered"><thead class="table-light"><tr><th>Competency</th><th>Level</th><th>Notes</th></tr></thead><tbody>';
            competencies.forEach(function (c) {
                html += '<tr><td><strong>' + parentPortalController.esc(c.code || '') + '</strong> ' + parentPortalController.esc(c.competency_name || '') + '</td>' +
                    '<td><span class="badge bg-info">' + parentPortalController.esc(c.level_name || c.level_code || '-') + '</span></td>' +
                    '<td><small>' + parentPortalController.esc(c.notes || '') + '</small></td></tr>';
            });
            html += '</tbody></table></div>';
        }
        if (values.length) {
            html += '<h6 class="text-muted mb-2">Core Values</h6><div class="table-responsive mb-4"><table class="table table-sm table-bordered"><thead class="table-light"><tr><th>Value</th><th>Rating</th></tr></thead><tbody>';
            values.forEach(function (v) {
                html += '<tr><td>' + parentPortalController.esc(v.value_name || '') + '</td><td>' + parentPortalController.esc(v.rating || '-') + '</td></tr>';
            });
            html += '</tbody></table></div>';
        }
        if (attendance.total_days) {
            html += '<h6 class="text-muted mb-2">Term Attendance</h6><div class="row g-2 mb-3">' +
                '<div class="col-auto"><span class="badge bg-success fs-6">' + attPct + '%</span></div>' +
                '<div class="col-auto text-muted small lh-lg">' + attendance.days_present + '/' + attendance.total_days + ' days present</div></div>';
        }
        if (!scores.length && !competencies.length) {
            html = '<div class="alert alert-info">No performance data available for the current term.</div>';
        }
        content.innerHTML = html;
    },

    renderReportCard(data) {
        var content = document.getElementById('studentDetailContent');
        if (!data || data.released === false) {
            content.innerHTML = '<div class="alert alert-info"><i class="bi bi-lock me-2"></i>' +
                this.esc((data && data.message) || 'The school has not released a report card for this learner yet.') + '</div>';
            return;
        }
        var s = data.student || {};
        var term = data.term || {};
        var scores = data.scores || [];
        var comps = data.competencies || [];
        var vals = data.values || [];
        var att = data.attendance || {};
        var enr = data.enrollment || {};
        var school = data.school || {};
        var self = this;
        var official = data.official_release || {};
        var attPct = att.total_days > 0 ? Math.round(100 * att.days_present / att.total_days) : 0;
        // ── Header ──
        var html = '<div class="report-card-container p-3">' +
            '<div class="alert alert-success py-2"><i class="bi bi-patch-check-fill me-2"></i>Official school release' +
            (official.version_no ? ' · Version ' + self.esc(official.version_no) : '') +
            (official.released_at ? ' · ' + self.esc(official.released_at) : '') + '</div>' +
            '<div class="text-center mb-3 border-bottom pb-3">' +
            '<h4 class="fw-bold mb-0">' + self.esc(school.name || 'Kingsway Preparatory School') + '</h4>' +
            '<small class="text-muted">' + self.esc(school.address || '') + ' | ' + self.esc(school.phone || '') + '</small>' +
            '<h5 class="mt-2">School Report Card</h5>' +
            '<small class="text-muted">' + self.esc(term.name || '') + ' · ' + self.esc(data.year || term.year_code || '') + '</small></div>' +
            // ── Student Info ──
            '<div class="row mb-3 small"><div class="col-6"><strong>Name:</strong> ' + self.esc(s.first_name + ' ' + s.last_name) + '</div>' +
            '<div class="col-3"><strong>Adm:</strong> ' + self.esc(s.admission_no || '') + '</div>' +
            '<div class="col-3"><strong>Class:</strong> ' + self.esc(s.class_name || '') + ' ' + self.esc(s.stream_name || '') + '</div></div>';
        // ── Subject Scores ──
        if (scores.length) {
            html += '<h6 class="text-muted border-bottom pb-1">Academic Performance</h6>' +
                '<div class="table-responsive mb-3"><table class="table table-sm table-bordered"><thead class="table-light"><tr><th>Subject</th><th>Formative</th><th>Summative</th><th>Overall</th><th>Grade</th></tr></thead><tbody>';
            scores.forEach(function (sc) {
                var fmt = parseFloat(sc.formative_percentage || sc.formative_score || 0);
                var sum = parseFloat(sc.summative_percentage || sc.summative_score || 0);
                var ovr = parseFloat(sc.overall_percentage || sc.total_score || sc.score || 0);
                var grd = sc.overall_grade || sc.grade || sc.cbc_grade || GradingScale.grade(ovr);
                html += '<tr><td>' + self.esc(sc.subject_name || '') + '</td><td>' + fmt + '</td><td>' + sum + '</td><td><strong>' + ovr + '</strong></td><td><span class="badge bg-primary">' + self.esc(grd) + '</span></td></tr>';
            });
            html += '</tbody></table></div>';
        }
        // ── Competencies ──
        if (comps.length) {
            html += '<h6 class="text-muted border-bottom pb-1">Core Competencies</h6>' +
                '<div class="table-responsive mb-3"><table class="table table-sm table-bordered"><thead class="table-light"><tr><th>Competency</th><th>Level</th><th>Notes</th></tr></thead><tbody>';
            comps.forEach(function (c) {
                html += '<tr><td><strong>' + self.esc(c.code || '') + '</strong> ' + self.esc(c.competency_name || '') + '</td>' +
                    '<td><span class="badge bg-info">' + self.esc(c.level_name || c.level_code || '-') + '</span></td>' +
                    '<td><small>' + self.esc(c.notes || '') + '</small></td></tr>';
            });
            html += '</tbody></table></div>';
        }
        // ── Values ──
        if (vals.length) {
            html += '<h6 class="text-muted border-bottom pb-1">Core Values</h6>' +
                '<div class="table-responsive mb-3"><table class="table table-sm table-bordered"><thead class="table-light"><tr><th>Value</th><th>Rating</th></tr></thead><tbody>';
            vals.forEach(function (v) {
                html += '<tr><td>' + self.esc(v.value_name || '') + '</td><td>' + self.esc(v.rating || '-') + '</td></tr>';
            });
            html += '</tbody></table></div>';
        }
        // ── Attendance ──
        if (att.total_days) {
            html += '<h6 class="text-muted border-bottom pb-1">Attendance</h6>' +
                '<div class="row g-2 mb-3"><div class="col-auto"><span class="badge bg-success fs-6">' + attPct + '%</span></div>' +
                '<div class="col-auto text-muted small lh-lg">' + (att.days_present || 0) + '/' + (att.total_days || 0) + ' days present | ' +
                (att.days_absent || 0) + ' absent | ' + (att.days_late || 0) + ' late</div></div>';
        }
        // ── Teacher Comments ──
        if (enr.teacher_comments) {
            html += '<div class="card bg-light mb-2"><div class="card-body py-2"><small class="text-muted">Class Teacher</small>' +
                '<p class="mb-0 fst-italic">" ' + self.esc(enr.teacher_comments) + ' "</p>' +
                (enr.class_teacher_name ? '<small class="text-muted">— ' + self.esc(enr.class_teacher_name) + '</small>' : '') + '</div></div>';
        }
        if (enr.head_teacher_comments) {
            html += '<div class="card bg-light mb-2"><div class="card-body py-2"><small class="text-muted">Head Teacher</small>' +
                '<p class="mb-0 fst-italic">" ' + self.esc(enr.head_teacher_comments) + ' "</p></div></div>';
        }
        // ── Official PDF ──
        html += '<div class="text-center mt-3"><button class="btn btn-primary btn-sm" id="btnPrintReportCard"' +
            (official.download_url ? '' : ' disabled') + '><i class="bi bi-file-earmark-pdf me-1"></i>Open official PDF</button></div></div>';
        content.innerHTML = html;
        var printBtn = document.getElementById('btnPrintReportCard');
        if (printBtn && official.download_url) {
            printBtn.addEventListener('click', function () {
                window.open(official.download_url, '_blank', 'noopener');
            });
        }
    },

    buildReportCardHTML(data) {
        var s = data.student || {};
        var school = data.school || {};
        var term = data.term || {};
        var scores = data.scores || [];
        var comps = data.competencies || [];
        var vals = data.values || {};
        var att = data.attendance || {};
        var enr = data.enrollment || {};
        var self = this;
        var attPct = att.total_days > 0 ? Math.round(100 * att.days_present / att.total_days) : 0;
        var scoreRows = scores.map(function (sc) {
            var ovr = parseFloat(sc.overall_percentage || sc.total_score || sc.score || 0);
            var grd = sc.overall_grade || sc.grade || sc.cbc_grade || GradingScale.grade(ovr);
            return '<tr><td>' + self.esc(sc.subject_name || '') + '</td><td>' + (parseFloat(sc.formative_percentage || 0)) + '</td><td>' + (parseFloat(sc.summative_percentage || 0)) + '</td><td><strong>' + ovr + '</strong></td><td><strong>' + self.esc(grd) + '</strong></td></tr>';
        }).join('');
        var compRows = comps.map(function (c) {
            return '<tr><td><strong>' + self.esc(c.code || '') + '</strong></td><td>' + self.esc(c.competency_name || '') + '</td><td>' + self.esc(c.level_name || c.level_code || '-') + '</td></tr>';
        }).join('');
        return '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Report Card</title>' +
            '<style>body{font-family:Arial,sans-serif;padding:20px}table{width:100%;border-collapse:collapse;margin:10px 0}th,td{border:1px solid #999;padding:6px 8px;text-align:left}th{background:#eee}.header{text-align:center;border-bottom:2px solid #333;padding-bottom:10px;margin-bottom:15px}.footer{margin-top:20px;border-top:1px solid #ccc;padding-top:10px;font-size:12px}</style></head><body>' +
            '<div class="header"><h2>' + self.esc(school.name || 'Kingsway Preparatory School') + '</h2>' +
            '<h3>School Report Card</h3><p>' + self.esc(term.name || '') + ' · ' + (data.year || '') + '</p></div>' +
            '<table><tr><td><strong>Name:</strong> ' + self.esc(s.first_name + ' ' + s.last_name) + '</td><td><strong>Adm:</strong> ' + self.esc(s.admission_no || '') + '</td><td><strong>Class:</strong> ' + self.esc(s.class_name || '') + ' ' + self.esc(s.stream_name || '') + '</td></tr></table>' +
            (scoreRows ? '<h4>Academic Performance</h4><table><thead><tr><th>Subject</th><th>Formative</th><th>Summative</th><th>Overall</th><th>Grade</th></tr></thead><tbody>' + scoreRows + '</tbody></table>' : '') +
            (compRows ? '<h4>Core Competencies</h4><table><thead><tr><th>Code</th><th>Competency</th><th>Level</th></tr></thead><tbody>' + compRows + '</tbody></table>' : '') +
            (vals.length ? '<h4>Core Values</h4><table><thead><tr><th>Value</th><th>Rating</th></tr></thead><tbody>' + vals.map(function (v) { return '<tr><td>' + self.esc(v.value_name || '') + '</td><td>' + self.esc(v.rating || '-') + '</td></tr>'; }).join('') + '</tbody></table>' : '') +
            (att.total_days ? '<h4>Attendance</h4><p>' + attPct + '% (' + (att.days_present || 0) + '/' + (att.total_days || 0) + ' days present)</p>' : '') +
            (enr.teacher_comments ? '<h4>Class Teacher Comment</h4><p><em>"' + self.esc(enr.teacher_comments) + '"</em></p>' : '') +
            (enr.head_teacher_comments ? '<h4>Head Teacher Comment</h4><p><em>"' + self.esc(enr.head_teacher_comments) + '"</em></p>' : '') +
            '<div class="footer"><p>Generated: ' + self.esc(data.generated_at || '') + ' | Kingsway Preparatory School</p></div>' +
            '</body></html>';
    },

    renderMessages(data) {
        var content = document.getElementById('studentDetailContent');
        var msgs = data || [];
        var self = this;
        var html = '<div class="d-flex justify-content-between align-items-center mb-3">' +
            '<h6 class="mb-0 text-muted">Messages with School</h6>' +
            '<button class="btn btn-primary btn-sm" id="btnComposeMessage"><i class="bi bi-pencil me-1"></i>Compose</button></div>';
        if (!msgs.length) {
            html += '<div class="alert alert-info">No messages yet. Click "Compose" to send a message to the school.</div>';
        } else {
            html += '<div class="list-group mb-3">';
            msgs.forEach(function (m) {
                var isParent = m.sender_type === 'parent';
                html += '<div class="list-group-item list-group-item-action ' + (isParent ? '' : 'bg-light') + '">' +
                    '<div class="d-flex justify-content-between"><small class="fw-bold">' + self.esc(m.sender_name || (isParent ? 'You' : 'School')) + '</small>' +
                    '<small class="text-muted">' + (m.created_at || '').substring(0, 16) + '</small></div>' +
                    '<strong class="d-block small">' + self.esc(m.subject || '') + '</strong>' +
                    '<p class="mb-0 small">' + self.esc(m.message || '') + '</p></div>';
            });
            html += '</div>';
        }
        // Compose form (hidden initially)
        html += '<div id="composeForm" style="display:none" class="card border-0 shadow-sm p-3">' +
            '<h6 class="text-muted mb-3">Send Message to School</h6>' +
            '<div class="mb-2"><input type="text" id="msgSubject" class="form-control form-control-sm" placeholder="Subject"></div>' +
            '<div class="mb-2"><textarea id="msgBody" class="form-control" rows="3" placeholder="Your message..."></textarea></div>' +
            '<div id="msgError" class="alert alert-danger d-none"></div>' +
            '<div><button class="btn btn-success btn-sm" id="btnSendMessage"><i class="bi bi-send me-1"></i>Send</button>' +
            '<button class="btn btn-outline-secondary btn-sm ms-2" id="btnCancelMessage">Cancel</button></div></div>';
        content.innerHTML = html;
        this.on('btnComposeMessage', 'click', function () { self.setView('composeForm', true); });
        this.on('btnCancelMessage', 'click', function () { self.setView('composeForm', false); document.getElementById('msgError').classList.add('d-none'); });
        this.on('btnSendMessage', 'click', function () { self.sendMessage(); });
    },

    sendMessage() {
        var subject = document.getElementById('msgSubject').value.trim();
        var message = document.getElementById('msgBody').value.trim();
        var errEl = document.getElementById('msgError');
        var self = this;
        errEl.classList.add('d-none');
        if (!subject || !message) { errEl.textContent = 'Subject and message are required'; errEl.classList.remove('d-none'); return; }
        this.apiFetch('/send-message', 'POST', {
            student_id: this.state.selectedStudentId,
            subject: subject,
            message: message
        }).then(function () {
            document.getElementById('msgSubject').value = '';
            document.getElementById('msgBody').value = '';
            self.setView('composeForm', false);
            self.loadStudentTab('messages');
        }).catch(function (err) {
            errEl.textContent = err.message || 'Failed to send message';
            errEl.classList.remove('d-none');
        });
    },

    renderPortfolio(data) {
        var content = document.getElementById('studentDetailContent');
        var portfolio = data.portfolio || {};
        var artifacts = data.artifacts || [];
        var self = this;
        if (!portfolio) {
            content.innerHTML = '<div class="alert alert-info">No portfolio found for this student. Portfolios are created by the teacher.</div>';
            return;
        }
        var html = '<div class="d-flex justify-content-between align-items-center mb-2 flex-wrap gap-2">' +
            '<h6 class="mb-0 text-muted">' + self.esc(portfolio.title || 'Portfolio') + '</h6>' +
            '<div>' +
            '<button class="btn btn-outline-primary btn-sm me-1" onclick="parentPortalController.printPortfolio()"><i class="bi bi-printer me-1"></i>Print</button>' +
            '<span class="badge bg-secondary">' + (portfolio.portfolio_type || 'digital') + '</span></div></div>';
        if (portfolio.description) {
            html += '<p class="small text-muted mb-3">' + self.esc(portfolio.description) + '</p>';
        }
        if (portfolio.theme) {
            html += '<p class="small"><strong>Theme:</strong> ' + self.esc(portfolio.theme) + '</p>';
        }
        if (!artifacts.length) {
            html += '<div class="alert alert-info">No artifacts have been added yet.</div>';
        } else {
            html += '<div class="row g-2">';
            artifacts.forEach(function (a) {
                var typeIcon = a.artifact_type === 'photo' || a.artifact_type === 'video' ? 'bi-file-image' :
                    a.artifact_type === 'document' ? 'bi-file-text' :
                    a.artifact_type === 'project' ? 'bi-diagram-3' : 'bi-file';
                html += '<div class="col-12"><div class="card border-0 shadow-sm">' +
                    '<div class="card-body py-2">' +
                    '<div class="d-flex justify-content-between align-items-start">' +
                    '<div><i class="bi ' + typeIcon + ' me-2 text-primary"></i><strong>' + self.esc(a.artifact_title || '') + '</strong>' +
                    ' <span class="badge bg-light text-muted">' + self.esc(a.artifact_type || '') + '</span></div>' +
                    '<small class="text-muted">' + (a.upload_date || '').substring(0, 10) + '</small></div>';
                if (a.description) html += '<p class="small mb-1 mt-1">' + self.esc(a.description) + '</p>';
                // Competency mapping
                if (a.competency_name) html += '<small class="text-muted d-block"><strong>C:</strong> ' + self.esc(a.competency_name) + '</small>';
                if (a.value_name) html += '<small class="text-muted d-block"><strong>V:</strong> ' + self.esc(a.value_name) + '</small>';
                if (a.rating) html += '<small class="text-muted d-block"><strong>Rating:</strong> ' + a.rating + '/5</small>';
                // Learner reflection
                if (a.learner_reflection) {
                    html += '<div class="bg-light rounded p-2 mt-1"><small class="text-muted"><em>Student:</em> ' + self.esc(a.learner_reflection) + '</small></div>';
                }
                // Teacher feedback
                if (a.teacher_feedback) {
                    html += '<div class="bg-info bg-opacity-10 rounded p-2 mt-1"><small class="text-primary"><em>Teacher:</em> ' + self.esc(a.teacher_feedback) + '</small></div>';
                }
                if (a.file_path) {
                    html += '<a href="' + self.esc(a.file_path) + '" target="_blank" class="btn btn-outline-primary btn-sm mt-1"><i class="bi bi-eye me-1"></i>View</a>';
                }
                html += '</div></div></div>';
            });
            html += '</div>';
        }
        content.innerHTML = html;
    },

    printPortfolio: async function () {
        var stdIdEl = document.getElementById('studentDetailContent');
        var studentId = this.state.currentStudentId;
        if (!studentId) { await window.infoDialog('Notice', 'No student selected.'); return; }
        if (window.PrintManager) {
            window.PrintManager.printPortfolio({ student_id: studentId });
        } else {
            await window.infoDialog('Notice', 'Print service not available.');
        }
    },

    openMpesaModal(amount) {
        document.getElementById('mpesaAmount').value = amount || '';
        document.getElementById('mpesaPhone').value = '';
        this.setView('mpesaPaymentForm', true);
        this.setView('mpesaWaiting', false);
        document.getElementById('mpesaError').classList.add('d-none');
        var modal = bootstrap.Modal.getOrCreateInstance(document.getElementById('mpesaPaymentModal'));
        modal.show();
    },

    initiateMpesaPayment() {
        var amount = document.getElementById('mpesaAmount').value;
        var phone = document.getElementById('mpesaPhone').value.trim();
        var provider = document.getElementById('mpesaProvider').value;
        var errEl = document.getElementById('mpesaError');
        var spinner = document.getElementById('mpesaSpinner');
        var self = this;
        errEl.classList.add('d-none');
        if (!amount || parseFloat(amount) <= 0) { errEl.textContent = 'Invalid amount'; errEl.classList.remove('d-none'); return; }
        if (!phone) { errEl.textContent = 'Phone number is required'; errEl.classList.remove('d-none'); return; }
        spinner.classList.remove('d-none');
        this.apiFetch('/initiate-mpesa-payment', 'POST', {
            student_id: this.state.selectedStudentId,
            amount: parseFloat(amount),
            phone: phone,
            provider: provider
        }).then(function (resp) {
            var d = resp.data || resp;
            if (d.checkout_request_id) {
                self.setView('mpesaPaymentForm', false);
                self.setView('mpesaWaiting', true);
                self.startPolling(d.checkout_request_id);
            } else {
                errEl.textContent = d.message || 'Failed to initiate payment';
                errEl.classList.remove('d-none');
            }
        }).catch(function (err) {
            errEl.textContent = err.message || 'Payment initiation failed';
            errEl.classList.remove('d-none');
        }).finally(function () {
            spinner.classList.add('d-none');
        });
    },

    startPolling(checkoutRequestId) {
        var self = this;
        var attempts = 0;
        var maxAttempts = 30;
        var statusEl = document.getElementById('mpesaPollingStatus');
        this.state._mpesaPolling = setInterval(function () {
            attempts++;
            statusEl.textContent = 'Checking status... (' + attempts + '/' + maxAttempts + ')';
            if (attempts >= maxAttempts) {
                clearInterval(self.state._mpesaPolling);
                statusEl.textContent = 'Payment confirmation timed out. Check your M-Pesa messages.';
                return;
            }
            self.checkMpesaStatus(checkoutRequestId, function (done) {
                if (done) {
                    clearInterval(self.state._mpesaPolling);
                    statusEl.textContent = 'Payment confirmed! Refreshing...';
                    var modal = bootstrap.Modal.getInstance(document.getElementById('mpesaPaymentModal'));
                    if (modal) modal.hide();
                    self.loadBalanceSummary(self.state.selectedStudentId);
                    self.loadStudentTab('fees');
                }
            });
        }, 4000);
    },

    checkMpesaStatus(checkoutRequestId, callback) {
        this.apiFetch('/mpesa-status/' + checkoutRequestId, 'GET')
            .then(function (resp) {
                var d = resp.data || resp;
                if (d.ResultCode === '0' || d.resultCode === '0' || d.status === 'completed') {
                    callback(true);
                }
            }).catch(function () {});
    },

    resetMpesaModal() {
        if (this.state._mpesaPolling) {
            clearInterval(this.state._mpesaPolling);
            this.state._mpesaPolling = null;
        }
        this.setView('mpesaPaymentForm', true);
        this.setView('mpesaWaiting', false);
    },

    storeAuth(token, expiresAt, parent, csrfToken) {
        this.state.token = token;
        sessionStorage.setItem('pp_token', obfuscate(token));
        sessionStorage.setItem('pp_expires', expiresAt || '');
        if (csrfToken) {
            sessionStorage.setItem('pp_csrf', csrfToken);
            if (window.AuthContext && typeof window.AuthContext.setCsrfToken === 'function') {
                window.AuthContext.setCsrfToken(csrfToken);
            }
        }
        if (parent) {
            var nameEl = document.getElementById('parentName');
            if (nameEl) nameEl.textContent = parent.first_name || 'Parent';
        }
    },

    stashGuardianForAdmission(parent) {
        try {
            var name = [parent.first_name, parent.last_name].filter(Boolean).join(' ').trim();
            var email = (parent.email || '').trim();
            var phone = (parent.phone || '').trim();
            if (name || email || phone) {
                sessionStorage.setItem('kw_admissions_guardian', JSON.stringify({
                    parent_name: name,
                    parent_email: email,
                    parent_phone: phone,
                }));
            }
        } catch (e) { /* sessionStorage may be unavailable; prefill is optional */ }
    },

    /* ── In-page "Apply for Admission" modal ─────────────────────────────── */
    openApplyAdmissionModal() {
        var self = this;
        this.prefillGuardianFromSession();
        var modalEl = document.getElementById('applyAdmissionModal');
        if (!modalEl || !window.bootstrap) return;
        var modal = bootstrap.Modal.getOrCreateInstance(modalEl);
        modal.show();
        var form = document.getElementById('applyAdmissionForm');
        if (form) {
            // Remove a previous one-off submit listener before adding a new one.
            if (form.__applySubmit) form.removeEventListener('submit', form.__applySubmit);
            form.__applySubmit = function (e) { e.preventDefault(); self.submitApplyAdmission(form); };
            form.addEventListener('submit', form.__applySubmit);
        }
    },

    prefillGuardianFromSession() {
        var raw = sessionStorage.getItem('kw_admissions_guardian');
        if (!raw) return;
        var g;
        try { g = JSON.parse(raw); } catch (_) { return; }
        var fields = {
            ppParentName: g.parent_name,
            ppParentPhone: g.parent_phone,
            ppParentEmail: g.parent_email,
        };
        Object.keys(fields).forEach(function (id) {
            if (!fields[id]) return;
            var el = document.getElementById(id);
            if (el && !el.value) el.value = fields[id];
        });
    },

    submitApplyAdmission(form) {
        var self = this;
        var msg = document.getElementById('ppApplyMsg');
        var btn = document.getElementById('ppApplySubmit');
        msg.classList.add('d-none');
        var decl = document.getElementById('ppDeclaration');
        if (!decl || !decl.checked) {
            if (decl) decl.focus();
            msg.textContent = 'Please confirm the declaration before submitting.';
            msg.className = 'alert alert-warning mt-2 mb-0';
            return;
        }

        var fd = new FormData(form);
        // Attach the resolved academic_year_terms.id for the start term.
        var termSel = document.getElementById('ppPreferredStart');
        var termId = termSel && termSel.selectedOptions && termSel.selectedOptions[0]
            ? termSel.selectedOptions[0].dataset.termId : null;
        if (termId) fd.append('target_term_id', termId);

        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Submitting…';
        apiCall('/public/applications', 'POST', fd, null, {
            isFile: true,
            noRedirect: true,
            checkPermission: false,
        })
            .then(function (json) {
                json = json && json.data !== undefined ? json.data : json;
                if (json && json.success) {
                    msg.textContent = 'Application submitted successfully. Reference: ' + (json.ref || json.application_no || '');
                    msg.className = 'alert alert-success mt-2 mb-0';
                    form.reset();
                    var modal = document.getElementById('applyAdmissionModal');
                    if (modal) setTimeout(function () {
                        var inst = bootstrap.Modal.getInstance(modal);
                        if (inst) inst.hide();
                    }, 1800);
                } else {
                    msg.textContent = json.message || 'Submission failed. Please try again.';
                    msg.className = 'alert alert-danger mt-2 mb-0';
                }
            })
            .catch(function () {
                msg.textContent = 'Network error. Please try again or call 0720 113 030.';
                msg.className = 'alert alert-danger mt-2 mb-0';
            })
            .finally(function () {
                btn.disabled = false;
                btn.innerHTML = '<i class="bi bi-send me-1"></i>Submit Application';
            });
    },

    clearAuth() {
        this.state.token = null;
        this.state.initialRouteApplied = false;
        sessionStorage.removeItem('pp_token');
        sessionStorage.removeItem('pp_expires');
        sessionStorage.removeItem('pp_csrf');
        sessionStorage.removeItem('kw_admissions_guardian');
    },

    setLoading(on) {
        var el = document.getElementById('portal-loading');
        if (el) el.style.display = on ? 'block' : 'none';
    },

    showErr(el, msg) { el.textContent = msg; el.classList.remove('d-none'); },
    val(id) { return (document.getElementById(id) || {}).value || ''; },
    setText(id, txt) { var el = document.getElementById(id); if (el) el.textContent = txt; },
    on(id, ev, fn) { var el = document.getElementById(id); if (el) el.addEventListener(ev, fn); },
    setView(id, visible) { var el = document.getElementById(id); if (el) el.style.display = visible ? 'block' : 'none'; },
    esc(s) {
        return String(s || '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }
};

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', function () { parentPortalController.init().catch(function () {}); });
} else {
    parentPortalController.init().catch(function () {});
}

window.parentPortalController = parentPortalController;
