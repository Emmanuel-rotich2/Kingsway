/**
 * Mentor Meetings Controller
 * Schedule and log mentor–intern meetings.
 * API: GET /staff/mentor-meetings, POST /staff/mentor-meetings, PUT /staff/mentor-meetings/{id}
 */

const mentorMeetingsController = (() => {
  let modal = null;
  let meetings = [];

  function show(id) { const el = document.getElementById(id); if (el) el.style.display = ''; }
  function hide(id) { const el = document.getElementById(id); if (el) el.style.display = 'none'; }
  function val(id) { const el = document.getElementById(id); return el ? el.value.trim() : ''; }
  function setVal(id, v) { const el = document.getElementById(id); if (el) el.value = v || ''; }
  function setText(id, v) { const el = document.getElementById(id); if (el) el.textContent = v; }

  function formatDate(d) {
    if (!d) return '—';
    return new Date(d).toLocaleDateString('en-GB', { day: 'numeric', month: 'short', year: 'numeric' });
  }

  function formatDateTime(d, t) {
    const datePart = formatDate(d);
    if (!t) return datePart;
    const [h, m] = t.split(':');
    const ampm = parseInt(h) >= 12 ? 'PM' : 'AM';
    const h12 = ((parseInt(h) % 12) || 12).toString().padStart(2, '0');
    return `${datePart} ${h12}:${m} ${ampm}`;
  }

  function isUpcoming(dateStr) {
    return new Date(dateStr) >= new Date();
  }

  function isThisMonth(dateStr) {
    const d = new Date(dateStr);
    const now = new Date();
    return d.getMonth() === now.getMonth() && d.getFullYear() === now.getFullYear();
  }

  function typeBadge(type) {
    const map = {
      scheduled: 'bg-primary',
      informal: 'bg-secondary',
      observation_debrief: 'bg-info text-dark',
    };
    return `<span class="badge ${map[type] || 'bg-light text-dark'}">${(type || '—').replace(/_/g, ' ')}</span>`;
  }

  function statusBadge(dateStr) {
    const upcoming = isUpcoming(dateStr);
    return upcoming
      ? '<span class="badge bg-primary">Upcoming</span>'
      : '<span class="badge bg-success">Completed</span>';
  }

  async function load() {
    show('mmLoading'); hide('mmContent'); hide('mmEmpty');
    try {
      const r = await callAPI('/staff/mentor-meetings', 'GET');
      meetings = Array.isArray(r?.data) ? r.data : (Array.isArray(r) ? r : []);
      hide('mmLoading');

      const upcoming = meetings.filter(m => isUpcoming(m.date || m.meeting_date)).length;
      const thisMonth = meetings.filter(m => isThisMonth(m.date || m.meeting_date)).length;
      setText('mmStatUpcoming', upcoming);
      setText('mmStatThisMonth', thisMonth);
      setText('mmStatTotal', meetings.length);

      if (!meetings.length) { show('mmEmpty'); return; }
      renderMeetings(meetings);
      show('mmContent');
    } catch (e) {
      hide('mmLoading');
      show('mmEmpty');
      console.error('mentor_meetings load error', e);
    }
  }

  function renderMeetings(meetings) {
    const tbody = document.getElementById('mmTableBody');
    if (!tbody) return;
    tbody.innerHTML = meetings.map(m => {
      const dateStr = m.date || m.meeting_date || '';
      const timeStr = m.time || m.meeting_time || '';
      return `
        <tr>
          <td class="text-nowrap">${formatDateTime(dateStr, timeStr)}</td>
          <td>${typeBadge(m.type || m.meeting_type)}</td>
          <td>${m.location || m.venue || '—'}</td>
          <td class="small">${m.agenda || m.topic || '—'}</td>
          <td>${statusBadge(dateStr)}</td>
          <td class="small text-muted" style="max-width:200px;">${m.notes || m.summary || '—'}</td>
          <td>
            <button class="btn btn-outline-primary btn-sm" onclick="mentorMeetingsController.editMeeting(${m.id})">
              <i class="bi bi-pencil"></i>
            </button>
          </td>
        </tr>
      `;
    }).join('');
  }

  function showAddModal() {
    setVal('mmMeetingId', '');
    setVal('mmMeetDate', new Date().toISOString().slice(0, 10));
    setVal('mmMeetTime', '');
    setVal('mmLocation', '');
    setVal('mmMeetType', 'scheduled');
    setVal('mmAgenda', '');
    setVal('mmNotes', '');
    hide('mmNotesGroup');
    document.getElementById('mmModalLabel').innerHTML = '<i class="bi bi-calendar-plus me-2 text-primary"></i>Schedule Meeting';
    if (!modal) modal = new bootstrap.Modal(document.getElementById('mmModal'));
    modal.show();
  }

  function editMeeting(id) {
    const m = meetings.find(x => x.id == id);
    if (!m) return;
    const dateStr = m.date || m.meeting_date || '';
    const timeStr = m.time || m.meeting_time || '';
    setVal('mmMeetingId', m.id);
    setVal('mmMeetDate', dateStr.slice(0, 10));
    setVal('mmMeetTime', timeStr);
    setVal('mmLocation', m.location || m.venue || '');
    setVal('mmMeetType', m.type || m.meeting_type || 'scheduled');
    setVal('mmAgenda', m.agenda || m.topic || '');
    setVal('mmNotes', m.notes || m.summary || '');
    show('mmNotesGroup');
    document.getElementById('mmModalLabel').innerHTML = '<i class="bi bi-pencil me-2 text-primary"></i>Edit Meeting';
    if (!modal) modal = new bootstrap.Modal(document.getElementById('mmModal'));
    modal.show();
  }

  async function saveMeeting() {
    const id = val('mmMeetingId');
    const date = val('mmMeetDate');
    if (!date) { showNotification('Date is required.', 'warning'); return; }

    const body = {
      date,
      time: val('mmMeetTime'),
      location: val('mmLocation'),
      type: val('mmMeetType'),
      agenda: val('mmAgenda'),
      notes: val('mmNotes'),
    };

    try {
      if (id) {
        await callAPI(`/staff/mentor-meetings/${id}`, 'PUT', body);
        showNotification('Meeting updated.', 'success');
      } else {
        await callAPI('/staff/mentor-meetings', 'POST', body);
        showNotification('Meeting scheduled.', 'success');
      }
      if (modal) modal.hide();
      await load();
    } catch (e) {
      showNotification('Failed to save meeting.', 'danger');
      console.error(e);
    }
  }

  function init() {
    const scheduleBtn = document.getElementById('mmScheduleBtn');
    if (scheduleBtn) scheduleBtn.addEventListener('click', showAddModal);

    const saveBtn = document.getElementById('mmSaveBtn');
    if (saveBtn) saveBtn.addEventListener('click', saveMeeting);

    load();
  }

  return { init, editMeeting, showAddModal };
})();

document.addEventListener('DOMContentLoaded', () => mentorMeetingsController.init());
