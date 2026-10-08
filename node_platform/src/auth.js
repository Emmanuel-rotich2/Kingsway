'use strict';

/**
 * Capability verification for the realtime gateway.
 *
 * Local verification only — the gateway NEVER calls the DB or PHP to check a
 * token. PHP mints a short-lived HS256 capability JWT carrying
 * `{ realtime: true, channels: [...] }` from `GET /api/realtime/stream-token`,
 * having already resolved RBAC and row-level scope server-side. Node proves the
 * signature and the time bounds, then routes strictly by the encoded channels.
 *
 * `jsonwebtoken` is used instead of hand-rolled HMAC maths because it is the
 * audited implementation: it pins the accepted algorithm set (so `alg: none`
 * and HMAC/RSA confusion are structurally impossible), validates exp/nbf/iat,
 * and does constant-time signature comparison.
 */

const jwt = require('jsonwebtoken');
const crypto = require('node:crypto');

const CHANNEL_PATTERN = /^[a-zA-Z0-9:_-]{1,128}$/;
const MAX_TOKEN_LENGTH = 8192;

function safeEqual(a, b) {
  const left = Buffer.from(String(a));
  const right = Buffer.from(String(b));
  if (left.length !== right.length) return false;
  return crypto.timingSafeEqual(left, right);
}

/**
 * @returns {{userId: number, channels: string[]}|null} null for ANY failure —
 * callers must never distinguish "expired" from "forged" to the client.
 */
function verifyCapability(token, config) {
  if (typeof token !== 'string' || token.length === 0 || token.length > MAX_TOKEN_LENGTH) return null;
  if (!config.jwtSecret) return null;

  let claims;
  try {
    claims = jwt.verify(token, config.jwtSecret, {
      // Pinning the algorithm list is the single most important line here.
      algorithms: ['HS256'],
      issuer: config.jwtIssuer,
      audience: config.jwtAudience,
      clockTolerance: config.tokenClockSkewSeconds,
      complete: false,
    });
  } catch (_) {
    return null;
  }

  if (!claims || typeof claims !== 'object') return null;
  if (claims.realtime !== true) return null;

  const now = Math.floor(Date.now() / 1000);
  if (!Number.isInteger(claims.exp) || !Number.isInteger(claims.iat)) return null;
  if (claims.exp <= now) return null;
  if (claims.iat > now + config.tokenClockSkewSeconds) return null;
  // A capability must be short-lived by construction, not merely by request:
  // reject any token whose validity span exceeds the configured ceiling so a
  // minted-but-long token cannot outlive the policy.
  if (claims.exp - claims.iat > config.maxTokenTtlSeconds) return null;

  const userId = Number.isInteger(claims.user_id) ? claims.user_id
    : (Number.isInteger(claims.sub) ? claims.sub : null);
  if (userId === null) return null;

  if (!Array.isArray(claims.channels) || claims.channels.length < 1) return null;
  if (claims.channels.length > config.maxChannels) return null;
  for (const channel of claims.channels) {
    if (typeof channel !== 'string' || !CHANNEL_PATTERN.test(channel)) return null;
  }
  const channels = [...new Set(claims.channels)];
  // Reject a token whose channel list collapsed under de-duplication: it means
  // the publisher sent malformed input.
  if (channels.length !== claims.channels.length) return null;

  return { userId, channels };
}

function isLoopback(req) {
  const address = (req.socket?.remoteAddress || req.raw?.socket?.remoteAddress || '')
    .replace(/^::ffff:/, '');
  return address === '127.0.0.1' || address === '::1';
}

/**
 * Internal publish routes require BOTH the shared worker secret AND a permitted
 * source address (loopback, or the configured allowlist). A secret alone is not
 * enough: it must never be sufficient from an arbitrary network peer.
 */
function hasPublishCredential(req, config) {
  const headers = req.headers || {};
  const secret = headers['x-kingsway-worker-secret'] || '';
  if (!config.publishSecret || !safeEqual(secret, config.publishSecret)) return false;

  if (isLoopback(req)) return true;

  const allowed = String(config.publishAllowIps || '')
    .split(',')
    .map((ip) => ip.trim())
    .filter(Boolean);
  const remote = (req.socket?.remoteAddress || req.raw?.socket?.remoteAddress || '')
    .replace(/^::ffff:/, '');
  return allowed.includes(remote);
}

module.exports = { verifyCapability, isLoopback, hasPublishCredential, safeEqual };