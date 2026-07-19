# Client Data Ownership Matrix

## Storage Policy Overview

This document defines exactly what data should be stored in which browser storage mechanisms, with clear ownership, TTL, invalidation, and security policies.

**First Principle:** The server database remains the authoritative source. Browser storage is used for caching, temporary offline availability, pending synchronization, drafts, preferences, performance, and resilience only.

## Storage Mechanisms

### localStorage
**Purpose:** Small non-sensitive user preferences  
**Capacity:** ~5-10 MB  
**Persistence:** Persists across sessions  
**Security:** XSS vulnerable (non-sensitive data only)  
**Cleanup:** Manual user action + app lifecycle

### sessionStorage
**Purpose:** Tab-scoped temporary state  
**Capacity:** ~5-10 MB  
**Persistence:** Cleared on tab close  
**Security:** XSS vulnerable (non-sensitive data only)  
**Cleanup:** Automatic on tab close

### IndexedDB
**Purpose:** Structured data, offline support, large datasets  
**Capacity:** Hundreds of MB to GB  
**Persistence:** Persists across sessions (user-scoped)  
**Security:** Better than localStorage (not accessible via simple XSS)  
**Cleanup:** Manual user action + logout + quota management

### Cache Storage
**Purpose:** Static assets, safe API responses  
**Capacity:** Depends on device  
**Persistence:** Persists until versioned invalidation  
**Security:** Same-origin only  
**Cleanup:** Service worker version updates

## localStorage Ownership Matrix

### User Preferences

| Key | Data Type | Max Size | TTL | Invalidation | Security | Owner |
|-----|-----------|----------|-----|--------------|----------|-------|
| `kingsway:ui:theme` | string | 50 B | Permanent | User change | LOW | User |
| `kingsway:ui:sidebar` | boolean | 10 B | Permanent | User change | LOW | User |
| `kingsway:ui:table-density` | string | 20 B | Permanent | User change | LOW | User |
| `kingsway:ui:language` | string | 10 B | Permanent | User change | LOW | User |
| `kingsway:ui:accessibility` | object | 2 KB | Permanent | User change | LOW | User |
| `kingsway:ui:page-size` | number | 10 B | Permanent | User change | LOW | User |
| `kingsway:ui:dismissed-messages` | array | 5 KB | 30 days | User action | LOW | User |

### Migration Flags

| Key | Data Type | Max Size | TTL | Invalidation | Security | Owner |
|-----|-----------|----------|-----|--------------|----------|-------|
| `kingsway:migration:version` | number | 10 B | Permanent | Migration complete | LOW | System |
| `kingsway:migration:auth` | number | 10 B | Permanent | Migration complete | LOW | System |

## sessionStorage Ownership Matrix

### Navigation State

| Key | Data Type | Max Size | TTL | Invalidation | Security | Owner |
|-----|-----------|----------|-----|--------------|----------|-------|
| `kingsway:nav:current-page` | string | 100 B | Tab session | Navigation | LOW | User |
| `kingsway:nav:previous-page` | string | 100 B | Tab session | Navigation | LOW | User |
| `kingsway:nav:redirect-target` | string | 200 B | Tab session | Redirect consumed | LOW | System |

### Wizard State

| Key | Data Type | Max Size | TTL | Invalidation | Security | Owner |
|-----|-----------|----------|-----|--------------|----------|-------|
| `kingsway:wizard:current-step` | number | 10 B | Tab session | Wizard complete/cancel | LOW | User |
| `kingsway:wizard:form-data` | object | 50 KB | Tab session | Wizard complete/cancel | MEDIUM | User |

### Modal State

| Key | Data Type | Max Size | TTL | Invalidation | Security | Owner |
|-----|-----------|----------|-----|--------------|----------|-------|
| `kingsway:modal:active` | string | 50 B | Tab session | Modal close | LOW | System |
| `kingsway:modal:state` | object | 10 KB | Tab session | Modal close | LOW | System |

### Search State

| Key | Data Type | Max Size | TTL | Invalidation | Security | Owner |
|-----|-----------|----------|-----|--------------|----------|-------|
| `kingsway:search:query` | string | 200 B | Tab session | Search change | LOW | User |
| `kingsway:search:filters` | object | 5 KB | Tab session | Search change | LOW | User |

