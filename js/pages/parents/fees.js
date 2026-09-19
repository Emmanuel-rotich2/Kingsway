/**
 * js/pages/parents/fees.js — Fees & Payments (fees, payments, statement tabs).
 */
(function () {
  'use strict';
  var P = window.ParentCommon;

  function renderSummary(data, child) {
    var totalBal = parseFloat(data.total_balance || 0);
    var badge = document.getElementById('ppBalanceBadge');
    if (badge) badge.innerHTML = '<span class="badge bg-' + (totalBal <= 0 ? 'success' : 'danger') + ' px-3 py-2">' + (totalBal <= 0 ? 'Cleared' : 'KES ' + totalBal.toLocaleString() + ' Due') + '</span>';
    var cards = document.getElementById('ppFeeSummaryCards');
    if (!cards) return;
    var rows = data.per_term || [];
    var cur = rows[0] || {};
    cards.innerHTML = [
      ['Total Outstanding', 'KES ' + totalBal.toLocaleString(), totalBal > 0 ? 'danger' : 'success'],
      ['Current Term Due', 'KES ' + Number(cur.total_due || 0).toLocaleString(), 'primary'],
      ['Current Term Paid', 'KES ' + Number(cur.total_paid || 0).toLocaleString(), 'info'],
    ].map(function (k) {
      return '<div class="col-md-4"><div class="pp-kpi-card bg-' + k[2] + '-subtle text-' + k[2] + '"><div><div class="small text-muted">' + k[0] + '</div><div class="fw-bold fs-6">' + k[1] + '</div></div></div></div>';
    }).join('');
  }

  function renderFeeHistory(data) {
    var years = data.academic_years || data || [];
    if (!years.length) return '<div class="alert alert-info">No fee history found.</div>';
    return years.map(function (yr) {
      return '<div class="card mb-3 border-0 shadow-sm"><div class="card-header bg-success text-white fw-bold">Academic Year ' + yr.year + '</div><div class="card-body">' +
        (yr.terms || []).map(function (term) {
          var rows = (term.obligations || []).map(function (o) {
            var sc = o.payment_status === 'paid' ? 'success' : (o.payment_status === 'partial' ? 'warning' : 'danger');
            return '<tr><td>' + P.esc(o.fee_type_name || '') + '</td><td>KES ' + Number(o.amount_due || 0).toLocaleString() + '</td><td>KES ' + Number(o.amount_paid || 0).toLocaleString() + '</td><td><strong>KES ' + Number(o.balance || 0).toLocaleString() + '</strong></td><td><span class="badge bg-' + sc + '">' + P.esc(o.payment_status || 'pending') + '</span></td></tr>';
          }).join('');
          var payBtn = term.balance > 0 ? '<button class="btn btn-sm btn-success pay-now-btn" data-amount="' + term.balance + '"><i class="bi bi-phone me-1"></i>Pay Now</button>' : '';
          return '<h6 class="text-muted mb-2">' + P.esc(term.term_name || '') + '</h6>' +
            '<div class="table-responsive mb-3"><table class="pp-table table-sm"><thead><tr><th>Fee Type</th><th>Billed</th><th>Paid</th><th>Balance</th><th>Status</th></tr></thead><tbody>' + rows + '</tbody>' +
            '<tfoot class="fw-bold"><tr><td>Total</td><td>KES ' + Number(term.total_due || 0).toLocaleString() + '</td><td>KES ' + Number(term.total_paid || 0).toLocaleString() + '</td><td>KES ' + Number(term.balance || 0).toLocaleString() + '</td><td>' + payBtn + '</td></tr></tfoot></table></div>';
        }).join('') + '</div></div>';
    }).join('');
  }

  function renderPayments(payments) {
    if (!payments || !payments.length) return '<div class="alert alert-info">No payment records found.</div>';
    var rows = payments.map(function (p) {
      return '<tr><td>' + (p.payment_date || '').substring(0, 10) + '</td><td><span class="badge bg-secondary">' + P.esc(p.payment_method || '') + '</span></td><td>KES ' + Number(p.amount_paid || 0).toLocaleString() + '</td><td>' + P.esc(p.receipt_no || '') + '</td><td>' + P.esc(p.reference_no || '') + '</td><td>' + P.esc(p.term_name || '') + '</td></tr>';
    }).join('');
    return '<div class="table-responsive"><table class="pp-table table-sm"><thead><tr><th>Date</th><th>Method</th><th>Amount</th><th>Receipt #</th><th>Reference</th><th>Term</th></tr></thead><tbody>' + rows + '</tbody></table></div>';
  }

  function renderStatementView(studentId, el) {
    el.innerHTML = '<div class="text-center py-3"><p class="text-muted">Generate a printable fee statement for this student.</p>' +
      '<button class="btn btn-success rounded-pill" id="btnGenStmt"><i class="bi bi-file-earmark-pdf me-2"></i>Generate Statement</button></div>';
    document.getElementById('btnGenStmt').addEventListener('click', function () {
      var btn = this;
      btn.disabled = true;
      btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Loading...';
      P.apiFetch('/student-statement/' + studentId, 'GET')
        .then(function (resp) {
          var d = resp.data !== undefined ? resp.data : resp;
          if (window.PrintManager && window.PrintManager.printHtml) {
            window.PrintManager.printHtml(buildStatementHTML(d), { title: 'Fee Statement' });
          }
        })
        .catch(function (e) { P.showError(el, 'Failed: ' + e.message); })
        .finally(function () { btn.disabled = false; btn.innerHTML = '<i class="bi bi-file-earmark-pdf me-2"></i>Generate Statement'; });
    });
  }

  function buildStatementHTML(data) {
    var s = data.student || {};
    var fees = (data.fees && data.fees.academic_years) || [];
    var pmts = data.payments || [];
    var feeRows = fees.map(function (yr) {
      return '<h5>Academic Year ' + yr.year + '</h5>' +
        (yr.terms || []).map(function (t) {
          return '<p><strong>' + P.esc(t.term_name || '') + '</strong></p>' +
            '<table border="1" cellpadding="4" style="border-collapse:collapse;width:100%"><thead><tr><th>Fee Type</th><th>Amount Due</th><th>Paid</th><th>Balance</th></tr></thead><tbody>' +
            (t.obligations || []).map(function (o) {
              return '<tr><td>' + P.esc(o.fee_type_name || '') + '</td><td>KES ' + Number(o.amount_due || 0).toLocaleString() + '</td><td>KES ' + Number(o.amount_paid || 0).toLocaleString() + '</td><td>KES ' + Number(o.balance || 0).toLocaleString() + '</td></tr>';
            }).join('') + '</tbody></table>';
        }).join('');
    }).join('');
    var pmtRows = pmts.map(function (p) {
      return '<tr><td>' + (p.payment_date || '').substring(0, 10) + '</td><td>' + P.esc(p.payment_method || '') + '</td><td>KES ' + Number(p.amount_paid || 0).toLocaleString() + '</td><td>' + P.esc(p.receipt_no || '') + '</td></tr>';
    }).join('');
    return '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Fee Statement</title>' +
      '<link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css" rel="stylesheet"></head>' +
      '<body class="p-4"><h3 class="text-center">Kingsway Preparatory School</h3><h5 class="text-center text-muted">Fee Statement</h5><hr>' +
      '<p><strong>Student:</strong> ' + P.esc(s.first_name + ' ' + s.last_name) + ' &nbsp; <strong>Adm No:</strong> ' + P.esc(s.admission_no || '') + ' &nbsp; <strong>Class:</strong> ' + P.esc(s.class_name || '') + '</p>' +
      '<p><strong>Generated:</strong> ' + P.esc(data.generated_at || '') + '</p><hr>' + feeRows +
      (pmtRows ? '<h5 class="mt-4">Payment History</h5><table border="1" cellpadding="4" style="border-collapse:collapse;width:100%"><thead><tr><th>Date</th><th>Method</th><th>Amount</th><th>Receipt #</th></tr></thead><tbody>' + pmtRows + '</tbody></table>' : '') +
      '</body></html>';
  }

  var _childId = null;

  P.childPage({
    contentId: 'ppFeesContent',
    defaultTab: 'fees',
    loadTab: function (tab, child, el) {
      _childId = child.id;
      P.apiFetch('/fee-balance/' + child.id, 'GET')
        .then(function (resp) { renderSummary(resp.data !== undefined ? resp.data : resp, child); })
        .catch(function () {});
      if (tab === 'fees') {
        P.apiFetch('/student-fees/' + child.id, 'GET')
          .then(function (r) { el.innerHTML = renderFeeHistory(r.data !== undefined ? r.data : r); })
          .catch(function (e) { P.showError(el, e.message); });
      } else if (tab === 'payments') {
        P.apiFetch('/student-payment-history/' + child.id, 'GET')
          .then(function (r) { el.innerHTML = renderPayments(r.data !== undefined ? r.data : r); })
          .catch(function (e) { P.showError(el, e.message); });
      } else if (tab === 'statement') {
        renderStatementView(child.id, el);
      }
    },
  });

  // Pay Now button wiring
  document.addEventListener('click', function (e) {
    var btn = e.target.closest('.pay-now-btn');
    if (!btn) return;
    var amt = btn.dataset.amount || '';
    if (typeof P.openMpesaModal === 'function') {
      var children = [];
      try { children = JSON.parse(sessionStorage.getItem('pp_children') || '[]'); } catch (_) {}
      P.openMpesaModal(children, amt, _childId);
    }
  });

  var sidebarPay = document.getElementById('ppPayNow');
  if (sidebarPay) sidebarPay.addEventListener('click', function () {
    var children = [];
    try { children = JSON.parse(sessionStorage.getItem('pp_children') || '[]'); } catch (_) {}
    P.openMpesaModal(children, '', _childId);
  });
})();