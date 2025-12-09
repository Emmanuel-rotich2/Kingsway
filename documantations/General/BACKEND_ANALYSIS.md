# Backend Analysis - Kingsway Academy System

## Database Overview

**Database Name:** `KingsWayAcademy`  
**Tables:** 344 total  
**Views:** 42 pre-computed views for reports and dashboards  
**Connection:** `/opt/lampp/bin/mysql -u root -padmin123 KingsWayAcademy`

---

## 1. CORE DATA MODULES & ENDPOINTS

### 1.1 STUDENTS MODULE
**Endpoint:** `/api/students`  
**Controller:** `StudentsController.php`  
**API Class:** `StudentsAPI.php` (3021 lines)

#### Data Structure (GET /api/students)
```
RESPONSE FORMAT: Array of nested objects
└── students[] (array)
    ├── id (int)
    ├── admission_no (string) - unique
    ├── first_name (string)
    ├── middle_name (string, nullable)
    ├── last_name (string)
    ├── date_of_birth (date)
    ├── gender (enum: male, female, other)
    ├── stream_id (int, FK) → class_streams
    ├── student_type_id (int, FK)
    ├── user_id (int, FK, nullable)
    ├── admission_date (date)
    ├── nemis_number (string, nullable)
    ├── upi (string, nullable, unique)
    ├── upi_status (enum: not_assigned, assigned, transferred, pending)
    ├── status (enum: active, inactive, graduated, transferred, suspended)
    ├── photo_url (string, nullable)
    ├── qr_code_path (string, nullable)
    ├── is_sponsored (boolean)
    ├── sponsor_name (string, nullable)
    ├── sponsor_type (enum: partial, full, conditional)
    ├── sponsor_waiver_percentage (decimal)
    ├── class_name (string - nested from classes table)
    ├── stream_name (string - nested from class_streams table)
    ├── blood_group (string, nullable)
    ├── created_at (timestamp)
    └── updated_at (timestamp)

PAGINATION: {page, limit, total, total_pages}
```

#### Available Endpoints
- `GET /api/students` - List all students (paginated)
- `GET /api/students/{id}` - Single student
- `POST /api/students` - Create student
- `PUT /api/students/{id}` - Update student
- `DELETE /api/students/{id}` - Delete student
- `GET /api/students/profile/get/{id}` - Full profile
- `GET /api/students/medical/{id}` - Medical records
- `GET /api/students/discipline/{id}` - Discipline records
- `GET /api/students/documents/{id}` - Student documents
- `GET /api/students/parents/{id}` - Parent information

#### Related Tables
- `students` - Main student data (28 fields)
- `student_registrations` - Registration history
- `student_types` - Student type classification
- `student_parents` - Parent associations
- `student_discipline` - Discipline records
- `student_suspension` - Suspension data
- `student_promotions` - Promotion history
- `student_attendance` - Daily attendance

---

### 1.2 STAFF MODULE
**Endpoint:** `/api/staff`  
**Controller:** `StaffController.php`  
**API Class:** `StaffAPI.php`

#### Data Structure (GET /api/staff)
```
RESPONSE FORMAT: Array of nested objects
└── staff[] (array)
    ├── id (int)
    ├── staff_number (string, unique)
    ├── first_name (string)
    ├── middle_name (string, nullable)
    ├── last_name (string)
    ├── email (string, unique)
    ├── phone (string, nullable)
    ├── gender (enum: male, female, other)
    ├── date_of_birth (date)
    ├── staff_type_id (int, FK)
    ├── staff_category (string)
    ├── department_id (int, FK)
    ├── designation (string)
    ├── employment_status (enum: active, contract, leave, retired, terminated)
    ├── hire_date (date)
    ├── kra_pin (string, nullable)
    ├── nssf_number (string, nullable)
    ├── nhif_number (string, nullable)
    ├── bank_account (string, nullable)
    ├── basic_salary (decimal)
    ├── qualifications (text, nullable)
    ├── photo_url (string, nullable)
    ├── created_at (timestamp)
    └── updated_at (timestamp)
```

#### Available Endpoints
- `GET /api/staff` - List all staff
- `GET /api/staff/{id}` - Single staff member
- `POST /api/staff` - Create staff
- `PUT /api/staff/{id}` - Update staff
- `GET /api/staff/attendance/{id}` - Staff attendance
- `GET /api/staff/leave/{id}` - Leave records
- `GET /api/staff/qualifications/{id}` - Qualifications
- `GET /api/staff/performance/{id}` - Performance reviews

