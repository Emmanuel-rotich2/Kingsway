# Role-Based Dashboard Design Specification

**Document Created**: Dec 28, 2025  
**Last Updated**: Dec 28, 2025 (CRITICAL SECURITY FIX)  
**Purpose**: Define dashboard content, metrics, and UI components for each role  
**Principle**: **Dashboards are driven by ROLE FUNCTION, not SYSTEM POWER**

---

## ⚠️ CRITICAL SECURITY PRINCIPLE: Role Authority vs. System Access

### The Mistake That Must Not Be Made

**Root system access ≠ Business data visibility**

Designers often confuse **technical authority** with **institutional data ownership**. This is a **serious access-control mistake**.

**The System Administrator:**
- ✅ Manages the SYSTEM (infrastructure, codebase, database, users, roles, permissions)
- ❌ Does NOT manage the SCHOOL (finance, payroll, operations, academics, students)

**The Director/Principal:**
- ✅ Manages the SCHOOL (finances, staff, students, approvals, strategic decisions)
- ❌ Does NOT manage the SYSTEM (cannot fix code, cannot access database directly, cannot manage tech infrastructure)

### Principle of Least Privilege

Every dashboard **must answer exactly one question**:

> **"What does this role need to see to do its job — and nothing more?"**

**NOT**: "What permissions does this role have?"  
**NOT**: "How much system power does this role have?"

### Why This Matters

1. **Privacy & Governance**: Finance data belongs to the school, not the developer
2. **Separation of Duties**: Technical staff should not see institutional secrets
3. **Audit Compliance**: Access logs must show legitimate business purpose, not "developer root access"
4. **Data Minimization**: Even root users follow least privilege
5. **Trust**: Staff trust the system only if different roles are truly isolated

---

## System Architecture Overview

### Permission Model
- **Format**: `entity_action` (e.g., `students_view`, `finance_create`)
- **Entity Categories** (54 total):
  - Academic (schedules, exams, assessments, results, classes, subjects, etc.)
  - Students (admission, enrollment, discipline, fees, medical, documents)
  - Staff (assignments, performance, leaves, timesheets, roles)
  - Finance (fees, payroll, budgets, expenses, invoices, payments)
  - Attendance (class, boarding, staff)
  - Boarding (rooms, health, discipline, students)
  - Communications (announcements, messages, templates, recipients)
  - Inventory (items, movements, requisitions, purchase orders)
  - System (users, roles, permissions, logs, media)
  - Transport (drivers, routes, vehicles, trips)
  - Library, Catering, Activities, Reports, Security, Integrations, Notifications, Settings

### Role Hierarchy & Capability Matrix

| Role | ID | Perm Count | Category | Dashboard Focus |
|------|-----|------------|----------|-----------------|
| **System Administrator** | 2 | 4,456 | Technical | System Health, Users, Roles, Permissions, Logs (NO business data) |
| **Director** | 3 | 13 | Executive | Finance, Staff, Students, Attendance, Communications, Approvals |
| **School Administrator** | 4 | 20 | Operational | Activities, Communications, Staff, Students, Academic Operations |
| **Headteacher** | 5 | 13 | Academic | Academic Schedules, Students, Staff, Communications |
| **Deputy Head - Academic** | 6 | 5 | Academic | Limited Academic Oversight |
| **Class Teacher** | 7 | 6 | Teaching | My Class, My Students, Assessments, Communications |
| **Subject Teacher** | 8 | 6 | Teaching | My Subject, Students, Assessments, Exams |
| **Accountant** | 10 | 6 | Finance | Finance, Fees, Payroll, Budget, Reconciliation |
| **Inventory Manager** | 14 | 6 | Logistics | Inventory, Stock, Requisitions, Purchase Orders |
| **Boarding Master** | 18 | 7 | Boarding | Boarding Facilities, Student Welfare, Discipline |
| **Talent Development** | 21 | 10 | Activities | Sports, Music, Activities, Talent Development |

---

## TIER 1: System Administrator (ID: 2)

