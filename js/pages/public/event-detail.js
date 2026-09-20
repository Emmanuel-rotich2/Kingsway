/* =============================================================================
   Event Detail — js/pages/public/event-detail.js
   Renders a single event from GET /api/website/events/<id> (raw row) plus the
   upcoming-events list and academic terms for the sidebar. A missing/invalid
   id falls back to the events listing client-side.
   ============================================================================= */
(function () {
  'use strict';

  const PS = window.PublicSite;
  if (!PS) return;

  const S = PS.escapeHtml;
  const base = String(window.APP_BASE || '').replace(/\/+$/, '');

  const TYPE_COLORS = {
    Academic: ['#e3f2fd', '#1565c0'],
    Ceremony: ['#fff8e1', '#f57f17'],
    Sports: ['#e8f5e9', '#2e7d32'],
    Meeting: ['#fce4ec', '#b71c1c'],
    Community: ['#f3e5f5', '#6a1b9a'],
    Cultural: ['#e0f2f1', '#00695c'],
  };

  function typeColor(type) {
    return TYPE_COLORS[type] || ['#f5f5f5', '#333'];
  }

  function toDate(value) {
    if (!value) return null;
    const d = new Date(String(value).replace(' ', 'T'));
    return isNaN(d.getTime()) ? null : d;
  }

  function dateOnly(value) {
    const m = String(value).match(/^(\d{4})-(\d{2})-(\d{2})/);
    return m ? m[1] + '-' + m[2] + '-' + m[3] : '';
  }

  function hasTime(value) {
    return /\d{1,2}:\d{2}(:\d{2})?/.test(String(value));
  }

  const page = {
    initialized: false,

    async init() {
      if (this.initialized) return;
      this.initialized = true;

      const id = new URLSearchParams(window.location.search).get('id');
      if (!id || !/^\d+$/.test(id)) {
        window.location.replace(base + '/index.php?route=r78213fd42e2d');
        return;
      }

      try {
        const [event, list, terms] = await Promise.all([
          PS.get('events', { id }, { tier: 'dynamic' }),
          PS.get('events', {}, { tier: 'dynamic' }),
          PS.get('terms', {}, { tier: 'reference' }),
        ]);
        const related = PS.deduplicateEvents(PS.items(list)).filter((e) => String(e.id) !== String(event.id)).slice(0, 3);
        this.render(event, related, terms);
      } catch (err) {
        if (window.KINGSWAY_DEBUG) console.warn('[event-detail] not found or failed:', err);
        window.location.replace(base + '/index.php?route=r78213fd42e2d');
      }
    },

    render(event, related, terms) {
      const title = String(event.title || 'Event');
      const titleEl = document.getElementById('ed-title');
      const crumbEl = document.getElementById('ed-crumb');
      if (titleEl) titleEl.textContent = title;
      if (crumbEl) crumbEl.textContent = title.slice(0, 40) + (title.length > 40 ? '…' : '');

      const d = toDate(event.start_at);
      const tc = typeColor(event.type);
      const isPast = dateOnly(event.start_at) < new Date().toISOString().slice(0, 10);

      const banner = document.getElementById('ed-banner');
      if (banner) {
        banner.innerHTML =
          '<div class="d-flex flex-column align-items-center justify-content-center text-white rounded-3 flex-shrink-0" ' +
          'style="width:90px;height:90px;background:var(--green-dark)">' +
          '<div style="font-size:2.2rem;font-weight:900;line-height:1">' + (d ? String(d.getDate()).padStart(2, '0') : '') + '</div>' +
          '<div style="font-size:.85rem;text-transform:uppercase">' + (d ? d.toLocaleString('en-GB', { month: 'short' }) : '') + '</div>' +
          '<div style="font-size:.75rem;opacity:.8">' + (d ? d.getFullYear() : '') + '</div>' +
          '</div>' +
          '<div>' +
          '<span class="event-type mb-2 d-inline-block" style="background:' + tc[0] + ';color:' + tc[1] + '">' + S(event.type || 'Event') + '</span>' +
          '<h2 class="fw-bold mb-2" style="font-size:1.4rem">' + S(title) + '</h2>' +
          '<div class="d-flex flex-wrap gap-3 text-muted small">' +
          (event.start_at && hasTime(event.start_at) ? '<span><i class="bi bi-clock text-success me-1"></i>' + (d ? PS.formatDate(d, 'time') : '') + '</span>' : '') +
          (event.end_at && dateOnly(event.end_at) !== dateOnly(event.start_at)
            ? '<span><i class="bi bi-calendar-range text-success me-1"></i>Until ' + (toDate(event.end_at) ? toDate(event.end_at).toLocaleDateString('en-GB', { day: 'numeric', month: 'short', year: 'numeric' }) : '') + (event.end_at && hasTime(event.end_at) ? ' ' + PS.formatDate(toDate(event.end_at), 'time') : '') + '</span>'
            : '') +
          (event.location ? '<span><i class="bi bi-geo-alt text-success me-1"></i>' + S(event.location) + '</span>' : '') +
          (isPast
            ? '<span class="badge bg-secondary">Past Event</span>'
            : '<span class="badge bg-success">Upcoming</span>') +
          '</div></div>';
      }

      const descCard = document.getElementById('ed-description-card');
      const desc = document.getElementById('ed-description');
      if (desc) desc.textContent = String(event.description || '');
      if (descCard && !event.description) descCard.style.display = 'none';

      const share = document.getElementById('ed-share');
      if (share) {
        const url = window.location.href;
        const waText = encodeURIComponent(title + ' — ' + (d ? d.toLocaleDateString('en-GB', { day: 'numeric', month: 'short', year: 'numeric' }) : '') + (event.location ? ' at ' + event.location : ''));
        share.innerHTML =
          '<span class="fw-semibold small">Share this event:</span>' +
          '<a href="https://wa.me/?text=' + waText + '" target="_blank" class="btn btn-sm btn-outline-success rounded-pill px-3">' +
          '<i class="bi bi-whatsapp me-1"></i>WhatsApp</a>' +
          '<a href="https://www.facebook.com/sharer/sharer.php?u=' + encodeURIComponent(url) + '" target="_blank" class="btn btn-sm btn-outline-secondary rounded-pill px-3">' +
          '<i class="bi bi-facebook me-1"></i>Facebook</a>';
      }

      const subscribeBox = document.getElementById('ed-subscribe-box');
      if (subscribeBox) subscribeBox.style.display = isPast ? 'none' : '';

      this.renderTerms(terms);
      this.renderRelated(related);

      if (window.PublicUI && window.PublicUI.observeReveals) window.PublicUI.observeReveals(banner || document.body);
    },

    renderTerms(data) {
      const el = document.getElementById('ed-terms');
      if (!el) return;
      const list = PS.items(data);
      if (list.length) {
        el.innerHTML = list.map((t) => {
          const fmt = (v) => {
            const d = toDate(v);
            return d ? d.toLocaleDateString('en-GB', { day: 'numeric', month: 'short' }) : '';
          };
          const end = t.end_date ? toDate(t.end_date).toLocaleDateString('en-GB', { day: 'numeric', month: 'short', year: 'numeric' }) : '';
          return '<div class="mb-3 pb-3 border-bottom">' +
            '<div class="fw-semibold small">' + S(t.name || 'Term ' + (t.term_number || '')) + '</div>' +
            '<div class="text-muted" style="font-size:.8rem">' + fmt(t.start_date) + ' — ' + end + '</div>' +
            '</div>';
        }).join('');
        return;
      }
      [['Term 1', 'Jan 20', 'Apr 4'], ['Term 2', 'May 5', 'Aug 15'], ['Term 3', 'Sep 1', 'Nov 28']].forEach((t) => {
        el.innerHTML += '<div class="mb-3 pb-3 border-bottom"><div class="fw-semibold small">' + t[0] + '</div>' +
          '<div class="text-muted" style="font-size:.8rem">' + t[1] + ' — ' + t[2] + '</div></div>';
      });
    },

    renderRelated(list) {
      const el = document.getElementById('ed-related');
      if (!el) return;
      if (!list.length) {
        el.innerHTML = '<p class="text-muted small mb-0">No other upcoming events right now.</p>';
        return;
      }
      el.innerHTML = list.map((r) => {
        const rd = toDate(r.start_at);
        const tc = typeColor(r.type);
        return '<a href="' + base + '/index.php?route=rdee45953a6c1&id=' + encodeURIComponent(r.id) + '" ' +
          'class="d-flex align-items-center gap-3 mb-3 text-decoration-none text-dark">' +
          '<div class="d-flex flex-column align-items-center justify-content-center text-white rounded-2 flex-shrink-0" ' +
          'style="width:44px;height:44px;background:var(--green-dark);font-size:.75rem">' +
          '<div class="fw-bold lh-1">' + (rd ? rd.getDate() : '') + '</div>' +
          '<div>' + (rd ? rd.toLocaleString('en-GB', { month: 'short' }) : '') + '</div>' +
          '</div>' +
          '<div>' +
          '<div class="small fw-semibold lh-sm">' + S(String(r.title || '').slice(0, 55)) + '</div>' +
          '<span class="d-inline-block mt-1" style="background:' + tc[0] + ';color:' + tc[1] + ';font-size:.7rem;font-weight:600;padding:1px 6px;border-radius:4px">' + S(r.type || 'Event') + '</span>' +
          '</div></a>';
      }).join('');
    },
  };

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => page.init());
  } else {
    page.init();
  }
})();
