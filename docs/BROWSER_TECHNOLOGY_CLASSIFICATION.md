# Browser Technology Classification for Kingsway

> **STATUS UPDATE (2026-07-14):** Most items classified as "Not implemented"
> below have since been built. Verified current state:
> - **IndexedDB** — IMPLEMENTED (`js/storage/kingsway_db.js`), backing `DataStore`.
> - **Cache Storage** — IMPLEMENTED (service-worker cache-first / SWR strategies).
> - **Service Worker** — IMPLEMENTED (`service-worker.js`, registers at the
>   `/Kingsway` subpath scope).
> - **Background Sync** — REGISTERED but reconciliation stubbed
>   (`service-worker.js` `syncOutbox`/`syncDrafts` are `// TODO`).
> - **AuthContext / apiCall** — as classified (Partial), now with the case-
>   insensitive Authorization header fix and web-storage access-token model.
> The "Implementation Plan" sections remain useful as TODO backlog; only the
> "Current Status: Not implemented" labels are superseded.

## Classification Methodology

Each browser technology is classified based on:
- **Relevance to school ERP operations**
- **Browser support and stability**
- **Security implications**
- **Implementation complexity**
- **Risk/benefit ratio**

## A. REQUIRED NOW (Implement Immediately)

### 1. IndexedDB
**Priority:** HIGH  
**Current Status:** Not implemented  
**Browser Support:** IE10+, all modern browsers  
**Implementation Complexity:** MEDIUM  

**Why Required:**
- Structured storage for offline data
- Large capacity (hundreds of MB to GB)
- Asynchronous operations (non-blocking)
- Indexed queries for fast lookups
- Transaction support for data integrity

**Kingsway Use Cases:**
- Reference metadata (classes, streams, subjects)
- Cached read models (student directory, staff directory)
- Offline drafts (admission forms, attendance)
- Pending mutations (sync_outbox)
- Sync conflicts
- Notifications

**Implementation Plan:**
- Create `js/storage/kingsway_db.js`
- Define schema for stores
- Implement CRUD operations
- Add indexing strategy
- Handle quota exceeded

### 2. Cache Storage
**Priority:** HIGH  
**Current Status:** Not implemented  
**Browser Support:** All modern browsers  
**Implementation Complexity:** MEDIUM  

**Why Required:**
- Static asset caching for offline
- API response caching
- Version control for cache updates
- Service worker integration
- Significant performance improvement

**Kingsway Use Cases:**
- Application shell (CSS, JS, icons, fonts)
- Safe API responses (reference data)
- School logo and branding
- Offline fallback page

**Implementation Plan:**
- Create versioned cache names
- Implement cache strategies
- Handle cache invalidation
- Cache size management

### 3. Service Worker
**Priority:** HIGH  
**Current Status:** Not implemented  
**Browser Support:** All modern browsers  
**Implementation Complexity:** HIGH  

**Why Required:**
- Offline functionality foundation
- Background sync capabilities
- Push notification support
- Asset caching control
- Network interception

**Kingsway Use Cases:**
- Offline page serving
- Background sync registration
- Push notification handling
- Cache version management
- Network request interception

**Implementation Plan:**
- Create `service-worker.js`
- Implement lifecycle management
- Add cache strategies
- Handle updates safely
- Debug logging

### 4. Centralized Session Manager
**Priority:** HIGH  
**Current Status:** Partial (AuthContext)  
**Implementation Complexity:** MEDIUM  

**Why Required:**
- Single source of truth for authentication
- Consistent session state across tabs
- Centralized token management
- Session event system
- Migration path to secure cookies

**Kingsway Use Cases:**
- Authentication state management
- Token refresh coordination
- Cross-tab session sync
- Session expiry handling
- Logout coordination

**Implementation Plan:**
- Create `js/core/session_manager.js`
- Migrate AuthContext functionality
- Add event system
- Implement cross-tab sync
- Add session monitoring

### 5. Centralized API Client
**Priority:** HIGH  
**Current Status:** Partial (apiCall)  
**Implementation Complexity:** MEDIUM  

**Why Required:**
- Consistent API call patterns
- Centralized error handling
- Request deduplication
- Offline queue integration
- Correlation ID tracking

