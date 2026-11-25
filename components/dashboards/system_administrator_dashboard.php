<?php
/** System Administrator dashboard — System Domain infrastructure only. */
$rootId = 'systemAdministratorDashboardPage';
?>
<section
  class="container-fluid py-4 role-dashboard"
  id="systemAdministratorDashboardPage"
  data-dashboard-layout="system-operations-grid"
  aria-labelledby="systemAdministratorDashboardTitle"
  aria-busy="true"
>
  <header class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
    <div>
      <h2 class="h3 mb-1" id="systemAdministratorDashboardTitle">System administrator dashboard</h2>
      <p class="text-muted mb-0">
        A working view of accounts, sessions, security signals and service health.
      </p>
    </div>
    <nav class="d-flex flex-wrap gap-2" aria-label="System administration quick actions">
      <a href="<?= htmlspecialchars($appBase, ENT_QUOTES, 'UTF-8') ?>/home.php?route=active_sessions" class="btn btn-sm btn-outline-secondary">
        <i class="bi bi-person-check me-1" aria-hidden="true"></i>Active sessions
      </a>
      <a href="<?= htmlspecialchars($appBase, ENT_QUOTES, 'UTF-8') ?>/home.php?route=activity_audit_logs" class="btn btn-sm btn-outline-secondary">
        <i class="bi bi-journal-check me-1" aria-hidden="true"></i>Audit activity
      </a>
      <a href="<?= htmlspecialchars($appBase, ENT_QUOTES, 'UTF-8') ?>/home.php?route=api_metrics" class="btn btn-sm btn-outline-secondary">
        <i class="bi bi-activity me-1" aria-hidden="true"></i>API metrics
      </a>
      <a href="<?= htmlspecialchars($appBase, ENT_QUOTES, 'UTF-8') ?>/home.php?route=error_logs" class="btn btn-sm btn-outline-secondary">
        <i class="bi bi-terminal me-1" aria-hidden="true"></i>Error logs
      </a>
    </nav>
  </header>

  <div
    class="alert alert-info"
    id="systemAdministratorDashboardState"
    role="status"
    aria-live="polite"
  >
    Loading system metrics...
  </div>

  <div class="row g-3 mb-4" id="systemAdministratorMetricCards">
    <div class="col-sm-6 col-xl-3">
      <div class="card h-100">
        <div class="card-body">
          <div class="text-muted small">Enabled users</div>
          <div class="fs-3 fw-bold" id="metricEnabledUsers">—</div>
          <small class="text-muted" id="metricEnabledUsersNote"></small>
        </div>
      </div>
    </div>
    <div class="col-sm-6 col-xl-3">
      <div class="card h-100">
        <div class="card-body">
          <div class="text-muted small">Active sessions</div>
          <div class="fs-3 fw-bold" id="metricActiveSessions">—</div>
          <small class="text-muted" id="metricActiveSessionsNote"></small>
        </div>
      </div>
    </div>
    <div class="col-sm-6 col-xl-3">
      <div class="card h-100">
        <div class="card-body">
          <div class="text-muted small">Failed logins · 24h</div>
          <div class="fs-3 fw-bold" id="metricFailedLogins">—</div>
          <small class="text-muted" id="metricFailedLoginsNote"></small>
        </div>
      </div>
    </div>
    <div class="col-sm-6 col-xl-3">
      <div class="card h-100">
        <div class="card-body">
          <div class="text-muted small">Open incidents</div>
          <div class="fs-3 fw-bold" id="metricOpenIncidents">—</div>
          <small class="text-muted" id="metricOpenIncidentsNote"></small>
        </div>
      </div>
    </div>
  </div>

  <div class="row g-3">
    <div class="col-xl-7">
      <div class="card h-100">
        <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
          <h3 class="h6 mb-0">Recent authentication activity</h3>
          <div class="d-flex flex-wrap align-items-center gap-2" role="group" aria-label="Recent activity actions">
            <small class="text-muted" id="systemAdministratorActivityCount"></small>
            <button
              type="button"
              class="btn btn-sm btn-outline-secondary no-print"
              data-kw-sa-export
              data-kw-sa-export-target="#systemAdministratorActivityTable"
              data-kw-sa-export-name="system_activity"
            >
              <i class="bi bi-filetype-csv me-1" aria-hidden="true"></i>Export CSV
            </button>
            <button type="button" class="btn btn-sm btn-outline-secondary no-print" data-kw-sa-print>
              <i class="bi bi-printer me-1" aria-hidden="true"></i>Print / PDF
            </button>
          </div>
        </div>
        <div class="table-responsive">
          <table class="table table-hover align-middle mb-0" id="systemAdministratorActivityTable">
            <caption class="visually-hidden">Recent authentication activity, last 24 hours</caption>
            <thead>
              <tr>
                <th scope="col">Time</th>
                <th scope="col">Actor</th>
                <th scope="col">Action</th>
                <th scope="col">Status</th>
                <th scope="col">IP address</th>
              </tr>
            </thead>
            <tbody id="systemAdministratorActivityBody">
              <tr>
                <td colspan="5" class="text-center py-4">Loading...</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <div class="col-xl-5">
      <div class="d-flex flex-column gap-3 h-100">
        <div class="row g-3">
          <div class="col-6">
            <div class="card h-100 border-start border-success border-3">
              <div class="card-body">
                <div class="small text-muted">Pending jobs</div>
                <div class="fs-3 fw-bold" id="metricPendingJobs">—</div>
                <small class="text-muted" id="metricPendingJobsNote"></small>
              </div>
            </div>
          </div>
          <div class="col-6">
            <div class="card h-100 border-start border-warning border-3">
              <div class="card-body">
                <div class="small text-muted">API errors · 24h</div>
                <div class="fs-3 fw-bold" id="metricApiErrors">—</div>
                <small class="text-muted" id="metricApiErrorsNote"></small>
              </div>
            </div>
          </div>
        </div>
        <div class="card">
          <div class="card-header">
            <h3 class="h6 mb-0">Technical checks</h3>
          </div>
          <div
            class="list-group list-group-flush"
            id="systemAdministratorTechnicalChecks"
          >
            <div class="list-group-item text-muted">Loading...</div>
          </div>
        </div>

        <div class="card flex-grow-1">
          <div class="card-header">
            <h3 class="h6 mb-0">Current system attention</h3>
          </div>
          <div class="list-group list-group-flush" id="systemAdministratorAlerts">
            <div class="list-group-item text-muted">Loading...</div>
          </div>
        </div>
      </div>
    </div>
  </div>
</section>

<script src="<?= htmlspecialchars($appBase, ENT_QUOTES, 'UTF-8') ?>/js/dashboards/system_administrator_dashboard.js?v=<?= asset_version('js/dashboards/system_administrator_dashboard.js') ?>"></script>
