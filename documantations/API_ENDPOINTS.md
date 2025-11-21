

# API Endpoints Documentation (Full List)

This document lists every available API endpoint, grouped by controller, with HTTP method, path, and a brief description. For request/response fields, see the controller or API module.

---

## UsersController

**Authentication & User Management**
- `POST   /api/users/login` — Authenticate user
- `POST   /api/users/logout` — Logout user
- `POST   /api/users/register` — Register new user
- `GET    /api/users/{id}` — Get user by ID
- `PUT    /api/users/{id}` — Update user
- `DELETE /api/users/{id}` — Delete user

**Password & Roles**
- `PUT    /api/users/password/change` — Change password
- `POST   /api/users/password/reset` — Reset password
- `GET    /api/users/roles/get` — List all roles
- `GET    /api/users/permissions/get` — List all permissions
- `PUT    /api/users/{id}/permissions/update` — Update user permissions
- `POST   /api/users/{id}/role/assign` — Assign role to user
- `POST   /api/users/{id}/permission/assign` — Assign permission to user
- `GET    /api/users/{id}/role/main` — Get user's main role
- `GET    /api/users/{id}/role/extra` — Get user's extra roles

**Sidebar & UI**
- `GET    /api/users/sidebar/items` — Get sidebar items for user

**Bulk & Fine-grained Operations**
- `POST   /api/users/roles/bulk-create` — Bulk create roles
- `PUT    /api/users/roles/bulk-update` — Bulk update roles
- `DELETE /api/users/roles/bulk-delete` — Bulk delete roles
- `POST   /api/users/permissions/bulk-assign-to-role` — Bulk assign permissions to role
- `DELETE /api/users/permissions/bulk-revoke-from-role` — Bulk revoke permissions from role
- `POST   /api/users/permissions/bulk-assign-to-user` — Bulk assign permissions to user
- `DELETE /api/users/permissions/bulk-revoke-from-user` — Bulk revoke permissions from user
- `POST   /api/users/roles/bulk-assign-to-user` — Bulk assign roles to user
- `DELETE /api/users/roles/bulk-revoke-from-user` — Bulk revoke roles from user
- `POST   /api/users/bulk-assign-to-role` — Bulk assign users to role
- `DELETE /api/users/bulk-revoke-from-role` — Bulk revoke users from role
- `POST   /api/users/permissions/bulk-assign-to-user-direct` — Bulk assign permissions to user (direct)
- `DELETE /api/users/permissions/bulk-revoke-from-user-direct` — Bulk revoke permissions from user (direct)
- `POST   /api/users/bulk-assign-to-permission` — Bulk assign users to permission
- `DELETE /api/users/bulk-revoke-from-permission` — Bulk revoke users from permission
- `POST   /api/users/role/assign-to-user` — Assign role to user (fine-grained)
- `DELETE /api/users/role/revoke-from-user` — Revoke role from user
- `POST   /api/users/permission/assign-to-user-direct` — Assign permission to user (direct)
- `DELETE /api/users/permission/revoke-from-user-direct` — Revoke permission from user (direct)
- `POST   /api/users/permission/assign-to-role` — Assign permission to role
- `DELETE /api/users/permission/revoke-from-role` — Revoke permission from role

---

## AcademicController

**Base CRUD**
- `GET    /api/academic` — List all academic records
- `GET    /api/academic/{id}` — Get academic record by ID
- `POST   /api/academic` — Create academic record
- `PUT    /api/academic/{id}` — Update academic record
- `DELETE /api/academic/{id}` — Delete academic record

**Examination Workflow**
- `POST   /api/academic/exams/start-workflow` — Start examination workflow
- `POST   /api/academic/exams/create-schedule` — Create exam schedule
- `POST   /api/academic/exams/submit-questions` — Submit question papers
- `POST   /api/academic/exams/prepare-logistics` — Prepare exam logistics
- `POST   /api/academic/exams/conduct` — Conduct examination
- `POST   /api/academic/exams/assign-marking` — Assign exam marking
- `POST   /api/academic/exams/record-marks` — Record exam marks
- `POST   /api/academic/exams/verify-marks` — Verify exam marks
- `POST   /api/academic/exams/moderate-marks` — Moderate exam marks
- `POST   /api/academic/exams/compile-results` — Compile exam results
- `POST   /api/academic/exams/approve-results` — Approve exam results

