<?php /** Headteacher daily command workspace. Populated by headteacher_dashboard.js. */ ?>
<section class="container-fluid py-4 role-dashboard dashboard-leadership" id="headteacherDashboard" data-dashboard-layout="edu-center-command" aria-busy="true">
    <div id="dashboardError" class="alert alert-danger mb-3" role="alert" hidden><span id="dashboardErrorMessage">Dashboard data is unavailable.</span></div>
    <div class="dash-period-selector mb-3" id="headteacherDashboardPeriodBar">
        <span class="small text-muted me-2">Period:</span>
        <button class="btn btn-sm btn-success dash-period-btn" data-period="today" type="button">Today</button>
        <button class="btn btn-sm btn-outline-success dash-period-btn" data-period="week" type="button">This week</button>
        <button class="btn btn-sm btn-outline-success dash-period-btn" data-period="term" type="button">This term</button>
        <span class="visually-hidden" id="headteacherDashboardPeriodBarLabel">Today</span>
    </div>
    <div class="row g-3 mb-3 edu-center-kpis">
        <?php $cards = [
            ['totalStudents','studentGrowth','Learners enrolled','bi-people-fill','primary'],
            ['attendanceToday','attendanceDetails','Attendance today','bi-person-check-fill','success'],
            ['classSchedules','','Classes scheduled','bi-calendar-week-fill','info'],
            ['pendingAdmissions','admissionDetails','Admissions pending','bi-person-plus-fill','warning'],
            ['disciplineCases','disciplineDetails','Open discipline cases','bi-shield-exclamation','danger'],
            ['parentComms','','Parent communications','bi-chat-dots-fill','purple'],
            ['assessments','assessmentDetails','Assessments','bi-clipboard-data-fill','teal'],
            ['classPerformance','','CBC performance','bi-graph-up-arrow','orange'],
        ]; foreach ($cards as [$valueId,$noteId,$label,$icon,$tone]): ?>
            <div class="col-6 col-lg-3"><article class="dash-kpi-card dash-kpi-<?= $tone ?> h-100"><div><div class="dash-kpi-label"><?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?></div><div class="dash-kpi-value" id="<?= $valueId ?>">—</div><?php if ($noteId): ?><div class="dash-stat-sub" id="<?= $noteId ?>"></div><?php endif; ?></div><i class="bi <?= $icon ?> dash-kpi-icon" aria-hidden="true"></i></article></div>
        <?php endforeach; ?>
    </div>
    <div class="row g-3">
        <div class="col-xl-8">
            <div class="row g-3 mb-3">
                <div class="col-lg-7"><article class="card border-0 shadow-sm h-100"><div class="card-header bg-white"><strong>Attendance trend</strong></div><div class="card-body dash-chart-wrap-lg"><canvas id="attendanceChart"></canvas></div></article></div>
                <div class="col-lg-5"><article class="card border-0 shadow-sm h-100"><div class="card-header bg-white"><strong>CBC class performance</strong></div><div class="card-body dash-chart-wrap-lg"><canvas id="performanceChart"></canvas></div></article></div>
            </div>
            <article class="card border-0 shadow-sm"><div class="card-header bg-white d-flex justify-content-between"><strong>Admissions requiring attention</strong><a href="<?= htmlspecialchars($appBase, ENT_QUOTES, 'UTF-8') ?>/home.php?route=admissions_academic_applications" class="small">Open workflow</a></div><div class="table-responsive"><table class="table table-hover align-middle mb-0"><thead><tr><th>Applicant</th><th>Grade applied</th><th>Submitted</th><th>Status</th></tr></thead><tbody id="admissionsTableBody"><tr><td colspan="4" class="text-center text-muted py-4">Loading admissions…</td></tr></tbody></table></div></article>
        </div>
        <aside class="col-xl-4">
            <article class="card border-0 shadow-sm mb-3"><div class="card-header bg-white"><strong>Discipline attention queue</strong></div><div class="table-responsive"><table class="table table-sm align-middle mb-0"><thead><tr><th>Learner</th><th>Class</th><th>Incident</th><th>Priority</th></tr></thead><tbody id="disciplineTableBody"><tr><td colspan="4" class="text-center text-muted py-4">Loading cases…</td></tr></tbody></table></div></article>
            <article class="card border-0 shadow-sm"><div class="card-header bg-white"><strong>Upcoming school activities</strong></div><ul class="list-group list-group-flush" id="upcomingEvents"><li class="list-group-item text-muted">Loading activities…</li></ul></article>
        </aside>
    </div>
</section>
<?php asset_script($appBase, 'js/dashboards/headteacher_dashboard.js'); ?>
