#!/bin/bash
# Create test users (one per role) via API and assign their role permissions
# Usage: TRUNCATE=true ./create_test_users_api.sh

set -euo pipefail

API_BASE="http://localhost/Kingsway/api/users"
LOGIN_USER="test_system_admin"
LOGIN_PASS="testpass"
DB_NAME="KingsWayAcademy"
MYSQL="/opt/lampp/bin/mysql -u root -padmin123"

log() { echo "[$(date +%H:%M:%S)] $*"; }

# Optional truncate for a clean run
if [[ "${TRUNCATE:-false}" == "true" ]]; then
  log "Truncating users, user_roles, user_permissions..."
  $MYSQL $DB_NAME <<'EOSQL'
SET FOREIGN_KEY_CHECKS = 0;
TRUNCATE TABLE user_permissions;
TRUNCATE TABLE user_roles;
TRUNCATE TABLE users;
SET FOREIGN_KEY_CHECKS = 1;
EOSQL

  # Seed bootstrap admin for API login
  log "Seeding bootstrap system admin (test_system_admin)..."
  ADMIN_HASH=$(php -r "echo password_hash('testpass', PASSWORD_BCRYPT);")
  $MYSQL $DB_NAME <<EOSQL
INSERT INTO users (username, email, password, first_name, last_name, role_id, status, created_at, updated_at)
VALUES ('test_system_admin', 'testsystemadmin@kingsway.ac.ke', '$ADMIN_HASH', 'Test', 'System Admin', 2, 'active', NOW(), NOW());
EOSQL
fi

# Login to get JWT
log "Logging in as ${LOGIN_USER}..."
set +e
login_resp=$(curl -s -X POST "${API_BASE}/login" \
  -H 'Content-Type: application/json' \
  -d "{\"username\":\"${LOGIN_USER}\",\"password\":\"${LOGIN_PASS}\"}")
set -e

TOKEN=$(echo "$login_resp" | python3 -c "import json,sys; d=json.load(sys.stdin); print(d.get('data', {}).get('token', ''))")

if [[ -z "$TOKEN" ]]; then
  log "ERROR: Failed to obtain token"
  log "Login response: ${login_resp}"
  exit 1
fi
log "Token acquired."

# Ensure admin has role permissions
ADMIN_ID=$($MYSQL -B -N $DB_NAME -e "SELECT id FROM users WHERE username='test_system_admin' LIMIT 1;")
if [[ -n "$ADMIN_ID" ]]; then
  assign_payload="{\"user_id\":${ADMIN_ID},\"role_ids\":[2]}"
  curl -s -X POST "${API_BASE}/roles-bulk-assign-to-user" \
    -H 'Content-Type: application/json' \
    -H "Authorization: Bearer ${TOKEN}" \
    -d "${assign_payload}" >/dev/null
fi

# Fetch roles from DB (tab-separated: id\tname)
log "Fetching roles from database..."
roles=$($MYSQL -B -N $DB_NAME -e "SELECT id, name FROM roles ORDER BY id;")

if [[ -z "$roles" ]]; then
  log "ERROR: No roles found" && exit 1
fi

created=0; failed=0
while IFS=$'\t' read -r role_id role_name; do
  [[ -z "$role_id" ]] && continue
  role_slug=$(echo "$role_name" \
    | tr '[:upper:]' '[:lower:]' \
    | sed -e 's#/#_#g' -e 's/ - /_/g' -e 's/ /_/g' -e 's/[^a-z0-9_]//g')
  username="test_${role_slug}"
  email_name=$(echo "$role_slug" | tr -d '_')
  email="${email_name}@kingsway.ac.ke"
  first_name="Test"
  last_name=$(echo "$role_slug" | sed -e 's/_/ /g' -e 's/.*/\u&/')

  payload=$(cat <<EOF
{"username":"${username}","email":"${email}","password":"testpass","first_name":"${first_name}","last_name":"${last_name}","role_id":${role_id},"status":"active"}
EOF
)

  set +e
  create_resp=$(curl -s -X POST "${API_BASE}" \
    -H 'Content-Type: application/json' \
    -H "Authorization: Bearer ${TOKEN}" \
    -d "${payload}")
  set -e

  user_id=$(echo "$create_resp" | python3 -c "import json,sys; d=json.load(sys.stdin); print(d.get('data', {}).get('id', '') if isinstance(d.get('data'), dict) else '')" 2>/dev/null)

  if [[ -z "$user_id" ]]; then
    log "[FAIL] ${role_name} -> ${username} (create) -> response: ${create_resp}"
    ((failed++))
    continue
  fi

  # Assign role via bulk endpoint to copy permissions
  assign_payload="{\"user_id\":${user_id},\"role_ids\":[${role_id}]}"
  curl -s -X POST "${API_BASE}/roles-bulk-assign-to-user" \
    -H 'Content-Type: application/json' \
    -H "Authorization: Bearer ${TOKEN}" \
    -d "${assign_payload}" >/dev/null

  log "[OK] ${role_name} -> ${username} (id=${user_id})"
  ((created++))
done <<< "$roles"

log "Done. Created: ${created}, Failed: ${failed}"

# Summary: count permissions for admin user
$MYSQL -e "SELECT COUNT(*) AS user_perm_count FROM user_permissions WHERE user_id=2;" $DB_NAME
