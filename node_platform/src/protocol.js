'use strict';

/**
 * Realtime change-descriptor protocol.
 *
 * THE RULE: a payload carries identifiers and state-change metadata ONLY —
 * never a learner, payment, health, counselling, discipline or case record
 * body. `additionalProperties: false` in `payloadSchema` below is the machine
 * enforcement point; an unknown key is a 400, not a warning. PHP remains the
 * authorization boundary and re-authorizes any follow-up data fetch.
 *
 * Event types are deliberately few and named by what the BROWSER must do:
 *
 *   CACHE_INVALIDATE — purge named DataStore keys, then refetch. Generic.
 *   ROW_UPDATED      — one entity changed; patch it surgically if the page
 *                      registered a patch handler, otherwise fall back to a
 *                      region refresh.
 *   ROW_DELETED      — one entity removed.
 *   JOB_PROGRESS     — 0-100 progress of a long Python batch job.
 *   JOB_COMPLETE     — batch job finished.
 *   JOB_FAILED       — batch job failed.
 *   PRESENCE         — a peer user started/stopped editing a named row.
 *   CONFLICT_HINT    — withheld-from-you update: you are editing this row.
 *   PAYMENT_ALERT    — a payment state changed for the authorized scope.
 *   SYNC             — coalesced generic refresh for a set of targets.
 *   DATA_CHANGED     — legacy alias kept for the existing PHP publisher.
 */

/** Descriptor fields a publisher may send. Nothing else is accepted. */
const PAYLOAD_FIELDS = new Set([
  'id', 'ids', 'entity_id', 'record_id', 'job_id', 'version', 'progress',
  'status', 'targets', 'changed_fields', 'domain', 'action', 'method',
  'activity_key', 'responder_id', 'user_id', 'state', 'origin_user_id',
  'source', 'reason',
]);

const EVENT_TYPES = Object.freeze([
  // Engine liveness only. A comment heartbeat (`: heartbeat`) keeps proxies
  // from timing the connection out but is consumed by the browser and never
  // reaches JS, so it cannot prove the stream is alive to application code.
  // The browser's polling supervisor needs an app-visible liveness signal.
  'HEARTBEAT',
  'CACHE_INVALIDATE',
  'ROW_UPDATED',
  'ROW_DELETED',
  'JOB_PROGRESS',
  'JOB_COMPLETE',
  'JOB_FAILED',
  'PRESENCE',
  'CONFLICT_HINT',
  'PAYMENT_ALERT',
  'SYNC',
  'DATA_CHANGED',
]);

const EVENT_TYPE_PATTERN = /^[A-Z][A-Z0-9_]{1,63}$/;
const SCOPE_PATTERN = /^[a-zA-Z0-9:_-]{1,128}$/;
const MAX_PAYLOAD_KEYS = 20;
const MAX_ARRAY_ITEMS = 100;
const MAX_STRING = 256;
const MAX_ARRAY_STRING = 128;
const ACTIVITY_KEY_PATTERN = /^[a-zA-Z0-9:_.-]{1,128}$/;
const PRESENCE_STATES = Object.freeze(['editing', 'viewing', 'idle']);

const boundedValue = {
  anyOf: [
    { type: 'string', maxLength: MAX_STRING },
    { type: 'integer', minimum: 0 },
    { type: 'boolean' },
    { type: 'null' },
    {
      type: 'array',
      maxItems: MAX_ARRAY_ITEMS,
      items: {
        anyOf: [
          { type: 'string', maxLength: MAX_ARRAY_STRING },
          { type: 'integer', minimum: 0 },
        ],
      },
    },
  ],
};

/** JSON Schema used as the Fastify request body schema: the allowlist. */
const payloadSchema = {
  type: 'object',
  maxProperties: MAX_PAYLOAD_KEYS,
  additionalProperties: false,
  properties: Object.fromEntries([...PAYLOAD_FIELDS].map((field) => [field, boundedValue])),
};

const publishSchema = {
  body: {
    type: 'object',
    additionalProperties: false,
    required: ['type', 'scope'],
    properties: {
      type: { type: 'string', enum: EVENT_TYPES },
      scope: { type: 'string', pattern: '^[a-zA-Z0-9:_-]{1,128}$' },
      payload: payloadSchema,
      emitted_at: { type: 'string', maxLength: 40 },
      // Correlation id so a browser report can be tied to a PHP request id.
      request_id: { type: 'string', maxLength: 64 },
    },
  },
};

const presenceSchema = {
  body: {
    type: 'object',
    additionalProperties: false,
    required: ['activity_key', 'state'],
    properties: {
      activity_key: { type: 'string', pattern: '^[a-zA-Z0-9:_.-]{1,128}$' },
      state: { type: 'string', enum: PRESENCE_STATES },
      scope: { type: 'string', pattern: '^[a-zA-Z0-9:_-]{1,128}$' },
    },
  },
};

