<?php
// admin/dashboard.php — Main Dashboard
require_once __DIR__ . '/../includes/config/app.php';
require_once __DIR__ . '/../includes/config/database.php';
requireLogin();

$pdo = getDB();

// ── Stat queries ──────────────────────────────────────────
$totalOrders    = $pdo->query('SELECT COUNT(*) FROM orders')->fetchColumn();
$totalCustomers = $pdo->query('SELECT COUNT(*) FROM customers')->fetchColumn();
$totalSales     = $pdo->query('SELECT COALESCE(SUM(amount_paid),0) FROM payments WHERE payment_status="paid"')->fetchColumn();
$pendingOrders  = $pdo->query('SELECT COUNT(*) FROM orders WHERE status="pending"')->fetchColumn();
$processingOrders = $pdo->query('SELECT COUNT(*) FROM orders WHERE status="processing"')->fetchColumn();
$completedOrders  = $pdo->query('SELECT COUNT(*) FROM orders WHERE status="completed"')->fetchColumn();
$unpaidAmount   = $pdo->query('SELECT COALESCE(SUM(o.total_amount - COALESCE(p.amount_paid,0)),0) FROM orders o LEFT JOIN payments p ON p.order_id=o.id WHERE p.payment_status!="paid" OR p.id IS NULL')->fetchColumn();

// Today's sales
$todaySales = $pdo->query(
    'SELECT COALESCE(SUM(amount_paid),0) FROM payments WHERE DATE(payment_date)=CURDATE() AND payment_status="paid"'
)->fetchColumn();

// Recent 10 orders
$recentOrders = $pdo->query(
    'SELECT o.*, c.name AS customer_name, p.payment_status
     FROM orders o
     JOIN customers c ON c.id = o.customer_id
     LEFT JOIN payments p ON p.order_id = o.id
     ORDER BY o.created_at DESC LIMIT 10'
)->fetchAll();

// Monthly sales chart data (last 6 months)
$chartData = $pdo->query(
    "SELECT DATE_FORMAT(payment_date,'%b %Y') AS month,
            SUM(amount_paid) AS total
     FROM payments
     WHERE payment_status='paid'
       AND payment_date >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
     GROUP BY YEAR(payment_date), MONTH(payment_date)
     ORDER BY payment_date ASC"
)->fetchAll();

$chartLabels = json_encode(array_column($chartData, 'month'));
$chartValues = json_encode(array_map('floatval', array_column($chartData, 'total')));

$pageTitle  = 'Dashboard';
$activePage = 'dashboard';
?>
<?php require_once __DIR__ . '/../includes/header.php'; ?>

<div class="layout-wrapper">
<?php require_once __DIR__ . '/../includes/sidebar.php'; ?>

<div class="main-content">
<?php // header.php already outputs the navbar ?>

