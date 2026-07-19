# Service Worker Implementation Guide

## Overview

This guide documents the Service Worker implementation for Kingsway Academy School Management System, including architecture, caching strategies, and usage instructions.

## Architecture

### Service Worker Components

**File:** `service-worker.js`  
**Version:** v1  
**Cache Names:**
- `kingsway-static-v1` - Application shell and static assets
- `kingsway-api-v1` - Safe API responses

### Manager Component

**File:** `js/core/service_worker_manager.js`  
**Purpose:** Manages service worker registration, updates, and communication  
**Features:**
- Safe service worker registration
- Update detection and notification
- Cache statistics
- Manual cache management
- Message passing to/from service worker

### Offline Page

**File:** `offline.html`  
**Purpose:** User-friendly offline fallback page  
**Features:**
- Connection status indicator
- Available/unavailable features list
- Pending changes count
- Retry connection button
- Auto-redirect on reconnection

### Web Manifest

**File:** `manifest.webmanifest`  
**Purpose:** Progressive Web App configuration  
**Features:**
- App name and description
- Icons for different sizes
- Start URL and scope
- Display mode (standalone)
- Theme colors
- App shortcuts

## Caching Strategies

### Cache First (Static Assets)

**Purpose:** Immutable assets that rarely change  
**Assets:** CSS, JavaScript, icons, fonts, logos  
**Behavior:** Serve from cache immediately, fetch in background  
**Invalidation:** Version update (cache name change)

**Examples:**
- `/css/school-theme.css`
- `/js/api.js`
- `/images/favicon/favicon-96x96.png`
- External libraries (jQuery, Chart.js, Bootstrap Icons)

### Stale While Revalidate (Reference Data)

**Purpose:** Safe GET API responses that can tolerate slight staleness  
**Endpoints:** Classes, streams, subjects, terms, school profile  
**Behavior:** Serve from cache immediately, fetch in background, update cache  
**Invalidation:** Time-based (24 hours) or manual

**Examples:**
- `/api/classes` (24 hours TTL)
- `/api/streams` (24 hours TTL)
- `/api/subjects` (24 hours TTL)
- `/api/school/profile` (1 hour TTL)

### Network First (Navigation)

**Purpose:** HTML pages and dynamic content  
**Behavior:** Try network first, fall back to cache, then offline page  
**Invalidation:** Always fetch from network when online

**Examples:**
- `/home.php`
- Navigation requests (mode: navigate)

### Network Only (Sensitive Operations)

**Purpose:** Authentication, payments, and sensitive operations  
**Behavior:** Always fetch from network, never cache  
**Invalidation:** N/A (never cached)

**Examples:**
- `/api/auth/*` (authentication)
- `/api/payments/*` (payments)
- `/api/payroll/*` (payroll)
- POST/PUT/PATCH/DELETE requests

## Safe Caching Rules

### Never Cache

1. **Authentication endpoints** - `/api/auth/*`
2. **Payment endpoints** - `/api/payments/*`
3. **Payroll endpoints** - `/api/payroll/*`
4. **Student lists** - Use IndexedDB instead
5. **Admission queues** - Use IndexedDB instead
6. **Mutations** - POST, PUT, PATCH, DELETE requests

### Safe to Cache (GET only)

1. **Reference metadata** - Classes, streams, subjects, terms
2. **School configuration** - School profile, settings
3. **Static assets** - CSS, JS, icons, fonts
4. **External libraries** - jQuery, Chart.js, Bootstrap

## Service Worker Lifecycle

### Install Event

1. **Trigger:** First service worker registration
2. **Actions:**
   - Open `kingsway-static-v1` cache
   - Cache all static assets
   - Skip waiting (activate immediately)
3. **Fallback:** If caching fails, continue without cache

### Activate Event

1. **Trigger:** Service worker becomes active
2. **Actions:**
   - Delete old version caches (different version numbers)
   - Claim all clients (take control immediately)
3. **Cleanup:** Remove caches with different version names

### Fetch Event

1. **Trigger:** Every network request
2. **Strategy Selection:**
   - Static assets → Cache First
   - Safe API → Stale While Revalidate
   - Navigation → Network First
   - Everything else → Network Only
