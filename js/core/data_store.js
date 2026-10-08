/**
 * DataStore
 *
 * Coordinates memory cache, IndexedDB and authoritative backend fetches.
 * Cache keys are identifiers only; they are never converted into API paths.
 *
 * ── Per-key TTL Override ──────────────────────────────────────────────
 * Controllers can pass a per-call TTL override via the options object:
 *   DataStore.get('students', { ttl: 300000 })              // 5 minutes
 *   DataStore.get('attendance', { ttl: 15000, strategy: 'network-first' })
 *
 * The default TTL for each key is defined in the `policies` object below
 * (60 s for volatile data, 24 h for reference data, 7 d for long-lived).
 * If no policy matches, MEMORY_TTL (60 s) is used.
 *
 * ── Real‑time / Cross‑tab Updates ─────────────────────────────────────
 *  • BroadcastChannel ('kingsway-cache') — when the same origin fires
 *    CACHE_INVALIDATED, all non‑sending tabs invalidate the listed keys.
 *  • localStorage fallback (kingsway_cache_invalidation) — the same
 *    mechanism via the 'storage' event for browsers without BroadcastChannel.
 *  • Subscriber pattern — controllers can subscribe to keys and react to
 *    'local' and 'network' source events (see `subscribe()`).
 *
 * There is no WebSocket / SSE integration yet. If real‑time push is added,
 * use the subscriber API to deliver live updates:
 *   DataStore.subscribe('attendance', ({ key, data, source }) => { … });
 */
