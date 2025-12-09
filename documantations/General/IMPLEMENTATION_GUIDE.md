# FRONTEND IMPLEMENTATION GUIDE - Based on Real Backend Data

## IMPLEMENTATION ROADMAP

Based on the actual Kingsway Academy backend analysis, here's what we need to build in the frontend:

---

## PHASE 1: CRITICAL DASHBOARDS (Start Here)

### 1. Finance Director Dashboard
**Role:** Director/Owner, School Accountant  
**Data Sources:** `vw_arrears_summary`, `vw_payment_tracking`, `vw_outstanding_fees`

```
Layout:
┌─────────────────────────────────────────────────────┐
│  FINANCE DASHBOARD                    [Date Range]   │
├─────────────────────────────────────────────────────┤
│                                                       │
│  ┌──────────────────┐  ┌──────────────────────────┐  │
│  │ Collections MTD   │  │ Collection Rate          │  │
│  │ KES 2.5M         │  │ 68% (vs 75% target)     │  │
│  └──────────────────┘  └──────────────────────────┘  │
│                                                       │
│  ┌──────────────────┐  ┌──────────────────────────┐  │
│  │ Outstanding      │  │ Days Cash on Hand        │  │
│  │ KES 850K         │  │ 32 days (⚠️ low)        │  │
│  └──────────────────┘  └──────────────────────────┘  │
│                                                       │
│  ┌────────────────────────────────────────────────┐  │
│  │ ARREARS BY CLASS                               │  │
│  │ Form 4: KES 250K (18 students)                 │  │
│  │ Form 3: KES 180K (12 students)                 │  │
│  │ Form 2: KES 220K (15 students)                 │  │
│  │ Form 1: KES 200K (14 students)                 │  │
│  └────────────────────────────────────────────────┘  │
│                                                       │
│  ┌────────────────────────────────────────────────┐  │
│  │ OVERDUE ANALYSIS                               │  │
│  │ 30-60 days overdue: 8 students                 │  │
│  │ 60-90 days overdue: 5 students                 │  │
│  │ 90+ days overdue: 12 students                  │  │
│  │ Settlement Plans Active: 15 plans              │  │
│  └────────────────────────────────────────────────┘  │
│                                                       │
└─────────────────────────────────────────────────────┘
```

**Data Query:**
```sql
SELECT * FROM vw_arrears_summary;
-- Returns: level, students_in_arrears, total_arrears_amount, 
--          overdue_30days, overdue_60days, overdue_90days, settlement_plans
```

**Frontend Components Needed:**
- Dashboard card for each KPI
- Line chart for collection trend (monthly)
- Bar chart for arrears by class
- Table for top 10 overdue students (with action buttons: "Send Reminder", "Create Settlement Plan")

---

### 2. Academic Headteacher Dashboard
**Role:** Headteacher, Deputy Headteacher  
**Data Sources:** `vw_active_students_per_class`, Student/Academic APIs

```
Layout:
┌─────────────────────────────────────────────────────┐
│  ACADEMIC DASHBOARD                   [Current Term] │
├─────────────────────────────────────────────────────┤
│                                                       │
│  ┌──────────────────┐  ┌──────────────────────────┐  │
│  │ Total Enrolled   │  │ Average Class Size       │  │
│  │ 150 students     │  │ 32.5 students           │  │
│  └──────────────────┘  └──────────────────────────┘  │
│                                                       │
│  ┌──────────────────┐  ┌──────────────────────────┐  │
│  │ Avg Attendance   │  │ Promotion Rate (YTD)     │  │
│  │ 85.2% 📈        │  │ 92% 🎓                  │  │
│  └──────────────────┘  └──────────────────────────┘  │
│                                                       │
│  ┌────────────────────────────────────────────────┐  │
│  │ CLASS UTILIZATION                              │  │
│  │ Form 4: 45/50 (90%) ████████░                  │  │
│  │ Form 3: 38/50 (76%) ███████░░                  │  │
│  │ Form 2: 42/50 (84%) ████████░                  │  │
│  │ Form 1: 40/50 (80%) ████████░░                 │  │
│  └────────────────────────────────────────────────┘  │
│                                                       │
│  ┌────────────────────────────────────────────────┐  │
│  │ AT-RISK STUDENTS (Below 60% attendance)        │  │
│  │ Form 4: 3 students                             │  │
│  │ Form 3: 2 students                             │  │
│  │ Form 2: 4 students                             │  │
│  │ Form 1: 1 student                              │  │
│  │ [ACTION: Send SMS Reminder] [Contact Parent]   │  │
│  └────────────────────────────────────────────────┘  │
│                                                       │
└─────────────────────────────────────────────────────┘
```

