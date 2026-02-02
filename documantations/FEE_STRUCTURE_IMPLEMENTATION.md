# Fee Structure Implementation Guide

## Overview

A comprehensive Fee Structure management system has been implemented with **permission-aware data access**. Each user role sees only the fee structures relevant to them, ensuring data isolation and security.

## 📋 Completed Tasks

### 1. ✅ Fee Structure Page (`pages/fee_structure.php`)
**Location:** `/Kingsway/pages/fee_structure.php`

**Features:**
- Permission-based visibility filtering
- Filter by Academic Year, Class, Status, and Search
- Summary statistics (Total, Active, Pending, Expected Revenue)
- Responsive data table with pagination
- View, Edit, Delete, and Duplicate actions
- Modal windows for detailed views

**Role-Based Access:**
- **School Admin / Director / Accountant / Headteacher:** See all fee structures
- **Teacher:** See only structures for their taught classes
- **Parent:** See only their children's class structures
- **Student:** See only their class's structure

### 2. ✅ API Endpoints (`api/controllers/FinanceController.php`)

**New endpoints added:**

```
GET    /api/finance/fee-structures/list          List all fee structures
GET    /api/finance/fee-structures/{id}           Get specific structure with items
POST   /api/finance/fee-structures                Create new structure
PUT    /api/finance/fee-structures/{id}           Update structure
DELETE /api/finance/fee-structures/{id}           Delete structure
POST   /api/finance/fee-structures/{id}/duplicate Duplicate to new academic year
```

**Request/Response Format:**

```json
// GET /api/finance/fee-structures/list
Query Parameters:
{
  "page": 1,
  "limit": 20,
  "academic_year": "2025",
  "class_id": "5",
  "status": "active",
  "search": "Form One"
}

Response:
{
  "success": true,
  "data": {
    "fee_structures": [
      {
        "id": 123,
        "class_id": 5,
        "class_name": "Form One",
        "level_name": "Secondary",
        "academic_year": 2025,
        "status": "active",
        "total_amount": 125000,
        "created_at": "2024-01-15T10:30:00Z"
      }
    ],
    "pagination": {
      "total": 50,
      "page": 1,
      "limit": 20,
      "pages": 3
    },
    "stats": {
      "total": 50,
      "active": 35,
      "pending": 10,
      "total_revenue": 5250000
    }
  }
}
```

### 3. ✅ Backend Services

#### FinanceAPI Methods
**Location:** `/api/modules/finance/FinanceAPI.php`

```php
public function listFeeStructures($filters, $page, $limit)
public function getFeeStructure($structureId)
public function createFeeStructure($data)
public function updateFeeStructure($structureId, $data)
public function deleteFeeStructure($structureId)
public function duplicateFeeStructure($structureId, $data)
```

**Permission Enforcement:**
- Uses `hasPermission()` to check user rights
- `fees_create`, `fees_edit`, `fees_delete` permissions
- `canViewFeeStructure()` - Restricts view based on role
- `canEditFeeStructure()` - Blocks editing of active structures (directors only)
- `canDeleteFeeStructure()` - Allows deletion only for draft structures by directors

**Helper Methods:**
- `getStudentClassId($userId)` - Get student's class
- `getParentChildrenClassIds($userId)` - Get all children's classes
- `getTeacherClassIds($userId)` - Get taught classes
- `applyFeeStructurePermissions()` - Apply role-based filtering

#### FeeManager Methods
**Location:** `/api/modules/finance/FeeManager.php`

```php
public function getFeeStructure($structureId)
public function deleteFeeStructure($structureId)
public function duplicateFeeStructure($sourceStructureId, $data)
```

**Features:**
- `getFeeStructure()` - Returns structure + fee items + student statistics
- `deleteFeeStructure()` - Checks if in use before deletion
- `duplicateFeeStructure()` - Copies structure with optional price adjustment

