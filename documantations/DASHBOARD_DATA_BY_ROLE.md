# Dashboard Data Display by User Role
**Kingsway Academy Management System**

---

## Overview

The Kingsway system uses **role-based dashboard routing** where each user role sees a permission-specific dashboard displaying only data relevant to their responsibilities.

**Key Principle:** Each role sees ONLY its role-specific dashboard content. No cross-role data leakage.

**Architecture:**
1. User logs in → Session role stored
2. Dashboard page (`/pages/dashboard.php`) loads
3. Dashboard Router (`dashboard_router.js`) detects user role
4. Role-specific dashboard script loads dynamically
5. Dashboard controller fetches role-specific API data
6. UI renders with appropriate KPIs, charts, and tables

---

## Dashboard Data by Role

### 1. **System Administrator** (Role ID: 2)
**Scope:** Infrastructure & Technical Monitoring Only

#### Summary Cards (6):
- **Active Users** - Successful logins (24h) | Failed login attempts
- **RBAC Configuration** - Total roles (29) | Total permissions (4,456)
- **Active Sessions** - Currently logged-in users | Average session duration
- **System Uptime** - Availability percentage | Last downtime
- **Critical Errors** - Count from last 24 hours | Error severity breakdown
- **System Warnings** - Database, API, storage warnings

#### Charts:
- **System Health Timeline** - Error/Warning trends over 24 hours
- **API Request Load** - Requests per second (avg & peak)
- **Hourly Request Distribution** - Traffic patterns throughout day
- **Component Uptime** - Database, API, Web Server, File Storage

#### Tables:
- **Recent Auth Events** - Login/logout audit trail with IP addresses
- **System Errors** - Critical and high-severity errors with timestamps
- **System Warnings** - Disk space, memory, backup status

#### API Endpoints Used:
```
GET /api/system/auth-events
GET /api/system/active-sessions
GET /api/system/uptime
GET /api/system/health-errors
GET /api/system/health-warnings
GET /api/system/api-load
```

**⚠️ SECURITY NOTE:** System Admin sees ONLY technical/infrastructure data. NO business data (finance, students, staff operations, inventory).

---

### 2. **Director / Principal** (Role ID: 3)
**Scope:** Executive/Strategic Overview

#### Summary Cards (8):
- **Total Students** - Current enrollment | Growth percentage (12%)
- **Teaching Staff** - Active teachers | Staffing ratio
- **Fees Collected (MTD)** - Monthly collection target | Percentage collected
- **Today's Attendance** - Student attendance percentage | Absent count
- **Pending Approvals** - Workflow items awaiting director signature
- **Students by Gender** - Male/Female breakdown
- **Overdue Payments** - Count of past-due student accounts
- **Staff Attendance** - Teaching staff presence today

#### Charts:
- **Fee Collection Trends** - Historical monthly collection pattern
- **Student Enrollment Growth** - Quarterly enrollment progression
- **Staff Statistics** - Breakdown by department/role
- **Attendance Trends** - Weekly attendance percentage pattern
- **Pending Approvals Status** - High/Normal priority breakdown

#### Tables:
- **Pending Approvals** - Finance, Academic, Operations approvals with due dates
- **Fee Collection Summary** - Monthly breakdown of collected vs. target
- **Recent Communications** - Notifications sent to parents/staff
- **Staff & Student Metrics** - Key performance indicators

#### API Endpoints Used:
```
GET /api/students/stats
GET /api/staff/stats
GET /api/payments/stats
GET /api/attendance/today
GET /api/payments/collection-trends
GET /api/system/pending-approvals
```

**Auto-Refresh:** 1 hour

---

### 3. **School Administrator / HOD** (Role ID: 4)
**Scope:** Operational Administration

#### Summary Cards (8):
- **Pending Communications** - Unsent announcements/emails
- **Active Classes** - Currently running classes
- **Class Activities** - Scheduled activities for the week
- **Student Admissions** - Pending applications
- **Upcoming Events** - Calendar events this week
- **Class Requisitions** - Pending supply requests
- **Parent Messages** - Unread messages from parents
- **System Health** - Quick system status check

