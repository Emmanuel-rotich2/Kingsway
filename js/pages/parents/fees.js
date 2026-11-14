/**
 * js/pages/parents/fees.js — Fees & Payments (fees, payments, statement tabs).
 */
(function () {
  'use strict';
  var P = window.ParentCommon;

  function renderSummary(data, child) {
    var totalBal = parseFloat(data.total_balance || 0);
    var badge = document.getElementById('ppBalanceBadge');
    if (badge) badge.innerHTML = '<span class="badge bg-' + (totalBal <= 0 ? 'success' : 'danger') + ' px-3 py-2">' +
      (totalBal < 0 ? 'KES ' + Math.abs(totalBal).toLocaleString() + ' in credit' : (totalBal === 0 ? 'Cleared' : 'KES ' + totalBal.toLocaleString() + ' Due')) + '</span>';
    var cards = document.getElementById('ppFeeSummaryCards');
    if (!cards) return;
    var rows = data.per_term || [];
    var cur = rows[0] || {};
    var bf = data.brought_forward || {};
    var balanceNote = data.balance_year
      ? '<div class="col-12 small text-muted">Balance for ' + P.esc(data.balance_year) +
        (bf.arrears > 0 ? ', including KES ' + Number(bf.arrears).toLocaleString() + ' brought forward from ' + P.esc(bf.from_year || 'the previous year') : '') +
        (bf.credit > 0 ? ', after KES ' + Number(bf.credit).toLocaleString() + ' credit brought forward from ' + P.esc(bf.from_year || 'the previous year') : '') + '.</div>'
      : '';
    cards.innerHTML = [
      ['Total Outstanding', 'KES ' + totalBal.toLocaleString(), totalBal > 0 ? 'danger' : 'success'],
      ['Current Term Due', 'KES ' + Number(cur.total_due || 0).toLocaleString(), 'primary'],
      ['Current Term Paid', 'KES ' + Number(cur.total_paid || 0).toLocaleString(), 'info'],
    ].map(function (k) {
      return '<div class="col-md-4"><div class="pp-kpi-card bg-' + k[2] + '-subtle text-' + k[2] + '"><div><div class="small text-muted">' + k[0] + '</div><div class="fw-bold fs-6">' + k[1] + '</div></div></div></div>';
    }).join('') + balanceNote;
  }

  function renderFeeHistory(data) {
    var years = data.academic_years || data || [];
    var relief = data.financial_relief;
    var reliefHtml = relief ? '<div class="alert alert-warning small"><i class="bi bi-shield-check me-1"></i><strong>Approved financial relief:</strong> ' +
      (relief.registration_fee_waived ? 'Registration fee waived. ' : '') +
      (relief.school_fee_waived ? 'School-fee relief: ' + (relief.school_fee_waiver_type || 'approved') + '. ' : '') +
      (relief.school_fee_waived_amount > 0 ? 'School-fee waiver applied: KES ' + Number(relief.school_fee_waived_amount).toLocaleString() + '. ' : '') +
      (relief.reason ? 'Reason: ' + P.esc(relief.reason) : '') + '</div>' : '';
    if (!years.length) return reliefHtml + '<div class="alert alert-info">No fee history found.</div>';
    return reliefHtml + years.map(function (yr) {
      var bf = yr.brought_forward || {};
      var bfHtml = (bf.arrears > 0 || bf.credit > 0)
        ? '<div class="alert alert-light border small mb-3"><strong>Brought forward from ' + P.esc(bf.from_year || 'the previous year') + ':</strong> ' +
          (bf.arrears > 0 ? 'arrears KES ' + Number(bf.arrears).toLocaleString() + ' (added to the first term). ' : '') +
          (bf.credit > 0 ? 'credit KES ' + Number(bf.credit).toLocaleString() + ' (an overpayment, deducted from the first term). ' : '') + '</div>'
        : '';
      var closing = yr.summary ? Number(yr.summary.closing_balance || 0) : null;
      var closingHtml = closing === null ? '' : '<div class="text-end fw-bold">' +
        (closing < 0 ? 'In credit: KES ' + Math.abs(closing).toLocaleString() : 'Balance for ' + P.esc(yr.year) + ': KES ' + closing.toLocaleString()) + '</div>';
      return '<div class="card mb-3 border-0 shadow-sm"><div class="card-header bg-success text-white fw-bold">Academic Year ' + yr.year + '</div><div class="card-body">' + bfHtml +
        (yr.terms || []).map(function (term) {
          var rows = (term.obligations || []).map(function (o) {
            var sc = o.payment_status === 'paid' ? 'success' : (o.payment_status === 'partial' ? 'warning' : 'danger');
            return '<tr><td>' + P.esc(o.fee_type_name || '') + '</td><td>KES ' + Number(o.amount_due || 0).toLocaleString() + '</td><td>KES ' + Number(o.amount_paid || 0).toLocaleString() + '</td><td>KES ' + Number(o.amount_waived || 0).toLocaleString() + '</td><td><strong>KES ' + Number(o.balance || 0).toLocaleString() + '</strong></td><td><span class="badge bg-' + (o.payment_status === 'waived' ? 'secondary' : sc) + '">' + P.esc(o.payment_status || 'pending') + '</span></td></tr>';
          }).join('');
          if (term.arrears_brought_forward > 0) {
            rows = '<tr class="table-warning"><td>Arrears brought forward</td><td>KES ' + Number(term.arrears_brought_forward).toLocaleString() + '</td><td colspan="4"></td></tr>' + rows;
          }
          if (term.credit_brought_forward > 0) {
            rows += '<tr class="table-info"><td>Credit brought forward</td><td colspan="2"></td><td>KES ' + Number(term.credit_brought_forward).toLocaleString() + '</td><td colspan="2"></td></tr>';
          }
          var payBtn = term.balance > 0 ? '<button class="btn btn-sm btn-success pay-now-btn" data-amount="' + term.balance + '"><i class="bi bi-phone me-1"></i>Pay Now</button>' : '';
          return '<h6 class="text-muted mb-2">' + P.esc(term.term_name || '') + '</h6>' +
            '<div class="table-responsive mb-3"><table class="pp-table table-sm"><thead><tr><th>Fee Type</th><th>Billed</th><th>Paid</th><th>Waived</th><th>Balance</th><th>Status</th></tr></thead><tbody>' + rows + '</tbody>' +
            '<tfoot class="fw-bold"><tr><td>Total</td><td>KES ' + (Number(term.total_due || 0) + Number(term.arrears_brought_forward || 0)).toLocaleString() + '</td><td>KES ' + Number(term.total_paid || 0).toLocaleString() + '</td><td>KES ' + Number(term.total_waived || 0).toLocaleString() + '</td><td>' +
            (Number(term.balance || 0) < 0 ? 'KES ' + Math.abs(Number(term.balance)).toLocaleString() + ' CR' : 'KES ' + Number(term.balance || 0).toLocaleString()) + '</td><td>' + payBtn + '</td></tr></tfoot></table></div>';
        }).join('') + closingHtml + '</div></div>';
    }).join('');
  }

  function renderPayments(payments) {
    if (!payments || !payments.length) return '<div class="alert alert-info">No payment records found.</div>';
    var rows = payments.map(function (p) {
      return '<tr><td>' + (p.payment_date || '').substring(0, 10) + '</td><td><span class="badge bg-secondary">' + P.esc(p.payment_method || '') + '</span></td><td>KES ' + Number(p.amount_paid || 0).toLocaleString() + '</td><td>' + P.esc(p.receipt_no || '') + '</td><td>' + P.esc(p.reference_no || '') + '</td><td>' + P.esc(p.term_name || '') + '</td></tr>';
    }).join('');
    return '<div class="table-responsive"><table class="pp-table table-sm"><thead><tr><th>Date</th><th>Method</th><th>Amount</th><th>Receipt #</th><th>Reference</th><th>Term</th></tr></thead><tbody>' + rows + '</tbody></table></div>';
  }

  // The statement is the accounts office's PDF (brought-forward arrears or
  // credit, each term, payments and a closing balance that adds up), not a
  // page assembled in the browser.
  function renderStatementView(studentId, el) {
    el.innerHTML = '<div class="text-center py-3"><p class="text-muted">Download the official fee statement for this learner.</p>' +
      '<button class="btn btn-success rounded-pill" id="btnGenStmt"><i class="bi bi-file-earmark-pdf me-2"></i>Download Statement (PDF)</button></div>';
    document.getElementById('btnGenStmt').addEventListener('click', function () {
      var btn = this;
      btn.disabled = true;
      btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Generating…';
      P.apiFetch('/download-statement/' + studentId, 'POST')
        .then(function (resp) {
          var d = resp.data !== undefined ? resp.data : resp;
          if (d.download_url) window.PrintManager?.openDocument(d.download_url, { title: 'Fee statement' });
          else P.showError(el, d.message || 'Failed to generate statement');
        })
        .catch(function (e) { P.showError(el, 'Failed: ' + e.message); })
        .finally(function () { btn.disabled = false; btn.innerHTML = '<i class="bi bi-file-earmark-pdf me-2"></i>Download Statement (PDF)'; });
    });
  }

  var _childId = null;
  var _child = null;
  var _tab = null;
  var _el = null;

  P.childPage({
    contentId: 'ppFeesContent',
    defaultTab: 'fees',
    loadTab: function (tab, child, el) {
      _childId = child.id;
      _child = child;
      _tab = tab;
      _el = el;
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

  // Pay Now button wiring (fees entries open the payment modal for fees only)
  document.addEventListener('click', function (e) {
    var btn = e.target.closest('.pay-now-btn');
    if (!btn) return;
    var amt = btn.dataset.amount || '';
    if (typeof P.openMpesaModal === 'function') {
      var children = [];
      try { children = JSON.parse(sessionStorage.getItem('pp_children') || '[]'); } catch (_) {}
      P.openMpesaModal({ children: children, studentId: _childId, purpose: 'fees', amount: amt });
    }
  });

  var sidebarPay = document.getElementById('ppPayNow');
  if (sidebarPay) sidebarPay.addEventListener('click', function () {
    var children = [];
    try { children = JSON.parse(sessionStorage.getItem('pp_children') || '[]'); } catch (_) {}
    P.openMpesaModal({ children: children, studentId: _childId, purpose: 'fees' });
  });

  // Realtime: when a verified payment lands for this child (published by the
  // server straight into this guardian's family:<childId> channel), refresh
  // the open fee view in place — balance, obligations and payment history
  // update without a reload. The dispatch layer already routed the descriptor;
  // this re-renders the visible tab from fresh API reads.
  window.addEventListener('kingsway:data-mutated', function (event) {
    if (!_childId || !_child || !_el || !_tab) return;
    var detail = event.detail || {};
    var targets = (detail.targets || []).map(String);
    var isFamilyFeeChange = detail.scope === 'family:' + _childId
      || targets.indexOf('fees') !== -1
      || targets.indexOf('payments') !== -1
      || targets.indexOf('finance') !== -1;
    if (!isFamilyFeeChange) return;
    // Only react while this page's fee content is actually on screen.
    if (!document.getElementById('ppFeesContent')) return;
    P.apiFetch('/fee-balance/' + _childId, 'GET')
      .then(function (resp) { renderSummary(resp.data !== undefined ? resp.data : resp, _child); })
      .catch(function () {});
    if (_tab === 'fees') {
      P.apiFetch('/student-fees/' + _childId, 'GET')
        .then(function (r) { _el.innerHTML = renderFeeHistory(r.data !== undefined ? r.data : r); })
        .catch(function () {});
    } else if (_tab === 'payments') {
      P.apiFetch('/student-payment-history/' + _childId, 'GET')
        .then(function (r) { _el.innerHTML = renderPayments(r.data !== undefined ? r.data : r); })
        .catch(function () {});
    }
  });
})();
