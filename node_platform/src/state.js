'use strict';

/**
 * Deterministic counters for the realtime gateway.
 *
 * These exist purely for observability (`/internal/stats`) and reset on restart —
 * that is by design and never a data loss, because the authoritative state lives
 * in MySQL and a reconnecting browser resyncs through PHP's catch-up reader.
 *
 * This module deliberately holds NO connection registry. The live socket set
 * belongs to `connections.js`; keeping a second copy of it in two modules is how
 * a gauge and a counter drift apart and mislead an operator. Connection counts
 * are therefore maintained in exactly one place, and everything here is a pure
 * counter with a single writer.
 */

function createState() {
  const stats = {
    started_at: null,
    connections_total: 0,
    connections_active: 0,
    // Fan-outs actually executed. This is NOT the same as the number of
    // publishes accepted: coalescing splits one accepted publish into a
    // leading and a trailing fan-out, so a burst shows more fan-outs than
    // requests. Both numbers are reported, because their ratio is how a
    // duplicate-refresh complaint is diagnosed.
    events_published: 0,
    events_accepted: 0,
    events_delivered: 0,
    publish_rejected: 0,
    publish_failed: 0,
    auth_rejected: 0,
  };

  return {
    stats,

    /** Called by the connection registry when a stream is accepted. */
    openedConnection() {
      stats.connections_total += 1;
    },

    /** One publish accepted from PHP/Python. */
    recordAccepted() {
      stats.events_accepted += 1;
    },

    /**
     * @param {number} delivered connections written to for this event
     */
    recordPublished(delivered) {
      stats.events_published += 1;
      stats.events_delivered += delivered;
    },

    recordRejected(kind) {
      if (kind === 'auth') stats.auth_rejected += 1;
      else stats.publish_rejected += 1;
    },

    recordPublishFailure() {
      stats.publish_failed += 1;
    },
  };
}

module.exports = { createState };