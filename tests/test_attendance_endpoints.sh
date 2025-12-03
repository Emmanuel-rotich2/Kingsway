#!/bin/bash
# Test script for /api/attendance endpoints
# Tests student and staff attendance tracking

BASE_URL="http://localhost/Kingsway/api/attendance"
LOG_FILE="tests/attendance_test_results.txt"
COOKIE_JAR="tests/attendance_cookies.txt"

# Clean up previous logs
rm -f "$LOG_FILE" "$COOKIE_JAR"

echo "==== Testing /api/attendance endpoints ====" | tee -a "$LOG_FILE"

# Helper: Print and log
log() {
  echo -e "$1" | tee -a "$LOG_FILE"
}

# Helper: Curl wrapper with X-Test-Token header for authentication
TEST_TOKEN="devtest"
call_api() {
  local method="$1"
  local url="$2"
  local data="$3"
  local extra="$4"
  if [[ "$method" == "GET" || "$method" == "DELETE" ]]; then
    resp=$(curl -s -w "\nHTTP_STATUS:%{http_code}\n" -X "$method" "$url" -H "X-Test-Token: $TEST_TOKEN" -b "$COOKIE_JAR" $extra)
  else
    resp=$(curl -s -w "\nHTTP_STATUS:%{http_code}\n" -X "$method" "$url" -H "X-Test-Token: $TEST_TOKEN" -H "Content-Type: application/json" -d "$data" -b "$COOKIE_JAR" $extra)
  fi
  log "==== $method $url ===="
  log "$resp"
  echo "$resp"  # Also return for processing
}

# 1. GET /api/attendance/index
log "\n========== Testing GET /api/attendance/index =========="
call_api GET "$BASE_URL/index" > /dev/null

# ========== STUDENT ATTENDANCE ENDPOINTS ==========

# 2. GET /api/attendance/student-history
log "\n========== Testing GET /api/attendance/student-history =========="
call_api GET "$BASE_URL/student-history?studentId=101" > /dev/null

# 3. GET /api/attendance/student-summary
log "\n========== Testing GET /api/attendance/student-summary =========="
call_api GET "$BASE_URL/student-summary?studentId=101" > /dev/null

# 4. GET /api/attendance/student-percentage
log "\n========== Testing GET /api/attendance/student-percentage =========="
call_api GET "$BASE_URL/student-percentage?studentId=101&termId=1&yearId=2025" > /dev/null

# 5. GET /api/attendance/chronic-student-absentees
log "\n========== Testing GET /api/attendance/chronic-student-absentees =========="
call_api GET "$BASE_URL/chronic-student-absentees?classId=1&termId=1&yearId=2025&threshold=0.2" > /dev/null

# ========== CLASS ATTENDANCE ENDPOINTS ==========

# 6. GET /api/attendance/class-attendance
log "\n========== Testing GET /api/attendance/class-attendance =========="
call_api GET "$BASE_URL/class-attendance?classId=1&termId=1&yearId=2025" > /dev/null

# ========== STAFF ATTENDANCE ENDPOINTS ==========

# 7. GET /api/attendance/staff-history
log "\n========== Testing GET /api/attendance/staff-history =========="
call_api GET "$BASE_URL/staff-history?staffId=1" > /dev/null

# 8. GET /api/attendance/staff-summary
log "\n========== Testing GET /api/attendance/staff-summary =========="
call_api GET "$BASE_URL/staff-summary?staffId=1" > /dev/null

# 9. GET /api/attendance/staff-percentage
log "\n========== Testing GET /api/attendance/staff-percentage =========="
call_api GET "$BASE_URL/staff-percentage?staffId=1&termId=1&yearId=2025" > /dev/null

# 10. GET /api/attendance/chronic-staff-absentees
log "\n========== Testing GET /api/attendance/chronic-staff-absentees =========="
call_api GET "$BASE_URL/chronic-staff-absentees?departmentId=1&termId=1&yearId=2025&threshold=0.2" > /dev/null

# ========== DEPARTMENT ATTENDANCE ENDPOINTS ==========

# 11. GET /api/attendance/department-attendance
log "\n========== Testing GET /api/attendance/department-attendance =========="
call_api GET "$BASE_URL/department-attendance?departmentId=1&termId=1&yearId=2025" > /dev/null

# ========== CRUD ENDPOINTS ==========

# 12. GET /api/attendance
log "\n========== Testing GET /api/attendance (CRUD) =========="
call_api GET "$BASE_URL" > /dev/null

# 13. POST /api/attendance
log "\n========== Testing POST /api/attendance (Create Attendance Record) =========="
call_api POST "$BASE_URL" "{
  \"type\": \"student\",
  \"student_id\": 101,
  \"class_id\": 1,
  \"date\": \"2025-12-02\",
  \"status\": \"present\",
  \"term_id\": 1
}" > /dev/null

# 14. PUT /api/attendance
log "\n========== Testing PUT /api/attendance (Update Attendance Record) =========="
call_api PUT "$BASE_URL/1" "{
  \"type\": \"student\",
  \"status\": \"absent\"
}" > /dev/null

# 15. DELETE /api/attendance
log "\n========== Testing DELETE /api/attendance =========="
call_api DELETE "$BASE_URL/1?type=student" > /dev/null

log "\n========== Attendance API Tests Complete ==========="
