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
        const normalizeRole = (role) => String(
            typeof role === 'object' && role !== null
                ? (role.name || role.code || role.role || role.role_name || '')
                : role
        ).toLowerCase().replace(/[^a-z0-9]+/g, '_').replace(/^_+|_+$/g, '');

        const userRoles = (AuthContext.getRoles() || []).map(normalizeRole);
        return roleNames.some(n => userRoles.includes(normalizeRole(n)));
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
        const canAction = (action) => {
            if (typeof AuthContext !== 'undefined' && typeof AuthContext.canAction === 'function') {
                return AuthContext.canAction(mod, action);
            }
            return hasAny((window.PermissionContract?.aliasesFor?.(mod, action)) || [`${mod}_${action}`, `${mod}.${action}`]);
        };

        return {
            canView:    canAction('view'),
            canCreate:  canAction('create'),
            canEdit:    canAction('edit'),
            canDelete:  canAction('delete'),
            canApprove: canAction('approve'),
            canReject:  canAction('reject'),
            canExport:  canAction('export'),
            canPrint:   canAction('print'),
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
    async function loadRoleTemplate(options) {
        const {
            loadingId,
            contentId,
            templateDir,
            module: moduleName = 'this module',
            scriptSrc,
            levels = [],
            afterLoad,
        } = options;

        const loadingEl = document.getElementById(loadingId);
        const contentEl = document.getElementById(contentId);

        function showError(html) {
            if (loadingEl) {
                loadingEl.style.display = 'block';
                loadingEl.innerHTML = html;
            }
            if (contentEl) contentEl.style.display = 'none';
        }

        function ensureExternalScript(src) {
            const requested = new URL(src, window.location.href);
            const existing = Array.from(document.scripts).find((script) => {
                if (!script.src) return false;
                try {
                    const loaded = new URL(script.src, window.location.href);
                    return loaded.origin === requested.origin && loaded.pathname === requested.pathname;
                } catch (_) {
                    return false;
                }
            });
            if (existing?.dataset.kingswayScriptState === 'error') {
                existing.remove();
            } else if (existing) {
                if (existing.dataset.kingswayScriptState === 'loading') {
                    return new Promise((resolve, reject) => {
                        existing.addEventListener('load', () => resolve(existing), { once: true });
                        existing.addEventListener('error', () => reject(new Error('Script load failed: ' + src)), { once: true });
                    });
                }
                return Promise.resolve(existing);
            }

            return new Promise((resolve, reject) => {
                const script = document.createElement('script');
                script.src = src;
                script.async = false;
                script.dataset.kingswayScriptState = 'loading';
                script.addEventListener('load', () => {
                    script.dataset.kingswayScriptState = 'loaded';
                    resolve(script);
                }, { once: true });
                script.addEventListener('error', () => {
                    script.dataset.kingswayScriptState = 'error';
                    reject(new Error('Script load failed: ' + src));
                }, { once: true });
                document.body.appendChild(script);
            });
        }

        // Auth check — MUST wait for the silent boot refresh (AuthContext.ready())
        // before gating. The access token is in-memory only; on a fresh page load
        // it is restored asynchronously from the HttpOnly refresh cookie. Gating
        // synchronously here bounces valid users to index.php and aborts the
        // in-flight refresh POST (NS_BINDING_ABORTED).
        if (typeof AuthContext === 'undefined') {
            showError('<div class="alert alert-danger">Authentication system not loaded. Please refresh the page.</div>');
            return;
        }
        try {
            await Promise.race([
                AuthContext.ready(),
                new Promise(function (_, reject) {
                    setTimeout(function () {
                        reject(new Error('Authentication timed out'));
                    }, 15000);
                }),
            ]);
        } catch (e) {
            showError('<div class="alert alert-danger">' + e.message + '. Please refresh the page.</div>');
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

        // Role templates are injected HTML rather than normal document
        // navigations. Give each fetch a fresh URL so a service-worker or
        // browser cache cannot restore an older table after the template has
        // changed.
        const templateUrl = (window.APP_BASE || '') + templateDir + match.file +
            '?v=' + Date.now();

        fetch(templateUrl)
            .then(function (response) {
                if (!response.ok) {
                    // 403 means the template was blocked (deny rule/permission),
                    // not that it is missing — reporting "Template not found"
                    // sent the operator chasing a file that exists.
                    var reason = response.status === 403
                        ? 'Access denied loading template: ' + templateUrl
                        : 'Template not found: ' + templateUrl;
                    throw new Error(reason);
                }
                return response.text();
            })
            .then(async function (html) {
                if (loadingEl) loadingEl.style.display = 'none';
                if (contentEl) {
                    contentEl.innerHTML = html;
                    contentEl.style.display = 'block';
                }

                if (window.RoleBasedUI && typeof window.RoleBasedUI.applyTo === 'function') {
                    window.RoleBasedUI.applyTo(contentEl);
                }
                if (window.API && typeof window.API.applyPermissionContract === 'function') {
                    window.API.applyPermissionContract(contentEl);
                }

                // Make controller code available before inline template bootstraps run.
                // Dynamic scripts are not parser-blocking: appending an external script
                // and then immediately executing inline code races the network load.
                const templateScripts = contentEl
                    ? Array.from(contentEl.querySelectorAll('script'))
                    : [];
                const inlineScripts = [];
                for (const original of templateScripts) {
                    if (original.src) {
                        const src = original.src;
                        original.remove();
                        await ensureExternalScript(src);
                    } else {
                        inlineScripts.push(original.textContent || '');
                        original.remove();
                    }
                }

                if (scriptSrc) {
                    await ensureExternalScript((window.APP_BASE || '') + scriptSrc);
                }

                // Apply page-specific post-processing before generic role-based UI.
                if (typeof afterLoad === 'function') {
                    await afterLoad(contentEl, match);
                }

                inlineScripts.forEach((source) => {
                    const script = document.createElement('script');
                    script.textContent = source;
                    document.body.appendChild(script);
                });
                window.APIRealtime?.attachCurrentRouteControllers?.();
                window.dispatchEvent(new CustomEvent('kingsway:role-template-loaded', {
                    detail: { route: window.REQUESTED_ROUTE, file: match.file, container: contentEl },
                }));
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
