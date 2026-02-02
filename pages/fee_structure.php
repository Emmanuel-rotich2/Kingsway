<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Fee Structure Management - Kingsway</title>
</head>

<body>
    <!-- Loading state while determining user role -->
    <div id="loading-state" style="padding: 40px; text-align: center;">
        <div class="spinner-border text-primary" role="status">
            <span class="visually-hidden">Loading...</span>
        </div>
        <p class="mt-3">Loading...</p>
    </div>

    <!-- Container where the correct template will be loaded -->
    <div id="fee-structure-container" style="display: none;"></div>

    <script>
        /**
         * Fee Structure Page - JWT-Based Router
         * 
         * STATELESS ARCHITECTURE:
         * - NO PHP sessions (compatible with load balancing across 10 servers)
         * - User role determined from JWT token in localStorage
         * - Template loaded client-side based on role
         * 
         * Role-to-Template Mapping:
         * - director_owner, school_admin, system_administrator → admin template
         * - accountant, bursar → accountant template
         * - headteacher, deputy_headteacher, hod → viewer template
         */

        (function () {
            // Ensure AuthContext is loaded
            if (typeof AuthContext === 'undefined') {
                console.error('AuthContext not found - cannot determine user role');
                document.getElementById('loading-state').innerHTML =
                    '<div class="alert alert-danger">Authentication system not loaded. Please refresh the page.</div>';
                return;
            }

            // Check authentication
            if (!AuthContext.isAuthenticated()) {
                console.warn('User not authenticated - redirecting to login');
                window.location.href = '/Kingsway/index.php';
                return;
            }

            // Get user from JWT token (stored in localStorage)
            const user = AuthContext.getUser();
            if (!user || !user.role_name) {
                console.error('User role not found in JWT token');
                document.getElementById('loading-state').innerHTML =
                    '<div class="alert alert-danger">User role not found. Please log in again.</div>';
                return;
            }

            // Normalize role name to match our mapping
            const userRoleName = user.role_name.toLowerCase().replace(/\s+/g, '_').replace(/\//g, '_');

            console.log('User role from JWT:', user.role_name);
            console.log('Normalized role:', userRoleName);

            // Map roles to template files
            const roleTemplateMap = {
                // Admin roles - Full management interface
                'director_owner': 'admin_fee_structure.php',
                'school_admin': 'admin_fee_structure.php',
                'system_administrator': 'admin_fee_structure.php',

                // Accountant roles - Revenue and payment tracking
                'school_accountant': 'accountant_fee_structure.php',
                'accountant': 'accountant_fee_structure.php',
                'bursar': 'accountant_fee_structure.php',

                // Viewer roles - Read-only oversight
                'headteacher': 'viewer_fee_structure.php',
                'deputy_headteacher': 'viewer_fee_structure.php',
                'hod': 'viewer_fee_structure.php',

                // Limited access roles
                'teacher': 'viewer_fee_structure.php',
                'parent': 'viewer_fee_structure.php',
                'student': 'viewer_fee_structure.php'
            };

            // Determine which template to load
            const templateFile = roleTemplateMap[userRoleName];

            if (!templateFile) {
                console.error('No template found for role:', userRoleName);
                document.getElementById('loading-state').innerHTML =
                    '<div class="alert alert-danger">' +
                    '<i class="bi bi-shield-exclamation me-2"></i>' +
                    'Access denied: Your role (' + user.role_name + ') does not have permission to view fee structures.' +
                    '</div>';
                return;
            }

            // Load the appropriate template
            const templatePath = '/Kingsway/pages/fee_structure/' + templateFile;

            console.log('Loading template:', templatePath);

            // Fetch and inject the template
            fetch(templatePath)
                .then(response => {
                    if (!response.ok) {
                        throw new Error('Template not found: ' + templatePath);
                    }
                    return response.text();
                })
                .then(html => {
                    // Hide loading, show content
                    document.getElementById('loading-state').style.display = 'none';
                    const container = document.getElementById('fee-structure-container');
                    container.innerHTML = html;
                    container.style.display = 'block';

                    // Execute any scripts in the template
                    const scripts = container.querySelectorAll('script');
                    scripts.forEach(script => {
                        const newScript = document.createElement('script');
                        if (script.src) {
                            newScript.src = script.src;
                        } else {
                            newScript.textContent = script.textContent;
                        }
                        document.body.appendChild(newScript);
                    });

                    console.log('Template loaded successfully for role:', user.role_name);
                })
                .catch(error => {
                    console.error('Failed to load template:', error);
                    document.getElementById('loading-state').innerHTML =
                        '<div class="alert alert-warning">' +
                        '<i class="bi bi-exclamation-triangle me-2"></i>' +
                        'Template not found for your role. Please contact system administrator.' +
                        '</div>';
                });
        })();
    </script>
</body>

</html>