## IndexedDB Ownership Matrix

### Database Structure

**Database Name:** `KingswayDB`  
**Version:** 1

### Reference Metadata Stores

#### Store: `reference_classes`
**Purpose:** Cached class/grade information  
**Owner:** System  
**TTL:** 24 hours  
**Invalidation:** Class create/update/delete

| Field | Type | Description |
|-------|------|-------------|
| id | integer | Primary key |
| name | string | Class name |
| stream_id | integer | Foreign key |
| capacity | integer | Max students |
| teacher_id | integer | Class teacher |
| academic_year_id | integer | Academic year |
| cached_at | timestamp | Cache timestamp |
| expires_at | timestamp | Expiry timestamp |
| user_id | integer | User who cached |
| role_id | integer | User role when cached |
| etag | string | Server ETag for validation |

#### Store: `reference_streams`
**Purpose:** Cached stream information  
**Owner:** System  
**TTL:** 24 hours  
**Invalidation:** Stream create/update/delete

#### Store: `reference_terms`
**Purpose:** Cached term/semester information  
**Owner:** System  
**TTL:** 24 hours  
**Invalidation:** Term create/update/delete

#### Store: `reference_academic_years`
**Purpose:** Cached academic year information  
**Owner:** System  
**TTL:** 7 days  
**Invalidation:** Academic year create/update/delete

#### Store: `reference_subjects`
**Purpose:** Cached subject information  
**Owner:** System  
**TTL:** 24 hours  
**Invalidation:** Subject create/update/delete

#### Store: `reference_departments`
**Purpose:** Cached department information  
**Owner:** System  
**TTL:** 7 days  
**Invalidation:** Department create/update/delete

#### Store: `reference_dormitories`
**Purpose:** Cached dormitory information  
**Owner:** System  
**TTL:** 24 hours  
**Invalidation:** Dormitory create/update/delete

#### Store: `reference_transport_routes`
**Purpose:** Cached transport route information  
**Owner:** System  
**TTL:** 24 hours  
**Invalidation:** Route create/update/delete

#### Store: `reference_activity_types`
**Purpose:** Cached activity type information  
**Owner:** System  
**TTL:** 7 days  
**Invalidation:** Activity type create/update/delete

### Cached Read Models

#### Store: `student_directory_cache`
**Purpose:** Cached student directory for offline viewing  
**Owner:** System  
**TTL:** 5 minutes  
**Invalidation:** Student create/update/delete, class transfer, promotion  
**Security:** MEDIUM (student PII)

| Field | Type | Description |
|-------|------|-------------|
| id | integer | Student ID |
| admission_number | string | Admission number |
| name | string | Student name |
| class_id | integer | Current class |
| stream_id | integer | Current stream |
| status | string | Student status |
| cached_at | timestamp | Cache timestamp |
| expires_at | timestamp | Expiry timestamp |
| user_id | integer | User who cached |
| role_id | integer | User role when cached |
| scope_hash | string | Permission scope hash |

#### Store: `staff_directory_cache`
**Purpose:** Cached staff directory for offline viewing  
**Owner:** System  
**TTL:** 30 minutes  
**Invalidation:** Staff create/update/delete, role change  
**Security:** MEDIUM (staff PII)

#### Store: `class_list_cache`
**Purpose:** Cached class lists for offline viewing  
**Owner:** System  
**TTL:** 10 minutes  
**Invalidation:** Student class change, enrollment change  
**Security:** MEDIUM (student PII)

#### Store: `admission_queue_cache`
**Purpose:** Cached admission queue for offline viewing  
**Owner:** System  
**TTL:** 60 seconds  
**Invalidation:** Any workflow transition  
**Security:** MEDIUM (applicant PII)

#### Store: `attendance_roster_cache`
**Purpose:** Cached attendance roster for offline marking  
**Owner:** System  
**TTL:** 5 minutes  
**Invalidation:** Student enrollment change, class change  
**Security:** MEDIUM (student PII)

### Offline Drafts

