<?php
/** Safe dashboard fallback for authenticated users with no assigned dashboard. */
?>
<div class="container-fluid py-4">
    <div class="alert alert-warning border shadow-sm" role="alert">
        <h4 class="alert-heading">
            <i class="bi bi-shield-lock me-2"></i>
            Dashboard not assigned
        </h4>
        <p>
            Your account is authenticated, but no active dashboard has been assigned to your role.
        </p>
        <hr>
        <p class="mb-0">
            Contact the School Administrator or System Administrator to correct your role assignment.
        </p>
    </div>
</div>