/** Unit-level mirror of payloadSchema, kept for direct assertions. */
function isDescriptor(payload) {
  if (!payload || typeof payload !== 'object' || Array.isArray(payload)) return false;
  const entries = Object.entries(payload);
  if (entries.length > MAX_PAYLOAD_KEYS) return false;
  return entries.every(([key, value]) => {
    if (!PAYLOAD_FIELDS.has(key)) return false;
    if (Array.isArray(value)) {
      return value.length <= MAX_ARRAY_ITEMS && value.every(
        (item) => (typeof item === 'string' && item.length <= MAX_ARRAY_STRING)
          || (Number.isSafeInteger(item) && item >= 0),
      );
    }
    return (
      (typeof value === 'string' && value.length <= MAX_STRING)
      || (Number.isSafeInteger(value) && value >= 0)
      || typeof value === 'boolean'
      || value === null
    );
  });
}

const hasAny = (payload, keys) => keys.some((key) => payload[key] !== undefined && payload[key] !== null);

/**
 * Per-type semantic rules, applied after shape validation. These are the rules
 * that keep a malformed descriptor from becoming a meaningless client event.
 */
function validateEvent(event) {
  const payload = event.payload || {};

  if (event.type === 'CACHE_INVALIDATE' || event.type === 'DATA_CHANGED' || event.type === 'SYNC') {
    // `targets` is OPTIONAL on a refresh signal, not required. The real PHP
    // publisher sends {domain, action, method} and only adds precise cache
    // targets when it knows them; the browser falls back to a domain-level
    // invalidation plus a loader refresh. What is never allowed is a malformed
    // target list, and that is checked for every event type below.
    if (payload.targets !== undefined && !Array.isArray(payload.targets)) {
      return 'payload.targets must be an array when present';
    }
  }

  if (event.type === 'ROW_UPDATED' || event.type === 'ROW_DELETED') {
    if (!hasAny(payload, ['entity_id', 'id', 'record_id'])) {
      return `payload.entity_id is required for ${event.type}`;
    }
    if (!Array.isArray(payload.targets) || payload.targets.length === 0) {
      return `payload.targets is required for ${event.type}`;
    }
    // activity_key lets the engine suppress the patch for the connection that
    // is actively editing that exact row and send CONFLICT_HINT instead.
    if (payload.activity_key !== undefined && !ACTIVITY_KEY_PATTERN.test(String(payload.activity_key))) {
      return 'payload.activity_key has an invalid format';
    }
  }

  if (event.type === 'JOB_PROGRESS') {
    if (!hasAny(payload, ['job_id', 'id'])) return 'payload.job_id is required for JOB_PROGRESS';
    if (payload.progress !== undefined) {
      const progress = Number(payload.progress);
      if (!Number.isFinite(progress) || progress < 0 || progress > 100) {
        return 'payload.progress must be between 0 and 100';
      }
    }
  }

  if (event.type === 'JOB_COMPLETE' || event.type === 'JOB_FAILED') {
    if (!hasAny(payload, ['job_id', 'id'])) return `payload.job_id is required for ${event.type}`;
    if (payload.status !== undefined && typeof payload.status !== 'string') {
      return 'payload.status must be a string';
    }
  }

  if (event.type === 'PRESENCE') {
    if (!hasAny(payload, ['activity_key'])) return 'payload.activity_key is required for PRESENCE';
    if (payload.state !== undefined && !PRESENCE_STATES.includes(payload.state)) {
      return `payload.state must be one of ${PRESENCE_STATES.join(', ')}`;
    }
  }

  return null;
}

function buildEvent(body, { now = () => new Date().toISOString() } = {}) {
  if (!body || typeof body !== 'object' || Array.isArray(body)) return null;
  if (typeof body.type !== 'string' || !EVENT_TYPES.includes(body.type)) return null;
  if (typeof body.scope !== 'string' || !SCOPE_PATTERN.test(body.scope)) return null;
  if (!isDescriptor(body.payload || {})) return null;

  const event = {
    type: body.type,
    scope: body.scope,
    payload: body.payload || {},
    emitted_at: typeof body.emitted_at === 'string' ? body.emitted_at : now(),
  };
  if (typeof body.request_id === 'string') event.request_id = body.request_id;

  const invalid = validateEvent(event);
  if (invalid) {
    const error = new Error(invalid);
    error.code = 'INVALID_DESCRIPTOR';
    error.event = event;
    throw error;
  }
  return event;
}

/**
 * Serialize one SSE frame.
 *
 * The SSE `event:` name stays the single stable `kingsway` value on purpose:
 * a newer PHP publishing an event type this client build has never heard of
 * must still reach the browser's handler instead of being silently dropped by
 * a listener awaiting a named event. The browser routes on `event.type` inside
 * the payload, and unknown types degrade to a cache invalidation.
 *
 * `id` is the engine's monotonic counter so a reconnecting browser can discard
 * duplicates it already applied. The authoritative resync is still PHP's
 * catch-up reader — the gateway deliberately keeps no durable event log.
 */
function frameFor(event, id) {
  const prefix = Number.isSafeInteger(id) ? `id: ${id}\n` : '';
  return `${prefix}event: kingsway\ndata: ${JSON.stringify(event)}\n\n`;
}

/**
 * Scope routing. "all" is the global staff channel; scoped channels are minted
 * by PHP per connection (users:12, stream:45, family:8…). A connection holding
 * "all" receives every event; a scoped connection receives only its own scope,
 * which is what stops a parent ever inheriting a staff broadcast.
 */
function connectionReceives(client, event) {
  if (client.channels.includes('all')) return true;
  if (event.scope === 'all') return client.channels.includes('all');
  return client.channels.includes(event.scope);
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
};