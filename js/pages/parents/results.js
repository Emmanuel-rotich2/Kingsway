/**s
 * js/pages/parents/results.js — Learning & Results.
 * Tabs: performance, report card, covered content, competencies,
 * progress & comparison (charts), SWOT analysis.
 * Tabs are in the page markup; childPage base binds them automatically.
 */
(function () {
  'use strict';
  var P = window.ParentCommon;
  var _lastData = null;
  var _lastTab = null;
  var _charts = [];

  function destroyCharts() {
    (_charts || []).forEach(function (ch) {
      try { ch.destroy && ch.destroy(); } catch (_e) { /* ignore */ }
    });
    _charts = [];
  }

  function rubricBadge(level) {
    var map = { EE: 'success', ME: 'primary', AE: 'info', BE: 'warning' };
    return '<span class="badge bg-' + (map[level] || 'secondary') + '">' + P.esc(level || '-') + '</span>';
  }

  function renderPerformance(data, el) {
    var scores = data.scores || [];
    var comps = data.competencies || [];
    var vals = data.values || [];
    var att = data.attendance || {};
    var attPct = att.total_days > 0 ? Math.round(100 * att.days_present / att.total_days) : 0;
    var html = '';
    if (scores.length) {
      html += '<h6 class="text-muted mb-2">Subject Scores</h6><div class="table-responsive mb-4"><table class="pp-table table-sm"><thead><tr><th>Subject</th><th>Score</th><th>Grade</th></tr></thead><tbody>';
      scores.forEach(function (s) {
        var score = parseFloat(s.score || s.total_score || 0);
        var grade = s.grade || s.letter_grade || (window.GradingScale && window.GradingScale.grade ? window.GradingScale.grade(score) : '');
        html += '<tr><td>' + P.esc(s.subject_name || '') + '</td><td>' + score + '</td><td><span class="badge bg-primary">' + P.esc(grade) + '</span></td></tr>';
      });
      html += '</tbody></table></div>';
    }
    if (comps.length) {
      html += '<h6 class="text-muted mb-2">Core Competencies</h6><div class="table-responsive mb-4"><table class="pp-table table-sm"><thead><tr><th>Competency</th><th>Level</th><th>Notes</th></tr></thead><tbody>';
      comps.forEach(function (c) {
        html += '<tr><td><strong>' + P.esc(c.code || '') + '</strong> ' + P.esc(c.competency_name || '') + '</td><td>' + rubricBadge(c.level_code || c.level_name) + '</td><td><small>' + P.esc(c.notes || '') + '</small></td></tr>';
      });
      html += '</tbody></table></div>';
    }
    if (vals.length) {
      html += '<h6 class="text-muted mb-2">Core Values</h6><div class="table-responsive mb-4"><table class="pp-table table-sm"><thead><tr><th>Value</th><th>Rating</th></tr></thead><tbody>';
      vals.forEach(function (v) { html += '<tr><td>' + P.esc(v.value_name || '') + '</td><td>' + P.esc(v.rating || '-') + '</td></tr>'; });
      html += '</tbody></table></div>';
    }
    if (att.total_days) {
      html += '<h6 class="text-muted mb-2">Term Attendance</h6><div class="row g-2 mb-3"><div class="col-auto"><span class="badge bg-success fs-6">' + attPct + '%</span></div>' +
        '<div class="col-auto text-muted small lh-lg">' + (att.days_present || 0) + '/' + (att.total_days || 0) + ' days present</div></div>';
    }
    if (!scores.length && !comps.length) html = '<div class="alert alert-info">No performance data available for the current term.</div>';
    el.innerHTML = html;
  }

  function renderReportCard(data, el) {
    if (!data || data.released === false) {
      el.innerHTML = '<div class="alert alert-info"><i class="bi bi-lock me-2"></i>' + P.esc((data && data.message) || 'The school has not released a report card for this learner yet.') + '</div>';
      return;
    }
    var s = data.student || {};
    var term = data.term || {};
    var scores = data.scores || [];
    var comps = data.competencies || [];
    var vals = data.values || [];
    var att = data.attendance || {};
    var enr = data.enrollment || {};
    var school = data.school || {};
    var official = data.official_release || {};
    var attPct = att.total_days > 0 ? Math.round(100 * att.days_present / att.total_days) : 0;
    var html = '<div class="report-card-container p-3">' +
      '<div class="alert alert-success py-2"><i class="bi bi-patch-check-fill me-2"></i>Official school release' +
      (official.version_no ? ' · Version ' + P.esc(official.version_no) : '') +
      (official.released_at ? ' · ' + P.esc(official.released_at) : '') + '</div>' +
      '<div class="text-center mb-3 border-bottom pb-3">' +
      '<h4 class="fw-bold mb-0">' + P.esc(school.name || 'Kingsway Preparatory School') + '</h4>' +
      '<small class="text-muted">' + P.esc(school.address || '') + ' | ' + P.esc(school.phone || '') + '</small>' +
      '<h5 class="mt-2">School Report Card</h5>' +
      '<small class="text-muted">' + P.esc(term.name || '') + ' · ' + P.esc(data.year || term.year_code || '') + '</small></div>' +
      '<div class="row mb-3 small"><div class="col-6"><strong>Name:</strong> ' + P.esc(s.first_name + ' ' + s.last_name) + '</div>' +
      '<div class="col-3"><strong>Adm:</strong> ' + P.esc(s.admission_no || '') + '</div>' +
      '<div class="col-3"><strong>Class:</strong> ' + P.esc(s.class_name || '') + ' ' + P.esc(s.stream_name || '') + '</div></div>';
    if (scores.length) {
      html += '<h6 class="text-muted border-bottom pb-1">Academic Performance</h6><div class="table-responsive mb-3"><table class="pp-table table-sm"><thead><tr><th>Subject</th><th>Formative</th><th>Summative</th><th>Overall</th><th>Grade</th></tr></thead><tbody>';
      scores.forEach(function (sc) {
        var ovr = parseFloat(sc.overall_percentage || sc.total_score || sc.score || 0);
        var grd = sc.overall_grade || sc.grade || sc.cbc_grade || (window.GradingScale && window.GradingScale.grade ? window.GradingScale.grade(ovr) : '');
        html += '<tr><td>' + P.esc(sc.subject_name || '') + '</td><td>' + (parseFloat(sc.formative_percentage || 0)) + '</td><td>' + (parseFloat(sc.summative_percentage || 0)) + '</td><td><strong>' + ovr + '</strong></td><td><span class="badge bg-primary">' + P.esc(grd) + '</span></td></tr>';
      });
      html += '</tbody></table></div>';
    }
    if (comps.length) {
      html += '<h6 class="text-muted border-bottom pb-1">Core Competencies</h6><div class="table-responsive mb-3"><table class="pp-table table-sm"><thead><tr><th>Competency</th><th>Level</th><th>Notes</th></tr></thead><tbody>';
      comps.forEach(function (c) {
        html += '<tr><td><strong>' + P.esc(c.code || '') + '</strong> ' + P.esc(c.competency_name || '') + '</td><td>' + rubricBadge(c.level_code || c.level_name) + '</td><td><small>' + P.esc(c.notes || '') + '</small></td></tr>';
      });
      html += '</tbody></table></div>';
    }
    if (vals.length) {
      html += '<h6 class="text-muted border-bottom pb-1">Core Values</h6><div class="table-responsive mb-3"><table class="pp-table table-sm"><thead><tr><th>Value</th><th>Rating</th></tr></thead><tbody>';
      vals.forEach(function (v) { html += '<tr><td>' + P.esc(v.value_name || '') + '</td><td>' + P.esc(v.rating || '-') + '</td></tr>'; });
      html += '</tbody></table></div>';
    }
    if (att.total_days) {
      html += '<h6 class="text-muted border-bottom pb-1">Attendance</h6><div class="row g-2 mb-3"><div class="col-auto"><span class="badge bg-success fs-6">' + attPct + '%</span></div>' +
        '<div class="col-auto text-muted small lh-lg">' + (att.days_present || 0) + '/' + (att.total_days || 0) + ' days present | ' + (att.days_absent || 0) + ' absent | ' + (att.days_late || 0) + ' late</div></div>';
    }
    if (enr.teacher_comments) {
      html += '<div class="card bg-light mb-2"><div class="card-body py-2"><small class="text-muted">Class Teacher</small><p class="mb-0 fst-italic">" ' + P.esc(enr.teacher_comments) + ' "</p>' +
        (enr.class_teacher_name ? '<small class="text-muted">— ' + P.esc(enr.class_teacher_name) + '</small>' : '') + '</div></div>';
    }
    if (enr.head_teacher_comments) {
      html += '<div class="card bg-light mb-2"><div class="card-body py-2"><small class="text-muted">Head Teacher</small><p class="mb-0 fst-italic">" ' + P.esc(enr.head_teacher_comments) + ' "</p></div></div>';
    }
    html += '<div class="text-center mt-3"><button class="btn btn-primary btn-sm" id="btnPrintReportCard"' +
      (official.download_url ? '' : ' disabled') + '><i class="bi bi-file-earmark-pdf me-1"></i>Open official PDF</button></div></div>';
    el.innerHTML = html;
    var printBtn = document.getElementById('btnPrintReportCard');
    if (printBtn && official.download_url) {
      printBtn.addEventListener('click', function () { window.open(official.download_url, '_blank', 'noopener'); });
    }
  }

  /* ── Covered content (new): approved/delivered lessons + assignments ── */

  function renderCoverage(data, el) {
    var ctx = data.context || {};
    var lessons = data.lessons || [];
    var assignments = data.assignments || [];
    var html = '<div class="alert alert-success"><strong>' + P.esc(ctx.term_name || 'Current term') + '</strong> · ' +
      P.esc(ctx.class_name || '') + ' ' + P.esc(ctx.stream_name || '') +
      '<br><small>Content actually taught to this class, with published assignments and submission status.</small></div>';

    if (lessons.length) {
      // Group: week → learning area → day.
      var byWeek = {};
      lessons.forEach(function (l) {
        var w = String(l.week_number || '0');
        if (!byWeek[w]) byWeek[w] = { start: l.week_start, end: l.week_end, areas: {} };
        var la = l.learning_area || 'Other';
        if (!byWeek[w].areas[la]) byWeek[w].areas[la] = [];
        byWeek[w].areas[la].push(l);
      });
      html += '<h6 class="text-muted mb-2">Taught content by week</h6>';
      Object.keys(byWeek)
        .sort(function (a, b) { return Number(a) - Number(b); })
        .forEach(function (week) {
          var wk = byWeek[week];
          html += '<div class="card border-0 shadow-sm mb-3"><div class="card-header fw-bold">Week ' + week +
            (wk.start || wk.end ? ' <span class="text-muted small fw-normal">(' + P.esc((wk.start || '').substring(0, 10)) + ' – ' + P.esc((wk.end || '').substring(0, 10)) + ')</span>' : '') +
            '</div><div class="card-body p-0"><div class="table-responsive"><table class="pp-table table-sm mb-0"><thead><tr><th>Learning area</th><th>Strand / Sub-strand</th><th>Date</th><th>Lesson</th></tr></thead><tbody>';
          Object.keys(wk.areas).forEach(function (la) {
            wk.areas[la].forEach(function (l) {
              html += '<tr><td><strong>' + P.esc(la) + '</strong></td>' +
                '<td>' + P.esc(l.strand_name || '') + (l.sub_strand_name ? ' / ' + P.esc(l.sub_strand_name) : '') + '</td>' +
                '<td>' + P.esc((l.lesson_date || '').substring(0, 10)) + '</td>' +
                '<td>' + P.esc(l.title || 'Lesson') + '</td></tr>';
            });
          });
          html += '</tbody></table></div></div></div>';
        });
    } else {
      html += '<div class="alert alert-info">No delivered lessons are recorded for this term yet.</div>';
    }

    if (assignments.length) {
      html += '<h6 class="text-muted mt-4 mb-2">Published assignments & submission status</h6><div class="table-responsive"><table class="pp-table table-sm"><thead><tr><th>Assignment</th><th>Learning area</th><th>Due</th><th>Status</th><th>Marks</th></tr></thead><tbody>';
      assignments.forEach(function (a) {
        var st = a.submission_status || 'not_submitted';
        var badge = st === 'submitted' ? 'info' : st === 'graded' ? 'success' : st === 'marked' ? 'success' : 'secondary';
        var label = st === 'submitted' ? 'Submitted' : st === 'graded' || st === 'marked' ? 'Marked' : 'Pending';
        if (a.submission_id) label = st || (a.is_graded ? 'Marked' : 'Under review');
        html += '<tr><td><strong>' + P.esc(a.title || '') + '</strong>' +
          (a.sub_strand_name ? '<div class="small text-muted">' + P.esc(a.strand_name || '') + (a.sub_strand_name ? ' / ' + P.esc(a.sub_strand_name) : '') + '</div>' : '') + '</td>' +
          '<td>' + P.esc(a.learning_area || '') + '</td>' +
          '<td>' + P.esc((a.due_date || '').substring(0, 10)) + '</td>' +
          '<td><span class="badge bg-' + badge + '">' + P.esc(label) + '</span></td>' +
          '<td>' + (a.marks_awarded !== null && a.marks_awarded !== undefined ? a.marks_awarded + ' / ' + (a.total_marks || '') : '—') + '</td></tr>';
      });
      html += '</tbody></table></div>';
    } else {
      html += '<div class="alert alert-info mt-3">No published assignments for this term yet.</div>';
    }
    el.innerHTML = html;
  }

  /* ── Competencies (new): full CBC competencies + values + legend ── */

  function renderCompetencies(data, el) {
    var comps = data.competencies || [];
    var vals = data.values || [];
    var levels = data.levels || [];
    var sum = data.summary || {};

    var html = '<div class="row g-2 mb-3">' +
      '<div class="col-auto"><div class="pp-kpi-card bg-info-subtle text-info"><div><small class="text-muted">Competencies assessed</small><div class="fw-bold fs-6">' + (sum.assessed || 0) + ' / ' + (sum.total || 0) + '</div></div></div></div>' +
      '<div class="col-auto"><div class="pp-kpi-card bg-primary-subtle text-primary"><div><small class="text-muted">Core values evidenced</small><div class="fw-bold fs-6">' + (sum.assessed_values || 0) + ' / ' + (vals.length || 0) + '</div></div></div></div>' +
      '</div>';

    if (levels.length) {
      html += '<div class="mb-3">' + levels.map(function (lv) {
        return '<span class="me-2">' + rubricBadge(lv.code) + ' ' + P.esc(lv.name || '') + '</span>';
      }).join('') + '</div>';
    }

    if (comps.length) {
      html += '<h6 class="text-muted mb-2">Core Competencies <small class="text-muted">(CBC)</small></h6><div class="table-responsive mb-4"><table class="pp-table table-sm"><thead><tr><th>Competency</th><th>Level</th><th>Evidence / Notes</th></tr></thead><tbody>';
      comps.forEach(function (c) {
        var evidence = c.evidence || c.teacher_notes || '';
        var detail = evidence
          ? '<small>' + P.esc(evidence) + '</small>'
          : (c.has_assessment ? '<small class="text-muted">Assessed, evidence not yet shared.</small>' : '<small class="text-muted">Not yet assessed this term.</small>');
        if (c.assessed_date) detail += '<small class="text-muted d-block">Assessed ' + P.esc(c.assessed_date) + '</small>';
        html += '<tr><td><strong>' + P.esc(c.code || '') + '</strong> ' + P.esc(c.competency_name || '') + '</td>' +
          '<td>' + (c.level_name || c.level_code ? rubricBadge(c.level_code || c.level_name) + ' ' + P.esc(c.level_name || '') : '<span class="text-muted">—</span>') + '</td>' +
          '<td>' + detail + '</td></tr>';
      });
      html += '</tbody></table></div>';
    } else {
      html += '<div class="alert alert-info">No competency records for this child this term.</div>';
    }

    if (vals.length) {
      html += '<h6 class="text-muted mb-2">Core Values</h6><div class="table-responsive"><table class="pp-table table-sm"><thead><tr><th>Value</th><th>Evidence</th></tr></thead><tbody>';
      vals.forEach(function (v) {
        var ev = v.evidence ? '<small>' + P.esc(v.evidence) + '</small>' : '<small class="text-muted">' + (v.has_evidence ? '' : 'No evidence recorded this term.') + '</small>';
        if (v.incident_date) ev += '<small class="text-muted d-block">' + P.esc(v.incident_date) + '</small>';
        html += '<tr><td><strong>' + P.esc(v.code || '') + '</strong> ' + P.esc(v.value_name || '') + '</td><td>' + ev + '</td></tr>';
      });
      html += '</tbody></table></div>';
    }
    el.innerHTML = html;
  }

  /* ── Progress charts & comparison (new, Chart.js) ── */

  function emptyChartNote(htmlEl) {
    htmlEl.innerHTML += '<div class="alert alert-info mt-3">Not enough assessment data is available for charts yet — check back after this term\'s assessments are marked.</div>';
  }

  function renderCharts(data, el) {
    destroyCharts();
    var termOverview = data.term_overview || [];
    var classComparison = data.class_comparison || [];
    var rubric = data.rubric_distribution || {};
    var html = '<div class="alert alert-success small mb-3"><i class="bi bi-graph-up me-2"></i>Progress is shown from approved formative and summative assessment data for your child only. Comparisons use the whole class average so no other learner is identified.</div>';
    html += '<div class="row g-3">' +
      '<div class="col-lg-6"><div class="card h-100"><div class="card-header fw-bold">Term trend (average %)</div><div class="card-body"><canvas id="ppChartTrend" height="140"></canvas></div></div></div>' +
      '<div class="col-lg-6"><div class="card h-100"><div class="card-header fw-bold">Learning-area rubric distribution</div><div class="card-body"><canvas id="ppChartRubric" height="140"></canvas></div></div></div>' +
      '<div class="col-12"><div class="card"><div class="card-header fw-bold">Child vs class average by learning area</div><div class="card-body"><canvas id="ppChartCompare" height="110"></canvas></div></div></div>' +
      '</div>';
    el.innerHTML = html;

    if (!window.Chart) {
      emptyChartNote(el);
      return;
    }

    // Term trend line.
    var tLabels = termOverview.map(function (t) { return t.term_name || ('Term ' + t.term_number); });
    var tVals = termOverview.map(function (t) { return t.average_percentage !== null ? t.average_percentage : null; });
    if (termOverview.length) {
      _charts.push(new Chart(document.getElementById('ppChartTrend'), {
        type: 'line',
        data: {
          labels: tLabels,
          datasets: [{ label: 'Average %', data: tVals, borderColor: '#198754', backgroundColor: 'rgba(25,135,84,.12)', fill: true, tension: .3 }],
        },
        options: { responsive: true, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true, max: 100 } } },
      }));
    } else {
      document.getElementById('ppChartTrend').innerHTML = '<div class="text-muted small py-4 text-center">No term trend yet.</div>';
    }

    // Rubric pie.
    var rLabels = ['Exceeding (EE)', 'Meeting (ME)', 'Approaching (AE)', 'Below (BE)'];
    var rVals = [rubric.ee || 0, rubric.me || 0, rubric.ae || 0, rubric.be || 0];
    var rTotal = rVals.reduce(function (a, b) { return a + b; }, 0);
    if (rTotal > 0) {
      _charts.push(new Chart(document.getElementById('ppChartRubric'), {
        type: 'pie',
        data: {
          labels: rLabels,
          datasets: [{ data: rVals, backgroundColor: ['#198754', '#0d6efd', '#0dcaf0', '#ffc107'] }],
        },
        options: { responsive: true, plugins: { legend: { position: 'bottom', labels: { boxWidth: 12 } } } },
      }));
    } else {
      document.getElementById('ppChartRubric').innerHTML = '<div class="text-muted small py-4 text-center">No rubric scores yet.</div>';
    }

    // Child vs class bar.
    if (classComparison.length) {
      var ccLabels = classComparison.map(function (c) { return c.learning_area || 'LA'; });
      var child = classComparison.map(function (c) { return c.child_average; });
      var cls = classComparison.map(function (c) { return c.class_average; });
      _charts.push(new Chart(document.getElementById('ppChartCompare'), {
        type: 'bar',
        data: {
          labels: ccLabels,
          datasets: [
            { label: 'My child', data: child, backgroundColor: 'rgba(25,135,84,.7)' },
            { label: 'Class average', data: cls, backgroundColor: 'rgba(13,110,253,.55)' },
          ],
        },
        options: { responsive: true, scales: { y: { beginAtZero: true, max: 100 } } },
      }));
    } else {
      document.getElementById('ppChartCompare').innerHTML = '<div class="text-muted small py-4 text-center">No class comparison data yet.</div>';
    }

    // Accessible data table under charts (charts supplement, never replace).
    if (classComparison.length) {
      html += '<div class="mt-3"><h6 class="text-muted">Comparison detail</h6><div class="table-responsive"><table class="pp-table table-sm"><thead><tr><th>Learning area</th><th>My child %</th><th>Class avg %</th><th>Students assessed</th></tr></thead><tbody>' +
        classComparison.map(function (c) {
          return '<tr><td>' + P.esc(c.learning_area || '') + '</td><td>' + (c.child_average !== null ? c.child_average : '—') + '</td><td>' + (c.class_average !== null ? c.class_average : '—') + '</td><td>' + (c.students_assessed || 0) + '</td></tr>';
        }).join('') + '</tbody></table></div></div>';
      el.innerHTML += html;
    }
  }

  /* ── SWOT analysis (new, deterministic rule-based) ── */

  function renderSwot(data, el) {
    var swot = data.swot || {};
    var html = '<div class="alert alert-success small mb-3"><i class="bi bi-lightbulb me-2"></i>This summary is computed from attendance, marks and rubric rules — it is a guide for a conversation with the school, not a diagnosis or ranking.</div>';
    var sections = [
      ['strengths', 'Strengths', 'success', 'bi-hand-thumbs-up-fill'],
      ['weaknesses', 'Areas to support', 'danger', 'bi-exclamation-triangle-fill'],
      ['opportunities', 'Opportunities', 'primary', 'bi-graph-up-arrow'],
      ['threats', 'Watch closely', 'warning', 'bi-shield-exclamation'],
    ];
    var hasAny = sections.some(function (s) { return (swot[s[0]] || []).length > 0; });
    if (!hasAny) {
      el.innerHTML = html + '<div class="alert alert-info">Not enough term data is available for a SWOT summary yet.</div>';
      return;
    }
    html += '<div class="row g-3">';
    sections.forEach(function (s) {
      var items = swot[s[0]] || [];
      html += '<div class="col-md-6"><div class="card h-100"><div class="card-header fw-bold bg-' + s[2] + '-subtle text-' + s[2] + '"><i class="bi ' + s[3] + ' me-2"></i>' + s[1] + ' (' + items.length + ')</div><div class="card-body">' +
        (items.length
          ? '<ul class="mb-0">' + items.map(function (t) { return '<li class="mb-1">' + P.esc(t) + '</li>'; }).join('') + '</ul>'
          : '<div class="text-muted small">Nothing to report.</div>') +
        '</div></div></div>';
    });
    html += '</div>';
    el.innerHTML = html;
  }

  /* ── CSV per active tab ── */

  function exportCsvForTab(tab) {
    var d = _lastData;
    if (!d) return;
    var headers, rows, filename;
    if (tab === 'performance') {
      headers = ['Subject', 'Score', 'Grade']; filename = 'performance.csv';
      rows = (d.scores || []).map(function (s) { return [s.subject_name || '', parseFloat(s.score || s.total_score || 0), s.grade || s.letter_grade || '']; });
    } else if (tab === 'report-card') {
      headers = ['Subject', 'Formative', 'Summative', 'Overall', 'Grade']; filename = 'report-card-scores.csv';
      rows = (d.scores || []).map(function (sc) { return [sc.subject_name || '', sc.formative_percentage || 0, sc.summative_percentage || 0, parseFloat(sc.overall_percentage || sc.total_score || sc.score || 0), sc.overall_grade || sc.grade || '']; });
    } else if (tab === 'covered') {
      headers = ['Type', 'Learning area', 'Strand / Sub-strand', 'Date / Due', 'Title', 'Status', 'Marks'];
      filename = 'covered-content.csv';
      rows = [];
      (d.lessons || []).forEach(function (l) {
        rows.push(['Lesson', l.learning_area || '', (l.strand_name || '') + (l.sub_strand_name ? ' / ' + l.sub_strand_name : ''), (l.lesson_date || '').substring(0, 10), l.title || '', 'Taught', '']);
      });
      (d.assignments || []).forEach(function (a) {
        var st = a.submission_status || 'not_submitted';
        if (a.submission_id) st = st || (a.is_graded ? 'marked' : 'under_review');
        rows.push(['Assignment', a.learning_area || '', (a.strand_name || '') + (a.sub_strand_name ? ' / ' + a.sub_strand_name : ''), (a.due_date || '').substring(0, 10), a.title || '', st, a.marks_awarded !== null && a.marks_awarded !== undefined ? a.marks_awarded + '/' + (a.total_marks || '') : '']);
      });
    } else if (tab === 'competencies') {
      headers = ['Type', 'Code', 'Name', 'Level', 'Evidence / Notes'];
      filename = 'competencies.csv';
      rows = [];
      (d.competencies || []).forEach(function (c) {
        rows.push(['Competency', c.code || '', c.competency_name || '', c.level_code || c.level_name || 'Not assessed', c.evidence || c.teacher_notes || '']);
      });
      (d.values || []).forEach(function (v) {
        rows.push(['Value', v.code || '', v.value_name || '', 'has evidence: ' + (v.has_evidence ? 'yes' : 'no'), v.evidence || '']);
      });
    } else if (tab === 'charts') {
      headers = ['Learning area', 'My child %', 'Class avg %', 'Students assessed'];
      filename = 'class-comparison.csv';
      rows = (d.class_comparison || []).map(function (c) { return [c.learning_area || '', c.child_average !== null ? c.child_average : '', c.class_average !== null ? c.class_average : '', c.students_assessed || 0]; });
    } else if (tab === 'swot') {
      headers = ['Quadrant', 'Finding'];
      filename = 'swot.csv';
      rows = [];
      [['strengths', 'Strengths'], ['weaknesses', 'Areas to support'], ['opportunities', 'Opportunities'], ['threats', 'Watch closely']].forEach(function (q) {
        ((d.swot || {})[q[0]] || []).forEach(function (t) { rows.push([q[1], t]); });
      });
    } else {
      return;
    }
    if (!rows.length) {
      alert('Nothing to export for this tab yet.'); // eslint-disable-line no-alert
      return;
    }
    P.exportCsv(filename, P.csvFromHeaders(headers, rows));
  }

  P.childPage({
    contentId: 'ppResultsContent',
    defaultTab: 'performance',
    loadTab: function (tab, child, el) {
      var endpoint;
      if (tab === 'performance') endpoint = '/student-performance/' + child.id;
      else if (tab === 'report-card') endpoint = '/student-report-card/' + child.id;
      else if (tab === 'covered') endpoint = '/student-coverage/' + child.id;
      else if (tab === 'competencies') endpoint = '/student-competencies/' + child.id;
      else if (tab === 'charts' || tab === 'swot') endpoint = '/student-analytics/' + child.id;
      else endpoint = '/student-performance/' + child.id;

      P.apiFetch(endpoint, 'GET')
        .then(function (resp) {
          var d = resp.data !== undefined ? resp.data : resp;
          _lastData = d;
          _lastTab = tab;
          if (tab === 'performance') renderPerformance(d, el);
          else if (tab === 'report-card') renderReportCard(d, el);
          else if (tab === 'covered') renderCoverage(d, el);
          else if (tab === 'competencies') renderCompetencies(d, el);
          else if (tab === 'charts') renderCharts(d, el);
          else if (tab === 'swot') renderSwot(d, el);
        })
        .catch(function (e) { P.showError(el, e.message); });
    },
  });

  document.getElementById('btnResultsCsv').addEventListener('click', function () { exportCsvForTab(_lastTab || 'performance'); });
})();