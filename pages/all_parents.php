<?php
/**
 * All Parents/Guardians Page
 * 
 * Purpose: View and manage all parents/guardians
 * Features:
 * - List all parents with their children
 * - Communication history
 * - Fee status overview
 */
?>

<div class="container-fluid py-4">
    <!-- Page Header -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h4 class="mb-1"><i class="fas fa-users me-2"></i>Parents & Guardians</h4>
                    <p class="text-muted mb-0">Manage parent/guardian information and communications</p>
                </div>
                <div class="btn-group">
                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addParentModal">
                        <i class="fas fa-plus me-1"></i> Add Parent
                    </button>
                    <button class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#bulkMessageModal">
                        <i class="fas fa-envelope me-1"></i> Send Message
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Stats -->
    <div class="row mb-4">
        <div class="col-md-4">
            <div class="card bg-primary text-white">
                <div class="card-body text-center">
                    <h2 id="totalParents">--</h2>
                    <p class="mb-0">Total Parents</p>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card bg-success text-white">
                <div class="card-body text-center">
                    <h2 id="activeParents">--</h2>
                    <p class="mb-0">With Active Students</p>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card bg-info text-white">
                <div class="card-body text-center">
                    <h2 id="ptaMembers">--</h2>
                    <p class="mb-0">PTA Members</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Parents Table -->
    <div class="card">
        <div class="card-header">
            <div class="row g-2">
                <div class="col-md-4">
                    <input type="text" class="form-control" id="searchParent"
                        placeholder="Search parent name or phone...">
                </div>
                <div class="col-md-3">
                    <select class="form-select" id="filterByClass">
                        <option value="">All Classes</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <select class="form-select" id="filterByFeeStatus">
                        <option value="">All Fee Status</option>
                        <option value="cleared">Cleared</option>
                        <option value="balance">Has Balance</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <button class="btn btn-outline-secondary w-100" id="exportParents">
                        <i class="fas fa-download"></i> Export
                    </button>
                </div>
            </div>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover" id="parentsTable">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Phone</th>
                            <th>Email</th>
                            <th>Children</th>
                            <th>Fee Status</th>
                            <th>Last Contact</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td colspan="7" class="text-center">Loading parents...</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script src="js/pages/all_parents.js"></script>