#### Related Tables
- `staff` - Main staff data
- `staff_types` - Teaching vs non-teaching
- `staff_categories` - Department categories
- `staff_attendance` - Attendance tracking
- `staff_leaves` - Leave management
- `staff_qualifications` - Academic qualifications
- `staff_contracts` - Employment contracts
- `staff_performance_reviews` - Annual reviews
- `staff_allowances` - Additional allowances
- `staff_deductions` - Salary deductions
- `staff_loans` - Staff loans

---

### 1.3 ACADEMIC MODULE
**Endpoint:** `/api/academic`  
**Controller:** `AcademicController.php`  
**API Class:** `AcademicAPI.php`

#### Data Structure (GET /api/academic)
```
RESPONSE FORMAT: Nested hierarchy
└── academic_years[] (array)
    ├── id (int)
    ├── year (varchar) - e.g., "2025"
    ├── start_date (date)
    ├── end_date (date)
    ├── is_current (boolean)
    ├── status (enum: draft, active, closed, archived)
    └── terms[] (array)
        ├── id (int)
        ├── name (varchar) - "Term 1", "Term 2", etc.
        ├── start_date (date)
        ├── end_date (date)
        └── classes[] (array)
            ├── id (int)
            ├── name (varchar)
            ├── class_level_id (int, FK)
            ├── total_students (int)
            └── streams[] (array)
                ├── id (int)
                ├── stream_name (varchar)
                ├── class_teacher_id (int, FK)
                └── students[] (array) [nested relationship]
                    ├── student_id (int)
                    ├── admission_no (varchar)
                    ├── name (varchar)
                    └── ...
```

#### Available Endpoints
- `GET /api/academic/years` - All academic years
- `GET /api/academic/years/{id}` - Single year
- `GET /api/academic/terms` - All terms
- `GET /api/academic/classes` - All classes
- `GET /api/academic/classes/{id}` - Single class with streams
- `GET /api/academic/assessments/{id}` - Class assessments
- `GET /api/academic/results/{id}` - Class results

#### Related Tables
- `academic_years` - School years
- `academic_terms` - School terms
- `classes` - Class definitions
- `class_streams` - Class divisions
- `class_enrollments` - Student enrollment
- `assessments` - Assessment definitions
- `assessment_results` - Student scores
- `learning_areas` - Subjects/learning areas
- `learning_outcomes` - Curriculum outcomes
- `grading_scales` - Grading rubrics
- `grade_rules` - Grade calculation rules

---

### 1.4 FINANCE MODULE
**Endpoint:** `/api/payments`  
**Controller:** `PaymentsController.php`  
**API Class:** `PaymentsAPI.php` + Reports

#### Data Structure
```
RESPONSE FORMAT: Complex nested structure
└── transactions[] (array)
    ├── id (int)
    ├── payment_date (date)
    ├── student_id (int, FK)
    ├── student_admission_no (string)
    ├── student_name (string)
    ├── amount_paid (decimal)
    ├── payment_method (enum: bank, mpesa, cash, check, bank_transfer)
    ├── mpesa_receipt (string, nullable)
    ├── receipt_number (string, unique)
    ├── reference (string, nullable)
    ├── balance_before (decimal)
    ├── balance_after (decimal)
    ├── reconciliation_status (enum: unreconciled, reconciled, disputed)
    ├── term_id (int, FK)
    ├── recorded_by (int, FK → users)
    ├── created_at (timestamp)
    └── updated_at (timestamp)

FEE STRUCTURE: Nested objects
├── id (int)
├── class_id (int, FK)
├── academic_year_id (int, FK)
├── academic_term_id (int, FK)
├── is_active (boolean)
├── created_at (timestamp)
└── fee_items[] (array)
    ├── id (int)
    ├── fee_type_id (int, FK)
    ├── amount (decimal)
    ├── is_mandatory (boolean)
    ├── repeats_monthly (boolean)
    ├── repeat_frequency (int)
    └── ...

STUDENT BALANCE: Single object
├── student_id (int)
├── student_name (string)
├── current_balance (decimal)
├── balance_status (enum: paid, partial, overdue)
├── days_overdue (int)
├── arrears_amount (decimal)
├── total_due (decimal)
└── settlement_plan_id (int, nullable)
```

#### Available Endpoints
- `GET /api/payments` - List payments (filterable)
- `GET /api/payments/{id}` - Single payment
- `POST /api/payments` - Record payment
- `GET /api/payments/fees/structure/{classId}` - Fee structure
- `GET /api/payments/balances/{studentId}` - Student balance
- `GET /api/payments/reports/arrears` - Arrears report
- `GET /api/payments/reports/collection` - Collection report
- `GET /api/payments/reports/bank-reconciliation` - Bank reconciliation

