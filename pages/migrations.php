<?php
/** Kingsway System Administrator: Migrations. */
?>
<div class="container-fluid py-4" id="migrationsPage">
    <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
        <div>
            <h2 class="h3 mb-1">Migrations</h2>
            <p class="text-muted mb-0">Inspect migration history and record controlled migrations.</p>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <button type="button" class="btn btn-outline-secondary" id="migrationsExportCsvBtn" title="Export to CSV">
                <i class="bi bi-filetype-csv me-1"></i> Export CSV
            </button>
            <button type="button" class="btn btn-outline-secondary" id="migrationsPrintBtn" title="Print / save as PDF">
                <i class="bi bi-printer me-1"></i> Print / PDF
            </button>
            <button type="button" class="btn btn-outline-secondary" id="migrationsRefreshBtn">
                <i class="bi bi-arrow-clockwise me-1"></i> Refresh
            </button>
            <button type="button" class="btn btn-primary" id="migrationsCreateBtn">
                <i class="bi bi-plus-lg me-1"></i> Run migration
            </button>
        </div>
    </div>

    <div class="alert alert-info" id="migrationsState" role="status" aria-live="polite">
        Loading migrations...
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white">
            <div class="row g-2 align-items-end">
                <div class="col-lg-5 col-xl-4">
                    <label class="form-label small text-muted mt-2 mb-1" for="migrationsSearch">Search</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-search"></i></span>
                        <input class="form-control" id="migrationsSearch" type="search" maxlength="200" placeholder="Migration name or status" autocomplete="off">
                    </div>
                </div>
                <div class="col-12 col-lg mt-2">
                    <span class="text-muted small" id="migrationsCount">No migrations loaded</span>
                </div>
            </div>
        </div>

        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th scope="col">Migration</th>
                        <th scope="col">Status</th>
                        <th scope="col">Ran At</th>
                    </tr>
                </thead>
                <tbody id="migrationsTableBody">
                    <tr>
                        <td colspan="3" class="text-center py-5 text-muted">Loading migrations...</td>
                    </tr>
                </tbody>
                <template id="migrationsRowTemplate">
                    <tr>
                        <td><code class="text-break" data-fill="name"></code></td>
                        <td><span class="badge" data-fill="status"></span></td>
                        <td class="text-nowrap text-muted" data-fill="ran_at"></td>
                    </tr>
                </template>
            </table>
        </div>

        <div class="card-footer bg-white d-flex flex-wrap gap-3 justify-content-between align-items-center">
            <span class="text-muted small">Migrations</span>
            <nav aria-label="Migrations pages">
                <div class="btn-group btn-group-sm">
                    <button type="button" class="btn btn-outline-secondary" id="migrationsPreviousPage">
                        <i class="bi bi-chevron-left me-1"></i> Previous
                    </button>
                    <span class="btn btn-outline-secondary disabled" id="migrationsPageIndicator">Page 1 of 1</span>
                    <button type="button" class="btn btn-outline-secondary" id="migrationsNextPage">
                        Next <i class="bi bi-chevron-right ms-1"></i>
                    </button>
                </div>
            </nav>
        </div>
    </div>
</div>

<div class="modal fade" id="migrationsModal" tabindex="-1" aria-labelledby="migrationsModalTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-scrollable modal-dialog-centered">
        <div class="modal-content">
            <form id="migrationsForm" novalidate>
                <input type="hidden" id="migrationsEditId">
                <div class="modal-header">
                    <h5 class="modal-title" id="migrationsModalTitle">Run Migration</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label" for="migrationsMigration">Migration <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="migrationsMigration" placeholder="e.g. 2024_01_01_000000_add_users_table" maxlength="255" required>
                        <div class="form-text">Enter the migration filename as it appears in the migration history.</div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary" id="migrationsSaveBtn">Run migration</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="<?= htmlspecialchars($appBase) ?>/js/pages/system/migrations.js?v=<?= asset_version('js/pages/system/migrations.js') ?>"></script>
