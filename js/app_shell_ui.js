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

  // Realtime event targets that change what the header bell shows: internal
  // messages (conversations) and module notifications/announcements. Kept in
  // sync with RealtimeMutationPublisher's DOMAIN_TARGETS for 'communications'.
  const REALTIME_NOTIFICATION_TARGETS = new Set([
    "notifications",
    "conversations",
    "communications",
  ]);
  let notificationsRealtimeTimer = 0;

  /**
   * Refresh the header bell in response to a realtime change. Debounced so a
   * burst of mutations (e.g. a bulk announcement fan-out) collapses into a
   * single forced fetch; `force` deliberately bypasses the 30s boot rate limit
   * because this is an event-driven refresh, not a polling tick.
   */
  function onRealtimeNotificationChange(event) {
    const targets = Array.isArray(event?.detail?.targets)
      ? event.detail.targets
      : [];

    if (!targets.some((target) => REALTIME_NOTIFICATION_TARGETS.has(target))) {
      return;
    }

    if (notificationsRealtimeTimer) {
      window.clearTimeout(notificationsRealtimeTimer);
    }
    notificationsRealtimeTimer = window.setTimeout(() => {
      notificationsRealtimeTimer = 0;
      void fetchNotifications({ force: true });
    }, 1200);
  }

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
    // The governed agent answers for every authenticated staff member, so the
    // composer must stay usable even if the suggestion catalogue fails.
    const queryForm = $("#global-ai-assistant-query");
    if (queryForm) queryForm.hidden = false;

    const route = String(window.REQUESTED_ROUTE || "");
    try {
      // Persisted catalogue (IndexedDB + memory LRU) feeds the optional
      // suggested-question chips; no workflow cards are rendered.
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
      const suggestions = $("#global-ai-assistant-suggestions");
      if (suggestions && workflows.length) {
        const questions = workflows.flatMap((workflow) => Array.isArray(workflow.suggested_questions) ? workflow.suggested_questions : []).slice(0, 5);
        suggestions.innerHTML = questions.map((question) => `<button type="button" class="btn btn-sm btn-outline-secondary ai-question-suggestion">${escapeHtml(question)}</button>`).join("");
        suggestions.querySelectorAll(".ai-question-suggestion").forEach((button) => button.addEventListener("click", () => {
          const input = $("#global-ai-assistant-question");
          if (input) { input.value = button.textContent; input.focus(); }
        }));
      }
    } catch (error) {
      // Suggestion chips are optional; the composer remains available.
    }
  }

  const ASSISTANT_HISTORY_KEY = "kw_staff_assistant_history";

  // Session state
  let currentSessionId = null;
  let currentSessionMessages = [];
  let sessionsCache = [];
  let assistantGenerating = false;
  let activeAssistantStream = null;
  let assistantGenerationSeq = 0;

  const ASSISTANT_ACTIVE_SESSION_KEY = "kw_staff_assistant_active_session";

  function getStoredActiveSession() {
    try {
      return localStorage.getItem(ASSISTANT_ACTIVE_SESSION_KEY) || null;
    } catch {
      return null;
    }
  }

  function rememberActiveSession(sessionId) {
    try {
      if (sessionId) localStorage.setItem(ASSISTANT_ACTIVE_SESSION_KEY, String(sessionId));
      else localStorage.removeItem(ASSISTANT_ACTIVE_SESSION_KEY);
    } catch {
      /* storage unavailable */
    }
  }

  function setAssistantGenerating(on) {
    assistantGenerating = !!on;
    const send = $("#global-ai-assistant-send");
    const input = $("#global-ai-assistant-question");
    if (send) {
      const icon = send.querySelector("i");
      if (icon) {
        icon.className = on ? "bi bi-stop-fill" : "bi bi-send-fill";
      }
      send.classList.toggle("btn-danger", on);
      send.classList.toggle("btn-success", !on);
      send.title = on ? "Stop generating" : "Send question";
      send.setAttribute("aria-label", on ? "Stop generating" : "Send question");
    }
    if (input) input.disabled = on;
    if (on) {
      assistantGenerationSeq += 1;
    }
  }

  function stopAssistantGeneration() {
    if (!assistantGenerating) return;
    assistantGenerating = false;
    const seq = assistantGenerationSeq;
    try {
      activeAssistantStream?.cancel?.();
    } catch {
      /* already settled */
    }
    setAssistantGenerating(false);
    // Restore send button if this generation is still current
    const send = $("#global-ai-assistant-send");
    if (send) {
      const icon = send.querySelector("i");
      if (icon) icon.className = "bi bi-send-fill";
      send.classList.remove("btn-danger");
      send.classList.add("btn-success");
      send.title = "Send question";
      send.setAttribute("aria-label", "Send question");
    }
    return seq;
  }

  async function loadAssistantSessions() {
    try {
      const res = await window.API.dashboard.getAssistantSessions({limit: 30});
      sessionsCache = res?.data || [];
      return sessionsCache;
    } catch (error) {
      console.warn("Failed to load assistant sessions:", error);
      // Fallback to localStorage history for backward compatibility
      try {
        const raw = JSON.parse(localStorage.getItem(ASSISTANT_HISTORY_KEY) || "[]");
        const fallback = Array.isArray(raw)
          ? raw.filter((item) => typeof item === "string" && item.trim() !== "").slice(0, 8).map((q, i) => ({
              id: `local-${i}`,
              title: q,
              created_at: Date.now() / 1000 - i * 60,
              message_count: 1,
            }))
          : [];
        sessionsCache = fallback;
        return fallback;
      } catch {
        sessionsCache = [];
        return [];
      }
    }
  }

  function renderAssistantSessions() {
    const host = $("#global-ai-assistant-history");
    if (!host) return;
    if (!sessionsCache.length) {
      host.innerHTML =
        '<p class="small text-muted px-2 mb-0">Your conversations will appear here.</p>';
      return;
    }
    host.innerHTML = sessionsCache
      .map(
        (session) => {
          const title = session.title || "New chat";
          const date = session.updated_at
            ? new Date(session.updated_at * 1000).toLocaleDateString([], {month: "numeric", day: "numeric"})
            : "";
          const isActive = String(session.id) === String(currentSessionId);
          const id = escapeHtml(session.id);
          return `<div class="chat-history-row${isActive ? " active" : ""}">
            <button type="button" class="chat-history-item" data-session-id="${id}" title="${escapeHtml(title)}">
              <span class="chat-history-title">${escapeHtml(title)}</span>
              ${date ? `<span class="chat-history-date">${escapeHtml(date)}</span>` : ""}
            </button>
            <button type="button" class="session-delete-btn" data-session-id="${id}" title="Delete chat" aria-label="Delete ${escapeHtml(title)}"><i class="bi bi-trash"></i></button>
          </div>`;
        }
      )
      .join("");
    // Click to load session
    host.querySelectorAll(".chat-history-item").forEach((button) => {
      button.addEventListener("click", () => {
        const sessionId = button.getAttribute("data-session-id");
        if (sessionId) loadAssistantSession(sessionId);
      });
    });
    // Delete session
    host.querySelectorAll(".session-delete-btn").forEach((btn) => {
      btn.addEventListener("click", (e) => {
        e.stopPropagation();
        const sessionId = btn.getAttribute("data-session-id");
        if (sessionId) deleteAssistantSession(sessionId);
      });
    });
  }

  function renderAssistantHistory() {
    // Keep the session list in sync and, when the cached active session carries
    // messages, restore its transcript. Otherwise leave the current greeting.
    renderAssistantSessions();
    if (!currentSessionId) return;
    const active = sessionsCache.find(
      (session) => String(session.id) === String(currentSessionId)
    );
    if (active && Array.isArray(active.messages) && active.messages.length) {
      renderConversationThread(active);
    }
  }

  function growAssistantInput() {
    const input = $("#global-ai-assistant-question");
    if (!input) return;
    const maxHeight = 160;
    input.style.height = "auto";
    input.style.height = `${Math.min(input.scrollHeight, maxHeight)}px`;
    input.style.overflowY = input.scrollHeight > maxHeight ? "auto" : "hidden";
  }

  function scrollAssistantToBottom() {
    const thread = $("#global-ai-assistant-thread");
    const target = thread || $("#global-ai-assistant-conversation");
    if (!target) return;
    target.scrollTop = target.scrollHeight;
  }

  async function createAssistantSession() {
    try {
      const route = window.REQUESTED_ROUTE || "";
      const res = await window.API.dashboard.postAssistantSession({route, title: ""});
      const session = res?.data;
      if (session?.id) {
        sessionsCache.unshift(session);
        renderAssistantSessions();
        currentSessionId = session.id;
        rememberActiveSession(currentSessionId);
        return session.id;
      }
    } catch (error) {
      console.warn("Failed to create session:", error);
    }
    return null;
  }

  async function loadAssistantSession(sessionId) {
    try {
      const res = await window.API.dashboard.getAssistantSession({session_id: sessionId, limit: 200});
      const session = res?.data;
      if (!session) return;
      currentSessionId = sessionId;
      rememberActiveSession(currentSessionId);
      // Populate local message history from the loaded session
      currentSessionMessages = (session.messages || []).map((m) => ({
        role: m.role,
        content: m.content || "",
        created_at: m.created_at || Math.floor(Date.now() / 1000),
      }));
      // Populate session topics
      sessionTopics.set(sessionId, new Set(session.topics || []));
      // Update sidebar active state
      document.querySelectorAll(".chat-history-item").forEach((item) => {
        item.classList.toggle("active", item.getAttribute("data-session-id") === sessionId);
      });
      // Render conversation thread
      renderConversationThread(session);
      // Scroll to bottom
      scrollAssistantToBottom();
    } catch (error) {
      console.warn("Failed to load session:", error);
    }
  }

  async function deleteAssistantSession(sessionId) {
    if (!confirm("Delete this chat session?")) return;
    try {
      await window.API.dashboard.deleteAssistantSession({session_id: sessionId});
      sessionsCache = sessionsCache.filter((s) => s.id !== sessionId);
      if (currentSessionId === sessionId) {
        currentSessionId = null;
        clearConversation();
      }
      renderAssistantSessions();
    } catch (error) {
      window.showNotification?.("Failed to delete session", "error");
    }
  }

  function renderConversationThread(session) {
    const thread = $("#global-ai-assistant-thread");
    if (!thread) return;
    const messages = (session && session.messages) || [];
    if (!messages.length) {
      thread.innerHTML = getGreetingHtml();
      bindAssistantPromptChips(thread);
      return;
    }
    thread.innerHTML = messages
      .map((msg) => {
        if (msg.role === "user") {
          return `<div class="d-flex justify-content-end mb-3"><div class="user-bubble chat-bubble max-w-75">${escapeHtml(msg.content)}</div></div>`;
        } else {
          return `<div class="d-flex mb-3"><span class="assistant-avatar assistant-avatar-sm me-2" aria-hidden="true"><i class="bi bi-robot"></i></span><div class="card border-0 shadow-sm flex-grow-1">${renderAiAgentAnswerFromMsg(msg)}</div></div>`;
        }
      })
      .join("");
    thread.querySelectorAll(".ai-followup-question").forEach((button) =>
      button.addEventListener("click", () => {
        const input = $("#global-ai-assistant-question");
        if (input) {
          input.value = button.textContent;
          input.focus();
        }
      })
    );
  }

  function getGreetingHtml() {
    const user = window.AuthContext?.getUser?.();
    const displayName = user?.first_name || "there";
    return `
      <div class="assistant-empty-state" id="global-ai-assistant-empty-state">
        <div class="d-flex gap-3 mb-3">
          <span class="assistant-avatar assistant-avatar-sm" aria-hidden="true"><i class="bi bi-robot"></i></span>
          <div>
            <p class="small mb-1">Hi <strong>${escapeHtml(displayName)}</strong>, I'm your Kingsway staff assistant. How can I help with your dashboard tasks today?</p>
          </div>
        </div>
        <div class="assistant-prompt-chips d-flex flex-wrap gap-2" id="global-ai-assistant-prompt-chips">
          <button type="button" class="btn btn-outline-secondary btn-sm" data-assistant-prompt="What are the key trends in my authorized school reports?"><i class="bi bi-graph-up me-1"></i>Key trends in reports</button>
          <button type="button" class="btn btn-outline-secondary btn-sm" data-assistant-prompt="Show me admissions status"><i class="bi bi-person-plus me-1"></i>Admissions status</button>
          <button type="button" class="btn btn-outline-secondary btn-sm" data-assistant-prompt="What is the fee status for this term?"><i class="bi bi-currency-dollar me-1"></i>Fees status</button>
          <button type="button" class="btn btn-outline-secondary btn-sm" data-assistant-prompt="Summarize my workspace"><i class="bi bi-layout-sidebar me-1"></i>Workspace summary</button>
        </div>
      </div>
    `;
  }

  function bindAssistantPromptChips(root) {
    root.querySelectorAll("[data-assistant-prompt]").forEach((button) => {
      button.addEventListener("click", () => {
        const input = $("#global-ai-assistant-question");
        if (!input) return;
        input.value = button.getAttribute("data-assistant-prompt") || "";
        input.focus();
        growAssistantInput();
      });
    });
  }

  function clearConversation() {
    const thread = $("#global-ai-assistant-thread");
    if (!thread) return;
    const briefing = $("#global-ai-assistant-briefing");
    if (briefing) briefing.innerHTML = "";
    thread.innerHTML = getGreetingHtml();
    bindAssistantPromptChips(thread);
  }

  function renderAiAgentAnswerFromMsg(msg) {
    // msg.content is the assistant body text; msg.meta may have agent info
    const meta = msg.meta || {};
    const agentName = meta.agent_id ? meta.agent_id.split(".").pop() : "Assistant";
    return `<div class="card-body">
      <div class="d-flex justify-content-between align-items-start gap-2 mb-2">
        <h6 class="mb-0">${escapeHtml("Assistant")}</h6>
        <span class="badge text-bg-light">${escapeHtml(agentName)}</span>
      </div>
      <p class="small mb-2" style="white-space: pre-line;">${escapeHtml(msg.content || "")}</p>
    </div>`;
  }

  let assistantChromeBound = false;
  let assistantRecognition = null;

  async function initAssistantChrome() {
    if (assistantChromeBound) return;
    assistantChromeBound = true;

    const user = window.AuthContext?.getUser?.();
    const displayName = resolveDisplayName(user);
    setText(
      "#assistant-greeting-name",
      (user?.first_name || displayName.split(" ")[0] || "there").trim()
    );

    const input = $("#global-ai-assistant-question");
    input?.addEventListener("input", growAssistantInput);
    input?.addEventListener("keydown", (event) => {
      if (event.key === "Escape" && assistantGenerating) {
        event.preventDefault();
        stopAssistantGeneration();
        return;
      }
      if (event.key === "Enter" && !event.shiftKey) {
        event.preventDefault();
        const form = $("#global-ai-assistant-query");
        if (input.value.trim() && typeof form?.requestSubmit === "function") {
          form.requestSubmit();
        }
      }
    });
    growAssistantInput();

    // Load sessions on first open
    await loadAssistantSessions();
    renderAssistantSessions();
    // Restore the session the user had open before reload (if any)
    const storedSessionId = getStoredActiveSession();
    if (storedSessionId && sessionsCache.some((s) => String(s.id) === storedSessionId)) {
      await loadAssistantSession(storedSessionId);
    }

    $("#global-ai-assistant-new-chat")?.addEventListener("click", async () => {
      const newSessionId = await createAssistantSession();
      if (newSessionId) {
        currentSessionId = newSessionId;
        clearConversation();
        document.querySelectorAll(".chat-history-item").forEach((item) => {
          item.classList.toggle("active", item.getAttribute("data-session-id") === newSessionId);
        });
        scrollAssistantToBottom();
      }
    });

    $("#global-ai-assistant-help")?.addEventListener("click", () => {
      // Open info modal instead of toast
      const modal = $("#assistant-info-modal");
      if (modal && window.bootstrap?.Modal) {
        new window.bootstrap.Modal(modal).show();
      } else {
        window.showNotification?.(
          "Ask about school processes, registers or reports. The assistant explains and drafts answers, but never posts, approves or sends anything.",
          "info"
        );
      }
    });

    $("#global-ai-assistant-settings")?.addEventListener("click", () => {
      // Open settings modal
      const modal = $("#assistant-settings-modal");
      if (modal && window.bootstrap?.Modal) {
        new window.bootstrap.Modal(modal).show();
        loadAssistantPreferences();
      }
    });

    // Settings modal: save preferences
    $("#assistant-save-settings")?.addEventListener("click", async () => {
      const saveHistory = $("#pref-save-history");
      const responseStyle = $("#pref-response-style");
      const autoCharts = $("#pref-auto-charts");
      const language = $("#pref-language");
      const prefs = {
        save_history: saveHistory?.checked !== false,
        response_style: responseStyle?.value || "balanced",
        auto_charts: autoCharts?.checked === true,
        language: language?.value || "en",
      };
      try {
        localStorage.setItem("kw_assistant_prefs", JSON.stringify(prefs));
      } catch {}
      // Persist to server
      try {
        await window.API.dashboard.postAssistantPreferences(prefs);
      } catch {
        window.showNotification?.("Failed to save assistant settings", "error");
        return;
      }
      const modal = $("#assistant-settings-modal");
      if (modal && window.bootstrap?.Modal) {
        const bsModal = window.bootstrap.Modal.getInstance(modal);
        if (bsModal) bsModal.hide();
      }
      window.showNotification?.("Assistant settings saved", "success");
    });

    // Settings modal: clear all history
    $("#assistant-clear-history")?.addEventListener("click", async () => {
      if (!confirm("Clear all chat sessions? This cannot be undone.")) return;
      try {
        await window.API.dashboard.postAssistantSessionsClear();
      } catch {
        window.showNotification?.("Failed to clear chat history", "error");
        return;
      }
      sessionsCache = [];
      currentSessionId = null;
      currentSessionMessages = [];
      sessionTopics.clear();
      renderAssistantSessions();
      clearConversation();
      const modal = $("#assistant-settings-modal");
      if (modal && window.bootstrap?.Modal) {
        const bsModal = window.bootstrap.Modal.getInstance(modal);
        if (bsModal) bsModal.hide();
      }
      window.showNotification?.("All chat history cleared", "info");
    });

    const attachment = $("#global-ai-assistant-attachment");
    $("#global-ai-assistant-attach")?.addEventListener("click", () =>
      attachment?.click()
    );
    attachment?.addEventListener("change", () => {
      if (attachment.files?.length) {
        window.showNotification?.(
          "File attachments are not enabled for this assistant yet. Describe the information in your question instead.",
          "info"
        );
        attachment.value = "";
      }
    });

    const voiceButton = $("#global-ai-assistant-voice");
    voiceButton?.addEventListener("click", () => {
      const Recognition = window.SpeechRecognition || window.webkitSpeechRecognition;
      if (!Recognition) {
        window.showNotification?.(
          "Voice input is not supported in this browser. Please type your question.",
          "info"
        );
        return;
      }
      if (assistantRecognition) {
        assistantRecognition.stop();
        return;
      }
      const recognition = new Recognition();
      assistantRecognition = recognition;
      recognition.lang = document.documentElement.lang || "en-KE";
      recognition.interimResults = false;
      recognition.maxAlternatives = 1;

      const setIdle = () => {
        assistantRecognition = null;
        voiceButton.classList.add("btn-light", "text-muted");
        voiceButton.classList.remove("btn-success", "text-white");
      };

      voiceButton.classList.remove("btn-light", "text-muted");
      voiceButton.classList.add("btn-success", "text-white");
      recognition.onresult = (event) => {
        const transcript = event.results?.[0]?.[0]?.transcript || "";
        if (transcript && input) {
          input.value = transcript;
          growAssistantInput();
          input.focus();
        }
      };
      recognition.onerror = (event) => {
        if (event.error === "not-allowed" || event.error === "service-not-allowed") {
          window.showNotification?.(
            "Microphone access was blocked. Allow it in your browser to dictate questions.",
            "warning"
          );
        } else if (event.error !== "aborted" && event.error !== "no-speech") {
          window.showNotification?.(
            "Voice input stopped. Please try again or type your question.",
            "info"
          );
        }
      };
      recognition.onend = setIdle;
      try {
        recognition.start();
      } catch (error) {
        setIdle();
      }
    });

    const thread = $("#global-ai-assistant-thread");
    if (thread && typeof MutationObserver !== "undefined") {
      new MutationObserver(() => scrollAssistantToBottom()).observe(thread, {
        childList: true,
        subtree: true,
        characterData: true,
      });
    }

    // Show the greeting in the thread when no session is active
    if (!currentSessionId) clearConversation();
  }

  let aiAssistantQueryBound = false;

  // Session topics (client-side, persisted to server on each turn)
  let sessionTopics = new Map();

  function getSessionTopics(sessionId) {
    return sessionId ? Array.from(sessionTopics.get(sessionId) || []) : [];
  }

  function updateSessionTopics(sessionId, newTopics) {
    if (!sessionId) return;
    const existing = sessionTopics.get(sessionId) || new Set();
    for (const topic of newTopics) existing.add(topic);
    sessionTopics.set(sessionId, existing);
  }

  async function persistSessionTopics(sessionId, topics) {
    if (!sessionId || !topics.length) return;
    try {
      await window.API.dashboard.patchAssistantSession?.({
        session_id: sessionId,
        topics,
      });
    } catch {
      /* silent fail - topics are best-effort */
    }
  }

  // Resolve which assistant persona the current user should speak to.
  // One shared UI — the backend decides the persona based on domain + role +
  // permissions. No duplication across staff, system admin, parents, public.
  // Returns one of: "system_admin" | "parent" | "public" | "staff"
  function getAssistantDomain() {
    const user = window.AuthContext?.getUser?.() || {};
    const isAuthenticated = window.AuthContext?.isAuthenticated?.() ?? false;

    // Public / unauthenticated → public assistant (customer care, no internal info)
    if (!isAuthenticated) {
      return "public";
    }

    // SYSTEM domain / system_admin permission / System admin role → watchman assistant
    const domain = String(user.domain || "").toUpperCase();
    if (domain === "SYSTEM") {
      return "system_admin";
    }
    const role = String(user.role_name || user.role || "").toLowerCase();
    if (role === "system admin") {
      return "system_admin";
    }
    const permissions = window.AuthContext?.getPermissions?.() || [];
    if (permissions.includes("system_admin")) {
      return "system_admin";
    }

    // Parent account → parent assistant (parent/guardian focused)
    if (String(user.account_type || "").toLowerCase() === "parent") {
      return "parent";
    }
    if (role === "parent") {
      return "parent";
    }

    // SCHOOL domain staff → staff assistant (educational/operational)
    return "staff";
  }

  // Backward-compatible shim: system admin assistant check
  function canSystemAssistant() {
    return getAssistantDomain() === "system_admin";
  }

  function getAssistantPreferences() {
    try {
      const raw = localStorage.getItem("kw_assistant_prefs");
      return raw ? JSON.parse(raw) : {};
    } catch {
      return {};
    }
  }

  function extractTopics(text) {
    const t = String(text ?? "").toLowerCase();
    const topicMap = {
      admissions: ["admission", "applicant", "application", "enroll", "enrol", "placement", "interview"],
      fees: ["fee", "payment", "invoice", "finance", "budget", "expense", "bursar", "pay"],
      attendance: ["attendance", "absent", "present", "late", "roll call", "register"],
      reports: ["report", "performance", "result", "mark", "grade", "score", "transcript"],
      communications: ["message", "sms", "email", "announcement", "newsletter", "communicat"],
      discipline: ["discipline", "behavior", "behaviour", "conduct", "punish", "detention"],
      boarding: ["boarding", "dormitory", "hostel", "boarder", "residence"],
      health: ["health", "sick", "sick bay", "medical", "clinic", "nurse"],
      transport: ["transport", "bus", "route", "pickup", "drop"],
      academics: ["academic", "curriculum", "syllabus", "lesson", "timetable", "class"],
      staff: ["staff", "teacher", "payroll", "payslip", "leave", "employment"],
    };
    const found = new Set();
    for (const [topic, keywords] of Object.entries(topicMap)) {
      if (keywords.some((kw) => t.includes(kw))) found.add(topic);
    }
    return Array.from(found);
  }

  // Slash command handler: /new, /clear, /help, /topic, /export, /tour
  async function handleAssistantCommand(cmd, args, input, conversation, answer) {
    switch (cmd) {
      case "new": {
        const newSessionId = await createAssistantSession();
        if (newSessionId) {
          currentSessionId = newSessionId;
          clearConversation();
          document.querySelectorAll(".chat-history-item").forEach((item) => {
            item.classList.toggle("active", item.getAttribute("data-session-id") === newSessionId);
          });
        }
        if (input) { input.value = ""; input.focus(); }
        growAssistantInput();
        scrollAssistantToBottom();
        return true;
      }
      case "clear": {
        if (currentSessionId) {
          try {
            await window.API.dashboard.clearAssistantSessionMessages?.({session_id: currentSessionId});
          } catch {}
          currentSessionMessages = [];
          sessionTopics.delete(currentSessionId);
        }
        clearConversation();
        if (input) { input.value = ""; input.focus(); }
        growAssistantInput();
        scrollAssistantToBottom();
        return true;
      }
      case "help": {
        const helpHtml = `
          <div class="card border-0 bg-light-subtle mb-3"><div class="card-body p-3">
            <h6 class="mb-2">Assistant Commands</h6>
            <ul class="small mb-2 list-unstyled">
              <li><code>/new</code> — Start a new chat session</li>
              <li><code>/clear</code> — Clear the current conversation</li>
              <li><code>/topic &lt;name&gt;</code> — Tag the current session (e.g. /topic admissions)</li>
              <li><code>/export</code> — Download the current transcript as CSV</li>
              <li><code>/tour</code> — Start the guided system tour</li>
              <li><code>/help</code> — Show this help</li>
            </ul>
            <p class="small text-muted mb-0">Or just ask a question naturally. While an answer is being generated the send button becomes a stop button.</p>
          </div></div>`;
        const tempDiv = document.createElement("div");
        tempDiv.innerHTML = helpHtml;
        if (conversation) conversation.appendChild(tempDiv);
        scrollAssistantToBottom();
        if (input) { input.value = ""; input.focus(); }
        growAssistantInput();
        return true;
      }
      case "topic": {
        if (!currentSessionId) {
          window.showNotification?.("No active session to tag.", "warning");
          return true;
        }
        if (!args) {
          const topics = getSessionTopics(currentSessionId);
          window.showNotification?.(
            topics.length ? "Topics: " + topics.join(", ") : "No topics set for this session.",
            "info"
          );
          return true;
        }
        updateSessionTopics(currentSessionId, [args]);
        persistSessionTopics(currentSessionId, getSessionTopics(currentSessionId));
        window.showNotification?.("Session tagged: " + args, "success");
        if (input) { input.value = ""; input.focus(); }
        growAssistantInput();
        return true;
      }
      case "export": {
        if (!currentSessionId) {
          window.showNotification?.("Nothing to export.", "warning");
          return true;
        }
        const lines = ["role,content,timestamp"];
        for (const m of currentSessionMessages) {
          lines.push(`"${m.role}","${String(m.content || "").replace(/"/g, '""')}",${m.created_at || ""}`);
        }
        const csv = lines.join("\n");
        const blob = new Blob([csv], {type: "text/csv"});
        const url = URL.createObjectURL(blob);
        const a = document.createElement("a");
        a.href = url;
        a.download = `kingsway-assistant-${currentSessionId}.csv`;
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        URL.revokeObjectURL(url);
        if (input) { input.value = ""; input.focus(); }
        growAssistantInput();
        return true;
      }
      case "tour": {
        SystemTour.start(true);
        if (input) { input.value = ""; input.focus(); }
        growAssistantInput();
        return true;
      }
      default:
        return false;
    }
  }

  // System Tour — guided walkthrough. Restartable via /tour.
  class SystemTour {
    static STEPS = [
      {
        id: "sidebar",
        target: ".sidebar-link[data-route='school_administrative_officer_dashboard'], .sidebar-link[data-route='manage_students_admissions']",
        title: "Sidebar Navigation",
        text: "Use the sidebar to switch between dashboard areas. Click any item to open that page.",
        action: null,
      },
      {
        id: "assistant",
        target: "#global-ai-assistant-query",
        title: "Staff Assistant",
        text: "Ask questions about your dashboard data here. Try /help for commands.",
        action: null,
      },
      {
        id: "notifications",
        target: ".header-notifications, [data-notifications]",
        title: "Notifications",
        text: "Important alerts and updates appear in the notifications panel.",
        action: null,
      },
      {
        id: "user_menu",
        target: "#header-user-menu, .dropdown-toggle",
        title: "User Menu",
        text: "Access your profile, settings, and sign out from the user menu.",
        action: null,
      },
    ];

    static completedKey = "kw_tour_completed";
    static currentStepIndex = 0;
    static overlay = null;
    static tooltip = null;

    static isCompleted() {
      try {
        return localStorage.getItem(this.completedKey) === "1";
      } catch {
        return false;
      }
    }

    static markCompleted() {
      try {
        localStorage.setItem(this.completedKey, "1");
      } catch {}
    }

    static start(force = false) {
      if (!force && this.isCompleted()) return;
      this.hideOverlay();
      this.hideTooltip();
      this.currentStepIndex = 0;
      this.showStep(0);
    }

    static showStep(index) {
      if (index >= this.STEPS.length) {
        this.markCompleted();
        this.hideOverlay();
        this.hideTooltip();
        window.showNotification?.("Tour completed. Restart anytime with /tour.", "info");
        return;
      }
      const step = this.STEPS[index];
      const target = document.querySelector(step.target);
      if (!target) {
        this.showStep(index + 1);
        return;
      }
      this.hideTooltip();
      const rect = target.getBoundingClientRect();
      const tooltip = document.createElement("div");
      tooltip.id = "system-tour-tooltip";
      tooltip.className = "position-fixed bg-dark text-white p-3 rounded shadow-lg";
      tooltip.style.zIndex = "9999";
      tooltip.style.left = Math.max(10, rect.left) + "px";
      tooltip.style.top = Math.max(10, rect.bottom + 8) + "px";
      tooltip.style.maxWidth = "300px";
      tooltip.innerHTML = `
        <div class="d-flex justify-content-between mb-2">
          <strong>${step.title}</strong>
          <span class="badge bg-info text-dark">${index + 1} / ${this.STEPS.length}</span>
        </div>
        <div class="small">${step.text}</div>
        <div class="mt-2 d-flex gap-2">
          <button type="button" class="btn btn-sm btn-outline-light" id="tour-next">${
            index < this.STEPS.length - 1 ? "Next" : "Done"
          }</button>
          <button type="button" class="btn btn-sm btn-danger" id="tour-skip">Skip</button>
        </div>
      `;
      document.body.appendChild(tooltip);
      this.tooltip = tooltip;
      tooltip.querySelector("#tour-next")?.addEventListener("click", () => {
        this.showStep(index + 1);
      });
      tooltip.querySelector("#tour-skip")?.addEventListener("click", () => {
        this.markCompleted();
        this.hideOverlay();
        this.hideTooltip();
      });
    }

    static hideOverlay() {
      const ov = document.getElementById("system-tour-overlay");
      if (ov) ov.remove();
    }

    static hideTooltip() {
      const tp = document.getElementById("system-tour-tooltip");
      if (tp) tp.remove();
      this.tooltip = null;
    }
  }

  function initAiAssistantQuery() {
    const form = $("#global-ai-assistant-query");
    if (!form || aiAssistantQueryBound) return;
    aiAssistantQueryBound = true;
    form.addEventListener("submit", async (event) => {
      event.preventDefault();
      try {
        const input = $("#global-ai-assistant-question");
        const thread = $("#global-ai-assistant-thread");
        const question = String(input?.value || "").trim();
        if (!question || !thread) return;

        // Block duplicate sends while generating
        if (assistantGenerating) return;

        // Command system: /new, /clear, /help, /topic, /export, /tour
        if (question.startsWith("/")) {
          const cmdParts = question.slice(1).split(/\s+/);
          const cmd = cmdParts[0]?.toLowerCase();
          const args = cmdParts.slice(1).join(" ");
          const cmdHandled = await handleAssistantCommand(cmd, args, input, thread, null);
          if (cmdHandled) {
            input.value = "";
            growAssistantInput();
            scrollAssistantToBottom();
            return;
          }
          // Unknown command falls through to normal AI processing
        }

        // Ensure we have a session. If the session service is temporarily
        // unavailable, proceed without one: the backend creates a session for
        // the turn, and we re-list sessions once it settles. Never abort the
        // send — a ChatGPT-style composer always accepts the message.
        if (!currentSessionId) {
          try {
            const newSessionId = await createAssistantSession();
            if (newSessionId) {
              currentSessionId = newSessionId;
              document.querySelectorAll(".chat-history-item").forEach((item) => {
                item.classList.toggle("active", item.getAttribute("data-session-id") === newSessionId);
              });
            }
          } catch {
            /* fall through: backend creates the session for this turn */
          }
        }

        // Clear the composer immediately, exactly like ChatGPT
        input.value = "";
        growAssistantInput();

        // Add user bubble to conversation thread
        thread.querySelector("#global-ai-assistant-empty-state")?.remove();
        const userBubble = document.createElement("div");
        userBubble.className = "d-flex justify-content-end mb-3";
        userBubble.innerHTML = `<div class="user-bubble chat-bubble max-w-75">${escapeHtml(question)}</div>`;
        thread.appendChild(userBubble);

        // Track history locally (native role-based)
        currentSessionMessages.push({
          role: "user",
          content: question,
          created_at: Math.floor(Date.now() / 1000),
        });

        // Streaming placeholder
        const streamPlaceholder = document.createElement("div");
        streamPlaceholder.className = "d-flex mb-3";
        streamPlaceholder.innerHTML = `<span class="assistant-avatar assistant-avatar-sm me-2" aria-hidden="true"><i class="bi bi-robot"></i></span><div class="ai-stream-bubble flex-grow-1"><div class="text-muted small"><span class="spinner-border spinner-border-sm me-2" role="status"></span>Routing your question to the right agent…</div></div>`;
        thread.appendChild(streamPlaceholder);
        scrollAssistantToBottom();

        const generation = ++assistantGenerationSeq;
        const isCurrent = () => generation === assistantGenerationSeq;
        setAssistantGenerating(true);
        activeAssistantStream = null;

        const requestOptions = {
          session_id: currentSessionId,
          history: currentSessionMessages,
          topics: getSessionTopics(currentSessionId),
          sidebar_state: window.SoftNavigation?.getSidebarState?.(),
          preferences: getAssistantPreferences(),
          system_admin: canSystemAssistant(),
          assistant_domain: getAssistantDomain(),
        };

        const stream = window.API?.dashboard?.agentAssistStream;
        let handled = false;
        if (typeof stream === "function") {
          try {
            const handle = stream(
              question,
              String(window.REQUESTED_ROUTE || ""),
              "dashboard",
              {
                ...requestOptions,
                onStatus: () => {
                  const bubble = streamPlaceholder.querySelector(".ai-stream-bubble");
                  if (bubble) bubble.innerHTML = '<div class="text-muted small"><span class="spinner-border spinner-border-sm me-2" role="status"></span>Checking governed school data…</div>';
                },
                onDelta: (chunk) => {
                  if (!isCurrent()) return;
                  const bubble = streamPlaceholder.querySelector(".ai-stream-bubble");
                  if (bubble) bubble.textContent += chunk;
                },
                onError: (message) => {
                  if (!isCurrent()) return;
                  const bubble = streamPlaceholder.querySelector(".ai-stream-bubble");
                  if (bubble && !bubble.textContent.trim()) {
                    bubble.innerHTML = `<div class="alert alert-warning small mb-0">${escapeHtml(message || "The assistant could not answer right now.")}</div>`;
                  }
                },
              },
            );
            activeAssistantStream = handle;
            const result = await handle.promise;
            activeAssistantStream = null;
            // A Stop press increments the generation id; do not render abandoned answer
            if (!isCurrent()) return;
            if (result) {
              handled = true;
              streamPlaceholder.remove();
              // Add assistant message to thread
              const assistantMsg = document.createElement("div");
              assistantMsg.className = "d-flex mb-3";
              const answerHtml = renderAiAgentAnswer(result);
              assistantMsg.innerHTML = `<span class="assistant-avatar assistant-avatar-sm me-2" aria-hidden="true"><i class="bi bi-robot"></i></span><div class="card border-0 shadow-sm flex-grow-1">${answerHtml}</div>`;
              thread.appendChild(assistantMsg);
              // Track assistant message in history
              const assistantBody = result?.answer?.body || result?.message || "";
            if (assistantBody) {
              currentSessionMessages.push({
                role: "assistant",
                content: assistantBody,
                created_at: Math.floor(Date.now() / 1000),
              });
            }
            // Persist topics
            const topics = extractTopics(currentSessionMessages.map((m) => m.content).join(" "));
            if (topics.length && currentSessionId) {
              updateSessionTopics(currentSessionId, topics);
              persistSessionTopics(currentSessionId, getSessionTopics(currentSessionId));
            }
            await loadAssistantSessions();
            renderAssistantSessions();
            // Re-bind follow-up buttons
            thread.querySelectorAll(".ai-followup-question").forEach((button) => {
              button.addEventListener("click", () => {
                const inp = $("#global-ai-assistant-question");
                if (inp) { inp.value = button.textContent; inp.focus(); }
              });
            });
            scrollAssistantToBottom();
          }
          setAssistantGenerating(false);
        } catch (streamError) {
          // fall through to buffered
          setAssistantGenerating(false);
        }
      }

      if (handled) {
        scrollAssistantToBottom();
        return;
      }

      // Buffered fallback
      try {
        const payload = await window.API?.dashboard?.agentAssist?.(
          question,
          String(window.REQUESTED_ROUTE || ""),
          "dashboard",
          requestOptions
        );
        if (!isCurrent()) return;
        const result = payload?.data !== undefined ? payload.data : payload;
        streamPlaceholder.remove();
        const assistantMsg = document.createElement("div");
        assistantMsg.className = "d-flex mb-3";
        const answerHtml = renderAiAgentAnswer(result);
        assistantMsg.innerHTML = `<span class="assistant-avatar assistant-avatar-sm me-2" aria-hidden="true"><i class="bi bi-robot"></i></span><div class="card border-0 shadow-sm flex-grow-1">${answerHtml}</div>`;
        thread.appendChild(assistantMsg);
        const assistantBody = result?.answer?.body || result?.message || "";
        if (assistantBody) {
          currentSessionMessages.push({
            role: "assistant",
            content: assistantBody,
            created_at: Math.floor(Date.now() / 1000),
          });
        }
        await loadAssistantSessions();
        renderAssistantSessions();
        scrollAssistantToBottom();
      } catch (agentError) {
        if (!isCurrent()) return;
        streamPlaceholder.innerHTML = `<span class="assistant-avatar assistant-avatar-sm me-2" aria-hidden="true"><i class="bi bi-robot"></i></span><div class="alert alert-warning small mb-0">The assistant could not answer right now. Open the governed reports directly.</div>`;
      } finally {
        if (isCurrent()) setAssistantGenerating(false);
      }
    } catch (error) {
      console.error("Assistant submit error:", error);
      const thread = $("#global-ai-assistant-thread");
      const input = $("#global-ai-assistant-question");
      if (thread) {
        const errorDiv = document.createElement("div");
        errorDiv.className = "d-flex mb-3";
        errorDiv.innerHTML = `<span class="assistant-avatar assistant-avatar-sm me-2" aria-hidden="true"><i class="bi bi-robot"></i></span><div class="alert alert-warning small mb-0">Unable to send your question right now. Please try again.</div>`;
        thread.appendChild(errorDiv);
        scrollAssistantToBottom();
      }
      if (input) {
        input.value = String(input?.value || "").trim() !== "" ? input.value : "";
        setAssistantGenerating(false);
      }
    }
    });

    // Stop button: clicking while generating aborts the stream
    $("#global-ai-assistant-send")?.addEventListener("click", (event) => {
      if (!assistantGenerating) return;
      event.preventDefault();
      event.stopPropagation();
      stopAssistantGeneration();
    });
  }

  function addAssistantMessageToThread(result, userQuestion) {
    const thread = $("#global-ai-assistant-thread");
    if (!thread) return;

    // Create assistant message bubble
    const assistantMsg = document.createElement("div");
    assistantMsg.className = "d-flex mb-3";
    const answerHtml = renderAiAgentAnswer(result);
    const meta = result?.agent || {};
    assistantMsg.innerHTML = `<span class="assistant-avatar assistant-avatar-sm me-2" aria-hidden="true"><i class="bi bi-robot"></i></span><div class="card border-0 shadow-sm flex-grow-1">${answerHtml}</div>`;
    thread.appendChild(assistantMsg);

    // Re-bind follow-up buttons
    thread.querySelectorAll(".ai-followup-question").forEach((button) =>
      button.addEventListener("click", () => {
        const input = $("#global-ai-assistant-question");
        if (input) {
          input.value = button.textContent;
          input.focus();
        }
      })
    );

    // If this is the first turn in a new session, update session title
    if (currentSessionId && thread.querySelectorAll(".user-bubble").length === 1) {
      updateSessionTitle(currentSessionId, userQuestion);
    }
  }

  async function updateSessionTitle(sessionId, firstQuestion) {
    const title = firstQuestion.slice(0, 60);
    try {
      await window.API.dashboard.patchAssistantSession({session_id: sessionId, title});
      await loadAssistantSessions();
      renderAssistantSessions();
    } catch (error) {
      // Silent fail - title update is optional
    }
  }

  function loadAssistantPreferences() {
    // Load preferences into settings modal
    window.API.dashboard.getAssistantPreferences?.().then((res) => {
      const prefs = res?.data || {};
      const saveHistory = $("#pref-save-history");
      const responseStyle = $("#pref-response-style");
      const autoCharts = $("#pref-auto-charts");
      if (saveHistory) saveHistory.checked = prefs.save_history !== false;
      if (responseStyle) responseStyle.value = prefs.response_style || "balanced";
      if (autoCharts) autoCharts.checked = prefs.auto_charts === true;
    }).catch(() => {});
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
        .querySelectorAll("#global-ai-assistant-thread .ai-followup-question")
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
        initAssistantChrome();
        renderAssistantHistory();
        void loadAiAssistantCatalog();
        initAiAssistantQuery();
      }
    );

    $("#globalAiAssistantPanel")?.addEventListener(
      "shown.bs.offcanvas",
      () => {
        growAssistantInput();
        scrollAssistantToBottom();
        if (!isMobile()) {
          $("#global-ai-assistant-question")?.focus();
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

    // Keep the header bell live: the real-time dispatcher emits
    // kingsway:data-mutated for every change (from either the Node SSE stream
    // or the static-buffer fallback), so the badge updates without a manual
    // reload or a polling interval.
    window.addEventListener(
      "kingsway:data-mutated",
      onRealtimeNotificationChange
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
      () => {
        initialize();
        // Fallback: ensure the AI assistant submit listener is attached even
        // if the offcanvas show event never fires (e.g. Bootstrap not ready).
        initAssistantChrome();
        initAiAssistantQuery();
      },
      { once: true }
    );
  } else {
    initialize();
    initAssistantChrome();
    initAiAssistantQuery();
  }
})();
