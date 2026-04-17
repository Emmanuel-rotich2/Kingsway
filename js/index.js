// Sidebar toggle (desktop & mobile)
function toggleSidebar() {
    const sidebar = document.querySelector('.sidebar');
    const mainFlex = document.querySelector('.main-flex-layout');
    sidebar.classList.toggle('sidebar-collapsed');
    const logo = document.querySelector('.sidebar .logo');

    // Hide all submenus and dropdown arrows when collapsed
    if (sidebar.classList.contains('sidebar-collapsed')) {
        document.querySelectorAll('.sidebar .collapse').forEach(c => c.classList.remove('show'));
        document.querySelectorAll('.sidebar .sidebar-toggle .fa-chevron-down').forEach(icon => icon.style.display = 'none');
        // Hide logo name/text
        document.querySelectorAll('.sidebar .logo .logo-name, .sidebar .logo h5').forEach(el => el.style.display = 'none');
        mainFlex.style.marginLeft = '60px';
        logo.style.width = '60px';
    } else {
        document.querySelectorAll('.sidebar .sidebar-toggle .fa-chevron-down').forEach(icon => icon.style.display = '');
        // Show logo name/text
        document.querySelectorAll('.sidebar .logo .logo-name, .sidebar .logo h5').forEach(el => el.style.display = '');
        mainFlex.style.marginLeft = '250px';
        logo.style.width = '250px';
    }
    // For mobile
    if (window.innerWidth < 992) {
        sidebar.classList.toggle('sidebar-visible-mobile');
        mainFlex.style.marginLeft = '0';
    }
}

// Sidebar UI re-initializer for dynamic sidebar
window.initSidebarUI = function() {
    // Sidebar submenu accordion (open/close on click)
    document.querySelectorAll('.sidebar-toggle').forEach(function (toggle) {
        toggle.addEventListener('click', function (e) {
            e.preventDefault();
            const target = document.querySelector(this.getAttribute('href'));
            if (!target) return;
            if (target.classList.contains('show')) {
                target.classList.remove('show');
                this.setAttribute('aria-expanded', 'false');
            } else {
                // Close all open submenus
                document.querySelectorAll('.list-group .collapse.show').forEach(function (open) {
                    open.classList.remove('show');
                });
                document.querySelectorAll('.sidebar-toggle[aria-expanded="true"]').forEach(function (btn) {
                    btn.setAttribute('aria-expanded', 'false');
                });
                // Open the clicked submenu
                target.classList.add('show');
                this.setAttribute('aria-expanded', 'true');
            }
        });
    });

    // Responsive sidebar behavior on resize
    window.addEventListener('resize', function () {
        const sidebar = document.querySelector('.sidebar');
        const mainFlex = document.querySelector('.main-flex-layout');
        if (!sidebar || !mainFlex) return;
        if (window.innerWidth < 992) {
            sidebar.classList.add('sidebar-collapsed');
            sidebar.classList.remove('sidebar-visible-mobile');
            mainFlex.style.marginLeft = '0';
            document.querySelectorAll('.sidebar .sidebar-toggle .fa-chevron-down').forEach(icon => icon.style.display = 'none');
            document.querySelectorAll('.sidebar .logo .logo-name, .sidebar .logo h5').forEach(el => el.style.display = 'none');
        } else {
            sidebar.classList.remove('sidebar-visible-mobile');
            sidebar.classList.remove('sidebar-collapsed');
            mainFlex.style.marginLeft = '250px';
            document.querySelectorAll('.sidebar .sidebar-toggle .fa-chevron-down').forEach(icon => icon.style.display = '');
            document.querySelectorAll('.sidebar .logo .logo-name, .sidebar .logo h5').forEach(el => el.style.display = '');
        }
    });
};
// Call on DOMContentLoaded for initial sidebar
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', window.initSidebarUI);
} else {
    window.initSidebarUI();
}

