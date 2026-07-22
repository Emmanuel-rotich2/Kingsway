KINGSWAY CACHE/BACKEND COORDINATION FIX

This package corrects cache keys, backend endpoints, and failure fallback behavior throughout the JavaScript folder.

CORE CHANGES
- js/core/data_store.js
  - Cache keys are never converted into API URLs.
  - Added cache-only peek().
  - Added getOrFetch() with explicit endpoint/fetcher requirements.
  - Added request de-duplication.
  - Stale cache is retained when network revalidation fails.
  - Background revalidation only runs when an endpoint or fetcher exists.
  - Removed guessed fallback endpoint generation.

- js/api.js
  - Normalizes accidental /api-prefixed endpoints to avoid /api/api paths.
  - School-admin aggregate endpoint uses a 404 circuit breaker for the page session.
  - Removed duplicate DataStore writes from the API method.

- js/dashboards/school_administrative_officer_dashboard.js
  - Reads cache using DataStore.peek() without causing a network request.
  - Revalidates from the backend once.
  - Falls back to individual real dashboard endpoints when the aggregate route is unavailable.
  - Keeps valid cached data visible if all live requests fail.
  - Does not overwrite a valid cache with zero placeholder data after a network error.

- js/core/connectivity_manager.js
  - Removed the non-existent /api/health/connectivity dependency.
  - Uses a deduplicated HEAD reachability probe against APP_BASE.
  - 401/403/404 prove server reachability and are not treated as offline.
  - 60-second interval and no overlapping probes.
  - Never refreshes or clears authentication.

- js/utils/storage_manager.js
  - Replaces an incompatible pre-existing global instance when required.
  - Guarantees initialize() exists.
  - Never clears authentication keys.

- js/core/speculative_loader.js
  - Uses DataStore.peek() for cache-only checks.
  - Corrected /api/students to /students.

- js/pages/mark_attendance.js
  - Separates endpoint and query params to avoid duplicated query strings.

GLOBAL NORMALIZATION
- Explicit DataStore endpoint options beginning with /api/ were normalized because api.js already owns the /api base.

VALIDATION
- Every JavaScript file was checked with node --check.
- No JavaScript syntax errors remain.

BACKEND NOTE
The package no longer requires GET /api/health/connectivity.
The school-admin aggregate route may remain absent: the dashboard now stops retrying after a confirmed 404 and uses the individual dashboard methods.
