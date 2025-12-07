<?php
<<<<<<< HEAD
$conn = new mysqli("localhost", "root", "", "kingswayacademy");

$class = $_GET['class'] ?? '';
$student_id = $_GET['student_id'] ?? '';

$student = null;
$results = [];

if ($class && $student_id) {
    $stmt = $conn->prepare("SELECT * FROM students WHERE id = ? AND class = ?");
    $stmt->bind_param("is", $student_id, $class);
    $stmt->execute();
    $student = $stmt->get_result()->fetch_assoc();

    $stmt2 = $conn->prepare("SELECT * FROM results WHERE student_id = ?");
    $stmt2->bind_param("i", $student_id);
    $stmt2->execute();
    $results = $stmt2->get_result();
}
?>

<div style="max-width: 800px; margin: auto; padding: 20px;">
    <h2>Student Result Report</h2>

    <form method="get" style="margin-bottom: 20px;">
        <label>Select Class</label><br>
        <input type="text" name="class" value="<?= htmlspecialchars($class) ?>" required><br>
        <label>Student ID</label><br>
        <input type="number" name="student_id" value="<?= htmlspecialchars($student_id) ?>" required><br><br>
        <button type="submit">View Results</button>
    </form>

    <?php if ($student): ?>
        <h3><?= htmlspecialchars($student['name']) ?> - <?= htmlspecialchars($student['class']) ?></h3>
        <table border="1" width="100%" cellpadding="10">
            <tr>
                <th>Subject</th>
                <th>Marks</th>
            </tr>
            <?php while ($row = $results->fetch_assoc()): ?>
                <tr>
                    <td><?= htmlspecialchars($row['subject']) ?></td>
                    <td><?= htmlspecialchars($row['marks']) ?></td>
                </tr>
            <?php endwhile; ?>
        </table>
    <?php elseif ($class && $student_id): ?>
        <p>No student or results found for the provided ID and class.</p>
    <?php endif; ?>
</div>
=======
// Get class ID and subject from query string
$class_id = $_GET['class'] ?? null;
$subject_id = $_GET['subject'] ?? null;
$term = $_GET['term'] ?? null;
$exam_type = $_GET['exam_type'] ?? null;

// Get class details, subjects, and results from database
$class = []; // TODO: Replace with actual database query
$subjects = []; // TODO: Replace with actual database query
$results = []; // TODO: Replace with actual database query
?>

