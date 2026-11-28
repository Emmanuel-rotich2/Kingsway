/**
 * Admissions — Apply for Admission (staff self-service). The applying staff
 * member's details come from their staff profile; they become the
 * parent/guardian of record. Shows the applications they have submitted.
 */
(function () {
  'use strict';
  function unwrap(value) { return value && value.data !== undefined ? value.data : value; }
  function esc(value) { return String(value ?? '').replace(/[&<>"']/g, function (c) { return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' }[c]); }); }
  function titleCase(v) { return String(v || '').replace(/_/g, ' ').replace(/\b\w/g, function (m) { return m.toUpperCase(); }); }
  var call = function (endpoint, method, data, params, opts) {
    return window.API.apiCall(endpoint, method || 'GET', data || null, params || {}, Object.assign({ checkPermission: false }, opts || {}));
  };

  async function loadApplications() {
    var body = document.getElementById('saApplications');
    try {
      var d = unwrap(await call('/staff/my-admission-applications')) || {};
      var rows = d.applications || [];
      body.innerHTML = rows.length ? rows.map(function (a) {
        var st = String(a.status || '');
        var cls = /enrolled|approved|admitted/.test(st) ? 'bg-success' : /reject|withdraw|cancel/.test(st) ? 'bg-danger' : 'bg-warning text-dark';
        return '<tr><td>' + esc(a.application_no || '') + '</td><td>' + esc(a.applicant_name || '') + '</td><td>' + esc(a.grade_applying_for || '') + '</td>' +
          '<td>' + esc(String(a.created_at || '').slice(0, 10)) + '</td><td>' + esc(a.admission_appointment_date || '—') + '</td>' +
          '<td><span class="badge ' + cls + '">' + esc(titleCase(st) || 'Submitted') + '</span></td></tr>';
      }).join('') : '<tr><td colspan="6" class="text-center text-muted py-3">You have not submitted any applications yet.</td></tr>';
    } catch (e) {
      body.innerHTML = '<tr><td colspan="6" class="text-center text-danger py-3">' + esc(e.message || 'Your applications could not be loaded.') + '</td></tr>';
    }
  }

  async function init() {
    var identity = document.getElementById('staffAdmissionIdentity');
    if (!identity) return;
    await window.AuthContext?.ready?.();
    var grade = document.getElementById('staffAdmissionGrade');
    var windowSelect = document.getElementById('staffAdmissionWindow');
    var form = document.getElementById('staffAdmissionForm');
    try {
      var profile = unwrap(await call('/staff/profile-get')) || {};
      var name = [profile.first_name, profile.middle_name, profile.last_name].filter(Boolean).join(' ');
      identity.innerHTML = '<div class="row g-2 small">' +
        '<div class="col-md-4"><span class="text-muted">Name</span><br><strong>' + esc(name || '—') + '</strong></div>' +
        '<div class="col-md-3"><span class="text-muted">Phone</span><br>' + esc(profile.phone || '—') + '</div>' +
        '<div class="col-md-3"><span class="text-muted">Email</span><br>' + esc(profile.email || '—') + '</div>' +
        '<div class="col-md-2"><span class="text-muted">Staff no.</span><br>' + esc(profile.staff_no || '—') + '</div></div>' +
        '<div class="small text-muted mt-2">These come from your staff profile. To change them, update your profile.</div>';
      var options = unwrap(await call('/staff/my-admission-options')) || {};
      var grades = options.grades || [];
      grade.innerHTML = '<option value="">Select grade</option>' + grades.map(function (g) { return '<option value="' + esc(g) + '">' + esc(g) + '</option>'; }).join('');
      var windows = options.windows || [];
      windowSelect.innerHTML = windows.length
        ? '<option value="">Select an open admission window</option>' + windows.map(function (w) { return '<option value="' + Number(w.admission_window_id || 0) + '">' + esc(w.admission_window_label || 'Admission window') + '</option>'; }).join('')
        : '<option value="">No admission window is open right now</option>';
      if (windows.length === 1) windowSelect.selectedIndex = 1;
    } catch (error) {
      identity.className = 'alert alert-danger border mb-4';
      identity.textContent = error.message || 'Unable to load your staff profile or the open admission windows.';
    }
    form.addEventListener('submit', async function (event) {
      event.preventDefault();
      var message = document.getElementById('staffAdmissionMessage');
      var button = document.getElementById('staffAdmissionSubmit');
      message.className = 'alert d-none mb-0';
      if (!form.checkValidity()) {
        form.reportValidity();
        return;
      }
      button.disabled = true;
      try {
        var fd = new FormData(form);
        window.KingswayDocumentCamera?.syncFormData(fd, form);
        var response = unwrap(await call('/staff/my-admission-application', 'POST', fd, {}, { isFile: true, noRedirect: true }));
        message.textContent = 'Application submitted. Reference: ' + (response.ref || response.application_no || '') + '. The admissions office will contact you about the next step.';
        message.className = 'alert alert-success mb-0';
        form.reset();
        await loadApplications();
      } catch (error) {
        message.textContent = error.message || 'The application could not be submitted.';
        message.className = 'alert alert-danger mb-0';
      } finally {
        button.disabled = false;
      }
    });
    document.getElementById('saPrint').addEventListener('click', function () { window.print(); });
    await loadApplications();
  }

  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init); else init();
})();