#### Related Tables
- `payment_transactions` - Payment records (1000s of records expected)
- `student_fee_balances` - Current balances per student
- `student_fee_obligations` - Fee obligations
- `fee_structures` - Fee definitions
- `fee_structures_detailed` - Fee breakdown
- `fee_types` - Fee categories
- `fee_discounts_waivers` - Discounts
- `fee_reminders` - Payment reminders
- `arrears_settlement_plans` - Payment plans
- `financial_transactions` - GL entries
- `bank_transactions` - Bank reconciliation
- `payment_allocations` - Multi-item allocations

---

### 1.5 ATTENDANCE MODULE
**Endpoint:** `/api/attendance`  
**Controller:** `AttendanceController.php`  
**API Class:** `AttendanceAPI.php`

#### Data Structure
```
RESPONSE FORMAT: Array with daily records
└── attendance_records[] (array)
    ├── id (int)
    ├── attendance_date (date)
    ├── student_id (int, FK)
    ├── class_stream_id (int, FK)
    ├── status (enum: present, absent, late, excused, exempted)
    ├── recorded_by (int, FK → users)
    ├── notes (text, nullable)
    ├── created_at (timestamp)
    └── updated_at (timestamp)

SUMMARY VIEW: Aggregated data
├── student_id (int)
├── student_name (string)
├── term (varchar)
├── total_school_days (int)
├── days_present (int)
├── days_absent (int)
├── days_late (int)
├── attendance_percentage (decimal)
└── status (enum: good, warning, critical)
```

#### Available Endpoints
- `GET /api/attendance` - List attendance (filterable)
- `GET /api/attendance/{id}` - Single record
- `POST /api/attendance` - Record attendance
- `PUT /api/attendance/{id}` - Update attendance
- `GET /api/attendance/summary/{classId}` - Class summary
- `GET /api/attendance/student/{studentId}` - Student history

#### Related Tables
- `student_attendance` - Daily records
- `staff_attendance` - Staff attendance
- `attendance_views` - Pre-computed summaries

---

### 1.6 INVENTORY MODULE
**Endpoint:** `/api/inventory`  
**Controller:** `InventoryController.php`  
**API Class:** `InventoryAPI.php`

#### Data Structure
```
RESPONSE FORMAT: Hierarchical with stock levels
└── inventory_items[] (array)
    ├── id (int)
    ├── item_code (string, unique)
    ├── item_name (string)
    ├── description (text, nullable)
    ├── category_id (int, FK) → inventory_categories
    ├── unit_of_measure (varchar) - "pieces", "liters", "kg", etc.
    ├── reorder_level (int)
    ├── current_quantity (int)
    ├── unit_price (decimal)
    ├── total_value (decimal)
    ├── status (enum: active, discontinued, damaged, obsolete)
    ├── supplier_id (int, FK, nullable)
    ├── last_updated (timestamp)
    └── batches[] (array)  [nested if tracking by batch]
        ├── batch_id (int)
        ├── batch_number (string)
        ├── quantity (int)
        ├── expiry_date (date, nullable)
        └── ...

REQUISITION STRUCTURE: Workflow data
├── id (int)
├── requisition_number (string, unique)
├── requested_by (int, FK → staff)
├── department_id (int, FK)
├── status (enum: draft, submitted, approved, fulfilled, rejected)
├── requested_date (date)
├── required_date (date)
└── items[] (array)
    ├── item_id (int, FK)
    ├── quantity_requested (int)
    ├── quantity_allocated (int)
    ├── status (enum: pending, allocated, fulfilled, rejected)
    └── ...
```

#### Available Endpoints
- `GET /api/inventory` - List items (with stock levels)
- `GET /api/inventory/{id}` - Single item detail
- `POST /api/inventory` - Add item
- `PUT /api/inventory/{id}` - Update item/quantity
- `GET /api/inventory/categories` - Item categories
- `GET /api/inventory/requisitions` - Requisition list
- `POST /api/inventory/requisitions` - Create requisition
- `GET /api/inventory/allocations` - Active allocations
- `GET /api/inventory/reports/low-stock` - Low stock items

#### Related Tables
- `inventory_items` - Item master data
- `inventory_categories` - Item categories
- `item_batches` - Batch tracking
- `item_serials` - Serial tracking
- `inventory_requisitions` - Requisition workflow
- `requisition_items` - Line items
- `inventory_allocations` - Allocation tracking
- `inventory_transactions` - Movement history
- `inventory_counts` - Physical counts
- `inventory_adjustments` - Variance adjustments
- `suppliers` - Vendor data
- `purchase_orders` - Purchase tracking

---

### 1.7 COMMUNICATIONS MODULE
**Endpoint:** `/api/communications`  
**Controller:** `CommunicationsController.php`  
**API Class:** `CommunicationsAPI.php`

