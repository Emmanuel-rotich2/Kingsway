<?php
// Principal/Head Teacher Dashboard (Kenya Primary/Secondary School)
include 'config/db_connection.php';
include 'components/charts/chart.php';
include 'components/tables/table.php';
include 'components/cards/card_component.php';

// Summary cards for principal/head teacher
$summaryCards = [
  [
    'title' => 'Total Students',
    'count' => 1240,
    'percent' => 100,
    'days' => 1,
    'icon' => 'bi-people-fill',
    'bgColor' => '#6f42c1',
    'iconColor' => 'text-white',
    'iconSize' => 'fs-3',
    'textColor' => 'text-white',
    'subTextColor' => 'text-white-50',
    'cardClass' => 'card-rounded small-card shadow-sm',
    'iconPosition' => 'start'
  ],
  [
    'title' => 'Teachers',
    'count' => 48,
    'percent' => 100,
    'days' => 1,
    'icon' => 'bi-person-badge-fill',
    'bgColor' => '#0d6efd',
    'iconColor' => 'text-white',
    'iconSize' => 'fs-3',
    'textColor' => 'text-white',
    'subTextColor' => 'text-white-50',
    'cardClass' => 'card-rounded small-card shadow-sm',
    'iconPosition' => 'start'
  ],
  [
    'title' => 'Attendance Today',
    'count' => 1175,
    'percent' => 95,
    'days' => 1,
    'icon' => 'bi-person-check-fill',
    'bgColor' => '#198754',
    'iconColor' => 'text-white',
    'iconSize' => 'fs-3',
    'textColor' => 'text-white',
    'subTextColor' => 'text-white-50',
    'cardClass' => 'card-rounded small-card shadow-sm',
    'iconPosition' => 'start'
  ],
  [
    'title' => 'Fees Collected (Ksh)',
    'count' => 2350000,
    'percent' => 80,
    'days' => 30,
    'icon' => 'bi-currency-dollar',
    'bgColor' => '#fd7e14',
    'iconColor' => 'text-white',
    'iconSize' => 'fs-3',
    'textColor' => 'text-white',
    'subTextColor' => 'text-white-50',
    'cardClass' => 'card-rounded small-card shadow-sm',
    'iconPosition' => 'start'
  ]
];

// Chart data for principal context
$months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
$admissions = [30, 40, 35, 50, 60, 55, 70, 68, 90, 75, 80, 100];
$attendance = [95, 96, 94, 97, 95, 96, 97, 98, 96, 97, 95, 96];
$feePayments = [200000, 180000, 220000, 250000, 210000, 230000, 240000, 260000, 270000, 250000, 245000, 255000];
$teacherAttendance = [48, 47, 48, 48, 47, 48, 48, 48, 47, 48, 48, 48];

$schoolStats = [
  [
    'label' => 'Current Term Admissions',
    'value' => 100,
    'change' => '+8%',
    'changeClass' => 'text-success'
  ],
  [
    'label' => 'Attendance Rate',
    'value' => '95%',
    'change' => '+1%',
    'changeClass' => 'text-success'
  ],
  [
    'label' => 'Outstanding Fees',
    'value' => 600000,
    'change' => '-2%',
    'changeClass' => 'text-danger'
  ]
];

// Recent events/announcements table
$eventHeaders = ['No', 'Date', 'Event/Announcement', 'Target Group', 'Status'];
$eventRows = [
  [1, '2024-05-10', 'Staff Meeting', 'Teachers', 'Scheduled'],
  [2, '2024-05-09', 'Parents Meeting', 'Parents', 'Completed'],
  [3, '2024-05-08', 'Midterm Exams', 'Students', 'Ongoing'],
  [4, '2024-05-07', 'Sports Day', 'All', 'Upcoming'],
  [5, '2024-05-06', 'Fee Reminder', 'Parents', 'Sent'],
];
?>

