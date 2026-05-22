<?php
// admin/orders.php — Orders Management (List + Create + View + Edit + Delete)
require_once __DIR__ . '/../includes/config/app.php';
require_once __DIR__ . '/../includes/config/database.php';
requireLogin();

$pdo    = getDB();
$action = $_GET['action'] ?? 'list';
$id     = (int)($_GET['id'] ?? 0);

// ── Handle POST ────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $act         = $_POST['action'] ?? '';
    $custId      = (int)($_POST['customer_id'] ?? 0);
    $printType   = sanitize($_POST['print_type']   ?? '');
    $paperSize   = sanitize($_POST['paper_size']   ?? '');
    $qty         = (int)($_POST['quantity']        ?? 1);
    $unitPrice   = (float)($_POST['unit_price']    ?? 0);
    $total       = $qty * $unitPrice;
    $notes       = sanitize($_POST['notes']        ?? '');
    $status      = sanitize($_POST['status']       ?? 'pending');
    $payStatus   = sanitize($_POST['payment_status'] ?? 'unpaid');
    $amountPaid  = (float)($_POST['amount_paid']   ?? 0);
    $payMethod   = sanitize($_POST['payment_method'] ?? 'cash');
    $oid         = (int)($_POST['order_id'] ?? 0);

    // Validate
    $errors = [];
    if (!$custId)      $errors[] = 'Please select a customer.';
    if (!$printType)   $errors[] = 'Print type is required.';
    if (!$paperSize)   $errors[] = 'Paper size is required.';
    if ($qty < 1)      $errors[] = 'Quantity must be at least 1.';
    if ($unitPrice < 0) $errors[] = 'Unit price cannot be negative.';

    // File upload
    $filePath = null; $fileName = null;
    if (!empty($_FILES['upload_file']['name'])) {
        $upload = handleFileUpload($_FILES['upload_file']);
        if (!$upload['success']) {
            $errors[] = $upload['error'];
        } else {
            $filePath = $upload['filename'];
            $fileName = $upload['original'];
        }
    }

    if ($errors) {
        setFlash('error', implode(' | ', $errors));
        header('Location: ' . BASE_URL . '/admin/orders.php?action=' . ($act === 'create' ? 'new' : 'edit') . ($oid ? '&id='.$oid : ''));
        exit;
    }

    if ($act === 'create') {
        $receiptNo = generateReceiptNo();
        $stmt = $pdo->prepare(
            'INSERT INTO orders (receipt_no,customer_id,print_type,paper_size,quantity,unit_price,total_amount,file_path,file_name,notes,status,created_by)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,?)'
        );
        $stmt->execute([$receiptNo, $custId, $printType, $paperSize, $qty, $unitPrice, $total, $filePath, $fileName, $notes, $status, currentUser()['id']]);
        $newOrderId = $pdo->lastInsertId();

        // Insert payment record
        $pdo->prepare(
            'INSERT INTO payments (order_id,amount_paid,payment_method,payment_status,payment_date)
             VALUES (?,?,?,?,?)'
        )->execute([
            $newOrderId, $amountPaid, $payMethod, $payStatus,
            $payStatus === 'paid' ? date('Y-m-d H:i:s') : null
        ]);

        setFlash('success', "Order $receiptNo created successfully!");
        header('Location: ' . BASE_URL . '/admin/orders.php?action=view&id=' . $newOrderId);
        exit;

    } elseif ($act === 'update' && $oid) {
        $updateFields = 'customer_id=?,print_type=?,paper_size=?,quantity=?,unit_price=?,total_amount=?,notes=?,status=?';
        $params = [$custId, $printType, $paperSize, $qty, $unitPrice, $total, $notes, $status];
        if ($filePath) {
            $updateFields .= ',file_path=?,file_name=?';
            $params[] = $filePath; $params[] = $fileName;
        }
        $params[] = $oid;
        $pdo->prepare("UPDATE orders SET $updateFields WHERE id=?")->execute($params);

        // Update payment
        $pdo->prepare(
            'UPDATE payments SET amount_paid=?,payment_method=?,payment_status=?,payment_date=? WHERE order_id=?'
        )->execute([
            $amountPaid, $payMethod, $payStatus,
            $payStatus === 'paid' ? date('Y-m-d H:i:s') : null,
            $oid
        ]);

        setFlash('success', 'Order updated successfully.');
        header('Location: ' . BASE_URL . '/admin/orders.php?action=view&id=' . $oid);
        exit;

    } elseif ($act === 'delete' && $oid) {
        $pdo->prepare('DELETE FROM orders WHERE id=?')->execute([$oid]);
        setFlash('success', 'Order deleted.');
        header('Location: ' . BASE_URL . '/admin/orders.php');
        exit;

    } elseif ($act === 'update_status' && $oid) {
        $pdo->prepare('UPDATE orders SET status=? WHERE id=?')->execute([$status, $oid]);
        setFlash('success', 'Order status updated.');
        header('Location: ' . BASE_URL . '/admin/orders.php?action=view&id=' . $oid);
        exit;
    }
}

