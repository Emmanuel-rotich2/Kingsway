/**
 * Soft Navigation Controller
 * Prevents full page reload when navigating between routes.
 * Only the #main-content-segment is replaced.
 * Sidebar, header and footer stay intact.
 */
(function () {
    'use strict';

    let isNavigating = false;

    /**
     * Load only the main content of a route (fragment mode)
     */
    async function loadFragment(route) {
        if (isNavigating) return;
        isNavigating = true;

        const container = document.getElementById('main-content-segment');
        if (!container) {
            isNavigating = false;
            return;
        }

        // Show loading indicator
        container.innerHTML = `
            <div class="text-center p-5">
                <div class="spinner-border text-success" role="status">
                    <span class="visually-hidden">Loading...</span>
                </div>
                <p class="mt-2 text-muted">Loading page...</p>
            </div>
        `;

        try {
            const url = `${window.APP_BASE || ''}/home.php?route=${encodeURIComponent(route)}&fragment=1`;

            const response = await fetch(url, {
                method: 'GET',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'text/html'
                },
                credentials: 'same-origin'
            });

            if (!response.ok) {
                throw new Error(`HTTP ${response.status}`);
            }

            const html = await response.text();
            container.innerHTML = html;

            // Re-execute <script> tags that came with the new page
            container.querySelectorAll('script').forEach((oldScript) => {
                const newScript = document.createElement('script');
                if (oldScript.src) {
                    newScript.src = oldScript.src;
                    newScript.async = false;
                } else {
                    newScript.textContent = oldScript.textContent;
                }
                document.body.appendChild(newScript);
                // Optional: remove the old script tag
                oldScript.remove();
            });

            // Update active state in the sidebar
            updateSidebarActiveState(route);

            // Dispatch a custom event so other modules know the page changed
            window.dispatchEvent(new CustomEvent('kingsway:page-loaded', {
                detail: { route }
            }));

        } catch (error) {
            console.error('Soft navigation failed:', error);
            container.innerHTML = `
                <div class="alert alert-danger m-3">
                    <i class="bi bi-exclamation-triangle me-2"></i>
                    Failed to load the page. Please try again.
                    <br>
                    <button class="btn btn-sm btn-outline-danger mt-2" onclick="window.location.reload()">
                        Reload full page
                    </button>
                </div>
            `;
        } finally {
            isNavigating = false;
        }
    }

    /**
     * Highlight the correct item in the sidebar
     */
    function updateSidebarActiveState(route) {
        // Remove active class from all sidebar links
        document.querySelectorAll('#sidebar-container a').forEach(link => {
            link.classList.remove('active');
        });

        // Add active class to the matching link
        const activeLink = document.querySelector(`#sidebar-container a[href*="route=${route}"]`);
        if (activeLink) {
            activeLink.classList.add('active');
        }
    }

    /**
     * Intercept sidebar (and other internal) clicks
     */
    function handleClick(e) {
        // Ignore if user is holding Ctrl / Cmd (open in new tab)
        if (e.ctrlKey || e.metaKey || e.shiftKey || e.button !== 0) return;

        const link = e.target.closest('a');
        if (!link) return;

        const href = link.getAttribute('href');
        if (!href) return;

        // Only handle links that go to home.php?route=...
        if (!href.includes('home.php') || !href.includes('route=')) return;

        // Ignore external links or special links
        if (link.target === '_blank' || link.hasAttribute('download')) return;

        e.preventDefault();

        try {
            const url = new URL(href, window.location.origin);
            const route = url.searchParams.get('route');

            if (!route) return;

            // Update browser URL without reloading
            const newUrl = `${window.APP_BASE || ''}/home.php?route=${encodeURIComponent(route)}`;
            history.pushState({ route }, '', newUrl);

            // Load only the content
            loadFragment(route);

        } catch (err) {
            console.error('Navigation error:', err);
            // Fallback to normal navigation
            window.location.href = href;
        }
    }

    /**
     * Handle browser Back / Forward buttons
     */
    function handlePopState(e) {
        if (e.state && e.state.route) {
            loadFragment(e.state.route);
        } else {
            // Fallback: reload the page
            window.location.reload();
        }
    }

    /**
     * Initialize soft navigation
     */
    function init() {
        // Listen for clicks on the whole document (event delegation)
        document.addEventListener('click', handleClick);

        // Listen for browser back/forward
        window.addEventListener('popstate', handlePopState);

        // Store the current route on first load
        const currentRoute = window.REQUESTED_ROUTE;
        if (currentRoute && currentRoute !== 'loading') {
            history.replaceState({ route: currentRoute }, '', window.location.href);
        }
    }

    // Start when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

    // Optional: expose for debugging
    window.SoftNavigation = {
        load: loadFragment
    };
})();