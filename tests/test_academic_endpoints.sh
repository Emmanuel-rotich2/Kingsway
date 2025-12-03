
#!/bin/bash
# Comprehensive test script for all /api/academic endpoints
# Usage: ./test_academic_endpoints.sh > academic_test_results.txt

API_URL="http://localhost/Kingsway/api/academic"
TEST_TOKEN="devtest" # Must match AuthMiddleware test mode

COMMON_HEADERS=("-H" "X-Test-Token: $TEST_TOKEN" "-H" "Content-Type: application/json")

function test_endpoint() {
	local method=$1
	local endpoint=$2
	local data=$3
	echo -e "\n==== $method $endpoint ===="
	if [ "$method" == "GET" ]; then
		curl -s -w "\nHTTP_STATUS:%{http_code}\n" -X GET "$endpoint" -H "X-Test-Token: $TEST_TOKEN"
	elif [ "$method" == "POST" ]; then
		curl -s -w "\nHTTP_STATUS:%{http_code}\n" -X POST "$endpoint" -H "X-Test-Token: $TEST_TOKEN" -H "Content-Type: application/json" -d "$data"
	elif [ "$method" == "PUT" ]; then
		curl -s -w "\nHTTP_STATUS:%{http_code}\n" -X PUT "$endpoint" -H "X-Test-Token: $TEST_TOKEN" -H "Content-Type: application/json" -d "$data"
	elif [ "$method" == "DELETE" ]; then
		curl -s -w "\nHTTP_STATUS:%{http_code}\n" -X DELETE "$endpoint" -H "X-Test-Token: $TEST_TOKEN"
	fi
}



test_endpoint GET "$API_URL/index"
test_endpoint GET "$API_URL"
test_endpoint POST "$API_URL" '{"name":"Test Academic Record","description":"Test description"}'
test_endpoint PUT "$API_URL" '{"id":1,"name":"Updated Academic Record","description":"Updated description"}'
test_endpoint DELETE "$API_URL" '{"id":1}'

# --- Exam workflow sequence ---
echo -e "\n==== POST $API_URL/exams-start-workflow (sequential) ===="
exam_start_resp=$(curl -s -X POST "$API_URL/exams-start-workflow" -H "X-Test-Token: $TEST_TOKEN" -H "Content-Type: application/json" -d '{"title":"End Term Exam","term_id":1,"start_date":"2025-12-01","end_date":"2025-12-10"}')
echo "$exam_start_resp"
exam_id=$(echo "$exam_start_resp" | grep -o '"exam_id"[ ]*:[ ]*[0-9]*' | grep -o '[0-9]*' | head -1)
echo "exam_id: $exam_id"

if [ -n "$exam_id" ]; then
	echo -e "\n==== POST $API_URL/exams-create-schedule (sequential) ===="
	curl -s -w "\nHTTP_STATUS:%{http_code}\n" -X POST "$API_URL/exams-create-schedule" -H "X-Test-Token: $TEST_TOKEN" -H "Content-Type: application/json" -d '{"exam_id":'$exam_id',"schedule":[{"subject":"Math","date":"2025-12-02"}]}'

	echo -e "\n==== POST $API_URL/exams-submit-questions (sequential) ===="
	curl -s -w "\nHTTP_STATUS:%{http_code}\n" -X POST "$API_URL/exams-submit-questions" -H "X-Test-Token: $TEST_TOKEN" -H "Content-Type: application/json" -d '{"exam_id":'$exam_id',"questions":[{"subject":"Math","file_url":"/files/math_questions.pdf"}]}'

	echo -e "\n==== POST $API_URL/exams-prepare-logistics (sequential) ===="
	curl -s -w "\nHTTP_STATUS:%{http_code}\n" -X POST "$API_URL/exams-prepare-logistics" -H "X-Test-Token: $TEST_TOKEN" -H "Content-Type: application/json" -d '{"exam_id":'$exam_id',"rooms":[101,102],"invigilators":[5,6]}'

	echo -e "\n==== POST $API_URL/exams-conduct (sequential) ===="
	curl -s -w "\nHTTP_STATUS:%{http_code}\n" -X POST "$API_URL/exams-conduct" -H "X-Test-Token: $TEST_TOKEN" -H "Content-Type: application/json" -d '{"exam_id":'$exam_id',"date":"2025-12-02"}'

	echo -e "\n==== POST $API_URL/exams-assign-marking (sequential) ===="
	curl -s -w "\nHTTP_STATUS:%{http_code}\n" -X POST "$API_URL/exams-assign-marking" -H "X-Test-Token: $TEST_TOKEN" -H "Content-Type: application/json" -d '{"exam_id":'$exam_id',"markers":[7,8]}'

	echo -e "\n==== POST $API_URL/exams-record-marks (sequential) ===="
	curl -s -w "\nHTTP_STATUS:%{http_code}\n" -X POST "$API_URL/exams-record-marks" -H "X-Test-Token: $TEST_TOKEN" -H "Content-Type: application/json" -d '{"exam_id":'$exam_id',"marks":[{"student_id":1001,"score":85}]}'

	echo -e "\n==== POST $API_URL/exams-verify-marks (sequential) ===="
	curl -s -w "\nHTTP_STATUS:%{http_code}\n" -X POST "$API_URL/exams-verify-marks" -H "X-Test-Token: $TEST_TOKEN" -H "Content-Type: application/json" -d '{"exam_id":'$exam_id'}'

	echo -e "\n==== POST $API_URL/exams-moderate-marks (sequential) ===="
	curl -s -w "\nHTTP_STATUS:%{http_code}\n" -X POST "$API_URL/exams-moderate-marks" -H "X-Test-Token: $TEST_TOKEN" -H "Content-Type: application/json" -d '{"exam_id":'$exam_id'}'

	echo -e "\n==== POST $API_URL/exams-compile-results (sequential) ===="
	curl -s -w "\nHTTP_STATUS:%{http_code}\n" -X POST "$API_URL/exams-compile-results" -H "X-Test-Token: $TEST_TOKEN" -H "Content-Type: application/json" -d '{"exam_id":'$exam_id'}'

	echo -e "\n==== POST $API_URL/exams-approve-results (sequential) ===="
	curl -s -w "\nHTTP_STATUS:%{http_code}\n" -X POST "$API_URL/exams-approve-results" -H "X-Test-Token: $TEST_TOKEN" -H "Content-Type: application/json" -d '{"exam_id":'$exam_id'}'
