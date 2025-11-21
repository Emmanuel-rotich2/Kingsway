<?php
// reset_password.php
$token = $_GET['token'] ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Reset Password - Kingsway</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
  <div class="container py-5">
    <div class="row justify-content-center">
      <div class="col-md-6 col-lg-5">
        <div class="card shadow-sm">
          <div class="card-body">
            <h4 class="mb-3">Reset Password</h4>
            <form id="reset-form">
              <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">
              <div class="mb-3">
                <label for="new_password" class="form-label">New Password</label>
                <input type="password" class="form-control" id="new_password" name="new_password" required>
              </div>
              <button type="submit" class="btn btn-primary w-100">Set New Password</button>
              <div id="reset-success" class="text-success mt-2"></div>
              <div id="reset-error" class="text-danger mt-2"></div>
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
    document.getElementById('reset-form').addEventListener('submit', function(e) {
      e.preventDefault();
      document.getElementById('reset-success').textContent = '';
      document.getElementById('reset-error').textContent = '';
      const formData = new FormData(this);
      fetch('api/reset_password.php', {
        method: 'POST',
        body: formData
      })
      .then(res => res.json())
      .then(data => {
        if (data.status === 'success') {
          document.getElementById('reset-success').textContent = data.message;
        } else {
          document.getElementById('reset-error').textContent = data.message || 'Failed to reset password.';
        }
      })
      .catch(() => {
        document.getElementById('reset-error').textContent = 'An error occurred. Please try again.';
      });
    });
  </script>
</body>
</html>