<?php
/**
 * Role-aware student profile route.
 *
 * Uses /api/students/context-profile so tabs and fields are server-scoped.
 */
?>
<section class="student-profile-context bg-white border rounded p-3" id="studentProfileContext">
    <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-3">
        <div>
            <h2 class="h4 mb-1">Student Profile</h2>
            <div class="text-muted small">Role-aware profile details and actions.</div>
        </div>
        <a class="btn btn-outline-secondary btn-sm" href="home.php?route=all_students">
            <i class="bi bi-arrow-left me-1"></i>Directory
        </a>
    </div>

    <div id="studentProfileState" class="text-center text-muted py-5">
        <div class="spinner-border text-primary mb-2" role="status" aria-hidden="true"></div>
        <div>Loading profile...</div>
    </div>

    <div id="studentProfileSearch" class="d-none">
        <div class="input-group mb-3">
            <span class="input-group-text"><i class="bi bi-search"></i></span>
            <input type="search" class="form-control" id="studentProfileSearchInput" placeholder="Search by name or admission number">
        </div>
        <div class="list-group" id="studentProfileResults"></div>
    </div>

    <div id="studentProfileView" class="d-none">
        <div class="d-flex align-items-center gap-3 mb-3">
            <div class="rounded-circle bg-primary bg-opacity-10 d-flex align-items-center justify-content-center" style="width:72px;height:72px;">
                <i class="bi bi-person-fill fs-2 text-primary"></i>
            </div>
            <div>
                <h3 class="h5 mb-1" id="studentProfileName"></h3>
                <div class="text-muted small" id="studentProfileMeta"></div>
            </div>
        </div>

        <ul class="nav nav-tabs" id="studentProfileTabs"></ul>
        <div class="border border-top-0 rounded-bottom p-3" id="studentProfileTabContent"></div>
    </div>
</section>

<script src="js/pages/student_profile_context.js?v=20260703"></script>
