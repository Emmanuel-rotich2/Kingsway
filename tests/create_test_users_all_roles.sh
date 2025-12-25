#!/bin/bash

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
BLUE='\033[0;34m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

API_URL="http://localhost/Kingsway/api"
TEST_TOKEN="devtest"

echo -e "${BLUE}=== Creating Test Users for All Roles ===${NC}\n"

# Define users for each role (1 user per role)
# Username must: be 3-30 chars, start with letter, contain only alphanumeric/underscore/hyphen
USERS=(
    # System Admin (id: 2)
    '{"first_name":"Silas","last_name":"Angera","username":"test_sysadmin","email":"angerasilas@gmail.com","password":"Pass123!@","phone":"0700000001","role_ids":[2]}'
    
    # Director (id: 3)
    '{"first_name":"John","last_name":"Director","username":"test_director","email":"john@yahoo.com","password":"Pass123!@","phone":"0700000002","role_ids":[3]}'
    
    # School Administrator (id: 4)
    '{"first_name":"Alice","last_name":"Administrator","username":"test_scholadmin","email":"alice@outlook.com","password":"Pass123!@","phone":"0700000003","role_ids":[4]}'
    
    # Headteacher (id: 5)
    '{"first_name":"Robert","last_name":"Headteacher","username":"test_headteacher","email":"robert@gmail.com","password":"Pass123!@","phone":"0700000004","role_ids":[5]}'
    
    # Deputy Head - Academic (id: 6)
    '{"first_name":"Margaret","last_name":"DeputyAcad","username":"test_deputy_acad","email":"margaret@yahoo.com","password":"Pass123!@","phone":"0700000005","role_ids":[6]}'
    
    # Class Teacher (id: 7)
    '{"first_name":"Michael","last_name":"ClassTeacher","username":"test_classteacher","email":"michael@gmail.com","password":"Pass123!@","phone":"0700000006","role_ids":[7]}'
    
    # Subject Teacher (id: 8)
    '{"first_name":"Patricia","last_name":"SubjectTeacher","username":"test_subjectteacher","email":"patricia@gmail.com","password":"Pass123!@","phone":"0700000007","role_ids":[8]}'
    
    # Intern/Student Teacher (id: 9)
    '{"first_name":"David","last_name":"InternTeacher","username":"test_internteacher","email":"david@gmail.com","password":"Pass123!@","phone":"0700000008","role_ids":[9]}'
    
    # Accountant (id: 10)
    '{"first_name":"Jennifer","last_name":"Accountant","username":"test_accountant","email":"jenifer@gmail.com","password":"Pass123!@","phone":"0700000009","role_ids":[10]}'
    
    # Inventory Manager (id: 14)
    '{"first_name":"Christopher","last_name":"InventoryMgr","username":"test_inventorymgr","email":"christopher@gmail.com","password":"Pass123!@","phone":"0700000010","role_ids":[14]}'
    
    # Cateress (id: 16)
    '{"first_name":"Susan","last_name":"Cateress","username":"test_cateress","email":"susan@gmail.com","password":"Pass123!@","phone":"0700000011","role_ids":[16]}'
    
    # Boarding Master (id: 18)
    '{"first_name":"Thomas","last_name":"BoardingMaster","username":"test_boardingmaster","email":"thomas@gmail.com","password":"Pass123!@","phone":"0700000012","role_ids":[18]}'
    
    # Talent Development (id: 21)
    '{"first_name":"Linda","last_name":"TalentDev","username":"test_talentdev","email":"linda@gmail.com","password":"Pass123!@","phone":"0700000013","role_ids":[21]}'
    
    # Driver (id: 23)
    '{"first_name":"Daniel","last_name":"Driver","username":"test_driver","email":"daniel@gmail.com","password":"Pass123!@","phone":"0700000014","role_ids":[23]}'
    
    # Chaplain (id: 24)
    '{"first_name":"Elizabeth","last_name":"Chaplain","username":"test_chaplain","email":"elizabeth@outlook.com","password":"Pass123!@","phone":"0700000015","role_ids":[24]}'
    
    # Kitchen Staff (id: 32)
    '{"first_name":"James","last_name":"KitchenStaff","username":"test_kitchenstaff","email":"james@yahoo.com","password":"Pass123!@","phone":"0700000016","role_ids":[32]}'
    
    # Security Staff (id: 33)
    '{"first_name":"Joseph","last_name":"SecurityStaff","username":"test_securitystaff","email":"joseph@outlook.com","password":"Pass123!@","phone":"0700000017","role_ids":[33]}'
    
    # Janitor (id: 34)
    '{"first_name":"Mary","last_name":"Janitor","username":"test_janitor","email":"mary@gmail.com","password":"Pass123!@","phone":"0700000018","role_ids":[34]}'
    
    # Deputy Head - Discipline (id: 63)
    '{"first_name":"William","last_name":"DeputyDisc","username":"test_deputy_disc","email":"william@outlook.com","password":"Pass123!@","phone":"0700000019","role_ids":[63]}'
)

