[js components pages layouts home.php index.php](..) [js components pages layouts home.php index.php](../../components) [js components pages layouts home.php index.php](../../pages) [js components pages layouts home.php index.php](../../layouts) [js components pages layouts home.php index.php](../../home.php) [js components pages layouts home.php index.php](../../index.php)# Page Controllers Documentation

## Overview
All page controllers have been created following a consistent pattern for easy replication across remaining HTML pages.

## Created Page Controllers (14 total)

### Core Module Controllers ✅
1. **students.js** (260 lines)
   - API Endpoint: `/students/index`
   - Statistics: Total students, active students, gender breakdown, attendance rate
   - Actions: View, Edit, Delete
   - Columns: ID, First Name, Last Name, Admission #, Class, Status, Created Date
   - Modal Forms: Create/Edit Student, View Profile

2. **staff.js** (150 lines)
   - API Endpoint: `/staff/index`
   - Statistics: Total staff, teaching/non-teaching count, present today
   - Actions: View, Edit, Assign (for classes/subjects), Delete
   - Columns: ID, First Name, Last Name, Email, Phone, Type, Status
   - Modal Forms: Create/Edit Staff, Assignment Dialog

3. **academic.js** (140 lines)
   - API Endpoints: `/academic/classes`, `/academic/lesson-plans`
   - Tables: Classes Table + Lesson Plans Table
   - Statistics: Total classes, total lesson plans
   - Actions: View, Edit, View Lessons (for classes), Approve (for lesson plans)
   - Columns: ID, Name, Form Level, Teacher, Student Count, Status

4. **finance.js** (160 lines)
   - API Endpoints: `/finance/payroll`, `/finance/fee-structures`
   - Tables: Payroll Table + Fee Structure Table
   - Statistics: Total payroll, pending approvals, outstanding fees
   - Payroll Status: draft, calculated, verified, approved, processed
   - Actions: View, Edit, Approve, Process
   - Columns: ID, Period, Staff Count, Gross, Deductions, Net, Status

5. **inventory.js** (200 lines)
   - API Endpoints: `/inventory/items`, `/inventory/purchase-orders`
   - Tables: Inventory Table + Purchase Order Table
   - Statistics: Total items, low stock count, total stock value, pending orders
   - Quantity Status Badges: critical (red), low (yellow), adequate (green)
   - Actions: View, Edit, Adjust Stock, Receive PO, Cancel
   - Columns: ID, Name, Category, Quantity, Reorder Level, Unit Cost, Status

### Additional Module Controllers ✅

6. **attendance.js** (150 lines)
   - API Endpoint: `/attendance/index`
   - Statistics: Present today, absent today, average rate, chronic absentees
   - Actions: View, Edit attendance record
   - Columns: ID, Student Name, Class, Date, Status, Remarks

7. **communications.js** (170 lines)
   - API Endpoints: `/communications/messages`, `/communications/announcements`
   - Tables: Messages/Notifications Table + Announcements Table
   - Statistics: Unread messages, total announcements, pending responses, notification queue
   - Actions: View, Delete (messages), View, Edit, Delete (announcements)

8. **assessments.js** (200 lines)
   - API Endpoints: `/academic/assessments`, `/academic/result-marks`
   - Tables: Assessments Table + Result Marks Table
   - Statistics: Total assessments, completed, pending marking, average performance
   - Status Badges: planned, in-progress, completed, marked
   - Actions: View, Edit, Conduct, Mark, Edit Mark

9. **timetable.js** (160 lines)
   - API Endpoints: `/academic/timetables`, `/academic/room-schedules`
   - Tables: Class Timetable Table + Room Schedule Table
   - Statistics: Total classes, total rooms, conflicts count, available slots
   - Features: Auto-generate timetable functionality
   - Actions: Edit, Delete (timetables)

10. **admissions.js** (190 lines)
    - API Endpoints: `/admissions/applications`, `/admissions/admitted-students`
    - Tables: Applications Table + Admitted Students Table
    - Statistics: Pending applications, total admitted, pending registration, acceptance rate
    - Application Status Flow: submitted → under-review → interview-scheduled → interviewed → admitted/rejected
    - Actions: View, Review, Schedule Interview, Admit, Reject, Register

11. **workflows.js** (210 lines)
    - API Endpoints: `/workflows/promotions`, `/workflows/transfers`, `/workflows/terminations`
    - Tables: Promotions Table + Transfers Table + Terminations Table
    - Statistics: Pending promotions, transfers, terminations, total executed
    - Status Workflows: pending → approved → executed/rejected
    - Actions: View, Approve, Reject

12. **transport.js** (210 lines)
    - API Endpoints: `/transport/routes`, `/transport/vehicles`, `/transport/allocations`
    - Tables: Routes Table + Vehicles Table + Allocations Table
    - Statistics: Total routes, active vehicles, allocated students, maintenance queue
    - Vehicle Status: operational, maintenance, retired
    - Actions: View, Edit, Maintenance, Deactivate

13. **boarding.js** (190 lines)
    - API Endpoints: `/boarding/allocations`, `/boarding/dormitories`, `/boarding/meals`
    - Tables: Allocations Table + Dormitories Table + Meals Table
    - Statistics: Total boarders, total dormitories, occupancy rate, pending allocations
    - Actions: View, Transfer, Terminate, Inspect, Record Actual (meals)

