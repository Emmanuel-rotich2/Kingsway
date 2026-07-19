# Storage System Migration Guide

## Overview

This document describes the storage system improvements made to Kingsway Academy's authentication and data storage architecture.

## Problem Fixed

### The Storage Desync Bug

**Symptom:** Users experienced 401 "Missing Authorization header" errors even after successful token refresh.

**Root Cause:** The `AuthContext.getItem()` function called `detectAuthStorage()` on every read, while `setItem()` used a cached `activeStorage`. This created a split-brain scenario:

1. If both `sessionStorage.token` and `localStorage.token` existed, reads would flip between storages
2. Writes went only to the cached `activeStorage` 
3. After token refresh, the new token was written to one storage but subsequent reads checked the other
4. Result: `getToken()` returned `null` even though a valid token existed

**Fix:** Made `getItem()` use the same cached `activeStorage` as `setItem()`, ensuring read/write consistency.

## New Storage Architecture

### StorageManager Utility

A new smart storage manager (`js/utils/storage_manager.js`) provides:

- **Automatic fallback** when storage is unavailable or quota exceeded
- **Purpose-specific storage** for different data types
- **Cache management** with TTL support
- **Storage statistics** for monitoring

### Storage Strategy

| Data Type | Primary Storage | Fallback | Purpose |
|-----------|----------------|----------|---------|
| **Auth Tokens** | HttpOnly Cookies (server) | localStorage | Security-first, XSS protection |
| **User Preferences** | localStorage | sessionStorage → memory | Theme, language, settings |
| **Session State** | sessionStorage | memory | Tab-specific UI state |
| **Cache Data** | IndexedDB | localStorage → memory | API responses, large datasets |
| **Temporary Data** | memory | - | Form drafts, calculations |

### Security Considerations

⚠️ **Important:** Auth tokens are NOT stored in multiple locations. The current implementation uses localStorage with a migration path to HttpOnly cookies for better security.

The new StorageManager is **NOT** used for auth tokens - it's specifically for:
- User preferences (theme, sidebar state)
- Session data (form state, current page)
- Cache (API responses, static data)

## Usage Examples

### User Preferences

```javascript
// Store user preference (persists across sessions)
StorageManager.setPreference('user_theme', 'dark');
StorageManager.setPreference('items_per_page', 25);

// Retrieve preference (with fallback)
const theme = StorageManager.getPreference('user_theme', 'light');
const itemsPerPage = StorageManager.getPreference('items_per_page', 10);
```

### Session State

```javascript
// Store session state (cleared on tab close)
StorageManager.setSessionState('current_page', 'students');
StorageManager.setSessionState('form_draft', formData);

// Retrieve session state
const currentPage = StorageManager.getSessionState('current_page');
const formDraft = StorageManager.getSessionState('form_draft');

// Clear all session state
StorageManager.clearSessionState();
```

### Cache Management

```javascript
// Cache API response (1 hour TTL)
await StorageManager.setCache('students_list', studentsData, 3600000);

// Retrieve cached data
const cachedStudents = await StorageManager.getCache('students_list');
if (cachedStudents) {
  // Use cached data
} else {
  // Fetch from API
}

// Clear expired cache entries
await StorageManager.clearExpiredCache();
```

### Storage Statistics

```javascript
// Monitor storage usage
const stats = await StorageManager.getStorageStats();
console.log('Storage usage:', stats);
// Output:
// {
//   localStorage: { used: 12345, available: 5242880, keys: 15 },
//   sessionStorage: { used: 5678, available: 5242880, keys: 8 },
//   indexedDB: { used: 0, available: 0, databases: 0 },
//   memory: { used: 0, entries: 3 }
// }
```

## Migration Guide

### For Existing Code

**Before (direct localStorage usage):**
```javascript
localStorage.setItem('sidebar_state', 'collapsed');
const sidebarState = localStorage.getItem('sidebar_state');
```

**After (using StorageManager):**
```javascript
StorageManager.setPreference('sidebar_state', 'collapsed');
const sidebarState = StorageManager.getPreference('sidebar_state');
```

### For New Features

**Caching API Responses:**
```javascript
async function fetchStudents() {
  // Try cache first
  const cached = await StorageManager.getCache('students_list');
  if (cached) return cached;
  
  // Fetch from API
  const response = await callAPI('students', 'GET');
  
  // Cache for 5 minutes
  await StorageManager.setCache('students_list', response, 300000);
  
  return response;
}
```

