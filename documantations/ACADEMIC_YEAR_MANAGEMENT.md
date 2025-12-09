# Academic Year Management System
## Kenyan CBC-Compliant Academic Year Lifecycle

**Last Updated:** 9 December 2025  
**System:** Kingsway Academy School Management System  
**Curriculum:** Competency-Based Curriculum (CBC)

---

## Overview

This document explains how Kingsway Academy manages academic years following the Kenyan education system and CBC (Competency-Based Curriculum) requirements.

### Kenyan Academic Year Structure

**Duration:** January to November (approx. 9 months)  
**Terms:** 3 terms per year  
**Breaks:** 1-month break between terms, 2-month break after Term 3

**Typical Calendar:**
- **Term 1:** January - March/April (12-13 weeks)
- **Break 1:** April (4 weeks)
- **Term 2:** May - July/August (12-13 weeks)
- **Break 2:** August (4 weeks)
- **Term 3:** September - November (12-13 weeks)
- **Long Break:** December - January (8 weeks)

---

## Academic Year Lifecycle

### 1. Planning Phase (`status = 'planning'`)

**What Happens:**
- Academic year is created in the system
- System auto-generates 3 terms with equal duration
- Initial calendar structure is set up
- Fee structures are prepared

**Actions Required:**
- Define year code (e.g., 2025)
- Set start and end dates
- Set registration windows
- Configure academic calendar

**System Behavior:**
```javascript
// When creating year 2025 with dates: 2025-01-15 to 2025-11-30
// System automatically creates:
- Term 1: 2025-01-15 to 2025-04-20 (status: 'upcoming')
- Term 2: 2025-04-21 to 2025-07-27 (status: 'upcoming')
- Term 3: 2025-07-28 to 2025-11-30 (status: 'upcoming')
```

---

### 2. Registration Phase (`status = 'registration'`)

**What Happens:**
- System opens for student enrollment
- New students can be admitted
- Returning students can be re-registered
- Fee collection begins

**Actions Required:**
- Activate student registration portal
- Process new admissions
- Confirm returning students
- Assign students to classes

**CBC Considerations:**
- Students grouped by Grade Levels (PP1, PP2, Grade 1-6, Junior Secondary)
- Learning areas assigned per grade
- Competency assessment frameworks initialized

---

### 3. Active Phase (`status = 'active'`)

**What Happens:**
- Academic year is fully operational
- Teaching and learning in progress
- Attendance tracking active
- Assessments being conducted
- Reports generated per term

**Per-Term Activities:**

**Term 1 (Current):**
- Baseline assessments
- Formative assessments begin
- Mid-term evaluations
- Term 1 reports

**Term 2 (Current):**
- Continued formative assessments
- Competency tracking
- Mid-term evaluations
- Term 2 reports

**Term 3 (Current):**
- Summative assessments
- Final competency evaluation
- End-of-year reports
- Promotion decisions

**CBC Assessment Cycle:**
- **Formative Assessment:** Ongoing (daily/weekly)
- **Mid-Term Assessment:** Middle of each term
- **Summative Assessment:** End of each term
- **Annual Assessment:** End of Term 3

---

### 4. Closing Phase (`status = 'closing'`)

**Critical Phase Before New Year Creation**

**What Must Happen:**
1. **Complete All Assessments**
   - All Term 3 assessments finalized
   - All grades submitted and approved
   - Competency levels determined

2. **Generate Final Reports**
   - Individual student reports
   - Class performance reports
   - School-wide analytics
   - CBC competency progress reports

3. **Student Promotions**
   - Determine promotion eligibility
   - Execute bulk promotions
   - Handle special cases (retention, acceleration)
   - Update student grade levels

4. **Data Archival**
   - Archive assessment data
   - Archive competency baselines
   - Archive attendance records
   - Archive fee transactions

5. **Financial Closure**
   - Clear all outstanding fees
   - Generate fee arrears reports
   - Archive financial records
   - Prepare new fee structures

**System Workflow:**
```bash
Use: Academic Year Transition Workflow

Stages:
1. Prepare Calendar → Create new year calendar
2. Archive Data → Archive previous year records
3. Execute Promotions → Bulk promote students
4. Setup New Year → Create classes, terms, structures
5. Migrate Baselines → Transfer CBC competency data
6. Validate Readiness → Final system checks
```

---

### 5. Archived Phase (`status = 'archived'`)

**What Happens:**
- Year is read-only
- Historical data preserved
- Reports can still be accessed
- Data used for trends analysis

