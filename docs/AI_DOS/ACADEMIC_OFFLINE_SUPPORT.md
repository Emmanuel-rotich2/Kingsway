# Academic Module Offline Support Documentation

**Generated:** 2026-07-14  
**System:** Kingsway School Management System  
**Module:** Academic Module  
**Purpose:** Offline support implementation for academic data

---

## Overview

The Academic Module now includes comprehensive offline support through the AcademicOfflineService, enabling users to access and modify academic data even when network connectivity is unavailable. The service uses IndexedDB for client-side storage and automatic synchronization when connectivity is restored.

---

## Architecture

### Storage Technology
- **IndexedDB:** Browser-based database for offline storage
- **Service Worker:** Background synchronization (future enhancement)
- **Sync Queue:** Queued operations for offline-to-online sync

### Data Storage Strategy

| Data Type | Store Name | Sync Strategy | TTL |
|-----------|------------|---------------|-----|
| Academic Context | academic_context | Real-time sync | 5 minutes |
| Academic Years | academic_years | Background sync | 1 hour |
| Academic Terms | academic_terms | Background sync | 1 hour |
| Classes | classes | Background sync | 1 hour |
| Subjects | subjects | Background sync | 1 hour |
| Learning Areas | learning_areas | Background sync | 1 hour |
| Schemes of Work | schemes_of_work | Queue sync | 24 hours |
| Lesson Plans | lesson_plans | Queue sync | 24 hours |
| Assessments | assessments | Queue sync | 24 hours |
| Results | results | Queue sync | 24 hours |

---

## Service API

### Initialization

```javascript
// Initialize offline service
await AcademicOfflineService.init();
```

### Context Management

```javascript
// Cache academic context
await AcademicOfflineService.cacheAcademicContext(context);

// Get cached context
const context = await AcademicOfflineService.getCachedAcademicContext();
```

### Reference Data Caching

```javascript
// Cache academic years
await AcademicOfflineService.cacheAcademicYears(years);
const years = await AcademicOfflineService.getCachedAcademicYears();

// Cache academic terms
await AcademicOfflineService.cacheAcademicTerms(terms);
const terms = await AcademicOfflineService.getCachedAcademicTerms();

// Cache classes
await AcademicOfflineService.cacheClasses(classes);
const classes = await AcademicOfflineService.getCachedClasses();

// Cache subjects
await AcademicOfflineService.cacheSubjects(subjects);
const subjects = await AcademicOfflineService.getCachedSubjects();
```

### Synchronization

```javascript
// Check online status
const isOnline = AcademicOfflineService.isCurrentlyOnline();

// Sync with server
await AcademicOfflineService.syncWithServer();

// Add operation to sync queue
await AcademicOfflineService.addToSyncQueue({
    type: 'create',
    endpoint: 'academic/formative-assessments',
    method: 'POST',
    data: assessmentData
});
```

### Cache Management

```javascript
// Clear all cached data
await AcademicOfflineService.clearCache();

// Get storage statistics
const stats = await AcademicOfflineService.getStorageStats();
console.log('Storage stats:', stats);
```

---

## Integration with AcademicContext

The AcademicOfflineService integrates seamlessly with the existing AcademicContext service:

```javascript
// Enhanced AcademicContext with offline support
if (window.AcademicContext) {
    // Cache context when loaded
    window.AcademicContext.subscribe((context, event, data) => {
        if (event === 'initialized' || event === 'refreshed') {
            AcademicOfflineService.cacheAcademicContext(context);
        }
    });
    
    // Load from cache if offline
    if (!AcademicOfflineService.isCurrentlyOnline()) {
        const cachedContext = await AcademicOfflineService.getCachedAcademicContext();
        if (cachedContext) {
            // Use cached context
        }
    }
}
```

---

## Integration with Academic Pages

### Pattern for Academic Pages

