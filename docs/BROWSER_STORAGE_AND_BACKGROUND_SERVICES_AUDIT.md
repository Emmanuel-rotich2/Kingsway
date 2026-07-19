# Browser Storage and Background Services Audit

> **STATUS UPDATE (2026-07-14):** The body of this audit below describes the
> codebase *as it was at audit time*. Since then the storage/sync stack has been
> **implemented and is live**. The "NOT IMPLEMENTED" rows are now OUTDATED.
> Verified current state:
> - **IndexedDB** — implemented (`js/storage/kingsway_db.js`), used by `DataStore`.
> - **Service Worker** — exists (`service-worker.js`), registers with correct
>   `/Kingsway` subpath scope (`js/core/service_worker_manager.js`). Cache Storage
>   strategies (cache-first, SWR) are implemented.
> - **Background Sync** — `SyncManager` IS registered (`js/sync/sync_queue.js`
>   calls `registration.sync.register('sync-outbox')`); however
>   `service-worker.js` `syncOutbox()`/`syncDrafts()` are still `// TODO` stubs,
>   so registration exists but the server reconciliation is not wired yet.
> - **Caching** — `js/core/data_store.js` does stale-while-revalidate with
>   memory + IndexedDB layers; reference data (classes/subjects/terms/years/
>   departments) and directory lists (students/staff) are being warmed through
>   it. NOTE: the IndexedDB array-persistence path was silently broken
>   (`setCached` spread arrays had no `id` for `keyPath:'id'` stores) and has
>   since been fixed via an `{id, data}` envelope in `persist()`/`DataStore.set`.
> The original fragmentation/auth findings in the body remain valid and worth
> reading; only the "nothing is implemented" conclusion is superseded.

## Executive Summary

This audit reveals a fragmented authentication and storage architecture with multiple competing token sources, no centralized API client, no offline support, and no modern browser capabilities implemented. The system relies entirely on network connectivity with no resilience or synchronization mechanisms.

<sub>The above paragraph reflected the state at audit time; see the status note above — the storage/sync capabilities have since been built.</sub>

## Current Authentication Landscape

### Token Storage Sources Found

| Source | Location | Usage | Problems |
|--------|----------|-------|----------|
| `localStorage.getItem("token")` | 20+ files | Direct access | Bypasses AuthContext, inconsistent |
| `sessionStorage.getItem("token")` | 8+ files | Direct access | Bypasses AuthContext, inconsistent |
| `AuthContext.getToken()` | 263+ files | Centralized (correct) | Storage desync bug (fixed) |
| `Authorization: Bearer` header | 51 files | API calls | Manual header construction |
| `document.cookie` | 1 file | StorageManager only | Not used for auth |
| `refresh_token` cookie | AuthAPI.php | HttpOnly (secure) | Correct implementation |

### Authentication Inconsistencies

**Module A (Dashboard Router):**
```javascript
// Lines 219-221 - Fallback direct storage access
const userData = JSON.parse(localStorage.getItem('user_data') || 
                          sessionStorage.getItem('user_data') || '{}');
```

**Module B (Various pages):**
```javascript
// Multiple pages bypass AuthContext entirely
const token = localStorage.getItem("token");
```

**Module C (AuthContext):**
```javascript
// Centralized but with storage desync (fixed)
function getToken() {
  return getItem("token");
}
```

**Module D (API Middleware):**
```javascript
// Server-side expects Authorization header
$authHeader = $headers['Authorization'];
```

### Authentication Flow Analysis

**Current Flow:**
1. Login → JWT token returned to client
2. Client stores token in localStorage/sessionStorage (based on "Remember me")
3. Refresh token stored as HttpOnly cookie (secure)
4. API calls manually construct `Authorization: Bearer <token>` header
5. Token refresh checks both localStorage token and cookie refresh token
6. Storage desync caused 401 errors (fixed but architecture remains fragmented)

**Problems:**
- No single source of truth for token access
- Manual header construction in every API call
- Multiple storage access patterns
- No centralized session management
- No cross-tab synchronization
- Storage desync between localStorage/sessionStorage

## Current Storage Landscape

### localStorage Usage

