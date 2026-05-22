<?php
// admin/reports.php — Sales Reports (Daily / Weekly / Monthly)
require_once __DIR__ . '/../includes/config/app.php';
require_once __DIR__ . '/../includes/config/database.php';
requireLogin();

$pdo = getDB();

// ── Date range ────────────────────────────────────────────────
$rangeType = sanitize($_GET['range'] ?? 'today');
$dateStart = sanitize($_GET['date_start'] ?? '');
$dateEnd   = sanitize($_GET['date_end']   ?? '');

// Set defaults based on range type
if (!$dateStart || !$dateEnd) {
    $today = date('Y-m-d');
    switch ($rangeType) {
        case 'today':
            $dateStart = $dateEnd = $today; break;
        case 'week':
            $dateStart = date('Y-m-d', strtotime('-6 days'));
            $dateEnd   = $today; break;
        case 'month':
            $dateStart = date('Y-m-01');
            $dateEnd   = $today; break;
        default:
            $dateStart = $dateEnd = $today;
    }
}

// ── Fetch report data ─────────────────────────────────────────
$stmt = $pdo->prepare(
    "SELECT o.*, c.name AS customer_name,
            p.payment_status, p.amount_paid, p.payment_method
     FROM orders o
     JOIN customers c ON c.id = o.customer_id
     LEFT JOIN payments p ON p.order_id = o.id
     WHERE DATE(o.created_at) BETWEEN ? AND ?
     ORDER BY o.created_at ASC"
);
$stmt->execute([$dateStart, $dateEnd]);
$rows = $stmt->fetchAll();

// ── Summary calculations ──────────────────────────────────────
$totalOrders    = count($rows);
$totalRevenue   = array_sum(array_column($rows, 'total_amount'));
$totalCollected = array_sum(array_column($rows, 'amount_paid'));
$totalBalance   = $totalRevenue - $totalCollected;

// Breakdown by print type
$byType = [];
foreach ($rows as $r) {
    $t = $r['print_type'];
    if (!isset($byType[$t])) $byType[$t] = ['count' => 0, 'revenue' => 0];
    $byType[$t]['count']++;
    $byType[$t]['revenue'] += $r['total_amount'];
}

// Breakdown by status
$byStatus = [];
foreach ($rows as $r) {
    $s = $r['status'];
    if (!isset($byStatus[$s])) $byStatus[$s] = 0;
    $byStatus[$s]++;
}

// Daily breakdown (for chart)
$dailyTotals = [];
foreach ($rows as $r) {
    $day = date('M j', strtotime($r['created_at']));
    if (!isset($dailyTotals[$day])) $dailyTotals[$day] = 0;
    $dailyTotals[$day] += $r['total_amount'];
}

$chartLabels = json_encode(array_keys($dailyTotals));
$chartValues = json_encode(array_values($dailyTotals));

