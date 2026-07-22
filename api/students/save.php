<?php
header('Content-Type: application/json');
include 'db.php'; // include your PDO connection

final class LegacyStudentSaveUploadAdapter extends \App\API\Includes\BaseAPI
{
    public function store(array $file, string $category): ?string
    {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            return null;
        }
        $stored = $this->uploadManaged($file, $category, [
            'prefix' => $category,
        ]);
        return (string) $stored['application_path'];
    }
}

$legacyUploads = new LegacyStudentSaveUploadAdapter('students');

try {
    $data = $_POST;

    $studentId = $data['studentId'] ?? null;
    $admission_no = $data['admissionNumber'];
    $first_name = $data['firstName'];
    $middle_name = $data['middleName'] ?? '';
    $last_name = $data['lastName'];
    $dob = $data['dateOfBirth'];
    $gender = $data['gender'];
    $class_id = $data['studentClass'];
    $stream_id = $data['studentStream'] ?? null;
    $student_type_id = $data['studentTypeId'];
    $admission_date = $data['admissionDate'] ?? null;
    $status = $data['studentStatus'];
    $boarding_status = $data['boardingStatus'] ?? 'day';
    $assessment_number = $data['assessmentNumber'] ?? null;
    $assessment_status = $data['assessmentStatus'] ?? 'not_assigned';
    $is_sponsored = isset($data['isSponsored']) ? 1 : 0;
    $sponsor_name = $data['sponsorName'] ?? null;
    $sponsor_type = $data['sponsorType'] ?? null;
    $sponsor_waiver = $data['sponsorWaiverPercentage'] ?? 0;
    $email = $data['studentEmail'] ?? null;
    $phone = $data['studentPhone'] ?? null;
    $address = $data['studentAddress'] ?? null;

    // Handle file uploads through the inherited canonical service.
    $profile_pic = $legacyUploads->store(
        $_FILES['profile_pic'] ?? [],
        'student_photo'
    );
    $national_id_file = $legacyUploads->store(
        $_FILES['nationalId'] ?? [],
        'student_document'
    );

    // Insert/Update Student
    if($studentId){
        $stmt = $pdo->prepare("UPDATE students SET first_name=?, middle_name=?, last_name=?, date_of_birth=?, gender=?, class_id=?, stream_id=?, student_type_id=?, admission_date=?, status=?, boarding_status=?, assessment_number=?, assessment_status=?, is_sponsored=?, sponsor_name=?, sponsor_type=?, sponsor_waiver=?, email=?, phone=?, address=?, profile_pic=?, national_id=? WHERE id=?");
        $stmt->execute([$first_name,$middle_name,$last_name,$dob,$gender,$class_id,$stream_id,$student_type_id,$admission_date,$status,$boarding_status,$assessment_number,$assessment_status,$is_sponsored,$sponsor_name,$sponsor_type,$sponsor_waiver,$email,$phone,$address,$profile_pic,$national_id_file,$studentId]);
        $studentId = $studentId;
    }else{
        $stmt = $pdo->prepare("INSERT INTO students (admission_no, first_name, middle_name, last_name, date_of_birth, gender, class_id, stream_id, student_type_id, admission_date, status, boarding_status, assessment_number, assessment_status, is_sponsored, sponsor_name, sponsor_type, sponsor_waiver, email, phone, address, profile_pic, national_id) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
        $stmt->execute([$admission_no,$first_name,$middle_name,$last_name,$dob,$gender,$class_id,$stream_id,$student_type_id,$admission_date,$status,$boarding_status,$assessment_number,$assessment_status,$is_sponsored,$sponsor_name,$sponsor_type,$sponsor_waiver,$email,$phone,$address,$profile_pic,$national_id_file]);
        $studentId = $pdo->lastInsertId();
    }

    // Handle Parent
    if(isset($data['isNewParent'])){
        // insert new parent
        $stmt = $pdo->prepare("INSERT INTO parents (first_name,last_name,gender,phone1,phone2,email,occupation,address) VALUES (?,?,?,?,?,?,?,?)");
        $stmt->execute([
            $data['parentFirstName'],$data['parentLastName'],$data['parentGender']??null,
            $data['parentPhone1'],$data['parentPhone2']??null,$data['parentEmail']??null,
            $data['parentOccupation']??null,$data['parentAddress']??null
        ]);
        $parentId = $pdo->lastInsertId();
    }else{
        $parentId = $data['existingParentId'];
    }

    // Link student to parent
    if($parentId){
        $stmt = $pdo->prepare("INSERT INTO student_parent (student_id,parent_id,relationship) VALUES (?,?,?) ON DUPLICATE KEY UPDATE relationship=?");
        $stmt->execute([$studentId,$parentId,$data['guardianRelationship'],$data['guardianRelationship']]);
    }

    // Handle initial payment if not sponsored
    if(!$is_sponsored && !empty($data['initialPaymentAmount'])){
        $stmt = $pdo->prepare("INSERT INTO payments (student_id,amount,method,reference,receipt_no) VALUES (?,?,?,?,?)");
        $stmt->execute([$studentId,$data['initialPaymentAmount'],$data['paymentMethod'],$data['paymentReference'],$data['receiptNo']]);
    }

    echo json_encode(['status'=>'success','message'=>'Student saved successfully']);
}catch(Exception $e){
    echo json_encode(['status'=>'error','message'=>$e->getMessage()]);
}
