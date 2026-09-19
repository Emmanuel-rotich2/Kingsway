<?php
/** Kingsway System Administrator: Role Navigation — hierarchical role preview (dark-accent). */
if (!isset($appBase)) {
    $appBase = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '')), '/');
    if ($appBase === '.') { $appBase = ''; }
}
?>
<div class="container-fluid py-4" id="roleNavigationConfigPage">
    <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
        <div>
            <h2 class="h2 fw-bold mb-1"><i class="bi bi-list-ul text-dark me-2"></i>Role Navigation</h2>
            <p class="text-muted mb-0">Effective menus generated from <code>config/role_sidebars.php</code> — exactly what users receive at login.</p>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <button type="button" class="btn btn-outline-secondary" id="roleNavigationConfigExportCsvBtn" title="Export to CSV">
                <i class="bi bi-filetype-csv me-1"></i> Export CSV
            </button>
            <button type="button" class="btn btn-outline-secondary" id="roleNavigationConfigPrintBtn" title="Print / save as PDF">
                <i class="bi bi-printer me-1"></i> Print / PDF
            </button>
            <button type="button" class="btn btn-outline-secondary" id="roleNavigationConfigRefreshBtn">
                <i class="bi bi-arrow-clockwise me-1"></i> Refresh
            </button>
        </div>
    </div>

    <div class="alert alert-info" id="roleNavigationConfigState" role="status" aria-live="polite">
        Loading effective role navigation...
    </div>

    <div class="card border-0 shadow-sm" style="border-top: 4px solid var(--bs-dark)">
        <div class="card-header bg-light">
            <div class="row g-2 align-items-center">
                <div class="col-lg-6">
                    <div class="input-group input-group-sm">
                        <span class="input-group-text"><i class="bi bi-search"></i></span>
                        <input class="form-control" id="roleNavigationConfigSearch" type="search" maxlength="200" placeholder="Role, menu item or route" autocomplete="off">
                    </div>
                </div>
                <div class="col-12 col-lg text-lg-end mt-2 mt-lg-0">
                    <span class="text-muted small" id="roleNavigationConfigCount">No records loaded</span>
                </div>
            </div>
        </div>

        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th scope="col">Role</th>
                        <th scope="col">Section</th>
                        <th scope="col">Menu Item</th>
                        <th scope="col">Route</th>
                        <th scope="col">Status</th>
                    </tr>
                </thead>
                <tbody id="roleNavigationConfigTableBody">
                    <tr>
                        <td colspan="5" class="text-center py-5 text-muted">Loading role navigation...</td>
                    </tr>
                </tbody>
                <template id="roleNavigationConfigRowTemplate">
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

        <div class="card-footer bg-light d-flex flex-wrap gap-3 justify-content-between align-items-center">
            <span class="text-muted small">Role Navigation</span>
            <nav aria-label="Role navigation pages">
                <div class="btn-group btn-group-sm">
                    <button type="button" class="btn btn-outline-secondary" id="roleNavigationConfigPreviousPage">
                        <i class="bi bi-chevron-left me-1"></i> Previous
                    </button>
                    <span class="btn btn-outline-secondary disabled" id="roleNavigationConfigPageIndicator">Page 1 of 1</span>
                    <button type="button" class="btn btn-outline-secondary" id="roleNavigationConfigNextPage">
                        Next <i class="bi bi-chevron-right ms-1"></i>
                    </button>
                </div>
            </nav>
        </div>
    </div>
</div>

<script src="<?= htmlspecialchars($appBase) ?>/js/pages/system/role_navigation_config.js?v=<?= asset_version('js/pages/system/role_navigation_config.js') ?>"></script>