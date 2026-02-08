<?php
/**
 * Manage Student Admissions Page - Role-Based Router
 *
 * Loads a role-specific template from pages/admissions/.
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
    (function () {
        if (typeof AuthContext === "undefined") {
            document.getElementById("manage-admissions-loading").innerHTML =
                '<div class="alert alert-danger">Authentication system not loaded. Please refresh the page.</div>';
            return;
        }

        if (!AuthContext.isAuthenticated()) {
            window.location.href = "/Kingsway/index.php";
            return;
        }

        const user = AuthContext.getUser();
        let userRoleName = null;
        if (user && user.roles && user.roles.length > 0) {
            const firstRole = user.roles[0];
            const roleName = typeof firstRole === "string" ? firstRole : (firstRole.name || firstRole);
            userRoleName = String(roleName).toLowerCase().replace(/\s+/g, "_").replace(/\//g, "_");
        }

        if (!userRoleName) {
            document.getElementById("manage-admissions-loading").innerHTML =
                '<div class="alert alert-danger">User role not found. Please log in again.</div>';
            return;
        }

        const roleTemplateMap = {
            // Admin roles
            "director": "admin_admissions.php",
            "director_owner": "admin_admissions.php",
            "system_administrator": "admin_admissions.php",
            "admin": "admin_admissions.php",
            "school_administrator": "admin_admissions.php",

            // Manager roles
            "headteacher": "manager_admissions.php",
            "deputy_head_discipline": "manager_admissions.php",
            "deputy_headteacher": "manager_admissions.php",

            // Operator roles
            "registrar": "operator_admissions.php",
            "secretary": "operator_admissions.php",

            // Viewer roles
            "accountant": "viewer_admissions.php",
            "bursar": "viewer_admissions.php",
            "finance_officer": "viewer_admissions.php"
        };

        const templateFile = roleTemplateMap[userRoleName] || "viewer_admissions.php";
        const templatePath = "/Kingsway/pages/admissions/" + templateFile;

        fetch(templatePath)
            .then(response => {
                if (!response.ok) {
                    throw new Error("Template not found: " + templatePath);
                }
                return response.text();
            })
            .then(html => {
                document.getElementById("manage-admissions-loading").style.display = "none";
                const container = document.getElementById("manage-admissions-content");
                container.innerHTML = html;
                container.style.display = "block";

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
                document.getElementById("manage-admissions-loading").innerHTML =
                    '<div class="alert alert-warning">' +
                    '<i class="bi bi-exclamation-triangle me-2"></i>' +
                    'Template not found for your role. Please contact system administrator.' +
                    '<br><small>Error: ' + error.message + '</small>' +
                    '</div>';
            });
    })();
</script>