### ⚠️ SECURITY PRINCIPLE: Technical Authority ≠ Business Data Access

**CRITICAL DESIGN RULE**: The System Administrator is the **technical root user**, NOT a business stakeholder.
- **Root access** = Infrastructure management (users, roles, permissions, system health, logs, security)
- **NOT** = Institutional data (finance, payroll, student records, operational metrics)

This dashboard shows **system intelligence only** — answers: *"Is the system healthy and secure?"* — NOT *"How is the school performing?"*

### Role Description
Complete system access. Technical oversight, system configuration, database/codebase management, user/role/permission management, system uptime, security monitoring, and infrastructure health.

**Does NOT include**: Finance, payroll, student data, operational metrics, or business intelligence.

### Dashboard Characteristics
- **View Type**: Infrastructure & Security Focus
- **Update Frequency**: Real-time (30-second refresh)
- **Primary Use Cases**: 
  - Monitor system uptime and performance
  - Track security events and authentication
  - Manage users, roles, and permissions
  - Monitor API health and database status
  - Review system audit logs

### Summary Cards (8 cards, arranged in 2 rows × 4 columns)

#### Row 1: System Access & Security
1. **Total Active Users** 
   - Data: COUNT(users) WHERE status='active'
   - Subtitle: "Users with System Access"
   - Secondary: "Last 24h: +X new users"
   - Icon: users
   - Color: Blue

2. **Roles & Permissions Configured**
   - Data: COUNT(roles) | COUNT(permissions)
   - Subtitle: "System Security Configuration"
   - Secondary: "29 Roles | 4,456 Permissions"
   - Icon: shield
   - Color: Purple

3. **Authentication Events (24h)**
   - Data: COUNT(successful logins) | COUNT(failed attempts)
   - Subtitle: "Login Activity"
   - Secondary: "Success: X | Failed: Y"
   - API: `/system/auth-events` (NEW)
   - Color: Green

4. **Active Sessions**
   - Data: COUNT(active_sessions)
   - Subtitle: "Currently Logged In Users"
   - Secondary: "Avg session: X min"
   - API: `/system/active-sessions` (NEW)
   - Color: Cyan

#### Row 2: Infrastructure Health
5. **System Uptime**
   - Data: Current uptime percentage
   - Subtitle: "System Availability"
   - Secondary: "99.X% | Last downtime: X ago"
   - API: `/system/uptime` (NEW)
   - Color: Green

6. **System Health - Errors**
   - Data: COUNT(error_logs) in last 24h
   - Subtitle: "Error Events"
   - Secondary: "Critical: X | High: Y | Medium: Z"
   - API: `/system/health-errors` (NEW)
   - Color: Red

7. **System Health - Warnings**
   - Data: COUNT(warning_logs) in last 24h
   - Subtitle: "Warning Events"
   - Secondary: "Database: X | API: Y | Storage: Z"
   - API: `/system/health-warnings` (NEW)
   - Color: Orange

8. **API Request Load**
   - Data: Avg requests/sec, peak load
   - Subtitle: "API Performance"
   - Secondary: "Avg: X req/s | Peak: Y req/s"
   - API: `/system/api-load` (NEW)
   - Color: Yellow

### Charts (2 graphs)

1. **System Activity Over Time** (Line Chart)
   - X-axis: Last 24 hours (hourly)
   - Y-axis: API requests per second
   - Show: Request volume, response times
   - Alerts: Spikes that indicate problems

2. **Authentication & Error Trends** (Dual-axis Line Chart)
   - X-axis: Last 7 days
   - Y-axis Left: Successful logins per hour
   - Y-axis Right: System errors per hour
   - Show: Security events and system stability correlation

### Data Tables (Tabbed Interface)

- **Authentication & Access Logs**: 
  - User login/logout events
  - IP addresses, timestamps
  - Success/failure indicators
  - Last 200 events

- **Permission & Role Changes**: 
  - New roles created
  - New permissions assigned
  - Role modifications
  - Permission removals
  - Last 100 changes

- **System Audit Trail**: 
  - Database operations by admin users
  - Configuration changes
  - System maintenance events
  - Backup/restore operations
  - Last 150 events

