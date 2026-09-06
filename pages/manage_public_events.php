<?php
/* Public Events — standalone page (split from Website Management). */
$appBase = rtrim(str_replace('\\','/',dirname($_SERVER['SCRIPT_NAME'] ?? '')), '/');
if ($appBase === '.') $appBase = '';
?>

<style>
.ws-stat-card { background:#fff;border:1px solid #e2e8f0;border-radius:12px;padding:20px 24px;display:flex;align-items:center;gap:16px; }
.ws-stat-icon { width:48px;height:48px;border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:1.3rem;flex-shrink:0; }
.ws-table td,.ws-table th { vertical-align:middle;font-size:.85rem; }
.ws-tag-chip { display:inline-block;padding:2px 8px;border-radius:12px;font-size:.72rem;font-weight:600;border:1px solid; }
.ws-form-group label { font-size:.82rem;font-weight:600;color:#374151;margin-bottom:4px;display:block; }
.ws-form-group input,.ws-form-group select,.ws-form-group textarea { width:100%;padding:9px 12px;border:1px solid #d1d5db;border-radius:8px;font-size:.88rem;outline:none;transition:border-color .15s; }
.ws-form-group input:focus,.ws-form-group select:focus,.ws-form-group textarea:focus { border-color:#198754;box-shadow:0 0 0 3px rgba(25,135,84,.1); }
</style>

<div class="container-fluid px-4 py-4">

  <div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-3">
    <div>
      <h4 class="fw-bold mb-0"><i class="bi bi-calendar-event text-success me-2"></i>Public Events</h4>
      <p class="text-muted small mb-0 mt-1">Schedule and manage the events displayed on the public website.</p>
    </div>
    <a href="<?= $appBase ?>/" target="_blank" class="btn btn-sm btn-outline-success rounded-pill px-3">
      <i class="bi bi-box-arrow-up-right me-1"></i>View Public Site
    </a>
  </div>

  <div class="row g-3 mb-4">
    <div class="col-6 col-lg-3"><div class="ws-stat-card">
      <div class="ws-stat-icon" style="background:#e3f2fd"><i class="bi bi-calendar-event text-primary"></i></div>
      <div><div class="fw-bold fs-5 mb-0" id="statEvents">—</div><div class="text-muted small">Events Scheduled</div></div>
    </div></div>
    <div class="col-6 col-lg-3"><div class="ws-stat-card">
      <div class="ws-stat-icon" style="background:#e8f5e9"><i class="bi bi-calendar-check text-success"></i></div>
      <div><div class="fw-bold fs-5 mb-0" id="statUpcoming">—</div><div class="text-muted small">Upcoming</div></div>
    </div></div>
  </div>

  <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <label class="d-flex align-items-center gap-2 small"><input type="checkbox" id="eventsUpcomingOnly"> Upcoming only</label>
    <button class="btn btn-success btn-sm rounded-pill px-3" onclick="publicEventsOpenModal()">
      <i class="bi bi-plus-lg me-1"></i>New Event
    </button>
  </div>

  <div class="bg-white border rounded-3 overflow-hidden">
    <table class="table table-hover ws-table mb-0">
      <thead class="table-light"><tr>
        <th scope="col">Date</th><th scope="col">Title</th><th scope="col">Category</th><th scope="col">Location</th><th scope="col">Status</th><th class="text-end">Actions</th>
      </tr></thead>
      <tbody id="eventsTableBody"><tr><td colspan="6" class="text-center py-4 text-muted"><div class="spinner-border spinner-border-sm me-2"></div>Loading…</td></tr></tbody>
    </table>
  </div>

</div>

<!-- Event Modal -->
<div class="modal fade" id="wsEventModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-scrollable modal-lg"><div class="modal-content">
    <div class="modal-header border-0 pb-0">
      <h5 class="modal-title fw-bold" id="wsEventModalTitle">New Event</h5>
      <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
    </div>
    <div class="modal-body">
      <input type="hidden" id="eventEditId">
      <div class="row g-3">
        <div class="col-12 ws-form-group"><label>Event Title *</label><input type="text" id="eventTitle" placeholder="Event name"></div>
        <div class="col-md-6 ws-form-group"><label>Date *</label><input type="date" id="eventDate"></div>
        <div class="col-md-6 ws-form-group"><label>Time</label><input type="time" id="eventTime"></div>
        <div class="col-md-6 ws-form-group"><label>End Date</label><input type="date" id="eventEndDate"></div>
        <div class="col-md-6 ws-form-group">
          <label>Category *</label>
          <select id="eventCategory">
            <option value="Academic">Academic</option>
            <option value="Sports">Sports</option>
            <option value="Ceremony">Ceremony</option>
            <option value="Meeting">Meeting</option>
            <option value="Community">Community</option>
            <option value="Cultural">Cultural</option>
          </select>
        </div>
        <div class="col-12 ws-form-group"><label>Location</label><input type="text" id="eventLocation" placeholder="e.g. School Assembly Ground"></div>
        <div class="col-12 ws-form-group"><label>Description</label><textarea id="eventDescription" rows="4" placeholder="Full event details…"></textarea></div>
        <div class="col-md-6 ws-form-group">
          <label>Status</label>
          <select id="eventStatus"><option value="upcoming">Upcoming</option><option value="ongoing">Ongoing</option><option value="past">Past</option><option value="cancelled">Cancelled</option></select>
        </div>
      </div>
    </div>
    <div class="modal-footer border-0">
      <button class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
      <button class="btn btn-success btn-sm px-4" onclick="publicEventsSave()"><i class="bi bi-calendar-check me-1"></i>Save Event</button>
    </div>
  </div></div>
</div>

<?php asset_script($appBase, 'js/pages/manage_public_events.js'); ?>
