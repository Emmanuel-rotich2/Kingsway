# Kingsway Academy - System Architecture Investigation

## Overview

The Kingsway Academy Management System uses a **JWT-based stateless REST API architecture** with role-based access control. It's designed for scalability, load balancing, and multiple user roles accessing the same application.

---

## 1. High-Level Data Flow

```
┌─────────────────────────────────────────────────────────────────┐
│                        Browser/Frontend                          │
│                                                                   │
│  1. User loads home.php → JWT token loaded from localStorage    │
│  2. JavaScript validates authentication (AuthContext.js)         │
│  3. DashboardRouter determines correct dashboard for user role   │
└─────────────────────────────────────────────────────────────────┘
                              ↓
┌─────────────────────────────────────────────────────────────────┐
│                     Page Load (app_layout.php)                   │
│                                                                   │
│  app_layout.php:                                                │
│  - Gets route from ?route=fee_structure query param             │
│  - Checks if route exists via DashboardRouter                   │
│  - Includes the page file (e.g., pages/fee_structure.php)       │
│  - Page includes layout: sidebar, header, main content, footer  │
└─────────────────────────────────────────────────────────────────┘
                              ↓
┌─────────────────────────────────────────────────────────────────┐
│                   Static HTML Structure Loaded                   │
│                                                                   │
│  pages/fee_structure.php:                                        │
│  - Generates static HTML with Bootstrap markup                  │
│  - Includes modals, tables, filters (all empty)                 │
│  - Loads CSS and JavaScript files                               │
│  - Includes <script src="/Kingsway/js/pages/fee_structure.js">  │
└─────────────────────────────────────────────────────────────────┘
                              ↓
┌─────────────────────────────────────────────────────────────────┐
│               JavaScript Initialization (DOMContentLoaded)       │
│                                                                   │
│  1. fee_structure.js loads and calls:                           │
│     FeeStructureController.init()                               │
│                                                                   │
│  2. Controller initializes:                                      │
│     - Reads userRole from DOM element                           │
│     - Sets up event listeners                                   │
│     - Loads academic years dropdown                             │
│     - Loads classes dropdown                                    │
│     - Loads fee structures list                                 │
│                                                                   │
│  3. API calls made via API.GET(), API.POST(), etc.              │
│     Format: /api/{module}/{endpoint}?action=handler_name        │
└─────────────────────────────────────────────────────────────────┘
                              ↓
┌─────────────────────────────────────────────────────────────────┐
│                    API Request Processing                        │
│                                                                   │
│  api/index.php routes to appropriate controller:                │
│  - Extracts route: /api/finance/fee-structures/list             │
│  - Routes to: FinanceController                                 │
│  - Calls: getFeesStructuresList() method                        │
│                                                                   │
│  FinanceController delegates to FinanceAPI:                     │
│  - FinanceAPI.listFeeStructures()                               │
│  - Checks user permissions via hasPermission()                  │
│  - Applies role-based filtering: applyFeeStructurePermissions() │
│  - Calls FeeManager for database operations                     │
│                                                                   │
│  FeeManager executes database queries:                          │
│  - Returns formatted response via formatResponse()              │
└─────────────────────────────────────────────────────────────────┘
                              ↓
┌─────────────────────────────────────────────────────────────────┐
│                   JSON Response Returned                         │
│                                                                   │
│  {                                                               │
│    "success": true,                                             │
│    "data": {                                                    │
│      "structures": [...],                                       │
│      "pagination": {...},                                       │
│      "summary": {...}                                           │
│    }                                                             │
│  }                                                               │
└─────────────────────────────────────────────────────────────────┘
                              ↓
┌─────────────────────────────────────────────────────────────────┐
│              JavaScript Renders UI (DOM Manipulation)            │
│                                                                   │
│  1. Response received and parsed                                 │
│  2. Controller calls renderFeeStructures(data)                  │
│  3. Dynamically creates table rows, modals content              │
│  4. Updates summary cards with statistics                       │
│  5. Renders pagination controls                                 │
│  6. Attaches event listeners to action buttons                  │
└─────────────────────────────────────────────────────────────────┘
```

---

## 2. Page Structure & Navigation

