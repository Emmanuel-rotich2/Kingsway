<?php
/**
 * Fee Structure Page - JWT-Based Router
 * 
 * STATELESS ARCHITECTURE:
 * - NO PHP sessions (compatible with load balancing across 10 servers)
 * - User role determined from JWT token in localStorage via JavaScript
 * - Template loaded client-side based on role from AuthContext
 * 
 * Role-to-Template Mapping:
 * - director_owner, school_admin, system_administrator → admin template
 * - accountant, bursar, school_accountant → accountant template
 * - headteacher, deputy_headteacher, hod → viewer template
 * 
 * This file is included by app_layout.php and renders within the main content area
 */
?>

<!-- Loading state while determining user role -->
<div id="fee-structure-loading" style="padding: 40px; text-align: center;">
    <div class="spinner-border text-primary" role="status">
        <span class="visually-hidden">Loading...</span>
    </div>
    <p class="mt-3">Loading fee structure interface...</p>
</div>

<!-- Container where the correct template will be loaded -->
<div id="fee-structure-content" style="display: none;"></div>

<script>
    /**
     * Fee Structure Page - JWT-Based Client-Side Router
     * 
     * STATELESS ARCHITECTURE:
     * - Reads user role from JWT token in localStorage (AuthContext)
     * - Loads appropriate template client-side
     * - NO server-side sessions
     */

    (function () {
        // Ensure AuthContext is loaded
        if (typeof AuthContext === 'undefined') {
            console.error('AuthContext not found - cannot determine user role');
            document.getElementById('fee-structure-loading').innerHTML =
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

        // Extract role name from JWT (roles is an array of role objects)
        let userRoleName = null;
        if (user && user.roles && user.roles.length > 0) {
            const firstRole = user.roles[0];
            // Handle both { name: 'Role Name' } and 'Role Name' formats
            const roleName = typeof firstRole === 'string' ? firstRole : (firstRole.name || firstRole);
            userRoleName = String(roleName).toLowerCase().replace(/\s+/g, '_').replace(/\//g, '_');
        }

        if (!userRoleName) {
            console.error('User role not found in JWT token. User object:', user);
            document.getElementById('fee-structure-loading').innerHTML =
                '<div class="alert alert-danger">User role not found. Please log in again.</div>';
            return;
        }

        console.log('Fee Structure - User roles from JWT:', user.roles);
        console.log('Fee Structure - Normalized role:', userRoleName);

        // Map roles to template files
        const roleTemplateMap = {
            // Admin roles - Full management interface
            'director': 'admin_fee_structure.php',
            'director_owner': 'admin_fee_structure.php',
            'school_admin': 'admin_fee_structure.php',
            'system_administrator': 'admin_fee_structure.php',

            // Accountant roles - Revenue and payment tracking
            'accountant': 'accountant_fee_structure.php',
            'school_accountant': 'accountant_fee_structure.php',
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
            const roleDisplayName = (user.roles && user.roles[0]) ?
                (typeof user.roles[0] === 'string' ? user.roles[0] : user.roles[0].name) :
                'Unknown';
            document.getElementById('fee-structure-loading').innerHTML =
                '<div class="alert alert-danger">' +
                '<i class="bi bi-shield-exclamation me-2"></i>' +
                'Access denied: Your role (' + roleDisplayName + ') does not have permission to view fee structures.' +
                '</div>';
            return;
        }

        // Load the appropriate template
        const templatePath = '/Kingsway/pages/fee_structure/' + templateFile;

        console.log('Fee Structure - Loading template:', templatePath);

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
                document.getElementById('fee-structure-loading').style.display = 'none';
                const container = document.getElementById('fee-structure-content');
                container.innerHTML = html;
                container.style.display = 'block';

                // Extract and execute scripts from the template
                const tempDiv = document.createElement('div');
                tempDiv.innerHTML = html;
                const scripts = tempDiv.querySelectorAll('script');

                scripts.forEach(script => {
                    const newScript = document.createElement('script');
                    if (script.src) {
                        newScript.src = script.src;
                        newScript.async = false;
                    } else {
                        newScript.textContent = script.textContent;
                    }
                    document.body.appendChild(newScript);
                });

                const roleDisplayName = (user.roles && user.roles[0]) ?
                    (typeof user.roles[0] === 'string' ? user.roles[0] : user.roles[0].name) :
                    'Unknown';
                console.log('Fee Structure - Template loaded successfully for role:', roleDisplayName);
            })
            .catch(error => {
                console.error('Failed to load template:', error);
                document.getElementById('fee-structure-loading').innerHTML =
                    '<div class="alert alert-warning">' +
                    '<i class="bi bi-exclamation-triangle me-2"></i>' +
                    'Template not found for your role. Please contact system administrator.' +
                    '<br><small>Error: ' + error.message + '</small>' +
                    '</div>';
            });
    })();
</script>


<script>
    // Initialize fee structure management when page loads
    document.addEventListener('DOMContentLoaded', function () {
        console.log('Fee Structure Management page loaded');
        // TODO: Implement feeStructureController in js/pages/feeStructure.js
    });
</script>