else
	echo "[ERROR] Could not extract exam_id from exams-start-workflow response."
fi

# --- Promotions workflow sequence ---
echo -e "\n==== POST $API_URL/promotions-start-workflow (sequential) ===="
# Send the full criteria object as the payload (not wrapped in a key)
promotion_criteria='{ "from_academic_year":2025, "to_academic_year":2026, "from_grade_id":6, "to_grade_id":7, "batch_name":"Grade 6 to 7 Promotion 2025", "min_overall_score":50, "min_attendance_pct":80, "auto_promote_lower_primary":true, "notes":"Test batch" }'
promotion_start_resp=$(curl -s -X POST "$API_URL/promotions-start-workflow" -H "X-Test-Token: $TEST_TOKEN" -H "Content-Type: application/json" -d "$promotion_criteria")
echo "$promotion_start_resp"
promotion_id=$(echo "$promotion_start_resp" | grep -o '"promotion_id"[ ]*:[ ]*[0-9]*' | grep -o '[0-9]*' | head -1)
echo "promotion_id: $promotion_id"

if [ -n "$promotion_id" ]; then
	echo -e "\n==== POST $API_URL/promotions-identify-candidates (sequential) ===="
	curl -s -w "\nHTTP_STATUS:%{http_code}\n" -X POST "$API_URL/promotions-identify-candidates" -H "X-Test-Token: $TEST_TOKEN" -H "Content-Type: application/json" -d '{"promotion_id":'$promotion_id'}'

	echo -e "\n==== POST $API_URL/promotions-validate-eligibility (sequential) ===="
	curl -s -w "\nHTTP_STATUS:%{http_code}\n" -X POST "$API_URL/promotions-validate-eligibility" -H "X-Test-Token: $TEST_TOKEN" -H "Content-Type: application/json" -d '{"promotion_id":'$promotion_id'}'

	echo -e "\n==== POST $API_URL/promotions-execute (sequential) ===="
	curl -s -w "\nHTTP_STATUS:%{http_code}\n" -X POST "$API_URL/promotions-execute" -H "X-Test-Token: $TEST_TOKEN" -H "Content-Type: application/json" -d '{"promotion_id":'$promotion_id'}'

	echo -e "\n==== POST $API_URL/promotions-generate-reports (sequential) ===="
	curl -s -w "\nHTTP_STATUS:%{http_code}\n" -X POST "$API_URL/promotions-generate-reports" -H "X-Test-Token: $TEST_TOKEN" -H "Content-Type: application/json" -d '{"promotion_id":'$promotion_id'}'
else
	echo "[ERROR] Could not extract promotion_id from promotions-start-workflow response."
fi

# --- Assessment workflow sequence ---
echo -e "\n==== POST $API_URL/assessments-start-workflow (sequential) ===="
# Send the full plan object as the payload (not wrapped in a key)
assessment_plan='{ "title":"Midterm Assessment", "subject_id":1, "class_id":1, "classification_code":"CA", "term_id":1, "total_marks":100 }'
assessment_start_resp=$(curl -s -X POST "$API_URL/assessments-start-workflow" -H "X-Test-Token: $TEST_TOKEN" -H "Content-Type: application/json" -d "$assessment_plan")
echo "$assessment_start_resp"
assessment_id=$(echo "$assessment_start_resp" | grep -o '"assessment_id"[ ]*:[ ]*[0-9]*' | grep -o '[0-9]*' | head -1)
echo "assessment_id: $assessment_id"

