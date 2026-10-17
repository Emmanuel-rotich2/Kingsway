import { createHash, timingSafeEqual } from 'node:crypto';

const EXPECTED_PREFIX = 'kingsway:worker-secret:';

export function verifyWorkerSecret(req, config) {
  const provided = req.headers?.['x-kingsway-worker-secret'];
  const expected = config?.security?.workerSecret;
  if (typeof expected !== 'string' || expected === '') return false;
  if (typeof provided !== 'string' || provided === '') return false;

  const digest = (value) => createHash('sha256').update(EXPECTED_PREFIX + value).digest();
  return timingSafeEqual(digest(provided), digest(expected));
}