#### Store: `offline_drafts`
**Purpose:** Offline form drafts for later synchronization  
**Owner:** User  
**TTL:** 30 days  
**Invalidation:** Manual delete, successful sync  
**Security:** MEDIUM (potentially sensitive data)

| Field | Type | Description |
|-------|------|-------------|
| id | string | UUID |
| module | string | Module name (admissions, attendance, etc.) |
| form_type | string | Type of form |
| form_data | object | Form field data |
| created_at | timestamp | Draft creation time |
| updated_at | timestamp | Last update time |
| user_id | integer | User who created draft |
| status | string | Draft status (draft, ready_to_sync, synced) |
| sync_attempt_count | integer | Number of sync attempts |
| last_sync_error | string | Last sync error message |
| metadata | object | Additional metadata |

**Eligible Draft Types:**
- Admission intake forms
- Admission guardian forms
- Previous school details
- Attendance marking drafts
- Activity participation drafts
- Inventory count drafts
- Boarding roll call drafts

**Not Eligible (High-Risk):**
- Payment forms
- Payroll forms
- Final approval forms
- Student deletion forms
- Fee adjustment forms
- Examination publication forms

### Pending Mutations

#### Store: `sync_outbox`
**Purpose:** Offline operation queue for synchronization  
**Owner:** System  
**TTL:** 7 days (failed operations)  
**Invalidation:** Successful sync, manual delete  
**Security:** HIGH (contains mutation data)

| Field | Type | Description |
|-------|------|-------------|
| id | string | UUID |
| operation_id | string | Unique operation identifier |
| module | string | Module name |
| endpoint | string | API endpoint |
| method | string | HTTP method (POST, PUT, PATCH, DELETE) |
| payload | object | Request payload |
| entity_type | string | Type of entity being modified |
| entity_id | integer | ID of entity being modified |
| created_at | timestamp | Operation creation time |
| updated_at | timestamp | Last update time |
| retry_count | integer | Number of retry attempts |
| last_error | string | Last error message |
| status | string | Status (pending, processing, success, failed) |
| user_id | integer | User who initiated operation |
| school_id | integer | School ID (multi-tenant) |
| idempotency_key | string | Idempotency key for deduplication |
| dependency_ids | array | IDs of operations this depends on |
| priority | integer | Operation priority (higher = more important) |

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

### Sync Conflicts

#### Store: `sync_conflicts`
**Purpose:** Conflict resolution data for user-mediated resolution  
**Owner:** System  
**TTL:** 30 days  
**Invalidation:** User resolution, manual delete  
**Security:** MEDIUM (contains conflicting data)

| Field | Type | Description |
|-------|------|-------------|
| id | string | UUID |
| operation_id | string | Related operation ID |
| entity_type | string | Type of entity with conflict |
| entity_id | integer | ID of entity with conflict |
| server_version | object | Server version of data |
| local_version | object | Local version of data |
| conflict_type | string | Type of conflict (version, field, delete) |
| detected_at | timestamp | Conflict detection time |
| resolved_at | timestamp | Conflict resolution time (null if unresolved) |
| resolution | string | Resolution action (merge, discard_local, reapply) |
| user_id | integer | User who must resolve |
| metadata | object | Additional conflict metadata |

### Notifications

#### Store: `notification_inbox`
**Purpose:** Cached notification data for offline viewing  
**Owner:** System  
**TTL:** 7 days  
**Invalidation:** Notification read, notification expire  
**Security:** LOW (notification data)

| Field | Type | Description |
|-------|------|-------------|
| id | string | Notification ID |
| type | string | Notification type |
| title | string | Notification title |
| message | string | Notification message |
| data | object | Notification data |
| read | boolean | Read status |
| created_at | timestamp | Notification creation time |
| expires_at | timestamp | Notification expiry time |
| user_id | integer | User who should receive |
| priority | string | Notification priority (low, normal, high, urgent) |

### Dashboard Cache

#### Store: `dashboard_cache`
**Purpose:** Cached dashboard summaries for offline viewing  
**Owner:** System  
**TTL:** 15 minutes  
**Invalidation:** Manual refresh, data change  
**Security:** LOW (aggregated data)

