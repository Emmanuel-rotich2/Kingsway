/* =============================================================================
   Careers — js/pages/public/careers.js
   Renders the benefits list, staff stat cards, and job listing grid from
   /api/website/{jobs,benefits,departments,stats,settings} through
   window.PublicSite. careers.php stays a thin HTML shell; this controller fills
   #careers-benefits, #careers-stats, #careers-jobs and #careers-count. The Apply
   modal + submission logic lives in the page's inline script.
   ============================================================================= */
(function () {
  'use strict';

  const PS = window.PublicSite;
  if (!PS) return;

  const S = (v) => PS.escapeHtml(v);
  const base = String(window.APP_BASE || '').replace(/\/+$/, '');

  // "2026-08-20" → "20 Aug 2026" (date-only, no timezone shift).
  function dateOnly(value) {
    if (!value) return '';
    const m = String(value).match(/^(\d{4})-(\d{2})-(\d{2})/);
    if (!m) return PS.formatDate(value, 'full');
    const months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
    const month = months[(parseInt(m[2], 10) || 1) - 1] || '';
    return parseInt(m[3], 10) + ' ' + month + ' ' + m[1];
  }

  function jsonList(value) {
    if (Array.isArray(value)) return value.map(String);
    try {
      const parsed = JSON.parse(value || '[]');
      return Array.isArray(parsed) ? parsed.map(String) : [];
    } catch (e) {
      return [];
    }
  }

  const careers = {
    initialized: false,

    async init() {
      if (this.initialized) return;
      this.initialized = true;
      try {
        const [jobs, benefits, stats, settings] = await Promise.all([
          PS.get('jobs', {}, { tier: 'dynamic' }),
          PS.get('benefits', {}, { tier: 'reference' }),
          PS.get('stats', {}, { tier: 'dynamic' }),
          PS.get('settings', {}, { tier: 'reference' }),
        ]);
        const statMap = PS.keyToMap(settings, 'setting_key', 'setting_value');

        this.renderBenefits(benefits);
        this.renderStaffStats(stats, statMap);
        this.renderJobs(jobs);
      } catch (err) {
        if (window.KINGSWAY_DEBUG) console.warn('[careers] render failed:', err);
      }
    },

    renderBenefits(data) {
      const el = document.getElementById('careers-benefits');
      if (!el) return;
      const list = PS.items(data);
      if (!list.length) { el.innerHTML = PS.emptyHTML(); return; }
      el.innerHTML = list.map((b) =>
        '<div class="d-flex align-items-start gap-3">' +
        '<div class="bg-success bg-opacity-10 rounded-2 p-2 flex-shrink-0">' +
        '<i class="bi ' + S(b.icon || 'bi-star-fill') + ' text-success fs-5"></i></div>' +
        '<div>' +
        '<div class="fw-semibold small">' + S(b.title) + '</div>' +
        '<div class="text-muted" style="font-size:.82rem">' + S(b.description) + '</div>' +
        '</div></div>'
      ).join('');
    },

    renderStaffStats(stats, m) {
      const el = document.getElementById('careers-stats');
      if (!el) return;
      const staff = (stats && typeof stats.staff === 'number') ? stats.staff : 0;
      const boxes = [
        { target: staff, suffix: '+', label: 'Qualified Staff', animated: true },
        { value: m.careers_stat_experience || '15+', label: 'Years Avg Experience' },
        { value: m.careers_stat_retention || '98%', label: 'Staff Retention Rate' },
        { value: m.careers_stat_cpd || '100%', label: 'CPD Participation' },
      ];
      el.innerHTML = boxes.map((b) =>
        '<div class="col-6">' +
        '<div class="p-4 bg-white border rounded-4 h-100">' +
        (b.animated
          ? '<div class="stat-number text-success mb-1" data-target="' + b.target + '" data-suffix="' + S(b.suffix) + '">0</div>'
          : '<div class="stat-number text-success mb-1">' + S(b.value) + '</div>') +
        '<div class="text-muted small">' + S(b.label) + '</div>' +
        '</div></div>'
      ).join('');
      if (window.PublicUI && window.PublicUI.observeCounters) window.PublicUI.observeCounters(el);
    },

    renderJobs(data) {
      const el = document.getElementById('careers-jobs');
      if (!el) return;
      const list = PS.items(data);
      const countBadge = document.getElementById('careers-count');
      if (countBadge) countBadge.textContent = list.length + ' Open';

      if (!list.length) {
        el.innerHTML =
          '<div class="text-center py-5">' +
          '<i class="bi bi-briefcase fs-1 text-muted d-block mb-3"></i>' +
          '<p class="text-muted">No open vacancies at the moment. Check back soon!</p></div>';
        return;
      }

      el.innerHTML = list.map((job, i) => {
        const color = job.color || '#198754';
        const deptName = job.department || 'Department';
        const req = jsonList(job.requirements);
        const reqTop = req.slice(0, 3);
        return '<div class="col-lg-6">' +
          '<div class="card-modern p-4 h-100 reveal delay-' + ((i % 3) + 1) + '">' +
          '<div class="d-flex align-items-start gap-3 mb-3">' +
          '<div class="job-icon" style="background:' + S(color) + '"><i class="bi bi-briefcase-fill"></i></div>' +
          '<div class="flex-grow-1">' +
          '<div class="d-flex flex-wrap gap-2 mb-1">' +
          '<span class="job-type" style="background:' + S(color) + '22;color:' + S(color) + '">' + S(job.job_type) + '</span>' +
          '<span class="job-type" style="background:#f1f5f9;color:#64748b">' + S(deptName) + '</span>' +
          '</div>' +
          '<h5 class="job-title">' + S(job.title) + '</h5>' +
          '<div class="job-meta">' +
          '<span><i class="bi bi-geo-alt text-success"></i>' + S(job.location) + '</span>' +
          '<span><i class="bi bi-calendar-x text-danger"></i>Closes ' + dateOnly(job.deadline) + '</span>' +
          '</div></div></div>' +
          '<p class="text-muted small mb-3">' + S(String(job.description || '').replace(/<[^>]*>/g, ' ').replace(/\s+/g, ' ').trim().slice(0, 150)) + '</p>' +
          (req.length
            ? '<div class="mb-3">' +
              '<div class="small fw-semibold mb-2 text-dark">Key Requirements:</div>' +
              '<ul class="list-unstyled mb-0">' +
              reqTop.map((r) =>
                '<li class="d-flex align-items-start gap-2 small text-muted mb-1">' +
                '<i class="bi bi-check-circle-fill text-success mt-1 flex-shrink-0"></i>' + S(r) +
                '</li>'
              ).join('') +
              (req.length > 3 ? '<li class="small text-muted ms-4">+' + (req.length - 3) + ' more</li>' : '') +
              '</ul></div>'
            : '') +
          '<div class="mt-auto pt-3 border-top d-flex gap-2">' +
          '<a href="' + base + '/index.php?route=re93c70de83f2&id=' + encodeURIComponent(job.id) + '" class="btn-kw-outline flex-grow-1 justify-content-center py-2" style="font-size:.85rem">' +
          '<i class="bi bi-info-circle"></i>View Details</a>' +
          '<button type="button" class="btn-kw-primary apply-job-btn justify-content-center py-2" style="font-size:.85rem" ' +
          'data-job-id="' + job.id + '" data-job-title="' + S(job.title) + '">' +
          '<i class="bi bi-send-fill"></i>Apply</button>' +
          '</div></div></div>';
      }).join('');

      // The Apply modal helpers are defined in the page's inline script.
      el.addEventListener('click', (e) => {
        const btn = e.target.closest('.apply-job-btn');
        if (btn && typeof window.openApplyModal === 'function') {
          window.openApplyModal(parseInt(btn.dataset.jobId, 10), btn.dataset.jobTitle || 'Position');
        }
      });
      if (window.PublicUI && window.PublicUI.observeReveals) window.PublicUI.observeReveals(el);
    },
  };

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => careers.init());
  } else {
    careers.init();
  }
})();
