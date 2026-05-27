<?php
/**
 * Manage Student Admissions Page - Permission-Based Router
 *
 * Loads a role-tier template from pages/admissions/ based on effective permissions.
 */
?>

<style>
    .admissions-page-shell {
        min-height: calc(100vh - 110px);
        padding: 1.5rem;
        background:
            radial-gradient(circle at top left, rgba(255, 193, 7, 0.16), transparent 32rem),
            linear-gradient(135deg, #f7fbf8 0%, #eef7f1 48%, #fff8e1 100%);
    }

    .admissions-page-hero {
        border: 1px solid rgba(25, 135, 84, 0.18);
        border-radius: 1.25rem;
        background: linear-gradient(135deg, #198754 0%, #146c43 72%);
        color: #fff;
        box-shadow: 0 1rem 2.5rem rgba(20, 108, 67, 0.18);
    }

    .admissions-page-hero .text-muted {
        color: rgba(255, 255, 255, 0.78) !important;
    }

    .admissions-page-panel {
        border-radius: 1.25rem;
        background: rgba(255, 255, 255, 0.96);
        box-shadow: 0 0.75rem 2rem rgba(15, 81, 50, 0.08);
    }

    .admissions-page-panel .card {
        border-color: rgba(25, 135, 84, 0.16);
    }

    @media (max-width: 767.98px) {
        .admissions-page-shell {
            padding: 1rem;
        }
    }
</style>

<div class="admissions-page-shell">
    <div class="admissions-page-hero p-4 mb-4">
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-3">
            <div>
                <p class="text-muted text-uppercase fw-semibold small mb-1">Admissions Workflow</p>
                <h4 class="mb-1">Student Admissions</h4>
                <p class="mb-0 text-muted">Review applications, verify documents, and move learners through enrollment.</p>
            </div>
            <span class="badge rounded-pill text-bg-warning px-3 py-2">
                <i class="bi bi-folder-check me-1"></i>Documents Pending
            </span>
        </div>
    </div>

    <div class="admissions-page-panel p-3 p-lg-4">
        <div id="manage-admissions-loading" class="text-center py-5">
            <div class="spinner-border text-success" role="status">
                <span class="visually-hidden">Loading...</span>
            </div>
            <p class="mt-3 mb-0 text-muted">Loading admissions interface...</p>
        </div>

        <div id="manage-admissions-content" style="display: none;"></div>
    </div>
</div>

<script>
    document.addEventListener("DOMContentLoaded", function () {
        var loading = document.getElementById("manage-admissions-loading");
        var container = document.getElementById("manage-admissions-content");

        function showMessage(type, icon, text) {
            var div = document.createElement("div");
            div.className = "alert alert-" + type;
            if (icon) {
                var i = document.createElement("i");
                i.className = icon + " me-2";
                div.appendChild(i);
            }
            div.appendChild(document.createTextNode(text));
            loading.replaceChildren(div);
        }

        function showWarning(icon, text) {
            showMessage("warning", icon, text);
        }

        function showError(text) {
            showMessage("danger", null, text);
        }

        function startAdmissionsPage() {
            if (!AuthContext.isAuthenticated()) {
                window.location.href = window.APP_BASE + "/index.php";
                return;
            }

            var hasAnyPermission = function (permissions) {
                return typeof AuthContext.hasAnyPermission === "function" &&
                    AuthContext.hasAnyPermission(permissions);
            };

            var routeAuthorized = false;

            function proceedWithAdmissions() {
                window.__admissionsRouteAuthorized = routeAuthorized;

                var anyAdmissionsAccess = hasAnyPermission([
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
                    showWarning("bi bi-shield-lock", "You do not have permission to access Admissions.");
                    return;
                }

                var canFullAdmissions = hasAnyPermission([
                    "admission_applications_approve_final",
                    "admission_applications_generate",
                    "admission_applications_assign",
                    "admission_applications_approve",
                    "admission_applications_view_all"
                ]);

                var canManageAdmissions = hasAnyPermission([
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

                var canOperateAdmissions = hasAnyPermission([
                    "admission_applications_create",
                    "admission_applications_submit",
                    "admission_documents_upload",
                    "admission_documents_create",
                    "admission_applications_upload"
                ]);

                var templateFile = "viewer_admissions.php";
                if (canFullAdmissions) {
                    templateFile = "admin_admissions.php";
                } else if (canManageAdmissions) {
                    templateFile = "manager_admissions.php";
                } else if (canOperateAdmissions) {
                    templateFile = "operator_admissions.php";
                }

                var templatePath = window.APP_BASE + "/pages/admissions/" + templateFile;
                var templateUrl = templatePath + "?v=" + Date.now();

                fetch(templateUrl)
                    .then(function (response) {
                        if (!response.ok) {
                            throw new Error("Template not found: " + templatePath);
                        }
                        return response.text();
                    })
                    .then(function (html) {
                        loading.style.display = "none";

                        var parser = new DOMParser();
                        var doc = parser.parseFromString(html, "text/html");
                        var bodyChildren = Array.from(doc.body.childNodes);

                        bodyChildren.forEach(function (node) {
                            container.appendChild(document.adoptNode(node));
                        });
                        container.style.display = "block";

                        if (window.RoleBasedUI && window.RoleBasedUI.applyTo) {
                            window.RoleBasedUI.applyTo(container);
                        }

                        var scripts = container.querySelectorAll("script");
                        var pendingScripts = 0;

                        var initAdmissions = function () {
                            if (window.AdmissionsController && window.AdmissionsController.init) {
                                window.AdmissionsController.init();
                            }
                        };

                        var onScriptLoaded = function () {
                            pendingScripts--;
                            if (pendingScripts <= 0) {
                                initAdmissions();
                            }
                        };

                        if (window.AdmissionsController && window.AdmissionsController.init) {
                            initAdmissions();
                            return;
                        }

                        scripts.forEach(function (script) {
                            if (script.src) {
                                var existingScript = document.querySelector('script[src="' + script.src + '"]');
                                if (existingScript) {
                                    return;
                                }
                                pendingScripts++;
                                var newScript = document.createElement("script");
                                newScript.src = script.src;
                                newScript.async = false;
                                newScript.onload = onScriptLoaded;
                                newScript.onerror = onScriptLoaded;
                                document.body.appendChild(newScript);
                            } else {
                                var inlineScript = document.createElement("script");
                                inlineScript.textContent = script.textContent;
                                document.body.appendChild(inlineScript);
                            }
                        });

                        if (pendingScripts <= 0) {
                            initAdmissions();
                        }
                    })
                    .catch(function (error) {
                        var div = document.createElement("div");
                        div.className = "alert alert-warning";
                        var icon = document.createElement("i");
                        icon.className = "bi bi-exclamation-triangle me-2";
                        div.appendChild(icon);
                        div.appendChild(document.createTextNode("Could not load admissions template for your permissions. "));
                        div.appendChild(document.createElement("br"));
                        var errSpan = document.createElement("small");
                        errSpan.textContent = "Error: " + error.message;
                        div.appendChild(errSpan);
                        loading.replaceChildren(div);
                    });
            }

            if (window.API && window.API.systemconfig && window.API.systemconfig.authorizeRoute) {
                window.API.systemconfig.authorizeRoute("manage_students_admissions")
                    .then(function (authResult) {
                        var authPayload = (authResult && authResult.data) ? authResult.data : authResult;
                        routeAuthorized = Boolean(authPayload && authPayload.authorized);

                        if (!routeAuthorized) {
                            showWarning("bi bi-shield-lock", "You are not allowed to access Admissions.");
                            return;
                        }
                        proceedWithAdmissions();
                    })
                    .catch(function (error) {
                        console.warn("Route authorization check failed for admissions:", error);
                        proceedWithAdmissions();
                    });
            } else {
                proceedWithAdmissions();
            }
        }

        if (typeof AuthContext !== "undefined") {
            startAdmissionsPage();
            return;
        }

        var attempts = 0;
        var waitForAuth = setInterval(function () {
            attempts++;
            if (typeof AuthContext !== "undefined") {
                clearInterval(waitForAuth);
                startAdmissionsPage();
            } else if (attempts > 20) {
                clearInterval(waitForAuth);
                showError("Authentication system not loaded. Please refresh the page.");
            }
        }, 250);
    });
</script>
