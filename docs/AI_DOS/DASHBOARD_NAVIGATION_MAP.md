# Dashboard & Navigation Map

**Generated:** 2025-06-24  
**Purpose:** Map all role sidebar URLs to existing pages/dashboards, identify broken links, document dual routing systems

---

## 1. Dual Dashboard Routing Systems (CRITICAL INCONSISTENCY)

### PHP Router: `config/DashboardRouter.php`
- **37 role_id mappings** to dashboard file keys
- Used by: `home.php`, `layouts/app_layout.php`
- File-based lookup: `components/dashboards/{key}.php`

| Role ID | Role Name | Dashboard Key | Dashboard File |
|---------|-----------|---------------|----------------|
| 2 | System Administrator | system_administrator_dashboard | system_administrator_dashboard.php ✅ |
| 3 | Director/Owner | director_owner_dashboard | director_owner_dashboard.php ✅ |
| 4 | School Administrator | school_administrative_officer_dashboard | school_administrative_officer_dashboard.php ✅ |
| 5 | Headteacher | headteacher_dashboard | headteacher_dashboard.php ✅ |
| 6 | Deputy Head Academic | deputy_head_academic_dashboard | deputy_head_academic_dashboard.php ✅ |
| 7 | Class Teacher | class_teacher_dashboard | class_teacher_dashboard.php ✅ |
| 8 | Subject Teacher | subject_teacher_dashboard | subject_teacher_dashboard.php ✅ |
| 9 | Intern/Student Teacher | intern_student_teacher_dashboard | intern_student_teacher_dashboard.php ✅ |
| 10 | School Accountant | school_accountant_dashboard | school_accountant_dashboard.php ✅ |
| 14 | Store Manager | store_manager_dashboard | store_manager_dashboard.php ✅ |
| 16 | Catering Manager/Cook Lead | catering_manager_cook_lead_dashboard | catering_manager_cook_lead_dashboard.php ✅ |
| 18 | Matron/Housemother | matron_housemother_dashboard | matron_housemother_dashboard.php ✅ |
| 21 | HOD Talent Development | hod_talent_development_dashboard | hod_talent_development_dashboard.php ✅ |
| 23 | Driver | driver_dashboard | driver_dashboard.php ✅ |
| 24 | School Counselor/Chaplain | school_counselor_chaplain_dashboard | school_counselor_chaplain_dashboard.php ✅ |
| 32 | Support Staff | support_staff_dashboard | support_staff_dashboard.php ✅ |
| 33 | Support Staff | support_staff_dashboard | support_staff_dashboard.php ✅ |
| 34 | Support Staff | support_staff_dashboard | support_staff_dashboard.php ✅ |
| 63 | Deputy Head Discipline | deputy_head_discipline_dashboard | deputy_head_discipline_dashboard.php ✅ |
| 64 | Support Staff | support_staff_dashboard | support_staff_dashboard.php ✅ |

**Default fallback:** `headteacher_dashboard`

**Missing role_ids from role_sidebars.php that lack PHP dashboard mapping:**
- Role IDs in sidebar but NOT in PHP router: 21 (HOD Talent), 23 (Driver), 24 (Counselor) - actually mapped above
- **Actually mapped:** All 20 sidebar roles have PHP dashboard mappings ✅

---

### JS Router: `js/dashboards/dashboard_router.js`
- **28 role_id mappings** to JS controllers
- Used by: `js/index.js` → `DashboardRouter.loadDashboardForCurrentUser()`
- Dynamic script loading: `js/dashboards/{file}.js`

