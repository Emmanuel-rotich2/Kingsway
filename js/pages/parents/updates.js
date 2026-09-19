/**
 * js/pages/parents/updates.js — School Updates (announcements + events).
 * No child selector; loaded for the whole family account.
 */
(function () {
  'use strict';
  var P = window.ParentCommon;
  var _data = null;
  var _tab = 'announcements';

  function priorityBadge(p) {
    var map = { critical: 'danger', high: 'warning', normal: 'success', low: 'secondary' };
    return '<span class="badge bg-' + (map[p] || 'success') + '">' + P.esc(p || 'normal') + '</span>';
  }

  function renderAnnouncements(d, el) {
    var items = d.announcements || [];
    if (!items.length) {
      el.innerHTML = '<div class="alert alert-info">There are no announcements for parents right now.</div>';
      return;
    }
    var html = items.map(function (a) {
      return '<div class="card border-0 shadow-sm mb-3"><div class="card-body">' +
        '<div class="d-flex justify-content-between align-items-start gap-2">' +
        '<h5 class="fw-bold mb-1">' + P.esc(a.title || '') + '</h5>' + priorityBadge(a.priority) + '</div>' +
        '<div class="small text-muted mb-2">' + P.esc((a.published_at || '').substring(0, 10)) +
        (a.announcement_type ? ' · ' + P.esc(a.announcement_type) : '') + '</div>' +
        '<p class="small mb-0">' + P.esc(a.content || '') + '</p>' +
        '</div></div>';
    }).join('');
    el.innerHTML = html;
  }

  function renderEvents(d, el) {
    var events = d.events || [];
    var exams = d.exams || [];
    var terms = d.term_dates || [];
    var html = '<div class="alert alert-success small"><i class="bi bi-info-circle me-2"></i>Events below are published by the school for parents. School-term dates and any upcoming assessments for your children are listed underneath.</div>';

    html += '<h6 class="text-muted mt-2 mb-2"><i class="bi bi-calendar2-event me-1"></i>Upcoming school events</h6>';
    if (events.length) {
      var rows = events.map(function (e) {
        return '<tr><td>' + P.esc((e.start_at || '').substring(0, 16).replace('T', ' ')) + '</td><td><strong>' + P.esc(e.title || '') + '</strong>' +
          (e.description ? '<div class="small text-muted">' + P.esc(e.description) + '</div>' : '') + '</td>' +
          '<td>' + P.esc(e.category || e.type || '') + '</td><td>' + P.esc(e.location || '') + '</td>' +
          '<td><span class="badge bg-' + (e.status === 'ongoing' ? 'danger' : 'success') + '">' + P.esc(e.status || 'upcoming') + '</span></td></tr>';
      }).join('');
      html += '<div class="table-responsive"><table class="pp-table table-sm"><thead><tr><th>When</th><th>Event</th><th>Category</th><th>Venue</th><th>Status</th></tr></thead><tbody>' + rows + '</tbody></table></div>';
    } else {
      html += '<div class="alert alert-info py-2">No upcoming school events are published right now.</div>';
    }

    html += '<h6 class="text-muted mt-4 mb-2"><i class="bi bi-pencil-square me-1"></i>Upcoming assessments for your children</h6>';
    if (exams.length) {
      var eRows = exams.map(function (x) {
        return '<tr><td>' + P.esc(x.student_name || '') + '</td><td>' + P.esc((x.assessment_date || '').substring(0, 10)) + '</td><td><strong>' + P.esc(x.title || '') + '</strong></td>' +
          '<td>' + P.esc(x.learning_area || '') + '</td><td>' + P.esc(x.class_name || '') + ' ' + P.esc(x.stream_name || '') + '</td>' +
          '<td><span class="badge bg-primary">' + P.esc(x.status || '') + '</span></td></tr>';
      }).join('');
      html += '<div class="table-responsive"><table class="pp-table table-sm"><thead><tr><th>Child</th><th>Date</th><th>Assessment</th><th>Learning area</th><th>Class</th><th>Status</th></tr></thead><tbody>' + eRows + '</tbody></table></div>';
    } else {
      html += '<div class="alert alert-info py-2">No upcoming assessments are scheduled for your children.</div>';
    }

    html += '<h6 class="text-muted mt-4 mb-2"><i class="bi bi-calendar3 me-1"></i>Term dates</h6>';
    if (terms.length) {
      var tRows = terms.map(function (t) {
        return '<tr><td><strong>' + P.esc(t.term_name || '') + '</strong></td>' +
          '<td>' + P.esc((t.opening_date || '').substring(0, 10)) + '</td>' +
          '<td>' + P.esc((t.half_term_start || '').substring(0, 10)) + ' – ' + P.esc((t.half_term_end || '').substring(0, 10)) + '</td>' +
          '<td>' + P.esc((t.closing_date || '').substring(0, 10)) + '</td>' +
          '<td><span class="badge bg-' + (t.status === 'current' ? 'success' : 'secondary') + '">' + P.esc(t.status || '') + '</span></td></tr>';
      }).join('');
      html += '<div class="table-responsive"><table class="pp-table table-sm"><thead><tr><th>Term</th><th>Opens</th><th>Half-term</th><th>Closes</th><th>Status</th></tr></thead><tbody>' + tRows + '</tbody></table></div>';
    } else {
      html += '<div class="alert alert-info py-2">Term dates are not available yet.</div>';
    }

    el.innerHTML = html;
  }

  function render() {
    var el = document.getElementById('ppUpdatesContent');
    if (!el) return;
    if (!_data) { el.innerHTML = '<div class="alert alert-success">Loading school updates…</div>'; return; }
    if (_tab === 'announcements') renderAnnouncements(_data, el);
    else renderEvents(_data, el);
  }

  async function init() {
    if (!(await P.ensureAuth())) return;
    await P.loadDashboard();
    var el = document.getElementById('ppUpdatesContent');
    P.showLoading(el, 'Loading school updates…');
    try {
      var resp = await P.apiFetch('/updates', 'GET');
      _data = resp.data !== undefined ? resp.data : resp;
      render();
    } catch (e) {
      P.showError(el, e.message);
    }
  }

  document.body.addEventListener('click', function (e) {
    var btn = e.target.closest('[data-pp-tab]');
    if (!btn) return;
    _tab = btn.dataset.ppTab;
    document.querySelectorAll('[data-pp-tab]').forEach(function (b) { b.classList.toggle('active', b === btn); });
    render();
  });

  document.getElementById('btnUpdatesCsv').addEventListener('click', function () {
    if (!_data) return;
    var rows = [];
    if (_tab === 'announcements') {
      rows = (_data.announcements || []).map(function (a) { return [a.title, a.priority, a.announcement_type, a.published_at, a.content]; });
      P.exportCsv('announcements.csv', P.csvFromHeaders(['Title', 'Priority', 'Type', 'Published', 'Content'], rows));
    } else {
      var rows2 = [];
      (_data.events || []).forEach(function (e) {
        rows2.push(['Event', e.title, e.category || e.type, (e.start_at || '').replace('T', ' '), e.location, e.status, e.description || '']);
      });
      (_data.exams || []).forEach(function (x) {
        rows2.push(['Assessment', x.title, x.learning_area, x.assessment_date, (x.class_name || '') + ' ' + (x.stream_name || ''), x.status, x.student_name || '']);
      });
      (_data.term_dates || []).forEach(function (t) {
        rows2.push(['Term', t.term_name, '', t.opening_date + ' to ' + t.closing_date, '', t.status, 'Half-term ' + t.half_term_start + ' – ' + t.half_term_end]);
      });
      if (!rows2.length) { alert('Nothing to export right now.'); return; }
      P.exportCsv('events-and-dates.csv', P.csvFromHeaders(['Kind', 'Title/Category', 'Type/Area', 'Date(s)', 'Venue/Class', 'Status', 'Details'], rows2));
    }
  });

  document.getElementById('btnUpdatesPrint').addEventListener('click', function () { P.printSection(); });

  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init);
  else init();
})();