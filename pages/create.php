<?php
header("Content-Type: application/json");

require "../config/db.php";
require "../manage_subjects.php";

$db = (new Database())->connect();
$subject = new Subject($db);

$data = json_decode(file_get_contents("php://input"));

$subject->subject_code = $data->subject_code;
$subject->subject_name = $data->subject_name;
$subject->category = $data->category;
$subject->class_level = $data->class_level;
$subject->status = "Active";

if ($subject->create()) {
    echo json_encode(["message" => "Subject added successfully"]);
} else {
    echo json_encode(["error" => "Failed to add subject"]);
}
