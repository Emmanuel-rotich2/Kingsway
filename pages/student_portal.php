<?php
/**
 * Student Portal - Role-based Student Information Display
 * 
 * This page displays student information based on the user's role:
 * - Parents: See all details
 * - Admin/Director/Headteacher/Deputy: See all details
 * - Teachers: See academic details
 * - Security Personnel: See authorization details (name, class, etc.)
 * - Drivers: See transportation details
 * - Accountant: See financial and academic details
 * - Other staff: See details related to their department
 */

// Load Config for BASE_URL
require_once __DIR__ . '/../config/Config.php';
\App\Config\Config::init();

// Simple authentication check - in production, use proper JWT validation
session_start();

// Check if user is logged in via session
if (!isset($_SESSION['user']) && !isset($_SERVER['auth_user'])) {
    // For QR code scanning, we need to allow public access but with limited info
    // For now, we'll show basic info to anyone, but sensitive info requires auth
    $isAuthenticated = false;
} else {
    $isAuthenticated = true;
    $currentUser = $_SESSION['user'] ?? $_SERVER['auth_user'];
}

// Get student ID from URL
$studentId = isset($_GET['student_id']) ? (int)$_GET['student_id'] : 0;

if (!$studentId) {
    header('HTTP/1.0 404 Not Found');
    echo 'Student not found';
    exit;
}

// Get database connection
require_once __DIR__ . '/database/Database.php';
$db = \App\Database\Database::getInstance()->getConnection();

// Get student details
$stmt = $db->prepare("
    SELECT 
        s.*,
        c.name as class_name,
        cs.stream_name,
        YEAR(s.admission_date) as year_joined,
        (YEAR(s.admission_date) + c.level) as expected_graduation_year
    FROM students s
    LEFT JOIN class_streams cs ON s.stream_id = cs.id
    LEFT JOIN classes c ON cs.class_id = c.id
    WHERE s.id = ?
");
$stmt->execute([$studentId]);
$student = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$student) {
    header('HTTP/1.0 404 Not Found');
    echo 'Student not found';
    exit;
}

// Get current user and role
$currentUser = $isAuthenticated ? ($currentUser ?? null) : null;
$userRole = $currentUser['role'] ?? 'guest';
$userDepartment = $currentUser['department'] ?? '';

// For public access (QR scanning), show only basic info
if (!$isAuthenticated) {
    $userRole = 'public';
}

// Define what each role can see
$rolePermissions = [
    'public' => ['basic'], // QR scanners see only basic info
    'parent' => ['all'],
    'admin' => ['all'],
    'director' => ['all'],
    'headteacher' => ['all'],
    'deputy_headteacher' => ['all'],
    'teacher' => ['academic', 'basic'],
    'security' => ['authorization', 'basic'],
    'driver' => ['transportation', 'basic'],
    'accountant' => ['financial', 'academic', 'basic'],
    'finance_manager' => ['financial', 'academic', 'basic'],
    'transport_manager' => ['transportation', 'basic'],
    'medical_staff' => ['medical', 'basic'],
    'sports_coordinator' => ['sports', 'basic'],
    'librarian' => ['library', 'basic'],
    'guidance_counselor' => ['guidance', 'basic'],
];

// Determine which sections to show based on user role
$allowedSections = $rolePermissions[$userRole] ?? ['basic'];

// Helper function to check if section should be shown
function canShowSection($section, $allowedSections) {
    return in_array('all', $allowedSections) || in_array($section, $allowedSections);
}

// Get additional student data based on sections
$additionalData = [];

