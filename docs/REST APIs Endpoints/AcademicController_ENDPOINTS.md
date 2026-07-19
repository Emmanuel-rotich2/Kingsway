# AcademicController API Endpoints

## get

**What it's used for:**
Retrieve academic records. If called as GET /api/academic, it lists all records. If called as GET /api/academic/{id}, it retrieves a specific record by ID.

**How frontend calls it:**

- To list all: `GET /api/academic`
- To get one: `GET /api/academic/{id}`

**Request parameters:**

- For list: none required
- For single: `id` in URL

**Sample request:**

```http
GET /api/academic/1
```

**Sample response:**

```json
{
 "id": 1,
 "student_id": 123,
 "year": 2024,
 "class": "Grade 8",
 "term": "Term 1",
 "average_score": 85.2
}
```

---

## post


**What it's used for:**
Create a new academic record.

**How frontend calls it:**

- `POST /api/academic` with JSON body

**Request body:**

```json
{
 "student_id": 123,
 "year": 2024,
 "class": "Grade 8",
 "term": "Term 1",
 "average_score": 85.2
}
```

**Sample response:**

```json
{
 "success": true,
 "message": "Academic record created successfully.",
 "record": {
  "id": 3,
  "student_id": 123,
  "year": 2024,
  "class": "Grade 8",
  "term": "Term 1",
  "average_score": 85.2
 }
}
```

---

## put

**What it's used for:**
Update an existing academic record.

**How frontend calls it:**

- `PUT /api/academic/{id}` with JSON body

**Request parameters:**

- `id` in URL

**Request body:**

```json
{
 "average_score": 90.0
}
```

**Sample response:**

```json
{
 "success": true,
 "message": "Academic record updated successfully.",
 "record": {
  "id": 1,
  "student_id": 123,
  "year": 2024,
  "class": "Grade 8",
  "term": "Term 1",
  "average_score": 90.0
 }
}
```

## delete

---

**What it's used for:**
Delete an academic record by its ID.

**How frontend calls it:**

- `DELETE /api/academic/{id}`

**Request parameters:**

- `id` in URL

**Sample request:**

```http
DELETE /api/academic/1
```

**Sample response:**

```json
{
 "success": true,
 "message": "Academic record deleted successfully."
}
```

---

## postExamsStartWorkflow

**What it's used for:**
Start a new examination cycle (planning stage).

**How frontend calls it:**
- `POST /api/academic/exams/start-workflow` with JSON body

**Request body:**
```json
{
    "title": "End of Term Exams",
    "classification_code": "SA",
    "term_id": 2,
    "academic_year": 2025,
    "start_date": "2025-11-20",
    "end_date": "2025-11-30",
    "formative_weight": 0.4,
    "summative_weight": 0.6
}
```

**Sample response:**
```json
{
    "success": true,
    "data": {
        "workflow_instance_id": 101,
        "next_stage": "schedule_creation"
    },
    "message": "Assessment cycle planned successfully"
}
```

---

## postExamsCreateSchedule

**What it's used for:**
Create a detailed exam timetable for the cycle.

**How frontend calls it:**
- `POST /api/academic/exams/create-schedule` with JSON body

**Request body:**
```json
{
    "instance_id": 101,
    "schedule_entries": [
        {
            "class_id": 5,
            "subject_id": 12,
            "exam_date": "2025-11-22",
            "start_time": "09:00",
            "end_time": "11:00",
            "max_marks": 100,
            "title": "Mathematics Paper 1",
            "room_id": 3,
            "invigilator_id": 45,
            "learning_outcome_id": 7,
            "competency_ids": [1,2,3]
        }
    ]
}
```

**Sample response:**
```json
{
    "success": true,
    "data": {
        "instance_id": 101,
        "assessments_created": 1
    },
    "message": "Assessment schedule created successfully"
}
```

---

## postExamsSubmitQuestions

**What it's used for:**
Submit a question paper file for an assessment.

**How frontend calls it:**
- `POST /api/academic/exams/submit-questions` with form-data (file upload) or JSON body

**Request body:**
```json
{
    "instance_id": 101,
    "subject_id": 12,
    "paper_data": {
        "file": "(binary file upload)",
        "filename": "math_paper1.pdf"
    }
}
```

**Sample response:**
```json
{
    "success": true,
    "data": {
        "assessment_id": 201,
        "paper_path": "/uploads/assessments/201/papers/math_paper1.pdf"
    },
    "message": "Question paper submitted successfully"
}
```

---

## postExamsPrepareLogistics

**What it's used for:**
Prepare logistics for the exam (materials, venues, seating).

