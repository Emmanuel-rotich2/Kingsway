# Student Lifecycle Workflow Documentation

## Overview

This document explains the complete student lifecycle in the Kingsway Academy Management System, from initial admission application to daily class attendance and fee management.

---

## 1. Student Admission Flows

The system supports **multiple admission pathways** depending on the student's situation:

| Pathway | Who It's For | Stages | Interview Required |
|---------|--------------|--------|-------------------|
| **New Admission (ECD)** | Playground, PP1, PP2 | Application → Documents → Placement → Payment → Enrollment | ❌ No |
| **New Admission (Grade 1/7)** | Entry points to primary/junior secondary | Application → Documents → Placement → Payment → Enrollment | ❌ No |
| **New Admission (Grade 2-6)** | Transferees mid-primary | Application → Documents → Interview → Placement → Payment → Enrollment | ✅ Yes |
| **Re-Enrollment** | Existing students continuing | Auto-promoted via promotion workflow | ❌ No |
| **Re-Admission** | Returning after withdrawal | Direct creation with admin bypass | ❌ No |
| **Direct Entry** | Admin quick-add | Direct API call | ❌ No |

---

### 1.1 New Student Admission (Formal Workflow)

Handled by `StudentAdmissionWorkflow.php`:

| Stage | Role | Description | Status |
|-------|------|-------------|--------|
| 1. Application | Parent/Registrar | Submit admission application with applicant details | `submitted` |
| 2. Documents | Registrar | Upload birth certificate, immunization card, etc. | `documents_pending` → `documents_verified` |
| 3. Interview Scheduling | Registrar | Schedule interview (**Grade 2-6 ONLY**) | - |
| 4. Interview Assessment | Teacher | Assess student readiness (**Grade 2-6 ONLY**) | - |
| 5. Placement Offer | Head Teacher | Assign class, calculate total fees | `placement_offered` |
| 6. Fee Payment | Accountant | Record initial payment | `fees_pending` |
| 7. Enrollment | Registrar | Create student record, assign class | `enrolled` |

**Key Business Rule**: A student must have at least one payment recorded before proceeding to enrollment. The school determines acceptable minimum payment thresholds.

---

### 1.2 ECD & Entry-Level Admission (Simplified)

For **Playground, PP1, PP2, Grade 1, and Grade 7** students, the interview stages are automatically skipped:

```
Application → Document Verification → Auto-Qualify → Placement Offer → Payment → Enrollment
```

**Code Logic** (from `StudentAdmissionWorkflow.php`):
```php
// ECD, PP1, PP2, Grade1, and Grade7 skip interview - go directly to placement offer
if (!$this->requiresAssessment($grade)) {
    $this->advanceStage($instance['id'], 'placement_offer', 'documents_verified_auto_qualify');
    $this->updateApplicationStatus($application_id, 'auto_qualified');
}
```

**Grades requiring interview assessment**:
- Grade 2, Grade 3, Grade 4, Grade 5, Grade 6

**Grades that skip interview** (auto-qualify after documents):
- Playground, PP1, PP2, Grade 1, Grade 7, Grade 8, Grade 9

---

### 1.3 Re-Enrollment (Existing Students → New Academic Year)

For students already in the system who are continuing to the next academic year:

**Handled by**: `StudentPromotionWorkflow.php`

This is an **end-of-year process** that:
1. Creates new `class_enrollments` record for the new academic year
2. Promotes student to the next class/grade
3. Generates new fee obligations

**CBC Promotion Rules**:
| From Grade | To Grade | Criteria |
|------------|----------|----------|
| PP1 → PP2 | Automatic | No retention in ECD |
| PP2 → Grade 1 | Automatic | No retention in ECD |
| Grade 1-2 | Automatic | Formative assessment only |
| Grade 3-6 | Performance-based | Continuous assessment |
| Grade 6 → Grade 7 | Transition | Junior secondary entry |

```sql
-- Re-enroll existing student for new academic year
CALL sp_enroll_student_in_class(
    student_id,
    new_academic_year_id,
    new_class_id,
    new_stream_id,
    CURDATE(),
    @enrollment_id
);
```

---

### 1.4 Re-Admission (Returning Students)

For students who previously left (transferred, withdrawn, etc.) and are returning:

**Use**: `StudentsAPI.addExistingStudent()` or direct `StudentsAPI.create()` with `skip_payment_check = true`

