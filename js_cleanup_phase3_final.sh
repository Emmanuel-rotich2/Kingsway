#!/bin/bash

# Phase 3: Final Consolidation
# Remove remaining duplicate/orphaned files

cd /home/prof_angera/Projects/php_pages/Kingsway/js/pages

echo "=========================================="
echo "PHASE 3: FINAL CONSOLIDATION"
echo "=========================================="
echo ""

DELETED=0

# REMOVE LARGE DUPLICATES THAT AREN'T USED
# (students-management.js: 19KB - not referenced in PHP)
if [ -f "students-management.js" ]; then
    echo "🗑️  Deleting: students-management.js (19KB - not referenced in pages)"
    rm students-management.js
    ((DELETED++))
fi

# (staff-management.js: 21KB - not referenced in PHP)
if [ -f "staff-management.js" ]; then
    echo "🗑️  Deleting: staff-management.js (21KB - not referenced in pages)"
    rm staff-management.js
    ((DELETED++))
fi

# AUTO-GENERATED API REGISTRY (65KB - auto-generated, not maintained)
if [ -f "api_usage_registry.js" ]; then
    echo "🗑️  Deleting: api_usage_registry.js (65KB - auto-generated, orphaned)"
    rm api_usage_registry.js
    ((DELETED++))
fi

# ORPHANED MULTI-CONTROLLER HUB
if [ -f "manage-controllers.js" ]; then
    echo "🗑️  Deleting: manage-controllers.js (13KB - orphaned, redundant)"
    rm manage-controllers.js
    ((DELETED++))
fi

# ORPHANED SMALL FEATURES (No corresponding PHP pages using them)
ORPHANED_FILES=(
    "activities.js"
    "admissions.js"
    "assessments.js"
    "attendance.js"
    "lesson_plans.js"
    "timetable.js"
    "workflows.js"
)

for file in "${ORPHANED_FILES[@]}"; do
    if [ -f "$file" ]; then
        echo "🗑️  Deleting: $file (orphaned, no active usage)"
        rm "$file"
        ((DELETED++))
    fi
done

echo ""
echo "=========================================="
echo "✅ PHASE 3 COMPLETE - FINAL CLEANUP DONE"
echo "=========================================="
echo "Files deleted: $DELETED"
echo ""
echo "📊 FINAL DIRECTORY STATE:"
REMAINING=$(ls -1 | wc -l)
echo "Total files: $REMAINING"
echo ""
echo "Remaining files:"
ls -1 | sort
echo ""
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo "✅ Reconciliation Complete!"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo ""
echo "Summary:"
echo "  Before: 83 files (~1.2MB)"
echo "  After:  $REMAINING files"
echo "  Reduction: $((83-REMAINING)) files deleted"
echo ""
echo "Next Steps:"
echo "  1. Run system tests to verify all pages work"
echo "  2. Check browser console for any errors"
echo "  3. Test all dashboard functionality"
echo "  4. Commit cleanup to git"
