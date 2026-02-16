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
            document.getElementById("all-students-loading").innerHTML =
                '<div class="alert alert-danger">User role not found. Please log in again.</div>';
            return;
        }

        const roleTemplateMap = {
            // Admin roles
            "director": "admin_students.php",
            "director_owner": "admin_students.php",
            "school_administrator": "admin_students.php",
            "system_administrator": "admin_students.php",
            "admin": "admin_students.php",

            // Manager roles
            "headteacher": "manager_students.php",
            "deputy_headteacher": "manager_students.php",
            "deputy_head_academic": "manager_students.php",
            "registrar": "manager_students.php",
            "school_admin": "manager_students.php",

            // Accountant roles
            "accountant": "accountant_students.php",
            "school_accountant": "accountant_students.php",
            "bursar": "accountant_students.php",

            // Operator roles
            "secretary": "operator_students.php",
            "class_teacher": "operator_students.php",
            "subject_teacher": "operator_students.php",
            "intern_student_teacher": "operator_students.php",
            "teacher": "operator_students.php",
            "intern": "operator_students.php",

            // Viewer roles
            "parent": "viewer_students.php",
            "student": "viewer_students.php"
        };

        const templateFile = roleTemplateMap[userRoleName] || "viewer_students.php";
        const templatePath = "/Kingsway/pages/students/" + templateFile;

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