```php
// Endpoint: POST /api/?route=students&action=add-existing
{
    "admission_no": "ADM/2023/045",  // Can use original admission number
    "first_name": "John",
    "last_name": "Returning",
    "date_of_birth": "2016-03-20",
    "gender": "male",
    "stream_id": 12,
    "admission_date": "2026-01-15",
    "status": "active",  // Reactivate
    "skip_payment_check": true,  // Admin bypass
    "parent_id": 45  // Link to existing parent
}
```

**Alternative**: Reactivate existing student record:
```sql
-- Reactivate a previously withdrawn student
UPDATE students SET status = 'active' WHERE id = ?;

-- Create new enrollment for current year
CALL sp_complete_student_enrollment(student_id, class_id, stream_id, NULL, @enr_id, @fees);
```

---

### 1.5 Direct Entry (Admin Quick-Add)

For administrative flexibility, students can be created directly via `StudentsAPI.create()`:

```php
// Endpoint: POST /api/?route=students&action=create
{
    "admission_no": "ADM/2025/001",
    "first_name": "John",
    "last_name": "Doe",
    "date_of_birth": "2018-05-15",
    "gender": "male",
    "stream_id": 5,
    "student_type_id": 1,
    "admission_date": "2025-01-15",
    
    // Payment OR Sponsorship required
    "is_sponsored": 0,  // OR 1 for sponsored students
    "initial_payment_amount": 5000,
    "payment_method": "mpesa",
    "payment_reference": "QWE123456",
    
    // Parent information
    "parent_info": {
        "first_name": "Jane",
        "last_name": "Doe",
        "phone_1": "0712345678",
        "email": "jane.doe@email.com"
    }
}
```

**Validation Rules**:
- Student must be either **sponsored** (`is_sponsored = 1`) OR have an **initial payment** (`initial_payment_amount > 0`)
- Admin users can bypass with `skip_payment_check = true`

---

## 2. Class Assignment & Enrollment

### 2.1 How Students Get Assigned to Classes

When a student is created or enrolled:

1. **Stream Selection**: User selects a `stream_id` (e.g., "Grade 3 - East Stream")
2. **Class Derivation**: The system gets `class_id` from `class_streams.class_id`
3. **Enrollment Creation**: Stored procedure `sp_complete_student_enrollment` creates:
   - Entry in `class_enrollments` table
   - Fee obligations in `student_fee_obligations` table

### 2.2 Class Enrollments Table

The `class_enrollments` table is the authoritative source for student-class relationships:

```sql
-- Check student's current enrollment
SELECT ce.*, c.name as class_name, cs.stream_name
FROM class_enrollments ce
JOIN classes c ON ce.class_id = c.id
JOIN class_streams cs ON ce.stream_id = cs.id
WHERE ce.student_id = ? 
  AND ce.academic_year_id = (SELECT id FROM academic_years WHERE is_current = 1)
  AND ce.enrollment_status IN ('enrolled', 'active');
```

### 2.3 Key Stored Procedures

```sql
-- Complete student enrollment with fee obligations
CALL sp_complete_student_enrollment(
    student_id,     -- Student's ID
    class_id,       -- Class ID
    stream_id,      -- Stream ID
    academic_year_id, -- NULL for current year
    @enrollment_id, -- OUT: Created enrollment ID
    @fee_count      -- OUT: Number of fee obligations created
);
```

---

## 3. Class Register (Attendance & Assessments)

### 3.1 Attendance Marking

Students appear in the class register through the `class_enrollments` table:

```sql
-- Get class register for attendance
SELECT s.id, s.admission_no, s.first_name, s.last_name,
       ce.enrollment_status
FROM students s
JOIN class_enrollments ce ON s.id = ce.student_id
WHERE ce.class_id = ?
  AND ce.stream_id = ?
  AND ce.academic_year_id = ?
  AND ce.enrollment_status IN ('enrolled', 'active')
ORDER BY s.last_name, s.first_name;
```

### 3.2 Recording Attendance

Attendance is stored in `student_attendance`:

```sql
-- Mark student attendance
INSERT INTO student_attendance (student_id, date, status, class_id, term_id, marked_by)
VALUES (?, CURDATE(), 'present', ?, ?, ?);

-- Status values: 'present', 'absent', 'late', 'excused'
```

### 3.3 Assessments & Results