**Promotion Workflow**
- `POST   /api/academic/promotions/start-workflow` — Start promotion workflow
- `POST   /api/academic/promotions/identify-candidates` — Identify promotion candidates
- `POST   /api/academic/promotions/validate-eligibility` — Validate promotion eligibility
- `POST   /api/academic/promotions/execute` — Execute promotions
- `POST   /api/academic/promotions/generate-reports` — Generate promotion reports

**Assessment Workflow**
- `POST   /api/academic/assessments/start-workflow` — Start assessment workflow
- `POST   /api/academic/assessments/create-items` — Create assessment items
- `POST   /api/academic/assessments/administer` — Administer assessment
- `POST   /api/academic/assessments/mark-and-grade` — Mark and grade assessment
- `POST   /api/academic/assessments/analyze-results` — Analyze assessment results

**Reports, Library, Curriculum, Year Transition, Competency, Terms, Classes, Streams, Schedules, Units, Topics, Lesson Plans, Observations, Scheme of Work, Teachers, Subjects, Workflow Status, Custom**
- All endpoints follow the pattern: `METHOD /api/academic/{resource}/{action}` (see controller for full list)

---

## ActivitiesController

**Base CRUD**
- `GET    /api/activities` — List all activities
- `GET    /api/activities/{id}` — Get activity by ID
- `POST   /api/activities` — Create activity
- `PUT    /api/activities/{id}` — Update activity
- `DELETE /api/activities/{id}` — Delete activity

**Upcoming & Statistics**
- `GET    /api/activities/upcoming/list` — List upcoming activities
- `GET    /api/activities/statistics/get` — Get activity statistics

**Categories**
- `GET    /api/activities/categories/list` — List categories
- `GET    /api/activities/categories/get/{id}` — Get category by ID
- `POST   /api/activities/categories/create` — Create category
- `PUT    /api/activities/categories/update/{id}` — Update category
- `DELETE /api/activities/categories/delete/{id}` — Delete category
- `GET    /api/activities/categories/statistics` — Get category statistics
- `POST   /api/activities/categories/toggle-status` — Toggle category status

**Participants**
- `GET    /api/activities/participants/list` — List participants
- `GET    /api/activities/participants/get/{id}` — Get participant by ID
- `POST   /api/activities/participants/register` — Register participant
- `PUT    /api/activities/participants/update-status` — Update participant status
- `POST   /api/activities/participants/withdraw` — Withdraw participant
- `GET    /api/activities/participants/student-history` — Get student activity history
- `GET    /api/activities/participants/participation-stats` — Get participation stats
- `POST   /api/activities/participants/bulk-register` — Bulk register participants

**Resources**
- `GET    /api/activities/resources/list` — List resources
- `GET    /api/activities/resources/get/{id}` — Get resource by ID
- `POST   /api/activities/resources/add` — Add resource
- `PUT    /api/activities/resources/update/{id}` — Update resource
- `DELETE /api/activities/resources/delete/{id}` — Delete resource
- `GET    /api/activities/resources/by-activity` — Get resources by activity
- `GET    /api/activities/resources/check-availability` — Check resource availability
- `GET    /api/activities/resources/statistics` — Get resource statistics
- `PUT    /api/activities/resources/update-status` — Update resource status

