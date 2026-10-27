/**
 * Kingsway governed-answer Markdown renderer.
 *
 * One implementation shared by every assistant surface (staff chat, public FAQ,
 * parent portal) so bold text, bullets and headings look identical everywhere
 * instead of leaking literal ** and _ markers into the UI.
 *
 * Security model: the input is HTML-escaped FIRST and only then transformed, so
 * the output can only ever contain the tags this module emits itself. No author
 * supplied tag, attribute, URL or script survives.
 *
 * Supported: bold, italic, bullet lists (dash, asterisk, bullet glyph),
 * numbered lists, headings, inline code, fenced code, and line breaks.
 *
 * Usage: window.KingswayMarkdown.render(text) -> HTML string.
 */
(function (root) {
  "use strict";

  const ALLOWED_TAGS = new Set([
    "p", "strong", "em", "ul", "ol", "li", "code", "pre", "h6", "br",
  ]);
  const ALLOWED_ATTR = /^(class|type)=("[^"]*"|[a-z0-9-]+)/i;

  function escapeHtml(value) {
    const node = document.createElement("div");
    node.textContent = String(value ?? "");
    return node.innerHTML;
  }

  /**
   * A whole line wrapped in underscores whose content itself holds underscores
   * (report codes such as ENR_CURRENT_BY_CLASS) is still valid emphasis; the
   * intraword guard below deliberately leaves it alone.
   */
  function lineEmphasis(line) {
    const whole = line.match(/^_([^_](?:[\s\S]*[^_])?)_$/);
    return whole ? `<em>${whole[1]}</em>` : line;
  }

  function render(raw) {
    const text = String(raw ?? "");
    if (!text.trim()) return "";

    // Escape first: everything below operates on inert text.
    const lines = escapeHtml(text).split("\n");
    const out = [];
    let listType = null; // "ul" | "ol"
    let para = [];
    let inFence = false;
    let fenceLines = [];

    const inline = (value) => {
      let html = value;
      // Inline code first, so its content is not further transformed.
      const codes = [];
      html = html.replace(/`([^`]+)`/g, (_match, code) => {
        codes.push(code);
        return `\u0000CODE${codes.length - 1}\u0000`;
      });
      html = html
        .replace(/\*\*([^*]+)\*\*/g, "<strong>$1</strong>")
        // Emphasis must be delimited by whitespace/line edges and never be
        // intraword, or identifiers like ENR_CURRENT_BY_CLASS get mangled.
        .replace(/(^|[\s([{>])\*([^*\n]+)\*(?=[\s}\].:,;!?)]|$)/g, "$1<em>$2</em>")
        .replace(/(^|[\s([{>])_([^_\n]+)_(?=[\s}\].:,;!?)]|$)/g, "$1<em>$2</em>");
      return html.replace(
        /\u0000CODE(\d+)\u0000/g,
        (_m, index) => `<code>${codes[Number(index)]}</code>`
      );
    };

    const flushParagraph = () => {
      if (!para.length) return;
      const body = para.map(lineEmphasis).map(inline).join("<br>");
      out.push(`<p class="mb-2">${body}</p>`);
      para = [];
    };

    const flushList = () => {
      if (!listType) return;
      out.push(listType === "ul" ? "</ul>" : "</ol>");
      listType = null;
    };

    const closeBlock = () => {
      flushParagraph();
      flushList();
    };

    for (const rawLine of lines) {
      const trimmed = rawLine.trim();

      // Fenced code block: content passes through verbatim (already escaped).
      if (/^```/.test(trimmed)) {
        closeBlock();
        if (inFence) {
          out.push(
            `<pre class="assistant-code-block">${fenceLines.join("\n")}</pre>`
          );
          fenceLines = [];
          inFence = false;
        } else {
          inFence = true;
        }
        continue;
      }
      if (inFence) {
        fenceLines.push(rawLine);
        continue;
      }

      if (!trimmed) {
        closeBlock();
        continue;
      }

      const heading = trimmed.match(/^(#{1,6})\s+(.*)$/);
      if (heading) {
        closeBlock();
        out.push(
          `<h6 class="mt-2 mb-1 fw-semibold">${inline(heading[2])}</h6>`
        );
        continue;
      }

      const bullet =
        trimmed.match(/^[-\u2022]\s+(.*)$/) || trimmed.match(/^\*\s+(.*)$/);
      const numbered = trimmed.match(/^\d+[.)]\s+(.*)$/);
      if (bullet || numbered) {
        flushParagraph();
        const nextType = bullet ? "ul" : "ol";
        if (listType && listType !== nextType) flushList();
        if (!listType) {
          listType = nextType;
          out.push(
            nextType === "ul"
              ? '<ul class="mb-2 ps-3">'
              : '<ol class="mb-2 ps-3">'
          );
        }
        out.push(`<li>${inline((bullet || numbered)[1])}</li>`);
        continue;
      }

      flushList();
      para.push(rawLine.replace(/\s+$/, ""));
    }

    if (inFence && fenceLines.length) {
      out.push(
        `<pre class="assistant-code-block">${fenceLines.join("\n")}</pre>`
      );
    }
    closeBlock();
    return out.join("");
  }

  /**
   * Assert that rendered output only contains tags and attributes this module
   * emits. Exposed so automated checks (and the UI smoke tests) can verify the
   * XSS invariant rather than trusting it.
   */
  function audit(output) {
    const problems = [];
    for (const match of String(output).matchAll(/<\/?([a-zA-Z][a-zA-Z0-9]*)((?:\s+[^<>]*)?)>/g)) {
      const name = match[1].toLowerCase();
      if (!ALLOWED_TAGS.has(name)) problems.push(`tag<${name}>`);
      const attrs = (match[2] || "").trim();
      if (attrs) {
        for (const attr of attrs.split(/\s+(?=[a-zA-Z-]+=)/)) {
          if (!ALLOWED_ATTR.test(attr)) problems.push(`attr(${attr})`);
        }
      }
    }
    return problems;
  }

  root.KingswayMarkdown = {
    render,
    audit,
    allowedTags: Array.from(ALLOWED_TAGS),
    escapeHtml,
  };
})(typeof window !== "undefined" ? window : globalThis);