**Data Queries:**
```sql
SELECT cs.class_id, c.name, COUNT(*) as enrollment 
FROM class_streams cs
JOIN classes c ON cs.class_id = c.id
JOIN students s ON s.stream_id = cs.id
WHERE s.status = 'active'
GROUP BY cs.class_id;

SELECT s.id, s.first_name, s.last_name, COUNT(*) as absent_days,
       (COUNT(*) * 100 / (SELECT COUNT(*) FROM student_attendance WHERE date >= DATE_SUB(NOW(), INTERVAL 30 DAY))) as absence_rate
FROM students s
JOIN student_attendance sa ON s.id = sa.student_id
WHERE sa.status = 'absent' AND sa.attendance_date >= DATE_SUB(NOW(), INTERVAL 30 DAY)
GROUP BY s.id
HAVING absence_rate > 40;
```

**Frontend Components:**
- 4 KPI cards (Total, Average, Attendance, Promotion)
- Class utilization bar chart
- At-risk students table with color-coded severity
- Action buttons: "Contact Parent", "Send SMS", "View Details"

---

### 3. Finance Staff - Collections Dashboard
**Role:** School Accountant, Accounts Assistant  
**Data Sources:** `payment_transactions`, `vw_payment_tracking`

```
Layout:
┌──────────────────────────────────────────────────────┐
│  PAYMENTS & COLLECTIONS              [This Month]    │
├──────────────────────────────────────────────────────┤
│                                                        │
│  ┌────────────────────┐  ┌──────────────────────────┐ │
│  │ Total Received     │  │ Expected (This Month)    │ │
│  │ KES 1.2M           │  │ KES 1.8M                │ │
│  └────────────────────┘  └──────────────────────────┘ │
│                                                        │
│  ┌────────────────────┐  ┌──────────────────────────┐ │
│  │ Collection %       │  │ Payment Methods          │ │
│  │ 67% ▓▓▓▓▓░░░░░░   │  │ M-Pesa: 65% (780K)      │ │
│  │                   │  │ Bank: 25% (300K)         │ │
│  │                   │  │ Cash: 10% (120K)         │ │
│  └────────────────────┘  └──────────────────────────┘ │
│                                                        │
│  ┌──────────────────────────────────────────────────┐ │
│  │ LATEST PAYMENTS (Paginated)                      │ │
│  ├──────┬──────────┬──────────┬────────┬────────────┤ │
│  │Date  │Student   │Amount    │Method  │Ref/Receipt │ │
│  ├──────┼──────────┼──────────┼────────┼────────────┤ │
│  │12/7  │John Doe  │50000     │M-Pesa  │MPE-001     │ │
│  │12/6  │Jane S... │75000     │Bank    │TRF-001     │ │
│  │12/5  │Peter K...|25000     │Cash    │CSH-001     │ │
│  │      │[← More]  │          │        │            │ │
│  └──────┴──────────┴──────────┴────────┴────────────┘ │
│                                                        │
│  ┌──────────────────────────────────────────────────┐ │
│  │ RECONCILIATION STATUS                            │ │
│  │ Unreconciled: 3 transactions (45000)             │ │
│  │ Reconciled: 95 transactions                      │ │
│  │ Disputed: 0 transactions                         │ │
│  └──────────────────────────────────────────────────┘ │
│                                                        │
└──────────────────────────────────────────────────────┘
```

**Frontend Components:**
- 4 KPI cards
- Pie chart for payment methods
- Paginated table with inline modals for:
  - View receipt
  - Edit payment details
  - Reconcile payment
- Bulk actions: "Reconcile Selected", "Send Receipt Emails"

---

## PHASE 2: CLASS TEACHER TOOLS

