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
      if (loginError)  loginError.classList.add('d-none');
      if (loginBtnTxt) loginBtnTxt.classList.add('d-none');
      if (loginSpinner)loginSpinner.classList.remove('d-none');
      if (loginBtn)    loginBtn.disabled = true;
      try {
        const res = await API.auth.login(username, password);
        if (!res?.token) throw new Error(res?.message || 'Login failed. Check your credentials.');
      } catch (err) {
        showLoginErr(err.message || 'Login failed. Please try again.');
      }
    });
  }

  /* ── Smooth scroll for anchor links ─────────────────────────────────────────── */
  document.querySelectorAll('a[href^="#"]').forEach(a => {
    a.addEventListener('click', e => {
      const target = document.querySelector(a.getAttribute('href'));
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
