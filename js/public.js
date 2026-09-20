/* =============================================================================
   Kingsway Public Website — Interactions & Animations
   ============================================================================= */

/* PublicUI — shared scroll-reveal + count-up observers. Public page controllers
 * render their content asynchronously from the REST API, so after injecting
 * markup they re-call PublicUI.observeReveals(container) and
 * PublicUI.observeCounters(container) to wire up the newly added elements. */
window.PublicUI = (() => {
  'use strict';

  function observeReveals(root) {
    const io = new IntersectionObserver((entries) => {
      entries.forEach(e => { if (e.isIntersecting) { e.target.classList.add('visible'); io.unobserve(e.target); } });
    }, { threshold: 0.12, rootMargin: '0px 0px -40px 0px' });
    (root || document).querySelectorAll('.reveal').forEach(el => io.observe(el));
  }

  function observeCounters(root) {
    const counterIO = new IntersectionObserver((entries) => {
      entries.forEach(e => {
        if (!e.isIntersecting) return;
        const el = e.target;
        const target = +el.dataset.target;
        const suffix = el.dataset.suffix || '';
        const prefix = el.dataset.prefix || '';
        const duration = 2000;
        const start = performance.now();
        counterIO.unobserve(el);
        const tick = (now) => {
          const elapsed = Math.min(1, (now - start) / duration);
          const ease = 1 - Math.pow(1 - elapsed, 4);
          el.textContent = prefix + Math.round(ease * target).toLocaleString() + suffix;
          if (elapsed < 1) requestAnimationFrame(tick);
        };
        requestAnimationFrame(tick);
      });
    }, { threshold: 0.5 });
    (root || document).querySelectorAll('[data-target]').forEach(el => counterIO.observe(el));
  }

  return { observeReveals, observeCounters };
})();