// Sidebar submenu accordion (open/close on click)
document.addEventListener('DOMContentLoaded', function () {
    // Only run sidebar logic if sidebar exists
    const sidebar = document.querySelector('.sidebar');
    if (!sidebar) return;

    // Make sidebar scrollable if overflow
    const sidebarScroll = document.querySelector('.sidebar .shadow-sm');
    if (sidebarScroll) {
        sidebarScroll.style.overflowY = 'auto';
        sidebarScroll.style.flex = '1 1 0';
    }

    // Hide dropdown arrows and logo name if sidebar is collapsed on load
    if (document.querySelector('.sidebar').classList.contains('sidebar-collapsed')) {
        document.querySelectorAll('.sidebar .sidebar-toggle .fa-chevron-down').forEach(icon => icon.style.display = 'none');
        document.querySelectorAll('.sidebar .logo .logo-name, .sidebar .logo h5').forEach(el => el.style.display = 'none');
    }

    // Accordion for sidebar submenus
    document.querySelectorAll('.sidebar-toggle').forEach(function (toggle) {
        toggle.addEventListener('click', function (e) {
            // Prevent toggling when sidebar is collapsed
            if (document.querySelector('.sidebar').classList.contains('sidebar-collapsed')) {
                e.preventDefault();
                return;
            }
            e.preventDefault();
            const target = document.querySelector(this.getAttribute('href'));
            if (target.classList.contains('show')) {
                target.classList.remove('show');
                this.setAttribute('aria-expanded', 'false');
            } else {
                // Close all open submenus
                document.querySelectorAll('.list-group .collapse.show').forEach(function (open) {
                    open.classList.remove('show');
                });
                document.querySelectorAll('.sidebar-toggle[aria-expanded="true"]').forEach(function (btn) {
                    btn.setAttribute('aria-expanded', 'false');
                });
                // Open the clicked submenu
                target.classList.add('show');
                this.setAttribute('aria-expanded', 'true');
            }
        });
    });
});

// Responsive sidebar behavior on resize
window.addEventListener('resize', function () {
    const sidebar = document.querySelector('.sidebar');
    const mainFlex = document.querySelector('.main-flex-layout');
    if (window.innerWidth < 992) {
        sidebar.classList.add('sidebar-collapsed');
        sidebar.classList.remove('sidebar-visible-mobile');
        mainFlex.style.marginLeft = '0';
        document.querySelectorAll('.sidebar .sidebar-toggle .fa-chevron-down').forEach(icon => icon.style.display = 'none');
        document.querySelectorAll('.sidebar .logo .logo-name, .sidebar .logo h5').forEach(el => el.style.display = 'none');
    } else {
        sidebar.classList.remove('sidebar-visible-mobile');
        sidebar.classList.remove('sidebar-collapsed');
        mainFlex.style.marginLeft = '250px';
        document.querySelectorAll('.sidebar .sidebar-toggle .fa-chevron-down').forEach(icon => icon.style.display = '');
        document.querySelectorAll('.sidebar .logo .logo-name, .sidebar .logo h5').forEach(el => el.style.display = '');
    }
});

window.toggleSidebar = toggleSidebar;