<div class="container-fluid py-4">
  <div class="row g-3">
    <?php foreach ($summaryCards as $card) renderSummaryCard($card); ?>
  </div>

  <div class="row mt-4">
    <div class="col-lg-8">
      <div class="card shadow-sm p-3">
        <h5 class="mb-3">Admissions & Fee Payments (Year)</h5>
        <?php
        renderChart(
          'barChart',
          'bar',
          $months,
          [
            [
              'label' => 'Admissions',
              'data' => $admissions,
              'backgroundColor' => '#6f42c1'
            ],
            [
              'label' => 'Fee Payments (Ksh)',
              'data' => $feePayments,
              'backgroundColor' => '#20c997'
            ]
          ],
          [
            'plugins' => [
              'title' => ['display' => true, 'text' => 'Admissions & Fee Payments per Month'],
              'legend' => ['display' => true, 'position' => 'top']
            ],
            'scales' => [
              'y' => ['beginAtZero' => true, 'title' => ['display' => true, 'text' => 'Count / Amount']]
            ]
          ]
        );
        ?>
      </div>
    </div>
    <div class="col-lg-4">
      <div class="card shadow-sm p-3 mb-3">
        <h5 class="mb-3">Attendance Rate (Monthly)</h5>
        <?php
        renderChart(
          'attendanceLine',
          'line',
          $months,
          [[
            'label' => 'Attendance (%)',
            'data' => $attendance,
            'borderColor' => '#198754',
            'backgroundColor' => 'rgba(25,135,84,0.2)',
            'tension' => 0.4,
            'fill' => true
          ]],
          [
            'plugins' => [
              'title' => ['display' => true, 'text' => 'Student Attendance Rate'],
              'legend' => ['display' => false]
            ],
            'scales' => [
              'y' => ['beginAtZero' => true, 'title' => ['display' => true, 'text' => 'Attendance (%)']]
            ]
          ]
        );
        ?>
        <ul class="list-group list-group-flush mt-3">
          <?php foreach ($schoolStats as $stat): ?>
            <li class="list-group-item d-flex justify-content-between">
              <span><?= htmlspecialchars($stat['label']) ?></span>
              <span>
                <?= is_numeric($stat['value']) ? number_format($stat['value']) : htmlspecialchars($stat['value']) ?>
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
        <h6>Teacher Attendance (Monthly)</h6>
        <?php
        renderChart(
          'teacherAttendanceLine',
          'line',
          $months,
          [[
            'label' => 'Teacher Attendance',
            'data' => $teacherAttendance,
            'borderColor' => '#0d6efd',
            'backgroundColor' => 'rgba(13,110,253,0.2)',
            'tension' => 0.4,
            'fill' => true
          ]],
          [
            'plugins' => [
              'title' => ['display' => true, 'text' => 'Teacher Attendance'],
              'legend' => ['display' => false]
            ],
            'scales' => [
              'y' => ['beginAtZero' => true, 'title' => ['display' => true, 'text' => 'Teachers Present']]
            ]
          ]
        );
        ?>
      </div>
    </div>
    <div class="col-md-6 col-xl-6">
      <div class="card text-center shadow-sm p-3">
        <h6>Fee Collection Trend</h6>
        <?php
        renderChart(
          'feeCollectionLine',
          'line',
          $months,
          [[
            'label' => 'Fee Payments (Ksh)',
            'data' => $feePayments,
            'borderColor' => '#fd7e14',
            'backgroundColor' => 'rgba(253,126,20,0.2)',
            'tension' => 0.4,
            'fill' => true
          ]],
          [
            'plugins' => [
              'title' => ['display' => true, 'text' => 'Fee Collection Trend'],
              'legend' => ['display' => false]
            ],
            'scales' => [
              'y' => ['beginAtZero' => true, 'title' => ['display' => true, 'text' => 'Amount (Ksh)']]
            ]
          ]
        );
        ?>
      </div>
    </div>
  </div>

  <div class="row mt-4">
    <div class="col-12">
      <?php renderTable("Recent Events & Announcements", $eventHeaders, $eventRows, true); ?>
    </div>
  </div>
</div>

