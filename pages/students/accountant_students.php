<?php
/**
 * Students - Accountant Layout
 * Focused on fee tracking and student billing history.
 */
/* PARTIAL — no DOCTYPE/html/head/body. Injected into app shell via fetch. */
?>

<!-- Filters -->
<div class="manager-filters">
    <select class="filter-select" id="filterClass">
        <option value="">All Classes</option>
    </select>
    <select class="filter-select" id="filterStream">
        <option value="">All Streams</option>
    </select>
    <select class="filter-select" id="filterStatus">
        <option value="">All Status</option>
        <option value="active">Active</option>
        <option value="inactive">Inactive</option>
        <option value="graduated">Graduated</option>
        <option value="transferred">Transferred</option>
    </select>
    <input type="text" class="filter-search search-input" id="searchStudent" placeholder="Search name or admission no">
    <button class="btn btn-outline btn-sm" id="exportStudentsBtn">📥 Export</button>
</div>

<!-- Table -->
<div class="manager-table-card">
    <table class="manager-data-table" id="studentsTable">
        <thead>
            <tr>
                <th>Adm No</th>
                <th>Name</th>
                <th>Class</th>
                <th>Stream</th>
                <th>Gender</th>
                <th>Type</th>
                <th>Status</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody id="studentsTableBody">
            <tr>
                <td colspan="8" class="text-center">Loading students...</td>
            </tr>
        </tbody>
    </table>

    <div class="table-footer">
        <span class="page-info">Showing <span id="showingCount">0</span> of <span id="totalCount">0</span></span>
        <div class="pagination" id="pagination"></div>
    </div>
</div>

<!-- Fee Track Modal -->
<div class="modal fade" id="feeTrackModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title" id="feeTrackTitle">Fee Track</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div id="feeTrackContent" class="text-muted">Select a student to view fee history.</div>
            </div>
        </div>
    </div>
</div>

<script src="<?= $appBase ?>js/pages/all_students_accountant.js"></script>
