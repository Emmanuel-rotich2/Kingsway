<!--components/global/header.php-->
<div class="school-header d-flex align-items-center justify-content-start px-3">


    <div class="header-items d-flex align-items-center justify-content-between flex-grow-1">
        <div class="topbar d-flex align-items-center flex-grow-1 gap-4">
            <button class="btn btn-light" onclick="toggleSidebar()">☰</button>
            <div>Welcome <?php echo ucfirst($user_role); ?></div>
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
                <button class="btn btn-secondary dropdown-toggle" type="button" id="userDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                    <i class="fas fa-user"></i> <?php echo $_SESSION['username'] ?? 'Guest'; ?>
                </button>
                <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="userDropdown">
                    <li><a class="dropdown-item" href="?route=profile">Profile</a></li>
                    <li><a class="dropdown-item text-danger" href="#" onclick="confirmLogout()" ><i class="fas fa-sign-out-alt"></i> Logout</a></li>
                </ul>
            </div>
        </div>
    </div>
</div>
<script src="../../js/index.js" type="text/js"></script>
<script>
function confirmLogout() {
    if (confirm("Are you sure you want to logout?")) {
        window.location.href = 'logout.php';
    }
}
</script>