if [ -n "$assessment_id" ]; then
	echo -e "\n==== POST $API_URL/assessments-create-items (sequential) ===="
	curl -s -w "\nHTTP_STATUS:%{http_code}\n" -X POST "$API_URL/assessments-create-items" -H "X-Test-Token: $TEST_TOKEN" -H "Content-Type: application/json" -d '{"assessment_id":'$assessment_id',"items":[{"type":"quiz","description":"Quiz 1"}]}'

	echo -e "\n==== POST $API_URL/assessments-administer (sequential) ===="
	curl -s -w "\nHTTP_STATUS:%{http_code}\n" -X POST "$API_URL/assessments-administer" -H "X-Test-Token: $TEST_TOKEN" -H "Content-Type: application/json" -d '{"assessment_id":'$assessment_id'}'

	echo -e "\n==== POST $API_URL/assessments-mark-and-grade (sequential) ===="
	curl -s -w "\nHTTP_STATUS:%{http_code}\n" -X POST "$API_URL/assessments-mark-and-grade" -H "X-Test-Token: $TEST_TOKEN" -H "Content-Type: application/json" -d '{"assessment_id":'$assessment_id'}'

	echo -e "\n==== POST $API_URL/assessments-analyze-results (sequential) ===="
	curl -s -w "\nHTTP_STATUS:%{http_code}\n" -X POST "$API_URL/assessments-analyze-results" -H "X-Test-Token: $TEST_TOKEN" -H "Content-Type: application/json" -d '{"assessment_id":'$assessment_id'}'
else
	echo "[ERROR] Could not extract assessment_id from assessments-start-workflow response."
fi

# --- Reports workflow sequence ---
echo -e "\n==== POST $API_URL/reports-start-workflow (sequential) ===="
report_start_resp=$(curl -s -X POST "$API_URL/reports-start-workflow" -H "X-Test-Token: $TEST_TOKEN" -H "Content-Type: application/json" -d '{"title":"Term Report","term_id":1,"scope":[{"class_id":1,"term_id":1}]}')
echo "$report_start_resp"
report_id=$(echo "$report_start_resp" | grep -o '"report_id"[ ]*:[ ]*[0-9]*' | grep -o '[0-9]*' | head -1)
echo "report_id: $report_id"

if [ -n "$report_id" ]; then
	echo -e "\n==== POST $API_URL/reports-compile-data (sequential) ===="
	curl -s -w "\nHTTP_STATUS:%{http_code}\n" -X POST "$API_URL/reports-compile-data" -H "X-Test-Token: $TEST_TOKEN" -H "Content-Type: application/json" -d '{"report_id":'$report_id'}'

	echo -e "\n==== POST $API_URL/reports-generate-student-reports (sequential) ===="
	curl -s -w "\nHTTP_STATUS:%{http_code}\n" -X POST "$API_URL/reports-generate-student-reports" -H "X-Test-Token: $TEST_TOKEN" -H "Content-Type: application/json" -d '{"report_id":'$report_id'}'

	echo -e "\n==== POST $API_URL/reports-review-and-approve (sequential) ===="
	curl -s -w "\nHTTP_STATUS:%{http_code}\n" -X POST "$API_URL/reports-review-and-approve" -H "X-Test-Token: $TEST_TOKEN" -H "Content-Type: application/json" -d '{"report_id":'$report_id'}'

	echo -e "\n==== POST $API_URL/reports-distribute (sequential) ===="
	curl -s -w "\nHTTP_STATUS:%{http_code}\n" -X POST "$API_URL/reports-distribute" -H "X-Test-Token: $TEST_TOKEN" -H "Content-Type: application/json" -d '{"report_id":'$report_id'}'
else
	echo "[ERROR] Could not extract report_id from reports-start-workflow response."
