<?php
// forgot_password.php
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Forgot Password - Kingsway</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
  <div class="container py-5">
    <div class="row justify-content-center">
      <div class="col-md-6 col-lg-5">
        <div class="card shadow-sm">
          <div class="card-body">
            <h4 class="mb-3">Forgot Password</h4>
            <form id="forgot-form">
              <div class="mb-3">
                <label for="email" class="form-label">Email or Username</label>
                <input type="text" class="form-control" id="email" name="email" required>
              </div>
              <button type="submit" class="btn btn-primary w-100">Send Reset Link</button>
              <div id="forgot-success" class="text-success mt-2"></div>
              <div id="forgot-error" class="text-danger mt-2"></div>
            </form>
            <div class="mt-3">
              <a href="index.php">Back to Login</a>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
  <script>
    document.getElementById('forgot-form').addEventListener('submit', function(e) {
      e.preventDefault();
      document.getElementById('forgot-success').textContent = '';
      document.getElementById('forgot-error').textContent = '';
      const formData = new FormData(this);
      fetch('api/forgot_password.php', {
        method: 'POST',
        body: formData
      })
      .then(res => res.json())
      .then(data => {
        if (data.status === 'success') {
          document.getElementById('forgot-success').textContent = data.message;
        } else {
          document.getElementById('forgot-error').textContent = data.message || 'Failed to send reset link.';
        }
      })
      .catch(() => {
        document.getElementById('forgot-error').textContent = 'An error occurred. Please try again.';
      });
    });
  </script>
</body>
</html>