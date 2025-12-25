# Schedules & SchoolConfig API Testing Documentation

## Overview

This document provides comprehensive analysis of the **Schedules API** (`/api/schedules`) and **SchoolConfig API** (`/api/schoolconfig`) endpoints for the Kingsway Academy Management System.

---

## 1. Architecture & Data Flow

### 1.1 Complete Data Flow Diagram

```
┌─────────────────────────────────────────────────────────┐
│ DATABASE LAYER (MySQL - KingsWayAcademy)                │
├─────────────────────────────────────────────────────────┤
│ Core Tables:                                            │
│ • class_schedules - Timetable entries                   │
│ • exam_schedules - Exam schedule                        │
│ • activity_schedule - Co-curricular activities         │
│ • route_schedules - Transport schedules                │
│ • schedules - Generic schedule table                   │
│ • school_configuration - School settings               │
│ • schedule_changes - Audit trail                       │
└──────────────────────┬──────────────────────────────────┘
                       │
┌──────────────────────▼──────────────────────────────────┐
│ API MODULE LAYER                                        │
├──────────────────────────────────────────────────────────┤
│ Classes:                                                │
│ • SchedulesAPI - Coordinates all schedule operations   │
│ • SchedulesManager - Executes business logic queries   │
│ • SchedulesWorkflow - Manages workflow states          │
│ • TermHolidayManager - Term and holiday management     │
│ • TermHolidayWorkflow - Workflow for terms             │
└──────────────────────┬──────────────────────────────────┘
                       │
┌──────────────────────▼──────────────────────────────────┐
│ CONTROLLER LAYER                                        │
├──────────────────────────────────────────────────────────┤
│ Controllers:                                            │
│ • SchedulesController - Routes requests                │
│ • SchoolConfigController - Config management           │
│ • BaseController - Unified response formatting         │
└──────────────────────┬──────────────────────────────────┘
                       │
┌──────────────────────▼──────────────────────────────────┐
│ HTTP RESPONSE (Unified JSON Format)                     │
├──────────────────────────────────────────────────────────┤
│ {                                                       │
│   "status": "success|error",                            │
│   "message": "Human-readable message",                  │
│   "data": null|object|array,                           │
│   "code": 200,                                         │
│   "timestamp": "2024-11-14T10:30:00Z",                 │
│   "request_id": "req_12345"                            │
│ }                                                       │
└─────────────────────────────────────────────────────────┘
```

### 1.2 Request Flow Example: Creating a Class Schedule

```
Frontend Request:
POST /api/schedules/timetable-create
Content-Type: application/json
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
        ↓
Router receives request and calls:
SchedulesController::postTimetableCreate($id, $data, $segments)
        ↓
Controller validates and calls:
SchedulesAPI::createTimetableEntry($data)
        ↓
SchedulesAPI delegates to:
SchedulesManager::insertClassSchedule($data)
        ↓
SchedulesManager executes INSERT:
INSERT INTO class_schedules (class_id, day_of_week, start_time, 
end_time, subject_id, teacher_id, room_id, status) 
VALUES (1, 'Monday', '09:00:00', '10:00:00', 5, 10, 3, 'active')
        ↓
Database returns inserted ID (e.g., 42)
        ↓
API returns successResponse:
{
    "status": "success",
    "message": "Timetable entry created",
    "data": {
        "id": 42,
        "class_id": 1,
        "day_of_week": "Monday",
        "start_time": "09:00:00",
        ...
    },
    "code": 201,
    "timestamp": "2024-11-14T10:30:15Z",
    "request_id": "req_abc123"
}
```

---

## 2. Database Schema

### 2.1 class_schedules Table

**Purpose**: Stores the weekly timetable for classes

| Column | Type | Key | Description |
|--------|------|-----|-------------|
| id | INT UNSIGNED | PK | Schedule entry ID |
| class_id | INT UNSIGNED | FK | Reference to classes.id |
| day_of_week | ENUM | - | Day (Monday-Sunday) |
| start_time | TIME | - | Class start time (HH:MM:SS) |
| end_time | TIME | - | Class end time (HH:MM:SS) |
| subject_id | INT UNSIGNED | FK | Reference to curriculum_units.id |
| teacher_id | INT UNSIGNED | FK | Reference to staff.id |
| room_id | INT UNSIGNED | FK | Reference to rooms.id |
| status | ENUM | - | active or inactive |
| created_at | TIMESTAMP | - | Record creation timestamp |