function getRouteFromUrl(url) {
    if (!url) return '';
    const value = String(url).trim();
    const match = value.match(/[?&]route=([^&#]+)/);
    if (match && match[1]) {
        try {
            return decodeURIComponent(match[1]);
        } catch (e) {
            return match[1];
        }
    }
    return value;
}

function getCurrentUserRoleIds() {
    const user = typeof AuthContext !== "undefined" ? AuthContext.getUser() : null;
    if (!user) {
        return [];
    }

    if (Array.isArray(user.role_ids) && user.role_ids.length > 0) {
        return [...new Set(user.role_ids.map((roleId) => Number(roleId)).filter(Boolean))];
    }

    const resolved = [];
    const roles = Array.isArray(user.roles) ? user.roles : [];
    roles.forEach((role) => {
        if (role && typeof role === "object") {
            const roleId = role.id || role.role_id;
            if (roleId) {
                resolved.push(Number(roleId));
            }
        } else if (role) {
            const numericRole = Number(role);
            if (numericRole) {
                resolved.push(numericRole);
            }
        }
    });

    return [...new Set(resolved)];
}

function collectAllowedRoutes(items, allowed = new Set()) {
    if (!Array.isArray(items)) {
        return allowed;
    }

    items.forEach((item) => {
        if (!item) {
            return;
        }

        const route = getRouteFromUrl(item.url || item.route || item.data_route || "");
        if (route && route !== "#" && route !== "loading") {
            allowed.add(route);
        }

        if (Array.isArray(item.subitems)) {
            collectAllowedRoutes(item.subitems, allowed);
        }
    });

    return allowed;
}

function getAllowedRoutes() {
    const allowed = collectAllowedRoutes(
        typeof AuthContext !== "undefined" ? AuthContext.getSidebarItems() : []
    );
    const dashboardInfo =
        typeof AuthContext !== "undefined" ? AuthContext.getDashboardInfo() : null;
    const dashboardRoute = getRouteFromUrl(dashboardInfo?.key || "");
    if (dashboardRoute) {
        allowed.add(dashboardRoute);
    }
    return allowed;
}

function getBestAllowedRoute(excludedRoute = "") {
    const allowed = [...getAllowedRoutes()].filter(
        (route) => route && route !== excludedRoute
    );

    const dashboardInfo =
        typeof AuthContext !== "undefined" ? AuthContext.getDashboardInfo() : null;
    const dashboardRoute = getRouteFromUrl(dashboardInfo?.key || "");
    if (dashboardRoute && dashboardRoute !== excludedRoute && allowed.includes(dashboardRoute)) {
        return dashboardRoute;
    }

    return allowed[0] || dashboardRoute || "";
}

async function authorizeRouteAccess(route) {
    const normalizedRoute = getRouteFromUrl(route);
    if (!normalizedRoute || normalizedRoute === "loading") {
        return { authorized: true, route: normalizedRoute, source: "shell" };
    }

    if (typeof AuthContext === "undefined" || !AuthContext.isAuthenticated()) {
        return { authorized: false, route: normalizedRoute, reason: "unauthenticated" };
    }

    const user = AuthContext.getUser() || {};
    const userId = user.id || user.user_id || null;
    const roleIds = getCurrentUserRoleIds();

    try {
        const response = await API.systemconfig.authorizeRoute(normalizedRoute, {
            userId,
            roleIds,
        });
        if (response && response.success === false) {
            throw new Error(response.message || "Route authorization failed");
        }

        const authorization = {
            route: normalizedRoute,
            ...(response || { authorized: false }),
        };
        return authorization;
    } catch (error) {
        console.warn("Route authorization API failed, denying route:", normalizedRoute, error);
        return {
            authorized: false,
            route: normalizedRoute,
            source: "api_error",
            reason: "authorization_check_failed",
        };
    }
}

// Route guard overlay removed — PHP serves the correct page directly.
// These stubs keep existing callers from throwing.
function setRouteGuardPending(_isPending, _message) { /* no-op */ }
function revealProtectedContent() { /* no-op */ }

async function redirectToAllowedRoute(disallowedRoute) {
    const normalizedRoute = getRouteFromUrl(disallowedRoute);
    const fallbackRoute = getBestAllowedRoute(normalizedRoute);
    if (!fallbackRoute || fallbackRoute === normalizedRoute) {
        revealProtectedContent();
        return null;
    }

    window.location.replace(
        (window.APP_BASE || '') + `/home.php?route=${encodeURIComponent(fallbackRoute)}`
    );
    return fallbackRoute;
}

// Navigation handler for sidebar links
window.addEventListener('click', async function(e) {
    if (e.defaultPrevented) {
        return;
    }
    const link = e.target.closest ? e.target.closest('.sidebar-link') : null;
    if (link) {
        e.preventDefault();
        const route = link.getAttribute('data-route');
        if (route) {
            const authorization = await authorizeRouteAccess(route);
            if (!authorization.authorized) {
                showNotification("You are not allowed to open that page.", NOTIFICATION_TYPES.WARNING);
                await redirectToAllowedRoute(getRouteFromUrl(route));
                return;
            }

            const normalizedRoute = getRouteFromUrl(route);
            window.location.href = (window.APP_BASE || '') + `/home.php?route=${encodeURIComponent(normalizedRoute)}`;
        }
    }
});

// Navigation function for loading dashboard/pages
async function navigateToRoute(route) {
    const normalizedRoute = getRouteFromUrl(route);
    const authorization = await authorizeRouteAccess(normalizedRoute);
    if (!authorization.authorized) {
        showNotification("You are not allowed to open that page.", NOTIFICATION_TYPES.WARNING);
        await redirectToAllowedRoute(normalizedRoute);
        return false;
    }

    let html = '';
    try {
        html = await fetchContent((window.APP_BASE || '') + `/components/dashboards/${normalizedRoute}.php`);
    } catch {
        try {
            html = await fetchContent((window.APP_BASE || '') + `/pages/${normalizedRoute}.php`);
        } catch {
            html = "<div class='alert alert-warning'>Page not found.</div>";
        }
    }
    document.getElementById('main-content-segment').innerHTML = html;
    return true;
}

async function fetchContent(path) {
    const res = await fetch(path);
    if (!res.ok) throw new Error('Not found');
    return await res.text();
}

// Initial load: load sidebar and default dashboard if needed
if (typeof loadSidebarAndDefault === 'function') {
    document.addEventListener('DOMContentLoaded', loadSidebarAndDefault);
}

window.AppRouteAccess = {
    authorizeRoute: authorizeRouteAccess,
    getAllowedRoutes,
    getBestAllowedRoute,
    redirectToAllowedRoute,
    revealProtectedContent,
    setPending: setRouteGuardPending,
    getCurrentUserRoleIds,
    normalizeRoute: getRouteFromUrl,
};
window.navigateToRoute = navigateToRoute;
