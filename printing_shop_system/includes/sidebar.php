<?php
// includes/sidebar.php
// Variable expected: $activePage (string) — e.g. 'dashboard', 'orders', etc.
$active = $activePage ?? '';

$navItems = [
  ['href' => BASE_URL . '/admin/dashboard.php',  'icon' => 'bi-speedometer2',       'label' => 'Dashboard',   'key' => 'dashboard'],
  ['href' => BASE_URL . '/admin/orders.php',      'icon' => 'bi-receipt-cutoff',      'label' => 'Orders',      'key' => 'orders'],
  ['href' => BASE_URL . '/admin/customers.php',   'icon' => 'bi-people-fill',         'label' => 'Customers',   'key' => 'customers'],
  ['href' => BASE_URL . '/admin/payments.php',    'icon' => 'bi-cash-coin',           'label' => 'Payments',    'key' => 'payments'],
  ['href' => BASE_URL . '/admin/reports.php',     'icon' => 'bi-bar-chart-line-fill', 'label' => 'Reports',     'key' => 'reports'],
];
?>
<!-- Sidebar Overlay (mobile) -->
<div class="sidebar-overlay" id="sidebarOverlay"></div>

<!-- Sidebar -->
<aside class="sidebar" id="sidebar">
  <!-- Brand -->
  <div class="sidebar-brand">
    <div class="brand-icon">
      <i class="bi bi-printer-fill"></i>
    </div>
    <div class="brand-text">
      <span class="brand-name">PrintShop</span>
      <span class="brand-sub">Manager</span>
    </div>
  </div>

  <nav class="sidebar-nav">
    <span class="nav-section-label">MAIN MENU</span>

    <?php foreach ($navItems as $item): ?>
    <a href="<?= $item['href'] ?>"
       class="nav-link-item <?= $active === $item['key'] ? 'active' : '' ?>">
      <i class="bi <?= $item['icon'] ?>"></i>
      <span><?= $item['label'] ?></span>
      <?php if ($active === $item['key']): ?>
        <span class="active-dot ms-auto"></span>
      <?php endif; ?>
    </a>
    <?php endforeach; ?>

    <span class="nav-section-label mt-3">QUICK ACTIONS</span>
    <a href="<?= BASE_URL ?>/admin/orders.php?action=new"
       class="nav-link-item nav-link-accent">
      <i class="bi bi-plus-circle-fill"></i>
      <span>New Order</span>
    </a>
  </nav>

  <!-- Sidebar Footer -->
  <div class="sidebar-footer">
    <div class="d-flex align-items-center gap-2">
      <div class="user-avatar-sm">
        <?= strtoupper(substr(currentUser()['name'] ?? 'A', 0, 1)) ?>
      </div>
      <div class="lh-1">
        <div class="fw-600 small"><?= e(currentUser()['name'] ?? 'Admin') ?></div>
        <div class="text-muted" style="font-size:.7rem"><?= ucfirst(currentUser()['role'] ?? '') ?></div>
      </div>
      <a href="<?= BASE_URL ?>/logout.php" class="ms-auto text-danger" title="Logout">
        <i class="bi bi-power fs-5"></i>
      </a>
    </div>
  </div>
</aside>
