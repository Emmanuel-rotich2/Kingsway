/**
 * js/pages/parents/downloads.js — Downloads & Documents.
 * Report-card PDFs (immutable releases), fee-statement PDF and receipts
 * for confirmed payments. Every generated file comes back as a short-lived
 * download URL from the API.
 */
(function () {
  'use strict';
  var P = window.ParentCommon;
  var _state = { reportCards: [], transport: [], uniform: null, payments: [], resources: [] };

  function renderReportCards(el) {
    var cards = _state.reportCards;
    if (!cards.length) {
      return '<div class="alert alert-info">No report cards have been released for this learner. Released PDFs appear here.</div>';
    }
    return '<h6 class="text-muted mb-2">Released report cards</h6>' +
      '<div class="table-responsive"><table class="pp-table table-sm"><thead><tr><th>Version</th><th>Released</th><th>Download</th></tr></thead><tbody>' +
      cards.map(function (c) {
        return '<tr><td>Version ' + c.version_no + '</td><td>' + P.esc((c.released_at || '').substring(0, 10)) + '</td>' +
          '<td><a class="btn btn-outline-primary btn-sm" href="' + P.esc(c.download_url) + '" target="_blank" rel="noopener"><i class="bi bi-file-earmark-pdf me-1"></i>Open PDF</a></td></tr>';
      }).join('') + '</tbody></table></div>';
  }

  function renderTransport(el) {
    var rows = _state.transport;
    if (!rows.length) return '';
    return '<h6 class="text-muted mb-2 mt-3">Transport billing</h6>' +
      '<div class="table-responsive"><table class="pp-table table-sm"><thead><tr><th>Month</th><th>Billed</th><th>Paid</th><th>Balance</th><th>Status</th></tr></thead><tbody>' +
      rows.map(function (r) {
        var st = r.payment_status;
        var badge = st === 'paid' ? 'success' : (st === 'partial' ? 'warning' : 'danger');
        return '<tr><td>' + P.esc(r.billing_month || '') + '</td><td>KES ' + Number(r.amount_due || 0).toLocaleString() + '</td>' +
          '<td>KES ' + Number(r.amount_paid || 0).toLocaleString() + '</td><td><strong>KES ' + Number(r.balance_due || 0).toLocaleString() + '</strong></td>' +
          '<td><span class="badge bg-' + badge + '">' + P.esc(st || 'pending') + '</span></td></tr>';
      }).join('') + '</tbody></table></div>';
  }

  function renderUniform(el) {
    if (!_state.uniform) return '';
    var u = _state.uniform;
    return '<h6 class="text-muted mb-2 mt-3">Uniform store balance</h6>' +
      '<div class="row g-2"><div class="col-md-4"><div class="pp-kpi-card bg-info-subtle text-info"><div><small class="text-muted">Total billed</small><div class="fw-bold fs-6">KES ' + Number(u.total_billed || 0).toLocaleString() + '</div></div></div></div>' +
      '<div class="col-md-4"><div class="pp-kpi-card bg-success-subtle text-success"><div><small class="text-muted">Total paid</small><div class="fw-bold fs-6">KES ' + Number(u.total_paid || 0).toLocaleString() + '</div></div></div></div>' +
      '<div class="col-md-4"><div class="pp-kpi-card bg-' + (Number(u.total_balance || 0) > 0 ? 'danger' : 'success') + '-subtle text-' + (Number(u.total_balance || 0) > 0 ? 'danger' : 'success') + '"><div><small class="text-muted">Balance due</small><div class="fw-bold fs-6">KES ' + Number(u.total_balance || 0).toLocaleString() + '</div></div></div></div></div>';
  }

  function renderStatement(el) {
    return '<h6 class="text-muted mb-2 mt-3">Fee statement</h6>' +
      '<div class="d-flex align-items-center gap-2 flex-wrap">' +
      '<button class="btn btn-success btn-sm" id="btnStmtPdf"><i class="bi bi-file-earmark-pdf me-1"></i>Download fee statement (PDF)</button>' +
      '<span class="small text-muted">Generated from the accounts office template.</span></div>';
  }

  function renderReceipts(el) {
    var rows = _state.payments;
    if (!rows.length) return '';
    return '<h6 class="text-muted mb-2 mt-3">Payment receipts</h6>' +
      '<div class="table-responsive"><table class="pp-table table-sm"><thead><tr><th>Date</th><th>Method</th><th>Amount</th><th>Receipt #</th><th>Receipt</th></tr></thead><tbody>' +
      rows.map(function (p) {
        return '<tr><td>' + P.esc((p.payment_date || '').substring(0, 10)) + '</td><td>' + P.esc(p.payment_method || '') + '</td>' +
          '<td>KES ' + Number(p.amount_paid || 0).toLocaleString() + '</td><td>' + P.esc(p.receipt_no || '') + '</td>' +
          '<td><button class="btn btn-outline-primary btn-sm receipt-btn" data-payment="' + p.id + '"><i class="bi bi-file-earmark-pdf me-1"></i>Receipt</button></td></tr>';
      }).join('') + '</tbody></table></div>' +
      '<div class="alert alert-success small mt-2">Receipts are available for confirmed payments only.</div>';
  }

  var EXAM = { opener: 'Opener', mid_term: 'Mid-term', end_term: 'End-term', mock: 'Mock', kpsea: 'KPSEA', kjsea: 'KJSEA', keya: 'KEYA', internal: 'Internal', other: 'Other' };

  // Teaching materials and past papers the school published for parents.
  function renderResources() {
    var rows = _state.resources;
    if (!rows.length) return '';
    var section = function (kind, heading) {
      var list = rows.filter(function (r) { return r.kind === kind; });
      if (!list.length) return '';
      return '<h6 class="text-muted mb-2 mt-3">' + heading + '</h6><div class="table-responsive"><table class="pp-table table-sm"><thead><tr><th>Title</th><th>Learning area</th><th>' + (kind === 'past_paper' ? 'Paper' : 'Type') + '</th><th></th></tr></thead><tbody>' +
        list.map(function (r) {
          var detail = kind === 'past_paper' ? P.esc((EXAM[r.exam_type] || r.exam_type || '') + ' ' + (r.exam_year || '') + (r.term_number ? ' · Term ' + r.term_number : '')) : P.esc(r.media_kind || '');
          return '<tr><td><strong>' + P.esc(r.title) + '</strong>' + (r.description ? '<div class="small text-muted">' + P.esc(String(r.description).slice(0, 140)) + '</div>' : '') + '</td><td>' + P.esc(r.learning_area_name || 'General') + '</td><td>' + detail + '</td>' +
            '<td class="text-nowrap"><button class="btn btn-outline-primary btn-sm res-open" data-kind="' + kind + '" data-id="' + Number(r.id) + '"><i class="bi bi-box-arrow-up-right me-1"></i>Open</button>' +
            (r.has_marking_scheme ? ' <button class="btn btn-outline-secondary btn-sm res-open" data-scheme="1" data-kind="' + kind + '" data-id="' + Number(r.id) + '">Marking scheme</button>' : '') + '</td></tr>';
        }).join('') + '</tbody></table></div>';
    };
    return section('material', 'Learning materials') + section('past_paper', 'Past papers for revision');
  }

  function renderAll(el) {
    el.innerHTML = renderResources() + renderReportCards(el) + renderStatement(el) + renderReceipts(el) + renderTransport(el) + renderUniform(el) +
      '<div class="alert alert-info small mt-3"><i class="bi bi-info-circle me-2"></i>Files are short-lived download links generated on demand.</div>';
    // Statement PDF action
    var stmtBtn = document.getElementById('btnStmtPdf');
    if (stmtBtn) stmtBtn.addEventListener('click', function () {
      var btn = stmtBtn;
      btn.disabled = true;
      btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Generating…';
      P.apiFetch('/download-statement/' + _state.studentId, 'POST')
        .then(function (resp) {
          var d = resp.data !== undefined ? resp.data : resp;
          if (d.download_url) window.PrintManager?.openDocument(d.download_url, { title: 'Fee statement' });
          else P.showError(el, d.message || 'Failed to generate statement');
        })
        .catch(function (e) { P.showError(el, e.message); })
        .finally(function () { btn.disabled = false; btn.innerHTML = '<i class="bi bi-file-earmark-pdf me-1"></i>Download fee statement (PDF)'; });
    });
    // Learning resources open through short-lived links.
    el.querySelectorAll('.res-open').forEach(function (b) {
      b.addEventListener('click', function () {
        b.disabled = true;
        P.apiFetch('/learning-resource-open/' + _state.studentId + '?kind=' + encodeURIComponent(b.dataset.kind) + '&resource_id=' + Number(b.dataset.id) + (b.dataset.scheme ? '&scheme=1' : ''), 'GET')
          .then(function (resp) {
            var d = resp.data !== undefined ? resp.data : resp;
            if (d.url) window.open(d.url, '_blank', 'noopener,noreferrer');
            else P.showError(el, d.message || 'This resource could not be opened.');
          })
          .catch(function (e) { P.showError(el, e.message); })
          .finally(function () { b.disabled = false; });
      });
    });
    // Receipt actions
    el.querySelectorAll('.receipt-btn').forEach(function (b) {
      b.addEventListener('click', function () {
        var btn = b;
        btn.disabled = true;
        P.apiFetch('/download-receipt', 'POST', { student_id: _state.studentId, payment_id: Number(btn.dataset.payment) })
          .then(function (resp) {
            var d = resp.data !== undefined ? resp.data : resp;
            if (d.download_url) window.PrintManager?.openDocument(d.download_url, { title: 'Payment receipt' });
            else P.showError(el, d.message || 'Failed to generate receipt');
          })
          .catch(function (e) { P.showError(el, e.message); })
          .finally(function () { btn.disabled = false; });
      });
    });
  }

  P.childPage({
    contentId: 'ppDownloadsContent',
    defaultTab: 'downloads',
    loadTab: function (tab, child, el) {
      _state.studentId = child.id;
      // Payment history for receipts (reuses fee history).
      P.apiFetch('/student-payment-history/' + child.id, 'GET')
        .then(function (r) {
          var d = r.data !== undefined ? r.data : r;
          _state.payments = Array.isArray(d) ? d : (d.payments || []);
        })
        .catch(function () { _state.payments = []; });

      var resourcesReady = P.apiFetch('/learning-resources/' + child.id, 'GET')
        .then(function (r) { var d = r.data !== undefined ? r.data : r; _state.resources = (d && d.items) || []; })
        .catch(function () { _state.resources = []; });

      Promise.resolve(resourcesReady).then(function () { return P.apiFetch('/downloads/' + child.id, 'GET'); })
        .then(function (resp) {
          var d = resp.data !== undefined ? resp.data : resp;
          _state.reportCards = d.report_cards || [];
          _state.transport = d.transport || [];
          _state.uniform = d.uniform || null;
          renderAll(el);
        })
        .catch(function (e) { P.showError(el, e.message); });
    },
  });

  document.getElementById('btnDownloadsCsv').addEventListener('click', function () {
    var rows = _state.reportCards.map(function (c) {
      return ['Report card v' + c.version_no, (c.released_at || '').substring(0, 10)];
    });
    _state.transport.forEach(function (r) {
      rows.push(['Transport ' + (r.billing_month || ''), 'KES ' + Number(r.amount_due || 0) + ' billed / KES ' + Number(r.balance_due || 0) + ' due']);
    });
    _state.payments.forEach(function (p) {
      rows.push(['Receipt ' + (p.receipt_no || ''), 'KES ' + Number(p.amount_paid || 0) + ' on ' + (p.payment_date || '').substring(0, 10)]);
    });
    if (!rows.length) return;
    P.exportCsv('downloads.csv', P.csvFromHeaders(['Item', 'Detail'], rows));
  });

  document.getElementById('btnDownloadsPrint').addEventListener('click', function () { P.printSection(); });
})();
