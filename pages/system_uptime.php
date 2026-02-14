<?php /** System Uptime - Track system uptime */ ?>
<div>
    <div class="row mb-4"><div class="col-12"><div class="d-flex justify-content-between align-items-center">
        <div><h4 class="mb-1"><i class="fas fa-clock me-2"></i>System Uptime</h4><p class="text-muted mb-0">Track system uptime</p></div>
        <div><span id="lastUpdated" class="text-muted me-3"></span><button class="btn btn-outline-success" onclick="window._monCtrl.exportCSV()"><i class="fas fa-download me-1"></i> Export</button></div>
    </div></div></div>
    <div class="row mb-4">
        <div class="col-md-3 mb-3"><div class="card shadow-sm border-0"><div class="card-body text-center py-4"><h6 class="text-muted mb-2">Status</h6><span id="statusIndicator" class="badge fs-5 bg-secondary">Checking...</span></div></div></div>
        <div class="col-md-3 mb-3"><div class="card shadow-sm border-0"><div class="card-body"><div class="d-flex align-items-center"><div class="rounded-circle bg-primary bg-opacity-10 p-3 me-3"><i class="fas fa-clock text-primary fa-lg"></i></div><div><h6 class="text-muted mb-1">Uptime</h6><h4 class="mb-0" id="statUptime">--</h4></div></div></div></div></div>
        <div class="col-md-3 mb-3"><div class="card shadow-sm border-0"><div class="card-body"><div class="d-flex align-items-center"><div class="rounded-circle bg-success bg-opacity-10 p-3 me-3"><i class="fas fa-chart-line text-success fa-lg"></i></div><div><h6 class="text-muted mb-1">Metric 1</h6><h4 class="mb-0" id="statValue1">0</h4></div></div></div></div></div>
        <div class="col-md-3 mb-3"><div class="card shadow-sm border-0"><div class="card-body"><div class="d-flex align-items-center"><div class="rounded-circle bg-info bg-opacity-10 p-3 me-3"><i class="fas fa-signal text-info fa-lg"></i></div><div><h6 class="text-muted mb-1">Metric 2</h6><h4 class="mb-0" id="statValue2">0</h4></div></div></div></div></div>
    </div>
    <div class="card shadow-sm"><div class="card-header bg-white"><h6 class="mb-0"><i class="fas fa-clock me-2"></i>System Uptime Details</h6></div><div class="card-body"><div id="monitorContent" class="text-center text-muted py-4">Loading monitoring data...</div></div></div>
</div>
<script src="/Kingsway/js/pages/system/monitoring_controller.js?v=<?php echo time(); ?>"></script>
<script>window._monCtrl = new MonitoringController({ title: 'System Uptime', apiEndpoint: '/system/uptime', refreshInterval: 30000 });</script>