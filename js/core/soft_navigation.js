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
   * Execute an inline script string in the global scope.
   * Kept for potential future use; currently disabled to avoid redeclaration
   * errors on soft navigation.
   */
  function executeInlineScript(code) {
    const s = document.createElement('script');
    s.textContent = code;
    document.head.appendChild(s);
    s.remove();
  }

  /**
   * Load only the main content of a route (fragment mode).
   * Called by window.AppRouter.go after authorization passes.
   * @param {string} route
   * @param {string} extraParams
   * @param {{updateHistory?: boolean}} options
   */
  async function loadFragment(route, extraParams = '', options = {}) {
    if (isNavigating) return;
    isNavigating = true;

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
        if (code.trim()) inlineScripts.push(code);
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

      // --- Do NOT re-execute inline scripts from fragments ---
      // Inline <script> blocks use const/let at top level and would cause
      // "redeclaration" errors on every soft-nav. Page controllers self-init
      // via the kingsway:page-loaded event + synthetic DOMContentLoaded below,
      // with `if (this.initialized) return;` guards to prevent double-init.

      // --- Persist route for reload recovery ---
      window.UIStateManager?.saveRoute?.(route);

      // Keep the global route pointer in sync with the fragment that is now
      // rendered. Every route-scoped module (tabs, drafts, assistant context,
      // error reporting) reads REQUESTED_ROUTE, and it must no longer describe
      // the page the user first landed on.
      window.REQUESTED_ROUTE = route;

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