| Role ID | Role Name | JS Controller | JS File |
|---------|-----------|---------------|---------|
| 2 | System Administrator | sysAdminDashboardController | system_administrator_dashboard.js ✅ |
| 3 | Director | directorDashboardController | director_dashboard.js ✅ |
| 4 | School Administrator | schoolAdminDashboardController | school_administrative_officer_dashboard.js ✅ |
| 5 | Headteacher | headteacherDashboardController | headteacher_dashboard.js ✅ |
| 6 | Deputy Head Academic | deputyAcademicDashboard | deputy_head_academic_dashboard.js ✅ |
| 7 | Class Teacher | teacherDashboardController | teacher_dashboard.js ⚠️ |
| 8 | Subject Teacher | teacherDashboardController | teacher_dashboard.js ⚠️ |
| 9 | Intern/Student Teacher | internStudentTeacherDashboard | intern_student_teacher_dashboard.js ✅ |
| 10 | Accountant | accountantDashboardController | school_accountant_dashboard.js ⚠️ |
| 11 | Accountant (M-Pesa) | accountantMpesaController | accountant_mpesa_dashboard.js |
| 12 | Accountant (Assets) | accountantAssetsController | accountant_assets_dashboard.js |
| 13 | Accountant (Vendors) | accountantVendorsController | accountant_vendors_dashboard.js |
| 14 | Accountant (Controls) | accountantControlsController | accountant_controls_dashboard.js |
| 14 | Store Manager | storeManagerDashboardController | store_manager_dashboard.js ✅ |
| 16 | Catering Manager | cateringManagerCookLeadDashboard | catering_manager_cook_lead_dashboard.js ✅ |
| 18 | Matron/Housemother | matronHousemotherDashboard | matron_housemother_dashboard.js ✅ |
| 21 | HOD Talent Development | hodTalentDevelopmentDashboard | hod_talent_development_dashboard.js ✅ |
| 23 | Driver | driverDashboard | driver_dashboard.js ✅ |
| 24 | School Counselor/Chaplain | schoolCounselorChaplainDashboard | school_counselor_chaplain_dashboard.js ✅ |
| 32 | Support Staff | supportStaffDashboard | support_staff_dashboard.js ✅ |
| 33 | Support Staff | supportStaffDashboard | support_staff_dashboard.js ✅ |
| 34 | Support Staff | supportStaffDashboard | support_staff_dashboard.js ✅ |
| 63 | Deputy Head Discipline | deputyHeadDisciplineDashboard | deputy_head_discipline_dashboard.js ✅ |
| 64 | Support Staff | supportStaffDashboard | support_staff_dashboard.js ✅ |

**⚠️ MISMATCHES:**
| Role ID | PHP Dashboard | JS Dashboard | Issue |
|---------|---------------|--------------|-------|
| 7 | class_teacher_dashboard.php | teacher_dashboard.js | Different files |
| 8 | subject_teacher_dashboard.php | teacher_dashboard.js | Different files |
| 10 | school_accountant_dashboard.php | school_accountant_dashboard.js | Different files (PHP vs JS file naming) |
| 32-34,64 | support_staff_dashboard.php | support_staff_dashboard.js | Same base name, OK |

---

## 2. Sidebar URLs vs Existing Pages/Dashboards

### 5 BROKEN URLs (exist in role_sidebars.php but NO page/dashboard exists)

| Broken URL | Referenced By Roles | Suggested Fix |
|------------|---------------------|---------------|
| `admissions/director_admissions` | Director (3) | Create `pages/admissions/director_admissions.php` or map to `manage_students_admissions` |
| `admissions/enrollment_confirmations` | Director (3), School Admin (4) | Create page or map to `admission_status` |
| `leave_requests` | School Admin (4), Headteacher (5), Deputy Academic (6), Deputy Discipline (63) | Create `pages/leave_requests.php` or map to `staff_attendance` |
| `student_discipline` | Deputy Discipline (63), Boarding Master (18), Class Teacher (7) | Create `pages/student_discipline.php` or map to `discipline_cases` |
| `submit_attendance` | Class Teacher (7), Subject Teacher (8) | Create page or map to `mark_attendance` |

---

## 3. Dashboard Files Existence Check