| Field | Type | Description |
|-------|------|-------------|
| id | string | Cache key (dashboard_type + user_id) |
| dashboard_type | string | Type of dashboard |
| user_id | integer | User who owns dashboard |
| data | object | Dashboard summary data |
| cached_at | timestamp | Cache timestamp |
| expires_at | timestamp | Expiry timestamp |
| role_id | integer | User role when cached |

### File Upload Queue

#### Store: `pending_uploads`
**Purpose:** Metadata for files pending upload  
**Owner:** User  
**TTL:** 7 days  
**Invalidation:** Successful upload, manual delete  
**Security:** MEDIUM (file metadata)

| Field | Type | Description |
|-------|------|-------------|
| id | string | UUID |
| file_name | string | Original file name |
| file_type | string | MIME type |
| file_size | integer | File size in bytes |
| file_blob | Blob | File data (stored in separate store if large) |
| upload_url | string | Upload endpoint |
| metadata | object | Upload metadata |
| created_at | timestamp | Queue creation time |
| retry_count | integer | Number of upload attempts |
| status | string | Status (pending, uploading, success, failed) |
| user_id | integer | User who initiated upload |
| entity_type | string | Type of entity file belongs to |
| entity_id | integer | ID of entity file belongs to |

## Cache Storage Ownership Matrix

### Static Application Shell

**Cache Name:** `kingsway-static-v1`  
**Purpose:** Immutable application assets  
**Strategy:** Cache First  
**Invalidation:** Version update

| Asset Type | Pattern | TTL | Strategy |
|------------|--------|-----|----------|
| CSS | `/css/*.css` | Permanent | Cache First |
| JavaScript | `/js/*.js` | Permanent | Cache First |
| Icons | `/images/icons/*` | Permanent | Cache First |
| Fonts | `/fonts/*` | Permanent | Cache First |
| School Logo | `/images/logo*` | 7 days | Cache First |
| Manifest | `/manifest.webmanifest` | Permanent | Cache First |

### API Cache

**Cache Name:** `kingsway-api-v1`  
**Purpose:** Safe GET API responses  
**Strategy:** Stale While Revalidate  
**Invalidation:** Manual or time-based

| Endpoint | Data Type | TTL | Strategy | Invalidation Event |
|----------|-----------|-----|----------|-------------------|
| `/api/classes` | Reference data | 24 hours | Stale While Revalidate | Class create/update/delete |
| `/api/streams` | Reference data | 24 hours | Stale While Revalidate | Stream create/update/delete |
| `/api/subjects` | Reference data | 24 hours | Stale While Revalidate | Subject create/update/delete |
| `/api/terms` | Reference data | 24 hours | Stale While Revalidate | Term create/update/delete |
| `/api/academic-years` | Reference data | 7 days | Stale While Revalidate | Academic year create/update/delete |
| `/api/departments` | Reference data | 7 days | Stale While Revalidate | Department create/update/delete |
| `/api/dormitories` | Reference data | 24 hours | Stale While Revalidate | Dormitory create/update/delete |
| `/api/transport-routes` | Reference data | 24 hours | Stale While Revalidate | Route create/update/delete |
| `/api/activity-types` | Reference data | 7 days | Stale While Revalidate | Activity type create/update/delete |
| `/api/school/profile` | School config | 1 hour | Stale While Revalidate | Config change |

**Never Cache:**
- `/api/auth/*` (authentication endpoints)
- `/api/payments/*` (payment endpoints)
- `/api/payroll/*` (payroll endpoints)
- `/api/students` (student lists - use IndexedDB instead)
- `/api/admissions` (admission queues - use IndexedDB instead)
- POST/PATCH/DELETE requests

## Security Classification

### HIGH Security Data
**Requirements:** Server-side only, never in browser storage  
**Examples:**
- Passwords
- Payment credentials
- Full medical records
- Counseling notes
- Financial secrets
- API secrets
- Authentication tokens (should be HttpOnly cookies)

### MEDIUM Security Data
**Requirements:** Encrypted in browser storage, user-scoped only  
**Examples:**
- Student PII (names, addresses, contacts)
- Staff PII (names, addresses, contacts)
- Offline drafts with sensitive data
- Pending mutations
- Sync conflicts
- File upload metadata

