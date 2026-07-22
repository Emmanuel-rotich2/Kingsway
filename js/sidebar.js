/**
 * Sidebar Manager
 * Renders permission-aware navigation from AuthContext.
 * UI interaction is handled by js/index.js through window.KingswayShell.
 */
(() => {
  "use strict";

  let initialized = false;

  function escapeHtml(value) {
    const element = document.createElement("div");
    element.textContent = String(value ?? "");
    return element.innerHTML;
  }

  function hasRequiredPermission(item) {
    const permissions =
      item?.permission ||
      item?.permissions ||
      item?.required_permissions;

    if (!permissions || !window.AuthContext) {
      return true;
    }

    const list = Array.isArray(permissions)
      ? permissions
      : String(permissions)
          .split(",")
          .map((permission) => permission.trim())
          .filter(Boolean);

    return (
      list.length === 0 ||
      window.AuthContext.hasAnyPermission?.(list)
    );
  }

  function filterMenuItem(item) {
    if (!item || !hasRequiredPermission(item)) {
      return null;
    }

    const subitems = Array.isArray(item.subitems)
      ? item.subitems.map(filterMenuItem).filter(Boolean)
      : [];

    if (Array.isArray(item.subitems)) {
      return subitems.length > 0 ||
        item.url ||
        item.route ||
        item.route_name
        ? { ...item, subitems }
        : null;
    }

    return item;
  }

  function normalizeRoute(value) {
    if (!value) {
      return {
        route: "#",
        params: "",
      };
    }

    const text = String(value).trim();

    try {
      const parsed = new URL(text, window.location.origin);
      const route =
        parsed.searchParams.get("route") ||
        text.replace(/^\/+/, "");

      parsed.searchParams.delete("route");

      return {
        route,
        params: parsed.searchParams.toString(),
      };
    } catch {
      const [route, query = ""] = text.split("?");

      return {
        route: route.replace(/^\/+/, ""),
        params: query,
      };
    }
  }

  function createId(label) {
    try {
      return `submenu-${btoa(unescape(encodeURIComponent(label)))
        .replace(/[^A-Za-z0-9]/g, "")
        .slice(0, 28)}`;
    } catch {
      return `submenu-${Date.now().toString(36)}`;
    }
  }

  function renderSimpleItem(item) {
    const routeData = normalizeRoute(
      item.route || item.route_name || item.url || "#"
    );

    const label = escapeHtml(item.label || "Menu");
    const icon = escapeHtml(item.icon || "bi bi-circle");

    return `
      <a
        href="#"
        data-route="${escapeHtml(routeData.route)}"
        data-params="${escapeHtml(routeData.params)}"
        class="app-sidebar-item sidebar-link"
        title="${label}"
      >
        <span class="app-sidebar-icon">
          <i class="${icon}"></i>
        </span>
        <span class="sidebar-text">${label}</span>
      </a>
    `;
  }

  function renderParentItem(item) {
    const submenuId = createId(item.label || "menu");
    const label = escapeHtml(item.label || "Menu");
    const icon = escapeHtml(item.icon || "bi bi-folder");

    const children = item.subitems
      .map((subitem) => {
        const routeData = normalizeRoute(
          subitem.route ||
            subitem.route_name ||
            subitem.url ||
            "#"
        );

        const subLabel = escapeHtml(
          subitem.label || "Submenu"
        );

        const subIcon = escapeHtml(
          subitem.icon || "bi bi-dot"
        );

        return `
          <a
            href="#"
            data-route="${escapeHtml(routeData.route)}"
            data-params="${escapeHtml(routeData.params)}"
            class="app-sidebar-subitem sidebar-link"
            title="${subLabel}"
          >
            <span class="app-sidebar-subicon">
              <i class="${subIcon}"></i>
            </span>
            <span class="sidebar-text">${subLabel}</span>
          </a>
        `;
      })
      .join("");

    return `
      <button
        class="app-sidebar-item sidebar-toggle"
        type="button"
        data-submenu-target="#${submenuId}"
        aria-expanded="false"
        aria-controls="${submenuId}"
        title="${label}"
      >
        <span class="app-sidebar-icon">
          <i class="${icon}"></i>
        </span>
        <span class="sidebar-text">${label}</span>
        <i class="bi bi-chevron-down app-sidebar-chevron"></i>
      </button>

      <div
        class="app-sidebar-submenu collapse"
        id="${submenuId}"
      >
        ${children}
      </div>
    `;
  }

  function renderSidebar(menuItems) {
    const container =
      document.getElementById("sidebarMenu");

    if (!container) {
      return;
    }

    const filtered = Array.isArray(menuItems)
      ? menuItems.map(filterMenuItem).filter(Boolean)
      : [];

    if (!filtered.length) {
      container.innerHTML = `
        <div class="app-sidebar-empty">
          <i class="bi bi-exclamation-circle"></i>
          <span class="sidebar-text">
            No navigation items available
          </span>
        </div>
      `;
      return;
    }

    container.innerHTML = `
      <div class="app-sidebar-section-label">
        Navigation
      </div>
      ${filtered
        .map((item) =>
          Array.isArray(item.subitems) &&
          item.subitems.length
            ? renderParentItem(item)
            : renderSimpleItem(item)
        )
        .join("")}
    `;

    window.KingswayShell?.refresh?.();
  }

  function initializeSidebar() {
    const items =
      window.AuthContext?.getSidebarItems?.() || [];

    renderSidebar(items);
  }

  function initialize() {
    if (initialized) {
      initializeSidebar();
      return;
    }

    initialized = true;
    initializeSidebar();

    document.addEventListener(
      "authchanged",
      initializeSidebar
    );

    window.addEventListener(
      "kingsway:ready",
      initializeSidebar,
      { once: true }
    );
  }

  window.refreshSidebar = (menuItems) => {
    renderSidebar(
      menuItems ||
        window.AuthContext?.getSidebarItems?.() ||
        []
    );
  };

  window.SidebarManager = {
    initialize,
    render: renderSidebar,
    refresh: window.refreshSidebar,
  };

  if (document.readyState === "loading") {
    document.addEventListener(
      "DOMContentLoaded",
      initialize,
      { once: true }
    );
  } else {
    initialize();
  }
})();
