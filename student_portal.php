<?php
/**
 * ID Card Verification - Role-Based Learner Check
 *
 * Landing page for the QR code on a learner's school ID card. Pure UI shell:
 * this page holds NO SQL and NO business logic. It renders an empty skeleton
 * and loads js/pages/student_card_verification.js, which fetches
 * GET /api/public/student-verification/{id} and renders the sections the
 * server authorized for the observer (public scan = name + class only;
 * signed-in staff get their role's sections; ?scope= narrows for device pins).
 *
 * Visibility is decided server-side by StudentCardVerificationService::viewPolicy
 * — a browser user can never widen their own sections.
 */
if (session_status() === PHP_SESSION_NONE) { session_start(); }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ID Card Verification | Kingsway Preparatory School</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-icons/1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link rel="stylesheet" href="css/app-common.css">
    <style>
        :root { --kw-green: #0d4f2a; --kw-green-light: #198754; --kw-gold: #f9c80e; }
        body { background: #f5f5f5; padding: 20px; }
        .student-header { background: linear-gradient(135deg, var(--kw-green) 0%, var(--kw-green-light) 100%); color: white; padding: 30px; border-radius: 15px; margin-bottom: 30px; }
        .student-photo { width: 120px; height: 120px; border-radius: 50%; border: 4px solid white; object-fit: cover; }
        .info-card { background: white; border-radius: 10px; padding: 20px; margin-bottom: 20px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .info-card h4 { color: var(--kw-green); margin-bottom: 15px; border-bottom: 2px solid var(--kw-green); padding-bottom: 10px; }
        .info-row { display: flex; padding: 8px 0; border-bottom: 1px solid #eee; }
        .info-label { font-weight: 600; width: 150px; color: #555; }
        .info-value { color: #333; flex: 1; }
        .role-badge { background: rgba(255,255,255,0.2); padding: 5px 15px; border-radius: 20px; font-size: 14px; }
    </style>
</head>
<body>
    <div class="container" id="verification-root">
        <div class="student-header d-flex align-items-center" id="header">
            <div class="spinner-border text-light" role="status" aria-label="Verifying"></div>
            <span class="ms-2">Verifying ID card…</span>
        </div>
        <div id="sections"></div>
        <div class="text-center mt-4 mb-4" id="footer" hidden>
            <p class="text-muted"><small>ID card verification — role-based details for school staff and guardians | Kingsway Preparatory School</small></p>
        </div>
    </div>
    <script src="js/api.js"></script>
    <script src="js/pages/student_card_verification.js"></script>
</body>
</html>
