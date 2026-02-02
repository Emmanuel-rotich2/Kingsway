<?php
/**
 * Fee Structure - Accountant Layout
 * For School Accountant, Bursar
 * 
 * Features:
 * - Collapsible sidebar (80px → 240px)
 * - 4 stat cards (focused on revenue & collections)
 * - 2 charts (revenue tracking, payment status)
 * - Data table with accountant-relevant columns
 * - Actions: Create, Edit, Duplicate, Export (no Delete, Approve requires request)
 * - Revenue tracking and reconciliation tools
 */
?>

<!-- Fee Structure Accountant Content -->

<!-- Page Header -->
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h2 class="mb-1"><i class="bi bi-clipboard-data"></i> Fee Structure Management</h2>
        <p class="text-muted mb-0">Manage fee structures and track revenue projections</p>
    </div>
    <div>
        <button class="btn btn-outline-secondary btn-sm" onclick="exportToExcel()"><i class="bi bi-download"></i> Export
            to Excel</button>
        <button class="btn btn-success btn-sm" onclick="showDuplicateForNewYear()"><i class="bi bi-files"></i> Duplicate
            for New Year</button>
        <button class="btn btn-primary btn-sm" onclick="showCreateStructureModal()"><i class="bi bi-plus-circle"></i>
            Create Structure</button>
    </div>
</div>

<!-- Stats Row - 4 cards for Accountant -->
<div class="row g-3 mb-4">
    <div class="col-md-3">
        <div class="card border-primary">
            <div class="card-body text-center">
                <h6 class="text-muted mb-2"><i class="bi bi-clipboard-data"></i> Active Structures</h6>
                <h3 class="text-primary mb-0" id="activeStructures">0</h3>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-success">
            <div class="card-body text-center">
                <h6 class="text-muted mb-2"><i class="bi bi-cash-coin"></i> Expected Revenue</h6>
                <h3 class="text-success mb-0" id="expectedRevenue">KES 0</h3>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-info">
            <div class="card-body text-center">
                <h6 class="text-muted mb-2"><i class="bi bi-cash"></i> Amount Collected</h6>
                <h3 class="text-info mb-0" id="collectedAmount">KES 0</h3>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-warning">
            <div class="card-body text-center">
                <h6 class="text-muted mb-2"><i class="bi bi-graph-up"></i> Collection Rate</h6>
                <h3 class="text-warning mb-0" id="collectionRate">0%</h3>
            </div>
        </div>
    </div>
</div>

<!-- Charts Row -->
<div class="row g-3 mb-4">
    <div class="col-md-6">
        <div class="card">
            <div class="card-body">
                <h5 class="card-title"><i class="bi bi-cash-coin"></i> Revenue vs Collections by Class</h5>
                <div style="position: relative; height: 300px; max-height: 300px; overflow: hidden;">
                    <canvas id="revenueCollectionsChart"></canvas>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card">
            <div class="card-body">
                <h5 class="card-title"><i class="bi bi-pie-chart"></i> Payment Status Distribution</h5>
                <div style="position: relative; height: 300px; max-height: 300px; overflow: hidden;">
                    <canvas id="paymentStatusChart"></canvas>
                </div>
            </div>
        </div>
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
                </select>
            </div>
            <div class="col-md-2">
                <select class="form-select form-select-sm" id="schoolLevelFilter">
                    <option value="">All Levels</option>
                </select>
            </div>
            <div class="col-md-2">
                <select class="form-select form-select-sm" id="classFilter">
                    <option value="">All Classes</option>
                </select>
            </div>
            <div class="col-md-2">
                <select class="form-select form-select-sm" id="statusFilter">
                    <option value="">All Status</option>
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

<!-- Quick Actions -->
<div class="d-flex gap-2 mb-3">
    <button class="btn btn-outline-primary btn-sm" onclick="reconcileFees()"><i class="bi bi-arrow-repeat"></i>
        Reconcile Fees</button>
    <button class="btn btn-outline-warning btn-sm" onclick="viewDefaulters()"><i class="bi bi-exclamation-triangle"></i>
        View Defaulters</button>
    <button class="btn btn-outline-info btn-sm" onclick="generateInvoices()"><i class="bi bi-receipt"></i> Generate
        Invoices</button>
    <button class="btn btn-outline-secondary btn-sm" onclick="sendReminders()"><i class="bi bi-bell"></i> Send
        Reminders</button>
</div>

<!-- Data Table -->
<div class="card">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover" id="feeStructuresTable">
                <thead>
                    <tr>
                        <th style="width: 10%;">Academic Year</th>
                        <th style="width: 12%;">Term</th>
                        <th style="width: 15%;">School Level</th>
                        <th style="width: 15%;">Classes</th>
                        <th style="width: 12%;" class="text-end">Total Fees</th>
                        <th style="width: 12%;" class="text-end">Expected</th>
                        <th style="width: 12%;">Collection</th>
                        <th style="width: 12%;">Actions</th>
                    </tr>
                </thead>
                <tbody id="feeStructuresBody">
                    <tr>
                        <td colspan="8" class="loading-row">Loading fee structures...</td>
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

