#!/bin/bash

# Compare duplicate implementations to see what functionality differs

cd /home/prof_angera/Projects/php_pages/Kingsway/js/pages

echo "=========================================="
echo "COMPARING DUPLICATE FILES"
echo "=========================================="
echo ""

# Compare students.js vs students-management.js
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo "1. STUDENTS MODULE COMPARISON"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo ""
echo "📋 students.js (12KB - CURRENTLY USED):"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
head -50 students.js | grep -E "^\s*(class|function|const|async|\/\/)" | head -10
echo ""
echo "📋 students-management.js (19KB - REDUNDANT):"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
head -50 students-management.js | grep -E "^\s*(class|function|const|async|\/\/)" | head -10
echo ""
echo "Differences:"
echo "  - students.js: $(grep -E '^\s*(class|function|async)' students.js | wc -l) methods/classes"
echo "  - students-management.js: $(grep -E '^\s*(class|function|async)' students-management.js | wc -l) methods/classes"
echo ""
echo "Recommendation: CHECK FOR UNIQUE METHODS IN students-management.js"
echo ""

# Compare staff.js vs staff-management.js
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo "2. STAFF MODULE COMPARISON"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo ""
echo "📋 staff.js (6.6KB - CURRENTLY USED):"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
head -50 staff.js | grep -E "^\s*(class|function|const|async|\/\/)" | head -10
echo ""
echo "📋 staff-management.js (21KB - REDUNDANT):"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
head -50 staff-management.js | grep -E "^\s*(class|function|const|async|\/\/)" | head -10
echo ""
echo "Differences:"
echo "  - staff.js: $(grep -E '^\s*(class|function|async)' staff.js | wc -l) methods/classes"
echo "  - staff-management.js: $(grep -E '^\s*(class|function|async)' staff-management.js | wc -l) methods/classes"
echo ""
echo "Recommendation: CHECK FOR UNIQUE METHODS IN staff-management.js"
echo ""

echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo "NEXT: Manual Code Review Required"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo ""
echo "To compare methods in detail:"
echo "  grep -E '^\\s*(class|async|function|const \\w+ = (function|async))' students.js"
echo "  grep -E '^\\s*(class|async|function|const \\w+ = (function|async))' students-management.js"
echo ""
echo "Then manually merge unique methods from the 19KB file into 12KB file"
