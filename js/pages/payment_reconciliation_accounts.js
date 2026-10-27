/**
 * Injects the financial-account selector the reconciliation page needs.
 *
 * This file only supplies markup: it never patches window.API. The page
 * controller reads #routeFinancialAccount and #routingFinancialAccount and
 * sends financial_account_id itself, so a failed load is reported by name
 * instead of silently dropping the account from an allocation.
 */
document.addEventListener('DOMContentLoaded', async () => {
  const controller = window.paymentReconciliationController;
  if (!controller) return;
  const response = await window.callAPI('/finance/financial-accounts', 'GET').catch(() => null);
  const accounts = ((response?.data || response)?.accounts || []);
  const options = accounts.map(a => '<option value="' + Number(a.id) + '">' + controller.esc(a.account_name) + ' · ' + controller.esc(a.account_identifier) + '</option>').join('');

  const routeForm = document.getElementById('collectionRouteForm');
  if (routeForm) {
    const label = document.createElement('label');
    label.className = 'col-md-3';
    label.setAttribute('for', 'routeFinancialAccount');
    const span = document.createElement('span');
    span.className = 'small text-muted';
    span.textContent = 'Mapped financial account';
    const select = document.createElement('select');
    select.className = 'form-select';
    select.id = 'routeFinancialAccount';
    select.required = true;
    select.innerHTML = '<option value="">Select account</option>' + options;
    label.appendChild(span);
    label.appendChild(select);
    routeForm.insertBefore(label, routeForm.lastElementChild);
  }

  const modal = document.querySelector('#routingResolveModal .modal-body');
  if (modal) {
    const label = document.createElement('label');
    label.className = 'form-label';
    label.setAttribute('for', 'routingFinancialAccount');
    label.textContent = 'Receiving financial account';
    const select = document.createElement('select');
    select.className = 'form-select mb-3';
    select.id = 'routingFinancialAccount';
    select.required = true;
    select.innerHTML = '<option value="">Select account</option>' + options;
    label.appendChild(select);
    modal.appendChild(label);
  }
});
