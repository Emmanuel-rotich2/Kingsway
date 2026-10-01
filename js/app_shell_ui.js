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

    const hr = new Date().getHours();
    const greet = hr < 12 ? 'Good morning' : hr < 17 ? 'Good afternoon' : 'Good evening';
    const firstName = user.first_name || displayName.split(' ')[0] || 'User';
    setText("#header-greeting", greet + ', ' + firstName + '!');

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

  // ----------------------------------------------------------------------
  // Header notifications
  // ----------------------------------------------------------------------
  const NOTIFICATION_ICON_MAP = {
    message: {
      icon: "bi-chat-dots",
      cls: "bg-primary-subtle text-primary",
    },
    notification: {
      icon: "bi-megaphone",
      cls: "bg-warning-subtle text-warning-emphasis",
    },
    event: {
      icon: "bi-calendar-event",
      cls: "bg-success-subtle text-success",
    },
    reminder: {
      icon: "bi-alarm",
      cls: "bg-info-subtle text-info",
    },
  };

  function timeAgo(value) {
    if (!value) {
      return "";
    }

    const parsed = new Date(
      String(value).replace(" ", "T")
    );

    if (Number.isNaN(parsed.getTime())) {
      return "";
    }

    const seconds = Math.floor(
      (Date.now() - parsed.getTime()) / 1000
    );

    if (seconds < 60) {
      return "just now";
    }

    const minutes = Math.floor(seconds / 60);
    if (minutes < 60) {
      return `${minutes}m ago`;
    }

    const hours = Math.floor(minutes / 60);
    if (hours < 24) {
      return `${hours}h ago`;
    }

    const days = Math.floor(hours / 24);
    return `${days}d ago`;
  }

  function renderNotifications(data) {
    const badge = $("#header-notification-count");
    const list = $("#header-notification-list");

    if (!badge && !list) {
      return;
    }

    const items = Array.isArray(data?.items)
      ? data.items
      : [];
    const unread = Number(data?.unread_count) || 0;

    if (badge) {
      badge.textContent = String(unread);
      badge.classList.toggle("d-none", unread === 0);
    }

    if (!list) {
      return;
    }

    if (!items.length) {
      list.innerHTML =
        '<div class="app-notification-empty">You\'re all caught up.</div>';
      return;
    }

    list.innerHTML = items
      .map((item) => {
        const meta =
          NOTIFICATION_ICON_MAP[item.type] ||
          NOTIFICATION_ICON_MAP.notification;
        const title = escapeHtml(
          item.title || "Notification"
        );
        const message = escapeHtml(item.message || "");
        const when = timeAgo(item.created_at);
        const category = item.category || item.type || "notification";
        const context = item.context || "";
        const readCls = item.read
          ? " app-notification-item--read"
          : "";
        const unreadCls = item.unread
          ? " app-notification-item--unread"
          : "";
        const pill =
          item.badge && item.badge > 0
            ? `<span class="app-notification-pill">${Number(
                item.badge
              )}</span>`
            : "";
        const metaLine = [
          when,
          context,
          category,
        ]
          .filter(Boolean)
          .join(" · ");

        const action = item.action_url || "";
        const notificationId = String(item.id || "").replace("notification-", "");
        const stateButton = item.type === "notification" && notificationId
          ? `<button type="button" class="app-notification-state" data-notification-id="${notificationId}" data-read="${item.unread ? "true" : "false"}" aria-label="${item.unread ? "Mark as read" : "Mark as unread"}">${item.unread ? "Mark read" : "Mark unread"}</button>`
          : "";
        return `
          <div class="app-notification-item${unreadCls}${readCls}" ${action ? `data-notification-url="${escapeHtml(action)}" role="link" tabindex="0"` : ""}>
            <span class="app-notification-icon ${meta.cls}">
              <i class="bi ${meta.icon}"></i>
            </span>
            <div class="app-notification-body">
              <strong>${title}${pill}</strong>
              ${message ? `<small>${message}</small>` : ""}
              <small class="text-muted">${escapeHtml(
                metaLine
              )}</small>
              ${stateButton}
            </div>
          </div>`;
      })
      .join("");
  }

  const NOTIFICATIONS_MIN_INTERVAL_MS = 30000;
  let notificationsFetchInFlight = false;
  let notificationsLastFetchedAt = 0;

  async function fetchNotifications({ force = false } = {}) {
    // Notification loading is a protected operation. Every caller (initial
    // shell boot, authchanged, kingsway:ready, and manual refresh) must wait
    // for the single AuthContext boot promise before checking the user or
    // sending the request. This prevents the initial 401 race during silent
    // refresh-token restoration.
    try {
      if (window.AuthContext?.ready) {
        await window.AuthContext.ready();
      }
    } catch (error) {
      console.warn("[AppShell] AuthContext is not ready for notifications:", error);
      return;
    }

    const hasUser = Boolean(
      window.AuthContext?.getUser?.()
    );

    if (!hasUser || typeof window.callAPI !== "function") {
      return;
    }

    // De-duplicate concurrent callers and rate-limit automatic fetches so
    // the boot sequence (initialize + authchanged + kingsway:ready) and
    // token-refresh cycles don't spam the endpoint.
    const now = Date.now();
    if (
      notificationsFetchInFlight ||
      (!force &&
        now - notificationsLastFetchedAt <
          NOTIFICATIONS_MIN_INTERVAL_MS)
    ) {
      return;
    }

    notificationsFetchInFlight = true;

    try {
      const data = await window.callAPI(
        "notifications",
        "GET"
      );
      notificationsLastFetchedAt = Date.now();
      renderNotifications(data);
    } catch (error) {
      console.warn(
        "[AppShell] Failed to load notifications:",
        error
      );
    } finally {
      notificationsFetchInFlight = false;
    }
  }

  async function markAllNotificationsRead() {
    if (typeof window.callAPI !== "function") {
      return;
    }

    try {
      await window.callAPI(
        "notifications/mark-all-read",
        "POST",
        {}
      );
      await fetchNotifications({ force: true });
    } catch (error) {
      console.warn(
        "[AppShell] Failed to mark notifications read:",
        error
      );
    }
  }

  async function updateNotificationState(id, read) {
    try {
      await window.callAPI(`notifications/${encodeURIComponent(id)}`, "PUT", { read });
      await fetchNotifications({ force: true });
    } catch (error) {
      console.warn("[AppShell] Failed to update notification state:", error);
    }
  }

  function canBroadcastNotifications() {
    const user = window.AuthContext?.getUser?.() || {};
    const role = String(
      user.role_name || user.role || ""
    ).toLowerCase();

    if (
      ["system admin", "director", "school administrator"].includes(
        role
      )
    ) {
      return true;
    }

    const permissions =
      window.AuthContext?.getPermissions?.() || [];

    return [
      "notifications_push",
      "notifications_manage",
      "communications_all_permissions",
      "communications_manage",
      "system_admin",
    ].some((permission) =>
      permissions.includes(permission)
    );
  }

  function initializeNotificationBroadcast() {
    const toggle = $("#notification-broadcast-toggle");
    const wrap = $("#notification-broadcast-wrap");

    if (!toggle || !wrap || !canBroadcastNotifications()) {
      return;
    }

    toggle.classList.remove("d-none");

    toggle.addEventListener("click", () => {
      wrap.classList.toggle("d-none");
    });

    $("#notification-broadcast-form")?.addEventListener(
      "submit",
      async (event) => {
        event.preventDefault();

        const title =
          $("#nb-title")?.value.trim() || "";
        const message =
          $("#nb-message")?.value.trim() || "";

        if (!title || !message) {
          window.showNotification?.(
            "Title and message are required",
            "warning"
          );
          return;
        }

        const payload = {
          title,
          message,
          type: $("#nb-type")?.value || "announcement",
          audience: $("#nb-audience")?.value || "all_staff",
        };

        const button = $("#nb-submit");
        if (button) {
          button.disabled = true;
        }

        try {
          await window.callAPI(
            "notifications/push",
            "POST",
            payload
          );
          if ($("#nb-title")) {
            $("#nb-title").value = "";
          }
          if ($("#nb-message")) {
            $("#nb-message").value = "";
          }
          wrap.classList.add("d-none");
          window.showNotification?.("Broadcast sent", "success");
          await fetchNotifications();
        } catch (error) {
          console.warn("[AppShell] Broadcast failed:", error);
          window.showNotification?.(
            error.message || "Broadcast failed",
            "danger"
          );
        } finally {
          if (button) {
            button.disabled = false;
          }
        }
      }
    );
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
    void fetchNotifications();
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

  const aiWorkspaceRoutes = {
    admissions: "manage_students_admissions",
    academics: "schemes_of_work",
    attendance: "daily_attendance",
    finance: "finance/admin_finance",
    communications: "manage_communications",
    reports: "dashboard",
    boarding: "boarding_reports",
    counseling: "student_counseling",
    health: "health_reports",
    activities: "activity_reports",
    curriculum: "curriculum_cbc",
    inventory: "manage_inventory",
    catering: "catering_boarding_students",
    maintenance: "maintenance_mode",
    transport: "transport",
    staff: "staff",
    system: "system_diagnostics",
  };

// Proactive workspace briefing (page-load trigger). Three tiers, no blocking:
// 1) the persisted snapshot (memory LRU -> IndexedDB) renders instantly, even
//    offline, so opening the panel never waits on the network;
// 2) DataStore revalidates in the background (stale-while-revalidate) and
//    rewrites the snapshot, so the next open is already current;
// 3) while the server is still generating, we keep the snapshot visible and
//    poll a bounded number of times instead of showing a spinner.
  let aiBriefingTimer = null;
  let aiBriefingSnapshot = null;

  const AI_BRIEFING_TTL = 15 * 60 * 1000;

  async function fetchWorkspaceBriefing(route) {
    const payload = await window.API?.dashboard?.getWorkspaceBriefing?.(route);
    return payload?.data !== undefined ? payload.data : payload;
  }

  async function loadWorkspaceBriefing(attempt = 0) {
    const host = $("#global-ai-assistant-briefing");
    if (!host) return;
    const route = currentRoute() || "dashboard";
    const storeKey = "ai_workspace_briefing";

    if (attempt === 0 && !host.querySelector("[data-ai-briefing-body]")) {
      // Instant first paint from the persisted snapshot, before any network.
      window.DataStore?.peek?.(storeKey, {
        storeName: "ai_workspace_cache",
        ttl: AI_BRIEFING_TTL,
        params: { route },
      }).then((snapshot) => {
        if (snapshot?.status === "ready" && snapshot?.briefing && !host.querySelector("[data-ai-briefing-body]")) {
          host.innerHTML = renderWorkspaceBriefing(snapshot.briefing, true);
        }
      });
      host.innerHTML =
        '<div class="text-muted small"><span class="spinner-border spinner-border-sm me-2" role="status"></span>Reviewing this workspace…</div>';
    }

    try {
      const result = window.DataStore?.get
        ? await window.DataStore.get(storeKey, {
            strategy: "stale-while-revalidate",
            storeName: "ai_workspace_cache",
            ttl: AI_BRIEFING_TTL,
            params: { route },
            fetcher: () => fetchWorkspaceBriefing(route),
          })
        : await fetchWorkspaceBriefing(route);
      if (result?.status === "ready" && result?.briefing) {
        host.innerHTML = renderWorkspaceBriefing(result.briefing, false);
        return;
      }
      if (attempt < 5) {
        host.innerHTML = aiBriefingSnapshot
          ? renderWorkspaceBriefing(aiBriefingSnapshot, true, true)
          : '<div class="text-muted small"><span class="spinner-border spinner-border-sm me-2" role="status"></span>Preparing your workspace briefing…</div>';
        if (aiBriefingTimer) clearTimeout(aiBriefingTimer);
        aiBriefingTimer = setTimeout(() => {
          void loadWorkspaceBriefing(attempt + 1);
        }, 4000 * (attempt + 1));
        return;
      }
      host.innerHTML =
        '<div class="alert alert-secondary small mb-0">Your workspace briefing is still being prepared. It will be ready shortly.</div>';
    } catch (briefingError) {
      host.innerHTML =
        '<div class="alert alert-secondary small mb-0">Workspace briefing is unavailable right now. You can still ask the assistant a question below.</div>';
    }
  }

  function renderWorkspaceBriefing(briefing, fromSnapshot, updating) {
    if (briefing && !updating) aiBriefingSnapshot = briefing;
    const findings = Array.isArray(briefing?.findings) ? briefing.findings : [];
    const engine = String(briefing?.engine || "deterministic");
    const generatedAt = String(briefing?.generated_at || "");
    const parts = [];
    parts.push('<div data-ai-briefing-body>');
    if (updating) {
      parts.push(
        '<div class="alert alert-info py-1 px-2 small mb-2"><span class="spinner-border spinner-border-sm me-1" role="status"></span>Refreshing this workspace review…</div>'
      );
    }
    parts.push(
      '<div class="card border-0 bg-light-subtle mb-2"><div class="card-body p-3">'
    );
    parts.push(
      `<div class="d-flex align-items-center justify-content-between mb-1">
        <span class="badge text-bg-light border text-uppercase"><i class="bi bi-clipboard-data me-1"></i>Workspace review</span>
        <small class="text-muted">${escapeHtml(engine.replace(/-/g, " "))}</small>
      </div>`
    );
    parts.push(
      `<h6 class="mb-1">${escapeHtml(briefing?.headline || "Workspace review")}</h6>`
    );
    if (briefing?.summary) {
      parts.push(
        `<p class="small mb-0 text-muted">${escapeHtml(briefing.summary)}</p>`
      );
    }
    parts.push("</div></div>");

    if (findings.length) {
      findings.forEach((finding) => {
        const severity = ["info", "warning", "critical"].includes(
          String(finding?.severity)
        )
          ? String(finding.severity)
          : "info";
        parts.push(
          `<div class="ai-briefing-finding border rounded p-2 mb-2 bg-white" data-severity="${escapeHtml(severity)}">`
        );
        parts.push(
          `<div class="d-flex justify-content-between align-items-start gap-2">
            <strong class="small">${escapeHtml(finding?.title || "Finding")}</strong>
            <span class="badge text-bg-${severity === "critical" ? "danger" : severity === "warning" ? "warning" : "info"} text-uppercase">${escapeHtml(severity)}</span>
          </div>`
        );
        if (finding?.root_cause) {
          parts.push(
            `<p class="small mb-1 mt-1"><span class="text-muted fw-semibold">Likely cause:</span> ${escapeHtml(finding.root_cause)}</p>`
          );
        }
        if (finding?.suggested_action) {
          parts.push(
            `<p class="small mb-0"><span class="text-muted fw-semibold">Suggested:</span> ${escapeHtml(finding.suggested_action)}</p>`
          );
        }
        parts.push("</div>");
      });
    } else {
      parts.push(
        '<div class="alert alert-success small mb-2"><i class="bi bi-check-circle me-1"></i>No exceptions detected in this workspace.</div>'
      );
    }

    if (generatedAt) {
      const ageMinutes = Math.max(
        0,
        Math.round((Date.now() - new Date(generatedAt).getTime()) / 60000)
      );
      parts.push(
        `<div class="d-flex justify-content-between align-items-center mt-2">
          <small class="text-muted">${fromSnapshot && ageMinutes >= 15 ? "Saved review from" : "Updated"} ${escapeHtml(new Date(generatedAt).toLocaleString())}</small>
          <button type="button" class="btn btn-sm btn-link p-0" id="global-ai-assistant-briefing-refresh">Refresh</button>
        </div>`
      );
    }
    parts.push("</div>");
    $("#global-ai-assistant-briefing-refresh")?.addEventListener("click", () => {
      void loadWorkspaceBriefing(0);
    });
    return parts.join("");
  }

  async function loadAiAssistantCatalog() {
    const content = $("#global-ai-assistant-content");
    const context = $("#global-ai-assistant-context");
    if (!content) return;

    const route = String(window.REQUESTED_ROUTE || "");
    if (context) {
      context.textContent = route
        ? `Current workspace: ${route.replace(/[_/-]+/g, " ")}`
        : "Current workspace: dashboard";
    }

    try {
      // Persisted catalogue (IndexedDB + memory LRU) so opening the panel
      // paints from the snapshot; DataStore revalidates in the background
      // instead of blocking the click on a PHP round trip.
      const catalogUrl = `/dashboard/ai-assistant-catalog?route=${encodeURIComponent(route)}`;
      const payload = window.DataStore?.get
        ? await window.DataStore.get("ai_assistant_catalog", {
            strategy: "stale-while-revalidate",
            storeName: "ai_workspace_cache",
            ttl: 10 * 60 * 1000,
            params: { route },
            fetcher: () => window.API?.apiCall?.(catalogUrl, "GET"),
          })
        : await window.API?.apiCall?.(catalogUrl, "GET");
      const workflows = Array.isArray(payload?.workflows)
        ? payload.workflows
        : [];
      const queryForm = $("#global-ai-assistant-query");
      const canAskReports = workflows.some((workflow) => workflow.id === "system.nlq_query");
      // The governed agent assistant answers process/guidance questions for
      // every authenticated staff member, so the form stays available even
      // when this role has no report permissions.
      if (queryForm) queryForm.hidden = false;
      const suggestions = $("#global-ai-assistant-suggestions");
      if (suggestions && workflows.length) {
        const questions = workflows.flatMap((workflow) => Array.isArray(workflow.suggested_questions) ? workflow.suggested_questions : []).slice(0, 5);
        suggestions.innerHTML = questions.map((question) => `<button type="button" class="btn btn-sm btn-outline-secondary ai-question-suggestion">${escapeHtml(question)}</button>`).join("");
        suggestions.querySelectorAll(".ai-question-suggestion").forEach((button) => button.addEventListener("click", () => {
          const input = $("#global-ai-assistant-question");
          if (input) { input.value = button.textContent; input.focus(); }
        }));
      }

      if (!workflows.length) {
        content.innerHTML = canAskReports
          ? '<p class="text-muted">No assistance is currently available for your permissions.</p>'
          : '<p class="text-muted">No module assistance matches this workspace yet, but you can still ask a question above — the assistant answers school-process and guidance questions for every staff member.</p>';
        return;
      }

      content.innerHTML = workflows.map((workflow) => {
        const domain = String(workflow.domain || "system");
        const target = aiWorkspaceRoutes[domain] || route || "dashboard";
        const implemented = workflow.status === "implemented";
        const contextual = workflow.contextual !== false;
        const status = implemented ? "Available" : "Not yet available";
        const contextualBadge = contextual
          ? '<span class="badge text-bg-success-subtle text-success-emphasis border border-success-subtle me-1">For this workspace</span>'
          : "";
        const action = implemented
          ? `<a class="btn btn-sm btn-outline-success" href="${escapeHtml((window.APP_BASE || "") + "/home.php?route=" + target)}">Open workspace</a>`
          : '<span class="small text-muted">This capability is registered in the roadmap but is not yet enabled.</span>';
        return `<div class="card border-0 shadow-sm mb-3">
          <div class="card-body">
            <div class="d-flex justify-content-between align-items-start gap-2">
              <h6 class="mb-1">${contextualBadge}${escapeHtml(workflow.summary || workflow.id)}</h6>
              <span class="badge text-bg-light">${escapeHtml(status)}</span>
            </div>
            <p class="small text-muted mb-2">${escapeHtml(domain)} · ${escapeHtml(workflow.action_level || "assist")}</p>
            ${action}
          </div>
        </div>`;
      }).join("");
    } catch (error) {
      content.innerHTML = '<p class="text-danger small">Contextual assistance is temporarily unavailable. Continue using the existing workflow.</p>';
    }
  }

  let aiAssistantQueryBound = false;

  function initAiAssistantQuery() {
    const form = $("#global-ai-assistant-query");
    if (!form || aiAssistantQueryBound) return;
    aiAssistantQueryBound = true;
    form.addEventListener("submit", async (event) => {
      event.preventDefault();
      const input = $("#global-ai-assistant-question");
      const answer = $("#global-ai-assistant-answer");
      const question = String(input?.value || "").trim();
      if (!question || !answer) return;
      answer.innerHTML =
        '<div class="text-muted small"><span class="spinner-border spinner-border-sm me-2" role="status"></span>Routing your question to the right agent…</div>';

      const stream = window.API?.dashboard?.agentAssistStream;
      if (typeof stream === "function") {
        // Streamed path: words appear as they are produced instead of after
        // the whole answer has been generated.
        const bubble = document.createElement("div");
        bubble.className = "ai-stream-bubble";
        answer.innerHTML = "";
        answer.appendChild(bubble);
        let text = "";
        try {
          const handle = stream(
            question,
            String(window.REQUESTED_ROUTE || ""),
            "dashboard",
            {
              onStatus: () => {
                bubble.innerHTML =
                  '<div class="text-muted small"><span class="spinner-border spinner-border-sm me-2" role="status"></span>Checking governed school data…</div>';
              },
              onDelta: (chunk) => {
                text += chunk;
                // Rendered as text nodes, never HTML: streamed content is
                // model output and must not be able to inject markup.
                bubble.textContent = text;
              },
              onError: (message) => {
                if (!text) {
                  bubble.innerHTML = `<div class="alert alert-warning small mb-0">${escapeHtml(
                    message || "The assistant could not answer right now."
                  )}</div>`;
                }
              },
            },
          );
          const result = await handle.promise;
          if (result) {
            answer.innerHTML = renderAiAgentAnswer(result);
            handle.cancel();
          }
          if (answer.innerHTML === "") return;
        } catch (streamError) {
          answer.innerHTML = "";
        }
        if (answer.innerHTML !== "") return;
      }

      try {
        const payload = await window.API?.dashboard?.agentAssist?.(
          question,
          String(window.REQUESTED_ROUTE || ""),
          "dashboard"
        );
        const result = payload?.data !== undefined ? payload.data : payload;
        answer.innerHTML = renderAiAgentAnswer(result);
      } catch (agentError) {
        // Fall back to the governed reports NLQ assistant when the agent
        // layer is unavailable (older deployments, provider outage).
        try {
          const payload = await window.API?.reports?.askNlq?.(question);
          const result = payload?.data !== undefined ? payload.data : payload;
          answer.innerHTML = renderAiAnswer(result);
        } catch (error) {
          answer.innerHTML =
            '<div class="alert alert-warning small mb-0">The assistant could not answer right now. Open the governed reports directly.</div>';
        }
      }
    });
  }

  function renderAiAgentAnswer(result) {
    if (!result || typeof result !== "object") {
      return '<div class="alert alert-warning small mb-0">No answer was returned.</div>';
    }
    if (result.status === "unavailable") {
      return `<div class="alert alert-warning small mb-0">${escapeHtml(
        result.answer?.body || "The assistant is not available right now."
      )}</div>`;
    }
    if (result.status !== "answered" || !result.answer) {
      return '<div class="alert alert-info small mb-0">The assistant could not answer that question.</div>';
    }
    const agent = result.agent || {};
    const toolsUsed = Array.isArray(result.tools_used) ? result.tools_used : [];
    const nextSteps = Array.isArray(result.answer.next_steps) && result.answer.next_steps.length
      ? `<ul class="small mb-2">${result.answer.next_steps
          .map((step) => `<li>${escapeHtml(String(step))}</li>`)
          .join("")}</ul>`
      : "";
    const tools = toolsUsed.length
      ? `<span class="small text-muted">Grounded via: ${toolsUsed
          .map((tool) => escapeHtml(String(tool)))
          .join(", ")}</span>`
      : "";
    const escalation = result.answer.escalation_required
      ? '<div class="alert alert-warning small mt-2 mb-0">This needs a human decision — the assistant cannot act on it.</div>'
      : "";
    const followUps = Array.isArray(result.answer.suggested_questions) && result.answer.suggested_questions.length
      ? `<div class="d-flex flex-wrap gap-1 mt-2">${result.answer.suggested_questions
          .map(
            (question) =>
              `<button type="button" class="btn btn-sm btn-outline-secondary ai-followup-question">${escapeHtml(
                String(question)
              )}</button>`
          )
          .join("")}</div>`
      : "";
    const rendered = `<div class="card border-0 shadow-sm mb-3"><div class="card-body">
      <div class="d-flex justify-content-between align-items-start gap-2 mb-2">
        <h6 class="mb-0">${escapeHtml(result.answer.title || "Assistant")}</h6>
        <span class="badge text-bg-light">${escapeHtml(agent.name || "Agent")}</span>
      </div>
      <p class="small mb-2" style="white-space: pre-line;">${escapeHtml(result.answer.body || "")}</p>
      ${nextSteps}
      ${tools}
      ${escalation}
      ${followUps}
    </div></div>`;
    requestAnimationFrame(() => {
      document
        .querySelectorAll("#global-ai-assistant-answer .ai-followup-question")
        .forEach((button) =>
          button.addEventListener("click", () => {
            const input = $("#global-ai-assistant-question");
            if (input) {
              input.value = button.textContent;
              input.focus();
            }
          })
        );
    });
    return rendered;
  }

  function renderAiAnswer(result) {
    if (!result || typeof result !== "object") {
      return '<div class="alert alert-warning small mb-0">No answer was returned.</div>';
    }
    if (result.status === "unavailable") {
      return `<div class="alert alert-warning small mb-0">${escapeHtml(
        result.message || "The assistant is not available right now."
      )}</div>`;
    }
    if (result.status !== "answered") {
      return `<div class="alert alert-info small mb-0">${escapeHtml(
        result.message || "That question is outside the reports available to you."
      )}</div>`;
    }
    const answer = result.answer || {};
    const explanation = result.explanation
      ? `<p class="small mb-2">${escapeHtml(result.explanation)}</p>`
      : "";
    const warnings =
      Array.isArray(answer.warnings) && answer.warnings.length
        ? `<div class="alert alert-warning small mb-2">${answer.warnings
            .map((warning) => escapeHtml(String(warning)))
            .join("<br>")}</div>`
        : "";
    const limited = answer.preview_limited
      ? '<p class="small text-muted mb-1">Showing the first rows. Open the report for the full result.</p>'
      : "";
    const link = `<a class="btn btn-sm btn-outline-success" href="${escapeHtml(
      (window.APP_BASE || "") + "/home.php?route=governed_report"
    )}">Open report</a>`;
    return `<div class="card border-0 shadow-sm mb-3"><div class="card-body">
      <div class="d-flex justify-content-between align-items-start gap-2 mb-2">
        <h6 class="mb-0">${escapeHtml(answer.report_title || answer.report_code || "Answer")}</h6>
        <span class="badge text-bg-light">${escapeHtml(answer.report_code || "")}</span>
      </div>
      ${explanation}
      ${warnings}
      ${renderAiSummary(answer.summary)}
      ${limited}
      ${renderAiRows(answer)}
      <div class="d-flex justify-content-between align-items-center gap-2">
        <small class="text-muted">${escapeHtml(String(answer.row_count ?? 0))} rows · as of ${escapeHtml(
          String(answer.as_of || "")
        )}</small>
        ${link}
      </div>
    </div></div>`;
  }

  function renderAiSummary(summary) {
    if (!summary || typeof summary !== "object") return "";
    const items = [];
    Object.entries(summary).forEach(([key, value]) => {
      if (value === null || value === undefined) return;
      let display;
      if (typeof value === "object") {
        const inner = Object.entries(value)
          .filter(([, entry]) => entry === null || typeof entry !== "object")
          .map(([innerKey, entry]) => `${escapeHtml(innerKey)}: ${escapeHtml(String(entry))}`)
          .join(", ");
        if (!inner) return;
        display = inner;
      } else {
        display = escapeHtml(String(value));
      }
      items.push(
        `<li class="list-group-item d-flex justify-content-between gap-3 px-0"><span class="text-muted">${escapeHtml(
          key
        )}</span><span class="fw-semibold text-end">${display}</span></li>`
      );
    });
    if (!items.length) return "";
    return `<ul class="list-group list-group-flush mb-2">${items.join("")}</ul>`;
  }

  function renderAiRows(answer) {
    const rows = Array.isArray(answer.rows) ? answer.rows : [];
    if (!rows.length) return "";
    const columns =
      Array.isArray(answer.columns) && answer.columns.length
        ? answer.columns
        : Object.keys(rows[0] || {});
    const headers = columns.map((column) =>
      column && typeof column === "object"
        ? escapeHtml(column.label || column.key || "")
        : escapeHtml(String(column))
    );
    const keys = columns.map((column) =>
      column && typeof column === "object" ? column.key || column.label : column
    );
    const body = rows
      .slice(0, 10)
      .map((row) => {
        const cells = keys.map((key) => `<td>${escapeHtml(String(row?.[key] ?? ""))}</td>`);
        return `<tr>${cells.join("")}</tr>`;
      })
      .join("");
    return `<div class="table-responsive"><table class="table table-sm table-striped mb-2"><thead><tr>${headers
      .map((header) => `<th>${header}</th>`)
      .join("")}</tr></thead><tbody>${body}</tbody></table></div>`;
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

  function goToAccountSettings() {
    window.location.href =
      `${window.APP_BASE || ""}/home.php?route=account_settings`;
  }

  function handleDocumentClick(event) {
    const stateButton = event.target.closest(".app-notification-state");
    if (stateButton) {
      event.preventDefault();
      event.stopPropagation();
      void updateNotificationState(
        stateButton.dataset.notificationId,
        stateButton.dataset.read !== "true"
      );
      return;
    }

    const notification = event.target.closest("[data-notification-url]");
    if (notification) {
      event.preventDefault();
      window.location.href = notification.dataset.notificationUrl;
      return;
    }

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

    $("#globalAiAssistantPanel")?.addEventListener(
      "show.bs.offcanvas",
      () => {
        void loadAiAssistantCatalog();
        void loadWorkspaceBriefing();
        initAiAssistantQuery();
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
      ?.addEventListener("click", markAllNotificationsRead);

    document.addEventListener(
      "click",
      handleDocumentClick
    );

    document.getElementById("account-settings-button")?.addEventListener(
      "click",
      goToAccountSettings
    );

    const onAuthReady = () => {
      initializeHeaderUser();
      initializeNotificationBroadcast();
      void fetchNotifications();
    };

    document.addEventListener("authchanged", onAuthReady);

    window.addEventListener("authchanged", onAuthReady);

    window.addEventListener(
      "kingsway:ready",
      () => {
        initializeHeaderUser();
        markActiveRoute();
        void fetchNotifications();
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
    initializeNotificationBroadcast();
    void fetchNotifications();
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
  // Compatibility for old inline links/bookmarks. Both names now resolve to
  // the one unified account centre and are not exposed as separate menu items.
  window.goToProfile = goToAccountSettings;
  window.goToAccountSettings = goToAccountSettings;

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