**Archived Data Includes:**
- Student records and promotions
- Assessment results
- Competency progress
- Attendance records
- Fee transactions
- Academic reports

---

## Creating a New Academic Year

### Prerequisites Checklist

✅ **Before Creating New Year, Ensure:**

1. **Previous Year Status = 'archived'**
   ```sql
   -- Check current year status
   SELECT year_code, status, is_current 
   FROM academic_years 
   WHERE is_current = 1;
   ```

2. **All Promotions Completed**
   ```sql
   -- Check pending promotions
   SELECT COUNT(*) FROM student_promotions 
   WHERE academic_year = 2024 AND status = 'pending';
   ```

3. **All Reports Generated**
   ```sql
   -- Check report completion
   SELECT term, COUNT(*) as total_reports 
   FROM student_reports 
   WHERE academic_year = 2024 
   GROUP BY term;
   ```

4. **Fee Structures Updated**
   - New fee structure configured
   - Fee categories defined
   - Payment schedules set

5. **Data Archived**
   ```sql
   -- Verify archival
   SELECT * FROM academic_year_archives 
   WHERE academic_year = 2024;
   ```

### Creation Process

**Via UI:**
1. Navigate to: **Manage Academics → Academic Years Tab**
2. Click: **"Add Year"**
3. System shows warning if current year not archived
4. Fill in required fields:
   - Year Code (e.g., 2025)
   - Year Name (e.g., "2025 Academic Year")
   - Start Date (e.g., 2025-01-15)
   - End Date (e.g., 2025-11-30)
   - Registration Period
   - Status (start with 'planning')

5. Click **"Create Year"**
6. System automatically:
   - Creates 3 terms with equal duration
   - Sets up academic calendar structure
   - Initializes assessment frameworks
   - Prepares competency tracking

**Via Workflow (Recommended):**
1. Use: **Academic Year Transition Workflow**
2. Follow guided 6-stage process
3. System ensures all prerequisites met
4. Migrates competency baselines automatically
5. Validates readiness before activation

---

## Data Isolation Per Year

### Year-Specific Data

Each academic year maintains **separate, isolated data**:

**Academic Data:**
- Terms (3 per year)
- Classes and Streams
- Timetables and Schedules
- Lesson Plans
- Curriculum Units

**Assessment Data:**
- Formative Assessments
- Summative Assessments
- Competency Evaluations
- Student Reports

**Student Data:**
- Class Assignments (per year)
- Grade Level (changes with promotion)
- Attendance Records
- Behavior Records

**Financial Data:**
- Fee Structures (can change per year)
- Fee Transactions
- Payment History
- Arrears Tracking

### Cross-Year Data

**Data That Carries Forward:**

1. **Student Core Records**
   - Student ID (permanent)
   - Personal Information
   - Parent/Guardian Details
   - Medical Records

2. **CBC Competency Baselines**
   - Transferred via Year Transition Workflow
   - Ensures continuity of competency tracking
   - Maintains performance trends

3. **Historical Performance**
   - Accessible via student history
   - Used for trend analysis
   - Informs teaching strategies

---

## Database Schema

### Key Tables

```sql
-- Academic Years
academic_years (
    id, year_code, year_name, 
    start_date, end_date,
    status ENUM('planning', 'registration', 'active', 'closing', 'archived'),
    is_current BOOLEAN
)

-- Terms (linked to year)
academic_terms (
    id, name, term_number,
    year YEAR(4),  -- Links to academic year
    start_date, end_date,
    status ENUM('upcoming', 'current', 'completed')
)

-- Year Archives
academic_year_archives (
    id, academic_year,
    total_students, promoted_count, 
    graduated_count, closure_date,
    status ENUM('active', 'closing', 'archived', 'readonly')
)

-- Promotions
student_promotions (
    id, student_id, 
    from_grade, to_grade,
    academic_year,
    promotion_status
)

-- Class Year Assignments
class_year_assignments (
    class_id, academic_year,
    student_count, status
)

-- Fee Transition History
fee_transition_history (
    previous_year, new_year,
    fee_structure_changes,
    transition_date
)
```

---

## Best Practices

### 1. Year Planning
- ✅ Plan new year **2-3 months in advance**
- ✅ Align dates with national academic calendar
- ✅ Consider public holidays and breaks
- ✅ Coordinate with Ministry of Education calendar

### 2. Year Transition
- ✅ **Use the Workflow** - Don't create years manually
- ✅ Complete all assessments before closing
- ✅ Generate and distribute all reports
- ✅ Archive data systematically
- ✅ Validate data integrity after archival

