const ClassAttendanceHistoryController = {
  _years: [],
  _terms: [],
  _data: { payload: null, range: null }, // current register for export/print
  _state: {
    stream: '',
    rangeType: 'week',
    from: '',
    to: '',
    filterWeekdays: false,
  },

  async init() {
    await window.AuthContext?.ready?.();
    if (!window.AuthContext?.isAuthenticated()) return;

    const today = new Date();
    const monday = this.mondayOf(today);
    const weekStart = document.getElementById('historyWeekStart');
    weekStart.value = this.toInputDate(monday);
    document.getElementById('historyFrom').value = this.toInputDate(monday);
    document.getElementById('historyTo').value = this.toInputDate(this.addDays(monday, 4));
    const month = document.getElementById('historyMonth');
    month.value = today.toISOString().slice(0, 7);

    document.getElementById('historyStream').addEventListener('change', () => this.load());
    document.getElementById('historyRangeType').addEventListener('change', () => {
      this.toggleFilters();
      this.load();
    });
    ['historyFrom', 'historyTo', 'historyWeekStart', 'historyMonth', 'historyTerm', 'historyYear'].forEach(id => {
      document.getElementById(id).addEventListener('change', () => {
        if (id === 'historyYear') this.loadTerms().then(() => this.load());
        else this.load();
      });
    });
    document.getElementById('exportCsvBtn').addEventListener('click', () => {
      if (!window.AuthContext?.canExport?.('attendance')) {
        window.showNotification?.('You do not have permission to export this register', 'error');
        return;
      }
      this.exportCsv();
    });
    document.getElementById('printPdfBtn').addEventListener('click', () => {
      if (!window.AuthContext?.canPrint?.('attendance')) {
        window.showNotification?.('You do not have permission to print this register', 'error');
        return;
      }
      window.print();
    });

    this.toggleFilters();
    await this.classes();
    await this.loadYears();
  },

  toInputDate(date) {
    const y = date.getFullYear();
    const m = String(date.getMonth() + 1).padStart(2, '0');
    const d = String(date.getDate()).padStart(2, '0');
    return `${y}-${m}-${d}`;
  },

  parseDate(value) {
    if (!value) return null;
    const parsed = new Date(`${value}T00:00:00`);
    return Number.isNaN(parsed.getTime()) ? null : parsed;
  },

  addDays(date, days) {
    const copy = new Date(date);
    copy.setDate(copy.getDate() + days);
    return copy;
  },

  mondayOf(date) {
    const copy = new Date(date);
    const offset = (copy.getDay() + 6) % 7;
    copy.setDate(copy.getDate() - offset);
    return copy;
  },

  weekdayIndex(date) {
    return new Date(`${date}T00:00:00`).getDay();
  },

  toggleFilters() {
    const type = document.getElementById('historyRangeType').value;
    const mapping = { custom: 'filtersCustom', week: 'filtersWeek', month: 'filtersMonth', term: 'filtersTerm', year: 'filtersYear' };
    Object.keys(mapping).forEach(key => {
      document.getElementById(mapping[key]).classList.toggle('d-none', key !== type);
    });
    this._state.rangeType = type;
  },

  eventValue(id) {
    return document.getElementById(id).value;
  },

  resolveRange() {
    const type = this._state.rangeType;
    const weekInput = this.parseDate(this.eventValue('historyWeekStart'));
    const month = this.eventValue('historyMonth');

    if (type === 'week') {
      const monday = weekInput ? this.mondayOf(weekInput) : this.mondayOf(new Date());
      return { from: this.toInputDate(monday), to: this.toInputDate(this.addDays(monday, 4)), filterWeekdays: false };
    }

    if (type === 'month') {
      if (!month) return null;
      const [y, m] = month.split('-').map(Number);
      const lastDay = new Date(y, m, 0).getDate();
      const from = `${month}-01`;
      const to = `${month}-${String(lastDay).padStart(2, '0')}`;
      return { from, to, filterWeekdays: false };
    }

    if (type === 'term') {
      const term = this._terms.find(t => String(t.id) === this.eventValue('historyTerm'));
      if (!term) return null;
      return { from: term.opening_date, to: term.closing_date, filterWeekdays: true };
    }

    if (type === 'year') {
      const year = this._years.find(y => String(y.id) === this.eventValue('historyYear'));
      if (!year) return null;
      return { from: year.start_date, to: year.end_date, filterWeekdays: true };
    }

    const from = this.eventValue('historyFrom');
    const to = this.eventValue('historyTo');
    if (!from || !to) return null;
    return { from, to, filterWeekdays: false };
  },

  async classes() {
    try {
      const response = await window.API.apiCall('/attendance/classes', 'GET');
      const rows = this.unwrap(response);
      const select = document.getElementById('historyStream');
      rows.forEach(row => {
        const option = document.createElement('option');
        option.value = row.stream_id;
        option.textContent = `${row.display_name || row.name} (${row.student_count || 0})`;
        select.appendChild(option);
      });
      if (rows.length === 0) {
        this.renderMessageRow('No class streams are assigned to your account.');
        return;
      }
      const first = select.options[0];
      first.textContent = rows.length > 1 ? `All My Classes (${rows.length})` : 'All My Classes';
      select.value = '';
      await this.load();
    } catch (error) {
      this.message(error.message || 'Unable to load your class streams.', 'danger');
    }
  },

  async loadYears() {
    try {
      const response = await window.API.academic.getAllAcademicYears();
      const years = Array.isArray(response) ? response : (response?.data || response?.years || []);
      this._years = years;
      const select = document.getElementById('historyYear');
      select.replaceChildren();
      const ordered = [...years].sort((a, b) => String(b.start_date || '').localeCompare(String(a.start_date || '')));
      let current = null;
      ordered.forEach(year => {
        const option = document.createElement('option');
        option.value = year.id;
        option.textContent = year.year_code || year.year_name || String(year.start_date || '').slice(0, 4);
        select.appendChild(option);
        if (Number(year.is_current) === 1) current = year.id;
      });
      if (!current && ordered.length) current = ordered[0].id;
      select.value = current;
      await this.loadTerms();
    } catch (error) {
      this.message(error.message || 'Unable to load academic years.', 'danger');
    }
  },

  async loadTerms() {
    const yearId = this.eventValue('historyYear');
    const select = document.getElementById('historyTerm');
    select.replaceChildren(new Option('Select term', ''));
    if (!yearId) return;
    try {
      const response = await window.API.students.getAcademicYearTerms(yearId);
      const terms = Array.isArray(response) ? response : (response?.data || response?.terms || []);
      this._terms = terms;
      let current = null;
      terms.forEach(term => {
        const option = document.createElement('option');
        option.value = term.id;
        option.textContent = term.name || term.code || `Term ${term.term_number || ''}`.trim();
        select.appendChild(option);
        if (String(term.status) === 'current') current = term.id;
      });
      if (terms.length === 1) current = terms[0].id;
      if (current) select.value = current;
    } catch (error) {
      this._terms = [];
      this.message(error.message || 'Unable to load terms for this academic year.', 'danger');
    }
  },

  unwrap(response) {
    const value = response?.data?.data || response?.data || response || [];
    if (Array.isArray(value)) return value;
    return value.classes || value.rows || [];
  },

  async load() {
    this._state.stream = this.eventValue('historyStream');
    const range = this.resolveRange();
    if (!range) {
      this.renderMessageRow('Select a valid date range.');
      return;
    }
    this._state.from = range.from;
    this._state.to = range.to;
    this._state.filterWeekdays = range.filterWeekdays;

    try {
      const response = await window.API.attendance.getRegisterRange({
        stream_id: this._state.stream,
        from: this._state.from,
        to: this._state.to,
      });
      const payload = response?.data?.data || response?.data || response || {};
      this._data = { payload, range };
      this.render(payload, range);
      this.hideMessage();
    } catch (error) {
      this.message(error.message || 'Unable to load attendance history.', 'danger');
    }
  },

  displayedDates(payload, filterWeekdays) {
    return (payload.dates || [])
      .map(day => day.date)
      .filter(date => !filterWeekdays || (this.weekdayIndex(date) >= 1 && this.weekdayIndex(date) <= 5));
  },

  render(payload, range) {
    const body = document.getElementById('historyBody');
    const head = document.getElementById('historyHead');
    const foot = document.getElementById('historyFoot');
    const rows = payload.rows || [];
    const dates = this.displayedDates(payload, range.filterWeekdays);
    const dateLabels = {};
      (payload.dates || []).forEach(day => { dateLabels[day.date] = day.label; });
    const multiClass = (payload.classes || []).length > 1;

    document.getElementById('registerTitle').textContent =
      `Attendance Register — ${payload.class?.display_name || ''}`;

    const headerCells = dates.map(date => `<th>${this.esc(dateLabels[date] || date)}</th>`).join('');
    const classHead = multiClass ? '<th>Class</th>' : '';
    head.innerHTML = `<tr><th class="sticky-id">Admission No.</th><th class="sticky-name">Learner</th>${classHead}${headerCells}<th class="text-center" style="min-width:150px">Period Summary</th></tr>`;

    const badge = {
      present: 'text-bg-success',
      absent: 'text-bg-danger',
      late: 'text-bg-warning text-dark',
      permission: 'text-bg-info text-dark',
      not_marked: 'text-bg-light border',
    };

    if (!dates.length || !rows.length) {
      body.innerHTML = `<tr><td colspan="3" class="text-center text-muted py-4">${rows.length ? 'No school days in this range.' : 'No learners were enrolled in your class streams during this period.'}</td></tr>`;
      foot.innerHTML = '';
      this.showHint(payload, range, dates.length, rows.length);
      return;
    }

    body.innerHTML = rows.map(learner => {
      const cells = dates.map(date => {
        const status = learner.days?.[date] || 'not_marked';
        const label = status.replace(/_/g, ' ');
        const symbol = status === 'not_marked' ? '&ndash;' : (status === 'permission' ? 'Pm' : status.charAt(0).toUpperCase());
        return `<td class="date-cell" title="${this.esc(date)} &middot; ${this.esc(label)}"><span class="status-dot ${badge[status] || badge.not_marked}">${symbol}</span></td>`;
      }).join('');
      const classCell = multiClass ? `<td class="small text-muted">${this.esc(learner.display_name || learner.class_name || '')}</td>` : '';
      const summary = this.periodSummary(learner.days || {}, dates);
      return `<tr><td class="sticky-id">${this.esc(learner.admission_no)}</td><td class="sticky-name">${this.esc(learner.learner_name || [learner.first_name, learner.last_name].filter(Boolean).join(' '))}</td>${classCell}${cells}<td class="small">${summary}</td></tr>`;
    }).join('');

    const totals = [];
    dates.forEach(date => {
      let present = 0;
      let absent = 0;
      rows.forEach(learner => {
        const status = learner.days?.[date];
        if (status === 'present') present += 1;
        if (status === 'absent') absent += 1;
      });
      totals.push(`<td class="date-cell small text-muted">${present ? `<span class="text-success">P ${present}</span>` : ''}${present && absent ? ' &middot; ' : ''}${absent ? `<span class="text-danger">A ${absent}</span>` : ''}</td>`);
    });
    foot.innerHTML = `<tr><td colspan="${2 + (multiClass ? 1 : 0)}" class="small text-muted fw-semibold">Daily totals</td>${totals.join('')}<td></td></tr>`;

    this.showHint(payload, range, dates.length, rows.length);
  },

  periodSummary(days, dates) {
    const counts = { present: 0, absent: 0, late: 0, permission: 0 };
    dates.forEach(date => {
      const status = days[date];
      if (counts[status] !== undefined) counts[status] += 1;
    });
    const parts = [];
    if (counts.present) parts.push(`<span class="text-success">P ${counts.present}</span>`);
    if (counts.absent) parts.push(`<span class="text-danger">A ${counts.absent}</span>`);
    if (counts.late) parts.push(`<span class="text-warning">L ${counts.late}</span>`);
    if (counts.permission) parts.push(`<span class="text-info">Pm ${counts.permission}</span>`);
    return parts.join(' &middot; ') || '<span class="text-muted">&ndash;</span>';
  },

  showHint(payload, range, dayCount, learnerCount) {
    const hint = document.getElementById('historyRangeHint');
    const className = payload.class?.display_name || '';
    hint.innerHTML = `<i class="bi bi-arrow-down-circle me-1"></i>Showing <strong>${this.esc(range.from)}</strong> to <strong>${this.esc(range.to)}</strong>${range.filterWeekdays ? ' (weekdays only)' : ''} &middot; ${dayCount} day${dayCount === 1 ? '' : 's'}` +
      (learnerCount ? ` &middot; ${learnerCount} learner${learnerCount === 1 ? '' : 's'}` : '') +
      (className ? ` &middot; <strong>${this.esc(className)}</strong>` : '') +
      '<span class="ms-2 text-muted"><i class="bi bi-magic me-1"></i>Updates automatically.</span>';

    const meta = document.getElementById('printRegisterMeta');
    const generated = new Date().toLocaleString('en-GB', { timeZone: 'Africa/Nairobi' });
    meta.innerHTML = `${this.esc(className)} &middot; ${this.esc(range.from)} to ${this.esc(range.to)}${range.filterWeekdays ? ' (Mon&ndash;Fri)' : ''} &middot; Generated ${this.esc(generated)}`;
  },

  exportCsv() {
    const { payload, range } = this._data;
    if (!payload || !payload.rows || !payload.rows.length) {
      window.showNotification?.('No data to export yet.', 'warning');
      return;
    }
    const dates = this.displayedDates(payload, range.filterWeekdays);
    const headers = ['Admission No.', 'Learner', 'Class', 'Student Type', ...dates, 'Present', 'Absent', 'Late', 'Permission'];
    const statusLetter = { present: 'P', absent: 'A', late: 'L', permission: 'Pm', not_marked: '' };
    const rows = payload.rows.map(learner => {
      const counts = { present: 0, absent: 0, late: 0, permission: 0 };
      const cells = dates.map(date => {
        const status = learner.days?.[date] || 'not_marked';
        if (status !== 'not_marked' && counts[status] !== undefined) counts[status] += 1;
        return statusLetter[status] || '';
      });
      return [learner.admission_no, learner.learner_name, learner.display_name || learner.class_name || '', learner.student_type || '', ...cells, counts.present, counts.absent, counts.late, counts.permission];
    });
    const csv = [headers, ...rows].map(r => r.map(v => `"${String(v == null ? '' : v).replace(/"/g, '""')}"`).join(',')).join('\n');
    const scope = payload.class?.display_name ? payload.class.display_name.replace(/[^A-Za-z0-9]+/g, '_') : 'my_classes';
    const filename = `class_attendance_register_${scope}_${range.from}_to_${range.to}.csv`;
    KingswayFileLifecycle.exportText(csv, filename, 'text/csv');
  },

  renderMessageRow(text) {
    document.getElementById('historyBody').innerHTML = `<tr><td colspan="3" class="text-center text-muted py-4">${this.esc(text)}</td></tr>`;
    document.getElementById('historyHead').innerHTML = '<tr><th class="sticky-id">Admission No.</th><th class="sticky-name">Learner</th></tr>';
    document.getElementById('historyFoot').innerHTML = '';
    document.getElementById('historyRangeHint').textContent = '';
  },

  hideMessage() {
    const element = document.getElementById('historyMessage');
    element.className = 'alert d-none';
    element.textContent = '';
  },

  message(text, type) {
    const element = document.getElementById('historyMessage');
    element.textContent = text;
    element.className = `alert alert-${type}`;
  },

  esc(value) {
    const element = document.createElement('div');
    element.textContent = value == null ? '' : String(value);
    return element.innerHTML;
  },
};

document.addEventListener('DOMContentLoaded', () => ClassAttendanceHistoryController.init());

window.ClassAttendanceHistoryController = ClassAttendanceHistoryController;