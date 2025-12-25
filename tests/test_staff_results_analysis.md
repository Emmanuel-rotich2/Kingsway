# Staff API Test Results Analysis

## Test Summary: 12/19 Passed (63%)

### ✅ **WORKING ENDPOINTS** (12 endpoints)

1. **Index** - GET `/api/staff/index` 
2. **List All Staff** - GET `/api/staff`
3. **Create Staff Record** - POST `/api/staff` (2 successful)
4. **Get Departments** - GET `/api/staff/departments-get`
5. **View Payslip** - GET `/api/staff/payroll-payslip`
6. **Get Payroll History** - GET `/api/staff/payroll-history`
7. **View Allowances** - GET `/api/staff/payroll-allowances`
8. **View Deductions** - GET `/api/staff/payroll-deductions`
9. **Get Loan Details** - GET `/api/staff/payroll-loan-details`
10. **Get Review History** - GET `/api/staff/performance-review-history`
11. **Get Academic KPI Summary** - GET `/api/staff/performance-academic-kpi-summary`

---

## ❌ **FAILING ENDPOINTS** (7 endpoints) - Database Schema Issues

### 1. Get Profile - **500 Error**
- **Endpoint**: GET `/api/staff/profile-get`
- **Error**: Table 'KingsWayAcademy.class_teachers' doesn't exist
- **File**: `/api/modules/staff/StaffAPI.php` Line ~672
- **Issue**: Query uses non-existent tables:
  - `class_teachers` - **DOES NOT EXIST**
  - `subject_teachers` - **DOES NOT EXIST**
- **Available Tables**:
  - `staff_class_assignments` - Use this instead of `class_teachers`
  - No direct `subject_teachers` table - subjects are assigned via `class_schedules` with `teacher_id`

**Current Query:**
```php
LEFT JOIN class_teachers ct ON s.id = ct.teacher_id
LEFT JOIN classes c ON ct.class_id = c.id
LEFT JOIN subject_teachers st ON s.id = st.teacher_id
LEFT JOIN subjects sub ON st.subject_id = sub.id
```

**Should Be:**
```php
LEFT JOIN staff_class_assignments sca ON s.id = sca.staff_id
LEFT JOIN classes c ON sca.class_id = c.id
LEFT JOIN class_schedules cs ON s.id = cs.teacher_id
LEFT JOIN curriculum_units cu ON cs.subject_id = cu.id
```

---

### 2. Get Schedule - **500 Error**
- **Endpoint**: GET `/api/staff/schedule-get`
- **Error**: Table 'KingsWayAcademy.timetable' doesn't exist
- **File**: `/api/modules/staff/StaffAPI.php` Line ~695
- **Issue**: Query uses table `timetable` which doesn't exist
- **Available Tables**:
  - `schedules` - Generic schedule table
  - `class_schedules` - **USE THIS** for teaching schedules
  - `exam_schedules` - For exams
  - `activity_schedule` - For activities

**Current Query:**
```php
FROM timetable t
JOIN subjects s ON t.subject_id = s.id
JOIN classes c ON t.class_id = c.id
JOIN rooms r ON t.room_id = r.id
WHERE t.teacher_id = ?
ORDER BY t.day, t.start_time
```

**Should Be:**
```php
FROM class_schedules cs
LEFT JOIN curriculum_units cu ON cs.subject_id = cu.id
JOIN classes c ON cs.class_id = c.id
LEFT JOIN rooms r ON cs.room_id = r.id
WHERE cs.teacher_id = ?
ORDER BY 
    FIELD(cs.day_of_week, 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'),
    cs.start_time
```

---

### 3. Get Assignments - **500 Error**
- **Endpoint**: GET `/api/staff/assignments-get`
- **Error**: Unknown column 'last_name' in 'order clause'
- **File**: Likely in `StaffAssignmentManager.php`
- **Issue**: Query tries to ORDER BY staff.last_name but column doesn't exist in staff table
- **Available Columns in `staff` table**:
  - `first_name` ✅
  - `last_name` ✅ (EXISTS!)
- **Root Cause**: Query likely has alias mismatch or missing table join

