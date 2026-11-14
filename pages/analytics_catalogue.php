<?php
/**
 * Reports & Analytics workspace: the governed report catalogue.
 *
 * The server filters the catalogue to the reports the signed-in user's roles
 * may view (GET /reports/catalogue). This page used to be a redirect stub
 * whose only logic was an inline script; soft navigation never runs inline
 * fragment scripts, so it showed its spinner forever when opened from the
 * sidebar, and every role outside a hard-coded list landed on access denied.
 */
?>
<style>
    .analytics-hero {
        background: linear-gradient(135deg, #123c2d, #1f7a55);
        color: #fff;
        border-radius: 14px;
        padding: 1.5rem;
        box-shadow: 0 8px 24px rgba(18, 60, 45, .18);
    }
    .analytics-toolbar, .analytics-card, .analytics-viewer {
        background: #fff;
        border: 1px solid #e2ebe6;
        border-radius: 12px;
        box-shadow: 0 2px 10px rgba(18, 60, 45, .06);
    }
    .analytics-card {
        height: 100%;
        transition: transform .15s ease, box-shadow .15s ease;
    }
    .analytics-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 7px 20px rgba(18, 60, 45, .12);
    }
    .analytics-domain-label {
        color: #1f6a4d;
        font-size: .72rem;
        font-weight: 700;
        letter-spacing: .04em;
        text-transform: uppercase;
    }
    .analytics-meta { color: #63746b; font-size: .8rem; }
    .analytics-purpose {
        color: #3e4f47;
        font-size: .88rem;
        min-height: 3.8rem;
    }
    .analytics-empty {
        border: 1px dashed #bdcec5;
        border-radius: 12px;
        color: #66766e;
        padding: 3rem 1rem;
        text-align: center;
    }
    .analytics-metric {
        background: #f4faf7;
        border-left: 3px solid #2e8b62;
        border-radius: 8px;
        padding: .75rem;
    }
    .analytics-summary-card {
        background: #f7faf8;
        border: 1px solid #e1ebe5;
        border-radius: 9px;
        padding: .75rem;
    }
    .analytics-result-table { font-size: .84rem; }
    .analytics-result-table thead th {
        background: #174f39;
        color: #fff;
        white-space: nowrap;
    }
    .analytics-sensitivity-restricted { color: #9c1c1c; }
    .analytics-sensitivity-confidential { color: #8a5a00; }
</style>

<div class="container-fluid px-0" id="analyticsCataloguePage">
    <section class="analytics-hero mb-3" aria-labelledby="analyticsPageTitle">
        <div class="d-flex flex-column flex-lg-row justify-content-between gap-3 align-items-lg-center">
            <div>
                <div class="small text-uppercase opacity-75 fw-semibold">Reports &amp; Analytics</div>
                <h1 class="h3 mb-1" id="analyticsPageTitle">Governed report catalogue</h1>
                <p class="mb-0 opacity-75">Decision-focused reports with documented metrics, scope, freshness and export controls.</p>
            </div>
            <div class="text-lg-end">
                <div class="h3 mb-0" id="analyticsReportCount">—</div>
                <div class="small opacity-75">reports available to your roles</div>
            </div>
        </div>
    </section>

    <section class="analytics-toolbar p-3 mb-3" aria-label="Report catalogue filters">
        <div class="row g-2 align-items-end">
            <div class="col-lg-6">
                <label for="analyticsSearch" class="form-label small fw-semibold">Search reports</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="bi bi-search" aria-hidden="true"></i></span>
                    <input type="search" class="form-control" id="analyticsSearch" placeholder="Search by title, purpose or report code">
                </div>
            </div>
            <div class="col-lg-4">
                <label for="analyticsDomain" class="form-label small fw-semibold">Analytics domain</label>
                <select class="form-select" id="analyticsDomain">
                    <option value="">All available domains</option>
                </select>
            </div>
            <div class="col-lg-2">
                <button class="btn btn-outline-success w-100" type="button" id="analyticsRefresh">
                    <i class="bi bi-arrow-clockwise me-1" aria-hidden="true"></i>Refresh
                </button>
            </div>
        </div>
    </section>

    <div id="analyticsCatalogueStatus" class="visually-hidden" role="status" aria-live="polite"></div>
    <div class="row g-3" id="analyticsCatalogueGrid"></div>
</div>

<div class="modal fade" id="analyticsReportModal" tabindex="-1" aria-labelledby="analyticsReportModalTitle" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <div>
                    <div class="analytics-domain-label" id="analyticsReportDomain">Report</div>
                    <h2 class="modal-title fs-5" id="analyticsReportModalTitle">Report</h2>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-light border mb-3">
                    <div class="fw-semibold mb-1">Decision purpose</div>
                    <div id="analyticsDecisionPurpose"></div>
                    <div class="analytics-meta mt-2" id="analyticsDefinitionMeta"></div>
                </div>

                <div id="analyticsMetricPanel" class="row g-2 mb-3"></div>

                <form id="analyticsFilterForm" class="analytics-viewer p-3 mb-3" novalidate>
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <h3 class="h6 mb-0">Report filters</h3>
                        <span class="analytics-meta">Your authorized scope is enforced by the server.</span>
                    </div>
                    <div class="row g-2" id="analyticsFilterFields"></div>
                </form>

                <div id="analyticsResultRegion" hidden>
                    <div class="d-flex flex-column flex-md-row justify-content-between gap-2 align-items-md-center mb-2">
                        <div>
                            <h3 class="h6 mb-0">Report results</h3>
                            <div class="analytics-meta" id="analyticsRunMeta"></div>
                        </div>
                        <div class="btn-group btn-group-sm" id="analyticsExportButtons" role="group" aria-label="Export report"></div>
                    </div>
                    <div class="row g-2 mb-3" id="analyticsSummary"></div>
                    <div class="alert alert-warning py-2" id="analyticsWarnings" hidden></div>
                    <div class="table-responsive border rounded">
                        <table class="table table-hover table-striped mb-0 analytics-result-table" id="analyticsResultTable">
                            <thead></thead>
                            <tbody></tbody>
                        </table>
                    </div>
                    <div class="analytics-empty" id="analyticsNoRows" hidden>No records matched the selected filters.</div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php asset_script($appBase, 'js/pages/analytics_catalogue.js'); ?>
