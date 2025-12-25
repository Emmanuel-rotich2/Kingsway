# Quick Reference: Schedules & SchoolConfig API

## Quick Start

```bash
# 1. Run the test suite
./test_schedules_schoolconfig_api.sh

# 2. Check logs
tail -f api_test_*.log

# 3. View results
cat api_test_results_*.json | jq .
```

---

## Endpoint Map

### Schedules Base
```
GET    /api/schedules/index                 → Health check
GET    /api/schedules                        → List all
POST   /api/schedules                        → Create
GET    /api/schedules/{id}                   → Get one
PUT    /api/schedules/{id}                   → Update
DELETE /api/schedules/{id}                   → Delete
```

### Timetable (Classes)
```
GET    /api/schedules/timetable-get          → List
POST   /api/schedules/timetable-create       → Create
```

### Exam Schedules
```
GET    /api/schedules/exam-get               → List
POST   /api/schedules/exam-create            → Create
```

### Activities
```
GET    /api/schedules/activity-get           → List
POST   /api/schedules/activity-create        → Create
```

### Events
```
GET    /api/schedules/events-get             → List
POST   /api/schedules/events-create          → Create
```

### Rooms
```
GET    /api/schedules/rooms-get              → List
POST   /api/schedules/rooms-create           → Create
```

### Reports
```
GET    /api/schedules/reports-get            → List
POST   /api/schedules/reports-create         → Create
```

### Routes (Transport)
```
GET    /api/schedules/route-get              → List
POST   /api/schedules/route-create           → Create
```

### Role-Specific Views
```
GET    /api/schedules/teacher-schedule           → Teacher's classes
GET    /api/schedules/subject-teaching-load      → Subject load
GET    /api/schedules/all-activity-schedules     → All activities
GET    /api/schedules/driver-schedule            → Driver routes
GET    /api/schedules/staff-duty-schedule        → Staff duties
GET    /api/schedules/student-schedules          → Student's schedule
GET    /api/schedules/staff-schedules            → Staff schedule
GET    /api/schedules/master-schedule            → Admin overview
GET    /api/schedules/analytics                  → Stats
```

### Workflow
```
POST   /api/schedules/start-scheduling-workflow           → Initiate
POST   /api/schedules/advance-scheduling-workflow         → Next stage
GET    /api/schedules/scheduling-workflow-status          → Status
GET    /api/schedules/list-scheduling-workflows           → List
POST   /api/schedules/define-term-dates                   → Define terms
GET    /api/schedules/review-term-dates                   → Review
GET    /api/schedules/check-resource-availability         → Check rooms
GET    /api/schedules/find-optimal-schedule               → Find slot
POST   /api/schedules/detect-schedule-conflicts           → Detect
GET    /api/schedules/generate-master-schedule            → Generate
GET    /api/schedules/validate-schedule-compliance        → Validate
```

### School Config
```
GET    /api/schoolconfig/index                → Health check
GET    /api/schoolconfig                      → Get config
POST   /api/schoolconfig                      → Create/Update
PUT    /api/schoolconfig/{id}                 → Update
DELETE /api/schoolconfig/{id}                 → Delete (not supported)
GET    /api/schoolconfig/logs                 → View logs
POST   /api/schoolconfig/logs-clear           → Clear logs
POST   /api/schoolconfig/logs-archive         → Archive logs
GET    /api/schoolconfig/health               → Health check
```

---

## Common Payloads

### Create Timetable Entry
```json
{
    "class_id": 1,
    "day_of_week": "Monday",
    "start_time": "09:00:00",
    "end_time": "10:00:00",
    "subject_id": 5,
    "teacher_id": 10,
    "room_id": 3,
    "status": "active"
}
```

### Create Exam Schedule
```json
{
    "class_id": 1,
    "subject_id": 5,
    "exam_date": "2024-02-10",
    "start_time": "09:00:00",
    "end_time": "11:00:00",
    "room_id": 3,
    "invigilator_id": 15,
    "status": "scheduled"
}
```

### Create Activity Schedule
```json
{
    "activity_id": 2,
    "schedule_date": "2024-01-17",
    "day_of_week": "Wednesday",
    "start_time": "15:30:00",
    "end_time": "17:00:00",
    "venue": "School Hall"
}
```

### Create Route Schedule
```json
{
    "route_id": 1,
    "day_of_week": "Monday",
    "direction": "pickup",
    "departure_time": "07:00:00",
    "status": "active"
}
```

### Update School Config
```json
{
    "school_name": "Kingsway Academy",
    "school_code": "KWA001",
    "logo_url": "https://example.com/logo.png",
    "motto": "Excellence in Education",
    "vision": "Develop global citizens",
    "mission": "Quality education",
    "core_values": "Integrity, Innovation"
}
```

---

## Query Parameters

### Filtering
```
?id=1                           → Get specific item
?class_id=1                     → Filter by class
?teacher_id=10                  → Filter by teacher
?subject_id=5                   → Filter by subject
?term_id=1                      → Filter by term
?status=active                  → Filter by status
?date=2024-01-15                → Filter by date
```

