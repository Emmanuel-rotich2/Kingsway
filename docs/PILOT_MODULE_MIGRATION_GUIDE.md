# Pilot Module Migration Guide

## Overview

This guide provides step-by-step instructions for migrating pilot modules (Admissions, Students, Attendance) to use the new browser storage, offline support, and synchronization infrastructure.

**Goal:** Demonstrate that the new infrastructure works in practice with real-world modules.

**Strategy:** Incremental migration with backward compatibility maintained throughout.

## Prerequisites

Before starting migration, ensure:
- ✅ All new infrastructure is loaded in `home.php`
- ✅ KingswayDB is initialized
- ✅ DataStore is initialized
- ✅ SyncQueue is initialized
- ✅ ConnectivityManager is initialized
- ✅ StorageMonitor is initialized

## Migration Strategy

### Phase 1: Data Caching (Low Risk)
Replace direct API calls with DataStore for read operations.

### Phase 2: Offline Drafts (Medium Risk)
Add offline draft support for form data.

### Phase 3: Offline Operations (High Risk)
Enable offline queuing for write operations.

### Phase 4: Conflict Resolution (High Risk)
Add conflict resolution for data synchronization.

## Module 1: Admissions

### Current Implementation

**File:** `pages/manage_students_admissions.php`  
**Script:** `js/pages/admissions_workspace.js`  
**Key Function:** `loadQueueData()` - Loads admission queue data

### Phase 1: Data Caching

**Step 1: Identify Cached Data**

The admissions module fetches:
- Admission queues (`/admission/queues`)
- Application details (`/admission/application/{id}`)
- Workflow data
- Documents

**Step 2: Update `loadQueueData()` to use DataStore**

**Before:**
```javascript
loadQueueData: async function() {
    try {
        const response = await this.apiCall('/admission/queues', 'GET');
        console.log("Admissions workspace response:", response);

        if (!this.isSuccessfulResponse(response)) {
            throw new Error(response?.message || "Failed to load admissions data.");
        }

        this.queueData = this.unwrapPayload(response);
        console.log("Queue data loaded:", this.queueData);

        this.updateSummaryCards();
        this.updateTabBadges();
        this.loadCurrentTab();
    } catch (error) {
        console.error('Failed to load queue data:', error);
        this.showError('Failed to load admissions data');
    }
},
```

**After:**
```javascript
loadQueueData: async function() {
    try {
        let queueData;
        
        // Try DataStore first
        if (typeof DataStore !== 'undefined') {
            try {
                queueData = await DataStore.get('admissions', {
                    strategy: 'stale-while-revalidate',
                    ttl: 60000, // 1 minute for fresh queue data
                    storeName: 'admission_queue_cache',
                    endpoint: '/admission/queues',
                    forceRefresh: false
                });
                console.log("Admissions data from DataStore:", queueData);
            } catch (dataStoreError) {
                console.warn("DataStore failed, falling back to API:", dataStoreError);
            }
        }
        
        // Fallback to direct API call
        if (!queueData) {
            const response = await this.apiCall('/admission/queues', 'GET');
            console.log("Admissions workspace response:", response);

            if (!this.isSuccessfulResponse(response)) {
                throw new Error(response?.message || "Failed to load admissions data.");
            }

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
        console.log("Queue data loaded:", this.queueData);

        this.updateSummaryCards();
        this.updateTabBadges();
        this.loadCurrentTab();
    } catch (error) {
        console.error('Failed to load queue data:', error);
        this.showError('Failed to load admissions data');
    }
},
```

**Step 3: Update `viewApplication()` to use DataStore**

**Before:**
```javascript
viewApplication: function(applicationId) {
    if (!applicationId || Number.isNaN(Number(applicationId))) {
        this.notify("error", "Invalid application selected");
        return;
    }

    this.apiCall(`/admission/application/${applicationId}`, "GET")
        .then((response) => {
            if (!this.isSuccessfulResponse(response)) {
                throw new Error(response?.message || "Failed to load application details");
            }

            const payload = this.unwrapPayload(response);
            // ... rest of the code
        })
        .catch((error) => {
            console.error("Failed to view application:", error);
            this.notify("error", error.message || "Failed to load application details");
        });
},
```

