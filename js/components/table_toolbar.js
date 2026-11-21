/**
 * Hard Rule 7 for every page: each data table can be downloaded as CSV and
 * printed.
 *
 * Pages with their own export/print controls keep them; this only adds a
 * small "CSV / Print" bar above data tables in the page content that have
 * none nearby. It reads the table as shown at click time, so filters and
 * paging already applied on screen are what gets exported. Print uses the
 * school letterhead through PrintManager.printTable (A4, landscape when the
 * table is wide); without PrintManager it falls back to window.print().
 *
 * Opt out with data-no-export on the table or an ancestor. Gate by a
 * permission module with data-export-module (AuthContext.canExport/canPrint).
 */
(function (window, document) {
  'use strict';

  const ROOT_SELECTOR = '#main-content-segment, #main-content-area';
  const SKIP_ANCESTORS = '.modal, [data-no-export], .kw-export-pane, .kw-table-toolbar, nav, header';
  const WIDE_COLUMNS = 8;
  const enhanced = new WeakSet();

  function cellText(cell) {
    const field = cell.querySelector('input:not([type=hidden]):not([type=checkbox]):not([type=radio]), select, textarea');
    if (field) {
      if (field.tagName === 'SELECT') return (field.selectedOptions[0]?.textContent || '').trim();
      return String(field.value || '').trim();
    }
    const clone = cell.cloneNode(true);
    clone.querySelectorAll('button, .btn, .dropdown-menu, .visually-hidden, [aria-hidden="true"], script, style').forEach((n) => n.remove());
    // Join text nodes with a space so adjacent badges/lines don't run together.
    const parts = [];
    const walker = document.createTreeWalker(clone, NodeFilter.SHOW_TEXT);
    while (walker.nextNode()) parts.push(walker.currentNode.nodeValue);
    return parts.join(' ').replace(/\s+/g, ' ').trim();
  }

  function isActionColumn(label) {
    return /^(actions?|options?|manage|)$/i.test(label.trim());
  }

  function readTable(table) {
    const headRow = table.tHead?.rows[table.tHead.rows.length - 1] || table.querySelector('tr');
    const headers = headRow ? Array.from(headRow.cells).map(cellText) : [];
    const keep = headers.map((label) => !isActionColumn(label));
    const rows = [];
    Array.from(table.tBodies).forEach((body) => {
      Array.from(body.rows).forEach((row) => {
        if (row.offsetParent === null && row.getClientRects().length === 0) return; // hidden by a filter
        const cells = Array.from(row.cells);
        // Placeholder rows ("No records", "Loading…") span the whole table.
        if (cells.length === 1 && headers.length > 1) return;
        rows.push(cells.map(cellText).filter((_, i) => keep[i] !== false));
      });
    });
    return { headers: headers.filter((_, i) => keep[i]), rows };
  }

  function csvValue(value) {
    const text = String(value ?? '');
    // Leading = + - @ would run as a formula in a spreadsheet.
    const safe = /^[=+\-@]/.test(text) && !/^-?\d/.test(text) ? `'${text}` : text;
    return /[",\r\n]/.test(safe) ? `"${safe.replace(/"/g, '""')}"` : safe;
  }

  function slug(text) {
    return String(text).toLowerCase().replace(/[^a-z0-9]+/g, '_').replace(/^_|_$/g, '') || 'table';
  }

  function notify(message, type) {
    if (typeof window.showNotification === 'function') window.showNotification(message, type);
  }

  function moduleFor(table) {
    return table.closest('[data-export-module]')?.dataset.exportModule || '';
  }

  function allowed(kind, table) {
    const auth = window.AuthContext;
    const module = moduleFor(table);
    const check = kind === 'export' ? auth?.canExport : auth?.canPrint;
    return typeof check !== 'function' || !module || check.call(auth, module);
  }

  function textOf(el) {
    return el ? cellText(el).slice(0, 80) : '';
  }

  /** First real heading in scope: words, not a KPI figure or a placeholder. */
  function headingIn(scope) {
    for (const el of scope.querySelectorAll('h1, .page-title, h2, h3, h4, h5, .card-title')) {
      if (el.closest('.modal, .kw-table-toolbar')) continue;
      const text = textOf(el);
      if ((text.match(/[A-Za-z]/g) || []).length < 4) continue;
      if (/^(loading|kes\b|ksh\b|--|\d)/i.test(text)) continue;
      return text;
    }
    return '';
  }

  function titleFor(table) {
    if (table.dataset.exportTitle) return table.dataset.exportTitle;
    const caption = table.caption ? textOf(table.caption) : '';
    if (caption) return caption;
    // Only the card's own header names the table; headings deeper in the card
    // usually label filter panels.
    const card = table.closest('.card');
    const header = card?.querySelector(':scope > .card-header');
    const cardTitle = (header && (headingIn(header) || textOf(header)))
      || (card ? textOf(card.querySelector(':scope > .card-body > .card-title, :scope > .card-body > h5, :scope > .card-body > h4')) : '');
    const root = table.closest(ROOT_SELECTOR) || document;
    const page = headingIn(root);
    // Several tables: the card title tells them apart. One table: the page name.
    if (cardTitle && root.querySelectorAll('table').length > 1) return cardTitle;
    return page || cardTitle || (document.title || 'Table').slice(0, 80);
  }

  const EXPORT_WORDS = /\b(export|csv|excel|xlsx|download)\b/i;
  const PRINT_WORDS = /\b(print|pdf)\b/i;
  // A summary/insight print is not a print of the table.
  const NOT_TABLE = /\b(summary|insight|brief|certificate|card|letter|receipt|slip)\b/i;

  function ownControls(scope) {
    const found = { export: false, print: false };
    scope.querySelectorAll('button, a.btn, [role="button"]').forEach((el) => {
      if (el.closest('.kw-table-toolbar')) return;
      const text = [el.textContent, el.id, el.title, el.getAttribute('aria-label'), el.getAttribute('data-kw-onclick'), el.dataset.action]
        .filter(Boolean).join(' ');
      if (NOT_TABLE.test(text)) return;
      if (EXPORT_WORDS.test(text)) found.export = true;
      if (PRINT_WORDS.test(text)) found.print = true;
    });
    return found;
  }

  /** @return {{export:boolean, print:boolean}|null} the actions this table still needs */
  function needs(table, root) {
    if (enhanced.has(table)) return null;
    // Dashboard tables are listed in the dashboard's own Export-and-print tab.
    if (table.dataset.exportTitle !== undefined && document.querySelector('[data-kw-export-list]')) return null;
    if (table.closest(SKIP_ANCESTORS) || table.parentElement?.closest('table')) return null;
    const headRow = table.tHead?.rows[0];
    if (!headRow || headRow.cells.length < 2) return null;
    const scope = table.closest('.card, .tab-pane, section, [data-export-scope]') || root;
    const own = ownControls(scope);
    // One table on the page: page-level buttons cover it too.
    if (scope !== root && root.querySelectorAll('table').length === 1) {
      const page = ownControls(root);
      own.export = own.export || page.export;
      own.print = own.print || page.print;
    }
    if (own.export && own.print) return null;
    return { export: !own.export, print: !own.print };
  }

  async function downloadCsv(table) {
    const { headers, rows } = readTable(table);
    if (!rows.length) {
      notify('This table has no rows to download.', 'warning');
      return;
    }
    const csv = [headers, ...rows].map((line) => line.map(csvValue).join(',')).join('\r\n');
    const name = `${slug(titleFor(table))}_${new Date().toISOString().slice(0, 10)}.csv`;
    await window.KingswayFileLifecycle.exportText(`﻿${csv}`, name, 'text/csv;charset=utf-8');
  }

  async function printTable(table) {
    const { headers, rows } = readTable(table);
    if (!rows.length) {
      notify('This table has no rows to print.', 'warning');
      return;
    }
    if (!window.PrintManager?.printTable) {
      window.print();
      return;
    }
    const columns = headers.map((label, index) => ({ key: `c${index}`, label }));
    const records = rows.map((cells) => Object.fromEntries(cells.map((value, index) => [`c${index}`, value])));
    await window.PrintManager.printTable({
      title: titleFor(table),
      columns,
      rows: records,
      orientation: headers.length > WIDE_COLUMNS ? 'landscape' : 'portrait',
    });
  }

  function button(kind, icon, label, table, run) {
    const el = document.createElement('button');
    el.type = 'button';
    el.className = 'btn btn-sm btn-outline-secondary';
    el.innerHTML = `<i class="bi ${icon} me-1" aria-hidden="true"></i>`;
    el.append(label);
    if (!allowed(kind, table)) {
      el.hidden = true;
      return el;
    }
    el.addEventListener('click', async () => {
      el.disabled = true;
      try {
        await run(table);
      } catch (_) {
        notify(kind === 'export' ? 'Could not download this table.' : 'Could not print this table.', 'error');
      } finally {
        el.disabled = false;
      }
    });
    return el;
  }

  function enhance(table, want) {
    enhanced.add(table);
    const bar = document.createElement('div');
    bar.className = 'kw-table-toolbar d-print-none d-flex justify-content-end gap-2 mb-2';
    bar.setAttribute('role', 'toolbar');
    bar.setAttribute('aria-label', 'Download or print this table');
    if (want.export) bar.append(button('export', 'bi-filetype-csv', 'CSV', table, downloadCsv));
    if (want.print) bar.append(button('print', 'bi-printer', 'Print', table, printTable));
    if (!Array.from(bar.children).some((b) => !b.hidden)) return;
    const anchor = table.closest('.table-responsive') || table;
    anchor.parentNode.insertBefore(bar, anchor);
  }

  function scan() {
    const area = document.querySelector('#main-content-area') || document.querySelector('#main-content-segment');
    if (!area) return;
    area.querySelectorAll('table').forEach((table) => {
      const root = table.closest('#main-content-segment') || area;
      const want = needs(table, root);
      if (want) enhance(table, want);
    });
  }

  let pending = 0;
  function schedule() {
    if (pending) return;
    // Pages build tables and their own toolbars asynchronously; wait for quiet.
    pending = window.setTimeout(() => { pending = 0; scan(); }, 400);
  }

  function init() {
    schedule();
    const target = document.querySelector('#main-content-area') || document.body;
    new MutationObserver(schedule).observe(target, { childList: true, subtree: true });
  }

  window.KingswayTableToolbar = Object.freeze({ scan, readTable, titleFor });
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init);
  else init();
})(window, document);
