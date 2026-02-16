<?php /** Failed Login Attempts - Monitor failed login attempts */ ?>
<div>
    <div class="row mb-4"><div class="col-12"><div class="d-flex justify-content-between align-items-center">
        <div><h4 class="mb-1"><i class="fas fa-user-times me-2"></i>Failed Login Attempts</h4><p class="text-muted mb-0">Monitor failed login attempts</p></div>
        <button class="btn btn-outline-success" onclick="window._logCtrl.exportCSV()"><i class="fas fa-file-csv me-1"></i> Export</button>
    </div></div></div>
    <div class="row mb-4">
        <div class="col-md-3 mb-3"><div class="card shadow-sm border-0"><div class="card-body"><div class="d-flex align-items-center"><div class="rounded-circle bg-primary bg-opacity-10 p-3 me-3"><i class="fas fa-list text-primary fa-lg"></i></div><div><h6 class="text-muted mb-1">Total Records</h6><h4 class="mb-0" id="statTotal">0</h4></div></div></div></div></div>
        <div class="col-md-3 mb-3"><div class="card shadow-sm border-0"><div class="card-body"><div class="d-flex align-items-center"><div class="rounded-circle bg-danger bg-opacity-10 p-3 me-3"><i class="fas fa-times-circle text-danger fa-lg"></i></div><div><h6 class="text-muted mb-1">Errors</h6><h4 class="mb-0" id="statErrors">0</h4></div></div></div></div></div>
        <div class="col-md-3 mb-3"><div class="card shadow-sm border-0"><div class="card-body"><div class="d-flex align-items-center"><div class="rounded-circle bg-warning bg-opacity-10 p-3 me-3"><i class="fas fa-exclamation-triangle text-warning fa-lg"></i></div><div><h6 class="text-muted mb-1">Warnings</h6><h4 class="mb-0" id="statWarnings">0</h4></div></div></div></div></div>
        <div class="col-md-3 mb-3"><div class="card shadow-sm border-0"><div class="card-body"><div class="d-flex align-items-center"><div class="rounded-circle bg-info bg-opacity-10 p-3 me-3"><i class="fas fa-calendar-day text-info fa-lg"></i></div><div><h6 class="text-muted mb-1">Today</h6><h4 class="mb-0" id="statToday">0</h4></div></div></div></div></div>
    </div>
    <div class="card shadow-sm mb-4"><div class="card-body"><div class="row g-3">
        <div class="col-md-3"><input type="text" class="form-control" id="searchInput" placeholder="Search logs..."></div>
        <div class="col-md-2"><select class="form-select" id="severityFilter"><option value="">All Levels</option><option value="error">Error</option><option value="warning">Warning</option><option value="info">Info</option><option value="critical">Critical</option></select></div>
        <div class="col-md-2"><input type="date" class="form-control" id="dateFrom"></div>
        <div class="col-md-2"><input type="date" class="form-control" id="dateTo"></div>
        <div class="col-md-3"><button class="btn btn-outline-secondary w-100" onclick="window._logCtrl.loadData()"><i class="fas fa-sync-alt me-1"></i> Refresh</button></div>
    </div></div></div>
    <div class="card shadow-sm"><div class="card-header bg-white"><h6 class="mb-0"><i class="fas fa-table me-2"></i>Log Records</h6></div>
    <div class="card-body p-0"><div class="table-responsive"><table class="table table-hover mb-0" id="dataTable"><thead class="table-light"><tr><th>#</th><th>Timestamp</th><th>Level</th><th>Message</th><th>Source</th><th>Actions</th></tr></thead><tbody><tr><td colspan="6" class="text-center text-muted py-4">Loading...</td></tr></tbody></table></div></div></div>
</div>
<div class="modal fade" id="detailModal" tabindex="-1"><div class="modal-dialog modal-lg"><div class="modal-content"><div class="modal-header"><h5 class="modal-title"><i class="fas fa-info-circle me-2"></i>Detail</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body" id="detailContent">--</div></div></div></div>
<script src="/Kingsway/js/pages/system/log_viewer_controller.js?v=<?php echo time(); ?>"></script>
<script>window._logCtrl = new LogViewerController({ title: 'Failed Login Attempts', apiEndpoint: '/system/failed-login-attempts' });</script>