#!/bin/bash

# Student ID Card System Setup Script
# Run this after fresh installation

set -e  # Exit on error

echo "========================================="
echo "Kingsway Academy - ID Card System Setup"
echo "========================================="
echo ""

# Colors for output
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
RED='\033[0;31m'
NC='\033[0m' # No Color

# Get project root
PROJECT_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
cd "$PROJECT_ROOT"

echo -e "${YELLOW}Step 1: Installing Composer Dependencies${NC}"
if ! command -v composer &> /dev/null; then
    echo -e "${RED}Error: Composer not found. Please install Composer first.${NC}"
    exit 1
fi

composer require endroid/qr-code
echo -e "${GREEN}✓ QR Code library installed${NC}"
echo ""

echo -e "${YELLOW}Step 2: Creating Required Directories${NC}"
mkdir -p images/students
mkdir -p images/qr_codes
mkdir -p templates/id_cards
echo -e "${GREEN}✓ Directories created${NC}"
echo ""

echo -e "${YELLOW}Step 3: Setting Permissions${NC}"
chmod 755 images/students
chmod 755 images/qr_codes
chmod 755 templates/id_cards

# If running as sudo, set proper ownership
if [ "$EUID" -eq 0 ]; then
    WEB_USER=$(ps -ef | egrep '(httpd|apache2|apache|nginx)' | grep -v root | head -n1 | awk '{print $1}')
    if [ -n "$WEB_USER" ]; then
        chown -R $WEB_USER:$WEB_USER images/
        chown -R $WEB_USER:$WEB_USER templates/
        echo -e "${GREEN}✓ Ownership set to $WEB_USER${NC}"
    fi
else
    echo -e "${YELLOW}⚠ Not running as root. You may need to manually set ownership:${NC}"
    echo "  sudo chown -R www-data:www-data images/ templates/"
fi
echo ""

echo -e "${YELLOW}Step 4: Running Database Migration${NC}"
echo "Please enter your MySQL credentials:"
read -p "Database host [localhost]: " DB_HOST
DB_HOST=${DB_HOST:-localhost}
read -p "Database name [KingsWayAcademy]: " DB_NAME
DB_NAME=${DB_NAME:-KingsWayAcademy}
read -p "Database user [root]: " DB_USER
DB_USER=${DB_USER:-root}
read -sp "Database password: " DB_PASS
echo ""

# Test database connection
if mysql -h "$DB_HOST" -u "$DB_USER" -p"$DB_PASS" -e "USE $DB_NAME" 2>/dev/null; then
    echo -e "${GREEN}✓ Database connection successful${NC}"
    
    # Run migration
    mysql -h "$DB_HOST" -u "$DB_USER" -p"$DB_PASS" "$DB_NAME" < database/student_id_card_migration.sql
    echo -e "${GREEN}✓ Database migration completed${NC}"
else
    echo -e "${RED}✗ Database connection failed${NC}"
    echo "Please run the migration manually:"
    echo "  mysql -u $DB_USER -p $DB_NAME < database/student_id_card_migration.sql"
fi
echo ""

echo -e "${YELLOW}Step 5: Verifying PHP Extensions${NC}"
php -m | grep -q gd && echo -e "${GREEN}✓ GD library available${NC}" || echo -e "${RED}✗ GD library missing (required for image processing)${NC}"
php -m | grep -q pdo_mysql && echo -e "${GREEN}✓ PDO MySQL available${NC}" || echo -e "${RED}✗ PDO MySQL missing${NC}"
php -m | grep -q json && echo -e "${GREEN}✓ JSON extension available${NC}" || echo -e "${RED}✗ JSON extension missing${NC}"
echo ""

echo -e "${YELLOW}Step 6: Checking PHP Configuration${NC}"
MAX_UPLOAD=$(php -r "echo ini_get('upload_max_filesize');")
MAX_POST=$(php -r "echo ini_get('post_max_size');")
echo "  upload_max_filesize: $MAX_UPLOAD"
echo "  post_max_size: $MAX_POST"

# Convert to bytes for comparison (rough check)
if [[ "$MAX_UPLOAD" == *"M"* ]]; then
    MAX_UPLOAD_NUM=${MAX_UPLOAD//M/}
    if [ "$MAX_UPLOAD_NUM" -lt 5 ]; then
        echo -e "${YELLOW}⚠ Warning: upload_max_filesize is less than 5M${NC}"
        echo "  Consider increasing in php.ini"
    fi
fi
echo ""

echo -e "${YELLOW}Step 7: Creating Test Student Photo Directory${NC}"
if [ -f "images/logo.png" ]; then
    echo -e "${GREEN}✓ School logo found${NC}"
else
    echo -e "${YELLOW}⚠ No school logo found at images/logo.png${NC}"
    echo "  Please add your school logo for ID cards"
fi
echo ""

echo "========================================="
echo -e "${GREEN}Setup Complete!${NC}"
echo "========================================="
echo ""
echo "Next Steps:"
echo "1. Access ID card management: /pages/student_id_cards.php"
echo "2. Upload school logo to: /images/logo.png (recommended 200x200px)"
echo "3. Configure school details in database (school_configuration table)"
echo "4. Upload student photos and generate ID cards"
echo ""
echo "Documentation: /docs/STUDENT_ID_CARDS.md"
echo ""
echo -e "${GREEN}Happy ID Card Generating! 🎓${NC}"
