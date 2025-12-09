<?php
/**
 * View Results Page
 * HTML structure only - all logic in js/pages/academic.js (viewResultsController)
 * Embedded in app_layout.php
 */
?>

<div class="card shadow">
    <div class="card-header bg-info text-white">
        <h2 class="mb-0">📊 View Student Results</h2>
    </div>
    <div class="card-body">
        <form id="resultsSearchForm">
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label">Class</label>
                    <select id="classSelect" class="form-select" required onchange="viewResultsController.loadStudents()">
                        <option value="">-- Select Class --</option>
                    </select>
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">Student</label>
                    <select id="studentSelect" class="form-select" required onchange="viewResultsController.loadResults()">
                        <option value="">-- Select Student --</option>
                    </select>
                </div>
            </div>
        </form>

        <div id="resultsContainer" class="mt-4">
            <p class="text-muted">Select a student to view their results</p>
        </div>
    </div>
</div>