**Indexes**:
- `class_id`
- `subject_id`
- `teacher_id`
- `room_id`
- `idx_schedule_datetime` (day_of_week, start_time, end_time)

**Sample Record**:
```sql
INSERT INTO class_schedules VALUES (1, 1, 'Monday', '09:00:00', '10:00:00', 5, 10, 3, 'active', NOW());
```

### 2.2 exam_schedules Table

**Purpose**: Stores exam schedules

| Column | Type | Key | Description |
|--------|------|-----|-------------|
| id | INT UNSIGNED | PK | Exam schedule ID |
| class_id | INT UNSIGNED | FK | Reference to classes.id |
| subject_id | INT UNSIGNED | FK | Reference to curriculum_units.id |
| exam_date | DATE | - | Date of exam (YYYY-MM-DD) |
| start_time | TIME | - | Exam start time |
| end_time | TIME | - | Exam end time |
| room_id | INT UNSIGNED | FK | Reference to rooms.id |
| invigilator_id | INT UNSIGNED | FK | Reference to staff.id |
| status | ENUM | - | scheduled, completed, or cancelled |
| created_at | TIMESTAMP | - | Record creation timestamp |

**Indexes**:
- `class_id`
- `subject_id`
- `room_id`
- `invigilator_id`
- `idx_exam_schedule_datetime` (exam_date, start_time, end_time)

### 2.3 activity_schedule Table

**Purpose**: Stores co-curricular activity schedules

| Column | Type | Key | Description |
|--------|------|-----|-------------|
| id | INT UNSIGNED | PK | Activity schedule ID |
| activity_id | INT UNSIGNED | FK | Reference to activities.id |
| day_of_week | VARCHAR | - | Day name (Monday-Sunday) |
| schedule_date | DATE | - | Specific date (YYYY-MM-DD) |
| start_time | TIME | - | Activity start time |
| end_time | TIME | - | Activity end time |
| venue | VARCHAR | - | Location/venue name |
| created_at | TIMESTAMP | - | Record creation timestamp |
| updated_at | TIMESTAMP | - | Last update timestamp |

### 2.4 route_schedules Table

**Purpose**: Stores transport route schedules

| Column | Type | Key | Description |
|--------|------|-----|-------------|
| id | INT UNSIGNED | PK | Route schedule ID |
| route_id | INT UNSIGNED | FK | Reference to transport_routes.id |
| day_of_week | ENUM | - | Day (Monday-Sunday) |
| direction | ENUM | - | pickup or dropoff |
| departure_time | TIME | - | Time of departure |
| status | ENUM | - | active or inactive |
| created_at | TIMESTAMP | - | Record creation timestamp |

### 2.5 schedules Table

**Purpose**: Generic schedule table for general events

| Column | Type | Key | Description |
|--------|------|-----|-------------|
| id | INT UNSIGNED | PK | Schedule ID |
| title | VARCHAR | - | Schedule title |
| description | TEXT | - | Detailed description |
| start_time | DATETIME | - | Start (YYYY-MM-DD HH:MM:SS) |
| end_time | DATETIME | - | End (YYYY-MM-DD HH:MM:SS) |
| created_at | TIMESTAMP | - | Record creation timestamp |

### 2.6 school_configuration Table

**Purpose**: Stores school-wide settings and configuration

| Column | Type | Key | Description |
|--------|------|-----|-------------|
| id | INT UNSIGNED | PK | Configuration ID |
| school_name | VARCHAR | - | Official school name |
| school_code | VARCHAR | - | Unique school code |
| logo_url | VARCHAR | - | URL to school logo |
| favicon_url | VARCHAR | - | URL to favicon |
| motto | VARCHAR | - | School motto |
| vision | TEXT | - | School vision statement |
| mission | TEXT | - | School mission statement |
| core_values | TEXT | - | School core values |
| *Other fields* | | | Address, phone, email, etc. |

---

## 3. API Endpoints - Detailed Reference

### 3.1 Base CRUD Operations

#### GET /api/schedules/index
- **Purpose**: API health check
- **Method**: GET
- **Response**: `{ "message": "Schedules API is running" }`

