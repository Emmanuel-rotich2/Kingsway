<?php
/**
 * View Past Papers Page
 * Purpose: Intern-specific read-only view of past exam papers
 * Features: Browse and download past papers (read-only)
 * Block 4: Teaching Delivery
 * Role: Intern (9)
 */

?>

<div class="card shadow-sm">
    <div class="card-header bg-gradient bg-secondary text-white">
        <div class="d-flex justify-content-between align-items-center">
            <h4 class="mb-0">
                <i class="bi bi-files"></i> Past Papers
            </h4>
            <div class="btn-group">
                <button class="btn btn-light btn-sm" data-kw-onclick="ViewPastPapersController.refresh()">
                    <i class="bi bi-arrow-clockwise"></i> Refresh
                </button>
            </div>
        </div>
    </div>
    
    <div class="card-body">
        <!-- Statistics Cards -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="card border-primary">
                    <div class="card-body text-center">
                        <h6 class="text-muted mb-2">Total Papers</h6>
                        <h3 class="text-primary mb-0" id="totalPapersCount">0</h3>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card border-success">
                    <div class="card-body text-center">
                        <h6 class="text-muted mb-2">Mid-Term</h6>
                        <h3 class="text-success mb-0" id="midtermCount">0</h3>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card border-info">
                    <div class="card-body text-center">
                        <h6 class="text-muted mb-2">End-Term</h6>
                        <h3 class="text-info mb-0" id="endtermCount">0</h3>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card border-warning">
                    <div class="card-body text-center">
                        <h6 class="text-muted mb-2">Mock/KNEC</h6>
                        <h3 class="text-warning mb-0" id="mockCount">0</h3>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filters -->
        <div class="row mb-3">
            <div class="col-md-3">
                <input type="text" id="searchInput" class="form-control" placeholder="Search papers...">
            </div>
            <div class="col-md-3">
                <select id="subjectFilter" class="form-select">
                    <option value="">All Subjects</option>
                </select>
            </div>
            <div class="col-md-2">
                <select id="yearFilter" class="form-select">
                    <option value="">All Years</option>
                </select>
            </div>
            <div class="col-md-2">
                <select id="typeFilter" class="form-select">
                    <option value="">All Types</option>
                    <option value="Mid-Term">Mid-Term</option>
                    <option value="End-Term">End-Term</option>
                    <option value="Mock">Mock</option>
                    <option value="KNEC">KNEC</option>
                </select>
            </div>
            <div class="col-md-2">
                <button class="btn btn-primary w-100" data-kw-onclick="ViewPastPapersController.filter()">
                    <i class="bi bi-search"></i> Search
                </button>
            </div>
        </div>

        <!-- Papers Table -->
        <div class="table-responsive">
            <table class="table table-hover table-striped" id="papersTable">
                <thead class="table-light">
                    <tr>
                        <th scope="col">#</th>
                        <th scope="col">Title</th>
                        <th scope="col">Subject</th>
                        <th scope="col">Year</th>
                        <th scope="col">Class Level</th>
                        <th scope="col">Type</th>
                        <th scope="col">Uploaded By</th>
                        <th scope="col">Date</th>
                        <th scope="col">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td colspan="9" class="text-center py-4">
                            <div class="spinner-border text-primary" role="status">
                                <span class="visually-hidden">Loading...</span>
                            </div>
                            <p class="text-muted mt-2">Loading past papers...</p>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php asset_script($appBase, 'js/pages/view_past_papers.js'); ?>