#### Data Structure
```
RESPONSE FORMAT: Multi-type messaging system
└── messages[] (array)
    ├── id (int)
    ├── message_type (enum: announcement, email, sms, internal, parent_message)
    ├── subject (string, nullable)
    ├── body (text)
    ├── sender_id (int, FK → users)
    ├── sender_name (string)
    ├── created_at (timestamp)
    ├── attachment_count (int)
    ├── read_count (int)
    ├── recipients[] (array)
    │   ├── recipient_id (int, FK → users)
    │   ├── recipient_name (string)
    │   ├── is_read (boolean)
    │   ├── read_at (timestamp, nullable)
    │   └── ...
    └── attachments[] (array)
        ├── file_id (int)
        ├── file_name (string)
        ├── file_type (string)
        └── file_url (string)

INBOX VIEW: Conversation list
├── message_id (int)
├── subject (string)
├── sender_name (string)
├── preview_text (text, truncated)
├── is_read (boolean)
├── unread_count (int)
├── received_date (date)
├── has_attachments (boolean)
└── message_type (enum)
```

#### Available Endpoints
- `GET /api/communications/inbox` - User inbox (paginated)
- `GET /api/communications/sent` - Sent messages
- `GET /api/communications/drafts` - Draft messages
- `GET /api/communications/{id}` - Single message
- `POST /api/communications` - Send message
- `PUT /api/communications/{id}` - Update draft
- `DELETE /api/communications/{id}` - Delete message
- `POST /api/communications/announcements` - Broadcast announcement
- `GET /api/communications/groups` - Recipient groups

#### Related Tables
- `communications` - Main message data
- `communication_recipients` - Per-recipient delivery
- `communication_attachments` - File attachments
- `message_read_status` - Read tracking
- `communication_groups` - Static groups
- `communication_templates` - Message templates
- `announcements_bulletin` - Public announcements
- `parent_communication_preferences` - Parent settings
- `sms_communications` - SMS tracking
- `external_emails` - Email tracking

---

### 1.8 TRANSPORT MODULE
**Endpoint:** `/api/transport`  
**Controller:** `TransportController.php`  
**API Class:** `TransportAPI.php`

#### Data Structure
```
RESPONSE FORMAT: Route and allocation management
└── routes[] (array)
    ├── id (int)
    ├── route_code (string, unique)
    ├── route_name (string)
    ├── total_capacity (int)
    ├── current_allocations (int)
    ├── vehicle_id (int, FK)
    ├── driver_id (int, FK)
    ├── morning_departure (time)
    ├── afternoon_departure (time)
    ├── status (enum: active, inactive, under_maintenance)
    └── stops[] (array)  [nested]
        ├── stop_id (int)
        ├── stop_name (string)
        ├── stop_order (int)
        ├── pickup_time (time)
        ├── dropoff_time (time)
        ├── students_at_stop (int)
        └── ...

ALLOCATION DATA: Per student
├── student_id (int)
├── student_name (string)
├── route_id (int)
├── route_name (string)
├── pickup_stop (string)
├── allocation_status (enum: active, inactive, on_hold)
├── monthly_fee (decimal)
├── payment_status (enum: paid, partial, overdue)
└── ...
```

#### Available Endpoints
- `GET /api/transport/routes` - All routes
- `GET /api/transport/routes/{id}` - Route detail
- `GET /api/transport/allocations` - Student allocations
- `POST /api/transport/allocations` - Allocate student
- `GET /api/transport/vehicles` - Vehicle list
- `GET /api/transport/drivers` - Driver list
- `GET /api/transport/payments` - Transport fees

#### Related Tables
- `transport_routes` - Route definitions
- `transport_vehicles` - Vehicle master
- `drivers` - Driver information
- `transport_stops` - Stop locations
- `route_schedules` - Timing data
- `transport_payments` - Fee tracking
- `transport_vehicle_routes` - Vehicle-route assignments

---

### 1.9 ADMISSIONS MODULE
**Endpoint:** `/api/admission`  
**Controller:** `AdmissionController.php`  
**API Class:** `AdmissionAPI.php`

#### Data Structure
```
RESPONSE FORMAT: Application workflow data
└── applications[] (array)
    ├── id (int)
    ├── application_number (string, unique)
    ├── applicant_name (string)
    ├── applicant_email (string)
    ├── applicant_phone (string)
    ├── date_of_birth (date)
    ├── preferred_class_id (int, FK)
    ├── previous_school (string, nullable)
    ├── application_status (enum: draft, submitted, under_review, approved, rejected, accepted)
    ├── workflow_stage (varchar) - "Document Review", "Assessment", "Interview", "Decision"
    ├── application_date (date)
    ├── documents[] (array)  [nested]
    │   ├── doc_id (int)
    │   ├── document_type (varchar)
    │   ├── file_url (string)
    │   ├── status (enum: pending, submitted, approved, rejected)
    │   └── ...
    └── assessments[] (array)  [nested]
        ├── assessment_id (int)
        ├── assessment_type (varchar)
        ├── score (decimal)
        ├── grade (varchar)
        └── ...
```

