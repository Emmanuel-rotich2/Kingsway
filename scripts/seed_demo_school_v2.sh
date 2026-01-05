#!/usr/bin/env bash
set -euo pipefail

API_URL="http://localhost/Kingsway/api"
TOKEN="devtest"
ACADEMIC_YEAR="2026"
CURRENT_TERM="Term 1"
DB_NAME="KingsWayAcademy"
MYSQL="/opt/lampp/bin/mysql -u root -padmin123"

# Require jq
if ! command -v jq >/dev/null 2>&1; then
  echo "jq is required for this script"
  exit 1
fi

classes=("Playgroup" "Grade 1" "Grade 2" "Grade 3" "Grade 4" "Grade 5" "Grade 6" "Grade 7" "Grade 8" "Grade 9")

echo "Using API: $API_URL (token devtest)"

# Get Academics department id from DB (fallback to API)
DEPT_ID=$($MYSQL $DB_NAME -N -s -e "SELECT id FROM departments WHERE name LIKE '%Academ%' LIMIT 1" || true)
if [ -z "$DEPT_ID" ]; then
  DEPT_ID=$(curl -s -H "X-Test-Token: $TOKEN" "$API_URL/staff/departments-get" | jq -r '(.data.data // .data // []) | map(select(.name|test("academ","i")))[0].id // .[0].id')
fi
if [ -z "$DEPT_ID" ]; then
  echo "No Academics department found; aborting" >&2
  exit 1
fi

echo "Academics department id: $DEPT_ID"

# Ensure staff.date_of_birth exists
exists=$($MYSQL -N -s $DB_NAME -e "SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA='$DB_NAME' AND TABLE_NAME='staff' AND COLUMN_NAME='date_of_birth'" || echo 0)
if [ "$exists" -eq 0 ]; then
  echo "Adding date_of_birth column to staff table"
  $MYSQL $DB_NAME -e "ALTER TABLE staff ADD COLUMN date_of_birth DATE NULL"
else
  echo "staff.date_of_birth already exists"
fi

# Helper: find or create class
find_or_create_class() {
  local name="$1"; local grade=$2
  local cid
  cid=$(curl -s -H "X-Test-Token: $TOKEN" "$API_URL/academic/classes-list" | jq -r --arg name "$name" '.data[]? | select(.name==$name) | .id' | head -n1)
  if [ -z "$cid" ]; then
      echo "Creating class $name" >&2
      # Determine level_id: Playgroup -> 1 (Nursery), Grade1-3 -> 2 (Lower Primary), Grade4-6 -> 3 (Upper Primary), Grade7-9 -> 4 (Junior Secondary)
      if [ "$name" == "Playgroup" ]; then level_id=1; elif [ "$grade" -ge 1 ] && [ "$grade" -le 3 ]; then level_id=2; elif [ "$grade" -ge 4 ] && [ "$grade" -le 6 ]; then level_id=3; elif [ "$grade" -ge 7 ] && [ "$grade" -le 9 ]; then level_id=4; else level_id=2; fi
      create_resp=$(curl -s -H "X-Test-Token: $TOKEN" -H "Content-Type: application/json" -d "{\"name\": \"$name\", \"grade\": $grade, \"level_id\": $level_id, \"academic_year\": \"$ACADEMIC_YEAR\"}" "$API_URL/academic/classes-create")
      if echo "$create_resp" | jq -e . >/dev/null 2>&1; then
        cid=$(echo "$create_resp" | jq -r '.data.id // empty')
      else
        echo "Warning: classes-create returned non-JSON: $create_resp" >&2
        cid=""
      fi
      # If creation failed because class already exists, re-query to find the id
      if [ -z "$cid" ]; then
        cid=$(curl -s -H "X-Test-Token: $TOKEN" "$API_URL/academic/classes-list" | jq -r --arg name "$name" '.data[]? | select(.name==$name) | .id' | head -n1)
      fi
    fi
  echo "$cid"
}

