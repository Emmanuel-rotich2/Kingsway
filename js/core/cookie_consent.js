(function () {
  const COOKIE_NAME = 'kingsway_cookie_consent';
  const STORAGE_KEY = 'kingsway_cookie_consent';
  const CONSENT_VALUES = {
    accepted: 'accepted',
    declined: 'declined'
  };

  // This script is included by several standalone entry pages. Keep a single
  // controller if a shell accidentally includes it more than once.
  if (window.KingswayCookieConsent) {
    window.KingswayCookieConsent.init();
    return;
  }

  function isSecure() {
    return location.protocol === 'https:';
  }

  function buildCookieAttributes(days) {
    const maxAge = Math.max(0, Math.floor(days * 86400));
    const expires = new Date(Date.now() + maxAge * 1000).toUTCString();
    const attrs = [
      'expires=' + expires,
      'max-age=' + maxAge,
      'path=/',
      'SameSite=Lax'
    ];
    // The public and authenticated shells may be reached through www and
    // apex hosts. Share this preference across those two Kingsway origins.
    if (location.hostname === 'kingswaypreparatoryschool.sc.ke' ||
        location.hostname.endsWith('.kingswaypreparatoryschool.sc.ke')) {
      attrs.push('domain=.kingswaypreparatoryschool.sc.ke');
    }
    if (isSecure()) {
      attrs.push('Secure');
    }
    return attrs.join('; ');
  }

  function getConsent() {
    // Parse cookie separators explicitly; browsers expose cookies as a
    // semicolon-delimited string, with optional whitespace around entries.
    const cookie = document.cookie.split(';').map(part => part.trim())
      .find(part => part.startsWith(COOKIE_NAME + '='));
    if (cookie) {
      try { return decodeURIComponent(cookie.slice(COOKIE_NAME.length + 1)); }
      catch { return cookie.slice(COOKIE_NAME.length + 1); }
    }
    // localStorage is the fallback for browsers that block cookies.
    try {
      return localStorage.getItem(STORAGE_KEY) || null;
    } catch {
      return null;
    }
  }

  function setConsent(value, days = 365) {
    try {
      localStorage.setItem(STORAGE_KEY, value);
    } catch {
      /* Cookie remains the persistence path when localStorage is unavailable. */
    }
    // Remove the older host-only cookie before writing the shared-domain one,
    // otherwise duplicate cookie names can make the stale value win parsing.
    document.cookie = COOKIE_NAME + '=; expires=Thu, 01 Jan 1970 00:00:00 GMT; path=/';
    document.cookie =
      COOKIE_NAME + '=' + encodeURIComponent(value) + '; ' + buildCookieAttributes(days);
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
      document.cookie = COOKIE_NAME + '=; expires=Thu, 01 Jan 1970 00:00:00 GMT; path=/; domain=.kingswaypreparatoryschool.sc.ke';
      try {
        localStorage.removeItem(STORAGE_KEY);
      } catch {}
    }
  };

  init();
})();