### Permission-Aware Rendering
- **VISIBLE**: System users, roles, permissions, logs, uptime, health metrics, security events
- **HIDDEN**: Finance, payroll, student data, staff operational metrics, inventory, institutional data
- No cross-boundary visibility into business data
- Data strictly limited to infrastructure management

---

## TIER 2: Director/Principal (ID: 3)

### Role Description
Executive overview. Financial reports, transaction approvals, payroll oversight, communications, strategic KPIs.

### Dashboard Characteristics
- **View Type**: Executive Summary
- **Update Frequency**: 1-hour refresh
- **Primary Use Cases**:
  - Review daily/weekly KPIs
  - Approve financial transactions
  - Monitor communications
  - Track staff & student metrics

### Summary Cards (8 cards, arranged in 2 rows × 4 columns)

#### Row 1: Strategic KPIs
1. **Total Enrollment**
   - Data: COUNT(students) WHERE status='active'
   - Subtitle: "Current Student Population"
   - Secondary: "Growth: +X% YoY"
   - Color: Blue

2. **Staff Strength**
   - Data: COUNT(staff) teaching + non-teaching
   - Subtitle: "Total Workforce"
   - Secondary: "Teaching: X% | Non-teaching: Y%"
   - Color: Orange

3. **Financial Status**
   - Data: Fees collected vs outstanding
   - Subtitle: "Fee Collection Rate"
   - Secondary: "Collected: XYZ | Outstanding: ABC"
   - API: `/payments/stats`
   - Color: Green

4. **Attendance Rate**
   - Data: Today's attendance percentage
   - Subtitle: "Student Attendance"
   - Secondary: "Present: X% | Absent: Y%"
   - API: `/attendance/today`
   - Color: Teal

#### Row 2: Activity & Pending Items
5. **Active Communications**
   - Data: COUNT(messages sent this week)
   - Subtitle: "Weekly Communications"
   - Secondary: "Announcements: X | Messages: Y"
   - Color: Purple

6. **Pending Approvals**
   - Data: COUNT(approval workflows pending director)
   - Subtitle: "Approval Queue"
   - Secondary: "Finance: X | Academic: Y"
   - API: `/system/pending-approvals` (NEW)
   - Color: Red

7. **Payroll Status**
   - Data: Current payroll cycle status
   - Subtitle: "Payroll Processing"
   - Secondary: "Due: X | Processed: Y"
   - Color: Yellow

8. **System Status**
   - Data: System health indicator
   - Subtitle: "Infrastructure Health"
   - Secondary: "Status: Operational"
   - API: `/system/health` (NEW)
   - Color: Green

### Charts (2 graphs)
1. **Fee Collection Trend** (Line Chart)
   - X-axis: Last 12 months
   - Y-axis: Amount collected
   - Show: Target vs actual collection

2. **Student Enrollment Trend** (Line Chart)
   - X-axis: Last 4 academic years
   - Y-axis: Enrollment count
   - Show: Growth trajectory

### Data Tables (Tabbed Interface)
- **Pending Approvals**: Workflow items requiring director approval with due dates
- **Communications Log**: Announcements/messages sent to stakeholders
- **Financial Summary**: Monthly fee collection, payments, outstanding amounts

### Permission-Aware Rendering
- Show: communications, staff, students entities
- Hide: system administration, technical settings, user management
- Data filtered to summary level (no individual student/staff details)

---

## TIER 3: School Administrator (ID: 4)

### Role Description
Operational school management. Day-to-day operations, user/role management (limited), staff coordination, student management (overview level), communications.

### Dashboard Characteristics
- **View Type**: Operational Dashboard
- **Update Frequency**: 15-minute refresh
- **Primary Use Cases**:
  - Manage daily operations
  - Coordinate activities
  - Monitor staff assignments
  - Oversee student enrollment
  - Manage communications

### Summary Cards (10 cards, arranged in 2 rows × 5 columns)