#### Available Endpoints
- `GET /api/admission/applications` - List applications
- `GET /api/admission/applications/{id}` - Single application
- `POST /api/admission/applications` - Create application
- `PUT /api/admission/applications/{id}/status` - Update status
- `GET /api/admission/workflows` - Current workflows
- `POST /api/admission/approve/{id}` - Approve admission
- `POST /api/admission/reject/{id}` - Reject application

#### Related Tables
- `admission_applications` - Application records
- `admission_documents` - Required documents
- `workflow_instances` - Workflow state
- `workflow_stages` - Workflow steps
- `workflow_history` - Workflow audit

---

## 2. ROLE-BASED DATA ACCESS

### Role Hierarchy (29 Roles Total)

1. **System Administrator** - Full system access, user management, security
2. **Director/Owner** - Reports, approvals, payroll, strategic decisions
3. **School Administrative Officer** - All operational permissions
4. **Headteacher** - Class management, assessments, academic
5. **Deputy Headteacher** - Academic support, assessments
6. **Class Teacher** - Class attendance, assessments, results
7. **Subject Teacher** - Exam, assessments, results for subject
8. **Intern/Student Teacher** - View-only access
9. **School Accountant** - Fees, payroll, budgets, reconciliation
10. **Accounts Assistant** - Fee/payment view, invoicing
11. **School Bursar** - Cash management, receipts, payments
12. **Finance Officer** - Financial planning, budgets
13. **Transport Coordinator** - Vehicle, routes, allocations
14. **Transport Driver** - Route operations, payments
15. **Hostel Manager** - Boarding, meals, rooms
16. **Chef/Cook** - Meal planning, food consumption
17. **School Nurse** - Health records, medical staff
18. **Procurement Officer** - Purchase orders, suppliers
19. **Inventory Manager** - Stock management, requisitions
20. **Inventory Staff** - Stock movements
21. **Human Resource Officer** - Staff management, contracts
22. **HR Assistant** - Payroll support
23. **Communications Officer** - Announcements, messages
24. **Admissions Officer** - Admission workflows
25. **Examinations Officer** - Exam scheduling, results
26. **Activities Coordinator** - Activities management
27. **IT Administrator** - System configuration
28. **Parent** - View child data, payments, communications
29. **Student** - View own data, learning materials

### Data Visibility by Role

```
DIRECTOR/OWNER can see:
├── Dashboard: Total students, revenue, staff count, pending approvals
├── Finance: Collections, arrears by class, payroll summary
├── Staff: Salaries, performance, attendance
├── Academic: Results, promotion rates, graduation rates
├── Reports: All financial, academic, operational reports
└── Approvals: All workflows requiring director approval

HEADTEACHER can see:
├── Dashboard: Class stats, assessment progress, attendance summary
├── Students: Assigned classes only
├── Academic: Assessments, results, timetable
├── Staff: Teaching staff assignments, performance
├── Reports: Academic performance reports
└── Cannot see: Finance details, non-academic staff data

CLASS TEACHER can see:
├── Dashboard: Class attendance, pending assessments
├── Students: Own class students only
├── Attendance: Own class only
├── Academic: Own class subjects/assessments
├── Reports: Class performance reports only
└── Cannot see: Other classes, finance, staff data

ACCOUNTANT can see:
├── Dashboard: Collections, arrears, outstanding
├── Finance: All payments, balances, reconciliation
├── Reports: Fee collection, arrears, bank reconciliation
├── Students: Name, admission_no, balance only
└── Cannot see: Academic data, staff data (except payroll)

TRANSPORT COORDINATOR can see:
├── Dashboard: Route capacity, allocations
├── Transport: All routes, vehicles, allocations
├── Students: Only allocated students (name, ID, route)
├── Finance: Transport payments only
└── Cannot see: Academic data, discipline, personal data

PARENT can see:
├── Dashboard: Own child's academic progress
├── Student: Own child only
├── Academic: Own child's results, attendance
├── Finance: Own child's balance only
├── Communications: Messages, announcements
└── Cannot see: Other students, staff data, system config

STUDENT can see:
├── Dashboard: Own grades, attendance
├── Academic: Own results, timetable
├── Communications: Messages, announcements
├── Documents: Own ID card, certificates
└── Cannot see: Finance, staff, other students
```

---

