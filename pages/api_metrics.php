<?php
/** Kingsway System Administrator: API Metrics dashboard. */
?>
<div class="container-fluid py-4" id="apiMetricsPage">
    <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
        <div>
            <h2 class="h2 fw-bold mb-1">API Metrics</h2>
            <p class="text-muted mb-0">Requests, latency and errors across registered endpoints.</p>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <button type="button" class="btn btn-outline-secondary" id="apiMetricsExportCsvBtn" title="Export to CSV">
                <i class="bi bi-filetype-csv me-1"></i> Export CSV
            </button>
            <button type="button" class="btn btn-outline-secondary" id="apiMetricsPrintBtn" title="Print / save as PDF">
                <i class="bi bi-printer me-1"></i> Print / PDF
            </button>
            <button type="button" class="btn btn-primary" id="apiMetricsRefreshBtn">
                <i class="bi bi-arrow-clockwise me-1"></i> Refresh
            </button>
        </div>
    </div>

    <div class="alert alert-info" id="apiMetricsState" role="status" aria-live="polite">
        Loading API metrics...
    </div>

    <div class="row g-4 mb-4" id="apiMetricsHero">
        <div class="col-xl-3 col-md-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body d-flex align-items-center gap-3">
                    <div class="rounded-3 bg-primary bg-opacity-10 p-3 text-primary">
                        <i class="bi bi-arrow-repeat fs-3"></i>
                    </div>
                    <div>
                        <div class="text-muted small text-uppercase">Requests</div>
                        <div class="fs-3 fw-bold" id="apiMetricsTotal">—</div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body d-flex align-items-center gap-3">
                    <div class="rounded-3 bg-info bg-opacity-10 p-3 text-info">
                        <i class="bi bi-stopwatch fs-3"></i>
                    </div>
                    <div>
                        <div class="text-muted small text-uppercase">Avg latency</div>
                        <div class="fs-3 fw-bold" id="apiMetricsLatency">—</div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body d-flex align-items-center gap-3">
                    <div class="rounded-3 bg-success bg-opacity-10 p-3 text-success">
                        <i class="bi bi-check-circle fs-3"></i>
                    </div>
                    <div>
                        <div class="text-muted small text-uppercase">Success rate</div>
                        <div class="fs-3 fw-bold" id="apiMetricsSuccess">—</div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body d-flex align-items-center gap-3">
                    <div class="rounded-3 bg-danger bg-opacity-10 p-3 text-danger">
                        <i class="bi bi-exclamation-triangle fs-3"></i>
                    </div>
                    <div>
                        <div class="text-muted small text-uppercase">Errors</div>
                        <div class="fs-3 fw-bold" id="apiMetricsErrors">—</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-xl-7">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white d-flex flex-wrap gap-2 justify-content-between align-items-center">
                    <h3 class="h6 mb-0">Endpoints by usage</h3>
                    <div class="input-group input-group-sm" style="max-width: 280px">
                        <span class="input-group-text"><i class="bi bi-search"></i></span>
                        <input class="form-control" id="apiMetricsSearch" type="search" placeholder="Endpoint or path" autocomplete="off">
                    </div>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th scope="col">Endpoint</th>
                                <th scope="col" style="width: 34%">Share</th>
                                <th scope="col" class="text-end">Calls</th>
                                <th scope="col" class="text-end">Avg (ms)</th>
                                <th scope="col" class="text-end">Errors</th>
                            </tr>
                        </thead>
                        <tbody id="apiMetricsTableBody">
                            <tr><td colspan="5" class="text-center py-5 text-muted">Loading endpoints...</td></tr>
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
                <div class="card-footer bg-white d-flex flex-wrap gap-3 justify-content-between align-items-center">
                    <span class="text-muted small" id="apiMetricsCount">No metrics loaded</span>
                    <nav aria-label="API metrics pages">
                        <div class="btn-group btn-group-sm">
                            <button type="button" class="btn btn-outline-secondary" id="apiMetricsPreviousPage">
                                <i class="bi bi-chevron-left me-1"></i> Previous
                            </button>
                            <span class="btn btn-outline-secondary disabled" id="apiMetricsPageIndicator">Page 1 of 1</span>
                            <button type="button" class="btn btn-outline-secondary" id="apiMetricsNextPage">
                                Next <i class="bi bi-chevron-right ms-1"></i>
                            </button>
                        </div>
                    </nav>
                </div>
            </div>
        </div>
        <div class="col-xl-5">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white">
                    <h3 class="h6 mb-0">Response distribution</h3>
                </div>
                <div class="card-body">
                    <div class="text-muted small text-center py-5" id="apiMetricsDistribution">Loading...</div>
                </div>
            </div>
            <div class="card border-0 shadow-sm mt-4">
                <div class="card-header bg-white">
                    <h3 class="h6 mb-0">Last 24h by hour</h3>
                </div>
                <div class="card-body">
                    <div class="text-muted small text-center py-5" id="apiMetricsTrend">Loading...</div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="<?= htmlspecialchars($appBase) ?>/js/pages/system/api_metrics.js?v=<?= asset_version('js/pages/system/api_metrics.js') ?>"></script>