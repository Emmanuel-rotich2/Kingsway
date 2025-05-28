<?php
// School Admin Dashboard (Kenya Primary/Secondary School)
include 'config/db_connection.php';
include 'components/charts/chart.php';
include 'components/tables/table.php';
include 'components/cards/card_component.php';

// Summary cards for a school admin
$summaryCards = [
  [
    'title' => 'Total Students',
    'count' => 1240,
    'percent' => 92,
    'days' => 7,
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
    'title' => 'Present Today',
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

// Chart data for school context
$months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
$admissions = [30, 40, 35, 50, 60, 55, 70, 68, 90, 75, 80, 1000];
$feePayments = [200000, 180000, 220000, 250000, 210000, 230000, 240000, 260000, 270000, 250000, 245000, 255000];

$doughnutData = [1175, 65]; // Present vs Absent today
$incomeData = [80, 20]; // % fees collected vs outstanding
$withdrawData = [60, 40]; // % expenses vs balance
$activityLabels = ['Sat', 'Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri'];
$activityData = [120, 130, 140, 135, 145, 150, 148]; // e.g. lessons taught

$salesStats = [
  [
    'label' => 'Current Term',
    'value' => 2350000,
    'change' => '+5%',
    'changeClass' => 'text-success'
  ],
  [
    'label' => 'Last Term',
    'value' => 2250000,
    'change' => '+2%',
    'changeClass' => 'text-success'
  ],
  [
    'label' => 'Outstanding Fees',
    'value' => 600000,
    'change' => '-1%',
    'changeClass' => 'text-danger'
  ]
];

// Recent admissions table
$studentHeaders = ['No', 'Name', 'Admission Date', 'Class', 'Parent Contact', 'Status'];
$studentRows = [
  [1, 'Faith Wanjiku', '2024-05-10', 'Grade 4', '0712 345678', 'Active'],
  [2, 'Brian Otieno', '2024-05-09', 'Form 1', '0722 123456', 'Active'],
  [3, 'Mercy Mwikali', '2024-05-08', 'Grade 8', '0733 987654', 'Active'],
  [4, 'Samuel Kiptoo', '2024-05-07', 'Form 2', '0700 112233', 'Active'],
  [5, 'Janet Njeri', '2024-05-06', 'Grade 6', '0799 445566', 'Active'],
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
        <h5 class="mb-3">Attendance Today</h5>
        <?php
        renderChart(
          'doughnutChart',
          'doughnut',
          ['Present', 'Absent'],
          [[
            'data' => $doughnutData,
            'backgroundColor' => ['#198754', '#dc3545']
          ]],
          [
            'plugins' => [
              'title' => ['display' => true, 'text' => 'Student Attendance'],
              'legend' => ['display' => true, 'position' => 'bottom']
            ]
          ]
        );
        ?>
        <ul class="list-group list-group-flush mt-3">
          <?php foreach ($salesStats as $stat): ?>
            <li class="list-group-item d-flex justify-content-between">
              <span><?= htmlspecialchars($stat['label']) ?></span>
              <span>
                <?= htmlspecialchars(number_format($stat['value'])) ?>
                <span class="<?= $stat['changeClass'] ?>"><?= htmlspecialchars($stat['change']) ?></span>
              </span>
            </li>
          <?php endforeach; ?>
        </ul>
      </div>
    </div>
  </div>

  <div class="row mt-4">
    <div class="col-md-6 col-xl-3">
      <div class="card text-center shadow-sm p-3">
        <h6>Fees Collection</h6>
        <h4>Ksh. <?= number_format(2350000) ?></h4>
        <?php
        renderChart(
          'incomeGauge',
          'doughnut',
          ['Collected', 'Outstanding'],
          [[
            'data' => $incomeData,
            'backgroundColor' => ['#20c997', '#e9ecef']
          ]],
          [
            'plugins' => [
              'title' => ['display' => false],
              'legend' => ['display' => false]
            ],
            'cutout' => '80%',
            'borderWidth' => 0
          ]
        );
        ?>
      </div>
    </div>
    <div class="col-md-6 col-xl-3">
      <div class="card text-center shadow-sm p-3">
        <h6>Expenses</h6>
        <h4>Ksh. <?= number_format(1400000) ?></h4>
        <?php
        renderChart(
          'withdrawGauge',
          'doughnut',
          ['Expenses', 'Balance'],
          [[
            'data' => $withdrawData,
            'backgroundColor' => ['#fd7e14', '#e9ecef']
          ]],
          [
            'plugins' => [
              'title' => ['display' => false],
              'legend' => ['display' => false]
            ],
            'cutout' => '80%',
            'borderWidth' => 0
          ]
        );
        ?>
      </div>
    </div>
    <div class="col-md-12 col-xl-6">
      <div class="card shadow-sm p-3">
        <h6>Lessons Taught This Week</h6>
        <?php
        renderChart(
          'lineChart',
          'line',
          $activityLabels,
          [[
            'label' => 'Lessons',
            'data' => $activityData,
            'borderColor' => '#6f42c1',
            'backgroundColor' => 'rgba(111,66,193,0.2)',
            'tension' => 0.4,
            'fill' => true
          ]],
          [
            'plugins' => [
              'title' => ['display' => true, 'text' => 'Lessons Taught (Weekly Trend)'],
              'legend' => ['display' => false]
            ],
            'scales' => [
              'y' => ['beginAtZero' => true, 'title' => ['display' => true, 'text' => 'Lessons']]
            ]
          ]
        );
        ?>
      </div>
    </div>
  </div>

  <div class="row mt-4">
    <div class="col-12">
      <?php renderTable("Recent Admissions", $studentHeaders, $studentRows, true); ?>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>