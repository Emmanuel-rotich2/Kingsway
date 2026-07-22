<?php /** Canonical System Administration console. */ ?>
<div class="container-fluid py-4" data-system-console data-mode="registry" data-registry="retention">
 <div class="d-flex justify-content-between align-items-start mb-4"><div><h3>Data Retention</h3><p class="text-muted">System Domain records only. All actions are permission checked and audited.</p></div><button class="btn btn-outline-primary" data-refresh><i class="fas fa-sync-alt me-1"></i>Refresh</button></div>
 <div class="alert alert-info" data-state>Loading…</div>
 <div class="card border-0 shadow-sm"><div class="table-responsive"><table class="table table-hover mb-0"><thead><tr><th>Loading</th></tr></thead><tbody><tr><td class="text-center py-4">Loading…</td></tr></tbody></table></div></div>
</div>
<script src="<?= $appBase ?>/js/pages/system/system_admin_console.js?v=<?= time() ?>"></script>
