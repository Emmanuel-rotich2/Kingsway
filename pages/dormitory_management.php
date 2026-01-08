<?php
/**
 * Dormitory Management Page
 * 
 * Purpose: Manage boarding dormitories
 * Features:
 * - Dormitory allocation
 * - Bed assignments
 * - Capacity tracking
 */
?>

<div class="container-fluid py-4">
    <!-- Page Header -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h4 class="mb-1"><i class="fas fa-bed me-2"></i>Dormitory Management</h4>
                    <p class="text-muted mb-0">Manage dormitories, bed allocation, and boarding students</p>
                </div>
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addDormModal">
                    <i class="fas fa-plus me-1"></i> Add Dormitory
                </button>
            </div>
        </div>
    </div>

    <!-- Stats -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card bg-primary text-white">
                <div class="card-body text-center">
                    <h2 id="totalDorms">--</h2>
                    <p class="mb-0">Dormitories</p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-success text-white">
                <div class="card-body text-center">
                    <h2 id="totalBeds">--</h2>
                    <p class="mb-0">Total Beds</p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-info text-white">
                <div class="card-body text-center">
                    <h2 id="occupiedBeds">--</h2>
                    <p class="mb-0">Occupied</p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-warning text-dark">
                <div class="card-body text-center">
                    <h2 id="availableBeds">--</h2>
                    <p class="mb-0">Available</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Dormitories Grid -->
    <div class="row" id="dormsGrid">
        <div class="col-12 text-center py-5">
            <div class="spinner-border text-primary"></div>
            <p>Loading dormitories...</p>
        </div>
    </div>
</div>

<!-- Add Dormitory Modal -->
<div class="modal fade" id="addDormModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add Dormitory</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="addDormForm">
                    <div class="mb-3">
                        <label class="form-label">Dormitory Name</label>
                        <input type="text" class="form-control" name="name" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Gender</label>
                        <select class="form-select" name="gender" required>
                            <option value="">Select Gender</option>
                            <option value="male">Boys</option>
                            <option value="female">Girls</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Capacity (Beds)</label>
                        <input type="number" class="form-control" name="capacity" min="1" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Dorm Master/Mistress</label>
                        <select class="form-select" name="warden_id">
                            <option value="">Select Staff</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Description</label>
                        <textarea class="form-control" name="description" rows="2"></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" form="addDormForm" class="btn btn-primary">Add Dormitory</button>
            </div>
        </div>
    </div>
</div>

<script src="js/pages/dormitory_management.js"></script>