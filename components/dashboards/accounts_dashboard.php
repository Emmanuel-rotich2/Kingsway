<?php
// Accountant/Bursar Dashboard (Kenya Primary/Secondary School)
include 'config/db_connection.php';
include 'components/charts/chart.php';
include 'components/tables/table.php';
include 'components/cards/card_component.php';

// Summary cards for accountant/bursar
$summaryCards = [
  [
    'title' => 'Total Fees Collected',
    'count' => 3250000,
    'percent' => 85,
    'days' => 30,
    'icon' => 'bi-currency-dollar',
    'bgColor' => '#20c997',
    'iconColor' => 'text-white',
    'iconSize' => 'fs-3',
    'textColor' => 'text-white',
    'subTextColor' => 'text-white-50',
    'cardClass' => 'card-rounded small-card shadow-sm',
    'iconPosition' => 'start'
  ],
  [
    'title' => 'Outstanding Fees',
    'count' => 450000,
    'percent' => 15,
    'days' => 30,
    'icon' => 'bi-exclamation-circle-fill',
    'bgColor' => '#fd7e14',
    'iconColor' => 'text-white',
    'iconSize' => 'fs-3',
    'textColor' => 'text-white',
    'subTextColor' => 'text-white-50',
    'cardClass' => 'card-rounded small-card shadow-sm',
    'iconPosition' => 'start'
  ],
  [
    'title' => 'Total Expenses',
    'count' => 2100000,
    'percent' => 65,
    'days' => 30,
    'icon' => 'bi-cash-stack',
    'bgColor' => '#0d6efd',
    'iconColor' => 'text-white',
    'iconSize' => 'fs-3',
    'textColor' => 'text-white',
    'subTextColor' => 'text-white-50',
    'cardClass' => 'card-rounded small-card shadow-sm',
    'iconPosition' => 'start'
  ],
  [
    'title' => 'Balance',
    'count' => 1150000,
    'percent' => 35,
    'days' => 30,
    'icon' => 'bi-bank',
    'bgColor' => '#6f42c1',
    'iconColor' => 'text-white',
    'iconSize' => 'fs-3',
    'textColor' => 'text-white',
    'subTextColor' => 'text-white-50',
    'cardClass' => 'card-rounded small-card shadow-sm',
    'iconPosition' => 'start'
  ]
];

// Chart data for accountant context
$months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
$feesCollected = [250000, 300000, 280000, 320000, 310000, 290000, 330000, 340000, 350000, 360000, 370000, 380000];
$expenses = [150000, 180000, 170000, 200000, 190000, 185000, 210000, 220000, 215000, 225000, 230000, 240000];
$outstanding = [40000, 35000, 30000, 45000, 40000, 38000, 42000, 41000, 39000, 37000, 36000, 35000];

$financeStats = [
  [
    'label' => 'This Month Collected',
    'value' => 380000,
    'change' => '+4%',
    'changeClass' => 'text-success'
  ],
  [
    'label' => 'This Month Expenses',
    'value' => 240000,
    'change' => '+2%',
    'changeClass' => 'text-warning'
  ],
  [
    'label' => 'Outstanding',
    'value' => 35000,
    'change' => '-1%',
    'changeClass' => 'text-danger'
  ]
];

// Recent transactions table
$transHeaders = ['No', 'Date', 'Description', 'Type', 'Amount (Ksh)', 'Status'];
$transRows = [
  [1, '2024-05-10', 'Tuition Fees - Form 1', 'Credit', '50000', 'Received'],
  [2, '2024-05-09', 'Library Books', 'Debit', '15000', 'Paid'],
  [3, '2024-05-08', 'Uniform Fees - Grade 6', 'Credit', '12000', 'Received'],
  [4, '2024-05-07', 'Sports Equipment', 'Debit', '20000', 'Paid'],
  [5, '2024-05-06', 'Transport Fees - Term 2', 'Credit', '25000', 'Received'],
];
?>

<div class="container-fluid py-4">
  <div class="row g-3">
    <?php foreach ($summaryCards as $card) renderSummaryCard($card); ?>
  </div>

  <div class="row mt-4">
    <div class="col-lg-8">
      <div class="card shadow-sm p-3">
        <h5 class="mb-3">Fees & Expenses Overview (Year)</h5>
        <?php
        renderChart(
          'financeBar',
          'bar',
          $months,
          [
            [
              'label' => 'Fees Collected',
              'data' => $feesCollected,
              'backgroundColor' => '#20c997'
            ],
            [
              'label' => 'Expenses',
              'data' => $expenses,
              'backgroundColor' => '#0d6efd'
            ],
            [
              'label' => 'Outstanding',
              'data' => $outstanding,
              'backgroundColor' => '#fd7e14'
            ]
          ],
          [
            'plugins' => [
              'title' => ['display' => true, 'text' => 'Fees, Expenses & Outstanding per Month'],
              'legend' => ['display' => true, 'position' => 'top']
            ],
            'scales' => [
              'y' => ['beginAtZero' => true, 'title' => ['display' => true, 'text' => 'Amount (Ksh)']]
            ]
          ]
        );
        ?>
      </div>
    </div>
    <div class="col-lg-4">
      <div class="card shadow-sm p-3 mb-3">
        <h5 class="mb-3">Finance Summary</h5>
        <?php
        renderChart(
          'financeDoughnut',
          'doughnut',
          ['Collected', 'Expenses', 'Outstanding'],
          [[
            'data' => [3250000, 2100000, 450000],
            'backgroundColor' => ['#20c997', '#0d6efd', '#fd7e14']
          ]],
          [
            'plugins' => [
              'title' => ['display' => true, 'text' => 'Finance Distribution'],
              'legend' => ['display' => true, 'position' => 'bottom']
            ],
            'cutout' => '80%',
            'borderWidth' => 0
          ]
        );
        ?>
        <ul class="list-group list-group-flush mt-3">
          <?php foreach ($financeStats as $stat): ?>
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
    <div class="col-12">
      <?php renderTable("Recent Transactions", $transHeaders, $transRows, true); ?>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>