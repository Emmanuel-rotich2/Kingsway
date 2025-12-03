#!/bin/bash
# Improved test script for /api/activities endpoints
# Handles workflow IDs intelligently and avoids duplicate errors

BASE_URL="http://localhost/Kingsway/api/activities"
LOG_FILE="tests/activities_test_results.txt"
COOKIE_JAR="tests/activities_cookies.txt"

# Storage for workflow IDs
PLANNING_WORKFLOW_ID=""
COMPETITION_WORKFLOW_ID=""
EVALUATION_WORKFLOW_ID=""
REGISTRATION_WORKFLOW_ID=""

# Clean up previous logs
rm -f "$LOG_FILE" "$COOKIE_JAR"

echo "==== Testing /api/activities endpoints ====" | tee -a "$LOG_FILE"

# Helper: Print and log
log() {
  echo -e "$1" | tee -a "$LOG_FILE"
}

# Helper: Extract workflow_id from JSON response
extract_workflow_id() {
  local response="$1"
  echo "$response" | grep -o '"workflow_id":[0-9]*' | grep -o '[0-9]*' | head -1
}

# Helper: Curl wrapper with X-Test-Token header for authentication
TEST_TOKEN="devtest" # Must match AuthMiddleware test mode
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

# 1. /api/activities/index
call_api GET "$BASE_URL/index" > /dev/null

# 2. /api/activities (CRUD)
call_api GET "$BASE_URL" > /dev/null
call_api POST "$BASE_URL" '{"title":"Football Club","description":"Weekly football training","category_id":1,"start_date":"2025-11-29","end_date":"2026-03-01","target_audience":"students","started_by":1}' > /dev/null
call_api PUT "$BASE_URL" '{"id":1,"title":"Updated Activity","started_by":1}' > /dev/null
call_api DELETE "$BASE_URL?id=1" > /dev/null

# 3. /api/activities/upcoming-list
call_api GET "$BASE_URL/upcoming-list" > /dev/null

# 4. /api/activities/statistics-get
call_api GET "$BASE_URL/statistics-get" > /dev/null
call_api GET "$BASE_URL/statistics-get?id=1" > /dev/null

# 5. /api/activities/categories-list
call_api GET "$BASE_URL/categories-list" > /dev/null

# 6. /api/activities/categories-get
call_api GET "$BASE_URL/categories-get?id=1" > /dev/null

# 7. /api/activities/categories-create
call_api POST "$BASE_URL/categories-create" '{"name":"Sports","description":"All sports activities","started_by":1}' > /dev/null

# 8. /api/activities/categories-update
call_api PUT "$BASE_URL/categories-update" '{"id":1,"name":"Updated Category","started_by":1}' > /dev/null

# 9. /api/activities/categories-delete
call_api DELETE "$BASE_URL/categories-delete?id=1" > /dev/null

# 10. /api/activities/categories-statistics
call_api GET "$BASE_URL/categories-statistics" > /dev/null

# 11. /api/activities/categories-toggle-status
call_api POST "$BASE_URL/categories-toggle-status" '{"category_id":1,"started_by":1}' > /dev/null

# 12. /api/activities/participants-list
call_api GET "$BASE_URL/participants-list" > /dev/null

# 13. /api/activities/participants-get
call_api GET "$BASE_URL/participants-get?id=1" > /dev/null

# 14. /api/activities/participants-register
call_api POST "$BASE_URL/participants-register" '{"activity_id":1,"student_id":104,"role":"member","started_by":1}' > /dev/null

# 15. /api/activities/participants-update-status
call_api PUT "$BASE_URL/participants-update-status" '{"id":1,"status":"active","started_by":1}' > /dev/null

# 16. /api/activities/participants-withdraw
call_api POST "$BASE_URL/participants-withdraw" '{"participant_id":2,"reason":"Transferred school","started_by":1}' > /dev/null

# 17. /api/activities/participants-student-history
call_api GET "$BASE_URL/participants-student-history?student_id=1" > /dev/null

# 18. /api/activities/participants-participation-stats
call_api GET "$BASE_URL/participants-participation-stats?student_id=101&activity_id=1" > /dev/null

# 19. /api/activities/participants-bulk-register
call_api POST "$BASE_URL/participants-bulk-register" '{"activity_id":1,"student_ids":[105,106,107],"started_by":1}' > /dev/null

# 20. /api/activities/resources-list
call_api GET "$BASE_URL/resources-list" > /dev/null

# 21. /api/activities/resources-get
call_api GET "$BASE_URL/resources-get?id=1" > /dev/null

# 22. /api/activities/resources-add
call_api POST "$BASE_URL/resources-add" '{"activity_id":1,"resource_name":"Football","resource_type":"Ball","quantity":10,"description":"Football balls","started_by":1}' > /dev/null

