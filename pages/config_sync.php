<?php /** Config Sync - Manage config synchronization */ ?>
<div class="container-fluid py-4">
    <div class="row mb-4"><div class="col-12"><div class="d-flex justify-content-between align-items-center">
        <div><h4 class="mb-1"><i class="fas fa-sync me-2"></i>Config Sync</h4><p class="text-muted mb-0">Manage config synchronization</p></div>
        <button class="btn btn-outline-primary" onclick="window._toggleCtrl.loadData()"><i class="fas fa-sync-alt me-1"></i> Refresh</button>
    </div></div></div>
    <div class="row mb-4">
        <div class="col-md-4 mb-3"><div class="card shadow-sm border-0"><div class="card-body"><div class="d-flex align-items-center"><div class="rounded-circle bg-primary bg-opacity-10 p-3 me-3"><i class="fas fa-list text-primary fa-lg"></i></div><div><h6 class="text-muted mb-1">Total Settings</h6><h4 class="mb-0" id="statTotal">0</h4></div></div></div></div></div>
        <div class="col-md-4 mb-3"><div class="card shadow-sm border-0"><div class="card-body"><div class="d-flex align-items-center"><div class="rounded-circle bg-success bg-opacity-10 p-3 me-3"><i class="fas fa-toggle-on text-success fa-lg"></i></div><div><h6 class="text-muted mb-1">Enabled</h6><h4 class="mb-0" id="statEnabled">0</h4></div></div></div></div></div>
        <div class="col-md-4 mb-3"><div class="card shadow-sm border-0"><div class="card-body"><div class="d-flex align-items-center"><div class="rounded-circle bg-secondary bg-opacity-10 p-3 me-3"><i class="fas fa-toggle-off text-secondary fa-lg"></i></div><div><h6 class="text-muted mb-1">Disabled</h6><h4 class="mb-0" id="statDisabled">0</h4></div></div></div></div></div>
    </div>
    <div class="card shadow-sm"><div class="card-header bg-white"><h6 class="mb-0"><i class="fas fa-sync me-2"></i>Config Sync Settings</h6></div><div class="card-body" id="settingsContainer"><div class="text-center text-muted py-4">Loading settings...</div></div></div>
</div>
<script src="/Kingsway/js/pages/system/toggle_config_controller.js?v=<?php echo time(); ?>"></script>
<script>window._toggleCtrl = new ToggleConfigController({ title: 'Config Sync', apiEndpoint: '/system/config-sync' });</script>