SUCCESS_COUNT=0
FAILURE_COUNT=0

for USER in "${USERS[@]}"; do
    RESPONSE=$(curl -s -X POST "${API_URL}/users" \
        -H "Content-Type: application/json" \
        -H "X-Test-Token: ${TEST_TOKEN}" \
        -d "$USER")
    
    # Extract details from user data
    email=$(echo "$USER" | grep -o '"email":"[^"]*' | cut -d'"' -f4)
    username=$(echo "$USER" | grep -o '"username":"[^"]*' | cut -d'"' -f4)
    ROLE_ID=$(echo "$USER" | grep -o '"role_ids":\[[0-9]*' | grep -o '[0-9]*$')
    
    if echo "$RESPONSE" | grep -q '"status":"success"'; then
        USER_ID=$(echo "$RESPONSE" | grep -o '"user_id":[0-9]*' | head -1 | cut -d':' -f2)
        if [ -z "$USER_ID" ]; then
            USER_ID=$(echo "$RESPONSE" | grep -o '"id":[0-9]*' | head -1 | cut -d':' -f2)
        fi
        echo -e "${GREEN}✓ Role ${ROLE_ID}: ${username} (${email}) - ID: ${USER_ID}${NC}"
        SUCCESS_COUNT=$((SUCCESS_COUNT + 1))
    else
        ERROR=$(echo "$RESPONSE" | grep -o '"message":"[^"]*' | head -1 | cut -d'"' -f4)
        if [ -z "$ERROR" ]; then
            ERROR=$(echo "$RESPONSE" | grep -o '"error":"[^"]*' | head -1 | cut -d'"' -f4)
        fi
        echo -e "${RED}✗ Role ${ROLE_ID}: ${username} (${email}) - ${ERROR}${NC}"
        FAILURE_COUNT=$((FAILURE_COUNT + 1))
    fi
done

echo -e "\n${BLUE}=== Summary ===${NC}"
echo -e "Users created: ${GREEN}${SUCCESS_COUNT}${NC}/19"
echo -e "Users failed: ${RED}${FAILURE_COUNT}${NC}"

echo -e "\n${BLUE}=== Verifying Created Users ===${NC}\n"

# Query to show created users with their roles and departments
/opt/lampp/bin/mysql -u root -padmin123 -e "
USE KingsWayAcademy;
SELECT 
    u.id,
    u.email,
    r.name as role,
    d.name as department,
    st.name as staff_type,
    sc.category_name as staff_category,
    COALESCE(s.staff_no, 'N/A') as staff_no
FROM users u
LEFT JOIN user_roles ur ON u.id = ur.user_id
LEFT JOIN roles r ON ur.role_id = r.id
LEFT JOIN staff s ON u.id = s.user_id
LEFT JOIN departments d ON s.department_id = d.id
LEFT JOIN staff_types st ON s.staff_type_id = st.id
LEFT JOIN staff_categories sc ON s.staff_category_id = sc.id
ORDER BY u.id;
" KingsWayAcademy

echo -e "\n${GREEN}Test completed!${NC}"
