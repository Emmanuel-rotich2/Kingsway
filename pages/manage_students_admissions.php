<?php
/**
 * Manage Student Admissions Page - Permission-Based Router
 *
 * Loads a role-tier template from pages/admissions/ based on effective permissions.
 */
?>

<div id="manage-admissions-loading" style="padding: 40px; text-align: center;">
    <div class="spinner-border text-success" role="status">
        <span class="visually-hidden">Loading...</span>
    </div>
    <p class="mt-3">Loading admissions interface...</p>
</div>

<div id="manage-admissions-content" style="display: none;"></div>

<script>
    (async function () {
        const loading = document.getElementById("manage-admissions-loading");
        const container = document.getElementById("manage-admissions-content");

        if (typeof AuthContext === "undefined") {
            loading.innerHTML =
                '<div class="alert alert-danger">Authentication system not loaded. Please refresh the page.</div>';
            return;
        }

        if (!AuthContext.isAuthenticated()) {
            window.location.href = "/Kingsway/index.php";
            return;
        }

        const hasAnyPermission = (permissions) =>
            typeof AuthContext.hasAnyPermission === "function" &&
            AuthContext.hasAnyPermission(permissions);

        let routeAuthorized = false;
        try {
            if (window.API?.systemconfig?.authorizeRoute) {
                const authResult = await window.API.systemconfig.authorizeRoute("manage_students_admissions");
                const authPayload = authResult?.data ?? authResult;
                routeAuthorized = Boolean(authPayload?.authorized);
            }
        } catch (error) {
            console.warn("Route authorization check failed for admissions:", error);
        }

        if (!routeAuthorized) {
            loading.innerHTML =
                '<div class="alert alert-warning">' +
                '<i class="bi bi-shield-lock me-2"></i>' +
                "You are not allowed to access Admissions." +
                "</div>";
            return;
        }

        window.__admissionsRouteAuthorized = routeAuthorized;

        const anyAdmissionsAccess = hasAnyPermission([
            "admission_view",
            "admission_applications_view_all",
            "admission_applications_view_own",
            "admission_applications_view",
            "admission_documents_view_all",
            "admission_documents_view_own",
            "admission_documents_view",
            "admission_interviews_view_all",
            "admission_interviews_view_own",
            "admission_interviews_view"
        ]) || routeAuthorized;

        if (!anyAdmissionsAccess) {
            loading.innerHTML =
                '<div class="alert alert-warning">' +
                '<i class="bi bi-shield-lock me-2"></i>' +
                "You do not have permission to access Admissions." +
                "</div>";
            return;
        }

        const canFullAdmissions = hasAnyPermission([
            "admission_applications_approve_final",
            "admission_applications_generate",
            "admission_applications_assign",
            "admission_applications_approve",
            "admission_applications_view_all"
        ]);

        const canManageAdmissions = hasAnyPermission([
            "admission_documents_verify",
            "admission_documents_approve",
            "admission_documents_validate",
            "admission_interviews_schedule",
            "admission_interviews_create",
            "admission_interviews_edit",
            "admission_interviews_approve",
            "admission_applications_schedule",
            "admission_applications_verify",
            "admission_applications_validate"
        ]);

        const canOperateAdmissions = hasAnyPermission([
            "admission_applications_create",
            "admission_applications_submit",
            "admission_documents_upload",
            "admission_documents_create",
            "admission_applications_upload"
        ]);

        let templateFile = "viewer_admissions.php";
        if (canFullAdmissions) {
            templateFile = "admin_admissions.php";
        } else if (canManageAdmissions) {
            templateFile = "manager_admissions.php";
        } else if (canOperateAdmissions) {
            templateFile = "operator_admissions.php";
        }

        const templatePath = "/Kingsway/pages/admissions/" + templateFile;

        fetch(templatePath)
            .then(response => {
                if (!response.ok) {
                    throw new Error("Template not found: " + templatePath);
                }
                return response.text();
            })
            .then(html => {
                loading.style.display = "none";
                container.innerHTML = html;
                container.style.display = "block";

                if (window.RoleBasedUI?.applyTo) {
                    window.RoleBasedUI.applyTo(container);
                }

                const initAdmissions = () => {
                    if (window.AdmissionsController?.init) {
                        window.AdmissionsController.init();
                    }
                };

                if (window.AdmissionsController?.init) {
                    initAdmissions();
                    return;
                }

                const tempDiv = document.createElement("div");
                tempDiv.innerHTML = html;
                const scripts = tempDiv.querySelectorAll("script");

                scripts.forEach(script => {
                    const newScript = document.createElement("script");
                    if (script.src) {
                        const existingScript = document.querySelector(`script[src="${script.src}"]`);
                        if (existingScript) {
                            return;
                        }
                        newScript.src = script.src;
                        newScript.async = false;
                        newScript.onload = initAdmissions;
                    } else {
                        newScript.textContent = script.textContent;
                    }
                    document.body.appendChild(newScript);
                });
            })
            .catch(error => {
                loading.innerHTML =
                    '<div class="alert alert-warning">' +
                    '<i class="bi bi-exclamation-triangle me-2"></i>' +
                    'Could not load admissions template for your permissions.' +
                    '<br><small>Error: ' + error.message + '</small>' +
                    '</div>';
            });
    })();
</script>
