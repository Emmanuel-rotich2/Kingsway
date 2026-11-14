/**
 * Soft Navigation Controller
 * Single responsibility: DOM event interception + fragment loading.
 *
 * - Intercepts internal link clicks (sidebar data-route / href=?route=)
 * - Handles browser Back/Forward (popstate)
 * - Fetches ?fragment=1 content, strips scripts, swaps #main-content-segment
 * - Loads page-specific scripts from fragment, dispatches synthetic DOMContentLoaded
 * - Delegates authorization + route execution to window.AppRouter.go (index.js)
 *
 * Global scripts (loaded by home.php) are NEVER re-executed.
 */
(function () {
  'use strict';

  let isNavigating = false;
  const pageScriptRoutes = new Map();

  // Page scripts use DOMContentLoaded to initialize their controllers. Soft
  // navigation emits a synthetic DOMContentLoaded after every route change;
  // without route scoping, listeners from every previously visited page run
  // against the new page's DOM (often dereferencing a missing tbody).
  const nativeDocumentAddEventListener = document.addEventListener.bind(document);
  document.addEventListener = function (type, listener, options) {
    const currentScript = document.currentScript;
    const scriptUrl = currentScript?.src || '';
    const isExternalPageScript = /\/js\/pages\//.test(scriptUrl);
    const isInlinePageScript = Boolean(currentScript && !scriptUrl && currentScript.closest?.('#main-content-segment'));
    if (type === 'DOMContentLoaded' && typeof listener === 'function' && (isExternalPageScript || isInlinePageScript)) {
      let registeredRoutes;
      if (isExternalPageScript) {
        const scriptPath = scriptUrl.split('?')[0];
        registeredRoutes = pageScriptRoutes.get(scriptPath);
        if (!registeredRoutes) {
          registeredRoutes = new Set();
          pageScriptRoutes.set(scriptPath, registeredRoutes);
        }
        if (window.REQUESTED_ROUTE) registeredRoutes.add(window.REQUESTED_ROUTE);
      } else {
        // Inline initializers in the first server-rendered page also remain
        // registered after soft navigation. Bind each one to its source route
        // so it cannot touch DOM belonging to a different page.
        registeredRoutes = new Set(window.REQUESTED_ROUTE ? [window.REQUESTED_ROUTE] : []);
      }
      const once = typeof options === 'object' && options !== null && options.once === true;
      let invoked = false;
      const routeScopedListener = function (event) {
        if (invoked) return;
        if (!registeredRoutes.has(window.REQUESTED_ROUTE)) return;
        if (once) {
          invoked = true;
          nativeDocumentRemoveEventListener(type, routeScopedListener, options);
        }
        return listener.call(this, event);
      };
      const nativeDocumentRemoveEventListener = document.removeEventListener.bind(document);
      const scopedOptions = once ? { ...options, once: false } : options;
      return nativeDocumentAddEventListener(type, routeScopedListener, scopedOptions);
    }
    return nativeDocumentAddEventListener(type, listener, options);
  };

  /**
   * Dynamically load an external script by URL.
   * Matches already-loaded scripts by path (ignores cache-busting ?v= params).
   */
  function loadScript(src) {
    return new Promise((resolve, reject) => {
      const srcPath = src.split('?')[0];
      const alreadyLoaded = Array.from(document.querySelectorAll('script[src]')).some(
        (s) => s.src.split('?')[0] === srcPath
      );
      if (alreadyLoaded) {
        resolve();
        return;
      }
      const s = document.createElement('script');
      s.src = src;
      s.async = false;
      s.onload = () => resolve();
      s.onerror = () => reject(new Error('Failed to load script: ' + src));
      document.head.appendChild(s);
    });
  }

  /**
   * Execute a fragment's inline script. The code is wrapped in a block so
   * its top-level const/let/class bindings stay local to that block. A
   * failure in one block is reported and does not stop the navigation.
   */
  function executeInlineScript(code, host) {
    const s = document.createElement('script');
    s.textContent = `{\n${code}\n}`;
    try {
      (host || document.head).appendChild(s);
    } catch (error) {
      console.warn('Soft nav inline script failed:', error?.message || error);
    } finally {
      s.remove();
    }
  }

  /**
   * Load only the main content of a route (fragment mode).
   * Called by window.AppRouter.go after authorization passes.
   * @param {string} route
   * @param {string} extraParams
   * @param {{updateHistory?: boolean}} options
   */
  // A page load that failed for lack of a connection, waiting to run again.
  let pendingResume = null;

  function cancelPendingResume() {
    if (!pendingResume) return;
    window.removeEventListener('kingsway:server-reachable', pendingResume);
    pendingResume = null;
  }

  function showResumableFailure(container, route, extraParams, options) {
    window.dispatchEvent(new CustomEvent('kingsway:server-unreachable', {
      detail: { source: 'page_load' },
    }));

    container.innerHTML = `
      <div class="text-center p-5 text-muted">
        <i class="bi bi-wifi-off fs-2 d-block mb-2" aria-hidden="true"></i>
        <p class="mb-1 fw-semibold text-body">This page could not load because the school server cannot be reached.</p>
        <p class="mb-3">It will open by itself as soon as the connection is back.</p>
        <button type="button" class="btn btn-sm btn-outline-secondary" data-kw-resume>Try again</button>
      </div>
    `;

    const resume = () => {
      cancelPendingResume();
      void loadFragment(route, extraParams, options);
    };
    pendingResume = resume;
    window.addEventListener('kingsway:server-reachable', resume);
    container.querySelector('[data-kw-resume]')?.addEventListener('click', resume);
  }

  async function loadFragment(route, extraParams = '', options = {}) {
    if (isNavigating) return;
    isNavigating = true;
    cancelPendingResume();

    const updateHistory = options.updateHistory !== false;

    const container = document.getElementById('main-content-segment');
    if (!container) {
      isNavigating = false;
      return;
    }

    container.innerHTML = `
      <div class="text-center p-5">
        <div class="spinner-border text-success" role="status">
          <span class="visually-hidden">Loading...</span>
        </div>
        <p class="mt-2 text-muted">Loading page...</p>
      </div>
    `;

    try {
      const separator = extraParams ? '&' : '';
      const url = `${window.APP_BASE || ''}/home.php?route=${encodeURIComponent(route)}${separator}${extraParams}&fragment=1`;

      const response = await fetch(url, {
        method: 'GET',
        headers: {
          'X-Requested-With': 'XMLHttpRequest',
          'Accept': 'text/html'
        },
        credentials: 'same-origin'
      });

      if (!response.ok) {
        throw new Error('HTTP ' + response.status);
      }

      const html = await response.text();

      // --- Parse fragment HTML: separate scripts from content ---
      const temp = document.createElement('div');
      temp.innerHTML = html;

      const scriptSrcs = [];
      temp.querySelectorAll('script[src]').forEach((s) => {
        scriptSrcs.push(s.src);
        s.remove();
      });

      const inlineScripts = [];
      temp.querySelectorAll('script:not([src])').forEach((s) => {
        const code = s.textContent || s.innerHTML || '';
        // Only classic scripts run; data blocks (application/json, templates)
        // and modules (which cannot be block-wrapped) are left out.
        const type = (s.getAttribute('type') || '').trim().toLowerCase();
        const isClassic = type === '' || type === 'text/javascript' || type === 'application/javascript';
        if (isClassic && code.trim()) inlineScripts.push(code);
        s.remove();
      });

      const sanitizedHtml = temp.innerHTML;

      // Page controllers commonly bind listeners to concrete DOM nodes and
      // keep one-time `initialized` flags. Re-inserting a visited route does
      // not recreate those listeners, and triggering old realtime callbacks
      // against the replacement DOM causes null tbody/innerHTML failures.
      // Start a clean document whenever the route's controller was loaded
      // before; first visits remain soft navigations.
      const previouslyLoadedPageController = scriptSrcs.some((src) => {
        const path = src.split('?')[0];
        if (!/\/js\/pages\//.test(path)) return false;
        return Array.from(document.querySelectorAll('script[src]'))
          .some((script) => script.src.split('?')[0] === path);
      });
      if (previouslyLoadedPageController) {
        const params = extraParams ? `&${extraParams}` : '';
        window.location.assign(`${window.APP_BASE || ''}/home.php?route=${encodeURIComponent(route)}${params}`);
        return;
      }

      // --- Insert sanitized content (no scripts) ---
      container.innerHTML = sanitizedHtml;

      // The page controller's DOMContentLoaded listener must capture the route
      // being loaded, because it is evaluated before the navigation event is
      // dispatched below.
      window.REQUESTED_ROUTE = route;

      // Remove the prior page's realtime subscriptions before incoming page
      // scripts evaluate. Some controllers register at script evaluation time.
      window.dispatchEvent(new CustomEvent('kingsway:page-will-load', {
        detail: { route: route, extraParams: extraParams }
      }));

      // --- Load external page-specific scripts (skipped if already loaded) ---
      for (const src of scriptSrcs) {
        try {
          await loadScript(src);
        } catch (e) {
          console.warn('Soft nav script load failed:', e.message);
        }
      }

      // --- Run the fragment's inline scripts ---
      // Many pages start their controller only from an inline block
      // (`document.addEventListener('DOMContentLoaded', () => x.init())`), and
      // some keep all their logic inline. Skipping them left those pages inert
      // on soft navigation (e.g. a spinner that never resolved). Each block
      // runs in its own block scope, so top-level const/let from different
      // pages cannot collide; a revisited route already takes the full-reload
      // path above, so a page's own blocks never run twice in one document.
      // Appending inside the content segment lets the DOMContentLoaded
      // wrapper bind each listener to this route.
      for (const code of inlineScripts) {
        executeInlineScript(code, container);
      }

      // --- Persist route for reload recovery ---
      window.UIStateManager?.saveRoute?.(route);

      // Keep the global route pointer in sync with the fragment that is now
      // rendered. Every route-scoped module (tabs, drafts, assistant context,
      // error reporting) reads REQUESTED_ROUTE, and it must no longer describe
      // the page the user first landed on.
      window.REQUESTED_ROUTE = route;

      // Fragment navigation leaves the shell and its sidebar in place, so the
      // shell's initial route highlighting does not run again automatically.
      // Reuse its existing matcher to move the active item and expand the
      // matching submenu as soon as the new route is committed.
      window.KingswayShell?.markActiveRoute?.();

      // Update browser URL so F5/hard refresh keeps the current page
      if (updateHistory) {
        try {
          const params = extraParams ? ('&' + extraParams) : '';
          const url = (window.APP_BASE || '') + '/home.php?route=' + encodeURIComponent(route) + params;
          history.pushState({ route: route, extraParams: extraParams }, '', url);
        } catch (e) {
          // History updates can fail in sandboxed/embedded contexts; the
          // fragment has already loaded, so continue without a URL change.
        }
      }

      // --- Notify shell + other modules that the page changed ---
      window.dispatchEvent(new CustomEvent('kingsway:page-loaded', {
        detail: { route: route, extraParams: extraParams }
      }));

      // --- Synthetic DOMContentLoaded so page controllers self-init ---
      document.dispatchEvent(new Event('DOMContentLoaded'));

    } catch (error) {
      // fetch() rejects only when it could not connect; HTTP errors arrive as
      // responses and are handled below. Keep the user here and resume this
      // exact page when the server answers, instead of a full reload that the
      // service worker can only answer with the offline page.
      if (error instanceof TypeError || navigator.onLine === false) {
        showResumableFailure(container, route, extraParams, options);
        return;
      }
      console.error('Soft navigation failed:', error);
      container.innerHTML = `
        <div class="alert alert-danger m-3">
          <i class="bi bi-exclamation-triangle me-2"></i>
          Failed to load the page. Please try again.
          <br>
          <button class="btn btn-sm btn-outline-danger mt-2" onclick="window.location.reload()">
            Reload full page
          </button>
        </div>
      `;
    } finally {
      isNavigating = false;
    }
  }

  /**
   * Extract route + params from a clicked link.
   * Returns { route, params } or null.
   */
  function extractRouteFromLink(link) {
    const href = link.getAttribute('href');
    const dataRoute = link.getAttribute('data-route');
    const dataParams = link.getAttribute('data-params');

    if (!href && !dataRoute) return null;

    // Ignore real external URLs
    if (href && href !== '#' && !href.includes('home.php?route=')) return null;

    let route, params;

    if (dataRoute) {
      route = dataRoute;
      params = dataParams || '';
    } else {
      const url = new URL(href, window.location.origin);
      route = url.searchParams.get('route');
      params = url.searchParams.toString()
        .replace('route=' + encodeURIComponent(route), '')
        .replace(/^&/, '');
    }

    if (!route) return null;
    return { route, params };
  }

  /**
   * Intercept internal link clicks.
   * Delegates navigation decision to window.AppRouter.go (index.js).
   */
  function handleClick(e) {
    if (e.ctrlKey || e.metaKey || e.shiftKey || e.button !== 0) return;

    const link = e.target.closest('a');
    if (!link) return;

    const nav = extractRouteFromLink(link);
    if (!nav) return;

    e.preventDefault();

    // Close mobile sidebar if open
    if (window.innerWidth < 992) {
      window.KingswayShell?.closeMobileSidebar?.();
    }

    // Delegate authorization + execution to index.js
    if (window.AppRouter?.go) {
      window.AppRouter.go(nav.route, nav.params);
    } else {
      // Fallback: full reload if AppRouter not ready
      const newUrl = `${window.APP_BASE || ''}/home.php?route=${encodeURIComponent(nav.route)}${nav.params ? '&' + nav.params : ''}`;
      window.location.href = newUrl;
    }
  }

  /**
   * Handle browser Back / Forward buttons.
   */
  function handlePopState(e) {
    if (e.state && e.state.route) {
      // The history entry already carries this route; do not push a new one.
      loadFragment(e.state.route, e.state.extraParams || '', { updateHistory: false });
    } else {
      window.location.reload();
    }
  }

  /**
   * Initialize.
   */
  function init() {
    document.addEventListener('click', handleClick);
    window.addEventListener('popstate', handlePopState);

    const currentRoute = window.REQUESTED_ROUTE;
    if (currentRoute && currentRoute !== 'loading') {
      history.replaceState({ route: currentRoute }, '', window.location.href);
    }
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }

  /**
   * Capture sidebar state: expanded submenus and visible routes.
   * Used to give the AI context about what the user can see/navigate.
   */
  function getSidebarState() {
    const expanded = [];
    document.querySelectorAll('.sidebar-toggle[aria-expanded="true"]').forEach((btn) => {
      const target = btn.getAttribute('data-submenu-target');
      if (target) expanded.push(target);
    });
    const visibleRoutes = [];
    const visibleItems = [];
    document.querySelectorAll('.sidebar-link[data-route]').forEach((link) => {
      const r = link.getAttribute('data-route');
      if (r && r !== '#') {
        visibleRoutes.push(r);
        const label =
          (link.querySelector('.sidebar-text')?.textContent || link.title || '').trim();
        if (label) visibleItems.push({ label, route: r });
      }
    });
    const currentRoute = window.REQUESTED_ROUTE || '';
    const currentItem = visibleItems.find((item) => item.route === currentRoute);
    return {
      expanded_submenus: expanded,
      visible_routes: visibleRoutes,
      visible_items: visibleItems,
      current_route: currentRoute,
      current_label: currentItem?.label || '',
    };
  }

  // Expose for debugging / programmatic use
  window.SoftNavigation = {
    load: loadFragment,
    getSidebarState: getSidebarState,
  };
})();
