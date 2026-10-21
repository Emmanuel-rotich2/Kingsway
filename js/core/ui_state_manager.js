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

  /**
   * Track open modals. Called on Bootstrap modal show/hide events.
   */
  function addOpenModal(modalId) {
    const mods = _get(KEY_OPEN_MODALS) || [];
    if (!mods.includes(modalId)) {
      mods.push(modalId);
      _set(KEY_OPEN_MODALS, mods);
    }
  }

  function removeOpenModal(modalId) {
    const mods = _get(KEY_OPEN_MODALS) || [];
    const idx = mods.indexOf(modalId);
    if (idx !== -1) {
      mods.splice(idx, 1);
      _set(KEY_OPEN_MODALS, mods);
    }
  }

  function getOpenModals() {
    return _get(KEY_OPEN_MODALS) || [];
  }

  function clearOpenModals() {
    _remove(KEY_OPEN_MODALS);
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
    clearActiveTab();
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
})();
