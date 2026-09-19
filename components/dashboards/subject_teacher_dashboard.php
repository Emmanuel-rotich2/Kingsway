<?php /** Subject Teacher workspace. */ ?>
<div class="container-fluid py-3 teacher-workspace teacher-workspace--subject" id="subjectTeacherDashboard">
  <section class="teacher-assignment-overview">
    <header>
      <div><span>Assignment overview</span>
        <h2>Teaching workload</h2>
      </div>
    </header>
    <div class="teacher-kpis teacher-kpis--subject">
      <article><i class="bi bi-collection"></i><span>Classes</span><strong id="stClasses">—</strong><small
          id="stClassSplit">Assigned learners</small></article>
      <article><i class="bi bi-diagram-3"></i><span>Streams</span><strong id="stSections">—</strong><small
          id="stSectionSplit">Teaching sections</small></article>
      <article><i class="bi bi-hourglass-split"></i><span>Not submitted</span><strong
          id="stAssessments">—</strong><small id="stAssessmentSplit">Pending assessments</small></article>
      <article><i class="bi bi-check2-circle"></i><span>Marked this week</span><strong id="stGraded">—</strong><small
          id="stGradedSplit">Completed results</small></article>
    </div>
  </section>
  <div class="teacher-grid teacher-grid--subject">
    <main>
      <div class="teacher-chart-pair">
        <section class="teacher-panel">
          <header>
            <div><span>Comparison</span>
              <h2>Performance by class</h2>
            </div><a href="#" data-route="academic_reports">View all</a>
          </header>
          <div class="teacher-chart"><canvas id="subPerformanceChart"></canvas></div>
        </section>
        <section class="teacher-panel teacher-working">
          <header>
            <div><span>Marking</span>
              <h2>Completion</h2>
            </div>
          </header>
          <div class="teacher-ring" id="stMarkingRing"><strong id="stMarkingRate">0%</strong><span>completed</span>
          </div><small id="stMarkingCaption">No submitted work</small>
        </section>
      </div>
      <section class="teacher-panel">
        <header>
          <div><span>Work queue</span>
            <h2>Pending assessments</h2>
          </div><a href="#" data-route="subject_grade_entry">Enter marks</a>
        </header>
        <div class="table-responsive">
          <table class="table teacher-table">
            <thead>
              <tr>
                <th>Assessment</th>
                <th>Class</th>
                <th>Due date</th>
                <th>Status</th>
              </tr>
            </thead>
            <tbody id="stPendingBody"></tbody>
          </table>
        </div>
      </section>
      <section class="teacher-panel">
        <header>
          <div><span>Trend</span>
            <h2>Assessment performance over time</h2>
          </div>
        </header>
        <div class="teacher-chart teacher-chart--short"><canvas id="subGradingChart"></canvas></div>
      </section>
    </main>
    <aside>
      <section class="teacher-panel">
        <header>
          <div><span>Daily schedule</span>
            <h2>Today's lessons</h2>
          </div><a href="#" data-route="timetable">Full timetable</a>
        </header>
        <div class="teacher-list" id="stScheduleList"></div>
      </section>
      <section class="teacher-panel">
        <header>
          <div><span>School calendar</span>
            <h2>Upcoming events</h2>
          </div>
        </header>
        <div class="teacher-list" id="stEventsList"></div>
      </section>
      <section class="teacher-panel">
        <header>
          <div><span>Dates</span>
            <h2>Upcoming exams</h2>
          </div>
        </header>
        <div class="teacher-list" id="stExamList"></div>
      </section>
      <nav class="teacher-actions"><a href="#" data-route="subject_grade_entry"><i class="bi bi-pencil-square"></i>Enter
          marks</a><a href="#" data-route="manage_lesson_plans"><i class="bi bi-journal-text"></i>Lesson plans</a><a
          href="#" data-route="teaching_materials"><i class="bi bi-folder2-open"></i>Resources</a></nav>
    </aside>
  </div>
</div>
<?php asset_script($appBase, 'js/dashboards/teacher_dashboard_ui.js'); ?>
<?php asset_script($appBase, 'js/dashboards/subject_teacher_dashboard.js'); ?>