### 2.1 Entry Points

**Primary Entry Point: `home.php`**
```php
// home.php (Main entry)
$route = $_GET['route'] ?? '';  // Get route from query param

// If no route, default to 'loading'
if (empty($route)) {
    $route = 'loading';  // JavaScript determines correct dashboard
}

// Include app_layout.php which handles everything else
include __DIR__ . '/layouts/app_layout.php';
```

**Usage:**
- `/Kingsway/home.php?route=fee_structure`
- `/Kingsway/home.php?route=system_administrator_dashboard`
- `/Kingsway/home.php?route=school_accountant_dashboard`

### 2.2 Layout System: `app_layout.php`

```php
// layouts/app_layout.php
$route = $_GET['route'] ?? 'loading';

// Determine if route is a dashboard or regular page
if (DashboardRouter::dashboardExists($route)) {
    $requestedPath = DashboardRouter::getDashboardPath($route);
} else {
    $requestedPath = __DIR__ . "/../pages/{$route}.php";
}

// Include the page
if (file_exists($requestedPath)) {
    include $requestedPath;  // ← LOADS THE ACTUAL PAGE CONTENT
}
```

**Structure of app_layout.php:**
```
<div class="app-layout">
    <!-- Sidebar: populated by JavaScript -->
    <div id="sidebar-container">
        <?php include 'components/global/sidebar.php'; ?>
    </div>
    
    <!-- Main Content -->
    <main class="main-content">
        <?php include 'components/global/header.php'; ?>
        
        <!-- ACTUAL PAGE CONTENT GOES HERE -->
        <div id="main-content-segment">
            <?php include $requestedPath; ?>  ← DYNAMIC PAGE
        </div>
        
        <?php include 'components/global/footer.php'; ?>
    </main>
</div>
```

---

## 3. Navigation & Routing System

### 3.1 URL Structure

```
https://school.local/Kingsway/home.php?route=fee_structure
                                               ↓
                                      Route Name
                                      
Maps to:  pages/fee_structure.php  OR  components/dashboards/fee_structure_dashboard.php
```

### 3.2 DashboardRouter Configuration

**Purpose:** Maps roles to their default dashboards and verifies route validity

**Database Tables:**
- `dashboards` - Dashboard definitions
- `role_dashboards` - Role → Dashboard mappings  
- `roles` - Role definitions

**Usage in Code:**
```php
// Check if route exists
DashboardRouter::dashboardExists($route)  // true/false

// Get file path for route
DashboardRouter::getDashboardPath($route)  // /pages/route.php

// Get default dashboard for role
DashboardRouter::getDashboardForRole($roleId)  // returns route name
```

### 3.3 Sidebar Navigation (JavaScript-Driven)

**Sidebar is built from database, not hardcoded:**

```javascript
// sidebar.js - Dynamically renders sidebar from API data
function renderSidebar(menuItems) {
    // menuItems come from API call
    // renderSidebar builds HTML with:
    // - Menu items with icons
    // - Collapsible submenu groups
    // - Click handlers that change route
}

// When user clicks sidebar item:
// 1. Click handler gets data-route="fee_structure"
// 2. Calls: window.location.href = home.php?route=fee_structure
// 3. Page loads via app_layout.php routing
```

---

## 4. Page Files & Their Role

### 4.1 Different Types of Pages

**Type A: Standalone Management Pages**
```
pages/fee_structure.php
pages/manage_payments.php
pages/manage_finance.php
```
- Pure HTML/PHP structure
- Bootstrap markup for tables, modals, forms
- No PHP logic (stateless)
- Include their own JavaScript file
- Call API for all data operations

**Type B: Role-Specific Dashboards**
```
components/dashboards/school_accountant_dashboard.php
components/dashboards/system_administrator_dashboard.php
components/dashboards/director_owner_dashboard.php
```
- Complex HTML with many cards and sections
- Pre-rendered UI (not fully dynamic)
- Include role-specific JavaScript controllers
- Mixed approach: some hardcoded, some dynamic via API

### 4.2 Current Fee Structure Page

**File:** `pages/fee_structure.php` (302 lines)

