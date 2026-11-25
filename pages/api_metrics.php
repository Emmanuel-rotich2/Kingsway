<?php
/** Kingsway System Administrator: API Metrics dashboard. */
?>
<div class="container-fluid" id="apiMetricsPage">
    <header class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-3">
        <div>
            <h2 class="mb-1">API metrics</h2>
            <p class="text-muted mb-0">Requests, latency and errors across registered endpoints.</p>
        </div>
        <div class="d-flex flex-wrap gap-2" role="group" aria-label="API metrics actions">
            <button type="button" class="btn btn-outline-secondary" id="apiMetricsExportCsvBtn" title="Export to CSV">
                <i class="bi bi-filetype-csv me-1" aria-hidden="true"></i>Export CSV
            </button>
            <button type="button" class="btn btn-outline-secondary" id="apiMetricsPrintBtn" title="Print / save as PDF">
                <i class="bi bi-printer me-1" aria-hidden="true"></i>Print / PDF
            </button>
            <button type="button" class="btn btn-primary" id="apiMetricsRefreshBtn">
                <i class="bi bi-arrow-clockwise me-1" aria-hidden="true"></i>Refresh
            </button>
        </div>
    </header>

    <div class="alert alert-info" id="apiMetricsState" role="status" aria-live="polite">
        Loading API metrics...
    </div>

    <div class="row g-3 mb-3" id="apiMetricsHero">
        <div class="col-xl-3 col-md-6">
            <div class="card h-100">
                <div class="card-body d-flex align-items-center gap-3">
                    <span class="text-secondary fs-4"><i class="bi bi-arrow-repeat" aria-hidden="true"></i></span>
                    <div>
                        <div class="text-muted small">Requests</div>
                        <div class="fs-3 fw-bold" id="apiMetricsTotal">—</div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6">
            <div class="card h-100">
                <div class="card-body d-flex align-items-center gap-3">
                    <span class="text-secondary fs-4"><i class="bi bi-stopwatch" aria-hidden="true"></i></span>
                    <div>
                        <div class="text-muted small">Average latency</div>
                        <div class="fs-3 fw-bold" id="apiMetricsLatency">—</div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6">
            <div class="card h-100">
                <div class="card-body d-flex align-items-center gap-3">
                    <span class="text-secondary fs-4"><i class="bi bi-check-circle" aria-hidden="true"></i></span>
                    <div>
                        <div class="text-muted small">Success rate</div>
                        <div class="fs-3 fw-bold" id="apiMetricsSuccess">—</div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6">
            <div class="card h-100">
                <div class="card-body d-flex align-items-center gap-3">
                    <span class="text-secondary fs-4"><i class="bi bi-exclamation-triangle" aria-hidden="true"></i></span>
                    <div>
                        <div class="text-muted small">Errors</div>
                        <div class="fs-3 fw-bold" id="apiMetricsErrors">—</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3">
        <div class="col-xl-7">
            <div class="card">
                <div class="card-header d-flex flex-wrap gap-2 justify-content-between align-items-center">
                    <h3 class="h6 mb-0">Endpoints by usage</h3>
                    <div class="input-group input-group-sm" style="max-width: 20rem">
                        <span class="input-group-text"><i class="bi bi-search" aria-hidden="true"></i></span>
                        <input class="form-control" id="apiMetricsSearch" type="search" placeholder="Endpoint or path" autocomplete="off" aria-label="Search endpoints">
                    </div>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <caption class="visually-hidden">Registered endpoints by usage</caption>
                        <thead>
                            <tr>
                                <th scope="col">Endpoint</th>
                                <th scope="col" style="width: 34%">Share</th>
                                <th scope="col" class="text-end">Calls</th>
                                <th scope="col" class="text-end">Average (ms)</th>
                                <th scope="col" class="text-end">Errors</th>
                            </tr>
                        </thead>
                        <tbody id="apiMetricsTableBody">
                            <tr id="apiMetricsTableState"><td colspan="5" class="text-center py-5 text-muted">Loading endpoints...</td></tr>
                        </tbody>
                        <template id="apiMetricsRowTemplate">
                            <tr>
                                <td class="text-break"><code data-fill="path"></code></td>
                                <td>
                                    <div class="progress" style="height: 8px" role="progressbar">
                                        <div class="progress-bar" data-fill="share" style="width: 0%"></div>
                                    </div>
                                </td>
                                <td class="text-end" data-fill="calls"></td>
                                <td class="text-end" data-fill="avg"></td>
                                <td class="text-end"><span class="badge" data-fill="errorRate"></span></td>
                            </tr>
                        </template>
                    </table>
                </div>
                <div class="card-footer d-flex flex-wrap gap-3 justify-content-between align-items-center">
                    <span class="text-muted small" id="apiMetricsCount">No metrics loaded</span>
                    <nav aria-label="API metrics pages">
                        <div class="btn-group btn-group-sm">
                            <button type="button" class="btn btn-outline-secondary" id="apiMetricsPreviousPage">
                                <i class="bi bi-chevron-left me-1" aria-hidden="true"></i>Previous
                            </button>
                            <span class="btn btn-outline-secondary disabled" id="apiMetricsPageIndicator">Page 1 of 1</span>
                            <button type="button" class="btn btn-outline-secondary" id="apiMetricsNextPage">
                                Next <i class="bi bi-chevron-right ms-1" aria-hidden="true"></i>
                            </button>
                        </div>
                    </nav>
                </div>
            </div>
        </div>
        <div class="col-xl-5">
            <div class="card">
                <div class="card-header">
                    <h3 class="h6 mb-0">Response distribution</h3>
                </div>
                <div class="card-body">
                    <div class="text-muted small text-center py-5" id="apiMetricsDistribution">Loading...</div>
                    <template id="apiMetricsDistributionRowTemplate"><div class="d-flex justify-content-between align-items-center py-1 border-bottom"><span data-fill="label"></span><strong data-fill="value"></strong></div></template>
                </div>
            </div>
            <div class="card mt-3">
                <div class="card-header">
                    <h3 class="h6 mb-0">Last 24h by hour</h3>
                </div>
                <div class="card-body">
                    <div class="text-muted small text-center py-5" id="apiMetricsTrend">Loading...</div>
                    <template id="apiMetricsTrendItemTemplate"><div class="d-flex flex-column align-items-center gap-1 flex-fill"><div class="bg-primary rounded" data-fill="bar" style="height:4px;width:12px;min-height:4px"></div><span class="tiny text-muted" data-fill="label"></span></div></template>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="<?= htmlspecialchars($appBase) ?>/js/pages/system/api_metrics.js?v=<?= asset_version('js/pages/system/api_metrics.js') ?>"></script>