<div class="container-fluid">
    <h2 class="mb-4">View Results</h2>
    
    <?php if (!$class_id): ?>
        <div class="alert alert-warning">
            Please select a class first.
        </div>
    <?php else: ?>
        <div class="row mb-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-body">
                        <form id="filterForm" class="row g-3">
                            <div class="col-md-3">
                                <label class="form-label">Class</label>
                                <input type="text" class="form-control" value="<?php echo $class['name']; ?>" readonly>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Subject</label>
                                <select name="subject" class="form-select" required>
                                    <option value="">Choose Subject...</option>
                                    <?php foreach ($subjects as $subject): ?>
                                        <option value="<?php echo $subject['id']; ?>" <?php echo $subject_id == $subject['id'] ? 'selected' : ''; ?>>
                                            <?php echo $subject['name']; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">Term</label>
                                <select name="term" class="form-select" required>
                                    <option value="">Choose Term...</option>
                                    <option value="1" <?php echo $term == '1' ? 'selected' : ''; ?>>Term 1</option>
                                    <option value="2" <?php echo $term == '2' ? 'selected' : ''; ?>>Term 2</option>
                                    <option value="3" <?php echo $term == '3' ? 'selected' : ''; ?>>Term 3</option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">Exam Type</label>
                                <select name="exam_type" class="form-select" required>
                                    <option value="">Choose Type...</option>
                                    <option value="mid_term" <?php echo $exam_type == 'mid_term' ? 'selected' : ''; ?>>Mid Term</option>
                                    <option value="end_term" <?php echo $exam_type == 'end_term' ? 'selected' : ''; ?>>End Term</option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">&nbsp;</label>
                                <button type="submit" class="btn btn-primary d-block">
                                    <i class="bi bi-search"></i> View Results
                                </button>
                            </div>
    </form>
                    </div>
                </div>
            </div>
        </div>

        <?php if ($subject_id && $term && $exam_type): ?>
            <div class="card">
                <div class="card-header">
                    <div class="row align-items-center">
                        <div class="col">
                            <h5 class="mb-0">Results Summary</h5>
                        </div>
                        <div class="col-auto">
                            <button type="button" class="btn btn-success" onclick="exportToExcel()">
                                <i class="bi bi-file-excel"></i> Export to Excel
                            </button>
                            <button type="button" class="btn btn-danger" onclick="exportToPDF()">
                                <i class="bi bi-file-pdf"></i> Export to PDF
                            </button>
                        </div>
                    </div>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover" id="resultsTable">
                            <thead>
                                <tr>
                                    <th>Admission No</th>
                                    <th>Student Name</th>
                <th>Marks</th>
                                    <th>Grade</th>
                                    <th>Remarks</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($results as $result): ?>
                                    <tr>
                                        <td><?php echo $result['admission_no']; ?></td>
                                        <td><?php echo $result['student_name']; ?></td>
                                        <td><?php echo $result['marks']; ?></td>
                                        <td><?php echo $result['grade']; ?></td>
                                        <td><?php echo $result['remarks']; ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="row mt-4">
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-body">
                            <h5 class="card-title">Performance Distribution</h5>
                            <canvas id="gradesChart"></canvas>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-body">
                            <h5 class="card-title">Statistics</h5>
                            <table class="table">
                                <tbody>
                                    <tr>
                                        <th>Mean Score:</th>
                                        <td id="meanScore">0</td>
                                    </tr>
                                    <tr>
                                        <th>Highest Score:</th>
                                        <td id="highestScore">0</td>
                                    </tr>
                                    <tr>
                                        <th>Lowest Score:</th>
                                        <td id="lowestScore">0</td>
            </tr>
                <tr>
                                        <th>Standard Deviation:</th>
                                        <td id="stdDev">0</td>
                </tr>
                                </tbody>
        </table>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('filterForm');
    if (form) {
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            const formData = new FormData(this);
            const queryString = new URLSearchParams(formData).toString();
            window.location.href = '?route=view_results&' + queryString;
        });
    }

    // Initialize charts if results are shown
    const results = <?php echo json_encode($results); ?>;
    if (results && results.length > 0) {
        initializeCharts(results);
        calculateStatistics(results);
    }
});

function initializeCharts(results) {
    const grades = ['A', 'A-', 'B+', 'B', 'B-', 'C+', 'C', 'C-', 'D+', 'D', 'D-', 'E'];
    const gradeCount = grades.reduce((acc, grade) => {
        acc[grade] = results.filter(r => r.grade === grade).length;
        return acc;
    }, {});

    const ctx = document.getElementById('gradesChart').getContext('2d');
    new Chart(ctx, {
        type: 'bar',
        data: {
            labels: grades,
            datasets: [{
                label: 'Number of Students',
                data: grades.map(grade => gradeCount[grade]),
                backgroundColor: 'rgba(54, 162, 235, 0.5)',
                borderColor: 'rgba(54, 162, 235, 1)',
                borderWidth: 1
            }]
        },
        options: {
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        stepSize: 1
                    }
                }
            }
        }
    });
}

function calculateStatistics(results) {
    const marks = results.map(r => parseFloat(r.marks));
    const mean = marks.reduce((a, b) => a + b, 0) / marks.length;
    const highest = Math.max(...marks);
    const lowest = Math.min(...marks);
    const variance = marks.reduce((a, b) => a + Math.pow(b - mean, 2), 0) / marks.length;
    const stdDev = Math.sqrt(variance);

    document.getElementById('meanScore').textContent = mean.toFixed(2);
    document.getElementById('highestScore').textContent = highest;
    document.getElementById('lowestScore').textContent = lowest;
    document.getElementById('stdDev').textContent = stdDev.toFixed(2);
}

function exportToExcel() {
    // Use academic module's export function
    window.API.academic.exportResults({ format: 'xlsx', ...getFilterParams() })
        .catch(error => window.API.showNotification(error.message, 'error'));
}

function exportToPDF() {
    // Use academic module's export function
    window.API.academic.exportResults({ format: 'pdf', ...getFilterParams() })
        .catch(error => window.API.showNotification(error.message, 'error'));
}

function getFilterParams() {
    return {
        class_id: document.querySelector('[name="class"]').value,
        subject_id: document.querySelector('[name="subject"]').value,
        term: document.querySelector('[name="term"]').value,
        exam_type: document.querySelector('[name="exam_type"]').value
    };
}
</script>
>>>>>>> 015101eaa5fcec34bce60a268265d985d4998948
