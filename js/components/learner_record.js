/**
 * Learner academic record view (shared by staff and parent pages).
 *
 * Renders the payload of GET /api/academic/learner-record/{id} (staff) or
 * GET /api/parent-portal/learner-record/{id} (parents): one section per
 * academic year in the class of that year, each learning area with Terms
 * 1-3, approved summative results and formative evidence by strand and
 * sub-strand, plus each learning area's trend across the years.
 *
 *   LearnerRecordView.render(container, record, { onPrint })
 */
(function (window) {
  'use strict';

  const esc = (value) => String(value ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c]);
  const pct = (value) => (value === null || value === undefined ? '—' : `${Number(value).toLocaleString('en-KE', { maximumFractionDigits: 1 })}%`);
  const statusText = { not_assessed: 'Not assessed', awaiting_approval: 'Awaiting approval', assessed: 'Assessed' };

  function termCell(term) {
    if (term.term_result) {
      return `<strong>${pct(term.term_result.overall_percentage)}</strong>${term.term_result.grade ? ` <span class="badge bg-success-subtle text-success-emphasis">${esc(term.term_result.grade)}</span>` : ''}`;
    }
    if (term.summative.length) {
      const scored = term.summative.map((s) => s.percentage).filter((v) => v !== null);
      return scored.length ? `Exam ${pct(scored.reduce((a, b) => a + b, 0) / scored.length)}` : 'Assessed';
    }
    if (term.formative_strands.length) return 'Formative only';
    if (term.status === 'awaiting_approval') return `<span class="text-warning-emphasis">Awaiting approval (${term.awaiting_approval})</span>`;
    return '<span class="text-muted">Not assessed</span>';
  }

  function areaDetails(area) {
    const parts = area.terms.map((term) => {
      const lines = [];
      term.summative.forEach((s) => lines.push(
        `<li><span class="badge bg-primary-subtle text-primary-emphasis me-1">Summative</span>${esc(s.title)}: <strong>${s.score ?? '—'} / ${s.out_of ?? '—'}</strong> (${pct(s.percentage)})${s.grade ? ` · ${esc(s.grade)}` : ''}${s.entry_status && s.entry_status !== 'present' ? ` · ${esc(s.entry_status)}` : ''}</li>`));
      term.formative_strands.forEach((strand) => strand.sub_strands.forEach((sub) => sub.evidence.forEach((e) => lines.push(
        `<li><span class="badge bg-info-subtle text-info-emphasis me-1">Formative</span>${esc(strand.strand)} › ${esc(sub.sub_strand)}: ${esc(e.title)} <strong>${e.score ?? '—'} / ${e.out_of ?? '—'}</strong> (${pct(e.percentage)})${e.grade ? ` · ${esc(e.grade)}` : ''}</li>`))));
      if (term.term_result) {
        lines.unshift(`<li>Term result: <strong>${pct(term.term_result.overall_percentage)}</strong>${term.term_result.grade ? ` (${esc(term.term_result.grade)})` : ''} · formative ${pct(term.term_result.formative_percentage)} · summative ${pct(term.term_result.summative_percentage)}</li>`);
      }
      if (!lines.length) lines.push(`<li class="text-muted">${statusText[term.status] || 'Not assessed'}${term.awaiting_approval ? ` (${term.awaiting_approval} awaiting approval)` : ''}</li>`);
      return `<div class="col-md-4"><div class="small fw-semibold mb-1">${esc(term.term)}</div><ul class="small ps-3 mb-2">${lines.join('')}</ul></div>`;
    });
    return `<div class="row g-2">${parts.join('')}</div>`;
  }

  function yearSection(year, open) {
    const termHeads = year.terms.map((t) => `<th class="text-center">${esc(t.term)}</th>`).join('');
    const rows = year.learning_areas.map((area, index) => `
      <tr>
        <th scope="row" class="fw-normal">${esc(area.name)}</th>
        ${area.terms.map((t) => `<td class="text-center">${termCell(t)}</td>`).join('')}
        <td class="text-end"><button type="button" class="btn btn-link btn-sm p-0" data-lr-toggle="${esc(year.academic_year_id)}-${index}">Details</button></td>
      </tr>
      <tr class="d-none" data-lr-detail="${esc(year.academic_year_id)}-${index}"><td colspan="${year.terms.length + 2}" class="bg-body-tertiary">${areaDetails(area)}</td></tr>`).join('');
    const status = year.enrollment_status ? ` · ${esc(year.enrollment_status)}` : '';
    return `
      <details class="lr-year border rounded-3 mb-3" ${open ? 'open' : ''}>
        <summary class="px-3 py-2 fw-semibold">${esc(year.academic_year)} · ${esc(year.class_name || 'Class not recorded')}${year.stream_name ? ` ${esc(year.stream_name)}` : ''}${status}${year.is_current ? ' <span class="badge bg-success ms-1">Current year</span>' : ''}</summary>
        <div class="px-3 pb-3">
          ${year.results_awaiting_approval ? `<div class="alert alert-warning py-2 small mb-2">${year.results_awaiting_approval} mark(s) are awaiting approval and are not shown as results yet.</div>` : ''}
          ${year.learning_areas.length ? `<div class="table-responsive"><table class="table table-sm align-middle mb-0" data-export-title="Academic record ${esc(year.academic_year)}">
            <thead><tr><th>Learning area</th>${termHeads}<th></th></tr></thead><tbody>${rows}</tbody></table></div>`
            : '<div class="text-muted small">No learning areas were set for this class in this year.</div>'}
        </div>
      </details>`;
  }

  function trendSection(record) {
    const trends = record.learning_area_trends || [];
    if (!trends.length) return '<div class="text-muted small">No term results have been approved yet, so there is no trend to show.</div>';
    const options = trends.map((t, i) => `<option value="${i}">${esc(t.learning_area)}</option>`).join('');
    return `<div class="d-flex flex-wrap gap-2 align-items-center mb-2"><label class="small text-muted" for="lrTrendArea">Learning area</label>
      <select class="form-select form-select-sm w-auto" id="lrTrendArea">${options}</select></div><div id="lrTrendTable"></div>`;
  }

  function renderTrend(record, container) {
    const select = container.querySelector('#lrTrendArea');
    const host = container.querySelector('#lrTrendTable');
    if (!select || !host) return;
    const draw = () => {
      const trend = record.learning_area_trends[Number(select.value)] || { points: [] };
      host.innerHTML = `<div class="table-responsive"><table class="table table-sm mb-0" data-export-title="${esc(trend.learning_area)} across the years">
        <thead><tr><th>Academic year</th><th>Class</th><th>Term</th><th class="text-end">Term result</th><th>Grade</th></tr></thead>
        <tbody>${trend.points.map((p) => `<tr><td>${esc(p.academic_year)}</td><td>${esc(p.class_name || '—')}</td><td>${esc(p.term)}</td><td class="text-end">${pct(p.overall_percentage)}</td><td>${esc(p.grade || '—')}</td></tr>`).join('')}</tbody></table></div>`;
    };
    select.addEventListener('change', draw);
    draw();
  }

  /** Every approved result as CSV rows: one line per result. */
  function csvRows(record) {
    const rows = [['Academic year', 'Class', 'Stream', 'Learning area', 'Term', 'Type', 'Strand', 'Sub-strand', 'Assessment', 'Score', 'Out of', 'Percentage', 'Grade']];
    record.years.forEach((y) => y.learning_areas.forEach((a) => a.terms.forEach((t) => {
      if (t.term_result) rows.push([y.academic_year, y.class_name, y.stream_name, a.name, t.term, 'Term result', '', '', '', '', '', t.term_result.overall_percentage, t.term_result.grade || '']);
      t.summative.forEach((s) => rows.push([y.academic_year, y.class_name, y.stream_name, a.name, t.term, 'Summative', '', '', s.title, s.score, s.out_of, s.percentage, s.grade || '']));
      t.formative_strands.forEach((st) => st.sub_strands.forEach((sub) => sub.evidence.forEach((e) => rows.push([y.academic_year, y.class_name, y.stream_name, a.name, t.term, 'Formative', st.strand, sub.sub_strand, e.title, e.score, e.out_of, e.percentage, e.grade || '']))));
    })));
    return rows;
  }

  async function exportCsv(record) {
    const cell = (v) => { const s = String(v ?? ''); return /[",\n\r]/.test(s) ? `"${s.replace(/"/g, '""')}"` : s; };
    const csv = csvRows(record).map((r) => r.map(cell).join(',')).join('\r\n');
    const name = `academic_record_${String(record.student.admission_no || record.student.id).replace(/[^A-Za-z0-9]+/g, '_')}.csv`;
    await window.KingswayFileLifecycle.exportText(`﻿${csv}`, name, 'text/csv;charset=utf-8');
  }

  function render(container, record, options = {}) {
    if (!container) return;
    const years = record?.years || [];
    const student = record?.student || {};
    const joined = student.joined ? `Joined in ${esc(student.joined.academic_year)}, ${esc(student.joined.class_name || 'class not recorded')}` : '';
    const canExport = window.AuthContext?.canExport ? window.AuthContext.canExport('academic') !== false : true;
    const canPrint = window.AuthContext?.canPrint ? window.AuthContext.canPrint('academic') !== false : true;
    container.innerHTML = `
      <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <div class="small text-muted">${joined}${joined ? ' · ' : ''}${years.length} academic year(s) on record. ${esc(record?.rules || '')}</div>
        <div class="d-flex gap-2">
          <button type="button" class="btn btn-sm btn-outline-secondary" data-lr-csv ${canExport ? '' : 'disabled'}><i class="bi bi-filetype-csv me-1"></i>CSV</button>
          <button type="button" class="btn btn-sm btn-outline-secondary" data-lr-print ${canPrint ? '' : 'disabled'}><i class="bi bi-printer me-1"></i>Print record</button>
        </div>
      </div>
      ${years.length ? [...years].reverse().map((y, i) => yearSection(y, i === 0)).join('') : '<div class="alert alert-info">No enrollment is on record for this learner.</div>'}
      <h6 class="mt-4">Learning areas across the years</h6>
      ${trendSection(record || {})}`;

    container.querySelectorAll('[data-lr-toggle]').forEach((button) => button.addEventListener('click', () => {
      container.querySelector(`[data-lr-detail="${button.dataset.lrToggle}"]`)?.classList.toggle('d-none');
    }));
    container.querySelector('[data-lr-csv]')?.addEventListener('click', () => exportCsv(record).catch(() => {}));
    container.querySelector('[data-lr-print]')?.addEventListener('click', () => (options.onPrint ? options.onPrint() : window.print()));
    renderTrend(record, container);
  }

  window.LearnerRecordView = { render, csvRows };
})(window);
