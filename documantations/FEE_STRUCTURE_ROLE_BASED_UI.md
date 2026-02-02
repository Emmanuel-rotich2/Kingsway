# Fee Structure Page - Role-Based UI Implementation

## Overview

We've completely redesigned the fee structure page to display **different user interfaces for different user roles**, solving the issue where all users saw the same page layout regardless of their needs.

---

## Problem Statement

**Before:**
- All users (Director, Accountant, Headteacher, etc.) saw the SAME page layout
- Only the DATA was filtered based on role (backend filtering)
- Button visibility controlled by PHP conditionals
- No role-specific workflows or UI optimization

**After:**
- Each role gets a **completely different UI** tailored to their specific needs
- Director: Full management interface with bulk operations and approval workflows
- Accountant: Revenue tracking and reconciliation-focused interface  
- Headteacher: Read-only overview with reporting capabilities
- Each UI optimized for that role's primary tasks

---

## Architecture

### File Structure

```
pages/
├── fee_structure.php                      ← ROUTER (determines which template to load)
└── fee_structure/
    ├── admin_fee_structure.php            ← Full management (Director, System Admin)
    ├── accountant_fee_structure.php       ← Revenue tracking (Accountant, Bursar)
    └── viewer_fee_structure.php           ← Read-only (Headteacher, Deputy, HODs)
```

### Data Flow

```
1. User navigates to: home.php?route=fee_structure
   ↓
2. app_layout.php includes: pages/fee_structure.php
   ↓
3. fee_structure.php (ROUTER):
   - Checks $_SESSION['role']
   - Maps role to template file
   - Includes appropriate template
   ↓
4. Role-specific template loads:
   - admin_fee_structure.php     (for director/admin)
   - accountant_fee_structure.php (for accountant)
   - viewer_fee_structure.php     (for headteacher)
   ↓
5. Template loads role-specific JavaScript:
   - fee_structure_admin.js       (for admin template)
   - fee_structure_accountant.js  (for accountant template)
   - fee_structure_viewer.js      (for viewer template)
   ↓
6. JavaScript calls API with JWT token
   ↓
7. API applies role-based data filtering (existing logic)
   ↓
8. Response rendered in role-specific UI
```

---

## Role Mapping

### Router Logic (`pages/fee_structure.php`)

```php
$roleTemplateMap = [
    // ADMIN ROLES → Full management interface
    'director_owner'        => 'admin_fee_structure.php',
    'school_admin'          => 'admin_fee_structure.php',
    'system_administrator'  => 'admin_fee_structure.php',
    
    // ACCOUNTANT ROLES → Revenue tracking interface
    'accountant'            => 'accountant_fee_structure.php',
    'bursar'                => 'accountant_fee_structure.php',
    
    // VIEWER ROLES → Read-only interface
    'headteacher'           => 'viewer_fee_structure.php',
    'deputy_headteacher'    => 'viewer_fee_structure.php',
    'hod'                   => 'viewer_fee_structure.php',
];
```

---

## Role-Specific Features

### 1. Admin Template (`admin_fee_structure.php`)

**Target Users:** Director, System Admin

**UI Components:**
- ✅ Full sidebar (280px) with all navigation
- ✅ 5 stat cards: Total, Active, Pending Approval, Revenue, Students
- ✅ 2 charts: Fee Distribution, Revenue Projection
- ✅ Full data table with 14 columns
- ✅ Bulk selection checkboxes
- ✅ Bulk operations toolbar

**Actions Available:**
- ➕ Create New Structure
- ✏️ Edit Structure
- 🗑️ Delete Structure
- 📑 Duplicate for New Year
- ✅ Approve/Reject
- 🔄 Bulk Activate/Archive/Delete
- 📥 Export All
- ⚙️ Bulk Operations

**JavaScript Controller:** `fee_structure_admin.js`

**Unique Features:**
- Approval workflows
- Version control
- Audit history
- Detailed analytics
- Multi-level filtering

---

### 2. Accountant Template (`accountant_fee_structure.php`)

**Target Users:** Accountant, Bursar

**UI Components:**
- ✅ Collapsible sidebar (80px → 240px)
- ✅ 4 stat cards: Active Structures, Expected Revenue, Collected, Collection Rate
- ✅ 2 charts: Revenue vs Collections, Payment Status
- ✅ Data table with 12 columns (revenue-focused)
- ✅ Quick action buttons

**Actions Available:**
- ➕ Create Structure
- ✏️ Edit Structure
- 📑 Duplicate for New Year
- 💾 Save as Draft
- ✅ Submit for Approval (not approve directly)
- 📥 Export to Excel
- 🔄 Reconcile Fees
- ⚠️ View Defaulters
- 🧾 Generate Invoices
- 🔔 Send Reminders

**JavaScript Controller:** `fee_structure_accountant.js`

**Unique Features:**
- Revenue tracking metrics
- Payment reconciliation tools
- Collection rate monitoring
- Defaulter management
- Invoice generation
- Payment status distribution

---

### 3. Viewer Template (`viewer_fee_structure.php`)

**Target Users:** Headteacher, Deputy Headteacher, HODs

**UI Components:**
- ✅ Minimal sidebar (60px)
- ✅ 3 stat cards: Active Structures, Expected Revenue, Students
- ✅ 1 chart: Fee Distribution
- ✅ Read-only data table with 9 columns
- ✅ Summary section

**Actions Available:**
- 👁️ View Details (read-only)
- 📥 Export Report
- 🖨️ Print Summary

**NO Actions:**
- ❌ Cannot create
- ❌ Cannot edit
- ❌ Cannot delete
- ❌ Cannot approve

**JavaScript Controller:** `fee_structure_viewer.js`

