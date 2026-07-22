/**
 * Push Notification Manager
 * 
 * Manages Web Push API for real-time notifications.
 * Handles subscription, message handling, and permission requests.
 * 
 * Features:
- Request notification permissions
- Subscribe to push notifications
- Handle incoming push messages
- Display notifications to user
- Manage subscription lifecycle
- Support for custom notification actions
 */

const PushNotificationManager = {
  initialized: false,
  state: {
    subscription: null,
    permission: 'default',
    swRegistration: null,
    publicKey: null
  },

  /**
   * Initialize push notification manager
   */
  async initialize() {
    if (this.initialized) return;
    this.initialized = true;

    console.log('[PushNotificationManager] Initializing...');

    // Check if push API is supported
    if (!('serviceWorker' in navigator) || !('PushManager' in window)) {
      console.warn('[PushNotificationManager] Push API not supported');
      return;
    }

    // Check current permission
    this.state.permission = Notification.permission;
    console.log('[PushNotificationManager] Current permission:', this.state.permission);

    // Register service worker and get subscription
    try {
      this.state.swRegistration = await navigator.serviceWorker.ready;
      this.state.subscription = await this.state.swRegistration.pushManager.getSubscription();
      
      if (this.state.subscription) {
        console.log('[PushNotificationManager] Existing subscription found');
      } else {
        console.log('[PushNotificationManager] No existing subscription');
      }
    } catch (error) {
      console.error('[PushNotificationManager] Failed to get subscription:', error);
    }

    console.log('[PushNotificationManager] Initialized');
  },

  /**
   * Request notification permission
   */
  async requestPermission() {
    if (!('Notification' in window)) {
      console.warn('[PushNotificationManager] Notifications not supported');
      return false;
    }

    try {
      const permission = await Notification.requestPermission();
      this.state.permission = permission;
      
      console.log('[PushNotificationManager] Permission:', permission);
      
      if (permission === 'granted') {
        // Subscribe after permission granted
        await this.subscribe();
        return true;
      } else {
        console.warn('[PushNotificationManager] Permission denied');
        return false;
      }
    } catch (error) {
      console.error('[PushNotificationManager] Permission request failed:', error);
      return false;
    }
  },

  /**
   * Subscribe to push notifications
   */
  async subscribe() {
    if (!this.state.swRegistration) {
      console.warn('[PushNotificationManager] Service worker not registered');
      return false;
    }

    try {
      // Convert base64 public key to Uint8Array
      const applicationServerKey = this.urlBase64ToUint8Array(this.getPublicKey());
      
      const subscription = await this.state.swRegistration.pushManager.subscribe({
        userVisibleOnly: true,
        applicationServerKey: applicationServerKey
      });

      this.state.subscription = subscription;
      
      // Send subscription to server
      await this.sendSubscriptionToServer(subscription);
      
      console.log('[PushNotificationManager] Subscribed successfully');
      return true;
    } catch (error) {
      console.error('[PushNotificationManager] Subscription failed:', error);
      return false;
    }
  },

  /**
   * Unsubscribe from push notifications
   */
  async unsubscribe() {
    if (!this.state.subscription) {
      console.log('[PushNotificationManager] No subscription to unsubscribe');
      return true;
    }

    try {
      await this.state.subscription.unsubscribe();
      this.state.subscription = null;
      
      // Remove subscription from server
      await this.removeSubscriptionFromServer();
      
      console.log('[PushNotificationManager] Unsubscribed successfully');
      return true;
    } catch (error) {
      console.error('[PushNotificationManager] Unsubscribe failed:', error);
      return false;
    }
  },

  /**
   * Send subscription to server
   */
  async sendSubscriptionToServer(subscription) {
    if (typeof window.API === 'undefined' || typeof window.API.apiCall !== 'function') {
      console.warn('[PushNotificationManager] API not available');
      return;
    }

    try {
      const subscriptionData = JSON.parse(JSON.stringify(subscription));
      
      await window.API.apiCall('/push/subscribe', 'POST', {
        subscription: subscriptionData,
        device_fingerprint: SessionManager?.getDeviceFingerprint()
      });
      
      console.log('[PushNotificationManager] Subscription sent to server');
    } catch (error) {
      console.error('[PushNotificationManager] Failed to send subscription:', error);
    }
  },

  /**
   * Remove subscription from server
   */
  async removeSubscriptionFromServer() {
    if (typeof window.API === 'undefined' || typeof window.API.apiCall !== 'function') {
      console.warn('[PushNotificationManager] API not available');
      return;
    }

    try {
      await window.API.apiCall('/push/unsubscribe', 'POST', {
        device_fingerprint: SessionManager?.getDeviceFingerprint()
      });
      
      console.log('[PushNotificationManager] Subscription removed from server');
    } catch (error) {
      console.error('[PushNotificationManager] Failed to remove subscription:', error);
    }
  },

  /**
   * Get public key for push notifications
   * In production, this should come from server configuration
   */
  getPublicKey() {
    // This should be configured from server
    // For now, return a placeholder
    return window.PUSH_PUBLIC_KEY || '';
  },

  /**
   * Convert base64 to Uint8Array
   */
  urlBase64ToUint8Array(base64String) {
    const padding = '='.repeat((4 - base64String.length % 4) % 4);
    const base64 = (base64String + padding).replace(/-/g, '+').replace(/_/g, '/');
    const rawData = window.atob(base64);
    const outputArray = new Uint8Array(rawData.length);
    
    for (let i = 0; i < rawData.length; ++i) {
      outputArray[i] = rawData.charCodeAt(i);
    }
    
    return outputArray;
  },

  /**
   * Show local notification (for testing)
   */
  showLocalNotification(title, options = {}) {
    if (this.state.permission !== 'granted') {
      console.warn('[PushNotificationManager] Permission not granted');
      return;
    }

    const notification = new Notification(title, {
      icon: '/favicon.ico',
      badge: '/favicon.ico',
      ...options
    });

    notification.onclick = () => {
      window.focus();
      notification.close();
    };
  },

  /**
   * Get current state
   */
  getState() {
    return {
      permission: this.state.permission,
      subscribed: !!this.state.subscription,
      publicKey: this.getPublicKey()
    };
  },

  /**
   * Check if push notifications are supported
   */
  isSupported() {
    return 'serviceWorker' in navigator && 'PushManager' in window;
  },

  /**
   * Check if subscribed
   */
  isSubscribed() {
    return !!this.state.subscription;
  },

  /**
   * Get permission status
   */
  getPermission() {
    return this.state.permission;
  }
};

if (typeof window !== 'undefined') window.PushNotificationManager = PushNotificationManager;
