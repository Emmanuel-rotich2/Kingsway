<?php
/**
 * Discipline - Operator Layout
 * Minimal layout for Class Teachers, Subject Teachers
 *
 * Features:
 * - 2 stat cards
 * - No charts
 * - Simple table (4 columns)
 * - Can report cases, view own reports only
 */
/* PARTIAL — no DOCTYPE/html/head/body. Injected into app shell via fetch. */
?>

<!-- Stats - 2 columns -->
<div class="operator-stats">
    <div class="operator-stat-card">
        <div class="stat-icon">📋</div>
        <div class="stat-info">
            <div class="stat-value" id="myCases">0</div>
            <div class="stat-label">My Reports</div>
        </div>
    </div>
    <div class="operator-stat-card">
        <div class="stat-icon">🔴</div>
        <div class="stat-info">
            <div class="stat-value" id="pendingCases">0</div>
            <div class="stat-label">Pending</div>
        </div>
    </div>
</div>

<!-- Search -->
<div class="operator-filters">
    <input type="text" class="search-input form-control" id="searchCase" placeholder="Search cases...">
    <button class="btn btn-warning btn-sm" id="reportBtn">📋 Report Case</button>
</div>

<!-- Table - 4 essential columns -->
<div class="operator-table-card">
    <div class="operator-table-header">
        <span class="table-title">Cases I Reported</span>
    </div>

    <table class="operator-data-table" id="casesTable">
        <thead>
            <tr>
                <th scope="col">Date</th>
                <th scope="col">Student</th>
                <th scope="col">Category</th>
                <th scope="col">Status</th>
            </tr>
        </thead>
        <tbody id="casesTableBody">
            <!-- Data loaded dynamically -->
        </tbody>
    </table>
</div>

<!-- Simple Report Modal -->
<div class="modal fade" id="reportModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header bg-warning text-dark">
                <h5 class="modal-title">Report Discipline Case</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="reportForm">
                    <div class="mb-3">
                        <label class="form-label">Student</label>
                        <select class="form-select" id="studentId" required></select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Category</label>
                        <select class="form-select" id="category" required>
                            <option value="">Select</option>
                            <option value="misconduct">Misconduct</option>
                            <option value="truancy">Truancy</option>
                            <option value="fighting">Fighting</option>
                            <option value="other">Other</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">What happened?</label>
                        <textarea class="form-control" id="description" rows="4" required></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button class="btn btn-warning" id="submitBtn">Submit Report</button>
            </div>
        </div>
    </div>
</div>

<?php asset_script($appBase, 'js/pages/discipline.js'); ?>