**Unique Features:**
- Simplified overview
- Focus on reporting
- Print-optimized views
- Export capabilities
- Summary statistics

---

## UI Comparison Table

| Feature | Admin | Accountant | Viewer |
|---------|-------|------------|--------|
| **Sidebar** | Full (280px) | Collapsible (80-240px) | Minimal (60px) |
| **Stat Cards** | 5 cards | 4 cards | 3 cards |
| **Charts** | 2 charts | 2 charts | 1 chart |
| **Table Columns** | 14 columns | 12 columns | 9 columns |
| **Create** | ✅ Yes | ✅ Yes | ❌ No |
| **Edit** | ✅ Yes | ✅ Yes (draft only) | ❌ No |
| **Delete** | ✅ Yes | ❌ No | ❌ No |
| **Approve** | ✅ Yes | ❌ No (request only) | ❌ No |
| **Bulk Operations** | ✅ Yes | ❌ No | ❌ No |
| **Revenue Tracking** | ✅ Basic | ✅ Advanced | ✅ View Only |
| **Reconciliation** | ✅ Yes | ✅ Advanced | ❌ No |

---

## Next Steps

### 1. Create JavaScript Controllers (Required)

You need to create 3 separate JavaScript files:

```javascript
// js/pages/fee_structure_admin.js
class FeeStructureAdminController {
    static init() {
        // Admin-specific initialization
        // Load full data with all fields
        // Enable bulk operations
        // Setup approval workflows
    }
}

// js/pages/fee_structure_accountant.js
class FeeStructureAccountantController {
    static init() {
        // Accountant-specific initialization  
        // Load revenue tracking data
        // Setup reconciliation tools
        // Enable payment monitoring
    }
}

// js/pages/fee_structure_viewer.js
class FeeStructureViewerController {
    static init() {
        // Viewer-specific initialization
        // Load read-only data
        // Setup export/print functions
        // Disable editing features
    }
}
```

### 2. Create Role-Specific CSS (Recommended)

```css
/* css/roles/admin-theme.css */
.admin-layout { /* Full-featured styles */ }

/* css/roles/manager-theme.css */
.manager-layout { /* Streamlined styles */ }

/* css/roles/viewer-theme.css */  
.viewer-layout { /* Minimal read-only styles */ }
```

### 3. Update API Endpoints (If Needed)

The existing API endpoints should work as-is because they already implement role-based data filtering. However, you may want to add accountant-specific endpoints:

```php
// For accountant template - revenue tracking
GET /api/finance/fee-structures/revenue-summary
GET /api/finance/fee-structures/collection-rate
GET /api/finance/fee-structures/reconciliation-data
```

### 4. Testing Checklist

- [ ] Test as Director - should see admin_fee_structure.php
- [ ] Test as Accountant - should see accountant_fee_structure.php  
- [ ] Test as Headteacher - should see viewer_fee_structure.php
- [ ] Test role switching - UI should change completely
- [ ] Test permissions - actions should match role capabilities
- [ ] Test API filtering - data should be role-appropriate

---

## Benefits of This Approach

### 1. **Role-Optimized Workflows**
- Each role sees only what they need
- UI reflects their primary tasks
- No clutter from irrelevant features

### 2. **Better User Experience**
- Director gets full control panel
- Accountant gets revenue-focused dashboard
- Headteacher gets clean oversight view

### 3. **Security Through UI**
- Dangerous actions (delete, approve) only shown to authorized roles
- Visual confirmation of permission level

### 4. **Maintainability**
- Clear separation of concerns
- Easy to modify one role's UI without affecting others
- Template pattern is well-established

### 5. **Scalability**
- Easy to add new roles (just create new template)
- Can customize per role without touching others
- Router makes role management centralized

---

## Example: What Each Role Sees

### Director Sees:
```
📋 Fee Structure Management
[Create New] [Bulk Operations] [Duplicate] [Export All]

Stats: [Total: 92] [Active: 45] [Pending: 3] [Revenue: 15M] [Students: 450]

Charts: [Fee Distribution] [Revenue Projection]

Table: All 14 columns with checkboxes for bulk selection
Actions per row: View | Edit | Delete | Duplicate | Approve
```

### Accountant Sees:
```
📋 Fee Structure Management  
[Create Structure] [Duplicate] [Export Excel]

Stats: [Active: 45] [Expected: 15M] [Collected: 12M] [Rate: 80%]

Charts: [Revenue vs Collections] [Payment Status]

Quick Actions: [Reconcile] [Defaulters] [Invoices] [Reminders]

Table: 12 revenue-focused columns
Actions per row: View | Edit | Payment History
```

### Headteacher Sees:
```
📋 Fee Structure Overview
[Export Report] [Print Summary]

Stats: [Active: 45] [Expected Revenue: 15M] [Students: 450]

Chart: [Fee Distribution]

Table: 9 columns (read-only)
Actions per row: View Details | Print
```

---

## Files Created

1. `/pages/fee_structure.php` - Router (modified existing file)
2. `/pages/fee_structure/admin_fee_structure.php` - Admin template
3. `/pages/fee_structure/accountant_fee_structure.php` - Accountant template
4. `/pages/fee_structure/viewer_fee_structure.php` - Viewer template

**Total Lines:** ~1,500 lines of role-specific HTML/PHP

---

## Pattern Can Be Replicated

This same approach can be applied to other shared pages:

- `pages/manage_finance.php` (already uses this pattern!)
- `pages/manage_admissions.php` (already uses this pattern!)
- `pages/manage_students.php` (can be upgraded)
- `pages/manage_staff.php` (can be upgraded)
- Any page where multiple roles need different workflows

The pattern is proven and consistent with your existing codebase architecture.
