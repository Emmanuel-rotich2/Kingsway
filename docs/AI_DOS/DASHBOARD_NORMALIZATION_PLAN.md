# Dashboard/Layout/Navigation Module - Normalization Plan

## Problem Summary

**Two conflicting dashboard routing systems exist:**

| Aspect | PHP Router (`config/DashboardRouter.php`) | JS Router (`js/dashboards/dashboard_router.js`) |
|--------|-------------------------------------------|-------------------------------------------------|
| Mappings | 37 role_id mappings | 28 role_id mappings |
| Used by | `home.php` (initial load), `layouts/app_layout.php` | `js/index.js` → `DashboardRouter.routeToDashboard()` |
| Data source | Hardcoded array | Hardcoded array (different values) |
| Accountant sub-roles | Single: role 10 → school_accountant_dashboard | 4 roles: 10, 11, 12, 13, 14 |
| Teacher roles | Separate: 7→class_teacher, 8→subject_teacher | Unified: 7,8,9→teacher_dashboard |

**5 Broken Sidebar URLs** (from `config/role_sidebars.php`):
- `admissions/director_admissions`
- `admissions/enrollment_confirmations`
- `leave_requests`
- `student_discipline`
- `submit_attendance`

---

## Normalization Strategy: PHP Router as Canonical Source

### Phase 1: Fix Broken Sidebar URLs (Immediate)
Remap broken URLs in `config/role_sidebars.php` to existing pages:

| Broken URL | Remap To | Rationale |
|------------|----------|-----------|
| `admissions/director_admissions` | `manage_students_admissions` | Existing admissions management page |
| `admissions/enrollment_confirmations` | `admission_status` | Shows enrollment confirmations |
| `leave_requests` | `staff_attendance` | Leave requests handled in attendance |
| `student_discipline` | `discipline_cases` | Existing discipline management |
| `submit_attendance` | `mark_attendance` | Existing attendance marking page |

### Phase 2: Expose PHP Router via API
Create `api/modules/dashboard/DashboardAPI.php` with endpoint:
- `GET /api/dashboard/config` → returns PHP `ROLE_DASHBOARDS` map
- `GET /api/dashboard/route?role_id=X` → returns dashboard key for role

### Phase 3: Update JS Router to Consume PHP Config
- Remove hardcoded `ROLE_DASHBOARD_MAP` from `dashboard_router.js`
- Fetch config from `/api/dashboard/config` on init
- Cache in `sessionStorage` with version check
- Fallback to hardcoded map if API fails

### Phase 4: Align Dashboard File Naming
Ensure PHP dashboard key matches JS controller file:
| Role | PHP Dashboard Key | JS File | Action |
|------|-------------------|---------|--------|
| 7 Class Teacher | class_teacher_dashboard | teacher_dashboard.js | Rename JS or add alias |
| 8 Subject Teacher | subject_teacher_dashboard | teacher_dashboard.js | Rename JS or add alias |
| 10 Accountant | school_accountant_dashboard | school_accountant_dashboard.js | ✅ Match |

### Phase 5: Remove JS Router Role Map
Delete `ROLE_DASHBOARD_MAP` from `dashboard_router.js`, keep only:
- `getCurrentUserRoles()`
- `getPrimaryRole()` (hierarchy logic)
- `loadDashboardScript()`
- `routeToDashboard()` (now fetches config from API)
- `addRoleSwitcher()`

---

## Implementation Order

1. **Fix broken sidebar URLs** → `config/role_sidebars.php` (5 changes)
2. **Create DashboardAPI** → `api/modules/dashboard/DashboardAPI.php` + route in `api/router/ControllerRouter.php`
3. **Update JS router** → `js/dashboards/dashboard_router.js` (fetch from API)
4. **Align naming** → Rename JS files or add controller aliases
5. **Verify all 20 roles** load correct dashboard with real data

---

## Testing Checklist

- [ ] All 5 broken sidebar URLs resolved
- [ ] `GET /api/dashboard/config` returns PHP router map
- [ ] JS router loads config from API (check network tab)
- [ ] Each of 20 roles loads correct dashboard partial
- [ ] Multi-role users see role switcher with working dashboards
- [ ] System Admin dashboard shows real API data (not fallback)
- [ ] No 404s for dashboard partials
- [ ] Sidebar matches `role_sidebars.php` for each role