## 3. AVAILABLE REPORTS & INSIGHTS

### 3.1 Student Reports (`StudentReportManager.php`)
- **Attendance Report** - By date range, class, stream
- **Total Students** - Breakdown by class, stream, gender, year
- **Enrollment Trends** - Monthly/yearly trends
- **Attendance Rates** - Percentage by class/stream/term
- **Promotion Rates** - By class/stream/year
- **Performance Summary** - Academic achievement
- **Discipline Summary** - Conduct issues by severity

### 3.2 Finance Reports (`FinanceReportManager.php`)
- **Fee Summary** - Outstanding balances per student
- **Payment Trends** - Monthly payment patterns
- **Discount Statistics** - Discounts granted by type
- **Arrears Statistics** - Outstanding by class
- **Financial Transactions Summary** - By type
- **Bank Transactions Summary** - By account
- **Fee Structure Changelog** - Historical changes

### 3.3 Staff Reports (`StaffReportManager.php`)
- **Payroll Summary** - Salaries, deductions, net pay
- **Leave Balance** - Available vs taken leave
- **Performance Reviews** - Annual ratings
- **Attendance Summary** - Staff attendance patterns
- **Loan Details** - Active staff loans
- **Workload Analysis** - Classes/subjects assigned

### 3.4 Inventory Reports (`InventoryReportManager.php`)
- **Stock Levels** - Current quantities, values
- **Low Stock Alerts** - Below reorder level
- **Usage Trends** - Consumption patterns
- **Supplier Performance** - Delivery, quality
- **Inventory Reconciliation** - Physical vs system

### 3.5 System Reports
- **User Activity** - Login patterns, actions
- **API Usage** - Endpoint calls, errors
- **System Health** - Database, server metrics
- **Audit Trail** - All data modifications

---

## 4. PRE-COMPUTED VIEWS FOR DASHBOARDS

### Financial Views
| View Name | Purpose | Data Returned |
|-----------|---------|---------------|
| `vw_arrears_summary` | Arrears overview by level | students_in_arrears, total_amount, overdue_counts, settlement_plans |
| `vw_outstanding_fees` | Students with outstanding fees | student_id, class, outstanding_amount, days_overdue |
| `vw_fee_collection_by_year` | Annual collection trends | year, amount_collected, target, collection_rate |
| `vw_payment_tracking` | Payment status by student | student_id, payment_date, amount, method, status |
| `vw_fee_schedule_by_class` | Fee structure per class | class, fee_items[], total_amount |
| `vw_outstanding_by_class` | Arrears breakdown by class | class_name, count_in_arrears, total_amount, percentage |

### Academic Views
| View Name | Purpose | Data Returned |
|-----------|---------|---------------|
| `vw_class_rosters` | Student lists per class | class_id, students[], enrollment_count |
| `vw_current_enrollments` | Active student registrations | enrollment_id, student, class, stream, status |
| `vw_active_students_per_class` | Current class populations | class_name, active_count, capacity, utilization |

### Staff Views
| View Name | Purpose | Data Returned |
|-----------|---------|---------------|
| `vw_staff_payroll_summary` | Salary data snapshot | staff_id, staff_name, basic, allowances, deductions, net, ytd_totals |
| `vw_staff_leave_balance` | Leave entitlements | staff_id, leave_type, total_entitled, taken, remaining, balance |
| `vw_staff_assignments_detailed` | Teaching assignments | staff_id, classes[], subjects[], timetable |
| `vw_staff_performance_summary` | Annual ratings | staff_id, rating_date, overall_rating, department_rank |
| `vw_staff_workload` | Course load analysis | staff_id, total_classes, total_students, hours_per_week |

### Operational Views
| View Name | Purpose | Data Returned |
|-----------|---------|---------------|
| `vw_pending_requisitions` | Open requisition requests | req_id, department, items[], status, requested_date |
| `vw_inventory_low_stock` | Items below reorder level | item_id, item_name, current_qty, reorder_level, variance |
| `vw_inventory_health` | Stock status overview | total_items, in_stock, low_stock, out_of_stock, pending_orders |
| `vw_requisition_fulfillment` | Requisition completion | req_id, requested_qty, fulfilled_qty, pending_qty, fulfillment_rate |
| `vw_pending_sms` | Unsent messages | sms_id, recipient, message, scheduled_time, retry_count |
| `vw_maintenance_schedule` | Equipment maintenance due | equipment_id, last_service, next_due_date, days_remaining |

