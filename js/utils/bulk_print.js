/**
 * BulkPrint - asynchronous bulk-document pipeline client.
 *
 * Server-side batches (ID cards today; report cards, portfolios, certificates,
 * staff passes, payslips, fee statements and transcripts follow) are drained by
 * the background worker in bounded steps, so 1000+ items never hold an HTTP
 * request open. This utility enqueues, polls progress and opens the finished
 * artifact (single PDF, or ZIP for very large batches).
 *
 * Response shape from /api/bulkprint/status/{id}:
 *   status: 'pending' | 'processing' | 'done' | 'failed' | 'cancelled'
 *   total, processed, progress_pct, unit, delivery ('pdf'|'zip'),
 *   download_url (when done), error
 */

(function (global) {
  "use strict";

  const ENDPOINT = "/bulkprint";

  function api() {
    if (global.API && typeof global.API.callAPI === "function") {
      return global.API.callAPI.bind(global.API);
    }
    if (global.API && typeof global.API.apiCall === "function") {
      return global.API.apiCall.bind(global.API);
    }
    throw new Error("BulkPrint: API helper not available (expected API.callAPI or API.apiCall).");
  }

  function unwrap(response) {
    if (!response) return {};
    if (response && response.data) return response.data;
    return response || {};
  }

  /**
   * Start a bulk-print batch.
   * @param {string} kind  e.g. 'student_id_cards'
   * @param {object} selection e.g. { student_ids: [1,2,3] }
   * @param {object} options e.g. { printerMode: 'a4_pdf', side: 'both' }
   * @returns {Promise<{batch_id: string, status: string}>}
   */
  async function enqueue(kind, selection, options) {
    const response = await api()("/bulkprint/enqueue", "POST", {
      kind,
      selection: selection || {},
      options: options || {},
    });
    const data = unwrap(response);
    if (!data.batch_id) {
      throw new Error(response.message || "Bulk print could not be started.");
    }
    return data;
  }

  async function status(batchId) {
    const response = await api()(`/bulkprint/status/${batchId}`, "GET");
    return unwrap(response);
  }

  async function cancel(batchId) {
    const response = await api()(`/bulkprint/cancel/${batchId}`, "POST", {});
    return unwrap(response);
  }

  async function kinds() {
    const response = await api()("/bulkprint/kinds", "GET");
    const data = unwrap(response);
    return (data && data.kinds) || [];
  }

  /**
   * Poll a batch until it completes.
   * @param {string} batchId
   * @param {object} hooks { onProgress(payload), onDone(payload), intervalMs, maxAttempts, expectedTransferMs }
   * @returns {Promise<object>} final status payload (progress reached 100 / done)
   */
  async function poll(batchId, hooks) {
    const intervalMs = Math.max(1500, hooks.intervalMs || 2500);
    const maxAttempts = hooks.maxAttempts || 600;
    const timeoutWarningAt = hooks.expectedTransferMs || 180000;

    let attempts = 0;
    let startedAt = Date.now();
    let warned = false;
    let watchedJobId = 0;
    let wake = null;

    // Realtime early-wake: the batch worker publishes JOB_* descriptors on
    // the SSE stream; a matching job_id interrupts the sleep so a finished
    // batch resolves immediately instead of after the next interval. The
    // poll loop remains the fallback for browsers without the stream.
    const onRealtimeJob = (event) => {
      const payload = (event.detail && event.detail.payload) || {};
      const jobId = Number(payload.job_id || 0);
      if (!jobId || (watchedJobId && jobId !== watchedJobId)) return;
      if (wake) wake();
    };
    global.addEventListener?.("kingsway:job", onRealtimeJob);

    const sleepUntil = (ms) =>
      new Promise((resolve) => {
        const timer = setTimeout(() => {
          wake = null;
          resolve();
        }, ms);
        wake = () => {
          clearTimeout(timer);
          wake = null;
          resolve();
        };
      });

    try {
      while (attempts < maxAttempts) {
        attempts += 1;
        const payload = await status(batchId);
        if (Number(payload.job_id || 0)) watchedJobId = Number(payload.job_id);
        const onProgress = hooks.onProgress || function () {};

        if (payload.status === "done") {
          onProgress(payload);
          if (hooks.onDone) hooks.onDone(payload);
          return payload;
        }
        if (payload.status === "failed" || payload.status === "cancelled") {
          const error = new Error(
            payload.error || `Bulk print ${payload.status}.`
          );
          error.code = payload.status;
          throw error;
        }

        onProgress(payload);

        if (!warned && Date.now() - startedAt > timeoutWarningAt) {
          warned = true;
          notify(
            "info",
            "Bulk print is still running in the background. You can leave this page."
          );
        }

        await sleepUntil(intervalMs);
      }

      const timeout = new Error(
        "Bulk print is still processing. Check later from the ID Cards page."
      );
      timeout.code = "timeout";
      throw timeout;
    } finally {
      global.removeEventListener?.("kingsway:job", onRealtimeJob);
    }
  }

  function sleep(ms) {
    return new Promise((resolve) => setTimeout(resolve, ms));
  }

  function notify(type, message) {
    if (typeof global.showNotification === "function") {
      global.showNotification(type, message);
      return;
    }
    if (global.API && typeof global.API.showNotification === "function") {
      global.API.showNotification(message, type);
      return;
    }
    console.log(`[${type.toUpperCase()}] ${message}`);
  }

  /**
   * Open a finished artifact in the application viewer.
   */
  function openArtifact(payload) {
    const url = payload && payload.download_url;
    if (!url) {
      throw new Error("Bulk print completed but no download is available.");
    }
    const title = payload.unit
      ? `Bulk print (${payload.total} ${payload.unit})`
      : "Bulk print";
    if (global.PrintManager && global.PrintManager.openDocument) {
      global.PrintManager.openDocument(url, { title });
    } else if (global.KingswayFileLifecycle && global.KingswayFileLifecycle.open) {
      global.KingswayFileLifecycle.open(url);
    } else {
      global.open(url, "_blank");
    }
  }

  global.BulkPrint = {
    enqueue,
    status,
    cancel,
    kinds,
    poll,
    openArtifact,
  };
})(window);