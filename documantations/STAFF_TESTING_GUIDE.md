# Quick Testing Guide - Staff Management

## 🚀 BEFORE YOU START

1. **Ensure XAMPP is running:**
```bash
sudo /opt/lampp/lampp start
```

2. **Verify database connection:**
```bash
/opt/lampp/bin/mysql -u root -padmin123 KingsWayAcademy -e "SELECT COUNT(*) FROM staff;"
```

3. **Check if departments exist:**
```bash
/opt/lampp/bin/mysql -u root -padmin123 KingsWayAcademy -e "SELECT * FROM departments WHERE status='active';"
```

If no departments exist, add some:
```sql
INSERT INTO departments (name, code, status, created_at) VALUES
('Mathematics', 'MATH', 'active', NOW()),
('English', 'ENG', 'active', NOW()),
('Science', 'SCI', 'active', NOW()),
('Administration', 'ADMIN', 'active', NOW()),
('Support Services', 'SUPPORT', 'active', NOW());
```

---

## 📋 TEST CHECKLIST

### Test 1: Manage Staff Page
**URL:** `http://localhost/Kingsway/home.php?route=manage_staff`

#### Expected Behavior:
- [ ] Page loads without errors
- [ ] Statistics cards show numbers (Total Staff, Active Staff, Teaching Staff, Departments)
- [ ] Staff table displays with data (or "No staff found" if empty)
- [ ] Search box is visible
- [ ] Department filter dropdown is populated
- [ ] Status filter has Active/Inactive options
- [ ] "Add Staff" button is visible

#### Test Actions:
1. **Add New Staff:**
   - Click "Add Staff" button
   - Fill in form:
     - First Name: `John`
     - Last Name: `Doe`
     - Email: `john.doe@kingsway.edu`
     - Phone: `+254712345678`
     - Position: `Subject Teacher` (or select from dropdown)
     - Department: Select any
     - Employment Date: Use date picker
     - Status: Active
   - Click "Save Staff"
   - Expected: Success notification, modal closes, staff appears in table

2. **Edit Staff:**
   - Click edit button (pencil icon) on any row
   - Modify phone number
   - Click "Save Staff"
   - Expected: Success notification, changes reflected

3. **View Staff:**
   - Click view button (eye icon)
   - Expected: Modal shows staff details, fields are disabled

4. **Delete Staff:**
   - Click delete button (trash icon)
   - Confirm deletion
   - Expected: Confirmation prompt, staff removed from table

5. **Search:**
   - Type staff name in search box
   - Expected: Table filters in real-time

6. **Filter by Department:**
   - Select department from dropdown
   - Expected: Only staff from that department shown

---

### Test 2: Staff Attendance
**URL:** `http://localhost/Kingsway/home.php?route=staff_attendance`

#### Expected Behavior:
- [ ] Page loads without errors
- [ ] Date filters (From/To) are visible with default values
- [ ] Department filter is populated
- [ ] Duty Type filter has options (Teaching, Boarding, Gate, etc.)
- [ ] Status filter has options (Present, Absent, Late, Leave)
- [ ] Summary cards show "0" if no data
- [ ] "Mark Today" button is visible
- [ ] "Generate" button is visible

#### Test Actions:
1. **Mark Attendance:**
   - Click "Mark Today" button
   - Select staff member
   - Select date (today)
   - Select status: Present
   - Enter Time In: 08:00
   - Enter Time Out: 17:00
   - Select Duty Type: Teaching
   - Click "Mark Attendance"
   - Expected: Success notification, record appears in table

2. **View Attendance:**
   - Set date range
   - Click "Generate"
   - Expected: Attendance records load in table

3. **Filter by Department:**
   - Select department
   - Click "Generate"
   - Expected: Only attendance for that department

---

### Test 3: Manage Teachers
**URL:** `http://localhost/Kingsway/home.php?route=manage_teachers`

#### Expected Behavior:
- [ ] Page loads without errors
- [ ] Only teaching staff shown (if staff_type = 'teaching')
- [ ] "Add Teacher" button visible
- [ ] Position dropdown has teaching roles

