#!/bin/bash

# Communications API Test Script
# Tests all communication module endpoints
# Output: test_communications_results.txt

BASE_URL="http://localhost/Kingsway/api"
OUTPUT_FILE="test_communications_results.txt"
TOKEN="devtest"

# Clear output file
> "$OUTPUT_FILE"

# Function to test an endpoint
test_endpoint() {
    local method=$1
    local endpoint=$2
    local payload=$3
    local description=$4
    
    echo "========================================" >> "$OUTPUT_FILE"
    echo "TEST: $method $endpoint" >> "$OUTPUT_FILE"
    echo "Description: $description" >> "$OUTPUT_FILE"
    echo "URL: $BASE_URL$endpoint" >> "$OUTPUT_FILE"
    echo "========================================" >> "$OUTPUT_FILE"
    
    if [ -n "$payload" ] && [ "$payload" != "null" ]; then
        echo "PAYLOAD:" >> "$OUTPUT_FILE"
        echo "$payload" >> "$OUTPUT_FILE"
        echo "" >> "$OUTPUT_FILE"
        echo "RESPONSE:" >> "$OUTPUT_FILE"
        curl -s -X "$method" \
            -H "Content-Type: application/json" \
            -H "X-Test-Token: $TOKEN" \
            -d "$payload" \
            "$BASE_URL$endpoint" >> "$OUTPUT_FILE" 2>&1
    else
        echo "RESPONSE:" >> "$OUTPUT_FILE"
        curl -s -X "$method" \
            -H "Content-Type: application/json" \
            -H "X-Test-Token: $TOKEN" \
            "$BASE_URL$endpoint" >> "$OUTPUT_FILE" 2>&1
    fi
    
    echo "" >> "$OUTPUT_FILE"
    echo "" >> "$OUTPUT_FILE"
}

# ============================================
# 1. INDEX
# ============================================
test_endpoint "GET" "/communications/index" "null" "Get communications index"

# ============================================
# 2. SMS CALLBACKS (Realistic payloads from Africa's Talking)
# ============================================
test_endpoint "POST" "/communications/sms-delivery-report" '{"message_id":"MSG123456","status":"Success","delivered_at":"2025-12-03 21:30:00","error_message":null}' "SMS Delivery Report"
test_endpoint "POST" "/communications/sms-opt-out-callback" '{"phone":"+254712345678","channel":"sms"}' "SMS Opt-Out Callback"
test_endpoint "POST" "/communications/sms-subscription-callback" '{"phone":"+254712345678","message":"Hello from student","channel":"sms","received_at":"2025-12-03 21:35:00"}' "SMS Subscription Callback"

# ============================================
# 3. CONTACT MANAGEMENT
# ============================================
test_endpoint "GET" "/communications/contact" "null" "Get all contacts"
test_endpoint "POST" "/communications/contact" '{"name":"John Doe","email":"john@example.com","phone":"+254712345678","contact_type":"parent","department":"Parent","role":"Guardian","notes":"Contact for student ABC123"}' "Create contact"
test_endpoint "POST" "/communications/contact" '{"name":"Jane Smith","email":"jane@school.com","phone":"+254787654321","contact_type":"staff","department":"Administration","role":"Admin Officer"}' "Create staff contact"
test_endpoint "GET" "/communications/contact/1" "null" "Get specific contact"
test_endpoint "PUT" "/communications/contact/1" '{"name":"John Doe Updated","email":"john.updated@example.com","phone":"+254712345679","department":"Parents"}' "Update contact"
test_endpoint "DELETE" "/communications/contact/1" "null" "Delete contact"

# ============================================
# 4. INBOUND MESSAGES (External messages)
# ============================================
test_endpoint "GET" "/communications/inbound" "null" "Get inbound messages"
test_endpoint "POST" "/communications/inbound" '{"source_type":"sms","source_address":"+254712345678","subject":"Inquiry","body":"I need information about fees","received_at":"2025-12-03 21:40:00","status":"pending"}' "Create inbound SMS"
test_endpoint "POST" "/communications/inbound" '{"source_type":"email","source_address":"parent@gmail.com","subject":"School Fee Payment","body":"When is the next payment due?","received_at":"2025-12-03 21:45:00","status":"pending"}' "Create inbound email"
test_endpoint "GET" "/communications/inbound/1" "null" "Get specific inbound message"
test_endpoint "PUT" "/communications/inbound/1" '{"status":"processed","processing_notes":"Responded via email"}' "Update inbound message"
test_endpoint "DELETE" "/communications/inbound/1" "null" "Delete inbound message"

