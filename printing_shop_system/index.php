<?php
// index.php — redirect to dashboard or login
require_once __DIR__ . '/includes/config/app.php';
sessionStart();
if (isLoggedIn()) {
    header('Location: ' . BASE_URL . '/admin/dashboard.php');
} else {
    header('Location: ' . BASE_URL . '/login.php');
}
exit;
