<?php session_start(); ?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Kingsway Admin Login</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="king.css">
</head>
<body>
  <div class="container text-center mt-5">
    <h1>Welcome to Kingsway Preparatory School Administration</h1>
    <button class="btn btn-primary mt-4" data-bs-toggle="modal" data-bs-target="#loginModal">Login</button>
  </div>
  <!-- Login Modal -->
  <div class="modal fade" id="loginModal" tabindex="-1">
    <div class="modal-dialog">
      <form class="modal-content" id="login-form">
        <div class="modal-header">
          <h5 class="modal-title">Admin Login</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <input name="username" class="form-control mb-2" placeholder="Username" required>
          <input name="password" type="password" class="form-control mb-2" placeholder="Password" required>
          <div id="login-error" class="text-danger small"></div>
        </div>
        <div class="modal-footer">
          <button class="btn btn-primary" type="submit">Login</button>
        </div>
      </form>
    </div>
  </div>
  <script>
    document.getElementById('login-form').addEventListener('submit', function(e){
      e.preventDefault();
      const formData = new FormData(this);
      fetch('api/login.php', {
        method: 'POST',
        body: formData
      })
      .then(res => res.json())
      .then(data => {
        if(data.success){
          window.location = 'home.php';
        } else {
          document.getElementById('login-error').textContent = data.message || 'Invalid credentials';
        }
      });
    });
  </script>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>