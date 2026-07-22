<?php /** System-domain dashboard. No School Domain data is rendered here. */ ?>
<div class="container-fluid py-4" id="systemAdminDashboard">
  <div class="d-flex justify-content-between align-items-start mb-4"><div><h2 class="mb-1"><i class="fas fa-shield-alt me-2"></i>System Administration</h2><p class="text-muted mb-0">Identity, security, configuration and platform operations</p></div><button class="btn btn-primary" id="refreshSystemDashboard"><i class="fas fa-sync-alt me-1"></i>Refresh</button></div>
  <div id="systemDashboardState" class="alert alert-info">Loading real system metrics…</div>
  <div class="row g-3 mb-4" id="systemMetricCards" hidden>
    <div class="col-md-4 col-xl"><div class="card border-0 shadow-sm h-100"><div class="card-body"><small class="text-muted">Enabled users</small><h3 id="metricEnabledUsers">—</h3></div></div></div>
    <div class="col-md-4 col-xl"><div class="card border-0 shadow-sm h-100"><div class="card-body"><small class="text-muted">Active sessions</small><h3 id="metricActiveSessions">—</h3></div></div></div>
    <div class="col-md-4 col-xl"><div class="card border-0 shadow-sm h-100"><div class="card-body"><small class="text-muted">Failed logins · 24h</small><h3 id="metricFailedLogins">—</h3></div></div></div>
    <div class="col-md-4 col-xl"><div class="card border-0 shadow-sm h-100"><div class="card-body"><small class="text-muted">Open incidents</small><h3 id="metricIncidents">—</h3></div></div></div>
    <div class="col-md-4 col-xl"><div class="card border-0 shadow-sm h-100"><div class="card-body"><small class="text-muted">Pending jobs</small><h3 id="metricJobs">—</h3></div></div></div>
    <div class="col-md-4 col-xl"><div class="card border-0 shadow-sm h-100"><div class="card-body"><small class="text-muted">Database latency</small><h3><span id="metricDbLatency">—</span> ms</h3></div></div></div>
  </div>
  <div class="card border-0 shadow-sm"><div class="card-header bg-white d-flex justify-content-between"><strong>Recent system activity</strong><small id="systemGeneratedAt" class="text-muted"></small></div><div class="table-responsive"><table class="table table-hover mb-0"><thead><tr><th>Time</th><th>User</th><th>Action</th><th>Resource</th><th>Status</th><th>IP</th></tr></thead><tbody id="systemActivityRows"><tr><td colspan="6" class="text-center text-muted py-4">Loading…</td></tr></tbody></table></div></div>
</div>
<script src="<?= $appBase ?>/js/dashboards/system_administrator_dashboard.js?v=<?= time() ?>"></script>
