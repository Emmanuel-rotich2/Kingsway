/**
 * Sidebar Manager
 * Handles dynamic sidebar menu rendering based on user permissions
 */

(function() {
    'use strict';

    /**
     * Render sidebar menu items from data
     * @param {Array} menuItems - Array of menu item objects from backend
     */
    function renderSidebar(menuItems) {
        const sidebarMenu = document.getElementById('sidebarMenu');
        if (!sidebarMenu) {
            console.warn('Sidebar menu container not found');
            return;
        }

        if (!Array.isArray(menuItems) || menuItems.length === 0) {
            sidebarMenu.innerHTML = '<div class="p-3 text-muted text-center">No menu items available</div>';
            return;
        }

        let html = '';

        menuItems.forEach(item => {
            if (!item) return;

            const hasSubitems = item.subitems && Array.isArray(item.subitems) && item.subitems.length > 0;
            const itemId = btoa(item.label || '').replace(/=/g, ''); // Use base64 hash for unique ID
            const icon = item.icon || 'bi-circle';
            const route = item.route || '#';

            if (hasSubitems) {
                // Menu item with subitems (collapsible)
                html += `
                    <a href="#submenu-${itemId}"
                       class="list-group-item list-group-item-action d-flex justify-content-between align-items-center sidebar-toggle"
                       data-bs-toggle="collapse"
                       aria-expanded="false"
                       aria-controls="submenu-${itemId}">
                        <span>
                            <i class="${icon} me-2"></i>
                            <span class="sidebar-text">${escapeHtml(item.label)}</span>
                        </span>
                        <i class="fas fa-chevron-down small"></i>
                    </a>
                    <div class="collapse" id="submenu-${itemId}" data-bs-parent="#sidebarMenu">
                `;

                // Render subitems
                item.subitems.forEach(subitem => {
                    if (!subitem) return;
                    const subIcon = subitem.icon || 'bi-dot';
                    const subRoute = subitem.route || '#';
                    
                    html += `
                        <a href="#" data-route="${escapeHtml(subRoute)}" class="list-group-item list-group-item-action ps-5 sidebar-link">
                            <i class="${subIcon} me-2"></i>
                            ${escapeHtml(subitem.label)}
                        </a>
                    `;
                });

                html += `</div>`;
            } else {
                // Simple menu item (no subitems)
                html += `
                    <a href="#" data-route="${escapeHtml(route)}" class="list-group-item list-group-item-action sidebar-link">
                        <i class="${icon} me-2"></i>
                        <span class="sidebar-text">${escapeHtml(item.label)}</span>
                    </a>
                `;
            }
        });

        sidebarMenu.innerHTML = html;

        // Re-attach click handlers for SPA navigation
        attachSidebarHandlers();
    }

    /**
     * Attach click handlers to sidebar links for SPA navigation
     */
    function attachSidebarHandlers() {
        const sidebarLinks = document.querySelectorAll('.sidebar-link');
        
        sidebarLinks.forEach(link => {
            link.addEventListener('click', function(e) {
                e.preventDefault();
                const route = this.getAttribute('data-route');
                
                if (route && route !== '#') {
                    // Remove active class from all links
                    document.querySelectorAll('.sidebar-link').forEach(l => l.classList.remove('active'));
                    // Add active class to clicked link
                    this.classList.add('active');
                    
                    // Trigger SPA navigation (if using a router)
                    if (typeof window.navigateTo === 'function') {
                        window.navigateTo(route);
                    } else {
                        console.log('Navigate to:', route);
                    }
                }
            });
        });
    }

    /**
     * Escape HTML to prevent XSS
     */
    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    /**
     * Initialize sidebar from localStorage on page load
     */
    function initializeSidebar() {
        // Get sidebar items from AuthContext
        if (typeof AuthContext !== 'undefined' && typeof AuthContext.getSidebarItems === 'function') {
            const sidebarItems = AuthContext.getSidebarItems();
            if (sidebarItems && sidebarItems.length > 0) {
                renderSidebar(sidebarItems);
            }
        }
    }

    /**
     * Refresh sidebar with new menu items
     * Called after login or when permissions change
     */
    window.refreshSidebar = function(menuItems) {
        if (menuItems) {
            renderSidebar(menuItems);
        } else if (typeof AuthContext !== 'undefined') {
            const sidebarItems = AuthContext.getSidebarItems();
            renderSidebar(sidebarItems);
        }
    };

    // Initialize sidebar when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initializeSidebar);
    } else {
        initializeSidebar();
    }

    // Expose for global use
    window.SidebarManager = {
        render: renderSidebar,
        initialize: initializeSidebar,
        refresh: window.refreshSidebar
    };
})();
