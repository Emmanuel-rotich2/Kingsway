/**
 * Kingsway application-shell UI
 *
 * Owns:
 * - header user details
 * - desktop sidebar collapse
 * - mobile sidebar drawer
 * - submenu accordion
 * - active route expansion
 * - header search, notifications and theme controls
 *
 * Navigation authorization remains in js/index.js.
 */
(() => {
  "use strict";

  const COLLAPSE_STORAGE_KEY = "kingsway_sidebar_collapsed";
  const THEME_STORAGE_KEY = "kingsway_theme";
  const MOBILE_BREAKPOINT = 992;

  let initialized = false;
  let resizeTimer = null;

  const $ = (selector, root = document) =>
    root.querySelector(selector);

  const $$ = (selector, root = document) =>
    Array.from(root.querySelectorAll(selector));

  function isMobile() {
    return window.innerWidth < MOBILE_BREAKPOINT;
  }

  function prettifyRole(role) {
    const raw =
      typeof role === "object" && role
        ? role.name ||
          role.role_name ||
          role.label ||
          role.code ||
          ""
        : role || "";

    return String(raw || "User")
      .replace(/[_-]+/g, " ")
      .replace(/\s+/g, " ")
      .trim()
      .replace(/\b\w/g, (character) =>
        character.toUpperCase()
      );
  }

  function resolvePrimaryRole(user) {
    const contextRoles =
      window.AuthContext?.getRoles?.() || [];

    const userRoles = Array.isArray(user?.roles)
      ? user.roles
      : [];

    return (
      contextRoles[0] ||
      userRoles[0] ||
      user?.main_role ||
      user?.role_name ||
      user?.role ||
      "User"
    );
  }

  function resolveDisplayName(user) {
    const fullName = [
      user?.first_name,
      user?.last_name,
    ]
      .filter(Boolean)
      .join(" ")
      .trim();

    return (
      fullName ||
      user?.name ||
      user?.full_name ||
      user?.username ||
      "User"
    );
  }

  function initialsFor(value) {
    return String(value || "User")
      .trim()
      .split(/\s+/)
      .filter(Boolean)
      .slice(0, 2)
      .map((part) => part.charAt(0))
      .join("")
      .toUpperCase() || "U";
  }

  function setText(selector, value) {
    const element = $(selector);

    if (element) {
      element.textContent = value;
    }
  }

  function initializeHeaderUser() {
    const user = window.AuthContext?.getUser?.();

    if (!user) {
      return false;
    }

    const role = prettifyRole(resolvePrimaryRole(user));
    const displayName = resolveDisplayName(user);
    const username =
      user.username || displayName || "User";
    const initials = initialsFor(displayName);

    setText("#header-user-role", role);
    setText("#header-role-short", role);
    setText("#header-username", username);
    setText("#menu-username", displayName);
    setText(
      "#menu-user-email",
      user.email || "Signed in"
    );
    setText("#header-user-avatar", initials);
    setText("#menu-user-avatar", initials);

    return true;
  }

  async function waitForAuthenticatedContext() {
    try {
      if (window.AuthContext?.ready) {
        await window.AuthContext.ready();
      } else if (window.AuthContext?.initialize) {
        await window.AuthContext.initialize();
      }
    } catch (error) {
      console.warn(
        "[AppShell] AuthContext did not become ready:",
        error
      );
    }

    initializeHeaderUser();
  }

  function updateToggleState() {
    const toggle = $("#sidebar-toggle-button");

    if (!toggle) {
      return;
    }

    const expanded = isMobile()
      ? document.body.classList.contains(
          "sidebar-mobile-open"
        )
      : !document.body.classList.contains(
          "sidebar-collapsed"
        );

    toggle.setAttribute(
      "aria-expanded",
      String(expanded)
    );
  }

  function closeAllSubmenus(exception = null) {
    $$(".sidebar-toggle").forEach((toggle) => {
      const selector = toggle.dataset.submenuTarget;
      const submenu = selector ? $(selector) : null;

      if (!submenu || submenu === exception) {
        return;
      }

      submenu.classList.remove("show");
      toggle.setAttribute("aria-expanded", "false");
    });
  }

  function setDesktopCollapsed(
    collapsed,
    persist = true
  ) {
    if (isMobile()) {
      return;
    }

    document.body.classList.toggle(
      "sidebar-collapsed",
      Boolean(collapsed)
    );

    if (collapsed) {
      closeAllSubmenus();
    }

    if (persist) {
      localStorage.setItem(
        COLLAPSE_STORAGE_KEY,
        collapsed ? "1" : "0"
      );
    }

    updateToggleState();
  }

  function openMobileSidebar() {
    document.body.classList.add(
      "sidebar-mobile-open"
    );
    updateToggleState();
  }

  function closeMobileSidebar() {
    document.body.classList.remove(
      "sidebar-mobile-open"
    );
    updateToggleState();
  }

  function toggleSidebar() {
    if (isMobile()) {
      document.body.classList.contains(
        "sidebar-mobile-open"
      )
        ? closeMobileSidebar()
        : openMobileSidebar();

      return;
    }

    setDesktopCollapsed(
      !document.body.classList.contains(
        "sidebar-collapsed"
      )
    );
  }

  function toggleSubmenu(toggle) {
    const selector = toggle.dataset.submenuTarget;
    const submenu = selector ? $(selector) : null;

    if (!submenu) {
      return;
    }

    if (
      !isMobile() &&
      document.body.classList.contains(
        "sidebar-collapsed"
      )
    ) {
      setDesktopCollapsed(false);

      window.setTimeout(
        () => openSubmenu(toggle, submenu),
        180
      );

      return;
    }

    const shouldOpen =
      !submenu.classList.contains("show");

    if (shouldOpen) {
      openSubmenu(toggle, submenu);
    } else {
      submenu.classList.remove("show");
      toggle.setAttribute(
        "aria-expanded",
        "false"
      );
    }
  }

  function openSubmenu(toggle, submenu) {
    closeAllSubmenus(submenu);
    submenu.classList.add("show");
    toggle.setAttribute("aria-expanded", "true");
  }

  function normalizeRoute(value) {
    if (!value) {
      return "";
    }

    try {
      const parsed = new URL(
        String(value),
        window.location.origin
      );

      return (
        parsed.searchParams.get("route") ||
        String(value)
          .replace(/^\/+/, "")
          .split("?")[0]
      );
    } catch {
      return String(value)
        .replace(/^\/+/, "")
        .split("?")[0];
    }
  }

  function currentRoute() {
    return (
      normalizeRoute(window.REQUESTED_ROUTE) ||
      new URLSearchParams(
        window.location.search
      ).get("route") ||
      ""
    );
  }

  function markActiveRoute() {
    const route = currentRoute();

    $$(".sidebar-link").forEach((link) => {
      const active =
        normalizeRoute(link.dataset.route) === route;

      link.classList.toggle("active", active);

      if (active) {
        link.setAttribute("aria-current", "page");

        const submenu = link.closest(
          ".app-sidebar-submenu"
        );

        if (submenu) {
          const toggle = $(
            `.sidebar-toggle[data-submenu-target="#${CSS.escape(
              submenu.id
            )}"]`
          );

          if (toggle) {
            openSubmenu(toggle, submenu);
          }
        }
      } else {
        link.removeAttribute("aria-current");
      }
    });
  }

  function escapeHtml(value) {
    const node = document.createElement("div");
    node.textContent = String(value ?? "");
    return node.innerHTML;
  }

  function sidebarSearchItems() {
    return $$(".sidebar-link")
      .map((link) => ({
        label:
          $(".sidebar-text", link)?.textContent?.trim() ||
          link.title ||
          "",
        route: link.dataset.route || "",
        icon:
          $("i", link)?.className ||
          "bi bi-arrow-right",
      }))
      .filter((item) => item.label && item.route);
  }

  function renderSearch(query) {
    const results = $("#global-search-results");

    if (!results) {
      return;
    }

    const term = String(query || "")
      .trim()
      .toLowerCase();

    if (!term) {
      results.innerHTML =
        '<p class="text-muted mb-0">Start typing to search available navigation pages.</p>';
      return;
    }

    const matches = sidebarSearchItems()
      .filter((item) =>
        item.label.toLowerCase().includes(term)
      )
      .slice(0, 12);

    results.innerHTML = matches.length
      ? matches
          .map(
            (item) => `
              <a
                href="#"
                class="app-search-result"
                data-search-route="${escapeHtml(
                  item.route
                )}"
              >
                <i class="${escapeHtml(
                  item.icon
                )}"></i>
                <span>${escapeHtml(
                  item.label
                )}</span>
              </a>
            `
          )
          .join("")
      : '<p class="text-muted mb-0">No matching pages found.</p>';
  }

  function setTheme(dark) {
    document.body.classList.toggle(
      "app-dark",
      dark
    );

    localStorage.setItem(
      THEME_STORAGE_KEY,
      dark ? "dark" : "light"
    );

    const icon = $("#header-theme-button i");

    if (icon) {
      icon.className = dark
        ? "bi bi-sun"
        : "bi bi-moon-stars";
    }
  }

  function showLogoutModal() {
    const element = $("#logoutModal");

    if (element && window.bootstrap?.Modal) {
      window.bootstrap.Modal
        .getOrCreateInstance(element)
        .show();
    }
  }

  async function executeLogout() {
    const button = $("#confirmLogoutBtn");

    button && (button.disabled = true);
    $("#logoutBtnText")?.classList.add("d-none");
    $("#logoutSpinner")?.classList.remove(
      "d-none"
    );

    try {
      await window.API?.auth?.logout?.();
    } catch (error) {
      console.warn(
        "[AppShell] Server logout failed:",
        error
      );
    } finally {
      window.AuthContext?.clearUser?.();
      window.location.replace(
        `${window.APP_BASE || ""}/index.php`
      );
    }
  }

  function goToProfile() {
    window.location.href =
      `${window.APP_BASE || ""}/home.php?route=profile`;
  }

  function handleDocumentClick(event) {
    const sidebarToggle = event.target.closest(
      ".sidebar-toggle"
    );

    if (sidebarToggle) {
      event.preventDefault();
      toggleSubmenu(sidebarToggle);
      return;
    }

    const searchResult = event.target.closest(
      "[data-search-route]"
    );

    if (searchResult) {
      event.preventDefault();
      window.navigateToRoute?.(
        searchResult.dataset.searchRoute
      );
    }
  }

  function bindEvents() {
    $("#sidebar-toggle-button")?.addEventListener(
      "click",
      toggleSidebar
    );

    $("#sidebar-mobile-close")?.addEventListener(
      "click",
      closeMobileSidebar
    );

    $("#sidebar-overlay")?.addEventListener(
      "click",
      closeMobileSidebar
    );

    $("#header-search-button")?.addEventListener(
      "click",
      () => {
        const panel = $("#globalSearchPanel");

        if (
          panel &&
          window.bootstrap?.Offcanvas
        ) {
          window.bootstrap.Offcanvas
            .getOrCreateInstance(panel)
            .show();

          window.setTimeout(
            () => $("#global-search-input")?.focus(),
            180
          );
        }
      }
    );

    $("#global-search-input")?.addEventListener(
      "input",
      (event) => renderSearch(event.target.value)
    );

    $("#header-theme-button")?.addEventListener(
      "click",
      () =>
        setTheme(
          !document.body.classList.contains(
            "app-dark"
          )
        )
    );

    $("#mark-all-notifications-read")
      ?.addEventListener("click", () => {
        const badge = $(
          "#header-notification-count"
        );

        if (badge) {
          badge.textContent = "0";
          badge.classList.add("d-none");
        }
      });

    document.addEventListener(
      "click",
      handleDocumentClick
    );

    document.addEventListener(
      "authchanged",
      initializeHeaderUser
    );

    window.addEventListener(
      "authchanged",
      initializeHeaderUser
    );

    window.addEventListener(
      "kingsway:ready",
      () => {
        initializeHeaderUser();
        markActiveRoute();
      }
    );

    window.addEventListener("resize", () => {
      clearTimeout(resizeTimer);

      resizeTimer = window.setTimeout(() => {
        if (!isMobile()) {
          closeMobileSidebar();
        }

        updateToggleState();
      }, 120);
    });

    document.addEventListener(
      "keydown",
      (event) => {
        if (event.key === "Escape") {
          closeMobileSidebar();
        }

        if (
          (event.ctrlKey || event.metaKey) &&
          event.key.toLowerCase() === "k"
        ) {
          event.preventDefault();
          $("#header-search-button")?.click();
        }
      }
    );
  }

  function refresh() {
    initializeHeaderUser();
    markActiveRoute();
    updateToggleState();
  }

  function initialize() {
    if (initialized) {
      refresh();
      return;
    }

    initialized = true;

    if (!isMobile()) {
      setDesktopCollapsed(
        localStorage.getItem(
          COLLAPSE_STORAGE_KEY
        ) === "1",
        false
      );
    }

    setTheme(
      localStorage.getItem(THEME_STORAGE_KEY) ===
        "dark"
    );

    bindEvents();
    refresh();
    void waitForAuthenticatedContext();
  }

  window.KingswayShell = {
    initialize,
    refresh,
    toggleSidebar,
    openMobileSidebar,
    closeMobileSidebar,
    initializeHeaderUser,
    markActiveRoute,
  };

  window.toggleSidebar = toggleSidebar;
  window.showLogoutModal = showLogoutModal;
  window.handleLogout = showLogoutModal;
  window.executeLogout = executeLogout;
  window.goToProfile = goToProfile;

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
