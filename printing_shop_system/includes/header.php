<?php
// includes/header.php — Top of every admin page
// Variables expected: $pageTitle (string)
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title><?= e($pageTitle ?? 'Dashboard') ?> — <?= APP_NAME ?></title>

  <!-- Bootstrap 5 -->
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" />
  <!-- Bootstrap Icons -->
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" />
  <!-- Google Fonts -->
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&family=JetBrains+Mono:wght@400;600&display=swap" rel="stylesheet" />
  <!-- Custom CSS -->
  <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css" />
</head>
<body>
<!-- Top Navbar -->
<nav class="navbar navbar-expand-lg top-navbar" id="topNavbar">
  <div class="container-fluid px-3">
    <!-- Sidebar toggle (mobile) -->
    <button class="btn btn-link sidebar-toggle me-2" id="sidebarToggle" type="button">
      <i class="bi bi-list fs-4"></i>
    </button>

    <!-- Brand -->
    <a class="navbar-brand d-lg-none" href="<?= BASE_URL ?>/admin/dashboard.php">
      <i class="bi bi-printer-fill text-primary me-1"></i>
      <span class="fw-800">PrintShop</span>
    </a>

    <div class="ms-auto d-flex align-items-center gap-3">
      <!-- Current date -->
      <span class="text-muted small d-none d-md-inline">
        <i class="bi bi-calendar3 me-1"></i><?= date('F j, Y') ?>
      </span>

      <!-- User dropdown -->
      <div class="dropdown">
        <button class="btn btn-light user-btn dropdown-toggle" type="button" data-bs-toggle="dropdown">
          <span class="user-avatar">
            <?= strtoupper(substr(currentUser()['name'] ?? 'A', 0, 1)) ?>
          </span>
          <span class="d-none d-md-inline ms-2 fw-600">
            <?= e(currentUser()['name'] ?? 'Admin') ?>
          </span>
        </button>
        <ul class="dropdown-menu dropdown-menu-end shadow-sm">
          <li><span class="dropdown-item-text text-muted small px-3 py-2">
            Logged in as <strong><?= e(currentUser()['role'] ?? '') ?></strong>
          </span></li>
          <li><hr class="dropdown-divider my-0" /></li>
          <li><a class="dropdown-item" href="<?= BASE_URL ?>/logout.php">
            <i class="bi bi-box-arrow-right me-2 text-danger"></i>Logout
          </a></li>
        </ul>
      </div>
    </div>
  </div>
</nav>

<!-- Flash Message -->
<?php $flash = getFlash(); if ($flash): ?>
<div class="flash-container">
  <div class="alert alert-<?= $flash['type'] === 'error' ? 'danger' : $flash['type'] ?> alert-dismissible fade show shadow-sm" role="alert">
    <i class="bi bi-<?= $flash['type'] === 'success' ? 'check-circle' : ($flash['type'] === 'error' ? 'x-circle' : 'info-circle') ?> me-2"></i>
    <?= e($flash['message']) ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
  </div>
</div>
<?php endif; ?>
