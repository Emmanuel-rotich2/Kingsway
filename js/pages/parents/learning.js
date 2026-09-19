/**
 * js/pages/parents/learning.js — Homework & Objectives.
 * Tabs: assignments (published homework + grading state), objectives &
 * expectations (approved scheme workbook items → learning outcomes), and
 * practical questions (suggested experiences + questions).
 */
(function () {
  'use strict';
  var P = window.ParentCommon;
  var _data = null;

  function renderAssignments(d, el) {
    var items = d.assignments || [];
    if (!items.length) {
      el.innerHTML = '<div class="alert alert-info">No assignments have been published for this learner yet.</div>';
      return;
    }
    var rows = items.map(function (a) {
      var sub = a.submission_status || 'none';
      var badge = sub === 'submitted' ? 'bg-primary' : (sub === 'graded' ? 'bg-success' : (a.is_graded ? 'bg-success' : 'bg-secondary'));
      var gradeLabel = a.is_graded ? (a.marks_awarded !== null ? a.marks_awarded + '/' + a.total_marks : 'Graded') : '';
      var att = a.attachment_url ? '<span class="badge bg-light text-primary">attachment</span>' : '';
      return '<tr><td><strong>' + P.esc(a.title || '') + '</strong><div class="small text-muted">' + P.esc(a.learning_area || '') + (a.strand_name ? ' · ' + P.esc(a.strand_name) : '') + (a.sub_strand_name ? ' / ' + P.esc(a.sub_strand_name) : '') + '</div></td>' +
        '<td>' + P.esc((a.description || '').substring(0, 140)) + '</td>' +
        '<td>' + P.esc((a.due_date || '').substring(0, 10)) + '</td>' +
        '<td><span class="badge ' + badge + '">' + P.esc(sub || 'not submitted') + '</span></td>' +
        '<td>' + P.esc(gradeLabel || '—') + '</td></tr>';
    }).join('');
    el.innerHTML = '<div class="table-responsive"><table class="pp-table table-sm"><thead><tr><th>Assignment</th><th>Instructions</th><th>Due</th><th>Status</th><th>Marks</th></tr></thead><tbody>' + rows + '</tbody></table></div>';
  }

  function renderObjectives(d, el) {
    var items = d.workbook_items || [];
    var outcomes = d.learning_outcomes || [];
    if (!items.length && !outcomes.length) {
      el.innerHTML = '<div class="alert alert-info">No scheme objectives have been approved for this learner yet.</div>';
      return;
    }
    var html = '';
    if (items.length) {
      var weeks = {};
      items.forEach(function (it) {
        var w = 'Week ' + it.week_number;
        if (!weeks[w]) weeks[w] = [];
        weeks[w].push(it);
      });
      Object.keys(weeks).sort(function (a, b) { return Number(a.split(' ')[1]) - Number(b.split(' ')[1]); }).forEach(function (w) {
        html += '<div class="card border-0 shadow-sm mb-3"><div class="card-header fw-bold">' + w + '</div><div class="card-body">';
        weeks[w].forEach(function (it) {
          html += '<div class="border-bottom pb-2 mb-2"><strong>' + P.esc(it.learning_area || '') + '</strong> · ' + P.esc(it.item_title || '') +
            '<div class="small text-muted">' + P.esc(it.strand_name || '') + (it.sub_strand_name ? ' / ' + P.esc(it.sub_strand_name) : '') + '</div>' +
            (it.outcome_text ? '<div class="small mt-1">' + P.esc(it.outcome_text) + '</div>' : '') + '</div>';
        });
        html += '</div></div>';
      });
    }
    if (outcomes.length) {
      html += '<h6 class="text-muted mt-3 mb-2">Grade-level CBC expectations</h6><div class="table-responsive"><table class="pp-table table-sm"><thead><tr><th>Learning area</th><th>Grade</th><th>Expected outcome</th></tr></thead><tbody>';
      outcomes.forEach(function (o) {
        html += '<tr><td>' + P.esc(o.learning_area || '') + '</td><td>' + P.esc(d.context && d.context.class_name || '') + '</td><td>' + P.esc(o.outcome || '') + '</td></tr>';
      });
      html += '</tbody></table></div>';
    }
    el.innerHTML = html;
  }

  function renderQuestions(d, el) {
    var items = d.workbook_items || [];
    var mapped = items.filter(function (it) { return it.question_text || it.experience_text; });
    if (!mapped.length) {
      el.innerHTML = '<div class="alert alert-info">No practical questions or suggested experiences have been published yet.</div>';
      return;
    }
    var html = '<div class="table-responsive"><table class="pp-table table-sm"><thead><tr><th>Week</th><th>Learning area</th><th>Item</th><th>Practical question / experience</th></tr></thead><tbody>';
    mapped.forEach(function (it) {
      var text = it.question_text || it.experience_text || '';
      html += '<tr><td>' + P.esc(it.week_number ? 'Week ' + it.week_number : '') + '</td><td>' + P.esc(it.learning_area || '') + '</td><td>' + P.esc(it.item_title || '') + '</td><td>' + P.esc(text) + '</td></tr>';
    });
    html += '</tbody></table></div><div class="alert alert-success small mt-3"><i class="bi bi-lightbulb me-2"></i>These are suggested practical activities from the approved scheme of work — parents may support the learner at home.</div>';
    el.innerHTML = html;
  }

  P.childPage({
    contentId: 'ppLearningContent',
    defaultTab: 'assignments',
    loadTab: function (tab, child, el) {
      P.apiFetch('/student-learning/' + child.id, 'GET')
        .then(function (resp) {
          var d = resp.data !== undefined ? resp.data : resp;
          _data = d;
          if (tab === 'assignments') renderAssignments(d, el);
          else if (tab === 'objectives') renderObjectives(d, el);
          else if (tab === 'questions') renderQuestions(d, el);
        })
        .catch(function (e) { P.showError(el, e.message); });
    },
  });

  document.getElementById('btnLearningCsv').addEventListener('click', function () {
    if (!_data || !_data.assignments) return;
    var rows = _data.assignments.map(function (a) {
      return [a.title, a.learning_area, a.strand_name || '', a.sub_strand_name || '', a.due_date, a.submission_status || 'none', a.is_graded ? a.marks_awarded : ''];
    });
    var csv = P.csvFromHeaders(['Assignment', 'Learning area', 'Strand', 'Sub-strand', 'Due', 'Status', 'Marks'], rows);
    P.exportCsv('assignments.csv', csv);
  });

  document.getElementById('btnLearningPrint').addEventListener('click', function () { P.printSection(); });
})();