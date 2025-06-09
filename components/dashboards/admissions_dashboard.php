<?php
// Admissions Office Dashboard (Kenya Primary/Secondary School)
include 'config/db_connection.php';
include 'components/charts/chart.php';
include 'components/tables/table.php';
include 'components/cards/card_component.php';

// Summary cards for admissions office
$summaryCards = [
  [
    'title' => 'Applications Received',
    'count' => 320,
    'percent' => 80,
    'days' => 30,
    'icon' => 'bi-envelope-open-fill',
    'bgColor' => '#0d6efd',
    'iconColor' => 'text-white',
    'iconSize' => 'fs-3',
    'textColor' => 'text-white',
    'subTextColor' => 'text-white-50',
    'cardClass' => 'card-rounded small-card shadow-sm',
    'iconPosition' => 'start'
  ],
  [
    'title' => 'Admitted Students',
    'count' => 210,
    'percent' => 66,
    'days' => 30,
    'icon' => 'bi-person-check-fill',
    'bgColor' => '#20c997',
    'iconColor' => 'text-white',
    'iconSize' => 'fs-3',
    'textColor' => 'text-white',
    'subTextColor' => 'text-white-50',
    'cardClass' => 'card-rounded small-card shadow-sm',
    'iconPosition' => 'start'
  ],
  [
    'title' => 'Pending Applications',
    'count' => 90,
    'percent' => 28,
    'days' => 30,
    'icon' => 'bi-hourglass-split',
    'bgColor' => '#fd7e14',
    'iconColor' => 'text-white',
    'iconSize' => 'fs-3',
    'textColor' => 'text-white',
    'subTextColor' => 'text-white-50',
    'cardClass' => 'card-rounded small-card shadow-sm',
    'iconPosition' => 'start'
  ],
  [
    'title' => 'Rejected Applications',
    'count' => 20,
    'percent' => 6,
    'days' => 30,
    'icon' => 'bi-x-circle-fill',
    'bgColor' => '#dc3545',
    'iconColor' => 'text-white',
    'iconSize' => 'fs-3',
    'textColor' => 'text-white',
    'subTextColor' => 'text-white-50',
    'cardClass' => 'card-rounded small-card shadow-sm',
    'iconPosition' => 'start'
  ]
];

// Chart data for admissions context
$months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
$applications = [10, 15, 20, 25, 30, 40, 50, 60, 35, 20, 10, 5];
$admitted = [5, 10, 15, 20, 25, 30, 40, 45, 30, 15, 8, 3];
$pending = [3, 2, 4, 3, 2, 5, 6, 8, 3, 2, 1, 1];
$rejected = [2, 3, 1, 2, 3, 5, 4, 7, 2, 3, 1, 1];

$admissionStats = [
  [
    'label' => 'This Month',
    'value' => 30,
    'change' => '+10%',
    'changeClass' => 'text-success'
  ],
  [
    'label' => 'Last Month',
    'value' => 20,
    'change' => '-5%',
    'changeClass' => 'text-danger'
  ],
  [
    'label' => 'Pending',
    'value' => 8,
    'change' => '+2',
    'changeClass' => 'text-warning'
  ]
];

// Recent applications table
$appHeaders = ['No', 'Applicant Name', 'Date Applied', 'Class', 'Status', 'Parent Contact'];
$appRows = [
  [1, 'Mutua Kelvin', '2024-05-10', 'Form 1', 'Pending', '0712 111222'],
  [2, 'Wambui Grace', '2024-05-09', 'Grade 7', 'Admitted', '0722 333444'],
  [3, 'Omondi Brian', '2024-05-08', 'Form 2', 'Rejected', '0733 555666'],
  [4, 'Amina Hassan', '2024-05-07', 'Grade 8', 'Admitted', '0744 777888'],
  [5, 'Kiptoo Samuel', '2024-05-06', 'Form 1', 'Pending', '0755 999000'],
];
?>

<div class="container-fluid py-4">
  <div class="row g-3">
    <?php foreach ($summaryCards as $card) renderSummaryCard($card); ?>
  </div>

  <div class="row mt-4">
    <div class="col-lg-8">
      <div class="card shadow-sm p-3">
        <h5 class="mb-3">Admissions Overview (Year)</h5>
        <?php
        renderChart(
          'admissionsBar',
          'bar',
          $months,
          [
            [
              'label' => 'Applications',
              'data' => $applications,
              'backgroundColor' => '#0d6efd'
            ],
            [
              'label' => 'Admitted',
              'data' => $admitted,
              'backgroundColor' => '#20c997'
            ],
            [
              'label' => 'Pending',
              'data' => $pending,
              'backgroundColor' => '#fd7e14'
            ],
            [
              'label' => 'Rejected',
              'data' => $rejected,
              'backgroundColor' => '#dc3545'
            ]
          ],
          [
            'plugins' => [
              'title' => ['display' => true, 'text' => 'Admissions Status per Month'],
              'legend' => ['display' => true, 'position' => 'top']
            ],
            'scales' => [
              'y' => ['beginAtZero' => true, 'title' => ['display' => true, 'text' => 'No. of Applications']]
            ]
          ]
        );
        ?>
      </div>
    </div>
    <div class="col-lg-4">
      <div class="card shadow-sm p-3 mb-3">
        <h5 class="mb-3">Admissions Summary</h5>
        <?php
        renderChart(
          'admissionsDoughnut',
          'doughnut',
          ['Admitted', 'Pending', 'Rejected'],
          [[
            'data' => [210, 90, 20],
            'backgroundColor' => ['#20c997', '#fd7e14', '#dc3545']
          ]],
          [
            'plugins' => [
              'title' => ['display' => true, 'text' => 'Admissions Distribution'],
              'legend' => ['display' => true, 'position' => 'bottom']
            ],
            'cutout' => '80%',
            'borderWidth' => 0
          ]
        );
        ?>
        <ul class="list-group list-group-flush mt-3">
          <?php foreach ($admissionStats as $stat): ?>
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
    <div class="col-12">
      <?php renderTable("Recent Applications", $appHeaders, $appRows, true); ?>
    </div>
  </div>
</div>