# ============================================
# 5. MESSAGE THREADS
# ============================================
test_endpoint "GET" "/communications/thread" "null" "Get message threads"
test_endpoint "POST" "/communications/thread" '{"subject":"Staff Meeting Discussion","created_by":1,"participants":[1,2,3],"body":"Discussing agenda for next staff meeting"}' "Create thread"
test_endpoint "GET" "/communications/thread/1" "null" "Get specific thread"
test_endpoint "PUT" "/communications/thread/1" '{"subject":"Updated: Staff Meeting Discussion","body":"Agenda finalized"}' "Update thread"
test_endpoint "DELETE" "/communications/thread/1" "null" "Delete thread"

# ============================================
# 6. ANNOUNCEMENTS
# ============================================
test_endpoint "GET" "/communications/announcement" "null" "Get announcements"
test_endpoint "POST" "/communications/announcement" '{"title":"School Closure Notice","body":"School will be closed for public holiday","created_by":1,"priority":"high","recipients":"all"}' "Create announcement"
test_endpoint "POST" "/communications/announcement" '{"title":"Term Break Dates","body":"Term 1 ends 15th December, resumes 5th January","created_by":1,"priority":"medium","recipients":"parents"}' "Create parents announcement"
test_endpoint "GET" "/communications/announcement/1" "null" "Get specific announcement"
test_endpoint "PUT" "/communications/announcement/1" '{"title":"Updated: School Closure Notice","body":"Extended closure notice","priority":"high"}' "Update announcement"
test_endpoint "DELETE" "/communications/announcement/1" "null" "Delete announcement"

# ============================================
# 7. INTERNAL REQUESTS
# ============================================
test_endpoint "GET" "/communications/internal-request" "null" "Get internal requests"
test_endpoint "POST" "/communications/internal-request" '{"title":"Finance Report Needed","body":"Please prepare quarterly finance report","requested_by":1,"assigned_to":2,"priority":"high"}' "Create internal request"
test_endpoint "GET" "/communications/internal-request/1" "null" "Get specific request"
test_endpoint "PUT" "/communications/internal-request/1" '{"title":"Finance Report Needed","status":"in_progress","body":"Report in progress"}' "Update internal request"
test_endpoint "DELETE" "/communications/internal-request/1" "null" "Delete internal request"

# ============================================
# 8. PARENT MESSAGES
# ============================================
test_endpoint "GET" "/communications/parent-message" "null" "Get parent messages"
test_endpoint "POST" "/communications/parent-message" '{"student_id":1,"body":"Your child achieved excellent marks in Mathematics","created_by":1,"message_type":"achievement"}' "Create parent achievement message"
test_endpoint "GET" "/communications/parent-message/1" "null" "Get specific parent message"
test_endpoint "PUT" "/communications/parent-message/1" '{"body":"Your child achieved excellent marks in Mathematics - Grade A","status":"sent"}' "Update parent message"
test_endpoint "DELETE" "/communications/parent-message/1" "null" "Delete parent message"

# ============================================
# 9. STAFF FORUM TOPICS
# ============================================
test_endpoint "GET" "/communications/staff-forum-topic" "null" "Get staff forum topics"
test_endpoint "POST" "/communications/staff-forum-topic" '{"title":"Teaching Methods Discussion","body":"Let us discuss effective teaching methodologies","created_by":1}' "Create staff forum topic"
test_endpoint "GET" "/communications/staff-forum-topic/1" "null" "Get specific forum topic"
test_endpoint "PUT" "/communications/staff-forum-topic/1" '{"title":"Updated: Teaching Methods Discussion","body":"Discussion ongoing"}' "Update staff forum topic"
test_endpoint "DELETE" "/communications/staff-forum-topic/1" "null" "Delete staff forum topic"

# ============================================
# 10. STAFF REQUESTS
# ============================================
test_endpoint "GET" "/communications/staff-request" "null" "Get staff requests"
test_endpoint "POST" "/communications/staff-request" '{"title":"Professional Development Course","body":"Request to attend coding workshop","requested_by":1,"assigned_to":2,"priority":"medium"}' "Create staff request"
test_endpoint "GET" "/communications/staff-request/1" "null" "Get specific staff request"
test_endpoint "PUT" "/communications/staff-request/1" '{"title":"Professional Development Course","status":"approved","body":"Approved for attendance"}' "Update staff request"
test_endpoint "DELETE" "/communications/staff-request/1" "null" "Delete staff request"