### 4. Attendance Marking Interface
**Role:** Class Teacher  
**Data Sources:** `student_attendance`, `class_streams`

```
Layout:
┌─────────────────────────────────────────────────────┐
│  ATTENDANCE - Form 4 Science         [2025-12-07]   │
├─────────────────────────────────────────────────────┤
│  [Previous] [Today] [Next] | [Submit] [Cancel]      │
├─────────────────────────────────────────────────────┤
│                                                       │
│  ┌─────┬──────────────┬──────────────┬──────────┐   │
│  │ No. │ Student Name │ Status       │ Notes    │   │
│  ├─────┼──────────────┼──────────────┼──────────┤   │
│  │ 1   │ John Doe     │ [✓ Present]  │          │   │
│  │ 2   │ Jane Smith   │ [✗ Absent]   │ Unwell   │   │
│  │ 3   │ Peter Khan   │ [⏱ Late]    │          │   │
│  │ 4   │ Mary Johnson │ [✓ Present]  │          │   │
│  │ 5   │ ...          │              │          │   │
│  └─────┴──────────────┴──────────────┴──────────┘   │
│                                                       │
│  Summary: Present: 38 | Absent: 5 | Late: 2         │
│                                                       │
└─────────────────────────────────────────────────────┘
```

**Data Query:**
```sql
SELECT s.id, s.first_name, s.last_name, 
       COALESCE(sa.status, 'unmarked') as status,
       sa.notes
FROM students s
JOIN class_streams cs ON s.stream_id = cs.id
WHERE cs.id = ?
ORDER BY s.first_name;
```

**Frontend Components:**
- Simple dropdown per student (Present, Absent, Late, Excused, Exempted)
- Optional notes field
- Bulk actions: "Mark All Present", "Download Report"
- Submit button validates and saves to DB

---

### 5. Results Entry Form
**Role:** Class Teacher, Subject Teacher  
**Data Sources:** `assessment_results`, `assessments`, `students`

```
Layout:
┌─────────────────────────────────────────────────────┐
│  RESULTS ENTRY - English (Form 4 Science)           │
│  Assessment: End of Term Exam | Total Marks: 100   │
├─────────────────────────────────────────────────────┤
│  [Previous] [Next Assessment] | [Save] [Cancel]    │
├─────────────────────────────────────────────────────┤
│                                                       │
│  ┌──────┬──────────────┬────────┬──────┬──────────┐  │
│  │ No.  │ Student Name │ Marks  │Grade │ Actions  │  │
│  ├──────┼──────────────┼────────┼──────┼──────────┤  │
│  │ 1    │ John Doe     │[__/100]│ A    │ [Edit]   │  │
│  │ 2    │ Jane Smith   │[__/100]│ B+   │ [Edit]   │  │
│  │ 3    │ Peter Khan   │[__/100]│ A-   │ [Edit]   │  │
│  │      │              │        │      │          │  │
│  └──────┴──────────────┴────────┴──────┴──────────┘  │
│                                                       │
│  Class Average: 68.5 | Median: 72 | Std Dev: 12.3  │
│  Grade Distribution: A: 5 | B+: 8 | B: 6 | C: 10  │
│                                                       │
│  ☐ Lock Results (prevent further edits)             │
│                                                       │
└─────────────────────────────────────────────────────┘
```

**Frontend Components:**
- Subject + Assessment selector
- Inline editable result table
- Real-time grade calculation
- Statistics summary below table
- Lock checkbox before submit

---

## PHASE 3: OPERATIONS & INVENTORY

### 6. Inventory Management Dashboard
**Role:** Inventory Manager, Inventory Staff  
**Data Sources:** `vw_inventory_health`, `vw_inventory_low_stock`, `inventory_items`

