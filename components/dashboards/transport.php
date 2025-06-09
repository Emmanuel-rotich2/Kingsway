<?php
// db.php - database connection
$conn = new mysqli("localhost", "root", "", "transport");
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
?>

<!-- scan.html - QR Code Scanning Interface -->
<!DOCTYPE html>
<html>
<head>
    <title>Student Transport QR Scan</title>
    <script src="https://unpkg.com/html5-qrcode"></script>
</head>
<body>
    <h2>Scan Student QR Code</h2>
    <div id="reader" style="width:300px;"></div>

    <script>
      function onScanSuccess(decodedText) {
        window.location.href = "check_fee.php?id=" + decodedText;
      }
      new Html5Qrcode("reader").start(
        { facingMode: "environment" },
        { fps: 10, qrbox: 250 },
        onScanSuccess
      );
    </script>
</body>
</html>

<?php
// check_fee.php - Fee Checking Logic
require 'db.php';

if (!isset($_GET['id'])) {
    die("Invalid access");
}

$student_id = intval($_GET['id']);

$stmt = $conn->prepare("SELECT full_name, transport_fee_status FROM students WHERE student_id = ?");
$stmt->bind_param("i", $student_id);
$stmt->execute();
$result = $stmt->get_result();
$student = $result->fetch_assoc();

if ($student) {
    if ($student['transport_fee_status'] == 'Paid') {
        echo "<h2 style='color:green;'>Access Granted: {$student['full_name']}</h2>";
    } else {
        echo "<h2 style='color:red;'>Access Denied: Transport Fee Not Cleared</h2>";
    }
} else {
    echo "<h2 style='color:red;'>Student Not Found</h2>";
}
?>
