/**
 * Kingsway authenticated theme controller.
 *
 * Owns the single source of truth for light/dark presentation across every tab
 * and window opened by the same authenticated user. The head bootstrap script
 * in home.php applies the initial value before first paint; this module then
 * takes over the header toggle, system-preference changes, storage events and
 * cross-tab broadcasting.
 */
(() => {
  "use strict";

  const SYSTEM = "system";
  const LIGHT = "light";
  const DARK = "dark";
  const LEGACY_KEY = "kingsway_theme";
  const OWNER_KEY = "kingsway_theme_owner";
  const ACTIVE_USER_KEY = "kw_active_user_id";
  const CHANNEL_NAME = "kingsway:theme";
  const SWITCH_CLASS = "kw-theme-switching";

  let preference = SYSTEM;
  let resolved = LIGHT;
  let userId = "";
  let channel = null;
  let switchTimer = null;
  let bound = false;
  let boundButton = null;

  // Page partials and JS templates use a mix of Bootstrap utility classes,
  // stylesheet blocks and hard-coded inline colours. Keep the original inline
  // declarations so light mode is restored byte-for-byte.
  const originalInlineStyles = new WeakMap();
  let inlineFrame = null;
  let inlineObserver = null;
  const KEEP_LIGHT = [
    "[data-kw-keep-light]",
    ".web-preview-stage",
    ".qr-code",
    ".qrcode",
    '[class*="qr-code"]',
    ".certificate",
    ".certificate-watermark",
    ".official-seal",
    ".payslip-document",
    ".id-card-preview-front",
    ".id-card-preview-back",
    ".print-preview-stage",
    ".web-canvas",
    '[class*="swatch"]',
    '[class*="signature"]',
    "canvas",
    "svg",
    "iframe",
  ].join(", ");

  function storage() {
    try {
      return window.localStorage || null;
    } catch (_) {
      return null;
    }
  }

  function normalize(value) {
    return value === LIGHT || value === DARK ? value : SYSTEM;
  }

  function currentUserId() {
    // Standalone shells (parent portal, QR scanner) publish their authenticated
    // identity here before the controller boots. It wins over staff storage so
    // a parent and a staff member sharing a browser never share a preference.
    const published = String(window.KW_THEME_USER_ID || "").trim();
    if (published) return published;

    const user = window.AuthContext?.getUser?.();
    const id = user?.id ?? user?.user_id ?? user?.user_id_number;
    if (id !== undefined && id !== null && String(id) !== "") {
      return String(id);
    }

    const store = storage();
    try {
      return String(store?.getItem(ACTIVE_USER_KEY) || "");
    } catch (_) {
      return "";
    }
  }

  function scopedKey(id = userId) {
    return id ? `kw_${id}_theme` : LEGACY_KEY;
  }

  function scope(id = userId) {
    return id || "anonymous";
  }

  function readPreference() {
    const store = storage();
    if (!store) return SYSTEM;

    try {
      userId = currentUserId();
      const key = scopedKey(userId);
      const own = normalize(store.getItem(key));
      if (own !== SYSTEM) return own;

      // Migrate the pre-shell global value once, but never let one user's old
      // choice leak into another user's session on a shared browser profile.
      if (userId) {
        const owner = String(store.getItem(OWNER_KEY) || "");
        const legacy = normalize(store.getItem(LEGACY_KEY));
        if (legacy !== SYSTEM && (!owner || owner === userId)) {
          store.setItem(OWNER_KEY, userId);
          store.setItem(key, legacy);
          return legacy;
        }
        return SYSTEM;
      }

      return normalize(store.getItem(LEGACY_KEY));
    } catch (_) {
      return SYSTEM;
    }
  }

  function systemPrefersDark() {
    try {
      return window.matchMedia?.("(prefers-color-scheme: dark)").matches === true;
    } catch (_) {
      return false;
    }
  }

  function prefersReducedMotion() {
    try {
      return window.matchMedia?.("(prefers-reduced-motion: reduce)").matches === true;
    } catch (_) {
      return false;
    }
  }

  function resolve(next) {
    return next === DARK || (next === SYSTEM && systemPrefersDark())
      ? DARK
      : LIGHT;
  }

  function parseColor(input) {
    const value = String(input || "").trim().toLowerCase();
    if (!value || value === "transparent") return null;
    const named = { white: "#ffffff", black: "#000000" };
    const normalized = named[value] || value;

    if (normalized.startsWith("#")) {
      let hex = normalized.slice(1);
      if (!/^[0-9a-f]{3,8}$/.test(hex)) return null;
      if (hex.length === 3 || hex.length === 4) {
        hex = hex.split("").map((char) => char + char).join("");
      }
      return {
        r: parseInt(hex.slice(0, 2), 16),
        g: parseInt(hex.slice(2, 4), 16),
        b: parseInt(hex.slice(4, 6), 16),
        a: hex.length === 8 ? parseInt(hex.slice(6, 8), 16) / 255 : 1,
      };
    }

    const rgb = normalized.match(
      /^rgba?\(\s*(\d{1,3})\s*[, ]\s*(\d{1,3})\s*[, ]\s*(\d{1,3})(?:\s*[,/]\s*([\d.]+%?))?/,
    );
    if (!rgb) return null;
    const alphaRaw = rgb[4] || "1";
    const alpha = alphaRaw.endsWith("%")
      ? parseFloat(alphaRaw) / 100
      : parseFloat(alphaRaw);
    return {
      r: parseInt(rgb[1], 10),
      g: parseInt(rgb[2], 10),
      b: parseInt(rgb[3], 10),
      a: Number.isFinite(alpha) ? alpha : 1,
    };
  }

  function isLight(color) {
    if (!color || color.a < 0.55) return false;
    return (0.2126 * color.r + 0.7152 * color.g + 0.0722 * color.b) / 255 > 0.82;
  }

  function isDarkColor(color) {
    if (!color || color.a < 0.55) return false;
    return (0.2126 * color.r + 0.7152 * color.g + 0.0722 * color.b) / 255 < 0.3;
  }

  function allLightColors(value) {
    const tokens = String(value || "").match(
      /#[0-9a-f]{3,8}\b|rgba?\([^)]*\)|\b(?:white|black)\b/gi,
    );
    if (!tokens || !tokens.length) return false;
    return tokens.every((token) => isLight(parseColor(token)));
  }

  function restoreInline(element) {
    const stored = originalInlineStyles.get(element);
    if (!stored) return;
    Object.keys(stored).forEach((property) => {
      if (stored[property]) {
        element.style.setProperty(property, stored[property], "");
      } else {
        element.style.removeProperty(property);
      }
    });
    originalInlineStyles.delete(element);
  }

  function normalizeInline(element) {
    if (!element.style) return;
    if (element.closest?.(KEEP_LIGHT)) {
      restoreInline(element);
      return;
    }
    if (resolved !== DARK) {
      restoreInline(element);
      return;
    }

    const stored = originalInlineStyles.get(element) || {};

    [
      { props: ["background", "background-color"], token: "--kw-theme-surface", test: allLightColors },
      { props: ["color"], token: "--kw-theme-text", test: (value) => isDarkColor(parseColor(value)) },
      { props: ["border-color", "border"], token: "--kw-theme-border", test: allLightColors },
    ].forEach((group) => {
      group.props.forEach((property) => {
        const current = element.style.getPropertyValue(property);
        if (!current) return;
        if (!Object.prototype.hasOwnProperty.call(stored, property)) {
          stored[property] = current;
        }
        if (group.test(current)) {
          element.style.setProperty(property, `var(${group.token})`, "important");
        }
      });
    });

    if (Object.keys(stored).length) {
      originalInlineStyles.set(element, stored);
    }
  }

  function scheduleInlineSync() {
    const schedule = window.requestAnimationFrame || ((callback) => window.setTimeout(callback, 0));
    const cancel = window.cancelAnimationFrame || ((handle) => window.clearTimeout(handle));
    if (inlineFrame) cancel(inlineFrame);
    inlineFrame = schedule(() => {
      inlineFrame = null;
      document.querySelectorAll?.("[style]").forEach(normalizeInline);
    });
  }

  // Page partials contain their own <style> blocks and route-specific CSS is
  // injected during soft navigation. Move the theme sheet after those rules so
  // the dark overrides win the normal cascade without editing every template.
  function ensureThemeStylesheetLast() {
    const body = document.body;
    const links = document.querySelectorAll?.(
      "link[rel='stylesheet'][href*='/css/theme.css']",
    );
    const link = links && links[links.length - 1];
    if (!body || !link) return;

    const children = Array.from(body.children || []);
    const lastStyleIndex = children.reduce(
      (last, node, index) =>
        node.tagName === "STYLE" ||
        (node.tagName === "LINK" && node.rel === "stylesheet")
          ? index
          : last,
      -1,
    );
    if (link.parentNode === body && children.indexOf(link) >= lastStyleIndex) return;

    body.appendChild(link);
  }

  function rootElement() {
    return document.documentElement;
  }

  function apply(next, options = {}) {
    const { persist = false, broadcast = false, animate = false } = options;
    const store = storage();

    preference = normalize(next);
    resolved = resolve(preference);
    userId = currentUserId();

    if (persist && store) {
      try {
        const key = scopedKey(userId);
        if (preference === SYSTEM) {
          store.removeItem(key);
          if (!userId) store.removeItem(LEGACY_KEY);
        } else {
          store.setItem(key, preference);
          if (!userId) {
            store.setItem(LEGACY_KEY, preference);
            store.setItem(OWNER_KEY, "anonymous");
          }
        }
      } catch (_) {
        /* Storage may be unavailable; the in-memory state still applies. */
      }
    }

    const root = rootElement();
    if (root?.dataset) {
      root.dataset.kwTheme = resolved;
      root.dataset.kwThemePreference = preference;
      root.dataset.bsTheme = resolved;
    }

    const meta = document.querySelector?.('meta[name="color-scheme"]');
    if (meta) {
      meta.content = preference === SYSTEM ? "light dark" : resolved;
    }

    const themeColor = document.querySelector?.('meta[name="theme-color"]');
    if (themeColor) {
      themeColor.content = resolved === DARK ? "#073523" : "#178a50";
    }

    const body = document.body;
    if (body?.classList) {
      body.classList.toggle("app-dark", resolved === DARK);
      body.classList.toggle("app-light", resolved === LIGHT);
    }

    const button = document.getElementById?.("header-theme-button");
    if (button && button !== boundButton) bindToggle();

    if (button) {
      const isDark = resolved === DARK;
      const label = isDark ? "Switch to light theme" : "Switch to dark theme";
      button.setAttribute?.("aria-pressed", String(isDark));
      button.setAttribute?.("aria-label", label);
      button.title = label;

      const hiddenLabel = button.querySelector?.("[data-kw-theme-label]");
      if (hiddenLabel) hiddenLabel.textContent = label;
    }

    const pageVisible = !document.hidden && document.visibilityState !== "hidden";
    if (
      animate &&
      pageVisible &&
      !prefersReducedMotion() &&
      root?.classList
    ) {
      root.classList.add(SWITCH_CLASS);
      if (switchTimer) window.clearTimeout(switchTimer);
      switchTimer = window.setTimeout(() => {
        root.classList.remove(SWITCH_CLASS);
        switchTimer = null;
      }, 220);
    }

    if (document.body) {
      scheduleInlineSync();
      ensureThemeStylesheetLast();
    }

    if (broadcast && channel) {
      try {
        channel.postMessage({
          type: "preference",
          scope: scope(userId),
          preference,
          at: Date.now(),
        });
      } catch (_) {
        /* Cross-tab channel is an enhancement, not a requirement. */
      }
    }

    try {
      window.dispatchEvent(
        new CustomEvent("kingsway:theme-changed", {
          detail: { preference, resolved, scope: scope(userId) },
        }),
      );
    } catch (_) {
      /* CustomEvent is available in every supported browser. */
    }
  }

  function setPreference(next, options = {}) {
    apply(next, {
      persist: options.persist !== false,
      broadcast: options.broadcast !== false,
      animate: options.animate !== false,
    });
  }

  function hydrate(options = {}) {
    const next = readPreference();
    const root = rootElement();
    const domMatches =
      root?.dataset?.kwTheme === resolve(next) &&
      root?.dataset?.kwThemePreference === next;

    if (options.force || next !== preference || !domMatches) {
      apply(next, {
        persist: false,
        broadcast: false,
        animate: false,
      });
    }
  }

  function toggleFromHeader() {
    const target = resolved === DARK ? LIGHT : DARK;
    const system = systemPrefersDark() ? DARK : LIGHT;

    // A two-state header control still has three stored states. Selecting the
    // system's current value removes the override so users can return to
    // automatic light/dark switching without a dedicated third button.
    const next = target === system ? SYSTEM : target;
    setPreference(next, { persist: true, broadcast: true, animate: true });
  }

  function bindToggle() {
    const button = document.getElementById?.("header-theme-button");
    if (!button || (bound && button === boundButton)) return;

    bound = true;
    boundButton = button;
    button.addEventListener?.("click", toggleFromHeader);

    const hiddenLabel = button.querySelector?.("[data-kw-theme-label]");
    if (hiddenLabel) {
      hiddenLabel.textContent =
        resolved === DARK ? "Switch to light theme" : "Switch to dark theme";
    }
    button.setAttribute?.("aria-pressed", String(resolved === DARK));
  }

  function bindBroadcast() {
    if (channel || typeof BroadcastChannel === "undefined") return;

    try {
      channel = new BroadcastChannel(CHANNEL_NAME);
      channel.addEventListener?.("message", (event) => {
        const data = event?.data || {};
        if (
          data.type === "preference" &&
          data.scope === scope(currentUserId()) &&
          normalize(data.preference) !== preference
        ) {
          apply(data.preference, {
            persist: false,
            broadcast: false,
            animate: true,
          });
        }
      });
    } catch (_) {
      channel = null;
    }
  }

  function onStorage(event) {
    if (!event) return;

    if (event.key === ACTIVE_USER_KEY) {
      hydrate({ force: true });
      return;
    }

    const store = storage();
    let activeScope = "anonymous";
    try {
      activeScope = String(store?.getItem(ACTIVE_USER_KEY) || "");
    } catch (_) {}
    const activeKey = activeScope ? `kw_${activeScope}_theme` : LEGACY_KEY;

    if (
      event.key === activeKey ||
      event.key === scopedKey(currentUserId()) ||
      event.key === LEGACY_KEY ||
      event.key === OWNER_KEY
    ) {
      const next = readPreference();
      if (next !== preference) {
        apply(next, { persist: false, broadcast: false, animate: true });
      }
    }
  }

  function onSystemPreferenceChange() {
    if (preference === SYSTEM) {
      apply(SYSTEM, { persist: false, broadcast: false, animate: true });
    }
  }

  function observeInlineStyles() {
    if (inlineObserver || typeof MutationObserver === "undefined") return;
    inlineObserver = new MutationObserver((records) => {
      let needsSync = false;
      let needsButtonRebind = false;
      for (const record of records) {
        for (const node of record.addedNodes || []) {
          if (node.nodeType !== 1) continue;
          const isStyle =
            node.tagName === "STYLE" ||
            (node.tagName === "LINK" && node.rel === "stylesheet") ||
            node.hasAttribute?.("style") ||
            node.querySelector?.("style, link[rel='stylesheet'], [style]");
          const isButton =
            (node.tagName === "BUTTON" && node.id === "header-theme-button") ||
            node.querySelector?.("#header-theme-button");
          if (isStyle) needsSync = true;
          if (isButton) needsButtonRebind = true;
          if (needsSync && needsButtonRebind) break;
        }
        if (needsSync && needsButtonRebind) break;
      }
      if (!needsSync && !needsButtonRebind) return;
      if (needsButtonRebind) {
        bindToggle();
        hydrate({ force: true });
      }
      ensureThemeStylesheetLast();
      if (resolved === DARK) scheduleInlineSync();
    });
    inlineObserver.observe(document.documentElement, {
      childList: true,
      subtree: true,
    });
  }

  function initialize() {
    hydrate({ force: true });
    bindToggle();
    bindBroadcast();
    observeInlineStyles();

    window.addEventListener?.("storage", onStorage);
    window.addEventListener?.("focus", () => hydrate());
    document.addEventListener?.(
      "visibilitychange",
      () => {
        if (!document.hidden) hydrate();
      },
    );
    window.addEventListener?.("kingsway:page-loaded", () => {
      hydrate();
      ensureThemeStylesheetLast();
      if (resolved === DARK) scheduleInlineSync();
    });

    try {
      const query = window.matchMedia?.("(prefers-color-scheme: dark)");
      if (query?.addEventListener) {
        query.addEventListener("change", onSystemPreferenceChange);
      } else if (query?.addListener) {
        query.addListener(onSystemPreferenceChange);
      }
    } catch (_) {
      /* System preference listening is progressive enhancement. */
    }
  }

  window.KingswayTheme = {
    initialize,
    hydrate,
    setPreference,
    toggle: toggleFromHeader,
    getPreference: () => preference,
    getResolved: () => resolved,
    storageKey: () => scopedKey(currentUserId()),
  };

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", initialize, { once: true });
  } else {
    initialize();
  }
})();
