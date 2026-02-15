/**
 * Manage Staff Page Controller (legacy shim)
 * Kept for backward compatibility with old includes.
 */

document.addEventListener("DOMContentLoaded", () => {
  if (!AuthContext.isAuthenticated()) {
    window.location.href = "/Kingsway/index.php";
    return;
  }

  if (window.staffManagementController?.init) {
    window.staffManagementController.init();
  }
});
