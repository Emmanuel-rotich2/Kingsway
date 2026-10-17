/**
 * Realtime job telemetry & payment alert widget.
 *
 * Activates the engine's background-telemetry and payment-alert surfaces on
 * every authenticated page. The gateway already streams:
 *   JOB_PROGRESS { job_id, progress (0-100), status, domain: "queue" }
 *   JOB_COMPLETE / JOB_FAILED { job_id, status, domain }
 *   PAYMENT_ALERT { record_id, status, domain: "finance", action }
 * but nothing rendered them, so batch jobs (bulk PDFs, report-card exports,
 * AI worker jobs) ran invisibly and finance staff never saw a verified
 * payment land. This widget turns those descriptors into live, non-blocking
 * toasts:
 *
 *   - a persistent toast per running job with a real progress bar that
 *     fills as the Python worker reports, then flips to success/failure and
 *     auto-dismisses;
 *   - a success toast on PAYMENT_ALERT ("fees received & posted").
 *
 * IMPORTANT: Only shows toasts for jobs explicitly initiated by the current
 * session (via window.API.nodeJobs.enqueue). Background/cron jobs that the
 * user did not trigger do NOT produce popups.
 *
 * Descriptors are identifiers and enums only, so there is nothing to escape,
 * but every value is still written via textContent — never innerHTML.
 *
 * Transport-agnostic: it listens to the DOM events RealtimeDispatch emits,
 * so both the SSE stream and the static-buffer fallback drive it. Pages that
 * poll job status may keep their existing logic; this widget is additive and
 * never blocks or refreshes anything (dispatch routes job events away from
 * the refresh path by design).
 */
