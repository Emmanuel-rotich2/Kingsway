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
                    <li><a class="dropdown-item text-danger" href="javascript:void(0);" onclick="showLogoutModal()"><i
                                class="fas fa-sign-out-alt"></i> Logout</a></li>
                </ul>
            </div>
        </div>
    </div>
</div>

<!-- Logout Confirmation Modal -->
<div class="modal fade" id="logoutModal" tabindex="-1" aria-labelledby="logoutModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 1rem; overflow: hidden;">
            <!-- Header -->
            <div class="modal-header border-0 pb-0 pt-4 px-4">
                <div class="w-100 text-center">
                    <div class="mx-auto mb-3" style="width: 70px; height: 70px; background: linear-gradient(135deg, #dc3545 0%, #c82333 100%); border-radius: 50%; display: flex; align-items: center; justify-content: center; box-shadow: 0 4px 15px rgba(220,53,69,0.3);">
                        <i class="fas fa-sign-out-alt text-white" style="font-size: 1.8rem;"></i>
                    </div>
                    <h5 class="modal-title fw-bold text-dark" id="logoutModalLabel">Logout Confirmation</h5>
                </div>
                <button type="button" class="btn-close position-absolute top-0 end-0 m-3" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            
            <!-- Body -->
            <div class="modal-body text-center px-4 py-3">
                <p class="text-muted mb-1">Are you sure you want to sign out?</p>
                <p class="text-muted small mb-0">You'll need to log in again to access your account.</p>
            </div>
            
            <!-- Footer -->
            <div class="modal-footer border-0 justify-content-center gap-2 pb-4 px-4">
                <button type="button" class="btn btn-light px-4 py-2" data-bs-dismiss="modal" style="border-radius: 0.5rem; min-width: 100px;">
                    <i class="fas fa-times me-1"></i>Cancel
                </button>
                <button type="button" class="btn btn-danger px-4 py-2" id="confirmLogoutBtn" onclick="executeLogout()" style="border-radius: 0.5rem; min-width: 100px;">
                    <span id="logoutBtnText"><i class="fas fa-sign-out-alt me-1"></i>Logout</span>
                    <span id="logoutSpinner" class="d-none">
                        <span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span>
                        Logging out...
                    </span>
                </button>
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
     * Show the logout confirmation modal
     */
    function showLogoutModal() {
        const logoutModal = new bootstrap.Modal(document.getElementById('logoutModal'));
        logoutModal.show();
    }

    /**
     * Execute logout - clear auth context and redirect to login
     */
    function executeLogout() {
        const logoutBtnText = document.getElementById('logoutBtnText');
        const logoutSpinner = document.getElementById('logoutSpinner');
        const confirmLogoutBtn = document.getElementById('confirmLogoutBtn');
        
        // Show loading state
        if (logoutBtnText) logoutBtnText.classList.add('d-none');
        if (logoutSpinner) logoutSpinner.classList.remove('d-none');
        if (confirmLogoutBtn) confirmLogoutBtn.disabled = true;
        
        API.auth.logout().catch(err => {
            console.error('Logout error:', err);
            // Even if API call fails, clear local storage and redirect
            AuthContext.clearUser();
            window.location.href = '/Kingsway/index.php';
        });
    }

    /**
     * Handle logout - legacy function that now shows the modal
     */
    function handleLogout() {
        showLogoutModal();
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