| Data Type | Keys | Size | Purpose | Security Risk |
|-----------|------|------|---------|---------------|
| Auth tokens | `token`, `refresh_token` | ~2KB | Authentication | HIGH (XSS vulnerable) |
| User data | `user_data`, `user_permissions`, `user_roles` | ~5KB | Session state | MEDIUM (PII) |
| UI state | `auth_storage_mode`, sidebar state | ~1KB | Preferences | LOW |
| Dev bypass | `dev_bypass_auth` | ~50B | Development | MEDIUM (security risk) |
| Dashboard config | `dashboard_router_config` | ~2KB | Caching | LOW |
| Migration flags | `jwt_migration_*` | ~200B | Migration | LOW |

### sessionStorage Usage

| Data Type | Keys | Size | Purpose | Problems |
|-----------|------|------|---------|----------|
| Auth tokens | `token`, `refresh_token` | ~2KB | Session auth | Duplicates localStorage |
| Dashboard config | `dashboard_router_config` | ~2KB | Caching | Not versioned |
| Navigation state | Various | ~1KB | UI state | No invalidation |

### IndexedDB Usage

**Status:** NOT IMPLEMENTED

- Referenced only in documentation
- No actual IndexedDB operations in codebase
- StorageManager has IndexedDB support but not used

### Cache Storage Usage

**Status:** NOT IMPLEMENTED

- Referenced only in documentation
- No service worker registered
- No cache operations in codebase

### Service Worker Status

**Status:** NOT IMPLEMENTED

- No `service-worker.js` file
- No `sw.js` file
- No service worker registration
- No offline fallback page
- No background sync

## Current API Client Architecture

### API Call Patterns Found

**Pattern 1: Centralized apiCall (correct)**
```javascript
// js/api.js - lines 1284-1396
async function apiCall(endpoint, method, data, params, options) {
  const token = AuthContext.getToken();
  const fetchOptions = {
    headers: {
      ...(token && { Authorization: "Bearer " + token })
    }
  };
  // ... fetch with retry logic
}
```

**Pattern 2: Direct fetch (dashboard)**
```javascript
// Multiple dashboard files
const response = await fetch(url, {
  headers: {
    'Authorization': 'Bearer ' + AuthContext.getToken()
  }
});
```

**Pattern 3: Manual token construction (old pages)**
```javascript
// Legacy pages
const token = localStorage.getItem("token");
fetch(url, {
  headers: {
    'Authorization': 'Bearer ' + token
  }
});
```

### API Client Problems

1. **No centralized request deduplication** - Same data fetched multiple times
2. **No offline queue** - Failed requests are lost
3. **No cache integration** - Every request hits network
4. **No conflict resolution** - No optimistic concurrency
5. **No correlation IDs** - Hard to debug request chains
6. **No structured error handling** - Inconsistent error patterns
7. **No request cancellation** - No abort controller usage

## Current State Management

### Global State Sources

| Source | Files | Purpose | Problems |
|--------|-------|---------|----------|
| `window.AuthContext` | 263+ files | Auth state | Storage desync (fixed) |
| `window.API_BASE_URL` | 21 files | API config | Global pollution |
| `window.APP_BASE` | 264+ files | App path | Global pollution |
| `window.isRefreshingToken` | api.js | Refresh lock | Global state |
| `window.refreshTokenPromise` | api.js | Refresh promise | Global state |
| `window.StorageManager` | New | Storage utility | Good pattern |
| Module-specific globals | 100+ files | Local state | Fragmented |

### Data Flow Problems

1. **No single source of truth** - Multiple global variables
2. **No state synchronization** - Components have different views
3. **No event system** - No pub/sub for state changes
4. **No cache coordination** - Stale data across components
5. **No invalidation propagation** - Changes don't update all views

## Current Offline Capabilities

**Status:** NONE

- No offline detection
- No offline queue
- No offline UI
- No sync on reconnect
- No conflict resolution
- System completely fails without network

## Current Caching Strategy

**Status:** AD-HOC

- sessionStorage for dashboard config (no versioning)
- localStorage for user data (no TTL)
- No HTTP caching headers utilized
- No ETag support
- No stale-while-revalidate
- No cache invalidation on mutations

## Current Cross-Tab Behavior

**Status:** UNSYNCHRONIZED

- No BroadcastChannel
- No storage event listeners
- No shared state
- Logout in one tab doesn't affect others
- Token refresh in one tab doesn't sync

## Current Browser API Usage

### APIs Currently Used

| API | Usage | Implementation | Quality |
|-----|-------|----------------|---------|
| localStorage | Auth, preferences | Direct access | POOR (inconsistent) |
| sessionStorage | Auth, config | Direct access | POOR (no versioning) |
| cookies | Refresh tokens | HttpOnly (secure) | GOOD |
| fetch | API calls | Direct + apiCall | MIXED |
| History API | Navigation | Basic | ADEQUATE |