Students in a class can have assessments recorded via:
- `exam_results` table for formal exams
- `assessment_results` table for continuous assessment

---

## 4. Fee Management

### 4.1 Fee Structure

Fees are defined per level, academic year, term, and student type:

```sql
-- Fee structure definition
SELECT fsd.*, ft.name as fee_type_name
FROM fee_structures_detailed fsd
JOIN fee_types ft ON fsd.fee_type_id = ft.id
WHERE fsd.level_id = ?           -- E.g., Primary Level
  AND fsd.academic_year = 2025
  AND fsd.student_type_id = ?    -- Day scholar, Boarder, etc.
  AND fsd.status = 'approved';
```

### 4.2 Fee Obligation Generation

When a student enrolls, fee obligations are automatically created:

```sql
CALL sp_generate_student_fee_obligations(
    student_id,
    academic_year_id,  -- NULL for current year
    term_id,           -- NULL for all terms
    @obligations_created
);
```

This creates records in `student_fee_obligations` with:
- Amount due (from fee structure)
- Amount waived (if sponsored with waiver percentage)
- Balance to pay

### 4.3 Recording Payments

Payments are recorded in `payment_transactions`:

```sql
INSERT INTO payment_transactions (
    student_id, academic_year_id, term_id,
    amount, payment_method, reference_no, receipt_no,
    payment_date, status
) VALUES (?, ?, ?, ?, 'mpesa', 'QWE123456', 'RCT-2025-001', CURDATE(), 'confirmed');
```

Payments are distributed across outstanding fee obligations automatically.

### 4.4 Checking Student Balance

```sql
-- Get student's fee balance
SELECT 
    SUM(amount_due) as total_fees,
    SUM(amount_paid) as total_paid,
    SUM(amount_waived) as total_waived,
    SUM(balance) as total_balance
FROM student_fee_obligations
WHERE student_id = ?
  AND academic_year_id = ?;
```

---

## 5. Workflow Diagrams

### 5.1 ECD/Entry-Level Admission (Playground, PP1, PP2, Grade 1, Grade 7+)

```
┌─────────────────────────────────────────────────────────────────────────┐
│               ECD & ENTRY-LEVEL ADMISSION (NO INTERVIEW)                │
├─────────────────────────────────────────────────────────────────────────┤
│                                                                         │
│  ┌──────────────┐    ┌──────────────┐    ┌───────────────────────────┐ │
│  │ Application  │───►│  Documents   │───►│  AUTO-QUALIFY             │ │
│  │ Submitted    │    │  Verified    │    │  (Skip Interview)         │ │
│  └──────────────┘    └──────────────┘    └───────────────────────────┘ │
│         │                                              │               │
│         ▼                                              ▼               │
│  ┌──────────────┐                        ┌───────────────────────────┐ │
│  │ Parent Info  │                        │ Placement Offer           │ │
│  │ Collected    │                        │ (Assign Class + Fees)     │ │
│  └──────────────┘                        └───────────────────────────┘ │
│                                                        │               │
│                                                        ▼               │
│                                          ┌───────────────────────────┐ │
│                                          │ Fee Payment (Any amount)  │ │
│                                          │ OR Sponsored              │ │
│                                          └───────────────────────────┘ │
│                                                        │               │
│                                                        ▼               │
│                                          ┌───────────────────────────┐ │
│                                          │ STUDENT ENROLLED          │ │
│                                          │ Active in class register  │ │
│                                          └───────────────────────────┘ │
└─────────────────────────────────────────────────────────────────────────┘

Applies to: Playground, PP1, PP2, Grade 1, Grade 7, Grade 8, Grade 9
```

### 5.2 Transfer Student Admission (Grade 2-6 with Interview)

