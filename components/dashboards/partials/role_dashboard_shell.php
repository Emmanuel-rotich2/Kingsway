<?php
/**
 * Shared Bootstrap dashboard shell.
 *
 * Required $dashboardConfig keys:
 * root_id, title, subtitle, icon, controller_file, controller_name,
 * cards, charts, tables, quick_actions.
 */

if (!isset($dashboardConfig) || !is_array($dashboardConfig)) {
    throw new RuntimeException('Dashboard configuration is required.');
}

$rootId = (string) ($dashboardConfig['root_id'] ?? 'roleDashboard');
$title = (string) ($dashboardConfig['title'] ?? 'Dashboard');
$subtitle = (string) ($dashboardConfig['subtitle'] ?? 'Role workspace');
$icon = (string) ($dashboardConfig['icon'] ?? 'bi-speedometer2');
$controllerFile = (string) ($dashboardConfig['controller_file'] ?? '');
$cards = $dashboardConfig['cards'] ?? [];
$charts = $dashboardConfig['charts'] ?? [];
$tables = $dashboardConfig['tables'] ?? [];
$quickActions = $dashboardConfig['quick_actions'] ?? [];

$escape = static function ($value): string {
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
};
?>

<div class="container-fluid py-4 role-dashboard" id="<?= $escape($rootId) ?>">
    <div class="dash-greeting-bar">
        <div>
            <h5>
                <i class="bi <?= $escape($icon) ?> me-2"></i>
                <?= $escape($title) ?>
            </h5>
            <p><?= $escape($subtitle) ?></p>
        </div>

        <div class="dash-meta">
            <span class="dash-badge" id="<?= $escape($rootId) ?>Scope"></span>
            <span class="small opacity-75">
                Updated <span id="<?= $escape($rootId) ?>LastUpdated">—</span>
            </span>
            <button
                type="button"
                class="dash-refresh-btn"
                id="<?= $escape($rootId) ?>Refresh"
            >
                <i class="bi bi-arrow-clockwise me-1"></i>
                Refresh
            </button>
        </div>
    </div>

    <div
        class="dashboard-state alert alert-light border"
        id="<?= $escape($rootId) ?>State"
        role="status"
    >
        Loading dashboard data...
    </div>

    <?php if ($cards): ?>
        <div class="row g-3 mb-4">
            <?php foreach ($cards as $card): ?>
                <div class="col-sm-6 col-xl-3">
                    <div class="dash-stat <?= $escape($card['colour'] ?? 'dsc-blue') ?> h-100">
                        <i class="bi <?= $escape($card['icon'] ?? 'bi-bar-chart') ?> dash-stat-icon"></i>
                        <div class="dash-stat-value" id="<?= $escape($card['id']) ?>">0</div>
                        <div class="dash-stat-label"><?= $escape($card['label']) ?></div>
                        <?php if (!empty($card['subtitle_id'])): ?>
                            <div class="dash-stat-sub" id="<?= $escape($card['subtitle_id']) ?>">
                                <?= $escape($card['subtitle'] ?? '') ?>
                            </div>
                        <?php elseif (!empty($card['subtitle'])): ?>
                            <div class="dash-stat-sub">
                                <?= $escape($card['subtitle']) ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <?php if ($charts): ?>
        <div class="row g-3 mb-4">
            <?php foreach ($charts as $chart): ?>
                <div class="<?= $escape($chart['column'] ?? 'col-lg-6') ?>">
                    <div class="card dash-card">
                        <div class="card-header">
                            <h6 class="dashboard-section-title">
                                <i class="bi <?= $escape($chart['icon'] ?? 'bi-graph-up') ?>"></i>
                                <?= $escape($chart['title']) ?>
                            </h6>
                        </div>
                        <div class="card-body">
                            <div class="<?= $escape($chart['size'] ?? 'dash-chart-wrap') ?>">
                                <canvas id="<?= $escape($chart['id']) ?>"></canvas>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <?php if ($tables || $quickActions): ?>
        <div class="row g-3">
            <?php foreach ($tables as $table): ?>
                <div class="<?= $escape($table['column'] ?? 'col-xl-6') ?>">
                    <div class="card dash-card">
                        <div class="card-header d-flex align-items-center justify-content-between gap-2">
                            <h6 class="dashboard-section-title">
                                <i class="bi <?= $escape($table['icon'] ?? 'bi-table') ?>"></i>
                                <?= $escape($table['title']) ?>
                            </h6>
                            <?php if (!empty($table['route'])): ?>
                                <button
                                    type="button"
                                    class="btn btn-sm btn-outline-success"
                                    data-route="<?= $escape($table['route']) ?>"
                                >
                                    View all
                                </button>
                            <?php endif; ?>
                        </div>
                        <div class="card-body p-0 dashboard-table-wrap">
                            <div class="table-responsive">
                                <table class="table table-hover align-middle mb-0">
                                    <thead>
                                        <tr>
                                            <?php foreach (($table['columns'] ?? []) as $column): ?>
                                                <th><?= $escape($column) ?></th>
                                            <?php endforeach; ?>
                                        </tr>
                                    </thead>
                                    <tbody id="<?= $escape($table['body_id']) ?>">
                                        <tr>
                                            <td colspan="<?= count($table['columns'] ?? []) ?>"
                                                class="text-center text-muted py-4">
                                                Loading...
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>

            <?php if ($quickActions): ?>
                <div class="col-xl-4">
                    <div class="card dash-card">
                        <div class="card-header">
                            <h6 class="dashboard-section-title">
                                <i class="bi bi-lightning-charge"></i>
                                Quick Actions
                            </h6>
                        </div>
                        <div class="card-body">
                            <div class="dashboard-action-grid">
                                <?php foreach ($quickActions as $action): ?>
                                    <a
                                        href="#"
                                        class="dash-quick-link"
                                        data-route="<?= $escape($action['route']) ?>"
                                    >
                                        <i class="bi <?= $escape($action['icon'] ?? 'bi-arrow-right-circle') ?> ql-icon <?= $escape($action['colour'] ?? 'bg-success text-white') ?>"></i>
                                        <span><?= $escape($action['label']) ?></span>
                                        <i class="bi bi-chevron-right ql-arrow"></i>
                                    </a>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>

<script src="<?= $escape($appBase) ?>/js/dashboards/dashboard_base_controller.js?v=<?= filemtime(__DIR__ . '/../../../js/dashboards/dashboard_base_controller.js') ?>"></script>
<script src="<?= $escape($appBase) ?>/js/dashboards/<?= $escape($controllerFile) ?>?v=<?= filemtime(__DIR__ . '/../../../js/dashboards/' . $controllerFile) ?>"></script>