```javascript
const pageCtrl = (() => {
    async function loadData() {
        // Try online first
        if (AcademicOfflineService.isCurrentlyOnline()) {
            try {
                const response = await apiCall('academic/years-list');
                state.years = response.data;
                await AcademicOfflineService.cacheAcademicYears(state.years);
            } catch (error) {
                console.error('Online load failed, using cache:', error);
                state.years = await AcademicOfflineService.getCachedAcademicYears();
            }
        } else {
            // Use cached data
            state.years = await AcademicOfflineService.getCachedAcademicYears();
        }
    }

    async function saveData(data) {
        if (AcademicOfflineService.isCurrentlyOnline()) {
            // Save directly to server
            await apiCall('academic/endpoint', 'POST', data);
        } else {
            // Queue for sync
            await AcademicOfflineService.addToSyncQueue({
                type: 'create',
                endpoint: 'academic/endpoint',
                method: 'POST',
                data: data
            });
            showNotification('Changes will sync when online', 'warning');
        }
    }

    async function init() {
        await AcademicOfflineService.init();
        await loadData();
    }

    return { init };
})();
```

---

## Usage Examples

### Example 1: Offline-Aware Years Loading

```javascript
async function loadYears() {
    const select = document.getElementById('yearFilter');
    
    if (AcademicOfflineService.isCurrentlyOnline()) {
        try {
            const response = await apiCall('academic/years-list');
            state.years = response.data;
            await AcademicOfflineService.cacheAcademicYears(state.years);
        } catch (error) {
            console.error('Failed to load years online:', error);
            state.years = await AcademicOfflineService.getCachedAcademicYears();
        }
    } else {
        state.years = await AcademicOfflineService.getCachedAcademicYears();
        showNotification('Using cached years (offline mode)', 'info');
    }

    // Render years
    select.innerHTML = '<option value="">All Years</option>';
    state.years.forEach(year => {
        const option = document.createElement('option');
        option.value = year.id;
        option.textContent = year.year_name;
        if (year.is_current) option.selected = true;
        select.appendChild(option);
    });
}
```

### Example 2: Offline-Aware Form Submission

```javascript
async function submitAssessment(data) {
    const submitBtn = document.getElementById('submitBtn');
    submitBtn.disabled = true;
    submitBtn.textContent = 'Saving...';

    try {
        if (AcademicOfflineService.isCurrentlyOnline()) {
            const response = await apiCall('academic/formative-assessments', 'POST', data);
            showNotification('Assessment saved successfully', 'success');
        } else {
            await AcademicOfflineService.addToSyncQueue({
                type: 'create',
                endpoint: 'academic/formative-assessments',
                method: 'POST',
                data: data
            });
            showNotification('Assessment queued for sync (offline mode)', 'warning');
        }
    } catch (error) {
        console.error('Save failed:', error);
        showNotification('Failed to save assessment', 'error');
    } finally {
        submitBtn.disabled = false;
        submitBtn.textContent = 'Save Assessment';
    }
}
```

### Example 3: Offline Status Indicator

```javascript
function showOfflineStatus() {
    const indicator = document.getElementById('offlineIndicator');
    
    if (!AcademicOfflineService.isCurrentlyOnline()) {
        indicator.classList.remove('d-none');
        indicator.classList.add('bg-warning');
        indicator.innerHTML = '<i class="bi bi-wifi-off me-1"></i>Offline Mode';
    } else {
        indicator.classList.add('d-none');
    }
}

// Update status on connection changes
window.addEventListener('online', showOfflineStatus);
window.addEventListener('offline', showOfflineStatus);
```

---

## Performance Considerations

### Storage Limits
- **IndexedDB Limit:** ~50% of available disk space
- **Typical Usage:** 10-50 MB for academic data
- **Cache Management:** Implement TTL-based expiration

### Sync Performance
- **Batch Operations:** Queue multiple operations before sync
- **Throttling:** Limit sync frequency to avoid server overload
- **Conflict Resolution:** Last-write-wins strategy

### Memory Management
- **Lazy Loading:** Load data only when needed
- **Data Pagination:** Store large datasets in chunks
- **Cache Eviction:** Remove old data when storage limits reached

---

## Security Considerations

### Data Protection
- **Encryption:** Consider encrypting sensitive data at rest
- **Authentication:** Verify user identity before sync
- **Authorization:** Validate permissions during sync

