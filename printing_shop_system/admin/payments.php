<?php
// admin/payments.php — Payments Management
require_once __DIR__ . '/../includes/config/app.php';
require_once __DIR__ . '/../includes/config/database.php';
requireLogin();

$pdo = getDB();

// ── Handle quick payment update ───────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $orderId   = (int)($_POST['order_id']       ?? 0);
    $paid      = (float)($_POST['amount_paid']   ?? 0);
    $method    = sanitize($_POST['payment_method'] ?? 'cash');
    $status    = sanitize($_POST['payment_status'] ?? 'unpaid');

    if ($orderId) {
        // Check if payment record exists
        $exists = $pdo->prepare('SELECT id FROM payments WHERE order_id=?');
        $exists->execute([$orderId]);
        $payId = $exists->fetchColumn();

        if ($payId) {
            $pdo->prepare(
                'UPDATE payments SET amount_paid=?,payment_method=?,payment_status=?,payment_date=? WHERE order_id=?'
            )->execute([$paid, $method, $status, $status==='paid' ? date('Y-m-d H:i:s') : null, $orderId]);
        } else {
            $pdo->prepare(
                'INSERT INTO payments (order_id,amount_paid,payment_method,payment_status,payment_date) VALUES (?,?,?,?,?)'
            )->execute([$orderId, $paid, $method, $status, $status==='paid' ? date('Y-m-d H:i:s') : null]);
        }
        setFlash('success', 'Payment updated successfully.');
    }
    header('Location: ' . BASE_URL . '/admin/payments.php');
    exit;
}

// ── Filters ───────────────────────────────────────────────────
$filterStatus = sanitize($_GET['status'] ?? '');
$filterDate   = sanitize($_GET['date']   ?? '');
$filterSearch = sanitize($_GET['q']      ?? '');

$where  = ['1=1'];
$params = [];
if ($filterStatus) { $where[] = 'p.payment_status=?';  $params[] = $filterStatus; }
if ($filterDate)   { $where[] = 'DATE(o.created_at)=?'; $params[] = $filterDate; }
if ($filterSearch) {
    $where[] = '(o.receipt_no LIKE ? OR c.name LIKE ?)';
    $params[] = "%$filterSearch%"; $params[] = "%$filterSearch%";
}
$whereStr = implode(' AND ', $where);

$payments = $pdo->prepare(
    "SELECT o.id AS order_id, o.receipt_no, o.total_amount, o.status AS order_status, o.created_at,
            c.name AS customer_name,
            p.id AS payment_id, p.amount_paid, p.payment_method, p.payment_status, p.payment_date
     FROM orders o
     JOIN customers c ON c.id=o.customer_id
     LEFT JOIN payments p ON p.order_id=o.id
     WHERE $whereStr
     ORDER BY o.created_at DESC"
);
$payments->execute($params);
$payments = $payments->fetchAll();

// Totals
$totalPaid   = $pdo->query("SELECT COALESCE(SUM(amount_paid),0) FROM payments WHERE payment_status='paid'")->fetchColumn();
$totalUnpaid = $pdo->query(
    "SELECT COALESCE(SUM(o.total_amount - COALESCE(p.amount_paid,0)),0)
     FROM orders o LEFT JOIN payments p ON p.order_id=o.id
     WHERE (p.payment_status!='paid' OR p.id IS NULL)"
)->fetchColumn();
$totalOrders = $pdo->query("SELECT COUNT(*) FROM orders")->fetchColumn();

