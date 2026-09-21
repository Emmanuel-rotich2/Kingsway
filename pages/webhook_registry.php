<?php
/** Kingsway System Administrator: Webhook Registry (webhook-card variant). */
if (!isset($appBase)) {
    $appBase = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '')), '/');
    if ($appBase === '.') { $appBase = ''; }
}
?>
<div class="container-fluid py-4" id="webhookRegistryPage">
    <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
        <div>
            <h2 class="h2 fw-bold mb-1"><i class="bi bi-broadcast text-danger me-2"></i>Webhook Registry</h2>
            <p class="text-muted mb-0">Registered outbound webhook endpoints and the events they receive.</p>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <button type="button" class="btn btn-outline-secondary" id="webhookRegistryExportCsvBtn" title="Export to CSV">
                <i class="bi bi-filetype-csv me-1"></i> Export CSV
            </button>
            <button type="button" class="btn btn-outline-secondary" id="webhookRegistryPrintBtn" title="Print / save as PDF">
                <i class="bi bi-printer me-1"></i> Print / PDF
            </button>
            <button type="button" class="btn btn-primary" id="webhookRegistryCreateBtn">
                <i class="bi bi-plus-lg me-1"></i> New webhook
            </button>
            <button type="button" class="btn btn-outline-secondary" id="webhookRegistryRefreshBtn">
                <i class="bi bi-arrow-clockwise me-1"></i> Refresh
            </button>
        </div>
    </div>

    <div class="alert alert-info" id="webhookRegistryState" role="status" aria-live="polite">
        Loading webhooks...
    </div>

    <div class="card border-0 shadow-sm" style="border-top: 4px solid var(--bs-danger)">
        <div class="card-header bg-white">
            <div class="row g-2 align-items-end">
                <div class="col-lg-5 col-xl-4">
                    <label class="form-label small text-muted mt-2 mb-1" for="webhookRegistrySearch">Search</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-search"></i></span>
                        <input class="form-control" id="webhookRegistrySearch" type="search" maxlength="200" placeholder="Name, URL or event" autocomplete="off">
                    </div>
                </div>
                <div class="col-12 col-lg mt-2">
                    <span class="text-muted small" id="webhookRegistryCount">No webhooks loaded</span>
                </div>
            </div>
        </div>

        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th scope="col">#</th>
                        <th scope="col">Name</th>
                        <th scope="col">URL</th>
                        <th scope="col">Events</th>
                        <th scope="col">Secret</th>
                        <th scope="col">Status</th>
                        <th scope="col">Actions</th>
                    </tr>
                </thead>
                <tbody id="webhookRegistryTableBody">
                    <tr>
                        <td colspan="7" class="text-center py-5 text-muted">Loading webhooks...</td>
                    </tr>
                </tbody>
                <template id="webhookRegistryRowTemplate">
                    <tr>
                        <td class="text-nowrap" data-fill="id"></td>
                        <td><strong data-fill="name"></strong></td>
                        <td class="text-break"><code data-fill="url"></code></td>
                        <td class="text-wrap small" data-fill="events"></td>
                        <td><span class="badge bg-light text-dark border" data-fill="secret"></span></td>
                        <td><span class="badge" data-fill="is_active"></span></td>
                        <td>
                            <div class="btn-group btn-group-sm">
                                <button type="button" class="btn btn-outline-primary" data-action="edit" title="Edit webhook">
                                    <i class="bi bi-pencil"></i>
                                </button>
                                <button type="button" class="btn btn-outline-danger" data-action="delete" title="Delete webhook">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                </template>
            </table>
        </div>

        <div class="card-footer bg-white d-flex flex-wrap gap-3 justify-content-between align-items-center">
            <span class="text-muted small">Webhook Registry</span>
            <nav aria-label="Webhook pages">
                <div class="btn-group btn-group-sm">
                    <button type="button" class="btn btn-outline-secondary" id="webhookRegistryPreviousPage">
                        <i class="bi bi-chevron-left me-1"></i> Previous
                    </button>
                    <span class="btn btn-outline-secondary disabled" id="webhookRegistryPageIndicator">Page 1 of 1</span>
                    <button type="button" class="btn btn-outline-secondary" id="webhookRegistryNextPage">
                        Next <i class="bi bi-chevron-right ms-1"></i>
                    </button>
                </div>
            </nav>
        </div>
    </div>
</div>

<div class="modal fade" id="webhookRegistryModal" tabindex="-1" aria-labelledby="webhookRegistryModalTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-scrollable modal-lg modal-dialog-centered">
        <div class="modal-content">
            <form id="webhookRegistryForm" novalidate>
                <input type="hidden" id="webhookRegistryEditId">
                <div class="modal-header">
                    <h5 class="modal-title" id="webhookRegistryModalTitle">New Webhook</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label" for="webhookRegistryName">Name</label>
                            <input type="text" class="form-control" id="webhookRegistryName" required maxlength="150" placeholder="e.g. Payment events to partner">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="webhookRegistryStatus">Status</label>
                            <select class="form-select" id="webhookRegistryStatus">
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label" for="webhookRegistryUrl">URL</label>
                            <input type="url" class="form-control font-monospace" id="webhookRegistryUrl" required maxlength="500" placeholder="https://partner.example.com/hooks/payments">
                        </div>
                        <div class="col-12">
                            <label class="form-label" for="webhookRegistrySecret">Secret</label>
                            <input type="text" class="form-control font-monospace" id="webhookRegistrySecret" maxlength="300" placeholder="Leave empty to keep the current secret on edit">
                            <div class="form-text">Used to sign webhook deliveries. Never share this value.</div>
                        </div>
                        <div class="col-12">
                            <label class="form-label" for="webhookRegistryEvents">Events</label>
                            <input type="text" class="form-control" id="webhookRegistryEvents" required maxlength="500" placeholder="payment.collected, student.enrolled">
                            <div class="form-text">Comma-separated event names. Use <code>*</code> for all events.</div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary" id="webhookRegistrySaveBtn">Save webhook</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="<?= htmlspecialchars($appBase) ?>/js/pages/system/webhook_registry.js?v=<?= asset_version('js/pages/system/webhook_registry.js') ?>"></script>
