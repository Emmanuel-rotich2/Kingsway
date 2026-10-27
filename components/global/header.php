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
            <span class="app-header-kicker" id="header-greeting">Welcome back</span>
            <strong id="header-user-role">User</strong>
        </div>
    </div>

    <div class="app-header-actions">
        <button
            class="app-icon-button position-relative"
            id="header-ai-assistant-button"
            type="button"
            aria-label="Open staff assistant"
            title="Staff assistant"
            data-bs-toggle="offcanvas"
            data-bs-target="#globalAiAssistantPanel"
            aria-controls="globalAiAssistantPanel"
        >
            <i class="bi bi-stars" aria-hidden="true"></i>
        </button>
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
                >0</span>
            </button>

            <div
                class="dropdown-menu dropdown-menu-end app-header-menu"
                aria-labelledby="notificationsDropdown"
            >
                <div class="app-menu-heading">
                    <strong>Notifications</strong>
                    <span class="d-flex gap-1">
                        <button
                            type="button"
                            class="btn btn-sm btn-link text-decoration-none d-none"
                            id="notification-broadcast-toggle"
                        >
                            Broadcast
                        </button>
                        <button
                            type="button"
                            class="btn btn-sm btn-link text-decoration-none"
                            id="mark-all-notifications-read"
                        >
                            Mark all read
                        </button>
                    </span>
                </div>

                <div id="notification-broadcast-wrap" class="d-none">
                    <div class="app-notification-broadcast">
                        <form id="notification-broadcast-form" class="px-2 pb-2 pt-1">
                            <div class="mb-2">
                                <input
                                    type="text"
                                    id="nb-title"
                                    class="form-control form-control-sm"
                                    placeholder="Title"
                                    maxlength="200"
                                    required
                                >
                            </div>
                            <div class="mb-2">
                                <textarea
                                    id="nb-message"
                                    class="form-control form-control-sm"
                                    rows="2"
                                    placeholder="Message"
                                    maxlength="2000"
                                    required
                                ></textarea>
                            </div>
                            <div class="row g-2 mb-2">
                                <div class="col-6">
                                    <select id="nb-type" class="form-select form-select-sm">
                                        <option value="maintenance">Maintenance</option>
                                        <option value="announcement">Announcement</option>
                                        <option value="event">Event</option>
                                        <option value="general">General</option>
                                    </select>
                                </div>
                                <div class="col-6">
                                    <select id="nb-audience" class="form-select form-select-sm">
                                        <option value="all_staff">All staff</option>
                                        <option value="all_users">All users</option>
                                    </select>
                                </div>
                            </div>
                            <div class="d-grid">
                                <button type="submit" class="btn btn-sm btn-primary" id="nb-submit">
                                    Send broadcast
                                </button>
</div>
                    <div class="mt-2 text-end">
                        <span class="small text-muted d-none d-md-inline text-nowrap">Shift + Enter for a new line</span>
                    </div>
                </form>
                    </div>
                </div>

                <div id="header-notification-list">
                    <div class="app-notification-empty">
                        Loading notifications…
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
                        id="account-settings-button"
                    >
                        <i class="bi bi-person-gear me-2"></i>
                        My account
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
    class="offcanvas offcanvas-end ai-assistant-panel"
    tabindex="-1"
    id="globalAiAssistantPanel"
    data-persist-open="true"
    aria-labelledby="globalAiAssistantPanelLabel"