#### GET /api/schedules
- **Purpose**: List all schedules
- **Method**: GET
- **Query Parameters**: (optional filters)
- **Response**: Array of schedule objects

#### POST /api/schedules
- **Purpose**: Create new schedule
- **Method**: POST
- **Payload**:
```json
{
    "title": "Class Session",
    "description": "Description",
    "start_time": "2024-01-15 09:00:00",
    "end_time": "2024-01-15 10:00:00"
}
```
- **Response**: 201 Created with created schedule object

#### PUT /api/schedules/{id}
- **Purpose**: Update existing schedule
- **Method**: PUT
- **URL Parameter**: `id` (required)
- **Payload**: Fields to update
- **Response**: 200 OK with updated schedule

#### DELETE /api/schedules/{id}
- **Purpose**: Delete schedule
- **Method**: DELETE
- **URL Parameter**: `id` (required)
- **Response**: 204 No Content

### 3.2 Timetable Endpoints

#### GET /api/schedules/timetable-get
- **Purpose**: Get all timetable entries
- **Query Parameters**: 
  - `id` (optional): Get specific timetable entry
- **Response**: Array of class_schedules records

#### POST /api/schedules/timetable-create
- **Purpose**: Create new timetable entry
- **Payload**:
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
- **Database Operation**: 
  ```sql
  INSERT INTO class_schedules (class_id, day_of_week, start_time, 
  end_time, subject_id, teacher_id, room_id, status)
  VALUES (...)
  ```

### 3.3 Exam Schedule Endpoints

#### GET /api/schedules/exam-get
- **Purpose**: Get exam schedules
- **Query Parameters**: `id` (optional)
- **Response**: Array of exam_schedules records

#### POST /api/schedules/exam-create
- **Purpose**: Create exam schedule
- **Payload**:
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

### 3.4 Activity Schedule Endpoints

#### GET /api/schedules/activity-get
- **Purpose**: Get activity schedules
- **Query Parameters**: `id` (optional)

#### POST /api/schedules/activity-create
- **Purpose**: Create activity schedule
- **Payload**:
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

### 3.5 Room Management Endpoints

#### GET /api/schedules/rooms-get
- **Purpose**: Get available rooms
- **Query Parameters**: `id` (optional)

#### POST /api/schedules/rooms-create
- **Purpose**: Create room entry
- **Payload**:
```json
{
    "name": "Class 6A",
    "building": "Main Block",
    "floor": 1,
    "capacity": 40,
    "features": "Whiteboard, Projector",
    "status": "active"
}
```

### 3.6 Advanced Schedule Endpoints

#### GET /api/schedules/teacher-schedule
- **Purpose**: Get teacher's timetable
- **Query Parameters**: 
  - `teacher_id` (required)
  - `term_id` (optional)
- **Database Query**:
  ```sql
  SELECT cs.*, c.name as class_name, s.name as subject_name, r.name as room_name
  FROM class_schedules cs
  JOIN classes c ON cs.class_id = c.id
  JOIN subjects s ON cs.subject_id = s.id
  LEFT JOIN rooms r ON cs.room_id = r.id
  WHERE cs.teacher_id = ? AND cs.term_id = ?
  ORDER BY cs.day_of_week, cs.start_time
  ```

#### GET /api/schedules/student-schedules
- **Purpose**: Get student's complete schedule
- **Query Parameters**:
  - `student_id` (required)
  - `term_id` (optional)
- **Returns**: Classes, exams, events, holidays for student

#### GET /api/schedules/master-schedule
- **Purpose**: Get comprehensive schedule for admin
- **Query Parameters**: Various filters
- **Returns**:
```json
{
    "classes": [...],
    "activities": [...],
    "transport": [...],
    "duties": [...]
}
```

#### GET /api/schedules/analytics
- **Purpose**: Get schedule analytics
- **Returns**:
```json
{
    "total_classes": 125,
    "total_activities": 45,
    "total_conflicts": 3
}
```

### 3.7 Workflow Endpoints

#### POST /api/schedules/start-scheduling-workflow
- **Purpose**: Initiate schedule generation workflow
- **Payload**:
```json
{
    "term_id": 1,
    "academic_year": 2024,
    "workflow_type": "full_schedule_generation"
}
```
- **Workflow Stages**: 
  1. define_term_dates
  2. generate_master_schedule
  3. validate_compliance
  4. publish_schedule