#### Row 1: Operational Status
1. **Active Students**
   - Data: COUNT(students) WHERE status='active'
   - Subtitle: "Enrolled Students"
   - Secondary: "Classes: X"
   - API: `/students/stats`
   - Color: Blue

2. **Teaching Staff**
   - Data: COUNT(staff) WHERE role='teacher'
   - Subtitle: "Teaching Staff"
   - Secondary: "Present today: X%"
   - API: `/staff/stats`
   - Color: Orange

3. **Staff Activities**
   - Data: COUNT(staff_leaves) + COUNT(assignments_pending)
   - Subtitle: "Staff Coordination"
   - Secondary: "On leave: X | New assignments: Y"
   - Color: Yellow

4. **Class Timetables**
   - Data: Active timetables this term
   - Subtitle: "Academic Schedules"
   - Secondary: "Classes/week: X"
   - API: `/schedules/weekly`
   - Color: Cyan

5. **Daily Attendance**
   - Data: Student attendance today
   - Subtitle: "Daily Attendance"
   - Secondary: "Present: X% | Absent: Y%"
   - API: `/attendance/today`
   - Color: Teal

#### Row 2: Communications & Operations
6. **Announcements**
   - Data: COUNT(announcements) this week
   - Subtitle: "School Communications"
   - Secondary: "To: X recipients"
   - Color: Purple

7. **Student Admissions**
   - Data: Pending admission applications
   - Subtitle: "Admission Pipeline"
   - Secondary: "Pending: X | Approved: Y"
   - Color: Green

8. **Staff Leaves**
   - Data: Staff on leave today
   - Subtitle: "Staff Leave Status"
   - Secondary: "On leave: X"
   - Color: Red

9. **Class Distribution**
   - Data: Students per class
   - Subtitle: "Class Sizes"
   - Secondary: "Avg: X | Max: Y"
   - Color: Magenta

10. **System Performance**
    - Data: System uptime indicator
    - Subtitle: "System Status"
    - Secondary: "Uptime: 99.X%"
    - API: `/system/health` (NEW - limited)
    - Color: Green

### Charts (2 graphs)
1. **Weekly Attendance Trend** (Line Chart)
   - X-axis: Last 4 weeks (daily)
   - Y-axis: Attendance percentage
   - Show: Student attendance pattern

2. **Class Distribution** (Bar Chart)
   - X-axis: Class name
   - Y-axis: Number of students
   - Show: Balance across classes

### Data Tables (Tabbed Interface)
- **Pending Items**: Admission applications, leave requests, staff assignments
- **Today's Schedule**: Classes, activities, events for the day
- **Staff Directory**: Contact info for all active staff

### Permission-Aware Rendering
- Show: activities, communications, staff, students
- Hide: finance, system admin, technical settings
- Data limited to high-level summaries

### Sidebar Menu Structure

```
Dashboard                          (fas fa-tachometer-alt)
|
+-- Students                       (fas fa-user-graduate)
|   +-- All Students               → manage_students
|   +-- Admissions                 → manage_students_admissions
|   +-- Attendance                 → mark_attendance
|   +-- Enrollment                 → manage_students
|   +-- Student Reports            → enrollment_reports
|   +-- ID Cards                   → student_id_cards
|   +-- Family Groups              → manage_family_groups
|
+-- Academic                       (fas fa-graduation-cap)
|   +-- Classes                    → manage_classes
|   +-- Timetable                  → manage_timetable
|   +-- Results                    → view_results
|   +-- Subjects                   → manage_subjects
|   +-- Assessments                → manage_assessments
|
+-- Staff                          (fas fa-chalkboard-teacher)
|   +-- All Staff                  → manage_staff
|   +-- Attendance                 → staff_attendance
|   +-- Teachers                   → manage_teachers
|   +-- Non-Teaching Staff         → manage_non_teaching_staff
|   +-- Staff Leaves               → manage_staff
|
+-- Communications                 (fas fa-comments)
|   +-- Messages                   → manage_communications
|   +-- Announcements              → manage_announcements
|   +-- SMS                        → manage_sms
|   +-- Email                      → manage_email
|
+-- Activities                     (fas fa-running)
|   +-- All Activities             → manage_activities
|   +-- Clubs & Societies          → manage_activities
|   +-- Sports                     → manage_activities
|
+-- Calendar & Events              (fas fa-calendar-week)
|   +-- School Events              → manage_activities
|   +-- Daily Schedule             → manage_timetable
|
+-- Reports                        (fas fa-chart-bar)
|   +-- Attendance Reports         → view_attendance
|   +-- Enrollment Statistics      → enrollment_reports
|   +-- Staff Reports              → staff_performance
|
+-- Users                          (fas fa-users-cog)
    +-- Manage Users (limited)     → manage_users
```

