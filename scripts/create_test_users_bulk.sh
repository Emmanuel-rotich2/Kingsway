#!/bin/bash
# Create test users via bulk API endpoint (production-ready approach)
# Usage: TRUNCATE=true ./create_test_users_bulk.sh

set -euo pipefail

API_BASE="http://localhost/Kingsway/api/users"
LOGIN_USER="test_system_admin"
LOGIN_PASS="testpass"
DB_NAME="KingsWayAcademy"
MYSQL="/opt/lampp/bin/mysql -u root -padmin123"

log() { echo "[$(date +%H:%M:%S)] $*"; }

# Optional truncate for clean run
if [[ "${TRUNCATE:-false}" == "true" ]]; then
  log "Truncating users, user_roles, user_permissions..."
  $MYSQL $DB_NAME <<'EOSQL'
SET FOREIGN_KEY_CHECKS = 0;
TRUNCATE TABLE user_permissions;
TRUNCATE TABLE user_roles;
TRUNCATE TABLE users;
SET FOREIGN_KEY_CHECKS = 1;
EOSQL

  # Seed bootstrap admin
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

# Save response to temp file for parsing
echo "$login_resp" > /tmp/login_resp.json

TOKEN=$(python3 - <<'PY'
import json
try:
    with open('/tmp/login_resp.json', 'r') as f:
        d = json.load(f)
    # Handle both wrapped and unwrapped responses
    if 'data' in d and isinstance(d['data'], dict):
        token = d['data'].get('token', '')
    else:
        token = d.get('token', '')
    print(token)
except Exception as e:
    print('')
PY
)

if [[ -z "$TOKEN" ]]; then
  log "ERROR: Failed to obtain token"
  log "Login response saved to /tmp/login_resp.json"
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

# Fetch roles from DB
log "Fetching roles from database..."
roles=$($MYSQL -B -N $DB_NAME -e "SELECT id, name FROM roles ORDER BY id;")

if [[ -z "$roles" ]]; then
  log "ERROR: No roles found" && exit 1
fi

# Build JSON array of users
users_json="["
first=true
while IFS=$'\t' read -r role_id role_name; do
  [[ -z "$role_id" ]] && continue
  
  role_slug=$(echo "$role_name" \
    | tr '[:upper:]' '[:lower:]' \
    | sed -e 's#/#_#g' -e 's/ - /_/g' -e 's/ /_/g' -e 's/[^a-z0-9_]//g')
  username="test_${role_slug}"
  email_name=$(echo "$role_slug" | tr -d '_')
  email="${email_name}@kingsway.ac.ke"
  first_name="Test"
  last_name=$(echo "$role_name" | sed 's/.*/\u&/')

  if [[ "$first" == "false" ]]; then
    users_json+=","
  fi
  first=false

  users_json+=$(cat <<EOF
{"username":"${username}","email":"${email}","password":"testpass","first_name":"${first_name}","last_name":"${last_name}","role_id":${role_id},"status":"active","role_ids":[${role_id}]}
EOF
)
done <<< "$roles"
users_json+="]"

# Build final payload
payload=$(cat <<EOF
{"users":${users_json}}
EOF
)

# Send bulk create request
log "Creating users via bulk endpoint..."
create_resp=$(curl -s -X POST "${API_BASE}/bulk-create" \
  -H 'Content-Type: application/json' \
  -H "Authorization: Bearer ${TOKEN}" \
  -d "${payload}")

# Save response
echo "$create_resp" > /tmp/bulk_create_resp.json

# Parse response
success=$(python3 - <<'PY'
import json
try:
    with open('/tmp/bulk_create_resp.json', 'r') as f:
        d = json.load(f)
    # Handle wrapped response
    if d.get('status') == 'success':
        print('True')
    else:
        print('False')
except Exception:
    print('False')
PY
)

if [[ "$success" == "True" ]]; then
  created_count=$(python3 -c "import json; d=json.load(open('/tmp/bulk_create_resp.json')); print(d.get('data',{}).get('summary',{}).get('created_count',0))")
  failed_count=$(python3 -c "import json; d=json.load(open('/tmp/bulk_create_resp.json')); print(d.get('data',{}).get('summary',{}).get('failed_count',0))")
  log "SUCCESS: Created ${created_count} users, Failed: ${failed_count}"
else
  log "ERROR: Bulk creation failed"
  log "Response saved to /tmp/bulk_create_resp.json"
  exit 1
fi

# Verify permissions were copied
log "Checking permission counts..."
$MYSQL -e "SELECT u.username, COUNT(up.permission_id) AS perm_count FROM users u LEFT JOIN user_permissions up ON u.id = up.user_id GROUP BY u.id ORDER BY u.id LIMIT 10;" $DB_NAME
