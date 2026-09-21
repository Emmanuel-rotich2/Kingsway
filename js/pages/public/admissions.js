/* =============================================================================
   Admissions — js/pages/public/admissions.js
   Fills the live admissions data: the intake banner, response time / age range /
   enquiries strip, the "Grade Applying For" dropdown (/api/website/grades) and
   the "Preferred Start Term" dropdown (/api/website/terms). The multi-step form
   navigation and submission live in the page's inline script.
   ============================================================================= */
(function () {
  'use strict';

  const PS = window.PublicSite;
  if (!PS) return;

  const S = (v) => PS.escapeHtml(v);

  const admissions = {
    initialized: false,

    async init() {
      if (this.initialized) return;
      this.initialized = true;
      try {
        const [terms, grades, settings] = await Promise.all([
          PS.get('terms', {}, { tier: 'reference' }),
          PS.get('grades', {}, { tier: 'reference' }),
          PS.get('settings', {}, { tier: 'reference' }),
        ]);
        const termList = PS.items(terms);
        const statMap = PS.keyToMap(settings, 'setting_key', 'setting_value');

        this.renderQuickBar(termList, statMap);
        const configuredGrades = termList.flatMap((term) => {
          try { return JSON.parse(term.eligible_grades || '[]'); } catch (_) { return []; }
        });
        const allowedGrades = [...new Set(configuredGrades)];
        this.renderGradeOptions(allowedGrades.length ? allowedGrades : PS.items(grades));
        this.renderTermOptions(termList);
      } catch (err) {
        if (window.KINGSWAY_DEBUG) console.warn('[admissions] render failed:', err);
      }
    },

    renderQuickBar(terms, m) {
      const set = (id, val) => { const node = document.getElementById(id); if (node) node.textContent = val; };
      const lead = terms[0];
      set('ad-intake', lead
        ? lead.name + ' ' + lead.year + ' intake now open'
        : 'No admission intake is currently open');
      set('ad-response', m.admissions_response || 'Within 24 working hours');
      set('ad-age-range', m.admissions_age_range || '4 – 15 years (PP1 – Grade 9)');
      set('ad-enquiries', m.school_phone_main || m.school_phone || '0720 113 030');
    },

    renderGradeOptions(grades) {
      const select = document.querySelector('[name="grade_applying"]');
      if (!select) return;
      // The public intake range is intentionally limited to Playgroup–Grade 8.
      // Do not let term configuration or the reference endpoint add Grade 9.
      const allowedGrades = [
        'Playgroup', 'PP1', 'PP2',
        'Grade1', 'Grade2', 'Grade3', 'Grade4',
        'Grade5', 'Grade6', 'Grade7', 'Grade8',
      ];
      select.innerHTML = '<option value="">Select grade</option>' +
        allowedGrades.map((g) => {
          const label = g.replace(/^Grade(\d+)$/, 'Grade $1');
          return '<option value="' + S(g) + '">' + S(label) + '</option>';
        }).join('');
      // Re-run the page's grade-driven document requirement logic now that real
      // options exist (harmless no-op if the page-level script already ran).
      if (typeof window.adSyncDocumentRequirements === 'function') window.adSyncDocumentRequirements();
    },

    renderTermOptions(terms) {
      const select = document.querySelector('[name="preferred_start"]');
      if (!select) return;
      if (!terms.length) {
        select.innerHTML = '<option value="">Select term</option>' +
          '<option value="" disabled>No intake terms open right now</option>';
        select.disabled = true;
        return;
      }
      select.innerHTML = '<option value="">Select term</option>' +
        terms.map((t) => {
          const token = t.name + ' ' + t.year;
          const label = token + ' (' + (t.status ? t.status.charAt(0).toUpperCase() + t.status.slice(1) : '') + ')';
          // Carry the real academic_year_terms.id so the server can resolve the
          // application's target term even without re-deriving it from the label.
          return '<option value="' + S(token) + '" data-term-id="' + S(t.id) + '">' + S(label) + '</option>';
        }).join('');
      select.disabled = false;
    },
  };

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => admissions.init());
  } else {
    admissions.init();
  }
})();