#### Features:
- Manage communications (emails, SMS, announcements)
- Class schedule management
- Activity calendar
- Admission processing
- Inventory requisitions
- Parent communication interface

**Auto-Refresh:** 1 hour

---

### 4. **Headteacher** (Role ID: 5)
**Scope:** Academic Oversight & Administration

#### Summary Cards (8):
- **Total Students** - Department/year level enrollment
- **Today's Attendance** - Student presence percentage
- **Class Schedules** - Active classes this week
- **Pending Admissions** - Applications awaiting review
- **Discipline Cases** - Open discipline issues
- **Parent Communications** - Messages sent this week
- **Student Assessments** - Recent test results summary
- **Class Performance** - Academic results trend

#### Charts:
- **Weekly Class Attendance Trend** - 7-day attendance pattern
- **Academic Performance by Class** - Grade distribution and progress

#### Tables:
- **Pending Admissions** - Applications with review status
- **Open Discipline Cases** - Active discipline issues with actions

#### Data Isolation:
- Sees all student academic data
- Sees all class schedules
- Does NOT see: Finance data, staff salary, system data, other departments' operations

**Auto-Refresh:** 30 minutes

---

### 5. **Deputy Head - Academic** (Role ID: 6)
**Scope:** Academic Support, Admissions, Timetabling

#### Summary Cards:
- **Pending Admissions** - Applications under review
- **Class Timetables** - Master schedule status
- **Academic Calendar** - Key dates and terms
- **Form Class Status** - Student progression
- **Teacher Allocation** - Subject assignments
- **Assessment Schedule** - Upcoming exams/tests

#### Features:
- Admission management
- Timetable creation and management
- Academic calendar
- Class progression
- Teacher scheduling
- Exam scheduling

---

### 6. **Class Teacher** (Role ID: 7)
**Scope:** My Class Focus (Data Isolation)

#### Summary Cards (6):
- **My Students** - Count of assigned class students
- **Today's Attendance** - Class attendance percentage
- **Pending Assessments** - Grading tasks
- **Lesson Plans** - Lesson prep status
- **Class Communications** - Messages to class/parents
- **Class Performance** - Overall academic progress

#### Charts:
- **Weekly Attendance Trend** - 7-day attendance pattern (MY CLASS ONLY)
- **Assessment Performance** - Student grade distribution

#### Tables:
- **Today's Class Schedule** - Today's lessons
- **Student Assessment Status** - Individual student grades and progress

#### Data Isolation:
- Sees ONLY their assigned class data
- Cannot see other teachers' classes
- Cannot see other classes' students or grades

**Auto-Refresh:** 30 minutes

---

### 7. **Subject Teacher** (Role ID: 8)
**Scope:** My Subject & Assessments

#### Summary Cards:
- **My Students** - All students in my subject (across classes)
- **Pending Exams** - Assessment schedule
- **Grade Entry Status** - Grading completion
- **Subject Performance** - Class averages and trends
- **Resource Materials** - Available teaching resources
- **Student Engagement** - Attendance in my lessons

#### Charts:
- **Subject Performance by Class** - Grade distribution across classes
- **Assessment Trends** - Historical performance patterns

#### Tables:
- **Student Performance** - Individual grades and progress
- **Assessment Schedule** - Upcoming exams/tests

#### Data Isolation:
- Sees only students in their assigned subject
- Cannot see other subjects or non-enrolled students
- Cannot see whole-school data

---

### 8. **Intern / Student Teacher** (Role ID: 9)
**Scope:** Limited Teaching & Observation

#### Summary Cards:
- **Assigned Classes** - Classes under supervision
- **Lesson Observations** - Feedback from mentor teacher
- **Teaching Resources** - Available materials
- **Student Performance** - Classes I'm teaching
- **Development Progress** - Competency checklist