#### Test Actions:
1. **Add Teacher:**
   - Click "Add Teacher"
   - Fill form with teaching-specific position
   - Save
   - Expected: New teacher appears, staff_type automatically set to 'teaching'

2. **Verify Filtering:**
   - Go back to "Manage Staff" page
   - Check that teacher appears there too

---

### Test 4: Manage Non-Teaching Staff
**URL:** `http://localhost/Kingsway/home.php?route=manage_non_teaching_staff`

#### Expected Behavior:
- [ ] Page loads without errors
- [ ] Only non-teaching staff shown
- [ ] "Add Staff" button visible
- [ ] Position dropdown has non-teaching roles

#### Test Actions:
1. **Add Non-Teaching Staff:**
   - Click "Add Staff"
   - Fill form with non-teaching position (e.g., "Security Guard")
   - Save
   - Expected: New staff appears, staff_type automatically set to 'non-teaching'

---

## 🐛 Troubleshooting

### Issue: "Failed to load staff data"
**Solution:**
1. Check browser console for errors (F12)
2. Verify API endpoint:
```bash
curl http://localhost/Kingsway/api/?route=staff&action=index
```
3. Check database connection in config.php

### Issue: "Department dropdown is empty"
**Solution:**
1. Verify departments exist:
```sql
SELECT * FROM departments WHERE status='active';
```
2. Add departments if needed (see setup section above)

### Issue: "Cannot create staff - validation error"
**Solution:**
1. Check all required fields are filled
2. Verify email format is correct
3. Check department_id exists in database

### Issue: "Modal doesn't close after saving"
**Solution:**
1. Check browser console for JavaScript errors
2. Verify Bootstrap 5 is loaded
3. Check if `staff_controllers.js` is loaded

---

## 📊 Database Verification Queries

### Check Staff Records:
```sql
SELECT s.id, u.first_name, u.last_name, s.position, s.status, 
       d.name as department, st.name as staff_type
FROM staff s
LEFT JOIN users u ON s.user_id = u.id
LEFT JOIN departments d ON s.department_id = d.id
LEFT JOIN staff_types st ON s.staff_type_id = st.id
ORDER BY s.id DESC LIMIT 10;
```

### Check Attendance Records:
```sql
SELECT sa.date, CONCAT(u.first_name, ' ', u.last_name) as staff_name,
       sa.status, sa.time_in, sa.time_out, sa.duty_type
FROM staff_attendance sa
LEFT JOIN staff s ON sa.staff_id = s.id
LEFT JOIN users u ON s.user_id = u.id
ORDER BY sa.date DESC, sa.time_in DESC
LIMIT 10;
```

### Check Departments:
```sql
SELECT id, name, code, status FROM departments WHERE status='active';
```

### Check Staff Types:
```sql
SELECT * FROM staff_types;
```

---

## ✅ Success Criteria

All tests pass if:
- ✅ All 4 pages load without JavaScript errors
- ✅ Staff can be created, edited, viewed, deleted
- ✅ Attendance can be marked and viewed
- ✅ Search and filters work on all pages
- ✅ Teachers show only teaching staff
- ✅ Non-teaching staff show only non-teaching staff
- ✅ Department dropdowns are populated
- ✅ Statistics update correctly
- ✅ Modals open/close properly
- ✅ Data persists in database

---

## 🎯 Quick Smoke Test (2 minutes)

Run this to quickly verify everything works:

1. Open: `http://localhost/Kingsway/home.php?route=manage_staff`
2. Check statistics show numbers
3. Click "Add Staff" - modal should open
4. Close modal with X button
5. Open: `http://localhost/Kingsway/home.php?route=staff_attendance`
6. Click "Mark Today" - modal should open
7. Open: `http://localhost/Kingsway/home.php?route=manage_teachers`
8. Page should load with table
9. Open: `http://localhost/Kingsway/home.php?route=manage_non_teaching_staff`
10. Page should load with table

If all 10 steps work: ✅ **IMPLEMENTATION SUCCESSFUL!**

---

**Last Updated:** February 17, 2026
