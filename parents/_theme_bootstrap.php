<?php
/**
 * parents/_theme_bootstrap.php — pre-paint theme state for every parent portal
 * document. Included by both the login facade and the shared cpanel head.
 */
declare(strict_types=1);
?>
<meta name="color-scheme" content="light dark">
<meta name="theme-color" content="#178a50">
<script nonce="<?= csp_nonce() ?>">
  (function () {
    var root = document.documentElement;
    var uid = '';
    try { uid = localStorage.getItem('kw_active_user_id') || ''; } catch (_) {}

    // Parent sessions keep their JWT (obfuscated) in sessionStorage. Decode
    // only the payload so the theme is scoped to this guardian.
    if (!uid) {
      try {
        var raw = sessionStorage.getItem('pp_token');
        if (raw) {
          var token = atob(raw).split('').map(function (c, i) {
            return String.fromCharCode(c.charCodeAt(0) - ((i % 7) + 1));
          }).join('');
          var part = (token.split('.')[1] || '').replace(/-/g, '+').replace(/_/g, '/');
          while (part.length % 4) part += '=';
          var payload = JSON.parse(atob(part));
          uid = String(payload.user_id || payload.parent_id || payload.sub || payload.id || '');
        }
      } catch (_) {}
    }
    if (!uid) {
      try {
        var guardian = JSON.parse(sessionStorage.getItem('kw_admissions_guardian') || '{}');
        uid = String(guardian.parent_email || guardian.parent_phone || '');
      } catch (_) {}
    }
    window.KW_THEME_USER_ID = uid;

    var preference = 'system';
    try {
      var key = uid ? 'kw_' + uid + '_theme' : 'kingsway_theme';
      var stored = localStorage.getItem(key);
      // One-time legacy migration only. A stored owner keeps another
      // guardian's old preference from leaking into this family session.
      var owner = localStorage.getItem('kingsway_theme_owner') || '';
      if (!stored && uid && (!owner || owner === uid)) stored = localStorage.getItem('kingsway_theme');
      if (stored === 'light' || stored === 'dark') preference = stored;
    } catch (_) {}

    var dark = preference === 'dark' ||
      (preference === 'system' && window.matchMedia &&
        window.matchMedia('(prefers-color-scheme: dark)').matches);
    var resolved = dark ? 'dark' : 'light';
    root.dataset.kwTheme = resolved;
    root.dataset.kwThemePreference = preference;
    root.dataset.bsTheme = resolved;
    root.classList.add('kw-theme-ready');
    var meta = document.querySelector('meta[name="color-scheme"]');
    if (meta) meta.content = preference === 'system' ? 'light dark' : resolved;
    var themeColor = document.querySelector('meta[name="theme-color"]');
    if (themeColor) themeColor.content = dark ? '#073523' : '#178a50';
    window.__KW_THEME__ = { preference: preference, resolved: resolved };
  })();
</script>
