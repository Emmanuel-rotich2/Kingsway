/**
 * Parent Portal Controller
 * Standalone SPA: auth → dashboard → student detail
 * Uses its own fetch wrapper (not window.API — that requires staff JWT)
 */
(function () {
  'use strict';

  var BASE = window.APP_BASE || '';
  var API_BASE = BASE + '/api/parent-portal';

  var state = {
    token: null,
    otpSessionId: null,
    selectedStudentId: null,
    activeDetailTab: 'fees',
  };

  // ============================================================
  // INIT
  // ============================================================

  function init() {
    var stored  = localStorage.getItem('pp_token');
    var expires = localStorage.getItem('pp_expires');
    if (stored && expires && new Date(expires) > new Date()) {
      state.token = stored;
      showView('dashboard');
      loadDashboard();
    } else {
      clearAuth();
      showView('auth');
    }
    bindEvents();
  }

  // ============================================================
  // VIEW
  // ============================================================

  function showView(name) {
    document.querySelectorAll('.view').forEach(function (v) { v.classList.remove('active'); });
    var el = document.getElementById('view-' + name);
    if (el) el.classList.add('active');
  }

  // ============================================================
  // API HELPER
  // ============================================================

  function apiFetch(path, method, body) {
    var opts = { method: method || 'GET', headers: { 'Content-Type': 'application/json' } };
    if (state.token) opts.headers['Authorization'] = 'Bearer ' + state.token;
    if (body) opts.body = JSON.stringify(body);
    return fetch(API_BASE + path, opts)
      .then(function (r) { return r.json(); })
      .then(function (r) {
        if (r.status === 'error') throw new Error(r.message || 'Request failed');
        return r;
      });
  }

  // ============================================================
  // AUTH EVENTS
  // ============================================================

  function bindEvents() {
    // Login tab switch
    document.querySelectorAll('#loginTabs .nav-link').forEach(function (btn) {
      btn.addEventListener('click', function () {
        document.querySelectorAll('#loginTabs .nav-link').forEach(function (b) { b.classList.remove('active'); });
        btn.classList.add('active');
        set('tab-email', btn.dataset.tab === 'email');
        set('tab-otp',   btn.dataset.tab === 'otp');
      });
    });

    // Password toggle
    on('togglePwd', 'click', function () {
      var pwd = document.getElementById('loginPassword');
      var icon = document.querySelector('#togglePwd i');
      if (pwd.type === 'password') { pwd.type = 'text'; icon.className = 'bi bi-eye-slash'; }
      else { pwd.type = 'password'; icon.className = 'bi bi-eye'; }
    });

    // Email login
    on('btnEmailLogin', 'click', submitEmailLogin);
    on('loginPassword', 'keydown', function (e) { if (e.key === 'Enter') submitEmailLogin(); });

    // OTP
    on('btnRequestOtp', 'click', requestOTP);
    on('btnVerifyOtp',  'click', verifyOTP);
    on('btnResendOtp',  'click', function () { set('otp-step-2', false); set('otp-step-1', true); });

    // Logout / back
    on('btnLogout',          'click', logout);
    on('btnBackToDashboard', 'click', function () { showView('dashboard'); });

    // Student detail tabs
    document.querySelectorAll('#studentDetailTabs .nav-link').forEach(function (btn) {
      btn.addEventListener('click', function () {
        document.querySelectorAll('#studentDetailTabs .nav-link').forEach(function (b) { b.classList.remove('active'); });
        btn.classList.add('active');
        state.activeDetailTab = btn.dataset.tab;
        loadStudentTab(btn.dataset.tab);
      });
    });
  }

  // ============================================================
  // AUTH ACTIONS
  // ============================================================

  function submitEmailLogin() {
    var email    = val('loginEmail');
    var password = val('loginPassword');
    var errEl    = document.getElementById('loginError');
    var spinner  = document.getElementById('loginSpinner');

    errEl.classList.add('d-none');
    if (!email || !password) { showErr(errEl, 'Email and password are required'); return; }
    spinner.classList.remove('d-none');

    apiFetch('/login', 'POST', { email: email, password: password })
      .then(function (resp) {
        var d = resp.data || resp;
        storeAuth(d.token, d.expires_at, d.parent);
        showView('dashboard');
        loadDashboard();
      })
      .catch(function (err) { showErr(errEl, err.message || 'Login failed'); })
      .finally(function () { spinner.classList.add('d-none'); });
  }

  function requestOTP() {
    var phone = val('otpPhone');
    var errEl = document.getElementById('otpRequestError');
    errEl.classList.add('d-none');
    if (!phone) { showErr(errEl, 'Phone number required'); return; }

    apiFetch('/login-otp-request', 'POST', { phone: phone })
      .then(function (resp) {
        var d = resp.data || resp;
        state.otpSessionId = d.otp_session_id;
        set('otp-step-1', false);
        set('otp-step-2', true);
      })
      .catch(function (err) { showErr(errEl, err.message || 'Failed to send OTP'); });
  }

  function verifyOTP() {
    var code  = val('otpCode');
    var errEl = document.getElementById('otpVerifyError');
    errEl.classList.add('d-none');
    if (!code || !state.otpSessionId) { showErr(errEl, 'Enter the OTP code'); return; }

    apiFetch('/login-otp-verify', 'POST', { otp_session_id: state.otpSessionId, otp_code: code })
      .then(function (resp) {
        var d = resp.data || resp;
        storeAuth(d.token, d.expires_at, d.parent);
        showView('dashboard');
        loadDashboard();
      })
      .catch(function (err) { showErr(errEl, err.message || 'Invalid OTP'); });
  }

  function logout() {
    apiFetch('/logout', 'POST').catch(function () {});
    clearAuth();
    showView('auth');
  }

  // ============================================================
  // DASHBOARD
  // ============================================================

  function loadDashboard() {
    setLoading(true);
    apiFetch('/dashboard', 'GET')
      .then(function (resp) {
        var d      = resp.data || resp;
        var parent = d.parent || {};
        var nameEl = document.getElementById('parentName');
        if (nameEl) nameEl.textContent = parent.first_name || 'Parent';
        renderChildren(d.children || []);
      })
      .catch(function (err) {
        document.getElementById('childrenCards').innerHTML =
          '<div class="col-12"><div class="alert alert-danger">Failed to load dashboard: ' + esc(err.message) + '</div></div>';
      })
      .finally(function () { setLoading(false); });
  }

  function renderChildren(children) {
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
        '<div class="card border-0 shadow-sm rounded-4 child-card h-100" onclick="window.__pp.openStudent(' + c.id + ',\'' + esc(c.first_name + ' ' + c.last_name) + '\',\'' + esc(c.class_name || '') + '\')">' +
        '<div class="card-body p-4">' +
        '<div class="d-flex align-items-center mb-3">' +
        '<div class="rounded-circle bg-primary bg-opacity-10 text-primary d-flex align-items-center justify-content-center me-3" style="width:48px;height:48px;font-size:1.4rem;font-weight:700">' +
        esc((c.first_name || '?')[0].toUpperCase()) + '</div>' +
        '<div><h6 class="mb-0 fw-bold">' + esc(c.first_name + ' ' + c.last_name) + '</h6>' +
        '<small class="text-muted">' + esc(c.class_name || '') + ' · ' + esc(c.admission_no || '') + '</small></div></div>' +
        '<div class="d-flex justify-content-between align-items-center">' +
        '<span class="text-muted small">Current Balance</span>' +
        '<span class="badge bg-' + bc + ' px-3 py-2">' + balTxt + '</span></div>' +
        (c.last_payment_date ? '<small class="text-muted d-block mt-2">Last payment: ' + c.last_payment_date.substring(0, 10) + '</small>' : '') +
        '</div></div></div>';
    }).join('');
  }

  // ============================================================
  // STUDENT DETAIL
  // ============================================================

  function openStudent(studentId, studentName, className) {
    state.selectedStudentId = studentId;
    setText('studentDetailName',  studentName);
    setText('studentDetailClass', className);
    showView('student');
    loadBalanceSummary(studentId);
    // Reset to fees tab
    document.querySelectorAll('#studentDetailTabs .nav-link').forEach(function (b) { b.classList.remove('active'); });
    var first = document.querySelector('#studentDetailTabs .nav-link');
    if (first) first.classList.add('active');
    state.activeDetailTab = 'fees';
    loadStudentTab('fees');
  }

  function loadBalanceSummary(studentId) {
    apiFetch('/fee-balance/' + studentId, 'GET')
      .then(function (resp) {
        var d    = resp.data || resp;
        var rows = d.per_term || [];
        var cur  = rows[0] || {};
        var total = parseFloat(d.total_balance || 0);
        document.getElementById('balanceSummaryCards').innerHTML = [
          summCard('Total Outstanding', 'KES ' + total.toLocaleString(), total > 0 ? 'danger' : 'success', 'fas fa-wallet'),
          summCard('Current Term Due',  'KES ' + Number(cur.total_due  || 0).toLocaleString(), 'primary', 'fas fa-calendar'),
          summCard('Current Term Paid', 'KES ' + Number(cur.total_paid || 0).toLocaleString(), 'info', 'fas fa-check-circle'),
        ].join('');
      })
      .catch(function () {});
  }

  function summCard(label, value, color, icon) {
    return '<div class="col-md-4 mb-2"><div class="card border-0 shadow-sm rounded-4 bg-' + color + ' bg-opacity-10">' +
      '<div class="card-body p-3 d-flex align-items-center">' +
      '<div class="me-3 fs-3 text-' + color + '"><i class="' + icon + '"></i></div>' +
      '<div><div class="text-muted small">' + label + '</div><div class="fw-bold fs-6">' + value + '</div></div>' +
      '</div></div></div>';
  }

  function loadStudentTab(tab) {
    var id = state.selectedStudentId;
    var content = document.getElementById('studentDetailContent');
    content.innerHTML = '<div class="text-center py-4"><div class="spinner-border text-primary"></div></div>';

    if (tab === 'fees') {
      apiFetch('/student-fees/' + id, 'GET')
        .then(function (resp) { renderFeeHistory(resp.data || resp); })
        .catch(function (e) { content.innerHTML = '<div class="alert alert-danger">Failed to load fees: ' + esc(e.message) + '</div>'; });
    } else if (tab === 'payments') {
      apiFetch('/student-payment-history/' + id, 'GET')
        .then(function (resp) { renderPaymentHistory(resp.data || resp); })
        .catch(function () { content.innerHTML = '<div class="alert alert-danger">Failed to load payments</div>'; });
    } else if (tab === 'statement') {
      renderStatementView(id);
    }
  }

  function renderFeeHistory(data) {
    var content = document.getElementById('studentDetailContent');
    var years = data.academic_years || data || [];
    if (!years.length) { content.innerHTML = '<div class="alert alert-info">No fee history found.</div>'; return; }

    content.innerHTML = years.map(function (yr) {
      return '<div class="card mb-3 border-0 shadow-sm">' +
        '<div class="card-header bg-primary text-white fw-bold">Academic Year ' + yr.year + '</div>' +
        '<div class="card-body">' +
        (yr.terms || []).map(function (term) {
          var rows = (term.obligations || []).map(function (o) {
            var sc = o.payment_status === 'paid' ? 'success' : (o.payment_status === 'partial' ? 'warning' : 'danger');
            return '<tr><td>' + esc(o.fee_type_name || '') + '</td>' +
              '<td>KES ' + Number(o.amount_due  || 0).toLocaleString() + '</td>' +
              '<td>KES ' + Number(o.amount_paid || 0).toLocaleString() + '</td>' +
              '<td><strong>KES ' + Number(o.balance || 0).toLocaleString() + '</strong></td>' +
              '<td><span class="badge bg-' + sc + '">' + esc(o.payment_status || 'pending') + '</span></td></tr>';
          }).join('');
          return '<h6 class="text-muted mb-2">' + esc(term.term_name || '') + '</h6>' +
            '<table class="table table-sm table-bordered mb-3"><thead class="table-light"><tr><th>Fee Type</th><th>Billed</th><th>Paid</th><th>Balance</th><th>Status</th></tr></thead>' +
            '<tbody>' + rows + '</tbody>' +
            '<tfoot class="fw-bold table-light"><tr><td>Total</td>' +
            '<td>KES ' + Number(term.total_due  || 0).toLocaleString() + '</td>' +
            '<td>KES ' + Number(term.total_paid || 0).toLocaleString() + '</td>' +
            '<td>KES ' + Number(term.balance    || 0).toLocaleString() + '</td><td></td></tr></tfoot></table>';
        }).join('') +
        '</div></div>';
    }).join('');
  }

  function renderPaymentHistory(payments) {
    var content = document.getElementById('studentDetailContent');
    if (!payments || !payments.length) { content.innerHTML = '<div class="alert alert-info">No payment records found.</div>'; return; }
    var rows = payments.map(function (p) {
      return '<tr><td>' + esc((p.payment_date || '').substring(0, 10)) + '</td>' +
        '<td><span class="badge bg-secondary">' + esc(p.payment_method || '') + '</span></td>' +
        '<td>KES ' + Number(p.amount_paid || 0).toLocaleString() + '</td>' +
        '<td>' + esc(p.receipt_no || '—') + '</td>' +
        '<td>' + esc(p.reference_no || '—') + '</td>' +
        '<td>' + esc(p.term_name || '—') + '</td></tr>';
    }).join('');
    document.getElementById('studentDetailContent').innerHTML =
      '<div class="table-responsive"><table class="table table-hover"><thead class="table-light"><tr>' +
      '<th>Date</th><th>Method</th><th>Amount</th><th>Receipt #</th><th>Reference</th><th>Term</th>' +
      '</tr></thead><tbody>' + rows + '</tbody></table></div>';
  }

  function renderStatementView(studentId) {
    var content = document.getElementById('studentDetailContent');
    content.innerHTML = '<div class="text-center py-3">' +
      '<p class="text-muted">Generate a printable fee statement for this student.</p>' +
      '<button class="btn btn-primary" id="btnGenStmt"><i class="fas fa-file-invoice me-2"></i>Generate Statement</button></div>';

    document.getElementById('btnGenStmt').addEventListener('click', function () {
      var btn = this;
      btn.disabled = true;
      btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Loading...';
      apiFetch('/student-statement/' + studentId, 'GET')
        .then(function (resp) {
          var d   = resp.data || resp;
          var win = window.open('', '_blank');
          win.document.write(buildStatementHTML(d));
          win.document.close();
          win.print();
        })
        .catch(function (e) {
          content.innerHTML = '<div class="alert alert-danger">Failed to generate statement: ' + esc(e.message) + '</div>';
        })
        .finally(function () { btn.disabled = false; btn.innerHTML = '<i class="fas fa-file-invoice me-2"></i>Generate Statement'; });
    });
  }

  function buildStatementHTML(data) {
    var s    = data.student || {};
    var fees = (data.fees && data.fees.academic_years) || [];
    var pmts = data.payments || [];

    var feeRows = fees.map(function (yr) {
      return '<h5>Academic Year ' + yr.year + '</h5>' +
        (yr.terms || []).map(function (t) {
          return '<p><strong>' + esc(t.term_name || '') + '</strong></p>' +
            '<table border="1" cellpadding="4" style="border-collapse:collapse;width:100%"><thead><tr><th>Fee Type</th><th>Amount Due</th><th>Paid</th><th>Balance</th></tr></thead><tbody>' +
            (t.obligations || []).map(function (o) {
              return '<tr><td>' + esc(o.fee_type_name || '') + '</td>' +
                '<td>KES ' + Number(o.amount_due || 0).toLocaleString() + '</td>' +
                '<td>KES ' + Number(o.amount_paid || 0).toLocaleString() + '</td>' +
                '<td>KES ' + Number(o.balance || 0).toLocaleString() + '</td></tr>';
            }).join('') +
            '</tbody></table>';
        }).join('');
    }).join('');

    var pmtRows = pmts.map(function (p) {
      return '<tr><td>' + esc((p.payment_date || '').substring(0, 10)) + '</td>' +
        '<td>' + esc(p.payment_method || '') + '</td>' +
        '<td>KES ' + Number(p.amount_paid || 0).toLocaleString() + '</td>' +
        '<td>' + esc(p.receipt_no || '—') + '</td></tr>';
    }).join('');

    return '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Fee Statement</title>' +
      '<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">' +
      '</head><body class="p-4">' +
      '<h3 class="text-center">Kingsway Preparatory School</h3>' +
      '<h5 class="text-center text-muted">Fee Statement</h5><hr>' +
      '<p><strong>Student:</strong> ' + esc(s.first_name + ' ' + s.last_name) +
      ' &nbsp; <strong>Adm No:</strong> ' + esc(s.admission_no || '') +
      ' &nbsp; <strong>Class:</strong> ' + esc(s.class_name || '') + '</p>' +
      '<p><strong>Generated:</strong> ' + esc(data.generated_at || '') + '</p><hr>' +
      feeRows +
      (pmtRows ? '<h5 class="mt-4">Payment History</h5><table border="1" cellpadding="4" style="border-collapse:collapse;width:100%"><thead><tr><th>Date</th><th>Method</th><th>Amount</th><th>Receipt #</th></tr></thead><tbody>' + pmtRows + '</tbody></table>' : '') +
      '</body></html>';
  }

  // ============================================================
  // UTILS
  // ============================================================

  function storeAuth(token, expiresAt, parent) {
    state.token = token;
    localStorage.setItem('pp_token',   token);
    localStorage.setItem('pp_expires', expiresAt || '');
    if (parent) {
      var nameEl = document.getElementById('parentName');
      if (nameEl) nameEl.textContent = parent.first_name || 'Parent';
    }
  }

  function clearAuth() {
    state.token = null;
    localStorage.removeItem('pp_token');
    localStorage.removeItem('pp_expires');
  }

  function setLoading(on) {
    var el = document.getElementById('portal-loading');
    if (el) el.style.display = on ? 'block' : 'none';
  }

  function showErr(el, msg) { el.textContent = msg; el.classList.remove('d-none'); }
  function val(id) { return (document.getElementById(id) || {}).value || ''; }
  function setText(id, txt) { var el = document.getElementById(id); if (el) el.textContent = txt; }
  function on(id, ev, fn) { var el = document.getElementById(id); if (el) el.addEventListener(ev, fn); }
  function set(id, visible) { var el = document.getElementById(id); if (el) el.style.display = visible ? 'block' : 'none'; }
  function esc(s) {
    return String(s || '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
  }

  // Expose for inline onclick handlers
  window.__pp = { openStudent: openStudent };

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
