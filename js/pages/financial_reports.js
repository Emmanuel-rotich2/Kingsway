/**
 * financial_reports.js
 * Compatibility shim. The financial reports route reuses finance_reports.js.
 */

document.addEventListener("DOMContentLoaded", () => {
  if (window.financeReportsController?.init) {
    return;
  }
  const script = document.createElement("script");
  script.src = (window.APP_BASE || "") + "/js/pages/finance_reports.js";
  document.body.appendChild(script);
});
