// Sidebar toggle (desktop & mobile)
function toggleSidebar() {
    const sidebar = document.querySelector('.sidebar');
    const mainFlex = document.querySelector('.main-flex-layout');
    sidebar.classList.toggle('sidebar-collapsed');
    const logo = document.querySelector('.sidebar .logo');

    // Hide all submenus and dropdown arrows when collapsed
    if (sidebar.classList.contains('sidebar-collapsed')) {
        document.querySelectorAll('.sidebar .collapse').forEach(c => c.classList.remove('show'));
        document.querySelectorAll('.sidebar .sidebar-toggle .fa-chevron-down').forEach(icon => icon.style.display = 'none');
        // Hide logo name/text
        document.querySelectorAll('.sidebar .logo .logo-name, .sidebar .logo h5').forEach(el => el.style.display = 'none');
        mainFlex.style.marginLeft = '60px';
        logo.style.width = '60px';
    } else {
        document.querySelectorAll('.sidebar .sidebar-toggle .fa-chevron-down').forEach(icon => icon.style.display = '');
        // Show logo name/text
        document.querySelectorAll('.sidebar .logo .logo-name, .sidebar .logo h5').forEach(el => el.style.display = '');
        mainFlex.style.marginLeft = '250px';
        logo.style.width = '250px';
    }
    // For mobile
    if (window.innerWidth < 992) {
        sidebar.classList.toggle('sidebar-visible-mobile');
        mainFlex.style.marginLeft = '0';
    }
}

// Sidebar submenu accordion (open/close on click)
document.addEventListener('DOMContentLoaded', function () {
    // Make sidebar scrollable if overflow
    const sidebarScroll = document.querySelector('.sidebar .shadow-sm');
    if (sidebarScroll) {
        sidebarScroll.style.overflowY = 'auto';
        sidebarScroll.style.flex = '1 1 0';
    }

    // Hide dropdown arrows and logo name if sidebar is collapsed on load
    if (document.querySelector('.sidebar').classList.contains('sidebar-collapsed')) {
        document.querySelectorAll('.sidebar .sidebar-toggle .fa-chevron-down').forEach(icon => icon.style.display = 'none');
        document.querySelectorAll('.sidebar .logo .logo-name, .sidebar .logo h5').forEach(el => el.style.display = 'none');
    }

    // Accordion for sidebar submenus
    document.querySelectorAll('.sidebar-toggle').forEach(function (toggle) {
        toggle.addEventListener('click', function (e) {
            // Prevent toggling when sidebar is collapsed
            if (document.querySelector('.sidebar').classList.contains('sidebar-collapsed')) {
                e.preventDefault();
                return;
            }
            e.preventDefault();
            const target = document.querySelector(this.getAttribute('href'));
            if (target.classList.contains('show')) {
                target.classList.remove('show');
                this.setAttribute('aria-expanded', 'false');
            } else {
                // Close all open submenus
                document.querySelectorAll('.list-group .collapse.show').forEach(function (open) {
                    open.classList.remove('show');
                });
                document.querySelectorAll('.sidebar-toggle[aria-expanded="true"]').forEach(function (btn) {
                    btn.setAttribute('aria-expanded', 'false');
                });
                // Open the clicked submenu
                target.classList.add('show');
                this.setAttribute('aria-expanded', 'true');
            }
        });
    });
});

// Responsive sidebar behavior on resize
window.addEventListener('resize', function () {
    const sidebar = document.querySelector('.sidebar');
    const mainFlex = document.querySelector('.main-flex-layout');
    if (window.innerWidth < 992) {
        sidebar.classList.add('sidebar-collapsed');
        sidebar.classList.remove('sidebar-visible-mobile');
        mainFlex.style.marginLeft = '0';
        document.querySelectorAll('.sidebar .sidebar-toggle .fa-chevron-down').forEach(icon => icon.style.display = 'none');
        document.querySelectorAll('.sidebar .logo .logo-name, .sidebar .logo h5').forEach(el => el.style.display = 'none');
    } else {
        sidebar.classList.remove('sidebar-visible-mobile');
        sidebar.classList.remove('sidebar-collapsed');
        mainFlex.style.marginLeft = '250px';
        document.querySelectorAll('.sidebar .sidebar-toggle .fa-chevron-down').forEach(icon => icon.style.display = '');
        document.querySelectorAll('.sidebar .logo .logo-name, .sidebar .logo h5').forEach(el => el.style.display = '');
    }
});