3. **Fallback:** Offline page for navigation requests

## Service Worker Manager API

### Initialization

```javascript
// Initialize service worker (called automatically in home.php)
ServiceWorkerManager.initialize().then(success => {
  if (success) {
    console.log('Service Worker initialized successfully');
  }
});
```

### Update Management

```javascript
// Check if update is available
if (ServiceWorkerManager.hasUpdate()) {
  // Apply update immediately
  ServiceWorkerManager.applyUpdate();
}

// Skip waiting and activate new service worker
ServiceWorkerManager.skipWaiting();
```

### Cache Management

```javascript
// Get cache statistics
const stats = await ServiceWorkerManager.getCacheStats();
console.log('Cache stats:', stats);
// Output:
// {
//   "kingsway-static-v1": { entries: 25, totalSize: 1024000 },
//   "kingsway-api-v1": { entries: 10, totalSize: 51200 }
// }

// Clear specific cache
await ServiceWorkerManager.clearCache('kingsway-api-v1');
```

### Event Subscription

```javascript
// Subscribe to service worker events
const unsubscribe = ServiceWorkerManager.subscribe('UPDATE_AVAILABLE', (data) => {
  console.log('Update available:', data);
  // Show custom update UI
});

// Unsubscribe later
unsubscribe();
```

## Available Events

### UPDATE_AVAILABLE
**Triggered:** New service worker version detected  
**Data:** `{ version: string }`  
**Action:** Show update notification to user

### CONTROLLER_CHANGED
**Triggered:** New service worker becomes active  
**Data:** `{}`  
**Action:** Page reload (automatic)

### CACHE_STATS
**Triggered:** Cache statistics requested  
**Data:** Cache statistics object  
**Action:** Display cache information to user

### SERVICE_WORKER_MESSAGE
**Triggered:** Generic message from service worker  
**Data:** `{ type: string, data: any }`  
**Action:** Handle custom service worker messages

## Background Sync

### Supported Sync Tags

**sync-outbox** - Sync offline operation queue  
**sync-drafts** - Sync offline drafts

### Registration (Future Implementation)

```javascript
// Register background sync for offline queue
registration.sync.register('sync-outbox');

// Register background sync for drafts
registration.sync.register('sync-drafts');
```

### Sync Event Handling

The service worker listens for sync events and will process:
- Offline operation queue (when implemented)
- Offline drafts (when implemented)

## Push Notifications

### Push Event

**Triggered:** Server sends push message  
**Action:** Show notification to user

**Notification Structure:**
```javascript
{
  title: 'Kingsway Academy',
  body: 'Notification message',
  icon: '/images/favicon/favicon-96x96.png',
  badge: '/images/favicon/favicon-96x96.png',
  vibrate: [200, 100, 200],
  data: {
    dateOfArrival: Date.now(),
    primaryKey: 1
  },
  actions: [
    { action: 'explore', title: 'View' },
    { action: 'close', title: 'Close' }
  ]
}
```

### Notification Click Handling

**explore action:** Opens app in new tab  
**close action:** Dismisses notification

## Cache Invalidation

### Automatic Invalidation

- **Version update:** Old caches deleted on activate
- **Time-based:** API responses expire based on TTL
- **Manual:** User can clear caches via ServiceWorkerManager

### Manual Invalidation

```javascript
// Clear API cache
await ServiceWorkerManager.clearCache('kingsway-api-v1');

// Clear static cache (requires service worker update)
await ServiceWorkerManager.clearCache('kingsway-static-v1');
```

## Safe Update Mechanism

### Update Detection

1. Service worker detects new version on navigation
2. Triggers `updatefound` event
3. Shows update notification to user

### User Choice

**Update Now:** 
- Skips waiting service worker
- Activates new version
- Reloads page

**Later:**
- Dismisses notification
- Keeps using current version
- Will prompt again on next navigation

### Form Protection

The update mechanism checks for:
- Active forms with focused inputs
- Open modals
- Unsaved changes

If detected, delays notification to prevent data loss.

## Troubleshooting

### Service Worker Not Registering

**Check:**
- Browser supports service workers
- Service worker file is accessible
- No console errors during registration
- Correct scope (should be '/')

