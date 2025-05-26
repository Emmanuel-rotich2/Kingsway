<?php
$conn = new mysqli("localhost", "root", "", "kingsway");
if ($conn->connect_error) {
  die("Connection failed: " . $conn->connect_error);
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
  $name = $_POST['name'];
  $staff_id = $_POST['staff_id'];
  $subject = $_POST['subject'];
  $conn->query("INSERT INTO teachers (name, staff_id, subject) VALUES ('$name', '$staff_id', '$subject')");
}
$teachers = $conn->query("SELECT * FROM teachers");
?>

<!DOCTYPE html>
<html>
<head>
  <title>Manage Teachers</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<div class="container mt-5">
  <h2 class="mb-4">Manage Teachers</h2>
  <form method="post" class="mb-4 p-4 bg-white rounded shadow-sm">
    <div class="mb-3">
      <label>Teacher Name</label>
      <input type="text" name="name" class="form-control" required>
    </div>
    <div class="mb-3">
      <label>Staff ID</label>
      <input type="text" name="staff_id" class="form-control" required>
    </div>
    <div class="mb-3">
      <label>Subject</label>
      <input type="text" name="subject" class="form-control" required>
    </div>
    <button type="submit" class="btn btn-success">Add Teacher</button>
  </form>

  <h4 class="mb-3">Teacher List</h4>
  <table class="table table-bordered table-striped bg-white">
    <thead>
      <tr>
        <th>ID</th>
        <th>Name</th>
        <th>Staff ID</th>
        <th>Subject</th>
      </tr>
    </thead>
    <tbody>
      <?php while ($row = $teachers->fetch_assoc()): ?>
        <tr>
          <td><?= $row['id'] ?></td>
          <td><?= $row['name'] ?></td>
          <td><?= $row['staff_id'] ?></td>
          <td><?= $row['subject'] ?></td>
        </tr>
      <?php endwhile; ?>
    </tbody>
  </table>
</div>
</body>
</html>
