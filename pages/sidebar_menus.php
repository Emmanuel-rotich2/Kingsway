<?php
/** Kingsway System Administrator: Sidebar Menus — effective role navigation preview (teal-accent). */
if (!isset($appBase)) {
    $appBase = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '')), '/');
    if ($appBase === '.') { $appBase = ''; }
}
?>
<div class="container-fluid py-4" id="sidebarMenusPage">
    <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
        <div>
            <h2 class="h2 fw-bold mb-1"><i class="bi bi-layout-sidebar text-success me-2"></i>Sidebar Menus</h2>
            <p class="text-muted mb-0">Effective menus generated from <code>config/role_sidebars.php</code> — exactly what users receive at login.</p>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <button type="button" class="btn btn-outline-secondary" id="sidebarMenusExportCsvBtn" title="Export to CSV">
                <i class="bi bi-filetype-csv me-1"></i> Export CSV
            </button>
            <button type="button" class="btn btn-outline-secondary" id="sidebarMenusPrintBtn" title="Print / save as PDF">
                <i class="bi bi-printer me-1"></i> Print / PDF
            </button>
            <button type="button" class="btn btn-outline-secondary" id="sidebarMenusRefreshBtn">
                <i class="bi bi-arrow-clockwise me-1"></i> Refresh
            </button>
        </div>
    </div>

    <div class="alert alert-info" id="sidebarMenusState" role="status" aria-live="polite">
        Loading effective role navigation...
    </div>

    <div class="card border-0 shadow-sm" style="border-top: 4px solid #0d9488">
        <div class="card-header bg-white">
            <div class="row g-2 align-items-end">
                <div class="col-lg-5 col-xl-4">
                    <label class="form-label small text-muted mt-2 mb-1" for="sidebarMenusSearch">Search</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-search"></i></span>
                        <input class="form-control" id="sidebarMenusSearch" type="search" maxlength="200" placeholder="Role, menu item or route" autocomplete="off">
                    </div>
                </div>
                <div class="col-12 col-lg mt-2">
                    <span class="text-muted small" id="sidebarMenusCount">No records loaded</span>
                </div>
            </div>
        </div>

        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th scope="col">Role</th>
                        <th scope="col">Section</th>
                        <th scope="col">Menu Item</th>
                        <th scope="col">Route</th>
                        <th scope="col">Status</th>
                    </tr>
                </thead>
                <tbody id="sidebarMenusTableBody">
                    <tr>
                        <td colspan="5" class="text-center py-5 text-muted">Loading role navigation...</td>
                    </tr>
                </tbody>
                <template id="sidebarMenusRowTemplate">
                    <tr>
                        <td class="fw-semibold" data-fill="role_name"></td>
                        <td data-fill="section"></td>
                        <td data-fill="menu_label"></td>
                        <td><code class="text-break" data-fill="route"></code></td>
                        <td><span class="badge" data-fill="status"></span></td>
                    </tr>
                </template>
            </table>
        </div>

        <div class="card-footer bg-white d-flex flex-wrap gap-3 justify-content-between align-items-center">
            <span class="text-muted small">Sidebar Menus</span>
            <nav aria-label="Sidebar menu pages">
                <div class="btn-group btn-group-sm">
                    <button type="button" class="btn btn-outline-secondary" id="sidebarMenusPreviousPage">
                        <i class="bi bi-chevron-left me-1"></i> Previous
                    </button>
                    <span class="btn btn-outline-secondary disabled" id="sidebarMenusPageIndicator">Page 1 of 1</span>
                    <button type="button" class="btn btn-outline-secondary" id="sidebarMenusNextPage">
                        Next <i class="bi bi-chevron-right ms-1"></i>
                    </button>
                </div>
            </nav>
        </div>
    </div>
</div>

<script src="<?= htmlspecialchars($appBase) ?>/js/pages/system/sidebar_menus.js?v=<?= asset_version('js/pages/system/sidebar_menus.js') ?>"></script>