# 23. /api/activities/resources-update
call_api PUT "$BASE_URL/resources-update" '{"id":1,"name":"Updated Resource","started_by":1}' > /dev/null

# 24. /api/activities/resources-delete
call_api DELETE "$BASE_URL/resources-delete?id=1" > /dev/null

# 25. /api/activities/resources-by-activity
call_api GET "$BASE_URL/resources-by-activity?activity_id=1" > /dev/null

# 26. /api/activities/resources-check-availability
call_api GET "$BASE_URL/resources-check-availability?resource_id=1&resource_type=Ball&start_date=2025-12-01&end_date=2025-12-02" > /dev/null

# 27. /api/activities/resources-statistics
call_api GET "$BASE_URL/resources-statistics" > /dev/null

# 28. /api/activities/resources-update-status
call_api PUT "$BASE_URL/resources-update-status" '{"id":1,"status":"unavailable","started_by":1}' > /dev/null

# 29. /api/activities/schedules-list
call_api GET "$BASE_URL/schedules-list" > /dev/null

# 30. /api/activities/schedules-get
call_api GET "$BASE_URL/schedules-get?id=1" > /dev/null

# 31. /api/activities/schedules-create
call_api POST "$BASE_URL/schedules-create" '{"activity_id":1,"day_of_week":"Monday","start_time":"15:00","end_time":"17:00","venue":"Field A","started_by":1}' > /dev/null

# 32. /api/activities/schedules-update
call_api PUT "$BASE_URL/schedules-update" '{"id":1,"venue":"Hall B","started_by":1}' > /dev/null

# 33. /api/activities/schedules-delete
call_api DELETE "$BASE_URL/schedules-delete?id=1" > /dev/null

# 34. /api/activities/schedules-by-activity
call_api GET "$BASE_URL/schedules-by-activity?activity_id=1" > /dev/null

# 35. /api/activities/schedules-weekly-timetable
call_api GET "$BASE_URL/schedules-weekly-timetable" > /dev/null

# 36. /api/activities/schedules-venue-availability
call_api GET "$BASE_URL/schedules-venue-availability?venue=Hall%20A&day_of_week=Monday&start_time=09:00&end_time=10:00" > /dev/null

# 37. /api/activities/schedules-bulk-create
call_api POST "$BASE_URL/schedules-bulk-create" '{"activity_id":1,"schedules":[{"venue":"Field A","day_of_week":"Monday","start_time":"15:00","end_time":"17:00"}],"started_by":1}' > /dev/null

# 38. /api/activities/registration-initiate
RESULT=$(call_api POST "$BASE_URL/registration-initiate" '{"activity_id":1,"student_id":105,"started_by":1}')
REGISTRATION_WORKFLOW_ID=$(extract_workflow_id "$RESULT")

# 39. /api/activities/registration-review
if [ -n "$REGISTRATION_WORKFLOW_ID" ]; then
  call_api POST "$BASE_URL/registration-review" "{\"workflow_id\":$REGISTRATION_WORKFLOW_ID,\"status\":\"reviewed\",\"started_by\":1}" > /dev/null
fi

# 40. /api/activities/registration-approve
if [ -n "$REGISTRATION_WORKFLOW_ID" ]; then
  call_api POST "$BASE_URL/registration-approve" "{\"workflow_id\":$REGISTRATION_WORKFLOW_ID,\"started_by\":1}" > /dev/null
fi

# 41. /api/activities/registration-reject
call_api POST "$BASE_URL/registration-reject" "{\"workflow_id\":999,\"reason\":\"Test\",\"started_by\":1}" > /dev/null

# 42. /api/activities/registration-confirm
if [ -n "$REGISTRATION_WORKFLOW_ID" ]; then
  call_api POST "$BASE_URL/registration-confirm" "{\"workflow_id\":$REGISTRATION_WORKFLOW_ID,\"started_by\":1}" > /dev/null
fi

# 43. /api/activities/registration-complete
if [ -n "$REGISTRATION_WORKFLOW_ID" ]; then
  call_api POST "$BASE_URL/registration-complete" "{\"workflow_id\":$REGISTRATION_WORKFLOW_ID,\"started_by\":1}" > /dev/null
fi

# 44. /api/activities/planning-propose
RESULT=$(call_api POST "$BASE_URL/planning-propose" '{"activity_id":1,"title":"Activity Planning Proposal","proposal":"Plan details","budget":5000,"started_by":1}')
PLANNING_WORKFLOW_ID=$(extract_workflow_id "$RESULT")

# 45. /api/activities/planning-approve-budget
if [ -n "$PLANNING_WORKFLOW_ID" ]; then
  call_api POST "$BASE_URL/planning-approve-budget" "{\"workflow_id\":$PLANNING_WORKFLOW_ID,\"approved\":true,\"started_by\":1}" > /dev/null
fi