$pageTitle  = 'Reports';
$activePage = 'reports';
?>
<?php require_once __DIR__ . '/../includes/header.php'; ?>
<div class="layout-wrapper">
<?php require_once __DIR__ . '/../includes/sidebar.php'; ?>
<div class="main-content"><div class="page-content">

  <div class="page-header no-print">
    <div>
      <h1><i class="bi bi-bar-chart-line-fill me-2 text-primary"></i>Reports</h1>
      <nav aria-label="breadcrumb"><ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="<?= BASE_URL ?>/admin/dashboard.php">Home</a></li>
        <li class="breadcrumb-item active">Reports</li>
      </ol></nav>
    </div>
    <button onclick="window.print()" class="btn btn-outline-secondary">
      <i class="bi bi-printer me-1"></i> Print Report
    </button>
  </div>

  <!-- Filter Controls -->
  <div class="card mb-3 no-print">
    <div class="card-body">
      <form method="GET" class="row g-2 align-items-end">
        <div class="col-sm-6 col-md-3">
          <label class="form-label fw-600 small">Quick Range</label>
          <div class="d-flex gap-2">
            <button type="button" onclick="setDateRange('today')" class="btn btn-sm btn-outline-primary">Today</button>
            <button type="button" onclick="setDateRange('week')"  class="btn btn-sm btn-outline-primary">This Week</button>
            <button type="button" onclick="setDateRange('month')" class="btn btn-sm btn-outline-primary">This Month</button>
          </div>
        </div>
        <div class="col-sm-6 col-md-3">
          <label class="form-label fw-600 small">From Date</label>
          <input type="date" name="date_start" id="date_start" class="form-control"
                 value="<?= e($dateStart) ?>" />
        </div>
        <div class="col-sm-6 col-md-3">
          <label class="form-label fw-600 small">To Date</label>
          <input type="date" name="date_end" id="date_end" class="form-control"
                 value="<?= e($dateEnd) ?>" />
        </div>
        <div class="col-sm-6 col-md-3">
          <button type="submit" class="btn btn-primary w-100">
            <i class="bi bi-search me-1"></i> Generate Report
          </button>
        </div>
      </form>
    </div>
  </div>

  <!-- Report Header (visible when printed) -->
  <div class="text-center mb-4 d-none d-print-block">
    <h2 class="fw-800">🖨️ <?= APP_NAME ?></h2>
    <h4>Sales Report</h4>
    <p class="text-muted"><?= date('F j, Y', strtotime($dateStart)) ?> — <?= date('F j, Y', strtotime($dateEnd)) ?></p>
    <hr />
  </div>

  <!-- Summary Cards -->
  <div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
      <div class="stat-card">
        <div class="stat-icon blue"><i class="bi bi-receipt-cutoff"></i></div>
        <div>
          <div class="stat-value"><?= $totalOrders ?></div>
          <div class="stat-label">Total Orders</div>
        </div>
      </div>
    </div>
    <div class="col-6 col-md-3">
      <div class="stat-card">
        <div class="stat-icon amber"><i class="bi bi-cash-stack"></i></div>
        <div>
          <div class="stat-value" style="font-size:1.2rem"><?= peso($totalRevenue) ?></div>
          <div class="stat-label">Gross Revenue</div>
        </div>
      </div>
    </div>
    <div class="col-6 col-md-3">
      <div class="stat-card">
        <div class="stat-icon green"><i class="bi bi-check-circle-fill"></i></div>
        <div>
          <div class="stat-value" style="font-size:1.2rem"><?= peso($totalCollected) ?></div>
          <div class="stat-label">Collected</div>
        </div>
      </div>
    </div>
    <div class="col-6 col-md-3">
      <div class="stat-card">
        <div class="stat-icon red"><i class="bi bi-exclamation-circle-fill"></i></div>
        <div>
          <div class="stat-value" style="font-size:1.2rem"><?= peso($totalBalance) ?></div>
          <div class="stat-label">Outstanding</div>
        </div>
      </div>
    </div>
  </div>

  <div class="row g-3 mb-4">
    <!-- Sales Chart -->
    <div class="col-lg-8 no-print">
      <div class="card h-100">
        <div class="card-header"><i class="bi bi-graph-up text-primary"></i> Daily Revenue</div>
        <div class="card-body">
          <?php if (empty($dailyTotals)): ?>
          <p class="text-center text-muted py-4">No data for this period.</p>
          <?php else: ?>
          <canvas id="reportChart" height="120"></canvas>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <!-- By Print Type Breakdown -->
    <div class="col-lg-4">
      <div class="card h-100">
        <div class="card-header"><i class="bi bi-printer text-primary"></i> By Print Type</div>
        <div class="card-body">
          <?php if (empty($byType)): ?>
          <p class="text-center text-muted py-4">No data.</p>
          <?php else: foreach ($byType as $type => $data): ?>
          <div class="d-flex justify-content-between align-items-center mb-3">
            <div>
              <div class="fw-600 small"><?= printTypeLabel($type) ?></div>
              <div class="text-muted" style="font-size:.75rem"><?= $data['count'] ?> orders</div>
            </div>
            <span class="fw-700 text-primary"><?= peso($data['revenue']) ?></span>
          </div>
          <?php endforeach; endif; ?>
        </div>
      </div>
    </div>
  </div>

  <!-- Detailed Transactions Table -->
  <div class="table-card">
    <div class="card-header justify-content-between">
      <span><i class="bi bi-table text-primary"></i>
        Transaction Details —
        <?= date('M j', strtotime($dateStart)) ?> to <?= date('M j, Y', strtotime($dateEnd)) ?>
      </span>
      <span class="badge bg-primary"><?= $totalOrders ?> records</span>
    </div>
    <div class="table-responsive">
      <table class="table">
        <thead>
          <tr>
            <th>#</th>
            <th>Date</th>
            <th>Receipt #</th>
            <th>Customer</th>
            <th>Type</th>
            <th>Qty</th>
            <th>Total</th>
            <th>Paid</th>
            <th>Balance</th>
            <th>Pay Status</th>
            <th>Order Status</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($rows)): ?>
          <tr><td colspan="11" class="text-center text-muted py-5">
            <i class="bi bi-inbox display-4 d-block mb-2 opacity-25"></i>
            No transactions found for the selected period.
          </td></tr>
          <?php else: foreach ($rows as $i => $r):
            $balance = $r['total_amount'] - ($r['amount_paid'] ?? 0);
          ?>
          <tr>
            <td class="text-muted small"><?= $i + 1 ?></td>
            <td class="text-muted small"><?= date('M j, Y g:i A', strtotime($r['created_at'])) ?></td>
            <td><span class="receipt-no"><?= e($r['receipt_no']) ?></span></td>
            <td class="fw-600"><?= e($r['customer_name']) ?></td>
            <td><span class="badge bg-primary-soft text-primary"><?= printTypeLabel($r['print_type']) ?></span></td>
            <td><?= $r['quantity'] ?></td>
            <td class="fw-700"><?= peso($r['total_amount']) ?></td>
            <td class="text-success fw-600"><?= peso($r['amount_paid'] ?? 0) ?></td>
            <td class="<?= $balance > 0 ? 'text-danger' : 'text-muted' ?>"><?= peso($balance) ?></td>
            <td><?= statusBadge($r['payment_status'] ?? 'unpaid') ?></td>
            <td><?= statusBadge($r['status']) ?></td>
          </tr>
          <?php endforeach; endif; ?>
        </tbody>
        <?php if (!empty($rows)): ?>
        <tfoot>
          <tr class="table-light fw-700">
            <td colspan="6" class="text-end">TOTALS:</td>
            <td><?= peso($totalRevenue) ?></td>
            <td class="text-success"><?= peso($totalCollected) ?></td>
            <td class="text-danger"><?= peso($totalBalance) ?></td>
            <td colspan="2"></td>
          </tr>
        </tfoot>
        <?php endif; ?>
      </table>
    </div>
  </div>

  <!-- Print footer -->
  <div class="d-none d-print-block mt-4 text-center text-muted small">
    <hr />
    Report generated by <?= APP_NAME ?> on <?= date('F j, Y g:i A') ?>
  </div>

</div></div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>

<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>
<script>
(function(){
  const ctx = document.getElementById('reportChart');
  if (!ctx) return;
  new Chart(ctx, {
    type: 'line',
    data: {
      labels: <?= $chartLabels ?>,
      datasets: [{
        label: 'Revenue (₱)',
        data: <?= $chartValues ?>,
        borderColor: '#2563eb',
        backgroundColor: 'rgba(37,99,235,.08)',
        borderWidth: 2.5,
        pointBackgroundColor: '#2563eb',
        pointRadius: 4,
        fill: true,
        tension: 0.35,
      }]
    },
    options: {
      responsive: true,
      plugins: { legend: { display: false } },
      scales: {
        y: {
          beginAtZero: true,
          ticks: { callback: v => '₱' + v.toLocaleString() }
        }
      }
    }
  });
})();
</script>
