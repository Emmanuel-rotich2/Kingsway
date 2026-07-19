# Admissions Module Migration - Phase 1 Applied

## Date
2025-01-XX

## Changes Applied

### File: `js/pages/admissions_workspace.js`

### 1. Updated `loadQueueData()` function

**Changes:**
- Added DataStore integration for admission queue data
- Uses stale-while-revalidate strategy (1 minute TTL)
- Falls back to direct API call if DataStore fails
- Caches successful API responses in DataStore

**Lines Modified:** 148-198

**Code Pattern:**
```javascript
loadQueueData: async function() {
    try {
        let queueData;
        
        // Try DataStore first for caching
        if (typeof DataStore !== 'undefined') {
            try {
                queueData = await DataStore.get('admissions', {
                    strategy: 'stale-while-revalidate',
                    ttl: 60000, // 1 minute
                    storeName: 'admission_queue_cache',
                    endpoint: '/admission/queues',
                    forceRefresh: false
                });
            } catch (dataStoreError) {
                console.warn("DataStore failed, falling back to API:", dataStoreError);
            }
        }
        
        // Fallback to direct API call
        if (!queueData) {
            const response = await this.apiCall('/admission/queues', 'GET');
            queueData = this.unwrapPayload(response);
            
            // Cache in DataStore
            if (typeof DataStore !== 'undefined') {
                await DataStore.set('admissions', queueData, {
                    ttl: 60000,
                    storeName: 'admission_queue_cache'
                });
            }
        }
        
        this.queueData = queueData;
        // ... rest of function
    } catch (error) {
        console.error('Failed to load queue data:', error);
        this.showError('Failed to load admissions data');
    }
}
```

### 2. Updated `viewApplication()` function

**Changes:**
- Changed from synchronous to async function
- Added DataStore integration for application details
- Uses network-first strategy (5 minutes TTL)
- Falls back to direct API call if DataStore fails
- Caches successful API responses in DataStore

**Lines Modified:** 930-997

**Code Pattern:**
```javascript
viewApplication: async function(applicationId) {
    try {
        let payload;
        
        // Try DataStore first for caching
        if (typeof DataStore !== 'undefined') {
            try {
                payload = await DataStore.get(`admissions:${applicationId}`, {
                    strategy: 'network-first',
                    ttl: 300000, // 5 minutes
                    storeName: 'admission_queue_cache',
                    endpoint: `/admission/application/${applicationId}`,
                    params: { id: applicationId }
                });
            } catch (dataStoreError) {
                console.warn("DataStore failed, falling back to API:", dataStoreError);
            }
        }
        
        // Fallback to direct API call
        if (!payload) {
            const response = await this.apiCall(`/admission/application/${applicationId}`, "GET");
            payload = this.unwrapPayload(response);
            
            // Cache in DataStore
            if (typeof DataStore !== 'undefined') {
                await DataStore.set(`admissions:${applicationId}`, payload, {
                    ttl: 300000,
                    storeName: 'admission_queue_cache'
                });
            }
        }
        
        // ... rest of function
    } catch (error) {
        console.error("Failed to load application details:", error);
        this.notify("error", error.message || "Failed to load application details");
    }
}
```

## Architecture Compliance

✅ **All API calls use `this.apiCall()`** - flows through centralized `/js/api.js`  
✅ **DataStore uses `window.API.apiCall()` internally** - respects your architecture  
✅ **Fallback pattern maintained** - if DataStore fails, falls back to original implementation  
✅ **Backward compatible** - existing code continues to work if DataStore is not available  

## Expected Behavior

### Online Scenario
1. First load: DataStore cache miss → API call → cache response → return data
2. Subsequent loads (within 1 minute): DataStore cache hit → return cached data + background refresh
3. Mutation (POST/PUT/PATCH): `/js/api.js` automatically invalidates DataStore cache

### Offline Scenario
1. Network offline: DataStore serves stale cached data
2. User sees cached admission queue and application details
3. UI indicates offline status (via ConnectivityManager)

## Testing Checklist

- [ ] Load admissions page - should load with DataStore caching
- [ ] Check console for "Admissions data from DataStore" message
- [ ] Refresh page - should load from cache initially
- [ ] Perform admission action - should invalidate cache automatically
- [ ] Disable network - should serve stale data from cache
- [ ] Re-enable network - should refresh data automatically

## Next Steps

Phase 1 (Data Caching) is complete for Admissions module. 

**Recommended next steps:**
1. Test the changes in a browser environment
2. Verify console logs show DataStore integration working
3. If successful, proceed to Phase 2 (Offline Drafts)
4. If issues arise, revert changes (backward compatible)

## Rollback Plan

If issues arise, revert the two functions to their original implementations:
- `loadQueueData()` - remove DataStore integration
- `viewApplication()` - change back to synchronous with direct API call

The fallback pattern ensures the original implementation still works if DataStore is not available.