for cname in "${classes[@]}"; do
  grade=0
  if [[ "$cname" =~ Grade[[:space:]]([0-9]+) ]]; then grade=${BASH_REMATCH[1]}; fi

  class_id=$(find_or_create_class "$cname" $grade)
  echo "Class: $cname (id: $class_id)"

  # Ensure streams
  curl -s -H "X-Test-Token: $TOKEN" -H "Content-Type: application/json" -d "{\"class_id\": $class_id}" "$API_URL/academic/classes-auto-create-streams" >/dev/null || true

  # Find or create a stream id for this class. Prefer API, fallback to DB lookup, and if still missing insert default stream directly in DB (avoid creating via API).
  stream_id=""
  stream_resp=$(curl -s -H "X-Test-Token: $TOKEN" "$API_URL/academic/streams-list?class_id=$class_id" 2>/dev/null || echo "")
  if echo "$stream_resp" | jq -e . >/dev/null 2>&1; then
    stream_id=$(echo "$stream_resp" | jq -r '.data[0].id // empty' 2>/dev/null || echo "")
  fi

  if [ -z "$stream_id" ]; then
    # Try direct DB lookup for the default stream (trigger-created)
    stream_id=$($MYSQL $DB_NAME -N -s -e "SELECT id FROM class_streams WHERE class_id = $class_id AND status = 'active' LIMIT 1" 2>/dev/null || echo "")
  fi

  if [ -z "$stream_id" ]; then
    echo " No active stream found for class $class_id; inserting default stream via DB" >&2
    $MYSQL $DB_NAME -e "INSERT INTO class_streams (class_id, stream_name, capacity, status) VALUES ($class_id, 'A', 40, 'active')"
    stream_id=$($MYSQL $DB_NAME -N -s -e "SELECT LAST_INSERT_ID()" || echo "")
  fi

  echo " Stream id: $stream_id"

  # Ensure at least 5 students
  student_count=0
  students_resp=$(curl -s -H "X-Test-Token: $TOKEN" "$API_URL/students?stream_id=$stream_id&limit=100")
  if echo "$students_resp" | jq -e . >/dev/null 2>&1; then
    student_count=$(echo "$students_resp" | jq -r '(.data.students? // .data.data.students? // []) | length')
  else
    echo " Warning: students endpoint returned non-JSON when fetching for stream $stream_id" >&2
    student_count=0
  fi
  need=$((5 - student_count))
  if [ $need -gt 0 ]; then
    echo " Creating $need students for $cname"
    for i in $(seq 1 $need); do
      # Short admission_no (<=20 chars). Use class code + short timestamp and sequence
      if [ "$cname" == "Playgroup" ]; then
        code="PG"
      elif [[ "$cname" =~ Grade[[:space:]]([0-9]+) ]]; then
        code="G${BASH_REMATCH[1]}"
      else
        code=$(echo "$cname" | tr -cd 'A-Za-z' | tr '[:lower:]' '[:upper:]' | cut -c1-3)
      fi
      ts_short=$(date +%s | tail -c 5)
      admission_no="${code}_${ts_short}${i}"
      # Ensure admission_no is at most 20 chars
      admission_no=$(echo "$admission_no" | cut -c1-20)
      if [ "$cname" == "Playgroup" ]; then age=4; elif [[ "$cname" =~ Grade[[:space:]]([0-9]+) ]]; then n=${BASH_REMATCH[1]}; age=$((n+5)); else age=8; fi
      dob_year=$(( $(date +%Y) - age ))
      dob="${dob_year}-01-01"
      gender="male"
      if [ $((i % 2)) -eq 0 ]; then gender="female"; fi
      fn="${cname// /}_Stu_$i"
      ln="Demo"
      admission_date=$(date +%Y-%m-%d)

      if [ -n "$stream_id" ]; then
        payload=$(jq -n --arg an "$admission_no" --arg fn "$fn" --arg ln "$ln" --argjson sid "$stream_id" --arg dob "$dob" --arg gender "$gender" --arg adate "$admission_date" '{admission_no:$an, first_name:$fn, last_name:$ln, stream_id:$sid, date_of_birth:$dob, gender:$gender, admission_date:$adate}')
      else
        payload=$(jq -n --arg an "$admission_no" --arg fn "$fn" --arg ln "$ln" --arg dob "$dob" --arg gender "$gender" --arg adate "$admission_date" '{admission_no:$an, first_name:$fn, last_name:$ln, date_of_birth:$dob, gender:$gender, admission_date:$adate}')
      fi
      create_resp=$(curl -s -H "X-Test-Token: $TOKEN" -H "Content-Type: application/json" -d "$payload" "$API_URL/students" ) || true
      if echo "$create_resp" | jq -e . >/dev/null 2>&1; then
        created_id=$(echo "$create_resp" | jq -r '.data.id // empty')
        if [ -n "$created_id" ]; then
          echo " Created student id: $created_id"
        fi
      else
        echo " Warning: students create returned non-JSON: $create_resp" >&2
      fi
    done
  else
    echo " Already has $student_count students"
  fi

  # Ensure class teacher
  # Prefer using classes-list (avoids known classes-get SQL edge cases)
  assigned_teacher=$(curl -s -H "X-Test-Token: $TOKEN" "$API_URL/academic/classes-list" | jq -r --argjson cid "$class_id" '.data[]? | select(.id==$cid) | .teacher_id // empty' 2>/dev/null || echo "")
  if [ -z "$assigned_teacher" ]; then
    email="${cname// /}_teacher@example.com"
    fname="${cname// /}_Teacher"
    lname="Demo"
    dob_teacher="$(date -d "-30 years" +%Y-%m-%d)"
    staff_payload=$(jq -n --arg fn "$fname" --arg ln "$lname" --arg em "$email" --argjson did $DEPT_ID --arg edate "$(date +%Y-%m-%d)" --arg dob "$dob_teacher" '{first_name:$fn, last_name:$ln, email:$em, department_id:$did, position:"Class Teacher", employment_date:$edate, date_of_birth:$dob}')
    # create staff
      staff_resp=$(curl -s -H "X-Test-Token: $TOKEN" -H "Content-Type: application/json" -d "$staff_payload" "$API_URL/staff") || true
    if echo "$staff_resp" | jq -e . >/dev/null 2>&1; then
      teacher_id=$(echo "$staff_resp" | jq -r '.data.id // empty')
    else
      echo "Staff create response not JSON: $staff_resp" >&2
      teacher_id=""
    fi
    if [ -n "$teacher_id" ]; then
      curl -s -H "X-Test-Token: $TOKEN" -H "Content-Type: application/json" -d "{\"class_id\": $class_id, \"teacher_id\": $teacher_id}" "$API_URL/academic/classes-assign-teacher" >/dev/null || true
      echo " Assigned class teacher id: $teacher_id"
    fi
  else
    echo " Class already has teacher id: $assigned_teacher"
  fi

  # For grades 4-9, assign subject teachers
  if [ "$grade" -ge 4 ]; then
    if [ "$grade" -le 6 ]; then
      subjects=("Mathematics" "English" "Science & Technology")
    else
      subjects=("Mathematics (JSS)" "English (JSS)" "Integrated Science")
    fi
    for subj in "${subjects[@]}"; do
      subj_id=$(curl -s -H "X-Test-Token: $TOKEN" "$API_URL/academic/subjects/list" | jq -r --arg s "$subj" '.data[]? | select(.name==$s) | .id' | head -n1)
      if [ -z "$subj_id" ]; then echo " Subject $subj missing, skipping"; continue; fi
      t_email="${cname// /}_${subj// /}_teacher@example.com"
      staff_search_resp=$(curl -s -H "X-Test-Token: $TOKEN" "$API_URL/staff?search=$t_email" 2>/dev/null || echo "")
      if echo "$staff_search_resp" | jq -e . >/dev/null 2>&1; then
        exist_tid=$(echo "$staff_search_resp" | jq -r '.. | .id? // empty' | head -n1)
      else
        echo "Warning: staff search returned non-JSON: $staff_search_resp" >&2
        exist_tid=""
      fi
      if [ -z "$exist_tid" ]; then
        fname="${cname// /}_${subj// /}_T"
        lnam="Demo"
        dob_teacher="$(date -d "-30 years" +%Y-%m-%d)"
        staff_payload=$(jq -n --arg fn "$fname" --arg ln "$lnam" --arg em "$t_email" --argjson did $DEPT_ID --arg edate "$(date +%Y-%m-%d)" --arg dob "$dob_teacher" '{first_name:$fn, last_name:$ln, email:$em, department_id:$did, position:"Subject Teacher", employment_date:$edate, date_of_birth:$dob}')
        staff_resp=$(curl -s -H "X-Test-Token: $TOKEN" -H "Content-Type: application/json" -d "$staff_payload" "$API_URL/staff") || true
        if echo "$staff_resp" | jq -e . >/dev/null 2>&1; then
          exist_tid=$(echo "$staff_resp" | jq -r '.data.id // empty')
        else
          echo "Warning: staff create returned non-JSON: $staff_resp" >&2
          exist_tid=""
        fi
        echo " Created subject teacher id $exist_tid for $subj"
      fi
      # Assign subject to teacher for this class (best-effort)
      if [ -n "$exist_tid" ]; then
        assign_payload="{\"staff_id\": ${exist_tid:-null}, \"subject_id\": ${subj_id:-null}, \"class_id\": ${class_id:-null}, \"stream_id\": ${stream_id:-null}}"
        curl -s -H "X-Test-Token: $TOKEN" -H "Content-Type: application/json" -d "$assign_payload" "$API_URL/staff/assign-subject" >/dev/null || true
      fi
    done
  fi

  # Mark today's attendance for students in stream
  student_ids=""
  students_resp2=$(curl -s -H "X-Test-Token: $TOKEN" "$API_URL/students?stream_id=$stream_id&limit=200")
  if echo "$students_resp2" | jq -e . >/dev/null 2>&1; then
    student_ids=$(echo "$students_resp2" | jq -r '(.data.students? // .data.data.students? // []) | map(.id) | join(",")')
  else
    echo " Warning: students list returned non-JSON for stream $stream_id" >&2
  fi
  IFS=',' read -ra arr <<< "$student_ids"
  today=$(date +%Y-%m-%d)
  for sid in "${arr[@]}"; do
    if [ -n "$sid" ]; then
      curl -s -H "X-Test-Token: $TOKEN" -H "Content-Type: application/json" -d "{\"student_id\": $sid, \"date\": \"$today\", \"status\": \"present\"}" "$API_URL/students/attendance/mark" >/dev/null || true
    fi
  done

done

echo "Seeding complete (v2)." 