**Menu Item Count**: 39 items (8 parent groups + 31 child items)

**Implementation**:

- Migration: `database/migrations/school_admin_sidebar_menus.sql`
- Role ID: 4 (School Administrative Officer)
- Last Updated: January 2025

---

## TIER 4: Academic Leaders (Headteacher, Deputy Heads, Teachers)

### Headteacher/Head of Department (ID: 5, 6, 63)

**Role Description**: Academic oversight, student admissions, timetabling, parent communications, discipline.

**Dashboard Characteristics**:
- **View Type**: Academic Focus
- **Update Frequency**: 30-minute refresh
- **Primary Use Cases**: Monitor classes, manage timetables, admissions, discipline

**Summary Cards (8 cards)**:
1. **Total Students** - Enrolled in department/year level - Blue
2. **Attendance Today** - Student presence percentage - Teal
3. **Class Schedules** - Active classes this week - Cyan
4. **Pending Admissions** - Applications waiting review - Green
5. **Discipline Cases** - Open discipline issues - Red
6. **Parent Communications** - Messages sent this week - Purple
7. **Student Assessments** - Recent test results summary - Yellow
8. **Class Performance** - Academic results trend - Orange

### Class Teacher (ID: 7)

**Role Description**: Classroom management, attendance tracking, student assessment, lesson planning.

**Dashboard Characteristics**:
- **View Type**: Class-Centric
- **Update Frequency**: Daily
- **Primary Use Cases**: Manage own class, track attendance, record assessments

**Summary Cards (6 cards)**:
1. **My Class** - Student count in assigned class - Blue
2. **Today's Attendance** - My class attendance - Teal
3. **Upcoming Lessons** - This week's lessons - Cyan
4. **Student Assessments** - Recent assessments recorded - Green
5. **Pending Approvals** - Assessments awaiting approval - Yellow
6. **Class Notes** - Important class-related items - Purple

**Data Tables**:
- Student roster with attendance status
- Assessment grades for current term
- Lesson plan outline for current week

### Subject Teacher (ID: 8)

**Role Description**: Subject teaching, exam supervision, assessment grading, lesson planning.

**Dashboard Characteristics**:
- **View Type**: Subject-Centric
- **Update Frequency**: Daily
- **Primary Use Cases**: Teach subject, grade assessments, plan lessons

**Summary Cards (6 cards)**:
1. **Students Teaching** - Total students across sections - Blue
2. **Sections** - Classes teaching - Cyan
3. **Assessments Due** - Pending grading tasks - Yellow
4. **Graded This Week** - Assessments completed - Green
5. **Exam Schedule** - Upcoming exams - Orange
6. **Lesson Plans** - Created this term - Purple

---

## TIER 5: Finance & Operations

### Accountant (ID: 10)

**Role Description**: Financial management, fee collection, payroll, budget reconciliation, financial reporting.

**Dashboard Characteristics**:
- **View Type**: Finance-Focused
- **Update Frequency**: Daily
- **Primary Use Cases**: Track finances, process fees, manage payroll

**Summary Cards (8 cards)**:
1. **Total Fees Collected** - Amount collected to date - Green
2. **Outstanding Fees** - Amount pending collection - Red
3. **Student Fee Status** - % of students paid up - Blue
4. **Monthly Revenue** - Revenue for current month - Teal
5. **Payroll Status** - Current payroll cycle - Orange
6. **Expenses This Month** - Total expenses - Yellow
7. **Bank Balance** - Current bank account(s) - Purple
8. **Budget vs Actual** - Monthly budget tracking - Cyan

