'use strict';

/**
 * Dispatcher: the orchestration layer between the event bus and the sockets.
 *
 * Order of operations for every event:
 *   1. accept (already validated by the route / schema)
 *   2. coalesce refresh signals so N near-simultaneous writes cause ONE browser
 *      refresh, not N
 *   3. route strictly by the recipient's minted channels
 *   4. withhold a row patch from the connection that is actively editing that
 *      row and deliver a CONFLICT_HINT to it instead
 *   5. write through the connection registry so backpressure and slow-client
 *      eviction apply
 *   6. record latency and counters
 */

const { createCoalescer } = require('./coalescer');
const { fanOut, frameFor, buildEvent } = require('./events');

function createDispatcher({ registry, state, metrics, logger, coalesceWindowMs, coalesceMaxWaitMs }) {
  let sequence = 0;
  const nextId = () => {
    sequence += 1;
    return sequence;
  };

  function deliver(event) {
    const id = nextId();
    const startedAt = process.hrtime.bigint();
    const latencySeconds = () => Number(process.hrtime.bigint() - startedAt) / 1e9;

    const delivered = fanOut(registry, event, {
      id,
      send: (client, frame, type) => {
        const ok = registry.send(client, frame, type);
        if (ok && metrics) metrics.delivered(type, latencySeconds());
        return ok;
      },
      suppressed: (client, blocked) => {
        if (metrics) metrics.suppressed(blocked.type);
        // The recipient is mid-edit on this exact row: tell them what happened
        // instead of overwriting what they are typing.
        const hint = {
          type: 'CONFLICT_HINT',
          scope: blocked.scope,
          payload: {
            activity_key: blocked.payload.activity_key,
            entity_id: blocked.payload.entity_id ?? blocked.payload.id,
            version: blocked.payload.version,
            responder_id: blocked.payload.responder_id,
            targets: blocked.payload.targets,
          },
          emitted_at: new Date().toISOString(),
        };
        registry.send(client, frameFor(hint, nextId()), 'CONFLICT_HINT');
      },
    });

    state.recordPublished(delivered);
    if (delivered === 0 && event.type !== 'PRESENCE' && logger) {
      logger.journal('publish.no_recipients', { type: event.type, scope: event.scope });
    }
    return delivered;
  }

  const coalescer = createCoalescer({
    windowMs: coalesceWindowMs,
    maxWaitMs: coalesceMaxWaitMs,
    deliver,
    metrics,
    logger,
  });

  /**
   * Entry point for every accepted event, whatever brought it here (bus,
   * sibling instance, or a direct publish). Exactly one call path, so an event
   * can never be delivered twice.
   *
   * @returns {{delivered: number, queued: boolean}} `queued: true` means the
   *   event was merged into a pending refresh burst and will be delivered once
   *   the window closes; the publisher must not block or re-send for it.
   */
  function dispatch(event) {
    if (metrics) metrics.published(event.type);
    state.recordAccepted();
    return coalescer.submit(event);
  }

  /**
   * A user started or stopped editing a named row. Broadcast to the scope so
   * peers can show "X is editing this" without any record data crossing.
   */
  function announcePresence({ userId, activityKey, state: presenceState, scope }) {
    const event = buildEvent({
      type: 'PRESENCE',
      scope: scope || 'all',
      payload: {
        user_id: userId,
        activity_key: activityKey,
        state: presenceState,
      },
    });
    if (event) dispatch(event);
    return event;
  }

  return { dispatch, deliver, announcePresence, coalescer, nextId };
}

module.exports = { createDispatcher };