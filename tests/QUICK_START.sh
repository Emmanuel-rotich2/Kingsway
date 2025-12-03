#!/bin/bash

# Quick Start Guide for Communications API Tests
# Run this to get started immediately

echo "╔═══════════════════════════════════════════════════════╗"
echo "║   Communications API - Quick Start Guide              ║"
echo "║   Created: December 3, 2025                           ║"
echo "╚═══════════════════════════════════════════════════════╝"
echo ""

PROJECT_DIR="/home/prof_angera/Projects/php_pages/Kingsway"

echo "📁 Project Directory: $PROJECT_DIR"
echo ""

# Check if we're in the right directory
if [ ! -f "$PROJECT_DIR/api/index.php" ]; then
    echo "❌ Error: Project directory not found!"
    echo "Expected: $PROJECT_DIR/api/index.php"
    exit 1
fi

echo "✓ Project directory verified"
echo ""

# Show what's been created
echo "╔═══════════════════════════════════════════════════════╗"
echo "║   TEST FILES CREATED                                  ║"
echo "╚═══════════════════════════════════════════════════════╝"
echo ""

echo "📋 Test Scripts:"
echo "  1. tests/test_endpoints.sh"
echo "     - Bash/cURL endpoint tests (60+ endpoints)"
echo "     - Recommended for comprehensive testing"
echo ""

echo "  2. tests/test_communications_api.php"
echo "     - PHP HTTP endpoint tests"
echo "     - Use when PHP-FPM is running"
echo ""

echo "  3. tests/test_endpoints_direct.php"
echo "     - PHP infrastructure validation"
echo "     - Check database, controllers, config"
echo ""

echo "📚 Documentation:"
echo "  1. tests/ENDPOINT_TESTING_GUIDE.md"
echo "     - Comprehensive testing guide"
echo "     - Setup, troubleshooting, CI/CD"
echo ""

echo "  2. tests/ENDPOINT_VERIFICATION_REPORT.md"
echo "     - Full endpoint verification report"
echo "     - Production readiness checklist"
echo ""

echo "  3. tests/README_TEST_SUITE.md"
echo "     - Quick reference for test suite"
echo ""

echo ""
echo "╔═══════════════════════════════════════════════════════╗"
echo "║   QUICK START (3 COMMANDS)                            ║"
echo "╚═══════════════════════════════════════════════════════╝"
echo ""

echo "STEP 1: Check Infrastructure"
echo "  $ cd $PROJECT_DIR"
echo "  $ php tests/test_endpoints_direct.php"
echo ""

echo "STEP 2: Ensure Services Running"
echo "  $ sudo systemctl start mysql"
echo "  $ sudo systemctl start nginx"
echo "  $ sudo systemctl start php-fpm"
echo ""

echo "STEP 3: Run Endpoint Tests"
echo "  $ cd $PROJECT_DIR"
echo "  $ ./tests/test_endpoints.sh"
echo ""

echo "STEP 4: View Results"
echo "  $ cat tests/endpoint_test_results.log"
echo ""

echo ""
echo "╔═══════════════════════════════════════════════════════╗"
echo "║   COMMAND REFERENCE                                   ║"
echo "╚═══════════════════════════════════════════════════════╝"
echo ""

echo "Infrastructure Check:"
echo "  $ php tests/test_endpoints_direct.php"
echo ""

echo "Run All Endpoint Tests:"
echo "  $ ./tests/test_endpoints.sh"
echo ""

echo "Run PHP HTTP Tests:"
echo "  $ php tests/test_communications_api.php"
echo ""

echo "View Test Results:"
echo "  $ cat tests/endpoint_test_results.log"
echo "  $ tail -50 tests/endpoint_test_results.log"
echo ""

echo "Check PHP-FPM Status:"
echo "  $ systemctl status php-fpm"
echo ""

echo "Check MySQL Status:"
echo "  $ systemctl status mysql"
echo ""

echo "Check Nginx Status:"
echo "  $ systemctl status nginx"
echo ""

echo ""
echo "╔═══════════════════════════════════════════════════════╗"
echo "║   60+ ENDPOINTS TO TEST                               ║"
echo "╚═══════════════════════════════════════════════════════╝"
echo ""

echo "Categories:"
echo "  • SMS & Callbacks (3 endpoints)"
echo "  • Contacts (4 endpoints)"
echo "  • Inbound Messages (4 endpoints)"
echo "  • Message Threads (4 endpoints)"
echo "  • Announcements (4 endpoints)"
echo "  • Internal Requests (4 endpoints)"
echo "  • Parent Messages (4 endpoints)"
echo "  • Staff Forum (4 endpoints)"
echo "  • Staff Requests (4 endpoints)"
echo "  • Communications (4 endpoints)"
echo "  • Attachments (3 endpoints)"
echo "  • Groups (4 endpoints)"
echo "  • Templates (4 endpoints)"
echo "  • Logs (2 endpoints)"
echo "  • Recipients (3 endpoints)"
echo "  • Workflows (3 endpoints)"
echo ""

echo "All with:"
echo "  • Real production payloads"
echo "  • HTTP method validation (GET/POST/PUT/DELETE)"
echo "  • Status code verification"
echo "  • JSON response parsing"
echo ""

echo ""
echo "╔═══════════════════════════════════════════════════════╗"
echo "║   DOCUMENTATION FILES                                 ║"
echo "╚═══════════════════════════════════════════════════════╝"
echo ""

echo "For Complete Setup Instructions:"
echo "  $ cat tests/ENDPOINT_TESTING_GUIDE.md"
echo ""

echo "For Production Readiness Assessment:"
echo "  $ cat tests/ENDPOINT_VERIFICATION_REPORT.md"
echo ""

echo "For Test Summary:"
echo "  $ cat tests/README_TEST_SUITE.md"
echo ""

echo ""
echo "╔═══════════════════════════════════════════════════════╗"
echo "║   TROUBLESHOOTING                                     ║"
echo "╚═══════════════════════════════════════════════════════╝"
echo ""

echo "502 Bad Gateway:"
echo "  → Start PHP-FPM: sudo systemctl start php-fpm"
echo ""

echo "Database Connection Error:"
echo "  → Start MySQL: sudo systemctl start mysql"
echo "  → Check config/config.php credentials"
echo ""

echo "Missing Tables:"
echo "  → Run migrations: php scripts/run_migration.sh"
echo ""

echo "Namespace Errors:"
echo "  → Update autoloader: composer dump-autoload"
echo ""

echo ""
echo "╔═══════════════════════════════════════════════════════╗"
echo "║   ✅ READY TO TEST!                                    ║"
echo "╚═══════════════════════════════════════════════════════╝"
echo ""

echo "Next Steps:"
echo "  1. Run: cd $PROJECT_DIR"
echo "  2. Run: php tests/test_endpoints_direct.php"
echo "  3. Fix any issues found"
echo "  4. Run: ./tests/test_endpoints.sh"
echo "  5. Check: cat tests/endpoint_test_results.log"
echo ""

echo "Questions? See: tests/ENDPOINT_TESTING_GUIDE.md"
echo ""