(function (window, document) {
  'use strict';

  const MAX_VISIBLE = 5;
  const DISMISS_AFTER = { complete: 4500, failed: 9000, alert: 6000 };

  const jobs = new Map();
  const trackedJobs = new Set(); // job IDs explicitly enqueued by this session
  let container = null;

  function css() {
    const style = document.createElement('style');
    style.id = 'kingsway-jobs-widget-css';
    style.textContent = `
      #kingsway-job-toasts {
        position: fixed; right: 1rem; bottom: 1rem; z-index: 1080;
        display: flex; flex-direction: column; gap: .5rem; max-width: min(92vw, 24rem);
        pointer-events: none;
      }
      .rt-job-toast {
        pointer-events: auto; background: #fff; border: 1px solid rgba(15, 23, 42, .12);
        border-left: 4px solid #6c757d; border-radius: .5rem; box-shadow: 0 .5rem 1.25rem rgba(15, 23, 42, .18);
        padding: .55rem .75rem; font-size: .85rem; color: #212529;
        transition: opacity .25s ease, transform .25s ease;
      }
      .rt-job-toast.is-leaving { opacity: 0; transform: translateY(.5rem); }
      .rt-job-toast--running  { border-left-color: #0d6efd; }
      .rt-job-toast--complete { border-left-color: #198754; }
      .rt-job-toast--failed   { border-left-color: #dc3545; }
      .rt-job-toast--payment  { border-left-color: #20c997; }
      .rt-job-title { display: block; font-weight: 600; margin-bottom: .15rem; }
      .rt-job-sub { display: block; color: #495057; }
      .rt-job-bar { height: .35rem; margin-top: .4rem; background: #e9ecef; border-radius: .2rem; overflow: hidden; }
      .rt-job-bar > span { display: block; height: 100%; width: 0; background: #0d6efd; transition: width .4s ease; }
      .rt-job-toast--complete .rt-job-bar > span { background: #198754; width: 100%; }
      .rt-job-toast--failed .rt-job-bar > span { background: #dc3545; width: 100%; }
      @media print { #kingsway-job-toasts { display: none !important; } }
    `;
    document.head.appendChild(style);
  }

  function mount() {
    if (container) return container;
    container = document.createElement('div');
    container.id = 'kingsway-job-toasts';
    container.setAttribute('aria-live', 'polite');
    container.setAttribute('role', 'region');
    container.setAttribute('aria-label', 'Background job progress');
    document.body.appendChild(container);
    return container;
  }

  function prune() {
    while (jobs.size > MAX_VISIBLE) {
      const [oldestKey] = jobs.keys();
      dismiss(oldestKey);
    }
  }

  function dismiss(jobKey, delayMs) {
    const record = jobs.get(jobKey);
    if (!record) return;
    jobs.delete(jobKey);
    const { el } = record;
    window.setTimeout(() => {
      el.classList.add('is-leaving');
      window.setTimeout(() => el.remove(), 260);
    }, delayMs);
  }

  function buildToast({ kind, title }) {
    const el = document.createElement('div');
    el.className = `rt-job-toast rt-job-toast--${kind}`;
    const titleEl = document.createElement('span');
    titleEl.className = 'rt-job-title';
    titleEl.textContent = title;
    const subEl = document.createElement('span');
    subEl.className = 'rt-job-sub';
    const barEl = document.createElement('div');
    barEl.className = 'rt-job-bar';
    const fillEl = document.createElement('span');
    barEl.appendChild(fillEl);
    el.append(titleEl, subEl, barEl);
    return { el, titleEl, subEl, fillEl };
  }

  function isTracked(jobId) {
    return trackedJobs.has(jobId);
  }

  function trackJob(jobId) {
    trackedJobs.add(jobId);
  }

  function untrackJob(jobId) {
    trackedJobs.delete(jobId);
  }

  function onJobEvent(detail) {
    const payload = detail?.payload || {};
    const jobId = Number(payload.job_id);
    if (!Number.isInteger(jobId) || jobId < 1) return;
    const jobKey = `job:${jobId}`;
    const progress = Math.max(0, Math.min(100, Number(payload.progress) || 0));

    // Only show toasts for jobs this session explicitly enqueued
    if (!isTracked(jobId)) return;

    if (payload.status === 'completed' || detail?.type === 'JOB_COMPLETE') {
      const record = jobs.get(jobKey) || buildToast({ kind: 'complete', title: `Job #${jobId}` });
      record.el.className = 'rt-job-toast rt-job-toast--complete';
      record.subEl.textContent = 'Completed';
      if (!jobs.has(jobKey)) { mount().appendChild(record.el); jobs.set(jobKey, record); }
      prune();
      dismiss(jobKey, DISMISS_AFTER.complete);
      untrackJob(jobId);
      return;
    }

    if (detail?.type === 'JOB_FAILED') {
      const record = jobs.get(jobKey) || buildToast({ kind: 'failed', title: `Job #${jobId}` });
      record.el.className = 'rt-job-toast rt-job-toast--failed';
      record.subEl.textContent = 'Failed — check the job/queue surface for details';
      if (!jobs.has(jobKey)) { mount().appendChild(record.el); jobs.set(jobKey, record); }
      prune();
      dismiss(jobKey, DISMISS_AFTER.failed);
      untrackJob(jobId);
      return;
    }

    // JOB_PROGRESS (and any other running state): create-or-update.
    let record = jobs.get(jobKey);
    if (!record) {
      record = buildToast({ kind: 'running', title: `Background job #${jobId}` });
      record.el.className = 'rt-job-toast rt-job-toast--running';
      jobs.set(jobKey, record);
      mount().appendChild(record.el);
      prune();
    }
    record.fillEl.style.width = `${progress}%`;
    record.subEl.textContent = `${payload.status || 'running'} — ${progress}%`;
  }

  function onPaymentAlert(detail) {
    const payload = detail?.payload || {};
    const purpose = String(payload.action || 'payment');
    const record = buildToast({
      kind: 'payment',
      title: purpose === 'extra_charge' ? 'Extra-charge payment received' : `${purpose.charAt(0).toUpperCase() + purpose.slice(1)} payment received`,
    });
    record.el.className = 'rt-job-toast rt-job-toast--payment';
    record.subEl.textContent = 'Verified payment posted to the ledger — finance views have refreshed.';
    record.fillEl.parentElement.remove();
    mount().appendChild(record.el);
    jobs.set(`payment:${payload.record_id ?? Date.now()}`, record);
    prune();
    const key = [...jobs.entries()].find(([, value]) => value === record)?.[0];
    if (key) dismiss(key, DISMISS_AFTER.alert);
  }

  function init() {
    if (document.getElementById('kingsway-jobs-widget-css')) return;
    css();
    window.addEventListener('kingsway:job', (event) => onJobEvent(event.detail || {}));
    // PAYMENT_ALERT is not a job type, so dispatch routes it down the
    // refresh path; the raw event still reaches every listener first.
    window.addEventListener('kingsway:realtime', (event) => {
      const detail = event.detail || {};
      if (String(detail.type || '').toUpperCase() === 'PAYMENT_ALERT') onPaymentAlert(detail);
    });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init, { once: true });
  } else {
    init();
  }

  window.RealtimeJobs = Object.freeze({ onJobEvent, onPaymentAlert, trackJob, untrackJob, isTracked });
})(window, document);
