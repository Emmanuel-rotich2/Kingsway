<?php
/**
 * Enter Results Page
 * HTML structure only - all logic in js/pages/academic.js (enterResultsController)
 * Embedded in app_layout.php
 */
?>

<div class="card shadow">
    <div class="card-header bg-primary text-white">
        <h2 class="mb-0">📝 Enter Student Results</h2>
    </div>
    <div class="card-body">
        <form id="resultsForm">
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label">Class</label>
                    <select id="classSelect" class="form-select" required onchange="enterResultsController.loadStudents()">
                        <option value="">-- Select Class --</option>
                    </select>
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">Subject</label>
                    <select id="subjectSelect" class="form-select" required>
                        <option value="">-- Select Subject --</option>
                    </select>
                </div>
            </div>

            <div class="row">
                <div class="col-md-4 mb-3">
                    <label class="form-label">Term</label>
                    <select id="termSelect" class="form-select" required>
                        <option value="">-- Select Term --</option>
                        <option value="Term 1">Term 1</option>
                        <option value="Term 2">Term 2</option>
                        <option value="Term 3">Term 3</option>
                    </select>
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label">Academic Year</label>
                    <select id="yearSelect" class="form-select" required>
                        <option value="">-- Select Year --</option>
                    </select>
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label">Assessment Type</label>
                    <select id="assessmentType" class="form-select" required>
                        <option value="">-- Select Type --</option>
                        <option value="CAT">CAT</option>
                        <option value="Exam">End of Term Exam</option>
                        <option value="Assignment">Assignment</option>
                    </select>
                </div>
            </div>

            <div id="studentsContainer" class="mt-4">
                <p class="text-muted">Please select a class to load students</p>
            </div>

            <button type="submit" class="btn btn-primary btn-lg w-100 mt-3">Submit Results</button>
        </form>
    </div>
</div>
