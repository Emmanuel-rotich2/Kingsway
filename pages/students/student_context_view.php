<?php
/**
 * Shared Students role-context view.
 *
 * Expected variables:
 * - $studentContext
 * - $studentPageTitle
 * - $studentPageDescription
 */
$studentContext = $studentContext ?? '';
$studentPageTitle = $studentPageTitle ?? 'Students';
$studentPageDescription = $studentPageDescription ?? 'Student directory';
$studentReadOnly = !empty($studentReadOnly);
?>

<section
    class="student-context-page"
    data-student-context="<?= htmlspecialchars((string) $studentContext, ENT_QUOTES, 'UTF-8') ?>"
    data-read-only="<?= $studentReadOnly ? '1' : '0' ?>"
>
    <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-3">
        <div>
            <h2 class="h4 mb-1" id="studentContextTitle"><?= htmlspecialchars($studentPageTitle, ENT_QUOTES, 'UTF-8') ?></h2>
            <div class="text-muted small"><?= htmlspecialchars($studentPageDescription, ENT_QUOTES, 'UTF-8') ?></div>
        </div>
        <div class="d-flex gap-2" id="studentContextActions"></div>
    </div>

    <div class="border rounded bg-white p-3 mb-3">
        <div class="row g-2 align-items-end" id="studentContextFilters">
            <div class="col-md-4">
                <label class="form-label small text-muted" for="studentContextSearch">Search</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="bi bi-search"></i></span>
                    <input type="search" class="form-control" id="studentContextSearch" placeholder="Name or admission number">
                </div>
            </div>
            <div class="col-md-2" data-filter="gender">
                <label class="form-label small text-muted" for="studentContextGender">Gender</label>
                <select class="form-select" id="studentContextGender">
                    <option value="">All</option>
                    <option value="male">Male</option>
                    <option value="female">Female</option>
                </select>
            </div>
            <div class="col-md-2" data-filter="status">
                <label class="form-label small text-muted" for="studentContextStatus">Status</label>
                <select class="form-select" id="studentContextStatus">
                    <option value="">All</option>
                    <option value="active">Active</option>
                    <option value="suspended">Suspended</option>
                    <option value="inactive">Inactive</option>
                    <option value="graduated">Graduated</option>
                </select>
            </div>
        </div>
    </div>

    <div id="studentContextState" class="border rounded bg-white p-4 text-center text-muted">
        <div class="spinner-border text-primary mb-2" role="status" aria-hidden="true"></div>
        <div>Loading students...</div>
    </div>

    <div class="table-responsive border rounded bg-white d-none" id="studentContextTableWrap">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr id="studentContextHead"></tr>
            </thead>
            <tbody id="studentContextBody"></tbody>
        </table>
    </div>

    <div class="d-flex justify-content-between align-items-center mt-3 d-none" id="studentContextPager">
        <span class="small text-muted" id="studentContextPageInfo"></span>
        <div class="btn-group">
            <button class="btn btn-outline-secondary btn-sm" type="button" data-page-action="prev">
                <i class="bi bi-chevron-left"></i>
            </button>
            <button class="btn btn-outline-secondary btn-sm" type="button" data-page-action="next">
                <i class="bi bi-chevron-right"></i>
            </button>
        </div>
    </div>
</section>

<script src="js/pages/student_context.js?v=20260703"></script>
