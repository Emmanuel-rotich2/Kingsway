#!/usr/bin/env bash
# End-to-end API walk of the redesigned admissions workflow against the LIVE
# KingsWayAcademy DB, driven entirely through the running Apache app (which has
# pdo_mysql via libphp.so). The CLI `php` lacks pdo_mysql, so we exercise the
# real REST endpoints over HTTPS instead of the orphaned CLI verify script.
#
# Usage:
#   BASE_URL=https://127.0.0.1/Kingsway \
#   USER=director1 PASS='Pass123!@' \
#   APP_ID=8 \
#   ./scripts/verify_admissions_api_walk.sh
#
# Requires a login user that holds admissions-process permissions (registrar /
# headteacher / director / accountant, depending on the stage). If a single
# user lacks all of them, run once per role and stitch the results — or set
# USER/PASS to the most privileged admission user available.

set -uo pipefail

BASE_URL="${BASE_URL:-https://127.0.0.1/Kingsway}"
USER="${USER:-}"
PASS="${PASS:-}"
APP_ID="${APP_ID:-8}"          # the real application left at application_review after backfill
APP_ID="${APP_ID:-${APP_ID}}"

if [[ -z "$USER" || -z "$PASS" ]]; then
  echo "ERROR: set USER and PASS (a login with admission permissions)." >&2
  exit 2
fi

# Use curl with -k because the dev LAMPP cert is self-signed.
CURL=(curl -ks -H "Content-Type: application/json")
API="$BASE_URL/api"

jget() { php -r '$j=json_decode($argv[1],true); $p=explode(".",$argv[2]); $v=$j; foreach($p as $k){$v=is_array($v)?($v[$k]??null):null;} echo is_null($v)?"":(is_scalar($v)?$v:json_encode($v));' "$1" "$2"; }

# 1) Login and capture Bearer token.
login_resp=$("${CURL[@]}" -X POST "$API/auth/login" -d "{\"username\":\"$USER\",\"password\":\"$PASS\"}")
token=$(jget "$login_resp" "data.token")
status=$(jget "$login_resp" "status")
if [[ -z "$token" ]]; then
  echo "LOGIN FAILED (status=$status): $login_resp" >&2
  exit 3
fi
echo "=== Logged in as $USER (token len ${#token}) ==="
AUTH=(-H "Authorization: Bearer $token")

# Helper: call an admission action endpoint and report.
walk() {
  local label="$1"; local method="$2"; local path="$3"; local body="$4"
  local resp
  if [[ "$method" == "GET" ]]; then
    resp=$("${CURL[@]}" "${AUTH[@]}" "$API/$path")
  else
    resp=$("${CURL[@]}" "${AUTH[@]}" -X "$method" "$API/$path" -d "$body")
  fi
  local ok; ok=$(jget "$resp" "success")
  local st; st=$(jget "$resp" "data.current_stage")
  local msg; msg=$(jget "$resp" "message")
  printf "[%-4s] %-26s -> stage=%-22s msg=%s\n" "${ok}" "$label" "${st:-?}" "$msg"
  echo "   RAW: $resp" >&2
}

# Pull the current stage before we start (from getApplication).
cur() {
  local resp; resp=$("${CURL[@]}" "${AUTH[@]}" "$API/admission/applications/$APP_ID")
  jget "$resp" "data.current_stage"
}
echo "START stage = $(cur)"

# Walk the new-key pipeline. Stages:

# documents_upload -> documents_verification requires actual docs; the backfilled
# app 8 has 0 docs, so we test upload+verify via the document endpoints if
# available, then continue. We attempt the canonical action endpoints; if the
# controller gates them, walk() shows the gate message for manual review.

walk "review (view)"            GET  "admission/applications/$APP_ID"            ""
walk "check-class-space(avail)" POST "admission/check-class-space/$APP_ID"      '{"available":true,"notes":"api walk"}'
walk "admit-student"            POST "admission/admit-student/$APP_ID"           '{}'
walk "create-provisional Stud"  POST "admission/create-provisional-student/$APP_ID" '{}'
walk "record-fee-payment"       POST "admission/record-fee-payment/$APP_ID"     '{"amount":1000,"payment_method":"cash","reference":"WALK1"}'
walk "generate-student-id-card" POST "admission/generate-student-id-card/$APP_ID" '{}'
walk "final-approval"           POST "admission/final-approval/$APP_ID"         '{}'
walk "complete-enrollment"      POST "admission/complete-enrollment/$APP_ID"    '{"status":"verified"}'

echo "FINAL stage = $(cur)"

# Old-key assertion: ensure no legacy stage keys remain on this app's instance.
old_count=$(/opt/lampp/bin/mysql -u root -padmin123 KingsWayAcademy -N -e "
  SELECT COUNT(*) FROM workflow_instances wi
  JOIN admission_applications aa ON wi.reference_id=aa.id AND wi.reference_type='admission_application'
  WHERE aa.id=$APP_ID AND wi.current_stage IN ('application','document_verification','interview_assessment','placement_offer','fee_payment','enrollment','director_confirmation');
" 2>/dev/null)
echo "OLD stage keys for app $APP_ID (expect 0): ${old_count:-?}"

student_rows=$(/opt/lampp/bin/mysql -u root -padmin123 KingsWayAcademy -N -e "
  SELECT COUNT(*) FROM students WHERE application_id=$APP_ID;
" 2>/dev/null)
echo "students rows linked to app $APP_ID (expect 1): ${student_rows:-?}"
