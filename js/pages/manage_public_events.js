/**
 * manage_public_events.js — Public Events Controller (standalone page).
 * CRUD for public website events.
 */
const managePublicEventsController = {
  state: { items: [] },

  API: (method, endpoint, data, params, opts) => window.callAPI(endpoint, method, data, params, opts),

  notify(msg, type = 'success') {
    if (window.showNotification) { showNotification(msg, type); return; }
    const el = document.createElement('div');
    el.className = `alert alert-${type} position-fixed top-0 end-0 m-3 shadow`;
    el.style.zIndex = 99999;
    el.textContent = msg;
    document.body.appendChild(el);
    setTimeout(() => el.remove(), 3000);
  },

  esc(s) { return String(s || '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;'); },
  fmtDate(d) { return d ? new Date(d).toLocaleDateString('en-KE', { day: '2-digit', month: 'short', year: 'numeric' }) : '\u2014'; },
  badgeStatus(s) {
    const colors = { upcoming: 'success', ongoing: 'warning', past: 'secondary', cancelled: 'danger' };
    const c = colors[s] || 'secondary';
    return `<span class="badge bg-${c}">${s || '\u2014'}</span>`;
  },

  async loadStats() {
    try {
      const r = await this.API('GET', 'website/stats');
      if (r.status === 'success' && r.data) {
        const events = document.getElementById('statEvents');
        if (events) events.textContent = r.data.events ?? '\u2014';
      }
      const upcoming = this.state.items.filter(e => e.status === 'upcoming' || e.status === 'ongoing').length;
      const upcomingStat = document.getElementById('statUpcoming');
      if (upcomingStat) upcomingStat.textContent = upcoming;
    } catch (_) {}
  },

  async loadData() {
    const body = document.getElementById('eventsTableBody');
    if (!body) return;
    body.innerHTML = '<tr><td colspan="6" class="text-center py-3"><div class="spinner-border spinner-border-sm"></div></td></tr>';
    try {
      const upcoming = document.getElementById('eventsUpcomingOnly')?.checked ? '1' : '';
      const r = await this.API('GET', 'website/events', { upcoming });
      this.state.items = r?.data?.items || [];
      this.render();
      this.loadStats();
    } catch (e) {
      if (body.isConnected) body.innerHTML = `<tr><td colspan="6" class="text-center py-3 text-danger">${this.esc(e.message || 'Load failed')}</td></tr>`;
    }
  },

  render() {
    const body = document.getElementById('eventsTableBody');
    if (!body) return;
    if (!this.state.items.length) { body.innerHTML = '<tr><td colspan="6" class="text-center py-4 text-muted">No events found.</td></tr>'; return; }
    body.innerHTML = this.state.items.map(ev => `
      <tr>
        <td class="fw-semibold small">${this.fmtDate(ev.event_date)}</td>
        <td>${this.esc(ev.title)}<div class="text-muted" style="font-size:.72rem">${this.esc(ev.event_time || '')}</div></td>
        <td><span class="ws-tag-chip" style="background:#e3f2fd;color:#1976d2;border-color:#1976d244">${this.esc(ev.category || 'Academic')}</span></td>
        <td class="text-muted small">${this.esc(ev.location || '\u2014')}</td>
        <td>${this.badgeStatus(ev.status)}</td>
        <td class="text-end">
          <button class="btn btn-sm btn-outline-secondary rounded-pill px-2 me-1" onclick="publicEventsOpenModal(${ev.id})"><i class="bi bi-pencil"></i></button>
          <button class="btn btn-sm btn-outline-danger rounded-pill px-2" onclick="publicEventsDelete(${ev.id},'${this.esc(ev.title).replace(/'/g, '')}')"><i class="bi bi-trash"></i></button>
        </td>
      </tr>`).join('');
  },

  async openModal(id = null) {
    document.getElementById('eventEditId').value = id || '';
    document.getElementById('wsEventModalTitle').textContent = id ? 'Edit Event' : 'New Event';
    ['eventTitle', 'eventDate', 'eventTime', 'eventEndDate', 'eventLocation', 'eventDescription'].forEach(f => {
      const el = document.getElementById(f); if (el) el.value = '';
    });
    document.getElementById('eventStatus').value   = 'upcoming';
    document.getElementById('eventCategory').value = 'Academic';
    if (id) {
      const r = await this.API('GET', `website/events/${id}`);
      const ev = r?.data;
      if (ev) {
        document.getElementById('eventTitle').value       = ev.title || '';
        document.getElementById('eventDate').value        = ev.event_date?.split('T')[0] || '';
        document.getElementById('eventTime').value        = ev.event_time || '';
        document.getElementById('eventEndDate').value     = ev.end_date?.split('T')[0] || '';
        document.getElementById('eventLocation').value    = ev.location || '';
        document.getElementById('eventDescription').value = ev.description || '';
        document.getElementById('eventStatus').value      = ev.status || 'upcoming';
        document.getElementById('eventCategory').value    = ev.category || 'Academic';
      }
    }
    new bootstrap.Modal(document.getElementById('wsEventModal')).show();
  },

  async save() {
    const id = document.getElementById('eventEditId').value;
    const payload = {
      title:       document.getElementById('eventTitle').value.trim(),
      event_date:  document.getElementById('eventDate').value,
      event_time:  document.getElementById('eventTime').value || null,
      end_date:    document.getElementById('eventEndDate').value || null,
      location:    document.getElementById('eventLocation').value.trim(),
      description: document.getElementById('eventDescription').value.trim(),
      category:    document.getElementById('eventCategory').value,
      status:      document.getElementById('eventStatus').value,
    };
    if (!payload.title || !payload.event_date) return this.notify('Title and date are required.', 'warning');
    try {
      const r = id ? await this.API('PUT', `website/events/${id}`, payload) : await this.API('POST', 'website/events', payload);
      if (r.status === 'success') {
        this.notify(id ? 'Event updated' : 'Event created');
        bootstrap.Modal.getInstance(document.getElementById('wsEventModal')).hide();
        this.loadData();
      } else this.notify(r.message, 'danger');
    } catch (e) { this.notify(e.message, 'danger'); }
  },

  async delete(id, title) {
    if (!(await window.confirmAction('Confirm Deletion', `Delete event "${title}"?`, { confirmText: 'Delete', danger: true }))) return;
    try { await this.API('DELETE', `website/events/${id}`); this.notify('Event deleted'); this.loadData(); }
    catch (e) { this.notify(e.message, 'danger'); }
  },

  setupEventListeners() {
    document.getElementById('eventsUpcomingOnly')?.addEventListener('change', () => this.loadData());
  },

  async init() {
    if (window.AuthContext?.ready) await window.AuthContext.ready();
    this.setupEventListeners();
    this.loadData();
  },
};

window.publicEventsOpenModal = (id) => managePublicEventsController.openModal(id);
window.publicEventsSave      = () => managePublicEventsController.save();
window.publicEventsDelete    = (id, title) => managePublicEventsController.delete(id, title);

if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', () => managePublicEventsController.init().catch(() => {}));
} else {
  managePublicEventsController.init().catch(() => {});
}

window.managePublicEventsController = managePublicEventsController;
