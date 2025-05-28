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
    <form class="mb-3" id="add-student-form">
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
        <input type=" •text" class="form-control" id="class" name="class" required>
      </div>
      <button type="submit" name="add_student" class="btn btn-primary">Add Student</button>
    </form>


  </div>

  <script>
    document.getElementById('add-student-form').addEventListener('submit', function(e) {
      e.preventDefault();
      const formData = new FormData(this);
      fetch('/Kingsway/api/add_student.php', {
          method: 'POST',
          body: formData
        })
        .then(res => res.json())
        .then(data => {
          if (data.success) {
            loadStudents(); // Refresh the table
            this.reset();
          }
        });
    });
  </script>
</body>

</html>