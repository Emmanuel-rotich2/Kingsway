/**
 * Transport operator manifest and QR boarding controller.
 * The PHP template owns the live manifest markup; this controller owns data,
 * scan feedback, and offline status.
 */
(() => {
  'use strict';

  const sessions = new Set(['morning_pickup', 'evening_dropoff', 'midday_trip', 'special_trip']);
  const state = {
    root: null,
    manifest: null,
    date: '',
    session: 'morning_pickup',
    lastScan: '',
    lastScanAt: 0,
    scanning: false,
    serialPort: null,
    serialReader: null,
    serialTask: null,
    unsubscribeQueue: null,
    globalListenersBound: false,
  };

  const el = (id) => document.getElementById(id);
  const escapeHtml = (value) => String(value ?? '')
    .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;').replace(/'/g, '&#39;');

  function localDateValue(date = new Date()) {
    return `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`;
  }

  function setFeedback(message, kind = 'muted') {
    const feedback = el('operatorScanFeedback');
    if (!feedback) return;
    feedback.textContent = message;
    feedback.className = `scan-feedback text-${kind}`;
  }

  function setConnectivity(online = navigator.onLine !== false) {
    const badge = el('operatorConnectivity');
    if (!badge) return;
    badge.classList.toggle('offline', !online);
    badge.classList.toggle('online', online);
    badge.innerHTML = online
      ? '<i class="bi bi-wifi"></i> Connected'
      : '<i class="bi bi-wifi-off"></i> Offline';
  }

  function showEmptyState(message, show) {
    const empty = el('manifestEmpty');
    const table = el('operatorManifestBody')?.closest('.table-responsive');
    if (empty) {
      empty.textContent = message;
      empty.hidden = !show;
    }
    if (table) table.hidden = show;
  }

  function renderSummary(summary = {}) {
    const counts = {
      manifestExpected: summary.expected ?? 0,
      manifestBoarded: summary.boarded ?? 0,
      manifestRemaining: summary.remaining ?? 0,
    };
    Object.entries(counts).forEach(([id, value]) => {
      const target = el(id);
      if (target) target.textContent = String(Number(value) || 0);
    });
  }

  function renderManifest() {
    const manifest = state.manifest || {};
    const routes = Array.isArray(manifest.routes) ? manifest.routes : [];
    const students = Array.isArray(manifest.students) ? manifest.students : [];
    const routeLabel = el('manifestRouteLabel');
    if (routeLabel) {
      routeLabel.textContent = routes.length
        ? routes.map((route) => `${route.name || route.code || 'Route'}${route.vehicle_registration ? ` · ${route.vehicle_registration}` : ''}`).join(' / ')
        : 'No active vehicle and route are assigned to this driver.';
    }
    const generated = el('manifestGeneratedAt');
    if (generated) generated.textContent = manifest.generated_at ? `Updated ${manifest.generated_at}` : '—';
    renderSummary(manifest.summary || {});

    const body = el('operatorManifestBody');
    if (!body) return;
    if (!routes.length) {
      body.replaceChildren();
      showEmptyState('No active vehicle and route are assigned to this driver.', true);
      return;
    }
    if (!students.length) {
      body.innerHTML = '<tr><td colspan="5" class="text-center text-muted py-5">No active passengers are assigned to this route.</td></tr>';
      showEmptyState('', false);
      return;
    }

    body.innerHTML = students.map((student) => {
      const name = [student.first_name, student.last_name].filter(Boolean).join(' ') || '—';
      const time = student.arrival_time || student.departure_time || student.pickup_time || '—';
      const boarded = student.boarding_status === 'boarded' || Boolean(student.boarded);
      const status = boarded
        ? (state.session === 'evening_dropoff' ? 'Dropped off' : 'Boarded')
        : 'Waiting';
      return `<tr class="${boarded ? 'table-success' : ''}">
        <td>${escapeHtml(student.stop_name || '—')}</td>
        <td>${escapeHtml(name)}</td>
        <td><code>${escapeHtml(student.admission_no || '—')}</code></td>
        <td><span class="badge ${boarded ? 'text-bg-success' : 'text-bg-secondary'}">${status}</span></td>
        <td>${escapeHtml(time)}</td>
      </tr>`;
    }).join('');
    showEmptyState('', false);
  }

  async function updateQueuedCount() {
    try {
      const pending = await window.SyncQueue?.getPendingOperations?.() || [];
      const count = pending.filter((operation) => String(operation.endpoint || '').includes('/scan/verify')).length;
      for (const id of ['queuedScanCount', 'manifestQueued']) {
        const target = el(id);
        if (target) target.textContent = String(count);
      }
    } catch (error) {
      console.warn('Could not read queued scan count:', error);
    }
  }

  async function loadManifest() {
    const date = el('operatorDate')?.value || localDateValue();
    const session = el('operatorTripSession')?.value || 'morning_pickup';
    state.date = date;
    state.session = sessions.has(session) ? session : 'morning_pickup';
    const body = el('operatorManifestBody');
    if (body) body.innerHTML = '<tr><td colspan="5" class="text-center text-muted py-5"><span class="spinner-border spinner-border-sm me-2"></span>Loading manifest…</td></tr>';

    try {
      const response = await window.API.transport.getDriverManifest({ date, trip_session: state.session });
      state.manifest = response?.data || response || {};
      renderManifest();
      await updateQueuedCount();
    } catch (error) {
      console.error('Failed to load driver manifest:', error);
      state.manifest = { routes: [], students: [], summary: {} };
      renderSummary();
      showEmptyState(error.message || 'Could not load the assigned route manifest. Refresh to try again.', true);
      const routeLabel = el('manifestRouteLabel');
      if (routeLabel) routeLabel.textContent = 'Manifest unavailable';
    }
  }

  function createClientReference() {
    return window.crypto?.randomUUID?.() || `scan-${Date.now()}-${Math.random().toString(16).slice(2)}`;
  }

  async function handleScan(rawValue) {
    const qrData = String(rawValue || '').trim();
    if (qrData.length < 2 || state.scanning) return;
    const now = Date.now();
    if (qrData === state.lastScan && now - state.lastScanAt < 2000) return;
    state.lastScan = qrData;
    state.lastScanAt = now;

    if (state.date !== localDateValue()) {
      setFeedback('Select today to record a boarding scan.', 'warning');
      return;
    }

    state.scanning = true;
    const input = el('operatorScanInput');
    if (input) input.disabled = true;
    setFeedback('Checking learner card…', 'primary');
    try {
      const action = state.session === 'evening_dropoff' ? 'dropped_off' : 'picked_up';
      const result = await window.API.transport.verifyBoardingScan({
        qr_data: qrData,
        context: 'transport',
        action,
        trip_session: state.session,
        client_reference: createClientReference(),
      });
      if (result?.status === 'queued') {
        setFeedback('Offline: scan queued. Boarding will update after sync.', 'warning');
        await updateQueuedCount();
        return;
      }
      if (!result?.success || !result?.eligible) {
        setFeedback(result?.message || 'Scan rejected. Check the learner assignment and transport access.', 'danger');
        return;
      }
      const student = result.student || {};
      const learner = [student.first_name, student.last_name].filter(Boolean).join(' ') || student.admission_no || 'Learner';
      setFeedback(`${learner}: ${result.message || (action === 'dropped_off' ? 'Drop-off recorded.' : 'Boarding recorded.')}`, 'success');
      await loadManifest();
    } catch (error) {
      setFeedback(error.message || 'Scan could not be recorded. Try again.', 'danger');
    } finally {
      state.scanning = false;
      if (input) {
        input.disabled = false;
        input.value = '';
        input.focus();
      }
    }
  }

  async function connectSerialScanner() {
    if (!('serial' in navigator)) {
      setFeedback('Serial access is not available in this browser. A USB HID scanner can type directly into the scan field.', 'warning');
      return;
    }
    try {
      const port = await navigator.serial.requestPort();
      await port.open({ baudRate: 9600 });
      state.serialPort = port;
      const status = el('scannerDeviceStatus');
      if (status) status.textContent = 'Serial scanner connected';
      const decoder = new TextDecoder();
      let buffer = '';
      state.serialTask = (async () => {
        while (port.readable) {
          state.serialReader = port.readable.getReader();
          try {
            while (true) {
              const { value, done } = await state.serialReader.read();
              if (done) break;
              buffer += decoder.decode(value, { stream: true });
              const records = buffer.split(/[\r\n]+/);
              buffer = records.pop() || '';
              for (const record of records) if (record.trim()) await handleScan(record);
            }
          } finally {
            state.serialReader.releaseLock();
            state.serialReader = null;
          }
        }
      })().catch((error) => {
        console.warn('Serial scanner disconnected:', error);
        if (status) status.textContent = 'USB HID ready';
      });
    } catch (error) {
      setFeedback(error.message || 'Could not connect the serial scanner.', 'danger');
    }
  }

  async function syncQueuedScans() {
    if (navigator.onLine === false) {
      setFeedback('You are offline. Queued scans will sync when the connection returns.', 'warning');
      return;
    }
    await window.SyncQueue?.processQueue?.();
    await updateQueuedCount();
    await loadManifest();
  }

  function bindEvents() {
    el('operatorRefresh')?.addEventListener('click', loadManifest);
    el('operatorDate')?.addEventListener('change', loadManifest);
    el('operatorTripSession')?.addEventListener('change', loadManifest);
    el('connectSerialScanner')?.addEventListener('click', connectSerialScanner);
    el('operatorSync')?.addEventListener('click', syncQueuedScans);
    el('operatorScanInput')?.addEventListener('keydown', (event) => {
      if (event.key !== 'Enter') return;
      event.preventDefault();
      handleScan(event.currentTarget.value);
    });
    if (!state.globalListenersBound) {
      window.addEventListener('online', () => { setConnectivity(true); loadManifest(); });
      window.addEventListener('offline', () => setConnectivity(false));
      state.globalListenersBound = true;
    }
  }

  function subscribeQueue() {
    if (state.unsubscribeQueue) return;
    const matches = (operation) => String(operation?.endpoint || '').includes('/scan/verify');
    const subscription = window.SyncQueue?.subscribe;
    if (typeof subscription !== 'function') return;
    const cleanups = ['OPERATION_SUCCESS', 'OPERATION_FAILED', 'OPERATION_ERROR'].map((eventName) => (
      subscription(eventName, async (event) => {
        if (!matches(event?.operation)) return;
        await updateQueuedCount();
        if (eventName === 'OPERATION_SUCCESS') await loadManifest();
        if (eventName !== 'OPERATION_SUCCESS') setFeedback('A queued scan did not sync. Review the connection and try syncing again.', 'danger');
      })
    ));
    state.unsubscribeQueue = () => cleanups.forEach((cleanup) => cleanup?.());
  }

  async function init() {
    const root = el('transportOperatorApp');
    if (!root || state.root === root) return;
    state.root = root;
    if (window.AuthContext?.ready) await window.AuthContext.ready();
    const date = el('operatorDate');
    if (date && !date.value) date.value = localDateValue();
    const input = el('operatorScanInput');
    if (input) input.value = '';
    setConnectivity();
    bindEvents();
    subscribeQueue();
    await Promise.all([loadManifest(), updateQueuedCount()]);
    input?.focus();
  }

  window.transportOperatorController = { init, loadManifest, handleScan, syncQueuedScans };
  document.addEventListener('DOMContentLoaded', () => init().catch((error) => {
    console.error('Transport operator page setup failed:', error);
    setFeedback('The transport manifest could not start. Refresh the page to try again.', 'danger');
  }));
})();
