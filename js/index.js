(() => {
  "use strict";

  function getRouteDataFromUrl(url) {
    if (!url) return { route: "", params: "" };

    const value = String(url).trim();

    try {
      const parsed = new URL(value, window.location.origin);
      const route = parsed.searchParams.get("route");

      if (route) {
        parsed.searchParams.delete("route");
        return {
          route,
          params: parsed.searchParams.toString(),
        };
      }
    } catch {
      // Continue with fragment parsing.
    }

    const match = value.match(/^([^?&#]+)(?:\?([^#]*))?/);

    return {
      route: match?.[1]?.replace(/^\/+/, "") || "",
      params: match?.[2] || "",
    };
  }

  function getRouteFromUrl(url) {
    return getRouteDataFromUrl(url).route;
  }

  function collectAllowedRoutes(items, allowed = new Set()) {
    if (!Array.isArray(items)) return allowed;

    items.forEach((item) => {
      if (!item) return;

      const route = getRouteFromUrl(
        item.url || item.route || item.data_route || ""
      );

      if (route && route !== "#" && route !== "loading") {
        allowed.add(route);
      }

      collectAllowedRoutes(item.subitems, allowed);
    });

    return allowed;
  }

  function getAllowedRoutes() {
    const allowed = collectAllowedRoutes(
      window.AuthContext?.getSidebarItems?.() || []
    );

    const dashboardRoute = getRouteFromUrl(
      window.AuthContext?.getDashboardInfo?.()?.key || ""
    );

    if (dashboardRoute) {
      allowed.add(dashboardRoute);
    }

    allowed.add("profile");

    return allowed;
  }

  async function authorizeRouteAccess(route) {
    const normalizedRoute = getRouteFromUrl(route);

    if (!normalizedRoute || normalizedRoute === "loading") {
      return {
        authorized: true,
        route: normalizedRoute,
        source: "shell",
      };
    }

    if (!window.AuthContext?.isAuthenticated?.()) {
      return {
        authorized: false,
        route: normalizedRoute,
        reason: "unauthenticated",
      };
    }

    const allowedRoutes = getAllowedRoutes();
    const dashboardRoute = getRouteFromUrl(
      window.AuthContext?.getDashboardInfo?.()?.key || ""
    );

    const authorized =
      allowedRoutes.has(normalizedRoute) ||
      normalizedRoute === dashboardRoute;

    return {
      authorized,
      route: normalizedRoute,
      source: "local_sidebar",
      reason: authorized ? "in_sidebar" : "not_in_sidebar",
    };
  }

  function getBestAllowedRoute(excludedRoute = "") {
    const dashboardRoute = getRouteFromUrl(
      window.AuthContext?.getDashboardInfo?.()?.key || ""
    );

    if (dashboardRoute && dashboardRoute !== excludedRoute) {
      return dashboardRoute;
    }

    return (
      [...getAllowedRoutes()].find(
        (route) => route && route !== excludedRoute
      ) || ""
    );
  }

  async function redirectToAllowedRoute(disallowedRoute) {
    const fallbackRoute = getBestAllowedRoute(
      getRouteFromUrl(disallowedRoute)
    );

    if (!fallbackRoute) return null;

    window.location.replace(
      `${window.APP_BASE || ""}/home.php?route=${encodeURIComponent(
        fallbackRoute
      )}`
    );

    return fallbackRoute;
  }

  async function navigateWithFullPageShell(route) {
    const routeData = getRouteDataFromUrl(route);
    const normalizedRoute = routeData.route;

    if (!normalizedRoute || normalizedRoute === "#") {
      return false;
    }

    const authorization = await authorizeRouteAccess(normalizedRoute);

    if (!authorization.authorized) {
      window.showNotification?.(
        "You are not allowed to open that page.",
        window.NOTIFICATION_TYPES?.WARNING || "warning"
      );

      await redirectToAllowedRoute(normalizedRoute);
      return false;
    }

    const suffix = routeData.params
      ? `&${routeData.params}`
      : "";

    window.location.href =
      `${window.APP_BASE || ""}/home.php?route=` +
      `${encodeURIComponent(normalizedRoute)}${suffix}`;

    return true;
  }

  document.addEventListener("click", (event) => {
    const link = event.target.closest?.(".sidebar-link");
    if (!link) return;

    event.preventDefault();

    const route = link.dataset.route;
    if (!route) return;

    if (window.innerWidth < 992) {
      window.KingswayShell?.closeMobileSidebar?.();
    }

    void navigateWithFullPageShell(route);
  });

  window.AppRouter = {
    ...(window.AppRouter || {}),
    go: navigateWithFullPageShell,
  };

  window.AppRouteAccess = {
    authorizeRoute: authorizeRouteAccess,
    getAllowedRoutes,
    getBestAllowedRoute,
    redirectToAllowedRoute,
    revealProtectedContent() {},
    setPending() {},
    normalizeRoute: getRouteFromUrl,
  };

  window.navigateToRoute = navigateWithFullPageShell;
})();
