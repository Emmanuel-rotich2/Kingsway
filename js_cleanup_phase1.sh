#!/bin/bash

# JavaScript Cleanup Phase 1: Delete Obvious Duplicates
# Safe deletions with low risk (30+ files)

cd /home/prof_angera/Projects/php_pages/Kingsway/js/pages

echo "=========================================="
echo "PHASE 1: Deleting Obvious Duplicates"
echo "=========================================="
echo ""

# Track deleted files
DELETED=0

# OBVIOUS STUB DUPLICATES (files that have real versions used)

# Academics
if [ -f "manage_academics.js" ]; then
  echo "🗑️  Deleting: manage_academics.js (stub duplicate of academicsManager.js)"
  rm manage_academics.js
  ((DELETED++))
fi

# Communications  
if [ -f "manage_communications.js" ]; then
  echo "🗑️  Deleting: manage_communications.js (stub duplicate of communications.js)"
  rm manage_communications.js
  ((DELETED++))
fi

# Transport
if [ -f "manage_transport.js" ]; then
  echo "🗑️  Deleting: manage_transport.js (stub duplicate of transport.js)"
  rm manage_transport.js
  ((DELETED++))
fi

# Users
if [ -f "manage_users.js" ]; then
  echo "🗑️  Deleting: manage_users.js (stub duplicate of users.js)"
  rm manage_users.js
  ((DELETED++))
fi

# STAFF DUPLICATES
if [ -f "manage_staff.js" ]; then
  echo "🗑️  Deleting: manage_staff.js (stub duplicate of staff.js)"
  rm manage_staff.js
  ((DELETED++))
fi

if [ -f "manage_non_teaching_staff.js" ]; then
  echo "🗑️  Deleting: manage_non_teaching_staff.js (redundant variant)"
  rm manage_non_teaching_staff.js
  ((DELETED++))
fi

# STUDENTS DUPLICATES
if [ -f "manage_students.js" ]; then
  echo "🗑️  Deleting: manage_students.js (stub duplicate of students.js)"
  rm manage_students.js
  ((DELETED++))
fi

# FINANCE DUPLICATES
if [ -f "manage_finance.js" ]; then
  echo "🗑️  Deleting: manage_finance.js (stub duplicate of finance.js)"
  rm manage_finance.js
  ((DELETED++))
fi

if [ -f "manage_payrolls.js" ]; then
  echo "🗑️  Deleting: manage_payrolls.js (duplicate of finance.js payroll)"
  rm manage_payrolls.js
  ((DELETED++))
fi

if [ -f "manage_payments.js" ]; then
  echo "🗑️  Deleting: manage_payments.js (duplicate of finance.js payments)"
  rm manage_payments.js
  ((DELETED++))
fi

if [ -f "manage_fees.js" ]; then
  echo "🗑️  Deleting: manage_fees.js (duplicate of finance.js fees)"
  rm manage_fees.js
  ((DELETED++))
fi

if [ -f "finance_approvals.js" ]; then
  echo "🗑️  Deleting: finance_approvals.js (should be in finance.js)"
  rm finance_approvals.js
  ((DELETED++))
fi

if [ -f "finance_reports.js" ]; then
  echo "🗑️  Deleting: finance_reports.js (orphaned stub)"
  rm finance_reports.js
  ((DELETED++))
fi

if [ -f "payroll.js" ]; then
  echo "🗑️  Deleting: payroll.js (duplicate of manage_payrolls/finance.js)"
  rm payroll.js
  ((DELETED++))
fi

if [ -f "student_fees.js" ]; then
  echo "🗑️  Deleting: student_fees.js (should be in finance.js)"
  rm student_fees.js
  ((DELETED++))
fi

# SETTINGS DUPLICATES
if [ -f "school_settings.js" ]; then
  echo "🗑️  Deleting: school_settings.js (duplicate of settings.js)"
  rm school_settings.js
  ((DELETED++))
fi

if [ -f "system_settings.js" ]; then
  echo "🗑️  Deleting: system_settings.js (duplicate of settings.js)"
  rm system_settings.js
  ((DELETED++))