```
Layout:
┌─────────────────────────────────────────────────────┐
│  INVENTORY STATUS                                   │
├─────────────────────────────────────────────────────┤
│                                                       │
│  ┌──────────────────┐  ┌──────────────────────────┐  │
│  │ Total Items      │  │ Low Stock Items          │  │
│  │ 245 items        │  │ 18 items ⚠️             │  │
│  └──────────────────┘  └──────────────────────────┘  │
│                                                       │
│  ┌──────────────────┐  ┌──────────────────────────┐  │
│  │ Out of Stock     │  │ Total Inventory Value    │  │
│  │ 3 items 🔴      │  │ KES 2.45M                │  │
│  └──────────────────┘  └──────────────────────────┘  │
│                                                       │
│  ┌────────────────────────────────────────────────┐  │
│  │ CRITICAL STOCK ALERTS                          │  │
│  ├────────────────────────────────────────────────┤  │
│  │ ⚠️  Whiteboard Markers: 5 units (reorder: 20) │  │
│  │ ⚠️  Paper A4: 2 reams (reorder: 10)          │  │
│  │ 🔴 Ink Cartridges: 0 units (URGENT!)         │  │
│  │ ⚠️  Floppy Disks: 3 units (old stock)        │  │
│  └────────────────────────────────────────────────┘  │
│                                                       │
│  ┌────────────────────────────────────────────────┐  │
│  │ PENDING REQUISITIONS                           │  │
│  │ REQ-2025-005: Science Equipment (Submitted)   │  │
│  │ REQ-2025-006: Cleaning Supplies (Approved)    │  │
│  │ REQ-2025-007: Office Stationery (Pending)     │  │
│  │ [ACTION: Approve] [Reject] [Request More Info]│  │
│  └────────────────────────────────────────────────┘  │
│                                                       │
└─────────────────────────────────────────────────────┘
```

**Frontend Components:**
- 4 KPI cards with color coding
- Alert table with action buttons
- Modal for creating requisition
- Modal for viewing/approving requisition
- Table for requisition history with drill-down

---

### 7. Requisition Workflow (Drill-Down Navigation)
**Data Sources:** `inventory_requisitions`, `requisition_items`, `inventory_items`

```
NAVIGATION FLOW:
Requisitions List → Requisition Detail → Item Details → Stock History

┌──────────────────────────────┐
│ REQUISITIONS                 │
├──────────────────────────────┤
│ REQ-001: Approved ✓          │ ← Click
│ REQ-002: Pending ⏳          │
│ REQ-003: Rejected ✗          │
└──────────────────────────────┘
           ↓
┌──────────────────────────────┐
│ REQUISITION DETAIL (REQ-001) │
│ Status: Approved             │
│ Department: Biology Lab      │
│ Requested: 2025-12-01        │
├──────────────────────────────┤
│ Items:                       │
│ - Beakers (100) ← Click     │
│ - Flasks (50)               │
│ - Pipettes (200)            │
└──────────────────────────────┘
           ↓
┌──────────────────────────────┐
│ ITEM DETAIL (Beakers)       │
│ Current Stock: 5            │
│ Reorder Level: 20           │
│ Quantity Requested: 100     │
│ Quantity Allocated: 50      │
│ Status: Partial             │
├──────────────────────────────┤
│ Recent Transactions:         │
│ Dec 5: Issued 10 to Lab     │
│ Dec 1: Received 25 from ... │
│ Nov 25: Issued 5 to ...     │
└──────────────────────────────┘
```

**Frontend Components:**
- DataTable for requisitions with status badges
- PageNavigator for drill-down flow
- Modal for creating/editing requisition
- Modal for approving requisition
- Edit mode for changing allocations

---

## PHASE 4: COMMUNICATIONS & MESSAGING

### 8. Messaging System (Tab Navigation)
**Role:** All users  
**Data Sources:** `communications`, `communication_recipients`, `message_read_status`

```
Layout with Tabs:
┌──────────────────────────────────────────────────────┐
│  MESSAGES                                            │
├──────────────────────────────────────────────────────┤
│  [Inbox (3)] [Sent] [Drafts (1)] [+Compose]        │
├──────────────────────────────────────────────────────┤
│                                                       │
│  ┌──────┬──────────────┬──────────────┬────────────┐ │
│  │ From │ Subject      │ Preview      │ Date       │ │
│  ├──────┼──────────────┼──────────────┼────────────┤ │
│  │ 🔵   │ Announcement │ "Important n..."│ Today  │ │  (unread)
│  │ ⚪   │ Finance      │ "Fee reminder"  │ Dec 5  │ │  (read)
│  │ 🔵   │ Class Update │ "Next meeting..."│ Dec 4 │ │  (unread)
│  │      │ [← Load More]│                │        │ │
│  └──────┴──────────────┴──────────────┴────────────┘ │
│                                                       │
│  [Selected: 0] [Mark as Read] [Delete] [Archive]    │
│                                                       │
└──────────────────────────────────────────────────────┘
```