**After:**
```javascript
viewApplication: async function(applicationId) {
    if (!applicationId || Number.isNaN(Number(applicationId))) {
        this.notify("error", "Invalid application selected");
        return;
    }

    try {
        let applicationData;
        
        // Try DataStore first
        if (typeof DataStore !== 'undefined') {
            try {
                applicationData = await DataStore.get(`admissions:${applicationId}`, {
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
        if (!applicationData) {
            const response = await this.apiCall(`/admission/application/${applicationId}`, "GET");
            
            if (!this.isSuccessfulResponse(response)) {
                throw new Error(response?.message || "Failed to load application details");
            }

            const payload = this.unwrapPayload(response);
            applicationData = payload;
            
            // Cache in DataStore
            if (typeof DataStore !== 'undefined') {
                await DataStore.set(`admissions:${applicationId}`, applicationData, {
                    ttl: 300000,
                    storeName: 'admission_queue_cache'
                });
            }
        }

        // ... rest of the code
    } catch (error) {
        console.error("Failed to view application:", error);
        this.notify("error", error.message || "Failed to load application details");
    }
},
```

**Step 4: Cache invalidation is automatic**

The centralized API (`/js/api.js`) automatically invalidates DataStore caches on mutations (POST/PUT/PATCH/DELETE). No manual invalidation is needed when using `this.apiCall()`.

```javascript
runAdmissionAction: async function(actionPromise, successMessage) {
    try {
        await actionPromise;
        this.notify("success", successMessage);
        this.closeWorkspaceModal();
        
        // Cache invalidation is automatic via window.API.apiCall
        await this.loadQueueData();
    } catch (error) {
        console.error("Admission action failed:", error);
        this.notify("error", error.message || "Admission action failed");
    }
},
```

### Phase 2: Offline Drafts

**Step 1: Identify Draft-Eligible Forms**

- Document upload form
- Application review form
- Interview form
- Decision form

**Step 2: Add draft saving on form change**

```javascript
saveDraft: async function(formType, formData) {
    if (typeof KingswayDB === 'undefined') {
        console.warn("KingswayDB not available, skipping draft save");
        return;
    }

    try {
        const draft = {
            id: generateUUID(),
            module: 'admissions',
            form_type: formType,
            form_data: formData,
            created_at: Date.now(),
            updated_at: Date.now(),
            user_id: getCurrentUserId(),
            status: 'draft'
        };

        await KingswayDB.add('offline_drafts', draft);
        console.log("Draft saved:", draft.id);
    } catch (error) {
        console.error("Failed to save draft:", error);
    }
},
```

**Step 3: Add draft loading on form open**

```javascript
loadDraft: async function(formType) {
    if (typeof KingswayDB === 'undefined') {
        return null;
    }

    try {
        const drafts = await KingswayDB.getByIndex('offline_drafts', 'form_type', formType);
        const userDrafts = drafts.filter(d => d.user_id === getCurrentUserId());
        
        if (userDrafts.length > 0) {
            const latestDraft = userDrafts.sort((a, b) => b.updated_at - a.updated_at)[0];
            console.log("Found draft:", latestDraft.id);
            return latestDraft;
        }
        
        return null;
    } catch (error) {
        console.error("Failed to load draft:", error);
        return null;
    }
},
```

### Phase 3: Offline Operations

**Step 1: Identify Eligible Operations**

Eligible for offline queuing:
- Document upload
- Application review
- Interview notes
- Decision updates

Not eligible:
- Final approval
- Payment processing
- Student deletion

**Step 2: Note on Offline Operations**

The centralized API (`window.API.apiCall` from `/js/api.js`) does not currently support automatic offline queuing. Offline operations require manual implementation using the SyncQueue.

**Example of manual offline queuing:**