```
┌─────────────────────────────────────────────────────────────────────────┐
│            TRANSFER STUDENT ADMISSION (INTERVIEW REQUIRED)              │
├─────────────────────────────────────────────────────────────────────────┤
│                                                                         │
│  ┌──────────────┐    ┌──────────────┐    ┌───────────────────────────┐ │
│  │ Application  │───►│  Documents   │───►│ Interview Scheduling      │ │
│  │ Submitted    │    │  Verified    │    │                           │ │
│  └──────────────┘    └──────────────┘    └───────────────────────────┘ │
│                                                        │               │
│                                                        ▼               │
│                                          ┌───────────────────────────┐ │
│                                          │ Interview Assessment      │ │
│                                          │ (Teacher evaluates)       │ │
│                                          └───────────────────────────┘ │
│                                                        │               │
│                              ┌─────────────────────────┴───────┐       │
│                              ▼                                 ▼       │
│                     ┌──────────────┐                 ┌──────────────┐  │
│                     │   PASSED     │                 │   FAILED     │  │
│                     │ Placement    │                 │ Application  │  │
│                     │ Offer        │                 │ Cancelled    │  │
│                     └──────┬───────┘                 └──────────────┘  │
│                            │                                           │
│                            ▼                                           │
│                  ┌─────────────────────┐                               │
│                  │ Fee Payment → Enroll│                               │
│                  └─────────────────────┘                               │
└─────────────────────────────────────────────────────────────────────────┘

Applies to: Grade 2, Grade 3, Grade 4, Grade 5, Grade 6 (transfer students)
```

### 5.3 Re-Enrollment (Existing Students → New Academic Year)

```
┌─────────────────────────────────────────────────────────────────────────┐
│              RE-ENROLLMENT (EXISTING STUDENT PROMOTION)                 │
├─────────────────────────────────────────────────────────────────────────┤
│                                                                         │
│   END OF ACADEMIC YEAR                                                  │
│         │                                                               │
│         ▼                                                               │
│  ┌──────────────────────────────────────────────────────────────────┐  │
│  │              PROMOTION WORKFLOW INITIATED                         │  │
│  │  • Define criteria (from_grade → to_grade)                       │  │
│  │  • Identify eligible students                                     │  │
│  │  • Validate performance (Grade 3+) or auto-promote (ECD/Lower)   │  │
│  └──────────────────────────────────────────────────────────────────┘  │
│         │                                                               │
│         ▼                                                               │
│  ┌──────────────┐    ┌──────────────┐    ┌───────────────────────────┐ │
│  │   PROMOTE    │    │   RETAIN     │    │   GRADUATE/TRANSFER       │ │
│  │ New class    │    │ Same class   │    │   Exit system or          │ │
│  │ enrollment   │    │ repeat year  │    │   Junior Secondary        │ │
│  └──────────────┘    └──────────────┘    └───────────────────────────┘ │
│         │                   │                          │               │
│         └───────────────────┴──────────────────────────┘               │
│                             │                                          │
│                             ▼                                          │
│                  ┌─────────────────────┐                               │
│                  │ NEW YEAR ENROLLMENT │                               │
│                  │ • class_enrollments │                               │
│                  │ • fee_obligations   │                               │
│                  │ (automatic)         │                               │
│                  └─────────────────────┘                               │
└─────────────────────────────────────────────────────────────────────────┘

NO APPLICATION NEEDED - Existing students are promoted automatically
```

### 5.4 Re-Admission (Returning After Withdrawal)

```
┌─────────────────────────────────────────────────────────────────────────┐
│                 RE-ADMISSION (RETURNING STUDENT)                        │
├─────────────────────────────────────────────────────────────────────────┤
│                                                                         │
│   Student previously: transferred, withdrawn, suspended                 │
│         │                                                               │
│         ▼                                                               │
│  ┌──────────────────────────────────────────────────────────────────┐  │
│  │                     OPTION A: REACTIVATE                          │  │
│  │  UPDATE students SET status = 'active' WHERE id = ?               │  │
│  │  CALL sp_complete_student_enrollment(...)                         │  │
│  └──────────────────────────────────────────────────────────────────┘  │
│                             OR                                          │
│  ┌──────────────────────────────────────────────────────────────────┐  │
│  │                     OPTION B: QUICK ADD                           │  │
│  │  POST /api/?route=students&action=add-existing                    │  │
│  │  { ...student_data, skip_payment_check: true }                    │  │
│  └──────────────────────────────────────────────────────────────────┘  │
│         │                                                               │
│         ▼                                                               │
│  ┌──────────────────────────────────────────────────────────────────┐  │
│  │                   STUDENT ACTIVE AGAIN                            │  │
│  │  • Appears in class register                                      │  │
│  │  • Fee obligations generated                                      │  │
│  └──────────────────────────────────────────────────────────────────┘  │
└─────────────────────────────────────────────────────────────────────────┘

NO INTERVIEW - Admin discretion for returning students
```

### 5.5 Daily Student Flow

### 5.2 Daily Student Flow

