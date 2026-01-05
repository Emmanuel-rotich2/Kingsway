#!/usr/bin/env bash
set -euo pipefail

API_URL="http://localhost/Kingsway/api"
TOKEN="devtest"
TOKEN_HEADER='-H "X-Test-Token: devtest"'
ACADEMIC_YEAR="2026"
DB_NAME="KingsWayAcademy"
MYSQL="/opt/lampp/bin/mysql -u root -padmin123"

# Classes to ensure (Playgroup then Grade 1..9)
classes=("Playgroup" "Grade 1" "Grade 2" "Grade 3" "Grade 4" "Grade 5" "Grade 6" "Grade 7" "Grade 8" "Grade 9")

echo "Using API: $API_URL (test token)
"

# helper: get department id for Academics or first available
get_department_id() {
  if command -v jq >/dev/null 2>&1; then
    curl -s $TOKEN_HEADER "$API_URL/staff/departments-get" | jq -r '(.data.data // .data // [])[] | select(.name|test("academ","i")) | .id' | head -n1
    return
  fi
  resp=$(curl -s $TOKEN_HEADER "$API_URL/staff/departments-get")
  echo "$resp" | php -r '$d=json_decode(stream_get_contents(STDIN), true); $rows = $d["data"]["data"] ?? $d; if(is_array($rows)){ foreach($rows as $row){ if(stripos($row["name"], "academ")!==false){ echo $row["id"]; exit;} } if(isset($rows[0]["id"])) echo $rows[0]["id"]; }'
}

# Resolve department id (prefer Academics)
# Try to resolve Academics department via MySQL first (reliable)
DEPT_ID=$($MYSQL $DB_NAME -N -s -e "SELECT id FROM departments WHERE name LIKE '%Academ%' LIMIT 1" || echo "")
if [ -z "$DEPT_ID" ]; then
  # Fallback to API discovery
  if command -v jq >/dev/null 2>&1; then
    DEPT_ID=$(curl -s $TOKEN_HEADER "$API_URL/staff/departments-get" | jq -r '(.data.data // .data // []) | map(select(.name|test("academ","i")))[0].id // (.[0].id // empty)')
  else
    resp=$(curl -s $TOKEN_HEADER "$API_URL/staff/departments-get")
    DEPT_ID=$(echo "$resp" | php -r '$d=json_decode(stream_get_contents(STDIN), true); $rows = $d["data"]["data"] ?? $d; if(is_array($rows)){ foreach($rows as $row){ if(stripos($row["name"], "academ")!==false){ echo $row["id"]; exit;} } if(isset($rows[0]["id"])) echo $rows[0]["id"]; }')
  fi
fi
if [ -z "$DEPT_ID" ]; then
  echo "No department found; aborting" >&2; exit 1
fi

# Ensure staff table has a date_of_birth column (add if missing)
exists=$($MYSQL -N -s $DB_NAME -e "SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA='$DB_NAME' AND TABLE_NAME='staff' AND COLUMN_NAME='date_of_birth'")
if [ "$exists" -eq 0 ]; then
  echo "Adding date_of_birth column to staff table"
  $MYSQL $DB_NAME -e "ALTER TABLE staff ADD COLUMN date_of_birth DATE NULL"
else
  echo "staff.date_of_birth already exists"
fi

# function to find or create a class and return id (not used now, kept for reference)
ensure_class() {
  local cname="$1"; local grade="$2"
  # Robust implementation would query the classes-list endpoint and return the matching id
  cid=$(curl -s $TOKEN_HEADER "$API_URL/academic/classes-list" | php -r '$d=json_decode(stream_get_contents(STDIN), true); $rows = $d["data"] ?? $d; foreach($rows as $c){ if(isset($c["name"]) && $c["name"]=="'"${cname//"/\"}'"') { echo $c["id"]; break; } }')
}

