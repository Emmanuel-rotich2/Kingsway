import { getBus } from '../realtime/bus.js';

/**
 * Domain-event bridge: translates PHP domain events (`type`/`scope`/`payload`)
 * into bus messages that SSE connections subscribed to the matching scope
 * deliver as `event: kingsway` frames.
 *
 * Scope → channel mapping mirrors RealtimeScopeResolver:
 *   - 'all'        → 'broadcast'
 *   - 'users:<id>' → 'user:<id>'   (SSE subscribes `user:{sub}`)
 *   - other scopes → the scope string verbatim (view/presence/… capabilities)
 */

export function channelForScope(scope) {
  const value = String(scope || '').trim();
  if (value === 'all') return 'broadcast';
  if (value.startsWith('users:')) return `user:${value.slice('users:'.length)}`;
  return value;
}

export async function broadcast({ type, scope, payload = {}, correlationId } = {}) {
  if (typeof type !== 'string' || type === '' || typeof scope !== 'string' || scope === '') {
    return { ok: false, reason: 'type and scope are required' };
  }
  const channel = channelForScope(scope);
  if (channel === '') return { ok: false, reason: 'unroutable scope' };

  await getBus().publish(channel, {
    type: 'kingsway',
    payload: { type, scope, payload },
    source: 'domain',
    correlationId,
  });
  return { ok: true, channel };
}

export const events = { broadcast };