#### POST /api/schedules/advance-scheduling-workflow
- **Purpose**: Move workflow to next stage
- **Payload**:
```json
{
    "workflow_instance_id": 1,
    "action": "approve_master_schedule",
    "remarks": "Approved by admin"
}
```

#### GET /api/schedules/scheduling-workflow-status
- **Purpose**: Check workflow progress
- **Query Parameters**: `workflow_instance_id` (required)

---

## 4. SchoolConfig API

### 4.1 Configuration Endpoints

#### GET /api/schoolconfig
- **Purpose**: Retrieve school configuration
- **Response**: Current school configuration object

#### POST /api/schoolconfig
- **Purpose**: Create or update school configuration
- **Payload**:
```json
{
    "school_name": "Kingsway Academy",
    "school_code": "KWA001",
    "logo_url": "https://example.com/logo.png",
    "favicon_url": "https://example.com/favicon.ico",
    "motto": "Excellence in Education",
    "vision": "To develop confident and responsible global citizens",
    "mission": "Provide quality education",
    "core_values": "Integrity, Innovation, Inclusivity"
}
```

#### PUT /api/schoolconfig/{id}
- **Purpose**: Update specific configuration
- **URL Parameter**: `id` (configuration ID)
- **Payload**: Fields to update

#### GET /api/schoolconfig/health
- **Purpose**: System health check
- **Response**: Health status of all components

#### GET /api/schoolconfig/logs
- **Purpose**: Retrieve system logs
- **Query Parameters**: Filters (date range, type, etc.)

#### POST /api/schoolconfig/logs-clear
- **Purpose**: Clear old logs
- **Response**: Confirmation message

#### POST /api/schoolconfig/logs-archive
- **Purpose**: Archive logs to backup storage
- **Response**: Archive details

---

## 5. Request/Response Patterns

### 5.1 Standard Success Response

```json
{
    "status": "success",
    "message": "Operation completed successfully",
    "data": {
        "id": 42,
        "class_id": 1,
        "day_of_week": "Monday",
        "start_time": "09:00:00",
        "end_time": "10:00:00",
        "subject_id": 5,
        "teacher_id": 10,
        "room_id": 3,
        "status": "active"
    },
    "code": 200,
    "timestamp": "2024-11-14T10:30:15Z",
    "request_id": "req_abc123def456"
}
```

### 5.2 Standard Error Response

```json
{
    "status": "error",
    "message": "Invalid schedule time range",
    "data": null,
    "code": 400,
    "timestamp": "2024-11-14T10:31:00Z",
    "request_id": "req_xyz789"
}
```

### 5.3 Paginated List Response

```json
{
    "status": "success",
    "message": "Schedules retrieved",
    "data": {
        "items": [...],
        "pagination": {
            "page": 1,
            "per_page": 20,
            "total": 125,
            "total_pages": 7
        }
    },
    "code": 200,
    "timestamp": "2024-11-14T10:32:00Z",
    "request_id": "req_list001"
}
```

---

## 6. Data Validation Rules

### 6.1 Class Schedule Validation

- `class_id`: Must exist in classes table
- `day_of_week`: Must be one of (Monday-Sunday)
- `start_time`: Must be valid time format (HH:MM:SS)
- `end_time`: Must be after start_time
- `subject_id`: Must exist in curriculum_units table
- `teacher_id`: Must exist in staff table
- `room_id`: Must exist in rooms table (if provided)
- No conflicting schedules for teacher/room on same day/time

### 6.2 Exam Schedule Validation

- `exam_date`: Must be a future date
- `start_time` < `end_time`
- Duration typically 1-3 hours
- No conflicting exam schedules for invigilator

### 6.3 Activity Schedule Validation

- `activity_id`: Must exist in activities table
- `schedule_date`: Must be valid date
- Venue must be available at scheduled time

### 6.4 School Config Validation

- `school_name`: 1-255 characters, required
- `school_code`: Unique, alphanumeric, 3-20 characters
- URLs must be valid format

---

## 7. Error Codes & Messages