>
    <div class="assistant-shell d-flex h-100 w-100">
        <!-- Sidebar: sessions rail (full height, side-by-side with main) -->
        <aside class="assistant-sidebar flex-shrink-0 p-3">
            <div class="assistant-sidebar-brand d-flex align-items-center gap-2 mb-3">
                <img
                    src="<?= htmlspecialchars(($appBase ?? '') . '/uploads/school_assets/official_school_logo.png', ENT_QUOTES, 'UTF-8') ?>"
                    alt="Kingsway Preparatory School logo"
                    width="28"
                    height="28"
                    class="assistant-sidebar-logo"
                    onerror="this.onerror=null;this.src='<?= htmlspecialchars(($appBase ?? '') . '/images/official_school_logo.png', ENT_QUOTES, 'UTF-8') ?>';"
                >
                <span class="assistant-sidebar-brand-copy">
                    <span class="assistant-sidebar-school-name">Kingsway Preparatory School</span>
                    <small>Staff assistant</small>
                </span>
            </div>
            <button type="button" class="btn btn-success w-100 fw-medium d-flex align-items-center justify-content-center gap-2 mb-4 assistant-new-chat" id="global-ai-assistant-new-chat">
                <i class="bi bi-plus-lg"></i><span>New chat</span>
            </button>
            <div class="d-flex justify-content-between align-items-center mb-2 px-1">
                <span class="text-muted fw-semibold assistant-sidebar-label">Chat History</span>
            </div>
            <div class="overflow-auto flex-grow-1 pe-1" id="global-ai-assistant-history" aria-live="polite">
                <p class="small text-muted px-1 mb-0">Start a new chat to see sessions here.</p>
            </div>
            <div class="border-top pt-3 mt-auto d-flex justify-content-between px-3 py-2 gap-2">
                <button type="button" class="assistant-sidebar-action" id="global-ai-assistant-help" title="How to use the assistant" aria-label="How to use the assistant">
                    <i class="bi bi-question-circle"></i>
                </button>
                <button type="button" class="assistant-sidebar-action" id="global-ai-assistant-settings" title="Assistant settings" aria-label="Assistant settings">
                    <i class="bi bi-gear"></i>
                </button>
            </div>
        </aside>
        <!-- Main chat area: header spans full width, conversation below -->
        <div class="assistant-main d-flex flex-column flex-grow-1 h-100 min-w-0">
            <header class="assistant-header d-flex align-items-center gap-3 px-3 px-md-4 py-3 flex-shrink-0">
                <span class="assistant-avatar" aria-hidden="true"><i class="bi bi-robot"></i></span>
                <div class="min-w-0">
                    <h5 class="mb-0 fw-semibold text-white" id="globalAiAssistantPanelLabel">Staff Assistant</h5>
                    <div class="assistant-header-meta d-flex align-items-center gap-2">
                        <span class="assistant-online-badge"><i class="bi bi-circle-fill"></i>Online</span>
                    </div>
                </div>
                <button type="button" class="btn-close btn-close-white ms-auto" data-bs-dismiss="offcanvas" aria-label="Close"></button>
            </header>
            <div class="assistant-conversation flex-grow-1 overflow-y-auto p-3 p-md-4" id="global-ai-assistant-conversation" style="display:flex;flex-direction:column;">
                <!-- Thread: the single scrollable message container (never destroyed). Greeting is rendered here when empty. -->
                <div id="global-ai-assistant-thread" class="assistant-thread flex-grow-1 overflow-y-auto p-3" aria-live="polite"></div>
            </div>
<!-- Composer -->
            <footer class="assistant-footer border-top p-3 flex-shrink-0" id="global-ai-assistant-footer">
                <form id="global-ai-assistant-query" class="assistant-composer d-flex flex-column gap-2" autocomplete="off">
                    <div class="chat-input-box d-flex align-items-end gap-2 p-2 w-100">
                        <input type="file" id="global-ai-assistant-attachment" class="d-none" tabindex="-1" aria-hidden="true">
                        <button type="button" class="btn btn-icon-only btn-light text-muted" id="global-ai-assistant-attach" title="Attach a file" aria-label="Attach a file" style="background:transparent;border:0;width:34px;height:34px;display:inline-flex;align-items:center;justify-content:center;border-radius:50%;padding:0;">
                            <i class="bi bi-paperclip"></i>
                        </button>
                        <label class="visually-hidden" for="global-ai-assistant-question">Ask the assistant</label>
                        <textarea
                            class="form-control assistant-input flex-grow-1"
                            id="global-ai-assistant-question"
                            rows="1"
                            maxlength="500"
                            placeholder="Ask the assistant…"
                            aria-label="Ask the assistant"
                            style="border:0;outline:none;resize:none;box-shadow:none;"
                        ></textarea>
                        <button type="button" class="btn btn-icon-only btn-light text-muted" id="global-ai-assistant-voice" title="Dictate your question" aria-label="Dictate your question" style="background:transparent;border:0;width:34px;height:34px;display:inline-flex;align-items:center;justify-content:center;border-radius:50%;padding:0;">
                            <i class="bi bi-mic"></i>
                        </button>
                        <button type="submit" class="btn btn-success" id="global-ai-assistant-send" title="Send question" aria-label="Send question" style="width:34px;height:34px;display:inline-flex;align-items:center;justify-content:center;border-radius:50%;padding:0;">
                            <i class="bi bi-send-fill"></i>
                        </button>
                    </div>
                    <div class="mt-2 text-end">
                        <span class="small text-muted d-none d-md-inline text-nowrap">Shift + Enter for a new line</span>
                    </div>
                </form>
        </div>
    </div>
