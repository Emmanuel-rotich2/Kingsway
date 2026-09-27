(function () {
  'use strict';
  function unwrap(value) { return value && value.data !== undefined ? value.data : value; }
  function items(value) { var d = unwrap(value); return Array.isArray(d) ? d : (d && (d.items || d.data || d.rows)) || []; }
  function esc(value) { return String(value ?? '').replace(/[&<>"']/g, function (c) { return ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[c]); }); }
  document.addEventListener('DOMContentLoaded', async function () {
    var identity = document.getElementById('staffAdmissionIdentity');
    var grade = document.getElementById('staffAdmissionGrade');
    var windowSelect = document.getElementById('staffAdmissionWindow');
    var form = document.getElementById('staffAdmissionForm');
    try {
      var profile = unwrap(await apiCall('/staff/profile-get', 'GET')) || {};
      var name = [profile.first_name, profile.middle_name, profile.last_name].filter(Boolean).join(' ');
      identity.innerHTML = '<strong>' + esc(name || 'Staff profile') + '</strong><br><span class="text-muted">' + esc(profile.phone || '') + (profile.email ? ' · ' + esc(profile.email) : '') + '</span>';
      var options = unwrap(await apiCall('/staff/my-admission-options', 'GET')) || {};
      var grades = options.grades || [];
      grade.innerHTML = '<option value="">Select grade</option>' + grades.map(function (g) { return '<option value="' + esc(g) + '">' + esc(g) + '</option>'; }).join('');
      var windows = options.windows || [];
      windowSelect.innerHTML = '<option value="">Select an open admission window</option>' + windows.map(function (w) { return '<option value="' + Number(w.admission_window_id || 0) + '">' + esc(w.admission_window_label || 'Admission window') + '</option>'; }).join('');
    } catch (error) {
      identity.className = 'alert alert-danger border mb-4'; identity.textContent = 'Unable to load your staff profile or open admission windows.';
    }
    form.addEventListener('submit', async function (event) {
      event.preventDefault();
      var message = document.getElementById('staffAdmissionMessage'), button = document.getElementById('staffAdmissionSubmit');
      message.className = 'alert d-none mb-0'; button.disabled = true;
      try {
        var response = unwrap(await apiCall('/staff/my-admission-application', 'POST', new FormData(form), null, { isFile: true, noRedirect: true }));
        message.textContent = 'Application submitted successfully. Reference: ' + (response.ref || response.application_no || '');
        message.className = 'alert alert-success mb-0'; form.reset();
      } catch (error) { message.textContent = error.message || 'Application could not be submitted.'; message.className = 'alert alert-danger mb-0'; }
      finally { button.disabled = false; }
    });
  });
})();