```javascript
submitDocuments: async function(applicationId, documents) {
    try {
        // Check if offline
        if (!navigator.onLine) {
            // Queue operation for sync
            if (typeof SyncQueue !== 'undefined') {
                await SyncQueue.addOperation({
                    module: 'admissions',
                    endpoint: `/admission/application/${applicationId}/documents`,
                    method: 'POST',
                    payload: documents,
                    entity_type: 'document_upload',
                    entity_id: applicationId,
                    priority: 5
                });
                this.notify("info", "Document upload saved. Will sync when connection is restored.");
                return;
            }
            
            // Fallback error
            this.notify("warning", "You are offline. Please check your connection.");
            return;
        }
        
        // Online - use centralized API
        const response = await this.apiCall(`/admission/application/${applicationId}/documents`, "POST", documents);
        
        // ... rest of the code
    } catch (error) {
        console.error("Document upload failed:", error);
        this.notify("error", error.message || "Document upload failed");
    }
},
```

### Phase 4: Conflict Resolution

**Step 1: Subscribe to conflict events**

```javascript
init: async function() {
    // ... existing initialization code
    
    // Subscribe to conflict events
    if (typeof ConflictManager !== 'undefined') {
        ConflictManager.subscribe('CONFLICT_DETECTED', (conflict) => {
            this.handleConflict(conflict);
        });
    }
},

handleConflict: function(conflict) {
    console.log("Conflict detected:", conflict);
    
    // Show conflict resolution UI
    const conflictMessage = `
        <div class="alert alert-warning">
            <h5><i class="bi bi-exclamation-triangle"></i> Data Conflict Detected</h5>
            <p>There is a conflict between your offline changes and the server data.</p>
            <p><strong>Entity:</strong> ${conflict.entity_type} #${conflict.entity_id}</p>
            <div class="mt-3">
                <button class="btn btn-primary" onclick="resolveConflict('${conflict.id}', 'keep_server')">Keep Server Version</button>
                <button class="btn btn-success" onclick="resolveConflict('${conflict.id}', 'keep_local')">Keep Your Changes</button>
            </div>
        </div>
    `;
    
    this.notify("warning", "Data conflict detected. Please check the conflict resolution panel.");
},
```

**Step 2: Add conflict resolution handlers**

```javascript
resolveConflict: async function(conflictId, resolution) {
    if (typeof ConflictManager === 'undefined') {
        return;
    }

    try {
        await ConflictManager.resolveConflict(conflictId, resolution);
        this.notify("success", "Conflict resolved successfully");
        await this.loadQueueData();
    } catch (error) {
        console.error("Failed to resolve conflict:", error);
        this.notify("error", error.message || "Failed to resolve conflict");
    }
},
```

## Module 2: Students

### Current Implementation

**File:** `pages/all_students.php`  
**Script:** `js/pages/all_students.js`  
**Key Function:** Loads student directory data

### Phase 1: Data Caching

**Step 1: Update student list loading to use DataStore**

```javascript
loadStudents: async function(params = {}) {
    try {
        let studentsData;
        
        // Try DataStore first
        if (typeof DataStore !== 'undefined') {
            try {
                studentsData = await DataStore.get('students', {
                    strategy: 'stale-while-revalidate',
                    ttl: 300000, // 5 minutes
                    storeName: 'student_directory_cache',
                    endpoint: '/api/students',
                    params: params
                });
                console.log("Students data from DataStore:", studentsData);
            } catch (dataStoreError) {
                console.warn("DataStore failed, falling back to API:", dataStoreError);
            }
        }
        
        // Fallback to direct API call
        if (!studentsData) {
            const response = await this.apiCall('/api/students', 'GET', null, params);
            
            if (!this.isSuccessfulResponse(response)) {
                throw new Error(response?.message || "Failed to load students");
            }

            studentsData = this.unwrapPayload(response);
            
            // Cache in DataStore
            if (typeof DataStore !== 'undefined') {
                await DataStore.set('students', studentsData, {
                    ttl: 300000,
                    storeName: 'student_directory_cache'
                });
            }
        }

        this.students = studentsData;
        this.renderStudents();
    } catch (error) {
        console.error('Failed to load students:', error);
        this.showError('Failed to load students');
    }
},
```

