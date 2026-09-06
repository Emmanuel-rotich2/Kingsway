<?php
/* PARTIAL — no DOCTYPE/html/head/body. Injected into app shell via fetch. */
/**
 * Discipline - Admin Layout
 * Full featured for System Admin, Director, Headteacher, Deputy Heads
 *
 * Features:
 * - Full sidebar
 * - 4 stat cards with trends
 * - Charts (cases by type, trend over time)
 * - Full data table with all columns
 * - All actions: View, Edit, Delete, Escalate, Close
 */
?>

<!-- Header Actions -->
<div class="header-actions" style="margin-bottom: 1rem;">
    <button class="btn btn-outline" onclick="DisciplineController.exportCases()">📥 Export</button>
    <button class="btn btn-primary" onclick="DisciplineController.showNewCaseModal()">➕ New Case</button>
</div>

<!-- Stats Row - 4 cards -->
<div class="admin-stats-grid">
    <div class="stat-card">
        <div class="stat-icon bg-warning">📋</div>
        <div class="stat-content">
            <span class="stat-value" id="totalCases">0</span>
            <span class="stat-label">Total Cases</span>
            <span class="stat-trend" id="casesTrend">This Term</span>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon bg-danger">🔴</div>
        <div class="stat-content">
            <span class="stat-value" id="openCases">0</span>
            <span class="stat-label">Open</span>
            <span class="stat-trend" id="openTrend">Pending</span>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon bg-success">✅</div>
        <div class="stat-content">
            <span class="stat-value" id="resolvedCases">0</span>
            <span class="stat-label">Resolved</span>
            <span class="stat-trend up" id="resolvedTrend">-</span>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon bg-purple">⚠️</div>
        <div class="stat-content">
            <span class="stat-value" id="escalatedCases">0</span>
            <span class="stat-label">Escalated</span>
            <span class="stat-trend" id="escalatedTrend">Needs Attention</span>
        </div>
    </div>
</div>

<!-- Charts Row -->
<div class="admin-charts-row">
    <div class="chart-card chart-wide">
        <div class="chart-header">
            <h3>Cases Trend</h3>
            <select class="chart-filter" id="trendPeriod">
                <option value="term">This Term</option>
                <option value="year">This Year</option>
            </select>
        </div>
        <canvas id="trendChart" height="200"></canvas>
    </div>
    <div class="chart-card chart-narrow">
        <div class="chart-header">
            <h3>By Category</h3>
        </div>
        <canvas id="categoryChart" height="200"></canvas>
    </div>
</div>

<!-- Tabs -->
<div class="admin-tabs">
    <button class="tab-btn active" data-tab="all">All Cases</button>
    <button class="tab-btn" data-tab="open">Open</button>
    <button class="tab-btn" data-tab="escalated">Escalated</button>
    <button class="tab-btn" data-tab="resolved">Resolved</button>
</div>

<!-- Filters -->
<div class="admin-filters">
    <div class="filter-row">
        <select class="filter-select" id="filterCategory">
            <option value="">All Categories</option>
            <option value="misconduct">Misconduct</option>
            <option value="truancy">Truancy</option>
            <option value="fighting">Fighting</option>
            <option value="bullying">Bullying</option>
            <option value="substance">Substance Abuse</option>
            <option value="other">Other</option>
        </select>
        <select class="filter-select" id="filterSeverity">
            <option value="">All Severity</option>
            <option value="minor">Minor</option>
            <option value="moderate">Moderate</option>
            <option value="major">Major</option>
            <option value="critical">Critical</option>
        </select>
        <select class="filter-select" id="filterClass">
            <option value="">All Classes</option>
        </select>
        <input type="text" class="filter-search" id="searchCase"
            placeholder="🔍 Search by student or case ID...">
    </div>
</div>

<!-- Data Table -->
<div class="admin-table-card">
    <table class="admin-data-table" id="casesTable">
        <thead>
            <tr>
                <th scope="col">Case ID</th>
                <th scope="col">Date</th>
                <th scope="col">Student</th>
                <th scope="col">Class</th>
                <th scope="col">Category</th>
                <th scope="col">Description</th>
                <th scope="col">Severity</th>
                <th scope="col">Status</th>
                <th scope="col">Reported By</th>
                <th scope="col">Actions</th>
            </tr>
        </thead>
        <tbody id="casesTableBody">
            <!-- Data loaded dynamically -->
        </tbody>
    </table>

    <div class="table-footer">
        <div class="page-info">Showing <span id="showingCount">0</span> of <span id="totalCount">0</span></div>
        <div class="pagination" id="pagination"></div>
    </div>
</div>

<!-- New/Edit Case Modal -->
<div class="modal fade" id="caseModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-scrollable modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-warning text-dark">
                <h5 class="modal-title" id="caseModalTitle">New Discipline Case</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="caseForm">
                    <input type="hidden" id="caseId">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Student *</label>
                            <select class="form-select" id="studentId" required></select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Date of Incident *</label>
                            <input type="date" class="form-control" id="incidentDate" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Category *</label>
                            <select class="form-select" id="category" required>
                                <option value="">Select Category</option>
                                <option value="misconduct">Misconduct</option>
                                <option value="truancy">Truancy</option>
                                <option value="fighting">Fighting</option>
                                <option value="bullying">Bullying</option>
                                <option value="substance">Substance Abuse</option>
                                <option value="other">Other</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Severity *</label>
                            <select class="form-select" id="severity" required>
                                <option value="minor">Minor</option>
                                <option value="moderate">Moderate</option>
                                <option value="major">Major</option>
                                <option value="critical">Critical</option>
                            </select>
                        </div>
                        <div class="col-12 mb-3">
                            <label class="form-label">Description *</label>
                            <textarea class="form-control" id="description" rows="3" required></textarea>
                        </div>
                        <div class="col-12 mb-3">
                            <label class="form-label">Action Taken</label>
                            <textarea class="form-control" id="actionTaken" rows="2"></textarea>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Witnesses</label>
                            <input type="text" class="form-control" id="witnesses" placeholder="Names of witnesses">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Parent Notified</label>
                            <select class="form-select" id="parentNotified">
                                <option value="no">No</option>
                                <option value="yes">Yes</option>
                            </select>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button class="btn btn-warning" id="saveCaseBtn">Save Case</button>
            </div>
        </div>
    </div>
</div>

<?php asset_script($appBase, 'js/pages/discipline.js'); ?>

