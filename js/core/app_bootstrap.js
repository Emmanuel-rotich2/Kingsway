/** Kingsway single application bootstrap. */
const KingswayBootstrap = (() => {
  'use strict';
  let bootPromise = null;

  async function safe(name, callback, required = false) {
    try { return await callback(); }
    catch (error) {
      if (error?.name === 'AbortError' || error?.cancelled) return null;
      (required ? console.error : console.warn)(`[Bootstrap] ${name} failed`, error);
      if (required) throw error;
      return null;
    }
  }

  async function initialize() {
    if (bootPromise) return bootPromise;
    bootPromise = (async () => {
      window.__APP_BOOTING__ = true;
      window.__APP_BOOTED__ = false;
      const route = new URLSearchParams(location.search).get('route');

      await safe('authentication', async () => {
        if (window.AuthContext?.initialize) await AuthContext.initialize();
        else if (window.AuthContext?.ready) await AuthContext.ready();
      }, true);

      if (!window.AuthContext?.isAuthenticated?.()) {
        if (localStorage.getItem('dev_bypass_auth') !== 'true') {
          location.replace(`${window.APP_BASE || ''}/index.php`);
          return { redirected: true };
        }
      }

      await safe('session', () => window.SessionManager?.initialize?.());
      await safe('IndexedDB', () => window.KingswayDB?.initialize?.());
      await safe('storage manager', () => window.StorageManager?.initialize?.());

      window.ConnectivityManager?.initialize?.();
      window.StorageMonitor?.initialize?.();
      window.BFCacheHandler?.initialize?.();

      window.ErrorReporter?.initialize?.({
        enabled: true,
        localCaptureEnabled: true,
        remoteErrorsEnabled: false,
        remoteTelemetryEnabled: false
      });

      window.SpeculativeLoader?.initialize?.({ enabled: false });

      if (needsAcademicContext(route)) {
        await safe('academic context', async () => {
          if (window.AcademicContext?.init) await AcademicContext.init();
          window.AcademicContext?.listenForChanges?.();
        });
      }

      if (route && route !== 'loading' && window.AppRouteAccess?.authorizeRoute) {
        const access = await safe('route authorization', () => AppRouteAccess.authorizeRoute(route));
        if (access && !access.authorized) {
          window.showNotification?.('You are not allowed to open that page.', 'warning');
          await AppRouteAccess.redirectToAllowedRoute(route);
          return { redirected: true };
        }
      }

      await safe('service worker', () => window.ServiceWorkerManager?.initialize?.());

      window.__APP_BOOTED__ = true;
      const detail = { route, user: window.AuthContext?.getUser?.() || null };
      window.dispatchEvent(new CustomEvent('kingsway:ready', { detail }));
      return detail;
    })().finally(() => { window.__APP_BOOTING__ = false; });
    return bootPromise;
  }

  function needsAcademicContext(route) {
    const value = String(route || '').toLowerCase();
    if (!value) return false;
    return [
      'academic',
      'academics',
      'class',
      'stream',
      'subject',
      'learning',
      'assessment',
      'exam',
      'result',
      'grade',
      'term',
      'year',
      'calendar',
      'timetable',
      'syllabus',
      'scheme',
      'lesson',
      'curriculum',
      'report_card',
      'promotion',
      'placement',
      'teacher_workload',
      'teacher_performance'
    ].some((fragment) => value.includes(fragment));
  }

  return { initialize, needsAcademicContext };
})();
window.KingswayBootstrap = KingswayBootstrap;

if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', () => KingswayBootstrap.initialize(), { once: true });
} else {
  KingswayBootstrap.initialize();
}
