/**
 * My Mentor Controller
 * Displays intern's assigned mentor profile and meeting history.
 * API: GET /staff/my-mentor
 */

const myMentorController = (() => {
  function show(id) { const el = document.getElementById(id); if (el) el.style.display = ''; }
  function hide(id) { const el = document.getElementById(id); if (el) el.style.display = 'none'; }
  function setText(id, val) { const el = document.getElementById(id); if (el) el.textContent = val || '—'; }
  function setHref(id, href, label) {
    const el = document.getElementById(id);
    if (el) { el.textContent = label || href || '—'; if (href) el.href = href; }
  }

  function formatDate(d) {
    if (!d) return '—';
    return new Date(d).toLocaleDateString('en-GB', { day: 'numeric', month: 'short', year: 'numeric' });
  }

  function typeBadge(type) {
    const map = {
      scheduled: 'bg-primary',
      informal: 'bg-secondary',
      observation_debrief: 'bg-info text-dark',
    };
    const cls = map[type] || 'bg-light text-dark';
    return `<span class="badge ${cls}">${(type || '').replace(/_/g, ' ')}</span>`;
  }

  async function load() {
    show('mmLoading'); hide('mmContent'); hide('mmEmpty');
    try {
      const r = await callAPI('/staff/my-mentor', 'GET');
      const mentor = r?.data || r || null;

      hide('mmLoading');

      if (!mentor || !mentor.id) {
        show('mmEmpty');
        return;
      }

      const initials = ((mentor.first_name || '')[0] || '') + ((mentor.last_name || '')[0] || '');
      const avatarEl = document.getElementById('mmAvatar');
      if (avatarEl) avatarEl.textContent = initials || '?';

      setText('mmName', `${mentor.first_name || ''} ${mentor.last_name || ''}`.trim());
      setText('mmTitle', mentor.title || mentor.position || mentor.job_title || 'Teacher');
      setText('mmSubject', mentor.subject || mentor.department || '');
      setText('mmRoom', mentor.room || mentor.office || '');
      setText('mmOfficeHours', mentor.office_hours || 'Contact for availability');
      setHref('mmPhone', `tel:${mentor.phone || ''}`, mentor.phone || '');
      setHref('mmEmail', `mailto:${mentor.email || ''}`, mentor.email || '');

      const meetings = Array.isArray(mentor.meetings) ? mentor.meetings
        : (Array.isArray(r?.meetings) ? r.meetings : []);
      renderMeetings(meetings);

      show('mmContent');
    } catch (e) {
      hide('mmLoading');
      show('mmEmpty');
      console.error('my_mentor load error', e);
    }
  }

  function renderMeetings(meetings) {
    const tbody = document.getElementById('mmMeetingBody');
    if (!tbody) return;
    if (!meetings.length) {
      tbody.innerHTML = '<tr><td colspan="5" class="text-center text-muted py-3">No meetings recorded yet.</td></tr>';
      return;
    }
    tbody.innerHTML = meetings.map(m => `
      <tr>
        <td class="text-nowrap">${formatDate(m.date || m.meeting_date)}</td>
        <td>${typeBadge(m.type || m.meeting_type)}</td>
        <td>${m.topic || m.agenda || '—'}</td>
        <td>${m.duration || m.duration_minutes ? (m.duration || m.duration_minutes) + ' min' : '—'}</td>
        <td class="small text-muted" style="max-width:200px;">${m.notes || m.summary || '—'}</td>
      </tr>
    `).join('');
  }

  function init() {
    load();
  }

  return { init, load };
})();

document.addEventListener('DOMContentLoaded', () => myMentorController.init());