# 46. /api/activities/planning-schedule
if [ -n "$PLANNING_WORKFLOW_ID" ]; then
  call_api POST "$BASE_URL/planning-schedule" "{\"workflow_id\":$PLANNING_WORKFLOW_ID,\"schedule\":\"2025-12-01\",\"started_by\":1}" > /dev/null
fi

# 47. /api/activities/planning-prepare-resources
if [ -n "$PLANNING_WORKFLOW_ID" ]; then
  call_api POST "$BASE_URL/planning-prepare-resources" "{\"workflow_id\":$PLANNING_WORKFLOW_ID,\"resources\":[\"Resource 1\"],\"started_by\":1}" > /dev/null
fi

# 48. /api/activities/planning-execute
if [ -n "$PLANNING_WORKFLOW_ID" ]; then
  call_api POST "$BASE_URL/planning-execute" "{\"workflow_id\":$PLANNING_WORKFLOW_ID,\"started_by\":1}" > /dev/null
fi

# 49. /api/activities/planning-review
if [ -n "$PLANNING_WORKFLOW_ID" ]; then
  call_api POST "$BASE_URL/planning-review" "{\"workflow_id\":$PLANNING_WORKFLOW_ID,\"review\":\"Looks good\",\"started_by\":1}" > /dev/null
fi

# 50. /api/activities/competition-register
RESULT=$(call_api POST "$BASE_URL/competition-register" '{"activity_id":1,"competition_name":"Inter-School Debate","venue":"Main Hall","competition_date":"2025-12-10","category":"Debate","participants":[105,106],"started_by":1}')
COMPETITION_WORKFLOW_ID=$(extract_workflow_id "$RESULT")

# 51. /api/activities/competition-prepare-team
if [ -n "$COMPETITION_WORKFLOW_ID" ]; then
  call_api POST "$BASE_URL/competition-prepare-team" "{\"workflow_id\":$COMPETITION_WORKFLOW_ID,\"team_members\":[105,106],\"started_by\":1}" > /dev/null
fi

# 52. /api/activities/competition-record-participation
if [ -n "$COMPETITION_WORKFLOW_ID" ]; then
  call_api POST "$BASE_URL/competition-record-participation" "{\"workflow_id\":$COMPETITION_WORKFLOW_ID,\"participant_id\":105,\"started_by\":1}" > /dev/null
fi

# 53. /api/activities/competition-report-results
if [ -n "$COMPETITION_WORKFLOW_ID" ]; then
  call_api POST "$BASE_URL/competition-report-results" "{\"workflow_id\":$COMPETITION_WORKFLOW_ID,\"results\":\"Win\",\"started_by\":1}" > /dev/null
fi

# 54. /api/activities/competition-recognize-achievements
if [ -n "$COMPETITION_WORKFLOW_ID" ]; then
  call_api POST "$BASE_URL/competition-recognize-achievements" "{\"workflow_id\":$COMPETITION_WORKFLOW_ID,\"achievements\":[\"Gold Medal\"],\"started_by\":1}" > /dev/null
fi

# 55. /api/activities/evaluation-initiate
RESULT=$(call_api POST "$BASE_URL/evaluation-initiate" '{"activity_id":1,"participant_id":105,"evaluation_period":"Term 3 2025","criteria":[{"criterion":"Teamwork","score":null},{"criterion":"Discipline","score":null}],"started_by":1}')
EVALUATION_WORKFLOW_ID=$(extract_workflow_id "$RESULT")

# 56. /api/activities/evaluation-submit-assessment
if [ -n "$EVALUATION_WORKFLOW_ID" ]; then
  call_api POST "$BASE_URL/evaluation-submit-assessment" "{\"workflow_id\":$EVALUATION_WORKFLOW_ID,\"assessment\":\"Assessment details\",\"started_by\":1}" > /dev/null
fi

# 57. /api/activities/evaluation-verify-assessment
if [ -n "$EVALUATION_WORKFLOW_ID" ]; then
  call_api POST "$BASE_URL/evaluation-verify-assessment" "{\"workflow_id\":$EVALUATION_WORKFLOW_ID,\"verified\":true,\"started_by\":1}" > /dev/null
fi

# 58. /api/activities/evaluation-approve
if [ -n "$EVALUATION_WORKFLOW_ID" ]; then
  call_api POST "$BASE_URL/evaluation-approve" "{\"workflow_id\":$EVALUATION_WORKFLOW_ID,\"started_by\":1}" > /dev/null
fi

# 59. /api/activities/evaluation-publish-results
if [ -n "$EVALUATION_WORKFLOW_ID" ]; then
  call_api POST "$BASE_URL/evaluation-publish-results" "{\"workflow_id\":$EVALUATION_WORKFLOW_ID,\"started_by\":1}" > /dev/null
fi

log "\n==== All /api/activities endpoint tests completed ===="