**Form State Persistence:**
```javascript
// Save form state on input
document.getElementById('student_form').addEventListener('input', (e) => {
  const formData = getFormData();
  StorageManager.setSessionState('student_form_draft', formData);
});

// Restore form state on load
window.addEventListener('load', async () => {
  const draft = StorageManager.getSessionState('student_form_draft');
  if (draft) {
    restoreFormData(draft);
    if (confirm('Restore previous draft?')) {
      // Keep draft
    } else {
      StorageManager.clearSessionState();
    }
  }
});
```

## Testing

### Test Storage Availability

```javascript
// Check if specific storage is available
if (StorageManager.isAvailable('indexedDB')) {
  console.log('IndexedDB is available');
}

if (StorageManager.isAvailable('cookies')) {
  console.log('Cookies are enabled');
}
```

### Test Fallback Behavior

```javascript
// Simulate localStorage quota exceeded
function testFallback() {
  // Fill localStorage to test fallback
  for (let i = 0; i < 10000; i++) {
    try {
      localStorage.setItem(`test_${i}`, 'x'.repeat(1000));
    } catch (e) {
      console.log('localStorage quota exceeded, testing fallback');
      break;
    }
  }
  
  // Should fallback to sessionStorage
  StorageManager.setPreference('test_key', 'test_value');
  const value = StorageManager.getPreference('test_key');
  console.log('Fallback value:', value); // Should still work
}
```

## Browser Compatibility

- **localStorage/sessionStorage:** IE8+, all modern browsers
- **IndexedDB:** IE10+, all modern browsers
- **Cookies:** All browsers

## Performance Considerations

- **IndexedDB** is async and best for large datasets (>1MB)
- **localStorage** is synchronous and fast for small data
- **sessionStorage** is synchronous and cleared on tab close
- **memory** is fastest but doesn't persist

## Security Best Practices

1. **Never store sensitive data** in localStorage/sessionStorage (XSS vulnerable)
2. **Auth tokens** should be HttpOnly cookies (server-side)
3. **User preferences** are safe in localStorage (non-sensitive)
4. **Cache data** should be validated server-side
5. **Always sanitize** data before storage to prevent XSS

## Future Improvements

### Phase 1: Current (Complete)
- ✅ Fix storage desync bug
- ✅ Add StorageManager utility
- ✅ Implement fallback mechanisms
- ✅ Add cache management

### Phase 2: Next Steps
- 🔄 Migrate auth tokens to HttpOnly cookies
- 🔄 Add server-side cache invalidation
- 🔄 Implement cache warming for critical data
- 🔄 Add storage quota monitoring

### Phase 3: Advanced Features
- 📋 Background sync for offline support
- 📋 Cache compression for large datasets
- 📋 Predictive preloading based on usage patterns
- 📋 Cross-tab state synchronization

## Troubleshooting

### Issue: Preferences not persisting

**Cause:** localStorage quota exceeded or disabled

**Solution:** StorageManager automatically falls back to sessionStorage → memory. Check console for warnings.

### Issue: Cache not working

**Cause:** IndexedDB not available or quota exceeded

**Solution:** StorageManager falls back to localStorage → memory. Check `StorageManager.getStorageStats()`.

### Issue: Session state lost on refresh

**Cause:** sessionStorage cleared by browser settings

**Solution:** Use `setPreference()` instead of `setSessionState()` for data that needs to persist.

## Monitoring

Add this to your monitoring/logging:

```javascript
// Log storage usage periodically
setInterval(async () => {
  const stats = await StorageManager.getStorageStats();
  if (stats.localStorage.used / stats.localStorage.available > 0.9) {
    console.warn('localStorage nearly full:', stats);
    // Trigger cleanup or notify user
  }
}, 60000); // Check every minute
```

## References

- [MDN: Web Storage API](https://developer.mozilla.org/en-US/docs/Web/API/Web_Storage_API)
- [MDN: IndexedDB](https://developer.mozilla.org/en-US/docs/Web/API/IndexedDB_API)
- [OWASP: HTML5 Security](https://owasp.org/www-community/vulnerabilities/HTML5_Security_Cheat_Sheet)