**Kingsway Use Cases:**
- All API requests
- Token injection
- Retry logic
- Offline queuing
- Request cancellation

**Implementation Plan:**
- Create `js/core/api_client.js`
- Migrate apiCall functionality
- Add request deduplication
- Integrate offline queue
- Add correlation IDs

### 6. Offline Write Queue
**Priority:** HIGH  
**Current Status:** Not implemented  
**Browser Support:** All modern browsers  
**Implementation Complexity:** HIGH  

**Why Required:**
- Resilience to network failures
- Data protection during outages
- Automatic sync on reconnect
- User confidence in system
- Critical for school operations

**Kingsway Use Cases:**
- Attendance marking
- Admission drafts
- Activity participation
- Inventory counts
- Boarding roll call

**Implementation Plan:**
- Create IndexedDB store
- Implement queue processor
- Add retry logic
- Handle conflicts
- UI for pending operations

### 7. Cross-Tab State Synchronization
**Priority:** HIGH  
**Current Status:** Not implemented  
**Browser Support:** All modern browsers  
**Implementation Complexity:** MEDIUM  

**Why Required:**
- Consistent state across tabs
- Logout coordination
- Cache invalidation propagation
- Session security
- User experience consistency

**Kingsway Use Cases:**
- Session changes
- Logout events
- Permission updates
- Cache invalidation
- Entity updates

**Implementation Plan:**
- Implement BroadcastChannel
- Add storage event fallback
- Define event types
- Add event handlers
- Test cross-tab scenarios

### 8. Cache Invalidation System
**Priority:** HIGH  
**Current Status:** Not implemented  
**Browser Support:** All modern browsers  
**Implementation Complexity:** MEDIUM  

**Why Required:**
- Data consistency across views
- Stale data prevention
- Cache efficiency
- User data accuracy
- System reliability

**Kingsway Use Cases:**
- Student data changes
- Class updates
- Admission workflow transitions
- Permission changes
- Configuration updates

**Implementation Plan:**
- Define invalidation rules
- Implement event system
- Add cache clearing
- Handle cascade invalidation
- Monitor cache health

### 9. Storage Quota Monitoring
**Priority:** MEDIUM  
**Current Status:** Not implemented  
**Browser Support:** All modern browsers  
**Implementation Complexity:** LOW  

**Why Required:**
- Prevent quota exceeded errors
- Proactive cache management
- User notification
- Performance optimization
- Debug storage issues

**Kingsway Use Cases:**
- Monitor IndexedDB usage
- Monitor Cache Storage usage
- Alert on quota limits
- Provide cleanup options
- Optimize storage usage

**Implementation Plan:**
- Use navigator.storage.estimate()
- Create monitoring dashboard
- Add quota alerts
- Implement cleanup strategies
- Log storage events

### 10. Online/Offline State Manager
**Priority:** MEDIUM  
**Current Status:** Not implemented  
**Browser Support:** All modern browsers  
**Implementation Complexity:** LOW  

**Why Required:**
- User awareness of connectivity
- UI adaptation to offline mode
- Sync queue triggering
- Error context
- User guidance

**Kingsway Use Cases:**
- Offline detection
- Online detection
- Connectivity status UI
- Sync triggering
- User guidance

**Implementation Plan:**
- Monitor navigator.onLine
- Add event listeners
- Create connectivity UI
- Trigger sync on reconnect
- Log connectivity changes

## B. USE WITH FALLBACK (Implement with Feature Detection)

### 1. Background Sync API
**Priority:** MEDIUM  
**Current Status:** Not implemented  
**Browser Support:** Chrome, Edge (limited)  
**Fallback Strategy:** Manual sync on reconnect  

**Why Use with Fallback:**
- Improves offline reliability
- Better battery life
- Browser-optimized timing
- Limited browser support
- Not critical for core operations

**Kingsway Use Cases:**
- Sync offline queue
- Refresh cached data
- Upload pending files
- Background data refresh

**Implementation Plan:**
- Feature detection first
- Register sync tasks
- Fallback to manual sync
- Graceful degradation
- Monitor sync success

### 2. Storage Buckets API
**Priority:** LOW  
**Current Status:** Not implemented  
**Browser Support:** Chrome only (experimental)  
**Fallback Strategy:** Regular IndexedDB  

