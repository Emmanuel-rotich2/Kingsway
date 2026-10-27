/**
 * UIStateManager — Persists and restores UI state across page reloads.
 *
 * Stores in sessionStorage (cleared on tab/window close):
 *   - kingsway_ui_last_route       — last active route
 *   - kingsway_ui_active_tab       — last active tab, keyed by route
 *   - kingsway_ui_open_modals      — array of open modal IDs
 *   - kingsway_ui_open_offcanvas   — array of open offcanvas IDs
 *
 * Tabs are keyed by route so restoring a tab only applies to the same page.
 */
(function () {
  'use strict';

  const STORAGE_PREFIX = 'kingsway_ui_';
  const KEY_ROUTE = STORAGE_PREFIX + 'last_route';
  const KEY_ACTIVE_TAB = STORAGE_PREFIX + 'active_tab';
  const KEY_BOOTSTRAP_TABS = STORAGE_PREFIX + 'bootstrap_tabs';
  const KEY_OPEN_MODALS = STORAGE_PREFIX + 'open_modals';
  const KEY_OPEN_OFFCANVAS = STORAGE_PREFIX + 'open_offcanvas';

  function _get(key) {
    try {
      const raw = sessionStorage.getItem(key);
      return raw ? JSON.parse(raw) : null;
    } catch (e) {
      return null;
    }
  }

  function _set(key, value) {
    try {
      sessionStorage.setItem(key, JSON.stringify(value));
    } catch (e) {
      // Quota exceeded or private mode — ignore silently.
    }
  }

  function _remove(key) {
    try {
      sessionStorage.removeItem(key);
    } catch (e) {
      // ignore
    }
  }

  /**
   * Save the current route (called on navigation).
   */
  function saveRoute(route) {
    if (!route || route === 'loading') return;
    _set(KEY_ROUTE, route);
  }

  function getRoute() {
    return _get(KEY_ROUTE);
  }

  function currentRoute() {
    return String(window.REQUESTED_ROUTE || new URLSearchParams(window.location.search).get('route') || '');
  }

  function clearRoute() {
    _remove(KEY_ROUTE);
  }

  /**
   * Save the active tab for the current route.
   * Stored as { route: { tabId: '...' } }.
   */
  function saveActiveTab(tabId) {
    const route = window.REQUESTED_ROUTE || '';
    if (!route || route === 'loading') return;
    const all = _get(KEY_ACTIVE_TAB) || {};
    all[route] = tabId;
    _set(KEY_ACTIVE_TAB, all);
  }

  function getActiveTab() {
    const route = window.REQUESTED_ROUTE || '';
    const all = _get(KEY_ACTIVE_TAB) || {};
    return all[route] || null;
  }

  function clearActiveTab() {
    const route = window.REQUESTED_ROUTE || '';
    const all = _get(KEY_ACTIVE_TAB) || {};
    if (all[route]) {
      delete all[route];
      _set(KEY_ACTIVE_TAB, all);
    }
  }

  function saveBootstrapTab(tab) {
    if (!tab) return;
    const route = currentRoute();
    if (!route) return;
    const target = tab.getAttribute('data-bs-target') || tab.getAttribute('href') || '';
    const all = _get(KEY_BOOTSTRAP_TABS) || {};
    all[route] = {
      id: tab.id || '',
      target: target.startsWith('#') ? target : '',
    };
    _set(KEY_BOOTSTRAP_TABS, all);
  }

  function findBootstrapTab(saved) {
    if (!saved) return null;
    if (saved.id) {
      const byId = document.getElementById(saved.id);
      if (byId?.matches('[data-bs-toggle="tab"], [data-bs-toggle="pill"]')) return byId;
    }
    if (saved.target) {
      return Array.from(document.querySelectorAll('[data-bs-toggle="tab"], [data-bs-toggle="pill"]'))
        .find((tab) => (tab.getAttribute('data-bs-target') || tab.getAttribute('href')) === saved.target) || null;
    }
    return null;
  }

  function restoreBootstrapTab() {
    const saved = (_get(KEY_BOOTSTRAP_TABS) || {})[currentRoute()];
    if (!saved) return;
    let observer = null;
    let settleTimer = null;
    let timeoutTimer = null;
    const stopObserving = () => {
      if (observer) observer.disconnect();
      if (settleTimer !== null) window.clearTimeout(settleTimer);
      if (timeoutTimer !== null) window.clearTimeout(timeoutTimer);
      observer = null;
      settleTimer = null;
      timeoutTimer = null;
    };
    const activate = () => {
      const tab = findBootstrapTab(saved);
      if (!tab || tab.disabled || !window.bootstrap?.Tab) return false;
      window.bootstrap.Tab.getOrCreateInstance(tab).show();
      return true;
    };
    if (activate()) return;
    // Many page controllers render tabs after an asynchronous data request.
    // Observe that render briefly so the remembered tab can still be restored.
    if (!document.body || typeof MutationObserver === 'undefined') return;
    observer = new MutationObserver(() => {
      // Let page renderers finish their current batch before applying the saved
      // tab. Several screens replace tab markup more than once during startup.
      if (settleTimer !== null) window.clearTimeout(settleTimer);
      settleTimer = window.setTimeout(() => {
        settleTimer = null;
        if (activate()) stopObserving();
      }, 120);
    });
    observer.observe(document.body, {childList: true, subtree: true});
    timeoutTimer = window.setTimeout(stopObserving, 10000);
  }

  /**
   * Track open modals. Called on Bootstrap modal show/hide events.
   */
  function addOpenModal(modalId) {
    const stored = _get(KEY_OPEN_MODALS);
    const all = stored && !Array.isArray(stored) ? stored : {};
    const route = currentRoute();
    const mods = Array.isArray(all[route]) ? all[route] : [];
    if (!mods.includes(modalId)) {
      mods.push(modalId);
      all[route] = mods;
      _set(KEY_OPEN_MODALS, all);
    }
  }

  function removeOpenModal(modalId) {
    const stored = _get(KEY_OPEN_MODALS);
    const all = stored && !Array.isArray(stored) ? stored : {};
    const route = currentRoute();
    const mods = Array.isArray(all[route]) ? all[route] : [];
    const idx = mods.indexOf(modalId);
    if (idx !== -1) {
      mods.splice(idx, 1);
      all[route] = mods;
      _set(KEY_OPEN_MODALS, all);
    }
  }

  function getOpenModals() {
    const stored = _get(KEY_OPEN_MODALS);
    const all = stored && !Array.isArray(stored) ? stored : {};
    return all[currentRoute()] || [];
  }

  function clearOpenModals() {
    _remove(KEY_OPEN_MODALS);
  }

  function wireBootstrapState() {
    document.addEventListener('shown.bs.tab', (event) => saveBootstrapTab(event.target));
    document.addEventListener('show.bs.modal', (event) => {
      const modal = event.target;
      if (modal?.id && modal.dataset.persistOpen === 'true') addOpenModal(modal.id);
    });
    document.addEventListener('hidden.bs.modal', (event) => {
      const modal = event.target;
      if (modal?.id) removeOpenModal(modal.id);
    });
    document.addEventListener('show.bs.offcanvas', (event) => {
      const panel = event.target;
      if (panel?.id && panel.dataset.persistOpen === 'true') addOpenOffcanvas(panel.id);
    });
    document.addEventListener('hidden.bs.offcanvas', (event) => {
      const panel = event.target;
      if (panel?.id) removeOpenOffcanvas(panel.id);
    });

    const restore = () => {
      restoreBootstrapTab();
      if (window.bootstrap?.Modal) {
        getOpenModals().forEach((id) => {
          const modal = document.getElementById(id);
          // Only restore explicitly opted-in, static dialogs. Dynamic dialogs
          // and transactions must be recreated by their page workflow.
          if (modal?.dataset.persistOpen === 'true') {
            window.bootstrap.Modal.getOrCreateInstance(modal).show();
          } else {
            removeOpenModal(id);
          }
        });
      }
      if (window.bootstrap?.Offcanvas) {
        getOpenOffcanvases().forEach((id) => {
          const panel = document.getElementById(id);
          if (panel?.dataset.persistOpen === 'true') {
            window.bootstrap.Offcanvas.getOrCreateInstance(panel).show();
          } else {
            removeOpenOffcanvas(id);
          }
        });
      }
    };
    document.addEventListener('DOMContentLoaded', restore, {once: true});
    window.addEventListener('kingsway:page-loaded', restore);
    window.addEventListener('kingsway:ready', restore);
    if (document.readyState !== 'loading') window.setTimeout(restore, 0);
  }

  /**
   * Track open offcanvas panels.
   */
  function addOpenOffcanvas(offcanvasId) {
    const offs = _get(KEY_OPEN_OFFCANVAS) || [];
    if (!offs.includes(offcanvasId)) {
      offs.push(offcanvasId);
      _set(KEY_OPEN_OFFCANVAS, offs);
    }
  }

  function removeOpenOffcanvas(offcanvasId) {
    const offs = _get(KEY_OPEN_OFFCANVAS) || [];
    const idx = offs.indexOf(offcanvasId);
    if (idx !== -1) {
      offs.splice(idx, 1);
      _set(KEY_OPEN_OFFCANVAS, offs);
    }
  }

  function getOpenOffcanvases() {
    return _get(KEY_OPEN_OFFCANVAS) || [];
  }

  function clearOpenOffcanvases() {
    _remove(KEY_OPEN_OFFCANVAS);
  }

  /**
   * Clear all UI state (e.g. on logout).
   */
  function clearAll() {
    clearRoute();
    _remove(KEY_ACTIVE_TAB);
    _remove(KEY_BOOTSTRAP_TABS);
    clearOpenModals();
    clearOpenOffcanvases();
  }

  window.UIStateManager = {
    saveRoute,
    getRoute,
    clearRoute,
    saveActiveTab,
    getActiveTab,
    clearActiveTab,
    addOpenModal,
    removeOpenModal,
    getOpenModals,
    clearOpenModals,
    addOpenOffcanvas,
    removeOpenOffcanvas,
    getOpenOffcanvases,
    clearOpenOffcanvases,
    clearAll,
  };

  wireBootstrapState();
})();
