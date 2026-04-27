/**
 * Staff Schedule Controller
 * Shows the logged-in teacher's weekly timetable grid + class list + duties.
 * API: /schedules/teacher-schedule, /schedules/timetable-time-slots,
 *      /academic/terms-list, /staff/duty-schedule
 */
const staffScheduleController = {

  _slots:      [],   // time_slots from DB
  _schedule:   [],   // class_schedule entries for this teacher
  _termId:     null,
  _staffId:    null,
  DAYS: ['Monday','Tuesday','Wednesday','Thursday','Friday'],

  // ── Init ──────────────────────────────────────────────────────────────────
  init: async function () {
    if (!AuthContext.isAuthenticated()) {
      window.location.href = (window.APP_BASE || '') + '/index.php';
      return;
    }
    const user = AuthContext.getCurrentUser ? AuthContext.getCurrentUser() : AuthContext.user;
    this._staffId = user?.staff_id ?? user?.id ?? null;

    await Promise.all([this._loadTerms(), this._loadTimeSlots()]);
    await this.loadTimetable();
    this._loadDuties();
  },

  // ── Data loading ──────────────────────────────────────────────────────────
  _loadTerms: async function () {
    try {
      const r = await callAPI('/academic/terms-list', 'GET');
      const terms = Array.isArray(r?.data) ? r.data : (Array.isArray(r) ? r : []);
      const current = terms.find(t => (t.status || '') === 'current');
      if (current) this._termId = current.id;

      const sel = document.getElementById('ssTermFilter');
      if (sel) {
        sel.innerHTML = '<option value="">Current Term</option>' +
          terms.map(t => `<option value="${t.id}" ${t.id == this._termId ? 'selected' : ''}>${t.name} ${t.year || ''}</option>`).join('');
      }

      const badge = document.getElementById('ssTimetableTerm');
      if (badge && current) badge.textContent = current.name + ' ' + (current.year || '');
    } catch (e) { console.warn('Terms failed:', e); }
  },

  _loadTimeSlots: async function () {
    try {
      const r = await callAPI('/schedules/timetable-time-slots', 'GET');
      this._slots = Array.isArray(r?.data) ? r.data : (Array.isArray(r) ? r : []);
      // Sort by period number
      this._slots.sort((a, b) => Number(a.period_number) - Number(b.period_number));
    } catch (e) { console.warn('Time slots failed:', e); }
  },

  loadTimetable: async function () {
    const termId  = document.getElementById('ssTermFilter')?.value || this._termId || '';
    const loading = document.getElementById('ssTimetableLoading');
    const content = document.getElementById('ssTimetableContent');
    const empty   = document.getElementById('ssTimetableEmpty');

    if (loading) loading.style.display = '';
    if (content) content.style.display = 'none';
    if (empty)   empty.style.display   = 'none';

    try {
      const params = new URLSearchParams();
      if (this._staffId) params.set('teacher_id', this._staffId);
      if (termId)        params.set('term_id', termId);

      const r = await callAPI('/schedules/timetable-get?' + params.toString(), 'GET');
      this._schedule = Array.isArray(r?.data) ? r.data : (Array.isArray(r) ? r : []);

      if (loading) loading.style.display = 'none';

      if (!this._schedule.length) {
        if (empty) empty.style.display = '';
        this._renderClassesList([]);
        this._setStats([]);
        return;
      }

      if (content) content.style.display = '';
      this._renderGrid();
      this._renderClassesList(this._schedule);
      this._setStats(this._schedule);
    } catch (e) {
      if (loading) loading.style.display = 'none';
      if (empty) empty.style.display = '';
      console.warn('Schedule load failed:', e);
    }
  },

  _setStats: function (schedule) {
    const totalPeriods  = schedule.filter(s => s.subject_id || s.subject_name).length;
    const classes       = [...new Set(schedule.map(s => s.class_id).filter(Boolean))].length;
    const subjects      = [...new Set(schedule.map(s => s.subject_id || s.subject_name).filter(Boolean))].length;
    const totalLessonSlots = this._slots.filter(s => s.slot_type === 'lesson').length * 5;
    const free = Math.max(0, totalLessonSlots - totalPeriods);

    this._set('ssTotalPeriods',   totalPeriods);
    this._set('ssClassesCount',   classes);
    this._set('ssSubjectsCount',  subjects);
    this._set('ssFreePeriodsCount', free);
  },

  // ── Timetable grid ────────────────────────────────────────────────────────
  _renderGrid: function () {
    const tbody = document.getElementById('ssTimetableBody');
    if (!tbody || !this._slots.length) return;

    // Build lookup: [day][period_number] → schedule entry
    const lookup = {};
    this._schedule.forEach(s => {
      const key = `${s.day_of_week}_${s.period_number}`;
      lookup[key] = s;
    });

    tbody.innerHTML = this._slots.map(slot => {
      const isLesson = slot.slot_type === 'lesson';
      const isBreak  = ['break','lunch','assembly'].includes(slot.slot_type);
      const isGames  = slot.slot_type === 'games';

      const timeStr = this._formatTime(slot.start_time) + '–' + this._formatTime(slot.end_time);

      const periodCell = `<td class="small text-nowrap">
        <div class="fw-semibold">${this._esc(slot.label || 'P' + slot.period_number)}</div>
        <div class="text-muted" style="font-size:10px;">${timeStr}</div>
      </td>`;

      if (isBreak || isGames) {
        const bgCls = isGames ? 'table-success' : 'table-warning';
        return `<tr class="${bgCls}">
          ${periodCell}
          <td colspan="5" class="text-center small fw-semibold">${this._esc(slot.label || slot.slot_type)}</td>
        </tr>`;
      }

      const dayCells = this.DAYS.map(day => {
        const entry = lookup[`${day}_${slot.period_number}`];
        if (!entry) return `<td><div class="ss-free-cell">Free</div></td>`;
        return `<td>
          <div class="ss-lesson-cell">
            <div class="fw-semibold text-primary">${this._esc(entry.subject_name || entry.subject || '—')}</div>
            <div class="text-dark">${this._esc(entry.class_name || entry.class || '—')}</div>
            <div class="text-muted" style="font-size:10px;">${this._esc(entry.room_name || entry.room || '')}</div>
          </div>
        </td>`;
      }).join('');

      return `<tr>${periodCell}${dayCells}</tr>`;
    }).join('');
  },

  // ── Classes list ──────────────────────────────────────────────────────────
  _renderClassesList: function (schedule) {
    const tbody = document.getElementById('ssClassesBody');
    if (!tbody) return;

    if (!schedule.length) {
      tbody.innerHTML = '<tr><td colspan="4" class="text-center py-4 text-muted">No classes assigned yet.</td></tr>';
      return;
    }

    // Group by class+subject combination
    const groups = {};
    schedule.forEach(s => {
      if (!s.subject_id && !s.subject_name) return;
      const key = `${s.class_id}_${s.subject_id || s.subject_name}`;
      if (!groups[key]) groups[key] = {
        class_name:    s.class_name || s.class || '—',
        subject_name:  s.subject_name || s.subject || '—',
        periods:       0,
        students:      s.student_count || '—',
      };
      groups[key].periods++;
    });

    const rows = Object.values(groups);
    if (!rows.length) {
      tbody.innerHTML = '<tr><td colspan="4" class="text-center py-4 text-muted">No classes found.</td></tr>';
      return;
    }

    tbody.innerHTML = rows.map(r => `<tr>
      <td class="fw-semibold">${this._esc(r.class_name)}</td>
      <td>${this._esc(r.subject_name)}</td>
      <td class="text-center fw-bold text-primary">${r.periods}</td>
      <td class="text-center text-muted">${this._esc(r.students)}</td>
    </tr>`).join('');
  },

  // ── Duties ────────────────────────────────────────────────────────────────
  _loadDuties: async function () {
    const tbody = document.getElementById('ssDutiesBody');
    if (!tbody) return;
    try {
      const params = new URLSearchParams();
      if (this._staffId) params.set('staff_id', this._staffId);
      const r = await callAPI('/schedules/staff-duty-schedule?' + params.toString(), 'GET');
      const duties = Array.isArray(r?.data) ? r.data : (Array.isArray(r) ? r : []);

      if (!duties.length) {
        tbody.innerHTML = '<tr><td colspan="4" class="text-center py-4 text-muted">No upcoming duties assigned.</td></tr>';
        return;
      }

      const typeCls = { exam: 'danger', duty: 'warning', supervision: 'info', meeting: 'primary' };
      tbody.innerHTML = duties.map(d => `<tr>
        <td class="small">${this._esc(d.date || d.duty_date || '—')}</td>
        <td><span class="badge bg-${typeCls[(d.type||'duty').toLowerCase()]||'secondary'}">${this._esc(d.type||'Duty')}</span></td>
        <td class="small">${this._esc(d.start_time || '—')}${d.end_time ? '–' + this._esc(d.end_time) : ''}</td>
        <td class="small">${this._esc(d.location || d.room || '—')}</td>
      </tr>`).join('');
    } catch (e) {
      tbody.innerHTML = '<tr><td colspan="4" class="text-center py-4 text-muted">No duties data available.</td></tr>';
    }
  },

  // ── Utilities ─────────────────────────────────────────────────────────────
  _formatTime: function (t) {
    if (!t) return '—';
    const [h, m] = t.split(':').map(Number);
    const ampm = h >= 12 ? 'pm' : 'am';
    const h12  = h % 12 || 12;
    return `${h12}:${String(m).padStart(2,'0')}${ampm}`;
  },

  _set: (id, v) => { const e = document.getElementById(id); if (e) e.textContent = v; },
  _esc: s => { const d = document.createElement('div'); d.textContent = String(s ?? ''); return d.innerHTML; },
};

document.addEventListener('DOMContentLoaded', () => staffScheduleController.init());