**Step 2: Cache invalidation is automatic**

The centralized API (`/js/api.js`) automatically invalidates DataStore caches on mutations. No manual invalidation is needed when using `this.apiCall()`.

```javascript
updateStudent: async function(studentId, data) {
    try {
        const response = await this.apiCall(`/api/students/${studentId}`, 'PUT', data);
        
        if (!this.isSuccessfulResponse(response)) {
            throw new Error(response?.message || "Failed to update student");
        }
        
        // Cache invalidation is automatic via window.API.apiCall
        this.notify("success", "Student updated successfully");
        await this.loadStudents();
    } catch (error) {
        console.error("Failed to update student:", error);
        this.notify("error", error.message || "Failed to update student");
    }
},
```

## Module 3: Attendance

### Current Implementation

**File:** `pages/mark_attendance.php`  
**Script:** `js/pages/mark_attendance.js`  
**Key Function:** Loads attendance roster and marks attendance

### Phase 1: Data Caching

**Step 1: Update attendance roster loading to use DataStore**

```javascript
loadPassengers: async function() {
    try {
        let passengersData;
        
        // Try DataStore first
        if (typeof DataStore !== 'undefined') {
            try {
                passengersData = await DataStore.get('attendance_roster', {
                    strategy: 'network-first',
                    ttl: 300000, // 5 minutes
                    storeName: 'attendance_roster_cache',
                    endpoint: '/api/attendance/roster',
                    params: {
                        date: this.state.selectedDate,
                        route_id: this.ui.routeSelect.value
                    }
                });
                console.log("Attendance roster from DataStore:", passengersData);
            } catch (dataStoreError) {
                console.warn("DataStore failed, falling back to API:", dataStoreError);
            }
        }
        
        // Fallback to direct API call
        if (!passengersData) {
            const response = await this.apiCall('/api/attendance/roster', 'GET', null, {
                date: this.state.selectedDate,
                route_id: this.ui.routeSelect.value
            });
            
            if (!this.isSuccessfulResponse(response)) {
                throw new Error(response?.message || "Failed to load attendance roster");
            }

            passengersData = this.unwrapPayload(response);
            
            // Cache in DataStore
            if (typeof DataStore !== 'undefined') {
                await DataStore.set('attendance_roster', passengersData, {
                    ttl: 300000,
                    storeName: 'attendance_roster_cache'
                });
            }
        }

        this.state.passengers = passengersData;
        this.renderPassengers();
    } catch (error) {
        console.error('Failed to load passengers:', error);
        this.showError('Failed to load attendance roster');
    }
},
```

### Phase 3: Offline Operations

**Step 1: Manual offline queuing for attendance marking**

```javascript
saveAttendance: async function() {
    try {
        const attendanceData = this.collectAttendanceData();
        
        // Check if offline
        if (!navigator.onLine) {
            // Queue operation for sync
            if (typeof SyncQueue !== 'undefined') {
                await SyncQueue.addOperation({
                    module: 'attendance',
                    endpoint: '/api/attendance/mark',
                    method: 'POST',
                    payload: attendanceData,
                    entity_type: 'attendance',
                    entity_id: null,
                    priority: 5
                });
                this.notify("info", "Attendance saved. Will sync when connection is restored.");
                return;
            }
            
            // Fallback error
            this.notify("warning", "You are offline. Attendance will sync when connection is restored.");
            return;
        }
        
        // Online - use centralized API
        const response = await this.apiCall('/api/attendance/mark', 'POST', attendanceData);
        
        this.notify("success", "Attendance saved successfully");
        
        // Cache invalidation is automatic via window.API.apiCall
        await this.loadPassengers();
    } catch (error) {
        console.error("Failed to save attendance:", error);
        this.notify("error", error.message || "Failed to save attendance");
    }
},
```

## Testing Strategy

### Phase 1 Testing

