<?php
/**
 * Fee Structure - Headteacher/Viewer Layout
 * For Headteacher, Deputy Headteacher, HODs
 * 
 * Features:
 * - Minimal sidebar
 * - 3 stat cards (overview only)
 * - 1 chart (fee structure summary)
 * - Read-only data table
 * - Actions: View Details, Export (no Create, Edit, or Delete)
 * - Focus on oversight and reporting
 */
?>

<!-- Fee Structure Viewer Content -->

<!-- Page Header -->
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h2 class="mb-1"><i class="bi bi-clipboard-data"></i> Fee Structure Overview</h2>
        <p class="text-muted mb-0">View fee structures across all classes and levels</p>
    </div>
    <div>
        <button class="btn btn-outline-secondary btn-sm" onclick="exportReport()">
            <i class="bi bi-download"></i> Export Report
        </button>
        <button class="btn btn-outline-secondary btn-sm" onclick="printSummary()">
            <i class="bi bi-printer"></i> Print Summary
        </button>
    </div>
</div>

<!-- Stats Row -->
<div class="row g-3 mb-4">
    <div class="col-md-4">
        <div class="card border-primary">
            <div class="card-body text-center">
                <h6 class="text-muted mb-2"><i class="bi bi-clipboard-data"></i> Active Fee Structures</h6>
                <h3 class="text-primary mb-0" id="activeStructures">0</h3>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card border-success">
            <div class="card-body text-center">
                <h6 class="text-muted mb-2"><i class="bi bi-cash-coin"></i> Total Expected Revenue</h6>
                <h3 class="text-success mb-0" id="totalExpectedRevenue">KES 0</h3>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card border-info">
            <div class="card-body text-center">
                <h6 class="text-muted mb-2"><i class="bi bi-people"></i> Students Covered</h6>
                <h3 class="text-info mb-0" id="totalStudents">0</h3>
            </div>
        </div>
    </div>
</div>

<!-- Chart -->
<div class="card mb-3">
    <div class="card-body">
        <h5 class="card-title"><i class="bi bi-bar-chart"></i> Fee Structure Distribution by Class</h5>
        <canvas id="feeDistributionChart" height="300"></canvas>
    </div>
</div>

<!-- Filters -->
<div class="card mb-3">
    <div class="card-body">
        <div class="row g-2">
            <div class="col-md-2">
                <select class="form-select form-select-sm" id="academicYearFilter">
                    <option value="">All Academic Years</option>
                </select>
            </div>
            <div class="col-md-2">
                <select class="form-select form-select-sm" id="termFilter">
                    <option value="">All Terms</option>
                    <option value="1">Term 1</option>
                    <option value="2">Term 2</option>
                    <option value="3">Term 3</option>
                </select>
            </div>
            <div class="col-md-2">
                <select class="form-select form-select-sm" id="levelFilter">
                    <option value="">All Levels</option>
                </select>
            </div>
            <div class="col-md-2">
                <select class="form-select form-select-sm" id="classFilter">
                    <option value="">All Classes</option>
                </select>
            </div>
            <div class="col-md-3">
                <input type="text" class="form-control form-control-sm" id="searchInput" placeholder="Search...">
            </div>
            <div class="col-md-1">
                <button class="btn btn-outline-secondary btn-sm w-100" onclick="clearFilters()">Clear</button>
            </div>
        </div>
    </div>
</div>

<!-- Data Table -->
<div class="card mb-3">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover" id="feeStructuresTable">
                <thead>
                    <tr>
                        <th>Academic Year</th>
                        <th>Level</th>
                        <th>Class</th>
                        <th>Term</th>
                        <th>Total Fees</th>
                        <th>Students</th>
                        <th>Expected Revenue</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody id="feeStructuresBody">
                    <tr>
                        <td colspan="9" class="loading-row">Loading fee structures...</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <div class="d-flex justify-content-between align-items-center mt-3">
            <span class="text-muted" id="paginationInfo">Showing 0 of 0</span>
            <div id="paginationControls"></div>
        </div>
    </div>
</div>

<!-- Summary Section -->
<div class="card">
    <div class="card-body">
        <h5 class="card-title"><i class="bi bi-graph-up"></i> Summary</h5>
        <div class="row g-3">
            <div class="col-md-3">
                <div class="text-center">
                    <div class="text-muted">Total Fee Structures</div>
                    <div class="h4 mb-0" id="summaryTotal">0</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="text-center">
                    <div class="text-muted">Active Structures</div>
                    <div class="h4 mb-0 text-success" id="summaryActive">0</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="text-center">
                    <div class="text-muted">Total Expected Revenue</div>
                    <div class="h4 mb-0 text-primary" id="summaryRevenue">KES 0</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="text-center">
                    <div class="text-muted">Average Fee per Student</div>
                    <div class="h4 mb-0" id="summaryAverage">KES 0</div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- View Fee Structure Details Modal (Read-Only) -->
<div class="modal" id="viewFeeStructureModal">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">Fee Structure Details</h3>
                <button class="btn-close" onclick="closeModal('viewFeeStructureModal')">×</button>
            </div>
            <div class="modal-body" id="viewModalBody">
                <!-- Read-only details -->
                <div id="structureDetails"></div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" onclick="closeModal('viewFeeStructureModal')">Close</button>
                <button class="btn btn-outline" onclick="printStructure()">
                    <i class="bi bi-printer"></i> Print
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Export Options Modal -->
<div class="modal" id="exportModal">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">Export Fee Structures</h3>
                <button class="btn-close" onclick="closeModal('exportModal')">×</button>
            </div>
            <div class="modal-body">
                <div class="form-group">
                    <label>Export Format</label>
                    <select class="form-select" id="exportFormat">
                        <option value="pdf">PDF Report</option>
                        <option value="excel">Excel Spreadsheet</option>
                        <option value="csv">CSV File</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Include:</label>
                    <div class="checkbox-group">
                        <label><input type="checkbox" checked> Fee Items Breakdown</label>
                        <label><input type="checkbox" checked> Student Count</label>
                        <label><input type="checkbox" checked> Revenue Projection</label>
                        <label><input type="checkbox"> Charts & Graphs</label>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" onclick="closeModal('exportModal')">Cancel</button>
                <button class="btn btn-primary" onclick="confirmExport()">
                    <i class="bi bi-download"></i> Export
                </button>
            </div>
        </div>
    </div>
</div>

<script src="/Kingsway/js/pages/fee_structure_viewer.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        if (typeof window.FeeStructureViewerController !== 'undefined') {
            window.FeeStructureViewerController.init();
        } else {
            console.error('FeeStructureViewerController not found');
        }
    });
</script>