<?php
/**
 * Shared dashboard header: the term ribbon plus Overview / Reports /
 * Export and print tabs. Used by the School Administrator and Director
 * dashboards.
 *
 * Usage inside a dashboard template:
 *
 *   <?php require_once __DIR__ . '/_dashboard_tabs.php'; ?>
 *   <?php kw_dashboard_tabs_open('schooladmin'); ?>
 *       ...the dashboard's existing content (the Overview tab)...
 *   <?php kw_dashboard_tabs_close('schooladmin', $reports); ?>
 *
 * Every table that should be exportable carries data-export-title and
 * data-export-module (the permission module, for AuthContext.canExport /
 * canPrint). js/components/dashboard_tabs.js lists them in the Export tab.
 *
 * The Reports and Export panes are dashboards in their own right: they run
 * the governed reports the server authorizes for the signed-in user
 * (GET /reports/catalogue, POST /reports/execute) for one academic year and
 * term, render them in place, and download or print them in place. Nothing
 * navigates away. $reports is accepted for compatibility and no longer used.
 */

if (!function_exists('kw_dashboard_tabs_open')) {
    function kw_dashboard_tabs_open(string $id): void
    {
        $id = preg_replace('/[^a-z0-9_-]/i', '', $id);
        ?>
<section class="kw-dash-head" data-kw-dash-tabs="<?= $id ?>">
    <div class="kw-term" data-kw-term hidden>
        <div class="kw-term-text">
            <span class="kw-term-name" data-kw-term-name></span>
            <span class="kw-term-week" data-kw-term-week></span>
            <span class="kw-term-year" data-kw-term-year></span>
        </div>
        <div class="kw-year" data-kw-year role="img"></div>
    </div>

    <div class="kw-dash-nav">
        <ul class="nav kw-dash-tabs" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active" id="<?= $id ?>-tab-overview" data-bs-toggle="tab" data-bs-target="#<?= $id ?>-overview" type="button" role="tab" aria-controls="<?= $id ?>-overview" aria-selected="true">Overview</button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="<?= $id ?>-tab-reports" data-bs-toggle="tab" data-bs-target="#<?= $id ?>-reports" type="button" role="tab" aria-controls="<?= $id ?>-reports" aria-selected="false">Reports</button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="<?= $id ?>-tab-export" data-bs-toggle="tab" data-bs-target="#<?= $id ?>-export" type="button" role="tab" aria-controls="<?= $id ?>-export" aria-selected="false">Export and print</button>
            </li>
        </ul>
        <span class="kw-dash-updated">Updated <span id="last_refresh">—</span></span>
    </div>
</section>

<div class="tab-content kw-dash-panes">
    <div class="tab-pane fade show active" id="<?= $id ?>-overview" role="tabpanel" aria-labelledby="<?= $id ?>-tab-overview" tabindex="0">
        <?php
    }

    /**
     * @param array<int,array{route:string,label:string,about:string,icon:string}> $reports
     */
    function kw_dashboard_tabs_close(string $id, array $reports): void
    {
        $id = preg_replace('/[^a-z0-9_-]/i', '', $id);
        $base = function_exists('app_base_path') ? app_base_path() : '';
        ?>
    </div>

    <div class="tab-pane fade" id="<?= $id ?>-reports" role="tabpanel" aria-labelledby="<?= $id ?>-tab-reports" tabindex="0">
        <div class="kw-pane">
            <div class="kw-pane-intro">
                <h2 class="kw-pane-title">Reports</h2>
                <p class="kw-pane-lede" data-kw-compare-lede>How the school compares between academic years and terms.</p>
            </div>
            <div class="kw-compare" data-kw-compare aria-live="polite">
                <div class="kw-rcard-state">Loading the comparison…</div>
            </div>
            <h3 class="kw-export-heading kw-reports-heading">All reports <span class="kw-reports-scope" data-kw-report-scope></span></h3>
            <div class="kw-report-grid" data-kw-report-grid aria-live="polite"></div>
            <p class="kw-pane-empty" data-kw-report-empty hidden>Your account has no reports yet. Ask the system administrator to grant report access.</p>
        </div>
    </div>

    <div class="tab-pane fade" id="<?= $id ?>-export" role="tabpanel" aria-labelledby="<?= $id ?>-tab-export" tabindex="0">
        <div class="kw-pane">
            <div class="kw-pane-intro">
                <h2 class="kw-pane-title">Export and print</h2>
                <p class="kw-pane-lede">Download or print straight from here. School reports cover the current academic year and its latest term.</p>
            </div>
            <h3 class="kw-export-heading">School reports</h3>
            <ul class="kw-export-list" data-kw-dataset-list></ul>
            <h3 class="kw-export-heading">Tables on this dashboard</h3>
            <p class="kw-pane-lede">Each table is exported as it appears on the overview, including any search you have applied.</p>
            <ul class="kw-export-list" data-kw-export-list></ul>
            <div class="kw-export-all">
                <button type="button" class="btn btn-outline-secondary" data-kw-print-dashboard>
                    <i class="bi bi-printer me-2" aria-hidden="true"></i>Print this dashboard
                </button>
            </div>
        </div>
    </div>
</div>
<script src="<?= htmlspecialchars($base) ?>/js/components/dashboard_tabs.js?v=<?= function_exists('asset_version') ? asset_version('js/components/dashboard_tabs.js') : time() ?>"></script>
        <?php
    }
}