### Activity Views
| View Name | Purpose | Data Returned |
|-----------|---------|---------------|
| `vw_upcoming_class_schedules` | Next 30 days of classes | class_name, subject, date_time, teacher, room |
| `vw_upcoming_exam_schedules` | Exam calendar | exam_name, class, date_time, duration, room |
| `vw_upcoming_activities` | Events in next 30 days | activity_name, date, category, participants, status |
| `vw_unread_announcements` | New announcements | announcement_id, subject, sender, created_at, read_count |
| `vw_internal_conversations` | Recent messages | conversation_id, participants[], last_message, date |

---

## 5. KEY INSIGHTS & KPIs FOR DASHBOARDS

### Financial Dashboard
```
📊 KPIs:
├── Total Collections (This Month): SUM(payment_transactions.amount) WHERE month=current
├── Collection Rate (%): (Collections / Expected Fees) * 100
├── Outstanding Arrears: SUM(student_fee_balances.balance) WHERE balance > 0
├── Days Cash on Hand: Available_Cash / Daily_Expenses
├── Accounts Receivable Aging: Outstanding by 30/60/90+ days
├── Discount Rate (%): Total_Discounts / Total_Expected_Revenue
├── Overdue % by Class: Class with highest arrears
└── Settlement Plan Success Rate: Active_Plans / Completed_Plans
```

### Academic Dashboard
```
📊 KPIs:
├── Total Enrolled Students: COUNT(students) WHERE status='active'
├── Class Utilization (%): (Enrolled / Capacity) * 100 per class
├── Promotion Rate (%): (Promoted / Total) * 100
├── Average Class Size: AVG(students_per_class)
├── Attendance Rate (%): (Present_Days / Total_Days) * 100 by class/term
├── Subject Pass Rates (%): (Passed / Total) * 100 per subject
├── Performance by Level: Grade distribution (A-F)
└── At-Risk Students: Low attendance, poor grades, pending assessments
```

### Operations Dashboard
```
📊 KPIs:
├── Inventory Turn Over: COGS / Average_Inventory
├── Stock-out Events: Items with zero quantity
├── Low Stock Items: Items < reorder_level
├── Average Lead Time: Requisition to Receipt
├── Requisition Fulfillment Rate (%): Completed / Total
├── Transport Route Utilization: Allocated / Capacity per route
├── Pending Allocations: Unfulfilled requisitions
└── Equipment Availability: Operational / Total equipment
```

### Staff Dashboard
```
📊 KPIs:
├── Total Staff: COUNT(staff) WHERE status='active'
├── Staff by Department: COUNT() grouped by department
├── Payroll Total: SUM(net_salary) for current month
├── Leave Utilization: Taken / Entitled %
├── Staff Attendance Rate (%): Present / Total days
├── Performance Rating Avg: AVG(performance_score)
├── Pending Performance Reviews: COUNT() WHERE status='pending'
└── Staff Turnover: (Left this year / Avg_headcount) * 100
```

### Engagement Dashboard (Parents/Students)
```
📊 KPIs:
├── Unread Announcements: COUNT(announcements) WHERE read=false per user
├── Pending Responses: Awaiting parent action (permissions, forms)
├── Payment Due: Days until next payment due
├── Fee Balance: Current outstanding amount
├── Attendance Summary: % present this term
├── Last Updated Results: Most recent grades posted
└── Messages Unread: COUNT(messages) WHERE read=false
```

---

## 6. DATA CATEGORIZATION FOR UI

### Simple CRUD Tables (DataTable.js)
```
✅ Use DataTable for:
├── Student List (with modal for quick view)
├── Staff Directory (with modal for contact)
├── Fee Structure (with modal for details)
├── Requisition List (with status tracking)
├── Announcement List (with read status)
├── Transport Routes (with allocation count)
├── Activities List (with participant count)
└── Inventory Items (with stock level indicator)
```

### Tabbed Interfaces (TabNavigator.js)
```
✅ Use Tabs for:
├── Messaging (Inbox | Sent | Drafts | Compose)
├── Student Profile (Bio | Academic | Attendance | Finance | Discipline | Health | Documents)
├── Staff Profile (Bio | Qualifications | Assignments | Performance | Attendance | Loans)
├── Class Management (Students | Timetable | Performance | Attendance)
├── Finance Management (Collections | Arrears | Payroll | Budgets | Reconciliation)
└── Inventory Management (Stock | Requisitions | Allocations | Categories)
```

### Drill-Down Navigation (PageNavigator.js)
```
✅ Use PageNavigator for:
├── Classes → Class Detail → Students → Student Profile
├── Academic Year → Terms → Classes → Stream Details
├── Requisitions → Requisition Detail → Items → Item History
├── Activities → Activity Detail → Participants → Individual Records
├── Workflows → Workflow Instance → Current Stage → Actions
└── Finance Audit → Transaction List → Transaction Detail → Receipt
```

