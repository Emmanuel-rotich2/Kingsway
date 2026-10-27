(function () {
  const COOKIE_NAME = 'kingsway_cookie_consent';
  const STORAGE_KEY = 'kingsway_cookie_consent';
  const CONSENT_VALUES = {
    accepted: 'accepted',
    declined: 'declined'
  };

  function isSecure() {
    return location.protocol === 'https:';
  }

  function buildCookieAttributes(days) {
    const expires = new Date(Date.now() + days * 864e5).toUTCString();
    const attrs = [
      'expires=' + expires,
      'path=/',
      'SameSite=Lax'
    ];
    if (isSecure()) {
      attrs.push('Secure');
    }
    return attrs.join('; ');
  }

  function getConsent() {
    // Prefer cookie; fall back to localStorage when cookies are blocked.
    const match = document.cookie.match(new RegExp('(^| )' + COOKIE_NAME + '=([^;]+)'));
    if (match) return match[2];
    try {
      return localStorage.getItem(STORAGE_KEY) || null;
    } catch {
      return null;
    }
  }

  function setConsent(value, days = 365) {
    document.cookie =
      COOKIE_NAME + '=' + encodeURIComponent(value) + '; ' + buildCookieAttributes(days);
    try {
      localStorage.setItem(STORAGE_KEY, value);
    } catch {
      /* localStorage unavailable; cookie alone is used */
    }
  }

  function createConsentBanner() {
    if (document.getElementById('kingsway-cookie-consent')) return;

    const banner = document.createElement('div');
    banner.id = 'kingsway-cookie-consent';
    banner.className = 'cookie-consent-banner';
    banner.setAttribute('role', 'dialog');
    banner.setAttribute('aria-labelledby', 'cookie-consent-title');
    banner.setAttribute('aria-describedby', 'cookie-consent-desc');
    banner.innerHTML = `
      <div class="cookie-consent-content">
        <header class="cookie-consent-header">
          <i class="bi bi-shield-lock-fill cookie-consent-icon" aria-hidden="true"></i>
          <h2 id="cookie-consent-title">Cookie Consent</h2>
        </header>
        <p id="cookie-consent-desc" class="cookie-consent-text">
          This website uses cookies to improve your experience, analyze traffic, and enable core functionality.
          <a href="/privacy-policy" target="_blank" rel="noopener">Read our Privacy Policy</a>
        </p>
        <div class="cookie-consent-actions">
          <button type="button" class="btn btn-primary cookie-btn-accept" data-action="accept">
            <i class="bi bi-check-lg me-1"></i>Accept All
          </button>
          <button type="button" class="btn btn-outline-secondary cookie-btn-decline" data-action="decline">
            <i class="bi bi-x-lg me-1"></i>Decline
          </button>
        </div>
      </div>
    `;

    document.body.appendChild(banner);

    banner.querySelectorAll('[data-action]').forEach(btn => {
      btn.addEventListener('click', () => {
        const action = btn.dataset.action;
        setConsent(action === 'accept' ? CONSENT_VALUES.accepted : CONSENT_VALUES.declined);
        banner.classList.add('hiding');
        setTimeout(() => banner.remove(), 300);
      });
    });

    requestAnimationFrame(() => banner.classList.add('show'));
  }

  function init() {
    if (getConsent() === null) {
      if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', createConsentBanner);
      } else {
        createConsentBanner();
      }
    }
  }

  window.KingswayCookieConsent = {
    init,
    getConsent,
    setConsent,
    reset: () => {
      document.cookie =
        COOKIE_NAME + '=; expires=Thu, 01 Jan 1970 00:00:00 GMT; path=/';
      try {
        localStorage.removeItem(STORAGE_KEY);
      } catch {}
    }
  };

  init();
})();