**Structure:**
```php
<?php
// Session check
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: /index.php');
    exit;
}

// Role-based access check
$allowedRoles = ['school_admin', 'accountant', 'director_owner', ...];
if (!in_array($_SESSION['role'], $allowedRoles)) {
    http_response_code(403);
    exit;
}
?>

<!-- ONE UNIFIED HTML STRUCTURE FOR ALL ROLES -->
<div class="card shadow-sm">
    <div class="card-header">
        <h4>Fee Structures</h4>
        <!-- Same buttons for all roles -->
        <?php if (in_array($_SESSION['role'], ['school_admin', 'accountant', 'director_owner'])): ?>
            <button id="addFeeStructureBtn">New Structure</button>
        <?php endif; ?>
    </div>
    
    <div class="card-body">
        <!-- Same filters for all roles -->
        <select id="academicYearFilter">...</select>
        <select id="classFilter">...</select>
        
        <!-- Same table for all roles -->
        <table id="feeStructuresTable">
            ...
        </table>
    </div>
</div>

<!-- All modals included regardless of role -->
<div class="modal" id="feeStructureModal">...</div>
<div class="modal" id="deleteConfirmModal">...</div>

<script src="/Kingsway/js/pages/fee_structure.js"></script>
```

**Current Approach:**
- ✅ One page loads for all roles
- ✅ Backend filters what data each role can see
- ✅ Frontend shows/hides buttons based on role
- ❌ **UI layout is identical for all roles**
- ❌ **No role-specific workflows or purposes reflected in UI**

---

## 5. How Data Flows from Backend to Frontend

### 5.1 API Call Flow

**JavaScript (fee_structure.js)**
```javascript
loadFeeStructures(page = 1) {
    // Create filter object
    const filters = {
        page: page,
        academic_year: document.getElementById("academicYearFilter").value,
        class_id: document.getElementById("classFilter").value,
        status: document.getElementById("statusFilter").value,
        search: document.getElementById("searchFeeStructure").value,
    };
    
    // Make API call
    API.GET("/api/finance/fee-structures/list", filters)
        .then(response => {
            // Render response
            this.renderFeeStructures(response.data);
        });
}
```

**Backend Processing**

1. **FinanceController.php** (HTTP Handler)
```php
public function getFeesStructuresList($id, $data, $segments) {
    // Extract parameters from request
    $page = $_GET['page'] ?? 1;
    $limit = $_GET['limit'] ?? 20;
    
    // Delegate to API layer
    $result = $this->api->listFeeStructures($filters, $page, $limit);
    
    // Return JSON
    return $this->handleResponse($result);
}
```

2. **FinanceAPI.php** (Business Logic & Permission Checking)
```php
public function listFeeStructures($filters, $page, $limit) {
    // Check permission
    if (!$this->hasPermission('fees_view')) {
        return $this->formatResponse(false, 'No permission', null);
    }
    
    // Apply role-based filtering
    $this->applyFeeStructurePermissions($filters, $userRole, $userId);
    // This modifies filters based on role:
    // - Student: filters['class_id'] = their class
    // - Parent: filters['class_ids'] = their children's classes
    // - Teacher: filters['class_ids'] = their taught classes
    // - Admin: no filtering (see all)
    
    // Get data from manager
    $structures = $this->manager->getFeeStructures($filters, $page, $limit);
    
    // Return formatted
    return $this->formatResponse(true, 'Success', [
        'structures' => $structures,
        'pagination' => $pagination,
        'summary' => $summary,
    ]);
}
```

3. **FeeManager.php** (Database Operations)
```php
public function getFeeStructures($filters, $page, $limit) {
    // Build query with provided filters
    $query = "SELECT * FROM fee_structures_detailed WHERE 1=1";
    
    if (!empty($filters['class_id'])) {
        $query .= " AND class_id = " . $filters['class_id'];
    }
    // ... more filters
    
    // Execute and return
    return $this->db->fetchAll($result);
}
```

### 5.2 Response Structure