### PHP Dashboards (22 files in `components/dashboards/`)
| Dashboard File | Role(s) Mapped | Exists | Loads JS Controller |
|----------------|----------------|--------|---------------------|
| system_administrator_dashboard.php | 2 | ✅ | system_administrator_dashboard.js |
| director_owner_dashboard.php | 3 | ✅ | director_dashboard.js |
| school_administrative_officer_dashboard.php | 4 | ✅ | school_administrative_officer_dashboard.js |
| headteacher_dashboard.php | 5 | ✅ | headteacher_dashboard.js |
| deputy_head_academic_dashboard.php | 6 | ✅ | deputy_head_academic_dashboard.js |
| class_teacher_dashboard.php | 7 | ✅ | class_teacher_dashboard.js |
| subject_teacher_dashboard.php | 8 | ✅ | subject_teacher_dashboard.js |
| intern_student_teacher_dashboard.php | 9 | ✅ | intern_student_teacher_dashboard.js |
| school_accountant_dashboard.php | 10 | ✅ | school_accountant_dashboard.js |
| store_manager_dashboard.php | 14 | ✅ | store_manager_dashboard.js |
| catering_manager_cook_lead_dashboard.php | 16 | ✅ | catering_manager_cook_lead_dashboard.js |
| matron_housemother_dashboard.php | 18 | ✅ | matron_housemother_dashboard.js |
| hod_talent_development_dashboard.php | 21 | ✅ | hod_talent_development_dashboard.js |
| driver_dashboard.php | 23 | ✅ | driver_dashboard.js |
| school_counselor_chaplain_dashboard.php | 24 | ✅ | school_counselor_chaplain_dashboard.js |
| support_staff_dashboard.php | 32,33,34,64 | ✅ | support_staff_dashboard.js |
| deputy_head_discipline_dashboard.php | 63 | ✅ | deputy_head_discipline_dashboard.js |
| teacher_dashboard.php | (none in PHP router) | ✅ | teacher_dashboard.js |
| accountant_mpesa.php | (Accountant sub-dashboard) | ✅ | accountant_mpesa_dashboard.js |
| accountant_assets.php | (Accountant sub-dashboard) | ✅ | accountant_assets_dashboard.js |
| accountant_vendors.php | (Accountant sub-dashboard) | ✅ | accountant_vendors_dashboard.js |
| accountant_controls.php | (Accountant sub-dashboard) | ✅ | accountant_controls_dashboard.js |

### JS Dashboard Controllers (22 files in `js/dashboards/`)
| JS File | Matches PHP Dashboard? |
|---------|------------------------|
| system_administrator_dashboard.js | ✅ system_administrator_dashboard.php |
| director_dashboard.js | ✅ director_owner_dashboard.php |
| school_administrative_officer_dashboard.js | ✅ school_administrative_officer_dashboard.php |
| headteacher_dashboard.js | ✅ headteacher_dashboard.js |
| deputy_head_academic_dashboard.js | ✅ deputy_head_academic_dashboard.php |
| class_teacher_dashboard.js | ✅ class_teacher_dashboard.php |
| subject_teacher_dashboard.js | ✅ subject_teacher_dashboard.php |
| intern_student_teacher_dashboard.js | ✅ intern_student_teacher_dashboard.php |
| school_accountant_dashboard.js | ✅ school_accountant_dashboard.php |
| store_manager_dashboard.js | ✅ store_manager_dashboard.php |
| catering_manager_cook_lead_dashboard.js | ✅ catering_manager_cook_lead_dashboard.php |
| matron_housemother_dashboard.js | ✅ matron_housemother_dashboard.php |
| hod_talent_development_dashboard.js | ✅ hod_talent_development_dashboard.php |
| driver_dashboard.js | ✅ driver_dashboard.php |
| school_counselor_chaplain_dashboard.js | ✅ school_counselor_chaplain_dashboard.php |
| support_staff_dashboard.js | ✅ support_staff_dashboard.php |
| deputy_head_discipline_dashboard.js | ✅ deputy_head_discipline_dashboard.php |
| teacher_dashboard.js | ⚠️ teacher_dashboard.php (no PHP router mapping) |
| accountant_mpesa_dashboard.js | ✅ accountant_mpesa.php |
| accountant_assets_dashboard.js | ✅ accountant_assets.php |
| accountant_vendors_dashboard.js | ✅ accountant_vendors.php |
| accountant_controls_dashboard.js | ✅ accountant_controls.php |