### Dashboard Cards (UIComponents.js)
```
✅ Use Cards for KPIs:
├── Finance: Total Collections, Collection Rate, Outstanding Arrears, Days Cash
├── Academic: Enrollment, Promotion Rate, Attendance %, At-Risk Students
├── Operations: Inventory Status, Requisition Status, Equipment Availability
├── Staff: Payroll Total, Leave Usage, Attendance %, Performance Average
├── Engagement: Announcements, Messages, Payment Due, Fee Balance
└── Status Indicators: Green (on-track), Yellow (warning), Red (critical)
```

---

## 7. COMPLEX DATA STRUCTURES EXPLANATION

### Nested Student Object Example
```json
{
  "id": 1,
  "admission_no": "STU-001",
  "first_name": "John",
  "last_name": "Doe",
  "class_name": "Form 4A",      // ← NESTED from classes table
  "stream_name": "Science",      // ← NESTED from class_streams table
  "status": "active",
  "is_sponsored": true,
  "sponsor_type": "partial",
  "sponsor_waiver_percentage": 25.00
}
```

### Array of Requisition Items
```json
{
  "id": 1,
  "requisition_number": "REQ-2025-001",
  "status": "submitted",
  "items": [                     // ← ARRAY of nested items
    {
      "item_id": 5,
      "item_name": "Whiteboard Marker",
      "quantity_requested": 100,
      "quantity_allocated": 100,
      "status": "fulfilled"
    },
    {
      "item_id": 7,
      "item_name": "Chalk",
      "quantity_requested": 50,
      "quantity_allocated": 0,
      "status": "pending"
    }
  ]
}
```

### Hierarchical Academic Structure
```json
{
  "academic_year_id": 1,
  "year": "2025",
  "terms": [                     // ← ARRAY of terms
    {
      "term_id": 1,
      "name": "Term 1",
      "classes": [               // ← ARRAY of classes (nested)
        {
          "class_id": 1,
          "name": "Form 4",
          "streams": [            // ← ARRAY of streams (doubly nested)
            {
              "stream_id": 1,
              "stream_name": "Science",
              "class_teacher_id": 5,
              "total_students": 45
            }
          ]
        }
      ]
    }
  ]
}
```

---

## 8. API USAGE PATTERNS

### Authentication
```
All requests require:
├── JWT Token in Authorization header
├── User session in cookies
└── Request validated against role permissions
```

### Pagination Pattern
```
Request: GET /api/students?page=1&limit=20&search=john&sort=first_name&order=asc
Response: {
  "status": "success",
  "data": {
    "students": [...],
    "pagination": {
      "page": 1,
      "limit": 20,
      "total": 150,
      "total_pages": 8
    }
  }
}
```

### Error Handling
```
HTTP 400 - Bad Request: Missing required fields
HTTP 401 - Unauthorized: Invalid/expired token
HTTP 403 - Forbidden: Insufficient permissions
HTTP 404 - Not Found: Resource doesn't exist
HTTP 422 - Unprocessable: Validation errors
HTTP 500 - Server Error: Exception occurred
```

### Filter Pattern
```
GET /api/students?filter[status]=active&filter[class_id]=1&filter[stream_id]=2
GET /api/payments?filter[start_date]=2025-01-01&filter[end_date]=2025-12-31
```

---

## 9. WORKFLOW DATA

### Admission Workflow
```
States: draft → submitted → under_review → approved/rejected → accepted
Stages: Documentation Review → Assessment → Interview → Final Decision
Actors: Admissions Officer → Headteacher → Director
```

### Payment Settlement Workflow
```
States: due → reminder_sent → partial_paid → settled/overdue
Actions: Record payment → Allocate to fees → Generate receipts
Views: Outstanding list → Settlement plan → Payment history
```

### Requisition Workflow
```
States: draft → submitted → approved → fulfilled → closed
Stages: Request Submission → Manager Approval → Procurement → Receipt → Fulfillment
```

---

## 10. CONNECTION STRING

```bash
/opt/lampp/bin/mysql -u root -padmin123 KingsWayAcademy
```

**For direct queries:**
```bash
/opt/lampp/bin/mysql -u root -padmin123 KingsWayAcademy -e "SELECT * FROM students LIMIT 5;"
```

---

## Summary

**Total Data Sources:** 344 tables + 42 views  
**Main Modules:** 9 (Students, Staff, Academic, Finance, Attendance, Inventory, Communications, Transport, Admissions)  
**Available Roles:** 29  
**Report Managers:** 12  
**Key Views for Dashboards:** 42 pre-computed  
**KPI Categories:** Finance, Academic, Operations, Staff, Engagement  

The system is ready for comprehensive dashboard implementation with proper role-based data filtering and meaningful insights extraction.