**Charts**:
- Fee collection trend (12-month line chart)
- Expense categories (pie chart)
- Monthly cash flow (bar chart)

---

### Inventory Manager (ID: 14)

**Role Description**: Inventory control, stock management, requisitions, purchase orders.

**Dashboard Characteristics**:
- **View Type**: Logistics-Focused
- **Update Frequency**: Daily
- **Primary Use Cases**: Track stock, manage requisitions, process orders

**Summary Cards (8 cards)**:
1. **Total Items** - Count of inventory items - Blue
2. **Low Stock Alerts** - Items below minimum - Red
3. **Pending Requisitions** - Awaiting approval - Yellow
4. **Open Purchase Orders** - Pending delivery - Orange
5. **Stock Value** - Total inventory value - Green
6. **Categories** - Inventory categories - Cyan
7. **Suppliers** - Active suppliers - Purple
8. **Last Updated** - When inventory was last audited - Gray

---

## TIER 6: Support Services

### Boarding Master (ID: 18)

**Summary Cards (8 cards)**:
1. **Boarding Students** - Total in boarding
2. **Occupancy Rate** - Rooms occupied vs available
3. **Health Issues** - Students on medical care
4. **Discipline Cases** - Open discipline issues
5. **Leaves Pending** - Boarding leave requests
6. **Room Assignments** - Current room allocation
7. **Staff Present** - Boarding staff on duty
8. **Supplies Needed** - Inventory requests

### Catering/Cateress (ID: 16)

**Summary Cards (6 cards)**:
1. **Students to Feed** - Daily meal count
2. **Menu for Today** - Current day's menu
3. **Food Stock** - Inventory levels
4. **Suppliers** - Active food suppliers
5. **Meal Quality** - Feedback/ratings
6. **Budget vs Spend** - Monthly catering budget

### Talent Development Manager (ID: 21)

**Summary Cards (8 cards)**:
1. **Activities** - Active clubs/sports
2. **Participants** - Total engaged students
3. **Upcoming Events** - This month's events
4. **Equipment** - Available resources
5. **Budget** - Activities budget status
6. **Staff** - Activity coaches/facilitators
7. **Attendance** - Weekly participation rate
8. **Achievements** - Awards/recognitions

---

## TIER 7: Read-Only Roles (0 Permissions)

### Kitchen Staff, Security Staff, Janitor (IDs: 32, 33, 34)

**Dashboard**: Minimal - Personal Info Only
- Name & role assignment
- Today's schedule/shift
- Contact information
- (No data access - payroll entry only)

---

## Implementation Roadmap

### Phase 1: Core Dashboards (Priority)
1. ✅ System Administrator Dashboard - COMPLETE (current)
2. ⏳ Director Dashboard - BUILD NEXT
3. ⏳ School Administrator Dashboard - BUILD AFTER

### Phase 2: Academic Dashboards
4. ⏳ Headteacher Dashboard
5. ⏳ Class Teacher Dashboard
6. ⏳ Subject Teacher Dashboard

### Phase 3: Specialized Dashboards
7. ⏳ Accountant Dashboard
8. ⏳ Inventory Manager Dashboard
9. ⏳ Boarding Master Dashboard
10. ⏳ Catering Dashboard
11. ⏳ Talent Development Dashboard

### Phase 4: Other Roles
12. ⏳ Intern/Student Teacher Dashboard
13. ⏳ Driver Dashboard
14. ⏳ Chaplain Dashboard
15. ⏳ Deputy Head Dashboards
16. ⏳ Read-only role pages

---

## API Endpoints Required

### Already Implemented ✅
- `GET /api?route=students&action=stats` → /students/stats
- `GET /api?route=attendance&action=today` → /attendance/today
- `GET /api?route=staff&action=stats` → /staff/stats
- `GET /api?route=payments&action=stats` → /payments/stats
- `GET /api?route=schedules&action=weekly` → /schedules/weekly