| Code | Status | Meaning |
|------|--------|---------|
| 200 | OK | Request successful, data returned |
| 201 | Created | New resource created successfully |
| 204 | No Content | Deletion successful, no data |
| 400 | Bad Request | Invalid input, validation error |
| 401 | Unauthorized | Missing/invalid authentication |
| 403 | Forbidden | Insufficient permissions |
| 404 | Not Found | Resource not found |
| 409 | Conflict | Schedule/data conflict (e.g., teacher unavailable) |
| 422 | Unprocessable | Valid format but semantic error |
| 500 | Server Error | Database or server error |

---

## 8. Testing the API

### 8.1 Using the Provided Test Script

```bash
# Make script executable
chmod +x test_schedules_schoolconfig_api.sh

# Run all tests
./test_schedules_schoolconfig_api.sh

# Review results
cat api_test_results_*.json
cat api_test_*.log
```

### 8.2 Manual Testing with curl

**Get all schedules**:
```bash
curl -X GET http://localhost:8000/api/schedules \
  -H "Content-Type: application/json"
```

**Create timetable entry**:
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

**Get teacher schedule**:
```bash
curl -X GET "http://localhost:8000/api/schedules/teacher-schedule?teacher_id=10&term_id=1" \
  -H "Content-Type: application/json"
```

---

## 9. Integration with Frontend

### 9.1 JavaScript Fetch Example

```javascript
// Create timetable entry
async function createTimetableEntry(data) {
    const response = await fetch('/api/schedules/timetable-create', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify(data)
    });
    
    if (!response.ok) {
        throw new Error('Failed to create timetable entry');
    }
    
    const result = await response.json();
    if (result.status === 'success') {
        console.log('Timetable created:', result.data);
        return result.data;
    } else {
        throw new Error(result.message);
    }
}

// Get teacher schedule
async function getTeacherSchedule(teacherId, termId) {
    const response = await fetch(
        `/api/schedules/teacher-schedule?teacher_id=${teacherId}&term_id=${termId}`,
        { method: 'GET' }
    );
    
    const result = await response.json();
    if (result.status === 'success') {
        return result.data;
    } else {
        throw new Error(result.message);
    }
}
```

---

## 10. Performance Considerations

### 10.1 Database Optimization

- Use indexes on frequently queried columns (class_id, teacher_id, subject_id)
- Indexed composite key for (day_of_week, start_time, end_time)
- Consider materialized views for complex queries (master schedule, analytics)

### 10.2 Caching Strategy

- Cache school configuration (changes infrequently)
- Cache master schedules per term (regenerated on demand)
- Cache teacher/student schedules (24-hour TTL)

### 10.3 Pagination

For large datasets, implement pagination:
```
GET /api/schedules?page=1&per_page=50
```

---

## 11. Troubleshooting

### Common Issues

1. **Schedule Conflict Error (409)**
   - Cause: Teacher/room already scheduled for same time
   - Solution: Check availability before creating, use analytics endpoint

2. **Validation Error (400)**
   - Cause: Missing/invalid fields
   - Solution: Verify payload matches expected schema

3. **Not Found Error (404)**
   - Cause: Referenced resource doesn't exist
   - Solution: Verify IDs exist before creating relationships

4. **Database Error (500)**
   - Cause: Database connection or query error
   - Solution: Check database logs, verify connection string

---

## 12. API Specification Summary

**Total Endpoints**: 47
- Base CRUD: 5
- Timetable: 2
- Exam Schedule: 2
- Activity Schedule: 2
- Rooms: 2
- Reports: 2
- Route Schedule: 2
- Advanced: 15
- Workflow: 8
- SchoolConfig: 9

**Response Format**: Unified JSON
**Authentication**: (Check implementation for details)
**Rate Limiting**: (Verify with deployment docs)
**Version**: v1

---

## 13. Files

- **Test Script**: `test_schedules_schoolconfig_api.sh`
- **Controller**: `api/controllers/SchedulesController.php`, `api/controllers/SchoolConfigController.php`
- **API Module**: `api/modules/schedules/SchedulesAPI.php`
- **Manager**: `api/modules/schedules/SchedulesManager.php`
- **Database**: `database/KingsWayAcademyDatabase.sql`

---

**Last Updated**: December 20, 2024
**Version**: 1.0
