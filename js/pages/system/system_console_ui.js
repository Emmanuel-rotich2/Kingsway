/**
 * System Administrator console enhancer.
 *
 * Non-invasive: it only adds keyboard/AT affordances to existing table
 * wrappers and implements the generic print/CSV actions used by pages that do
 * not have a page-specific controller. It never replaces page controllers.
 */
(function () {
  'use strict';

  const ROOT = '.kw-sysadmin-console';

  function consoleRoot() {
    return document.querySelector(ROOT);
  }

  function accessibleTableName(wrap) {
    const caption = wrap.querySelector('table caption');
    if (caption && caption.textContent.trim()) {
      return caption.textContent.trim();
    }

    const card = wrap.closest('.card');
    const header = card && card.querySelector('.card-header');
    if (header && header.textContent.trim()) {
      return header.textContent.trim().replace(/\s+/g, ' ').slice(0, 120);
    }

    return 'System administration data table';
  }

  function enhanceTables(root) {
    if (!root) return;

    root.querySelectorAll('.table-responsive').forEach((wrap) => {
      if (wrap.dataset.kwSaEnhanced === '1') return;
      wrap.dataset.kwSaEnhanced = '1';

      if (!wrap.hasAttribute('tabindex')) wrap.tabIndex = 0;
      if (!wrap.hasAttribute('role')) wrap.setAttribute('role', 'region');
      if (!wrap.hasAttribute('aria-label')) {
        wrap.setAttribute('aria-label', accessibleTableName(wrap));
      }
    });
  }

  function csvCell(value) {
    const text = String(value ?? '')
      .replace(/\s+/g, ' ')
      .trim();
    return '"' + text.replace(/"/g, '""') + '"';
  }

  function exportTable(button) {
    const selector = button.getAttribute('data-kw-sa-export-target');
    const table = selector ? document.querySelector(selector) : null;
    if (!table) return;

    if (
      window.AuthContext &&
      typeof window.AuthContext.canExport === 'function' &&
      !window.AuthContext.canExport('system')
    ) {
      if (typeof window.showNotification === 'function') {
        window.showNotification('You do not have permission to export system data.', 'error');
      }
      return;
    }

    const headers = Array.from(table.querySelectorAll('thead th'))
      .filter((th) => !th.querySelector('input[type="checkbox"]'))
      .map((th) => th.textContent.trim());

    const rows = Array.from(table.querySelectorAll('tbody tr'))
      .filter((tr) => !tr.querySelector('td[colspan]'))
      .map((tr) =>
        Array.from(tr.children)
          .filter((cell) => !cell.querySelector('input[type="checkbox"]'))
          .map((cell) => csvCell(cell.textContent))
          .join(',')
      );

    if (!headers.length) return;

    const name =
      button.getAttribute('data-kw-sa-export-name') ||
      window.REQUESTED_ROUTE ||
      'system-admin-export';
    const date = new Date().toISOString().slice(0, 10);
    const csv = '\uFEFF' + [headers.map(csvCell).join(','), ...rows].join('\r\n');

    if (window.KingswayFileLifecycle?.exportText) {
      window.KingswayFileLifecycle.exportText(
        csv,
        `${name}_${date}.csv`,
        'text/csv;charset=utf-8'
      );
    }
  }

  function bind(root) {
    enhanceTables(root);
    if (!root || root.dataset.kwSaBound === '1') return;
    root.dataset.kwSaBound = '1';

    root.addEventListener('click', (event) => {
      const printButton = event.target.closest('[data-kw-sa-print]');
      if (printButton) {
        event.preventDefault();
        if (
          window.AuthContext &&
          typeof window.AuthContext.canPrint === 'function' &&
          !window.AuthContext.canPrint('system')
        ) {
          if (typeof window.showNotification === 'function') {
            window.showNotification('You do not have permission to print system data.', 'error');
          }
          return;
        }
        window.print();
        return;
      }

      const exportButton = event.target.closest('[data-kw-sa-export]');
      if (exportButton) {
        event.preventDefault();
        exportTable(exportButton);
      }
    });
  }

  function initialize() {
    const root = consoleRoot();
    if (root) bind(root);
  }

  document.addEventListener('DOMContentLoaded', initialize);

  // Soft navigation replaces #main-content-segment without a full reload.
  // Watch for the new console root and for tables rendered by page controllers.
  if (typeof MutationObserver !== 'undefined') {
    new MutationObserver(() => {
      const root = consoleRoot();
      if (!root) return;
      bind(root);
      enhanceTables(root);
    }).observe(document.documentElement, { childList: true, subtree: true });
  }

  window.SystemConsoleUI = { initialize, enhanceTables, bind };
})();