**Compose Modal:**
```
┌────────────────────────────────────┐
│ NEW MESSAGE                        │
├────────────────────────────────────┤
│ To: [Search recipients...______]   │
│ CC: [Search...________________]    │
│ BCC: [Search...________________]   │
│ Subject: [________________]        │
│                                    │
│ [Rich text editor]                 │
│ ┌──────────────────────────────┐  │
│ │ Message body...              │  │
│ │                              │  │
│ └──────────────────────────────┘  │
│                                    │
│ [Attach File] [Attach Photo]      │
│ [Save Draft] [Send] [Cancel]      │
└────────────────────────────────────┘
```

**Frontend Components:**
- TabNavigator for Inbox | Sent | Drafts
- Searchable recipient picker (users, groups)
- Rich text editor
- Modal for viewing message (with attachments)
- Bulk actions: Mark as read, Delete, Archive
- Unread badge on tabs

---

## PHASE 5: STAFF MANAGEMENT

### 9. Staff Directory with Payroll
**Role:** HR Officer, Director, Accountant  
**Data Sources:** `staff`, `vw_staff_payroll_summary`, `vw_staff_leave_balance`

```
DataTable with Drill-Down:
┌──────────────────────────────────────────────────────┐
│  STAFF DIRECTORY                                     │
├──────────────────────────────────────────────────────┤
│  [Filter: Department] [Filter: Status]               │
├──────┬──────────────┬─────────────┬───────┬─────────┤
│ No.  │ Name         │ Department  │ Phone │ Email   │
├──────┼──────────────┼─────────────┼───────┼─────────┤
│ 1    │ John Smith   │ English     │ 0710  │ john@.. │ ← Click
│ 2    │ Mary Jones   │ Maths       │ 0711  │ mary@.. │
│ 3    │ ...          │ ...         │ ...   │ ...     │
└──────┴──────────────┴─────────────┴───────┴─────────┘
           ↓
┌──────────────────────────────────┐
│ STAFF DETAIL: John Smith         │
├──────────────────────────────────┤
│ [Bio] [Qualifications] [         │
│  [Performance] [Leave] [Payroll] │
├──────────────────────────────────┤
│ PAYROLL TAB:                     │
│ Basic Salary: 65,000             │
│ Allowances: 15,000               │
│ Gross: 80,000                    │
│ Tax: 8,500                       │
│ NSSF: 2,160                      │
│ Net: 69,340                      │
│ YTD Gross: 960,000               │
│ YTD Tax: 102,000                 │
│ Payment Method: Bank Transfer    │
│ [View Payslips] [Edit Details]   │
└──────────────────────────────────┘
```

**Frontend Components:**
- DataTable with column sorting/filtering
- PageNavigator to staff detail page
- TabNavigator for Bio | Qualifications | Performance | Leave | Payroll
- Modals for editing each section
- Payslip PDF download button

---

## PHASE 6: PARENT PORTAL & STUDENT VIEW

### 10. Student Academic Progress (Parent View)
**Role:** Parent, Student  
**Data Sources:** `students`, `assessment_results`, `student_attendance`