#### Limitations:
- Read-only dashboard (cannot enter grades or make changes)
- See only assigned classes
- Restricted data access

---

### 9. **School Accountant** (Role ID: 10)
**Scope:** Financial Management & Accounting

#### Summary Cards (8):
- **Total Fees Due** - Outstanding student fees
- **Fees Collected (MTD)** - Month-to-date collection
- **Outstanding Invoices** - Pending vendor payments
- **Petty Cash** - Current petty cash balance
- **Bank Balance** - Account balance snapshot
- **Payroll Due** - Upcoming staff salaries
- **Budget Allocation** - Budget vs. actual spending
- **Collection Rate** - % of fees collected

#### Charts:
- **Monthly Fee Collection Trend** - Historical collection pattern
- **Budget vs. Actual Spending** - Expenditure comparison
- **Bank Balance Trend** - Cash flow pattern
- **Fee Collection by Category** - Boarding, tuition, others
- **Payroll Expense Trend** - Historical salary costs

#### Tables:
- **Student Outstanding Fees** - Delinquent accounts
- **Vendor Invoices Pending** - Unpaid invoices
- **Recent Transactions** - Bank and petty cash movements
- **Monthly Budget Report** - Income vs. expenditure

#### API Endpoints Used:
```
GET /api/dashboard/accountant/financial
GET /api/dashboard/accountant/payments
```

**Auto-Refresh:** 30 seconds

---

### 10. **Boarding Master / Matron** (Role ID: 18)
**Scope:** Student Boarding & Welfare

#### Summary Cards:
- **Boarders Present Today** - Count of students in dormitory
- **Boarding Applications** - Pending boarding requests
- **Health Issues** - Students with medical concerns
- **Discipline Cases** - Boarding conduct issues
- **Meal Summary** - Today's meal preparation
- **Room Occupancy** - Dorm capacity status
- **Parent Communications** - Messages from parents
- **Weekend Activities** - Planned activities

#### Charts:
- **Occupancy Trend** - Historical boarding population
- **Health Issues Trend** - Medical cases by type
- **Meal Preferences** - Dietary requirements

#### Tables:
- **Student Roster** - Boarders and their room assignments
- **Health Register** - Current health status and medications
- **Discipline Register** - Boarding conduct incidents

#### Data Isolation:
- Sees only boarding students
- Cannot see day students (unless they also board)
- Cannot see academic or financial data

---

### 11. **Store Manager / Inventory Manager** (Role ID: 14)
**Scope:** Inventory & Stock Management

#### Summary Cards:
- **Low Stock Items** - Items below minimum threshold
- **Pending Requisitions** - Awaiting approval/supply
- **Monthly Expenditure** - Inventory spending this month
- **Suppliers** - Active supplier count
- **Stock Valuation** - Total inventory value
- **Item Categories** - Count of tracked items
- **Expiring Stock** - Items nearing expiration
- **Recent Orders** - Latest purchase orders

#### Charts:
- **Stock Level Trends** - High-velocity items inventory
- **Expenditure by Category** - Spending breakdown
- **Supplier Performance** - Delivery metrics
- **Stock Turnover** - Item movement velocity

#### Tables:
- **Inventory Report** - Stock levels and reorder points
- **Pending Requisitions** - Items requested by departments
- **Recent Purchases** - Order history
- **Low Stock Alert** - Critical inventory items

#### Data Isolation:
- Sees only inventory/stock data
- Cannot see financial or academic data

---

### 12. **Catering Manager / Cook Lead** (Role ID: 16)
**Scope:** Kitchen & Food Service

#### Summary Cards:
- **Today's Meal Count** - Students/staff to feed
- **Menu Status** - Today's menu prep
- **Food Inventory** - Stock availability
- **Dietary Requirements** - Special diets needed
- **Budget Remaining** - Catering budget status
- **Suppliers** - Active food suppliers
- **Quality Feedback** - Recent feedback scores
- **Staff Present** - Kitchen staff today

