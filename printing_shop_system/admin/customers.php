<?php
// admin/customers.php — Customer Management
require_once __DIR__ . '/../includes/config/app.php';
require_once __DIR__ . '/../includes/config/database.php';
requireLogin();

$pdo    = getDB();
$action = $_GET['action'] ?? 'list';
$id     = (int)($_GET['id'] ?? 0);

// ── Handle POST actions ───────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $act   = $_POST['action'] ?? '';
    $name  = sanitize($_POST['name']    ?? '');
    $email = sanitize($_POST['email']   ?? '');
    $phone = sanitize($_POST['phone']   ?? '');
    $addr  = sanitize($_POST['address'] ?? '');
    $notes = sanitize($_POST['notes']   ?? '');
    $cid   = (int)($_POST['id'] ?? 0);

    if (empty($name)) {
        setFlash('error', 'Customer name is required.');
        header('Location: ' . BASE_URL . '/admin/customers.php');
        exit;
    }

    if ($act === 'add') {
        $stmt = $pdo->prepare(
            'INSERT INTO customers (name,email,phone,address,notes) VALUES (?,?,?,?,?)'
        );
        $stmt->execute([$name, $email, $phone, $addr, $notes]);
        setFlash('success', 'Customer added successfully.');
    } elseif ($act === 'edit' && $cid) {
        $stmt = $pdo->prepare(
            'UPDATE customers SET name=?,email=?,phone=?,address=?,notes=? WHERE id=?'
        );
        $stmt->execute([$name, $email, $phone, $addr, $notes, $cid]);
        setFlash('success', 'Customer updated successfully.');
    } elseif ($act === 'delete' && $cid) {
        // Check for existing orders
        $hasOrders = $pdo->prepare('SELECT COUNT(*) FROM orders WHERE customer_id=?');
        $hasOrders->execute([$cid]);
        if ($hasOrders->fetchColumn() > 0) {
            setFlash('error', 'Cannot delete: customer has existing orders.');
        } else {
            $pdo->prepare('DELETE FROM customers WHERE id=?')->execute([$cid]);
            setFlash('success', 'Customer deleted.');
        }
    }
    header('Location: ' . BASE_URL . '/admin/customers.php');
    exit;
}

// ── Fetch list ────────────────────────────────────────────────
$search = sanitize($_GET['q'] ?? '');
if ($search) {
    $stmt = $pdo->prepare(
        'SELECT c.*, COUNT(o.id) AS order_count FROM customers c
         LEFT JOIN orders o ON o.customer_id=c.id
         WHERE c.name LIKE ? OR c.email LIKE ? OR c.phone LIKE ?
         GROUP BY c.id ORDER BY c.created_at DESC'
    );
    $like = "%$search%";
    $stmt->execute([$like, $like, $like]);
} else {
    $stmt = $pdo->query(
        'SELECT c.*, COUNT(o.id) AS order_count FROM customers c
         LEFT JOIN orders o ON o.customer_id=c.id
         GROUP BY c.id ORDER BY c.created_at DESC'
    );
}
$customers = $stmt->fetchAll();

// Fetch single customer for edit
$editCustomer = null;
if ($action === 'edit' && $id) {
    $stmt = $pdo->prepare('SELECT * FROM customers WHERE id=?');
    $stmt->execute([$id]);
    $editCustomer = $stmt->fetch();
}