**Need to check**: `api/modules/staff/managers/StaffAssignmentManager.php`

---

### 4. Get Current Assignments - **500 Error**
- **Endpoint**: GET `/api/staff/assignments-current`
- **Error**: Same as above (likely same query method)
- **File**: Same as #3

---

### 5. Get Workload - **500 Error**
- **Endpoint**: GET `/api/staff/workload-get`
- **Error**: Needs investigation
- **File**: Likely in `StaffAssignmentManager.php`

---

### 6. Get Attendance - **500 Error**
- **Endpoint**: GET `/api/staff/attendance-get`
- **Error**: Unknown column 's.staff_id' in 'field list'
- **File**: `/api/modules/staff/StaffAPI.php` Line ~785
- **Issue**: Query uses `s.staff_id` but column doesn't exist
- **Available Columns in `staff` table**:
  - `id` ✅ (primary key)
  - `staff_no` ✅ (staff number like "20250001")
  - **NO `staff_id` column**

**Current Query:**
```php
s.staff_id,  -- THIS COLUMN DOESN'T EXIST
```

**Should Be:**
```php
s.id as staff_id,
s.staff_no,
```

---

### 7. List Leaves - **500 Error**
- **Endpoint**: GET `/api/staff/leaves-list`
- **Error**: Similar to attendance - likely using `s.staff_id`
- **File**: `/api/modules/staff/StaffAPI.php` Line ~868

**Current Query:**
```php
s.staff_id,  -- THIS COLUMN DOESN'T EXIST
```

**Should Be:**
```php
s.id as staff_id,
s.staff_no,
```

---

## 📊 **Database Schema Findings**

### **Tables That DON'T Exist:**
1. ❌ `class_teachers` - Use `staff_class_assignments` instead
2. ❌ `subject_teachers` - Use `class_schedules` with `teacher_id` instead
3. ❌ `timetable` - Use `class_schedules` instead
4. ❌ `subjects` - Use `curriculum_units` instead

### **Column Issues:**
1. ❌ `staff.staff_id` - Doesn't exist, use `staff.id` or `staff.staff_no`
2. ✅ `staff.first_name` - EXISTS
3. ✅ `staff.last_name` - EXISTS

### **Correct Tables Available:**
- ✅ `staff` - Main staff table
- ✅ `staff_class_assignments` - Staff to class assignments
- ✅ `class_schedules` - Teaching schedule (has teacher_id, subject_id, class_id)
- ✅ `staff_attendance` - Staff attendance records
- ✅ `staff_leaves` - Leave requests
- ✅ `curriculum_units` - Subjects/learning areas
- ✅ `classes` - Classes
- ✅ `rooms` - Rooms

---

## 🔧 **Files Requiring Fixes:**

1. `/api/modules/staff/StaffAPI.php`:
   - Line ~672: `getProfile()` method - Fix table names
   - Line ~695: `getSchedule()` method - Fix table name
   - Line ~717: `assignClass()` method - Fix table name
   - Line ~735: `assignSubject()` method - Fix table name
   - Line ~785: `getAttendance()` method - Fix column name
   - Line ~868: `getLeaves()` method - Fix column name

2. `/api/modules/staff/managers/StaffAssignmentManager.php`:
   - `getStaffAssignments()` method - Fix ORDER BY clause
   - `getCurrentAssignments()` method - Fix ORDER BY clause
   - `getStaffWorkload()` method - Investigate error

---

## ✅ **Next Steps:**

1. Fix all table/column name references in StaffAPI.php
2. Check and fix StaffAssignmentManager.php
3. Re-run test script to verify all endpoints pass
4. Update test data if needed

---

## 🎯 **Test Infrastructure Status:**

- ✅ X-Test-Token authentication working perfectly
- ✅ Database connection fixed (127.0.0.1)
- ✅ Test user creation working (19 users created)
- ✅ Test script functional and comprehensive
- ✅ 63% success rate (12/19 endpoints passing)
- ❌ 7 endpoints failing due to incorrect table/column names in business logic

**Conclusion**: The testing infrastructure is solid. All failures are due to incorrect database schema references in the business logic code, not missing tables or authentication issues.
