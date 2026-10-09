/* SummativePivotTable — pivoted learner-results table for the Results workspace.
   Each learner appears exactly once; subjects are dynamic columns. Owns inline
   editing, the row kebab menu, autosave, optimistic updates with rollback,
   and CSV/print. Data reshaping lives in js/utils/results_pivot.js, never here.

   Structure: toolbar (autosave toggle, export, print, paging) / table container
   / row renderer / editable cell / actions menu / save-status indicator —
   each a small named unit below. */
(() => {
  'use strict';

  const P = () => window.KingswayResultsPivot;
  const PER_PAGE = 100;

  const escAttr = (v) => String(v ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&', '<': '<', '>': '>', '"': '"', "'": '&#39;' }[c]));

  function create(mount, opts) {
    const o = Object.assign({ canEdit: false, userId: '', perPage: PER_PAGE, readOnly: false, prebuiltPivot: null }, opts || {});
    // A read-only mount (locked or published register) never enters edit mode.
    if (o.readOnly) o.canEdit = false;
    const esc = o.esc || ((v) => { const n = document.createElement('span'); n.textContent = String(v ?? ''); return n.innerHTML; });
    const notify = o.notify || (() => {});
    // Prebuilt pivots (paper grids) arrive already shaped; flat result rows
    // are reshaped here through the single shared utility.
    const pivot = o.prebuiltPivot ? o.prebuiltPivot : P().pivotStudentRows(o.rows || []);
    const state = {
      sort: { key: 'learner_name', dir: 'asc' },
      page: 1,
      editKey: null,
      snapshot: null,
      dirty: new Map(),
      errors: new Map(),
      save: new Map(),
      autosave: localStorage.getItem(`vrp:autosave:${o.userId}`) !== '0',
      menuFor: null,
    };

    function studentKeyByName(name) {
      const s = pivot.students.find(st => st.learner_name === name);
      return s ? s.key : '';
    }
    const debouncer = P().createDebouncedSave((key) => saveRow(key, { autosave: true }), 800);

    const headerLabel = (col) => esc(col.subject) + (pivot.multiPeriod ? ` <small class="text-muted d-block">${esc(col.period_title)}</small>` : '');
    const saveStateOf = (key) => state.save.get(key) || { status: 'idle' };

    function editableCell(student, col) {
      const cell = student.cells[col.key];
      const idAttr = `vrp-in-${escAttr(student.key)}-${escAttr(col.key)}`;
      const value = state.dirty.has(`${student.key}|${col.key}`)
        ? state.dirty.get(`${student.key}|${col.key}`)
        : (cell && cell.marks_obtained !== null ? cell.marks_obtained : '');
      const error = state.errors.get(`${student.key}|${col.key}`) || '';
      return `<td class="vrp-cell ${state.dirty.has(`${student.key}|${col.key}`) ? 'vrp-dirty' : ''} ${error ? 'vrp-invalid' : ''}">
        <input id="${idAttr}" class="form-control form-control-sm vrp-input" type="text" inputmode="decimal"
          autocomplete="off" value="${escAttr(value === null ? '' : value)}"
          data-student="${escAttr(student.key)}" data-col="${escAttr(col.key)}"
          aria-label="${escAttr(`${col.subject} mark for ${student.learner_name}, out of ${col.max_marks}`)}"
          ${error ? `aria-invalid="true" aria-describedby="${idAttr}-err"` : ''}>
        ${error ? `<div id="${idAttr}-err" class="vrp-err" role="alert">${esc(error)}</div>` : ''}
      </td>`;
    }

    function readCell(student, col) {
      const cell = student.cells[col.key];
      if (!cell) return '<td class="vrp-cell"><span class="text-muted">—</span></td>';
      if (cell.deleted) return `<td class="vrp-cell"><span class="text-decoration-line-through text-muted" title="Result deleted">${esc(cell.marks_obtained ?? '—')}</span></td>`;
      if (cell.entry_status === 'absent') return `<td class="vrp-cell text-center" title="Absent"><span class="badge bg-warning-subtle text-warning-emphasis border">A</span></td>`;
      if (cell.entry_status === 'exempted') return `<td class="vrp-cell text-center" title="Exempted"><span class="badge bg-info-subtle text-info-emphasis border">E</span></td>`;
      if (cell.marks_obtained === null) return '<td class="vrp-cell text-center"><span class="text-muted">—</span></td>';
      const grade = cell.grade ? `<span class="vrp-grade">${esc(cell.grade)}</span>` : '';
      return `<td class="vrp-cell text-center vrp-mark" data-sort="${escAttr(cell.marks_obtained)}">${esc(cell.marks_obtained)}${grade}</td>`;
    }

    function rowHtml(student) {
      const editing = state.editKey === student.key;
      const st = saveStateOf(student.key);
      const hasResults = Object.values(student.cells).some((c) => c.result_id && !c.deleted);
      const dirtyCount = [...state.dirty.keys()].filter((k) => k.startsWith(`${student.key}|`)).length;
      return `<tr data-student="${escAttr(student.key)}" class="${editing ? 'vrp-editing table-active' : ''}">
        <th scope="row" class="vrp-sticky vrp-sticky-1 fw-semibold"><a href="#" class="text-decoration-none vr-learner-link" data-learner-name="${escAttr(student.learner_name)}" data-learner-adm="${escAttr(student.admission_no)}">${esc(student.learner_name)}</a></th>
        <td class="vrp-sticky vrp-sticky-2">${esc(student.admission_no || '—')}</td>
        <td class="vrp-sticky vrp-sticky-3">${esc(student.class_name || '—')}${student.stream_name ? ` · ${esc(student.stream_name)}` : ''}</td>
        ${pivot.columns.map((col) => (editing ? editableCell(student, col) : readCell(student, col))).join('')}
        ${o.canEdit ? `<td class="vrp-sticky vrp-sticky-actions">
          <div class="d-flex align-items-center gap-2">
            ${saveStatusBadge(student.key, st)}
            <div class="dropdown position-static">
              <button class="btn btn-sm btn-outline-secondary vrp-kebab" type="button" data-kebab="${escAttr(student.key)}"
                aria-haspopup="menu" aria-expanded="false" aria-label="Actions for ${escAttr(student.learner_name)}">
                <i class="bi bi-three-dots-vertical" aria-hidden="true"></i></button>
              <ul class="dropdown-menu vrp-menu" role="menu" data-menu="${escAttr(student.key)}" style="display:none">
                <li role="none"><button class="dropdown-item" role="menuitem" data-action="add" ${editing ? 'disabled' : ''}><i class="bi bi-plus-circle me-2"></i>Add results</button></li>
                <li role="none"><button class="dropdown-item" role="menuitem" data-action="edit" ${editing ? 'disabled' : ''}><i class="bi bi-pencil me-2"></i>Edit</button></li>
                <li role="none"><button class="dropdown-item" role="menuitem" data-action="save" ${dirtyCount ? '' : 'disabled'}><i class="bi bi-save me-2"></i>Save</button></li>
                ${editing ? '<li role="none"><button class="dropdown-item" role="menuitem" data-action="cancel"><i class="bi bi-x-circle me-2"></i>Cancel</button></li>' : ''}
                ${o.deleteResult && hasResults ? `<li role="none"><hr class="dropdown-divider"></li><li role="none"><button class="dropdown-item text-danger" role="menuitem" data-action="delete"><i class="bi bi-trash me-2"></i>Delete results</button></li>` : ''}
              </ul>
            </div>
          </div>
        </td>` : `<td class="vrp-sticky vrp-sticky-actions">${saveStatusBadge(student.key, st)}</td>`}
      </tr>`;
    }

    /** Save-status indicator: idle / saving spinner / saved-with-time / error+retry. */
    function saveStatusBadge(key, st) {
      if (st.status === 'saving') return '<span class="vrp-status text-muted"><span class="spinner-border spinner-border-sm" role="status" aria-label="Saving"></span></span>';
      if (st.status === 'saved') return `<span class="vrp-status text-success" title="Saved ${escAttr(st.at || '')}"><i class="bi bi-check2-circle"></i></span>`;
      if (st.status === 'error') return `<button class="btn btn-sm btn-outline-danger vrp-status vrp-retry" data-retry="${escAttr(key)}" title="${escAttr(st.message || 'Save failed — retry')}"><i class="bi bi-arrow-clockwise"></i></button>`;
      return '<span class="vrp-status text-muted vrp-idle"></span>';
    }

    function sortedStudents() {
      return P().sortStudents(pivot.students, state.sort.key, state.sort.dir, pivot.columns);
    }

    function render() {
      if (!pivot.students.length || !pivot.columns.length) {
        mount.innerHTML = `<div class="vrp-empty"><i class="bi bi-inbox"></i>${o.emptyMessage ? esc(o.emptyMessage) : 'No learner result rows match this scope.'}</div>`;
        return;
      }
      const sorted = sortedStudents();
      const pages = Math.max(1, Math.ceil(sorted.length / o.perPage));
      state.page = Math.min(state.page, pages);
      const slice = sorted.slice((state.page - 1) * o.perPage, state.page * o.perPage);
      const sortIndicator = (key) => state.sort.key === key ? `<i class="bi bi-caret-${state.sort.dir === 'asc' ? 'up' : 'down'}-fill ms-1" aria-hidden="true"></i>` : '';
      const arrow = (key) => `aria-sort="${state.sort.key === key ? (state.sort.dir === 'asc' ? 'ascending' : 'descending') : 'none'}"`;

      mount.innerHTML = `
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-2 vrp-toolbar vr-no-print">
          <div class="d-flex align-items-center gap-3">
            <label class="form-check form-switch mb-0" title="Save each row automatically shortly after you stop typing">
              <input class="form-check-input" type="checkbox" role="switch" id="vrpAutosave" ${state.autosave ? 'checked' : ''}>
              <span class="form-check-label small">Autosave</span>
            </label>
            <small class="text-muted">${pivot.students.length} learners · ${pivot.columns.length} subject column(s)</small>
          </div>
          <div class="d-flex align-items-center gap-2">
            ${o.canExport ? '<button class="btn btn-sm btn-outline-secondary" id="vrpCsv" title="Export the whole table as CSV"><i class="bi bi-filetype-csv me-1"></i>CSV</button>' : ''}
            ${o.canPrint ? '<button class="btn btn-sm btn-outline-secondary" id="vrpPrint" title="Print / save as PDF"><i class="bi bi-printer me-1"></i>Print</button>' : ''}
            ${pages > 1 ? `<nav aria-label="Table pages"><ul class="pagination pagination-sm mb-0">
              <li class="page-item ${state.page <= 1 ? 'disabled' : ''}"><button class="page-link" data-page="${state.page - 1}" aria-label="Previous page">&laquo;</button></li>
              <li class="page-item disabled"><span class="page-link">${state.page} / ${pages}</span></li>
              <li class="page-item ${state.page >= pages ? 'disabled' : ''}"><button class="page-link" data-page="${state.page + 1}" aria-label="Next page">&raquo;</button></li>
            </ul></nav>` : ''}
          </div>
        </div>
        <div class="vrp-scroll">
          <table class="table table-sm table-hover align-middle vrp-table" id="vrpTable">
            <thead class="vrp-head"><tr>
              <th scope="col" class="vrp-sticky vrp-sticky-1 vrp-sortable" data-sort="learner_name" ${arrow('learner_name')}>Learner${sortIndicator('learner_name')}</th>
              <th scope="col" class="vrp-sticky vrp-sticky-2 vrp-sortable" data-sort="admission_no" ${arrow('admission_no')}>Admission no.${sortIndicator('admission_no')}</th>
              <th scope="col" class="vrp-sticky vrp-sticky-3 vrp-sortable" data-sort="class" ${arrow('class')}>Class / stream${sortIndicator('class')}</th>
              ${pivot.columns.map((col) => `<th scope="col" class="text-center vrp-sortable vrp-colhead" data-sort="${escAttr(col.key)}" ${arrow(col.key)}
                title="${escAttr(`${col.subject} — out of ${col.max_marks}${pivot.multiPeriod ? ` · ${col.period_title}` : ''}`)}">${headerLabel(col)}${sortIndicator(col.key)}</th>`).join('')}
              <th scope="col" class="vrp-sticky vrp-sticky-actions">${o.canEdit ? 'Actions' : 'Status'}</th>
            </tr></thead>
            <tbody>${slice.map(rowHtml).join('')}</tbody>
          </table>
        </div>`;

      bind();
    }

    function bind() {
      mount.querySelectorAll('.vrp-sortable').forEach((th) => {
        th.tabIndex = 0;
        th.addEventListener('click', () => {
          const key = th.dataset.sort;
          state.sort = { key, dir: state.sort.key === key && state.sort.dir === 'asc' ? 'desc' : 'asc' };
          render();
        });
        th.addEventListener('keydown', (e) => { if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); th.click(); } });
      });

      const autosaveToggle = mount.querySelector('#vrpAutosave');
      if (autosaveToggle) autosaveToggle.addEventListener('change', () => {
        state.autosave = autosaveToggle.checked;
        localStorage.setItem(`vrp:autosave:${o.userId}`, autosaveToggle ? (autosaveToggle.checked ? '1' : '0') : '1');
        if (!state.autosave) debouncer.cancelAll();
        notify(`Autosave ${state.autosave ? 'enabled' : 'disabled'}${state.autosave ? '' : ' — use the row menu or Enter to save'}.`, 'info');
      });

      const csv = mount.querySelector('#vrpCsv');
      if (csv) csv.addEventListener('click', exportCsv);
      const print = mount.querySelector('#vrpPrint');
      if (print) print.addEventListener('click', () => window.print());
      mount.querySelectorAll('[data-page]').forEach((b) => b.addEventListener('click', () => { state.page = Number(b.dataset.page); render(); }));

      mount.querySelectorAll('[data-kebab]').forEach(bindMenu);
      mount.querySelectorAll('.vrp-input').forEach(bindInput);
      mount.querySelectorAll('[data-retry]').forEach((b) => b.addEventListener('click', () => saveRow(b.dataset.retry, { manual: true })));
      mount.querySelectorAll('.vr-learner-link').forEach((link) => {
        link.addEventListener('click', (e) => {
          e.preventDefault();
          o.onLearnerClick?.(studentKeyByName(link.closest('tr')?.dataset.student || ''), link.dataset.learnerName, link.dataset.learnerAdm);
        });
      });
    }

    /** Row kebab menu: keyboard + ARIA + outside-click aware. */
    function bindMenu(button) {
      const key = button.dataset.kebab;
      const menu = mount.querySelector(`[data-menu="${CSS.escape(key)}"]`);
      if (!menu) return;
      const close = (focus) => { menu.style.display = 'none'; button.setAttribute('aria-expanded', 'false'); if (focus) button.focus(); };
      const open = () => {
        closeAllMenus();
        menu.style.display = 'block';
        menu.classList.add('show');
        button.setAttribute('aria-expanded', 'true');
        const first = menu.querySelector('[role="menuitem"]:not([disabled])');
        if (first) first.focus();
      };
      button.addEventListener('click', () => (button.getAttribute('aria-expanded') === 'true' ? close(true) : open()));
      button.addEventListener('keydown', (e) => { if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); open(); } });
      menu.addEventListener('keydown', (e) => {
        const items = [...menu.querySelectorAll('[role="menuitem"]:not([disabled])')];
        const index = items.indexOf(document.activeElement);
        if (e.key === 'Escape') { e.preventDefault(); close(true); }
        else if (e.key === 'ArrowDown') { e.preventDefault(); items[(index + 1) % items.length]?.focus(); }
        else if (e.key === 'ArrowUp') { e.preventDefault(); items[(index - 1 + items.length) % items.length]?.focus(); }
        else if (e.key === 'Tab') { close(false); }
      });
      menu.querySelectorAll('[data-action]').forEach((item) => item.addEventListener('click', () => {
        close(false);
        const student = pivot.students.find((s) => s.key === key);
        const action = item.dataset.action;
        if (!student) return;
        if (action === 'add') enterEdit(student, { focusEmpty: true });
        if (action === 'edit') enterEdit(student, {});
        if (action === 'save') { if (state.editKey === key || [...state.dirty.keys()].some((k) => k.startsWith(`${key}|`))) saveRow(key, { manual: true }); }
        if (action === 'cancel') cancelEdit();
        if (action === 'delete') deleteStudentResults(student);
      }));
      document.addEventListener('click', function outside(e) {
        if (menu.style.display === 'none') return;
        if (!menu.contains(e.target) && !button.contains(e.target)) close(false);
      });
    }
    function closeAllMenus() {
      mount.querySelectorAll('.vrp-menu').forEach((m) => { m.style.display = 'none'; });
      mount.querySelectorAll('[data-kebab]').forEach((b) => b.setAttribute('aria-expanded', 'false'));
    }

    /** Editable cell behaviour: validation, dirty marking, autosave, Enter/Escape. */
    function bindInput(input) {
      const studentKey = input.dataset.student;
      const colKey = input.dataset.col;
      const student = pivot.students.find((s) => s.key === studentKey);
      const col = pivot.columns.find((c) => c.key === colKey);
      if (!student || !col) return;
      const cell = student.cells[colKey];
      const original = cell && cell.marks_obtained !== null ? String(cell.marks_obtained) : '';

      input.addEventListener('input', () => {
        const raw = input.value.trim();
        const cellKey = `${studentKey}|${colKey}`;
        const outcome = P().validateScoreInput(raw, { max: col.max_marks, decimals: 2, required: false });
        if (raw === '' && !cell?.result_id) {
          state.dirty.delete(cellKey);
          state.errors.delete(cellKey);
        } else if (raw === '' && cell?.result_id) {
          state.dirty.delete(cellKey);
          state.errors.set(cellKey, 'Clearing a recorded mark is not supported in the table — set 0 or delete the row results.');
        } else if (raw !== original && outcome.ok) {
          state.dirty.set(cellKey, raw);
          state.errors.delete(cellKey);
        } else if (!outcome.ok) {
          state.dirty.set(cellKey, raw);
          state.errors.set(cellKey, outcome.errors[0]);
        } else {
          state.dirty.delete(cellKey);
          state.errors.delete(cellKey);
        }
        if (state.autosave && !state.errors.has(cellKey)) debouncer.schedule(studentKey);
        refreshCell(input, student, col, cellKey);
      });

      input.addEventListener('keydown', (e) => {
        if (e.key === 'Enter') { e.preventDefault(); saveRow(studentKey, { manual: true }); }
        if (e.key === 'Escape') { e.preventDefault(); cancelEdit(); }
      });
      input.addEventListener('blur', () => {
        const cellKey = `${studentKey}|${colKey}`;
        if (state.errors.has(cellKey)) refreshCell(input, student, col, cellKey);
      });
    }

    /** Re-render one cell + the row's save affordances without a full re-render. */
    function refreshCell(input, student, col, cellKey) {
      const td = input.closest('td');
      const error = state.errors.get(cellKey) || '';
      const dirty = state.dirty.has(cellKey);
      td.classList.toggle('vrp-dirty', dirty);
      td.classList.toggle('vrp-invalid', Boolean(error));
      let errEl = td.querySelector('.vrp-err');
      if (error) {
        input.setAttribute('aria-invalid', 'true');
        input.setAttribute('aria-describedby', `${input.id}-err`);
        if (!errEl) { errEl = document.createElement('div'); errEl.className = 'vrp-err'; errEl.role = 'alert'; errEl.id = `${input.id}-err`; td.appendChild(errEl); }
        errEl.textContent = error;
      } else {
        input.removeAttribute('aria-invalid');
        input.removeAttribute('aria-describedby');
        errEl?.remove();
      }
      const row = td.closest('tr');
      if (row) {
        const saveBtn = row.querySelector('[data-action="save"]');
        if (saveBtn) saveBtn.disabled = ![...state.dirty.keys()].some((k) => k.startsWith(`${student.key}|`));
      }
    }

    function enterEdit(student, { focusEmpty }) {
      if (state.editKey && state.editKey !== student.key) {
        if (state.autosave) debouncer.flush(state.editKey);
        else if ([...state.dirty.keys()].some((k) => k.startsWith(`${state.editKey}|`))) {
          if (!window.confirm('Discard unsaved changes in the row you are leaving?')) return;
        }
        discardRowEdits(state.editKey);
      }
      debouncer.cancel(student.key);
      state.save.set(student.key, { status: 'idle' });
      state.editKey = student.key;
      render();
      const row = mount.querySelector(`tr[data-student="${CSS.escape(student.key)}"]`);
      const inputs = row ? [...row.querySelectorAll('.vrp-input')] : [];
      const target = (focusEmpty ? inputs.find((i) => i.value === '') : inputs[0]) || inputs[0];
      if (target) target.focus();
    }

    function cancelEdit() {
      const key = state.editKey;
      if (!key) return;
      debouncer.cancel(key);
      discardRowEdits(key);
      state.editKey = null;
      render();
    }

    function discardRowEdits(key) {
      [...state.dirty.keys()].filter((k) => k.startsWith(`${key}|`)).forEach((k) => state.dirty.delete(k));
      [...state.errors.keys()].filter((k) => k.startsWith(`${key}|`)).forEach((k) => state.errors.delete(k));
    }

    function collectItems(student) {
      const items = [];
      for (const [cellKey, raw] of state.dirty) {
        if (!cellKey.startsWith(`${student.key}|`)) continue;
        const colKey = cellKey.split('|')[1];
        const col = pivot.columns.find((c) => c.key === colKey);
        const cell = student.cells[colKey];
        if (!col || state.errors.has(cellKey)) continue;
        // Columns declare which key identifies the save target: assessment
        // registers (subject overview) or individual papers (paper grid).
        const saveKey = col.save_key || 'assessment_id';
        const item = {
          student_academic_enrollment_id: student.enrollment_id,
          marks_obtained: raw,
          entry_status: 'present',
          expected_updated_at: cell?.updated_at || '',
          colKey,
        };
        item[saveKey] = Number(cell?.[saveKey] ?? col[saveKey] ?? 0);
        if (saveKey === 'assessment_id') item.result_id = cell?.result_id || 0;
        items.push(item);
      }
      return items;
    }

    /** Optimistic save: snapshot, apply, call the API, reconcile or rollback. */
    async function saveRow(studentKey, { autosave } = {}) {
      const student = pivot.students.find((s) => s.key === studentKey);
      if (!student) return;
      const items = collectItems(student);
      if (!items.length) return;
      if ([...state.errors.keys()].some((k) => k.startsWith(`${studentKey}|`))) {
        state.save.set(studentKey, { status: 'error', message: 'Fix the highlighted cells before saving.' });
        render();
        return;
      }
      debouncer.cancel(studentKey);
      const snapshot = [...state.dirty.keys()].filter((k) => k.startsWith(`${studentKey}|`)).map((k) => [k, state.dirty.get(k)]);
      state.save.set(studentKey, { status: 'saving' });
      render();
      try {
        const response = await o.saveRow(student, items);
        // The server echoes each item's payload index; reconciliation maps
        // back through the exact column each item was built from.
        (response?.results || []).forEach((outcome) => {
          const entry = items[Number(outcome.index)];
          if (!entry) return;
          const key = `${studentKey}|${entry.colKey}`;
          const cell = student.cells[entry.colKey];
          if (cell) {
            cell.result_id = Number(outcome.result_id || cell.result_id || 0);
            cell.marks_obtained = outcome.marks_obtained === null || outcome.marks_obtained === undefined ? cell.marks_obtained : Number(outcome.marks_obtained);
            cell.grade = outcome.grade ? String(outcome.grade).toUpperCase() : cell.grade;
            cell.entry_status = outcome.entry_status || cell.entry_status;
            cell.updated_at = outcome.updated_at || cell.updated_at;
            cell.deleted = false;
            if (cell.max_marks) cell.percentage = Math.round((cell.marks_obtained / cell.max_marks) * 10000) / 100;
          }
          state.dirty.delete(key);
          state.errors.delete(key);
        });
        state.save.set(studentKey, { status: 'saved', at: new Date().toLocaleTimeString('en-KE', { hour: '2-digit', minute: '2-digit', second: '2-digit' }) });
        notify(autosave ? 'Row autosaved.' : 'Row saved.', 'success');
        if (state.editKey === studentKey) state.editKey = null;
        render();
      } catch (error) {
        snapshot.forEach(([k, v]) => state.dirty.set(k, v));
        const conflict = Number(error?.status ?? error?.response?.status ?? 0) === 409;
        state.save.set(studentKey, { status: 'error', message: conflict ? 'Another teacher updated this row — reload to see their marks.' : (error.message || 'Save failed.') });
        notify(conflict ? 'Conflicting edit detected. Your changes were rolled back — retry to take the latest marks, or reload the row.' : (error.message || 'Unable to save this row.'), 'error');
        if (conflict) discardRowEdits(studentKey);
        render();
      }
    }

    async function deleteStudentResults(student) {
      const confirmed = await (window.confirmAction?.('Delete learner results', `Delete every recorded subject result for ${student.learner_name}? This is journalled and can be restored by an administrator.`) ?? window.confirm(`Delete every recorded subject result for ${student.learner_name}?`));
      if (!confirmed) return;
      const results = Object.entries(student.cells).filter(([, c]) => c.result_id && !c.deleted);
      try {
        for (const [, cell] of results) await o.deleteResult(cell.result_id);
        notify(`Deleted ${results.length} result(s) for ${student.learner_name}.`, 'success');
        o.onChanged?.();
      } catch (error) { notify(error.message || 'Unable to delete these results.', 'error'); }
    }

    function exportCsv() {
      const header = ['Learner', 'Admission no.', 'Class', 'Stream', ...pivot.columns.map((c) => `${c.subject}${pivot.multiPeriod ? ` (${c.period_title})` : ''} /${c.max_marks}`)];
      const lines = [header.join(',')];
      sortedStudents().forEach((s) => {
        lines.push([
          `"${s.learner_name.replace(/"/g, '""')}"`,
          `"${s.admission_no.replace(/"/g, '""')}"`,
          `"${s.class_name.replace(/"/g, '""')}"`,
          `"${s.stream_name.replace(/"/g, '""')}"`,
          ...pivot.columns.map((c) => { const cell = s.cells[c.key]; return cell && cell.marks_obtained !== null && !cell.deleted ? cell.marks_obtained : ''; }),
        ].join(','));
      });
      const csv = lines.join('\r\n');
      const filename = `summative-results-${new Date().toISOString().slice(0, 10)}.csv`;
      if (window.KingswayFileLifecycle?.exportText) window.KingswayFileLifecycle.exportText(csv, filename, 'text/csv');
      else {
        const blob = new Blob([csv], { type: 'text/csv' });
        const link = document.createElement('a');
        link.href = URL.createObjectURL(blob); link.download = filename; link.click(); URL.revokeObjectURL(link.href);
      }
    }

    function hasUnsavedWork() {
      return state.dirty.size > 0 || [...state.save.values()].some((s) => s.status === 'saving');
    }
    const beforeUnload = (e) => { if (hasUnsavedWork()) { e.preventDefault(); e.returnValue = ''; } };
    window.addEventListener('beforeunload', beforeUnload);

    render();
    return {
      destroy() { debouncer.cancelAll(); window.removeEventListener('beforeunload', beforeUnload); },
      saveRow: (key) => saveRow(key, { manual: true }),
      isDirty: hasUnsavedWork,
      refresh(newRows) {
        const next = P().pivotStudentRows(newRows);
        pivot.columns = next.columns; pivot.students = next.students; pivot.multiPeriod = next.multiPeriod;
        state.dirty.clear(); state.errors.clear(); state.editKey = null;
        render();
      },
    };
  }

  window.SummativePivotTable = { create };
})();