// ── Load data for forms ────────────────────────────────────────
$customers = $pdo->query('SELECT id, name FROM customers ORDER BY name ASC')->fetchAll();

// ── View single order ──────────────────────────────────────────
$order = null;
if (($action === 'view' || $action === 'edit') && $id) {
    $stmt = $pdo->prepare(
        'SELECT o.*, c.name AS customer_name, c.phone AS customer_phone, c.email AS customer_email,
                p.payment_status, p.amount_paid, p.payment_method, p.payment_date
         FROM orders o
         JOIN customers c ON c.id=o.customer_id
         LEFT JOIN payments p ON p.order_id=o.id
         WHERE o.id=?'
    );
    $stmt->execute([$id]);
    $order = $stmt->fetch();
    if (!$order) { setFlash('error','Order not found.'); header('Location: '.BASE_URL.'/admin/orders.php'); exit; }
}

// ── List with filters ──────────────────────────────────────────
$filterStatus   = sanitize($_GET['status']      ?? '');
$filterCustomer = (int)($_GET['customer_id']    ?? 0);
$filterSearch   = sanitize($_GET['q']           ?? '');
$filterDate     = sanitize($_GET['date']        ?? '');

$where  = ['1=1'];
$params = [];
if ($filterStatus)   { $where[] = 'o.status=?';          $params[] = $filterStatus; }
if ($filterCustomer) { $where[] = 'o.customer_id=?';      $params[] = $filterCustomer; }
if ($filterDate)     { $where[] = 'DATE(o.created_at)=?'; $params[] = $filterDate; }
if ($filterSearch)   {
    $where[] = '(o.receipt_no LIKE ? OR c.name LIKE ?)';
    $params[] = "%$filterSearch%"; $params[] = "%$filterSearch%";
}

$whereStr = implode(' AND ', $where);
$stmt = $pdo->prepare(
    "SELECT o.*, c.name AS customer_name, p.payment_status, p.amount_paid
     FROM orders o
     JOIN customers c ON c.id=o.customer_id
     LEFT JOIN payments p ON p.order_id=o.id
     WHERE $whereStr
     ORDER BY o.created_at DESC"
);
$stmt->execute($params);
$orders = $stmt->fetchAll();

$pageTitle  = 'Orders';
$activePage = 'orders';

// Paper sizes list
$paperSizes = ['Short (8.5x11)','Long (8.5x13)','A4 (8.27x11.69)','A3 (11.69x16.54)','4R (4x6)','5R (5x7)','Custom'];
$printTypes = ['black_white','colored','photo_print','tarpaulin','id_picture'];
$statuses   = ['pending','processing','completed','claimed'];
?>
<?php require_once __DIR__ . '/../includes/header.php'; ?>
<div class="layout-wrapper">
<?php require_once __DIR__ . '/../includes/sidebar.php'; ?>
<div class="main-content"><div class="page-content">

