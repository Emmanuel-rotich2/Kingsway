<?php
// Teacher Dashboard (Kenya Primary/Secondary School)
include 'config/db_connection.php';
include 'components/charts/chart.php';
include 'components/tables/table.php';
include 'components/cards/card_component.php';

// Summary cards for a teacher
$summaryCards = [
  [
    'title' => 'My Students',
    'count' => 45,
    'percent' => 100,
    'days' => 1,
    'icon' => 'bi-people-fill',
    'bgColor' => '#0d6efd',
    'iconColor' => 'text-white',
    'iconSize' => 'fs-3',
    'textColor' => 'text-white',
    'subTextColor' => 'text-white-50',
    'cardClass' => 'card-rounded small-card shadow-sm',
    'iconPosition' => 'start'
  ],
  [
    'title' => 'Lessons Today',
    'count' => 5,
    'percent' => 100,
    'days' => 1,
    'icon' => 'bi-journal-bookmark-fill',
    'bgColor' => '#20c997',
    'iconColor' => 'text-white',
    'iconSize' => 'fs-3',
    'textColor' => 'text-white',
    'subTextColor' => 'text-white-50',
    'cardClass' => 'card-rounded small-card shadow-sm',
    'iconPosition' => 'start'
  ],
  [
    'title' => 'Assignments Due',
    'count' => 3,
    'percent' => 60,
    'days' => 2,
    'icon' => 'bi-clipboard-check-fill',
    'bgColor' => '#fd7e14',
    'iconColor' => 'text-white',
    'iconSize' => 'fs-3',
    'textColor' => 'text-white',
    'subTextColor' => 'text-white-50',
    'cardClass' => 'card-rounded small-card shadow-sm',
    'iconPosition' => 'start'
  ],
  [
    'title' => 'Attendance Rate',
    'count' => '98%',
    'percent' => 98,
    'days' => 1,
    'icon' => 'bi-person-check-fill',
    'bgColor' => '#198754',
    'iconColor' => 'text-white',
    'iconSize' => 'fs-3',
    'textColor' => 'text-white',
    'subTextColor' => 'text-white-50',
    'cardClass' => 'card-rounded small-card shadow-sm',
    'iconPosition' => 'start'
  ]
];

// Chart data for teacher context
$days = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri'];
$attendance = [44, 45, 43, 45, 44];
$assignmentsMarked = [2, 3, 1, 4, 2];
$lessonProgress = [80, 85, 90, 92, 95];

$assignmentStats = [
  [
    'label' => 'Marked',
    'value' => 12,
    'change' => '+2',
    'changeClass' => 'text-success'
  ],
  [
    'label' => 'Pending',
    'value' => 3,
    'change' => '-1',
    'changeClass' => 'text-danger'
  ]
];

// Recent students table
$studentHeaders = ['No', 'Name', 'Class', 'Attendance (%)', 'Last Assignment', 'Status'];
$studentRows = [
  [1, 'Achieng Mary', 'Grade 6', '100', '2024-05-10', 'Active'],
  [2, 'Kamau John', 'Grade 6', '98', '2024-05-09', 'Active'],
  [3, 'Ali Hussein', 'Grade 6', '97', '2024-05-08', 'Active'],
  [4, 'Wanjiru Faith', 'Grade 6', '99', '2024-05-07', 'Active'],
  [5, 'Otieno Brian', 'Grade 6', '95', '2024-05-06', 'Active'],
];
?>

<div class="container-fluid py-4">
  <div class="row g-3">
    <?php foreach ($summaryCards as $card) renderSummaryCard($card); ?>
  </div>

  <div class="row mt-4">
    <div class="col-lg-8">
      <div class="card shadow-sm p-3">
        <h5 class="mb-3">Class Attendance (This Week)</h5>
        <?php
        renderChart(
          'attendanceBar',
          'bar',
          $days,
          [
            [
              'label' => 'Attendance',
              'data' => $attendance,
              'backgroundColor' => '#0d6efd'
            ]
          ],
          [
            'plugins' => [
              'title' => ['display' => true, 'text' => 'Attendance per Day'],
              'legend' => ['display' => false]
            ],
            'scales' => [
              'y' => ['beginAtZero' => true, 'title' => ['display' => true, 'text' => 'No. of Students']]
            ]
          ]
        );
        ?>
      </div>
    </div>
    <div class="col-lg-4">
      <div class="card shadow-sm p-3 mb-3">
        <h5 class="mb-3">Assignments</h5>
        <?php
        renderChart(
          'assignmentsDoughnut',
          'doughnut',
          ['Marked', 'Pending'],
          [[
            'data' => [12, 3],
            'backgroundColor' => ['#20c997', '#fd7e14']
          ]],
          [
            'plugins' => [
              'title' => ['display' => true, 'text' => 'Assignments Status'],
              'legend' => ['display' => true, 'position' => 'bottom']
            ],
            'cutout' => '80%',
            'borderWidth' => 0
          ]
        );
        ?>
        <ul class="list-group list-group-flush mt-3">
          <?php foreach ($assignmentStats as $stat): ?>
            <li class="list-group-item d-flex justify-content-between">
              <span><?= htmlspecialchars($stat['label']) ?></span>
              <span>
                <?= htmlspecialchars($stat['value']) ?>
                <span class="<?= $stat['changeClass'] ?>"><?= htmlspecialchars($stat['change']) ?></span>
              </span>
            </li>
          <?php endforeach; ?>
        </ul>
      </div>
    </div>
  </div>

  <div class="row mt-4">
    <div class="col-md-6 col-xl-6">
      <div class="card text-center shadow-sm p-3">
        <h6>Lesson Progress (%)</h6>
        <?php
        renderChart(
          'lessonLine',
          'line',
          $days,
          [[
            'label' => 'Progress',
            'data' => $lessonProgress,
            'borderColor' => '#6f42c1',
            'backgroundColor' => 'rgba(111,66,193,0.2)',
            'tension' => 0.4,
            'fill' => true
          ]],
          [
            'plugins' => [
              'title' => ['display' => true, 'text' => 'Lesson Progress'],
              'legend' => ['display' => false]
            ],
            'scales' => [
              'y' => ['beginAtZero' => true, 'title' => ['display' => true, 'text' => 'Progress (%)']]
            ]
          ]
        );
        ?>
      </div>
    </div>
    <div class="col-md-6 col-xl-6">
      <div class="card text-center shadow-sm p-3">
        <h6>Assignments Marked (This Week)</h6>
        <?php
        renderChart(
          'assignmentsLine',
          'line',
          $days,
          [[
            'label' => 'Assignments Marked',
            'data' => $assignmentsMarked,
            'borderColor' => '#fd7e14',
            'backgroundColor' => 'rgba(253,126,20,0.2)',
            'tension' => 0.4,
            'fill' => true
          ]],
          [
            'plugins' => [
              'title' => ['display' => true, 'text' => 'Assignments Marked'],
              'legend' => ['display' => false]
            ],
            'scales' => [
              'y' => ['beginAtZero' => true, 'title' => ['display' => true, 'text' => 'Assignments']]
            ]
          ]
        );
        ?>
      </div>
    </div>
  </div>

  <div class="row mt-4">
    <div class="col-12">
      <?php renderTable("My Students", $studentHeaders, $studentRows, true); ?>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>