### APIs Not Used (Should Use)

| API | Priority | Current Status | Impact |
|-----|----------|----------------|--------|
| IndexedDB | HIGH | Not implemented | No offline storage |
| Cache Storage | HIGH | Not implemented | No offline assets |
| Service Worker | HIGH | Not implemented | No offline support |
| Background Sync | MEDIUM | Not implemented | No offline queue |
| BroadcastChannel | MEDIUM | Not implemented | No cross-tab sync |
| Navigation Timing | LOW | Not implemented | No performance data |
| Storage Estimate | LOW | Not implemented | No quota monitoring |

### APIs Intentionally Excluded

| API | Reason for Exclusion |
|-----|---------------------|
| Shared Storage | Privacy/advertising tech, not suitable for school ERP |
| Interest Groups | Privacy/advertising tech, not suitable for school ERP |
| Private State Tokens | Privacy tech, not replacement for proper auth |
| Storage Buckets | Experimental, insufficient browser support |
| Periodic Background Sync | Experimental, insufficient browser support |
| Background Fetch | Experimental, insufficient browser support |

## Current Security Posture

### Authentication Security

| Aspect | Current | Risk | Recommendation |
|--------|---------|------|----------------|
| Token storage | localStorage/sessionStorage | HIGH (XSS) | Migrate to HttpOnly cookies |
| Refresh token | HttpOnly cookie | LOW | Good implementation |
| Token transmission | Authorization header | MEDIUM | Standard practice |
| CSRF protection | None | HIGH | Implement CSRF tokens |
| Session rotation | None | MEDIUM | Implement session rotation |
| Device binding | None | MEDIUM | Consider for future |

### Data Storage Security

| Data Type | Current Storage | Risk | Recommendation |
|-----------|-----------------|------|----------------|
| Auth tokens | localStorage/sessionStorage | HIGH | Move to HttpOnly cookies |
| User PII | localStorage | MEDIUM | Move to session only |
| Permissions | localStorage | LOW | Acceptable (non-sensitive) |
| Preferences | localStorage | LOW | Acceptable |
| Drafts | Not implemented | N/A | Implement with encryption |

## Current Performance Issues

### Network Performance

1. **No request deduplication** - Same data fetched multiple times
2. **No caching** - Every request hits network
3. **No preloading** - No predictive resource loading
4. **No compression** - Check if gzip enabled
5. **No CDN** - All assets served from origin

### Storage Performance

1. **Synchronous localStorage** - Blocks main thread
2. **No quota monitoring** - Risk of quota exceeded errors
3. **No cleanup** - Old data accumulates
4. **No size limits** - Risk of storage bloat

## Current Failure Modes

### Network Failure

**Current Behavior:** Complete system failure

**User Impact:**
- Cannot login
- Cannot load data
- Cannot save forms
- No error recovery
- Data loss possible

### Authentication Failure

**Current Behavior:** Redirect to login

**Problems:**
- No offline indication
- No retry logic
- No error context
- Lost form data

### Storage Failure

**Current Behavior:** Silent failures or crashes

**Problems:**
- No quota handling
- No fallback mechanisms
- No error recovery
- Data corruption possible

## Recommended Technology Classification

### A. REQUIRED NOW (Implement Immediately)

| Technology | Priority | Current | Implementation Complexity |
|------------|----------|---------|---------------------------|
| IndexedDB | HIGH | Not implemented | MEDIUM |
| Cache Storage | HIGH | Not implemented | MEDIUM |
| Service Worker | HIGH | Not implemented | HIGH |
| Centralized Session Manager | HIGH | Partial (AuthContext) | MEDIUM |
| Centralized API Client | HIGH | Partial (apiCall) | MEDIUM |
| Offline Write Queue | HIGH | Not implemented | HIGH |
| Cross-Tab Sync | HIGH | Not implemented | MEDIUM |
| Cache Invalidation | HIGH | Not implemented | MEDIUM |
| Storage Quota Monitoring | MEDIUM | Not implemented | LOW |
| Online/Offline Manager | MEDIUM | Not implemented | LOW |

### B. USE WITH FALLBACK (Implement with Feature Detection)

