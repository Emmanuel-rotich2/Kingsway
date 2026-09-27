/**
 * js/pages/parents/children.js — All children listing.
 */
(function () {
  'use strict';
  var P = window.ParentCommon;

  function photoUrl(value) {
    return window.KingswayFileLifecycle?.resolveUrl?.(value) || window.KingswayFileLifecycle?.avatarUrl?.() || '';
  }

  function applicationForChild(applications, childId) {
    return (applications || []).find(function (a) {
      return String(a.enrolled_student_id || '') === String(childId || '');
    }) || null;
  }

  function stageSummary(application) {
    if (!application || application.status === 'enrolled' || application.status === 'cancelled') return '';
    var stages = application.stages || [];
    var current = stages.findIndex(function (s) { return s.state === 'current'; });
    var currentName = application.current_stage_name || (current >= 0 ? stages[current].name : 'Application received');
    var nextName = current >= 0 && stages[current + 1] ? stages[current + 1].name : 'Awaiting finalisation';
    return '<div class="pp-child-workflow mt-3">' +
      '<div class="d-flex justify-content-between align-items-center gap-2 mb-1"><span class="badge bg-warning-subtle text-warning-emphasis">' + P.esc(application.status_label || 'Admission in progress') + '</span>' +
      '<small class="text-muted">' + P.esc(application.target_term || '') + '</small></div>' +
      '<div class="small"><strong>Current:</strong> ' + P.esc(currentName) + '</div><div class="small text-muted"><strong>Next:</strong> ' + P.esc(nextName) + '</div>' +
      '<div class="progress mt-2" style="height:5px"><div class="progress-bar bg-warning" style="width:' + (application.progress_percent || 0) + '%"></div></div></div>';
  }

  function renderUnlinkedAdmissions(applications, children) {
    var active = (applications || []).filter(function (a) {
      return a.status !== 'enrolled' && a.status !== 'cancelled' && !children.some(function (c) {
        return String(c.id) === String(a.enrolled_student_id || '');
      });
    });
    var box = document.getElementById('ppPendingAdmissions');
    if (!box) return;
    if (!active.length) { box.innerHTML = ''; box.classList.add('d-none'); return; }
    box.classList.remove('d-none');
    box.innerHTML = '<div class="d-flex align-items-center justify-content-between gap-2 mb-2"><h2 class="pp-pending-title"><i class="bi bi-hourglass-split me-2 text-warning"></i>Admissions in progress</h2><span class="badge rounded-pill bg-warning-subtle text-warning-emphasis">' + active.length + ' pending</span></div>' +
      '<div class="pp-pending-grid">' + active.map(function (a) {
        return '<article class="pp-pending-card"><h3>' + P.esc(a.applicant_name || 'Admission application') + '</h3><small>' + P.esc(a.application_no || '') + (a.target_term ? ' · ' + P.esc(a.target_term) : '') + '</small>' + stageSummary(a) + '</article>';
      }).join('') + '</div>';
  }

  function renderChildren(children, applications) {
    var el = document.getElementById('ppChildrenCards');
    if (!el) return;
    if (!children.length) {
      el.innerHTML = '<div class="col-12"><div class="alert alert-info text-center">No children linked to this account. Contact the school office.</div></div>';
      return;
    }
    el.innerHTML = children.map(function (c) {
      var application = applicationForChild(applications, c.id);
      var registrationDue = application ? parseFloat(application.registration_fee_due || 0) : 0;
      var bal = parseFloat(c.current_balance || 0) + registrationDue;
      var bc = bal <= 0 ? 'success' : (bal < 5000 ? 'warning' : 'danger');
      var bt = bal <= 0 ? 'Fees Cleared' : 'KES ' + bal.toLocaleString() + ' Due';
      var photo = photoUrl(c.photo_url);
      var initials = P.esc((c.first_name || '?')[0].toUpperCase());
      var identity = photo ? '<img class="pp-child-photo" src="' + P.esc(photo) + '" alt="' + P.esc(c.first_name + ' ' + c.last_name) + '" loading="lazy" onerror="this.hidden=true;this.nextElementSibling.hidden=false"><span class="pp-child-avatar-fallback" hidden>' + initials + '</span>' : '<span class="pp-child-avatar-fallback">' + initials + '</span>';
      return '<div class="pp-child-grid-item"><div class="card border-0 shadow-sm rounded-4 child-card"><div class="card-body p-4">' +
        '<div class="d-flex align-items-center mb-3"><div class="pp-child-photo-wrap me-3">' + identity + '</div><div><h6 class="mb-0 fw-bold">' + P.esc(c.first_name + ' ' + c.last_name) + '</h6><small class="text-muted">' + P.esc(c.class_name || 'Admission in progress') + (c.admission_no ? ' · ' + P.esc(c.admission_no) : '') + '</small></div></div>' +
        '<div class="d-flex justify-content-between align-items-center mb-2"><span class="text-muted small">' + (registrationDue > 0 ? 'Total due' : 'Current balance') + '</span><span class="badge bg-' + bc + ' px-3 py-2">' + bt + '</span></div>' + stageSummary(application) +
        '<div class="d-flex flex-wrap gap-2 mt-3"><a href="' + P.childUrl('results', c.id) + '" class="btn btn-sm btn-outline-success rounded-pill"><i class="bi bi-mortarboard me-1"></i>Results</a><a href="' + P.childUrl('fees', c.id) + '" class="btn btn-sm btn-outline-success rounded-pill"><i class="bi bi-receipt me-1"></i>Fees</a><a href="' + P.childUrl('attendance', c.id) + '" class="btn btn-sm btn-outline-success rounded-pill"><i class="bi bi-calendar-check me-1"></i>Attendance</a><label class="btn btn-sm btn-outline-secondary rounded-pill mb-0"><i class="bi bi-camera me-1"></i>Update photo<input type="file" accept="image/jpeg,image/png,image/webp" class="d-none pp-photo-input" data-student-id="' + c.id + '"></label></div>' +
        '</div></div></div>';
    }).join('');
  }

  async function init() {
    if (!(await P.ensureAuth())) return;
    var d = await P.loadDashboard();
    var children = d.children || [];
    var applications = [];
    try {
      var response = await P.apiFetch('/admission-applications', 'GET');
      var data = response && response.data !== undefined ? response.data : response;
      applications = data && data.applications ? data.applications : [];
    } catch (_) { /* keep the normal child cards usable */ }
    renderUnlinkedAdmissions(applications, children);
    renderChildren(children, applications);
    document.querySelectorAll('.pp-photo-input').forEach(function (input) {
      input.addEventListener('change', async function () {
        if (!input.files || !input.files[0]) return;
        var form = new FormData();
        form.append('student_id', input.dataset.studentId);
        form.append('photo', input.files[0]);
        try {
          await P.apiFetch('/student-photo', 'POST', form, { isFile: true });
          alert('Photo submitted for school approval.');
        } catch (e) {
          alert(e.message || 'Unable to submit the photo.');
        }
        input.value = '';
      });
    });
    var countEl = document.getElementById('ppChildCount');
    if (countEl) countEl.textContent = children.length + ' linked';
    document.body.classList.toggle('pp-phone-layout', window.innerWidth <= 575.98 && (!window.screen || window.screen.width <= 600));
  }

  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init);
  else init();
})();