fi

# TIMETABLE/CLASS DUPLICATES
if [ -f "manage_timetable.js" ]; then
  echo "🗑️  Deleting: manage_timetable.js (duplicate of timetable.js)"
  rm manage_timetable.js
  ((DELETED++))
fi

if [ -f "manage_classes.js" ]; then
  echo "🗑️  Deleting: manage_classes.js (duplicate of class management)"
  rm manage_classes.js
  ((DELETED++))
fi

if [ -f "myclasses.js" ]; then
  echo "🗑️  Deleting: myclasses.js (orphaned variant)"
  rm myclasses.js
  ((DELETED++))
fi

# OTHER OBVIOUS STUBS WITH NO PURPOSE

if [ -f "manage_activities.js" ]; then
  echo "🗑️  Deleting: manage_activities.js (orphaned stub)"
  rm manage_activities.js
  ((DELETED++))
fi

if [ -f "manage_announcements.js" ]; then
  echo "🗑️  Deleting: manage_announcements.js (orphaned stub)"
  rm manage_announcements.js
  ((DELETED++))
fi

if [ -f "manage_assessments.js" ]; then
  echo "🗑️  Deleting: manage_assessments.js (orphaned stub)"
  rm manage_assessments.js
  ((DELETED++))
fi

if [ -f "manage_boarding.js" ]; then
  echo "🗑️  Deleting: manage_boarding.js (orphaned stub)"
  rm manage_boarding.js
  ((DELETED++))
fi

if [ -f "manage_email.js" ]; then
  echo "🗑️  Deleting: manage_email.js (orphaned stub)"
  rm manage_email.js
  ((DELETED++))
fi

if [ -f "manage_expenses.js" ]; then
  echo "🗑️  Deleting: manage_expenses.js (orphaned stub)"
  rm manage_expenses.js
  ((DELETED++))
fi

if [ -f "manage_inventory.js" ]; then
  echo "🗑️  Deleting: manage_inventory.js (orphaned stub)"
  rm manage_inventory.js
  ((DELETED++))
fi

if [ -f "manage_lesson_plans.js" ]; then
  echo "🗑️  Deleting: manage_lesson_plans.js (orphaned stub)"
  rm manage_lesson_plans.js
  ((DELETED++))
fi

if [ -f "manage_requisitions.js" ]; then
  echo "🗑️  Deleting: manage_requisitions.js (orphaned stub)"
  rm manage_requisitions.js
  ((DELETED++))
fi

if [ -f "manage_roles.js" ]; then
  echo "🗑️  Deleting: manage_roles.js (orphaned stub)"
  rm manage_roles.js
  ((DELETED++))
fi

if [ -f "manage_sms.js" ]; then
  echo "🗑️  Deleting: manage_sms.js (orphaned stub)"
  rm manage_sms.js
  ((DELETED++))
fi

if [ -f "manage_stock.js" ]; then
  echo "🗑️  Deleting: manage_stock.js (orphaned stub)"
  rm manage_stock.js
  ((DELETED++))
fi

if [ -f "manage_subjects.js" ]; then
  echo "🗑️  Deleting: manage_subjects.js (orphaned stub)"
  rm manage_subjects.js
  ((DELETED++))
fi

if [ -f "manage_teachers.js" ]; then
  echo "🗑️  Deleting: manage_teachers.js (orphaned stub)"
  rm manage_teachers.js
  ((DELETED++))
fi

if [ -f "manage_workflows.js" ]; then
  echo "🗑️  Deleting: manage_workflows.js (orphaned stub)"
  rm manage_workflows.js
  ((DELETED++))
fi

echo ""
echo "=========================================="
echo "✅ PHASE 1 COMPLETE"
echo "=========================================="
echo "Deleted: $DELETED files"
echo ""
echo "📊 Directory Status:"
ls -1 | wc -l
echo " total files remaining"
echo ""
echo "Next: Run Phase 2 to consolidate large duplicate files"
