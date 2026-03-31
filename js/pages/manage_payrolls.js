/**
 * Manage Payrolls Page Controller - Compatibility Shim
 * 
 * The actual payroll management logic is in payroll_manager.js (PayrollManagerController).
 * manage_payrolls.php loads payroll_manager.js directly.
 * This file exists for backwards compatibility if any page references ManagePayrollsController.
 */

const ManagePayrollsController = {
  init: function () {
    if (typeof PayrollManagerController !== "undefined") {
      PayrollManagerController.init();
      return;
    }
    // Fallback: redirect to the payroll management page
    console.warn("PayrollManagerController not found, redirecting...");
    window.location.href = (window.APP_BASE || "") + "/home.php?route=manage_payrolls";
  },
};

document.addEventListener("DOMContentLoaded", () =>
  ManagePayrollsController.init(),
);
