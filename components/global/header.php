<!-- components/global/header.php -->
<header class="app-header" id="app-header">
    <div class="app-header-left">
        <button
            class="app-icon-button"
            id="sidebar-toggle-button"
            type="button"
            aria-controls="sidebar"
            aria-expanded="true"
            aria-label="Toggle navigation"
            title="Toggle navigation"
        >
            <i class="bi bi-list" aria-hidden="true"></i>
        </button>

        <div class="app-header-copy">
            <span class="app-header-kicker">Welcome back</span>
            <strong id="header-user-role">User</strong>
        </div>
    </div>

    <div class="app-header-actions">
        <button
            class="app-icon-button"
            id="header-search-button"
            type="button"
            aria-label="Search"
            title="Search"
        >
            <i class="bi bi-search" aria-hidden="true"></i>
        </button>

        <div class="dropdown">
            <button
                class="app-icon-button position-relative"
                id="notificationsDropdown"
                type="button"
                data-bs-toggle="dropdown"
                aria-expanded="false"
                aria-label="Notifications"
                title="Notifications"
            >
                <i class="bi bi-bell" aria-hidden="true"></i>
                <span
                    class="app-notification-badge"
                    id="header-notification-count"
                >3</span>
            </button>

            <div
                class="dropdown-menu dropdown-menu-end app-header-menu"
                aria-labelledby="notificationsDropdown"
            >
                <div class="app-menu-heading">
                    <strong>Notifications</strong>
                    <button
                        type="button"
                        class="btn btn-sm btn-link text-decoration-none"
                        id="mark-all-notifications-read"
                    >
                        Mark all read
                    </button>
                </div>

                <div id="header-notification-list">
                    <div class="app-notification-item">
                        <span class="app-notification-icon bg-success-subtle text-success">
                            <i class="bi bi-person-check"></i>
                        </span>
                        <div>
                            <strong>Admissions update</strong>
                            <small>2 applications are pending review.</small>
                        </div>
                    </div>

                    <div class="app-notification-item">
                        <span class="app-notification-icon bg-warning-subtle text-warning-emphasis">
                            <i class="bi bi-calendar-event"></i>
                        </span>
                        <div>
                            <strong>Academic schedule</strong>
                            <small>Review this week’s timetable.</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <button
            class="app-icon-button"
            id="header-theme-button"
            type="button"
            aria-label="Toggle theme"
            title="Toggle theme"
        >
            <i class="bi bi-moon-stars" aria-hidden="true"></i>
        </button>

        <div class="dropdown">
            <button
                class="app-user-button dropdown-toggle"
                type="button"
                id="userDropdown"
                data-bs-toggle="dropdown"
                aria-expanded="false"
            >
                <span class="app-user-avatar" id="header-user-avatar">U</span>
                <span class="app-user-meta">
                    <strong id="header-username">User</strong>
                    <small id="header-role-short">Account</small>
                </span>
            </button>

            <ul
                class="dropdown-menu dropdown-menu-end app-user-menu"
                aria-labelledby="userDropdown"
            >
                <li class="app-user-menu-summary">
                    <span
                        class="app-user-avatar app-user-avatar-lg"
                        id="menu-user-avatar"
                    >U</span>
                    <div>
                        <strong id="menu-username">User</strong>
                        <small id="menu-user-email">Signed in</small>
                    </div>
                </li>
                <li><hr class="dropdown-divider"></li>
                <li>
                    <button
                        class="dropdown-item"
                        type="button"
                        onclick="goToProfile()"
                    >
                        <i class="bi bi-person me-2"></i>
                        My profile
                    </button>
                </li>
                <li>
                    <button
                        class="dropdown-item"
                        type="button"
                        id="account-settings-button"
                    >
                        <i class="bi bi-gear me-2"></i>
                        Account settings
                    </button>
                </li>
                <li><hr class="dropdown-divider"></li>
                <li>
                    <button
                        class="dropdown-item text-danger"
                        type="button"
                        onclick="showLogoutModal()"
                    >
                        <i class="bi bi-box-arrow-right me-2"></i>
                        Sign out
                    </button>
                </li>
            </ul>
        </div>
    </div>
</header>

<div
    class="offcanvas offcanvas-top app-search-panel"
    tabindex="-1"
    id="globalSearchPanel"
    aria-labelledby="globalSearchPanelLabel"
>
    <div class="offcanvas-header">
        <h5 id="globalSearchPanelLabel">Search Kingsway</h5>
        <button
            type="button"
            class="btn-close"
            data-bs-dismiss="offcanvas"
            aria-label="Close"
        ></button>
    </div>
    <div class="offcanvas-body">
        <div class="input-group input-group-lg">
            <span class="input-group-text">
                <i class="bi bi-search"></i>
            </span>
            <input
                id="global-search-input"
                type="search"
                class="form-control"
                placeholder="Search pages and modules..."
                autocomplete="off"
            >
        </div>

        <div id="global-search-results" class="app-search-results">
            <p class="text-muted mb-0">
                Start typing to search available navigation pages.
            </p>
        </div>
    </div>
</div>

<div
    class="modal fade"
    id="logoutModal"
    tabindex="-1"
    aria-labelledby="logoutModalLabel"
    aria-hidden="true"
>
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content app-logout-modal">
            <div class="modal-body text-center p-4">
                <span class="app-logout-icon">
                    <i class="bi bi-box-arrow-right"></i>
                </span>

                <h5 id="logoutModalLabel" class="mt-3 mb-2">
                    Sign out of Kingsway?
                </h5>

                <p class="text-muted small">
                    You will need to sign in again to access your dashboard.
                </p>

                <div class="d-grid gap-2 mt-4">
                    <button
                        type="button"
                        class="btn btn-danger"
                        id="confirmLogoutBtn"
                        onclick="executeLogout()"
                    >
                        <span id="logoutBtnText">
                            <i class="bi bi-box-arrow-right me-1"></i>
                            Sign out
                        </span>
                        <span id="logoutSpinner" class="d-none">
                            <span
                                class="spinner-border spinner-border-sm me-1"
                                aria-hidden="true"
                            ></span>
                            Signing out...
                        </span>
                    </button>

                    <button
                        type="button"
                        class="btn btn-light"
                        data-bs-dismiss="modal"
                    >
                        Cancel
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>