### 4. ✅ Frontend Controller (`js/pages/fee_structure.js`)

**Location:** `/Kingsway/js/pages/fee_structure.js`

**Class Methods:**
```javascript
FeeStructureController.init()              // Initialize page
loadFeeStructures(page)                    // Load with filters
renderFeeStructures(structures)            // Render table
viewStructure(id)                          // View modal
editStructure(id)                          // Edit modal
deleteStructure(id)                        // Delete confirmation
duplicateStructure(id)                     // Duplicate with year selection
applyFilters()                             // Apply current filters
exportStructures()                         // Export to CSV
```

**Features:**
- Automatic data loading and refresh
- Real-time filtering by year, class, status, and search
- Modal-based editing interface
- Pagination with smart page navigation
- Currency formatting (KES)
- Status badge styling
- Permission-aware button visibility

## 🔐 Permission System

### Required Permissions
```sql
-- Check user permissions
CALL sp_get_user_permissions(user_id);

-- Grant permissions
CALL sp_grant_permission(user_id, 'fees_view', 'reason', admin_id, NULL);
CALL sp_grant_permission(user_id, 'fees_create', 'reason', admin_id, NULL);
CALL sp_grant_permission(user_id, 'fees_edit', 'reason', admin_id, NULL);
CALL sp_grant_permission(user_id, 'fees_delete', 'reason', admin_id, NULL);
```

### Role-Permission Mapping
```sql
-- View which roles have which permissions
SELECT p.code, r.name, COUNT(*) as count
FROM role_permissions rp
JOIN permissions p ON rp.permission_id = p.id
JOIN roles r ON rp.role_id = r.id
WHERE p.code LIKE 'fees_%'
GROUP BY p.code, r.name;
```

## 📊 Data Model

### Tables Used
```sql
-- Core fee structure tables
fee_structures_detailed    -- Main structure records
fee_structures            -- Individual fee items
student_fee_obligations   -- Student obligations linked to structure
academic_years           -- Academic year reference
classes                  -- Class reference
school_levels           -- School level reference
```

### Schema Example
```sql
-- View a specific structure
SELECT fs.*, sl.name as level_name, c.name as class_name
FROM fee_structures_detailed fs
LEFT JOIN school_levels sl ON fs.level_id = sl.id
LEFT JOIN classes c ON fs.class_id = c.id
WHERE fs.id = 123;

-- View fee items for a structure
SELECT * FROM fee_structures WHERE fee_structure_detail_id = 123;

-- View student obligations
SELECT * FROM student_fee_obligations WHERE fee_structure_detail_id = 123;
```

## 🚀 Usage

### Accessing the Page

```
URL: http://localhost/Kingsway/home.php?route=fee_structure
```

### Testing Different Roles

#### 1. School Admin / Director (Full Access)
```javascript
// Can see all structures
GET /api/finance/fee-structures/list
Response: All structures from all classes
```

#### 2. Accountant (Full Access)
```javascript
// Can see all structures
GET /api/finance/fee-structures/list
Response: All structures (used for payment allocation)
```

#### 3. Teacher (Limited Access)
```javascript
// Can see only their classes' structures
GET /api/finance/fee-structures/list
Response: Only structures for classes they teach
```

#### 4. Parent (Limited Access)
```javascript
// Can see only their children's class structures
GET /api/finance/fee-structures/list
Response: Only structures for their children's classes
```

#### 5. Student (Limited Access)
```javascript
// Can see only their class structure
GET /api/finance/fee-structures/list
Response: Only their class structure
```

## 🔍 Testing Scenarios

### Scenario 1: Create and Manage Fee Structure
1. Login as Director/Admin
2. Navigate to `Fee Structures` page
3. Click "New Structure" button
4. Fill in class, academic year, status
5. Add fee items
6. Save structure
7. Structure appears in list with "Draft" status

