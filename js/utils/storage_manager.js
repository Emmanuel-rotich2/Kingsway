/** StorageManager: preferences, UI state and cache only. Never owns authentication. */
class KingswayStorageManager {
  constructor() {
    this.memoryStorage = new Map();
    this.initialized = false;
    this.capabilities = { localStorage: false, sessionStorage: false, indexedDB: false, cookies: false };
  }
  initialize() {
    if (this.initialized) return this;
    this.capabilities.localStorage = this.testWebStorage('localStorage');
    this.capabilities.sessionStorage = this.testWebStorage('sessionStorage');
    this.capabilities.indexedDB = 'indexedDB' in window;
    this.capabilities.cookies = navigator.cookieEnabled;
    this.initialized = true;
    return this;
  }
  testWebStorage(type) {
    try { const key='__kps_test__'; window[type].setItem(key,'1'); window[type].removeItem(key); return true; }
    catch (_) { return false; }
  }
  isAvailable(type) { if (!this.initialized) this.initialize(); return this.capabilities[type] === true; }
  setPreference(key, value) { return this.write('pref_', key, value, 'localStorage'); }
  getPreference(key, fallback=null) { return this.read('pref_', key, fallback, ['localStorage','sessionStorage']); }
  setSessionState(key, value) { return this.write('state_', key, value, 'sessionStorage'); }
  getSessionState(key, fallback=null) { return this.read('state_', key, fallback, ['sessionStorage']); }
  setCache(key, value, ttl=3600000) { return this.write('cache_', key, { value, timestamp:Date.now(), ttl }, 'localStorage'); }
  getCache(key, fallback=null) {
    const entry=this.read('cache_', key, null, ['localStorage']);
    if (!entry) return fallback;
    if (entry.ttl && Date.now()-entry.timestamp>entry.ttl) { localStorage.removeItem('cache_'+key); return fallback; }
    return entry.value;
  }
  write(prefix,key,value,preferred) {
    const data=JSON.stringify(value);
    if (this.isAvailable(preferred)) { try { window[preferred].setItem(prefix+key,data); return true; } catch (_) {} }
    this.memoryStorage.set(prefix+key,value); return true;
  }
  read(prefix,key,fallback,stores) {
    for (const store of stores) if (this.isAvailable(store)) try {
      const raw=window[store].getItem(prefix+key); if (raw!==null) return JSON.parse(raw);
    } catch (_) {}
    return this.memoryStorage.has(prefix+key) ? this.memoryStorage.get(prefix+key) : fallback;
  }
  clearSessionState() { this.clearByPrefixes(['state_'], sessionStorage); }
  clearExpiredCache() {
    Object.keys(localStorage).filter(k=>k.startsWith('cache_')).forEach(k=>{ try {
      const e=JSON.parse(localStorage.getItem(k)); if (e.ttl && Date.now()-e.timestamp>e.ttl) localStorage.removeItem(k);
    } catch (_) { localStorage.removeItem(k); } });
  }
  clearByPrefixes(prefixes, storage) {
    Object.keys(storage).forEach(k=>{ if(prefixes.some(p=>k.startsWith(p))) storage.removeItem(k); });
    [...this.memoryStorage.keys()].forEach(k=>{ if(prefixes.some(p=>k.startsWith(p))) this.memoryStorage.delete(k); });
  }
  clearAll() {
    this.clearByPrefixes(['pref_','cache_','state_'], localStorage);
    this.clearByPrefixes(['pref_','cache_','state_'], sessionStorage);
  }
  async getStorageStats() {
    const estimate = navigator.storage?.estimate ? await navigator.storage.estimate() : {};
    return { localStorage:{keys:Object.keys(localStorage).length}, sessionStorage:{keys:Object.keys(sessionStorage).length}, indexedDB:{used:estimate.usage||0,available:estimate.quota||0}, memory:{entries:this.memoryStorage.size} };
  }
}
if (!window.StorageManager || typeof window.StorageManager.initialize !== 'function') {
  window.StorageManager = new KingswayStorageManager();
}
window.StorageManager.initialize();
