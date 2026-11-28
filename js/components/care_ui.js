/**
 * Chaplaincy pages (.kw-care): small presentation helpers.
 *  - copies each column header onto its cells as data-label, so narrow
 *    screens can show every row as a short labelled list (see chaplaincy.css)
 *  - makes scrolling table wrappers keyboard-reachable, named regions
 * Rows rendered later by the page scripts are labelled as they appear.
 */
(function () {
  'use strict';
  if (window.KwCareUI) return;

  function headersOf(table) {
    var row = table.tHead && table.tHead.rows[table.tHead.rows.length - 1];
    if (!row) return [];
    return Array.prototype.map.call(row.cells, function (th) { return th.textContent.trim(); });
  }

  function labelTable(table) {
    var labels = headersOf(table);
    if (!labels.length) return;
    Array.prototype.forEach.call(table.tBodies, function (body) {
      Array.prototype.forEach.call(body.rows, function (tr) {
        var col = 0;
        Array.prototype.forEach.call(tr.cells, function (cell) {
          if (cell.colSpan > 1) { col += cell.colSpan; return; }
          if (labels[col] && !cell.hasAttribute('data-label')) cell.setAttribute('data-label', labels[col]);
          col += 1;
        });
      });
    });
  }

  function prepareWrapper(wrap, root) {
    if (wrap.dataset.careReady) return;
    wrap.dataset.careReady = '1';
    if (!wrap.hasAttribute('tabindex')) wrap.setAttribute('tabindex', '0');
    if (!wrap.hasAttribute('role')) wrap.setAttribute('role', 'region');
    if (!wrap.hasAttribute('aria-label') && !wrap.hasAttribute('aria-labelledby')) {
      var title = root.querySelector('.care-title');
      wrap.setAttribute('aria-label', (title ? title.textContent.trim() + ' ' : '') + 'table');
    }
  }

  function apply(root) {
    root.querySelectorAll('.table-responsive').forEach(function (w) { prepareWrapper(w, root); });
    root.querySelectorAll('table').forEach(labelTable);
  }

  function init() {
    document.querySelectorAll('.kw-care').forEach(function (root) {
      apply(root);
      var pending = false;
      new MutationObserver(function () {
        if (pending) return;
        pending = true;
        requestAnimationFrame(function () { pending = false; apply(root); });
      }).observe(root, { childList: true, subtree: true });
    });
  }

  window.KwCareUI = { refresh: init };
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init); else init();
})();
