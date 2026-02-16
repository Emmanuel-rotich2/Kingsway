<?php
/**
 * Report Cards Page
 * HTML structure only - logic in js/pages/report_cards.js
 * Embedded in app_layout.php
 *
 * Role-based access:
 * - Class Teacher: Generate and manage report cards for own class
 * - Headteacher: View all, approve, and sign report cards
 * - Admin: Full access
 */
?>

<div class="container-fluid py-4">
    <!-- Page Header -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h4 class="mb-1"><i class="fas fa-file-alt me-2"></i>Report Cards</h4>
                    <p class="text-muted mb-0">Generate, manage, and distribute student report cards</p>
                </div>
                <div class="btn-group">
                    <button class="btn btn-primary btn-sm" id="generateAllBtn"
                            data-role="class_teacher,headteacher,admin">
                        <i class="bi bi-file-earmark-plus me-1"></i> Generate All
                    </button>
                    <button class="btn btn-outline-primary btn-sm" id="downloadAllBtn"
                            data-role="class_teacher,headteacher,admin">
                        <i class="bi bi-download me-1"></i> Download All
                    </button>
                    <button class="btn btn-outline-primary btn-sm" id="printAllBtn"
                            data-role="class_teacher,headteacher,admin">
                        <i class="bi bi-printer me-1"></i> Print All
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- KPI Summary Cards -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card border-primary">
                <div class="card-body text-center">
                    <h6 class="text-muted mb-2">Total Students</h6>
                    <h3 class="text-primary mb-0" id="totalStudents">0</h3>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-success">
                <div class="card-body text-center">
                    <h6 class="text-muted mb-2">Cards Generated</h6>
                    <h3 class="text-success mb-0" id="cardsGenerated">0</h3>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-warning">
                <div class="card-body text-center">
                    <h6 class="text-muted mb-2">Pending</h6>
                    <h3 class="text-warning mb-0" id="cardsPending">0</h3>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-info">
                <div class="card-body text-center">
                    <h6 class="text-muted mb-2">Downloaded</h6>
                    <h3 class="text-info mb-0" id="cardsDownloaded">0</h3>
                </div>
            </div>
        </div>
    </div>

    <!-- Filter / Search Row -->
    <div class="card mb-4">
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-3">
                    <label class="form-label">Term</label>
                    <select class="form-select" id="termFilter">
                        <option value="">All Terms</option>
                        <option value="1">Term 1</option>
                        <option value="2">Term 2</option>
                        <option value="3">Term 3</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Class</label>
                    <select class="form-select" id="classFilter">
                        <option value="">All Classes</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Search Student</label>
                    <input type="text" class="form-control" id="searchBox"
                           placeholder="Search by name or admission number...">
                </div>
                <div class="col-md-2">
                    <label class="form-label">&nbsp;</label>
                    <button class="btn btn-primary w-100" id="loadBtn">
                        <i class="bi bi-search me-1"></i> Search
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Data Table -->
    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover" id="reportCardsTable">
                    <thead class="table-light">
                        <tr>
                            <th><input type="checkbox" id="selectAll"></th>
                            <th>Student Name</th>
                            <th>Admission No</th>
                            <th>Class</th>
                            <th>Average Score</th>
                            <th>Rank</th>
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
                <ul class="pagination justify-content-center" id="pagination">
                    <!-- Dynamic pagination -->
                </ul>
            </nav>
        </div>
    </div>
</div>

<script src="/Kingsway/js/pages/report_cards.js"></script>
