/**
 * Service Worker Manager
 * 
 * Manages service worker registration, updates, and communication.
 * Handles safe updates without interrupting user workflows.
 */

const ServiceWorkerManager = (function() {
  'use strict';

  let registration = null;
  let newWorker = null;
  let isUpdateAvailable = false;
  let subscribers = new Set();

  /**
   * Initialize service worker
   */
  async function initialize() {
    if (!('serviceWorker' in navigator)) {
      console.warn('[ServiceWorkerManager] Service workers not supported');
      return false;
    }

    try {
      console.log('[ServiceWorkerManager] Registering service worker...');
      
      const swBase = (window.APP_BASE || '').replace(/\/+$/, '');
      registration = await navigator.serviceWorker.register(swBase + '/service-worker.js', {
        scope: swBase + '/',
        updateViaCache: 'none'
      });

      console.log('[ServiceWorkerManager] Service worker registered:', registration.scope);
      registration.update().catch((error) => {
        console.warn('[ServiceWorkerManager] Service worker update check failed:', error);
      });

      // Listen for updates
      registration.addEventListener('updatefound', handleUpdateFound);
      
      // Listen for controller change
      navigator.serviceWorker.addEventListener('controllerchange', handleControllerChange);
      
      // Listen for messages from service worker
      navigator.serviceWorker.addEventListener('message', handleServiceWorkerMessage);
      
      // Check for existing service worker
      if (registration.waiting) {
        handleUpdateFound();
      }

      return true;
    } catch (error) {
      console.error('[ServiceWorkerManager] Service worker registration failed:', error);
      return false;
    }
  }

  /**
   * Handle service worker update found
   */
  function handleUpdateFound() {
    console.log('[ServiceWorkerManager] Service worker update found');
    
    newWorker = registration.installing || registration.waiting;
    
    if (newWorker) {
      newWorker.addEventListener('statechange', () => {
        if (newWorker.state === 'installed' && navigator.serviceWorker.controller) {
          // New service worker is waiting, but old one is still active
          isUpdateAvailable = true;
          emit('UPDATE_AVAILABLE', { version: newWorker.scriptURL });
          showUpdateNotification();
        }
      });
    }
  }

  /**
   * Handle controller change (service worker activated)
   */
  function handleControllerChange() {
    console.log('[ServiceWorkerManager] Service worker controller changed');
    emit('CONTROLLER_CHANGED', {});
    
    // Reload page to activate new service worker
    // TODO: Save form state before reload
    window.location.reload();
  }

  /**
   * Handle messages from service worker
   */
  function handleServiceWorkerMessage(event) {
    const { type, data } = event.data;
    console.log('[ServiceWorkerManager] Message from service worker:', type, data);
    
    switch (type) {
      case 'CACHE_STATS':
        emit('CACHE_STATS', data);
        break;
      default:
        emit('SERVICE_WORKER_MESSAGE', { type, data });
    }
  }

  /**
   * Show update notification to user
   */
  function showUpdateNotification() {
    // Check if there's an active form that shouldn't be interrupted
    const hasActiveForm = document.querySelector('form:has(*:focus)') || 
                          document.querySelector('.modal.show');
    
    if (hasActiveForm) {
      console.log('[ServiceWorkerManager] Active form detected, delaying update notification');
      // Show notification after form is submitted or closed
      return;
    }

    const notification = document.createElement('div');
    notification.id = 'sw-update-notification';
    notification.style.cssText = `
      position: fixed;
      top: 20px;
      right: 20px;
      background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
      color: white;
      padding: 20px;
      border-radius: 12px;
      box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
      z-index: 10000;
      display: flex;
      align-items: center;
      gap: 15px;
      font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
      max-width: 400px;
    `;

    notification.innerHTML = `
      <div style="flex: 1;">
        <div style="font-weight: 600; margin-bottom: 5px;">Update Available</div>
        <div style="font-size: 14px; opacity: 0.9;">A new version of Kingsway is ready to install.</div>
      </div>
      <div style="display: flex; gap: 10px;">
        <button id="sw-update-later" style="
          background: rgba(255,255,255,0.2);
          color: white;
          border: none;
          padding: 8px 16px;
          border-radius: 6px;
          cursor: pointer;
          font-size: 14px;
        ">Later</button>
        <button id="sw-update-now" style="
          background: white;
          color: #667eea;
          border: none;
          padding: 8px 16px;
          border-radius: 6px;
          cursor: pointer;
          font-weight: 600;
          font-size: 14px;
        ">Update Now</button>
      </div>
    `;

    document.body.appendChild(notification);

    // Handle buttons
    document.getElementById('sw-update-later').addEventListener('click', () => {
      notification.remove();
    });

    document.getElementById('sw-update-now').addEventListener('click', () => {
      notification.remove();
      applyUpdate();
    });
  }

  /**
   * Apply service worker update
   */
  function applyUpdate() {
    if (newWorker) {
      console.log('[ServiceWorkerManager] Applying service worker update');
      
      // Tell the waiting service worker to skip waiting
      newWorker.postMessage({ type: 'SKIP_WAITING' });
      
      // The controllerchange event will trigger a page reload
    } else if (registration && registration.waiting) {
      console.log('[ServiceWorkerManager] Applying waiting service worker');
      registration.waiting.postMessage({ type: 'SKIP_WAITING' });
    } else {
      console.warn('[ServiceWorkerManager] No service worker to update');
    }
  }

  /**
   * Skip waiting and activate immediately
   */
  function skipWaiting() {
    if (registration && registration.waiting) {
      registration.waiting.postMessage({ type: 'SKIP_WAITING' });
    }
  }

  /**
   * Get cache statistics from service worker
   */
  async function getCacheStats() {
    if (!registration) {
      return null;
    }

    return new Promise((resolve) => {
      const messageChannel = new MessageChannel();
      
      messageChannel.port1.onmessage = (event) => {
        resolve(event.data.data);
      };

      registration.active.postMessage(
        { type: 'GET_CACHE_STATS' },
        [messageChannel.port2]
      );
    });
  }

  /**
   * Clear specific cache
   */
  async function clearCache(cacheName) {
    if (!registration) {
      return false;
    }

    try {
      registration.active.postMessage({
        type: 'CLEAR_CACHE',
        data: { cacheName }
      });
      return true;
    } catch (error) {
      console.error('[ServiceWorkerManager] Failed to clear cache:', error);
      return false;
    }
  }

  /**
   * Check if update is available
   */
  function hasUpdate() {
    return isUpdateAvailable;
  }

  /**
   * Get registration
   */
  function getRegistration() {
    return registration;
  }

  /**
   * Subscribe to service worker events
   */
  function subscribe(event, callback) {
    subscribers.add({ event, callback });
    
    return () => {
      subscribers.delete({ event, callback });
    };
  }

  /**
   * Emit event to subscribers
   */
  function emit(event, data) {
    subscribers.forEach(({ event: subscribedEvent, callback }) => {
      if (subscribedEvent === event || subscribedEvent === '*') {
        try {
          callback(data);
        } catch (error) {
          console.error('[ServiceWorkerManager] Event callback error:', error);
        }
      }
    });
  }

  // Public API
  return {
    initialize,
    applyUpdate,
    skipWaiting,
    getCacheStats,
    clearCache,
    hasUpdate,
    getRegistration,
    subscribe
  };

})();

// Export for use in other modules
if (typeof window !== 'undefined') {
  window.ServiceWorkerManager = ServiceWorkerManager;
}