```json
{
    "success": true,
    "data": {
        "structures": [
            {
                "id": 1,
                "name": "Class 1A - 2024",
                "class_id": 5,
                "class_name": "Class 1A",
                "academic_year": "2024",
                "status": "active",
                "total_amount": 150000,
                "fee_items_count": 5,
                "created_at": "2024-01-10"
            }
        ],
        "pagination": {
            "current_page": 1,
            "total_pages": 5,
            "total_items": 92,
            "has_next": true
        },
        "summary": {
            "total_structures": 92,
            "active_count": 45,
            "pending_count": 3,
            "total_expected_revenue": 15000000
        }
    }
}
```

### 5.3 Frontend Rendering

```javascript
renderFeeStructures(data) {
    // Clear existing rows
    const tbody = document.getElementById('feeStructuresBody');
    tbody.innerHTML = '';
    
    // Render each structure
    data.structures.forEach(structure => {
        const row = document.createElement('tr');
        row.innerHTML = `
            <td>${structure.class_name}</td>
            <td>KES ${this.formatCurrency(structure.total_amount)}</td>
            <td><span class="badge bg-${this.getStatusColor(structure.status)}">
                ${structure.status}
            </span></td>
            <td>
                <button onclick="controller.viewStructure(${structure.id})">View</button>
                <button onclick="controller.editStructure(${structure.id})">Edit</button>
                <button onclick="controller.deleteStructure(${structure.id})">Delete</button>
            </td>
        `;
        tbody.appendChild(row);
    });
    
    // Update pagination
    this.renderPagination(data.pagination);
    
    // Update stats
    this.updateStats(data.summary);
}
```

---

## 6. Role-Based Data Filtering (Current Implementation)

### 6.1 Where Filtering Happens

**Layer 1: JavaScript (Cosmetic)**
```javascript
// This is NOT permission control - just UI visibility
<?php if (in_array($_SESSION['role'], ['school_admin', 'accountant', 'director_owner'])): ?>
    <button id="addFeeStructureBtn">New Structure</button>
<?php endif; ?>
```

**Layer 2: API (Real Permission Control)**
```php
// In FinanceAPI.php
public function listFeeStructures($filters, $page, $limit) {
    // Check actual permission
    if (!$this->hasPermission('fees_view')) {
        throw new PermissionException('No permission');
    }
    
    // Filter data based on role
    $this->applyFeeStructurePermissions($filters, $userRole, $userId);
}
```

### 6.2 Role-Based Filtering Logic (FinanceAPI.php)

```php
private function applyFeeStructurePermissions(&$filters, $userRole, $userId) {
    switch ($userRole) {
        case 'student':
            // Student only sees fee structure for their own class
            $classId = $this->getStudentClassId($userId);
            $filters['class_id'] = $classId;
            break;
            
        case 'parent':
            // Parent sees fee structures for all their children's classes
            $classIds = $this->getParentChildrenClassIds($userId);
            $filters['class_ids'] = $classIds;
            break;
            
        case 'teacher':
            // Teacher sees fee structures only for classes they teach
            $classIds = $this->getTeacherClassIds($userId);
            $filters['class_ids'] = $classIds;
            break;
            
        case 'accountant':
        case 'school_admin':
        case 'director_owner':
            // Admins see all fee structures - no filtering
            break;
    }
}
```

---

## 7. How Different User Roles Should See Different UI

### Current State
```
All Roles See:
├── Filters: Academic Year, Class, Status
├── Statistics: Total, Active, Pending, Revenue
├── Table: All fields (Class, Level, Year, Amount, Status, etc.)
├── Actions: View, Edit, Delete, Duplicate
└── Modals: Identical structure for all roles
```

### What You Want (Role-Specific UI)

```
School Admin / Director:
├── Complete management interface
├── All fee structures across all classes
├── Bulk operations
├── Approval workflows
├── Full audit trail
└── Export/reporting options

School Accountant:
├── Payment tracking interface
├── Revenue analysis by structure
├── Collections reporting
├── Reconciliation tools
├── Structure modifications for new year
└── Fee vs. payment comparison

Teacher:
├── View-only mode
├── Fee structures for their classes only
├── Student defaulter reports
└── Payment status by student
```

---

## 8. Recommended Approach for Role-Specific UI