const DataStore = (() => {
  'use strict';

  const memoryCache = new Map();
  const subscribers = new Map();
  const inFlight = new Map();
  const MEMORY_LIMIT = 100;
  const MEMORY_TTL = 60000;

  // Stable cache scope for anonymous visitors. Every not-signed-in user (new or
  // returning, across all tabs) reads and writes public data under this single
  // sentinel, so the shared public-site cache is reused instead of being per-visit.
  // -1 is reserved and can never collide with a real users.id (positive integers),
  // so guests never share rows with authenticated accounts. There is no "guest"
  // account row in the DB — this is purely a cache-scope identity.
  const GUEST_USER_ID = -1;

  // Realtime-first defaults for an always-online school administration:
  // bounded snapshots are reused until their TTL or a mutation invalidation.
  // This prevents each page load from reopening PHP/MySQL while keeping a
  // short repair window for changes made outside the application event path.
  const DEFAULT_TTL = Object.freeze({
    REFERENCE: 300000,   // 5 minutes — live lists (classes, subjects, streams, terms)
    LONG: 3600000,       // 1 hour — slowly changing structures (departments, academic years)
    DIRECTORY: 300000,   // 5 minutes — student directory
  });

  const policies = Object.freeze({
    // Realtime mutation events invalidate these keys immediately. Cache-first
    // therefore removes repetitive PHP/MySQL reads while retaining bounded TTL
    // recovery when an external/cron change does not emit an event.
    classes: { ttl: DEFAULT_TTL.REFERENCE, strategy: 'cache-first' },
    streams: { ttl: DEFAULT_TTL.REFERENCE, strategy: 'cache-first' },
    subjects: { ttl: DEFAULT_TTL.REFERENCE, strategy: 'cache-first' },
    terms: { ttl: DEFAULT_TTL.REFERENCE, strategy: 'cache-first' },
    academic_years: { ttl: DEFAULT_TTL.LONG, strategy: 'cache-first' },
    departments: { ttl: DEFAULT_TTL.LONG, strategy: 'cache-first' },
    students: { ttl: DEFAULT_TTL.DIRECTORY, strategy: 'cache-first' },
    staff: { ttl: 1800000, strategy: 'cache-first' },
    attendance: { ttl: 60000, strategy: 'cache-first' },
    admissions: { ttl: 60000, strategy: 'cache-first' },
    school_profile: { ttl: DEFAULT_TTL.LONG, strategy: 'cache-first' },
  });

  function normalizeOptions(key, options) {
    if (typeof options === 'string') {
      console.warn('[DataStore] A string storeName is deprecated; pass an options object.');
      options = { storeName: options };
    }
    options = options || {};
    return {
      strategy: options.strategy || policies[key]?.strategy || 'stale-while-revalidate',
      ttl: Number(options.ttl || policies[key]?.ttl || MEMORY_TTL),
      forceRefresh: options.forceRefresh === true,
      storeName: options.storeName || getStoreNameForKey(key),
      endpoint: options.endpoint || null,
      fetcher: typeof options.fetcher === 'function' ? options.fetcher : null,
      params: options.params && typeof options.params === 'object' ? options.params : {},
      useMemory: options.useMemory !== false,
      useIndexedDB: options.useIndexedDB !== false,
      bypassCache: options.bypassCache === true,
      allowStaleOnError: options.allowStaleOnError !== false,
    };
  }

  function generateCacheKey(key, params = {}) {
    return Object.keys(params).length ? `${key}:${JSON.stringify(params)}` : key;
  }

  function getStoreNameForKey(key) {
    const map = {
      classes: 'reference_classes', streams: 'reference_streams',
      subjects: 'reference_subjects', terms: 'reference_terms',
      academic_years: 'reference_academic_years', departments: 'reference_departments',
      students: 'student_directory_cache', staff: 'staff_directory_cache',
      attendance: 'attendance_roster_cache', attendance_roster: 'attendance_roster_cache',
      admissions: 'admission_queue_cache', dashboard_school_admin: 'dashboard_cache',
    };
    return map[key] || 'dashboard_cache';
  }

  function currentUserId() {
    const auth = window.AuthContext;
    if (auth?.isAuthenticated?.()) {
      const id = auth.getUser?.()?.id ?? null;
      return id ?? GUEST_USER_ID;
    }
    return GUEST_USER_ID;
  }

  function currentRoleId() {
    const auth = window.AuthContext;
    const roles = auth?.getRoles?.() || [];
    const first = roles[0];
    return typeof first === 'object' ? first?.id ?? null : first ?? null;
  }

  function isExpired(entry) {
    return Boolean(entry?.expires_at && Date.now() > entry.expires_at);
  }

  function unwrap(record) {
    if (record && typeof record === 'object' && record.data !== undefined) return record.data;
    return record;
  }

  function setMemory(cacheKey, payload, ttl) {
    if (memoryCache.size >= MEMORY_LIMIT && !memoryCache.has(cacheKey)) {
      memoryCache.delete(memoryCache.keys().next().value);
    }
    memoryCache.set(cacheKey, {
      data: payload,
      cached_at: Date.now(),
      expires_at: Date.now() + ttl,
    });
  }

  async function readIndexed(storeName, cacheKey) {
    if (!window.KingswayDB || !storeName) return null;
    try {
      if (typeof KingswayDB.getCached === 'function') {
        return await KingswayDB.getCached(storeName, cacheKey, currentUserId());
      }
      if (typeof KingswayDB.get === 'function') return await KingswayDB.get(storeName, cacheKey);
    } catch (error) {
      console.warn('[DataStore] IndexedDB read failed:', storeName, cacheKey, error);
    }
    return null;
  }

  async function persist(storeName, cacheKey, payload, ttl) {
    if (!window.KingswayDB || !storeName) return;
    try {
      if (typeof KingswayDB.setCached === 'function') {
        await KingswayDB.setCached(
          storeName,
          { id: cacheKey, data: payload },
          ttl,
          currentUserId(),
          currentRoleId(),
        );
      }
    } catch (error) {
      console.warn('[DataStore] IndexedDB write failed:', storeName, cacheKey, error);
    }
  }

  async function peek(key, options = {}) {
    const config = normalizeOptions(key, options);
    const cacheKey = generateCacheKey(key, config.params);

    if (config.useMemory) {
      const entry = memoryCache.get(cacheKey);
      if (entry) return entry.data;
    }

    if (config.useIndexedDB) {
      const record = await readIndexed(config.storeName, cacheKey);
      const payload = unwrap(record);
      if (payload !== null && payload !== undefined) {
        if (config.useMemory) setMemory(cacheKey, payload, config.ttl);
        return payload;
      }
    }
    return null;
  }

  async function performFetch(key, config) {
    if (config.fetcher) return config.fetcher();
    if (!config.endpoint) {
      const error = new Error(`DataStore cache miss for "${key}" and no endpoint/fetcher was supplied.`);
      error.code = 'DATASTORE_NO_SOURCE';
      throw error;
    }
    if (!window.API?.apiCall) throw new Error('Centralized API is unavailable.');
    return window.API.apiCall(config.endpoint, 'GET', null, config.params);
  }

  async function fetchAndCache(key, config) {
    const cacheKey = generateCacheKey(key, config.params);
    if (inFlight.has(cacheKey)) return inFlight.get(cacheKey);

    const task = (async () => {
      const response = await performFetch(key, config);
      const payload = response?.success !== undefined && response?.data !== undefined
        ? response.data
        : response;
      if (payload === null || payload === undefined) {
        throw new Error(`The backend returned no data for ${key}.`);
      }
      if (config.useMemory) setMemory(cacheKey, payload, config.ttl);
      if (config.useIndexedDB) await persist(config.storeName, cacheKey, payload, config.ttl);
      emit(key, { key: cacheKey, data: payload, source: 'network' });
      return payload;
    })();

    inFlight.set(cacheKey, task);
    try { return await task; }
    finally { inFlight.delete(cacheKey); }
  }

  async function revalidate(key, config) {
    if (!config.endpoint && !config.fetcher) return null;
    try { return await fetchAndCache(key, config); }
    catch (error) {
      console.warn('[DataStore] Background revalidation failed:', key, error.message || error);
      return null;
    }
  }

  /**
   * Retrieve a cached value (or fetch from network).
   *
   * @param {string} key                    Cache key (must match a policy entry).
   * @param {object} [options]              Options bag.
   * @param {number} [options.ttl]          Override TTL in ms (default: policy TTL or 60000).
   * @param {string} [options.strategy]     Cache strategy (stale-while-revalidate, cache-first,
   *                                        cache-only, network-first).
   * @param {boolean} [options.forceRefresh] Skip cache and fetch from network.
   * @param {boolean} [options.bypassCache]  Skip memory + IndexedDB entirely.
   * @param {string} [options.endpoint]     API path to fetch (if not using fetcher).
   * @param {function} [options.fetcher]    Async function that returns data.
   * @param {object} [options.params]       Query params appended to endpoint.
   * @param {boolean} [options.useMemory]   Default true.
   * @param {boolean} [options.useIndexedDB] Default true.
   * @param {boolean} [options.allowStaleOnError] Default true — fall back to stale data
   *                                              when the network request fails.
   * @returns {Promise<*|null>}
   */
  async function get(key, options = {}) {
    const config = normalizeOptions(key, options);
    const cacheKey = generateCacheKey(key, config.params);

    if (config.bypassCache || config.forceRefresh) {
      return fetchAndCache(key, config);
    }

    const memoryEntry = config.useMemory ? memoryCache.get(cacheKey) : null;
    const memoryValue = memoryEntry?.data;
    const memoryFresh = memoryEntry && !isExpired(memoryEntry);

    let indexedRecord = null;
    let indexedValue = null;
    let indexedFresh = false;
    if (!memoryEntry && config.useIndexedDB) {
      indexedRecord = await readIndexed(config.storeName, cacheKey);
      indexedValue = unwrap(indexedRecord);
      indexedFresh = indexedRecord && !isExpired(indexedRecord);
      if (indexedValue !== null && indexedValue !== undefined && config.useMemory) {
        setMemory(cacheKey, indexedValue, config.ttl);
      }
    }

    const cachedValue = memoryValue ?? indexedValue;
    const cacheFresh = Boolean(memoryFresh || indexedFresh);

    if (config.strategy === 'cache-only') return cachedValue ?? null;

    // stale-while-revalidate only serves cached data while it is still fresh
    // (within TTL); an expired snapshot must not keep rendering old data, so it
    // falls through to the network below. Offline fallback still applies.
    if (
      config.strategy === 'stale-while-revalidate' &&
      cacheFresh &&
      cachedValue !== null &&
      cachedValue !== undefined
    ) {
      revalidate(key, config);
      return cachedValue;
    }

    if (config.strategy === 'cache-first' && cacheFresh) return cachedValue;

    try {
      return await fetchAndCache(key, config);
    } catch (error) {
      if (config.allowStaleOnError && cachedValue !== null && cachedValue !== undefined) {
        console.warn('[DataStore] Network failed; returning cached data:', key, error.message || error);
        return cachedValue;
      }
      throw error;
    }
  }

  async function getOrFetch(key, options = {}) {
    const config = normalizeOptions(key, options);
    if (!config.endpoint && !config.fetcher) {
      throw new TypeError(`DataStore.getOrFetch("${key}") requires endpoint or fetcher.`);
    }
    const cacheKey = generateCacheKey(key, config.params);

    // Resolve cached value + freshness the same way get() does. stale-while-
    // revalidate serves cache only while fresh; everything else goes to the
    // network so page controllers render current data for daily operations.
    let cachedValue = null;
    let cacheFresh = false;
    const memoryEntry = config.useMemory ? memoryCache.get(cacheKey) : null;
    if (memoryEntry) {
      cachedValue = memoryEntry.data;
      cacheFresh = !isExpired(memoryEntry);
    }
    if (cachedValue === null || cachedValue === undefined) {
      if (config.useIndexedDB) {
        const record = await readIndexed(config.storeName, cacheKey);
        const value = unwrap(record);
        if (value !== null && value !== undefined) {
          cachedValue = value;
          cacheFresh = Boolean(record && !isExpired(record));
          if (config.useMemory) setMemory(cacheKey, value, config.ttl);
        }
      }
    }

    if (
      cachedValue !== null &&
      cachedValue !== undefined &&
      config.strategy === 'stale-while-revalidate' &&
      !config.forceRefresh &&
      cacheFresh
    ) {
      revalidate(key, config);
      return { data: cachedValue, source: 'cache', stale: false };
    }
    try {
      const data = await fetchAndCache(key, config);
      return { data, source: 'network', stale: false };
    } catch (networkError) {
      if (cachedValue !== null && cachedValue !== undefined && config.allowStaleOnError) {
        return { data: cachedValue, source: 'cache', stale: true, networkError };
      }
      throw networkError;
    }
  }

  async function fetchPage(key, options = {}) {
    const result = await getOrFetch(key, options);
    return result.data;
  }

  async function set(key, data, options = {}) {
    const config = normalizeOptions(key, options);
    const cacheKey = generateCacheKey(key, config.params);
    if (config.useMemory) setMemory(cacheKey, data, config.ttl);
    if (config.useIndexedDB) await persist(config.storeName, cacheKey, data, config.ttl);
    emit(key, { key: cacheKey, data, source: 'local' });
    return data;
  }

  async function invalidate(key, options = {}) {
    const config = normalizeOptions(key, options);
    const cacheKey = generateCacheKey(key, config.params);

    // Remove the exact entry plus every parameterised variant of it
    // (cacheKey:{"..."} — e.g. filtered news lists, per-id detail views). A single
    // mutation (PUT /api/website/news/5) then refreshes ALL views of that resource,
    // not just the bare key. Safe: prefix always includes the ':' separator.
    const prefix = cacheKey + ':';
    for (const k of [...memoryCache.keys()]) {
      if (k !== cacheKey && k.startsWith(prefix)) memoryCache.delete(k);
    }
    memoryCache.delete(cacheKey);

    if (window.KingswayDB && config.storeName) {
      try {
        await KingswayDB.remove?.(config.storeName, cacheKey);
        const rows = await KingswayDB.getAll?.(config.storeName) || [];
        for (const row of rows) {
          const id = row && (row.id ?? row.key);
          if (id && id !== cacheKey && String(id).startsWith(prefix)) {
            await KingswayDB.remove(config.storeName, id);
          }
        }
      } catch (error) { console.warn('[DataStore] IndexedDB invalidation failed:', error); }
    }
    emit('INVALIDATED', { key, cacheKey });
  }

  async function invalidateMany(keys = []) {
    for (const key of keys.filter(Boolean)) await invalidate(key);
  }

  async function invalidateRelated(key) {
    const rules = {
      students: ['students', 'student_directory_cache', 'class_list_cache'],
      classes: ['classes', 'student_directory_cache', 'class_list_cache'],
      attendance: ['attendance', 'attendance_roster'],
      admissions: ['admissions', 'admission_queue_cache'],
      staff: ['staff', 'staff_directory_cache'],
    };
    await invalidateMany(rules[key] || []);
  }

  function subscribe(key, callback) {
    if (!subscribers.has(key)) subscribers.set(key, new Set());
    subscribers.get(key).add(callback);
    return () => subscribers.get(key)?.delete(callback);
  }

  function emit(key, data) {
    subscribers.get(key)?.forEach((callback) => {
      try { callback(data); }
      catch (error) { console.error('[DataStore] Subscriber failed:', error); }
    });
  }

  async function clearAll() {
    memoryCache.clear();
    const stores = [
      'student_directory_cache', 'staff_directory_cache', 'class_list_cache',
      'admission_queue_cache', 'attendance_roster_cache', 'dashboard_cache',
    ];
    for (const store of stores) {
      try { await window.KingswayDB?.clear?.(store); }
      catch (error) { console.warn('[DataStore] Failed to clear store:', store, error); }
    }
    emit('CLEARED', {});
  }

  const CACHE_INVALIDATION_KEY = 'kingsway_cache_invalidation';

  /**
   * Another tab changed these targets: drop our cached copies AND refresh the
   * loaders that read them, so this tab does not sit on stale data until the
   * next user action.
   *
   * Both cross-tab channels (BroadcastChannel and the storage-event fallback)
   * go through here so they cannot drift apart.
   */
  function scheduleRefresh(keys, source) {
    const targets = (Array.isArray(keys) ? keys : []).filter((k) => typeof k === 'string' && k);
    window.APIRealtime?.schedule?.(targets);
    window.dispatchEvent(new CustomEvent('kingsway:data-mutated', {
      detail: { source, targets }
    }));
  }

  function initCrossTabInvalidation() {
    if (typeof BroadcastChannel !== 'undefined') {
      try {
        const channel = new BroadcastChannel('kingsway-cache');
        channel.onmessage = (event) => {
          if (event.data?.type === 'CACHE_INVALIDATED' && Array.isArray(event.data?.keys)) {
            event.data.keys.forEach((key) => invalidate(key));
            scheduleRefresh(event.data.keys, 'cross-tab');
          }
        };
      } catch (_) {}
    }

    window.addEventListener('storage', (event) => {
      if (event.key === CACHE_INVALIDATION_KEY && event.newValue) {
        try {
          const message = JSON.parse(event.newValue);
          if (message?.type === 'CACHE_INVALIDATED' && Array.isArray(message?.keys)) {
            const keys = message.keys.filter((k) => typeof k === 'string');
            keys.forEach((key) => invalidate(key));
            scheduleRefresh(keys, 'cross-tab');
          }
        } catch (_) {}
      }
    });
  }

  initCrossTabInvalidation();

  return {
    get, getOrFetch, fetchPage, peek, set, invalidate, invalidateMany,
    invalidateRelated, subscribe,
    unsubscribeAll: () => subscribers.clear(),
    clearAll,
    getStats: () => ({ memory: { size: memoryCache.size, limit: MEMORY_LIMIT }, inFlight: inFlight.size }),
    DEFAULT_TTL,
  };
})();
window.DataStore = DataStore;