fi
test_endpoint POST "$API_URL/library-start-workflow" '{"title":"Library Acquisition","term_id":1,"request":[{"book_title":"Sample Book","author":"Author Name","quantity":2}]}'
test_endpoint POST "$API_URL/library-review-request" '{"request_id":1}'
test_endpoint POST "$API_URL/library-catalog-resources" '{"catalog_id":1}'
test_endpoint POST "$API_URL/library-distribute-and-track" '{"catalog_id":1}'
test_endpoint POST "$API_URL/curriculum-start-workflow" '{"title":"New Curriculum","year":2025}'
test_endpoint POST "$API_URL/curriculum-map-outcomes" '{"curriculum_id":1}'
test_endpoint POST "$API_URL/curriculum-create-scheme" '{"curriculum_id":1}'
test_endpoint POST "$API_URL/curriculum-review-and-approve" '{"curriculum_id":1}'
test_endpoint POST "$API_URL/year-transition-start-workflow" '{"year":2025}'
test_endpoint POST "$API_URL/year-transition-archive-data" '{"year":2025}'
test_endpoint POST "$API_URL/year-transition-execute-promotions" '{"year":2025}'
test_endpoint POST "$API_URL/year-transition-setup-new-year" '{"year":2026}'
test_endpoint POST "$API_URL/year-transition-migrate-competency-baselines" '{"year":2025}'
test_endpoint POST "$API_URL/year-transition-validate-readiness" '{"year":2025}'
test_endpoint POST "$API_URL/competency-record-evidence" '{"student_id":1001,"evidence":"Project work"}'
test_endpoint POST "$API_URL/competency-record-core-value-evidence" '{"student_id":1001,"core_value":"Integrity"}'
test_endpoint GET "$API_URL/competency-dashboard"
test_endpoint POST "$API_URL/terms-create" '{"name":"Term 1","start_date":"2025-01-10","end_date":"2025-04-10"}'
test_endpoint GET "$API_URL/terms-list"
test_endpoint POST "$API_URL/classes-create" '{"name":"Class 1A","grade":1}'
test_endpoint GET "$API_URL/classes-list"
test_endpoint GET "$API_URL/classes-get"
test_endpoint PUT "$API_URL/classes-update" '{"id":1,"name":"Class 1A Updated"}'
test_endpoint DELETE "$API_URL/classes-delete" '{"id":1}'
test_endpoint POST "$API_URL/classes-assign-teacher" '{"class_id":1,"teacher_id":10}'
test_endpoint POST "$API_URL/classes-auto-create-streams" '{"class_id":1}'
test_endpoint POST "$API_URL/streams-create" '{"name":"Stream A","class_id":1}'
test_endpoint GET "$API_URL/streams-list"
test_endpoint POST "$API_URL/schedules-create" '{"class_id":1,"term_id":1,"schedule":[{"day":"Monday","subject":"Math"}]}'
test_endpoint GET "$API_URL/schedules-list"
test_endpoint GET "$API_URL/schedules-get"
test_endpoint PUT "$API_URL/schedules-update" '{"id":1,"schedule":[{"day":"Tuesday","subject":"English"}]}'
test_endpoint DELETE "$API_URL/schedules-delete" '{"id":1}'
test_endpoint POST "$API_URL/schedules-assign-room" '{"schedule_id":1,"room_id":201}'
test_endpoint POST "$API_URL/curriculum-units-create" '{"name":"Unit 1","curriculum_id":1}'
test_endpoint GET "$API_URL/curriculum-units-list"
test_endpoint GET "$API_URL/curriculum-units-get"
test_endpoint PUT "$API_URL/curriculum-units-update" '{"id":1,"name":"Unit 1 Updated"}'
test_endpoint DELETE "$API_URL/curriculum-units-delete" '{"id":1}'
test_endpoint POST "$API_URL/topics-create" '{"name":"Topic 1","unit_id":1}'
test_endpoint GET "$API_URL/topics-list"
test_endpoint GET "$API_URL/topics-get"
test_endpoint PUT "$API_URL/topics-update" '{"id":1,"name":"Topic 1 Updated"}'
test_endpoint DELETE "$API_URL/topics-delete" '{"id":1}'
test_endpoint POST "$API_URL/lesson-plans-create" '{"title":"Lesson 1","class_id":1}'
test_endpoint GET "$API_URL/lesson-plans-list"
test_endpoint GET "$API_URL/lesson-plans-get"
test_endpoint PUT "$API_URL/lesson-plans-update" '{"id":1,"title":"Lesson 1 Updated"}'
test_endpoint DELETE "$API_URL/lesson-plans-delete" '{"id":1}'
test_endpoint POST "$API_URL/lesson-plans-approve" '{"lesson_plan_id":1}'
test_endpoint POST "$API_URL/lesson-observations-create" '{"lesson_plan_id":1,"observer_id":20}'
test_endpoint GET "$API_URL/lesson-observations-list"
test_endpoint POST "$API_URL/scheme-of-work-create" '{"class_id":1,"term_id":1}'
test_endpoint GET "$API_URL/scheme-of-work-get"
test_endpoint GET "$API_URL/teachers-classes"
test_endpoint GET "$API_URL/teachers-subjects"
test_endpoint GET "$API_URL/teachers-schedule"
test_endpoint GET "$API_URL/subjects-teachers"
test_endpoint GET "$API_URL/workflow-status"
test_endpoint GET "$API_URL/custom"
test_endpoint POST "$API_URL/custom" '{"custom_field":"value"}'
