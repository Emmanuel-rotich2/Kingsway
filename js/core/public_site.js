/**
 * PublicSite — tokenless REST client + render helpers for the public website.
 *
 * All reads go directly to the network (no DataStore/IndexedDB cache).
 * This ensures content is always fresh — like React's fetch()-only approach.
 * Browsers still benefit from HTTP-level caching (304 Not Modified), which
 * is lightweight and respects server Cache-Control headers.
 */
(function () {
  'use strict';

  const BASE = String(window.APP_BASE || '').replace(/\/+$/, '');

  const TTL = {
    DYNAMIC: 5 * 60 * 1000,
    REFERENCE: 60 * 60 * 1000,
  };

  // Category → [colour, bootstrap-icon] used by news cards site-wide.
  const CATEGORY_STYLE = {
    Sports: ['#198754', 'bi-lightning-fill'],
    Academic: ['#1976d2', 'bi-book-fill'],
    Infrastructure: ['#e91e63', 'bi-buildings-fill'],
    Announcement: ['#f9c80e', 'bi-megaphone-fill'],
    Arts: ['#9c27b0', 'bi-music-note-beamed'],
    Community: ['#00695c', 'bi-people-fill'],
  };

  // Category → Unsplash photo id (fallback art for articles with no image).
  const CATEGORY_IMAGE = {
    Sports: 'photo-1571019614242-c5c5dee9f50b',
    Academic: 'photo-1503676260728-1c00da094a0b',
    Infrastructure: 'photo-1581472723648-909f4851d4ae',
    Announcement: 'photo-1543269865-cbf427effbad',
    Arts: 'photo-1514320291840-2e0a9bf2a9ae',
    Community: 'photo-1488521787991-ed7bbaae773c',
  };

  async function fetchJSON(resource, params = {}) {
    let path = '/api/website/' + encodeURIComponent(resource);
    const qs = {};
    Object.keys(params).forEach((k) => {
      // A special `id` param is folded into the path (GET /api/website/<res>/<id>)
      // and kept out of the query string so it cannot collide with real params.
      if (k === 'id' && params[k] !== undefined && params[k] !== null && params[k] !== '') {
        path += '/' + encodeURIComponent(params[k]);
      } else {
        qs[k] = params[k];
      }
    });
    const url = new URL(BASE + path, window.location.origin);
    Object.keys(qs).forEach((k) => {
      const v = qs[k];
      if (v !== undefined && v !== null && v !== '') url.searchParams.set(k, String(v));
    });
    const response = await fetch(url.toString(), {
      method: 'GET',
      credentials: 'same-origin',
      headers: { Accept: 'application/json' },
    });
    if (!response.ok) {
      throw new Error('Public request failed (' + response.status + ') for website/' + resource);
    }
    const payload = await response.json();
    if (payload && payload.success === false) {
      throw new Error(payload.message || 'Public request failed for website/' + resource);
    }
    return payload && payload.data !== undefined ? payload.data : payload;
  }

  /**
   * Read a public resource — always fetches fresh from network.
   * @param {string} resource  e.g. 'news', 'content', 'settings'
   * @param {object} [params]  query params
   * @param {object} [opts]    { tier: 'dynamic'|'reference', strategy, forceRefresh }
   */
  async function get(resource, params = {}, opts = {}) {
    return fetchJSON(resource, params);
  }

  /**
   * Fire a non-cached view increment (GET /api/website/<resource>/<id>?view=1).
   * Runs asynchronously so a cache hit never blocks or re-caches the page.
   */
  function bumpView(resource, id) {
    if (!id) return;
    const url = new URL(BASE + '/api/website/' + encodeURIComponent(resource) + '/' + encodeURIComponent(id), window.location.origin);
    url.searchParams.set('view', '1');
    fetch(url.toString(), { method: 'GET', credentials: 'same-origin', headers: { Accept: 'application/json' } })
      .catch(() => { /* view counting must never break the page */ });
  }

  function escapeHtml(value) {
    if (value === null || value === undefined) return '';
    return String(value)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#039;');
  }

  // Resolve a stored media path against the app base so site-relative
  // paths such as "/uploads/..." load when the app is mounted under a
  // sub-path (e.g. "/Kingsway"). Absolute and data URLs pass through.
  function mediaUrl(path) {
    if (path === null || path === undefined) return '';
    const value = String(path).trim();
    if (!value) return '';
    if (/^(?:[a-z][a-z0-9+.-]*:)?\/\//i.test(value) || value.startsWith('data:')) return value;
    return BASE + '/' + value.replace(/^\/+/, '');
  }

  function categoryStyle(category) {
    return CATEGORY_STYLE[category] || ['#198754', 'bi-circle-fill'];
  }

  function categoryImage(category, width = 800) {
    const id = CATEGORY_IMAGE[category] || 'photo-1503676260728-1c00da094a0b';
    return 'https://images.unsplash.com/' + id + '?w=' + width + '&q=80';
  }

  function formatDate(value, pattern) {
    if (!value) return '';
    const d = new Date(value);
    if (isNaN(d.getTime())) return String(value);
    const month = d.toLocaleString('en-GB', { month: 'short' });
    const day = String(d.getDate()).padStart(2, '0');
    if (pattern === 'd') return String(d.getDate());
    if (pattern === 'M') return month;
    if (pattern === 'time') {
      const h = d.getHours() % 12 || 12;
      const m = String(d.getMinutes()).padStart(2, '0');
      return h + ':' + m + ' ' + (d.getHours() >= 12 ? 'PM' : 'AM');
    }
    if (pattern === 'full') return day + ' ' + month + ' ' + d.getFullYear();
    return String(value);
  }

  // Unwrap the {items, total} envelope most /api/website/* lists return.
  function items(data) {
    return Array.isArray(data) ? data : (data && Array.isArray(data.items) ? data.items : []);
  }

  /**
   * Collapse duplicate events that share the same title (e.g. a multi-day
   * holiday stored as one row per day). Keeps the widest date range per
   * title and merges statuses. Returns a new array sorted chronologically
   * by start_at ASC so no event reappears after another.
   */
  function deduplicateEvents(list) {
    const map = {};
    list.forEach((ev) => {
      const key = (ev.title || '').trim().toLowerCase();
      if (!key) { map['_' + ev.id] = ev; return; }
      if (!map[key]) { map[key] = Object.assign({}, ev); return; }
      const existing = map[key];
      const eStart = String(existing.start_at || '');
      const eEnd   = String(existing.end_at || existing.start_at || '');
      const nStart = String(ev.start_at || '');
      const nEnd   = String(ev.end_at || ev.start_at || '');
      if (nStart < eStart) existing.start_at = ev.start_at;
      if (nEnd > eEnd)     existing.end_at = ev.end_at;
      if (ev.description && !existing.description) existing.description = ev.description;
      if (ev.location && !existing.location) existing.location = ev.location;
    });
    return Object.values(map).sort((a, b) =>
      String(a.start_at || '').localeCompare(String(b.start_at || ''))
    );
  }

  // Build a {valueByKey} map from an array of rows (settings/content blocks).
  function keyToMap(data, keyField, valueField) {
    const map = {};
    items(data).forEach((row) => {
      if (row && row[keyField] !== undefined && row[keyField] !== null) {
        map[row[keyField]] = row[valueField] ?? '';
      }
    });
    return map;
  }

  function loadingHTML(className) {
    return '<div class="text-center py-4' + (className ? ' ' + className : '') + '">' +
      '<div class="spinner-border text-success" role="status"></div></div>';
  }

  function emptyHTML(message) {
    return '<div class="text-center text-muted py-4">' + escapeHtml(message || 'Nothing to show yet.') + '</div>';
  }

  function errorHTML() {
    return '<div class="text-center text-muted py-4"><i class="bi bi-cloud-slash me-2"></i>Unable to load this content. Please try again later.</div>';
  }

  window.PublicSite = {
    get,
    bumpView,
    escapeHtml,
    mediaUrl,
    categoryStyle,
    categoryImage,
    formatDate,
    items,
    deduplicateEvents,
    keyToMap,
    loadingHTML,
    emptyHTML,
    errorHTML,
    TTL,
  };
})();