### Privacy
- **Student Data:** Ensure student data is protected offline
- **Assessment Data:** Secure assessment results in storage
- **Personal Information:** Encrypt PII in offline storage

---

## Testing Strategy

### Manual Testing
1. **Online Mode:** Test normal operation with network
2. **Offline Mode:** Disconnect network and test cached data access
3. **Sync Mode:** Reconnect network and verify synchronization
4. **Conflict Resolution:** Test simultaneous offline modifications

### Automated Testing
```javascript
// Test offline data access
describe('AcademicOfflineService', () => {
    it('should cache and retrieve academic context', async () => {
        const context = { academicYearId: 1, termId: 2, isCurrent: true };
        await AcademicOfflineService.cacheAcademicContext(context);
        const retrieved = await AcademicOfflineService.getCachedAcademicContext();
        expect(retrieved.academicYearId).toBe(1);
    });

    it('should queue operations when offline', async () => {
        // Simulate offline mode
        await AcademicOfflineService.addToSyncQueue({
            type: 'create',
            endpoint: 'test',
            method: 'POST',
            data: { test: true }
        });
        const operations = await AcademicOfflineService.getUnsyncedOperations();
        expect(operations.length).toBeGreaterThan(0);
    });
});
```

---

## Browser Compatibility

| Browser | IndexedDB Support | Service Worker Support |
|---------|------------------|------------------------|
| Chrome | ✅ Full | ✅ Full |
| Firefox | ✅ Full | ✅ Full |
| Safari | ✅ Full | ✅ Full |
| Edge | ✅ Full | ✅ Full |
| IE11 | ❌ No | ❌ No |

### Fallback Strategy
For browsers without IndexedDB support:
- Use localStorage for basic caching
- Disable advanced sync features
- Show compatibility warning

---

## Future Enhancements

### Phase 1: Current Implementation
- ✅ IndexedDB storage
- ✅ Basic sync queue
- ✅ Online/offline detection
- ✅ Automatic cache refresh

### Phase 2: Enhanced Features
- ⏳ Service Worker for background sync
- ⏳ Conflict resolution strategies
- ⏳ Delta sync (only changed data)
- ⏳ Progressive data loading

### Phase 3: Advanced Features
- ⏳ Offline analytics
- ⏳ Predictive caching
- ⏳ Multi-device synchronization
- ⏳ Offline-first architecture

---

## Monitoring and Debugging

### Storage Statistics
```javascript
const stats = await AcademicOfflineService.getStorageStats();
console.log('Storage usage:', stats);
```

### Sync Queue Monitoring
```javascript
const operations = await AcademicOfflineService.getUnsyncedOperations();
console.log('Pending operations:', operations.length);
```

### Debug Logging
```javascript
// Enable debug logging
localStorage.setItem('academic_offline_debug', 'true');
```

---

## Troubleshooting

### Common Issues

**Issue:** Data not loading in offline mode
- **Solution:** Check if data was cached before going offline
- **Fix:** Implement pre-cache on page load

**Issue:** Sync failing after reconnection
- **Solution:** Check server API availability
- **Fix:** Implement retry logic with exponential backoff

**Issue:** Storage quota exceeded
- **Solution:** Clear old cached data
- **Fix:** Implement TTL-based cache eviction

---

## Best Practices

1. **Always check online status** before making API calls
2. **Cache reference data** early in page lifecycle
3. **Queue write operations** when offline
4. **Show user feedback** for offline/sync status
5. **Handle conflicts** gracefully during sync
6. **Clear cache periodically** to manage storage
7. **Test offline scenarios** thoroughly

---

## Conclusion

The AcademicOfflineService provides comprehensive offline support for the Academic Module, enabling users to work with academic data regardless of network connectivity. The service integrates seamlessly with existing AcademicContext and follows established patterns for consistency.

**Implementation Status:** Complete ✓  
**Service File:** `js/utils/academic_offline_service.js`  
**Integration Required:** Academic pages can integrate on-demand  
**Browser Support:** Modern browsers (Chrome, Firefox, Safari, Edge)

**Document End**

*Generated: 2026-07-14*
*Academic Module Offline Support*