#### Charts:
- **Daily Meal Count Trend** - Meal service volume
- **Food Cost Trends** - Spending patterns
- **Dietary Requirement Distribution** - Special diets breakdown
- **Supplier Performance** - Delivery quality

#### Tables:
- **Today's Meal Roster** - Students/staff to feed by meal
- **Food Inventory** - Available food stock
- **Menu Plan** - Weekly/monthly menu
- **Dietary Requirements Register** - Special diet students

---

### 13. **Chaplain / School Counselor** (Role ID: 24)
**Scope:** Pastoral Care & Student Welfare

#### Summary Cards:
- **Students Counseled** - Sessions this month
- **Follow-up Cases** - Ongoing support needed
- **Referrals** - External support referrals
- **Parent Conferences** - Scheduled meetings
- **Chapel Services** - Services scheduled
- **Spiritual Programs** - Activities planned
- **Crisis Cases** - Urgent intervention needed
- **Community Outreach** - Outreach programs

#### Charts:
- **Counseling Sessions Trend** - Monthly session volume
- **Issue Categories** - Types of counseling issues
- **Follow-up Status** - Ongoing cases progress
- **Impact Assessment** - Student improvement metrics

#### Tables:
- **Active Cases** - Ongoing student support cases
- **Referrals Log** - External referrals and status
- **Chapel Schedule** - Upcoming services
- **Parent Conference Log** - Meeting history

#### Data Isolation:
- Sees confidential counseling records (FERPA protected)
- Cannot see academic or financial data

---

### 14. **Driver / Transport Manager** (Role ID: 23)
**Scope:** Student Transport & Vehicle Management

#### Summary Cards:
- **Routes Today** - Active transport routes
- **Students Transported** - Total students today
- **Vehicle Status** - Operational vehicles
- **Maintenance Due** - Upcoming maintenance
- **Fuel Consumption** - Fuel usage status
- **Route Efficiency** - On-time delivery percentage
- **Safety Incidents** - Recent incidents (if any)
- **Staff Present** - Drivers/assistants today

#### Charts:
- **Daily Student Count Trend** - Transport volume
- **Fuel Consumption Trend** - Fuel efficiency
- **Route Performance** - On-time delivery by route
- **Vehicle Maintenance Schedule** - Upcoming services

#### Tables:
- **Active Routes** - Routes running today with students
- **Vehicle Inventory** - Fleet status and assignments
- **Maintenance Log** - Service history
- **Student Roster by Route** - Students per route

---

### 15. **Talent Development Manager / HOD Talent** (Role ID: 21)
**Scope:** Sports, Music, Activities & Talent Development

#### Summary Cards:
- **Active Clubs** - Sports and activity clubs
- **Participants** - Total students in talent programs
- **Competitions Scheduled** - Upcoming events
- **Awards Won** - Achievements this term
- **Equipment Status** - Sports equipment inventory
- **Practice Sessions** - Scheduled this week
- **Coaching Staff** - Available coaches/facilitators
- **Budget Allocation** - Talent program spending

#### Charts:
- **Participation Trends** - Student enrollment by activity
- **Competition Performance** - Event results
- **Equipment Utilization** - Usage patterns
- **Activity Distribution** - Students per activity

#### Tables:
- **Club Roster** - Members of each club/team
- **Competition Calendar** - Scheduled events
- **Equipment Inventory** - Sports/activity equipment
- **Coaching Staff** - Availability and assignments

---

### 16. **General Staff** (Roles 32-34: Kitchen Staff, Security, Janitors)
**Scope:** Read-Only Personal Information

#### Summary Cards (Limited):
- **My Profile** - Name, ID, contact
- **My Schedule** - Today's shift/assignment
- **My Department** - Supervisor, team info
- **Work Assignments** - Current tasks

#### Data Available:
- Personal information
- Assigned schedule
- Department contact info
- Basic notifications from management

#### Restrictions:
- Read-only access
- Cannot modify any data
- No access to: Student/staff sensitive data, finances, operations

---

## Summary Table by Role

