# Online Admission Submission Process

## Architecture Overview

The system uses a **two-table architecture** to handle online and physical admissions:

### Tables
1. **`web_admission_applications`** - Raw public website submissions (audit copy)
2. **`admission_applications`** - Canonical admissions workflow record (used by all internal pages)

### Relationship
```
web_admission_applications.id
        ↓ promoted/copied into
admission_applications.web_application_id
admission_applications.application_source = 'online'
```

### Key Fields
- `admission_applications.application_source` = 'online' or 'physical'
- `admission_applications.web_application_id` = Links to web_admission_applications (NULL for physical applications)

## Frontend Process (admissions.php)

### 1. Form Structure
- **Multi-step form** with 4 sections:
  - Section 1: Child Information (name, DOB, gender, nationality, grade applying, previous school)
  - Section 2: Parent/Guardian Information (name, relationship, ID, phone, email, address)
  - Section 3: Application Details (boarding preference, start term, referral source, special needs)
  - Section 4: Declaration & Submit (summary, checkbox declaration, submit button)

### 2. Form Submission Flow
1. User fills out all required fields across the 4 sections
2. JavaScript validates required fields before allowing navigation between sections
3. On final submit, JavaScript:
   - Validates the declaration checkbox is checked
   - Disables submit button and shows loading state
   - Collects form data using `FormData`
   - Sends POST request to `admissions.php` (self-post)
   - Expects JSON response

### 3. Client-Side Validation
```javascript
// Required field validation before next step
const required = section.querySelectorAll('[required]');
for (const el of required) {
    if (!el.value.trim()) {
        el.focus();
        el.style.borderColor = '#dc3545';
        return; // Stop navigation
    }
}
```

## Backend Process (public_data.php)

### 1. POST Handler
The PHP script checks for POST request and processes form data:

```php
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    
    // Extract and validate required fields
    $childName  = trim($_POST['child_name']  ?? '');
    $parentName = trim($_POST['parent_name'] ?? '');
    $phone      = trim($_POST['parent_phone'] ?? '');
    $grade      = trim($_POST['grade_applying'] ?? '');
    
    if (!$childName || !$parentName || !$phone || !$grade) {
        echo json_encode(['success'=>false,'message'=>'Please fill in all required fields.']);
        exit;
    }
    
    // Call save function
    $ref = kw_save_admission_application([...]);
    
    // Return JSON response
    if ($ref) {
        echo json_encode(['success'=>true,'ref'=>$ref, 'message'=>"Application received! Your reference number is <strong>{$ref}</strong>..."]);
    } else {
        echo json_encode(['success'=>false, 'message'=>'Submission failed...']);
    }
    exit;
}
```

### 2. Data Storage (Two-Step Process)
The `kw_save_admission_application()` function performs a transaction:

#### Step 1: Insert Raw Data (Audit Copy)
```php
// Insert into web_admission_applications
$webRef = 'WEB-' . strtoupper(substr(md5(uniqid()), 0, 6));
INSERT INTO web_admission_applications (child_full_name, child_dob, child_gender, ...)
VALUES (...)
$webAppId = $db->lastInsertId();
```

#### Step 2: Create/Find Parent Record
```php
// Try to find existing parent by phone or ID
SELECT id FROM parents WHERE phone_1 = ? OR id_number = ?

// If not found, create new parent
INSERT INTO parents (first_name, last_name, id_number, phone_1, ...)
VALUES (...)
$parentId = $db->lastInsertId();
```

#### Step 3: Insert into Canonical Table
```php
// Map grade from web form to admission_applications enum
$gradeMapping = [
    'PP1' => 'PP1',
    'Grade 1' => 'Grade1',
    // ...
];

// Insert into admission_applications (workflow table)
$appRef = 'KWA-' . strtoupper(substr(md5(uniqid()), 0, 6));
INSERT INTO admission_applications (
    application_no, applicant_name, date_of_birth, gender,
    grade_applying_for, academic_year, previous_school,
    parent_id, application_source, web_application_id,
    has_special_needs, special_needs_details, status
) VALUES (
    ?, ?, ?, ?, ?, YEAR(CURDATE() + INTERVAL 6 MONTH), ?,
    ?, 'online', ?, ?, ?, 'submitted'
)
```

### 3. Database Table Structures

#### web_admission_applications (Raw Submissions)
| Field | Type | Description |
|-------|------|-------------|
| id | int(10) unsigned | Auto-increment primary key |
| child_full_name | varchar(100) | Child's full name |
| child_dob | date | Date of birth |
| child_gender | enum('male','female','other') | Gender |
| child_nationality | varchar(50) | Nationality (default: Kenyan) |
| child_prev_school | varchar(255) | Previous school |
| child_prev_grade | varchar(50) | Previous grade |
| parent_name | varchar(100) | Parent/guardian name |
| parent_relationship | varchar(50) | Relationship to child |
| parent_id_number | varchar(50) | Parent ID number |
| parent_phone | varchar(30) | Parent phone (required) |
| parent_alt_phone | varchar(30) | Alternative phone |
| parent_email | varchar(200) | Email address |
| parent_address | varchar(255) | Residential address |
| grade_applying | varchar(20) | Grade applying for |
| boarding_preference | enum('day','full_boarding','weekly_boarding') | Boarding preference |
| preferred_start | varchar(50) | Preferred start term |
| referral_source | varchar(100) | How they heard about the school |
| special_needs | text | Special needs information |
| application_ref | varchar(20) | Unique reference number (WEB-XXXXXX) |
| ip_address | varchar(45) | Applicant's IP address |
| status | enum('new','reviewed','approved','rejected') | Application status |
| created_at | timestamp | Record creation time |
| updated_at | timestamp | Last update time |