<div class="page-content">

  <!-- Page Header -->
  <div class="page-header">
    <div>
      <h1><i class="bi bi-speedometer2 me-2 text-primary"></i>Dashboard</h1>
      <nav aria-label="breadcrumb"><ol class="breadcrumb">
        <li class="breadcrumb-item active">Home</li>
      </ol></nav>
    </div>
    <a href="<?= BASE_URL ?>/admin/orders.php?action=new" class="btn btn-primary">
      <i class="bi bi-plus-circle me-1"></i> New Order
    </a>
  </div>

  <!-- Stat Cards Row -->
  <div class="row g-3 mb-4">
    <div class="col-6 col-md-4 col-xl-2">
      <div class="stat-card">
        <div class="stat-icon blue"><i class="bi bi-receipt-cutoff"></i></div>
        <div>
          <div class="stat-value"><?= number_format($totalOrders) ?></div>
          <div class="stat-label">Total Orders</div>
        </div>
      </div>
    </div>
    <div class="col-6 col-md-4 col-xl-2">
      <div class="stat-card">
        <div class="stat-icon green"><i class="bi bi-people-fill"></i></div>
        <div>
          <div class="stat-value"><?= number_format($totalCustomers) ?></div>
          <div class="stat-label">Customers</div>
        </div>
      </div>
    </div>
    <div class="col-6 col-md-4 col-xl-2">
      <div class="stat-card">
        <div class="stat-icon amber"><i class="bi bi-cash-stack"></i></div>
        <div>
          <div class="stat-value" style="font-size:1.2rem"><?= peso($totalSales) ?></div>
          <div class="stat-label">Total Sales</div>
        </div>
      </div>
    </div>
    <div class="col-6 col-md-4 col-xl-2">
      <div class="stat-card">
        <div class="stat-icon purple"><i class="bi bi-clock-history"></i></div>
        <div>
          <div class="stat-value"><?= number_format($pendingOrders) ?></div>
          <div class="stat-label">Pending</div>
        </div>
      </div>
    </div>
    <div class="col-6 col-md-4 col-xl-2">
      <div class="stat-card">
        <div class="stat-icon pink"><i class="bi bi-check-circle-fill"></i></div>
        <div>
          <div class="stat-value"><?= number_format($completedOrders) ?></div>
          <div class="stat-label">Completed</div>
        </div>
      </div>
    </div>
    <div class="col-6 col-md-4 col-xl-2">
      <div class="stat-card">
        <div class="stat-icon blue"><i class="bi bi-sun-fill"></i></div>
        <div>
          <div class="stat-value" style="font-size:1.1rem"><?= peso($todaySales) ?></div>
          <div class="stat-label">Today's Sales</div>
        </div>
      </div>
    </div>
  </div>

  <div class="row g-3 mb-4">
    <!-- Sales Chart -->
    <div class="col-lg-8">
      <div class="card h-100">
        <div class="card-header">
          <i class="bi bi-bar-chart-line text-primary"></i> Monthly Sales (Last 6 Months)
        </div>
        <div class="card-body">
          <canvas id="salesChart" height="100"></canvas>
        </div>
      </div>
    </div>

    <!-- Order Status Summary -->
    <div class="col-lg-4">
      <div class="card h-100">
        <div class="card-header">
          <i class="bi bi-pie-chart-fill text-primary"></i> Order Status
        </div>
        <div class="card-body d-flex flex-column gap-3">
          <?php
          $statuses = [
            ['label'=>'Pending',    'value'=>$pendingOrders,    'color'=>'warning', 'icon'=>'clock'],
            ['label'=>'Processing', 'value'=>$processingOrders, 'color'=>'info',    'icon'=>'gear'],
            ['label'=>'Completed',  'value'=>$completedOrders,  'color'=>'success', 'icon'=>'check-circle'],
            ['label'=>'Claimed',    'value'=>$pdo->query('SELECT COUNT(*) FROM orders WHERE status="claimed"')->fetchColumn(), 'color'=>'secondary', 'icon'=>'bag-check'],
          ];
          foreach ($statuses as $s):
            $pct = $totalOrders > 0 ? round($s['value'] / $totalOrders * 100) : 0;
          ?>
          <div>
            <div class="d-flex justify-content-between mb-1">
              <span class="fw-600 small"><i class="bi bi-<?= $s['icon'] ?> me-1"></i><?= $s['label'] ?></span>
              <span class="small text-muted"><?= $s['value'] ?> (<?= $pct ?>%)</span>
            </div>
            <div class="progress" style="height:8px;border-radius:4px">
              <div class="progress-bar bg-<?= $s['color'] ?>" style="width:<?= $pct ?>%"></div>
            </div>
          </div>
          <?php endforeach; ?>

          <hr class="my-1"/>
          <div class="d-flex justify-content-between align-items-center">
            <span class="small text-muted">Unpaid Balance</span>
            <span class="fw-700 text-danger"><?= peso($unpaidAmount) ?></span>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Recent Orders -->
  <div class="table-card">
    <div class="card-header justify-content-between">
      <span><i class="bi bi-clock-history text-primary"></i> Recent Orders</span>
      <a href="<?= BASE_URL ?>/admin/orders.php" class="btn btn-sm btn-outline-primary">View All</a>
    </div>
    <div class="table-responsive">
      <table class="table">
        <thead>
          <tr>
            <th>Receipt #</th>
            <th>Customer</th>
            <th>Type</th>
            <th>Qty</th>
            <th>Total</th>
            <th>Status</th>
            <th>Payment</th>
            <th>Date</th>
            <th class="no-print">Action</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($recentOrders)): ?>
          <tr><td colspan="9" class="text-center text-muted py-4">No orders yet.</td></tr>
          <?php else: foreach ($recentOrders as $o): ?>
          <tr>
            <td><span class="receipt-no"><?= e($o['receipt_no']) ?></span></td>
            <td><?= e($o['customer_name']) ?></td>
            <td><span class="badge bg-primary-soft text-primary"><?= printTypeLabel($o['print_type']) ?></span></td>
            <td><?= $o['quantity'] ?></td>
            <td class="fw-600"><?= peso($o['total_amount']) ?></td>
            <td><?= statusBadge($o['status']) ?></td>
            <td><?= statusBadge($o['payment_status'] ?? 'unpaid') ?></td>
            <td class="text-muted small"><?= date('M j, Y', strtotime($o['created_at'])) ?></td>
            <td>
              <a href="<?= BASE_URL ?>/admin/orders.php?action=view&id=<?= $o['id'] ?>"
                 class="btn btn-sm btn-outline-secondary btn-icon" title="View">
                <i class="bi bi-eye"></i>
              </a>
            </td>
          </tr>
          <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>
  </div>

</div><!-- /.page-content -->
<?php require_once __DIR__ . '/../includes/footer.php'; ?>

<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>
<script>
(function(){
  const ctx = document.getElementById('salesChart');
  if (!ctx) return;
  new Chart(ctx, {
    type: 'bar',
    data: {
      labels: <?= $chartLabels ?>,
      datasets: [{
        label: 'Sales (₱)',
        data: <?= $chartValues ?>,
        backgroundColor: 'rgba(37,99,235,.15)',
        borderColor: '#2563eb',
        borderWidth: 2,
        borderRadius: 6,
        borderSkipped: false,
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
