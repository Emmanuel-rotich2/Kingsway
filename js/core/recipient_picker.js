/**
 * RecipientPicker — shared single/multi/cross-group recipient picker.
 *
 * Nothing has to be typed to find someone: focusing the field opens a browse
 * panel with a suggested list plus category tabs (All / Learners / Guardians /
 * Staff / Classes / Groups / Vendors) and narrowing filters (school level,
 * class, stream, learner type, staff role). Typing searches every category;
 * matches learner name/admission number/guardian details, staff, class, group
 * and vendor names (GET /communications/audience-search).
 *
 * Selections are emitted as `type:id` tokens understood by the PHP recipient
 * resolver (student|parent|staff|class|vendor|group).
 *
 * Usage:
 *   const picker = window.RecipientPicker.create(el, { mode: 'multi' });
 *   picker.setSelection([{ type: 'student', id: 901, label: 'Aaron Kipkirui' }]);
 *   picker.getTokens(); // ['student:901']
 */
(function () {
  if (typeof window === 'undefined') return;

  function escapeHtml(value) {
    const d = document.createElement('div');
    d.textContent = String(value == null ? '' : value);
    return d.innerHTML;
  }

  let _optionsPromise = null;
  function loadOptions() {
    if (_optionsPromise) return _optionsPromise;
    _optionsPromise = (async function () {
      try {
        if (window.API && window.API.communications && window.API.communications.getAudienceOptions) {
          const res = await window.API.communications.getAudienceOptions();
          return (res && (res.data || res)) || {};
        }
      } catch (err) {
        // Browse still works without filter lists; degrade gracefully.
      }
      return {};
    })();
    return _optionsPromise;
  }

  const TAB_DEFS = [
    { type: '', label: 'All' },
    { type: 'student', label: 'Learners', need: ['selected_students'] },
    { type: 'parent', label: 'Guardians', need: ['selected_parents'] },
    { type: 'staff', label: 'Staff', need: ['selected_staff', 'all_staff'] },
    { type: 'class', label: 'Classes', need: ['selected_class'] },
    { type: 'group', label: 'Groups', need: ['contact_group'] },
    { type: 'vendor', label: 'Vendors', need: ['selected_vendors', 'all_vendors'] },
  ];

  function RecipientPicker(container, options) {
    this.container = container;
    this.options = options || {};
    this.mode = this.options.mode === 'single' ? 'single' : 'multi';
    this.selection = [];
    this.activeType = '';
    this.filters = {};
    this.optionsData = {};
    this._searchSeq = 0;
    this._timer = null;
    this._render();
    this._bind();
    const self = this;
    loadOptions().then(function (data) {
      self.optionsData = data || {};
      self._renderPanel();
    });
  }

  RecipientPicker.prototype._render = function () {
    this.root = document.createElement('div');
    this.root.className = 'rp-wrapper position-relative';
    this.root.innerHTML =
      '<div class="rp-chips d-flex flex-wrap gap-1 mb-2"></div>' +
      '<input type="text" class="form-control form-control-sm rp-search" autocomplete="off" placeholder="' +
      escapeHtml(this.options.placeholder || 'Search learner, guardian, staff, class, group or vendor…') + '">' +
      '<div class="rp-panel position-absolute w-100 shadow-sm d-none border rounded bg-white" style="z-index:1080;top:100%;margin-top:2px;">' +
      '<div class="rp-tabs btn-group btn-group-sm w-100 flex-wrap p-1 border-bottom"></div>' +
      '<div class="rp-filters d-flex flex-wrap gap-1 p-2 border-bottom d-none"></div>' +
      '<div class="rp-results list-group list-group-flush" style="max-height:260px;overflow-y:auto;"></div>' +
      '</div>';
    this.container.appendChild(this.root);
    this.chipsEl = this.root.querySelector('.rp-chips');
    this.searchEl = this.root.querySelector('.rp-search');
    this.panelEl = this.root.querySelector('.rp-panel');
    this.tabsEl = this.root.querySelector('.rp-tabs');
    this.filtersEl = this.root.querySelector('.rp-filters');
    this.resultsEl = this.root.querySelector('.rp-results');
    this._renderChips();
    this._renderPanel();
  };

  RecipientPicker.prototype._allowedTypes = function () {
    const allowed = this.optionsData && this.optionsData.allowed_audiences;
    let tabs = TAB_DEFS.slice();
    if (Array.isArray(allowed) && allowed.length) {
      tabs = tabs.filter(function (tab) {
        if (!tab.need) return true;
        return tab.need.some(function (key) { return allowed.indexOf(key) !== -1; });
      });
    }
    const wanted = Array.isArray(this.options.types) && this.options.types.length ? this.options.types : null;
    if (wanted) {
      tabs = tabs.filter(function (tab) { return tab.type === '' || wanted.indexOf(tab.type) !== -1; });
    }
    return tabs;
  };

  RecipientPicker.prototype._renderPanel = function () {
    if (!this.panelEl) return;
    const self = this;
    const tabs = this._allowedTypes();
    const tabsEl = this.root.querySelector('.rp-tabs');
    tabsEl.innerHTML = tabs.map(function (tab) {
      const active = tab.type === self.activeType ? ' active' : '';
      return '<button type="button" class="btn btn-sm btn-outline-secondary rp-tab' + active + '" data-type="' + escapeHtml(tab.type) + '">' + escapeHtml(tab.label) + '</button>';
    }).join('');
    tabsEl.querySelectorAll('.rp-tab').forEach(function (button) {
      button.addEventListener('click', function () {
        self.activeType = button.getAttribute('data-type') || '';
        tabsEl.querySelectorAll('.rp-tab').forEach(function (b) { b.classList.remove('active'); });
        button.classList.add('active');
        self._renderFilters();
        self._browse();
      });
    });
    this._renderFilters();
  };

  RecipientPicker.prototype._filterDefs = function () {
    const o = this.optionsData || {};
    const asOptions = function (list, labelKey, valueKey) {
      return (Array.isArray(list) ? list : []).map(function (item) {
        return { value: item[valueKey || 'id'], label: item[labelKey || 'name'] };
      });
    };
    const defs = [];
    if (this.activeType === 'student' || this.activeType === 'parent') {
      defs.push({ key: 'school_level_id', label: 'School level', options: asOptions(o.school_levels) });
      defs.push({ key: 'class_id', label: 'Class', options: asOptions(o.classes) });
      defs.push({ key: 'stream_id', label: 'Stream', options: asOptions(o.streams) });
      defs.push({ key: 'student_type_id', label: 'Student type', options: asOptions(o.student_types) });
    } else if (this.activeType === 'staff') {
      defs.push({ key: 'role_id', label: 'Role', options: asOptions(o.roles) });
    } else if (this.activeType === 'class') {
      defs.push({ key: 'school_level_id', label: 'School level', options: asOptions(o.school_levels) });
    }
    return defs;
  };

  RecipientPicker.prototype._renderFilters = function () {
    const self = this;
    const filtersEl = this.root.querySelector('.rp-filters');
    if (!filtersEl) return;
    const defs = this._filterDefs();
    if (!defs.length) {
      filtersEl.innerHTML = '';
      filtersEl.classList.add('d-none');
      return;
    }
    filtersEl.innerHTML = defs.map(function (def) {
      const current = self.filters[def.key] || '';
      const opts = ['<option value="">All ' + escapeHtml(def.label.toLowerCase()) + '</option>'].concat(
        def.options.map(function (opt) {
          const selected = String(opt.value) === String(current) ? ' selected' : '';
          return '<option value="' + escapeHtml(opt.value) + '"' + selected + '>' + escapeHtml(opt.label) + '</option>';
        })
      );
      return '<select class="form-select form-select-sm rp-filter" style="max-width:170px" data-filter="' + escapeHtml(def.key) + '">' + opts.join('') + '</select>';
    }).join('');
    filtersEl.classList.remove('d-none');
    filtersEl.querySelectorAll('.rp-filter').forEach(function (select) {
      select.addEventListener('change', function () {
        const key = select.getAttribute('data-filter');
        if (select.value) self.filters[key] = select.value;
        else delete self.filters[key];
        self._browse();
      });
    });
  };

  RecipientPicker.prototype._bind = function () {
    const self = this;

    this.searchEl.addEventListener('focus', function () { self._open(); });

    this.searchEl.addEventListener('input', function () {
      clearTimeout(self._timer);
      self._timer = setTimeout(function () { self._browse(); }, 220);
    });

    this.searchEl.addEventListener('keydown', function (event) {
      if (event.key === 'Escape') {
        self._hideResults();
      } else if (event.key === 'Enter') {
        const first = self.resultsEl.querySelector('[data-token]:not(.d-none)');
        if (first) {
          event.preventDefault();
          first.click();
        }
      }
    });

    document.addEventListener('click', function (event) {
      if (!self.root.contains(event.target)) self._hideResults();
    });

    this.chipsEl.addEventListener('click', function (event) {
      const remove = event.target.closest('[data-remove-token]');
      if (remove) self.removeToken(remove.getAttribute('data-remove-token'));
    });
  };

  RecipientPicker.prototype._open = function () {
    this.resultsEl.classList.remove('d-none');
    this._browse();
  };

  RecipientPicker.prototype._browse = async function () {
    const self = this;
    const seq = ++this._searchSeq;
    const query = this.searchEl.value.trim();
    this.root.querySelector('.rp-panel').classList.remove('d-none');
    this.resultsEl.innerHTML = '<div class="list-group-item text-muted small">Loading recipients…</div>';
    try {
      const filters = Object.assign({ type: this.activeType }, this.filters);
      const res = window.API && window.API.communications && window.API.communications.searchAudience
        ? await window.API.communications.searchAudience(query, 30, filters)
        : [];
      if (seq !== self._searchSeq) return;
      self._renderResults(Array.isArray(res) ? res : (res && res.data) || []);
    } catch (err) {
      if (seq !== self._searchSeq) return;
      this.resultsEl.innerHTML = '<div class="list-group-item text-danger small">Search failed.</div>';
    }
  };

  RecipientPicker.prototype._renderResults = function (rows) {
    const self = this;
    const selected = new Set(this.getTokens());
    const seen = new Set();
    const usable = (rows || []).filter(function (r) {
      if (!r || !r.type || !r.id) return false;
      const token = r.type + ':' + r.id;
      if (seen.has(token)) return false;
      seen.add(token);
      return true;
    });

    if (!usable.length) {
      this.resultsEl.innerHTML = '<div class="list-group-item text-muted small">No matches.</div>';
      return;
    }

    this.resultsEl.innerHTML = usable.map(function (r) {
      const token = r.type + ':' + r.id;
      const hidden = selected.has(token) ? ' d-none' : '';
      const meta = [r.sublabel, r.contact_name, r.phone, r.email].filter(Boolean).join(' · ');
      return '<button type="button" class="list-group-item list-group-item-action' + hidden + '" data-token="' + escapeHtml(token) + '">' +
        '<div class="d-flex justify-content-between align-items-start">' +
        '<div><span class="badge bg-light text-dark border me-2">' + escapeHtml(r.type) + '</span>' +
        '<span class="fw-semibold">' + escapeHtml(r.label || ('#' + r.id)) + '</span>' +
        (meta ? '<div class="text-muted small">' + escapeHtml(meta) + '</div>' : '') +
        '</div><i class="bi bi-plus-circle"></i></div></button>';
    }).join('');

    this.resultsEl.querySelectorAll('[data-token]').forEach(function (button) {
      button.addEventListener('click', function () {
        const token = button.getAttribute('data-token');
        const row = usable.find(function (r) { return (r.type + ':' + r.id) === token; });
        if (row) self.addSelection(row);
      });
    });
  };

  RecipientPicker.prototype.addSelection = function (row) {
    const normalized = this._normalize(row);
    if (!normalized) return;
    if (this.mode === 'single') {
      this.selection = [normalized];
    } else if (!this.getTokens().includes(normalized.type + ':' + normalized.id)) {
      this.selection.push(normalized);
    }
    this.searchEl.value = '';
    this._hideResults();
    this._renderChips();
    this._emit();
  };

  RecipientPicker.prototype.removeToken = function (token) {
    this.selection = this.selection.filter(function (r) { return (r.type + ':' + r.id) !== token; });
    this._renderChips();
    this._emit();
  };

  RecipientPicker.prototype.setSelection = function (rows) {
    const self = this;
    this.selection = (Array.isArray(rows) ? rows : [])
      .map(function (r) { return self._normalize(r); })
      .filter(Boolean);
    this._renderChips();
    this._emit();
  };

  RecipientPicker.prototype.getSelection = function () { return this.selection.slice(); };

  RecipientPicker.prototype.getTokens = function () {
    return this.selection.map(function (r) { return r.type + ':' + r.id; });
  };

  RecipientPicker.prototype.clear = function () {
    this.selection = [];
    this._renderChips();
    this._emit();
  };

  RecipientPicker.prototype._renderChips = function () {
    if (!this.selection.length) {
      this.chipsEl.innerHTML = '<span class="text-muted small">No recipients selected.</span>';
      return;
    }
    this.chipsEl.innerHTML = this.selection.map(function (r) {
      const token = r.type + ':' + r.id;
      return '<span class="badge rounded-pill bg-primary-subtle text-primary border">' +
        escapeHtml(r.label || token) +
        '<button type="button" class="btn-close ms-1" style="font-size:.55rem;" data-remove-token="' + escapeHtml(token) + '"></button></span>';
    }).join('');
  };

  RecipientPicker.prototype._normalize = function (row) {
    const type = String((row && row.type) || '').toLowerCase();
    const id = parseInt(row && row.id, 10);
    if (!type || !id) return null;
    return { type: type, id: id, label: (row && row.label) || (type + ' #' + id), phone: (row && row.phone) || null, email: (row && row.email) || null };
  };

  RecipientPicker.prototype._emit = function () {
    if (typeof this.options.onChange === 'function') this.options.onChange(this.getSelection());
  };

  RecipientPicker.prototype._hideResults = function () {
    this.root.querySelector('.rp-panel').classList.add('d-none');
  };

  const VARIABLES = [
    { token: '@first_name', label: 'First name' },
    { token: '@last_name', label: 'Last name' },
    { token: '@full_name', label: 'Full name' },
    { token: '@email', label: 'Email' },
    { token: '@phone', label: 'Phone' },
    { token: '@school_name', label: 'School name' },
    { token: '@today', label: "Today's date" },
    { token: '@year', label: 'Year' },
  ];

  function insertVariable(target, token) {
    const el = typeof target === 'string' ? document.getElementById(target) : target;
    if (!el) return;
    const start = el.selectionStart != null ? el.selectionStart : el.value.length;
    const end = el.selectionEnd != null ? el.selectionEnd : el.value.length;
    el.value = el.value.slice(0, start) + token + el.value.slice(end);
    el.dispatchEvent(new Event('input', { bubbles: true }));
    if (typeof el.focus === 'function') el.focus();
    if (el.setSelectionRange) el.setSelectionRange(start + token.length, start + token.length);
  }

  window.RecipientPicker = {
    create: function (container, options) {
      const el = typeof container === 'string' ? document.getElementById(container) : container;
      return el ? new RecipientPicker(el, options) : null;
    },
    variables: function () { return VARIABLES.slice(); },
    insertVariable: insertVariable,
    loadOptions: loadOptions,
  };
})();