**How frontend calls it:**
- `POST /api/academic/exams/prepare-logistics` with JSON body

**Request body:**
```json
{
    "instance_id": 101,
    "materials_prepared": true,
    "venues_confirmed": true,
    "seating_arranged": true,
    "invigilators_briefed": true
}
```

**Sample response:**
```json
{
    "success": true,
    "message": "Exam logistics prepared successfully"
}
```

---

## postExamsConduct

**What it's used for:**
Mark the start of the exam period and record exam administration.

**How frontend calls it:**
- `POST /api/academic/exams/conduct` with JSON body

**Request body:**
```json
{
    "instance_id": 101,
    "assessment_id": 201,
    "notes": "Exam started on time."
}
```

**Sample response:**
```json
{
    "success": true,
    "data": {
        "assessment_id": 201
    },
    "message": "Exam conducted successfully"
}
```

---

## postExamsAssignMarking

**What it's used for:**
Assign teachers to mark exams.

**How frontend calls it:**
- `POST /api/academic/exams/assign-marking` with JSON body

**Request body:**
```json
{
    "instance_id": 101,
    "assignments": [
        {"assessment_id": 201, "marker_id": 55},
        {"assessment_id": 202, "marker_id": 56}
    ]
}
```

**Sample response:**
```json
{
    "success": true,
    "data": {
        "assignments_count": 2,
        "assigned_by": 12,
        "assigned_at": "2025-11-21T10:00:00"
    },
    "message": "Markers assigned."
}
```

---

## postExamsRecordMarks

**What it's used for:**
Record marks for students in a given assessment.

**How frontend calls it:**
- `POST /api/academic/exams/record-marks` with JSON body

**Request body:**
```json
{
    "assessment_id": 201,
    "marks": [
        {"student_id": 123, "score": 78},
        {"student_id": 124, "score": 85}
    ]
}
```

**Sample response:**
```json
{
    "success": true,
    "data": {
        "assessment_id": 201,
        "marks_recorded": 2
    },
    "message": "Marks recorded successfully."
}
```

---

## postExamsVerifyMarks

**What it's used for:**
Verify the accuracy of recorded marks before moderation.

**How frontend calls it:**
- `POST /api/academic/exams/verify-marks` with JSON body

**Request body:**
```json
{
    "assessment_id": 201,
    "verified_by": 45,
    "notes": "All marks checked and verified."
}
```

**Sample response:**
```json
{
    "success": true,
    "message": "Marks verified successfully."
}
```

---

## postExamsModerateMarks

**What it's used for:**
Moderate marks to ensure fairness and consistency.

**How frontend calls it:**
- `POST /api/academic/exams/moderate-marks` with JSON body

**Request body:**
```json
{
    "assessment_id": 201,
    "moderated_by": 46,
    "adjustments": [
        {"student_id": 123, "new_score": 80}
    ]
}
```

**Sample response:**
```json
{
    "success": true,
    "message": "Marks moderated successfully."
}
```

---

## postExamsCompileResults

**What it's used for:**
Compile all results for the exam cycle.

**How frontend calls it:**
- `POST /api/academic/exams/compile-results` with JSON body

**Request body:**
```json
{
    "instance_id": 101
}
```

**Sample response:**
```json
{
    "success": true,
    "data": {
        "results_compiled": true
    },
    "message": "Results compiled successfully."
}
```

---

## postExamsApproveResults

**What it's used for:**
Approve compiled results for release.

**How frontend calls it:**
- `POST /api/academic/exams/approve-results` with JSON body

**Request body:**
```json
{
    "instance_id": 101,
    "approved_by": 50
}
```

**Sample response:**
```json
{
    "success": true,
    "message": "Results approved for release."
}
```

---

## postPromotionsStartWorkflow

**What it's used for:**
Start the student promotion workflow for a new academic year.

**How frontend calls it:**
- `POST /api/academic/promotions/start-workflow` with JSON body

**Request body:**
```json
{
    "academic_year": 2026,
    "term_id": 1
}
```

**Sample response:**
```json
{
    "success": true,
    "data": {
        "promotion_instance_id": 301
    },
    "message": "Promotion workflow started."
}
```

---

## postPromotionsIdentifyCandidates

**What it's used for:**
Identify students eligible for promotion.

**How frontend calls it:**
- `POST /api/academic/promotions/identify-candidates` with JSON body

**Request body:**
```json
{
    "promotion_instance_id": 301
}
```

**Sample response:**
```json
{
    "success": true,
    "data": {
        "candidates_found": 120
    },
    "message": "Promotion candidates identified."
}
```