document.addEventListener('DOMContentLoaded', () => {

  // WebAuthn wire-format helpers. The PHP relying-party library returns
  // base64url values; the browser API requires ArrayBuffers.
  window.arrayBufferToBase64 = (buffer) => {
    let binary = ''; const bytes = new Uint8Array(buffer);
    bytes.forEach((b) => { binary += String.fromCharCode(b); });
    return btoa(binary);
  };
  window.recursiveBase64StrToArrayBuffer = (value, key = '') => {
    if (Array.isArray(value)) return value.map((v) => window.recursiveBase64StrToArrayBuffer(v, key));
    if (value && typeof value === 'object') { Object.keys(value).forEach((k) => { value[k] = window.recursiveBase64StrToArrayBuffer(value[k], k); }); return value; }
    if (typeof value === 'string' && ['challenge', 'id'].includes(key)) {
      const normalized = value.replace(/-/g, '+').replace(/_/g, '/') + '='.repeat((4 - value.length % 4) % 4);
      const binary = atob(normalized); const bytes = new Uint8Array(binary.length);
      for (let i = 0; i < binary.length; i++) bytes[i] = binary.charCodeAt(i);
      return bytes;
    }
    return value;
  };

  window.PublicUI.observeReveals(document);
  window.PublicUI.observeCounters(document);

  /* ── Navbar scroll behaviour ──────────────────────────────────────────────── */
  const nav = document.querySelector('.site-nav');
  if (nav) {
    const onScroll = () => nav.classList.toggle('scrolled', window.scrollY > 40);
    window.addEventListener('scroll', onScroll, { passive: true });
    onScroll();
  }

  /* ── Announcement ticker — render + pause on hover (shared site-wide) ───── */
  // Markup lives in public/layout/header.php; this fills #site-ticker on every
  // public page. Items are duplicated so the CSS marquee (translateX(-50%))
  // loops seamlessly.
  async function renderSiteTicker() {
    const track = document.getElementById('site-ticker');
    if (!track || !window.PublicSite) return;
    try {
      const data = await window.PublicSite.get('news', { limit: 3 }, { tier: 'dynamic' });
      const list = window.PublicSite.items(data).slice(0, 3);
      if (!list.length) return;
      const base = String(window.APP_BASE || '').replace(/\/+$/, '');
      const S = window.PublicSite.escapeHtml;
      const spans = list.map((n) =>
        '<span><a href="' + base + '/index.php?route=rfcb132e4845b&id=' + encodeURIComponent(n.id) + '">' + S(n.title) + '</a></span>'
      ).join('');
      track.innerHTML = spans + spans;
    } catch (err) {
      if (window.KINGSWAY_DEBUG) console.warn('[ticker] render failed:', err);
    }
  }
  renderSiteTicker();

  /* ── Announcement ticker pause on hover ──────────────────────────────────── */
  const ticker = document.querySelector('.ticker-track');
  if (ticker) {
    ticker.addEventListener('mouseenter', () => ticker.style.animationPlayState = 'paused');
    ticker.addEventListener('mouseleave', () => ticker.style.animationPlayState = 'running');
  }

  /* ── Active nav link ─────────────────────────────────────────────────────── */
  // Public routes are anonymised tokens (index.php?route=r<hex>). The server
  // injects the current page key (PUBLIC_ROUTE_KEY) and a token→key map
  // (PUBLIC_ROUTE_MAP); resolve any legacy keys here so highlighting survives
  // both token and plain-key URLs.
  const routeQs = new URLSearchParams(window.location.search);
  const routeParam = routeQs.get('route');
  const routeMap = window.PUBLIC_ROUTE_MAP || {};
  const serverKey = (window.PUBLIC_ROUTE_KEY || '').toString();
  const currentKey = serverKey || (routeParam ? (routeMap[routeParam] || routeParam) : '');
  document.querySelectorAll('.site-nav .nav-link').forEach(link => {
    const href = link.getAttribute('href') || '';
    if (!href) return;
    const m = href.match(/[?&]route=([^&#]+)/);
    if (!m) {
      if (currentKey === 'home' && (href === 'index.php' || /\/index\.php$/.test(href))) link.classList.add('active');
      else if (!currentKey && (href === currentKey || href.endsWith('index.php'))) link.classList.add('active');
      return;
    }
    const linkKey = routeMap[m[1]] || m[1];
    if (currentKey && linkKey === currentKey) link.classList.add('active');
  });

  /* ── Login modal show/hide ─────────────────────────────────────────────────── */
  const togglePwd = document.getElementById('togglePassword');
  const pwdInput  = document.getElementById('loginPassword');
  const pwdIcon   = document.getElementById('togglePasswordIcon');
  if (togglePwd && pwdInput && pwdIcon) {
    togglePwd.addEventListener('click', () => {
      const isText = pwdInput.type === 'text';
      pwdInput.type = isText ? 'password' : 'text';
      pwdIcon.classList.toggle('bi-eye', isText);
      pwdIcon.classList.toggle('bi-eye-slash', !isText);
    });
  }

  /* ── Login form submission ─────────────────────────────────────────────────── */
  const loginForm   = document.getElementById('loginForm');
  const loginError  = document.getElementById('loginError');
  const loginErrTxt = document.getElementById('loginErrorText');
  const loginBtnTxt = document.getElementById('loginBtnText');
  const loginSpinner= document.getElementById('loginSpinner');
  const loginBtn    = document.getElementById('loginSubmitBtn');
  if (new URLSearchParams(window.location.search).get('login') === '1') {
    const modalElement = document.getElementById('loginModal');
    if (modalElement && window.bootstrap) {
      bootstrap.Modal.getOrCreateInstance(modalElement).show();
    }
  }

  function resetLoginBtn() {
    if (loginBtnTxt)  loginBtnTxt.classList.remove('d-none');
    if (loginSpinner) loginSpinner.classList.add('d-none');
    if (loginBtn)     loginBtn.disabled = false;
  }
  // Updates the spinner's text WITHOUT removing the spinner-border element.
  // Keeps the user informed across the multi-second login → dashboard window.
  function setSpinnerLabel(label) {
    if (!loginSpinner) return;
    const spinnerEl = loginSpinner.querySelector('.spinner-border');
    loginSpinner.textContent = '';
    if (spinnerEl) loginSpinner.appendChild(spinnerEl);
    loginSpinner.appendChild(document.createTextNode(' ' + label));
  }
  function showLoginErr(msg) {
    if (loginErrTxt) loginErrTxt.textContent = msg;
    if (loginError)  loginError.classList.remove('d-none');
    resetLoginBtn();
  }

  if (loginForm) {
    loginForm.addEventListener('submit', async (e) => {
      e.preventDefault();
      const username = loginForm.querySelector('[name="username"]')?.value;
      const password = loginForm.querySelector('[name="password"]')?.value;
      const rememberMe = Boolean(document.getElementById('rememberMe')?.checked);
      if (loginError)  loginError.classList.add('d-none');
      if (loginBtnTxt) loginBtnTxt.classList.add('d-none');
      if (loginSpinner)loginSpinner.classList.remove('d-none');
      if (loginBtn)    loginBtn.disabled = true;
      setSpinnerLabel('Verifying credentials…');
      try {
        const res = await API.auth.login(username, password, rememberMe);

        // ── 2FA challenge ─────────────────────────────────────────────
        if (res && res.requires_2fa) {
          // Hide login modal, show 2FA modal
          const loginModal = bootstrap.Modal.getInstance(document.getElementById('loginModal'));
          loginModal?.hide();
          await showTFAVerification(res, username, password, rememberMe);
          return;
        }
        // ── end 2FA challenge ──────────────────────────────────────────

        if (!res?.token) throw new Error(res?.message || 'Login failed. Check your credentials.');
        setSpinnerLabel('Preparing your dashboard…');
      } catch (err) {
        const message = err.message || 'Login failed. Please try again.';
        showLoginErr(message);

        // A lockout is important enough to surface beyond the compact inline
        // form error. The toast remains visible above the open login modal.
        if (/account\s+(?:is\s+)?locked|account locked/i.test(message)) {
          if (typeof window.showNotification === 'function') {
            window.showNotification(message, 'warning');
          } else if (typeof window.infoDialog === 'function') {
            window.infoDialog('Account temporarily locked', message, { danger: true });
          } else {
            window.alert(message);
          }
        }
      }
    });
  }

  document.getElementById('passwordlessPasskeyBtn')?.addEventListener('click', async (event) => {
    const button = event.currentTarget;
    if (!window.PublicKeyCredential || !navigator.credentials?.get) {
      showLoginErr('This browser or device does not support passkey sign-in.'); return;
    }
    button.disabled = true;
    try {
      const optionsResponse = await window.callAPI?.('/twofactor/passwordless-options', 'POST', {});
      const publicKey = recursiveBase64StrToArrayBuffer(optionsResponse?.data || optionsResponse);
      const credential = await navigator.credentials.get({ publicKey });
      const encoded = {
        id: credential.id,
        clientDataJSON: arrayBufferToBase64(credential.response.clientDataJSON),
        authenticatorData: arrayBufferToBase64(credential.response.authenticatorData),
        signature: arrayBufferToBase64(credential.response.signature),
        userHandle: credential.response.userHandle ? arrayBufferToBase64(credential.response.userHandle) : null,
      };
      const verified = await window.callAPI?.('/twofactor/passwordless-verify', 'POST', { credential: encoded });
      if (!verified?.verified || !verified?.user_id || !verified?.challenge_token) throw new Error('Passkey verification failed.');
      const loginRes = await window.API.auth.complete2FALogin(verified.user_id, Boolean(document.getElementById('rememberMe')?.checked), verified.challenge_token);
      if (!loginRes?.token) throw new Error(loginRes?.message || 'Passwordless sign-in failed.');
      const dashboard = window.AuthContext?.getDashboardInfo?.();
      window.location.href = dashboard?.key ? `${window.APP_BASE || ''}/home.php?route=${encodeURIComponent(dashboard.key)}` : `${window.APP_BASE || ''}/home.php`;
    } catch (error) {
      if (error?.name !== 'NotAllowedError') showLoginErr(error?.message || 'Passkey sign-in failed.');
    } finally { button.disabled = false; }
  });

  /* ── 2FA Verification ───────────────────────────────────────────────────── */
  let tfaState = null; // { userId, method, challengeToken, rememberMe }

  async function renewTFAChallenge() {
    if (!tfaState?.username || !tfaState?.password) {
      throw new Error('Your verification session expired. Return to login and sign in again.');
    }
    const fresh = await API.auth.login(tfaState.username, tfaState.password, tfaState.rememberMe);
    if (!fresh?.requires_2fa || !fresh?.challenge_token) {
      throw new Error(fresh?.message || 'A new verification session could not be created.');
    }
    tfaState.userId = fresh.user_id;
    tfaState.method = fresh.method;
    tfaState.challengeToken = fresh.challenge_token;
    const methodSelect = document.getElementById('tfaMethodSelect');
    if (methodSelect && [...methodSelect.options].some(option => option.value === fresh.method)) {
      methodSelect.value = fresh.method;
    }
    if (fresh.method !== 'totp' && fresh.method !== 'passkey') {
      await window.callAPI?.('/twofactor/challenge', 'POST', {
        challenge_token: fresh.challenge_token,
      });
    }
    startTFACountdown();
    return fresh;
  }

  function isExpiredTFAChallenge(error) {
    return /invalid or expired 2fa challenge/i.test(String(error?.message || ''));
  }

  window.showTFAVerification = async function (res, username, password, rememberMe) {
    tfaState = { userId: res.user_id, method: res.method, challengeToken: res.challenge_token, username, password, rememberMe, onComplete: res.onComplete };

    const modalEl = document.getElementById('tfaModal');
    const methodDesc = document.getElementById('tfaMethodDesc');
    const codeLabel  = document.getElementById('tfaCodeLabel');
    const resendRow  = document.getElementById('tfaResend');
    const resendBtn  = document.getElementById('tfaResendBtn');
    const tfaRecoveryBtn = document.getElementById('tfaRecoveryBtn');
    const tfaCode = document.getElementById('tfaCode');
    const tfaError = document.getElementById('tfaError');
    const tfaErrorText = document.getElementById('tfaErrorText');
    const passkeyBtn = document.getElementById('tfaPasskeyBtn');
    const picker = document.getElementById('tfaMethodPicker');
    const select = document.getElementById('tfaMethodSelect');
    const methods = Array.isArray(res.available_methods) ? res.available_methods : [res.method];
    if (methods.length > 1 && picker && select) {
      const labels = {totp:'Authenticator app',email:'Email',sms:'SMS',whatsapp:'WhatsApp'};
      select.innerHTML = methods.map(m => `<option value="${m}" ${m === res.method ? 'selected' : ''}>${labels[m] || m}</option>`).join('');
      picker.classList.remove('d-none');
      select.onchange = async () => {
        tfaState.method = select.value;
        passkeyBtn?.classList.toggle('d-none', tfaState.method !== 'passkey');
        tfaCode?.classList.toggle('d-none', tfaState.method === 'passkey');
        try { await window.callAPI?.('/twofactor/challenge', 'POST', { challenge_token: tfaState.challengeToken, method: tfaState.method }); showTFASuccess('Verification method selected.'); } catch (e) { showTFAError(e.message || 'Unable to select method'); }
      };
    } else if (picker) picker.classList.add('d-none');
    passkeyBtn?.classList.toggle('d-none', res.method !== 'passkey');
    tfaCode?.classList.toggle('d-none', res.method === 'passkey');

    // Reset
    tfaCode.value = '';
    tfaError.classList.add('d-none');
    hideTFASpinner();

    if (res.method === 'totp') {
      methodDesc.textContent = 'Enter the 6-digit verification code from your authenticator app.';
      codeLabel.textContent = 'Authentication Code (from app)';
      resendRow.classList.add('d-none');
      tfaRecoveryBtn.classList.remove('d-none');
    } else if (res.method === 'email') {
      methodDesc.textContent = 'A verification code has been sent to your email address.';
      codeLabel.textContent = 'Email Verification Code';
      resendRow.classList.remove('d-none');
      tfaRecoveryBtn.classList.add('d-none');
      startTFACountdown();
      // Auto-send OTP
      try {
        await window.callAPI?.('/twofactor/challenge', 'POST', { challenge_token: res.challenge_token });
      } catch (_) { /* ignore — code may already be sent */ }
    } else if (res.method === 'sms' || res.method === 'whatsapp') {
      methodDesc.textContent = 'A verification code has been sent to your phone.';
      codeLabel.textContent = res.method === 'whatsapp' ? 'WhatsApp Verification Code' : 'SMS Verification Code';
      resendRow.classList.remove('d-none');
      tfaRecoveryBtn.classList.add('d-none');
      startTFACountdown();
      try {
        await window.callAPI?.('/twofactor/challenge', 'POST', { challenge_token: res.challenge_token });
      } catch (_) { /* ignore */ }
    }

    const tfaModal = new bootstrap.Modal(modalEl, { backdrop: 'static', keyboard: false });
    tfaModal.show();

    // Focus the input after modal opens
    modalEl.addEventListener('shown.bs.modal', () => tfaCode?.focus(), { once: true });
  };

  // Submit 2FA code
  document.getElementById('tfaPasskeyBtn')?.addEventListener('click', async () => {
    if (!tfaState) return;
    try {
      const start = await window.callAPI?.('/twofactor/challenge', 'POST', { challenge_token: tfaState.challengeToken, method: 'passkey' });
      const publicKey = start?.public_key || start?.data?.public_key;
      if (!publicKey || !navigator.credentials?.get) throw new Error('Passkeys are not supported by this browser.');
      const credential = await navigator.credentials.get({ publicKey: recursiveBase64StrToArrayBuffer(publicKey) });
      const encoded = {
        id: credential.id,
        clientDataJSON: arrayBufferToBase64(credential.response.clientDataJSON),
        authenticatorData: arrayBufferToBase64(credential.response.authenticatorData),
        signature: arrayBufferToBase64(credential.response.signature),
        userHandle: credential.response.userHandle ? arrayBufferToBase64(credential.response.userHandle) : null,
      };
      const verified = await window.callAPI?.('/twofactor/verify', 'POST', { challenge_token: tfaState.challengeToken, method: 'passkey', credential: encoded });
      if (!verified?.verified) throw new Error('Passkey verification failed.');
      const loginRes = await window.API.auth.complete2FALogin(tfaState.userId, tfaState.rememberMe, tfaState.challengeToken);
      if (!loginRes?.token) throw new Error(loginRes?.message || 'Login failed');
      if (typeof tfaState.onComplete === 'function') { const done = tfaState.onComplete; tfaState = null; bootstrap.Modal.getInstance(document.getElementById('tfaModal'))?.hide(); done(loginRes); return; }
      window.location.href = (window.APP_BASE || '') + '/home.php';
    } catch (e) { showTFAError(e.message || 'Passkey verification failed'); }
  });

  document.getElementById('tfaSubmitBtn')?.addEventListener('click', async () => {
    if (!tfaState) return;
    const code = document.getElementById('tfaCode')?.value?.trim();
    if (!code || code.length < 4) {
      showTFAError('Please enter a valid verification code.');
      return;
    }

    showTFASpinner();
    try {
      let currentMethod = document.getElementById('tfaRecoveryBtn')?.classList.contains('d-none')
        ? tfaState.method : tfaState.method;

      // If the input looks like a backup code (9 chars with dash), use backup method
      const isBackup = /^[A-Z0-9]{4}-[A-Z0-9]{4}$/i.test(code);
      const method = isBackup ? 'backup' : currentMethod;

      // Verify the code
      const verifyRes = await window.callAPI?.('/twofactor/verify', 'POST', {
        code,
        challenge_token: tfaState.challengeToken,
        method,
      });

      if (!verifyRes?.verified) {
        showTFAError('Invalid verification code. Please try again.');
        hideTFASpinner();
        return;
      }

      // 2FA passed — complete the login
      document.getElementById('tfaBtnText').textContent = 'Completing login…';
      const loginRes = await window.API.auth.complete2FALogin(tfaState.userId, tfaState.rememberMe, tfaState.challengeToken);
      if (!loginRes?.token) throw new Error(loginRes?.message || 'Login failed');

      if (typeof tfaState.onComplete === 'function') {
        const complete = tfaState.onComplete;
        tfaState = null;
        bootstrap.Modal.getInstance(document.getElementById('tfaModal'))?.hide();
        complete(loginRes);
        return;
      }

      // Close 2FA modal
      bootstrap.Modal.getInstance(document.getElementById('tfaModal'))?.hide();

      // Redirect to dashboard
      const dashboardInfo = window.AuthContext?.getDashboardInfo?.();
      const redirect = dashboardInfo?.key
        ? (window.APP_BASE || '') + '/home.php?route=' + dashboardInfo.key
        : (window.APP_BASE || '') + '/home.php';
      window.location.href = redirect;
    } catch (err) {
      if (isExpiredTFAChallenge(err)) {
        try {
          await renewTFAChallenge();
          const input = document.getElementById('tfaCode');
          if (input) input.value = '';
          showTFASuccess('Your verification session expired. A fresh code has been sent; enter the new code.');
          hideTFASpinner();
          input?.focus();
          return;
        } catch (renewError) {
          showTFAError(renewError.message || 'Your verification session expired. Return to login and sign in again.');
          hideTFASpinner();
          return;
        }
      }
      showTFAError(err.message || 'Verification failed. Please try again.');
      hideTFASpinner();
    }
  });

  window.requestTFAForSession = function (res) {
    return new Promise((resolve, reject) => {
      window.showTFAVerification({ ...res, onComplete: resolve }, '', '', true).catch(reject);
    });
  };

  // Back to login
  document.getElementById('tfaBackBtn')?.addEventListener('click', () => {
    bootstrap.Modal.getInstance(document.getElementById('tfaModal'))?.hide();
    tfaState = null;
    const loginModal = new bootstrap.Modal(document.getElementById('loginModal'));
    loginModal.show();
    resetLoginBtn();
  });

  // Recovery code toggle
  document.getElementById('tfaRecoveryBtn')?.addEventListener('click', () => {
    const codeInput = document.getElementById('tfaCode');
    const label  = document.getElementById('tfaCodeLabel');
    const desc   = document.getElementById('tfaMethodDesc');
    const btn    = document.getElementById('tfaRecoveryBtn');
    codeInput.placeholder = 'XXXX-XXXX';
    codeInput.maxLength = 9;
    codeInput.inputMode = 'text';
    label.textContent = 'Recovery Code';
    desc.textContent = 'Enter one of your backup recovery codes.';
    btn.classList.add('d-none');
    document.getElementById('tfaResend')?.classList.add('d-none');
    codeInput.focus();
  });

  // Resend code
  document.getElementById('tfaResendBtn')?.addEventListener('click', async () => {
    if (!tfaState) return;
    try {
      await window.callAPI?.('/twofactor/challenge', 'POST', {
        challenge_token: tfaState.challengeToken,
      });
      startTFACountdown();
      showTFASuccess('A new code has been sent.');
    } catch (error) {
      if (isExpiredTFAChallenge(error)) {
        try {
          await renewTFAChallenge();
          showTFASuccess('Your verification session was renewed and a fresh code was sent.');
          return;
        } catch (renewError) {
          showTFAError(renewError.message || 'Your verification session expired. Return to login and sign in again.');
          return;
        }
      }
      showTFAError(error?.message || 'Failed to resend. Please try again.');
    }
  });

  // Enter key submits in the 2FA code field
  document.getElementById('tfaCode')?.addEventListener('keydown', (e) => {
    if (e.key === 'Enter') document.getElementById('tfaSubmitBtn')?.click();
  });

  function showTFAError(msg) {
    const el = document.getElementById('tfaError');
    const txt = document.getElementById('tfaErrorText');
    if (el && txt) {
      el.classList.remove('d-none', 'alert-success');
      el.classList.add('alert-danger');
      txt.textContent = msg;
    }
  }

  function showTFASuccess(msg) {
    const el = document.getElementById('tfaError');
    const txt = document.getElementById('tfaErrorText');
    if (el && txt) { el.classList.remove('d-none', 'alert-danger'); el.classList.add('alert-success'); txt.textContent = msg; }
  }

  function showTFASpinner() {
    document.getElementById('tfaBtnText')?.classList.add('d-none');
    document.getElementById('tfaSpinner')?.classList.remove('d-none');
    document.getElementById('tfaSubmitBtn')?.setAttribute('disabled', '');
  }

  function hideTFASpinner() {
    document.getElementById('tfaBtnText')?.classList.remove('d-none');
    document.getElementById('tfaSpinner')?.classList.add('d-none');
    document.getElementById('tfaSubmitBtn')?.removeAttribute('disabled');
  }

  function startTFACountdown() {
    const resendBtn = document.getElementById('tfaResendBtn');
    const resendTimer = document.getElementById('tfaResendTimer');
    const countdownEl = document.getElementById('tfaCountdown');
    if (!resendBtn || !resendTimer || !countdownEl) return;

    resendBtn.classList.add('d-none');
    resendTimer.classList.remove('d-none');
    let seconds = 60;
    countdownEl.textContent = seconds;
    const interval = setInterval(() => {
      seconds--;
      countdownEl.textContent = seconds;
      if (seconds <= 0) {
        clearInterval(interval);
        resendBtn.classList.remove('d-none');
        resendTimer.classList.add('d-none');
      }
    }, 1000);
  }

  /* ── Smooth scroll for anchor links ─────────────────────────────────────────── */
  document.querySelectorAll('a[href^="#"]').forEach(a => {
    a.addEventListener('click', e => {
      const href = a.getAttribute('href');
      // Skip if href is just "#" (common for buttons styled as links)
      if (href === '#') return;
      const target = document.querySelector(href);
      if (target) { e.preventDefault(); target.scrollIntoView({ behavior: 'smooth', block: 'start' }); }
    });
  });

  /* ── Contact form ──────────────────────────────────────────────────────────── */
  const contactForm = document.getElementById('contactForm');
  if (contactForm) {
    contactForm.addEventListener('submit', async (e) => {
      e.preventDefault();
      const btn = contactForm.querySelector('[type="submit"]');
      const orig = btn.innerHTML;
      btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Sending…';
      btn.disabled = true;
      await new Promise(r => setTimeout(r, 1200));
      contactForm.reset();
      btn.innerHTML = '<i class="bi bi-check-circle me-2"></i>Message Sent!';
      btn.classList.add('btn-success');
      setTimeout(() => { btn.innerHTML = orig; btn.disabled = false; btn.classList.remove('btn-success'); }, 3000);
    });
  }

});
