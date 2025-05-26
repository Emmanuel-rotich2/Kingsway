
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Manage Students - Kingsway</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
  <div class="container mt-5">
    <h2 class="mb-4">Student Management</h2>
    <form method="POST" action="students.php" class="mb-3">
      <div class="mb-3">
        <label for="name" class="form-label">Student Name</label>
        <input type="text" class="form-control" id="name" name="name" required>
      </div>
      <div class="mb-3">
        <label for="admission_no" class="form-label">Admission Number</label>
        <input type="text" class="form-control" id="admission_no" name="admission_no" required>
      </div>
      <div class="mb-3">
        <label for="class" class="form-label">Class</label>
        <input type="text" class="form-control" id="class" name="class" required>
      </div>
      <button type="submit" name="add_student" class="btn btn-primary">Add Student</button>
    </form>

    <?php
    $conn = new mysqli("localhost", "root", "", "kingsway");
    if ($conn->connect_error) { die("Connection failed: " . $conn->connect_error); }

    if (isset($_POST['add_student'])) {
      $name = $_POST['name'];
      $admission_no = $_POST['admission_no'];
      $class = $_POST['class'];
      $stmt = $conn->prepare("INSERT INTO students (name, admission_no, class) VALUES (?, ?, ?)");
      $stmt->bind_param("sss", $name, $admission_no, $class);
      $stmt->execute();
      echo "<div class='alert alert-success'>Student added successfully!</div>";
    }

    $result = $conn->query("SELECT * FROM students");
    if ($result->num_rows > 0) {
      echo "<table class='table table-bordered'><thead><tr><th>ID</th><th>Name</th><th>Admission No</th><th>Class</th></tr></thead><tbody>";
      while($row = $result->fetch_assoc()) {
        echo "<tr><td>{$row['id']}</td><td>{$row['name']}</td><td>{$row['admission_no']}</td><td>{$row['class']}</td></tr>";
      }
      echo "</tbody></table>";
    } else {
      echo "<p>No students found.</p>";
    }
    $conn->close();
    ?>
  </div>
</body>
</html>