### Option 1: Separate Pages per Role
```
pages/fee_structure_admin.php
pages/fee_structure_accountant.php
pages/fee_structure_teacher.php
```
- ✅ Completely different UI per role
- ✅ Cleaner code
- ❌ Code duplication
- ❌ Harder to maintain

### Option 2: Single Page with Role-Based Sections (RECOMMENDED)
```php
pages/fee_structure.php
├── PHP: Determine user role early
└── HTML: Output role-specific template sections based on role

Structure:
<?php $role = $_SESSION['role']; ?>

<?php if (in_array($role, ['school_admin', 'director_owner'])): ?>
    <!-- ADMIN UI: Full management interface -->
<?php elseif ($role === 'accountant'): ?>
    <!-- ACCOUNTANT UI: Payment & revenue focus -->
<?php elseif ($role === 'teacher'): ?>
    <!-- TEACHER UI: View-only, their classes -->
<?php endif; ?>

<script src="/Kingsway/js/pages/fee_structure.js?role=<?php echo $role; ?>"></script>
```

### Option 3: Template Rendering by Role
```php
pages/fee_structure.php includes:
├── components/fee_structure/admin_section.php
├── components/fee_structure/accountant_section.php
├── components/fee_structure/teacher_section.php
└── components/fee_structure/student_section.php
```

---

## 9. Key Files & Their Responsibilities

| File | Purpose | Stateless? |
|------|---------|-----------|
| `home.php` | Entry point, checks JWT | Yes |
| `layouts/app_layout.php` | Routes to correct page, includes layout | Yes |
| `pages/fee_structure.php` | Static HTML structure | Yes |
| `js/pages/fee_structure.js` | Dynamic content loading & rendering | Yes |
| `api/controllers/FinanceController.php` | HTTP route handler | Yes |
| `api/modules/finance/FinanceAPI.php` | Business logic & permission checks | Yes |
| `api/modules/finance/FeeManager.php` | Database operations | Yes |
| `config/DashboardRouter.php` | Role → Dashboard mapping | Database-driven |

---

## 10. Authentication & User Context

### Current Implementation

**JWT-Based (Stateless):**
- User logs in → JWT token stored in localStorage
- Token sent in every API request header: `Authorization: Bearer <token>`
- Backend verifies token, extracts user info
- No PHP sessions needed

**User Role Access:**
```javascript
// In fee_structure.js
this.userRole = document.body.getAttribute("data-user-role") || "guest";

// Then later
if (this.userRole === 'accountant') {
    // Show accountant-specific UI
}
```

**Where is data-user-role set?**
Currently: Missing! This needs to be added to the layout or page.

**Better approach:**
```php
// In app_layout.php
$userRole = $_SESSION['role'] ?? '';  // From JWT decoded
?>

<div class="app-layout" data-user-role="<?php echo htmlspecialchars($userRole); ?>">
    ...
</div>
```

---

## Summary of Data Flow

```
1. User enters: home.php?route=fee_structure
2. app_layout.php determines: route = fee_structure
3. app_layout.php includes: pages/fee_structure.php
4. fee_structure.php outputs: Static HTML (same for all roles)
5. Browser loads: fee_structure.js
6. fee_structure.js calls: API.GET(/api/finance/fee-structures/list)
7. API arrives at: FinanceController.getFeesStructuresList()
8. FinanceController delegates to: FinanceAPI.listFeeStructures()
9. FinanceAPI checks: hasPermission('fees_view')
10. FinanceAPI filters: applyFeeStructurePermissions() based on role
11. FinanceAPI delegates to: FeeManager.getFeeStructures()
12. FeeManager executes: Database query with filters
13. Response returns: JSON with filtered fee structures
14. fee_structure.js receives: Response with only user-appropriate data
15. fee_structure.js renders: Dynamic HTML from response data
16. User sees: Table populated with filtered fee structures

KEY INSIGHT:
- Backend already filters data based on role ✅
- Frontend shows/hides buttons based on role ✅  
- UI LAYOUT is identical for all roles ❌ ← YOUR CONCERN

SOLUTION:
Make step 4 role-aware:
- pages/fee_structure.php outputs different HTML based on role
- This happens before fee_structure.js loads
- Each role gets a completely different interface structure
```
