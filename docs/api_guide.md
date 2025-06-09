# Kingsway School Management System API Documentation

## Overview

The Kingsway School Management System provides a modern, fully API-driven backend for all school operations. All frontend pages interact with the backend exclusively via these endpoints, using a centralized `api.js` file. All notifications and popups are handled by a unified Bootstrap modal. Dummy data is used on the frontend if the backend returns no data, ensuring a seamless user experience.

## Authentication

All endpoints (except login/register/reset) require JWT authentication.

**Header:**

```
Authorization: Bearer <jwt_token>
```

**Obtain Token:**

```
POST /api/auth.php?action=login
{
    "username": "user@example.com",
    "password": "password"
}
```

**Response:**

```
{
  "status": "success",
  "token": "<jwt_token>",
  "user": { ... }
}
```

## Common Response Format

All API responses follow this format:

```
{
    "status": "success|error",
    "message": "Optional message",
  "data": { ... }
}
```

## Error Handling

- 200: Success
- 201: Created
- 400: Bad Request
- 401: Unauthorized
- 403: Forbidden
- 404: Not Found
- 500: Server Error

## Real-Time Data & Notifications

- All frontend data is fetched via `api.js` and auto-reloads every 30 seconds.
- All notifications use a unified Bootstrap modal with color-coded backgrounds (success, error, warning, info).
- If the backend returns no data, the frontend uses dummy data to keep the UI populated.

## API Endpoints

### 1. User Management (`/api/users.php`)

#### List Users
- **GET** `/api/users.php?action=list`
- **Query:** `page`, `limit`, `search`, `sort`, `order`
- **Response:**
  - `users`: array of user objects (without passwords)
  - `pagination`: { page, limit, total, total_pages }

#### Get User
- **GET** `/api/users.php?action=view&id={id}`
- **Response:**
  - `user`: user object (without password)

#### Create User
- **POST** `/api/users.php?action=add`
- **Body:** `{ email, password, role, first_name, last_name, ... }`
- **Response:**
  - `id`, `username`, `role`
- **Notes:**
  - Sends welcome email on creation
  - Username is auto-generated if not provided

#### Update User
- **POST** `/api/users.php?action=update&id={id}`
- **Body:** `{ username?, email?, role_id?, status?, password? }`
- **Response:** Success message

#### Delete User
- **POST** `/api/users.php?action=delete&id={id}`
- **Response:** Success message (user is set to inactive)

#### Change Password
- **POST** `/api/users.php?action=change-password&id={id}`
- **Body:** `{ current_password, new_password }`
- **Response:** Success message

#### Reset Password
- **POST** `/api/users.php?action=reset-password`
- **Body:** `{ email }`
- **Response:** Success message (reset email sent)

#### Get Roles & Permissions
- **GET** `/api/users.php?action=roles`
- **GET** `/api/users.php?action=permissions`

#### Assign Role/Permission
- **POST** `/api/users.php?action=assign-role&id={id}` `{ role_id }`
- **POST** `/api/users.php?action=assign-permission&id={id}` `{ permission_id }`

### 2. Student Management (`/api/students.php`)

#### List Students
- **GET** `/api/students.php?action=list`
- **Query:** `page`, `limit`, `search`, `class_id`, ...
- **Response:**
  - `students`: array of student objects
  - `pagination`: { page, limit, total, total_pages }

#### Get Student
- **GET** `/api/students.php?action=view&id={id}`
- **Response:**
  - `student`: student object

#### Create Student
- **POST** `/api/students.php?action=add`
- **Body:** `{ first_name, last_name, date_of_birth, gender, stream_id, parent_details, ... }`
- **Response:**
  - `id`, `admission_no`, ...
- **Notes:**
  - Parent details can be nested or separate
  - Admission number is auto-generated if not provided

#### Update Student
- **POST** `/api/students.php?action=update&id={id}`
- **Body:** `{ ...fields }`
- **Response:** Success message

#### Delete Student
- **POST** `/api/students.php?action=delete&id={id}`
- **Response:** Success message

#### Get QR Code
- **GET** `/api/students.php?action=qr&id={id}`
- **Response:** QR code image or data

#### Get Attendance/Performance/Fees
- **GET** `/api/students.php?action=attendance&id={id}`
- **GET** `/api/students.php?action=performance&id={id}`
- **GET** `/api/students.php?action=fees&id={id}`

#### Promote/Transfer Student
- **POST** `/api/students.php?action=promote&id={id}` `{ ... }`
- **POST** `/api/students.php?action=transfer&id={id}` `{ ... }`

### 3. Staff Management (`/api/staff.php`)

#### List Staff
- **GET** `/api/staff.php?action=list`
- **Query:** `page`, `limit`, `search`, `role`, ...
- **Response:**
  - `staff`: array of staff objects
  - `pagination`: { page, limit, total, total_pages }

#### Get Staff
- **GET** `/api/staff.php?action=view&id={id}`
- **Response:**
  - `staff`: staff object

