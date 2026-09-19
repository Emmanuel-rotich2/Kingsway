/**
 * js/pages/parents/documents.js — Portfolio & documents.
 */
(function () {
  'use strict';
  var P = window.ParentCommon;

  function renderPortfolio(data, el) {
    var portfolio = data.portfolio || {};
    var artifacts = data.artifacts || [];
    if (!portfolio) {
      el.innerHTML = '<div class="alert alert-info">No portfolio found for this student. Portfolios are created by the teacher.</div>';
      return;
    }
    var html = '<div class="d-flex justify-content-between align-items-center mb-2 flex-wrap gap-2">' +
      '<h6 class="mb-0 text-muted">' + P.esc(portfolio.title || 'Portfolio') + '</h6>' +
      '<div><button class="btn btn-outline-primary btn-sm me-1" id="btnPrintPortfolio"><i class="bi bi-printer me-1"></i>Print</button>' +
      '<span class="badge bg-secondary">' + P.esc(portfolio.portfolio_type || 'digital') + '</span></div></div>';
    if (portfolio.description) html += '<p class="small text-muted mb-3">' + P.esc(portfolio.description) + '</p>';
    if (portfolio.theme) html += '<p class="small"><strong>Theme:</strong> ' + P.esc(portfolio.theme) + '</p>';
    if (!artifacts.length) {
      html += '<div class="alert alert-info">No artifacts have been added yet.</div>';
    } else {
      html += '<div class="row g-2">';
      artifacts.forEach(function (a) {
        var typeIcon = a.artifact_type === 'photo' || a.artifact_type === 'video' ? 'bi-file-image' :
          a.artifact_type === 'document' ? 'bi-file-text' :
          a.artifact_type === 'project' ? 'bi-diagram-3' : 'bi-file';
        html += '<div class="col-12"><div class="card border-0 shadow-sm"><div class="card-body py-2">' +
          '<div class="d-flex justify-content-between align-items-start">' +
          '<div><i class="bi ' + typeIcon + ' me-2 text-primary"></i><strong>' + P.esc(a.artifact_title || '') + '</strong>' +
          ' <span class="badge bg-light text-muted">' + P.esc(a.artifact_type || '') + '</span></div>' +
          '<small class="text-muted">' + (a.upload_date || '').substring(0, 10) + '</small></div>';
        if (a.description) html += '<p class="small mb-1 mt-1">' + P.esc(a.description) + '</p>';
        if (a.competency_name) html += '<small class="text-muted d-block"><strong>C:</strong> ' + P.esc(a.competency_name) + '</small>';
        if (a.value_name) html += '<small class="text-muted d-block"><strong>V:</strong> ' + P.esc(a.value_name) + '</small>';
        if (a.rating) html += '<small class="text-muted d-block"><strong>Rating:</strong> ' + a.rating + '/5</small>';
        if (a.learner_reflection) html += '<div class="bg-light rounded p-2 mt-1"><small class="text-muted"><em>Student:</em> ' + P.esc(a.learner_reflection) + '</small></div>';
        if (a.teacher_feedback) html += '<div class="bg-info bg-opacity-10 rounded p-2 mt-1"><small class="text-primary"><em>Teacher:</em> ' + P.esc(a.teacher_feedback) + '</small></div>';
        if (a.file_path) html += '<a href="' + P.esc(a.file_path) + '" target="_blank" class="btn btn-outline-primary btn-sm mt-1"><i class="bi bi-eye me-1"></i>View</a>';
        html += '</div></div></div>';
      });
      html += '</div>';
    }
    el.innerHTML = html;
    var printBtn = document.getElementById('btnPrintPortfolio');
    if (printBtn) printBtn.addEventListener('click', function () {
      if (window.PrintManager && window.PrintManager.printPortfolio) {
        window.PrintManager.printPortfolio({ student_id: portfolio.student_id || portfolio.id });
      } else {
        P.printSection();
      }
    });
  }

  P.childPage({
    contentId: 'ppDocumentsContent',
    defaultTab: 'portfolio',
    loadTab: function (tab, child, el) {
      P.apiFetch('/portfolio/' + child.id, 'GET')
        .then(function (r) { renderPortfolio(r.data !== undefined ? r.data : r, el); })
        .catch(function (e) { P.showError(el, e.message); });
    },
  });
})();