| Technology | Priority | Fallback Strategy |
|------------|----------|-------------------|
| Background Sync | MEDIUM | Manual sync on reconnect |
| Storage Buckets | LOW | Regular IndexedDB |
| Periodic Background Sync | LOW | Refresh on visibility change |
| Web Locks | LOW | Promise-based coordination |
| BroadcastChannel | MEDIUM | Storage events as fallback |

### C. FUTURE / EXPERIMENTAL (Not Required for Core)

| Technology | Status | Reason |
|------------|--------|--------|
| Device Bound Sessions | Experimental | Insufficient browser support |
| Periodic Background Sync | Experimental | Insufficient browser support |
| Background Fetch | Experimental | Insufficient browser support |
| Storage Buckets | Experimental | Insufficient browser support |

### D. NOT RELEVANT TO KINGWAY (Intentionally Excluded)

| Technology | Reason for Exclusion |
|------------|---------------------|
| Shared Storage | Privacy/advertising technology |
| Interest Groups | Privacy/advertising technology |
| Private State Tokens | Privacy technology, not auth replacement |
| Payment Handler | Backend payment integration required |

### E. BROWSER SECURITY / PRIVACY (Study Only)

| Technology | Action |
|------------|--------|
| Bounce tracking mitigations | Ensure first-party only |
| Anti-tracking controls | Use first-party storage |
| Third-party storage restrictions | Use first-party storage |

## Detailed Technology Recommendations

### Authentication Storage Policy

**Current Problem:** JWT tokens stored in localStorage/sessionStorage (XSS vulnerable)

**Recommended Solution:**
1. Keep refresh token as HttpOnly cookie (already implemented correctly)
2. Migrate access token to short-lived HttpOnly cookie
3. Implement session endpoint for client state
4. Use credentials: "include" for all API calls
5. Implement CSRF protection

**Migration Strategy:**
1. Create SessionController with /api/auth/session endpoint
2. Implement SessionManager client-side
3. Migrate AuthContext to use session endpoint
4. Remove tokens from localStorage/sessionStorage
5. Implement token migration service for legacy tokens

### Storage Ownership Matrix

**localStorage (Small non-sensitive preferences only):**
- Theme: `kingsway:ui:theme`
- Sidebar state: `kingsway:ui:sidebar`
- Table density: `kingsway:ui:table-density`
- Language: `kingsway:ui:language`
- Accessibility: `kingsway:ui:accessibility`

**sessionStorage (Tab-scoped temporary state):**
- Navigation state: `kingsway:nav:state`
- Wizard steps: `kingsway:wizard:step`
- Modal state: `kingsway:modal:state`
- Redirect targets: `kingsway:nav:redirect`

**IndexedDB (Structured data, offline support):**
- Reference metadata: classes, streams, subjects, terms
- Cached read models: student directory, staff directory
- Offline drafts: admission forms, attendance drafts
- Pending mutations: sync_outbox
- Sync conflicts: sync_conflicts
- Notifications: notification_inbox

**Cache Storage (Static assets, safe API responses):**
- Application shell: CSS, JS, icons, fonts
- API cache: Reference data, school configuration

### Service Worker Implementation

**Responsibilities:**
- Cache application shell (versioned)
- Offline fallback page
- Safe API caching (GET only)
- Background sync registration
- Push notification handling
- Cache version upgrades
- Old cache cleanup

**Cache Strategy:**
- Cache First: Immutable assets, logos, icons, fonts
- Stale While Revalidate: Classes, streams, subjects, terms
- Network First: Student lists, admission queues, attendance
- Network Only: Auth, payments, approvals, payroll

### Offline Write Queue

**Eligible Operations:**
- Attendance marking
- Low-risk notes
- Admission draft updates
- Activity participation
- Inventory counts
- Boarding roll call

**Not Eligible (High-Risk):**
- Payments
- Payroll
- Final approvals
- Student deletion
- Fee adjustments
- Examination publication

**Queue Structure:**
```javascript
{
  id: "uuid",
  operation_id: "uuid",
  module: "attendance",
  endpoint: "/api/attendance/mark",
  method: "POST",
  payload: {...},
  entity_type: "attendance_record",
  entity_id: 123,
  created_at: timestamp,
  updated_at: timestamp,
  retry_count: 0,
  last_error: null,
  status: "pending",
  user_id: 4,
  school_id: 1,
  idempotency_key: "uuid",
  dependency_ids: []
}
```

### Conflict Resolution

**Strategy:** Optimistic concurrency with user-mediated conflict resolution