### Need Implementation ⏳

#### System Admin Only
- `GET /api?route=system&action=health` → System health status
- `GET /api?route=system&action=pending-approvals` → Approval workflows
- `GET /api?route=activities&action=stats` → System-wide activities
- `GET /api?route=users&action=stats` → User statistics

#### Director-Accessible
- `GET /api?route=payments&action=collection-trend` → Fee trends (12-month)
- `GET /api?route=system&action=pending-approvals` → Director's approval queue

#### School Admin-Accessible
- `GET /api?route=activities&action=list` → Active activities
- `GET /api?route=admissions&action=pending` → Pending admissions

#### Teacher-Accessible
- `GET /api?route=attendance&action=my-class` → My class attendance
- `GET /api?route=assessments&action=my-results` → My class assessments
- `GET /api?route=schedules&action=my-lessons` → My lesson plan

#### Finance-Accessible
- `GET /api?route=payments&action=fee-status` → Fee collection by student
- `GET /api?route=finance&action=monthly-report` → Monthly financial summary
- `GET /api?route=finance&action=payroll-status` → Payroll cycle status

#### Inventory-Accessible
- `GET /api?route=inventory&action=stock-status` → Current stock levels
- `GET /api?route=inventory&action=low-stock-alerts` → Low stock items
- `GET /api?route=inventory&action=requisitions-pending` → Pending requisitions

---

## Permission-Based UI Rendering Strategy

### Implementation Approach

```javascript
// 1. Frontend checks user's role(s)
const userRoles = getCurrentUserRoles(); // ['System Administrator']

// 2. Define role-dashboard mapping
const ROLE_DASHBOARD_MAP = {
  2: 'system_administrator',   // System Admin
  3: 'director',               // Director
  4: 'school_administrator',   // School Admin
  5: 'headteacher',            // Headteacher
  7: 'class_teacher',          // Class Teacher
  10: 'accountant',            // Accountant
  // ... etc
};

// 3. Load appropriate dashboard controller
const dashboardController = getDashboardForRole(userRoles[0]);

// 4. Dashboard controller conditionally renders cards
// based on permission grants
if (hasPermission('students_view')) {
  renderStudentCard();
}
if (hasPermission('finance_view')) {
  renderFinanceCard();
}
```

### Card Visibility Rules

- **System Admin**: All cards visible
- **Director**: Only communications, staff, students visible
- **School Admin**: Only activities, communications, staff, students visible
- **Teachers**: Only students, communications, assessments visible
- **Accountant**: Only finance-related cards visible
- **Inventory Manager**: Only inventory cards visible
- **Other specialized**: Role-specific subset only

### Multi-Role Handling

If user has multiple roles (e.g., Class Teacher + Department Head):
1. Determine dominant role (highest permission scope)
2. Load primary dashboard for dominant role
3. Offer role switcher in header ("Switch to: Department Head view")
4. Load secondary dashboard on selection

---

## Next Steps

1. **Build Director Dashboard** 
   - 8 summary cards (strategic KPIs)
   - 2 charts (fee trends, enrollment trends)
   - 3 data tables (approvals, communications, financial summary)
   - Backend endpoints: `/payments/collection-trend`, `/system/pending-approvals`

2. **Build School Admin Dashboard**
   - 10 summary cards (operational status)
   - 2 charts (attendance trend, class distribution)
   - 3 data tables (pending items, daily schedule, staff directory)
   - Backend endpoints: `/activities/list`, `/admissions/pending`

3. **Implement Permission-Based Routing**
   - Middleware to detect user role on dashboard load
   - Redirect to appropriate dashboard controller
   - Fallback for unrecognized roles

4. **Implement Card Visibility Logic**
   - Dashboard controller checks `hasPermission()` before rendering
   - Gracefully handle missing permissions (hide card, no error)
   - Load fallback dashboard if user has no recognized role

---

**Document Version**: 1.0  
**Last Updated**: Dec 28, 2025  
**Status**: Planning Complete - Ready for Implementation