$pageTitle  = 'Customers';
$activePage = 'customers';
?>
<?php require_once __DIR__ . '/../includes/header.php'; ?>
<div class="layout-wrapper">
<?php require_once __DIR__ . '/../includes/sidebar.php'; ?>
<div class="main-content"><div class="page-content">

  <div class="page-header">
    <div>
      <h1><i class="bi bi-people-fill me-2 text-primary"></i>Customers</h1>
      <nav aria-label="breadcrumb"><ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="<?= BASE_URL ?>/admin/dashboard.php">Home</a></li>
        <li class="breadcrumb-item active">Customers</li>
      </ol></nav>
    </div>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addCustomerModal">
      <i class="bi bi-person-plus-fill me-1"></i> Add Customer
    </button>
  </div>

  <!-- Search Bar -->
  <div class="card mb-3">
    <div class="card-body py-2">
      <form method="GET" class="d-flex gap-2">
        <input type="text" name="q" class="form-control" placeholder="Search by name, email or phone…"
               value="<?= e($search) ?>" />
        <button class="btn btn-primary" type="submit"><i class="bi bi-search"></i></button>
        <?php if ($search): ?>
        <a href="<?= BASE_URL ?>/admin/customers.php" class="btn btn-outline-secondary">Clear</a>
        <?php endif; ?>
      </form>
    </div>
  </div>

  <!-- Customers Table -->
  <div class="table-card">
    <div class="card-header">
      <i class="bi bi-table text-primary"></i>
      Customer List
      <span class="badge bg-primary ms-2"><?= count($customers) ?></span>
    </div>
    <div class="table-responsive">
      <table class="table" id="customersTable">
        <thead>
          <tr>
            <th>#</th>
            <th>Name</th>
            <th>Email</th>
            <th>Phone</th>
            <th>Address</th>
            <th>Orders</th>
            <th>Added</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($customers)): ?>
          <tr><td colspan="8" class="text-center text-muted py-5">
            <i class="bi bi-people display-4 d-block mb-2 opacity-25"></i>No customers found.
          </td></tr>
          <?php else: foreach ($customers as $i => $c): ?>
          <tr>
            <td class="text-muted small"><?= $i + 1 ?></td>
            <td>
              <div class="d-flex align-items-center gap-2">
                <div class="user-avatar" style="width:32px;height:32px;font-size:.75rem">
                  <?= strtoupper(substr($c['name'], 0, 1)) ?>
                </div>
                <span class="fw-600"><?= e($c['name']) ?></span>
              </div>
            </td>
            <td><?= e($c['email'] ?: '—') ?></td>
            <td><?= e($c['phone'] ?: '—') ?></td>
            <td class="text-muted small"><?= e(mb_strimwidth($c['address'] ?? '', 0, 40, '…')) ?></td>
            <td>
              <a href="<?= BASE_URL ?>/admin/orders.php?customer_id=<?= $c['id'] ?>"
                 class="badge bg-primary-soft text-primary text-decoration-none">
                <?= $c['order_count'] ?> orders
              </a>
            </td>
            <td class="text-muted small"><?= date('M j, Y', strtotime($c['created_at'])) ?></td>
            <td>
              <div class="d-flex gap-1">
                <button class="btn btn-sm btn-outline-primary btn-icon"
                        onclick="openEditModal(<?= htmlspecialchars(json_encode($c), ENT_QUOTES) ?>)"
                        title="Edit">
                  <i class="bi bi-pencil"></i>
                </button>
                <form method="POST" class="d-inline">
                  <input type="hidden" name="action" value="delete" />
                  <input type="hidden" name="id" value="<?= $c['id'] ?>" />
                  <button type="submit" class="btn btn-sm btn-outline-danger btn-icon"
                          data-confirm="Delete customer '<?= e($c['name']) ?>'?"
                          title="Delete">
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

</div></div><!-- /.main-content -->
<?php require_once __DIR__ . '/../includes/footer.php'; ?>

<!-- Add Customer Modal -->
<div class="modal fade" id="addCustomerModal" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <form method="POST">
        <input type="hidden" name="action" value="add" />
        <div class="modal-header">
          <h5 class="modal-title fw-700"><i class="bi bi-person-plus me-2 text-primary"></i>Add Customer</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <?php include __DIR__ . '/../includes/customer_form_fields.php'; ?>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary"><i class="bi bi-check2 me-1"></i>Save Customer</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Edit Customer Modal -->
<div class="modal fade" id="editCustomerModal" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <form method="POST">
        <input type="hidden" name="action" value="edit" />
        <input type="hidden" name="id" id="editId" />
        <div class="modal-header">
          <h5 class="modal-title fw-700"><i class="bi bi-pencil me-2 text-primary"></i>Edit Customer</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <div class="row g-3">
            <div class="col-md-6">
              <label class="form-label">Full Name <span class="text-danger">*</span></label>
              <input type="text" name="name" id="editName" class="form-control" required />
            </div>
            <div class="col-md-6">
              <label class="form-label">Email Address</label>
              <input type="email" name="email" id="editEmail" class="form-control" />
            </div>
            <div class="col-md-6">
              <label class="form-label">Phone Number</label>
              <input type="text" name="phone" id="editPhone" class="form-control" />
            </div>
            <div class="col-md-6">
              <label class="form-label">Address</label>
              <input type="text" name="address" id="editAddress" class="form-control" />
            </div>
            <div class="col-12">
              <label class="form-label">Notes</label>
              <textarea name="notes" id="editNotes" class="form-control" rows="2"></textarea>
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary"><i class="bi bi-check2 me-1"></i>Update Customer</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
function openEditModal(c) {
  document.getElementById('editId').value      = c.id;
  document.getElementById('editName').value    = c.name;
  document.getElementById('editEmail').value   = c.email   || '';
  document.getElementById('editPhone').value   = c.phone   || '';
  document.getElementById('editAddress').value = c.address || '';
  document.getElementById('editNotes').value   = c.notes   || '';
  new bootstrap.Modal(document.getElementById('editCustomerModal')).show();
}
</script>
