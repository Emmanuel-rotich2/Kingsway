/* =============================================================================
   Downloads — js/pages/public/downloads.js
   Renders grouped document categories from GET /api/website/downloads and
   powers the #dlSearch filter. download_url is a signed public URL returned by
   the API, so each anchor points straight at the document.
   ============================================================================= */
(function () {
  'use strict';

  const PS = window.PublicSite;
  if (!PS) return;

  const S = PS.escapeHtml;

  const page = {
    initialized: false,
    items: [],
    generatedItems: [],
    generatedByKey: {},
    catalog: null,

    async init() {
      if (this.initialized) return;
      this.initialized = true;
      try {
        const [uploaded, generated] = await Promise.all([
          PS.get('downloads', {}, { tier: 'dynamic' }),
          PS.get('printable-downloads', {}, { tier: 'dynamic' }),
        ]);
        this.items = PS.items(uploaded);
        this.setGeneratedCatalog(generated);
        this.render();
        this.bindSearch();
        this.bindGeneratedDownloads();
      } catch (err) {
        if (window.KINGSWAY_DEBUG) console.warn('[downloads] render failed:', err);
        const el = document.getElementById('dl-categories');
        if (el) el.innerHTML = PS.errorHTML();
      }
    },

    setGeneratedCatalog(data) {
      this.catalog = data || {};
      this.generatedItems = PS.items(data).map((item) => Object.assign({}, item, {
        generated: true,
        file_type: 'PDF',
      }));
      this.generatedByKey = {};
      this.generatedItems.forEach((item) => { this.generatedByKey[item.key] = item; });

      // Intro line written by the school on the website workspace.
      const intro = document.getElementById('dl-intro');
      if (intro) {
        const text = String(data?.intro || '').trim();
        intro.textContent = text;
        intro.style.display = text ? '' : 'none';
      }

      const selectorWrap = document.getElementById('dl-year-selector');
      const selector = document.getElementById('dlAcademicYear');
      const years = Array.isArray(data?.academic_years) ? data.academic_years : [];
      if (!selectorWrap || !selector || years.length < 1) return;

      selector.innerHTML = years.map((year) => {
        const value = year.year_code || year.id;
        const label = year.year_name && year.year_name !== year.year_code
          ? `${year.year_code} — ${year.year_name}`
          : (year.year_code || year.year_name || year.id);
        const selected = String(value) === String(data.selected_academic_year || '') ? ' selected' : '';
        return `<option value="${S(value)}"${selected}>${S(label)}</option>`;
      }).join('');
      selectorWrap.style.display = '';
      selector.onchange = () => this.reloadGenerated(selector.value);
    },

    async reloadGenerated(year) {
      const selector = document.getElementById('dlAcademicYear');
      if (selector) selector.disabled = true;
      try {
        const data = await PS.get('printable-downloads', { academic_year: year }, { tier: 'dynamic' });
        this.setGeneratedCatalog(data);
        this.render();
      } catch (err) {
        if (window.KINGSWAY_DEBUG) console.warn('[downloads] printable catalog failed:', err);
      } finally {
        if (selector) selector.disabled = false;
      }
    },

    render() {
      const el = document.getElementById('dl-categories');
      if (!el) return;
      const allItems = this.items.concat(this.generatedItems);
      if (!allItems.length) {
        el.innerHTML = '<div class="text-center py-5"><i class="bi bi-folder-x fs-1 text-muted d-block mb-3"></i>' +
          '<p class="text-muted">No documents available yet.</p></div>';
        return;
      }

      // Group by category preserving the API ordering (category, display_order).
      const order = [];
      const groups = {};
      allItems.forEach((doc) => {
        const cat = doc.category || 'General';
        if (!groups[cat]) { groups[cat] = []; order.push(cat); }
        groups[cat].push(doc);
      });

      el.innerHTML = order.map((cat) => {
        const docs = groups[cat];
        return '<div class="mb-5 reveal dl-category">' +
          '<div class="d-flex align-items-center gap-3 mb-3">' +
          '<h4 class="fw-bold mb-0">' + S(cat) + '</h4>' +
          '<span class="badge bg-success">' + docs.length + ' document' + (docs.length !== 1 ? 's' : '') + '</span>' +
          '</div>' +
          '<div class="row g-3">' +
          docs.map((doc) => this.item(doc)).join('') +
          '</div></div>';
      }).join('');

      if (window.PublicUI && window.PublicUI.observeReveals) window.PublicUI.observeReveals(el);
    },

    item(doc) {
      const color = doc.color || '#198754';
      const title = String(doc.title || 'Document');
      return '<div class="col-lg-6 dl-item" data-title="' + S(title.toLowerCase()) + '">' +
        '<div class="download-item">' +
        '<div class="download-icon" style="background:' + S(color) + '22;color:' + S(color) + '">' +
        '<i class="bi ' + S(doc.icon || 'bi-file-earmark-pdf-fill') + '"></i></div>' +
        '<div class="flex-grow-1">' +
        this.downloadName(doc, title) +
        '<div class="download-meta">' +
        '<span class="tag me-1" style="background:' + S(color) + '22;color:' + S(color) + ';padding:2px 8px;font-size:.68rem">' + S(doc.file_type || 'PDF') + '</span>' +
        (doc.file_size ? '<span>' + S(doc.file_size) + '</span>' : '') +
        '</div></div>' +
        this.downloadAction(doc, title) +
        '</div></div>';
    },

    downloadName(doc, title) {
      if (doc.generated && doc.key) {
        return '<button type="button" class="download-name dl-generated-name" data-generated-key="' + S(doc.key) + '">' + S(title) + '</button>';
      }
      return doc.download_url
        ? '<a class="download-name" href="' + S(doc.download_url) + '" rel="noopener">' + S(title) + '</a>'
        : '<div class="download-name">' + S(title) + '</div>';
    },

    downloadAction(doc, title) {
      if (doc.generated && doc.key) {
        return '<button type="button" class="download-btn dl-generated-trigger" data-generated-key="' + S(doc.key) + '" title="Download ' + S(title) + '"><i class="bi bi-download"></i></button>';
      }
      return doc.download_url
        ? '<a href="' + S(doc.download_url) + '" class="download-btn" title="Download ' + S(title) + '" rel="noopener"><i class="bi bi-download"></i></a>'
        : '';
    },

    bindSearch() {
      const input = document.getElementById('dlSearch');
      if (!input) return;
      input.addEventListener('input', () => {
        const q = input.value.toLowerCase().trim();
        document.querySelectorAll('.dl-item').forEach((item) => {
          const haystack = (item.dataset.title || item.textContent || '').toLowerCase();
          item.style.display = (!q || haystack.includes(q)) ? '' : 'none';
        });
      });
    },

    bindGeneratedDownloads() {
      const el = document.getElementById('dl-categories');
      if (!el) return;
      el.addEventListener('click', (event) => {
        const trigger = event.target.closest('.dl-generated-trigger, .dl-generated-name');
        if (!trigger) return;
        event.preventDefault();
        this.generateDownload(trigger.dataset.generatedKey);
      });
    },

    async generateDownload(key) {
      const doc = this.generatedByKey[key];
      if (!doc) return;
      const triggers = document.querySelectorAll(`[data-generated-key="${CSS.escape(key)}"]`);
      triggers.forEach((trigger) => {
        trigger.disabled = true;
        trigger.setAttribute('aria-busy', 'true');
      });
      try {
        const data = await PS.get('printable-download', {
          kind: doc.kind,
          scope: doc.scope,
          student_type: doc.student_type,
          academic_year: this.catalog?.selected_academic_year || '',
        }, { tier: 'dynamic' });
        if (!data?.download_url) throw new Error('No download URL returned.');
        window.location.assign(data.download_url);
      } catch (err) {
        if (window.KINGSWAY_DEBUG) console.warn('[downloads] PDF generation failed:', err);
        window.alert('This PDF could not be generated right now. Please try again.');
      } finally {
        triggers.forEach((trigger) => {
          trigger.disabled = false;
          trigger.removeAttribute('aria-busy');
        });
      }
    },
  };

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => page.init());
  } else {
    page.init();
  }
})();