### LOW Security Data
**Requirements:** Standard browser storage acceptable  
**Examples:**
- User preferences
- Reference metadata (classes, streams, subjects)
- Aggregated dashboard data
- Notifications
- Navigation state
- Search state

## Cache Invalidation Policies

### Automatic Invalidation

| Event | Storage | Action |
|-------|---------|--------|
| User logout | All IndexedDB | Clear all user-scoped data |
| Role change | All IndexedDB | Clear permission-scoped caches |
| Permission change | All IndexedDB | Clear permission-scoped caches |
| Class create/update/delete | `reference_classes`, `class_list_cache` | Invalidate related caches |
| Student create/update/delete | `student_directory_cache`, `class_list_cache` | Invalidate related caches |
| Admission workflow transition | `admission_queue_cache` | Invalidate queue cache |
| Configuration change | `reference_*` stores, API cache | Invalidate reference caches |

### Manual Invalidation

User can manually clear:
- All cached data
- Specific module caches
- Offline drafts
- Pending operations

### Time-Based Invalidation

Each cache entry has TTL-based expiry:
- Reference data: 24 hours
- Read models: 5-30 minutes
- Drafts: 30 days
- Pending operations: 7 days (failed)
- Notifications: 7 days

## Data Ownership Rules

### User Ownership
- User preferences
- Offline drafts
- Notification read status
- Navigation state

### System Ownership
- Reference metadata
- Cached read models
- Sync queue
- Cache invalidation
- Dashboard cache

### Shared Ownership
- Conflict resolution (user resolves, system manages)
- File uploads (user initiates, system manages)

## Cleanup Policies

### Automatic Cleanup
- Expired cache entries (time-based)
- Successful sync operations
- Read notifications older than 7 days
- Failed operations older than 30 days

### Manual Cleanup
- User request via settings
- Logout action
- Storage quota exceeded (LRU eviction)

### Cleanup Priority
1. High-risk failed operations (delete immediately)
2. Expired cache entries (delete on access)
3. Old notifications (delete on logout)
4. Old drafts (warn user before delete)
5. Reference cache (keep until version update)

## Storage Quota Management

### Monitoring
- Use `navigator.storage.estimate()` to check usage
- Alert user at 80% capacity
- Prevent writes at 95% capacity

### Eviction Strategy
1. Delete expired cache entries
2. Delete old notifications
3. Delete old failed operations
4. Compress draft data
5. Ask user to clear data

### Quota Allocation
- Reference data: 30%
- Read models: 40%
- Drafts: 15%
- Sync queue: 10%
- Other: 5%

## Migration Strategy

### Phase 1: Foundation
- Implement localStorage/sessionStorage policies
- Add key prefixing
- Add TTL tracking

### Phase 2: IndexedDB
- Create database structure
- Implement reference metadata stores
- Add cache invalidation

### Phase 3: Offline Support
- Implement offline drafts
- Implement sync queue
- Add conflict resolution

### Phase 4: Cache Storage
- Implement service worker
- Add static asset caching
- Add API response caching

## Compliance Requirements

### GDPR Compliance
- User consent for data storage
- Right to deletion (clear all data)
- Data minimization (store only what's needed)
- Purpose limitation (clear documented purposes)

### Data Protection
- Encrypt sensitive data at rest
- Secure transmission (HTTPS)
- Access control (user-scoped data)
- Audit logging (storage operations)

### Performance Requirements
- Cache hit rate >80%
- Storage operations <100ms
- Sync success rate >99%
- No data loss during sync

## Success Metrics

### Storage Efficiency
- Quota usage <50%
- Cache hit rate >80%
- Expired data <5%

### Data Integrity
- Sync success rate >99%
- Conflict rate <1%
- Data loss rate <0.1%

### User Experience
- Offline availability for key features
- Seamless sync on reconnect
- Clear indication of offline status
- Easy cache management

## Conclusion

This ownership matrix provides clear guidelines for what data should be stored where, with explicit ownership, TTL, invalidation, and security policies. Following this matrix ensures data consistency, security, and performance while maintaining the principle that the server database remains the authoritative source.

**Next Phase:** Implement IndexedDB schema and storage manager based on this matrix.
