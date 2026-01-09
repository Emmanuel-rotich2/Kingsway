<?php
/**
 * Subject Teacher Dashboard Component
 * 
 * Purpose: SUBJECT-CENTRIC TEACHING & ASSESSMENT
 * - Manage teaching across multiple classes
 * - Track exam schedules and supervision
 * - Grade and manage assessments
 * - Plan lessons
 * 
 * Role: Subject Teacher (Role ID: 8)
 * Update Frequency: Daily
 * 
 * Summary Cards (6):
 * 1. Classes Teaching - Total students across sections
 * 2. Sections - Classes teaching
 * 3. Assessments Due - Pending grading tasks
 * 4. Graded This Week - Assessments completed
 * 5. Exam Schedule - Upcoming exams
 * 6. Lesson Plans - Created this term
 * 
 * Charts (2):
 * 1. Assessment Trend (weekly)
 * 2. Class Performance Comparison
 * 
 * Tables (2):
 * 1. Pending Assessments
 * 2. Exam Schedule
 */
require_once __DIR__ . '/../../components/global/dashboard_base.php';
?>

<div class="container-fluid py-4" id="subject-teacher-dashboard">
    <!-- Dashboard Header -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h4 class="mb-1">Subject Teaching Dashboard</h4>
                    <p class="text-muted mb-0">Manage assessments, exams, and lesson plans across your classes</p>
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

<script src="/Kingsway/js/dashboards/subject_teacher_dashboard.js"></script>
