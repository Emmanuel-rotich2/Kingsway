<?php /** IP Whitelist/Blacklist - Manage IP allow/deny lists */ ?>
<div class="container-fluid py-4">
    <div class="row mb-4"><div class="col-12"><div class="d-flex justify-content-between align-items-center">
        <div><h4 class="mb-1"><i class="fas fa-network-wired me-2"></i>IP Whitelist/Blacklist</h4><p class="text-muted mb-0">Manage IP allow/deny lists</p></div>
        <button class="btn btn-outline-success" onclick="window._matrixCtrl.exportCSV()"><i class="fas fa-file-csv me-1"></i> Export</button>
    </div></div></div>
    <div class="row mb-4">
        <div class="col-md-3 mb-3"><div class="card shadow-sm border-0"><div class="card-body"><div class="d-flex align-items-center"><div class="rounded-circle bg-primary bg-opacity-10 p-3 me-3"><i class="fas fa-th-list text-primary fa-lg"></i></div><div><h6 class="text-muted mb-1">IP Ranges</h6><h4 class="mb-0" id="statRows">0</h4></div></div></div></div></div>
        <div class="col-md-3 mb-3"><div class="card shadow-sm border-0"><div class="card-body"><div class="d-flex align-items-center"><div class="rounded-circle bg-success bg-opacity-10 p-3 me-3"><i class="fas fa-columns text-success fa-lg"></i></div><div><h6 class="text-muted mb-1">Rules</h6><h4 class="mb-0" id="statCols">0</h4></div></div></div></div></div>
        <div class="col-md-3 mb-3"><div class="card shadow-sm border-0"><div class="card-body"><div class="d-flex align-items-center"><div class="rounded-circle bg-info bg-opacity-10 p-3 me-3"><i class="fas fa-check text-info fa-lg"></i></div><div><h6 class="text-muted mb-1">Active Mappings</h6><h4 class="mb-0" id="statActive">0</h4></div></div></div></div></div>
        <div class="col-md-3 mb-3"><div class="card shadow-sm border-0"><div class="card-body"><div class="d-flex align-items-center"><div class="rounded-circle bg-warning bg-opacity-10 p-3 me-3"><i class="fas fa-th text-warning fa-lg"></i></div><div><h6 class="text-muted mb-1">Total Cells</h6><h4 class="mb-0" id="statTotal">0</h4></div></div></div></div></div>
    </div>
    <div class="card shadow-sm mb-4"><div class="card-body"><div class="row g-3"><div class="col-md-8"><input type="text" class="form-control" id="searchInput" placeholder="Filter IP Ranges..."></div><div class="col-md-4"><button class="btn btn-outline-secondary w-100" onclick="window._matrixCtrl.loadData()"><i class="fas fa-sync-alt me-1"></i> Refresh</button></div></div></div></div>
    <div class="card shadow-sm"><div class="card-header bg-white"><h6 class="mb-0"><i class="fas fa-network-wired me-2"></i>IP Range / Rule Matrix</h6></div><div class="card-body" id="matrixContainer"><div class="text-center text-muted py-4">Loading matrix...</div></div></div>
</div>
<script src="/Kingsway/js/pages/system/matrix_grid_controller.js?v=<?php echo time(); ?>"></script>
<script>window._matrixCtrl = new MatrixGridController({ title: 'IP Whitelist/Blacklist', apiEndpoint: '/system/ip-lists', rowLabel: 'IP Range', colLabel: 'Rule' });</script>