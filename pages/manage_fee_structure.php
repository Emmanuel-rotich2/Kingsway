<?php
/**
 * Manage Fee Structure Page
 * Displays fee structures for different school levels, years, terms, and classes
 * HTML structure only - logic will be in js/pages/feeStructure.js
 * Embedded in app_layout.php
 */
?>

<div class="card shadow-sm">
    <div class="card-header bg-gradient bg-info text-white">
        <div class="d-flex justify-content-between align-items-center">
            <h4 class="mb-0"><i class="fas fa-file-invoice-dollar"></i> Fee Structure Management</h4>
            <div class="btn-group">
                <button class="btn btn-light btn-sm" id="addFeeStructureBtn" data-permission="fees_create">
                    <i class="bi bi-plus-circle"></i> Add Fee Structure
                </button>
                <button class="btn btn-outline-light btn-sm" id="exportFeesBtn">
                    <i class="bi bi-download"></i> Export
                </button>
            </div>
        </div>
    </div>

    <div class="card-body">
        <!-- Fee Overview Cards -->
        <div class="row mb-4">
            <div class="col-md-4">
                <div class="card border-primary">
                    <div class="card-body text-center">
                        <h6 class="text-muted mb-2">Total Fee Structures</h6>
                        <h3 class="text-primary mb-0" id="totalFeeStructures">0</h3>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card border-success">
                    <div class="card-body text-center">
                        <h6 class="text-muted mb-2">Active Structures</h6>
                        <h3 class="text-success mb-0" id="activeFeeStructures">0</h3>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card border-info">
                    <div class="card-body text-center">
                        <h6 class="text-muted mb-2">Total Expected Revenue</h6>
                        <h3 class="text-info mb-0" id="expectedRevenue">KES 0</h3>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filters -->
        <div class="row mb-3">
            <div class="col-md-3">
                <input type="text" class="form-control" id="feeSearch" placeholder="Search fee structures...">
            </div>
            <div class="col-md-2">
                <select class="form-select" id="levelFilter">
                    <option value="">All Levels</option>
                </select>
            </div>
            <div class="col-md-2">
                <select class="form-select" id="classFilter">
                    <option value="">All Classes</option>
                </select>
            </div>
            <div class="col-md-2">
                <select class="form-select" id="termFilter">
                    <option value="">All Terms</option>
                    <option value="1">Term 1</option>
                    <option value="2">Term 2</option>
                    <option value="3">Term 3</option>
                </select>
            </div>
            <div class="col-md-2">
                <select class="form-select" id="yearFilter">
                    <option value="">All Years</option>
                </select>
            </div>
            <div class="col-md-1">
                <select class="form-select" id="statusFilter">
                    <option value="">All Status</option>
                    <option value="active">Active</option>
                    <option value="inactive">Inactive</option>
                </select>
            </div>
        </div>

        <!-- Fee Structures Table -->
        <div class="table-responsive">
            <table class="table table-hover" id="feeStructuresTable">
                <thead class="table-light">
                    <tr>
                        <th>Fee Name</th>
                        <th>School Level</th>
                        <th>Class</th>
                        <th>Term</th>
                        <th>Year</th>
                        <th>Amount (KES)</th>
                        <th>Type</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <!-- Dynamic content -->
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <nav>
            <ul class="pagination justify-content-center" id="feeStructuresPagination">
                <!-- Dynamic pagination -->
            </ul>
        </nav>
    </div>
</div>

<!-- Fee Structure Modal -->
<div class="modal fade" id="feeStructureModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Fee Structure Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="feeStructureForm">
                    <input type="hidden" id="fee_structure_id">

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Fee Name*</label>
                            <input type="text" class="form-control" id="fee_name" required>
                        </div>

                        <div class="col-md-6 mb-3">
                            <label class="form-label">Fee Type*</label>
                            <select class="form-select" id="fee_type" required>
                                <option value="">Select Type</option>
                                <option value="tuition">Tuition Fee</option>
                                <option value="examination">Examination Fee</option>
                                <option value="activity">Activity Fee</option>
                                <option value="boarding">Boarding Fee</option>
                                <option value="transport">Transport Fee</option>
                                <option value="other">Other</option>
                            </select>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-3 mb-3">
                            <label class="form-label">School Level*</label>
                            <select class="form-select" id="fee_level" required></select>
                        </div>

                        <div class="col-md-3 mb-3">
                            <label class="form-label">Class*</label>
                            <select class="form-select" id="fee_class" required></select>
                        </div>

                        <div class="col-md-3 mb-3">
                            <label class="form-label">Term*</label>
                            <select class="form-select" id="fee_term" required>
                                <option value="">Select Term</option>
                                <option value="1">Term 1</option>
                                <option value="2">Term 2</option>
                                <option value="3">Term 3</option>
                            </select>
                        </div>

                        <div class="col-md-3 mb-3">
                            <label class="form-label">Academic Year*</label>
                            <input type="text" class="form-control" id="fee_academic_year" placeholder="2025" required>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Amount (KES)*</label>
                            <input type="number" class="form-control" id="fee_amount" step="0.01" required>
                        </div>

                        <div class="col-md-6 mb-3">
                            <label class="form-label">Status*</label>
                            <select class="form-select" id="fee_status" required>
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                            </select>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Description</label>
                        <textarea class="form-control" id="fee_description" rows="3"></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="saveFeeStructureBtn">Save Fee Structure</button>
            </div>
        </div>
    </div>
</div>

<script>
    // Initialize fee structure management when page loads
    document.addEventListener('DOMContentLoaded', function () {
        console.log('Fee Structure Management page loaded');
        // TODO: Implement feeStructureController in js/pages/feeStructure.js
    });
</script>