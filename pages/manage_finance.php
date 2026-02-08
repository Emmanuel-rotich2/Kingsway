<?php
/**
 * Manage Finance Page - Role-Based Router
 *
 * Loads a role-specific template from pages/finance/.
 */
?>

<!-- Loading state while determining user role -->
<div id="manage-finance-loading" style="padding: 40px; text-align: center;">
    <div class="spinner-border text-success" role="status">
        <span class="visually-hidden">Loading...</span>
    </div>
    <p class="mt-3">Loading finance management interface...</p>
</div>

<!-- Container where the correct template will be loaded -->
<div id="manage-finance-content" style="display: none;"></div>

<script>
    (function () {
        if (typeof AuthContext === "undefined") {
            document.getElementById("manage-finance-loading").innerHTML =
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
            document.getElementById("manage-finance-loading").innerHTML =
                '<div class="alert alert-danger">User role not found. Please log in again.</div>';
            return;
        }

        const roleTemplateMap = {
            // Admin roles
            "director": "admin_finance.php",
            "director_owner": "admin_finance.php",
            "school_administrator": "admin_finance.php",
            "system_administrator": "admin_finance.php",
            "admin": "admin_finance.php",
            "headteacher": "admin_finance.php",

            // Manager roles
            "accountant": "manager_finance.php",
            "bursar": "manager_finance.php",
            "school_accountant": "manager_finance.php",
            "finance_manager": "manager_finance.php",

            // Operator roles
            "class_teacher": "operator_finance.php",
            "subject_teacher": "operator_finance.php",
            "intern_student_teacher": "operator_finance.php",
            "teacher": "operator_finance.php",

            // Viewer roles
            "parent": "viewer_finance.php",
            "student": "viewer_finance.php"
        };

        const templateFile = roleTemplateMap[userRoleName] || "viewer_finance.php";
        const templatePath = "/Kingsway/pages/finance/" + templateFile;

        fetch(templatePath)
            .then(response => {
                if (!response.ok) {
                    throw new Error("Template not found: " + templatePath);
                }
                return response.text();
            })
            .then(html => {
                document.getElementById("manage-finance-loading").style.display = "none";
                const container = document.getElementById("manage-finance-content");
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
                document.getElementById("manage-finance-loading").innerHTML =
                    '<div class="alert alert-warning">' +
                    '<i class="bi bi-exclamation-triangle me-2"></i>' +
                    'Template not found for your role. Please contact system administrator.' +
                    '<br><small>Error: ' + error.message + '</small>' +
                    '</div>';
            });
    })();
</script>
