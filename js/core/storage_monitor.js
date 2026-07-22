/** StorageMonitor: observes usage and only clears non-auth caches. */
const StorageMonitor = (() => {
  'use strict';
  const subscribers = new Set();
  let timer = null;
  function initialize() { if (!timer) timer=setInterval(checkStorageUsage,60000); }
  function usage(storage) { let used=0; Object.keys(storage).forEach(k=>{ used += (storage.getItem(k)||'').length; }); return {used,available:5*1024*1024,keys:Object.keys(storage).length}; }
  async function getStorageStats() {
    const estimate=navigator.storage?.estimate ? await navigator.storage.estimate() : {};
    return { localStorage:usage(localStorage), sessionStorage:usage(sessionStorage), indexedDB:{used:estimate.usage||0,available:estimate.quota||0} };
  }
  async function checkStorageUsage() { const stats=await getStorageStats(); emit('USAGE_UPDATE',stats); return stats; }
  function clearStorage(type) {
    if (type==='localStorage' || type==='sessionStorage') {
      const store=window[type];
      Object.keys(store).filter(k=>/^(cache_|pref_|state_)/.test(k)).forEach(k=>store.removeItem(k));
      return true;
    }
    if (type==='cacheStorage' && 'caches' in window) return caches.keys().then(names=>Promise.all(names.map(n=>caches.delete(n))));
    if (type==='all') { window.StorageManager?.clearAll?.(); return 'caches' in window ? caches.keys().then(names=>Promise.all(names.map(n=>caches.delete(n)))) : true; }
    return false;
  }
  function subscribe(event,callback){const entry={event,callback};subscribers.add(entry);return()=>subscribers.delete(entry);}
  function emit(event,data){subscribers.forEach(e=>{if(e.event===event||e.event==='*')e.callback(data);});}
  function stopMonitoring(){if(timer)clearInterval(timer);timer=null;}
  return {initialize,startMonitoring:initialize,stopMonitoring,checkStorageUsage,getStorageStats,clearStorage,subscribe};
})();
window.StorageMonitor = StorageMonitor;
