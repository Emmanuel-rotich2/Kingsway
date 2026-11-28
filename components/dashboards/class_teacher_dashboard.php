<?php /** Class Teacher workspace. */ ?>
<div class="container-fluid py-3 teacher-workspace teacher-workspace--class" id="classTeacherDashboard">
  <section class="teacher-kpis">
    <article><i class="bi bi-people"></i><span>Total students</span><strong id="ctStudents">—</strong><small id="ctClassLabel">Loading assigned class…</small><small id="ctGenderSplit">Class enrolment</small></article>
    <article><i class="bi bi-person-check"></i><span>Attendance today</span><strong id="ctAttendance">—</strong><small id="ctAttendanceSplit">Awaiting register</small></article>
    <article><i class="bi bi-graph-up-arrow"></i><span>Class average</span><strong id="ctAverage">—</strong><small id="ctPerformanceSplit">Assessment performance</small></article>
    <article><i class="bi bi-clipboard2-check"></i><span>Pending marking</span><strong id="ctAssessments">—</strong><small id="ctAssessmentSplit">Assessments</small></article>
  </section>
  <div class="teacher-grid">
    <main>
      <section class="teacher-panel teacher-panel--chart"><header><div><span>Trend</span><h2>Attendance and class performance</h2></div><a href="#" data-route="attendance_reports">View report</a></header><div class="teacher-chart"><canvas id="ctAttendanceChart"></canvas></div></section>
      <section class="teacher-panel"><header><div><span>Learner insight</span><h2>Students requiring attention</h2></div><a href="#" data-route="my_students_list">View class</a></header><div class="table-responsive"><table class="table teacher-table"><thead><tr><th>Learner</th><th>Admission</th><th>Average</th><th>Assessments</th><th>Status</th></tr></thead><tbody id="ctStudentBody"></tbody></table></div></section>
      <section class="teacher-panel"><header><div><span>Academic insight</span><h2>Performance distribution</h2></div></header><div class="teacher-chart teacher-chart--short"><canvas id="ctPerformanceChart"></canvas></div></section>
    </main>
    <aside>
      <section class="teacher-panel teacher-working"><header><div><span>Today</span><h2>Working progress</h2></div></header><div class="teacher-ring" id="ctWorkRing"><strong id="ctWorkRate">0%</strong><span>day complete</span></div><small id="ctNextLesson">No remaining lesson</small></section>
      <section class="teacher-panel"><header><div><span>Daily schedule</span><h2>Today's lessons</h2></div><a href="#" data-route="timetable">Full timetable</a></header><div class="teacher-list" id="ctScheduleList"></div></section>
      <section class="teacher-panel"><header><div><span>School calendar</span><h2>Upcoming events</h2></div></header><div class="teacher-list" id="ctEventsList"></div></section>
      <nav class="teacher-actions"><a href="#" data-route="class_mark_attendance"><i class="bi bi-check2-square"></i>Mark attendance</a><a href="#" data-route="formative_assessments"><i class="bi bi-pencil-square"></i>Enter marks</a><a href="#" data-route="manage_lesson_plans"><i class="bi bi-journal-text"></i>Lesson plans</a></nav>
    </aside>
  </div>
</div>
<?php asset_script($appBase, 'js/dashboards/teacher_dashboard_ui.js'); ?>
<?php asset_script($appBase, 'js/dashboards/class_teacher_dashboard.js'); ?>