# We'll use a simpler PHP parser per-step to reduce edge cases
for cname in "${classes[@]}"; do
  grade=0
  if [[ "$cname" =~ Grade[[:space:]]([0-9]+) ]]; then grade=${BASH_REMATCH[1]}; fi

  # Check existing
  # Use jq if available for robust JSON parsing
  if command -v jq >/dev/null 2>&1; then
    existing_id=$(curl -s $TOKEN_HEADER "$API_URL/academic/classes-list" \
      | jq -r --arg name "$cname" '.data[] | select(.name==$name) | .id' \
      | head -n1)
  else
    existing_id=$(curl -s $TOKEN_HEADER "$API_URL/academic/classes-list" \
      | NAME="$cname" php -r '
$d=json_decode(stream_get_contents(STDIN), true);
$name = getenv("NAME");
$rows = $d["data"] ?? $d;
foreach($rows as $c){ if(isset($c["name"]) && $c["name"]==$name){ echo $c["id"]; break; } }')
  fi

  if [ -n "$existing_id" ]; then
    echo "Class '$cname' exists (id: $existing_id)"
    class_id=$existing_id
  else
    echo "Creating class '$cname' (grade: $grade)"
    create_resp=$(curl -s $TOKEN_HEADER -H "Content-Type: application/json" -d "{\"name\": \"$cname\", \"grade\": $grade, \"academic_year\": \"$ACADEMIC_YEAR\"}" "$API_URL/academic/classes-create")
    if command -v jq >/dev/null 2>&1; then
      class_id=$(echo "$create_resp" | jq -r '.data.id // empty')
    else
      class_id=$(echo "$create_resp" | php -r '$d=json_decode(stream_get_contents(STDIN), true); echo $d["data"]["id"] ?? "";')
    fi
    echo " -> created id: $class_id"
  fi

  # Auto-create streams for the class
  echo "Ensuring streams for class $class_id"
  curl -s $TOKEN_HEADER -H "Content-Type: application/json" -d "{\"class_id\": $class_id}" "$API_URL/academic/classes-auto-create-streams" >/dev/null

  # Get first stream id
  if command -v jq >/dev/null 2>&1; then
    stream_id=$(curl -s $TOKEN_HEADER "$API_URL/academic/streams-list?class_id=$class_id" | jq -r '.data[0].id // empty')
  else
    stream_id=$(curl -s $TOKEN_HEADER "$API_URL/academic/streams-list?class_id=$class_id" | php -r '$d=json_decode(stream_get_contents(STDIN), true); echo $d["data"][0]["id"] ?? "";')
  fi
  echo " -> stream id: $stream_id"

  # Create 5 students for each class if fewer than 5 exist
  if command -v jq >/dev/null 2>&1; then
    existing_students=$(curl -s $TOKEN_HEADER "$API_URL/students?search=$cname" | jq -r '.data.students | length')
  else
    existing_students=$(curl -s $TOKEN_HEADER "$API_URL/students?search=$cname" | php -r '$d=json_decode(stream_get_contents(STDIN), true); echo count($d["data"]["students"] ?? []);')
  fi
  need=$((5 - existing_students))
  if [ $need -gt 0 ]; then
    echo "Creating $need students in $cname"
    for i in $(seq 1 $need); do
      timestamp=$(date +%s)
      admission_no="${cname// /}_$timestamp_$i"
      # approximate age: Playgroup -> 4; Grade1 -> 6; GradeN -> N+5
      if [ "$cname" == "Playgroup" ]; then age=4; elif [[ "$cname" =~ Grade[[:space:]]([0-9]+) ]]; then n=${BASH_REMATCH[1]}; age=$((n+5)); else age=8; fi
      year=$(date +%Y)
      dob_year=$((year - age))
      dob="${dob_year}-01-01"
      gender="male"
      if [ $((i % 2)) -eq 0 ]; then gender="female"; fi
      first_name="${cname// /}_Stu_$i"
      last_name="Demo"
      admission_date=$(date +%Y-%m-%d)
      data=$(cat <<JSON
{"admission_no":"$admission_no","first_name":"$first_name","last_name":"$last_name","stream_id":$stream_id,"date_of_birth":"$dob","gender":"$gender","admission_date":"$admission_date"}
JSON
)
      curl -s $TOKEN_HEADER -H "Content-Type: application/json" -d "$data" "$API_URL/students" >/dev/null
    done
  else
    echo "Class $cname already has $existing_students students"
  fi

  # Create and assign a class teacher if not assigned
  assigned_teacher=$(curl -s $TOKEN_HEADER "$API_URL/academic/classes-get?id=$class_id" | php -r '$d=json_decode(stream_get_contents(STDIN), true); echo $d["data"]["teacher_id"] ?? "";')
  if [ -n "$assigned_teacher" ]; then
    echo "Class $cname already has teacher id $assigned_teacher"
  else
    # create teacher staff
    email="${cname// /}_teacher@example.com"
    first="${cname// /}_Teacher"
    last="Demo"
    employ_date=$(date +%Y-%m-%d)
    staff_payload=$(cat <<JSON
{"first_name":"$first","last_name":"$last","email":"$email","department_id":$DEPT_ID,"position":"Class Teacher","employment_date":"$employ_date","date_of_birth":"$(date -d "$dob_year-01-01" +%Y-%m-%d)"}
JSON
)
    staff_resp=$(curl -s $TOKEN_HEADER -H "Content-Type: application/json" -d "$staff_payload" "$API_URL/staff")
    if command -v jq >/dev/null 2>&1; then
      teacher_id=$(echo "$staff_resp" | jq -r '.data.id // empty')
    else
      teacher_id=$(echo "$staff_resp" | php -r '$d=json_decode(stream_get_contents(STDIN), true); echo $d["data"]["id"] ?? "";')
    fi
    if [ -z "$teacher_id" ]; then
      echo "Failed to create teacher for $cname; response: $staff_resp" >&2
    else
      echo " -> created teacher id $teacher_id and assigning as class teacher"
      curl -s $TOKEN_HEADER -H "Content-Type: application/json" -d "{\"class_id\": $class_id, \"teacher_id\": $teacher_id}" "$API_URL/academic/classes-assign-teacher" >/dev/null

      # For grades 4-9, create subject teachers and assign them to this class/stream
      if [ "$grade" -ge 4 ]; then
        echo "Ensuring subject teachers for $cname (grade $grade)"
        # choose subject names per grade band
        if [ "$grade" -le 6 ]; then
          subject_names=("Mathematics" "English" "Science & Technology")
        else
          subject_names=("Mathematics (JSS)" "English (JSS)" "Integrated Science")
        fi

        for subj in "${subject_names[@]}"; do
          if command -v jq >/dev/null 2>&1; then
            subj_id=$(curl -s $TOKEN_HEADER "$API_URL/academic/subjects/list" | jq -r --arg s "$subj" '.data[] | select(.name==$s) | .id' | head -n1)
          else
            subj_id=$(curl -s $TOKEN_HEADER "$API_URL/academic/subjects/list" | php -r '$d=json_decode(stream_get_contents(STDIN), true); foreach($d["data"] as $r){ if($r["name"]=="'"${subj//"/\"}'"') { echo $r["id"]; break; } }')
          fi
          if [ -z "$subj_id" ]; then
            echo " -> subject '$subj' not found, skipping"
            continue
          fi

          # Create a subject teacher (if not exists for this class-subject)
          t_email="${cname// /}_${subj// /}_teacher@example.com"
          if command -v jq >/dev/null 2>&1; then
            exist_tid=$(curl -s $TOKEN_HEADER "$API_URL/staff?search=$t_email" | jq -r '.data[]?.id // empty' | head -n1)
          else
            exist_tid=$(curl -s $TOKEN_HEADER "$API_URL/staff?search=$t_email" | php -r '$d=json_decode(stream_get_contents(STDIN), true); echo $d["data"][0]["id"] ?? "";')
          fi
          if [ -z "$exist_tid" ]; then
            fname="${cname// /}_${subj// /}_T"
            lnam="Demo"
            dob_teacher="$(date -d "-30 years" +%Y-%m-%d)"
            staff_payload=$(cat <<JSON
{"first_name":"$fname","last_name":"$lnam","email":"$t_email","department_id":$DEPT_ID,"position":"Subject Teacher","employment_date":"$employ_date","date_of_birth":"$dob_teacher"}
JSON
)
            staff_resp=$(curl -s $TOKEN_HEADER -H "Content-Type: application/json" -d "$staff_payload" "$API_URL/staff")
            if command -v jq >/dev/null 2>&1; then
              exist_tid=$(echo "$staff_resp" | jq -r '.data.id // empty')
            else
              exist_tid=$(echo "$staff_resp" | php -r '$d=json_decode(stream_get_contents(STDIN), true); echo $d["data"]["id"] ?? "";')
            fi
            echo " -> created subject teacher id $exist_tid for subject $subj"
          fi

          # Assign subject to teacher for this class/stream (best-effort)
          assign_payload=$(cat <<JSON
{"staff_id": $exist_tid, "subject_id": $subj_id, "class_id": $class_id, "stream_id": $stream_id}
JSON
)
          curl -s $TOKEN_HEADER -H "Content-Type: application/json" -d "$assign_payload" "$API_URL/staff/assign-subject" >/dev/null || true
        done
      fi
    fi
  fi

  # Mark today's attendance as present for the students of this stream
  if command -v jq >/dev/null 2>&1; then
    student_ids=$(curl -s $TOKEN_HEADER "$API_URL/students?stream_id=$stream_id&limit=100" | jq -r '.data.students[]?.id // empty' | paste -sd, -)
  else
    student_ids=$(curl -s $TOKEN_HEADER "$API_URL/students?stream_id=$stream_id&limit=100" | php -r '$d=json_decode(stream_get_contents(STDIN), true); $out=[]; foreach($d["data"]["students"] as $s){ $out[]=$s["id"]; } echo implode(",",$out);')
  fi
  IFS=',' read -ra arr <<< "$student_ids"
  today=$(date +%Y-%m-%d)
  for sid in "${arr[@]}"; do
    if [[ -n "$sid" ]]; then
      curl -s $TOKEN_HEADER -H "Content-Type: application/json" -d "{\"student_id\": $sid, \"date\": \"$today\", \"status\": \"present\"}" "$API_URL/students/attendance/mark" >/dev/null
    fi
  done

done

echo "Seeding complete." 