```
Dashboard Layout:
┌─────────────────────────────────────────────────────┐
│  CHILD'S PROGRESS - Sarah Doe                       │
│  Class: Form 4 Science | Term: 3 of 3              │
├─────────────────────────────────────────────────────┤
│                                                       │
│  ┌──────────────────┐  ┌──────────────────────────┐  │
│  │ Current Balance  │  │ Attendance (This Term)   │  │
│  │ KES 5,500 (Due)  │  │ 86% (3 absences)       │  │
│  └──────────────────┘  └──────────────────────────┘  │
│                                                       │
│  ┌────────────────────────────────────────────────┐  │
│  │ CURRENT RESULTS                                │  │
│  │ English: 72 (A-) | Maths: 68 (B+) | Science: 75 (A)│  │
│  │ Kiswahili: 65 (B) | History: 58 (B-) | CRE: 80 (A) │  │
│  │                                                │  │
│  │ Class Average: 68.5 | Sarah's: 70 | Rank: 12/45  │  │
│  └────────────────────────────────────────────────┘  │
│                                                       │
│  ┌────────────────────────────────────────────────┐  │
│  │ PAYMENT REMINDER                               │  │
│  │ ⚠️ Balance due: KES 5,500                      │  │
│  │ Due date: 2025-12-15                           │  │
│  │ [Pay Now (M-Pesa)] [View Receipt] [FAQ]       │  │
│  └────────────────────────────────────────────────┘  │
│                                                       │
│  ┌────────────────────────────────────────────────┐  │
│  │ ANNOUNCEMENTS                                  │  │
│  │ Dec 6: School closes on Dec 20th              │  │
│  │ Dec 4: Form 4 registration opened             │  │
│  │ Dec 1: Holiday activities sign-up             │  │
│  └────────────────────────────────────────────────┘  │
│                                                       │
└─────────────────────────────────────────────────────┘
```

**Frontend Components:**
- Balance warning card (red if overdue)
- Attendance percentage card
- Subjects table with grades
- Class ranking
- Payment action button (links to external M-Pesa)
- Announcements feed

---

## IMPLEMENTATION PRIORITY

### MUST BUILD FIRST (Week 1):
1. ✅ DataTable.js - Already done
2. ✅ ModalForm.js - Already done
3. ✅ TabNavigator.js - Already done
4. ✅ PageNavigator.js - Already done
5. Finance Dashboard - KPI cards + charts
6. Attendance Marking Interface
7. Student Balance/Arrears List

### NEXT (Week 2):
8. Academic Dashboard
9. Results Entry Form
10. Messaging System (Tabs)

### THEN (Week 3-4):
11. Inventory Dashboard
12. Staff Directory (with drill-down)
13. Parent Portal
14. Reports (printing/PDF)

---

## KEY API ENDPOINTS TO USE IN FRONTEND

### Students
```javascript
// Get all students (paginated)
GET /api/students?page=1&limit=20&search=john&sort=first_name&order=asc

// Get single student with full profile
GET /api/students/{id}

// Get student balance
GET /api/payments/balances/{studentId}
```

### Finance
```javascript
// Get payment transactions
GET /api/payments?filter[start_date]=2025-12-01&filter[end_date]=2025-12-31

// Get arrears report
GET /api/payments/reports/arrears

// Get fee structure for class
GET /api/payments/fees/structure/{classId}
```

### Academic
```javascript
// Get classes
GET /api/academic/classes

// Get assessments for class
GET /api/academic/assessments/{classId}

// Get results
GET /api/academic/results/{classId}
```

### Attendance
```javascript
// Get attendance for date
GET /api/attendance?filter[date]=2025-12-07&filter[class_id]=1

// Record attendance (POST)
POST /api/attendance
{
  "student_id": 1,
  "date": "2025-12-07",
  "status": "present",
  "notes": ""
}
```

### Inventory
```javascript
// Get all items with stock levels
GET /api/inventory?filter[status]=active

// Get low stock items
GET /api/inventory/reports/low-stock

// Get requisitions
GET /api/inventory/requisitions?filter[status]=pending
```

---

## STYLING & COLOR SCHEME

Based on existing `king.css`:
- Primary: School branding color
- Success (Green): 68%, Present, Approved
- Warning (Yellow): 68-75%, Partial, Pending
- Danger (Red): <68%, Absent, Overdue
- Info (Blue): Messages, Announcements
- Neutral (Gray): Inactive, Completed

Use Bootstrap modal defaults + custom cards for KPIs.

---

## NEXT STEPS FOR YOU

1. ✅ You now have complete backend data structure
2. ✅ You know exactly what views/reports are available
3. ✅ You know which data is nested vs flat vs arrays
4. ✅ You know the role-based data access patterns
5. ⏭️ NEXT: Create page controllers that fetch REAL data from these endpoints
6. ⏭️ NEXT: Build dashboards using actual database views
7. ⏭️ NEXT: Create workflow modals that push to actual APIs
8. ⏭️ NEXT: Implement role-based rendering based on user permissions
