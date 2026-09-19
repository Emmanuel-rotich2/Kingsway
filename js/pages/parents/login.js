/**
 * js/pages/parents/login.js — standalone parent sign-in entry.
 *
 * Handles email+password and email-OTP authentication. A successful sign in
 * stores the pp_token (via ParentCommon) and forwards to the multi-page
 * cpanel at parents/dashboard.php.
 */
(function () {
  'use strict';

  var P = window.ParentCommon;
  var state = { otpSessionId: null };
  var _submitting = false;
  var _redirecting = false;

  function setView(id, visible) {
    var el = document.getElementById(id);
    if (el) el.style.display = visible ? 'block' : 'none';
  }
  function setTab(which) {
    document.querySelectorAll('#loginTabs .nav-link').forEach(function (b) {
      b.classList.toggle('active', b.dataset.tab === which);
    });
    setView('tab-email', which === 'email');
    setView('tab-otp', which === 'otp');
  }
  function showErr(el, msg) {
    if (!el) return;
    el.textContent = msg;
    el.classList.remove('d-none');
  }
  function val(id) {
    return (document.getElementById(id) || {}).value || '';
  }

  function togglePwd() {
    var pwd = document.getElementById('loginPassword');
    var icon = document.querySelector('#togglePwd i');
    if (!pwd || !icon) return;
    if (pwd.type === 'password') {
      pwd.type = 'text';
      icon.className = 'bi bi-eye-slash';
    } else {
      pwd.type = 'password';
      icon.className = 'bi bi-eye';
    }
  }

  function goToDashboard() {
    if (window.FAMILY_STAFF_MODE) {
      window.location.replace((window.APP_BASE || '') + '/my_family.php');
      return;
    }
    var next = new URLSearchParams(window.location.search).get('next');
    var target = next && /^\/parents\/[\w\-./]+\.php/.test(next)
      ? (window.APP_BASE || '') + next
      : (window.APP_BASE || '') + '/parents/dashboard.php';
    window.location.replace(target);
  }

  function alreadyAuthed() {
    var token = P.getToken();
    var expires = sessionStorage.getItem('pp_expires');
    return Boolean(token && expires && new Date(expires) > new Date());
  }

  function submitEmailLogin() {
    var email = val('loginEmail');
    var password = val('loginPassword');
    var errEl = document.getElementById('loginError');
    var spinner = document.getElementById('loginSpinner');
    errEl.classList.add('d-none');
    if (!email || !password) {
      showErr(errEl, 'Email or phone number and password are required');
      return;
    }
    var btn = document.getElementById('btnEmailLogin');
    spinner.classList.remove('d-none');
    btn.disabled = true;
    P.apiFetch('/login', 'POST', { email: email, password: password })
      .then(function (resp) {
        var d = resp.data !== undefined ? resp.data : resp;
        if (d.portal_mismatch === 'staff') {
          _redirecting = true;
          showErr(errEl, d.message || 'This account uses the school staff workspace, not the Parent Portal.');
          var staffBtn = document.getElementById('btnEmailLogin');
          if (staffBtn) staffBtn.disabled = true;
          setTimeout(function () {
            window.location.replace(d.staff_login_url || (window.APP_BASE || '') + '/login.php');
          }, 1800);
          return;
        }
        if (d.requires_otp) {
          state.otpSessionId = d.otp_session_id;
          var otpEmail = document.getElementById('otpEmail');
          if (otpEmail) otpEmail.value = email;
          setView('tab-email', false);
          setView('tab-otp', true);
          setView('otp-step-1', false);
          setView('otp-step-2', true);
          return;
        }
        P.setToken(d.token, d.expires_at);
        if (d.parent) P.storeGuardian(d.parent);
        goToDashboard();
      })
      .catch(function (err) {
        if (!_redirecting) showErr(errEl, err.message || 'Login failed');
      })
      .finally(function () {
        if (!_redirecting) {
          spinner.classList.add('d-none');
          btn.disabled = false;
        }
      });
  }

  function requestOTP() {
    var email = val('otpEmail');
    var errEl = document.getElementById('otpRequestError');
    errEl.classList.add('d-none');
    if (!email) {
      showErr(errEl, 'Email address required');
      return;
    }
    var btn = document.getElementById('btnRequestOtp');
    btn.disabled = true;
    P.apiFetch('/login-otp-request', 'POST', { email: email })
      .then(function (resp) {
        var d = resp.data !== undefined ? resp.data : resp;
        if (!d.otp_session_id) throw new Error('If the account exists, check its email for a verification code.');
        state.otpSessionId = d.otp_session_id;
        setView('otp-step-1', false);
        setView('otp-step-2', true);
      })
      .catch(function (err) {
        showErr(errEl, err.message || 'Failed to send OTP');
      })
      .finally(function () {
        btn.disabled = false;
      });
  }

  function verifyOTP() {
    var code = val('otpCode');
    var errEl = document.getElementById('otpVerifyError');
    errEl.classList.add('d-none');
    if (!code || !state.otpSessionId) {
      showErr(errEl, 'Enter the OTP code');
      return;
    }
    if (_submitting) return;
    _submitting = true;
    var btn = document.getElementById('btnVerifyOtp');
    btn.disabled = true;
    P.apiFetch('/login-otp-verify', 'POST', {
      otp_session_id: state.otpSessionId,
      otp_code: code,
    })
      .then(function (resp) {
        var d = resp.data !== undefined ? resp.data : resp;
        P.setToken(d.token, d.expires_at);
        if (d.csrf_token) {
          window.AuthContext && window.AuthContext.setCsrfToken
            ? window.AuthContext.setCsrfToken(d.csrf_token)
            : sessionStorage.setItem('pp_csrf', d.csrf_token);
        }
        if (d.parent) P.storeGuardian(d.parent);
        goToDashboard();
      })
      .catch(function (err) {
        showErr(errEl, err.message || 'Invalid OTP');
      })
      .finally(function () {
        _submitting = false;
        btn.disabled = false;
      });
  }

  function resetOtp() {
    state.otpSessionId = null;
    setView('tab-otp', false);
    setView('tab-email', true);
    var pwd = document.getElementById('loginPassword');
    if (pwd) pwd.value = '';
    (document.getElementById('loginEmail') || {}).focus && document.getElementById('loginEmail').focus();
  }

  function init() {
    if (window.FAMILY_STAFF_MODE && window.AuthContext && typeof window.AuthContext.ready === 'function') {
      window.AuthContext.ready().then(function () {
        if (window.AuthContext.isAuthenticated && window.AuthContext.isAuthenticated()) {
          goToDashboard();
        }
      });
      return;
    }
    if (alreadyAuthed()) {
      goToDashboard();
      return;
    }
    P.clearAuth();

    var toggleBtn = document.getElementById('togglePwd');
    if (toggleBtn) toggleBtn.addEventListener('click', togglePwd);
    var emailBtn = document.getElementById('btnEmailLogin');
    if (emailBtn) emailBtn.addEventListener('click', submitEmailLogin);
    var pwdInput = document.getElementById('loginPassword');
    if (pwdInput) pwdInput.addEventListener('keydown', function (e) { if (e.key === 'Enter') submitEmailLogin(); });
    var otpReqBtn = document.getElementById('btnRequestOtp');
    if (otpReqBtn) otpReqBtn.addEventListener('click', requestOTP);
    var otpCodeInput = document.getElementById('otpCode');
    if (otpCodeInput) {
      otpCodeInput.addEventListener('keydown', function (e) { if (e.key === 'Enter') verifyOTP(); });
      otpCodeInput.addEventListener('input', function () {
        var v = (otpCodeInput.value || '').trim();
        if (state.otpSessionId && /^\d{6}$/.test(v)) verifyOTP();
      });
    }
    var verifyBtn = document.getElementById('btnVerifyOtp');
    if (verifyBtn) verifyBtn.addEventListener('click', verifyOTP);
    var resendBtn = document.getElementById('btnResendOtp');
    if (resendBtn) resendBtn.addEventListener('click', resetOtp);
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();