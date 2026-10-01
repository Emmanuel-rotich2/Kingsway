/* Shared read-only fee-structure matrix for review and approval screens. */
(function () {
  const esc = (value) => String(value ?? '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#039;');
  const list = (response, key) => {
    if (Array.isArray(response)) return response;
    return response?.[key] || response?.data?.[key] || response?.data?.items || response?.items || response?.data || [];
  };

  window.FeeStructureMatrix = {
    async load(academicYear, studentTypeIds) {
      const [classesResponse, typesResponse] = await Promise.all([
        window.API.academic.listClasses(),
        window.API.finance.listStudentTypes(),
      ]);
      const classes = list(classesResponse, 'classes').slice().sort((a, b) => Number(a.sort_order || a.id) - Number(b.sort_order || b.id));
      const types = list(typesResponse, 'student_types').filter(t => String(t.code || '').toUpperCase() !== 'WEEKLY');
      const ids = (studentTypeIds || types.map(t => t.id)).map(Number);
      const response = await window.API.apiCall(`/finance/fees-bundle-grid?academic_year=${encodeURIComponent(academicYear)}&from_id=${classes[0]?.id}&to_id=${classes[classes.length - 1]?.id}&student_type_ids=${ids.join(',')}`, 'GET');
      const data = response?.class_grid ? response : (response?.data || response || {});
      return { classes, types, grid: data.class_grid || {} };
    },

    render(container, model) {
      if (!container) return;
      const terms = [1, 2, 3];
      const classes = model.classes || [];
      const types = model.types || [];
      const grid = model.grid || {};
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
        filteredClasses.forEach(cls => filteredTypes.forEach((type, index) => {
        const values = terms.map(n => Number(grid?.[cls.id]?.SCHOOL_FEES?.[`term${n}`]?.[String(type.id)] || 0));
        const hasValue = values.some(v => v > 0);
        html += `<tr>${index === 0 ? `<td rowspan="${filteredTypes.length}" class="fw-semibold align-middle">${esc(className(cls))}</td>` : ''}<td class="small fw-semibold">${esc(type.name || type.code || type.id)}</td>${values.map(v => `<td class="text-end">${hasValue && v ? v.toLocaleString('en-KE') : '–'}</td>`).join('')}<td class="text-end fw-semibold">${hasValue ? values.reduce((a, v) => a + v, 0).toLocaleString('en-KE') : '–'}</td></tr>`;
        }));
        const totals = terms.map(n => filteredClasses.reduce((sum, cls) => filteredTypes.reduce((s, type) => s + Number(grid?.[cls.id]?.SCHOOL_FEES?.[`term${n}`]?.[String(type.id)] || 0), sum), 0));
      html += `</tbody><tfoot class="table-light"><tr><td colspan="2" class="fw-bold">Column Total</td>${totals.map(v => `<td class="text-end fw-bold">${v ? v.toLocaleString('en-KE') : '–'}</td>`).join('')}<td class="text-end fw-bold">${totals.reduce((a, v) => a + v, 0).toLocaleString('en-KE')}</td></tr></tfoot></table></div>`;
        table.innerHTML = html;
      };
      container.querySelectorAll('select').forEach(select => select.addEventListener('change', draw));
      draw();
    },
  };
})();
