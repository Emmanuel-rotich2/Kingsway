<?php
/**
 * Manage Students Page - Role-Based Router
 * 
 * Loads a role-specific template from pages/students/.
 */
?>

<!-- Loading state while determining user role -->
<div id="manage-students-loading" style="padding: 40px; text-align: center;">
    <div class="spinner-border text-primary" role="status">
        <span class="visually-hidden">Loading...</span>
    </div>
    <p class="mt-3">Loading student management interface...</p>
</div>

<!-- Container where the correct template will be loaded -->
<div id="manage-students-content" style="display: none;"></div>

<script>
    (function () {
        if (typeof AuthContext === "undefined") {
            document.getElementById("manage-students-loading").innerHTML =
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
            document.getElementById("manage-students-loading").innerHTML =
                '<div class="alert alert-danger">User role not found. Please log in again.</div>';
            return;
        }

        const roleTemplateMap = {
            // Admin roles
            "director": "manage_students_admin.php",
            "director_owner": "manage_students_admin.php",
            "school_administrator": "manage_students_admin.php",
            "system_administrator": "manage_students_admin.php",
            "admin": "manage_students_admin.php",

            // Manager roles
            "headteacher": "manage_students_manager.php",
            "deputy_headteacher": "manage_students_manager.php",
            "deputy_head_academic": "manage_students_manager.php",
            "registrar": "manage_students_manager.php",
            "school_admin": "manage_students_manager.php",

            // Operator roles
            "secretary": "manage_students_operator.php",
            "class_teacher": "manage_students_operator.php",
            "subject_teacher": "manage_students_operator.php",
            "intern_student_teacher": "manage_students_operator.php",
            "teacher": "manage_students_operator.php",

            // Viewer roles
            "accountant": "manage_students_viewer.php",
            "bursar": "manage_students_viewer.php",
            "parent": "manage_students_viewer.php",
            "student": "manage_students_viewer.php"
        };

        const templateFile = roleTemplateMap[userRoleName] || "manage_students_viewer.php";
        const templatePath = "/Kingsway/pages/students/" + templateFile;

        fetch(templatePath)
            .then(response => {
                if (!response.ok) {
                    throw new Error("Template not found: " + templatePath);
                }
                return response.text();
            })
            .then(html => {
                document.getElementById("manage-students-loading").style.display = "none";
                const container = document.getElementById("manage-students-content");
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
                document.getElementById("manage-students-loading").innerHTML =
                    '<div class="alert alert-warning">' +
                    '<i class="bi bi-exclamation-triangle me-2"></i>' +
                    'Template not found for your role. Please contact system administrator.' +
                    '<br><small>Error: ' + error.message + '</small>' +
                    '</div>';
            });
    })();
</script>