#### Create Staff
- **POST** `/api/staff.php?action=add`
- **Body:** `{ name, staff_no, department, role, ... }`
- **Response:**
  - `id`, ...

#### Update Staff
- **POST** `/api/staff.php?action=update&id={id}`
- **Body:** `{ ...fields }`
- **Response:** Success message

#### Delete Staff
- **POST** `/api/staff.php?action=delete&id={id}`
- **Response:** Success message

#### Get Schedule/Attendance/Leaves
- **GET** `/api/staff.php?action=schedule&id={id}`
- **GET** `/api/staff.php?action=attendance&id={id}`
- **GET** `/api/staff.php?action=leaves&id={id}`

#### Submit Leave Request
- **POST** `/api/staff.php?action=leave-request&id={id}` `{ ... }`

### 4. Academic Management (`/api/academic.php`)

#### List Learning Areas
- **GET** `/api/academic.php?action=list`
- **Query:** `page`, `limit`, `level_id`, ...
- **Response:**
  - `learning_areas`: array
  - `pagination`: { ... }

#### Get Learning Area
- **GET** `/api/academic.php?action=view&id={id}`
- **Response:**
  - `learning_area`: object

#### Create/Update/Delete Learning Area
- **POST** `/api/academic.php?action=add`
- **POST** `/api/academic.php?action=update&id={id}`
- **POST** `/api/academic.php?action=delete&id={id}`

#### Custom Academic Endpoints
- **GET** `/api/academic.php?action=lesson-plans`
- **GET** `/api/academic.php?action=curriculum-units`
- **GET** `/api/academic.php?action=academic-terms`
- **GET** `/api/academic.php?action=schemes-of-work`
- **GET** `/api/academic.php?action=lesson-observations`

### 5. Finance Management (`/api/finance.php`)

#### List Transactions
- **GET** `/api/finance.php?action=list`
- **Query:** `page`, `limit`, `type`, ...
- **Response:**
  - `transactions`: array
  - `pagination`: { ... }

#### Get Transaction
- **GET** `/api/finance.php?action=view&id={id}`
- **Response:**
  - `transaction`: object

#### Create Payment
- **POST** `/api/finance.php?action=add`
- **Body:** `{ student_id, amount, payment_method, reference, ... }`
- **Response:**
  - `id`, ...

#### Update/Delete Payment
- **POST** `/api/finance.php?action=update&id={id}`
- **POST** `/api/finance.php?action=delete&id={id}`

#### Custom Finance Endpoints
- **GET** `/api/finance.php?id={id}&action=balance`
- **GET** `/api/finance.php?id={id}&action=statement`
- **GET** `/api/finance.php?id={id}&action=receipt`
- **GET** `/api/finance.php?id={id}&action=payslip`
- **GET** `/api/finance.php?id={id}&action=report`
- **POST** `/api/finance.php?id={id}&action=allocate`
- **POST** `/api/finance.php?id={id}&action=refund`
- **POST** `/api/finance.php?id={id}&action=approve`

### 6. Inventory Management (`/api/inventory.php`)

#### List Inventory Items
- **GET** `/api/inventory.php?action=list`
- **Query:** `page`, `limit`, `search`, ...
- **Response:**
  - `items`: array
  - `pagination`: { ... }

#### Get Inventory Item
- **GET** `/api/inventory.php?action=view&id={id}`
- **Response:**
  - `item`: object

#### Create/Update/Delete Inventory Item
- **POST** `/api/inventory.php?action=add`
- **POST** `/api/inventory.php?action=update&id={id}`
- **POST** `/api/inventory.php?action=delete&id={id}`

#### Record Inventory Transaction
- **POST** `/api/inventory.php?action=transaction`
- **Body:** `{ item_id, type, quantity, ... }`

#### Get Low Stock/Valuation
- **GET** `/api/inventory.php?action=low-stock`
- **GET** `/api/inventory.php?action=valuation`

### 7. Transport Management (`/api/transport.php`)

#### List Transport Routes
- **GET** `/api/transport.php?action=list`
- **Query:** `page`, `limit`, ...
- **Response:**
  - `routes`: array
  - `pagination`: { ... }

#### Get Route/Vehicle/Driver
- **GET** `/api/transport.php?action=view&id={id}`
- **GET** `/api/transport.php?action=routes`
- **GET** `/api/transport.php?action=vehicles`
- **GET** `/api/transport.php?action=drivers`

#### Create/Update/Delete Route
- **POST** `/api/transport.php?action=add`
- **POST** `/api/transport.php?action=update&id={id}`
- **POST** `/api/transport.php?action=delete&id={id}`

#### Custom Transport Endpoints
- **GET** `/api/transport.php?id={id}&action=schedule`
- **GET** `/api/transport.php?id={id}&action=maintenance`
- **GET** `/api/transport.php?id={id}&action=attendance`
- **GET** `/api/transport.php?id={id}&action=students`
- **POST** `/api/transport.php?id={id}&action=schedule`
- **POST** `/api/transport.php?id={id}&action=maintenance`
- **POST** `/api/transport.php?id={id}&action=attendance`

