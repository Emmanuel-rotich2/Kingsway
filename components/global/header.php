<!--components/global/header.php-->
<!-- 
Stateless header component using JWT tokens (no PHP sessions)
User data is populated from localStorage via JavaScript
Authentication is handled by AuthContext in js/api.js
Compatible with load balancing and horizontal scaling
-->
<div class="school-header d-flex align-items-center justify-content-start px-3">
    <div class="header-items d-flex align-items-center justify-content-between flex-grow-1">
        <div class="topbar d-flex align-items-center flex-grow-1 gap-4">
            <button class="btn btn-light" onclick="toggleSidebar()">☰</button>
            <div>Welcome <span id="header-user-role">User</span></div>
        </div>
        <!-- Actions -->
        <div class="d-flex align-items-center gap-2 ms-3">
            <!-- Sidebar Toggle (mobile only) -->
            <button class="btn btn-light d-lg-none me-2" onclick="toggleSidebar()">
                <i class="fas fa-bars"></i>
            </button>
            <!-- Notifications -->
            <button class="btn btn-light position-relative">
                <i class="fas fa-bell"></i>
                <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger">
                    3
                    <span class="visually-hidden">unread messages</span>
                </span>
            </button>
            <!-- Settings -->
            <button class="btn btn-light">
                <i class="fas fa-cog"></i>
            </button>
            <!-- User Dropdown -->
            <div class="dropdown">
                <button class="btn btn-secondary dropdown-toggle" type="button" id="userDropdown"
                    data-bs-toggle="dropdown" aria-expanded="false">
                    <i class="fas fa-user"></i> <span id="header-username">User</span>
                </button>
                <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="userDropdown">
                    <li><a class="dropdown-item" href="javascript:void(0);" onclick="goToProfile()">Profile</a></li>
                    <li><a class="dropdown-item text-danger" href="javascript:void(0);" onclick="handleLogout()"><i
                                class="fas fa-sign-out-alt"></i> Logout</a></li>
                </ul>
            </div>
        </div>
    </div>
</div>

<script>
    // ============================================================================
    // HEADER COMPONENT - STATELESS JWT-BASED AUTHENTICATION
    // ============================================================================

    /**
     * Initialize header with user info from AuthContext
     * Called when page loads and after login
     */
    function initializeHeader() {
        // Get current user from AuthContext
        const currentUser = AuthContext.getUser();
        const userRoles = AuthContext.getRoles();

        if (currentUser) {
            // Get primary role (first role or main_role)
            const primaryRole = (userRoles && userRoles.length > 0)
                ? userRoles[0]
                : (currentUser.main_role || 'User');

            // Update header with user info
            document.getElementById('header-user-role').textContent = primaryRole.replace(/_/g, ' ').split(' ')
                .map(word => word.charAt(0).toUpperCase() + word.slice(1).toLowerCase())
                .join(' ');

            document.getElementById('header-username').textContent = currentUser.username || currentUser.name || 'User';

            console.log('[Header] Initialized with user:', currentUser.username, 'Role:', primaryRole);
        } else {
            console.log('[Header] No user authenticated');
        }
    }

    /**
     * Handle logout - clear auth context and redirect to login
     */
    function handleLogout() {
        if (confirm('Are you sure you want to logout?')) {
            API.auth.logout().catch(err => {
                console.error('Logout error:', err);
                // Even if API call fails, clear local storage and redirect
                AuthContext.clearUser();
                window.location.href = '/Kingsway/index.php';
            });
        }
    }

    /**
     * Navigate to user profile
     */
    function goToProfile() {
        window.location.href = '/Kingsway/layouts/app_layout.php?route=profile';
    }

    /**
     * Toggle sidebar visibility
     */
    function toggleSidebar() {
        const sidebar = document.getElementById('sidebar');
        const mainContent = document.querySelector('.main-flex-layout');

        if (sidebar && mainContent) {
            sidebar.classList.toggle('collapsed');
            mainContent.classList.toggle('sidebar-collapsed');
        }
    }

    // Initialize header when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initializeHeader);
    } else {
        initializeHeader();
    }

    // Re-initialize header when user logs in (listen for storage changes)
    window.addEventListener('storage', (e) => {
        if (e.key === 'user_data' || e.key === 'token') {
            initializeHeader();
        }
    });

    // Also listen for custom auth change event
    document.addEventListener('authchanged', initializeHeader);
</script>