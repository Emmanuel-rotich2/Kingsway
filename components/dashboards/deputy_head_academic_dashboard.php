<?php /** Deputy Head — Academic registrar composition. */ ?>
<section class="container-fluid py-4 role-dashboard dashboard-academic-command" id="deputyAcademicDashboard" data-dashboard-layout="registrar-rail" aria-busy="true">
    <div class="alert alert-info mb-3" id="deputyAcademicDashboardState" role="status">Loading academic operations…</div>
    <div class="dash-period-selector mb-3" id="deputyAcademicDashboardPeriodBar"><span class="small text-muted me-2">Period:</span><button class="btn btn-sm btn-success dash-period-btn" data-period="today" type="button">Today</button><button class="btn btn-sm btn-outline-success dash-period-btn" data-period="week" type="button">This week</button><button class="btn btn-sm btn-outline-success dash-period-btn" data-period="term" type="button">This term</button><span class="visually-hidden" id="deputyAcademicDashboardPeriodBarLabel">Today</span></div>
    <div class="row g-3 mb-3">
        <aside class="col-xl-4"><div class="row g-3 registrar-kpi-rail">
            <?php $cards = [
                ['daAdmissions','daAdmissionsSub','Pending placements','success','bi-person-plus'],
                ['daGrading','daGradingSub','Grade entries pending','orange','bi-pencil-square'],
                ['daTimetable','daTimetableSub','Timetable coverage','info','bi-calendar2-week'],
                ['daLessonPlans','daLessonPlansSub','Lesson plans pending','purple','bi-journal-check'],
                ['daAttendance','daAttendanceSub','Learner attendance','primary','bi-person-check'],
                ['daWorkload','daWorkloadSub','Workload alerts','danger','bi-exclamation-triangle'],
            ]; foreach ($cards as [$id,$sub,$label,$tone,$icon]): ?><div class="col-6"><article class="dash-stat-block bg-<?= $tone ?> text-white h-100"><div><div class="dash-stat-value" id="<?= $id ?>">—</div><div class="dash-stat-label"><?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?></div><div class="dash-stat-sub" id="<?= $sub ?>"></div></div><i class="bi <?= $icon ?> fs-3" aria-hidden="true"></i></article></div><?php endforeach; ?>
        </div></aside>
        <div class="col-xl-8"><div class="row g-3 h-100"><div class="col-lg-7"><article class="card border-0 shadow-sm h-100"><div class="card-header bg-white"><strong>Academic performance by class</strong></div><div class="card-body dash-chart-wrap-lg"><canvas id="daPerformanceChart"></canvas></div></article></div><div class="col-lg-5"><article class="card border-0 shadow-sm h-100"><div class="card-header bg-white"><strong>Lesson-plan submissions</strong></div><div class="card-body dash-chart-wrap-lg"><canvas id="daLessonPlanChart"></canvas></div></article></div></div></div>
    </div>
    <div class="row g-3"><div class="col-xl-7"><article class="card border-0 shadow-sm h-100"><div class="card-header bg-white d-flex justify-content-between"><strong>Applicants awaiting class placement</strong><a class="small" href="<?= htmlspecialchars($appBase, ENT_QUOTES, 'UTF-8') ?>/home.php?route=admissions_academic_applications">Open admissions</a></div><div class="table-responsive"><table class="table table-hover align-middle mb-0"><thead><tr><th>Applicant</th><th>Grade applied</th><th>Test score</th><th>Status</th></tr></thead><tbody id="daPlacementsBody"><tr><td colspan="4" class="text-center text-muted py-4">Loading placements…</td></tr></tbody></table></div></article></div><div class="col-xl-5"><article class="card border-0 shadow-sm h-100"><div class="card-header bg-white"><strong>Incomplete grade-entry queue</strong></div><div class="table-responsive"><table class="table table-hover align-middle mb-0"><thead><tr><th>Teacher</th><th>Class</th><th>Pending</th><th>Status</th></tr></thead><tbody id="daGradesBody"><tr><td colspan="4" class="text-center text-muted py-4">Loading grade entries…</td></tr></tbody></table></div></article></div></div>
</section>
<?php asset_script($appBase, 'js/dashboards/dashboard_base_controller.js'); ?>
<?php asset_script($appBase, 'js/dashboards/deputy_head_academic_dashboard.js'); ?>