if (canShowSection('financial', $allowedSections)) {
    // Get financial data
    $stmt = $db->prepare("
        SELECT * FROM student_fees 
        WHERE student_id = ? 
        ORDER BY academic_year DESC, term DESC
        LIMIT 5
    ");
    $stmt->execute([$studentId]);
    $additionalData['fees'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

if (canShowSection('transportation', $allowedSections)) {
    // Get transportation data
    $stmt = $db->prepare("
        SELECT * FROM student_transport 
        WHERE student_id = ? 
        AND status = 'active'
    ");
    $stmt->execute([$studentId]);
    $additionalData['transport'] = $stmt->fetch(PDO::FETCH_ASSOC);
}

if (canShowSection('medical', $allowedSections)) {
    // Get medical data
    $stmt = $db->prepare("
        SELECT * FROM student_medical 
        WHERE student_id = ? 
        ORDER BY date DESC
        LIMIT 5
    ");
    $stmt->execute([$studentId]);
    $additionalData['medical'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

if (canShowSection('sports', $allowedSections)) {
    // Get sports data
    $stmt = $db->prepare("
        SELECT * FROM student_sports 
        WHERE student_id = ? 
        AND status = 'active'
    ");
    $stmt->execute([$studentId]);
    $additionalData['sports'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

if (canShowSection('library', $allowedSections)) {
    // Get library data
    $stmt = $db->prepare("
        SELECT * FROM student_library 
        WHERE student_id = ? 
        ORDER BY due_date ASC
        LIMIT 5
    ");
    $stmt->execute([$studentId]);
    $additionalData['library'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

if (canShowSection('guidance', $allowedSections)) {
    // Get guidance/counseling data
    $stmt = $db->prepare("
        SELECT * FROM student_guidance 
        WHERE student_id = ? 
        ORDER BY date DESC
        LIMIT 5
    ");
    $stmt->execute([$studentId]);
    $additionalData['guidance'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

if (canShowSection('academic', $allowedSections)) {
    // Get academic performance
    $stmt = $db->prepare("
        SELECT * FROM student_performance 
        WHERE student_id = ? 
        ORDER BY academic_year DESC, term DESC
        LIMIT 5
    ");
    $stmt->execute([$studentId]);
    $additionalData['academic'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Get authorization/security data (for security personnel)
if (canShowSection('authorization', $allowedSections)) {
    $stmt = $db->prepare("
        SELECT * FROM student_id_cards 
        WHERE student_id = ? 
        AND status IN ('issued', 'printed')
        ORDER BY issue_date DESC
        LIMIT 1
    ");
    $stmt->execute([$studentId]);
    $additionalData['id_card'] = $stmt->fetch(PDO::FETCH_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Portal - <?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?></title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            background: #f5f5f5;
            padding: 20px;
        }
        .student-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            border-radius: 15px;
            margin-bottom: 30px;
        }
        .student-photo {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            border: 4px solid white;
            object-fit: cover;
        }
        .info-card {
            background: white;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .info-card h4 {
            color: #667eea;
            margin-bottom: 15px;
            border-bottom: 2px solid #667eea;
            padding-bottom: 10px;
        }
        .info-row {
            display: flex;
            padding: 8px 0;
            border-bottom: 1px solid #eee;
        }
        .info-label {
            font-weight: 600;
            width: 150px;
            color: #555;
        }
        .info-value {
            color: #333;
            flex: 1;
        }
        .role-badge {
            background: rgba(255,255,255,0.2);
            padding: 5px 15px;
            border-radius: 20px;
            font-size: 14px;
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Header -->
        <div class="student-header">
            <div class="row align-items-center">
                <div class="col-md-2 text-center">
                    <?php if (!empty($student['photo_url'])): ?>
                        <img src="<?php echo htmlspecialchars($student['photo_url']); ?>" 
                             alt="Student Photo" 
                             class="student-photo"
                             onerror="this.src=KingswayFileLifecycle.assetUrl('students', 'avatar.jpg')">
                    <?php else: ?>
                        <img src=KingswayFileLifecycle.assetUrl('students', 'avatar.jpg') 
                             alt="Student Photo" 
                             class="student-photo">
                    <?php endif; ?>
                </div>
                <div class="col-md-10">
                    <h2><?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?></h2>
                    <p class="mb-2">
                        <strong>Admission No:</strong> <?php echo htmlspecialchars($student['admission_no']); ?> |
                        <strong>Class:</strong> <?php echo htmlspecialchars($student['class_name'] . ' - ' . $student['stream_name']); ?>
                    </p>
                    <p class="mb-0">
                        <?php if ($isAuthenticated): ?>
                            <span class="role-badge">
                                <i class="fas fa-user-shield"></i> Viewing as: <?php echo ucfirst($userRole); ?>
                            </span>
                        <?php else: ?>
                            <span class="role-badge">
                                <i class="fas fa-qrcode"></i> Public View - <a href="/index.php" style="color: white;">Login for full details</a>
                            </span>
                        <?php endif; ?>
                    </p>
                </div>
            </div>
        </div>

        <!-- Basic Information (always shown) -->
        <div class="info-card">
            <h4><i class="fas fa-user"></i> Basic Information</h4>
            <div class="info-row">
                <div class="info-label">Full Name:</div>
                <div class="info-value"><?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?></div>
            </div>
            <div class="info-row">
                <div class="info-label">Admission No:</div>
                <div class="info-value"><?php echo htmlspecialchars($student['admission_no']); ?></div>
            </div>
            <div class="info-row">
                <div class="info-label">Class:</div>
                <div class="info-value"><?php echo htmlspecialchars($student['class_name'] . ' - ' . $student['stream_name']); ?></div>
            </div>
            <div class="info-row">
                <div class="info-label">Gender:</div>
                <div class="info-value"><?php echo htmlspecialchars($student['gender']); ?></div>
            </div>
            <div class="info-row">
                <div class="info-label">Date of Birth:</div>
                <div class="info-value"><?php echo htmlspecialchars($student['date_of_birth']); ?></div>
            </div>
            <div class="info-row">
                <div class="info-label">Status:</div>
                <div class="info-value">
                    <span class="badge bg-<?php echo $student['status'] === 'active' ? 'success' : 'warning'; ?>">
                        <?php echo ucfirst($student['status']); ?>
                    </span>
                </div>
            </div>
        </div>

        <!-- Academic Information (for teachers, accountants, etc.) -->
        <?php if (canShowSection('academic', $allowedSections)): ?>
        <div class="info-card">
            <h4><i class="fas fa-graduation-cap"></i> Academic Information</h4>
            <div class="info-row">
                <div class="info-label">Year Joined:</div>
                <div class="info-value"><?php echo htmlspecialchars($student['year_joined']); ?></div>
            </div>
            <div class="info-row">
                <div class="info-label">Expected Graduation:</div>
                <div class="info-value"><?php echo htmlspecialchars($student['expected_graduation_year']); ?></div>
            </div>
            <?php if (!empty($additionalData['academic'])): ?>
                <h5 class="mt-3">Recent Performance</h5>
                <?php foreach ($additionalData['academic'] as $perf): ?>
                    <div class="info-row">
                        <div class="info-label"><?php echo htmlspecialchars($perf['term'] . ' ' . $perf['academic_year']); ?>:</div>
                        <div class="info-value"><?php echo htmlspecialchars($perf['grade'] ?? 'N/A'); ?></div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <!-- Financial Information (for accountants, finance managers) -->
        <?php if (canShowSection('financial', $allowedSections)): ?>
        <div class="info-card">
            <h4><i class="fas fa-money-bill-wave"></i> Financial Information</h4>
            <?php if (!empty($additionalData['fees'])): ?>
                <?php foreach ($additionalData['fees'] as $fee): ?>
                    <div class="info-row">
                        <div class="info-label"><?php echo htmlspecialchars($fee['term'] . ' ' . $fee['academic_year']); ?>:</div>
                        <div class="info-value">
                            <?php echo htmlspecialchars($fee['amount'] ?? '0'); ?> - 
                            <span class="badge bg-<?php echo ($fee['status'] ?? 'pending') === 'paid' ? 'success' : 'warning'; ?>">
                                <?php echo ucfirst($fee['status'] ?? 'pending'); ?>
                            </span>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <p class="text-muted">No financial records found.</p>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <!-- Transportation Information (for drivers, transport managers) -->
        <?php if (canShowSection('transportation', $allowedSections)): ?>
        <div class="info-card">
            <h4><i class="fas fa-bus"></i> Transportation Information</h4>
            <?php if (!empty($additionalData['transport'])): ?>
                <div class="info-row">
                    <div class="info-label">Route:</div>
                    <div class="info-value"><?php echo htmlspecialchars($additionalData['transport']['route_name'] ?? 'N/A'); ?></div>
                </div>
                <div class="info-row">
                    <div class="info-label">Vehicle:</div>
                    <div class="info-value"><?php echo htmlspecialchars($additionalData['transport']['vehicle_number'] ?? 'N/A'); ?></div>
                </div>
                <div class="info-row">
                    <div class="info-label">Driver:</div>
                    <div class="info-value"><?php echo htmlspecialchars($additionalData['transport']['driver_name'] ?? 'N/A'); ?></div>
                </div>
                <div class="info-row">
                    <div class="info-label">Pickup Point:</div>
                    <div class="info-value"><?php echo htmlspecialchars($additionalData['transport']['pickup_point'] ?? 'N/A'); ?></div>
                </div>
            <?php else: ?>
                <p class="text-muted">No transportation records found.</p>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <!-- Authorization Information (for security personnel) -->
        <?php if (canShowSection('authorization', $allowedSections)): ?>
        <div class="info-card">
            <h4><i class="fas fa-id-card"></i> Authorization Information</h4>
            <?php if (!empty($additionalData['id_card'])): ?>
                <div class="info-row">
                    <div class="info-label">Card Number:</div>
                    <div class="info-value"><?php echo htmlspecialchars($additionalData['id_card']['card_number'] ?? 'N/A'); ?></div>
                </div>
                <div class="info-row">
                    <div class="info-label">Issue Date:</div>
                    <div class="info-value"><?php echo htmlspecialchars($additionalData['id_card']['issue_date'] ?? 'N/A'); ?></div>
                </div>
                <div class="info-row">
                    <div class="info-label">Expiry Date:</div>
                    <div class="info-value"><?php echo htmlspecialchars($additionalData['id_card']['expiry_date'] ?? 'N/A'); ?></div>
                </div>
                <div class="info-row">
                    <div class="info-label">Status:</div>
                    <div class="info-value">
                        <span class="badge bg-success"><?php echo ucfirst($additionalData['id_card']['status'] ?? 'N/A'); ?></span>
                    </div>
                </div>
            <?php else: ?>
                <p class="text-muted">No active ID card found.</p>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <!-- Medical Information (for medical staff) -->
        <?php if (canShowSection('medical', $allowedSections)): ?>
        <div class="info-card">
            <h4><i class="fas fa-heartbeat"></i> Medical Information</h4>
            <?php if (!empty($additionalData['medical'])): ?>
                <?php foreach ($additionalData['medical'] as $medical): ?>
                    <div class="info-row">
                        <div class="info-label"><?php echo htmlspecialchars($medical['date']); ?>:</div>
                        <div class="info-value"><?php echo htmlspecialchars($medical['notes'] ?? 'N/A'); ?></div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <p class="text-muted">No medical records found.</p>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <!-- Sports Information (for sports coordinator) -->
        <?php if (canShowSection('sports', $allowedSections)): ?>
        <div class="info-card">
            <h4><i class="fas fa-running"></i> Sports Information</h4>
            <?php if (!empty($additionalData['sports'])): ?>
                <?php foreach ($additionalData['sports'] as $sport): ?>
                    <div class="info-row">
                        <div class="info-label">Sport:</div>
                        <div class="info-value"><?php echo htmlspecialchars($sport['sport_name'] ?? 'N/A'); ?></div>
                    </div>
                    <div class="info-row">
                        <div class="info-label">Position:</div>
                        <div class="info-value"><?php echo htmlspecialchars($sport['position'] ?? 'N/A'); ?></div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <p class="text-muted">No sports records found.</p>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <!-- Library Information (for librarian) -->
        <?php if (canShowSection('library', $allowedSections)): ?>
        <div class="info-card">
            <h4><i class="fas fa-book"></i> Library Information</h4>
            <?php if (!empty($additionalData['library'])): ?>
                <?php foreach ($additionalData['library'] as $book): ?>
                    <div class="info-row">
                        <div class="info-label">Book:</div>
                        <div class="info-value"><?php echo htmlspecialchars($book['book_title'] ?? 'N/A'); ?></div>
                    </div>
                    <div class="info-row">
                        <div class="info-label">Due Date:</div>
                        <div class="info-value"><?php echo htmlspecialchars($book['due_date'] ?? 'N/A'); ?></div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <p class="text-muted">No library records found.</p>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <!-- Guidance Information (for guidance counselor) -->
        <?php if (canShowSection('guidance', $allowedSections)): ?>
        <div class="info-card">
            <h4><i class="fas fa-comments"></i> Guidance & Counseling</h4>
            <?php if (!empty($additionalData['guidance'])): ?>
                <?php foreach ($additionalData['guidance'] as $guidance): ?>
                    <div class="info-row">
                        <div class="info-label"><?php echo htmlspecialchars($guidance['date']); ?>:</div>
                        <div class="info-value"><?php echo htmlspecialchars($guidance['notes'] ?? 'N/A'); ?></div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <p class="text-muted">No guidance records found.</p>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <!-- Footer -->
        <div class="text-center mt-4 mb-4">
            <p class="text-muted">
                <small>
                    Generated: <?php echo date('Y-m-d H:i:s'); ?> | 
                    Kingsway Preparatory School
                </small>
            </p>
        </div>
    </div>
</body>
</html>