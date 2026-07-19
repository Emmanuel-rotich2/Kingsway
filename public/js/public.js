/* =============================================================================
   Kingsway Public Website — Interactions & Animations
   ============================================================================= */

document.addEventListener('DOMContentLoaded', () => {

  /* ── Navbar scroll behaviour ──────────────────────────────────────────────── */
  const nav = document.querySelector('.site-nav');
  if (nav) {
    const onScroll = () => nav.classList.toggle('scrolled', window.scrollY > 40);
    window.addEventListener('scroll', onScroll, { passive: true });
    onScroll();
  }

  /* ── Scroll reveal ────────────────────────────────────────────────────────── */
  const io = new IntersectionObserver((entries) => {
    entries.forEach(e => { if (e.isIntersecting) { e.target.classList.add('visible'); io.unobserve(e.target); } });
  }, { threshold: 0.12, rootMargin: '0px 0px -40px 0px' });

  document.querySelectorAll('.reveal').forEach(el => io.observe(el));

  /* ── Animated counters ────────────────────────────────────────────────────── */
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

  document.querySelectorAll('[data-target]').forEach(el => counterIO.observe(el));

  /* ── Announcement ticker pause on hover ──────────────────────────────────── */
  const ticker = document.querySelector('.ticker-track');
  if (ticker) {
    ticker.addEventListener('mouseenter', () => ticker.style.animationPlayState = 'paused');
    ticker.addEventListener('mouseleave', () => ticker.style.animationPlayState = 'running');
  }

  /* ── Active nav link ─────────────────────────────────────────────────────── */
  const currentPage = location.pathname.split('/').pop() || 'index.php';
  document.querySelectorAll('.site-nav .nav-link').forEach(link => {
    const href = link.getAttribute('href') || '';
    if (href && (href === currentPage || href.endsWith(currentPage))) {
      link.classList.add('active');
    }
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
        if (!res?.token) throw new Error(res?.message || 'Login failed. Check your credentials.');
        // Token received — keep the spinner visible while the dashboard loads.
        // The browser unloads this page on redirect, so we don't reset here.
        setSpinnerLabel('Preparing your dashboard…');
      } catch (err) {
        showLoginErr(err.message || 'Login failed. Please try again.');
      }
    });
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

/* =============================================================================
   PublicCache — hybrid SSR + web-storage cache
   -----------------------------------------------------------------------------
   Public pages are server-rendered (SSR) at request time — great for SEO and
   for visitors with no JS. On top of that we progressively enhance with the
   existing js/core DataStore stack (memory + IndexedDB + stale-while-revalidate)
   already used by the admin SPA, plus the service worker registered in
   public/layout/footer.php for offline/bfcache.

   Design rule (important): the JS layer NEVER rewrites the rich SSR layout.
   The news/downloads/jobs pages ship carefully designed cards (featured post,
   pagination, search, colour tags). Re-painting them from JS would regress the
   UI. Instead PublicCache:
     1. PRIMES the cache — fetches each resource through DataStore so revisits
        and offline reads are instant, and so the service worker can cache it.
     2. COMPARES the live payload's signature (count + newest id) against the
        signature cached on the last visit (localStorage — a web-storage
        primitive the project already uses). When the server has newer content it
        shows a small non-destructive "Updates available — reload" banner. The
        user reloads to get fresh SSR. No silent in-place repaint of the
        curated markup.
   Static-only tables (leadership, programs, facilities, values, history,
   benefits, gallery) have no /api/website endpoint, so they are intentionally
   NOT in RESOURCES — SSR + service-worker cache covers them.
   ========================================================================== */
(function () {
  'use strict';

  // Read-only public showcase resources. Each has a real /api/website/<res> GET
  // endpoint that now responds to anonymous visitors (AuthMiddleware allows the
  // GET; WebsiteController opens website_view on read for null users). Writes
  // remain staff-gated. Keep this list in sync with the publicEndpoints block
  // in api/middleware/AuthMiddleware.php.
  const RESOURCES = [
    'news', 'events', 'downloads', 'jobs',
    'leadership', 'programs', 'facilities', 'history',
    'values', 'departments', 'steps', 'benefits',
    'gallery', 'categories', 'content', 'settings'
  ];

  // 24h localStorage lifetime for the "last seen signature" per resource.
  const SIG_TTL = 24 * 60 * 60 * 1000;

  function sigKey(res)   { return 'pc_sig_' + res; }
  function sigOf(items)   { return (items ? items.length : 0) + ':' + (items && items[0] ? items[0].id : ''); }

  function getStoredSig(res) {
    try {
      const raw = localStorage.getItem(sigKey(res));
      if (!raw) return null;
      const obj = JSON.parse(raw);
      if (!obj || Date.now() > obj.expires) { localStorage.removeItem(sigKey(res)); return null; }
      return obj.sig;
    } catch (e) { return null; }
  }
  function setStoredSig(res, sig) {
    try { localStorage.setItem(sigKey(res), JSON.stringify({ sig, expires: Date.now() + SIG_TTL })); }
    catch (e) { /* storage may be unavailable (private mode) — non-fatal */ }
  }

  function showUpdateBanner() {
    if (document.getElementById('pc-update-bar')) return;
    const bar = document.createElement('div');
    bar.id = 'pc-update-bar';
    bar.className = 'pc-update-bar';
    bar.setAttribute('role', 'status');
    bar.innerHTML =
      '<span><i class="bi bi-arrow-clockwise me-2"></i>New content is available.</span>' +
      '<button type="button" class="btn btn-sm btn-light">Reload</button>';
    bar.querySelector('button').addEventListener('click', () => location.reload());
    document.body.appendChild(bar);
  }

  // Per-resource renderers are intentionally OMITTED: we do not overwrite SSR.

  async function hydrate(res) {
    if (!window.DataStore || !window.DataStore.fetchPage) return; // SSR stays source of truth
    try {
      const data = await window.DataStore.fetchPage(res, {
        // RELATIVE endpoint — apiCall() prepends API_BASE_URL (/Kingsway/api),
        // so a leading slash + path avoids the double-/api/-prefix bug.
        endpoint: '/website/' + res,
        storeName: 'public_' + res,
        ttl: 5 * 60 * 1000,            // 5 min cache
        strategy: 'stale-while-revalidate'
      });
      const items = Array.isArray(data) ? data : (data && data.items) || [];
      const sig = sigOf(items);
      const prev = getStoredSig(res);
      if (prev !== null && prev !== sig) showUpdateBanner();
      setStoredSig(res, sig); // remember what we've now "seen"
      document.dispatchEvent(new CustomEvent('public:cache:updated', { detail: { resource: res, count: items.length } }));
    } catch (e) {
      // Network/API failure: keep SSR content, do not throw.
      if (window.console) console.warn('PublicCache hydration skipped for', res, e);
    }
  }

  function init() {
    RESOURCES.forEach(res => hydrate(res));
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
  window.PublicCache = { init, hydrate, refresh: init, resources: RESOURCES };
})();

