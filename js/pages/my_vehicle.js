/**
 * My Vehicle: the driver's logbook. Everything is scoped by the server to
 * the vehicle assigned to the signed-in driver (DriverLogbookService).
 */
(() => {
  'use strict';
  const root = document.getElementById('myVehiclePage');
  if (!root) return;
  const O = window.OpsCommon;
  const { esc, data, api, notify, label, today, kes, num, date, due } = O;
  const $ = (id) => document.getElementById(id);
  const TRIP = { morning_pickup: 'Morning pick-up', evening_dropoff: 'Evening drop-off', midday_trip: 'Midday trip', special_trip: 'Special trip' };
  const SERVICE = { routine: 'Routine service', repair: 'Repair', inspection: 'Inspection', emergency: 'Emergency repair' };
  const PROBLEM = { vehicle_breakdown: 'Breakdown or fault', accident: 'Accident', late_pickup: 'Late for pick-up', late_dropoff: 'Late for drop-off', other: 'Other' };
  let book = null;
  let tab = 'mvServices';

  const modal = (id) => bootstrap.Modal.getOrCreateInstance($(id));
  const val = (id) => $(id).value.trim();

  async function load() {
    try {
      render(data(await api('/transport/my-logbook')));
    } catch (e) {
      $('mvState').hidden = false;
      $('mvBody').hidden = true;
      $('mvActions').hidden = true;
      $('mvState').textContent = Number(e?.code || e?.status) === 404
        ? 'No vehicle is assigned to you yet. Ask the transport office to assign one.'
        : (e.message || 'Your vehicle could not be loaded.');
    }
  }

  function render(b) {
    book = b;
    const v = b.vehicle;
    $('mvState').hidden = true;
    $('mvBody').hidden = false;
    $('mvActions').hidden = false;
    $('mvPlate').textContent = String(v.registration_number || '').toUpperCase();
    $('mvModel').textContent = [v.make, v.model, v.year].filter(Boolean).join(' ') || label(v.type);
    $('mvMeta').textContent = [label(v.type), `${num(v.capacity)} seats`, v.status === 'active' ? 'In service' : label(v.status)].filter(Boolean).join(', ');

    const service = due(v.service_due_date);
    const cover = due(v.insurance_expiry, 30);
    $('mvFacts').innerHTML = [
      [service.tone, v.service_due_date ? `${date(v.service_due_date)}` : 'Not set', `Next service, ${service.text}`],
      [cover.tone, v.insurance_expiry ? `${date(v.insurance_expiry)}` : 'Not set', `Insurance ends, ${cover.text}`],
      ['', b.odometer ? `${num(b.odometer)} km` : '—', 'Last odometer reading'],
      ['', `${num(b.month.fuel_litres)} L`, `Fuel this month, ${kes(b.month.fuel_amount)}`],
    ].map(([tone, v1, l]) => `<li class="${tone}"><span class="ops-fact__v">${esc(v1)}</span><span class="ops-fact__l">${esc(l)}</span></li>`).join('');

    renderCheck();
    renderTables();
  }

  function renderCheck() {
    const done = book.checked_today || [];
    $('mvCheckToday').innerHTML = ['morning_pickup', 'evening_dropoff'].map((t) => done.includes(t)
      ? `<span class="ops-tag is-ok">${esc(TRIP[t])} checked</span>`
      : `<span class="ops-tag is-warn">${esc(TRIP[t])} not checked</span>`).join('');
    const hour = new Date().getHours();
    if (!$('mvCheckForm').dataset.touched) $('mvCheckTrip').value = hour >= 12 ? 'evening_dropoff' : 'morning_pickup';
    $('mvCheckItems').innerHTML = Object.entries(book.check_items).map(([k, name]) => `
      <li><span id="mvCi_${k}">${esc(name)}</span>
        <span class="btn-group" role="radiogroup" aria-labelledby="mvCi_${k}">
          <input type="radio" class="btn-check" name="ci_${k}" id="ci_${k}_ok" value="ok" checked>
          <label class="btn btn-outline-success btn-sm" for="ci_${k}_ok">OK</label>
          <input type="radio" class="btn-check" name="ci_${k}" id="ci_${k}_bad" value="defect">
          <label class="btn btn-outline-danger btn-sm" for="ci_${k}_bad">Problem</label>
        </span></li>`).join('');
    $('mvDefectBox').hidden = true;
  }

  const table = (head, rows, empty) => rows.length
    ? `<div class="table-responsive" tabindex="0" role="region" aria-label="${esc(head[0])}"><table class="table align-middle mb-0"><thead><tr>${head.map((h) => `<th scope="col">${esc(h)}</th>`).join('')}</tr></thead><tbody>${rows.map((r) => `<tr>${r.map((c) => `<td>${c}</td>`).join('')}</tr>`).join('')}</tbody></table></div>`
    : `<p class="ops-empty">${esc(empty)}</p>`;

  function rowsFor(which) {
    const b = book;
    if (which === 'mvServices') return {
      head: ['Date', 'Work', 'What was done', 'Odometer', 'Cost', 'Next service'],
      rows: b.services.map((s) => [date(s.maintenance_date), SERVICE[s.maintenance_type] || label(s.maintenance_type),
        [s.description, s.parts_replaced ? `Parts: ${s.parts_replaced}` : '', s.mechanic_details].filter(Boolean).join('. '),
        s.odometer_reading ? `${num(s.odometer_reading)} km` : '', s.cost !== null ? kes(s.cost) : '',
        [s.next_maintenance_date ? date(s.next_maintenance_date) : '', s.next_maintenance_reading ? `${num(s.next_maintenance_reading)} km` : ''].filter(Boolean).join(' or ')]),
      empty: 'No services logged yet. Use "Log a service" after each visit to the garage.',
    };
    if (which === 'mvFuel') return {
      head: ['Date', 'Litres', 'Amount', 'Odometer', 'Station', 'Receipt'],
      rows: b.fuel.map((f) => [date(f.fuel_date), `${num(f.litres)} L`, kes(f.amount), f.odometer_reading ? `${num(f.odometer_reading)} km` : '', f.station || '', f.receipt_no || '']),
      empty: 'No fuel logged yet.',
    };
    if (which === 'mvChecks') return {
      head: ['Date', 'Trip', 'Result', 'Problems found', 'Odometer'],
      rows: b.checks.map((c) => {
        const bad = Object.entries(c.items).filter(([, v]) => v === 'defect').map(([k]) => b.check_items[k] || k);
        return [date(c.check_date), TRIP[c.trip_session] || c.trip_session,
          c.fit_to_drive ? (bad.length ? 'Safe to drive, with problems' : 'All OK') : 'Not safe to drive',
          [bad.join(', '), c.defects].filter(Boolean).join(': '), c.odometer_reading ? `${num(c.odometer_reading)} km` : ''];
      }),
      empty: 'No checks saved yet.',
    };
    return {
      head: ['When', 'What happened', 'Description', 'Action taken', 'Urgent'],
      rows: b.problems.map((p) => [String(p.incident_datetime).slice(0, 16), PROBLEM[p.incident_type] || label(p.incident_type), p.description, p.action_taken || '', Number(p.escalated) ? 'Yes' : '']),
      empty: 'No problems reported.',
    };
  }

  function renderTables() {
    ['mvServices', 'mvFuel', 'mvChecks', 'mvProblems'].forEach((id) => {
      const { head, rows, empty } = rowsFor(id);
      const htmlRows = rows.map((r) => r.map((c, i) => (id === 'mvChecks' && i === 2
        ? `<span class="ops-tag ${c === 'All OK' ? 'is-ok' : c.startsWith('Not') ? 'is-stop' : 'is-warn'}">${esc(c)}</span>` : esc(c))));
      $(id).innerHTML = table(head, htmlRows, empty);
    });
  }

  async function submit(path, body, modalId, done) {
    try {
      render(data(await api(path, 'POST', body)));
      if (modalId) modal(modalId).hide();
      notify(done, 'success');
      return true;
    } catch (e) {
      notify(e.message || 'This could not be saved.', 'danger');
      return false;
    }
  }

  function wire() {
    O.tabs($('mvTabs'), (id) => { tab = id; });

    $('mvServiceBtn').addEventListener('click', () => { $('mvServiceForm').reset(); $('mvSvDate').value = today(); $('mvSvOdo').value = book?.odometer || ''; modal('mvServiceModal').show(); });
    $('mvFuelBtn').addEventListener('click', () => { $('mvFuelForm').reset(); $('mvFuDate').value = today(); modal('mvFuelModal').show(); });
    $('mvProblemBtn').addEventListener('click', () => { $('mvProblemForm').reset(); modal('mvProblemModal').show(); });

    $('mvServiceForm').addEventListener('submit', (e) => {
      e.preventDefault();
      if (!val('mvSvDesc')) { notify('Describe the work done.', 'warning'); return; }
      submit('/transport/my-service', {
        maintenance_type: $('mvSvType').value, maintenance_date: $('mvSvDate').value, description: val('mvSvDesc'),
        odometer_reading: val('mvSvOdo'), cost: val('mvSvCost'), parts_replaced: val('mvSvParts'), mechanic_details: val('mvSvGarage'),
        next_maintenance_date: $('mvSvNext').value, next_maintenance_reading: val('mvSvNextKm'),
      }, 'mvServiceModal', 'Service saved.');
    });
    $('mvFuelForm').addEventListener('submit', (e) => {
      e.preventDefault();
      if (!val('mvFuLitres') || !val('mvFuAmount')) { notify('Enter the litres and the amount paid.', 'warning'); return; }
      submit('/transport/my-fuel', {
        fuel_date: $('mvFuDate').value, litres: val('mvFuLitres'), amount: val('mvFuAmount'),
        odometer_reading: val('mvFuOdo'), station: val('mvFuStation'), receipt_no: val('mvFuReceipt'),
      }, 'mvFuelModal', 'Fuel saved.');
    });
    $('mvProblemForm').addEventListener('submit', (e) => {
      e.preventDefault();
      if (val('mvPrDesc').length < 5) { notify('Describe the problem.', 'warning'); return; }
      submit('/transport/my-problem', {
        incident_type: $('mvPrType').value, description: val('mvPrDesc'), action_taken: val('mvPrAction'), urgent: $('mvPrUrgent').checked,
      }, 'mvProblemModal', 'Problem reported to the transport office.');
    });

    $('mvCheckForm').addEventListener('change', (e) => {
      $('mvCheckForm').dataset.touched = '1';
      if (e.target.name?.startsWith('ci_')) {
        $('mvDefectBox').hidden = !$('mvCheckForm').querySelector('.btn-check[value="defect"]:checked');
      }
    });
    $('mvCheckForm').addEventListener('submit', async (e) => {
      e.preventDefault();
      const items = {};
      Object.keys(book.check_items).forEach((k) => { items[k] = $('mvCheckForm').querySelector(`input[name="ci_${k}"]:checked`)?.value || 'ok'; });
      const anyBad = Object.values(items).includes('defect');
      if (anyBad && !val('mvCheckDefects')) { notify('Describe what is wrong with the items marked as a problem.', 'warning'); $('mvCheckDefects').focus(); return; }
      const trip = $('mvCheckTrip').value;
      $('mvCheckSave').disabled = true;
      const fit = !anyBad || $('mvCheckFit').checked;
      const ok = await submit('/transport/my-check', {
        trip_session: trip, odometer_reading: val('mvCheckOdo'), items, defects: val('mvCheckDefects'), fit_to_drive: fit,
      }, null, fit ? `${TRIP[trip]} check saved.` : 'Check saved. The transport office has been told the vehicle is not safe to drive.');
      $('mvCheckSave').disabled = false;
      if (ok) { delete $('mvCheckForm').dataset.touched; $('mvCheckOdo').value = ''; $('mvCheckDefects').value = ''; $('mvCheckFit').checked = false; }
    });

    $('mvCsv').addEventListener('click', () => {
      if (!book) return;
      const { head, rows } = rowsFor(tab);
      O.exportCsv(`vehicle_${tab.slice(2).toLowerCase()}_${String(book.vehicle.registration_number || '').replace(/\s+/g, '')}.csv`, head, rows);
    });
    $('mvPrint').addEventListener('click', () => window.print());
    if (!O.canExport()) $('mvCsv').hidden = true;
  }

  async function init() {
    await window.AuthContext?.ready?.();
    wire();
    await load();
  }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init); else init();
})();
