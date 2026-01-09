<?php
// save_student.php
require_once __DIR__ . '/db.php'; // Your DB connection
header('Content-Type: application/json');

// Helper function to upload files
function uploadFile($file, $folder = 'uploads/students/') {
    if (!isset($file) || $file['error'] !== UPLOAD_ERR_OK) return null;

    if (!is_dir($folder)) mkdir($folder, 0777, true);

    $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = uniqid() . '.' . $ext;
    $target = $folder . $filename;

    if (move_uploaded_file($file['tmp_name'], $target)) {
        return $target;
    }
    return null;
}

// Get POST data
$data = $_POST;
$studentId = intval($data['studentId'] ?? 0);
$firstName = trim($data['firstName']);
$middleName = trim($data['middleName'] ?? '');
$lastName = trim($data['lastName']);
$dob = $data['dateOfBirth'];
$gender = $data['gender'];
$bloodGroup = $data['bloodGroup'] ?? '';
$classId = intval($data['studentClass']);
$streamId = intval($data['studentStream']);
$studentTypeId = intval($data['studentTypeId']);
$admissionNumber = trim($data['admissionNumber']);
$status = $data['studentStatus'];
$boardingStatus = $data['boardingStatus'] ?? 'day';
$admissionDate = $data['admissionDate'] ?? date('Y-m-d');
$assessmentNumber = trim($data['assessmentNumber'] ?? '');
$assessmentStatus = $data['assessmentStatus'] ?? '';
$sponsored = isset($data['isSponsored']) ? 1 : 0;
$sponsorName = trim($data['sponsorName'] ?? '');
$sponsorType = $data['sponsorType'] ?? '';
$sponsorWaiver = floatval($data['sponsorWaiverPercentage'] ?? 0);
$email = trim($data['studentEmail'] ?? '');
$phone = trim($data['studentPhone'] ?? '');
$address = trim($data['studentAddress'] ?? '');

// --- Parent Info ---
$isNewParent = isset($data['isNewParent']) && $data['isNewParent'] == 'true';
$guardianRelationship = $data['guardianRelationship'] ?? '';
$parentId = intval($data['existingParentId'] ?? 0);

$parentFirstName = trim($data['parentFirstName'] ?? '');
$parentLastName = trim($data['parentLastName'] ?? '');
$parentGender = $data['parentGender'] ?? '';
$parentPhone1 = trim($data['parentPhone1'] ?? '');
$parentPhone2 = trim($data['parentPhone2'] ?? '');
$parentEmail = trim($data['parentEmail'] ?? '');
$parentOccupation = trim($data['parentOccupation'] ?? '');
$parentAddress = trim($data['parentAddress'] ?? '');

// --- Payment Info ---
$initialPaymentAmount = floatval($data['initialPaymentAmount'] ?? 0);
$paymentMethod = $data['paymentMethod'] ?? '';
$paymentReference = $data['paymentReference'] ?? '';
$receiptNo = $data['receiptNo'] ?? '';

// --- Upload files ---
$profilePic = uploadFile($_FILES['profile_pic'] ?? null);
$nationalIdFile = uploadFile($_FILES['nationalId'] ?? null);

// --- Handle Parent ---
if ($isNewParent) {
    // Insert new parent
    $stmt = $conn->prepare("INSERT INTO parents (first_name,last_name,gender,phone1,phone2,email,occupation,address) VALUES (?,?,?,?,?,?,?,?)");
    $stmt->bind_param("ssssssss", $parentFirstName,$parentLastName,$parentGender,$parentPhone1,$parentPhone2,$parentEmail,$parentOccupation,$parentAddress);
    $stmt->execute();
    $parentId = $stmt->insert_id;
}

// --- Insert or Update Student ---
if ($studentId > 0) {
    // Update
    $stmt = $conn->prepare("
        UPDATE students SET
            first_name=?, middle_name=?, last_name=?, date_of_birth=?, gender=?, blood_group=?,
            class_id=?, stream_id=?, student_type_id=?, admission_no=?, status=?, boarding_status=?,
            admission_date=?, assessment_no=?, assessment_status=?, sponsored=?, sponsor_name=?, sponsor_type=?, sponsor_waiver=?,
            email=?, phone=?, address=?, parent_id=?, profile_pic=?, national_id_file=?
        WHERE id=?
    ");
    $stmt->bind_param(
        "ssssssiisssssssiisssssssi",
        $firstName,$middleName,$lastName,$dob,$gender,$bloodGroup,
        $classId,$streamId,$studentTypeId,$admissionNumber,$status,$boardingStatus,
        $admissionDate,$assessmentNumber,$assessmentStatus,$sponsored,$sponsorName,$sponsorType,$sponsorWaiver,
        $email,$phone,$address,$parentId,$profilePic,$nationalIdFile,$studentId
    );
    $stmt->execute();
    $studentId = $studentId; // existing
} else {
    // Insert
    $stmt = $conn->prepare("
        INSERT INTO students 
        (first_name,middle_name,last_name,date_of_birth,gender,blood_group,
         class_id,stream_id,student_type_id,admission_no,status,boarding_status,
         admission_date,assessment_no,assessment_status,sponsored,sponsor_name,sponsor_type,sponsor_waiver,
         email,phone,address,parent_id,profile_pic,national_id_file)
        VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)
    ");
    $stmt->bind_param(
        "ssssssiisssssssiissssss",
        $firstName,$middleName,$lastName,$dob,$gender,$bloodGroup,
        $classId,$streamId,$studentTypeId,$admissionNumber,$status,$boardingStatus,
        $admissionDate,$assessmentNumber,$assessmentStatus,$sponsored,$sponsorName,$sponsorType,$sponsorWaiver,
        $email,$phone,$address,$parentId,$profilePic,$nationalIdFile
    );
    $stmt->execute();
    $studentId = $stmt->insert_id;
}

// --- Initial Payment ---
if (!$sponsored && $initialPaymentAmount > 0) {
    $stmt = $conn->prepare("INSERT INTO payments (student_id,amount,method,reference,receipt_no,date_created) VALUES (?,?,?,?,?,NOW())");
    $stmt->bind_param("idsss",$studentId,$initialPaymentAmount,$paymentMethod,$paymentReference,$receiptNo);
    $stmt->execute();
}

echo json_encode([
    'success' => true,
    'student_id' => $studentId,
    'message' => 'Student saved successfully.'
]);
