/* =============================================================================
   Job Detail — js/pages/public/job-detail.js
   Renders a single vacancy from GET /api/website/jobs/<id> (fetched through
   PublicSite so DataStore caches the per-id variant `website/jobs:{"id":n}`).
   A missing/invalid id falls back to the careers listing client-side.
   ============================================================================= */
(function () {
  'use strict';

  const PS = window.PublicSite;
  if (!PS) return;

  const S = PS.escapeHtml;
  const base = String(window.APP_BASE || '').replace(/\/+$/, '');

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

  const page = {
    initialized: false,

    async init() {
      if (this.initialized) return;
      this.initialized = true;

      const id = new URLSearchParams(window.location.search).get('id');
      if (!id || !/^\d+$/.test(id)) {
        window.location.replace(base + '/index.php?route=ra5d49bd64f1c');
        return;
      }

      try {
        const job = await PS.get('jobs', { id }, { tier: 'dynamic' });
        this.render(job);
        PS.bumpView('jobs', id);
      } catch (err) {
        if (window.KINGSWAY_DEBUG) console.warn('[job-detail] not found or failed:', err);
        window.location.replace(base + '/index.php?route=ra5d49bd64f1c');
      }
    },

    render(job) {
      const titleEl = document.getElementById('jd-title');
      const crumbEl = document.getElementById('jd-crumb');
      const metaEl = document.getElementById('jd-meta');
      const descEl = document.getElementById('jd-description');
      const respEl = document.getElementById('jd-responsibilities');
      const reqEl = document.getElementById('jd-requirements');
      const applyBtn = document.getElementById('jd-apply-btn');
      const quickJob = document.querySelector('input[name="apply_job_id"]');

      const color = job.color || '#198754';
      const title = String(job.title || 'Position');

      if (titleEl) titleEl.textContent = title;
      if (crumbEl) crumbEl.textContent = title.slice(0, 40) + (title.length > 40 ? '…' : '');
      if (quickJob) quickJob.value = job.id;

      if (metaEl) {
        metaEl.innerHTML =
          '<div class="d-flex flex-wrap gap-3 mb-3">' +
          '<span class="tag text-white" style="background:' + S(color) + '">' + S(job.job_type) + '</span>' +
          '<span class="tag bg-white border text-dark">' + S(job.department || 'Department') + '</span>' +
          '<span class="tag bg-white border text-dark"><i class="bi bi-geo-alt me-1"></i>' + S(job.location) + '</span>' +
          '</div>' +
          '<div class="d-flex flex-wrap gap-4 text-muted small">' +
          '<span><i class="bi bi-calendar-x text-danger me-1"></i>Closes: ' + dateOnly(job.deadline) + '</span>' +
          '<span><i class="bi bi-clock me-1"></i>' + S(job.job_type) + '</span>' +
          '</div>';
      }

      if (descEl) {
        descEl.textContent = String(job.description || '');
      }

      const resp = jsonList(job.responsibilities);
      if (respEl) {
        respEl.innerHTML = resp.length
          ? resp.map((r) => this.li(r)).join('')
          : '<li class="text-muted small">No responsibilities listed.</li>';
      }

      const req = jsonList(job.requirements);
      if (reqEl) {
        reqEl.innerHTML = req.length
          ? req.map((r) => this.li(r)).join('')
          : '<li class="text-muted small">No requirements listed.</li>';
      }

      if (applyBtn) {
        applyBtn.addEventListener('click', () => {
          if (typeof window.openApplyModal === 'function') {
            window.openApplyModal(parseInt(job.id, 10), title);
          }
        });
      }

      const root = metaEl || descEl || document.body;
      if (window.PublicUI && window.PublicUI.observeReveals) window.PublicUI.observeReveals(root);
    },

    li(text) {
      return '<li class="d-flex align-items-start gap-2 mb-2">' +
        '<i class="bi bi-check-circle-fill text-success mt-1 flex-shrink-0"></i>' +
        '<span>' + PS.escapeHtml(text) + '</span></li>';
    },
  };

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => page.init());
  } else {
    page.init();
  }
})();
