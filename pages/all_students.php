<?php
/**
 * All Students Page - Role-Based Router
 *
 * Loads role-specific templates from pages/students/ based on JWT role.
 */
?>

<!-- Loading state while determining user role -->
<div id="all-students-loading" style="padding: 40px; text-align: center;">
    <div class="spinner-border text-primary" role="status">
        <span class="visually-hidden">Loading...</span>
    </div>
    <p class="mt-3">Loading students interface...</p>
</div>

<!-- Container where the correct template will be loaded -->
<div id="all-students-content" style="display: none;"></div>

<script>
    (function () {
        if (typeof AuthContext === "undefined") {
            document.getElementById("all-students-loading").innerHTML =
                '<div class="alert alert-danger">Authentication system not loaded. Please refresh the page.</div>';
            return;
        }

        if (!AuthContext.isAuthenticated()) {
            window.location.href = window.APP_BASE + "/index.php";
            return;
        }

        const user = AuthContext.getUser();
        const normalizeRoleName = (roleName) => String(roleName || "")
            .toLowerCase()
            .replace(/[^a-z0-9]+/g, "_")
            .replace(/^_+|_+$/g, "");

        const roleNames = (AuthContext.getRoles() || []).map(normalizeRoleName);
        const hasPermission = (permission) =>
            typeof AuthContext.hasPermission === "function" &&
            AuthContext.hasPermission(permission);
        const hasAnyPermissionAlias = (permissionGroups) => {
            if (!Array.isArray(permissionGroups)) return false;
            return permissionGroups.some((group) => {
                if (Array.isArray(group)) {
                    return group.some((perm) => hasPermission(perm));
                }
                return hasPermission(group);
            });
        };
        const hasAnyRole = (names) => names.some((name) => roleNames.includes(name));
        const canView = hasAnyPermissionAlias([
            ["students_view", "students_view_all", "students_view_own"],
        ]);
        const canCreate = hasAnyPermissionAlias([["students_create"]]);
        const canEdit = hasAnyPermissionAlias([["students_edit", "students_update", "students_edit_own"]]);
        const canDelete = hasAnyPermissionAlias([["students_delete"]]);
        const canPromote = hasAnyPermissionAlias([["students_promote", "students_approve", "students_approve_final"]]);
        const canFinanceView = hasAnyPermissionAlias([["fees_view", "finance_view"]]);
        const hasStudentsAccess =
            canView || canCreate || canEdit || canDelete || canPromote || canFinanceView ||
            hasAnyRole(["parent", "student"]);

        if (roleNames.length === 0 && !user) {
            document.getElementById("all-students-loading").innerHTML =
                '<div class="alert alert-danger">User role not found. Please log in again.</div>';
            return;
        }

        if (!hasStudentsAccess) {
            document.getElementById("all-students-loading").innerHTML =
                '<div class="alert alert-warning">' +
                '<i class="bi bi-shield-lock me-2"></i>' +
                "You do not have permission to access the Students module." +
                "</div>";
            return;
        }

        let templateFile = "manage_students_viewer.php";
        const isPortalViewerRole = hasAnyRole(["parent", "student"]);
        const canViewOwnOnly = hasAnyPermissionAlias([["students_view_own"]]) && !canCreate && !canEdit && !canDelete && !canPromote;

        if (isPortalViewerRole || canViewOwnOnly) {
            templateFile = "viewer_students.php";
        } else if (canDelete || hasAnyPermissionAlias([["students_view_all"]])) {
            templateFile = "manage_students_admin.php";
        } else if (canPromote) {
            templateFile = "manage_students_manager.php";
        } else if (canCreate || canEdit) {
            templateFile = "manage_students_operator.php";
        } else if (!canView && !canFinanceView) {
            templateFile = "viewer_students.php";
        }

        const templatePath = window.APP_BASE + "/pages/students/" + templateFile;

        fetch(templatePath)
            .then(response => {
                if (!response.ok) {
                    throw new Error("Template not found: " + templatePath);
                }
                return response.text();
            })
            .then(html => {
                document.getElementById("all-students-loading").style.display = "none";
                const container = document.getElementById("all-students-content");
                container.innerHTML = html;
                container.style.display = "block";

                if (window.RoleBasedUI?.applyTo) {
                    window.RoleBasedUI.applyTo(container);
                } else if (window.RoleBasedUI?.apply) {
                    window.RoleBasedUI.apply(container);
                }

                const tempDiv = document.createElement("div");
                tempDiv.innerHTML = html;
                const scripts = tempDiv.querySelectorAll("script");

                scripts.forEach(script => {
                    const newScript = document.createElement("script");
                    if (script.src) {
                        newScript.src = script.src;
                        newScript.async = false;
                    } else {
                        newScript.textContent = script.textContent;
                    }
                    document.body.appendChild(newScript);
                });
            })
            .catch(error => {
                document.getElementById("all-students-loading").innerHTML =
                    '<div class="alert alert-warning">' +
                    '<i class="bi bi-exclamation-triangle me-2"></i>' +
                    'Template not found for your role. Please contact system administrator.' +
                    '<br><small>Error: ' + error.message + '</small>' +
                    '</div>';
            });
    })();
</script>
