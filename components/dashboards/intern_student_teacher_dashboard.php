<?php
/**
 * Intern/Student Teacher Dashboard Component
 * 
 * Purpose: INTERN/STUDENT TEACHER LEARNING DASHBOARD
 * - Track lesson observations
 * - Monitor teaching resources
 * - View assigned classes
 * - Track competency development
 * 
 * Role: Intern/Student Teacher (Read-only)
 * Update Frequency: Daily
 * 
 * Data Isolation: Assigned classes only (no full school data)
 * 
 * Summary Cards (5):
 * 1. Assigned Classes
 * 2. Lesson Observations
 * 3. Teaching Resources
 * 4. Student Performance
 * 5. Development Progress
 * 
 * Charts: Limited (observation feedback trends)
 * 
 * Tables (3):
 * 1. Assigned Classes
 * 2. Observations
 * 3. Competencies
 */
require_once __DIR__ . '/../../components/global/dashboard_base.php';
?>

<div class="container-fluid py-4" id="intern-dashboard">
    <!-- Dashboard Header -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h4 class="mb-1">Learning & Development Dashboard</h4>
                    <p class="text-muted mb-0">Track your teaching journey, observations, and competency development</p>
                </div>
                <div class="d-flex align-items-center gap-2">
                    <span class="text-muted small">Last updated: <span id="lastRefreshTime">--</span></span>
                    <button class="btn btn-outline-primary btn-sm" id="refreshDashboardBtn">
                        <i class="bi bi-arrow-clockwise"></i> Refresh
                    </button>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Loading Indicator -->
    <div id="dashboardLoading" class="text-center py-5" style="display: none;">
        <div class="spinner-border text-primary" role="status">
            <span class="visually-hidden">Loading...</span>
        </div>
        <p class="mt-2 text-muted">Loading dashboard data...</p>
    </div>
    
    <!-- Summary Cards Container -->
    <div class="row g-3 mb-4" id="summaryCardsContainer">
        <!-- Cards will be dynamically rendered by JS -->
    </div>

    <!-- Charts Row -->
    <div id="chartsContainer" class="row mt-4">
        <!-- Charts will be dynamically rendered by JS -->
    </div>

    <!-- Tables Row -->
    <div id="tablesContainer" class="row mt-4">
        <!-- Tables will be dynamically rendered by JS -->
    </div>
</div>

<script src="/Kingsway/js/dashboards/intern_student_teacher_dashboard.js"></script>