### Teacher Schedule
```
GET /api/schedules/teacher-schedule?teacher_id=10&term_id=1
```

### Student Schedule
```
GET /api/schedules/student-schedules?student_id=5&term_id=1
```

### Driver Schedule
```
GET /api/schedules/driver-schedule?driver_id=20&term_id=1
```

### Check Availability
```
GET /api/schedules/check-resource-availability?resource_type=room&resource_id=3&date=2024-01-15
```

---

## Response Format

### Success (200/201)
```json
{
    "status": "success",
    "message": "Operation completed",
    "data": { /* resource or array */ },
    "code": 200,
    "timestamp": "2024-11-14T10:30:00Z",
    "request_id": "req_abc123"
}
```

### Error (400/401/404/500)
```json
{
    "status": "error",
    "message": "Error description",
    "data": null,
    "code": 400,
    "timestamp": "2024-11-14T10:31:00Z",
    "request_id": "req_xyz789"
}
```

---

## HTTP Status Codes

| Code | Meaning | When Used |
|------|---------|-----------|
| 200 | OK | Read, update successful |
| 201 | Created | Create successful |
| 204 | No Content | Delete successful |
| 400 | Bad Request | Validation error |
| 401 | Unauthorized | Auth required |
| 403 | Forbidden | No permission |
| 404 | Not Found | Resource missing |
| 409 | Conflict | Schedule conflict |
| 422 | Unprocessable | Semantic error |
| 500 | Server Error | Database/server error |

---

## Curl Examples

### List All Schedules
```bash
curl -X GET http://localhost:8000/api/schedules
```

### Create Timetable
```bash
curl -X POST http://localhost:8000/api/schedules/timetable-create \
  -H "Content-Type: application/json" \
  -d '{
    "class_id": 1,
    "day_of_week": "Monday",
    "start_time": "09:00:00",
    "end_time": "10:00:00",
    "subject_id": 5,
    "teacher_id": 10,
    "room_id": 3,
    "status": "active"
  }'
```

### Get Teacher Schedule
```bash
curl -X GET "http://localhost:8000/api/schedules/teacher-schedule?teacher_id=10&term_id=1"
```

### Get Master Schedule
```bash
curl -X GET "http://localhost:8000/api/schedules/master-schedule"
```

### Update School Config
```bash
curl -X PUT http://localhost:8000/api/schoolconfig/1 \
  -H "Content-Type: application/json" \
  -d '{
    "school_name": "Kingsway Academy",
    "motto": "Excellence"
  }'
```

### Check Health
```bash
curl -X GET http://localhost:8000/api/schoolconfig/health
```

---

## Database Tables

| Table | Purpose | Key Fields |
|-------|---------|-----------|
| class_schedules | Class timetable | class_id, day_of_week, time, teacher_id |
| exam_schedules | Exam schedule | class_id, subject_id, exam_date, invigilator_id |
| activity_schedule | Co-curricular | activity_id, schedule_date, venue |
| route_schedules | Transport | route_id, day_of_week, direction, departure_time |
| schedules | Generic | title, start_time, end_time |
| school_configuration | Settings | school_name, school_code, logo_url, motto |

---

## Data Validation Rules

### Time Fields
- Format: HH:MM:SS or YYYY-MM-DD HH:MM:SS
- end_time must be > start_time
- No negative durations

### Enum Fields
- day_of_week: Monday, Tuesday, ..., Sunday
- direction: pickup, dropoff
- status: active, inactive, scheduled, completed, cancelled

### Relationships
- class_id, teacher_id, subject_id, room_id must exist
- Foreign keys are validated before insertion
- Cascading deletes may apply

---

## Common Errors & Solutions

| Error | Cause | Solution |
|-------|-------|----------|
| 400 Bad Request | Invalid JSON/fields | Check payload format |
| 404 Not Found | ID doesn't exist | Verify resource exists |
| 409 Conflict | Schedule overlap | Check availability first |
| 422 Unprocessable | Logic error | Check business rules |
| 500 Server Error | DB issue | Check logs, restart |

---

## File Locations

- **Test Script**: `/home/prof_angera/Projects/php_pages/Kingsway/test_schedules_schoolconfig_api.sh`
- **Controller**: `/home/prof_angera/Projects/php_pages/Kingsway/api/controllers/SchedulesController.php`
- **API Module**: `/home/prof_angera/Projects/php_pages/Kingsway/api/modules/schedules/SchedulesAPI.php`
- **Manager**: `/home/prof_angera/Projects/php_pages/Kingsway/api/modules/schedules/SchedulesManager.php`
- **Database**: `/home/prof_angera/Projects/php_pages/Kingsway/database/KingsWayAcademyDatabase.sql`
- **Full Guide**: `/home/prof_angera/Projects/php_pages/Kingsway/SCHEDULES_API_TESTING_GUIDE.md`

---

**Version**: 1.0 | **Last Updated**: December 20, 2024