### Cache Not Working

**Check:**
- Cache is opened and has entries
- URLs match cache patterns
- Network requests are being intercepted
- Console for cache hit/miss logs

### Update Not Detected

**Check:**
- Service worker file actually changed
- Version number in CACHE_VERSION updated
- Browser is not caching old service worker
- DevTools "Update on reload" is disabled

### Offline Page Not Showing

**Check:**
- offline.html is cached
- Navigation requests use Network First strategy
- Offline page URL is correct relative path
- Service worker is active and intercepting requests

## DevTools Debugging

### Application Tab

1. Open Chrome DevTools
2. Go to Application tab
3. Check:
   - Service Workers (status, state)
   - Cache Storage (cache names, entries)
   - Manifest (PWA status)

### Service Worker Debugging

```javascript
// In browser console
// Get registration
const registration = await navigator.serviceWorker.getRegistration();
console.log('Registration:', registration);

// Get active worker
console.log('Active worker:', registration.active);

// Get waiting worker
console.log('Waiting worker:', registration.waiting);

// Send message to service worker
registration.active.postMessage({ type: 'GET_CACHE_STATS' });
```

### Cache Inspection

1. Application → Cache Storage
2. Select cache name (kingsway-static-v1, kingsway-api-v1)
3. View cached entries
4. Inspect response headers and body

## Testing Offline Functionality

### Chrome DevTools

1. Open DevTools
2. Go to Network tab
3. Check "Offline" checkbox
4. Reload page
5. Verify offline page shows

### Service Worker Testing

```javascript
// Unregister service worker
navigator.serviceWorker.getRegistrations().then(registrations => {
  registrations.forEach(registration => registration.unregister());
});

// Bypass service worker for testing
// DevTools → Application → Service Workers → Bypass for network
```

## Performance Monitoring

### Cache Hit Rate

Monitor cache effectiveness:
```javascript
const stats = await ServiceWorkerManager.getCacheStats();
const hitRate = calculateHitRate(stats);
console.log('Cache hit rate:', hitRate);
```

### Cache Size

Monitor storage usage:
```javascript
const estimate = await navigator.storage.estimate();
console.log('Storage usage:', estimate.usage);
console.log('Storage quota:', estimate.quota);
```

## Security Considerations

### Cache Poisoning Prevention

- Only cache same-origin requests
- Whitelist external CDNs
- Never cache authentication endpoints
- Never cache sensitive operations

### HTTPS Requirement

Service workers require HTTPS:
- Required for production
- Works on localhost for development
- No exceptions for HTTP

### Cache Exposure

- Cached data is same-origin only
- No cross-origin cache access
- Service worker respects CORS

## Browser Compatibility

### Supported Browsers

- Chrome 40+ (full support)
- Firefox 44+ (full support)
- Safari 11.1+ (full support)
- Edge 79+ (full support)

### Progressive Enhancement

- Works without service worker (online only)
- Graceful degradation for unsupported browsers
- Feature detection before registration

## Future Enhancements

### Planned Features

1. **Precaching** - Automatic asset caching
2. **Runtime caching** - Dynamic cache strategies
3. **Cache warming** - Preload likely-needed resources
4. **Analytics** - Cache performance metrics
5. **A/B testing** - Test different caching strategies

### Experimental Features

1. **Periodic Background Sync** - Auto-refresh reference data
2. **Background Fetch** - Download large reports offline
3. **Storage Buckets** - Better quota management

## Best Practices

### Do

- Version caches (use CACHE_VERSION)
- Clean up old caches on activate
- Provide offline fallback
- Handle service worker errors gracefully
- Test update mechanism thoroughly

### Don't

- Cache sensitive data
- Cache authentication endpoints
- Cache POST/PUT/DELETE requests
- Interrupt user workflows for updates
- Assume service worker is always available

## Conclusion

The service worker implementation provides:
- **Offline support** - Application works without network
- **Performance** - Faster page loads via caching
- **Resilience** - Graceful degradation on network issues
- **User experience** - Smooth updates and offline indication

The implementation follows web best practices while maintaining security and data integrity for the school management system.

**Next Phase:** Implement IndexedDB and offline queue for full offline capabilities.