# ============================================
# 11. COMMUNICATIONS
# ============================================
test_endpoint "GET" "/communications/communication" "null" "Get communications"
test_endpoint "POST" "/communications/communication" '{"subject":"Test Communication","content":"This is a test communication","type":"sms","status":"draft","priority":"medium","sender_id":1}' "Create communication"
test_endpoint "POST" "/communications/communication" '{"subject":"Parent Notification","content":"Scheduled message about exam results","type":"email","status":"scheduled","priority":"high","sender_id":1,"scheduled_at":"2025-12-04 08:00:00"}' "Create scheduled communication"
test_endpoint "GET" "/communications/communication/1" "null" "Get specific communication"
test_endpoint "PUT" "/communications/communication/1" '{"status":"sent","subject":"Test Communication"}' "Update communication"

# ============================================
# 12. ATTACHMENTS 
# ============================================
test_endpoint "GET" "/communications/attachment" "null" "Get attachments (all)"
test_endpoint "GET" "/communications/attachment?communication_id=100" "null" "Get attachments for communication"

# ============================================
# 13. GROUPS
# ============================================
test_endpoint "GET" "/communications/group" "null" "Get groups"
test_endpoint "POST" "/communications/group" '{"name":"Management Team","description":"School management staff","group_type":"staff","members":[1,2,3]}' "Create staff group"
test_endpoint "POST" "/communications/group" '{"name":"Class 8A Parents","description":"Parents of class 8A students","group_type":"parent","members":[10,11,12,13]}' "Create parent group"
test_endpoint "GET" "/communications/group/1" "null" "Get specific group"
test_endpoint "PUT" "/communications/group/1" '{"name":"Management Team Updated","members":[1,2,3,4]}' "Update group"
test_endpoint "DELETE" "/communications/group/1" "null" "Delete group"

# ============================================
# 14. LOGS
# ============================================
test_endpoint "GET" "/communications/log" "null" "Get communication logs"
test_endpoint "GET" "/communications/log/1" "null" "Get specific log"
test_endpoint "POST" "/communications/log" '{"action":"send_sms","entity_type":"communication","entity_id":1,"details":"SMS sent to +254712345678","created_by":1,"timestamp":"2025-12-03 21:50:00"}' "Create log entry"

# ============================================
# 15. RECIPIENTS
# ============================================
test_endpoint "GET" "/communications/recipient" "null" "Get recipients (all)"
test_endpoint "GET" "/communications/recipient?communication_id=100" "null" "Get recipients for communication"

# ============================================
# 16. TEMPLATES
# ============================================
test_endpoint "GET" "/communications/template" "null" "Get templates"
test_endpoint "POST" "/communications/template" '{"name":"Welcome Parent SMS","body":"Welcome to {school_name}. Your child {student_name} has been enrolled. Best regards","template_type":"sms","category":"parent_welcome"}' "Create SMS template"
test_endpoint "POST" "/communications/template" '{"name":"Exam Results Email","body":"Dear {parent_name}, exam results for {student_name} are available. Marks: {marks}. Grade: {grade}","template_type":"email","category":"exam_notification"}' "Create email template"
test_endpoint "GET" "/communications/template/1" "null" "Get specific template"
test_endpoint "PUT" "/communications/template/1" '{"name":"Welcome Parent SMS Updated","body":"Welcome to {school_name}. Student: {student_name}"}' "Update template"
test_endpoint "DELETE" "/communications/template/1" "null" "Delete template"

# ============================================
# 17. WORKFLOW INSTANCES
# ============================================
test_endpoint "GET" "/communications/workflow-instance" "null" "Get workflow instances"
test_endpoint "GET" "/communications/workflow-instance/1" "null" "Get specific workflow instance"
test_endpoint "POST" "/communications/workflow-instance" '{"reference_type":"communication","reference_id":3,"workflow_code":"communications","initiated_by":1}' "Create workflow instance"

echo "✓ Communications endpoints test completed"
echo "✓ Results saved to: $OUTPUT_FILE"