1. **Online Testing:**
   - Verify data loads correctly
   - Verify cache is populated
   - Verify cache hits on subsequent loads
   - Verify cache invalidation on mutations

2. **Offline Testing:**
   - Disable network connection
   - Verify stale data is served from cache
   - Verify user sees appropriate offline indicators

### Phase 2 Testing

1. **Draft Saving:**
   - Fill out a form
   - Navigate away
   - Return to form
   - Verify draft is restored

2. **Draft Cleanup:**
   - Submit form successfully
   - Verify draft is deleted

### Phase 3 Testing

1. **Offline Queuing:**
   - Disable network connection
   - Perform eligible operation
   - Verify operation is queued
   - Re-enable network
   - Verify operation syncs automatically

2. **Operation Eligibility:**
   - Try high-risk operation offline
   - Verify it's rejected with appropriate message

### Phase 4 Testing

1. **Conflict Detection:**
   - Modify data offline
   - Modify same data on another device
   - Reconnect
   - Verify conflict is detected

2. **Conflict Resolution:**
   - Choose "keep server" option
   - Verify server version is applied
   - Choose "keep local" option
   - Verify local version is applied

## Rollback Plan

If issues arise during migration:

1. **Revert DataStore calls:**
   - Remove DataStore integration
   - Restore direct API calls
   - No data loss

2. **Revert ApiClient calls:**
   - Remove ApiClient integration
   - Restore existing API calls
   - No data loss

3. **Revert draft functionality:**
   - Remove draft saving/loading
   - Forms work as before
   - No data loss

4. **Revert conflict resolution:**
   - Remove conflict event subscriptions
   - System continues without conflict detection
   - No data loss

## Success Metrics

### Performance Metrics
- Cache hit rate >80% for cached data
- Page load time (cached) <2 seconds
- Offline recovery time <5 seconds

### Reliability Metrics
- Sync success rate >99%
- Conflict rate <1%
- Data loss rate <0.1%

### User Experience Metrics
- Offline availability for key features
- Seamless sync on reconnect
- Clear indication of offline status
- Easy cache management

## Migration Checklist

### Admissions Module
- [ ] Update `loadQueueData()` to use DataStore
- [ ] Update `viewApplication()` to use DataStore
- [ ] Add cache invalidation on mutations
- [ ] Add draft saving for document upload
- [ ] Add draft saving for application review
- [ ] Add draft loading on form open
- [ ] Use ApiClient for document upload
- [ ] Use ApiClient for application review
- [ ] Handle queued responses
- [ ] Subscribe to conflict events
- [ ] Add conflict resolution handlers
- [ ] Test online functionality
- [ ] Test offline functionality
- [ ] Test conflict resolution

### Students Module
- [ ] Update `loadStudents()` to use DataStore
- [ ] Add cache invalidation on student mutations
- [ ] Add draft saving for student updates
- [ ] Add manual offline queuing for student updates
- [ ] Test online functionality
- [ ] Test offline functionality

### Attendance Module
- [ ] Update `loadPassengers()` to use DataStore
- [ ] Add cache invalidation on attendance mutations
- [ ] Add manual offline queuing for attendance marking
- [ ] Test online functionality
- [ ] Test offline functionality

## Conclusion

This migration guide provides a step-by-step approach to integrating the new browser storage, offline support, and synchronization infrastructure into the pilot modules. The incremental approach with backward compatibility ensures that migration can be done safely with minimal risk.

**Key Principles:**
1. **Incremental migration** - One phase at a time
2. **Backward compatibility** - Fallback to existing implementation
3. **Comprehensive testing** - Test each phase thoroughly
4. **Clear rollback plan** - Revert if issues arise
5. **Monitor metrics** - Track success metrics throughout

**Next Steps:**
1. Start with Phase 1 (Data Caching) for Admissions module
2. Test thoroughly before proceeding to Phase 2
3. Complete all phases for Admissions before moving to Students
4. Complete Students before moving to Attendance
5. After all modules are migrated, perform comprehensive testing
