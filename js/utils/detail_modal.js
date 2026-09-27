/* Shared profile header for application and learner detail modals. */
(function () {
  'use strict';

  function escapeHtml(value) {
    var node = document.createElement('div');
    node.textContent = String(value == null ? '' : value);
    return node.innerHTML;
  }

  function initials(name) {
    return String(name || '?').trim().split(/\s+/).slice(0, 2).map(function (part) {
      return part.charAt(0).toUpperCase();
    }).join('') || '?';
  }

  function normalizePhotoUrl(value) {
    return window.KingswayFileLifecycle?.resolveUrl?.(value) || window.KingswayFileLifecycle?.avatarUrl?.() || '';
  }

  function profileHeader(application, photoUrl, options) {
    var app = application || {};
    var name = app.applicant_name || app.name || 'Applicant';
    var photo = normalizePhotoUrl(photoUrl || app.passport_photo_url);
    var photoHtml = photo && !/^\d+$/.test(photo)
      ? '<img src="' + escapeHtml(photo) + '" alt="Passport photo of ' + escapeHtml(name) + '" class="kw-detail-profile-photo" onerror="this.onerror=null;this.src=\'\';this.classList.add(\'d-none\');this.nextElementSibling.classList.remove(\'d-none\');">'
      : '';
    var fallbackClass = photoHtml ? 'kw-detail-profile-fallback d-none' : 'kw-detail-profile-fallback';
    var status = app.status || app.current_stage || app.stage || 'Application';
    var secondary = [app.application_no, app.grade_applying_for || app.grade, app.gender].filter(Boolean).join(' · ');
    var extra = options && options.extra ? options.extra : '';
    return '<div class="kw-detail-profile-header mb-3">' +
      '<div class="kw-detail-profile-avatar">' + photoHtml + '<span class="' + fallbackClass + '">' + escapeHtml(initials(name)) + '</span></div>' +
      '<div class="kw-detail-profile-copy"><div class="kw-detail-eyebrow">Applicant profile</div><h4>' + escapeHtml(name) + '</h4><div class="kw-detail-profile-meta">' + escapeHtml(secondary || 'Admission application') + '</div><span class="badge bg-success-subtle text-success-emphasis mt-2">' + escapeHtml(status) + '</span>' + extra + '</div>' +
      '</div>';
  }

  window.KingswayDetailModal = { profileHeader: profileHeader, escapeHtml: escapeHtml };
}());
