/**
 * One-Time Token Migration Script
 * 
 * This script runs ONCE on page load to check if the user has an old bloated JWT token.
 * If detected, it shows a friendly notification and provides a fix button.
 * This prevents the 400 "Request Header Too Large" errors.
 * 
 * Place this script AFTER api.js in home.php
 */

(function() {
    'use strict';

    // Check if we've already run this migration check today
    const MIGRATION_KEY = 'jwt_migration_checked';
    const MIGRATION_DATE_KEY = 'jwt_migration_date';
    const today = new Date().toDateString();
    const lastChecked = localStorage.getItem(MIGRATION_DATE_KEY);

    // Only check once per day to avoid annoying users
    if (lastChecked === today) {
        console.log('✓ JWT migration already checked today');
        return;
    }

    // Check if user is authenticated
    if (typeof AuthContext === 'undefined' || !AuthContext.isAuthenticated()) {
        // Not logged in, nothing to check
        return;
    }

    const token = localStorage.getItem('token');
    if (!token) {
        return;
    }

    // Analyze token
    try {
        const parts = token.split('.');
        if (parts.length !== 3) {
            console.warn('Invalid JWT token format');
            return;
        }

        const payload = JSON.parse(atob(parts[1]));
        const tokenSize = token.length;
        const authHeaderSize = `Bearer ${token}`.length;

        // Check if permissions are bloated (objects instead of strings)
        let isBloated = false;
        if (payload.permissions && Array.isArray(payload.permissions)) {
            const firstPerm = payload.permissions[0];
            if (typeof firstPerm === 'object') {
                isBloated = true;
            }
        }

        // Also check token size (bloated tokens are typically > 2500 bytes)
        if (tokenSize > 2500) {
            isBloated = true;
        }

        if (isBloated) {
            console.warn('⚠️ Old bloated JWT token detected');
            console.log('Token size:', tokenSize, 'bytes');
            console.log('This may cause 400 "Request Header Too Large" errors');
            
            // Show notification with fix button (non-intrusive)
            showTokenMigrationNotification();
        } else {
            console.log('✓ JWT token is optimized (compact format)');
            // Mark as checked for today
            localStorage.setItem(MIGRATION_DATE_KEY, today);
        }

    } catch (e) {
        console.error('Error checking JWT token:', e);
    }

    /**
     * Show a friendly notification about the old token
     */
    function showTokenMigrationNotification() {
        // Create notification banner
        const banner = document.createElement('div');
        banner.id = 'jwt-migration-banner';
        banner.style.cssText = `
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 15px 20px;
            z-index: 9999;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            display: flex;
            align-items: center;
            justify-content: space-between;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
        `;

        banner.innerHTML = `
            <div style="flex: 1; display: flex; align-items: center; gap: 15px;">
                <div style="font-size: 24px;">⚡</div>
                <div>
                    <div style="font-weight: 600; font-size: 16px; margin-bottom: 4px;">
                        System Update Required
                    </div>
                    <div style="font-size: 14px; opacity: 0.95;">
                        Your authentication token needs to be refreshed to continue using the system.
                    </div>
                </div>
            </div>
            <div style="display: flex; gap: 10px; align-items: center;">
                <button id="jwt-fix-btn" style="
                    background: white;
                    color: #667eea;
                    border: none;
                    padding: 10px 24px;
                    border-radius: 6px;
                    font-weight: 600;
                    cursor: pointer;
                    font-size: 14px;
                    transition: transform 0.2s;
                " onmouseover="this.style.transform='scale(1.05)'" onmouseout="this.style.transform='scale(1)'">
                    Refresh Now
                </button>
                <button id="jwt-dismiss-btn" style="
                    background: transparent;
                    color: white;
                    border: 1px solid rgba(255,255,255,0.3);
                    padding: 10px 20px;
                    border-radius: 6px;
                    cursor: pointer;
                    font-size: 14px;
                " onmouseover="this.style.borderColor='white'" onmouseout="this.style.borderColor='rgba(255,255,255,0.3)'">
                    Later
                </button>
            </div>
        `;

        document.body.insertBefore(banner, document.body.firstChild);

        // Adjust body padding to prevent content from being hidden
        document.body.style.paddingTop = '80px';

        // Handle fix button
        document.getElementById('jwt-fix-btn').addEventListener('click', function() {
            if (confirm('This will log you out and redirect to the login page. You can log back in immediately with the same credentials. Continue?')) {
                // Clear localStorage and redirect
                localStorage.clear();
                window.location.href = '/Kingsway/index.php';
            }
        });

        // Handle dismiss button
        document.getElementById('jwt-dismiss-btn').addEventListener('click', function() {
            banner.remove();
            document.body.style.paddingTop = '';
            // Mark as checked for today (user dismissed it)
            localStorage.setItem(MIGRATION_DATE_KEY, today);
        });
    }

})();