**Why Use with Fallback:**
- Better quota management
- Prioritized storage
- Experimental technology
- Limited browser support
- Not critical for operations

**Kingsway Use Cases:**
- Critical data prioritization
- Reference data separation
- Media storage isolation
- Draft protection

**Implementation Plan:**
- Feature detection
- Use when available
- Fallback to regular IndexedDB
- No dependency on buckets
- Monitor support changes

### 3. Periodic Background Sync
**Priority:** LOW  
**Current Status:** Not implemented  
**Browser Support:** Chrome, Edge (very limited)  
**Fallback Strategy:** Refresh on visibility change  

**Why Use with Fallback:**
- Automatic data refresh
- Better user experience
- Experimental technology
- Very limited support
- Not critical for operations

**Kingsway Use Cases:**
- Refresh timetable
- Refresh announcements
- Refresh class metadata
- Refresh notifications

**Implementation Plan:**
- Feature detection
- Register periodic sync
- Fallback to visibility events
- No dependency on feature
- Manual refresh always available

### 4. Web Locks API
**Priority:** LOW  
**Current Status:** Not implemented  
**Browser Support:** Modern browsers (good support)  
**Fallback Strategy:** Promise-based coordination  

**Why Use with Fallback:**
- Better async coordination
- Prevent race conditions
- Good browser support
- Not critical for operations
- Can use promises instead

**Kingsway Use Cases:**
- Sync queue coordination
- Cache update coordination
- Draft conflict prevention
- IndexedDB transaction coordination

**Implementation Plan:**
- Feature detection
- Use when available
- Fallback to promises
- No dependency on locks
- Test coordination logic

### 5. BroadcastChannel API
**Priority:** MEDIUM  
**Current Status:** Not implemented  
**Browser Support:** All modern browsers  
**Fallback Strategy:** Storage events  

**Why Use with Fallback:**
- Better cross-tab communication
- More efficient than storage events
- Good browser support
- Storage events work as fallback
- Important for cross-tab sync

**Kingsway Use Cases:**
- Session changes
- Logout events
- Cache invalidation
- State synchronization

**Implementation Plan:**
- Feature detection
- Use BroadcastChannel primary
- Fallback to storage events
- Test both mechanisms
- Ensure compatibility

## C. FUTURE / EXPERIMENTAL (Not Required for Core)

### 1. Device Bound Sessions
**Status:** Experimental  
**Browser Support:** Very limited  
**Reason:** Insufficient browser support, not mature enough for production

**Future Consideration:**
- Monitor browser support growth
- Evaluate security benefits
- Assess implementation complexity
- Consider for Phase 4+ migration
- Requires backend changes

### 2. Periodic Background Sync
**Status:** Experimental  
**Browser Support:** Very limited  
**Reason:** Insufficient browser support, can use visibility events instead

**Future Consideration:**
- Monitor Chrome/Edge support
- Evaluate battery impact
- Assess user benefit
- Consider for data refresh only
- Manual refresh always available

### 3. Background Fetch
**Status:** Experimental  
**Browser Support:** Very limited  
**Reason:** Insufficient browser support, limited use cases

**Future Consideration:**
- Monitor browser support growth
- Evaluate for large file downloads
- Assess for report generation
- Consider for offline training materials
- Normal fetch works fine

### 4. Storage Buckets
**Status:** Experimental  
**Browser Support:** Chrome only  
**Reason:** Insufficient browser support, regular IndexedDB works fine

**Future Consideration:**
- Monitor cross-browser support
- Evaluate quota management benefits
- Assess prioritization needs
- Consider for media-heavy features
- Current storage sufficient

## D. NOT RELEVANT TO KINGWAY (Intentionally Excluded)

### 1. Shared Storage
**Reason for Exclusion:**
- Privacy/advertising technology
- Designed for cross-site tracking
- Not suitable for first-party school ERP
- Privacy concerns for student data
- Regulatory compliance issues

**Kingsway Policy:**
- Never store school data in Shared Storage
- Never use for student/staff profiling
- First-party storage only
- GDPR compliance required
- Student data privacy paramount

