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
  const MOBILE_BREAKPOINT = 992;

  let initialized = false;
  let resizeTimer = null;
  let aiChartSequence = 0;
  const aiChartInstances = new Map();

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
    window.KingswayTheme?.hydrate?.();
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

  /** Governed answers render through the shared, XSS-safe Markdown module. */
  const mdRender = (value) =>
    (window.KingswayMarkdown?.render ? window.KingswayMarkdown.render(value) : escapeHtml(value));

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
  let aiBriefingRequestId = 0;
  let aiBriefingRoute = "";
  let delegatedTaskTimer = null;
  let delegatedTaskRequestId = 0;

  const AI_BRIEFING_TTL = 15 * 60 * 1000;

  async function fetchWorkspaceBriefing(route) {
    const payload = await window.API?.dashboard?.getWorkspaceBriefing?.(route);
    return payload?.data !== undefined ? payload.data : payload;
  }

  async function loadWorkspaceBriefing(attempt = 0) {
    const host = $("#global-ai-assistant-briefing");
    if (!host) return;
    const route = currentRoute() || "dashboard";
    if (route !== aiBriefingRoute) {
      aiBriefingRoute = route;
      aiBriefingSnapshot = null;
      if (aiBriefingTimer) clearTimeout(aiBriefingTimer);
      aiBriefingTimer = null;
      host.innerHTML = '<div class="text-muted small">Checking this workspace for useful insights…</div>';
    }
    const requestId = ++aiBriefingRequestId;
    const storeKey = "ai_workspace_briefing";

    if (attempt === 0 && !host.querySelector("[data-ai-briefing-body]")) {
      // Instant first paint from the persisted snapshot, before any network.
      const snapshotRequest = window.DataStore?.peek?.(storeKey, {
        storeName: "ai_workspace_cache",
        ttl: AI_BRIEFING_TTL,
        params: { route },
      });
      snapshotRequest?.then?.((snapshot) => {
        if (requestId === aiBriefingRequestId && route === (currentRoute() || "dashboard") && snapshot?.status === "ready" && snapshot?.briefing && !host.querySelector("[data-ai-briefing-body]")) {
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
            // A "generating" response is a transient state, not a usable
            // snapshot. Caching it makes every bounded poll read the same
            // stale placeholder and give up even after the briefing is ready.
            // Retries must go to the network so the ready state is seen.
            forceRefresh: attempt > 0,
            fetcher: () => fetchWorkspaceBriefing(route),
          })
        : await fetchWorkspaceBriefing(route);
      if (requestId !== aiBriefingRequestId || route !== (currentRoute() || "dashboard")) return;
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
      if (requestId !== aiBriefingRequestId || route !== (currentRoute() || "dashboard")) return;
      host.innerHTML =
        '<div class="alert alert-secondary small mb-0">Workspace briefing is unavailable right now. You can still ask the assistant a question below.</div>';
    }
  }

  function delegatedTaskStorageKey() {
    const user = window.AuthContext?.getUser?.() || {};
    return `kw.ai.delegated-task.${String(user.id || user.user_id || "current")}`;
  }

  function unwrapApiData(response) {
    return response?.data?.data ?? response?.data ?? response;
  }

  function renderDelegatedTask(task, question = "") {
    const host = $("#global-ai-assistant-task-status");
    if (!host || !task) return;
    const status = String(task.status || "queued");
    const result = task.result;
    if (status === "completed" && task.result_available === false) {
      host.innerHTML = `<div class="alert alert-warning small mb-0">${escapeHtml(task.message || "Your access changed while this task was processing. Start a new task to get a current answer.")}</div>`;
      return;
    }
    if (status === "completed" && result) {
      const answer = result.answer?.body || result.answer?.title || "The assistant completed this request.";
      host.innerHTML = `<div class="card border-primary-subtle shadow-sm">
        <div class="card-header bg-primary-subtle py-2 d-flex justify-content-between align-items-center">
          <strong class="small"><i class="bi bi-check-circle me-1"></i>Background result ready</strong>
          <button type="button" class="btn-close" data-dismiss-delegated-task aria-label="Dismiss result"></button>
        </div>
        <div class="card-body p-3">
          ${question ? `<p class="small text-muted mb-2">${escapeHtml(question)}</p>` : ""}
          <div class="small assistant-answer-body">${mdRender(answer)}</div>
          ${Array.isArray(result.answer?.next_steps) && result.answer.next_steps.length ? `<ul class="small mt-2 mb-0">${result.answer.next_steps.map((step) => `<li>${escapeHtml(step)}</li>`).join("")}</ul>` : ""}
        </div>
      </div>`;
      host.querySelector("[data-dismiss-delegated-task]")?.addEventListener("click", () => {
        host.innerHTML = "";
        try { localStorage.removeItem(delegatedTaskStorageKey()); } catch {}
      });
      return;
    }
    if (["failed", "cancelled"].includes(status)) {
      host.innerHTML = `<div class="alert alert-warning small mb-0"><strong>Background task ${escapeHtml(status)}.</strong> ${escapeHtml(task.error || "You can try again from this page.")}</div>`;
      return;
    }
    host.innerHTML = `<div class="alert alert-info small mb-0"><span class="spinner-border spinner-border-sm me-2" role="status"></span>${escapeHtml(task.message || "Your request is queued for the assistant.")}</div>`;
  }

  async function pollDelegatedTask(taskId, question = "", attempt = 0) {
    const requestId = ++delegatedTaskRequestId;
    const maxAttempts = 80;
    if (delegatedTaskTimer) clearTimeout(delegatedTaskTimer);
    try {
      const task = unwrapApiData(await window.API.dashboard.agentTaskGet(taskId));
      if (requestId !== delegatedTaskRequestId) return;
      if (!task) throw new Error("Task status is unavailable.");
      renderDelegatedTask(task, question);
      if (["completed", "failed", "cancelled"].includes(String(task.status))) {
        if (delegatedTaskTimer) clearTimeout(delegatedTaskTimer);
        return;
      }
      try { localStorage.setItem(delegatedTaskStorageKey(), JSON.stringify({ task_id: taskId, question })); } catch {}
      if (attempt < maxAttempts) {
        delegatedTaskTimer = setTimeout(() => void pollDelegatedTask(taskId, question, attempt + 1), 4000);
      } else {
        const host = $("#global-ai-assistant-task-status");
        if (host) host.innerHTML = '<div class="alert alert-info small mb-0">The task is still running. Its result will appear here when you reopen the assistant.</div>';
      }
    } catch (error) {
      if (requestId !== delegatedTaskRequestId) return;
      const host = $("#global-ai-assistant-task-status");
      if (host) host.innerHTML = `<div class="alert alert-warning small mb-0">${escapeHtml(error?.message || "Could not retrieve the background task.")}</div>`;
    }
  }

  async function restoreDelegatedTask() {
    try {
      const saved = JSON.parse(localStorage.getItem(delegatedTaskStorageKey()) || "null");
      if (saved?.task_id) await pollDelegatedTask(String(saved.task_id), String(saved.question || ""));
    } catch { /* storage may be unavailable or contain an old value */ }
  }

  async function delegateAssistantQuestion() {
    const input = $("#global-ai-assistant-question");
    const button = $("#global-ai-assistant-delegate");
    const question = String(input?.value || "").trim();
    if (!question) {
      window.showNotification?.("Enter a task for the assistant first.", "warning");
      input?.focus();
      return;
    }
    if (!window.API?.dashboard?.agentTaskCreate) {
      window.showNotification?.("Background assistant work is unavailable right now.", "error");
      return;
    }
    if (button) button.disabled = true;
    const host = $("#global-ai-assistant-task-status");
    if (host) host.innerHTML = '<div class="alert alert-info small mb-0"><span class="spinner-border spinner-border-sm me-2" role="status"></span>Sending this task to the assistant…</div>';
    try {
      const route = currentRoute() || "dashboard";
      const response = await window.API.dashboard.agentTaskCreate({
        tool: "assistant",
        question,
        route,
        module: route,
        session_id: String(currentSessionId || ""),
      });
      const task = unwrapApiData(response);
      if (!task?.task_id) throw new Error(task?.message || "The assistant did not accept this task.");
      try { localStorage.setItem(delegatedTaskStorageKey(), JSON.stringify({ task_id: String(task.task_id), question })); } catch {}
      input.value = "";
      growAssistantInput();
      renderDelegatedTask(task, question);
      void pollDelegatedTask(String(task.task_id), question);
    } catch (error) {
      if (host) host.innerHTML = `<div class="alert alert-warning small mb-0">${escapeHtml(error?.message || "The task could not be queued.")}</div>`;
    } finally {
      if (button) button.disabled = false;
    }
  }

  // Coworker "Today" card: a deterministic, permission-scoped scan surfaced
  // directly to the panel (no model round trip, no approval queue). It answers
  // the daily questions staff actually ask - what is on today, what needs me,
  // and what have I missed - using only data their role already grants.
  let aiTodayRequestId = 0;
  let aiTodayRoute = "";

  async function loadAssistantToday() {
    const host = $("#global-ai-assistant-today");
    if (!host) return;
    const route = currentRoute() || "dashboard";
    const requestId = ++aiTodayRequestId;
    if (route !== aiTodayRoute) {
      aiTodayRoute = route;
      host.innerHTML = '<div class="text-muted small">Reviewing your day…</div>';
    }
    try {
      const payload = await window.API?.dashboard?.getAssistantToday?.(route);
      if (requestId !== aiTodayRequestId) return;
      const scan = payload?.data !== undefined ? payload.data : payload;
      if (!scan || scan.status !== "ok") {
        host.innerHTML = '<div class="alert alert-secondary small mb-0">Your day overview is unavailable right now.</div>';
        return;
      }
      host.innerHTML = renderAssistantToday(scan);
    } catch {
      if (requestId !== aiTodayRequestId) return;
      host.innerHTML = '<div class="alert alert-secondary small mb-0">Your day overview is unavailable right now.</div>';
    }
  }

  function todaySection(label, icon, section) {
    const items = Array.isArray(section?.items) ? section.items : [];
    if (!items.length) return "";
    const shown = items.slice(0, 3);
    return `<div class="ai-today-row mb-2">
      <div class="small fw-semibold text-muted mb-1"><i class="bi bi-${icon} me-1"></i>${escapeHtml(label)}</div>
      <ul class="small mb-0 ps-3">${shown.map((item) => `<li>${escapeHtml(String(item))}</li>`).join("")}</ul>
    </div>`;
  }

  function renderAssistantToday(scan) {
    const sections = scan.sections || {};
    const date = String(scan.date || "");
    const unread = Math.max(0, Number(scan.unread_notification_count || 0));
    const attention = (sections.attention?.items || []).length;
    const greeting = new Date().getHours() < 12 ? "Good morning" : new Date().getHours() < 17 ? "Good afternoon" : "Good evening";
    const parts = ['<div class="card border-primary-subtle shadow-sm"><div class="card-body p-3">'];
    parts.push(`<div class="d-flex align-items-center justify-content-between mb-2">
      <h6 class="mb-0"><i class="bi bi-stars text-primary me-2"></i>${greeting}</h6>
      <small class="text-muted">${escapeHtml(date)}</small>
    </div>`);
    const chips = [];
    if (attention > 0) chips.push(`<span class="badge text-bg-warning">${attention} need${attention === 1 ? "s" : ""} attention</span>`);
    if (unread > 0) chips.push(`<span class="badge text-bg-info">${unread} unread</span>`);
    if (!chips.length) chips.push('<span class="badge text-bg-success">All clear</span>');
    parts.push(`<div class="d-flex flex-wrap gap-1 mb-3">${chips.join("")}</div>`);

    const body =
      todaySection("Today's schedule", "calendar2-check", sections.schedule) +
      todaySection("Needs your attention", "exclamation-triangle", sections.attention) +
      todaySection("Notifications", "bell", sections.notifications);
    parts.push(body || '<div class="alert alert-success small py-2 px-3">Nothing needs your attention right now.</div>');

    // Quick actions prefill the composer so a staff member can ask a coworker
    // question in one click. These are prompts, never background writes.
    parts.push(`<div class="ai-today-actions d-flex flex-wrap gap-1 mt-3 pt-2 border-top">
      <button type="button" class="btn btn-sm btn-outline-primary" data-ai-today-ask="What's new in Kenya's CBC and CBE curriculum this week?"><i class="bi bi-newspaper me-1"></i>Education news</button>
      <button type="button" class="btn btn-sm btn-outline-secondary" data-ai-today-ask="Suggest what the school should post this week and how."><i class="bi bi-megaphone me-1"></i>Posting ideas</button>
      <button type="button" class="btn btn-sm btn-outline-secondary" data-ai-today-ask="Give me three useful suggestions for my work today."><i class="bi bi-lightbulb me-1"></i>Suggestions</button>
    </div>`);
    parts.push('</div></div>');

    // Defer listener binding until after insertion. Reuse the established
    // opener so the question is actually submitted through the governed
    // stream path, not merely typed into the box.
    queueMicrotask(() => {
      document.querySelectorAll("#global-ai-assistant-today [data-ai-today-ask]").forEach((button) => {
        button.addEventListener("click", () => {
          const question = button.getAttribute("data-ai-today-ask") || "";
          if (question) openAssistantWith(question);
        });
      });
    });
    return parts.join("");
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
        // A cause the scan could not determine is filler, not information.
        if (finding?.root_cause && !/^not determinable/i.test(String(finding.root_cause).trim())) {
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

  const ASSISTANT_HISTORY_KEY = "kw_staff_assistant_history";
  const ASSISTANT_ACTIVE_SESSION_KEY = "kw_staff_assistant_active_session";

  // Session state
  let currentSessionId = null;
  let currentSessionMessages = [];
  let sessionsCache = [];
  let assistantGenerating = false;
  let activeAssistantStream = null;
  let activeAssistantPlaceholder = null;
  let assistantGenerationSeq = 0;
  let assistantLastQuestion = "";
  let assistantQueue = [];
  let assistantQueueProcessing = false;
  let assistantSessionFilter = "";

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
    // NOTE: Input is intentionally NOT disabled during generation to allow queueing
    // if (input) input.disabled = on;

    // The generation counter belongs to the caller, which already bumps it
    // before opening a stream. Incrementing here too made isCurrent() compare
    // against a later id, so every delta and final answer was discarded.
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

  // Simple queue for assistant questions
  async function processAssistantQueue() {
    if (assistantQueueProcessing || assistantQueue.length === 0) return;
    assistantQueueProcessing = true;
    while (assistantQueue.length > 0) {
      const { question, sessionId, resolve, reject } = assistantQueue.shift();
      if (sessionId && sessionId !== currentSessionId) {
        currentSessionId = sessionId;
        rememberActiveSession(currentSessionId);
        await loadAssistantSession(currentSessionId);
      }
      try {
        // Use the existing submit logic inline. The mounted composer id is
        // `global-ai-assistant-query`; the old `global-ai-assistant-form`
        // lookup made every queued question a silent no-op.
        const form = $("#global-ai-assistant-query");
        const input = $("#global-ai-assistant-question");
        if (form && input) {
          input.value = question;
          input.dispatchEvent(new Event("input", { bubbles: true }));
          // Trigger the form's submit handler
          const submitEvent = new Event("submit", { bubbles: true, cancelable: true });
          form.dispatchEvent(submitEvent);
        }
        resolve();
      } catch (error) {
        reject(error);
      }
    }
    assistantQueueProcessing = false;
  }

  function queueAssistantQuestion(question, sessionId = currentSessionId) {
    return new Promise((resolve, reject) => {
      assistantQueue.push({ question, sessionId, resolve, reject });
      if (!assistantQueueProcessing) {
        processAssistantQueue();
      }
    });
  }

  async function loadAssistantSessions() {
    try {
      // apiCall() already unwraps the envelope, so the result is the array
      // itself; a legacy `.data` access returned undefined and hid history.
      const res = await window.API.dashboard.getAssistantSessions({limit: 30});
      sessionsCache = Array.isArray(res) ? res : (res?.data || []);
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

    // Search input for session filtering
    const searchHtml = `
      <div class="px-2 pb-2">
        <input type="search" class="form-control form-control-sm assistant-session-search"
               placeholder="Search chats…" aria-label="Search chat history"
               value="${escapeHtml(assistantSessionFilter)}">
      </div>`;

    // Filter sessions by search query
    let filteredSessions = sessionsCache;
    if (assistantSessionFilter.trim()) {
      const query = assistantSessionFilter.trim().toLowerCase();
      filteredSessions = sessionsCache.filter((s) =>
        (s.title || "").toLowerCase().includes(query)
      );
    }

    if (!filteredSessions.length) {
      host.innerHTML = searchHtml + (sessionsCache.length
        ? '<p class="small text-muted px-2 mb-0">No matching conversations.</p>'
        : '<p class="small text-muted px-2 mb-0">Your conversations will appear here.</p>');
      // Bind search input
      host.querySelector(".assistant-session-search")?.addEventListener("input", (e) => {
        assistantSessionFilter = e.target.value;
        renderAssistantSessions();
      });
      return;
    }

    host.innerHTML = searchHtml + filteredSessions
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
    // The conversation pane is the single scroller; the thread grows inside it.
    const target = $("#global-ai-assistant-conversation");
    if (!target) return;
    target.scrollTop = target.scrollHeight;
  }

  // Fold the day/workspace insights away while a conversation is on screen,
  // and reopen them for a fresh chat, so the messages are never pushed out.
  function setAssistantInsightsOpen(open) {
    const insights = $("#global-ai-assistant-insights");
    if (insights) insights.open = !!open;
  }

  async function createAssistantSession() {
    try {
      const route = window.REQUESTED_ROUTE || "";
      const res = await window.API.dashboard.postAssistantSession({route, title: ""});
      const session = res && typeof res === "object" ? res : res?.data;
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
      const session = res && typeof res === "object" ? res : res?.data;
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
      setAssistantInsightsOpen(true);
      thread.innerHTML = getGreetingHtml();
      bindAssistantPromptChips(thread);
      return;
    }
    setAssistantInsightsOpen(false);
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
    const profileCopy = getAssistantProfileCopy();
    return `
      <div class="assistant-empty-state" id="global-ai-assistant-empty-state">
        <div class="d-flex gap-3 mb-3">
          <span class="assistant-avatar assistant-avatar-sm" aria-hidden="true"><i class="bi bi-robot"></i></span>
          <div>
            <p class="small mb-1">Hi <strong>${escapeHtml(displayName)}</strong>, ${escapeHtml(profileCopy.greeting)}</p>
          </div>
        </div>
        <div class="assistant-prompt-chips d-flex flex-wrap gap-2" id="global-ai-assistant-prompt-chips">
          ${profileCopy.prompts.map(([label, prompt]) => `<button type="button" class="btn btn-outline-secondary btn-sm" data-assistant-prompt="${escapeHtml(prompt)}">${escapeHtml(label)}</button>`).join("")}
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
    setAssistantInsightsOpen(true);
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
      <div class="small assistant-answer-body mb-2">${mdRender(msg.content || "")}</div>
    </div>`;
  }

  let assistantChromeBound = false;
  let assistantRecognition = null;

  async function initAssistantChrome() {
    // Legacy global assistant panel removed; ai_inline_draft.js is the single assistant.
    if (!document.getElementById("globalAiAssistantPanel")) return;
    if (assistantChromeBound) return;
    assistantChromeBound = true;

    const user = window.AuthContext?.getUser?.();
    const displayName = resolveDisplayName(user);
    const profileCopy = getAssistantProfileCopy();
    
    setText(".assistant-sidebar-brand-copy small", profileCopy.title);
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
        personality: {
          tone: $("#pref-persona-tone")?.value || "professional",
          verbosity: $("#pref-persona-detail")?.value || "balanced",
          focus: ($("#pref-persona-focus")?.value || "").trim(),
          goals: ($("#pref-persona-goals")?.value || "").trim(),
          proactive: $("#pref-persona-proactive")?.checked !== false,
        },
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

    $("#global-ai-assistant-delegate")?.addEventListener("click", () => {
      void delegateAssistantQuestion();
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

    const permissions = window.AuthContext?.getPermissions?.() || [];
    if (permissions.includes("system_admin")) {
      return "system_admin";
    }

    // Parent account → parent assistant (parent/guardian focused)
    const role = String(user.role_name || user.role || "").toLowerCase();
    if (String(user.account_type || "").toLowerCase() === "parent") {
      return "parent";
    }
    if (role === "parent") {
      return "parent";
    }

    // SCHOOL domain staff → staff assistant (educational/operational)
    return "staff";
  }

  function getAssistantRoleProfile() {
    if (getAssistantDomain() === "system_admin") return "system_admin";
    const user = window.AuthContext?.getUser?.() || {};
    const roleValues = [
      ...(window.AuthContext?.getRoles?.() || []),
      ...(Array.isArray(user.roles) ? user.roles : []),
      ...(Array.isArray(user.role_names) ? user.role_names : []),
      user.role_name,
      user.role,
    ];
    const roles = roleValues.map((role) => {
      const label = typeof role === "object" && role
        ? role.name || role.role_name || role.label || role.slug || ""
        : role;
      return String(label || "").toLowerCase().replace(/[^a-z0-9]+/g, " ").trim();
    });
    if (roles.some((role) => ["director", "director owner"].includes(role))) return "director";
    if (roles.some((role) => ["school administrator", "school admin"].includes(role))) {
      return "school_administrator";
    }
    return "staff";
  }

  function getAssistantProfileCopy() {
    const profiles = {
      system_admin: {
        title: "System Operations Assistant",
        greeting: "I can help review operational health, error signals, and safe fixes.",
        prompts: [
          ["System health", "Summarize the current authorized system health signals."],
          ["Recent errors", "Group the recent authorized error signals and suggest safe next steps."],
          ["Queue health", "Summarize queue health and any jobs needing administrator review."],
        ],
      },
      school_administrator: {
        title: "School Operations Assistant",
        greeting: "I can help coordinate school operations, review reports, and prepare work for your approval.",
        prompts: [
          ["Operations brief", "Summarize the key school operations items needing follow-up."],
          ["Admissions review", "Summarize the authorized admissions items that need review."],
          ["Plan a timetable", "Help me prepare a timetable plan and ask for any missing constraints."],
        ],
      },
      director: {
        title: "Director’s Briefing Assistant",
        greeting: "I can prepare concise, evidence-based school briefings and highlight changes that may need your attention.",
        prompts: [
          ["Executive brief", "Prepare an executive briefing from my authorized school reports."],
          ["Changes and risks", "Highlight the material changes and risks in my authorized reports."],
          ["Compare periods", "Compare available authorized school indicators across reporting periods."],
        ],
      },
      staff: {
        title: "Staff Assistant",
        greeting: "How can I help with your school work today?",
        prompts: [
          ["Key trends in reports", "What are the key trends in my authorized school reports?"],
          ["Admissions status", "Show me admissions status"],
          ["Workspace summary", "Summarize my workspace"],
        ],
      },
    };
    return profiles[getAssistantRoleProfile()] || profiles.staff;
  }

  // Backward-compatible shim: system admin assistant check
  function canSystemAssistant() {
    return getAssistantDomain() === "system_admin";
  }

  // ---------------------------------------------------------------- page context
  // Co-worker awareness: the assistant should know what the operator is looking
  // at without a second data path. We report only labels the page already
  // shows — the record last opened, the open dialog, selected text, visible KPI
  // names and filter labels. Values still come from governed tools.
  const assistantPageState = { entity: null };

  document.addEventListener(
    "click",
    (event) => {
      const target = event.target?.closest?.("[data-ai-entity]");
      if (!target) return;
      const label = String(
        target.getAttribute("data-ai-entity") || target.textContent || ""
      )
        .trim()
        .slice(0, 80);
      if (!label) return;
      assistantPageState.entity = {
        kind: target.getAttribute("data-ai-entity-kind") || "record",
        label,
        id: target.getAttribute("data-ai-id") || "",
        status: target.getAttribute("data-ai-status") || "",
      };
    },
    true,
  );

  function getAssistantPageContext() {
    const context = {};
    const entity = assistantPageState.entity;
    if (entity && entity.label) {
      context.page_entity = {
        kind: entity.kind,
        label: entity.label,
        ...(entity.id ? { id: entity.id } : {}),
        ...(entity.status ? { status: entity.status } : {}),
      };
    }
    const modal = document.querySelector(".modal.show");
    if (modal) {
      const title = String(
        modal.querySelector(".modal-title")?.textContent || ""
      )
        .trim()
        .slice(0, 100);
      if (title) {
        context.modal = title;
        if (!context.page_entity) {
          context.page_entity = { kind: "modal", label: title };
        }
      }
    }
    const selection = window.getSelection?.()?.toString?.().trim() || "";
    if (selection) {
      context.selection = { text: selection.slice(0, 200) };
    }
    const metrics = Array.from(document.querySelectorAll("[data-ai-metric]"))
      .map((node) => String(node.textContent || "").trim().slice(0, 60))
      .filter(Boolean)
      .slice(0, 10);
    if (metrics.length) {
      context.visible_metrics = metrics;
    }
    const filters = {};
    document.querySelectorAll("[data-ai-filter]").forEach((node) => {
      const key = node.getAttribute("data-ai-filter");
      const value = String(node.value ?? node.textContent ?? "").trim();
      if (key && value) filters[key] = value.slice(0, 60);
    });
    if (Object.keys(filters).length) {
      context.filters = filters;
    }
    return context;
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

  // ---------------------------------------------------------------- proactive
  //
  // A workspace that sits idle, or a long page that stops scrolling, is a
  // moment where the staff member is thinking rather than clicking. That is
  // when one concrete next question is useful and when a chat launcher is
  // least intrusive. The nudge is deliberately quiet:
  //
  //   * one hint at a time, at most once per page state per browser session;
  //   * never while the panel is open, a modal is up, text is selected, the
  //     tab is hidden, or an answer is streaming;
  //   * never a claim - the server sends a question offer, not a figure.
  const PROACTIVE_IDLE_MS = 30000;
  // Each empty or failed answer doubles the wait (30 s -> 15 min). An idle
  // tab asked the AI engine every 30 s forever, which kept it busy enough
  // that interactive assistant calls timed out.
  const PROACTIVE_MAX_BACKOFF_MS = 15 * 60 * 1000;
  let proactiveBackoffMs = PROACTIVE_IDLE_MS;
  const PROACTIVE_SCROLL_SETTLE_MS = 2500;
  const PROACTIVE_MIN_SCROLL = 500;
  const PROACTIVE_SEEN_KEY = "kw_assistant_nudge_seen";
  const PROACTIVE_OFF_KEY = "kw_assistant_nudge_off";

  let proactiveIdleTimer = null;
  let proactiveScrollTimer = null;
  let proactiveInFlight = false;
  let proactiveBound = false;
  let proactiveCurrent = null;

  function proactiveSeen() {
    try {
      return new Set(JSON.parse(sessionStorage.getItem(PROACTIVE_SEEN_KEY) || "[]"));
    } catch {
      return new Set();
    }
  }

  function markProactiveSeen(signature) {
    if (!signature) return;
    try {
      const seen = proactiveSeen();
      seen.add(signature);
      // A session can wander many pages; keep the ring small.
      sessionStorage.setItem(
        PROACTIVE_SEEN_KEY,
        JSON.stringify(Array.from(seen).slice(-40))
      );
    } catch {
      /* private mode: the nudge simply repeats, which is harmless */
    }
  }

  function proactiveDisabled() {
    try {
      return localStorage.getItem(PROACTIVE_OFF_KEY) === "1";
    } catch {
      return false;
    }
  }

  function assistantPanelOpen() {
    return !document.getElementById("kwAiAssistPanel")?.hidden;
  }

  function proactiveBlocked() {
    if (proactiveDisabled() || proactiveInFlight) return true;
    if (document.hidden || assistantPanelOpen()) return true;
    if (document.querySelector(".modal.show")) return true;
    if (assistantGenerating) return true;
    if (window.getSelection?.()?.toString?.().trim()) return true;
    const active = document.activeElement;
    if (
      active &&
      (active.tagName === "INPUT" ||
        active.tagName === "TEXTAREA" ||
        active.tagName === "SELECT" ||
        active.isContentEditable)
    ) {
      return true;
    }
    return false;
  }

  function hideProactiveNudge() {
    proactiveCurrent = null;
    document.getElementById("assistant-proactive-nudge")?.remove();
  }

  function openAssistantWith(question) {
    const q = String(question || "").slice(0, 600);
    const ka = window.KingswayAssistant;
    // Never call ourselves through the shim: that recursed forever when the
    // session-aware panel loaded before this legacy shell script.
    if (ka?.ask && ka.ask !== openAssistantWith) { ka.ask(q); return; }
    const panel = document.getElementById("kwAiAssistPanel");
    if (panel) {
      panel.hidden = false;
      const input = panel.querySelector("#kwAiInput");
      const send = panel.querySelector("#kwAiSend");
      if (input) input.value = q;
      send?.click();
    }
  }

  // Compatibility shim only: the session-aware panel owns the assistant UI.
  if (typeof window.KingswayAssistant?.ask !== "function" || window.KingswayAssistant.ask === openAssistantWith) {
    window.KingswayAssistant = Object.assign(window.KingswayAssistant || {}, {
      ask: (question) => openAssistantWith(question),
    });
  }

  function renderProactiveNudge(suggestion, signature) {
    hideProactiveNudge();
    const title = String(suggestion?.title || "").trim().slice(0, 120);
    const prompt = String(suggestion?.prompt || "").trim().slice(0, 300);
    const reason = String(suggestion?.reason || "").trim().slice(0, 160);
    if (!title || !prompt) return;

    const nudge = document.createElement("div");
    nudge.id = "assistant-proactive-nudge";
    nudge.className = "ai-nudge";
    nudge.setAttribute("role", "status");
    nudge.innerHTML = `
      <div class="ai-nudge-head">
        <span class="ai-nudge-label"><i class="bi bi-stars me-1" aria-hidden="true"></i>Suggested next step</span>
        <button type="button" class="btn-close btn-close-sm" data-ai-nudge-dismiss aria-label="Dismiss suggestion"></button>
      </div>
      <p class="ai-nudge-title">${escapeHtml(title)}</p>
      ${reason ? `<p class="ai-nudge-reason">${escapeHtml(reason)}</p>` : ""}
      <div class="d-flex gap-2">
        <button type="button" class="btn btn-sm btn-primary" data-ai-nudge-ask>Ask this</button>
        <button type="button" class="btn btn-sm btn-outline-secondary" data-ai-nudge-dismiss>Not now</button>
      </div>`;

    nudge.querySelector("[data-ai-nudge-ask]")?.addEventListener("click", () => {
      markProactiveSeen(signature);
      hideProactiveNudge();
      openAssistantWith(prompt);
    });
    nudge.querySelectorAll("[data-ai-nudge-dismiss]").forEach((button) => {
      button.addEventListener("click", () => {
        markProactiveSeen(signature);
        hideProactiveNudge();
      });
    });

    document.body.appendChild(nudge);
    proactiveCurrent = { suggestion, signature };
  }

  async function requestProactiveNudge() {
    if (document.hidden) return;
    if (proactiveBlocked()) return;
    const suggest = window.API?.dashboard?.agentSuggest;
    if (typeof suggest !== "function") return;
    proactiveInFlight = true;
    try {
      const response = await suggest(
        String(window.REQUESTED_ROUTE || ""),
        "dashboard",
        getAssistantPageContext()
      );
      const payload = response?.data ?? response ?? {};
      const suggestions = Array.isArray(payload.suggestions) ? payload.suggestions : [];
      const signature = String(payload.signature || "");
      if (!suggestions.length) {
        proactiveBackoffMs = Math.min(proactiveBackoffMs * 2, PROACTIVE_MAX_BACKOFF_MS);
        return;
      }
      proactiveBackoffMs = PROACTIVE_IDLE_MS;
      if (signature && proactiveSeen().has(signature)) return;
      if (proactiveBlocked()) return;
      const first = suggestions.find((item) => item && item.title && item.prompt);
      if (!first) return;
      renderProactiveNudge(first, signature);
    } catch {
      /* a nudge is never worth surfacing an error */
      proactiveBackoffMs = Math.min(proactiveBackoffMs * 2, PROACTIVE_MAX_BACKOFF_MS);
    } finally {
      proactiveInFlight = false;
    }
  }

  function scheduleProactiveNudge(delay = proactiveBackoffMs) {
    window.clearTimeout(proactiveIdleTimer);
    if (proactiveDisabled()) return;
    proactiveIdleTimer = window.setTimeout(() => {
      void requestProactiveNudge();
      // If the moment was not right (a modal, a selection), try once more
      // after a full idle window rather than on a tight loop.
      scheduleProactiveNudge(proactiveBackoffMs);
    }, Math.max(5000, delay));
  }

  function initProactiveAssistant() {
    if (!document.getElementById("globalAiAssistantPanel")) return;
    if (proactiveBound) return;
    proactiveBound = true;

    // A hint belongs to the page it was offered on. Navigation clears it and
    // restarts the idle clock on the new workspace.
    const resetForPage = () => {
      proactiveBackoffMs = PROACTIVE_IDLE_MS;
      hideProactiveNudge();
      scheduleProactiveNudge();
    };
    window.addEventListener("kingsway:page-loaded", resetForPage);
    window.addEventListener("hashchange", resetForPage);
    window.addEventListener("popstate", resetForPage);

    const noteActivity = () => {
      if (proactiveCurrent) return;
      scheduleProactiveNudge();
    };
    document.addEventListener("pointerdown", noteActivity, { passive: true, capture: true });
    document.addEventListener("keydown", noteActivity, { capture: true });

    let lastScrollY = window.scrollY || 0;
    window.addEventListener(
      "scroll",
      () => {
        const now = window.scrollY || 0;
        const travelled = Math.abs(now - lastScrollY);
        if (travelled > 24) {
          lastScrollY = now;
          hideProactiveNudge();
        }
        window.clearTimeout(proactiveScrollTimer);
        proactiveScrollTimer = window.setTimeout(() => {
          if (now < PROACTIVE_MIN_SCROLL) return;
          void requestProactiveNudge();
        }, PROACTIVE_SCROLL_SETTLE_MS);
        scheduleProactiveNudge();
      },
      { passive: true }
    );

    scheduleProactiveNudge();
  }

  function initAiAssistantQuery() {
    if (!document.getElementById("global-ai-assistant-query")) return;
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

        // Store last question for regenerate/edit actions
        assistantLastQuestion = question;

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
        setAssistantInsightsOpen(false);
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
          ...getAssistantPageContext(),
        };

        const stream = window.API?.dashboard?.agentAssistStream;
        let handled = false;
        // Raw text accumulated from the stream, rendered as markdown as it lands.
        let streamedAnswerText = "";
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
                  if (!bubble) return;
                  // Accumulate raw text and re-render markdown so bold/lists
                  // appear progressively instead of literal asterisks.
                  streamedAnswerText += chunk;
                  bubble.classList.add("assistant-answer-body");
                  bubble.innerHTML = mdRender(streamedAnswerText);
                },
                onError: (message) => {
                  if (!isCurrent()) return;
                  const bubble = streamPlaceholder.querySelector(".ai-stream-bubble");
                  if (bubble && !streamedAnswerText.trim()) {
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
              hydrateAiCharts(assistantMsg, result?.charts);
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
        hydrateAiCharts(assistantMsg, result?.charts);
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
      // api.js already unwraps the envelope, so `res` is the payload itself.
      // Accepting both shapes keeps this safe if the transport changes.
      const prefs = res?.data || res || {};
      const saveHistory = $("#pref-save-history");
      const responseStyle = $("#pref-response-style");
      const autoCharts = $("#pref-auto-charts");
      const languageField = $("#pref-language");
      if (saveHistory) saveHistory.checked = prefs.save_history !== false;
      if (responseStyle) responseStyle.value = prefs.response_style || "balanced";
      if (autoCharts) autoCharts.checked = prefs.auto_charts === true;
      if (languageField) languageField.value = prefs.language || "en";
      const persona = prefs.personality || {};
      if ($("#pref-persona-tone")) $("#pref-persona-tone").value = persona.tone || "professional";
      if ($("#pref-persona-detail")) $("#pref-persona-detail").value = persona.verbosity || "balanced";
      if ($("#pref-persona-focus")) $("#pref-persona-focus").value = persona.focus || "";
      if ($("#pref-persona-goals")) $("#pref-persona-goals").value = persona.goals || "";
      if ($("#pref-persona-proactive")) $("#pref-persona-proactive").checked = persona.proactive !== false;
    }).catch(() => {});
  }

  function renderAiAgentAnswer(result) {
    if (!result || typeof result !== "object") {
      return '<div class="alert alert-warning small mb-0">No answer was returned.</div>';
    }
    if (result.status === "unavailable") {
      return `<div class="alert alert-warning small mb-0">${mdRender(
        result.answer?.body || "The assistant is not available right now."
      )}</div>`;
    }
    if (!result.answer || !(result.answer.body || result.answer.title)) {
      return '<div class="alert alert-info small mb-0">The assistant could not answer that question.</div>';
    }

    const agent = result.agent || {};
    const toolsUsed = Array.isArray(result.tools_used) ? result.tools_used : [];
    const citations = Array.isArray(result.citations) ? result.citations : [];
    const isInsufficient = result.answer?.insufficient_data === true;

    const nextSteps = Array.isArray(result.answer.next_steps) && result.answer.next_steps.length
      ? `<ul class="small mb-2">${result.answer.next_steps
          .map((step) => `<li>${escapeHtml(String(step))}</li>`)
          .join("")}</ul>`
      : "";

    const tools = toolsUsed.length
      ? '<span class="small text-muted">Grounded in authorized school information</span>'
      : "";

    const citationsHtml = citations.length
      ? `<div class="citations mt-2 mb-2">
          <h6 class="small mb-1">Sources</h6>
          <ul class="small mb-0">
            ${citations.map(c => `<li><strong>${escapeHtml(c.title || c.report_code || "Report")}</strong>: ${escapeHtml(c.excerpt || c.description || "")}</li>`).join("")}
          </ul>
        </div>`
      : "";
    const chartsHtml = renderAiCharts(result);
    const artifactsHtml = renderAiArtifacts(result);

    const insufficientHtml = isInsufficient
      ? `<div class="alert alert-info small mb-2">
          <i class="bi bi-info-circle me-1"></i>
          <strong>Insufficient data</strong>: The report returned no rows for the current filters. Try adjusting the time range, class, or other filters.
        </div>`
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

    // Action buttons for assistant message
    const actionButtons = `
      <div class="d-flex gap-1 mt-2 ai-message-actions">
        <button type="button" class="btn btn-sm btn-outline-secondary ai-regenerate-btn" title="Regenerate answer" data-question="${escapeHtml(assistantLastQuestion || "")}">
          <i class="bi bi-arrow-clockwise"></i> Regenerate
        </button>
        <button type="button" class="btn btn-sm btn-outline-secondary ai-edit-btn" title="Edit question">
          <i class="bi bi-pencil"></i> Edit
        </button>
        <button type="button" class="btn btn-sm btn-outline-secondary ai-copy-btn" title="Copy answer">
          <i class="bi bi-clipboard"></i> Copy
        </button>
        <button type="button" class="btn btn-sm btn-outline-secondary ai-download-btn" title="Download answer">
          <i class="bi bi-download"></i> Download
        </button>
      </div>`;

    // The governed answers repeat the opening line of the body in `title`
    // (title = body[:100]). Rendering both produced a heading that parroted the
    // first sentence, so the title is only shown when it adds information.
    const bodyText = String(result.answer.body || "");
    const titleText = String(result.answer.title || "").trim();
    const titleRedundant =
      !titleText ||
      titleText === "Assistant" ||
      bodyText.trim().startsWith(titleText) ||
      titleText.startsWith(bodyText.trim().slice(0, titleText.length));
    const heading = titleRedundant
      ? ""
      : `<h6 class="mb-0">${escapeHtml(titleText)}</h6>`;

    // A governed refusal (confidentiality probe, prompt injection, or a role
    // that lacks the scope) still carries a complete answer. Only the label
    // changes so the reader knows it was a deliberate policy answer rather
    // than a report result.
    const isRefusal = result.status !== "answered";
    const notice = isRefusal
      ? `<div class="alert alert-warning small mb-2 py-2">
           <i class="bi bi-shield-lock me-1"></i>${escapeHtml(
             result.routing === "confidentiality_guard"
               ? "Not shareable — that asks about the system itself."
               : "Outside your authorized access."
           )}
         </div>`
      : "";

    const rendered = `<div class="card border-0 shadow-sm mb-3"><div class="card-body">
      <div class="d-flex justify-content-between align-items-start gap-2 mb-2">
        ${heading || `<span class="small text-muted">${escapeHtml(isRefusal ? "Governed response" : "Governed answer")}</span>`}
        <span class="badge text-bg-light">${escapeHtml(agent.name || "Agent")}</span>
      </div>
      ${notice}
      ${insufficientHtml}
      <div class="small assistant-answer-body mb-2">${mdRender(bodyText)}</div>
      ${nextSteps}
      ${tools}
      ${citationsHtml}
      ${chartsHtml}
      ${artifactsHtml}
      ${escalation}
      ${followUps}
      ${actionButtons}
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
      // Action button handlers
      document
        .querySelectorAll("#global-ai-assistant-thread .ai-regenerate-btn")
        .forEach((button) =>
          button.addEventListener("click", () => {
            const question = button.getAttribute("data-question");
            if (question) {
              queueAssistantQuestion(question);
            }
          })
        );
      document
        .querySelectorAll("#global-ai-assistant-thread .ai-edit-btn")
        .forEach((button) =>
          button.addEventListener("click", () => {
            const input = $("#global-ai-assistant-question");
            if (input) {
              input.value = button.getAttribute("data-question") || "";
              input.focus();
            }
          })
        );
      document
        .querySelectorAll("#global-ai-assistant-thread .ai-copy-btn")
        .forEach((button) =>
          button.addEventListener("click", () => {
            const card = button.closest(".card");
            const body = card?.querySelector(".assistant-answer-body")?.textContent || "";
            navigator.clipboard.writeText(body).then(() => {
              button.innerHTML = '<i class="bi bi-check"></i> Copied!';
              setTimeout(() => { button.innerHTML = '<i class="bi bi-clipboard"></i> Copy'; }, 2000);
            });
          })
        );
      document
        .querySelectorAll("#global-ai-assistant-thread .ai-download-btn")
        .forEach((button) =>
          button.addEventListener("click", () => {
            const card = button.closest(".card");
            const body = card?.querySelector(".assistant-answer-body")?.textContent || "";
            const heading = card?.querySelector("h6")?.textContent?.trim() || "Assistant answer";
            const slug = heading.toLowerCase().replace(/[^a-z0-9]+/g, "-").replace(/^-|-$/g, "").slice(0, 60) || "answer";
            downloadBlob(
              `${slug}.md`,
              new Blob([`# ${heading}\n\n${body}\n`], { type: "text/markdown;charset=utf-8" })
            );
          })
        );
      document
        .querySelectorAll("#global-ai-assistant-thread .ai-artifact-download")
        .forEach((button) =>
          button.addEventListener("click", () => {
            const artifact = aiArtifactStore.get(button.getAttribute("data-artifact-key"));
            if (!artifact) return;
            downloadBlob(
              String(artifact.download_name || "artifact.txt"),
              new Blob([String(artifact.content || "")], {
                type: String(artifact.mime_type || "text/plain") + ";charset=utf-8",
              })
            );
          })
        );
    });
    return rendered;
  }

  const aiArtifactStore = new Map();
  let aiArtifactSequence = 0;

  function downloadBlob(filename, blob) {
    const url = URL.createObjectURL(blob);
    const link = document.createElement("a");
    link.href = url;
    link.download = filename || "download";
    document.body.appendChild(link);
    link.click();
    link.remove();
    setTimeout(() => URL.revokeObjectURL(url), 4000);
  }

  // The render window. An answer may carry a real figure (PNG data URI from
  // analytics.chart), a PDF/DOCX/audio/video URL, or plain text, and each is
  // rendered with the right element plus a download control — the coworker
  // produces deliverables, not only prose.
  function renderAiArtifacts(result) {
    const artifacts = Array.isArray(result?.artifacts) ? result.artifacts.slice(0, 4) : [];
    if (!artifacts.length) return "";
    const blocks = artifacts.map((artifact) => {
      if (!artifact || typeof artifact !== "object") return "";
      const mime = String(artifact.mime_type || "").toLowerCase();
      const url = String(artifact.url || artifact.image || "");
      const name = String(artifact.download_name || "artifact");
      const title = String(artifact.title || "Attachment").slice(0, 120);
      const summary = artifact.summary
        ? `<p class="small text-muted mb-1">${escapeHtml(String(artifact.summary))}</p>`
        : "";

      if (url && (artifact.image || mime.startsWith("image/"))) {
        return `<figure class="ai-artifact mb-3">
          <figcaption class="small fw-semibold mb-1">${escapeHtml(title)}</figcaption>
          ${summary}
          <img src="${escapeHtml(url)}" alt="${escapeHtml(title)}" class="img-fluid rounded border" loading="lazy" />
          <a class="btn btn-sm btn-outline-secondary mt-2" href="${escapeHtml(url)}" download="${escapeHtml(name)}">
            <i class="bi bi-download me-1"></i>Download image
          </a>
        </figure>`;
      }
      if (url && mime.startsWith("audio/")) {
        return `<figure class="ai-artifact mb-3">
          <figcaption class="small fw-semibold mb-1">${escapeHtml(title)}</figcaption>
          ${summary}
          <audio controls preload="none" class="w-100" src="${escapeHtml(url)}"></audio>
          <a class="btn btn-sm btn-outline-secondary mt-2" href="${escapeHtml(url)}" download="${escapeHtml(name)}">Download audio</a>
        </figure>`;
      }
      if (url && mime.startsWith("video/")) {
        return `<figure class="ai-artifact mb-3">
          <figcaption class="small fw-semibold mb-1">${escapeHtml(title)}</figcaption>
          ${summary}
          <video controls preload="metadata" class="w-100 rounded border" src="${escapeHtml(url)}"></video>
          <a class="btn btn-sm btn-outline-secondary mt-2" href="${escapeHtml(url)}" download="${escapeHtml(name)}">Download video</a>
        </figure>`;
      }
      if (url && mime === "application/pdf") {
        return `<figure class="ai-artifact mb-3">
          <figcaption class="small fw-semibold mb-1">${escapeHtml(title)}</figcaption>
          ${summary}
          <iframe src="${escapeHtml(url)}" title="${escapeHtml(title)}" class="w-100 rounded border" style="height:420px"></iframe>
          <a class="btn btn-sm btn-outline-secondary mt-2" href="${escapeHtml(url)}" download="${escapeHtml(name)}">Download PDF</a>
        </figure>`;
      }
      // A Word document is previewed as a page of text, not rendered: a browser
      // cannot render DOCX, and forcing it to download hid the file entirely
      // until the staff member went looking for it.
      if (url && (mime.includes("wordprocessingml") || name.toLowerCase().endsWith(".docx"))) {
        const size = artifact.size_bytes
          ? `<span class="badge text-bg-light border me-2">${(artifact.size_bytes / 1024).toFixed(0)} KB</span>`
          : "";
        return `<figure class="ai-artifact mb-3">
          <figcaption class="small fw-semibold mb-1">${escapeHtml(title)}</figcaption>
          ${summary}
          <div class="d-flex align-items-center gap-2">
            <span class="badge text-bg-light border">Word document</span>
            ${size}
            <a class="btn btn-sm btn-outline-secondary" href="${escapeHtml(url)}" download="${escapeHtml(name)}">
              <i class="bi bi-file-earmark-word me-1"></i>Download DOCX
            </a>
          </div>
        </figure>`;
      }
      // Anything else that carries a URL (DOCX, XLSX, a ZIP of exports) is a
      // download rather than something a browser can render inline. Without
      // this branch a non-previewable attachment silently vanished from the
      // render window.
      if (url) {
        const key = `ai-artifact-${++aiArtifactSequence}`;
        aiArtifactStore.set(key, artifact);
        while (aiArtifactStore.size > 20) {
          aiArtifactStore.delete(aiArtifactStore.keys().next().value);
        }
        return `<figure class="ai-artifact mb-3">
          <figcaption class="small fw-semibold mb-1">${escapeHtml(title)}</figcaption>
          ${summary}
          <div class="d-flex align-items-center gap-2">
            <span class="badge text-bg-light border">${escapeHtml(name)}</span>
            <a class="btn btn-sm btn-outline-secondary" href="${escapeHtml(url)}" download="${escapeHtml(name)}">
              <i class="bi bi-download me-1"></i>Download
            </a>
          </div>
        </figure>`;
      }
      if (artifact.content) {
        const key = `ai-artifact-${++aiArtifactSequence}`;
        aiArtifactStore.set(key, artifact);
        while (aiArtifactStore.size > 20) {
          aiArtifactStore.delete(aiArtifactStore.keys().next().value);
        }
        return `<figure class="ai-artifact mb-3">
          <figcaption class="small fw-semibold mb-1">${escapeHtml(title)}</figcaption>
          ${summary}
          <pre class="small bg-light border rounded p-2" style="max-height:260px;overflow:auto;white-space:pre-wrap">${escapeHtml(String(artifact.content))}</pre>
          <button type="button" class="btn btn-sm btn-outline-secondary ai-artifact-download" data-artifact-key="${key}">
            <i class="bi bi-download me-1"></i>Download ${escapeHtml(name)}
          </button>
        </figure>`;
      }
      return "";
    });
    const rendered = blocks.filter(Boolean).join("");
    return rendered ? `<div class="ai-artifacts mt-2">${rendered}</div>` : "";
  }

  function renderAiCharts(result) {
    const charts = Array.isArray(result?.charts) ? result.charts.slice(0, 3) : [];
    if (!charts.length) return "";
    return charts.map((item) => {
      const spec = item?.chart;
      if (!spec || !["bar", "line", "pie", "doughnut", "area"].includes(spec.type)) return "";
      const id = `ai-chart-${++aiChartSequence}`;
      const label = String(spec.title || "Governed report chart").slice(0, 120);
      const shown = Array.isArray(spec.labels) ? Math.min(spec.labels.length, 25) : 0;
      const note = item.preview_limited
        ? `<p class="small text-muted mb-1">Showing ${shown} of ${escapeHtml(String(item.row_count ?? shown))} rows. Open the report for the full result.</p>`
        : "";
      return `<figure class="ai-chart-panel mb-3"><figcaption class="small fw-semibold mb-1">${escapeHtml(label)}</figcaption>${note}<div style="height:240px;position:relative"><canvas id="${id}" role="img" aria-label="${escapeHtml(label)}"></canvas></div></figure>`;
    }).join("");
  }

  function hydrateAiCharts(root, chartSpecs) {
    const canvases = $$('canvas[id^="ai-chart-"]', root);
    if (!canvases.length || typeof window.Chart !== "function") return;
    const charts = Array.isArray(chartSpecs) ? chartSpecs.slice(0, 3) : [];
    canvases.forEach((canvas, index) => {
      const item = charts[index];
      const spec = item?.chart;
      if (!spec || !Array.isArray(spec.labels) || !Array.isArray(spec.datasets)) return;
      const datasets = spec.datasets.slice(0, 4).map((dataset) => ({
        label: String(dataset?.label || "Values").slice(0, 80),
        data: (Array.isArray(dataset?.data) ? dataset.data : []).slice(0, 25).map((value) => value !== null && Number.isFinite(Number(value)) ? Number(value) : null),
        backgroundColor: dataset?.backgroundColor || undefined,
        borderColor: dataset?.borderColor || undefined,
      }));
      const type = spec.type === "area" ? "line" : spec.type;
      const chart = new window.Chart(canvas, {
        type,
        data: { labels: spec.labels.slice(0, 25).map((value) => String(value).slice(0, 120)), datasets },
        options: { responsive: true, maintainAspectRatio: false, plugins: { title: { display: true, text: String(spec.title || "Report chart").slice(0, 120) } } },
      });
      aiChartInstances.set(canvas.id, chart);
      while (aiChartInstances.size > 10) {
        const oldest = aiChartInstances.keys().next().value;
        aiChartInstances.get(oldest)?.destroy?.();
        aiChartInstances.delete(oldest);
      }
    });
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

  // Kept for callers that still think in light/dark. The theme controller owns
  // storage, root state, icon state and cross-tab broadcasting.
  function setTheme(dark) {
    if (window.KingswayTheme?.setPreference) {
      window.KingswayTheme.setPreference(
        dark ? "dark" : "light",
        { persist: true, broadcast: true, animate: true }
      );
      return;
    }

    document.body.classList.toggle("app-dark", dark);
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

    let assistantPanelHydrated = false;
    function hydrateOpenAssistantPanel() {
      if (assistantPanelHydrated) return;
      assistantPanelHydrated = true;
      initAssistantChrome();
      renderAssistantHistory();
      initAiAssistantQuery();
      initProactiveAssistant();
      void loadAssistantToday();
      void loadWorkspaceBriefing(0);
      void restoreDelegatedTask();
    }

    // Legacy globalAiAssistantPanel removed. Universal AI panel is ai_inline_draft.js.

    // legacy globalAiAssistantPanel shown handler removed

    $("#global-search-input")?.addEventListener(
      "input",
      (event) => renderSearch(event.target.value)
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

    // The head bootstrap and theme_manager.js have already applied the stored
    // preference before this shell initializes.
    window.KingswayTheme?.hydrate?.();

    bindEvents();
    window.addEventListener("kingsway:page-loaded", () => {
      if (document.getElementById("globalAiAssistantPanel")?.classList.contains("show") || !document.getElementById("kwAiAssistPanel")?.hidden) {
        void loadAssistantToday();
        void loadWorkspaceBriefing(0);
      }
    });
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
        initProactiveAssistant();
      },
      { once: true }
    );
  } else {
    initialize();
    initAssistantChrome();
    initAiAssistantQuery();
    initProactiveAssistant();
  }
})();