#### admission_applications (Canonical Workflow Table)
| Field | Type | Description |
|-------|------|-------------|
| id | int(10) unsigned | Auto-increment primary key |
| application_no | varchar(20) | Application number (KWA-XXXXXX) |
| applicant_name | varchar(100) | Applicant's full name |
| date_of_birth | date | Date of birth |
| gender | enum('male','female','other') | Gender |
| grade_applying_for | enum('Playground','PP1','PP2','Grade1',...) | Grade applying for |
| academic_year | year(4) | Academic year |
| previous_school | varchar(255) | Previous school |
| parent_id | int(10) unsigned | Foreign key to parents table |
| **application_source** | enum('online','physical') | **Source of application** |
| **web_application_id** | int(10) unsigned | **Link to web_admission_applications** |
| admission_category | enum('standard','nursery_term_1','nursery_term_3') | Admission category |
| target_term_id | int(10) unsigned | Target term |
| requires_interview | tinyint(1) | Whether interview is required |
| interview_policy_reason | varchar(255) | Reason for interview policy |
| has_special_needs | tinyint(1) | Has special needs |
| special_needs_details | text | Special needs details |
| status | enum('submitted','documents_pending','documents_verified','placement_offered','fees_pending','enrolled','cancelled') | Workflow status |
| created_at | timestamp | Record creation time |
| updated_at | timestamp | Last update time |

## Admin Panel Integration

### Viewing Applications
All admin admissions pages (`pages/admissions/*.php`, `js/pages/admissions.js`) read from `admission_applications` table only. They can:

1. **View all applications** - Both online and physical appear together
2. **Filter by source** - Using `application_source` column
3. **Display source badge** - Show "Online" or "Physical" badge on each application
4. **Link to original submission** - Use `web_application_id` to view raw web submission if needed

### Internal Applications
When staff create applications via the admin panel (`new_application_form` in `admissions_base.php`):
- Insert directly into `admission_applications`
- Set `application_source = 'physical'`
- Set `web_application_id = NULL`
- Continue with normal workflow

### Workflow Processing
The workflow system (`api/modules/admission/StudentAdmissionWorkflow.php`) only uses `admission_applications`:
- All workflow stages operate on `admission_applications.id`
- Workflow tables reference `admission_applications.id`
- No changes needed to existing workflow logic

## Response Handling

### Success Response
```json
{
    "success": true,
    "ref": "KWA-A1B2C3",
    "message": "Application received! Your reference number is <strong>KWA-A1B2C3</strong>. Our admissions team will contact you within 24 hours."
}
```

### Error Response
```json
{
    "success": false,
    "message": "Submission failed. Please try calling us directly on 0720 113 030."
}
```

## User Experience Flow

1. **Form Completion**: User fills out the multi-step form
2. **Validation**: JavaScript validates required fields in real-time
3. **Submission**: User accepts declaration and clicks submit
4. **Processing**: Button shows loading state, form data sent via AJAX
5. **Database Transaction**: 
   - Raw data saved to `web_admission_applications`
   - Parent record created/found
   - Canonical record created in `admission_applications`
6. **Success**: Form hides, success message displays with reference number
7. **Admin Visibility**: Application appears in admin panel with "Online" source badge
8. **Workflow**: Application enters normal admissions workflow

## Security Features

- **Server-side validation**: Required fields checked again on server
- **IP tracking**: Applicant's IP address recorded in web table
- **Transaction safety**: All operations in a single database transaction
- **Prepared statements**: SQL injection prevention via PDO prepared statements
- **Reference numbers**: Unique random references for both tables
- **Audit trail**: Raw submission preserved in web_admission_applications
- **Error handling**: Graceful failure with rollback and fallback phone number

## Integration Points

- **Database**: Uses both `web_admission_applications` (audit) and `admission_applications` (workflow)
- **Parent System**: Integrates with existing `parents` table
- **Public Layout**: Uses `public/layout/public_data.php` for database operations
- **Admin Panel**: All admissions pages read from `admission_applications` only
- **Workflow System**: Existing workflow logic unchanged, uses `admission_applications`
- **Error Handling**: Returns school phone number from `kw_school_stat()` on failure
- **Response Format**: JSON for AJAX handling on frontend

## Benefits of This Architecture

1. **Separation of Concerns**: Raw submissions separate from workflow records
2. **Audit Trail**: Original web submission preserved for reference
3. **Unified Workflow**: All applications (online/physical) use same workflow
4. **Flexibility**: Easy to add more sources (mobile app, third-party integrations)
5. **Data Integrity**: Parent records normalized into dedicated table
6. **Backward Compatibility**: Existing workflow logic unchanged
7. **Filtering**: Easy to filter/report by application source

## Future Enhancements

Potential improvements could include:
- Email confirmation to applicant with application details
- SMS notification to admissions team for new online applications
- File upload for required documents directly from web form
- Status tracking portal for applicants to check progress
- API endpoint to retrieve web submission details for admin review
- Automated parent account creation with portal access