<?php // ═══════════ LIST VIEW ══════════════════════════════════
if ($action === 'list'): ?>

  <div class="page-header">
    <div>
      <h1><i class="bi bi-receipt-cutoff me-2 text-primary"></i>Orders</h1>
      <nav aria-label="breadcrumb"><ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="<?= BASE_URL ?>/admin/dashboard.php">Home</a></li>
        <li class="breadcrumb-item active">Orders</li>
      </ol></nav>
    </div>
    <a href="<?= BASE_URL ?>/admin/orders.php?action=new" class="btn btn-primary">
      <i class="bi bi-plus-circle-fill me-1"></i> New Order
    </a>
  </div>

  <!-- Filters -->
  <div class="card mb-3">
    <div class="card-body py-2">
      <form method="GET" class="row g-2 align-items-center">
        <input type="hidden" name="action" value="list" />
        <div class="col-sm-4 col-md-3">
          <input type="text" name="q" class="form-control form-control-sm" placeholder="Search receipt/customer…"
                 value="<?= e($filterSearch) ?>" />
        </div>
        <div class="col-sm-4 col-md-2">
          <select name="status" class="form-select form-select-sm">
            <option value="">All Statuses</option>
            <?php foreach ($statuses as $s): ?>
            <option value="<?= $s ?>" <?= $filterStatus===$s?'selected':'' ?>><?= ucfirst($s) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-sm-4 col-md-3">
          <select name="customer_id" class="form-select form-select-sm">
            <option value="">All Customers</option>
            <?php foreach ($customers as $c): ?>
            <option value="<?= $c['id'] ?>" <?= $filterCustomer==$c['id']?'selected':'' ?>><?= e($c['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-sm-4 col-md-2">
          <input type="date" name="date" class="form-control form-control-sm" value="<?= e($filterDate) ?>" />
        </div>
        <div class="col-sm-4 col-md-2 d-flex gap-2">
          <button class="btn btn-primary btn-sm" type="submit"><i class="bi bi-search"></i></button>
          <a href="<?= BASE_URL ?>/admin/orders.php" class="btn btn-outline-secondary btn-sm">Clear</a>
        </div>
      </form>
    </div>
  </div>

  <div class="table-card">
    <div class="card-header">
      <i class="bi bi-table text-primary"></i> Order List
      <span class="badge bg-primary ms-2"><?= count($orders) ?></span>
    </div>
    <div class="table-responsive">
      <table class="table" id="ordersTable">
        <thead>
          <tr>
            <th>Receipt #</th>
            <th>Customer</th>
            <th>Print Type</th>
            <th>Size</th>
            <th>Qty</th>
            <th>Total</th>
            <th>Payment</th>
            <th>Status</th>
            <th>Date</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($orders)): ?>
          <tr><td colspan="10" class="text-center text-muted py-5">
            <i class="bi bi-inbox display-4 d-block mb-2 opacity-25"></i>No orders found.
          </td></tr>
          <?php else: foreach ($orders as $o): ?>
          <tr>
            <td><span class="receipt-no"><?= e($o['receipt_no']) ?></span></td>
            <td class="fw-600"><?= e($o['customer_name']) ?></td>
            <td><span class="badge bg-primary-soft text-primary"><?= printTypeLabel($o['print_type']) ?></span></td>
            <td class="text-muted small"><?= e($o['paper_size']) ?></td>
            <td><?= $o['quantity'] ?></td>
            <td class="fw-700"><?= peso($o['total_amount']) ?></td>
            <td><?= statusBadge($o['payment_status'] ?? 'unpaid') ?></td>
            <td><?= statusBadge($o['status']) ?></td>
            <td class="text-muted small"><?= date('M j, Y', strtotime($o['created_at'])) ?></td>
            <td>
              <div class="d-flex gap-1">
                <a href="?action=view&id=<?= $o['id'] ?>" class="btn btn-sm btn-outline-primary btn-icon" title="View"><i class="bi bi-eye"></i></a>
                <a href="?action=edit&id=<?= $o['id'] ?>" class="btn btn-sm btn-outline-secondary btn-icon" title="Edit"><i class="bi bi-pencil"></i></a>
                <form method="POST" class="d-inline">
                  <input type="hidden" name="action" value="delete" />
                  <input type="hidden" name="order_id" value="<?= $o['id'] ?>" />
                  <button type="submit" class="btn btn-sm btn-outline-danger btn-icon"
                          data-confirm="Delete order <?= e($o['receipt_no']) ?>?" title="Delete">
                    <i class="bi bi-trash3"></i>
                  </button>
                </form>
              </div>
            </td>
          </tr>
          <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>
  </div>

<?php // ═══════════ NEW ORDER FORM ════════════════════════════
elseif ($action === 'new' || ($action === 'edit' && $order)): ?>

  <div class="page-header">
    <div>
      <h1><i class="bi bi-<?= $action==='new'?'plus-circle':'pencil' ?> me-2 text-primary"></i>
        <?= $action==='new' ? 'New Order' : 'Edit Order — ' . e($order['receipt_no']) ?>
      </h1>
      <nav aria-label="breadcrumb"><ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="<?= BASE_URL ?>/admin/dashboard.php">Home</a></li>
        <li class="breadcrumb-item"><a href="<?= BASE_URL ?>/admin/orders.php">Orders</a></li>
        <li class="breadcrumb-item active"><?= $action==='new'?'New':'Edit' ?></li>
      </ol></nav>
    </div>
    <a href="<?= BASE_URL ?>/admin/orders.php" class="btn btn-outline-secondary">
      <i class="bi bi-arrow-left me-1"></i> Back
    </a>
  </div>

  <form method="POST" enctype="multipart/form-data">
    <input type="hidden" name="action"   value="<?= $action==='new'?'create':'update' ?>" />
    <input type="hidden" name="order_id" value="<?= $order['id'] ?? '' ?>" />

    <div class="row g-3">
      <!-- Left Column -->
      <div class="col-lg-8">
        <div class="card mb-3">
          <div class="card-header"><i class="bi bi-person text-primary"></i> Customer & Order Info</div>
          <div class="card-body">
            <div class="row g-3">
              <div class="col-md-6">
                <label class="form-label">Customer <span class="text-danger">*</span></label>
                <select name="customer_id" class="form-select" required>
                  <option value="">— Select Customer —</option>
                  <?php foreach ($customers as $c): ?>
                  <option value="<?= $c['id'] ?>"
                    <?= ($order['customer_id'] ?? '') == $c['id'] ? 'selected' : '' ?>>
                    <?= e($c['name']) ?>
                  </option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="col-md-6">
                <label class="form-label">Print Type <span class="text-danger">*</span></label>
                <select name="print_type" id="print_type" class="form-select" required>
                  <option value="">— Select Type —</option>
                  <?php foreach ($printTypes as $pt): ?>
                  <option value="<?= $pt ?>"
                    <?= ($order['print_type'] ?? '') === $pt ? 'selected' : '' ?>>
                    <?= printTypeLabel($pt) ?>
                  </option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="col-md-6">
                <label class="form-label">Paper Size <span class="text-danger">*</span></label>
                <select name="paper_size" class="form-select" required>
                  <option value="">— Select Size —</option>
                  <?php foreach ($paperSizes as $ps): ?>
                  <option value="<?= $ps ?>"
                    <?= ($order['paper_size'] ?? '') === $ps ? 'selected' : '' ?>>
                    <?= $ps ?>
                  </option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="col-md-6">
                <label class="form-label">Order Status</label>
                <select name="status" class="form-select">
                  <?php foreach ($statuses as $s): ?>
                  <option value="<?= $s ?>"
                    <?= ($order['status'] ?? 'pending') === $s ? 'selected' : '' ?>>
                    <?= ucfirst($s) ?>
                  </option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="col-md-4">
                <label class="form-label">Quantity <span class="text-danger">*</span></label>
                <input type="number" name="quantity" id="quantity" class="form-control"
                       value="<?= $order['quantity'] ?? 1 ?>" min="1" required />
              </div>
              <div class="col-md-4">
                <label class="form-label">Unit Price (₱) <span class="text-danger">*</span></label>
                <input type="number" name="unit_price" id="unit_price" class="form-control"
                       value="<?= $order['unit_price'] ?? '' ?>" min="0" step="0.01" required />
              </div>
              <div class="col-md-4">
                <label class="form-label">Total Amount (₱)</label>
                <input type="number" name="total_amount" id="total_amount" class="form-control bg-light"
                       value="<?= $order['total_amount'] ?? '' ?>" readonly />
              </div>
              <div class="col-12">
                <label class="form-label">Notes / Special Instructions</label>
                <textarea name="notes" class="form-control" rows="2"
                          placeholder="e.g. glossy finish, double-sided…"><?= e($order['notes'] ?? '') ?></textarea>
              </div>
            </div>
          </div>
        </div>

        <!-- File Upload -->
        <div class="card mb-3">
          <div class="card-header"><i class="bi bi-paperclip text-primary"></i> File / Document Upload</div>
          <div class="card-body">
            <?php if (!empty($order['file_name'])): ?>
            <div class="alert alert-info py-2 small mb-3">
              <i class="bi bi-file-earmark me-1"></i>
              Current file: <strong><?= e($order['file_name']) ?></strong>
              <a href="<?= UPLOAD_URL . e($order['file_path']) ?>" target="_blank" class="ms-2">
                <i class="bi bi-download"></i> Download
              </a>
            </div>
            <?php endif; ?>
            <input type="file" name="upload_file" class="form-control"
                   accept=".jpg,.jpeg,.png,.gif,.pdf,.doc,.docx,.xlsx" />
            <div class="form-text">Allowed: JPG, PNG, PDF, DOC, DOCX, XLSX. Max 10 MB.</div>
          </div>
        </div>
      </div>

      <!-- Right Column: Payment -->
      <div class="col-lg-4">
        <div class="card mb-3">
          <div class="card-header"><i class="bi bi-cash-coin text-primary"></i> Payment Info</div>
          <div class="card-body">
            <div class="mb-3">
              <label class="form-label">Payment Status</label>
              <select name="payment_status" class="form-select">
                <option value="unpaid"  <?= ($order['payment_status'] ?? 'unpaid') === 'unpaid'  ? 'selected':'' ?>>Unpaid</option>
                <option value="paid"    <?= ($order['payment_status'] ?? '') === 'paid'    ? 'selected':'' ?>>Paid</option>
                <option value="partial" <?= ($order['payment_status'] ?? '') === 'partial'  ? 'selected':'' ?>>Partial</option>
              </select>
            </div>
            <div class="mb-3">
              <label class="form-label">Amount Paid (₱)</label>
              <input type="number" name="amount_paid" class="form-control"
                     value="<?= $order['amount_paid'] ?? 0 ?>" min="0" step="0.01" />
            </div>
            <div class="mb-3">
              <label class="form-label">Payment Method</label>
              <select name="payment_method" class="form-select">
                <?php foreach (['cash','gcash','maya','bank_transfer','other'] as $m): ?>
                <option value="<?= $m ?>"
                  <?= ($order['payment_method'] ?? 'cash') === $m ? 'selected':'' ?>>
                  <?= ucwords(str_replace('_',' ',$m)) ?>
                </option>
                <?php endforeach; ?>
              </select>
            </div>
          </div>
        </div>

        <div class="card">
          <div class="card-body">
            <button type="submit" class="btn btn-primary w-100 py-2 fw-700">
              <i class="bi bi-<?= $action==='new'?'plus-circle':'check2' ?>-fill me-2"></i>
              <?= $action==='new' ? 'Create Order' : 'Update Order' ?>
            </button>
            <a href="<?= BASE_URL ?>/admin/orders.php" class="btn btn-light w-100 mt-2">Cancel</a>
          </div>
        </div>
      </div>
    </div>
  </form>

<?php // ═══════════ VIEW ORDER ════════════════════════════════
elseif ($action === 'view' && $order): ?>

  <div class="page-header">
    <div>
      <h1><i class="bi bi-receipt me-2 text-primary"></i><?= e($order['receipt_no']) ?></h1>
      <nav aria-label="breadcrumb"><ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="<?= BASE_URL ?>/admin/dashboard.php">Home</a></li>
        <li class="breadcrumb-item"><a href="<?= BASE_URL ?>/admin/orders.php">Orders</a></li>
        <li class="breadcrumb-item active"><?= e($order['receipt_no']) ?></li>
      </ol></nav>
    </div>
    <div class="d-flex gap-2 flex-wrap">
      <button onclick="printReceipt('receiptBlock')" class="btn btn-outline-secondary">
        <i class="bi bi-printer me-1"></i> Print Receipt
      </button>
      <a href="?action=edit&id=<?= $order['id'] ?>" class="btn btn-outline-primary">
        <i class="bi bi-pencil me-1"></i> Edit
      </a>
      <form method="POST" class="d-inline">
        <input type="hidden" name="action"   value="delete" />
        <input type="hidden" name="order_id" value="<?= $order['id'] ?>" />
        <button type="submit" class="btn btn-outline-danger"
                data-confirm="Delete this order permanently?">
          <i class="bi bi-trash3 me-1"></i> Delete
        </button>
      </form>
    </div>
  </div>

  <div class="row g-3">
    <div class="col-lg-8">
      <!-- Order Details Card -->
      <div class="card mb-3">
        <div class="card-header justify-content-between">
          <span><i class="bi bi-info-circle text-primary"></i> Order Details</span>
          <?= statusBadge($order['status']) ?>
        </div>
        <div class="card-body">
          <div class="row g-3">
            <div class="col-md-6">
              <p class="text-muted small mb-1">Customer</p>
              <p class="fw-700 mb-0"><?= e($order['customer_name']) ?></p>
              <p class="small text-muted mb-0"><?= e($order['customer_phone'] ?: '') ?></p>
            </div>
            <div class="col-md-6">
              <p class="text-muted small mb-1">Print Type</p>
              <p class="fw-700 mb-0"><?= printTypeLabel($order['print_type']) ?></p>
            </div>
            <div class="col-md-4">
              <p class="text-muted small mb-1">Paper Size</p>
              <p class="fw-600 mb-0"><?= e($order['paper_size']) ?></p>
            </div>
            <div class="col-md-4">
              <p class="text-muted small mb-1">Quantity</p>
              <p class="fw-600 mb-0"><?= $order['quantity'] ?> copies</p>
            </div>
            <div class="col-md-4">
              <p class="text-muted small mb-1">Unit Price</p>
              <p class="fw-600 mb-0"><?= peso($order['unit_price']) ?></p>
            </div>
            <?php if ($order['notes']): ?>
            <div class="col-12">
              <p class="text-muted small mb-1">Notes</p>
              <p class="mb-0"><?= e($order['notes']) ?></p>
            </div>
            <?php endif; ?>
            <?php if ($order['file_name']): ?>
            <div class="col-12">
              <p class="text-muted small mb-1">Attached File</p>
              <a href="<?= UPLOAD_URL . e($order['file_path']) ?>" target="_blank" class="btn btn-sm btn-outline-primary">
                <i class="bi bi-file-earmark-arrow-down me-1"></i><?= e($order['file_name']) ?>
              </a>
            </div>
            <?php endif; ?>
          </div>
        </div>
      </div>

      <!-- Quick Status Update -->
      <div class="card">
        <div class="card-header"><i class="bi bi-arrow-repeat text-primary"></i> Update Status</div>
        <div class="card-body">
          <form method="POST" class="d-flex gap-2 flex-wrap">
            <input type="hidden" name="action"   value="update_status" />
            <input type="hidden" name="order_id" value="<?= $order['id'] ?>" />
            <select name="status" class="form-select" style="max-width:200px">
              <?php foreach ($statuses as $s): ?>
              <option value="<?= $s ?>" <?= $order['status']===$s?'selected':'' ?>><?= ucfirst($s) ?></option>
              <?php endforeach; ?>
            </select>
            <button type="submit" class="btn btn-primary">
              <i class="bi bi-check2 me-1"></i>Update
            </button>
          </form>
        </div>
      </div>
    </div>

    <div class="col-lg-4">
      <!-- Payment Summary -->
      <div class="card mb-3">
        <div class="card-header"><i class="bi bi-cash-coin text-primary"></i> Payment Summary</div>
        <div class="card-body">
          <div class="d-flex justify-content-between mb-2">
            <span class="text-muted">Total Amount</span>
            <span class="fw-700 fs-5"><?= peso($order['total_amount']) ?></span>
          </div>
          <div class="d-flex justify-content-between mb-2">
            <span class="text-muted">Amount Paid</span>
            <span class="fw-600 text-success"><?= peso($order['amount_paid'] ?? 0) ?></span>
          </div>
          <div class="d-flex justify-content-between mb-3">
            <span class="text-muted">Balance</span>
            <span class="fw-700 text-danger"><?= peso($order['total_amount'] - ($order['amount_paid'] ?? 0)) ?></span>
          </div>
          <div class="d-flex justify-content-between mb-2">
            <span class="text-muted">Payment Status</span>
            <?= statusBadge($order['payment_status'] ?? 'unpaid') ?>
          </div>
          <div class="d-flex justify-content-between">
            <span class="text-muted">Method</span>
            <span class="fw-600"><?= ucwords(str_replace('_',' ', $order['payment_method'] ?? 'cash')) ?></span>
          </div>
        </div>
      </div>

      <!-- Receipt Preview -->
      <div class="card">
        <div class="card-header"><i class="bi bi-receipt text-primary"></i> Receipt Preview</div>
        <div class="card-body p-3">
          <div id="receiptBlock" class="receipt-print">
            <div class="receipt-header">
              <h2>🖨️ <?= APP_NAME ?></h2>
              <small>Your local printing partner</small><br>
              <small><?= date('F j, Y  g:i A') ?></small>
            </div>
            <div class="receipt-row"><span>Receipt #</span><span><?= e($order['receipt_no']) ?></span></div>
            <div class="receipt-row"><span>Customer</span><span><?= e($order['customer_name']) ?></span></div>
            <div class="receipt-row"><span>Print Type</span><span><?= printTypeLabel($order['print_type']) ?></span></div>
            <div class="receipt-row"><span>Paper Size</span><span><?= e($order['paper_size']) ?></span></div>
            <div class="receipt-row"><span>Quantity</span><span><?= $order['quantity'] ?></span></div>
            <div class="receipt-row"><span>Unit Price</span><span><?= peso($order['unit_price']) ?></span></div>
            <div class="receipt-row receipt-total"><span>TOTAL</span><span><?= peso($order['total_amount']) ?></span></div>
            <div class="receipt-row"><span>Paid</span><span><?= peso($order['amount_paid'] ?? 0) ?></span></div>
            <div class="receipt-row"><span>Balance</span><span><?= peso($order['total_amount'] - ($order['amount_paid']??0)) ?></span></div>
            <div class="receipt-row"><span>Status</span><span><?= strtoupper($order['status']) ?></span></div>
            <br><div style="text-align:center;font-size:.75rem;color:#888">Thank you for your business!<br>Please come again.</div>
          </div>
        </div>
      </div>
    </div>
  </div>

<?php endif; ?>

</div></div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
