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
  let sessionsCache = [];

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
        return Array.isArray(raw)
          ? raw.filter((item) => typeof item === "string" && item.trim() !== "").slice(0, 8).map((q, i) => ({
              id: `local-${i}`,
              title: q,
              created_at: Date.now() / 1000 - i * 60,
              message_count: 1,
            }))
          : [];
      } catch {
        return [];
      }
    }
  }

  function renderAssistantSessions() {
    const host = $("#global-ai-assistant-history");
    if (!host) return;
    if (!sessionsCache.length) {
      host.innerHTML =
        '<p class="small text-muted px-1 mb-0">Start a new chat to see sessions here.</p>';
      return;
    }
    host.innerHTML = sessionsCache
      .map(
        (session, index) => {
          const title = session.title || "New chat";
          const time = session.updated_at
            ? new Date(session.updated_at * 1000).toLocaleTimeString([], {hour: "2-digit", minute: "2-digit"})
            : "";
          const isActive = session.id === currentSessionId;
          return `<button type="button" class="chat-history-item w-100 text-start d-flex flex-column align-items-start gap-1 p-2 mb-1${isActive ? " active" : ""}" data-session-id="${escapeHtml(session.id)}">
            <span class="text-truncate fw-medium w-100">${escapeHtml(title)}</span>
            <div class="d-flex align-items-center gap-2 small text-muted">
              <span>${escapeHtml(time)}</span>
              <span class="opacity-50">${session.message_count || 0} msgs</span>
              <button type="button" class="btn btn-icon-only btn-ghost btn-sm p-0 ms-auto session-delete-btn" data-session-id="${escapeHtml(session.id)}" title="Delete session" aria-label="Delete session"><i class="bi bi-trash"></i></button>
            </div>
          </button>`;
        }
      )
      .join("");
    // Click to load session
    host.querySelectorAll("[data-session-id]").forEach((button) => {
      if (button.classList.contains("session-delete-btn")) return;
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

  async function createAssistantSession() {
    try {
      const route = window.REQUESTED_ROUTE || "";
      const res = await window.API.dashboard.postAssistantSession({route, title: ""});
      const session = res?.data;
      if (session?.id) {
        sessionsCache.unshift(session);
        renderAssistantSessions();
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
    const conversation = $("#global-ai-assistant-conversation");
    if (!conversation) return;
    const messages = session.messages || [];
    if (!messages.length) {
      // Show greeting for empty session
      conversation.innerHTML = getGreetingHtml();
      return;
    }
    conversation.innerHTML = messages
      .map((msg) => {
        const time = msg.created_at
          ? new Date(msg.created_at * 1000).toLocaleTimeString([], {hour: "2-digit", minute: "2-digit"})
          : "";
        if (msg.role === "user") {
          return `<div class="d-flex justify-content-end mb-3"><div class="user-bubble chat-bubble max-w-75">${escapeHtml(msg.content)}</div></div>`;
        } else {
          // Assistant message - render as card
          return `<div class="d-flex mb-3"><span class="assistant-avatar assistant-avatar-sm me-2" aria-hidden="true"><i class="bi bi-robot"></i></span><div class="card border-0 shadow-sm flex-grow-1">${renderAiAgentAnswerFromMsg(msg)}</div></div>`;
        }
      })
      .join("");
    // Re-bind follow-up question buttons
    conversation.querySelectorAll(".ai-followup-question").forEach((button) =>
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
      <div class="d-flex gap-3 mb-3">
        <span class="assistant-avatar assistant-avatar-sm" aria-hidden="true"><i class="bi bi-robot"></i></span>
        <div>
          <p class="small mb-1">Hi <strong>${escapeHtml(displayName)}</strong>, I'm your Kingsway staff assistant. How can I help with your dashboard tasks today?</p>
        </div>
      </div>
    `;
  }

  function clearConversation() {
    const conversation = $("#global-ai-assistant-conversation");
    if (conversation) conversation.innerHTML = getGreetingHtml();
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

    const answer = $("#global-ai-assistant-answer");
    if (answer && typeof MutationObserver !== "undefined") {
      new MutationObserver(() => scrollAssistantToBottom()).observe(answer, {
        childList: true,
        subtree: true,
        characterData: true,
      });
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
      const conversation = $("#global-ai-assistant-conversation");
      const question = String(input?.value || "").trim();
      if (!question || !answer || !conversation) return;

      // Ensure we have a session
      if (!currentSessionId) {
        const newSessionId = await createAssistantSession();
        if (newSessionId) {
          currentSessionId = newSessionId;
          clearConversation();
          document.querySelectorAll(".chat-history-item").forEach((item) => {
            item.classList.toggle("active", item.getAttribute("data-session-id") === newSessionId);
          });
        }
      }

      // Add user bubble to conversation thread
      const userBubble = document.createElement("div");
      userBubble.className = "d-flex justify-content-end mb-3";
      userBubble.innerHTML = `<div class="user-bubble chat-bubble max-w-75">${escapeHtml(question)}</div>`;
      conversation.appendChild(userBubble);

      // Streaming placeholder
      const streamPlaceholder = document.createElement("div");
      streamPlaceholder.className = "d-flex mb-3";
      streamPlaceholder.innerHTML = `<span class="assistant-avatar assistant-avatar-sm me-2" aria-hidden="true"><i class="bi bi-robot"></i></span><div class="ai-stream-bubble flex-grow-1"><div class="text-muted small"><span class="spinner-border spinner-border-sm me-2" role="status"></span>Routing your question to the right agent…</div></div>`;
      conversation.appendChild(streamPlaceholder);
      scrollAssistantToBottom();

      const stream = window.API?.dashboard?.agentAssistStream;
      let handled = false;
      if (typeof stream === "function") {
        try {
          const handle = stream(
            question,
            String(window.REQUESTED_ROUTE || ""),
            "dashboard",
            {
              session_id: currentSessionId,
              onStatus: () => {
                const bubble = streamPlaceholder.querySelector(".ai-stream-bubble");
                if (bubble) bubble.innerHTML = '<div class="text-muted small"><span class="spinner-border spinner-border-sm me-2" role="status"></span>Checking governed school data…</div>';
              },
              onDelta: (chunk) => {
                const bubble = streamPlaceholder.querySelector(".ai-stream-bubble");
                if (bubble) bubble.textContent += chunk;
              },
              onError: (message) => {
                const bubble = streamPlaceholder.querySelector(".ai-stream-bubble");
                if (bubble && !bubble.textContent.trim()) {
                  bubble.innerHTML = `<div class="alert alert-warning small mb-0">${escapeHtml(message || "The assistant could not answer right now.")}</div>`;
                }
              },
            },
          );
          const result = await handle.promise;
          if (result) {
            handled = true;
            streamPlaceholder.remove();
            // Add assistant message to thread
            addAssistantMessageToThread(result, question);
            // Update session list (title may have changed)
            await loadAssistantSessions();
            renderAssistantSessions();
          }
          handle.cancel();
        } catch (streamError) {
          // fall through to buffered
        }
      }

      if (handled) {
        input.value = "";
        growAssistantInput();
        scrollAssistantToBottom();
        return;
      }

      // Buffered fallback
      try {
        const payload = await window.API?.dashboard?.agentAssist?.(
          question,
          String(window.REQUESTED_ROUTE || ""),
          "dashboard",
          {session_id: currentSessionId}
        );
        const result = payload?.data !== undefined ? payload.data : payload;
        streamPlaceholder.remove();
        addAssistantMessageToThread(result, question);
        await loadAssistantSessions();
        renderAssistantSessions();
      } catch (agentError) {
        // Fall back to the governed reports NLQ assistant
        try {
          const payload = await window.API?.reports?.askNlq?.(question);
          const result = payload?.data !== undefined ? payload.data : payload;
          streamPlaceholder.remove();
          addAssistantMessageToThread(result, question);
        } catch (error) {
          streamPlaceholder.innerHTML = `<span class="assistant-avatar assistant-avatar-sm me-2" aria-hidden="true"><i class="bi bi-robot"></i></span><div class="alert alert-warning small mb-0">The assistant could not answer right now. Open the governed reports directly.</div>`;
        }
      }
      input.value = "";
      growAssistantInput();
      scrollAssistantToBottom();
    });
  }

  function addAssistantMessageToThread(result, userQuestion) {
    const conversation = $("#global-ai-assistant-conversation");
    if (!conversation) return;

    // Create assistant message bubble
    const assistantMsg = document.createElement("div");
    assistantMsg.className = "d-flex mb-3";
    const answerHtml = renderAiAgentAnswer(result);
    const meta = result?.agent || {};
    assistantMsg.innerHTML = `<span class="assistant-avatar assistant-avatar-sm me-2" aria-hidden="true"><i class="bi bi-robot"></i></span><div class="card border-0 shadow-sm flex-grow-1">${answerHtml}</div>`;
    conversation.appendChild(assistantMsg);

    // Re-bind follow-up buttons
    conversation.querySelectorAll(".ai-followup-question").forEach((button) =>
      button.addEventListener("click", () => {
        const input = $("#global-ai-assistant-question");
        if (input) {
          input.value = button.textContent;
          input.focus();
        }
      })
    );

    // If this is the first turn in a new session, update session title
    if (currentSessionId && conversation.querySelectorAll(".user-bubble").length === 1) {
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
        initAssistantChrome();
        renderAssistantHistory();
        void loadAiAssistantCatalog();
        void loadWorkspaceBriefing();
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
      initialize,
      { once: true }
    );
  } else {
    initialize();
  }
})();
