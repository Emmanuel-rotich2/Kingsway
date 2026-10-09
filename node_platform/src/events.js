'use strict';

/**
 * Fan-out: turn one validated event into socket writes for exactly the
 * connections whose minted capability covers its scope.
 *
 * This module is the single fan-out path. It works against anything exposing an
 * iterable of `{ res, channels, ... }` clients — the production connection
 * registry (which adds backpressure and slow-client eviction through
 * `sendWith`) or a plain set, so the routing rule is defined exactly once and
 * cannot drift between the tested path and the serving path.
 */

const {
  PAYLOAD_FIELDS,
  EVENT_TYPES,
  PRESENCE_STATES,
  EVENT_TYPE_PATTERN,
  SCOPE_PATTERN,
  publishSchema,
  presenceSchema,
  payloadSchema,
  isDescriptor,
  validateEvent,
  buildEvent,
  frameFor,
  connectionReceives,
} = require('./protocol');

function clientsOf(target) {
  if (!target) return [];
  if (typeof target[Symbol.iterator] === 'function') return [...target];
  if (target.clients) return [...target.clients];
  return [];
}

/**
 * @param {object|Set} target             registry or client collection
 * @param {object} event                   validated protocol event
 * @param {object} [options]
 * @param {number} [options.id]            monotonic event id for the frame
 * @param {Function} [options.send]        (client, frame, type) => boolean
 * @param {Function} [options.suppressed]  (client, event) => void, called when
 *                                          the recipient is editing that row
 * @returns {number} connections written to
 */
function fanOut(target, event, options = {}) {
  const frame = frameFor(event, options.id);
  const activityKey = event.type === 'ROW_UPDATED' ? event.payload?.activity_key : undefined;

  let delivered = 0;
  for (const client of clientsOf(target)) {
    if (!connectionReceives(client, event)) continue;

    // Collaboration sync: if THIS connection declared that it is actively
    // editing that exact row, do not overwrite its screen. Tell the user
    // instead so they can reconcile deliberately.
    if (activityKey && client.activity && client.activity.has(activityKey)) {
      if (options.suppressed) options.suppressed(client, event);
      continue;
    }

    const sent = options.send
      ? options.send(client, frame, event.type)
      : client.res.write(frame);
    if (sent) delivered += 1;
  }
  return delivered;
}

module.exports = {
  PAYLOAD_FIELDS,
  EVENT_TYPES,
  PRESENCE_STATES,
  EVENT_TYPE_PATTERN,
  SCOPE_PATTERN,
  publishSchema,
  presenceSchema,
  payloadSchema,
  isDescriptor,
  validateEvent,
  buildEvent,
  frameFor,
  connectionReceives,
  fanOut,
  clientsOf,
};