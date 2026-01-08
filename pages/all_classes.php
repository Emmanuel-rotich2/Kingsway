<?php
/**
 * All Classes Page
 * 
 * Purpose: View and manage all classes and streams
 * Features:
 * - List all classes with student counts
 * - Class capacity management
 * - Stream configuration
 */
?>

<div class="container-fluid py-4">
    <!-- Page Header -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h4 class="mb-1"><i class="fas fa-school me-2"></i>All Classes</h4>
                    <p class="text-muted mb-0">View and manage classes, streams, and student distribution</p>
                </div>
                <a href="home.php?route=manage_classes" class="btn btn-primary">
                    <i class="fas fa-cog me-1"></i> Manage Classes
                </a>
            </div>
        </div>
    </div>

    <!-- Overview Cards -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card bg-primary text-white">
                <div class="card-body text-center">
                    <h2 id="totalClasses">--</h2>
                    <p class="mb-0">Total Classes</p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-success text-white">
                <div class="card-body text-center">
                    <h2 id="totalStreams">--</h2>
                    <p class="mb-0">Total Streams</p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-info text-white">
                <div class="card-body text-center">
                    <h2 id="totalStudentsEnrolled">--</h2>
                    <p class="mb-0">Students Enrolled</p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-warning text-dark">
                <div class="card-body text-center">
                    <h2 id="avgClassSize">--</h2>
                    <p class="mb-0">Avg Class Size</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Classes Grid -->
    <div class="row" id="classesGrid">
        <div class="col-12 text-center py-5">
            <div class="spinner-border text-primary"></div>
            <p>Loading classes...</p>
        </div>
    </div>
</div>

<script src="js/pages/all_classes.js"></script>