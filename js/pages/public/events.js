/* =============================================================================
   Events & Calendar — js/pages/public/events.js
   Renders the upcoming-events list, academic-terms sidebar and event-type
   legend from /api/website/{events,terms}. Events expose start_at (datetime)
   and type, so all the legacy event_date/event_time/category mapping happens
   here.
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

  const FALLBACK_TERMS = [
    ['Term 1', 'Jan 20', 'Apr 4'],
    ['Term 2', 'May 5', 'Aug 15'],
    ['Term 3', 'Sep 1', 'Nov 28'],
  ];

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
      try {
        const [events, terms] = await Promise.all([
          PS.get('events', { upcoming: '0' }, { tier: 'dynamic', forceRefresh: true }),
          PS.get('terms', {}, { tier: 'reference', forceRefresh: true }),
        ]);
        this.renderEvents(events);
        this.renderTerms(terms);
        this.renderCategories();
      } catch (err) {
        if (window.KINGSWAY_DEBUG) console.warn('[events] render failed:', err);
        const el = document.getElementById('events-list');
        if (el) el.innerHTML = PS.errorHTML();
      }
    },

    renderEvents(data) {
      const el = document.getElementById('events-list');
      if (!el) return;
      const today = new Date().toISOString().slice(0, 10);
      const all = PS.deduplicateEvents(PS.items(data));
      const list = all.filter((ev) => dateOnly(ev.end_at || ev.start_at) >= today);
      if (!list.length) {
        el.innerHTML =
          '<div class="text-center py-5">' +
          '<i class="bi bi-calendar-event fs-1 text-muted d-block mb-3"></i>' +
          '<p class="text-muted">No upcoming events scheduled. Check back soon!</p></div>';
        return;
      }

      const cards = list.map((ev) => {
        const d = toDate(ev.start_at);
        const dEnd = toDate(ev.end_at);
        const tc = typeColor(ev.type);
        const day = d ? String(d.getDate()).padStart(2, '0') : '';
        const mon = d ? d.toLocaleString('en-GB', { month: 'short' }) : '';
        const yr = d ? String(d.getFullYear()) : '';
        const sameDay = !dEnd || dateOnly(ev.end_at) === dateOnly(ev.start_at);
        const fmtShort = (dt) => dt ? dt.toLocaleDateString('en-GB', { day: 'numeric', month: 'short' }) : '';
        const dateRange = sameDay ? '' : fmtShort(d) + ' – ' + fmtShort(dEnd);
        const sT = ev.start_at && hasTime(ev.start_at) ? PS.formatDate(d, 'time') : '';
        const eT = ev.end_at && hasTime(ev.end_at) ? PS.formatDate(dEnd, 'time') : '';
        const timeStr = (sT && eT && eT !== sT) ? sT + ' – ' + eT : (sT || '');
        return '<a href="' + base + '/index.php?route=rdee45953a6c1&id=' + encodeURIComponent(ev.id) + '" class="text-decoration-none">' +
          '<div class="card-modern" style="cursor:pointer;background:#fff;border:1px solid #e2e8f0;border-radius:16px;overflow:hidden">' +
          '<div class="row g-0">' +
          '<div class="col-auto d-flex">' +
          '<div class="d-flex flex-column align-items-center justify-content-center px-4 text-white rounded-start-4" style="min-width:90px;background:#07552f;border-radius:16px 0 0 16px">' +
          '<div style="font-size:2rem;font-weight:900;line-height:1">' + day + '</div>' +
          '<div style="font-size:.8rem;text-transform:uppercase;letter-spacing:.5px;opacity:.9">' + mon + '</div>' +
          '<div style="font-size:.75rem;opacity:.75">' + yr + '</div>' +
          '</div></div>' +
          '<div class="col p-4" style="color:#333">' +
          '<div class="d-flex align-items-start justify-content-between flex-wrap gap-2">' +
          '<div>' +
          '<span style="display:inline-block;font-size:.72rem;font-weight:600;text-transform:uppercase;letter-spacing:.6px;padding:2px 8px;border-radius:4px;margin-bottom:5px;background:' + tc[0] + ';color:' + tc[1] + '">' + S(ev.type || 'Event') + '</span>' +
          '<h5 style="font-weight:700;font-size:1.05rem;margin:0 0 8px;color:#1a1a2e">' + S(ev.title) + '</h5>' +
          '<p style="color:#666;font-size:.88rem;margin:0 0 8px">' + S(String(ev.description || '').slice(0, 120)) + '</p>' +
          '<div style="font-size:.8rem;color:#666;display:flex;align-items:center;gap:12px">' +
          (dateRange ? '<span><i class="bi bi-calendar-range" style="color:#07552f"></i> ' + S(dateRange) + '</span>' : '') +
          (timeStr ? '<span><i class="bi bi-clock" style="color:#07552f"></i> ' + timeStr + '</span>' : '') +
          (ev.location ? '<span><i class="bi bi-geo-alt" style="color:#07552f"></i> ' + S(ev.location) + '</span>' : '') +
          '</div></div>' +
          '<div class="d-flex flex-column align-items-end gap-1">' +
          (dateOnly(ev.start_at) <= today
            ? '<span style="display:inline-flex;padding:4px 12px;border-radius:20px;font-size:.75rem;font-weight:600;background:#fff3cd;color:#856404">Ongoing</span>'
            : '<span style="display:inline-flex;padding:4px 12px;border-radius:20px;font-size:.75rem;font-weight:600;background:#e8f5e9;color:#07552f">Upcoming</span>') +
          '<span style="color:#999;font-size:.8rem"><i class="bi bi-chevron-right"></i>Details</span>' +
          '</div></div></div></div></a>';
      }).join('');

      el.innerHTML =
        '<div id="events-scroll-container" style="max-height:calc(6 * 180px);overflow-y:auto;scroll-behavior:smooth;border-radius:12px;scrollbar-width:thin;scrollbar-color:#07552f transparent">' +
          '<div style="display:flex;flex-direction:column;gap:20px;padding:4px">' +
            cards +
          '</div>' +
        '</div>' +
        (list.length > 6
          ? '<div id="events-scroll-wrap" style="display:flex;justify-content:center;margin-top:16px">' +
              '<button id="events-scroll-btn" ' +
              'style="display:inline-flex;align-items:center;gap:6px;padding:8px 20px;border:1px solid #07552f;border-radius:24px;background:transparent;color:#07552f;font-size:.85rem;font-weight:600;cursor:pointer;transition:all .2s">' +
                '<i class="bi bi-chevron-down"></i> Show more' +
              '</button>' +
            '</div>'
          : '');

      if (list.length > 6) {
        var btn = document.getElementById('events-scroll-btn');
        var sc = document.getElementById('events-scroll-container');
        if (btn && sc) {
          btn.addEventListener('click', function () {
            var atBottom = sc.scrollTop + sc.clientHeight >= sc.scrollHeight - 20;
            if (atBottom) {
              sc.scrollTo({ top: 0, behavior: 'smooth' });
              btn.innerHTML = '<i class="bi bi-chevron-down"></i> Show more';
            } else {
              sc.scrollTo({ top: sc.scrollTop + sc.clientHeight + 180, behavior: 'smooth' });
              btn.innerHTML = '<i class="bi bi-chevron-up"></i> Scroll to top';
            }
          });
          sc.addEventListener('scroll', function () {
            var atBottom = sc.scrollTop + sc.clientHeight >= sc.scrollHeight - 20;
            btn.innerHTML = atBottom
              ? '<i class="bi bi-chevron-up"></i> Scroll to top'
              : '<i class="bi bi-chevron-down"></i> Show more';
          });
        }
      }
    },

    renderTerms(data) {
      const el = document.getElementById('events-terms');
      if (!el) return;
      const list = PS.items(data);
      if (list.length) {
        el.innerHTML = list.map((t) => {
          const name = t.name || 'Term ' + (t.term_number || '');
          const fmt = (v) => {
            const d = toDate(v);
            return d ? d.toLocaleDateString('en-GB', { day: 'numeric', month: 'short' }) : '';
          };
          const start = fmt(t.start_date);
          const end = t.end_date ? toDate(t.end_date).toLocaleDateString('en-GB', { day: 'numeric', month: 'short', year: 'numeric' }) : '';
          return '<div class="mb-3 pb-3 border-bottom">' +
            '<div class="fw-semibold small">' + S(name) + (t.year ? ' ' + S(t.year) : '') + '</div>' +
            '<div class="text-muted" style="font-size:.8rem">' + start + ' — ' + end + '</div>' +
            '</div>';
        }).join('');
        return;
      }
      el.innerHTML = FALLBACK_TERMS.map((t) =>
        '<div class="mb-3 pb-3 border-bottom">' +
        '<div class="fw-semibold small">' + t[0] + ' ' + new Date().getFullYear() + '</div>' +
        '<div class="text-muted" style="font-size:.8rem">' + t[1] + ' — ' + t[2] + ' ' + new Date().getFullYear() + '</div>' +
        '</div>'
      ).join('');
    },

    renderCategories() {
      const el = document.getElementById('events-categories');
      if (!el) return;
      el.innerHTML = Object.keys(TYPE_COLORS).map((cat) => {
        const [bg, col] = TYPE_COLORS[cat];
        return '<div class="d-flex align-items-center justify-content-between py-2 border-bottom">' +
          '<span class="d-flex align-items-center gap-2">' +
          '<span class="rounded-2 px-2 py-1" style="background:' + bg + ';color:' + col + ';font-size:.75rem;font-weight:600">' + cat + '</span>' +
          '</span></div>';
      }).join('');
    },
  };

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => page.init());
  } else {
    page.init();
  }
})();