**Implementation:**
1. Backend records include version/updated_at/ETag
2. Client mutations include If-Match or record_version
3. On 409 Conflict, store in sync_conflicts
4. Show conflict UI with server/local versions
5. User chooses: merge, discard local, reapply

### Cross-Tab Synchronization

**Channel:** `kingsway-app`

**Events:**
- SESSION_CHANGED
- LOGGED_OUT
- PERMISSIONS_UPDATED
- CACHE_INVALIDATED
- ENTITY_UPDATED
- SYNC_COMPLETED
- SERVICE_WORKER_UPDATED

**Fallback:** Storage event listener for localStorage changes

### Push Notifications

**Use Cases:**
- New admission application
- Interview scheduled
- Admission decision
- Fee payment confirmation
- Timetable change
- School announcement
- Attendance alert
- Transport incident
- Approval required
- Report ready
- Sync completed

**Permission Strategy:**
- Request after meaningful user action
- Granular preferences by module
- Respect browser notification settings

## Implementation Priority

### Phase A: Foundation (Week 1-2)
1. SessionManager implementation
2. ApiClient centralization
3. Token migration service
4. Remove direct localStorage token access

### Phase B: Storage Layer (Week 3-4)
1. IndexedDB implementation
2. DataStore with caching
3. Metadata caching
4. Cache invalidation

### Phase C: Service Worker (Week 5-6)
1. Service worker registration
2. Static asset caching
3. Offline fallback page
4. Cache version management

### Phase D: Offline Support (Week 7-8)
1. Offline write queue
2. Background sync
3. Conflict resolution
4. Connectivity manager

### Phase E: Push Notifications (Week 9-10)
1. Push service integration
2. Notification manager
3. Permission handling
4. Preferences UI

### Phase F: Cross-Tab Sync (Week 11)
1. BroadcastChannel implementation
2. Event system
3. Session coordination
4. Cache synchronization

## Migration Risks

### High Risk Areas

1. **Token storage migration** - Could break all auth
2. **API client changes** - Could break all API calls
3. **Service worker** - Could break asset loading
4. **IndexedDB schema** - Could break offline storage

### Mitigation Strategies

1. **Feature flags** - Roll out changes gradually
2. **A/B testing** - Test with subset of users
3. **Rollback plan** - Quick revert capability
4. **Monitoring** - Detect issues early
5. **User communication** - Explain changes

## Testing Requirements

### Functional Testing

1. **Authentication flow**
   - Login with remember me
   - Login without remember me
   - Token refresh
   - Session expiry
   - Logout

2. **Offline scenarios**
   - Offline during form submission
   - Offline during data load
   - Reconnect after offline
   - Sync queue processing
   - Conflict resolution

3. **Cross-tab scenarios**
   - Login in one tab
   - Logout in one tab
   - Token refresh in one tab
   - Cache invalidation
   - State synchronization

4. **Storage scenarios**
   - Quota exceeded
   - Storage disabled
   - Private browsing
   - Storage corruption
   - Migration from old format

### Performance Testing

1. **Cache hit rates**
2. **Offline recovery time**
3. **Sync queue processing**
4. **Storage operation timing**
5. **Service worker startup**

### Security Testing

1. **XSS attempts on token storage**
2. **CSRF token validation**
3. **Session fixation attempts**
4. **Token leakage scenarios**
5. **Cross-origin isolation**

## Success Metrics

### Technical Metrics

- **Cache hit rate:** >80% for reference data
- **Offline success rate:** >95% for eligible operations
- **Sync success rate:** >99% for queued operations
- **Conflict rate:** <1% of offline operations
- **Storage quota usage:** <50% of available

### User Experience Metrics

- **Page load time:** <2 seconds (cached)
- **Offline recovery time:** <5 seconds
- **Form data loss:** <0.1% of forms
- **Cross-tab consistency:** 100%
- **Authentication failures:** <0.5%

## Conclusion

The current system has a fragmented authentication and storage architecture with no modern browser capabilities implemented. The system is entirely dependent on network connectivity with no resilience or synchronization mechanisms.

The recommended implementation will transform Kingsway into a modern, offline-capable, synchronized school management system while maintaining security best practices and data integrity.

The server database will remain the authoritative source, with browser storage used only for caching, temporary offline availability, pending synchronization, drafts, preferences, performance, and resilience.

**Next Steps:** Proceed with Phase A implementation - SessionManager, ApiClient, and token migration service.