### 8. Activities Management (`/api/activities.php`)

#### List Activities
- **GET** `/api/activities.php?action=list`
- **Query:** `page`, `limit`, `category`, `status`, ...
- **Response:**
  - `activities`: array
  - `pagination`: { ... }

#### Get Activity
- **GET** `/api/activities.php?action=view&id={id}`
- **Response:**
  - `activity`: object

#### Create/Update/Delete Activity
- **POST** `/api/activities.php?action=add`
- **POST** `/api/activities.php?action=update&id={id}`
- **POST** `/api/activities.php?action=delete&id={id}`

#### Register Participant
- **POST** `/api/activities.php?action=register-participant`
- **Body:** `{ activity_id, student_id, ... }`

#### Update Participant Status
- **POST** `/api/activities.php?action=update-participant-status&id={id}`
- **Body:** `{ status }`

#### Get Upcoming/Student Activities
- **GET** `/api/activities.php?action=upcoming`
- **GET** `/api/activities.php?action=student-activities&student_id={id}`

### 9. Attendance Management (`/api/attendance.php`)

#### List Attendance
- **GET** `/api/attendance.php?action=list`
- **Query:** `page`, `limit`, `date`, `class_id`, `type`, ...
- **Response:**
  - `attendance`: array
  - `pagination`: { ... }

#### Get Attendance Record
- **GET** `/api/attendance.php?action=view&id={id}`
- **Response:**
  - `attendance`: object

#### Mark/Bulk Mark Attendance
- **POST** `/api/attendance.php?action=mark`
- **POST** `/api/attendance.php?action=bulk-mark`
- **Body:** `{ ... }`

### 10. Schedules Management (`/api/schedules.php`)

#### List Schedules
- **GET** `/api/schedules.php?action=list`
- **Query:** `page`, `limit`, ...
- **Response:**
  - `schedules`: array
  - `pagination`: { ... }

#### Get Schedule
- **GET** `/api/schedules.php?action=view&id={id}`
- **Response:**
  - `schedule`: object

#### Create/Update/Delete Schedule
- **POST** `/api/schedules.php?action=add`
- **POST** `/api/schedules.php?action=update&id={id}`
- **POST** `/api/schedules.php?action=delete&id={id}`

#### Custom Schedules Endpoints
- **GET** `/api/schedules.php?action=timetable`
- **GET** `/api/schedules.php?action=exam-schedule`
- **GET** `/api/schedules.php?action=events`
- **GET** `/api/schedules.php?action=activity-schedule`
- **GET** `/api/schedules.php?action=rooms`
- **GET** `/api/schedules.php?action=scheduled-reports`
- **GET** `/api/schedules.php?action=route-schedule`

### 11. Communications (`/api/communications.php`)

#### List Communications
- **GET** `/api/communications.php?action=list`
- **Query:** `page`, `limit`, `type`, ...
- **Response:**
  - `communications`: array
  - `pagination`: { ... }

#### Get Communication
- **GET** `/api/communications.php?action=view&id={id}`
- **Response:**
  - `communication`: object

#### Send/Send Bulk Communication
- **POST** `/api/communications.php?action=send`
- **POST** `/api/communications.php?action=send-bulk`
- **Body:** `{ ... }` (see below)

#### Get/Create Templates
- **GET** `/api/communications.php?action=templates&type={type}`
- **POST** `/api/communications.php?action=create-template`
- **Body:** `{ type, name, email_subject, email_body, ... }`

#### Get/Create SMS/Email Templates
- **GET** `/api/communications.php?action=sms-templates`
- **GET** `/api/communications.php?action=email-templates`
- **POST** `/api/communications.php?action=create-sms-template`
- **POST** `/api/communications.php?action=create-email-template`

#### Get/Update SMS Config
- **GET** `/api/communications.php?action=sms-config`
- **POST** `/api/communications.php?action=update-sms-config`

#### Get Groups
- **GET** `/api/communications.php?action=groups`
- **POST** `/api/communications.php?action=create-group`

### 12. Reports (`/api/reports.php`)

#### List Reports
- **GET** `/api/reports.php?action=list`
- **Query:** `page`, `limit`, ...
- **Response:**
  - `reports`: array
  - `pagination`: { ... }

#### Get/Generate/Download/Export Report
- **GET** `/api/reports.php?action=view&id={id}`
- **POST** `/api/reports.php?action=generate`
- **GET** `/api/reports.php?action=download&id={id}&format=pdf|excel`
- **GET** `/api/reports.php?action=export&type={type}&format=excel`

### 13. General Notes

- All endpoints require JWT authentication unless otherwise noted.
- All responses follow the standard format.
- All list endpoints support pagination.
- All errors are returned with appropriate HTTP status codes and messages.
- File uploads (where supported) must use `multipart/form-data`.
- Dummy data is used on the frontend if the backend returns no data.
- All notifications use a unified Bootstrap modal.
- Data auto-reloads every 30 seconds on the frontend. 