### Scenario 2: Duplicate for New Year
1. Click "Copy" button on existing structure
2. Select target academic year
3. Optionally set price adjustment (%)
4. Confirm duplication
5. New structure created with adjusted prices

### Scenario 3: Permission-Based Visibility
1. Login as different roles
2. Verify data isolation:
   - **Admin:** Sees all 50 structures
   - **Teacher:** Sees 5 structures (their classes)
   - **Parent:** Sees 2 structures (children's classes)
   - **Student:** Sees 1 structure (their class)

### Scenario 4: Filter and Search
1. Filter by Academic Year: 2025
2. Filter by Class: Form One
3. Filter by Status: Active
4. Search: "biology"
5. Results update dynamically

## ⚙️ Configuration

### Database Queries for Setup

```sql
-- Verify permission structure
SELECT * FROM permissions WHERE code LIKE 'fees_%';

-- Check role mappings
SELECT r.name, p.code 
FROM role_permissions rp
JOIN roles r ON rp.role_id = r.id
JOIN permissions p ON rp.permission_id = p.id
WHERE p.code LIKE 'fees_%'
ORDER BY r.name, p.code;

-- Verify student-class relationships
SELECT u.id as user_id, s.id as student_id, c.name as class
FROM users u
JOIN students s ON s.user_id = u.id
JOIN class_streams cs ON s.stream_id = cs.id
JOIN classes c ON cs.class_id = c.id
WHERE u.role = 'student';

-- Verify teacher-class relationships
SELECT u.id as user_id, st.id as staff_id, c.name as class
FROM users u
JOIN staff st ON st.user_id = u.id
JOIN staff_classes sc ON st.id = sc.staff_id
JOIN class_streams cs ON sc.stream_id = cs.id
JOIN classes c ON cs.class_id = c.id
WHERE u.role = 'teacher';
```

## 🐛 Troubleshooting

### Issue: "Access denied to this fee structure"
**Solution:** Ensure user role has correct permission
```php
// Grant permission
$stmt = $pdo->prepare("CALL sp_grant_permission(?, ?, ?, ?, NULL, @success)");
$stmt->execute([userId, 'fees_view', 'Access grant', adminId]);
```

### Issue: No fee structures loading
**Possible Causes:**
1. User has no fee structures for their role
2. Academic year filter is too restrictive
3. Class relationship is broken
**Solution:** Check database relationships

### Issue: Can't edit active structures
**Expected Behavior:** Only directors can edit active structures
**Workaround:** Archive the current structure, create new one

### Issue: Duplicate not working
**Check:** Both source and target academic years exist
```sql
SELECT * FROM academic_years WHERE id IN (source_year, target_year);
```

## 📝 Logs and Monitoring

### Track Actions
```sql
-- View fee structure actions
SELECT * FROM activity_logs 
WHERE entity_type = 'fee_structures'
ORDER BY created_at DESC
LIMIT 20;

-- Audit permission changes
SELECT * FROM permission_audit_log
WHERE action LIKE '%fee%'
ORDER BY changed_at DESC;
```

## 🔄 Next Steps

1. **Implement Print Functionality**
   - Export fee structures to PDF
   - Print-ready format with item details

2. **Add Approval Workflow**
   - Require director approval for new structures
   - Track approval history

3. **Batch Operations**
   - Bulk edit multiple structures
   - Bulk archive structures
   - Bulk duplicate to multiple years

4. **Analytics**
   - Revenue projection by class
   - Fee collection trends
   - Per-student obligation tracking

## 📞 Support

For issues or questions:
1. Check error logs: `logs/errors.log`
2. Verify database connections and permissions
3. Test API endpoints directly with cURL:
```bash
curl -H "Authorization: Bearer {token}" \
  http://localhost/Kingsway/api/finance/fee-structures/list
```
4. Check browser console for JavaScript errors

---

**Implementation Date:** January 30, 2026  
**Status:** ✅ Complete and Ready for Testing  
**Version:** 1.0.0