<!-- Create/Edit Fee Structure Modal -->
<div class="modal" id="feeStructureModal">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title" id="modalTitle">Create Fee Structure</h3>
                <button class="btn-close" onclick="closeModal('feeStructureModal')">×</button>
            </div>
            <div class="modal-body" id="modalBody">
                <!-- Form content -->
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" onclick="closeModal('feeStructureModal')">Cancel</button>
                <button class="btn btn-outline" onclick="saveDraft()"><i class="bi bi-save"></i> Save as Draft</button>
                <button class="btn btn-primary" onclick="saveAndSubmit()"><i class="bi bi-check-circle"></i> Save &
                    Submit for Approval</button>
            </div>
        </div>
    </div>
</div>

<!-- View Fee Structure Details Modal -->
<div class="modal" id="viewFeeStructureModal">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">Fee Structure Details</h3>
                <button class="btn-close" onclick="closeModal('viewFeeStructureModal')">×</button>
            </div>
            <div class="modal-body" id="viewModalBody">
                <!-- Details content -->
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" onclick="closeModal('viewFeeStructureModal')">Close</button>
                <button class="btn btn-outline" onclick="viewPaymentHistory()"><i class="bi bi-cash-stack"></i> Payment
                    History</button>
                <button class="btn btn-warning" onclick="editStructure()"><i class="bi bi-pencil"></i> Edit</button>
            </div>
        </div>
    </div>
</div>

<!-- Duplicate Modal -->
<div class="modal" id="duplicateModal">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">Duplicate Fee Structure</h3>
                <button class="btn-close" onclick="closeModal('duplicateModal')">×</button>
            </div>
            <div class="modal-body">
                <div class="form-group">
                    <label>Target Academic Year *</label>
                    <select class="form-select" id="duplicateYear">
                        <option value="">Select Year</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Price Adjustment (%)</label>
                    <input type="number" class="form-input" id="priceAdjustment" value="0" step="0.5">
                    <small class="form-text">Enter percentage increase (e.g., 5 for 5% increase)</small>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" onclick="closeModal('duplicateModal')">Cancel</button>
                <button class="btn btn-primary" onclick="confirmDuplicate()"><i class="bi bi-files"></i>
                    Duplicate</button>
            </div>
        </div>
    </div>
</div>

<!-- Reconciliation Modal -->
<div class="modal" id="reconciliationModal">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">Fee Reconciliation</h3>
                <button class="btn-close" onclick="closeModal('reconciliationModal')">×</button>
            </div>
            <div class="modal-body" id="reconciliationBody">
                <!-- Reconciliation data -->
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" onclick="closeModal('reconciliationModal')">Close</button>
                <button class="btn btn-success" onclick="exportReconciliation()"><i class="bi bi-download"></i> Export
                    Report</button>
            </div>
        </div>
    </div>
</div>

<!-- Detailed Fee Structure Breakdown Modal -->
<div class="modal" id="detailedFeeStructureModal">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h3 class="modal-title">Fee Structure Breakdown</h3>
                <button class="btn-close btn-close-white" onclick="closeModal('detailedFeeStructureModal')">×</button>
            </div>
            <div class="modal-body" id="detailedFeeBody" style="padding: 30px;">
                <!-- Will be populated dynamically -->
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" onclick="closeModal('detailedFeeStructureModal')">Close</button>
                <button class="btn btn-warning" onclick="window.accountantController.editDetailedStructure()">
                    <i class="bi bi-pencil"></i> Edit
                </button>
                <button class="btn btn-success" onclick="window.accountantController.exportToPDF()">
                    <i class="bi bi-file-pdf"></i> Export PDF
                </button>
            </div>
        </div>
    </div>
</div>

<style>
    /* Fee Invoice Styling */
    .fee-invoice {
        font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    }

    .fee-invoice h4 {
        font-weight: 700;
        margin-bottom: 10px;
    }

    .fee-invoice h5 {
        font-weight: 600;
        color: #333;
    }

    .fee-invoice .table {
        margin-top: 20px;
    }

    .fee-invoice .table thead th {
        background-color: #f8f9fa;
        font-weight: 600;
        border-bottom: 2px solid #dee2e6;
    }

    .fee-invoice .table tbody tr:hover {
        background-color: #f8f9fa;
    }

    .fee-invoice .table tfoot th {
        font-size: 1.1em;
        padding: 15px 10px;
        border-top: 3px solid #0d6efd;
    }

    .fee-invoice .bg-light {
        background-color: #e9ecef !important;
    }

    .fee-invoice ul {
        list-style: none;
        padding-left: 0;
    }

    .fee-invoice ul li {
        padding: 5px 0;
        border-bottom: 1px solid #e9ecef;
    }

    .fee-invoice ul li:last-child {
        border-bottom: none;
    }

    /* Merged cells table styling */
    #feeStructuresBody tr td {
        vertical-align: middle;
    }

    #feeStructuresBody tr td[rowspan] {
        background-color: #f8f9fa;
        font-weight: 600;
        border-right: 2px solid #dee2e6;
    }
</style>

<script src="/Kingsway/js/pages/fee_structure_accountant.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        if (typeof window.FeeStructureAccountantController !== 'undefined') {
            window.FeeStructureAccountantController.init();
        } else {
            console.error('FeeStructureAccountantController not found');
        }
    });
</script>