</div>

<!-- Assistant Settings Modal -->
<div
    class="modal fade"
    id="assistant-settings-modal"
    tabindex="-1"
    aria-labelledby="assistantSettingsModalLabel"
    aria-hidden="true"
>
    <div class="modal-dialog modal-dialog-scrollable modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="assistantSettingsModalLabel">
                    <i class="bi bi-gear me-2"></i>Assistant Settings
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="assistant-settings-form">
                    <div class="mb-3">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" id="pref-save-history" checked>
                            <label class="form-check-label" for="pref-save-history">Save chat history</label>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="pref-response-style" class="form-label">Response style</label>
                        <select class="form-select" id="pref-response-style">
                            <option value="concise">Concise</option>
                            <option value="balanced" selected>Balanced</option>
                            <option value="detailed">Detailed</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" id="pref-auto-charts">
                            <label class="form-check-label" for="pref-auto-charts">Auto-generate charts</label>
                        </div>
                        <div class="form-text">Automatically create charts when data is available.</div>
                    </div>
                    <div class="mb-3">
                        <label for="pref-language" class="form-label">Language</label>
                        <select class="form-select" id="pref-language">
                            <option value="en" selected>English</option>
                            <option value="sw">Kiswahili</option>
                        </select>
                    </div>
                    <div class="d-grid gap-2 mt-3">
                        <button type="button" class="btn btn-outline-danger" id="assistant-clear-history">
                            <i class="bi bi-trash me-1"></i> Clear all chat history
                        </button>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-primary" id="assistant-save-settings">Save settings</button>
            </div>
        </div>
    </div>
</div>

<!-- Assistant Info Modal -->
<div
    class="modal fade"
    id="assistant-info-modal"
    tabindex="-1"
    aria-labelledby="assistantInfoModalLabel"
    aria-hidden="true"
>
    <div class="modal-dialog modal-dialog-scrollable modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="assistantInfoModalLabel">
                    <i class="bi bi-info-circle me-2"></i>About Kingsway Staff Assistant
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <h6>What this assistant can do</h6>
                    <ul class="small">
                        <li>Summarize your workspace and pending items</li>
                        <li>Run authorized governed reports and explain the results</li>
                        <li>Draft announcements, messages, and communications for human approval</li>
                        <li>Answer questions about school operations, policies, and schedules</li>
                        <li>Generate charts and visualizations from authorized data</li>
                    </ul>
                </div>
                <div class="mb-3">
                    <h6>What this assistant cannot do</h6>
                    <ul class="small">
                        <li>Access data outside your role permissions</li>
                        <li>Modify, approve, or send anything — drafts only</li>
                        <li>Reveal system architecture, database schemas, or technical internals</li>
                        <li>Diagnose learners, make welfare decisions, or access confidential records</li>
                    </ul>
                </div>
                <div class="mb-3">
                    <h6>How to use</h6>
                    <ul class="small">
                        <li>Ask naturally: "Summarize today's attendance for my class"</li>
                        <li>Use slash commands: <code>/summary</code>, <code>/bullet</code>, <code>/table</code>, <code>/step by step</code></li>
                        <li>Follow-up questions are remembered within each chat session</li>
                        <li>Click "New chat" to start a fresh session; previous sessions are saved in the sidebar</li>
                    </ul>
                </div>
                <div class="mb-3">
                    <h6>Privacy & Data</h6>
                    <ul class="small">
                        <li>Conversations are stored per staff member, encrypted at rest</li>
                        <li>History is never used to train models or build personal profiles</li>
                        <li>Use "Clear all chat history" in Settings to erase your data (DPA right to erasure)</li>
                        <li>All AI events are logged operationally with hashed prompts — no prompt text in logs</li>
                    </ul>
                </div>
                <div class="alert alert-info small mb-0">
                    <i class="bi bi-shield-check me-1"></i>
                    This assistant runs on the Kingsway AI platform (Python primary, PHP fallback). Your questions are governed by school RBAC — you only see what your role permits.
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
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
    <div class="modal-dialog modal-dialog-scrollable modal-dialog-centered modal-sm">
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