### 2. Interest Groups
**Reason for Exclusion:**
- Privacy/advertising technology
- Designed for ad targeting
- Not suitable for educational context
- Ethical concerns for student profiling
- Regulatory compliance issues

**Kingsway Policy:**
- Never use Interest Groups
- Never profile students/staff
- Educational purposes only
- Ethical data use required
- No behavioral tracking

### 3. Private State Tokens
**Reason for Exclusion:**
- Privacy technology for fraud detection
- Not a replacement for proper authentication
- Designed for cross-site signals
- Not suitable for first-party auth
- Overkill for school ERP needs

**Kingsway Policy:**
- Use proper authentication (HttpOnly cookies)
- Implement session management
- Use JWT for API authentication
- Server-side session validation
- No need for privacy tokens

## E. BROWSER SECURITY / PRIVACY FEATURES (Study Only)

### 1. Bounce Tracking Mitigations
**Action:** Ensure first-party only operations

**Kingsway Compliance:**
- First-party storage only
- No cross-site tracking
- No third-party cookies
- Same-site cookie policies
- First-party authentication

### 2. Browser Anti-Tracking Controls
**Action:** Respect user privacy settings

**Kingsway Compliance:**
- Work with private browsing
- Respect storage restrictions
- No tracking circumvention
- Privacy-first design
- User control over data

### 3. Third-Party Storage Restrictions
**Action:** Use first-party storage exclusively

**Kingsway Compliance:**
- No third-party localStorage
- No third-party cookies
- First-party IndexedDB only
- First-party Cache Storage only
- No cross-origin storage

## Implementation Timeline

### Phase 1: Foundation (Week 1-2)
- **Centralized Session Manager** (A.4)
- **Centralized API Client** (A.5)
- **Online/Offline Manager** (A.10)

### Phase 2: Storage Layer (Week 3-4)
- **IndexedDB** (A.1)
- **Cache Storage** (A.2)
- **Storage Quota Monitoring** (A.9)

### Phase 3: Service Worker (Week 5-6)
- **Service Worker** (A.3)
- **Cache Invalidation System** (A.8)

### Phase 4: Offline Support (Week 7-8)
- **Offline Write Queue** (A.6)
- **Background Sync** (B.1) with fallback

### Phase 5: Cross-Tab Sync (Week 9)
- **Cross-Tab State Synchronization** (A.7)
- **BroadcastChannel** (B.5) with fallback

### Phase 6: Enhanced Features (Week 10+)
- **Storage Buckets** (B.2) if supported
- **Web Locks** (B.4) if supported
- **Periodic Background Sync** (B.3) if supported

## Risk Assessment

### High Risk Implementations

1. **Service Worker** - Can break asset loading if misconfigured
2. **Offline Write Queue** - Data loss if sync fails
3. **Centralized Session Manager** - Can break all authentication
4. **Cache Storage** - Can serve stale data if invalidation fails

### Risk Mitigation

1. **Feature flags** - Gradual rollout
2. **A/B testing** - Test with subset of users
3. **Rollback plan** - Quick revert capability
4. **Monitoring** - Detect issues early
5. **User communication** - Explain changes

## Success Criteria

### Technical Success

- **IndexedDB:** Successfully stores and retrieves data
- **Cache Storage:** Assets cached and served offline
- **Service Worker:** Registered and intercepting requests
- **Session Manager:** Consistent auth across tabs
- **API Client:** All requests use centralized client
- **Offline Queue:** Operations sync successfully
- **Cross-Tab Sync:** State changes propagate
- **Cache Invalidation:** Stale data cleared

### User Experience Success

- **Offline mode:** System works without network
- **Sync reliability:** >99% sync success rate
- **Cross-tab consistency:** 100% state sync
- **Performance:** <2s cached page loads
- **Data integrity:** No data loss during sync

## Conclusion

This classification prioritizes stable, widely-supported browser technologies that directly benefit Kingsway's school ERP operations. Experimental and privacy-focused technologies are intentionally excluded or deferred until browser support improves.

The implementation timeline balances immediate needs (authentication, storage) with advanced features (offline support, sync) while maintaining system stability and user experience quality.

**Next Phase:** Implement Phase 1 - Centralized Session Manager, API Client, and Online/Offline Manager.
