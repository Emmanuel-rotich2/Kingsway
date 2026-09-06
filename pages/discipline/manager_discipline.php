<?php
/**
 * Discipline - Manager Layout
 * Compact layout for HODs, Boarding Master, Counselors
 *
 * Features:
 * - 3 stat cards
 * - 2 charts
 * - Standard table (7 columns)
 * - Can report and manage cases in their department
 */
/* PARTIAL — no DOCTYPE/html/head/body. Injected into app shell via fetch. */
?>

<!-- Stats - 3 columns -->
<div class="manager-stats-grid">
    <div class="manager-stat-card">
        <div class="stat-icon">📋</div>
        <div class="stat-info">
            <span class="stat-value" id="totalCases">0</span>
            <span class="stat-label">Total</span>
        </div>
    </div>
    <div class="manager-stat-card">
        <div class="stat-icon">🔴</div>
        <div class="stat-info">
            <span class="stat-value" id="openCases">0</span>
            <span class="stat-label">Open</span>
        </div>
    </div>
    <div class="manager-stat-card">
        <div class="stat-icon">✅</div>
        <div class="stat-info">
            <span class="stat-value" id="resolvedCases">0</span>
            <span class="stat-label">Resolved</span>
        </div>
    </div>
</div>

<!-- Charts - 2 columns -->
<div class="manager-charts-row">
    <div class="manager-chart-card">
        <h4>Weekly Trend</h4>
        <canvas id="trendChart" height="180"></canvas>
    </div>
    <div class="manager-chart-card">
        <h4>By Category</h4>
        <canvas id="categoryChart" height="180"></canvas>
    </div>
</div>

<!-- Filters -->
<div class="manager-filters">
    <select class="filter-select" id="filterCategory">
        <option value="">All Categories</option>
        <option value="misconduct">Misconduct</option>
        <option value="truancy">Truancy</option>
        <option value="fighting">Fighting</option>
        <option value="bullying">Bullying</option>
    </select>
    <select class="filter-select" id="filterStatus">
        <option value="">All Status</option>
        <option value="open">Open</option>
        <option value="resolved">Resolved</option>
    </select>
    <input type="text" class="filter-search" id="searchCase" placeholder="🔍 Search...">
    <button class="btn btn-primary btn-sm" onclick="DisciplineController.showNewCaseModal()">➕ Report Case</button>
</div>

<!-- Table - 7 columns -->
<div class="manager-table-card">
    <table class="manager-data-table" id="casesTable">
        <thead>
            <tr>
                <th scope="col">Date</th>
                <th scope="col">Student</th>
                <th scope="col">Class</th>
                <th scope="col">Category</th>
                <th scope="col">Severity</th>
                <th scope="col">Status</th>
                <th scope="col">Actions</th>
            </tr>
        </thead>
        <tbody id="casesTableBody">
            <!-- Data loaded dynamically -->
        </tbody>
    </table>

    <div class="table-footer">
        <span class="page-info">Showing <span id="showingCount">0</span> cases</span>
    </div>
</div>

<!-- Report Case Modal -->
<div class="modal fade" id="caseModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header bg-warning text-dark">
                <h5 class="modal-title">Report Discipline Case</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="caseForm">
                    <div class="mb-3">
                        <label class="form-label">Student *</label>
                        <select class="form-select" id="studentId" required></select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Category *</label>
                        <select class="form-select" id="category" required>
                            <option value="">Select</option>
                            <option value="misconduct">Misconduct</option>
                            <option value="truancy">Truancy</option>
                            <option value="fighting">Fighting</option>
                            <option value="bullying">Bullying</option>
                            <option value="other">Other</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Severity</label>
                        <select class="form-select" id="severity">
                            <option value="minor">Minor</option>
                            <option value="moderate">Moderate</option>
                            <option value="major">Major</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Description *</label>
                        <textarea class="form-control" id="description" rows="3" required></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Action Taken</label>
                        <textarea class="form-control" id="actionTaken" rows="2"></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button class="btn btn-warning" id="saveCaseBtn">Submit</button>
            </div>
        </div>
    </div>
</div>

<?php asset_script($appBase, 'js/pages/discipline.js'); ?>

