/**
 * Keeps Chart.js bitmaps in step with the authenticated light/dark theme.
 *
 * Chart.js paints to a canvas, so CSS custom properties are not resolved for
 * ticks, grids, legends or dataset fills. A small Chart.js plugin records the
 * author's original colours when a chart is created, then reapplies themed
 * colours on every `kingsway:theme-changed` event. Switching back to light
 * restores the recorded colours exactly.
 */
(() => {
  "use strict";

  if (window.KingswayChartTheme) return;

  const EVENT = "kingsway:theme-changed";
  const PAGE_EVENT = "kingsway:page-loaded";
  const charts = new Set();
  const snapshots = new WeakMap();

  const FALLBACK = {
    light: {
      palette: ["#176b4d", "#2d8f67", "#62b38f", "#e0a52b", "#d66b3d", "#8b5fbf", "#3677b5", "#b94356", "#e9ecef"],
      text: "#172033",
      muted: "#697386",
      border: "#e4e8ed",
      surface: "#ffffff",
    },
    dark: {
      palette: ["#63d6a4", "#43b98a", "#8ee5bd", "#f2cf78", "#f39a72", "#c4afff", "#7db8ef", "#ff9daa", "#41546b"],
      text: "#e9eef6",
      muted: "#a6b3c4",
      border: "#2b3949",
      surface: "#131d2a",
    },
  };

  // Literal colours used by the existing page controllers, mapped onto the
  // Kingsway chart sequence. Only recognised literals are recoloured; arbitrary
  // author colours are left alone in light mode.
  const LIGHT_INDEX = new Map([
    ["#176b4d", 0], ["#2d8f67", 1], ["#62b38f", 2], ["#e0a52b", 3],
    ["#d66b3d", 4], ["#8b5fbf", 5], ["#3677b5", 6], ["#b94356", 7],
    ["#e9ecef", 8],
    ["#0f6b3d", 0], ["#198754", 0], ["#28a745", 0], ["#26845d", 0], ["#2f7d5b", 0],
    ["#20c997", 1], ["#17a2b8", 1], ["#0e7490", 1],
    ["#6f42c1", 5], ["#6610f2", 5],
    ["#0d6efd", 6], ["#4e79a7", 6],
    ["#ffc107", 3], ["#e8b44c", 3], ["#f2cf78", 3],
    ["#dc3545", 7], ["#c64b4b", 7], ["#b53747", 7],
    ["#fd7e14", 4], ["#f39a72", 4],
    ["#6c757d", 8], ["#6b7280", 8], ["#d9e2dd", 8], ["#cbd5e1", 8],
  ]);

  const DATASET_COLORS = [
    "backgroundColor", "borderColor", "pointBackgroundColor",
    "pointBorderColor", "pointHoverBackgroundColor", "pointHoverBorderColor",
    "hoverBackgroundColor", "hoverBorderColor",
  ];

  function cssVar(name, fallback) {
    try {
      const value = getComputedStyle(document.documentElement).getPropertyValue(name).trim();
      return value || fallback;
    } catch (_) {
      return fallback;
    }
  }

  function themeMode() {
    return document.documentElement?.dataset?.kwTheme === "dark" ? "dark" : "light";
  }

  function themeColors(mode) {
    const base = FALLBACK[mode];
    return {
      palette: base.palette.map((color, index) =>
        cssVar(`--kw-chart-${index + 1}`, color)),
      text: cssVar("--kw-theme-text", base.text),
      muted: cssVar("--kw-theme-muted", base.muted),
      border: cssVar("--kw-theme-border", base.border),
      surface: cssVar("--kw-theme-surface", base.surface),
    };
  }

  function parseColor(value) {
    const input = String(value || "").trim().toLowerCase();
    if (!input) return null;
    if (input.startsWith("#")) {
      let hex = input.slice(1);
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
    const match = input.match(
      /^rgba?\(\s*(\d{1,3})\s*[, ]\s*(\d{1,3})\s*[, ]\s*(\d{1,3})(?:\s*[,/]\s*([\d.]+%?))?/,
    );
    if (!match) return null;
    const raw = match[4] || "1";
    const alpha = raw.endsWith("%") ? parseFloat(raw) / 100 : parseFloat(raw);
    return {
      r: parseInt(match[1], 10),
      g: parseInt(match[2], 10),
      b: parseInt(match[3], 10),
      a: Number.isFinite(alpha) ? alpha : 1,
    };
  }

  function hexKey(color) {
    return "#" + [color.r, color.g, color.b]
      .map((part) => part.toString(16).padStart(2, "0"))
      .join("");
  }

  function withAlpha(color, alpha) {
    const parsed = parseColor(color);
    if (!parsed) return color;
    const a = Math.max(0, Math.min(1, alpha));
    if (a >= 0.999) {
      return hexKey(parsed);
    }
    return `rgba(${parsed.r}, ${parsed.g}, ${parsed.b}, ${Number(a.toFixed(3))})`;
  }

  function luminance(color) {
    return (0.2126 * color.r + 0.7152 * color.g + 0.0722 * color.b) / 255;
  }

  function mapColor(value, mode, colors) {
    if (Array.isArray(value)) return value.map((item) => mapColor(item, mode, colors));
    if (typeof value !== "string" || value.startsWith("var(")) return value;

    const parsed = parseColor(value);
    if (!parsed) return value;

    const index = LIGHT_INDEX.get(hexKey(parsed));
    if (index !== undefined) {
      return withAlpha(colors.palette[index % colors.palette.length], parsed.a);
    }

    if (mode === "dark") {
      const lightness = luminance(parsed);
      if (lightness > 0.86) return withAlpha(colors.palette[8], parsed.a);
      if (lightness < 0.18) return withAlpha(colors.text, parsed.a);
    }
    return value;
  }

  function snap(target, key) {
    if (!target || typeof target !== "object") return null;
    // Chart.js exposes option/scales objects through a proxy whose
    // getOwnPropertyDescriptor trap throws for absent properties. Read the
    // value directly instead of asking hasOwnProperty about proxy descriptors.
    let value;
    try {
      value = target[key];
    } catch (_) {
      return null;
    }
    return { had: value !== undefined, value };
  }

  function restore(target, key, saved) {
    if (!target || !saved) return;
    try {
      if (saved.had) target[key] = saved.value;
      else delete target[key];
    } catch (_) {
      // A disposer may freeze a Chart.js proxy mid-update; leave it intact.
    }
  }

  function capture(chart) {
    if (snapshots.has(chart)) return snapshots.get(chart);

    const saved = { datasets: [], scales: {}, plugins: {} };
    chart.data?.datasets?.forEach((dataset) => {
      const colors = {};
      DATASET_COLORS.forEach((key) => {
        colors[key] = snap(dataset, key);
      });
      saved.datasets.push(colors);
    });

    Object.entries(chart.options?.scales || {}).forEach(([id, scale]) => {
      saved.scales[id] = {
        gridColor: snap(scale.grid, "color"),
        borderColor: snap(scale.border, "color"),
        tickColor: snap(scale.ticks, "color"),
        titleColor: snap(scale.title, "color"),
      };
    });

    const plugins = chart.options?.plugins || {};
    saved.plugins.color = snap(chart.options, "color");
    saved.plugins.borderColor = snap(chart.options, "borderColor");
    saved.plugins.legendColor = snap(plugins.legend?.labels, "color");
    saved.plugins.titleColor = snap(plugins.title, "color");
    saved.plugins.tooltipTitle = snap(plugins.tooltip, "titleColor");
    saved.plugins.tooltipBody = snap(plugins.tooltip, "bodyColor");
    saved.plugins.tooltipBackground = snap(plugins.tooltip, "backgroundColor");

    snapshots.set(chart, saved);
    return saved;
  }

  function applyChart(chart, mode, colors) {
    const saved = capture(chart);
    const dark = mode === "dark";

    chart.data?.datasets?.forEach((dataset, index) => {
      const original = saved.datasets[index] || {};
      DATASET_COLORS.forEach((key) => {
        restore(dataset, key, original[key]);
        if (dark && original[key]?.value !== undefined) {
          dataset[key] = mapColor(original[key].value, mode, colors);
        }
      });
    });

    Object.entries(chart.options?.scales || {}).forEach(([id, scale]) => {
      const original = saved.scales[id] || {};
      const restoreColor = (section, savedColor) => {
        if (!scale[section]) return;
        if (savedColor?.had) scale[section].color = savedColor.value;
        else delete scale[section].color;
      };
      const themedColor = (section, value) => {
        if (!dark) return;
        (scale[section] || (scale[section] = {})).color = value;
      };

      [
        ["grid", "gridColor", colors.border],
        ["border", "borderColor", colors.border],
        ["ticks", "tickColor", colors.muted],
        ["title", "titleColor", colors.muted],
      ].forEach(([section, key, themed]) => {
        restoreColor(section, original[key]);
        themedColor(section, themed);
      });
    });

    restore(chart.options, "color", saved.plugins.color);
    restore(chart.options, "borderColor", saved.plugins.borderColor);
    if (dark) {
      chart.options.color = colors.text;
      chart.options.borderColor = colors.border;
    }

    const plugins = chart.options?.plugins || {};
    const restorations = [
      [plugins.legend?.labels, "color", saved.plugins.legendColor, colors.text],
      [plugins.title, "color", saved.plugins.titleColor, colors.text],
      [plugins.tooltip, "titleColor", saved.plugins.tooltipTitle, colors.text],
      [plugins.tooltip, "bodyColor", saved.plugins.tooltipBody, colors.text],
      [plugins.tooltip, "backgroundColor", saved.plugins.tooltipBackground, colors.surface],
    ];
    restorations.forEach(([target, key, original, themed]) => {
      restore(target, key, original);
      if (dark && target) target[key] = themed;
    });

    chart.update("none");
  }

  function refreshAll() {
    const mode = themeMode();
    const colors = themeColors(mode);
    charts.forEach((chart) => {
      try {
        applyChart(chart, mode, colors);
      } catch (_) {
        // A chart mid-destroy must not break the rest of the page.
      }
    });
  }

  const plugin = {
    id: "kingswayThemeColors",
    afterInit(chart) {
      charts.add(chart);
      try {
        capture(chart);
        applyChart(chart, themeMode(), themeColors(themeMode()));
      } catch (_) {
        // A chart initialising during a destroy/soft-navigation is retried by
        // the next refresh; it must never reject the caller's render promise.
      }
    },
    beforeDestroy(chart) {
      charts.delete(chart);
    },
  };

  let registered = false;
  function register() {
    if (registered || typeof window.Chart === "undefined") return registered;
    try {
      window.Chart.register(plugin);
      registered = true;
      window.KingswayChartTheme.refresh();
    } catch (_) {
      registered = false;
    }
    return registered;
  }

  window.KingswayChartTheme = { refresh: refreshAll, register };
  register();

  const scheduleRefresh = () => {
    if (typeof window.requestAnimationFrame === "function") {
      window.requestAnimationFrame(refreshAll);
    } else {
      refreshAll();
    }
  };
  window.addEventListener(EVENT, scheduleRefresh);
  window.addEventListener(PAGE_EVENT, scheduleRefresh);
  window.addEventListener("load", register, { once: true });
  document.addEventListener("DOMContentLoaded", register, { once: true });
})();