14. **activities.js** (180 lines)
    - API Endpoints: `/activities/index`, `/activities/participation`
    - Tables: Activities Table + Participation Table
    - Statistics: Total activities, total participants, active activities, without activity
    - Actions: View, Edit, View Members, Enroll Student, Remove

15. **lesson_plans.js** (130 lines)
    - API Endpoint: `/academic/lesson-plans`
    - Statistics: Total plans, draft plans, pending approval, completed lessons
    - Status Flow: draft → submitted → approved → taught → marked
    - Actions: View, Edit, Approve, Reject, Delete

16. **settings.js** (210 lines)
    - API Endpoints: `/users/index`, `/settings/roles`, `/settings/permissions`
    - Tables: Users Table + Roles Table + Permissions Table
    - Statistics: Total users, active users, total roles, total permissions
    - Features: Password reset, role management, permissions management, database backup
    - Actions: View, Edit, Reset Password, Deactivate

## Standard Pattern Used

Every controller follows this pattern:
```javascript
// 1. Global table variables
let [table]Table = null;

// 2. Initialize on DOM ready
document.addEventListener('DOMContentLoaded', async () => {
    initializeTables();           // Create DataTable instances
    loadStatistics();             // Fetch and display stats
    attachEventListeners();       // Wire up button clicks
});

// 3. Table initialization
function initializeTables() {
    table = new DataTable('container', {
        apiEndpoint: '/module/endpoint',
        pageSize: 10,
        columns: [...],            // Define columns
        searchFields: [...],       // Searchable fields
        rowActions: [...]          // Action buttons
    });
}

// 4. Statistics loading
async function loadStatistics() {
    const stats = await window.API.apiCall('/reports/module-stats', 'GET');
    // Update DOM elements with values
}

// 5. Event listeners
function attachEventListeners() {
    // Wire up search inputs
    // Wire up filter dropdowns
    // Wire up action buttons
}
```

## Features Implemented in All Controllers

✅ Permission-based row actions (only visible if user has permission)
✅ Search functionality on searchable fields
✅ Filter dropdowns for categorical data
✅ Statistics cards with key metrics
✅ Modal-based CRUD operations
✅ Pagination with configurable page size
✅ Sorting on sortable columns
✅ Custom formatting for dates, badges, percentages
✅ Error handling with console logging
✅ Responsive table layout

## Integration with Core Components

Each controller uses:
- **DataTable.js** - For rendering tabular data with sorting, filtering, pagination
- **ModalForm.js** - For create/edit/delete operations
- **ActionButtons.js** - For permission-aware action buttons and dropdowns
- **UIComponents.js** - For statistics cards, badges, status indicators

## Next Steps

### 1. Apply Template to HTML Pages
The following pages need to be redesigned using the manage_students.php template pattern:
- manage_staff.php
- manage_academics.php (split into classes, subjects, lesson_plans)
- manage_finance.php (split into payroll, fee_structures)
- manage_inventory.php
- manage_attendance.php
- manage_communications.php
- manage_transport.php
- manage_workflows.php
- manage_assessments.php
- manage_timetable.php
- manage_boarding.php
- system_settings.php

### 2. Create Specialized Workflow Components
Some modules need workflow-specific components:
- **Exam Workflow Component**: Schedule → Questions → Conduct → Mark → Verify → Moderate → Compile → Approve
- **Admission Workflow Component**: Application → Verify Documents → Interview → Offer → Registration
- **Transfer Workflow Component**: Eligibility Check → Approval → Execution
- **Promotion Workflow Component**: Identify Candidates → Validate → Execute → Report Generation
- **Assessment Workflow Component**: Create Items → Administer → Mark → Analyze

### 3. Bulk Action Support
Add bulk action handlers to tables for operations like:
- Bulk student promotion
- Bulk fee invoice generation
- Bulk stock adjustment
- Bulk enrollment/removal from activities
- Bulk absence marking

## File Locations

All page controllers are located in: `/js/pages/`

```
js/pages/
├── students.js
├── staff.js
├── academic.js
├── finance.js
├── inventory.js
├── attendance.js
├── communications.js
├── assessments.js
├── timetable.js
├── admissions.js
├── workflows.js
├── transport.js
├── boarding.js
├── activities.js
├── lesson_plans.js
└── settings.js
```

All component libraries are located in: `/js/components/`

```
js/components/
├── DataTable.js
├── ModalForm.js
├── ActionButtons.js
└── UIComponents.js
```

## Statistics Endpoints

Each module should implement corresponding statistics endpoints:
- `/reports/[module]-stats` - Returns key metrics for dashboard

Example response structure:
```json
{
  "metric_1": 100,
  "metric_2": 45,
  "metric_3": 67.8,
  "metric_4": 23
}
```

## Total Implementation

- **Core Components**: 2,200+ lines (DataTable, ModalForm, ActionButtons, UIComponents)
- **Page Controllers**: 2,400+ lines (16 controllers with consistent patterns)
- **Templates**: 230+ lines (manage_students.php as template)
- **Total**: 4,830+ lines of new code

## Architecture Summary

The entire system now follows an API-driven modal architecture:
1. Main pages load statistics and create DataTable instances
2. Users interact with data through tables
3. Row actions (view/edit/delete) open modal forms
4. Modal forms submit to API endpoints
5. Tables refresh to show updated data
6. Permissions are enforced at component level, not just backend

This eliminates the need for 47 sub-page files and creates a unified, consistent user experience.