$pageTitle  = 'Payments';
$activePage = 'payments';
?>
<?php require_once __DIR__ . '/../includes/header.php'; ?>
<div class="layout-wrapper">
<?php require_once __DIR__ . '/../includes/sidebar.php'; ?>
<div class="main-content"><div class="page-content">

  <div class="page-header">
    <div>
      <h1><i class="bi bi-cash-coin me-2 text-primary"></i>Payments</h1>
      <nav aria-label="breadcrumb"><ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="<?= BASE_URL ?>/admin/dashboard.php">Home</a></li>
        <li class="breadcrumb-item active">Payments</li>
      </ol></nav>
    </div>
  </div>

  <!-- Summary Cards -->
  <div class="row g-3 mb-4">
    <div class="col-md-4">
      <div class="stat-card">
        <div class="stat-icon green"><i class="bi bi-check-circle-fill"></i></div>
        <div>
          <div class="stat-value" style="font-size:1.3rem"><?= peso($totalPaid) ?></div>
          <div class="stat-label">Total Collected</div>
        </div>
      </div>
    </div>
    <div class="col-md-4">
      <div class="stat-card">
        <div class="stat-icon red"><i class="bi bi-exclamation-circle-fill"></i></div>
        <div>
          <div class="stat-value" style="font-size:1.3rem"><?= peso($totalUnpaid) ?></div>
          <div class="stat-label">Outstanding Balance</div>
        </div>
      </div>
    </div>
    <div class="col-md-4">
      <div class="stat-card">
        <div class="stat-icon blue"><i class="bi bi-receipt-cutoff"></i></div>
        <div>
          <div class="stat-value"><?= $totalOrders ?></div>
          <div class="stat-label">Total Orders</div>
        </div>
      </div>
    </div>
  </div>

  <!-- Filters -->
  <div class="card mb-3">
    <div class="card-body py-2">
      <form method="GET" class="row g-2 align-items-center">
        <div class="col-sm-4 col-md-3">
          <input type="text" name="q" class="form-control form-control-sm"
                 placeholder="Receipt # or customer…" value="<?= e($filterSearch) ?>" />
        </div>
        <div class="col-sm-4 col-md-2">
          <select name="status" class="form-select form-select-sm">
            <option value="">All Payments</option>
            <option value="paid"    <?= $filterStatus==='paid'   ?'selected':'' ?>>Paid</option>
            <option value="unpaid"  <?= $filterStatus==='unpaid' ?'selected':'' ?>>Unpaid</option>
            <option value="partial" <?= $filterStatus==='partial'?'selected':'' ?>>Partial</option>
          </select>
        </div>
        <div class="col-sm-4 col-md-2">
          <input type="date" name="date" class="form-control form-control-sm" value="<?= e($filterDate) ?>" />
        </div>
        <div class="col-sm-4 col-md-2 d-flex gap-2">
          <button class="btn btn-primary btn-sm"><i class="bi bi-search"></i></button>
          <a href="<?= BASE_URL ?>/admin/payments.php" class="btn btn-outline-secondary btn-sm">Clear</a>
        </div>
      </form>
    </div>
  </div>

  <!-- Payments Table -->
  <div class="table-card">
    <div class="card-header">
      <i class="bi bi-table text-primary"></i> Payment Records
      <span class="badge bg-primary ms-2"><?= count($payments) ?></span>
    </div>
    <div class="table-responsive">
      <table class="table" id="paymentsTable">
        <thead>
          <tr>
            <th>Receipt #</th>
            <th>Customer</th>
            <th>Order Total</th>
            <th>Amount Paid</th>
            <th>Balance</th>
            <th>Method</th>
            <th>Pay Status</th>
            <th>Order Status</th>
            <th>Date</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($payments)): ?>
          <tr><td colspan="10" class="text-center text-muted py-5">
            <i class="bi bi-cash display-4 d-block mb-2 opacity-25"></i>No payment records found.
          </td></tr>
          <?php else: foreach ($payments as $p):
            $balance = $p['total_amount'] - ($p['amount_paid'] ?? 0);
          ?>
          <tr>
            <td><span class="receipt-no"><?= e($p['receipt_no']) ?></span></td>
            <td class="fw-600"><?= e($p['customer_name']) ?></td>
            <td class="fw-700"><?= peso($p['total_amount']) ?></td>
            <td class="text-success fw-600"><?= peso($p['amount_paid'] ?? 0) ?></td>
            <td class="<?= $balance > 0 ? 'text-danger fw-700' : 'text-muted' ?>"><?= peso($balance) ?></td>
            <td class="text-capitalize"><?= str_replace('_',' ', $p['payment_method'] ?? 'cash') ?></td>
            <td><?= statusBadge($p['payment_status'] ?? 'unpaid') ?></td>
            <td><?= statusBadge($p['order_status']) ?></td>
            <td class="text-muted small"><?= date('M j, Y', strtotime($p['created_at'])) ?></td>
            <td>
              <div class="d-flex gap-1">
                <a href="<?= BASE_URL ?>/admin/orders.php?action=view&id=<?= $p['order_id'] ?>"
                   class="btn btn-sm btn-outline-primary btn-icon" title="View Order">
                  <i class="bi bi-eye"></i>
                </a>
                <button class="btn btn-sm btn-outline-secondary btn-icon"
                        onclick="openPayModal(<?= htmlspecialchars(json_encode($p), ENT_QUOTES) ?>)"
                        title="Update Payment">
                  <i class="bi bi-pencil"></i>
                </button>
              </div>
            </td>
          </tr>
          <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>
  </div>

</div></div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>

<!-- Update Payment Modal -->
<div class="modal fade" id="payModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <form method="POST">
        <input type="hidden" name="order_id" id="payOrderId" />
        <div class="modal-header">
          <h5 class="modal-title fw-700"><i class="bi bi-cash-coin me-2 text-primary"></i>Update Payment</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <div class="mb-3">
            <label class="form-label">Receipt #</label>
            <input type="text" id="payReceipt" class="form-control" readonly />
          </div>
          <div class="mb-3">
            <label class="form-label">Order Total</label>
            <input type="text" id="payTotal" class="form-control" readonly />
          </div>
          <div class="mb-3">
            <label class="form-label">Amount Paid (₱)</label>
            <input type="number" name="amount_paid" id="payAmount" class="form-control" min="0" step="0.01" />
          </div>
          <div class="mb-3">
            <label class="form-label">Payment Method</label>
            <select name="payment_method" id="payMethod" class="form-select">
              <option value="cash">Cash</option>
              <option value="gcash">GCash</option>
              <option value="maya">Maya</option>
              <option value="bank_transfer">Bank Transfer</option>
              <option value="other">Other</option>
            </select>
          </div>
          <div class="mb-3">
            <label class="form-label">Payment Status</label>
            <select name="payment_status" id="payStatus" class="form-select">
              <option value="unpaid">Unpaid</option>
              <option value="paid">Paid</option>
              <option value="partial">Partial</option>
            </select>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary fw-700">
            <i class="bi bi-check2 me-1"></i>Update Payment
          </button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
function openPayModal(p) {
  document.getElementById('payOrderId').value  = p.order_id;
  document.getElementById('payReceipt').value  = p.receipt_no;
  document.getElementById('payTotal').value    = '₱' + parseFloat(p.total_amount).toFixed(2);
  document.getElementById('payAmount').value   = p.amount_paid || 0;
  document.getElementById('payMethod').value   = p.payment_method || 'cash';
  document.getElementById('payStatus').value   = p.payment_status || 'unpaid';
  new bootstrap.Modal(document.getElementById('payModal')).show();
}
</script>
