/**
 * Finance approvals workbench.
 *
 * Sources are loaded independently so a failed feed stays visibly unavailable
 * instead of becoming a misleading empty list. Decisions are only sent after
 * the reviewer chooses a reason and writes a note; the server remains the
 * final authority for permission and status checks.
 */
(() => {
  'use strict';

  const PAGE_SIZE = 12;
  const collator = new Intl.Collator('en', { numeric: true, sensitivity: 'base' });

  const SOURCE_DEFS = [
    {
      key: 'expenses',
      label: 'Expenses',
      fetch: () => window.API.finance.listExpenses({ limit: 500 }),
      rows: (payload) => firstArray(payload?.expenses, payload?.data?.expenses),
      normalize: normalizeExpense,
    },
    {
      key: 'budgets',
      label: 'Budget proposals',
      fetch: () => window.API.finance.getDepartmentBudgetsProposals(),
      rows: (payload) => firstArray(payload?.proposals, payload?.budgets, payload),
      normalize: normalizeBudget,
    },
    {
      key: 'payroll',
      label: 'Payroll runs',
      fetch: () => window.API.finance.listPayrolls({ page: 1, limit: 500 }),
      rows: (payload) => firstArray(payload?.payrolls, payload?.data?.payrolls),
      normalize: normalizePayroll,
    },
  ];

  const REASONS = {
    expense: {
      approve: [
        ['supporting_document_checked', 'Supporting document checked'],
        ['within_department_allocation', 'Within department allocation'],
        ['amount_and_vendor_verified', 'Amount and vendor verified'],
        ['authorised_request', 'Request was properly authorised'],
      ],
      reject: [
        ['missing_receipt', 'Receipt or quote missing'],
        ['amount_mismatch', 'Amount does not match the document'],
        ['not_in_budget', 'No available budget allocation'],
        ['duplicate_request', 'Duplicate of another request'],
      ],
    },
    budget: {
      approve: [
        ['aligned_to_term_plan', 'Aligned to the term plan'],
        ['funds_available', 'Funds are available'],
        ['priority_confirmed', 'Priority confirmed by leadership'],
        ['breakdown_checked', 'Cost breakdown checked'],
      ],
      reject: [
        ['insufficient_funds', 'Insufficient funds'],
        ['outside_priority_plan', 'Outside the approved priority plan'],
        ['unclear_breakdown', 'Cost breakdown is unclear'],
        ['duplicate_proposal', 'Duplicate of another proposal'],
      ],
    },
    payroll: {
      approve: [
        ['payslips_checked', 'Payslips checked against the run'],
        ['statutory_deductions_confirmed', 'Statutory deductions confirmed'],
        ['attendance_period_confirmed', 'Attendance and period confirmed'],
        ['approved_staff_changes', 'Staff changes were authorised'],
      ],
      reject: [
        ['payslip_mismatch', 'Payslips do not match the run'],
        ['missing_attendance_support', 'Attendance support is missing'],
        ['incorrect_statutory_deductions', 'Statutory deductions are incorrect'],
        ['wrong_payroll_period', 'Wrong payroll period'],
      ],
    },
  };

  const state = {
    records: [],
    filteredRecords: [],
    sources: {},
    sort: { key: 'date', direction: 'descending' },
    page: 1,
    selectedUid: null,
    decisionAllowed: false,
    busy: false,
    loading: false,
    lastUpdated: null,
    searchTimer: null,
  };

  function byId(id) {
    return document.getElementById(id);
  }

  function setText(id, value) {
    const element = byId(id);
    if (element) element.textContent = String(value);
  }

  function announce(message) {
    const element = byId('faAnnouncer');
    if (element) element.textContent = message;
  }

  function notify(message, type = 'info') {
    announce(message);
    if (typeof window.showNotification === 'function') {
      window.showNotification(message, type);
    }
  }

  function esc(value) {
    return String(value ?? '')
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#39;');
  }

  function escAttr(value) {
    return esc(value);
  }

  function firstArray(...candidates) {
    return candidates.find(Array.isArray) || [];
  }

  function firstText(...candidates) {
    return candidates.find((value) => value !== null && value !== undefined && String(value).trim() !== '') || '';
  }

  function number(value, fallback = null) {
    if (value === null || value === undefined || value === '') return fallback;
    const parsed = Number(value);
    return Number.isFinite(parsed) ? parsed : fallback;
  }

  function normalizeSource(source) {
    let value = source;
    for (let depth = 0; depth < 4; depth += 1) {
      if (
        value
        && typeof value === 'object'
        && !Array.isArray(value)
        && Object.prototype.hasOwnProperty.call(value, 'data')
      ) {
        value = value.data;
        continue;
      }
      break;
    }
    return value && typeof value === 'object' ? value : {};
  }

  function parseDate(value) {
    if (!value) return null;
    const text = String(value).trim();
    const iso = text.match(/^(\d{4})-(\d{2})(?:-(\d{2}))?/);
    if (iso) {
      const date = new Date(Number(iso[1]), Number(iso[2]) - 1, Number(iso[3] || 1));
      return Number.isNaN(date.getTime()) ? null : date;
    }
    const date = new Date(text.replace(' ', 'T'));
    return Number.isNaN(date.getTime()) ? null : date;
  }

  function toDateString(value) {
    const date = parseDate(value);
    if (!date) return '';
    return [
      date.getFullYear(),
      String(date.getMonth() + 1).padStart(2, '0'),
      String(date.getDate()).padStart(2, '0'),
    ].join('-');
  }

  function formatDate(value) {
    const date = parseDate(value);
    return date
      ? date.toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' })
      : 'Date unavailable';
  }

  function formatTime(value) {
    if (!value) return '—';
    return value.toLocaleTimeString('en-GB', { hour: '2-digit', minute: '2-digit' });
  }

  function ageLabel(value) {
    const date = parseDate(value);
    if (!date) return '';
    const days = Math.floor((Date.now() - date.getTime()) / 86400000);
    if (days <= 0) return 'Today';
    if (days === 1) return '1 day ago';
    return `${days} days ago`;
  }

  function formatAmount(value, withCurrency = false) {
    const amount = number(value, null);
    if (amount === null) return '—';
    const formatted = amount.toLocaleString('en-KE', {
      minimumFractionDigits: 2,
      maximumFractionDigits: 2,
    });
    return withCurrency ? `KES ${formatted}` : formatted;
  }

  function normalizeStatus(status) {
    const value = String(status ?? '').trim().toLowerCase().replace(/[\s-]+/g, '_');
    if (['approved', 'paid', 'payment_pending', 'completed', 'processed', 'disbursed', 'active', 'closed'].includes(value)) {
      return 'approved';
    }
    if (['rejected', 'declined', 'cancelled', 'canceled', 'failed', 'returned'].includes(value)) {
      return 'rejected';
    }
    if (['pending', 'pending_approval', 'submitted', 'draft', 'under_review', 'verification', 'verifying', 'review'].includes(value)) {
      return 'pending';
    }
    return 'unknown';
  }

  function statusLabel(status) {
    return {
      pending: 'Awaiting decision',
      approved: 'Approved',
      rejected: 'Rejected',
      unknown: 'Check source',
    }[status] || 'Check source';
  }

  function typeLabel(type) {
    return {
      expense: 'Expense',
      budget: 'Budget',
      payroll: 'Payroll',
    }[type] || 'Finance';
  }

  function attachmentFor(raw) {
    return firstText(
      raw?.attachment_path,
      raw?.attachment_url,
      raw?.receipt_file,
      raw?.document_url,
      raw?.supporting_document
    );
  }

  function buildRecord(config) {
    const rawId = firstText(config.id, config.ref, `${config.sourceKey}-${config.index + 1}`);
    return {
      uid: `${config.sourceKey}-${config.index}-${rawId}`,
      type: config.type,
      sourceKey: config.sourceKey,
      sourceLabel: config.sourceLabel,
      id: config.id ?? null,
      ref: config.ref || `${config.type.slice(0, 3).toUpperCase()}-${rawId}`,
      date: config.date || '',
      requestedBy: config.requestedBy || '—',
      description: config.description || 'Finance request',
      amount: number(config.amount, null),
      status: normalizeStatus(config.status),
      rawStatus: String(config.status ?? ''),
      context: config.context || '',
      attachment: config.attachment || '',
      raw: config.raw || {},
    };
  }

  function normalizeExpense(row, index) {
    const context = [
      firstText(row.category_name, row.expense_category),
      row.vendor_name ? `Vendor: ${row.vendor_name}` : '',
      row.reference_number ? `Ref: ${row.reference_number}` : '',
      row.expense_date ? `Expense date: ${formatDate(row.expense_date)}` : '',
    ].filter(Boolean).join(' · ');

    return buildRecord({
      type: 'expense',
      sourceKey: 'expenses',
      sourceLabel: 'Expense ledger',
      index,
      id: row.id,
      ref: row.expense_number ? `EXP-${row.expense_number}` : `EXP-${row.id ?? index + 1}`,
      date: firstText(row.expense_date, row.created_at, row.updated_at),
      requestedBy: firstText(row.recorded_by_name, row.created_by ? `User #${row.created_by}` : ''),
      description: firstText(row.description, row.vendor_name, row.category_name, 'Expense request'),
      amount: row.amount,
      status: row.status,
      context,
      attachment: attachmentFor(row),
      raw: row,
    });
  }

  function normalizeBudget(row, index) {
    const context = [
      row.academic_year ? `Year ${row.academic_year}` : '',
      row.term ? `Term ${row.term}` : '',
      firstText(row.description),
      row.submitted_at ? `Submitted ${formatDate(row.submitted_at)}` : '',
    ].filter(Boolean).join(' · ');

    return buildRecord({
      type: 'budget',
      sourceKey: 'budgets',
      sourceLabel: 'Budget proposals',
      index,
      id: row.id,
      ref: `BUD-${row.id ?? index + 1}`,
      date: firstText(row.created_at, row.submitted_at, row.reviewed_at),
      requestedBy: row.created_by ? `Submitter #${row.created_by}` : '—',
      description: firstText(row.title, row.description, 'Department budget proposal'),
      amount: firstText(row.amount_requested, row.amount, row.total_amount),
      status: row.status,
      context,
      attachment: attachmentFor(row),
      raw: row,
    });
  }

  function normalizePayroll(row, index) {
    const payrollId = firstText(row.payroll_id, row.id);
    const period = firstText(row.payroll_period, row.month && row.year ? `${row.year}-${String(row.month).padStart(2, '0')}` : '');
    const context = [
      row.staff_count !== undefined ? `${row.staff_count} payslip(s)` : '',
      row.total_gross !== undefined ? `Gross ${formatAmount(row.total_gross, true)}` : '',
      period ? `Period ${period}` : '',
    ].filter(Boolean).join(' · ');

    return buildRecord({
      type: 'payroll',
      sourceKey: 'payroll',
      sourceLabel: 'Payroll runs',
      index,
      id: payrollId,
      ref: `PAY-${firstText(period, payrollId, index + 1)}`,
      date: firstText(row.created_at, period ? `${period}-01` : ''),
      requestedBy: row.created_by ? `User #${row.created_by}` : 'Payroll run',
      description: period ? `Payroll ${period}` : 'Payroll run',
      amount: firstText(row.total_net, row.total_amount, row.net_salary, row.gross_salary),
      status: row.status,
      context,
      attachment: attachmentFor(row),
      raw: row,
    });
  }

  async function loadSources() {
    const results = await Promise.all(
      SOURCE_DEFS.map(async (definition) => {
        try {
          const payload = normalizeSource(await definition.fetch());
          const rows = definition.rows(payload);
          return {
            key: definition.key,
            label: definition.label,
            state: 'ready',
            records: rows.map(definition.normalize),
            error: null,
          };
        } catch (error) {
          console.warn(`[FinanceApprovals] ${definition.label} unavailable:`, error?.message || error);
          return {
            key: definition.key,
            label: definition.label,
            state: 'error',
            records: [],
            error: error?.message || 'Source unavailable',
          };
        }
      })
    );

    state.sources = results.reduce((map, result) => {
      map[result.key] = result;
      return map;
    }, {});
    state.records = results.flatMap((result) => result.records);
    state.lastUpdated = new Date();
  }

  function getFilters() {
    return {
      search: (byId('faSearch')?.value || '').trim().toLowerCase(),
      status: byId('faStatusFilter')?.value || '',
      type: byId('faTypeFilter')?.value || '',
      dateFrom: byId('faDateFrom')?.value || '',
      dateTo: byId('faDateTo')?.value || '',
    };
  }

  function sortValue(record, key) {
    if (key === 'amount') return record.amount;
    if (key === 'date') {
      const date = parseDate(record.date);
      return date ? date.getTime() : null;
    }
    if (key === 'status') return statusLabel(record.status);
    return String(record[key] ?? '');
  }

  function sortRecords() {
    const { key, direction } = state.sort;
    const factor = direction === 'ascending' ? 1 : -1;

    state.filteredRecords.sort((a, b) => {
      const left = sortValue(a, key);
      const right = sortValue(b, key);
      if (left === null || right === null) {
        if (left === right) return 0;
        return left === null ? 1 : -1;
      }
      const comparison = typeof left === 'number' && typeof right === 'number'
        ? left - right
        : collator.compare(String(left), String(right));
      return factor * comparison;
    });
  }

  function applyFilters({ resetPage = true } = {}) {
    const filters = getFilters();
    state.filteredRecords = state.records.filter((record) => {
      if (filters.type && record.type !== filters.type) return false;
      if (filters.status && record.status !== filters.status) return false;
      if (filters.dateFrom && toDateString(record.date) < filters.dateFrom) return false;
      if (filters.dateTo && toDateString(record.date) > filters.dateTo) return false;
      if (filters.search) {
        const haystack = [
          record.ref,
          record.description,
          record.requestedBy,
          record.sourceLabel,
          typeLabel(record.type),
          record.context,
        ].join(' ').toLowerCase();
        if (!haystack.includes(filters.search)) return false;
      }
      return true;
    });

    sortRecords();
    if (resetPage) state.page = 1;
    normalizePage();
    renderAll();
  }

  function failedSources() {
    return SOURCE_DEFS
      .map((definition) => state.sources[definition.key])
      .filter((source) => source?.state === 'error' || source?.state === 'unavailable');
  }

  function normalizePage() {
    const pages = Math.max(1, Math.ceil(state.filteredRecords.length / PAGE_SIZE));
    state.page = Math.min(Math.max(1, state.page), pages);
    return pages;
  }

  function paginate() {
    const pages = normalizePage();
    const start = (state.page - 1) * PAGE_SIZE;
    return {
      pageRecords: state.filteredRecords.slice(start, start + PAGE_SIZE),
      total: state.filteredRecords.length,
      pages,
    };
  }

  function renderMeta() {
    const failed = failedSources();
    const loaded = SOURCE_DEFS.length - failed.length;
    setText('faSources', `${loaded} of ${SOURCE_DEFS.length} loaded${failed.length ? ` · ${failed.length} unavailable` : ''}`);
    setText('faUpdated', state.lastUpdated
      ? `${formatTime(state.lastUpdated)}${failed.length ? ' (partial)' : ''}`
      : '—');
    setText(
      'faScope',
      failed.length
        ? `Loaded sources only · ${failed.map((source) => source.label).join(', ')} unavailable`
        : 'Expenses, department budgets and payroll runs available to your account'
    );
  }

  function renderState() {
    const banner = byId('faState');
    if (!banner) return;
    const failed = failedSources();
    if (!failed.length) {
      banner.hidden = true;
      banner.textContent = '';
      return;
    }

    const allFailed = failed.length === SOURCE_DEFS.length;
    banner.hidden = false;
    banner.dataset.tone = 'error';
    banner.textContent = allFailed
      ? 'No finance source could be loaded. The queue is unavailable rather than empty; try Refresh.'
      : `${failed.map((source) => source.label).join(', ')} could not be loaded. Counts and totals use loaded sources only; no failed source is shown as zero.`;
  }

  function renderSummary() {
    const failed = failedSources();
    const allFailed = failed.length === SOURCE_DEFS.length;
    const pending = state.filteredRecords.filter((record) => record.status === 'pending');
    const approved = state.filteredRecords.filter((record) => record.status === 'approved');
    const rejected = state.filteredRecords.filter((record) => record.status === 'rejected');
    const amounts = pending.map((record) => record.amount).filter((amount) => amount !== null);
    const partial = failed.length ? ' · loaded sources only' : '';

    setText('faPendingCount', allFailed ? '—' : pending.length);
    setText('faPendingValue', allFailed ? '—' : formatAmount(amounts.reduce((sum, amount) => sum + amount, 0), true));
    setText('faApprovedCount', allFailed ? '—' : approved.length);
    setText('faRejectedCount', allFailed ? '—' : rejected.length);
    setText('faPendingSub', allFailed ? 'No source loaded' : `${state.records.length} request(s) loaded${partial}`);
    setText(
      'faPendingValueSub',
      allFailed
        ? 'No source loaded'
        : amounts.length === pending.length
          ? `Across ${pending.length} open request(s)${partial}`
          : `${pending.length - amounts.length} amount(s) unavailable${partial}`
    );
    setText('faApprovedSub', allFailed ? 'No source loaded' : `In the loaded set${partial}`);
    setText('faRejectedSub', allFailed ? 'No source loaded' : `In the loaded set${partial}`);
  }

  function renderAttention() {
    const container = byId('faAttention');
    if (!container) return;
    const failed = failedSources();
    const pending = state.filteredRecords.filter((record) => record.status === 'pending');
    const items = [];

    if (failed.length) {
      items.push({
        tone: 'error',
        title: `${failed.length} finance ${failed.length === 1 ? 'source' : 'sources'} unavailable`,
        detail: `${failed.map((source) => source.label).join(', ')}. Refresh before treating the figures as complete.`,
      });
    }

    if (pending.length) {
      const byType = (type) => pending.filter((record) => record.type === type).length;
      const oldest = pending
        .filter((record) => parseDate(record.date))
        .sort((a, b) => parseDate(a.date) - parseDate(b.date))[0];
      items.push({
        tone: 'wait',
        title: `${pending.length} request${pending.length === 1 ? '' : 's'} awaiting decision`,
        detail: `Expenses ${byType('expense')} · budgets ${byType('budget')} · payroll ${byType('payroll')}.${oldest ? ` Oldest: ${oldest.ref}, ${ageLabel(oldest.date)}.` : ''}`,
      });
    } else if (!failed.length) {
      items.push({
        tone: 'clear',
        title: 'No open approval requests',
        detail: 'All loaded requests already have a decision recorded.',
      });
    }

    container.innerHTML = items.length
      ? items.map((item) => `
          <article class="fa-attention-item" data-tone="${escAttr(item.tone)}">
            <strong>${esc(item.title)}</strong>
            <span>${esc(item.detail)}</span>
          </article>
        `).join('')
      : '<p class="fa-loading-note">No approval information is available yet.</p>';
  }

  function actionCell(record) {
    const allowed = canDecide() && record.status === 'pending';
    return `<button type="button" class="fa-review-button" data-fa-review="${escAttr(record.uid)}"${allowed ? ' data-year-write' : ''}>${allowed ? 'Review' : 'View'}</button>`;
  }

  function renderTable() {
    const tbody = byId('faQueueBody');
    if (!tbody) return;
    const { pageRecords, total, pages } = paginate();

    if (!pageRecords.length) {
      tbody.innerHTML = '<tr><td colspan="8" class="fa-empty-cell">No approval requests match the selected filters.</td></tr>';
    } else {
      tbody.innerHTML = pageRecords.map((record) => {
        const date = toDateString(record.date);
        return `
          <tr data-uid="${escAttr(record.uid)}">
            <th scope="row" data-label="Request" data-value="${escAttr(record.ref)}">
              <span class="fa-ref">${esc(record.ref)}</span>
              <span class="fa-source-name">${esc(record.sourceLabel)}</span>
            </th>
            <td data-label="Type" data-value="${escAttr(record.type)}">${esc(typeLabel(record.type))}</td>
            <td data-label="Requested" data-value="${escAttr(date)}">
              <time class="fa-time" datetime="${escAttr(date)}">${esc(formatDate(record.date))}</time>
              <span class="fa-age">${esc(ageLabel(record.date))}</span>
            </td>
            <td data-label="Requested by" data-value="${escAttr(record.requestedBy)}">${esc(record.requestedBy)}</td>
            <td data-label="Request detail" data-value="${escAttr(record.description)}">
              <span class="fa-request-detail" title="${escAttr(record.description)}">${esc(record.description)}</span>
            </td>
            <td class="fa-num" data-label="Amount (KES)" data-value="${record.amount ?? ''}">${esc(formatAmount(record.amount))}</td>
            <td data-label="Decision" data-value="${escAttr(statusLabel(record.status))}">
              <span class="fa-status fa-status-${escAttr(record.status)}">${esc(statusLabel(record.status))}</span>
            </td>
            <td data-label="Action">${actionCell(record)}</td>
          </tr>
        `;
      }).join('');
    }

    renderPagination(total, pages);
    const start = total ? (state.page - 1) * PAGE_SIZE + 1 : 0;
    const end = Math.min(state.page * PAGE_SIZE, total);
    setText(
      'faQueueNote',
      total
        ? `${total} request(s) after filters · showing ${start}–${end}`
        : 'No requests in the loaded sources for these filters.'
    );
  }

  function renderPagination(total, pages) {
    const container = byId('faPagination');
    if (!container) return;

    if (pages <= 1) {
      container.innerHTML = `<span class="fa-page-note">${total} request(s)</span>`;
      return;
    }

    const buttons = [`<button type="button" data-page="${Math.max(1, state.page - 1)}"${state.page === 1 ? ' disabled' : ''} aria-label="Previous page">Prev</button>`];
    const start = Math.max(1, state.page - 2);
    const end = Math.min(pages, state.page + 2);
    for (let page = start; page <= end; page += 1) {
      buttons.push(`<button type="button" data-page="${page}"${page === state.page ? ' aria-current="page"' : ''} aria-label="Page ${page}">${page}</button>`);
    }
    buttons.push(`<button type="button" data-page="${Math.min(pages, state.page + 1)}"${state.page === pages ? ' disabled' : ''} aria-label="Next page">Next</button>`);
    container.innerHTML = buttons.join('');
  }

  function renderAll() {
    renderMeta();
    renderState();
    renderSummary();
    renderAttention();
    renderTable();
  }

  function setSortIndicators() {
    const table = byId('faQueueTable');
    if (!table) return;
    table.tHead.querySelectorAll('th[data-sort-key]').forEach((header) => {
      const active = header.dataset.sortKey === state.sort.key;
      if (active) header.setAttribute('aria-sort', state.sort.direction);
      else header.removeAttribute('aria-sort');
    });
  }

  function syncSortSelect() {
    const select = byId('faSortBy');
    if (!select) return;
    const value = `${state.sort.key}:${state.sort.direction}`;
    if (Array.from(select.options).some((option) => option.value === value)) {
      select.value = value;
    }
  }

  function reorderRows(rows) {
    const tbody = byId('faQueueBody');
    if (!tbody) return;
    const apply = () => {
      if (typeof tbody.moveBefore === 'function') {
        rows.forEach((row) => tbody.moveBefore(row, null));
      } else {
        rows.forEach((row) => tbody.appendChild(row));
      }
    };
    if (typeof tbody.startViewTransition === 'function') {
      tbody.startViewTransition(apply);
      return;
    }
    apply();
  }

  function sortBy(button) {
    const header = button?.closest('th[data-sort-key]');
    if (!header) return;
    const key = header.dataset.sortKey;
    const direction = state.sort.key === key && state.sort.direction === 'ascending'
      ? 'descending'
      : 'ascending';

    state.sort = { key, direction };
    setSortIndicators();
    syncSortSelect();
    sortRecords();

    const wasFirstPage = state.page === 1;
    state.page = 1;
    normalizePage();
    const { pageRecords } = paginate();

    if (wasFirstPage && pageRecords.length) {
      const existing = new Map(
        Array.from(byId('faQueueBody')?.rows || [])
          .filter((row) => row.dataset.uid)
          .map((row) => [row.dataset.uid, row])
      );
      const ordered = pageRecords.map((record) => existing.get(record.uid)).filter(Boolean);
      if (ordered.length === pageRecords.length) {
        reorderRows(ordered);
        const { total, pages } = paginate();
        renderPagination(total, pages);
      } else {
        renderTable();
      }
    } else {
      renderTable();
    }

    announce(`Sorted by ${header.textContent.trim()}, ${direction}, ${state.filteredRecords.length} rows.`);
  }

  function findRecord(uid) {
    return state.records.find((record) => record.uid === uid) || null;
  }

  function currentRoleData() {
    const user = window.AuthContext?.getUser?.() || {};
    const ids = [
      ...(Array.isArray(user.role_ids) ? user.role_ids : []),
      ...(Array.isArray(user.roles) ? user.roles : []).map((role) => (typeof role === 'object' ? role.id || role.role_id : null)),
    ]
      .map(Number)
      .filter(Number.isFinite);
    const names = [
      user.role_name,
      user.role,
      ...(Array.isArray(user.roles) ? user.roles : []).map((role) => (typeof role === 'object' ? role.name || role.role_name : role)),
    ]
      .filter(Boolean)
      .map((value) => String(value).trim().toLowerCase().replace(/[\s-]+/g, '_'));
    return { user, ids, names };
  }

  function canDecide() {
    const { ids, names } = currentRoleData();
    if (ids.includes(10) || names.includes('accountant') || window.AuthContext?.hasRole?.('accountant')) {
      return false;
    }
    const allowedIds = [2, 3, 4, 5];
    const allowedNames = ['director', 'school_administrator', 'headteacher', 'system_administrator'];
    if (ids.some((id) => allowedIds.includes(id))) return true;
    if (names.some((name) => allowedNames.includes(name))) return true;
    if (allowedNames.some((name) => window.AuthContext?.hasRole?.(name.replace(/_/g, ' ')))) return true;
    return [
      'finance_approve',
      'finance.approve',
      'expense_approve',
      'budget_approve',
      'payroll_approve',
      'staff.payroll.approve',
    ].some((permission) => window.AuthContext?.hasPermission?.(permission));
  }

  function authAllows(name) {
    const check = window.AuthContext?.[name];
    return typeof check === 'function' ? check('finance') !== false : true;
  }

  function applyPermissions() {
    ['faExport', 'faPrint'].forEach((id) => {
      const button = byId(id);
      if (!button) return;
      const allowed = id === 'faExport' ? authAllows('canExport') : authAllows('canPrint');
      button.disabled = !allowed;
      button.title = allowed
        ? button.title
        : 'Your account does not have permission to ' + (id === 'faExport' ? 'export' : 'print') + ' finance data.';
    });
  }

  function renderReasonOptions() {
    const select = byId('faReason');
    if (!select) return;
    const record = findRecord(state.selectedUid);
    const decision = document.querySelector('input[name="faDecision"]:checked')?.value || '';
    const options = record && decision ? REASONS[record.type]?.[decision] || [] : [];
    select.innerHTML = [
      '<option value="">Choose a reason</option>',
      ...options.map(([value, label]) => `<option value="${escAttr(value)}">${esc(label)}</option>`),
    ].join('');
  }

  function updateConfirmState() {
    const confirm = byId('faConfirmDecision');
    if (!confirm) return;
    const decision = document.querySelector('input[name="faDecision"]:checked')?.value || '';
    const reason = byId('faReason')?.value || '';
    const note = (byId('faNote')?.value || '').trim();
    const noteCount = byId('faNoteCount');
    if (noteCount) noteCount.textContent = `${note.length} / 500`;
    confirm.disabled = state.busy
      || !state.decisionAllowed
      || !decision
      || !reason
      || note.length < 10;
  }

  function safeAttachment(value) {
    if (!value) return '';
    try {
      const url = new URL(value, window.location.href);
      return ['http:', 'https:'].includes(url.protocol) ? url.href : '';
    } catch (error) {
      return '';
    }
  }

  function openDecision(uid) {
    const record = findRecord(uid);
    if (!record) return;

    state.selectedUid = uid;
    state.decisionAllowed = canDecide() && record.status === 'pending';

    setText('faDialogRef', record.ref);
    setText('faDialogDescription', record.description);
    setText('faDialogSource', `${record.sourceLabel}${record.context ? ` · ${record.context}` : ''}`);
    setText('faDialogAmount', formatAmount(record.amount, true));
    setText('faDialogRequestedBy', record.requestedBy);
    setText('faDialogDate', formatDate(record.date));
    setText('faDialogType', typeLabel(record.type));
    setText('faDialogStatus', `${statusLabel(record.status)}${record.rawStatus ? ` (${record.rawStatus})` : ''}`);
    setText('faDialogContext', record.amount === null
      ? 'The amount is unavailable in the source. Confirm it before recording a decision.'
      : '');

    const attachmentList = byId('faAttachmentsList');
    const attachmentBox = byId('faAttachments');
    const href = safeAttachment(record.attachment);
    if (attachmentList && attachmentBox) {
      attachmentList.innerHTML = href
        ? `<a href="${escAttr(href)}" target="_blank" rel="noopener">Open supporting document</a>`
        : '';
      attachmentBox.hidden = !href;
    }

    const panel = byId('faDecisionPanel');
    if (panel) panel.disabled = !state.decisionAllowed;
    document.querySelectorAll('input[name="faDecision"]').forEach((input) => {
      input.checked = false;
    });
    const note = byId('faNote');
    if (note) note.value = '';
    renderReasonOptions();

    const permissionNote = byId('faPermissionNote');
    if (permissionNote) {
      permissionNote.hidden = state.decisionAllowed;
      permissionNote.textContent = record.status !== 'pending'
        ? 'This request already has a decision. The review is read-only.'
        : 'Your account can view this request but cannot record finance decisions.';
    }

    const confirm = byId('faConfirmDecision');
    if (confirm) {
      confirm.textContent = 'Record decision';
      if (state.decisionAllowed) confirm.setAttribute('data-year-write', '');
      else confirm.removeAttribute('data-year-write');
    }
    updateConfirmState();

    const dialog = byId('faDialog');
    if (dialog && !dialog.open) dialog.showModal();
  }

  function closeDecision() {
    const dialog = byId('faDialog');
    if (dialog?.open) dialog.close();
  }

  function getCurrentUserId() {
    const user = window.AuthContext?.getUser?.() || {};
    return number(user.id || user.user_id, null);
  }

  function decisionNote(reasonCode, note) {
    const record = findRecord(state.selectedUid);
    const decision = document.querySelector('input[name="faDecision"]:checked')?.value || '';
    const options = REASONS[record?.type]?.[decision] || [];
    const reasonLabel = options.find(([value]) => value === reasonCode)?.[1] || reasonCode.replace(/_/g, ' ');
    return `Reason: ${reasonLabel}\n${note}`;
  }

  async function submitDecision() {
    if (state.busy || !state.decisionAllowed) return;
    const record = findRecord(state.selectedUid);
    if (!record) return;

    const decision = document.querySelector('input[name="faDecision"]:checked')?.value || '';
    const reasonCode = byId('faReason')?.value || '';
    const note = (byId('faNote')?.value || '').trim();
    if (!decision || !reasonCode || note.length < 10) {
      updateConfirmState();
      notify('Choose a decision, a reason and a note of at least 10 characters.', 'warning');
      return;
    }

    const userId = getCurrentUserId();
    const fullNote = decisionNote(reasonCode, note);
    const confirm = byId('faConfirmDecision');
    state.busy = true;
    if (confirm) {
      confirm.disabled = true;
      confirm.textContent = 'Recording…';
    }

    try {
      if (record.type === 'expense') {
        if (decision === 'approve') {
          await window.API.finance.approveExpense(record.id, fullNote);
        } else {
          await window.API.finance.rejectExpense(record.id, fullNote);
        }
      } else if (record.type === 'budget') {
        const payload = {
          proposal_id: record.id,
          status: decision === 'approve' ? 'approved' : 'rejected',
          notes: fullNote,
          reason: reasonCode,
        };
        if (userId) payload.reviewed_by = userId;
        await window.API.finance.approveDepartmentBudget(payload);
      } else if (record.type === 'payroll') {
        const payload = { payroll_id: record.id };
        if (userId) payload.user_id = userId;
        if (decision === 'approve') {
          payload.comments = fullNote;
          payload.reason = reasonCode;
          await window.API.finance.approvePayroll(payload);
        } else {
          payload.reason = fullNote;
          await window.API.finance.rejectPayroll(payload);
        }
      } else {
        throw new Error('This request type cannot be actioned here.');
      }

      notify(`${typeLabel(record.type)} ${record.ref} ${decision === 'approve' ? 'approved' : 'rejected'}.`, 'success');
      closeDecision();
      await loadData({ preservePage: true });
    } catch (error) {
      notify(error?.message || 'The decision could not be recorded.', 'error');
    } finally {
      state.busy = false;
      updateConfirmState();
      if (confirm) confirm.textContent = 'Record decision';
    }
  }

  function visibleRecords() {
    return paginate().pageRecords;
  }

  function csvCell(value) {
    return `"${String(value ?? '').replace(/"/g, '""')}"`;
  }

  function exportCsv() {
    if (!authAllows('canExport')) {
      notify('You do not have permission to export finance data.', 'warning');
      return;
    }
    const rows = visibleRecords();
    if (!rows.length) {
      notify('There are no visible rows to export.', 'warning');
      return;
    }

    const lines = [
      ['Request', 'Type', 'Requested', 'Requested by', 'Request detail', 'Amount (KES)', 'Decision', 'Source'],
      ...rows.map((record) => [
        record.ref,
        typeLabel(record.type),
        toDateString(record.date) || 'Unavailable',
        record.requestedBy,
        record.description,
        record.amount === null ? 'Unavailable' : record.amount.toFixed(2),
        statusLabel(record.status),
        record.sourceLabel,
      ]),
    ].map((row) => row.map(csvCell).join(','));

    window.KingswayFileLifecycle?.exportText?.(
      `\uFEFF${lines.join('\r\n')}`,
      `finance_approvals_${new Date().toISOString().slice(0, 10)}.csv`,
      'text/csv;charset=utf-8;'
    );
    announce(`${rows.length} visible row(s) exported as CSV.`);
  }

  function printQueue() {
    if (!authAllows('canPrint')) {
      notify('You do not have permission to print finance data.', 'warning');
      return;
    }
    announce(`Printing ${visibleRecords().length} visible row(s).`);
    window.print();
  }

  function handlePagination(event) {
    const button = event.target.closest('button[data-page]');
    if (!button || button.disabled) return;
    const page = number(button.dataset.page, null);
    if (!page) return;
    state.page = page;
    normalizePage();
    renderTable();
    announce(`Page ${state.page} of ${normalizePage()}.`);
  }

  function bindEvents() {
    const table = byId('faQueueTable');
    if (table) {
      table.addEventListener('command', (event) => {
        if (event.command === '--sort') sortBy(event.source);
      });
      if (!('commandForElement' in HTMLButtonElement.prototype)) {
        table.tHead.querySelectorAll('button[command="--sort"]').forEach((button) => {
          button.addEventListener('click', () => sortBy(button));
        });
      }
    }

    byId('faQueueBody')?.addEventListener('click', (event) => {
      const button = event.target.closest('[data-fa-review]');
      if (button) openDecision(button.dataset.faReview);
    });

    byId('faSearch')?.addEventListener('input', () => {
      clearTimeout(state.searchTimer);
      state.searchTimer = setTimeout(() => applyFilters(), 220);
    });
    ['faStatusFilter', 'faTypeFilter', 'faDateFrom', 'faDateTo'].forEach((id) => {
      byId(id)?.addEventListener('change', () => applyFilters());
    });
    byId('faSortBy')?.addEventListener('change', (event) => {
      const [key, direction] = String(event.target.value || '').split(':');
      if (!key || !['ascending', 'descending'].includes(direction)) return;
      state.sort = { key, direction };
      setSortIndicators();
      sortRecords();
      state.page = 1;
      applyFilters({ resetPage: false });
      announce(`Sorted by ${key.replace(/_/g, ' ')}, ${direction}.`);
    });
    byId('faClearFilters')?.addEventListener('click', () => {
      ['faSearch', 'faStatusFilter', 'faTypeFilter', 'faDateFrom', 'faDateTo'].forEach((id) => {
        const element = byId(id);
        if (!element) return;
        if (element.tagName === 'SELECT') element.value = id === 'faStatusFilter' ? 'pending' : '';
        else element.value = '';
      });
      applyFilters();
      announce('Approval filters cleared.');
    });
    byId('faPagination')?.addEventListener('click', handlePagination);
    byId('faRefresh')?.addEventListener('click', () => loadData({ preservePage: true }));
    byId('faExport')?.addEventListener('click', exportCsv);
    byId('faPrint')?.addEventListener('click', printQueue);

    byId('faCloseDecision')?.addEventListener('click', closeDecision);
    byId('faCancelDecision')?.addEventListener('click', closeDecision);
    byId('faConfirmDecision')?.addEventListener('click', submitDecision);
    document.querySelectorAll('input[name="faDecision"]').forEach((input) => {
      input.addEventListener('change', () => {
        renderReasonOptions();
        updateConfirmState();
      });
    });
    byId('faReason')?.addEventListener('change', updateConfirmState);
    byId('faNote')?.addEventListener('input', updateConfirmState);
    byId('faDialog')?.addEventListener('click', (event) => {
      if (event.target === event.currentTarget) closeDecision();
    });
  }

  async function loadData({ preservePage = false } = {}) {
    const refresh = byId('faRefresh');
    if (refresh) refresh.disabled = true;
    state.loading = true;
    const tbody = byId('faQueueBody');
    if (tbody && state.sources.expenses?.state !== 'ready') {
      tbody.innerHTML = '<tr><td colspan="8" class="fa-empty-cell">Loading approval requests…</td></tr>';
    }
    renderTable();

    await loadSources();
    state.loading = false;
    applyFilters({ resetPage: !preservePage });
    if (refresh) refresh.disabled = false;
    announce(`${state.records.length} approval request(s) loaded from ${SOURCE_DEFS.length - failedSources().length} source(s).`);
  }

  function canLoadSources() {
    if (window.AuthContext?.isAuthenticated?.() === false) return false;
    const user = currentRoleData().user;
    const roleIds = currentRoleData().ids;
    if (roleIds.includes(10)) return false;
    return Boolean(
      user?.id
      || user?.user_id
      || window.AuthContext?.hasPermission?.('finance.view')
      || window.AuthContext?.hasPermission?.('finance.approve')
      || canDecide()
    );
  }

  async function init() {
    await window.AuthContext?.ready?.();
    if (window.AuthContext?.isAuthenticated?.() === false) {
      window.location.href = `${window.APP_BASE || ''}/index.php`;
      return;
    }
    bindEvents();
    applyPermissions();
    setSortIndicators();

    if (canLoadSources()) {
      await loadData();
      return;
    }

    state.sources = SOURCE_DEFS.reduce((map, definition) => {
      map[definition.key] = {
        key: definition.key,
        label: definition.label,
        state: 'unavailable',
        records: [],
        error: 'Not exposed to this account',
      };
      return map;
    }, {});
    state.lastUpdated = new Date();
    applyFilters();
    announce('Finance approval sources are not available to this account.');
  }

  document.addEventListener('DOMContentLoaded', () => init());

  window.FinanceApprovalsController = {
    init,
    loadData,
    viewRequest: openDecision,
    exportCsv,
    printQueue,
  };
})();