### 3. Student Promotions
- ✅ Base on CBC competency levels
- ✅ Consider teacher recommendations
- ✅ Handle exceptions individually
- ✅ Communicate with parents before finalizing

### 4. Fee Management
- ✅ Update fee structures for new year
- ✅ Clear arrears before transition
- ✅ Document fee changes
- ✅ Communicate fee changes to parents

### 5. Data Management
- ✅ Regular backups before major transitions
- ✅ Verify data integrity after promotions
- ✅ Maintain audit trails
- ✅ Keep archived data accessible

---

## Common Scenarios

### Scenario 1: Mid-Year Student Transfer

**Question:** Student joins in Term 2 of ongoing year

**Solution:**
1. Register student to active year
2. Assign to appropriate grade/class
3. Obtain transfer records from previous school
4. Update competency baselines if available
5. Start assessment from current term

### Scenario 2: Year Created Too Early

**Question:** Accidentally created 2026 while 2025 is active

**Solution:**
1. Don't panic - years can coexist
2. Keep 2026 in 'planning' status
3. Properly close 2025 first
4. Transition to 2026 when ready
5. Delete 2026 and recreate if major errors

### Scenario 3: Forgot to Promote Students

**Question:** Started new year without promotions

**Solution:**
1. Pause new year activities
2. Go back to previous year
3. Execute promotions workflow
4. Verify all students promoted
5. Resume new year operations

### Scenario 4: Fee Structure Change Mid-Year

**Question:** Need to adjust fees in active year

**Solution:**
1. Create fee structure amendment
2. Document reason for change
3. Communicate to parents (30 days notice recommended)
4. Apply to new enrollments only OR
5. Pro-rate for existing students

---

## API Endpoints

### Academic Year Management

```javascript
// List all years
GET /api/academic/years/list

// Get current year
GET /api/academic/years/current

// Create new year
POST /api/academic/years/create
Body: {
    year_code: "2025",
    year_name: "2025 Academic Year",
    start_date: "2025-01-15",
    end_date: "2025-11-30",
    registration_start: "2024-11-01",
    registration_end: "2025-01-10",
    status: "planning"
}

// Update year
PUT /api/academic/years/update/{id}

// Set as current year
PUT /api/academic/years/set-current/{id}

// Archive year
POST /api/academic/years/archive/{id}
```

### Year Transition Workflow

```javascript
// Start transition workflow
POST /api/academic/workflows/year-transition/start

// Stages
POST /api/academic/workflows/year-transition/prepare-calendar
POST /api/academic/workflows/year-transition/archive-data
POST /api/academic/workflows/year-transition/execute-promotions
POST /api/academic/workflows/year-transition/setup-new-year
POST /api/academic/workflows/year-transition/migrate-baselines
POST /api/academic/workflows/year-transition/validate-readiness
```

---

## Troubleshooting

### Issue: Can't create new year

**Possible Causes:**
- Current year not archived
- Duplicate year code
- Invalid date range
- Missing permissions

**Solutions:**
1. Check current year status
2. Verify year code is unique
3. Ensure end_date > start_date
4. Verify user has 'academic:year:create' permission

### Issue: Terms not auto-created

**Possible Causes:**
- Database error during creation
- Transaction rollback
- Invalid date calculation

**Solutions:**
1. Check error logs
2. Manually create terms via Terms tab
3. Contact system administrator

### Issue: Students not appearing in new year

**Possible Causes:**
- Promotions not executed
- Year not set as current
- Class assignments missing

**Solutions:**
1. Run promotion workflow
2. Set year as current
3. Verify class structures exist
4. Re-assign students to classes

---

## Support & Resources

**Documentation:**
- [CBC Assessment Guide](./CBC_ASSESSMENT_GUIDE.md)
- [Student Promotion Workflow](./STUDENT_PROMOTION.md)
- [Fee Management](./FEE_MANAGEMENT.md)

**System Logs:**
- Error Logs: `logs/errors.log`
- Activity Logs: `logs/system_activity.log`
- Workflow Logs: `logs/workflow_activity.log`

**Database Views:**
- `vw_fee_collection_by_year` - Fee summary per year
- `vw_student_payment_history_multi_year` - Cross-year payments
- `vw_fee_transition_audit` - Fee structure changes

**Contact:**
- System Administrator
- Academic Coordinator
- IT Support Team

---

**Document Version:** 1.0  
**System Version:** Kingsway Academy v2.0  
**CBC Compliance:** ✅ Verified
