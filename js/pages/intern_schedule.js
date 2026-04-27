/**
 * Intern Schedule Controller
 * Weekly teaching timetable for intern placement.
 * API: GET /schedules/timetable?user=self&week=YYYY-WW
 */

const internScheduleController = (() => {
  const DAYS = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'];
  let currentWeek = ''; // YYYY-WW format

  function getISOWeek(date) {
    const d = new Date(date);
    d.setHours(0, 0, 0, 0);
    d.setDate(d.getDate() + 3 - ((d.getDay() + 6) % 7));
    const week1 = new Date(d.getFullYear(), 0, 4);
    const wn = 1 + Math.round(((d - week1) / 86400000 - 3 + ((week1.getDay() + 6) % 7)) / 7);
    return `${d.getFullYear()}-W${String(wn).padStart(2, '0')}`;
  }

  function getWeekLabel(isoWeek) {
    const [year, wStr] = isoWeek.split('-W');
    const jan4 = new Date(parseInt(year), 0, 4);
    const wNum = parseInt(wStr);
    const monday = new Date(jan4);
    monday.setDate(jan4.getDate() - ((jan4.getDay() + 6) % 7) + (wNum - 1) * 7);
    const friday = new Date(monday);
    friday.setDate(monday.getDate() + 4);
    const opts = { day: 'numeric', month: 'short', year: 'numeric' };
    return `${monday.toLocaleDateString('en-GB', opts)} – ${friday.toLocaleDateString('en-GB', opts)}`;
  }

  function show(id) { const el = document.getElementById(id); if (el) el.style.display = ''; }
  function hide(id) { const el = document.getElementById(id); if (el) el.style.display = 'none'; }

  async function loadSchedule() {
    show('isLoading'); hide('isContent'); hide('isEmpty');
    const weekLabel = document.getElementById('isWeekLabel');
    if (weekLabel) weekLabel.textContent = getWeekLabel(currentWeek);

    try {
      const r = await callAPI(`/schedules/timetable?user=self&week=${currentWeek}`, 'GET');
      const data = r?.data || r || {};
      const periods = Array.isArray(data.periods) ? data.periods : [];
      const slots = Array.isArray(data.slots) ? data.slots : (Array.isArray(data) ? data : []);

      hide('isLoading');

      if (!slots.length && !periods.length) {
        show('isEmpty');
        setStats(0, 0, 0);
        return;
      }

      renderGrid(periods.length ? periods : buildPeriods(slots), slots);
      computeStats(slots);
      show('isContent');
    } catch (e) {
      hide('isLoading');
      show('isEmpty');
      console.error('intern_schedule load error', e);
    }
  }

  function buildPeriods(slots) {
    const map = {};
    slots.forEach(s => { if (s.period) map[s.period] = s.start_time || ''; });
    return Object.entries(map).map(([p, t]) => ({ name: p, time: t }));
  }

  function renderGrid(periods, slots) {
    const tbody = document.getElementById('isTableBody');
    if (!tbody) return;

    // Build lookup: period -> day -> slot
    const grid = {};
    slots.forEach(s => {
      const p = s.period || s.period_name || '';
      const d = (s.day || s.day_name || '').charAt(0).toUpperCase() + (s.day || s.day_name || '').slice(1).toLowerCase();
      if (!grid[p]) grid[p] = {};
      grid[p][d] = s;
    });

    const periodNames = periods.length
      ? periods.map(p => typeof p === 'string' ? p : (p.name || p.period || String(p)))
      : Object.keys(grid);

    tbody.innerHTML = periodNames.map(period => {
      const periodObj = periods.find(p => (typeof p === 'string' ? p : (p.name || p.period)) === period) || {};
      const timeLabel = periodObj.time || periodObj.start_time || '';
      const cells = DAYS.map(day => {
        const s = (grid[period] || {})[day];
        if (!s) return '<td class="text-center text-muted small">—</td>';
        const subj = s.subject || s.subject_name || s.learning_area || '';
        const cls = s.class_name || s.grade || s.stream || '';
        const room = s.room || s.venue || '';
        return `<td>
          <div class="fw-semibold small">${subj}</div>
          <div class="text-muted" style="font-size:.75rem;">${cls}${room ? ' · ' + room : ''}</div>
        </td>`;
      }).join('');
      return `<tr>
        <td class="fw-semibold small text-nowrap">${period}<br><span class="text-muted fw-normal">${timeLabel}</span></td>
        ${cells}
      </tr>`;
    }).join('');
  }

  function computeStats(slots) {
    const classes = new Set(slots.map(s => s.class_name || s.grade || '').filter(Boolean));
    const subjects = new Set(slots.map(s => s.subject || s.subject_name || '').filter(Boolean));
    setStats(slots.length, classes.size, subjects.size);
  }

  function setStats(periods, classes, subjects) {
    const p = document.getElementById('isStatPeriods'); if (p) p.textContent = periods;
    const c = document.getElementById('isStatClasses'); if (c) c.textContent = classes;
    const s = document.getElementById('isStatSubjects'); if (s) s.textContent = subjects;
  }

  function prevWeek() {
    const [year, wStr] = currentWeek.split('-W');
    const wNum = parseInt(wStr) - 1;
    if (wNum < 1) {
      currentWeek = `${parseInt(year) - 1}-W52`;
    } else {
      currentWeek = `${year}-W${String(wNum).padStart(2, '0')}`;
    }
    loadSchedule();
  }

  function nextWeek() {
    const [year, wStr] = currentWeek.split('-W');
    const wNum = parseInt(wStr) + 1;
    if (wNum > 52) {
      currentWeek = `${parseInt(year) + 1}-W01`;
    } else {
      currentWeek = `${year}-W${String(wNum).padStart(2, '0')}`;
    }
    loadSchedule();
  }

  function goToday() {
    currentWeek = getISOWeek(new Date());
    loadSchedule();
  }

  function init() {
    currentWeek = getISOWeek(new Date());

    const prevBtn = document.getElementById('isPrevWeek');
    const nextBtn = document.getElementById('isNextWeek');
    const todayBtn = document.getElementById('isToday');

    if (prevBtn) prevBtn.addEventListener('click', prevWeek);
    if (nextBtn) nextBtn.addEventListener('click', nextWeek);
    if (todayBtn) todayBtn.addEventListener('click', goToday);

    loadSchedule();
  }

  return { init, prevWeek, nextWeek, goToday };
})();

document.addEventListener('DOMContentLoaded', () => internScheduleController.init());
