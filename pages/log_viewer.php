<?php
/**
 * System Administrator — Central Log Viewer
 * Controller: js/pages/log_viewer.js
 *
 * logcat-style viewer over the central JSON-lines log files
 * (logs/<env>/<category>.log). Colour-coded levels, category/level/search/
 * date filters, expandable request/response detail, and live tailing.
 */
?>
<link rel="stylesheet" href="<?= htmlspecialchars($appBase, ENT_QUOTES, 'UTF-8') ?>/css/pages/log-viewer.css?v=<?= asset_version('css/pages/log-viewer.css') ?>">

<div class="container-fluid" id="logViewerPage">
    <header class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-3">
        <div>
            <h2 class="mb-1">Log viewer</h2>
            <p class="text-muted mb-0">
                Trace who did what, on which route/session, from which machine,
                and at what time. All entries are read from the central
                JSON-lines log files.
            </p>
        </div>
        <div class="d-flex flex-wrap align-items-center gap-2" role="group" aria-label="Log viewer actions">
            <span class="badge text-bg-light border small fw-normal py-2 px-3"
                  id="logViewerEnvironment"
                  title="Active log environment directory">
                <i class="bi bi-folder2-open me-1" aria-hidden="true"></i>Environment: development
            </span>
            <button type="button" class="btn btn-outline-secondary"
                    id="resetLogViewerFiltersBtn">
                <i class="bi bi-arrow-counterclockwise me-1" aria-hidden="true"></i>Reset
            </button>
            <button type="button" class="btn btn-outline-secondary"
                    id="liveLogViewerBtn">
                <i class="bi bi-broadcast me-1" aria-hidden="true"></i>Live
            </button>
            <div class="btn-group" role="group" aria-label="Download audit report">
                <button type="button" class="btn btn-outline-success" id="exportLogCsvBtn"><i class="bi bi-filetype-csv me-1" aria-hidden="true"></i>CSV</button>
                <button type="button" class="btn btn-outline-success" id="exportLogPdfBtn"><i class="bi bi-filetype-pdf me-1" aria-hidden="true"></i>PDF</button>
            </div>
            <button type="button" class="btn btn-primary"
                    id="refreshLogViewerBtn">
                <i class="bi bi-arrow-clockwise me-1" aria-hidden="true"></i>Refresh
            </button>
        </div>
    </header>

    <div class="row g-3 mb-3" id="logViewerSummary"></div>

    <div class="card mb-3">
        <div class="card-header d-flex justify-content-between align-items-center">
            <div><strong>Active pages</strong><div class="small text-muted">Authenticated browsers seen during the last 90 seconds</div></div>
            <span class="badge text-bg-success" id="logViewerActiveCount">0 active</span>
        </div>
        <div class="table-responsive">
            <table class="table table-sm align-middle mb-0">
                <caption class="visually-hidden">Active authenticated pages</caption>
                <thead><tr><th scope="col">User</th><th scope="col">Page / route</th><th scope="col">Last seen</th><th scope="col">IP</th></tr></thead>
                <tbody id="logViewerPresenceBody"><tr><td colspan="4" class="text-center text-muted py-3">No active authenticated pages.</td></tr></tbody>
            </table>
        </div>
    </div>

    <div class="row g-3 mb-3">
        <div class="col-xl-6">
            <div class="card h-100">
                <div class="card-header"><strong>Most active users</strong><div class="small text-muted">Requests, actions and failures in the selected period</div></div>
                <div class="table-responsive"><table class="table table-sm align-middle mb-0">
                    <caption class="visually-hidden">Most active users</caption>
                    <thead><tr><th scope="col">User</th><th scope="col">Events</th><th scope="col">Requests</th><th scope="col">Actions</th><th scope="col">Failures</th></tr></thead>
                    <tbody id="logViewerUsersBody"></tbody>
                </table></div>
            </div>
        </div>
        <div class="col-xl-6">
            <div class="card h-100">
                <div class="card-header"><strong>Most accessed routes</strong><div class="small text-muted">Completed HTTP responses only; duplicate inbound traces are excluded</div></div>
                <div class="table-responsive"><table class="table table-sm align-middle mb-0">
                    <caption class="visually-hidden">Most accessed routes</caption>
                    <thead><tr><th scope="col">Route</th><th scope="col">Requests</th><th scope="col">Failures</th><th scope="col">Average</th></tr></thead>
                    <tbody id="logViewerRoutesBody"></tbody>
                </table></div>
            </div>
        </div>
        <div class="col-xl-6">
            <div class="card h-100">
                <div class="card-header"><strong>Audit actions</strong><div class="small text-muted">Business and security changes recorded by action type</div></div>
                <div class="card-body" id="logViewerActions"></div>
            </div>
        </div>
        <div class="col-xl-6">
            <div class="card h-100">
                <div class="card-header"><strong>Journal integrity</strong><div class="small text-muted">HMAC verification detects modified structured entries</div></div>
                <div class="card-body" id="logViewerIntegrity"></div>
            </div>
        </div>
    </div>

    <div class="alert alert-info d-none" id="logViewerState" role="status" aria-live="polite">
        No logs loaded.
    </div>

    <div class="log-workbench">
      <aside class="log-files-pane" aria-label="Journal files">
        <div class="log-pane-title"><span><i class="bi bi-folder2-open me-2" aria-hidden="true"></i>Journal files</span><button class="btn btn-sm btn-link text-light p-0" id="refreshLogFilesBtn" title="Refresh files" aria-label="Refresh journal files"><i class="bi bi-arrow-clockwise" aria-hidden="true"></i></button></div>
        <div class="log-files-summary" id="logFilesSummary">Loading governed journals…</div>
        <div id="logFilesList" class="log-files-list"></div>
      </aside>
      <section class="log-console-pane" aria-label="Journal console">
        <div class="log-console-toolbar">
          <div><strong><i class="bi bi-terminal me-2" aria-hidden="true"></i>Live journal</strong><span class="log-live-dot" id="logLiveDot"></span></div>
          <div class="d-flex gap-2"><button class="btn btn-sm btn-outline-light" id="clearTerminalBtn"><i class="bi bi-eraser" aria-hidden="true"></i> Clear screen</button><button class="btn btn-sm btn-outline-light" id="scrollTerminalBtn"><i class="bi bi-arrow-down" aria-hidden="true"></i> Latest</button></div>
        </div>
        <div class="log-query-bar">
          <div class="log-query-input"><i class="bi bi-search" aria-hidden="true"></i><input id="logViewerSearch" type="search" maxlength="200" placeholder="Search message, route, user, request ID…" autocomplete="off" aria-label="Search journal entries"></div>
          <div class="log-query-input"><span class="query-label include">Include</span><input id="logViewerIncludeRegex" type="text" maxlength="120" placeholder="regex" aria-label="Include regex"></div>
          <div class="log-query-input"><span class="query-label exclude">Exclude</span><input id="logViewerExcludeRegex" type="text" maxlength="120" placeholder="regex" aria-label="Exclude regex"></div>
        </div>
        <div class="log-level-strip" id="logLevelLegend" role="group" aria-label="Journal level">
          <button type="button" data-level="" class="active">All</button><button type="button" data-level="debug">Debug</button><button type="button" data-level="info">Info</button><button type="button" data-level="warning">Warn</button><button type="button" data-level="error">Error+</button><button type="button" data-level="audit">Audit</button>
          <span class="ms-auto"><label><input type="checkbox" id="logViewerArchivesFilter"> archives</label></span>
        </div>
        <div class="log-advanced-filters">
          <select id="logViewerCategoryFilter" aria-label="Journal stream"><option value="">All streams</option></select>
          <input id="logViewerUserFilter" type="number" min="1" placeholder="User ID" aria-label="User ID">
          <input id="logViewerActionFilter" type="search" maxlength="80" placeholder="Action / event" aria-label="Action or event">
          <input id="logViewerDateFrom" type="date" title="From date" aria-label="From date"><input id="logViewerDateTo" type="date" title="To date" aria-label="To date">
          <select id="logViewerPageSize" aria-label="Lines per page"><option value="50">50 lines</option><option value="100" selected>100 lines</option><option value="200">200 lines</option></select>
          <select id="logViewerLevelFilter" class="d-none" aria-label="Level"><option value=""></option></select>
        </div>
        <div class="log-terminal" id="logViewerTableBody" role="log" aria-live="polite"><div class="terminal-empty">Waiting for journal data…</div></div>
        <div class="log-console-footer"><span id="logViewerCount">No entries</span><div><button type="button" id="logViewerPreviousPage" aria-label="Previous page"><i class="bi bi-chevron-left" aria-hidden="true"></i></button><span id="logViewerPageIndicator">Page 1 of 1</span><button type="button" id="logViewerNextPage" aria-label="Next page"><i class="bi bi-chevron-right" aria-hidden="true"></i></button></div></div>
      </section>
    </div>

</div>

<script src="<?= htmlspecialchars($appBase) ?>/js/pages/log_viewer.js?v=<?= asset_version('js/pages/log_viewer.js') ?>"></script>
