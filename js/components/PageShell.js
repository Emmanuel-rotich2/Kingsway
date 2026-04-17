/**
 * PageShell — Shared role-router and permission helpers
 *
 * Eliminates duplicated role-detection and fetch-and-inject logic across
 * manage_*.php router pages. Each router page just calls PageShell.loadRoleTemplate()
 * with a permission map and a role→templateFile map.
 *
 * Usage in a router page:
 *
 *   PageShell.loadRoleTemplate({
 *     loadingId:   'boarding-loading',
 *     contentId:   'boarding-content',
 *     templateDir: '/pages/boarding/',
 *     scriptSrc:   '/js/pages/boarding.js',
 *     module:      'boarding',
 *     levels: [
 *       { file: 'admin_boarding.php',    test: () => PageShell.hasAny(['boarding_manage', 'boarding_delete']) || PageShell.hasRole(['headteacher', 'director']) },
 *       { file: 'manager_boarding.php',  test: () => PageShell.hasAny(['boarding_edit', 'boarding_assign']) },
 *       { file: 'operator_boarding.php', test: () => PageShell.hasAny(['boarding_view']) },
 *       { file: 'viewer_boarding.php',   test: () => true },    // fallback
 *     ],
 *   });
 */

const PageShell = (() => {

    // -------------------------------------------------------------------------
    // Permission helpers
    // -------------------------------------------------------------------------

    /**
     * Check if the current user has a single permission.
     * Supports both dot-notation ('boarding.view') and underscore aliases ('boarding_view').
     */
    function hasPerm(permCode) {
        if (typeof AuthContext === 'undefined') return false;
        if (typeof AuthContext.hasPermission === 'function') {
            return AuthContext.hasPermission(permCode);
        }
        return false;
    }

    /**
     * Return true if the user has ANY of the listed permissions.
     * @param {string[]} perms
     */
    function hasAny(perms) {
        return Array.isArray(perms) && perms.some(hasPerm);
    }

    /**
     * Return true if the user has ALL of the listed permissions.
     * @param {string[]} perms
     */
    function hasAll(perms) {
        return Array.isArray(perms) && perms.every(hasPerm);
    }

    /**
     * Return true if the current user has any of the listed roles.
     * Role names are compared case-insensitively after normalizing spaces/slashes to underscores.
     * @param {string[]} roleNames
     */
    function hasRole(roleNames) {
        if (typeof AuthContext === 'undefined') return false;
        const userRoles = (AuthContext.getRoles() || []).map(r =>
            String(r).toLowerCase().replace(/[^a-z0-9]+/g, '_').replace(/^_|_$/g, '')
        );
        return roleNames.some(n =>
            userRoles.includes(String(n).toLowerCase().replace(/[^a-z0-9]+/g, '_').replace(/^_|_$/g, ''))
        );
    }

    // -------------------------------------------------------------------------
    // Common permission checks — call these from page code
    // -------------------------------------------------------------------------

    /**
     * Build a permission checker for a given module.
     * Returns an object with canView, canCreate, canEdit, canDelete, canApprove, canExport.
     *
     * Example:
     *   const perms = PageShell.modulePerms('students');
     *   if (perms.canEdit) { ... }
     */
    function modulePerms(mod) {
        return {
            canView:    hasAny([`${mod}_view`, `${mod}_view_all`, `${mod}_view_own`, `${mod}.view`]),
            canCreate:  hasAny([`${mod}_create`, `${mod}.create`]),
            canEdit:    hasAny([`${mod}_edit`, `${mod}_update`, `${mod}_edit_own`, `${mod}.edit`]),
            canDelete:  hasAny([`${mod}_delete`, `${mod}.delete`]),
            canApprove: hasAny([`${mod}_approve`, `${mod}_approve_final`, `${mod}.approve`]),
            canExport:  hasAny([`${mod}_export`, `${mod}.export`]),
            canManage:  hasAny([`${mod}_manage`, `${mod}_admin`, `${mod}.manage`]),
        };
    }

    // -------------------------------------------------------------------------
    // Role-template router
    // -------------------------------------------------------------------------

    /**
     * Load the appropriate role-specific template into a container.
     *
     * @param {Object} options
     * @param {string}   options.loadingId   - ID of the loading spinner element
     * @param {string}   options.contentId   - ID of the content container element
     * @param {string}   options.templateDir - Path prefix for template URLs (e.g. '/pages/boarding/')
     * @param {string}   [options.module]    - Module name for the access-denied message
     * @param {string}   [options.scriptSrc] - Optional JS controller to load after template injection
     * @param {Array}    options.levels      - Array of { file, test } objects, checked in order.
     *                                         The first entry whose test() returns true is used.
     *                                         The last entry should always have test: () => true as fallback.
     */
    function loadRoleTemplate(options) {
        const {
            loadingId,
            contentId,
            templateDir,
            module: moduleName = 'this module',
            scriptSrc,
            levels = [],
        } = options;

        const loadingEl = document.getElementById(loadingId);
        const contentEl = document.getElementById(contentId);

        function showError(html) {
            if (loadingEl) loadingEl.innerHTML = html;
        }

        // Auth check
        if (typeof AuthContext === 'undefined') {
            showError('<div class="alert alert-danger">Authentication system not loaded. Please refresh the page.</div>');
            return;
        }
        if (!AuthContext.isAuthenticated()) {
            window.location.href = (window.APP_BASE || '') + '/index.php';
            return;
        }

        // Find the first matching level
        const match = levels.find(function (level) {
            try { return level.test(); } catch (e) { return false; }
        });

        // If no level matched (should not happen if last level has test: () => true)
        if (!match) {
            showError(
                '<div class="alert alert-warning">' +
                '<i class="bi bi-shield-lock me-2"></i>' +
                'You do not have permission to access ' + moduleName + '.' +
                '</div>'
            );
            return;
        }

        const templateUrl = (window.APP_BASE || '') + templateDir + match.file;

        fetch(templateUrl)
            .then(function (response) {
                if (!response.ok) throw new Error('Template not found: ' + templateUrl);
                return response.text();
            })
            .then(function (html) {
                if (loadingEl) loadingEl.style.display = 'none';
                if (contentEl) {
                    contentEl.innerHTML = html;
                    contentEl.style.display = 'block';
                }

                // Apply RoleBasedUI to injected content
                if (window.RoleBasedUI && typeof window.RoleBasedUI.applyTo === 'function') {
                    window.RoleBasedUI.applyTo(contentEl);
                }

                // Re-execute <script> tags in injected HTML
                if (contentEl) {
                    contentEl.querySelectorAll('script').forEach(function (original) {
                        const script = document.createElement('script');
                        if (original.src) {
                            script.src = original.src;
                            script.async = false;
                        } else {
                            script.textContent = original.textContent;
                        }
                        document.body.appendChild(script);
                    });
                }

                // Lazy-load the module JS controller if provided and not already present
                if (scriptSrc) {
                    const controllerName = _guessControllerName(scriptSrc);
                    if (controllerName && typeof window[controllerName] === 'undefined') {
                        const s = document.createElement('script');
                        s.src = (window.APP_BASE || '') + scriptSrc;
                        document.body.appendChild(s);
                    } else if (!controllerName) {
                        const s = document.createElement('script');
                        s.src = (window.APP_BASE || '') + scriptSrc;
                        document.body.appendChild(s);
                    }
                }
            })
            .catch(function (error) {
                showError(
                    '<div class="alert alert-warning">' +
                    '<i class="bi bi-exclamation-triangle me-2"></i>' +
                    'Could not load ' + moduleName + ' interface. Please contact the system administrator.' +
                    '<br><small>' + error.message + '</small>' +
                    '</div>'
                );
            });
    }

    /**
     * Guess the global controller variable name from a script src path.
     * e.g. '/js/pages/boarding.js' → 'boardingController'
     */
    function _guessControllerName(src) {
        const match = src.match(/\/([^/]+)\.js$/);
        if (!match) return null;
        const base = match[1]; // e.g. 'boarding'
        return base + 'Controller';
    }

    // -------------------------------------------------------------------------
    // Public API
    // -------------------------------------------------------------------------

    return {
        hasPerm,
        hasAny,
        hasAll,
        hasRole,
        modulePerms,
        loadRoleTemplate,
    };

})();

// Make available globally
window.PageShell = PageShell;
