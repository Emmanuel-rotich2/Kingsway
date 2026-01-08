<?php
/**
 * Comprehensive Director Dashboard API Test
 * Tests all backend services directly (bypassing auth for testing)
 */
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config/config.php';

use App\API\Services\DirectorAnalyticsService;

echo "╔══════════════════════════════════════════════════════════════╗\n";
echo "║        DIRECTOR DASHBOARD API VERIFICATION TEST             ║\n";
echo "╚══════════════════════════════════════════════════════════════╝\n\n";

$service = new DirectorAnalyticsService();
$allPassed = true;

// Test 1: Attendance Trends
echo "1️⃣  Testing getAttendanceTrends()...\n";
try {
    $result = $service->getAttendanceTrends();
    $hasData = isset($result['data']) && is_array($result['data']);
    $hasAbsentStudents = isset($result['absent_students']) && is_array($result['absent_students']);
    $hasAbsentStaff = isset($result['absent_staff']) && is_array($result['absent_staff']);
    $hasSummary = isset($result['summary']);
    
    if ($hasData && $hasAbsentStudents && $hasAbsentStaff && $hasSummary) {
        echo "   ✅ PASS: Returned " . count($result['data']) . " trend days, ";
        echo count($result['absent_students']) . " absent students, ";
        echo count($result['absent_staff']) . " absent staff\n";
    } else {
        echo "   ❌ FAIL: Missing required data keys\n";
        $allPassed = false;
    }
} catch (Exception $e) {
    echo "   ❌ ERROR: " . $e->getMessage() . "\n";
    $allPassed = false;
}

// Test 2: Announcements
echo "\n2️⃣  Testing getLatestAnnouncements()...\n";
try {
    $result = $service->getLatestAnnouncements();
    $hasAnnouncements = isset($result['announcements']) && is_array($result['announcements']);
    $hasExpiring = isset($result['expiring_notices']) && is_array($result['expiring_notices']);
    
    if ($hasAnnouncements && $hasExpiring) {
        echo "   ✅ PASS: Returned " . count($result['announcements']) . " announcements, ";
        echo count($result['expiring_notices']) . " expiring notices\n";
    } else {
        echo "   ❌ FAIL: Missing required data keys\n";
        $allPassed = false;
    }
} catch (Exception $e) {
    echo "   ❌ ERROR: " . $e->getMessage() . "\n";
    $allPassed = false;
}

// Test 3: Operational Risks
echo "\n3️⃣  Testing getOperationalRisks()...\n";
try {
    $result = $service->getOperationalRisks();
    $hasAdmissions = isset($result['admissions_queue']);
    $hasDiscipline = isset($result['discipline_summary']);
    $hasAudit = isset($result['audit_logs']);
    
    if ($hasAdmissions && $hasDiscipline && $hasAudit) {
        echo "   ✅ PASS: Returned ";
        echo count($result['admissions_queue']) . " admissions, ";
        echo count($result['discipline_summary']) . " discipline cases, ";
        echo count($result['audit_logs']) . " audit logs\n";
    } else {
        echo "   ❌ FAIL: Missing required data keys\n";
        $allPassed = false;
    }
} catch (Exception $e) {
    echo "   ❌ ERROR: " . $e->getMessage() . "\n";
    $allPassed = false;
}

// Test 4: Enrollment Stats
echo "\n4️⃣  Testing getEnrollmentStats()...\n";
try {
    $result = $service->getEnrollmentStats();
    if (isset($result['total'])) {
        echo "   ✅ PASS: Total students = " . $result['total'] . " (Male: " . $result['male'] . ", Female: " . $result['female'] . ")\n";
    } else {
        echo "   ❌ FAIL: Missing total count\n";
        $allPassed = false;
    }
} catch (Exception $e) {
    echo "   ❌ ERROR: " . $e->getMessage() . "\n";
    $allPassed = false;
}

// Test 5: Staff Stats
echo "\n5️⃣  Testing getStaffStats()...\n";
try {
    $result = $service->getStaffStats();
    if (isset($result['total'])) {
        echo "   ✅ PASS: Total staff = " . $result['total'] . "\n";
    } else {
        echo "   ❌ FAIL: Missing total count\n";
        $allPassed = false;
    }
} catch (Exception $e) {
    echo "   ❌ ERROR: " . $e->getMessage() . "\n";
    $allPassed = false;
}

echo "\n╔══════════════════════════════════════════════════════════════╗\n";
if ($allPassed) {
    echo "║                  ✅ ALL TESTS PASSED                        ║\n";
} else {
    echo "║                  ❌ SOME TESTS FAILED                       ║\n";
}
echo "╚══════════════════════════════════════════════════════════════╝\n";
