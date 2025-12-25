#!/bin/bash

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
BLUE='\033[0;34m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

API_URL="http://localhost/Kingsway/api"

echo -e "${BLUE}=== Cleaning up database ===${NC}"

# Delete all users, roles, and permissions
mysql -u root -padmin123 -e "
USE KingsWayAcademy;
DELETE FROM staff;
DELETE FROM user_permissions;
DELETE FROM user_roles;
DELETE FROM users WHERE id > 1;
DELETE FROM role_permissions;
DELETE FROM roles WHERE id NOT IN (2,3,4,5,6,7,8,9,10,14,16,18,21,23,24,32,33,34,63);
TRUNCATE TABLE role_permissions;
SELECT COUNT(*) as 'Users remaining' FROM users;
SELECT COUNT(*) as 'Staff remaining' FROM staff;
" KingsWayAcademy

echo -e "${GREEN}Database cleaned${NC}\n"

# Test data with various roles to test department mapping
USERS=(
    # Directors/Admin
    '{"first_name":"James","last_name":"Director","email":"director@kwps.edu","username":"director","password":"Pass123!@","phone":"0712345678","role_ids":[3]}'
    
    # Academic leadership
    '{"first_name":"Sarah","last_name":"Headteacher","email":"headteacher@kwps.edu","username":"headteacher","password":"Pass123!@","phone":"0712345679","role_ids":[5]}'
    '{"first_name":"Peter","last_name":"DeputyAcad","email":"deputyacad@kwps.edu","username":"deputyacad","password":"Pass123!@","phone":"0712345680","role_ids":[6]}'
    
    # Teaching staff
    '{"first_name":"Maria","last_name":"ClassTeacher","email":"classteacher@kwps.edu","username":"classteacher","password":"Pass123!@","phone":"0712345681","role_ids":[7]}'
    '{"first_name":"David","last_name":"SubjectTeacher","email":"subjectteacher@kwps.edu","username":"subjectteacher","password":"Pass123!@","phone":"0712345682","role_ids":[8]}'
    '{"first_name":"Lisa","last_name":"InternTeacher","email":"internteacher@kwps.edu","username":"internteacher","password":"Pass123!@","phone":"0712345683","role_ids":[9]}'
    
    # Support staff
    '{"first_name":"John","last_name":"Driver","email":"driver@kwps.edu","username":"driver","password":"Pass123!@","phone":"0712345684","role_ids":[23]}'
    '{"first_name":"Emma","last_name":"Cateress","email":"cateress@kwps.edu","username":"cateress","password":"Pass123!@","phone":"0712345685","role_ids":[16]}'
    '{"first_name":"Robert","last_name":"Chef","email":"kitchenstaff@kwps.edu","username":"kitchenstaff","password":"Pass123!@","phone":"0712345686","role_ids":[32]}'
    
    # Other roles
    '{"first_name":"Paul","last_name":"Accountant","email":"accountant@kwps.edu","username":"accountant","password":"Pass123!@","phone":"0712345687","role_ids":[10]}'
    '{"first_name":"Grace","last_name":"Chaplain","email":"chaplain@kwps.edu","username":"chaplain","password":"Pass123!@","phone":"0712345688","role_ids":[24]}'
    '{"first_name":"Tom","last_name":"SecurityStaff","email":"security@kwps.edu","username":"security","password":"Pass123!@","phone":"0712345689","role_ids":[33]}'
    '{"first_name":"Mike","last_name":"Janitor","email":"janitor@kwps.edu","username":"janitor","password":"Pass123!@","phone":"0712345690","role_ids":[34]}'
    '{"first_name":"Alice","last_name":"TalentDev","email":"talent@kwps.edu","username":"talent","password":"Pass123!@","phone":"0712345691","role_ids":[21]}'
)

echo -e "${BLUE}=== Creating test users ===${NC}\n"

SUCCESS_COUNT=0
FAILURE_COUNT=0

for USER in "${USERS[@]}"; do
    RESPONSE=$(curl -s -X POST "${API_URL}/users" \
        -H "Content-Type: application/json" \
        -H "X-Test-Token: devtest" \
        -d "$USER")
    
    # Extract username from user data
    USERNAME=$(echo "$USER" | grep -o '"username":"[^"]*' | cut -d'"' -f4)
    
    if echo "$RESPONSE" | grep -q '"success":true'; then
        echo -e "${GREEN}✓ Created user: $USERNAME${NC}"
        SUCCESS_COUNT=$((SUCCESS_COUNT + 1))
    else
        echo -e "${RED}✗ Failed to create user: $USERNAME${NC}"
        echo "  Response: $RESPONSE"
        FAILURE_COUNT=$((FAILURE_COUNT + 1))
    fi
done

echo -e "\n${BLUE}=== Summary ===${NC}"
echo -e "Users created: ${GREEN}${SUCCESS_COUNT}${NC}"
echo -e "Users failed: ${RED}${FAILURE_COUNT}${NC}"

echo -e "\n${BLUE}=== Verifying staff table assignments ===${NC}\n"

# Query staff table to verify department mapping
mysql -u root -padmin123 -e "
USE KingsWayAcademy;
SELECT 
    s.staff_no,
    u.first_name,
    u.last_name,
    r.name as role,
    d.dept_name as department,
    st.staff_type_name as staff_type,
    sc.category_name as staff_category
FROM staff s
JOIN users u ON s.user_id = u.id
LEFT JOIN user_roles ur ON u.id = ur.user_id
LEFT JOIN roles r ON ur.role_id = r.id
LEFT JOIN departments d ON s.department_id = d.id
LEFT JOIN staff_types st ON s.staff_type_id = st.id
LEFT JOIN staff_categories sc ON s.staff_category_id = sc.id
ORDER BY s.id;
" KingsWayAcademy

echo -e "\n${GREEN}Test completed!${NC}"