| Role | Summary Cards | Charts | Tables | Data Type | Refresh |
|------|---------------|--------|--------|-----------|---------|
| System Admin | 6 | 4 | 3 | Infrastructure Only | 30s |
| Director | 8 | 5 | 4 | Executive/Strategic | 1h |
| School Admin | 8 | - | - | Operational | 1h |
| Headteacher | 8 | 2 | 2 | Academic Only | 30m |
| Deputy Head | 6 | - | - | Academic/Admin | 30m |
| Class Teacher | 6 | 2 | 2 | My Class Only | 30m |
| Subject Teacher | 6 | 2 | 2 | My Subject Only | 30m |
| Intern Teacher | 5 | - | - | Read-Only | 1h |
| Accountant | 8 | 5 | 4 | Financial Only | 30s |
| Boarding Master | 8 | 3 | 3 | Boarding Only | 30m |
| Inventory Manager | 8 | 4 | 4 | Stock Only | 1h |
| Catering Manager | 8 | 4 | 4 | Food Service Only | 30m |
| Chaplain | 8 | 4 | 3 | Counseling Only | 1h |
| Driver | 8 | 4 | 4 | Transport Only | 1h |
| Talent Manager | 8 | 4 | 4 | Activities Only | 1h |
| General Staff | 4 | - | - | Read-Only | - |

---

## Security & Data Isolation Principles

### Role-Based Access Control (RBAC)
- **Each dashboard is role-specific** - loaded dynamically via router
- **API endpoints validate authorization** - backend enforces role restrictions
- **No cross-role data leakage** - each role sees only their data
- **Session-based authentication** - user role stored in session

### Data Isolation Examples
- **Class Teachers:** Cannot see other classes' data
- **Subject Teachers:** Cannot see subjects they don't teach
- **Accountant:** Cannot see student academic records
- **Boarding Master:** Cannot see day students
- **System Admin:** Cannot see business data (only infrastructure)

### Frontend Security
- Role detected in `dashboard_router.js`
- Appropriate dashboard script loaded dynamically
- API calls pass role context
- Fallback to login if role detection fails

### Backend Security
- Middleware validates token and role
- Every API endpoint enforces permissions
- Database queries filtered by role/assignment
- Invalid requests rejected at API layer

---

## API Architecture

### Dashboard API Endpoints (Summarized)

```
SYSTEM (Admin only):
  GET /api/system/auth-events
  GET /api/system/active-sessions
  GET /api/system/uptime
  GET /api/system/health-errors
  GET /api/system/health-warnings
  GET /api/system/api-load

ACADEMIC (Teachers, Headteacher):
  GET /api/students/stats
  GET /api/attendance/today
  GET /api/schedules/weekly
  GET /api/assessments/pending

FINANCIAL (Director, Accountant):
  GET /api/payments/stats
  GET /api/payments/collection-trends
  GET /api/budget/allocation
  GET /api/expenses/summary

OPERATIONAL (Admin, HOD):
  GET /api/activities/calendar
  GET /api/communications/pending
  GET /api/admissions/pending

BOARDING (Boarding Master):
  GET /api/boarding/occupancy
  GET /api/boarding/health-register
  GET /api/boarding/roster

INVENTORY (Store Manager):
  GET /api/inventory/stock-levels
  GET /api/inventory/requisitions
  GET /api/inventory/suppliers
```

---

## Implementation Notes

1. **Stateless Design:** Dashboard pages load role-specific content based on session role
2. **Dynamic Loading:** Dashboard scripts loaded only when needed
3. **Error Handling:** Graceful fallbacks to sample/demo data if API fails
4. **Performance:** Parallel API calls via Promise.allSettled()
5. **Refresh Intervals:** 30-second (system) to 1-hour (strategic) based on role criticality
6. **Responsive Design:** All dashboards work on desktop, tablet, mobile

---

**Last Updated:** 28 December 2025
**System Version:** Kingsway Academy 2.0
**Architecture:** Role-Based Dashboard Routing with RBAC API Validation