---

## postPromotionsValidateEligibility

**What it's used for:**
Validate eligibility of identified students for promotion.

**How frontend calls it:**
- `POST /api/academic/promotions/validate-eligibility` with JSON body

**Request body:**
```json
{
    "promotion_instance_id": 301,
    "criteria": ["passed_all_subjects", "attendance_above_80"]
}
```

**Sample response:**
```json
{
    "success": true,
    "data": {
        "eligible_count": 110
    },
    "message": "Eligibility validated."
}
```

---

## postPromotionsExecute

**What it's used for:**
Execute the promotion of eligible students.

**How frontend calls it:**
- `POST /api/academic/promotions/execute` with JSON body

**Request body:**
```json
{
    "promotion_instance_id": 301
}
```

**Sample response:**
```json
{
    "success": true,
    "data": {
        "students_promoted": 110
    },
    "message": "Promotion executed."
}
```

---

## postPromotionsGenerateReports

**What it's used for:**
Generate reports for the promotion process.

**How frontend calls it:**
- `POST /api/academic/promotions/generate-reports` with JSON body

**Request body:**
```json
{
    "promotion_instance_id": 301
}
```

**Sample response:**
```json
{
    "success": true,
    "data": {
        "report_url": "/reports/promotions/301.pdf"
    },
    "message": "Promotion reports generated."
}
```

---

## postAssessmentsStartWorkflow

---

## postAssessmentsCreateItems

**What it's used for:**
Create assessment items (questions/tasks) for a given assessment.

**How frontend calls it:**
- `POST /api/academic/assessments/create-items` with JSON body

**Request body:**
```json
{
    "assessment_id": 401,
    "items": [
        {"type": "multiple_choice", "question": "What is 2+2?", "choices": ["3", "4", "5"], "answer": "4"},
        {"type": "essay", "question": "Explain the water cycle."}
    ]
}
```

**Sample response:**
```json
{
    "success": true,
    "items_created": 2,
    "message": "Assessment items created."
}
```

---

## postAssessmentsAdminister

**What it's used for:**
Administer an assessment to students (start, record attendance, etc).

**How frontend calls it:**
- `POST /api/academic/assessments/administer` with JSON body

**Request body:**
```json
{
    "assessment_id": 401,
    "administered_by": 45,
    "notes": "Assessment started at 10:00 AM."
}
```

**Sample response:**
```json
{
    "success": true,
    "message": "Assessment administered."
}
```

---

## postAssessmentsMarkAndGrade

**What it's used for:**
Mark and grade student submissions for an assessment.

**How frontend calls it:**
- `POST /api/academic/assessments/mark-and-grade` with JSON body

**Request body:**
```json
{
    "assessment_id": 401,
    "marks": [
        {"student_id": 123, "score": 18},
        {"student_id": 124, "score": 20}
    ]
}
```

**Sample response:**
```json
{
    "success": true,
    "message": "Assessment marked and graded."
}
```

---

## postAssessmentsAnalyzeResults

**What it's used for:**
Analyze results for trends, averages, and insights.

**How frontend calls it:**
- `POST /api/academic/assessments/analyze-results` with JSON body

**Request body:**
```json
{
    "assessment_id": 401
}
```

**Sample response:**
```json
{
    "success": true,
    "data": {
        "average_score": 19,
        "highest_score": 20,
        "lowest_score": 15
    },
    "message": "Assessment results analyzed."
}
```

---

## postReportsStartWorkflow

**What it's used for:**
Start the process of generating academic reports.

**How frontend calls it:**
- `POST /api/academic/reports/start-workflow` with JSON body

**Request body:**
```json
{
    "term_id": 2,
    "academic_year": 2025
}
```

**Sample response:**
```json
{
    "success": true,
    "data": {
        "report_instance_id": 501
    },
    "message": "Report workflow started."
}
```

---

## postReportsCompileData

**What it's used for:**
Compile all necessary data for report generation.

**How frontend calls it:**
- `POST /api/academic/reports/compile-data` with JSON body

**Request body:**
```json
{
    "report_instance_id": 501
}
```

**Sample response:**
```json
{
    "success": true,
    "message": "Report data compiled."
}
```

---

## postReportsGenerateStudentReports

**What it's used for:**
Generate individual student reports.

**How frontend calls it:**
- `POST /api/academic/reports/generate-student-reports` with JSON body

**Request body:**
```json
{
    "report_instance_id": 501
}
```

**Sample response:**
```json
{
    "success": true,
    "data": {
        "reports_generated": 200
    },
    "message": "Student reports generated."
}
```

