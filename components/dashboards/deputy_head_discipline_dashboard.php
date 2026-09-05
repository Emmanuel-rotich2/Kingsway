<?php /** Deputy Head — Discipline executive mosaic. */ ?>
<section class="container-fluid py-4 role-dashboard dashboard-safeguarding" id="deputyDisciplineDashboard" data-dashboard-layout="executive-wave-mosaic" aria-busy="true">
    <div class="alert alert-info mb-3" id="deputyDisciplineDashboardState" role="status">Loading discipline operations…</div>
    <div class="dash-period-selector mb-3" id="deputyDisciplineDashboardPeriodBar"><span class="small text-muted me-2">Period:</span><button class="btn btn-sm btn-success dash-period-btn" data-period="today" type="button">Today</button><button class="btn btn-sm btn-outline-success dash-period-btn" data-period="week" type="button">This week</button><button class="btn btn-sm btn-outline-success dash-period-btn" data-period="term" type="button">This term</button><span class="visually-hidden" id="deputyDisciplineDashboardPeriodBarLabel">Today</span></div>
    <div class="row g-3 mb-3 executive-mosaic-kpis">
        <?php $cards = [
            ['ddOpenCases','ddOpenCasesSub','Open cases','purple','bi-shield-exclamation'],
            ['ddUrgent','ddUrgentSub','High-priority cases','orange','bi-exclamation-octagon'],
            ['ddAbsentees','ddAbsenteesSub','Absent today','teal','bi-person-x'],
            ['ddTruancy','ddTruancySub','Attendance risk','blue','bi-calendar-x'],
            ['ddCounseling','ddCounselingSub','Escalated cases','pink','bi-heart-pulse'],
            ['ddMeetings','ddMeetingsSub','Parent follow-up','green','bi-people'],
        ]; foreach ($cards as [$id,$sub,$label,$tone,$icon]): ?><div class="col-6 col-lg-4 col-xxl-2"><article class="dash-gradient-block g-<?= $tone ?> h-100"><i class="bi <?= $icon ?>" aria-hidden="true"></i><div class="dash-stat-value" id="<?= $id ?>">—</div><div class="dash-stat-label"><?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?></div><div class="dash-stat-sub" id="<?= $sub ?>"></div></article></div><?php endforeach; ?>
    </div>
    <div class="row g-3 mb-3"><div class="col-xl-8"><article class="card border-0 shadow-sm h-100"><div class="card-header bg-white"><strong>Attendance-risk trend</strong></div><div class="card-body dash-chart-wrap-lg"><canvas id="ddTrendChart"></canvas></div></article></div><div class="col-xl-4"><article class="card border-0 shadow-sm h-100"><div class="card-header bg-white"><strong>Cases by severity</strong></div><div class="card-body dash-chart-wrap-lg"><canvas id="ddCategoryChart"></canvas></div></article></div></div>
    <article class="card border-0 shadow-sm"><div class="card-header bg-white d-flex justify-content-between"><strong>Active discipline case queue</strong><a class="small" href="<?= htmlspecialchars($appBase, ENT_QUOTES, 'UTF-8') ?>/home.php?route=discipline_cases">Open case management</a></div><div class="table-responsive"><table class="table table-hover align-middle mb-0"><thead><tr><th>Learner</th><th>Incident</th><th>Severity</th><th>Date</th><th>Status</th></tr></thead><tbody id="ddCasesBody"><tr><td colspan="5" class="text-center text-muted py-4">Loading active cases…</td></tr></tbody></table></div></article>
</section>
<?php asset_script($appBase, 'js/dashboards/dashboard_base_controller.js'); ?>
<?php asset_script($appBase, 'js/dashboards/deputy_head_discipline_dashboard.js'); ?>
