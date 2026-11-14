/**
 * Dashboard tabs: term ribbon, Reports and Export-and-print panes.
 * Markup: components/dashboards/_dashboard_tabs.php.
 *
 * - The term ribbon draws one segment per school week, counted with the same
 *   rule as AcademicCalendarService::countWeeks (a partial first week, then
 *   Monday–Friday weeks), so the week shown matches the school calendar.
 * - Reports: the governed reports the server authorizes for this user run
 *   for one academic year + term and render in place (no navigation).
 * - Export: each governed report downloads/prints in place for that scope,
 *   and the "on this dashboard" list is built from tables carrying
 *   data-export-title. CSV exports what the table shows; Print uses the
 *   school's letterhead through PrintManager. AuthContext.canExport/canPrint
 *   gate each action by the table's data-export-module.
 */
(function (window, document) {
  'use strict';

  const DAY_MS = 86400000;

  // ---- Term ribbon --------------------------------------------------------

  function parseDate(value) {
    const match = /^(\d{4})-(\d{2})-(\d{2})/.exec(String(value || ''));
    return match ? new Date(Number(match[1]), Number(match[2]) - 1, Number(match[3])) : null;
  }

  function addDays(date, days) {
    const next = new Date(date);
    next.setDate(next.getDate() + days);
    return next;
  }

  /** ISO weekday: Monday 1 … Sunday 7. */
  function isoDay(date) {
    return date.getDay() === 0 ? 7 : date.getDay();
  }

  /** Port of AcademicCalendarService::countWeeks, returning the buckets. */
  function schoolWeeks(opening, closing) {
    const weeks = [];
    let cursor = new Date(opening);
    while (cursor <= closing && weeks.length < 60) {
      const dow = isoDay(cursor);
      let end = addDays(cursor, dow <= 5 ? 5 - dow : 7 - dow);
      if (end > closing) end = new Date(closing);
      weeks.push({ start: new Date(cursor), end });
      cursor = addDays(end, 1);
      if (isoDay(cursor) >= 6) cursor = addDays(cursor, 8 - isoDay(cursor));
    }
    return weeks;
  }

  function formatDay(date) {
    return date.toLocaleDateString(undefined, { day: 'numeric', month: 'short' });
  }

  /** "tomorrow", "in 4 days", "in 7 weeks": how far away a date is. */
  function distance(from, to) {
    const days = Math.round((to - from) / DAY_MS);
    if (days <= 1) return 'tomorrow';
    if (days < 14) return `in ${days} days`;
    return `in ${Math.round(days / 7)} weeks`;
  }

  async function loadTerms() {
    try {
      const response = await window.API?.academic?.getTerms?.();
      const rows = response?.terms || response?.data?.terms || response?.data || response;
      if (!Array.isArray(rows)) return [];
      return rows.map((row) => ({
        id: row.id,
        termNumber: Number(row.term_number || row.term_id) || null,
        name: row.name || `Term ${row.term_number}`,
        yearId: row.academic_year_id,
        yearName: row.year_name || '',
        serverWeeks: Number(row.weeks) || null,
        opening: parseDate(row.opening_date || row.start_date),
        closing: parseDate(row.closing_date || row.end_date),
        breakStart: parseDate(row.half_term_start),
        breakEnd: parseDate(row.half_term_end),
      }))
        .filter((term) => term.opening && term.closing && term.closing >= term.opening)
        .sort((x, y) => x.opening - y.opening);
    } catch (_) {
      return [];
    }
  }

  /**
   * Which school year to draw and what to say about today:
   *   in a term     -> that year; "Week n of N" (or the half-term break)
   *   between terms -> the year of the next term; when it opens
   *   no next term  -> the last year; that nothing is scheduled yet
   */
  function describeToday(terms, today) {
    const current = terms.find((term) => term.opening <= today && today <= term.closing);
    const next = terms.find((term) => term.opening > today);
    const focus = current || next || terms[terms.length - 1];
    return { current, next, yearId: focus.yearId, yearName: focus.yearName };
  }

  function weekSegments(term, today) {
    const weeks = schoolWeeks(term.opening, term.closing);
    let currentIndex = -1;
    if (today <= term.closing) {
      weeks.forEach((week, index) => { if (week.start <= today) currentIndex = index; });
    }
    return weeks.map((week, index) => {
      let state = 'ahead';
      let fill = 0;
      if (today > term.closing || index < currentIndex) {
        state = 'done';
        fill = 1;
      } else if (index === currentIndex) {
        state = 'now';
        const span = Math.max(1, Math.round((week.end - week.start) / DAY_MS) + 1);
        fill = Math.min(span, Math.round((today - week.start) / DAY_MS) + 1) / span;
      }
      const onBreak = Boolean(term.breakStart && term.breakEnd
        && week.start <= term.breakEnd && week.end >= term.breakStart);
      return { ...week, index, state, fill, onBreak };
    });
  }

  async function renderTerm(root) {
    const box = root.querySelector('[data-kw-term]');
    if (!box) return;
    const terms = await loadTerms();
    if (!terms.length) return; // no calendar configured: the ribbon stays hidden

    const today = new Date();
    today.setHours(0, 0, 0, 0);
    const view = describeToday(terms, today);
    const yearTerms = terms.filter((term) => term.yearId === view.yearId);

    const nameEl = box.querySelector('[data-kw-term-name]');
    const weekEl = box.querySelector('[data-kw-term-week]');
    weekEl.textContent = '';
    let summary;
    if (view.current) {
      const segments = weekSegments(view.current, today);
      const now = segments.find((segment) => segment.state === 'now');
      const number = now ? now.index + 1 : segments.length;
      nameEl.textContent = view.current.name;
      const strong = document.createElement('strong');
      strong.textContent = `Week ${number}`;
      weekEl.append(strong, ` of ${segments.length}`);
      if (now?.onBreak) weekEl.append(', half-term break');
      summary = `${view.current.name}, week ${number} of ${segments.length}`;
    } else if (view.next) {
      nameEl.textContent = 'School holiday';
      weekEl.textContent = `${view.next.name} opens ${formatDay(view.next.opening)}, ${distance(today, view.next.opening)}`;
      summary = `School holiday. ${weekEl.textContent}`;
    } else {
      nameEl.textContent = 'School holiday';
      weekEl.textContent = 'The next term has not been scheduled yet';
      summary = `School holiday. ${weekEl.textContent}`;
    }
    box.querySelector('[data-kw-term-year]').textContent = view.yearName;

    const year = box.querySelector('[data-kw-year]');
    year.innerHTML = '';
    yearTerms.forEach((term) => {
      const segments = weekSegments(term, today);
      const group = document.createElement('div');
      group.className = 'kw-year-term';
      if (term === view.current) group.classList.add('is-current');
      group.style.flexGrow = String(segments.length);

      const track = document.createElement('div');
      track.className = 'kw-term-track';
      segments.forEach((segment) => {
        const el = document.createElement('span');
        el.className = `kw-term-seg is-${segment.state}${segment.onBreak ? ' is-break' : ''}`;
        el.style.setProperty('--fill', segment.fill.toFixed(3));
        el.title = `${term.name}, week ${segment.index + 1}: ${formatDay(segment.start)} to ${formatDay(segment.end)}`
          + (segment.onBreak ? ' (half-term break)' : '');
        track.appendChild(el);
      });

      const label = document.createElement('div');
      label.className = 'kw-year-label';
      const termName = document.createElement('span');
      termName.className = 'kw-year-name';
      termName.textContent = term.name;
      const dates = document.createElement('span');
      dates.className = 'kw-year-dates';
      dates.textContent = `${formatDay(term.opening)} – ${formatDay(term.closing)}`;
      label.append(termName, dates);

      group.dataset.weeks = String(segments.length);
      if (term.serverWeeks) group.dataset.serverWeeks = String(term.serverWeeks);
      group.append(track, label);
      year.appendChild(group);
    });
    year.setAttribute('aria-label', `${view.yearName} school year. ${summary}.`);
    box.hidden = false;
  }

  // ---- Reports: governed reports rendered in place ------------------------
  //
  // The scope is never chosen by hand: the current academic year and its
  // reporting term (in session today, else the latest that has opened).
  // Every figure is for ONE academic year and ONE term of that year. Term
  // numbers repeat every year, so a term is identified by its academic-year
  // term id; reports whose data carries only the term number are narrowed
  // to the year by the year on each row.

  const scope = { years: [], terms: [], yearId: '', termId: '' };
  const resultCache = new Map();
  let catalogue = null;

  function isoDate(date) {
    if (!date) return '';
    const pad = (n) => String(n).padStart(2, '0');
    return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}`;
  }

  function humanLabel(key) {
    return String(key || '')
      .replace(/_id$/, '')
      .replace(/_/g, ' ')
      .replace(/\b\w/g, (letter) => letter.toUpperCase());
  }

  function displayValue(value) {
    if (value === null || value === undefined || value === '') return '—';
    if (typeof value === 'object') return JSON.stringify(value);
    if (typeof value === 'number' || (typeof value === 'string' && /^-?\d+(\.\d+)?$/.test(value.trim()))) {
      const number = Number(value);
      return Number.isInteger(number)
        ? number.toLocaleString('en-KE')
        : number.toLocaleString('en-KE', { maximumFractionDigits: 2 });
    }
    return String(value);
  }

  async function loadScope() {
    if (scope.years.length) return;
    try {
      const response = await window.API?.academic?.listYears?.();
      const rows = response?.data ?? response;
      scope.years = Array.isArray(rows) ? rows : [];
    } catch (_) {
      scope.years = [];
    }
    scope.terms = await loadTerms();
    const current = scope.years.find((year) => Number(year.is_current) === 1) || scope.years[0];
    scope.yearId = current ? String(current.id) : '';
    scope.termId = defaultTermId(scope.yearId);
  }

  /** In session today, else the latest term that has opened, else the first. */
  function defaultTermId(yearId) {
    const today = new Date();
    today.setHours(0, 0, 0, 0);
    const terms = scope.terms.filter((term) => String(term.yearId) === String(yearId));
    const pick = terms.find((term) => term.opening <= today && today <= term.closing)
      || terms.filter((term) => term.opening <= today).pop()
      || terms[0];
    return pick ? String(pick.id) : '';
  }

  function selectedYear() {
    return scope.years.find((year) => String(year.id) === String(scope.yearId)) || null;
  }

  function selectedTerm() {
    return scope.terms.find((term) => String(term.id) === String(scope.termId)) || null;
  }

  function scopeKey() {
    return `${scope.yearId}|${scope.termId}`;
  }

  function scopeLabel() {
    const year = selectedYear();
    const term = selectedTerm();
    return [year?.year_name || year?.year_code || '', term ? term.name : 'Whole year'].filter(Boolean).join(' · ');
  }

  /** The filters a report accepts, filled from the chosen year and term. */
  function filtersFor(report) {
    const allowed = Array.isArray(report.allowed_filters) ? report.allowed_filters : [];
    const year = selectedYear();
    const term = selectedTerm();
    const yearStart = String(year?.start_date || year?.year_code || '').match(/\d{4}/)?.[0] || '';
    const from = term ? term.opening : (year?.start_date ? parseDate(year.start_date) : null);
    const to = term ? term.closing : (year?.end_date ? parseDate(year.end_date) : null);
    const filters = {};
    allowed.forEach((key) => {
      if (key === 'academic_year_id' && scope.yearId) filters[key] = scope.yearId;
      else if (key === 'academic_term_id' && term) filters[key] = String(term.id);
      // These reports' data carries only the term number (1/2/3); the
      // chosen year is applied to their rows in narrowToYear().
      else if (key === 'term_id' && term?.termNumber) filters[key] = String(term.termNumber);
      else if (key === 'year' && yearStart) filters[key] = yearStart;
      else if (/(^|_)date_from$/.test(key) && from) filters[key] = isoDate(from);
      else if (/(^|_)date_to$/.test(key) && to) filters[key] = isoDate(to);
    });
    return filters;
  }

  /** Keep only rows of the chosen academic year when rows name their year. */
  function narrowToYear(rows) {
    const year = selectedYear();
    const code = String(year?.year_code || year?.year_name || '');
    if (!code || !rows.length) return rows;
    const key = ['academic_year', 'year_code', 'year_name'].find((name) => name in rows[0]);
    if (!key || !String(rows[0][key] ?? '').includes('/')) return rows;
    return rows.filter((row) => String(row[key] ?? '') === code);
  }

  async function loadCatalogue() {
    if (catalogue) return catalogue;
    try {
      const response = await window.API?.reports?.getCatalogue?.();
      const rows = response?.data ?? response;
      catalogue = Array.isArray(rows) ? rows : [];
    } catch (_) {
      catalogue = [];
    }
    return catalogue;
  }

  /** Run one report for the current scope (cached per scope). */
  function runReport(report) {
    const key = `${report.code}|${scopeKey()}`;
    if (!resultCache.has(key)) {
      const promise = (async () => {
        const response = await window.API.reports.execute(report.code, filtersFor(report));
        const result = response?.data ?? response ?? {};
        const rows = narrowToYear(Array.isArray(result.rows) ? result.rows : []);
        return { ...result, rows, row_count: rows.length };
      })();
      promise.catch(() => resultCache.delete(key));
      resultCache.set(key, promise);
    }
    return resultCache.get(key);
  }

  function columnsOf(result) {
    const rows = result.rows || [];
    if (!rows.length) return [];
    const available = new Set(Object.keys(rows[0]));
    const configured = (Array.isArray(result.columns) ? result.columns : [])
      .map((column) => (typeof column === 'string' ? { key: column } : column))
      .filter((column) => column && available.has(column.key));
    const columns = configured.length ? configured : [...available].map((key) => ({ key }));
    return columns.map((column) => ({ key: column.key, label: column.label || humanLabel(column.key) }));
  }

  function kpisOf(result) {
    const kpis = Object.entries(result.summary || {})
      .filter(([, value]) => value !== null && typeof value !== 'object')
      .filter(([, value]) => typeof value === 'number' || /^-?\d+(\.\d+)?$/.test(String(value).trim()))
      .slice(0, 4)
      .map(([key, value]) => ({ label: humanLabel(key), value: displayValue(value) }));
    kpis.unshift({ label: 'Records', value: displayValue(result.row_count || 0) });
    return kpis;
  }

  function exportOptions(result) {
    const report = result.report || {};
    return {
      title: report.title,
      subtitle: `${scopeLabel()} · As of ${new Date(result.as_of || Date.now()).toLocaleString('en-KE')}`,
      description: report.decision_purpose,
      rows: result.rows || [],
      columns: columnsOf(result),
      summary: result.summary || {},
      filters: result.filters || {},
      filename: String(report.code || 'report').toLowerCase(),
      orientation: columnsOf(result).length > 6 ? 'landscape' : 'portrait',
      analyticsRunId: result.run?.id,
      analyticsReportCode: report.code,
      analyticsReportVersion: report.version,
    };
  }

  function canExportResult(result, format) {
    const permitted = Array.isArray(result.permitted_exports) ? result.permitted_exports : [];
    return permitted.includes(format) && (result.rows || []).length > 0;
  }

  async function exportResult(result, format) {
    const options = exportOptions(result);
    if (format === 'csv') {
      if (window.PrintManager?.exportToServerCSV) {
        await window.PrintManager.exportToServerCSV(options);
      } else {
        const header = options.columns.map((column) => csvValue(column.label)).join(',');
        const body = options.rows.map((row) => options.columns.map((column) => csvValue(row[column.key])).join(','));
        await window.KingswayFileLifecycle.exportText(`﻿${[header, ...body].join('\r\n')}`, `${options.filename}.csv`, 'text/csv;charset=utf-8');
      }
    } else if (window.PrintManager?.printTable) {
      await window.PrintManager.printTable(options);
    } else {
      window.print();
    }
  }

  function actionButton(icon, text, enabled, title, run) {
    const button = document.createElement('button');
    button.type = 'button';
    button.className = 'btn btn-sm btn-outline-secondary';
    button.innerHTML = `<i class="bi ${icon} me-1" aria-hidden="true"></i>`;
    button.append(text);
    if (!enabled) {
      button.disabled = true;
      button.title = title;
      return button;
    }
    button.addEventListener('click', async () => {
      button.disabled = true;
      try { await run(); } catch (_) { notify(`Could not finish: ${text.toLowerCase()}.`, 'error'); }
      finally { button.disabled = false; }
    });
    return button;
  }

  function renderCardResult(card, result) {
    const body = card.querySelector('[data-kw-rcard-body]');
    body.replaceChildren();

    const kpis = document.createElement('div');
    kpis.className = 'kw-rcard-kpis';
    kpisOf(result).forEach((kpi) => {
      const item = document.createElement('div');
      item.className = 'kw-rcard-kpi';
      const label = document.createElement('span');
      label.className = 'kw-rcard-kpi-label';
      label.textContent = kpi.label;
      const value = document.createElement('span');
      value.className = 'kw-rcard-kpi-value';
      value.textContent = kpi.value;
      item.append(label, value);
      kpis.appendChild(item);
    });
    body.appendChild(kpis);

    const rows = result.rows || [];
    if (!rows.length) {
      const empty = document.createElement('div');
      empty.className = 'kw-rcard-state';
      empty.textContent = `No records for ${scopeLabel()}.`;
      body.appendChild(empty);
    } else {
      const columns = columnsOf(result);
      const wrap = document.createElement('div');
      wrap.className = 'kw-rcard-table';
      const table = document.createElement('table');
      table.className = 'table table-sm table-striped';
      const head = document.createElement('thead');
      const headRow = document.createElement('tr');
      columns.forEach((column) => {
        const th = document.createElement('th');
        th.scope = 'col';
        th.textContent = column.label;
        headRow.appendChild(th);
      });
      head.appendChild(headRow);
      const tbody = document.createElement('tbody');
      const preview = 6;
      const drawRows = (all) => {
        tbody.replaceChildren(...rows.slice(0, all ? rows.length : preview).map((row) => {
          const tr = document.createElement('tr');
          columns.forEach((column) => {
            const td = document.createElement('td');
            td.textContent = displayValue(row[column.key]);
            tr.appendChild(td);
          });
          return tr;
        }));
      };
      drawRows(false);
      table.append(head, tbody);
      wrap.appendChild(table);
      body.appendChild(wrap);
      if (rows.length > preview) {
        const toggle = document.createElement('button');
        toggle.type = 'button';
        toggle.className = 'btn btn-link btn-sm px-0';
        let expanded = false;
        toggle.textContent = `Show all ${rows.length} rows`;
        toggle.addEventListener('click', () => {
          expanded = !expanded;
          drawRows(expanded);
          toggle.textContent = expanded ? 'Show fewer rows' : `Show all ${rows.length} rows`;
        });
        body.appendChild(toggle);
      }
    }

    const foot = document.createElement('div');
    foot.className = 'kw-rcard-foot';
    const asOf = document.createElement('span');
    asOf.textContent = `${scopeLabel()} · as of ${new Date(result.as_of || Date.now()).toLocaleTimeString('en-KE', { hour: '2-digit', minute: '2-digit' })}`;
    const actions = document.createElement('div');
    actions.className = 'kw-export-actions';
    actions.append(
      actionButton('bi-filetype-csv', 'CSV', canExportResult(result, 'csv'), 'Not available for this report or there are no rows.', () => exportResult(result, 'csv')),
      actionButton('bi-printer', 'Print', canExportResult(result, 'pdf'), 'Not available for this report or there are no rows.', () => exportResult(result, 'pdf')),
    );
    foot.append(asOf, actions);
    body.appendChild(foot);
  }

  function reportCard(report) {
    const card = document.createElement('article');
    card.className = 'kw-rcard';
    card.dataset.kwReportCode = report.code;
    const domain = document.createElement('div');
    domain.className = 'kw-rcard-domain';
    domain.textContent = humanLabel(report.domain || report.category || 'Report');
    const title = document.createElement('h3');
    title.className = 'kw-rcard-title';
    title.textContent = report.title;
    const purpose = document.createElement('p');
    purpose.className = 'kw-rcard-purpose';
    purpose.textContent = report.decision_purpose || report.description || '';
    const body = document.createElement('div');
    body.dataset.kwRcardBody = '';
    card.append(domain, title, purpose, body);
    return card;
  }

  function setCardState(card, message, isError) {
    const body = card.querySelector('[data-kw-rcard-body]');
    const state = document.createElement('div');
    state.className = `kw-rcard-state${isError ? ' is-error' : ''}`;
    state.textContent = message;
    body.replaceChildren(state);
  }

  /** Run reports a few at a time so the dashboard never floods the server. */
  async function runQueue(items, worker, concurrency = 3) {
    let next = 0;
    const lanes = Array.from({ length: Math.min(concurrency, items.length) }, async () => {
      while (next < items.length) {
        const item = items[next++];
        await worker(item);
      }
    });
    await Promise.all(lanes);
  }

  // ---- Year / term comparison ---------------------------------------------

  function formatMeasure(value, kind) {
    if (value === null || value === undefined) return '—';
    const number = Number(value);
    if (!Number.isFinite(number)) return String(value);
    if (kind === 'money') return `KES ${number.toLocaleString('en-KE', { maximumFractionDigits: 0 })}`;
    if (kind === 'percent') return `${number.toLocaleString('en-KE', { maximumFractionDigits: 1 })}%`;
    return number.toLocaleString('en-KE');
  }

  /** Change from the earlier period; percentages change in points. */
  function formatChange(from, to, kind) {
    if (from === null || from === undefined || to === null || to === undefined) return { text: '—', tone: '' };
    const delta = Number(to) - Number(from);
    if (!Number.isFinite(delta)) return { text: '—', tone: '' };
    if (delta === 0) return { text: 'No change', tone: '' };
    const sign = delta > 0 ? '+' : '−';
    const size = Math.abs(delta);
    let text;
    if (kind === 'percent') text = `${sign}${size.toLocaleString('en-KE', { maximumFractionDigits: 1 })} pts`;
    else if (kind === 'money') text = `${sign}KES ${size.toLocaleString('en-KE', { maximumFractionDigits: 0 })}`;
    else text = `${sign}${size.toLocaleString('en-KE')}`;
    return { text, tone: delta > 0 ? 'is-up' : 'is-down' };
  }

  function comparisonTable(caption, fromLabel, toLabel, rows, single) {
    const wrap = document.createElement('div');
    wrap.className = 'kw-rcard-table';
    const table = document.createElement('table');
    table.className = 'table table-sm mb-0 kw-compare-table';
    table.dataset.exportTitle = caption;
    const cap = document.createElement('caption');
    cap.className = 'visually-hidden';
    cap.textContent = caption;
    const head = document.createElement('thead');
    const headRow = document.createElement('tr');
    const headers = single ? ['Measure', toLabel] : ['Measure', fromLabel || 'Earlier', toLabel, 'Change'];
    headers.forEach((text, index) => {
      const th = document.createElement('th');
      th.scope = 'col';
      th.textContent = text;
      if (index > 0) th.className = 'text-end';
      headRow.appendChild(th);
    });
    head.appendChild(headRow);
    const body = document.createElement('tbody');
    rows.forEach((row) => {
      const tr = document.createElement('tr');
      const name = document.createElement('th');
      name.scope = 'row';
      name.textContent = row.label;
      tr.appendChild(name);
      const cells = single
        ? [formatMeasure(row.to, row.kind)]
        : [formatMeasure(row.from, row.kind), formatMeasure(row.to, row.kind)];
      cells.forEach((text) => {
        const td = document.createElement('td');
        td.className = 'text-end';
        td.textContent = text;
        tr.appendChild(td);
      });
      if (!single) {
        const change = formatChange(row.from, row.to, row.kind);
        const td = document.createElement('td');
        td.className = `text-end kw-change ${change.tone}`;
        td.textContent = change.text;
        tr.appendChild(td);
      }
      body.appendChild(tr);
    });
    table.append(cap, head, body);
    wrap.appendChild(table);
    return wrap;
  }

  function comparisonCard(title, subtitle, content, exportRows) {
    const card = document.createElement('article');
    card.className = 'kw-rcard';
    const heading = document.createElement('h3');
    heading.className = 'kw-rcard-title';
    heading.textContent = title;
    const sub = document.createElement('p');
    sub.className = 'kw-rcard-purpose';
    sub.textContent = subtitle;
    card.append(heading, sub, content);
    if (exportRows) {
      const foot = document.createElement('div');
      foot.className = 'kw-rcard-foot';
      const actions = document.createElement('div');
      actions.className = 'kw-export-actions';
      const canExport = window.AuthContext?.canExport ? window.AuthContext.canExport('reports') !== false : true;
      const canPrint = window.AuthContext?.canPrint ? window.AuthContext.canPrint('reports') !== false : true;
      actions.append(
        actionButton('bi-filetype-csv', 'CSV', canExport, 'Your account cannot download this data.', () => exportRows('csv')),
        actionButton('bi-printer', 'Print', canPrint, 'Your account cannot print this data.', () => exportRows('pdf')),
      );
      foot.append(document.createElement('span'), actions);
      card.appendChild(foot);
    }
    return card;
  }

  /** Export a comparison table directly (CSV via the file lifecycle, or print). */
  async function exportComparison(title, columns, rows, format) {
    const filename = slug(title);
    if (format === 'csv') {
      const lines = [columns.map((c) => csvValue(c.label)).join(','), ...rows.map((row) => columns.map((c) => csvValue(row[c.key])).join(','))];
      await window.KingswayFileLifecycle.exportText(`﻿${lines.join('\r\n')}`, `${filename}_${new Date().toISOString().slice(0, 10)}.csv`, 'text/csv;charset=utf-8');
    } else if (window.PrintManager?.printTable) {
      await window.PrintManager.printTable({ title, columns, rows, orientation: 'portrait' });
    } else {
      window.print();
    }
  }

  async function renderComparison(pane) {
    const box = pane?.querySelector('[data-kw-compare]');
    if (!box) return;
    let data;
    try {
      const response = await window.API.reports.getYearComparison();
      data = response?.data ?? response ?? {};
    } catch (error) {
      box.innerHTML = '';
      const state = document.createElement('div');
      state.className = 'kw-rcard-state is-error';
      state.textContent = error?.message || 'The comparison could not be produced.';
      box.appendChild(state);
      return;
    }
    const lede = pane.querySelector('[data-kw-compare-lede]');
    const years = data.years || {};
    const terms = data.terms || {};
    if (lede) {
      const yearText = years.to
        ? (data.year_mode === 'transition'
          ? `Outgoing year ${years.from?.label || '—'} against incoming year ${years.to.label}`
          : `${years.from ? `Previous year ${years.from.label}` : 'No previous year'} against current year ${years.to.label}`)
        : 'No academic year is set as current';
      const termText = terms.to
        ? (data.term_mode === 'transition'
          ? `the term that closed (${terms.from?.label || '—'}) against the next term (${terms.to.label})`
          : `${terms.from ? `the previous term (${terms.from.label})` : 'no previous term'} against the current term (${terms.to.label})`)
        : 'no term is scheduled';
      lede.textContent = `${yearText}; ${termText}. ${data.counting_rule || ''}`;
    }

    const cards = [];
    (data.sections || []).forEach((section) => {
      const fromLabel = section.from?.label || '';
      const toLabel = section.to?.label || '';
      const single = section.level === 'movement';
      const rows = single
        ? (section.measures || []).map((m) => ({ label: m.label, to: m.value, kind: m.kind }))
        : (section.measures || []);
      if (!rows.length) return;
      const subtitle = single
        ? `${fromLabel} to ${toLabel}`
        : (fromLabel ? `${fromLabel} compared with ${toLabel}` : toLabel);
      const columns = single
        ? [{ key: 'label', label: 'Measure' }, { key: 'to', label: 'Learners' }]
        : [{ key: 'label', label: 'Measure' }, { key: 'from', label: fromLabel || 'Earlier' }, { key: 'to', label: toLabel }, { key: 'change', label: 'Change' }];
      const exportRows = rows.map((row) => ({
        label: row.label,
        from: formatMeasure(row.from, row.kind),
        to: formatMeasure(row.to, row.kind),
        change: formatChange(row.from, row.to, row.kind).text,
      }));
      cards.push(comparisonCard(
        section.title,
        subtitle,
        comparisonTable(section.title, fromLabel, single ? 'Learners' : toLabel, rows, single),
        (format) => exportComparison(`${section.title} ${subtitle}`, columns, exportRows, format)
      ));
    });

    if (Array.isArray(data.by_class) && data.by_class.length) {
      const fromLabel = years.from?.label || '';
      const toLabel = years.to?.label || '';
      const rows = data.by_class.map((row) => ({ label: row.class, from: row.from, to: row.to, kind: 'count' }));
      const columns = [{ key: 'label', label: 'Class' }, { key: 'from', label: fromLabel }, { key: 'to', label: toLabel }, { key: 'change', label: 'Change' }];
      const exportRows = rows.map((row) => ({ label: row.label, from: formatMeasure(row.from), to: formatMeasure(row.to), change: formatChange(row.from, row.to, 'count').text }));
      cards.push(comparisonCard(
        'Learners per class',
        `${fromLabel} compared with ${toLabel}. Each learner is counted in the class of that year.`,
        comparisonTable('Learners per class', fromLabel, toLabel, rows, false),
        (format) => exportComparison(`Learners per class ${fromLabel} to ${toLabel}`, columns, exportRows, format)
      ));
    }

    box.replaceChildren();
    const grid = document.createElement('div');
    grid.className = 'kw-report-grid';
    grid.append(...cards);
    box.appendChild(grid);
  }

  async function renderReports(pane) {
    const grid = pane?.querySelector('[data-kw-report-grid]');
    if (!grid) return;
    const reports = await loadCatalogue();
    const empty = pane.querySelector('[data-kw-report-empty]');
    if (empty) empty.hidden = reports.length > 0;
    if (!reports.length) {
      grid.replaceChildren();
      return;
    }
    const key = scopeKey();
    grid.dataset.kwScope = key;
    const scopeNote = pane.querySelector('[data-kw-report-scope]');
    if (scopeNote) scopeNote.textContent = `· ${scopeLabel()}`;
    const cards = reports.map((report) => {
      const card = reportCard(report);
      setCardState(card, 'Loading…', false);
      return [report, card];
    });
    grid.replaceChildren(...cards.map(([, card]) => card));
    await runQueue(cards, async ([report, card]) => {
      try {
        const result = await runReport(report);
        if (grid.dataset.kwScope !== key) return; // the filters changed meanwhile
        renderCardResult(card, result);
      } catch (error) {
        if (grid.dataset.kwScope !== key) return;
        setCardState(card, error?.message || 'This report could not be produced.', true);
      }
    });
  }

  /** Export pane: every governed report, downloadable without leaving. */
  async function renderDatasets(pane) {
    const list = pane?.querySelector('[data-kw-dataset-list]');
    if (!list) return;
    const reports = await loadCatalogue();
    list.replaceChildren(...reports.map((report) => {
      const item = document.createElement('li');
      item.className = 'kw-export-row';
      const label = document.createElement('div');
      label.className = 'kw-export-label';
      const name = document.createElement('span');
      name.className = 'kw-export-name';
      name.textContent = report.title;
      const meta = document.createElement('span');
      meta.className = 'kw-export-count';
      meta.textContent = scopeLabel();
      label.append(name, meta);
      const formats = Array.isArray(report.export_formats) ? report.export_formats : [];
      const run = (format) => async () => {
        const result = await runReport(report);
        if (!canExportResult(result, format)) {
          notify(`${report.title} has no rows for ${scopeLabel()}.`, 'warning');
          return;
        }
        await exportResult(result, format);
      };
      const actions = document.createElement('div');
      actions.className = 'kw-export-actions';
      actions.append(
        actionButton('bi-filetype-csv', 'Download CSV', formats.includes('csv'), 'This report cannot be downloaded.', run('csv')),
        actionButton('bi-printer', 'Print', formats.includes('pdf'), 'This report cannot be printed.', run('pdf')),
      );
      item.append(label, actions);
      return item;
    }));
  }

  // ---- Export and print ---------------------------------------------------

  /** A cell's readable text, skipping decoration hidden from assistive tech
   *  (avatar initials, icons) so "JK Joseph Kiprop" exports as the name. */
  function cellText(cell) {
    const copy = cell.cloneNode(true);
    copy.querySelectorAll('[aria-hidden="true"]').forEach((node) => node.remove());
    return String(copy.textContent || '').replace(/\s+/g, ' ').trim();
  }

  /** Header labels and the rows the table currently shows. */
  function readTable(table) {
    const headers = Array.from(table.querySelectorAll('thead th')).map(cellText);
    // Searches hide rows with style.display or the hidden/d-none markers. The
    // overview pane itself is hidden while this tab is open, so layout-based
    // visibility checks would report every row as hidden.
    const rows = Array.from(table.querySelectorAll('tbody tr'))
      .filter((row) => !row.hidden && row.style.display !== 'none' && !row.classList.contains('d-none'))
      .map((row) => Array.from(row.cells).map(cellText))
      // Placeholder rows ("Loading…", "No records") span every column.
      .filter((cells) => cells.length === headers.length && cells.some(Boolean));
    return { headers, rows };
  }

  function csvValue(value) {
    const text = String(value ?? '');
    return /[",\n\r]/.test(text) ? `"${text.replace(/"/g, '""')}"` : text;
  }

  function slug(text) {
    return String(text).toLowerCase().replace(/[^a-z0-9]+/g, '_').replace(/^_|_$/g, '');
  }

  function allowed(kind, module) {
    const auth = window.AuthContext;
    const check = kind === 'export' ? auth?.canExport : auth?.canPrint;
    return typeof check !== 'function' || !module || check.call(auth, module);
  }

  function notify(message, type) {
    if (typeof window.showNotification === 'function') window.showNotification(message, type);
  }

  async function downloadCsv(table) {
    const title = table.dataset.exportTitle;
    const { headers, rows } = readTable(table);
    if (!rows.length) {
      notify(`${title} has no rows to download.`, 'warning');
      return;
    }
    const csv = [headers, ...rows].map((line) => line.map(csvValue).join(',')).join('\r\n');
    const date = new Date().toISOString().slice(0, 10);
    await window.KingswayFileLifecycle.exportText(`﻿${csv}`, `${slug(title)}_${date}.csv`, 'text/csv;charset=utf-8');
  }

  async function printTable(table) {
    const title = table.dataset.exportTitle;
    const { headers, rows } = readTable(table);
    if (!rows.length) {
      notify(`${title} has no rows to print.`, 'warning');
      return;
    }
    if (!window.PrintManager?.printTable) {
      window.print();
      return;
    }
    const columns = headers.map((label, index) => ({ key: `c${index}`, label }));
    const records = rows.map((cells) => Object.fromEntries(cells.map((value, index) => [`c${index}`, value])));
    await window.PrintManager.printTable({ title, columns, rows: records });
  }

  function exportRow(table) {
    const title = table.dataset.exportTitle;
    const module = table.dataset.exportModule || '';
    const count = readTable(table).rows.length;

    const item = document.createElement('li');
    item.className = 'kw-export-row';
    const label = document.createElement('div');
    label.className = 'kw-export-label';
    const name = document.createElement('span');
    name.className = 'kw-export-name';
    name.textContent = title;
    const meta = document.createElement('span');
    meta.className = 'kw-export-count';
    meta.textContent = count === 1 ? '1 row on the overview' : `${count} rows on the overview`;
    label.append(name, meta);

    const actions = document.createElement('div');
    actions.className = 'kw-export-actions';
    const makeButton = (kind, icon, text, run) => {
      const button = document.createElement('button');
      button.type = 'button';
      button.className = 'btn btn-sm btn-outline-secondary';
      button.innerHTML = `<i class="bi ${icon} me-1" aria-hidden="true"></i>`;
      button.append(text);
      if (!allowed(kind, module)) {
        button.disabled = true;
        button.title = kind === 'export'
          ? 'Your account cannot download this data.'
          : 'Your account cannot print this data.';
      } else if (count === 0) {
        button.disabled = true;
        button.title = 'This table has no rows yet.';
      } else {
        button.addEventListener('click', async () => {
          button.disabled = true;
          try { await run(table); } catch (_) { notify(`Could not finish: ${text.toLowerCase()}.`, 'error'); }
          finally { button.disabled = false; }
        });
      }
      return button;
    };
    actions.append(
      makeButton('export', 'bi-filetype-csv', 'Download CSV', downloadCsv),
      makeButton('print', 'bi-printer', 'Print', printTable),
    );
    item.append(label, actions);
    return item;
  }

  function renderExports(pane, overview) {
    const list = pane?.querySelector('[data-kw-export-list]');
    if (!list || !overview) return;
    list.innerHTML = '';
    overview.querySelectorAll('table[data-export-title]').forEach((table) => list.appendChild(exportRow(table)));
  }

  // ---- Wiring -------------------------------------------------------------

  function init() {
    document.querySelectorAll('[data-kw-dash-tabs]').forEach((root) => {
      if (root.dataset.kwReady) return;
      root.dataset.kwReady = '1';
      const id = root.dataset.kwDashTabs;
      // The panes are siblings of the header, not inside it.
      const overview = document.getElementById(`${id}-overview`);
      const reportsPane = document.getElementById(`${id}-reports`);
      const exportPane = document.getElementById(`${id}-export`);

      void renderTerm(root);

      // Reports run when their pane is first opened (and again whenever the
      // year or term changes), so the overview never waits on them.
      let reportsShown = false;
      const showReports = () => {
        reportsShown = true;
        void renderComparison(reportsPane);
        void renderReports(reportsPane);
      };
      void loadScope().then(() => {
        if (reportsPane?.classList.contains('active')) showReports();
        if (exportPane?.classList.contains('active')) void renderDatasets(exportPane);
      });
      if (reportsPane) {
        new MutationObserver(() => {
          if (reportsPane.classList.contains('active') && !reportsShown && scope.years.length) showReports();
        }).observe(reportsPane, { attributes: true, attributeFilter: ['class'] });
      }

      // Row counts change as the overview loads and filters, so build the
      // export list each time its pane becomes active. Watching the pane's
      // class works however the tab is switched (a click, or the UI state
      // manager restoring the last tab); shown.bs.tab did not always fire.
      if (exportPane) {
        let wasActive = exportPane.classList.contains('active');
        if (wasActive) renderExports(exportPane, overview);
        new MutationObserver(() => {
          const active = exportPane.classList.contains('active');
          if (active && !wasActive) {
            renderExports(exportPane, overview);
            if (scope.years.length) void renderDatasets(exportPane);
          }
          wasActive = active;
        }).observe(exportPane, { attributes: true, attributeFilter: ['class'] });

        // Keep the row counts honest while the tab is open: the overview
        // keeps loading and refreshing its tables in the background.
        let pending = 0;
        overview?.querySelectorAll('table[data-export-title] tbody').forEach((body) => {
          new MutationObserver(() => {
            if (!exportPane.classList.contains('active')) return;
            window.clearTimeout(pending);
            pending = window.setTimeout(() => renderExports(exportPane, overview), 200);
          }).observe(body, { childList: true });
        });
      }

      exportPane?.querySelector('[data-kw-print-dashboard]')
        ?.addEventListener('click', () => {
          // Print the overview, not the pane holding the button.
          window.bootstrap?.Tab?.getOrCreateInstance(document.getElementById(`${id}-tab-overview`))?.show();
          window.setTimeout(() => window.print(), 250);
        });
    });
  }

  if (window.__APP_BOOTED__) {
    init();
  } else {
    window.addEventListener('kingsway:ready', init, { once: true });
  }
})(window, document);
