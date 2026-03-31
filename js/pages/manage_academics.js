/**
 * manage_academics.js — bootstrap shim
 * The full implementation lives in academicsManager.js which is loaded
 * directly by manage_academics.php via its own <script> tag.
 * This file ensures the controller initialises when this route is loaded
 * outside of manage_academics.php (e.g. SPA navigation).
 */
document.addEventListener('DOMContentLoaded', () => {
    if (!AuthContext.isAuthenticated()) {
        window.location.href = (window.APP_BASE || '') + '/index.php';
        return;
    }
    if (typeof academicsManager !== 'undefined' && typeof academicsManager.init === 'function') {
        academicsManager.init();
    }
});
