/* Shared read-only fee-structure matrix for review and approval screens. */
(function () {
  const esc = (value) => String(value ?? '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#039;');
  const list = (response, key) => {
    if (Array.isArray(response)) return response;
    return response?.[key] || response?.data?.[key] || response?.data?.items || response?.items || response?.data || [];
  };

  window.FeeStructureMatrix = {
    async load(academicYear, studentTypeIds) {
      const [classesResponse, typesResponse, yearsResponse, studentsResponse, parentsResponse] = await Promise.all([
        window.API.academic.listClasses(),
        window.API.finance.listStudentTypes(),
        window.API.finance.getExtraChargesAcademicYears().catch(() => ({ years: [] })),
        window.API.students.getAll({ limit: 500, offset: 0, status: 'active' }).catch(() => ({ data: [] })),
        window.API.students.getParentsList({ limit: 500, offset: 0, status: 'active' }).catch(() => ({ parents: [] })),
      ]);
      const classes = list(classesResponse, 'classes').slice().sort((a, b) => Number(a.sort_order || a.id) - Number(b.sort_order || b.id));
      const types = list(typesResponse, 'student_types').filter(t => String(t.code || '').toUpperCase() !== 'WEEKLY');
      const years = list(yearsResponse, 'years');
      const students = Array.isArray(studentsResponse?.data) ? studentsResponse.data : list(studentsResponse, 'students');
      const parentsPayload = parentsResponse?.data?.data || parentsResponse?.data || parentsResponse;
      const parents = Array.isArray(parentsPayload) ? parentsPayload : (parentsPayload?.parents || parentsPayload?.items || []);
      // Fee structures name their year by its starting year ("2026") while
      // the year list carries the full code ("2026/2027"). Comparing the two
      // as written never matched, so the year's extra charges were never
      // loaded. Match on the exact code or id first, then on the start year.
      const startYear = (value) => (String(value ?? '').match(/\d{4}/) || [''])[0];
      const wanted = String(academicYear ?? '');
      const yearRow = years.find(y => [y.year_code, y.year_name].some(v => String(v ?? '') === wanted))
        || (/^\d{1,3}$/.test(wanted) ? years.find(y => String(y.id) === wanted) : null)
        || (startYear(wanted) ? years.find(y => startYear(y.year_code || y.year_name) === startYear(wanted)) : null);
      const chargesResponse = yearRow ? await window.API.finance.listExtraCharges({ academic_year: yearRow.id, status: 'active' }).catch(() => ({ charges: [] })) : { charges: [] };
      const ids = (studentTypeIds || types.map(t => t.id)).map(Number);
      const response = await window.API.apiCall(`/finance/fees-bundle-grid?academic_year=${encodeURIComponent(academicYear)}&from_id=${classes[0]?.id}&to_id=${classes[classes.length - 1]?.id}&student_type_ids=${ids.join(',')}`, 'GET');
      const data = response?.class_grid ? response : (response?.data || response || {});
      return { classes, types, students, parents, grid: data.class_grid || {}, extraCharges: list(chargesResponse, 'charges') };
    },

    render(container, model) {
      if (!container) return;
      const terms = [1, 2, 3];
      const classes = model.classes || [];
      const types = model.types || [];
      const grid = model.grid || {};
      const extraCharges = model.extraCharges || [];
      const students = model.students || [];
      const parents = model.parents || [];
      const levelId = (c) => String(c.level_id ?? c.school_level_id ?? c.level_code ?? c.level_name ?? c.level ?? '');
      const levelName = (c) => c.level_name || c.school_level_name || c.level || c.level_code || 'Other';
      const className = (c) => c.name || c.class_name || c.code || c.class_code || c.id;
      const levels = [...new Map(classes.map(c => [levelId(c), levelName(c)])).entries()]
        .filter(([id]) => id !== '');
      container.innerHTML = `<div class="row g-2 mb-3"><div class="col-md-4"><label class="form-label small fw-semibold mb-1">School Level</label><select class="form-select form-select-sm" data-matrix-level><option value="">All school levels</option>${levels.map(([id, name]) => `<option value="${esc(id)}">${esc(name)}</option>`).join('')}</select></div><div class="col-md-4"><label class="form-label small fw-semibold mb-1">Grade</label><select class="form-select form-select-sm" data-matrix-class><option value="">All grades</option>${classes.map(c => `<option value="${esc(c.id)}">${esc(className(c))}</option>`).join('')}</select></div><div class="col-md-4"><label class="form-label small fw-semibold mb-1">Student Type</label><select class="form-select form-select-sm" data-matrix-type><option value="">Day + Full Boarder</option>${types.map(t => `<option value="${esc(t.id)}">${esc(t.name || t.code || t.id)}</option>`).join('')}</select></div></div><div data-matrix-table></div>`;
      const table = container.querySelector('[data-matrix-table]');
      const draw = () => {
        const level = container.querySelector('[data-matrix-level]')?.value || '';
        const classId = container.querySelector('[data-matrix-class]')?.value || '';
        const typeId = container.querySelector('[data-matrix-type]')?.value || '';
        const filteredClasses = classes.filter(c => (!level || levelId(c) === level) && (!classId || String(c.id) === classId));
        const filteredTypes = types.filter(t => !typeId || String(t.id) === typeId);
        // Headers must match the value order: terms = [1, 2, 3] ascending, so
        // Term 1 is column 1. The hardcoded descending header put Term 1's
        // amount under the "Term 3" heading and flipped the display.
        let html = '<div class="table-responsive"><table class="table table-bordered table-sm align-middle mb-0"><thead class="table-success text-center"><tr><th class="text-start">Grade</th><th class="text-start">Student Type</th><th>Term 1</th><th>Term 2</th><th>Term 3</th><th>Total</th></tr></thead><tbody>';
        filteredClasses.forEach(cls => {
          const applicableTypes = filteredTypes.filter(type =>
            terms.some(n => Number(grid?.[cls.id]?.SCHOOL_FEES?.[`term${n}`]?.[String(type.id)] || 0) > 0),
          );
          applicableTypes.forEach((type, index) => {
            const values = terms.map(n => Number(grid?.[cls.id]?.SCHOOL_FEES?.[`term${n}`]?.[String(type.id)] || 0));
            html += `<tr>${index === 0 ? `<td rowspan="${applicableTypes.length}" class="fw-semibold align-middle">${esc(className(cls))}</td>` : ''}<td class="small fw-semibold">${esc(type.name || type.code || type.id)}</td>${values.map(v => `<td class="text-end">${v > 0 ? v.toLocaleString('en-KE') : '–'}</td>`).join('')}<td class="text-end fw-semibold">${values.reduce((a, v) => a + v, 0).toLocaleString('en-KE')}</td></tr>`;
          });
        });
        const totals = terms.map(n => filteredClasses.reduce((sum, cls) => filteredTypes.reduce((s, type) => s + Number(grid?.[cls.id]?.SCHOOL_FEES?.[`term${n}`]?.[String(type.id)] || 0), sum), 0));
      html += `</tbody><tfoot class="table-light"><tr><td colspan="2" class="fw-bold">Column Total</td>${totals.map(v => `<td class="text-end fw-bold">${v ? v.toLocaleString('en-KE') : '–'}</td>`).join('')}<td class="text-end fw-bold">${totals.reduce((a, v) => a + v, 0).toLocaleString('en-KE')}</td></tr></tfoot></table></div>`;
        table.innerHTML = html;
        container.querySelector('[data-matrix-extra-charges]')?.remove();
        const names = (ids, options) => (ids || []).map(id => { const found = options.find(item => String(item.id) === String(id)); return found?.name || found?.class_name || found?.code || `#${id}`; });
        const personNames = (ids, options, kind) => (ids || []).map(id => {
          const found = options.find(item => String(item.id) === String(id));
          const name = found?.full_name || found?.student_name || found?.name || [found?.first_name, found?.middle_name, found?.last_name].filter(Boolean).join(' ');
          return name || `${kind} ${id}`;
        });
        const applicableCharges = extraCharges.filter(charge => {
          const classIds = charge.class_ids || (charge.class_id ? [charge.class_id] : []);
          const typeIds = charge.student_type_ids || (charge.student_type_id ? [charge.student_type_id] : []);
          return (!classId || !classIds.length || classIds.map(String).includes(classId)) && (!typeId || !typeIds.length || typeIds.map(String).includes(typeId));
        });
        const extraSection = document.createElement('section');
        extraSection.className = 'mt-4';
        extraSection.dataset.matrixExtraCharges = 'true';
        extraSection.innerHTML = `<h6>Extra charges</h6>${applicableCharges.length ? `<div class="table-responsive"><table class="table table-bordered table-sm align-middle"><thead class="table-light"><tr><th>Charge</th><th>Applies to</th><th>Classes</th><th>Learner types</th><th>Selected learners</th><th>Selected parents</th><th>Billing</th><th>Frequency</th><th>Pricing tiers (KES)</th><th>Credit account</th></tr></thead><tbody>${applicableCharges.map(charge => {
          const classIds = charge.class_ids || (charge.class_id ? [charge.class_id] : []);
          const typeIds = charge.student_type_ids || (charge.student_type_id ? [charge.student_type_id] : []);
          const studentIds = charge.student_ids || [];
          const parentIds = charge.parent_ids || [];
          const scope = { all_students: 'All learners', existing_students: 'Existing learners', new_admissions: 'New admissions', boarders: 'Boarders', day_students: 'Day learners', specific_class: 'Specific class' }[charge.target_scope] || charge.target_scope || '—';
          const tiers = (charge.pricing_tiers || []).map(tier => { const rule = String(tier.condition || 'default'); const label = rule === 'default' ? 'Default' : rule.replace(/^class:/, 'Class ').replace(/^student_type:/, 'Learner type ').replaceAll('_', ' '); return `<div>${esc(tier.label || label)} · ${esc(label)}: ${Number(tier.amount || 0).toLocaleString('en-KE')}</div>`; }).join('') || Number(charge.amount || 0).toLocaleString('en-KE');
          return `<tr><td>${esc(charge.name)}</td><td>${esc(scope)}</td><td>${esc(names(classIds, classes).join(', ') || 'All classes')}</td><td>${esc(names(typeIds, types).join(', ') || 'All types')}</td><td>${esc(personNames(studentIds, students, 'Learner').join(', ') || 'Any learner in scope')}</td><td>${esc(personNames(parentIds, parents, 'Parent').join(', ') || 'Any parent in scope')}</td><td>${esc(charge.billing_model || '—')}</td><td>${esc(charge.billing_frequency || '—')}</td><td>${tiers}</td><td>${esc(charge.gl_account_name || '—')}</td></tr>`;
        }).join('')}</tbody></table></div>` : '<p class="small text-muted mb-0">No active extra charges for this year.</p>'}`;
        table.after(extraSection);
      };
      container.querySelectorAll('select').forEach(select => select.addEventListener('change', draw));
      draw();
    },
  };
})();