---

## postReportsReviewAndApprove

**What it's used for:**
Review and approve generated reports before distribution.

**How frontend calls it:**
- `POST /api/academic/reports/review-and-approve` with JSON body

**Request body:**
```json
{
    "report_instance_id": 501,
    "approved_by": 50
}
```

**Sample response:**
```json
{
    "success": true,
    "message": "Reports reviewed and approved."
}
```

---

## postReportsDistribute

**What it's used for:**
Distribute approved reports to students/parents.

**How frontend calls it:**
- `POST /api/academic/reports/distribute` with JSON body

**Request body:**
```json
{
    "report_instance_id": 501
}
```

**Sample response:**
```json
{
    "success": true,
    "message": "Reports distributed."
}
```

---

## postLibraryStartWorkflow

**What it's used for:**
Start a new library workflow (e.g., cataloging, lending).

**How frontend calls it:**
- `POST /api/academic/library/start-workflow` with JSON body

**Request body:**
```json
{
    "workflow_type": "cataloging"
}
```

**Sample response:**
```json
{
    "success": true,
    "data": {
        "library_workflow_id": 601
    },
    "message": "Library workflow started."
}
```

---

## postLibraryReviewRequest

**What it's used for:**
Review a library resource request (e.g., book request).

**How frontend calls it:**
- `POST /api/academic/library/review-request` with JSON body

**Request body:**
```json
{
    "request_id": 701,
    "reviewed_by": 45,
    "status": "approved"
}
```

**Sample response:**
```json
{
    "success": true,
    "message": "Library request reviewed."
}
```

---

## postLibraryCatalogResources

**What it's used for:**
Catalog new library resources (books, media, etc).

**How frontend calls it:**
- `POST /api/academic/library/catalog-resources` with JSON body

**Request body:**
```json
{
    "resources": [
        {"title": "Chemistry 101", "type": "book", "author": "Jane Doe"}
    ]
}
```

**Sample response:**
```json
{
    "success": true,
    "resources_cataloged": 1,
    "message": "Resources cataloged."
}
```

---

## postLibraryDistributeAndTrack

**What it's used for:**
Distribute and track library resources (e.g., lending books).

**How frontend calls it:**
- `POST /api/academic/library/distribute-and-track` with JSON body

**Request body:**
```json
{
    "resource_id": 801,
    "borrower_id": 123,
    "due_date": "2025-12-01"
}
```

**Sample response:**
```json
{
    "success": true,
    "message": "Resource distributed and tracking started."
}
```

---

## postCurriculumStartWorkflow

---

## postCurriculumMapOutcomes

---

## postCurriculumCreateScheme

---

## postCurriculumReviewAndApprove

---

## postYearTransitionStartWorkflow

---

## postYearTransitionArchiveData

---

## postYearTransitionExecutePromotions

---

## postYearTransitionSetupNewYear

---

## postYearTransitionMigrateCompetencyBaselines

---

## postYearTransitionValidateReadiness

---

## postCompetencyRecordEvidence

---

## postCompetencyRecordCoreValueEvidence

---

## getCompetencyDashboard

---

## postTermsCreate

---

## getTermsList

---

## postClassesCreate

---

## getClassesList

---

## getClassesGet

---

## putClassesUpdate

---

## deleteClassesDelete

---

## postClassesAssignTeacher

---

## postClassesAutoCreateStreams

---

## postStreamsCreate

---

## getStreamsList

---

## postSchedulesCreate

---

## getSchedulesList

---

## getSchedulesGet

---

## putSchedulesUpdate

---

## deleteSchedulesDelete

---

## postSchedulesAssignRoom

---

## postCurriculumUnitsCreate

---

## getCurriculumUnitsList

---

## getCurriculumUnitsGet

---

## putCurriculumUnitsUpdate

---

## deleteCurriculumUnitsDelete

---

## postTopicsCreate

---

## getTopicsList

---

## getTopicsGet

---

## putTopicsUpdate

---

## deleteTopicsDelete

---

## postLessonPlansCreate

---

## getLessonPlansList

---

## getLessonPlansGet

---

## putLessonPlansUpdate

---

## deleteLessonPlansDelete

---

## postLessonPlansApprove

---

## postLessonObservationsCreate

---

## getLessonObservationsList

---

## postSchemeOfWorkCreate

---

## getSchemeOfWorkGet

---

## getTeachersClasses

---

## getTeachersSubjects

---

## getTeachersSchedule

---

## getSubjectsTeachers

---

## getWorkflowStatus

---

## getCustom

---

## postCustom

---