**Schedules**
- `GET    /api/activities/schedules/list` — List schedules
- `GET    /api/activities/schedules/get/{id}` — Get schedule by ID
- `POST   /api/activities/schedules/create` — Create schedule
- `PUT    /api/activities/schedules/update/{id}` — Update schedule
- `DELETE /api/activities/schedules/delete/{id}` — Delete schedule
- `GET    /api/activities/schedules/by-activity` — Get schedules by activity
- `GET    /api/activities/schedules/weekly-timetable` — Get weekly timetable
- `GET    /api/activities/schedules/venue-availability` — Get venue availability
- `POST   /api/activities/schedules/bulk-create` — Bulk create schedules

**Registration Workflow**
- `POST   /api/activities/registration/initiate` — Initiate registration
- `POST   /api/activities/registration/review` — Review registration
- `POST   /api/activities/registration/approve` — Approve registration
- `POST   /api/activities/registration/reject` — Reject registration
- `POST   /api/activities/registration/confirm` — Confirm participation
- `POST   /api/activities/registration/complete` — Complete participation

**Planning Workflow**
- `POST   /api/activities/planning/propose` — Propose activity
- `POST   /api/activities/planning/approve-budget` — Approve budget
- `POST   /api/activities/planning/schedule` — Schedule activity
- `POST   /api/activities/planning/prepare-resources` — Prepare resources
- `POST   /api/activities/planning/execute` — Execute activity
- `POST   /api/activities/planning/review` — Review activity

**Competition Workflow**
- `POST   /api/activities/competition/register` — Register for competition
- `POST   /api/activities/competition/prepare-team` — Prepare team
- `POST   /api/activities/competition/record-participation` — Record participation
- `POST   /api/activities/competition/report-results` — Report results
- `POST   /api/activities/competition/recognize-achievements` — Recognize achievements

**Evaluation Workflow**
- `POST   /api/activities/evaluation/initiate` — Initiate evaluation
- `POST   /api/activities/evaluation/submit-assessment` — Submit assessment
- `POST   /api/activities/evaluation/verify-assessment` — Verify assessment
- `POST   /api/activities/evaluation/approve` — Approve evaluation
- `POST   /api/activities/evaluation/publish-results` — Publish evaluation results

---

## AdmissionController

- `POST   /api/admission/apply` — Submit admission application
- `POST   /api/admission/interview` — Record interview results
- `POST   /api/admission/placement-offer` — Generate placement offer
- `POST   /api/admission/fee-payment` — Record fee payment
- `POST   /api/admission/enrollment` — Complete enrollment

---

## AttendanceController

- `GET    /api/attendance` — List all attendance records
- `GET    /api/attendance/{id}` — Get attendance record by ID
- `POST   /api/attendance` — Create attendance record
- `PUT    /api/attendance/{id}` — Update attendance record
- `DELETE /api/attendance/{id}` — Delete attendance record
- `POST   /api/attendance/start-workflow` — Start attendance workflow
- `POST   /api/attendance/advance-workflow/{workflowInstanceId}/{action}` — Advance workflow
- `GET    /api/attendance/workflow-status/{workflowInstanceId}` — Get workflow status
- `GET    /api/attendance/list-workflows` — List attendance workflows
- `GET    /api/attendance/staff-percentage/{staffId}/{termId}/{yearId}` — Get staff attendance percentage
- `GET    /api/attendance/chronic-staff-absentees/{departmentId}/{termId}/{yearId}/{threshold?}` — Get chronic staff absentees

---

## Other Controllers

Other controllers (Finance, Inventory, Payroll, Reports, Schedules, etc.) follow the same RESTful conventions:
- `GET    /api/{module}` — List all records
- `GET    /api/{module}/{id}` — Get record by ID
- `POST   /api/{module}` — Create record
- `PUT    /api/{module}/{id}` — Update record
- `DELETE /api/{module}/{id}` — Delete record

Refer to the specific controller for resource-specific actions and fields.

---

## Response Format

All endpoints return a unified JSON response:

```json
{
  "status": "success|error",
  "message": "Human-readable message",
  "data": null|object|array,
  "code": 200,
  "timestamp": "2025-11-21T10:30:00Z",
  "request_id": "req_12345"
}
```

---

**For detailed request/response fields for each resource, see the corresponding controller and API module.**