---

## 4. Role Coverage Summary

### Roles in `role_sidebars.php` (20 roles)
| Role ID | Role | PHP Dashboard | JS Dashboard | Broken Sidebar URLs |
|---------|------|---------------|--------------|---------------------|
| 2 | System Administrator | ✅ | ✅ | 0 |
| 3 | Director/Owner | ✅ | ✅ | 2 (admissions/*) |
| 4 | School Administrator | ✅ | ✅ | 1 (admissions/enrollment_confirmations) |
| 5 | Headteacher | ✅ | ✅ | 1 (leave_requests) |
| 6 | Deputy Head Academic | ✅ | ✅ | 1 (leave_requests) |
| 7 | Class Teacher | ✅ | ✅* | 1 (submit_attendance) |
| 8 | Subject Teacher | ✅ | ✅* | 1 (submit_attendance) |
| 9 | Intern/Student Teacher | ✅ | ✅ | 0 |
| 10 | School Accountant | ✅ | ✅ | 0 |
| 14 | Store Manager | ✅ | ✅ | 0 |
| 16 | Catering Manager | ✅ | ✅ | 0 |
| 18 | Matron/Housemother | ✅ | ✅ | 0 |
| 21 | HOD Talent Development | ✅ | ✅ | 0 |
| 23 | Driver | ✅ | ✅ | 0 |
| 24 | School Counselor/Chaplain | ✅ | ✅ | 0 |
| 32-34,64 | Support Staff | ✅ | ✅ | 0 |
| 63 | Deputy Head Discipline | ✅ | ✅ | 2 (leave_requests, student_discipline) |

*JS router maps both 7 & 8 to `teacher_dashboard.js` (shared controller)

---

## 5. Recommendations

### Immediate Fixes (Broken Sidebar URLs)
1. **Create missing pages** or **remap sidebar URLs** for the 5 broken links
2. Suggested remappings:
   - `admissions/director_admissions` → `manage_students_admissions`
   - `admissions/enrollment_confirmations` → `admission_status`
   - `leave_requests` → `staff_attendance` (or create page)
   - `student_discipline` → `discipline_cases`
   - `submit_attendance` → `mark_attendance`

### Architecture Decision: Pick ONE Router
**Option A: PHP Router as canonical** (recommended)
- Server-side route validation via `DashboardRouter::dashboardExists()`
- Consistent with `RouteAuthorization` middleware
- Single source of truth in PHP config

**Option B: JS Router as canonical**
- Client-side role detection from JWT
- Dynamic loading but no server-side enforcement
- Requires duplicating role map in JS

### Normalization Steps
1. **Deprecate JS router's role map** → import from PHP config via API endpoint
2. **Align dashboard file naming** (PHP key = JS file basename)
3. **Merge accountant sub-dashboards** into single role with tabs (PHP already does this)
4. **Unify Class/Subject Teacher** → both use `teacher_dashboard.js` in JS, but separate PHP files

---

## 6. Test Checklist

- [ ] Every role in `role_sidebars.php` loads a dashboard via `home.php?route=loading`
- [ ] No 404 on dashboard partial load
- [ ] Sidebar renders with correct items for each role
- [ ] All sidebar `url` values resolve to existing page or dashboard
- [ ] JS `DashboardRouter.loadDashboardForCurrentUser()` matches PHP `DashboardRouter::getDashboardForRole()`
- [ ] Multi-role users see role switcher with working dashboards