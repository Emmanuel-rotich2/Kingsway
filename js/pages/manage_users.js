/**
 * Manage Users Page Controller (compatibility shim)
 * The real controller is manageUsersController in users.js (949 lines).
 * This file exists for backward compatibility — if anything loads manage_users.js
 * instead of users.js, it delegates to the real controller.
 */

document.addEventListener("DOMContentLoaded", () => {
  if (typeof AuthContext !== "undefined" && !AuthContext.isAuthenticated()) {
    window.location.href = (window.APP_BASE || "") + "/index.php";
    return;
  }

  // Delegate to real controller if available
  if (window.manageUsersController?.init) {
    window.manageUsersController.init();
  } else {
    console.warn(
      "manage_users.js: manageUsersController not found. Ensure users.js is loaded.",
    );
  }
});