```
┌─────────────────────────────────────────────────────────────────────────┐
│                          DAILY STUDENT FLOW                             │
├─────────────────────────────────────────────────────────────────────────┤
│                                                                         │
│   MORNING                 DURING DAY               END OF DAY           │
│   ────────                ──────────               ───────────          │
│                                                                         │
│   ┌───────────┐         ┌─────────────┐         ┌─────────────┐        │
│   │ Attendance│         │ Assessments │         │ Fee Status  │        │
│   │ Marked    │────────►│ Recorded    │────────►│ Updated     │        │
│   │ (register)│         │ (CATs, HW)  │         │ (payments)  │        │
│   └───────────┘         └─────────────┘         └─────────────┘        │
│        │                      │                       │                 │
│        ▼                      ▼                       ▼                 │
│   student_attendance    exam_results          payment_transactions     │
│   student_id + date     student_id            student_id + amount      │
│   + status              + subject + marks     + method + receipt       │
│                                                                         │
└─────────────────────────────────────────────────────────────────────────┘
```

---

## 6. Database Tables Reference

### Core Student Tables

| Table | Purpose |
|-------|---------|
| `students` | Core student information (name, DOB, stream_id, status) |
| `class_enrollments` | Student-class relationship per academic year |
| `student_parents` | Links students to parents/guardians |

### Attendance & Academics

| Table | Purpose |
|-------|---------|
| `student_attendance` | Daily attendance records |
| `exam_results` | Formal exam results |
| `assessment_results` | Continuous assessment results |

### Finance

| Table | Purpose |
|-------|---------|
| `fee_structures_detailed` | Fee definitions per level/year/term |
| `student_fee_obligations` | What each student owes |
| `payment_transactions` | All payment records |

### Admission Workflow

| Table | Purpose |
|-------|---------|
| `admission_applications` | Application tracking |
| `admission_documents` | Document uploads and verification |
| `workflow_instances` | Workflow state management |

---

## 7. API Endpoints Reference

### Students

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/students/list` | List all students |
| GET | `/students/read/{id}` | Get student details |
| POST | `/students/create` | Create new student |
| PUT | `/students/update/{id}` | Update student |
| DELETE | `/students/delete/{id}` | Delete student |

### Enrollment

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/students/enrollments/{student_id}` | Get student's enrollments |
| POST | `/students/enroll` | Enroll student in class |

### Attendance

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/attendance/class/{class_id}` | Get class attendance |
| POST | `/attendance/mark` | Mark attendance |

### Fees

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/fees/student/{student_id}` | Get student fee obligations |
| POST | `/payments/record` | Record payment |

---

## 8. Common Queries

### Get Students in a Class

```sql
SELECT s.*, ce.enrollment_status
FROM students s
JOIN class_enrollments ce ON s.id = ce.student_id
JOIN academic_years ay ON ce.academic_year_id = ay.id
WHERE ce.class_id = 5
  AND ce.stream_id = 10
  AND ay.is_current = 1
  AND ce.enrollment_status = 'enrolled'
ORDER BY s.last_name;
```

### Get Students with Fee Arrears

```sql
SELECT s.id, s.admission_no, s.first_name, s.last_name,
       SUM(sfo.balance) as total_balance
FROM students s
JOIN student_fee_obligations sfo ON s.id = sfo.student_id
WHERE sfo.balance > 0
  AND sfo.academic_year_id = (SELECT id FROM academic_years WHERE is_current = 1)
GROUP BY s.id
HAVING total_balance > 0
ORDER BY total_balance DESC;
```

### Get Attendance Summary

```sql
SELECT s.id, s.first_name, s.last_name,
       COUNT(CASE WHEN sa.status = 'present' THEN 1 END) as days_present,
       COUNT(CASE WHEN sa.status = 'absent' THEN 1 END) as days_absent,
       COUNT(CASE WHEN sa.status = 'late' THEN 1 END) as days_late
FROM students s
LEFT JOIN student_attendance sa ON s.id = sa.student_id
WHERE sa.term_id = ?
  AND sa.class_id = ?
GROUP BY s.id;
```

---

## Appendix: Migration Files

1. `database/migrations/student_enrollment_procedures.sql` - Stored procedures for enrollment
2. `database/migrations/drop_student_user_id.sql` - Removed user_id from students table

---

**Last Updated**: December 2025  
**Maintained By**: Development Team
