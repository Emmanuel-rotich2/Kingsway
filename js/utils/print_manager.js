/**
 * PrintManager
 * Template-Driven Printing Utility for Kingsway Preparatory School
 *
 * Canonical server templates:
 *
 * Reports
 * - templates/print/server/report_header.php
 * - templates/print/server/report_footer.php
 * - public/css/print-reports.css
 *
 * Certificates
 * - templates/certificates/academic_excellence.php
 * - templates/certificates/sports_achievement.php
 * - templates/certificates/graduation.php
 *
 * Student ID cards
 * - templates/id-cards/student_id_front.php
 * - templates/id-cards/student_id_back.php
 * - templates/id-cards/student_id_both_single_row.php
 * - templates/id-cards/student_id_both_two_pages.php
 * - public/css/student-id-card.css
 *
 * The browser utility does not rebuild those designs. It sends normalized
 * data to the backend, where PrintService renders the canonical templates.
 *
 * @version 3.0.0
 */

const PrintManager = (() => {
  "use strict";

  const STORAGE_KEYS = Object.freeze({
    idPrinterMode: "kingsway_student_id_printer_mode",
    idSide: "kingsway_student_id_print_side",
    idChunkSize: "kingsway_student_id_chunk_size",
  });

  const VALID_ID_PRINTER_MODES = new Set(["a4_pdf", "direct_card"]);
  const VALID_ID_SIDES = new Set(["front", "back", "both"]);
  const VALID_CERTIFICATE_TYPES = new Set([
    "academic_excellence",
    "sports_achievement",
    "graduation",
  ]);

  const SCHOOL_COLORS = Object.freeze({
    primary: "#0f5b3b",
    primaryDark: "#083f2b",
    primaryLight: "#19734d",
    gold: "#d3ad24",
    goldDark: "#a88612",
    cream: "#fff8df",
    creamSoft: "#fffdf4",
    text: "#1b2a23",
    muted: "#5e6e65",
    border: "#c7d3cc",
    white: "#ffffff",
  });

  const defaults = {
    appBase: window.APP_BASE || "",
    apiBase: window.API_BASE_URL || window.APP_BASE || "",

    schoolName:
      window.SCHOOL_CONFIG?.name || "KINGSWAY PREPARATORY SCHOOL",
    schoolMotto:
      window.SCHOOL_CONFIG?.motto || "In God We Soar",
    schoolAddress:
      window.SCHOOL_CONFIG?.address ||
      "P.O. Box 203-20203, Londiani, Kericho County, Kenya",
    schoolPhone:
      window.SCHOOL_CONFIG?.phone || "0720 113 030 / 0720 113 031",
    schoolEmail:
      window.SCHOOL_CONFIG?.email ||
      "info@kingswaypreparatoryschool.sc.ke",
    schoolWebsite:
      window.SCHOOL_CONFIG?.website ||
      "www.kingswaypreparatoryschool.sc.ke",
    schoolLogo:
      window.SCHOOL_CONFIG?.logo ||
      `${window.APP_BASE || ""}/uploads/school_assets/official_school_logo.png`,

    endpoints: {
      tableReport: "/api/print/table",
      recordReport: "/api/print/record",
      certificate: "/api/print/certificate",
      studentIdCards: "/api/students/id-cards/print",
      receipt: "/api/print/receipt",
      fileDownload: "/api/print/download",
    },

    requestHeaders: {},
    credentials: "same-origin",

    defaultPaperSize: "A4",
    defaultOrientation: "portrait",
    idChunkSize: 100,
    maxIdChunkSize: 200,

    colors: SCHOOL_COLORS,
  };

  /* ==========================================================================
     General utilities
     ========================================================================== */

  function escapeHtml(value) {
    const div = document.createElement("div");
    div.textContent = value === null || value === undefined ? "" : String(value);
    return div.innerHTML;
  }

  function getValue(value, fallback = "") {
    return value === null || value === undefined || value === ""
      ? fallback
      : value;
  }

  function createReportCode(prefix = "KWPS") {
    const date = new Date();

    const timestamp = [
      date.getFullYear(),
      String(date.getMonth() + 1).padStart(2, "0"),
      String(date.getDate()).padStart(2, "0"),
      String(date.getHours()).padStart(2, "0"),
      String(date.getMinutes()).padStart(2, "0"),
      String(date.getSeconds()).padStart(2, "0"),
    ].join("");

    return `${prefix}-${timestamp}`;
  }

  function safeFilename(value, fallback = "document") {
    const filename = String(value || fallback)
      .trim()
      .replace(/[^A-Za-z0-9._-]+/g, "_")
      .replace(/^[._-]+|[._-]+$/g, "");

    return filename || fallback;
  }

  function normalizeConfig(options = {}) {
    return {
      ...defaults,
      ...options,
      endpoints: {
        ...defaults.endpoints,
        ...(options.endpoints || {}),
      },
      requestHeaders: {
        ...defaults.requestHeaders,
        ...(options.requestHeaders || {}),
      },
      colors: {
        ...defaults.colors,
        ...(options.colors || {}),
      },
    };
  }

  function buildUrl(path, config = defaults) {
    if (/^https?:\/\//i.test(path)) {
      return path;
    }

    const base = String(config.apiBase || config.appBase || "").replace(/\/+$/, "");
    const normalizedPath = String(path || "").startsWith("/")
      ? String(path)
      : `/${String(path || "")}`;

    return `${base}${normalizedPath}`;
  }

  function notify(type, message) {
    if (typeof window.showNotification === "function") {
      window.showNotification(type, message);
      return;
    }

    if (window.API && typeof window.API.showNotification === "function") {
      window.API.showNotification(message, type);
      return;
    }

    if (type === "error") {
      console.error(message);
      window.alert(message);
      return;
    }

    console.log(`[${String(type).toUpperCase()}] ${message}`);
  }

  function getCurrentUser() {
    if (
      window.AuthContext &&
      typeof window.AuthContext.getUser === "function"
    ) {
      return window.AuthContext.getUser() || {};
    }

    return {
      username: "System",
      full_name: "System User",
    };
  }

  function normalizeApiError(response, payload) {
    const message =
      payload?.message ||
      payload?.error ||
      response?.statusText ||
      "The print request failed.";

    const error = new Error(message);
    error.status = response?.status;
    error.payload = payload;

    return error;
  }

  async function parseResponse(response) {
    const contentType = response.headers.get("content-type") || "";

    if (contentType.includes("application/json")) {
      return response.json();
    }

    if (contentType.includes("application/pdf")) {
      return response.blob();
    }

    return response.text();
  }

  async function request(endpoint, payload, options = {}) {
    const config = normalizeConfig(options);

    if (
      window.API &&
      typeof window.API.callAPI === "function" &&
      options.preferApiHelper !== false
    ) {
      return window.API.callAPI(endpoint, "POST", payload);
    }

    if (
      window.API &&
      typeof window.API.apiCall === "function" &&
      options.preferApiHelper !== false
    ) {
      return window.API.apiCall(endpoint, "POST", payload);
    }

    const response = await fetch(buildUrl(endpoint, config), {
      method: "POST",
      credentials: config.credentials,
      headers: {
        Accept: "application/json, application/pdf",
        "Content-Type": "application/json",
        ...config.requestHeaders,
      },
      body: JSON.stringify(payload),
    });

    const result = await parseResponse(response);

    if (!response.ok) {
      throw normalizeApiError(response, result);
    }

    return result;
  }

  function normalizeServerPayload(response) {
    if (!response) {
      return {};
    }

    if (response.data && typeof response.data === "object") {
      return response.data;
    }

    return response;
  }

  function isBlob(value) {
    return typeof Blob !== "undefined" && value instanceof Blob;
  }

  function downloadBlob(blob, filename) {
    const url = URL.createObjectURL(blob);
    const link = document.createElement("a");

    link.href = url;
    link.download = safeFilename(filename, "document.pdf");

    document.body.appendChild(link);
    link.click();
    link.remove();

    window.setTimeout(() => URL.revokeObjectURL(url), 1000);
  }

  function openUrl(url, target = "_blank") {
    const popup = window.open(url, target);

    if (!popup) {
      notify(
        "error",
        "The browser blocked the print window. Allow popups and try again.",
      );
      return null;
    }

    return popup;
  }

  function resolveFileUrl(file, config = defaults) {
    if (!file) {
      return "";
    }

    if (typeof file === "string") {
      if (/^(https?:\/\/|blob:|data:)/i.test(file)) {
        return file;
      }

      if (file.startsWith("/")) {
        return buildUrl(file, config);
      }

      return buildUrl(
        `${config.endpoints.fileDownload}?file=${encodeURIComponent(file)}`,
        config,
      );
    }

    return (
      file.download_url ||
      file.url ||
      file.file_url ||
      file.path_url ||
      ""
    );
  }

  async function handleGeneratedFiles(response, options = {}) {
    const config = normalizeConfig(options);

    if (isBlob(response)) {
      const filename =
        options.filename || `document_${new Date().toISOString().slice(0, 10)}.pdf`;

      if (options.download === true) {
        downloadBlob(response, filename);
      } else {
        openUrl(URL.createObjectURL(response));
      }

      return {
        files: [filename],
        total_files: 1,
      };
    }

    const payload = normalizeServerPayload(response);

    if (payload.success === false) {
      throw new Error(payload.message || "The print request failed.");
    }

    const files = Array.isArray(payload.files)
      ? payload.files
      : payload.file
        ? [payload.file]
        : payload.download_url
          ? [payload.download_url]
          : payload.url
            ? [payload.url]
            : [];

    if (!files.length) {
      throw new Error(
        payload.message ||
          "The server completed the request but returned no printable file.",
      );
    }

    const fileUrls = files
      .map((file) => resolveFileUrl(file, config))
      .filter(Boolean);

    if (!fileUrls.length) {
      throw new Error("The returned print files did not contain usable URLs.");
    }

    if (fileUrls.length === 1) {
      if (options.download === true) {
        const link = document.createElement("a");
        link.href = fileUrls[0];
        link.download = "";
        document.body.appendChild(link);
        link.click();
        link.remove();
      } else {
        openUrl(fileUrls[0]);
      }
    } else {
      showGeneratedFilesDialog(fileUrls, payload, config);
    }

    return {
      ...payload,
      files: fileUrls,
      total_files: fileUrls.length,
    };
  }

  function showGeneratedFilesDialog(fileUrls, payload = {}, options = {}) {
    const existing = document.getElementById("printManagerFilesModal");

    if (existing) {
      existing.remove();
    }

    const wrapper = document.createElement("div");
    wrapper.id = "printManagerFilesModal";
    wrapper.style.cssText = [
      "position:fixed",
      "inset:0",
      "z-index:10850",
      "background:rgba(0,0,0,.55)",
      "display:flex",
      "align-items:center",
      "justify-content:center",
      "padding:20px",
    ].join(";");

    const estimatedPages = payload.estimated_pages
      ? `<p><strong>Estimated pages:</strong> ${escapeHtml(payload.estimated_pages)}</p>`
      : "";

    wrapper.innerHTML = `
      <div style="
        width:min(680px,100%);
        max-height:85vh;
        overflow:auto;
        background:#fff;
        border-radius:14px;
        padding:22px;
        box-shadow:0 20px 60px rgba(0,0,0,.28);
        font-family:Arial,sans-serif;
      ">
        <div style="display:flex;justify-content:space-between;gap:15px;">
          <div>
            <h3 style="margin:0;color:${options.colors.primaryDark};">
              Print files generated
            </h3>
            <p style="margin:8px 0 0;color:#5e6e65;">
              ${fileUrls.length} PDF files were created.
            </p>
          </div>

          <button type="button" data-close-print-files style="
            border:0;
            background:transparent;
            font-size:26px;
            cursor:pointer;
          ">&times;</button>
        </div>

        <div style="
          margin:16px 0;
          padding:12px;
          background:${options.colors.creamSoft};
          border-left:4px solid ${options.colors.gold};
        ">
          <p style="margin:0 0 5px;">
            <strong>Total cards:</strong>
            ${escapeHtml(payload.total_cards ?? "—")}
          </p>
          <p style="margin:0 0 5px;">
            <strong>Chunks:</strong>
            ${escapeHtml(payload.total_chunks ?? fileUrls.length)}
          </p>
          ${estimatedPages}
        </div>

        <div>
          ${fileUrls
            .map(
              (url, index) => `
                <a href="${escapeHtml(url)}" target="_blank" rel="noopener"
                  style="
                    display:flex;
                    justify-content:space-between;
                    align-items:center;
                    gap:12px;
                    margin:8px 0;
                    padding:12px 14px;
                    border:1px solid #c7d3cc;
                    border-radius:8px;
                    color:${options.colors.primaryDark};
                    text-decoration:none;
                  ">
                  <span>PDF ${String(index + 1).padStart(3, "0")}</span>
                  <strong>Open / Download</strong>
                </a>
              `,
            )
            .join("")}
        </div>
      </div>
    `;

    document.body.appendChild(wrapper);

    wrapper
      .querySelector("[data-close-print-files]")
      ?.addEventListener("click", () => wrapper.remove());

    wrapper.addEventListener("click", (event) => {
      if (event.target === wrapper) {
        wrapper.remove();
      }
    });
  }

  /* ==========================================================================
     Report payloads and server templates
     ========================================================================== */

  function normalizeReportCommon(options = {}) {
    const config = normalizeConfig(options);
    const user = getCurrentUser();

    return {
      title: config.title || "School Report",
      subtitle: config.subtitle || "",
      description: config.description || "Official school document",
      filters: config.filters || {},
      reportCode:
        config.reportCode ||
        createReportCode(config.reportCodePrefix || "KWPS"),
      generatedBy:
        config.generatedBy ||
        user.full_name ||
        user.fullName ||
        user.username ||
        "System User",
      generatedAt: config.generatedAt || new Date().toISOString(),
      printedAt: config.printedAt || new Date().toISOString(),
      confidentialityNote:
        config.confidentialityNote ||
        "This document is issued by Kingsway Preparatory School and is intended for authorized use only.",
      signatureSection: Array.isArray(config.signatureSection)
        ? config.signatureSection
        : [],
      showPageNumbers: config.showPageNumbers !== false,
      paperSize: config.paperSize || config.defaultPaperSize || "A4",
      orientation:
        config.orientation ||
        config.defaultOrientation ||
        defaults.defaultOrientation,
      filename:
        config.filename ||
        safeFilename(
          `${config.title || "report"}_${new Date().toISOString().slice(0, 10)}`,
          "report",
        ),
    };
  }

  function normalizeColumn(column, index = 0) {
    if (typeof column === "string") {
      return {
        key: column,
        label: column,
      };
    }

    return {
      key: column.key || "",
      label: column.label || column.key || "",
      type:
        column.type ||
        (index === 0 && column.key === "index" ? "index" : undefined),
      width: column.width || "",
      className: column.className || "",
      cellClassName: column.cellClassName || "",
      allowHtml: Boolean(column.allowHtml),
    };
  }

  function normalizeRowsForServer(rows, columns) {
    return rows.map((row, rowIndex) => {
      const normalized = {};

      columns.forEach((column) => {
        const sourceColumn = column.__source || column;
        let value;

        if (sourceColumn.type === "index") {
          value = rowIndex + 1;
        } else if (typeof sourceColumn.render === "function") {
          value = sourceColumn.render(row, rowIndex);
        } else {
          value = row[sourceColumn.key];
        }

        if (typeof sourceColumn.formatter === "function") {
          value = sourceColumn.formatter(value, row, rowIndex);
        }

        normalized[column.key || `column_${rowIndex}`] = getValue(value, "");
      });

      return normalized;
    });
  }

  async function printTable(options = {}) {
    const config = normalizeConfig(options);

    if (!Array.isArray(config.rows) || config.rows.length === 0) {
      notify("warning", "No records are available to print.");
      return null;
    }

    if (!Array.isArray(config.columns) || config.columns.length === 0) {
      notify("warning", "No report columns were provided.");
      return null;
    }

    const columns = config.columns.map((column, index) => ({
      ...normalizeColumn(column, index),
      __source: typeof column === "string" ? { key: column } : column,
    }));

    const payload = {
      ...normalizeReportCommon(config),
      columns: columns.map(({ __source, ...column }) => column),
      rows: normalizeRowsForServer(config.rows, columns),
      summary: config.summary || {},
      beforeContentHtml: config.beforeTableHtml || "",
      afterContentHtml: config.afterTableHtml || "",
    };

    try {
      const response = await request(
        config.endpoints.tableReport,
        payload,
        config,
      );

      return handleGeneratedFiles(response, config);
    } catch (error) {
      notify("error", error.message || "Unable to generate the report.");
      throw error;
    }
  }

  async function printRecord(options = {}) {
    const config = normalizeConfig(options);

    if (!Array.isArray(config.sections) || config.sections.length === 0) {
      notify("warning", "No record information is available to print.");
      return null;
    }

    const sections = config.sections.map((section) => ({
      title: section.title || "Details",
      content: section.content || "",
      allowHtml: Boolean(section.allowHtml),
      fields: Array.isArray(section.fields)
        ? section.fields.map((field) => ({
            label: field.label || "",
            value: getValue(field.value, ""),
            allowHtml: Boolean(field.allowHtml),
          }))
        : [],
    }));

    const payload = {
      ...normalizeReportCommon(config),
      sections,
      beforeContentHtml: config.beforeSectionsHtml || "",
      afterContentHtml: config.afterSectionsHtml || "",
    };

    try {
      const response = await request(
        config.endpoints.recordReport,
        payload,
        config,
      );

      return handleGeneratedFiles(response, config);
    } catch (error) {
      notify("error", error.message || "Unable to generate the record PDF.");
      throw error;
    }
  }

  function sanitizeContentHtml(html) {
    const container = document.createElement("div");
    container.innerHTML = html || "";

    container
      .querySelectorAll(
        [
          "script",
          "button",
          ".btn",
          ".modal-footer",
          ".modal-header .btn-close",
          ".no-print",
          "[data-no-print]",
          "input[type='button']",
          "input[type='submit']",
        ].join(","),
      )
      .forEach((element) => element.remove());

    container.querySelectorAll("input, textarea, select").forEach((field) => {
      const staticValue = document.createElement("span");

      if (field.tagName === "SELECT") {
        staticValue.textContent =
          field.options[field.selectedIndex]?.text || "";
      } else {
        staticValue.textContent = field.value || "";
      }

      field.replaceWith(staticValue);
    });

    return container.innerHTML;
  }

  async function printModal(modalId, options = {}) {
    const modal = document.getElementById(modalId);

    if (!modal) {
      notify("error", "The requested modal could not be found.");
      return null;
    }

    const body =
      modal.querySelector(".modal-body") ||
      modal.querySelector("[data-modal-body]");

    if (!body) {
      notify("error", "The modal does not contain printable content.");
      return null;
    }

    const title =
      modal.querySelector(".modal-title")?.textContent?.trim() || "Details";

    return printRecord({
      ...options,
      title: options.title || title,
      sections: [
        {
          title: options.sectionTitle || title,
          content: sanitizeContentHtml(body.innerHTML),
          allowHtml: true,
        },
      ],
    });
  }

  async function printElement(elementId, options = {}) {
    const element = document.getElementById(elementId);

    if (!element) {
      notify("error", "The requested printable element could not be found.");
      return null;
    }

    return printRecord({
      ...options,
      sections: [
        {
          title: options.sectionTitle || options.title || "Details",
          content: options.preserveInputs
            ? element.innerHTML
            : sanitizeContentHtml(element.innerHTML),
          allowHtml: true,
        },
      ],
    });
  }

  async function printReportHtml(content, options = {}) {
    return printRecord({
      ...options,
      sections: [
        {
          title: options.sectionTitle || options.title || "School Report",
          content,
          allowHtml: true,
        },
      ],
    });
  }

  /* ==========================================================================
     Certificate templates
     ========================================================================== */

  function normalizeCertificateData(options = {}) {
    const config = normalizeConfig(options);
    const type = String(config.type || "academic_excellence");

    if (!VALID_CERTIFICATE_TYPES.has(type)) {
      throw new Error(
        "Invalid certificate type. Use academic_excellence, sports_achievement or graduation.",
      );
    }

    return {
      type,
      data: {
        recipientName: String(config.recipientName || "").trim(),
        achievement: String(config.achievement || "").trim(),
        academicYear: String(config.academicYear || "").trim(),
        sport: String(config.sport || "").trim(),
        course: String(config.course || "").trim(),
        certificateNumber: String(config.certificateNumber || "").trim(),
        dateAwarded:
          config.dateAwarded || new Date().toISOString().slice(0, 10),
        principalName:
          config.principalName ||
          window.SCHOOL_CONFIG?.principal ||
          "Mr Bett Junior",
        principalTitle:
          config.principalTitle ||
          window.SCHOOL_CONFIG?.principalTitle ||
          window.SCHOOL_CONFIG?.principal_title ||
          "Headteacher",
        teacherName: config.teacherName || "Class Teacher",
        sportsCoordinatorName:
          config.sportsCoordinatorName || "Sports Coordinator",
        examOfficerName:
          config.examOfficerName || "Examinations Officer",
      },
      filename:
        config.filename ||
        safeFilename(
          `certificate_${type}_${config.certificateNumber || Date.now()}`,
          "certificate",
        ),
    };
  }

  async function printCertificate(options = {}) {
    const config = normalizeConfig(options);

    try {
      const certificate = normalizeCertificateData(config);

      const response = await request(
        config.endpoints.certificate,
        certificate,
        config,
      );

      return handleGeneratedFiles(response, {
        ...config,
        filename: `${certificate.filename}.pdf`,
      });
    } catch (error) {
      notify("error", error.message || "Unable to generate the certificate.");
      throw error;
    }
  }

  /* ==========================================================================
     Student ID templates
     ========================================================================== */

  function getStoredIdPrinterMode() {
    const stored = localStorage.getItem(STORAGE_KEYS.idPrinterMode);

    return VALID_ID_PRINTER_MODES.has(stored) ? stored : "a4_pdf";
  }

  function setStoredIdPrinterMode(mode) {
    if (!VALID_ID_PRINTER_MODES.has(mode)) {
      throw new Error("Invalid student ID printer mode.");
    }

    localStorage.setItem(STORAGE_KEYS.idPrinterMode, mode);
  }

  function getStoredIdSide() {
    const stored = localStorage.getItem(STORAGE_KEYS.idSide);

    return VALID_ID_SIDES.has(stored) ? stored : "both";
  }

  function setStoredIdSide(side) {
    if (!VALID_ID_SIDES.has(side)) {
      throw new Error("Invalid student ID print side.");
    }

    localStorage.setItem(STORAGE_KEYS.idSide, side);
  }

  function getStoredIdChunkSize() {
    const value = Number(localStorage.getItem(STORAGE_KEYS.idChunkSize));

    if (!Number.isInteger(value) || value < 1) {
      return defaults.idChunkSize;
    }

    return Math.min(value, defaults.maxIdChunkSize);
  }

  function setStoredIdChunkSize(chunkSize) {
    const value = Math.max(
      1,
      Math.min(defaults.maxIdChunkSize, Number(chunkSize) || defaults.idChunkSize),
    );

    localStorage.setItem(STORAGE_KEYS.idChunkSize, String(value));

    return value;
  }

  function bindIdPrintControls({
    printerModeSelect,
    sideSelect,
    chunkSizeInput,
  } = {}) {
    const printerElement =
      typeof printerModeSelect === "string"
        ? document.getElementById(printerModeSelect)
        : printerModeSelect;

    const sideElement =
      typeof sideSelect === "string"
        ? document.getElementById(sideSelect)
        : sideSelect;

    const chunkElement =
      typeof chunkSizeInput === "string"
        ? document.getElementById(chunkSizeInput)
        : chunkSizeInput;

    if (printerElement) {
      printerElement.value = getStoredIdPrinterMode();

      printerElement.addEventListener("change", () => {
        if (VALID_ID_PRINTER_MODES.has(printerElement.value)) {
          setStoredIdPrinterMode(printerElement.value);
        }
      });
    }

    if (sideElement) {
      sideElement.value = getStoredIdSide();

      sideElement.addEventListener("change", () => {
        if (VALID_ID_SIDES.has(sideElement.value)) {
          setStoredIdSide(sideElement.value);
        }
      });
    }

    if (chunkElement) {
      chunkElement.value = String(getStoredIdChunkSize());

      chunkElement.addEventListener("change", () => {
        chunkElement.value = String(
          setStoredIdChunkSize(chunkElement.value),
        );
      });
    }
  }

  function normalizeStudentIds(options = {}) {
    let ids = options.studentIds || options.student_ids || [];

    if (!Array.isArray(ids) && ids !== null && ids !== undefined) {
      ids = [ids];
    }

    ids = [...new Set(
      ids
        .map((id) => Number(id))
        .filter((id) => Number.isInteger(id) && id > 0),
    )];

    if (!ids.length && options.studentId) {
      const id = Number(options.studentId);

      if (Number.isInteger(id) && id > 0) {
        ids = [id];
      }
    }

    return ids;
  }

  function buildIdCardPrintPayload(options = {}) {
    const config = normalizeConfig(options);
    const studentIds = normalizeStudentIds(config);

    if (!studentIds.length) {
      throw new Error("Select at least one student before printing.");
    }

    const printerMode =
      config.printerMode ||
      config.printer_mode ||
      getStoredIdPrinterMode();

    const side = config.side || getStoredIdSide();

    if (!VALID_ID_PRINTER_MODES.has(printerMode)) {
      throw new Error(
        "Invalid printer mode. Use a4_pdf or direct_card.",
      );
    }

    if (!VALID_ID_SIDES.has(side)) {
      throw new Error(
        "Invalid card side. Use front, back or both.",
      );
    }

    const chunkSize = Math.max(
      1,
      Math.min(
        config.maxIdChunkSize || defaults.maxIdChunkSize,
        Number(
          config.chunkSize ||
            config.chunk_size ||
            getStoredIdChunkSize(),
        ) || defaults.idChunkSize,
      ),
    );

    setStoredIdPrinterMode(printerMode);
    setStoredIdSide(side);
    setStoredIdChunkSize(chunkSize);

    return {
      student_ids: studentIds,
      printer_mode: printerMode,
      side,
      chunk_size: studentIds.length === 1 ? 1 : chunkSize,
      batch_mode: studentIds.length === 1 ? "single" : "bulk",
      filename:
        config.filename ||
        safeFilename(
          `student_id_cards_${new Date().toISOString().slice(0, 10)}`,
          "student_id_cards",
        ),
    };
  }

  async function printIdCard(options = {}) {
    const config = normalizeConfig(options);

    try {
      const payload = buildIdCardPrintPayload(config);

      const response = await request(
        config.endpoints.studentIdCards,
        payload,
        config,
      );

      return handleGeneratedFiles(response, {
        ...config,
        filename: `${payload.filename}.pdf`,
      });
    } catch (error) {
      notify("error", error.message || "Unable to generate student ID cards.");
      throw error;
    }
  }

  async function printStudentIdCards(options = {}) {
    return printIdCard(options);
  }

  async function printSingleStudentIdCard(studentId, options = {}) {
    return printIdCard({
      ...options,
      studentIds: [studentId],
      chunkSize: 1,
    });
  }

  async function printBulkStudentIdCards(studentIds, options = {}) {
    return printIdCard({
      ...options,
      studentIds,
      chunkSize:
        options.chunkSize ||
        options.chunk_size ||
        getStoredIdChunkSize(),
    });
  }

  /* ==========================================================================
     Receipt
     ========================================================================== */

  async function printReceipt(options = {}) {
    const config = normalizeConfig(options);

    const payload = {
      schoolName: config.schoolName,
      schoolMotto: config.schoolMotto,
      schoolLogo: config.schoolLogo,
      schoolAddress: config.schoolAddress,
      schoolPhone: config.schoolPhone,
      schoolEmail: config.schoolEmail,
      receiptNumber: config.receiptNumber || "",
      date: config.date || new Date().toISOString(),
      customer: config.customer || "",
      items: Array.isArray(config.items) ? config.items : [],
      total: config.total || 0,
      receiptNote: config.receiptNote || "Thank you.",
      filename:
        config.filename ||
        safeFilename(
          `receipt_${config.receiptNumber || Date.now()}`,
          "receipt",
        ),
    };

    /*
     * Receipts use a backend template only when an endpoint exists.
     * This preserves compatibility with installations that have not yet
     * created a server receipt template.
     */
    if (config.endpoints.receipt) {
      try {
        const response = await request(
          config.endpoints.receipt,
          payload,
          config,
        );

        return handleGeneratedFiles(response, {
          ...config,
          filename: `${payload.filename}.pdf`,
        });
      } catch (error) {
        if (config.allowReceiptBrowserFallback === false) {
          notify("error", error.message || "Unable to generate the receipt.");
          throw error;
        }

        console.warn(
          "Server receipt generation failed. Using browser fallback.",
          error,
        );
      }
    }

    return printReceiptInBrowser(payload, config);
  }

  function printReceiptInBrowser(payload, options = {}) {
    const printWindow = window.open("", "_blank");

    if (!printWindow) {
      notify("error", "Allow popups before printing the receipt.");
      return null;
    }

    const items = payload.items
      .map(
        (item) => `
          <div style="display:flex;justify-content:space-between;gap:10px;margin:5px 0;">
            <span>${escapeHtml(item.name || item.description || "")}</span>
            <span>${escapeHtml(item.price ?? item.amount ?? "")}</span>
          </div>
        `,
      )
      .join("");

    printWindow.document.open();
    printWindow.document.write(`
      <!DOCTYPE html>
      <html lang="en">
      <head>
        <meta charset="UTF-8">
        <title>${escapeHtml(payload.filename)}</title>
        <style>
          @page { size: 80mm auto; margin: 4mm; }
          body {
            width:72mm;
            margin:0;
            font-family:"Courier New",monospace;
            font-size:11px;
          }
          .divider { border-bottom:1px dashed #000; margin:10px 0; }
        </style>
      </head>
      <body>
        <div style="text-align:center;">
          <img src="${escapeHtml(payload.schoolLogo)}"
            style="width:55px;height:55px;object-fit:contain;">
          <h3>${escapeHtml(payload.schoolName)}</h3>
          <div>${escapeHtml(payload.schoolAddress)}</div>
          <div>${escapeHtml(payload.schoolPhone)}</div>
        </div>

        <div class="divider"></div>

        <div><strong>Receipt:</strong> ${escapeHtml(payload.receiptNumber)}</div>
        <div><strong>Received from:</strong> ${escapeHtml(payload.customer)}</div>

        <div class="divider"></div>

        ${items}

        <div class="divider"></div>

        <div style="text-align:right;font-weight:bold;">
          TOTAL: ${escapeHtml(payload.total)}
        </div>

        <div class="divider"></div>

        <div style="text-align:center;">${escapeHtml(payload.receiptNote)}</div>
      </body>
      </html>
    `);
    printWindow.document.close();

    printWindow.addEventListener(
      "load",
      () => {
        window.setTimeout(() => {
          printWindow.focus();
          printWindow.print();
        }, 300);
      },
      { once: true },
    );

    return printWindow;
  }

  /* ==========================================================================
     CSV export
     ========================================================================== */

  function exportToCSV(options = {}) {
    const config = {
      columns: [],
      rows: [],
      filename: "export",
      ...options,
    };

    if (!Array.isArray(config.rows) || config.rows.length === 0) {
      notify("warning", "No records are available to export.");
      return;
    }

    const headers = config.columns.map((column) => {
      const label =
        typeof column === "string"
          ? column
          : column.label || column.key || "";

      return `"${String(label).replace(/"/g, '""')}"`;
    });

    const rows = config.rows.map((row, rowIndex) =>
      config.columns.map((column) => {
        let value;

        if (typeof column === "string") {
          value = row[column];
        } else if (column.type === "index") {
          value = rowIndex + 1;
        } else if (typeof column.render === "function") {
          value = column.render(row, rowIndex);
        } else {
          value = row[column.key];
        }

        return `"${String(getValue(value, "")).replace(/"/g, '""')}"`;
      }),
    );

    const csv = [headers, ...rows]
      .map((row) => row.join(","))
      .join("\n");

    const blob = new Blob([`\uFEFF${csv}`], {
      type: "text/csv;charset=utf-8;",
    });

    downloadBlob(
      blob,
      `${safeFilename(config.filename, "export")}_${new Date()
        .toISOString()
        .slice(0, 10)}.csv`,
    );
  }

  /* ==========================================================================
     Backward-compatible helper names
     ========================================================================== */

  function generateReportHeader() {
    console.warn(
      "generateReportHeader() is deprecated. The backend now renders templates/print/server/report_header.php.",
    );
    return "";
  }

  function generateReportFooter() {
    console.warn(
      "generateReportFooter() is deprecated. The backend now renders templates/print/server/report_footer.php.",
    );
    return "";
  }

  function generateReportIntroduction() {
    console.warn(
      "generateReportIntroduction() is deprecated. Report metadata is rendered by the server report template.",
    );
    return "";
  }

  function generateReportDocument(content) {
    return content || "";
  }

  function generatePrintCSS() {
    console.warn(
      "generatePrintCSS() is deprecated. Reports use public/css/print-reports.css and ID cards use public/css/student-id-card.css.",
    );
    return "";
  }

  /* ==========================================================================
     Public API
     ========================================================================== */

  return {
    printTable,
    printRecord,
    printModal,
    printElement,
    printReportHtml,

    printCertificate,

    printIdCard,
    printStudentIdCards,
    printSingleStudentIdCard,
    printBulkStudentIdCards,
    buildIdCardPrintPayload,
    bindIdPrintControls,
    getStoredIdPrinterMode,
    setStoredIdPrinterMode,
    getStoredIdSide,
    setStoredIdSide,
    getStoredIdChunkSize,
    setStoredIdChunkSize,

    printReceipt,
    exportToCSV,

    handleGeneratedFiles,
    showGeneratedFilesDialog,
    createReportCode,

    generateReportHeader,
    generateReportFooter,
    generateReportIntroduction,
    generateReportDocument,
    generatePrintCSS,

    setDefaults(newDefaults = {}) {
      if (!newDefaults || typeof newDefaults !== "object") {
        return;
      }

      if (newDefaults.endpoints) {
        defaults.endpoints = {
          ...defaults.endpoints,
          ...newDefaults.endpoints,
        };
      }

      if (newDefaults.requestHeaders) {
        defaults.requestHeaders = {
          ...defaults.requestHeaders,
          ...newDefaults.requestHeaders,
        };
      }

      if (newDefaults.colors) {
        defaults.colors = {
          ...defaults.colors,
          ...newDefaults.colors,
        };
      }

      Object.keys(newDefaults).forEach((key) => {
        if (!["endpoints", "requestHeaders", "colors"].includes(key)) {
          defaults[key] = newDefaults[key];
        }
      });
    },

    getDefaults() {
      return {
        ...defaults,
        endpoints: {
          ...defaults.endpoints,
        },
        requestHeaders: {
          ...defaults.requestHeaders,
        },
        colors: {
          ...defaults.colors,
        },
      };
    },
  };
})();

window.PrintManager = PrintManager;

