<?php
header("Content-Type: application/json");

require "../config/db.php";
require "../manage_subjects.php";

$db = (new Database())->connect();
$subject = new Subject($db);

$data = json_decode(file_get_contents("php://input"));
$subject->id = $data->id;

if ($subject->delete()) {
    echo json_encode(["message" => "Subject deleted successfully"]);
} else {
    echo json_encode(["error" => "Failed to delete subject"]);
}
