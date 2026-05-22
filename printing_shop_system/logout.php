<?php
// logout.php
require_once __DIR__ . '/includes/config/app.php';
require_once __DIR__ . '/includes/config/database.php';

sessionStart();

// Clear remember token from DB
if (isset($_SESSION['user_id'])) {
    $pdo = getDB();
    $pdo->prepare('UPDATE users SET remember_token = NULL WHERE id = ?')
        ->execute([$_SESSION['user_id']]);
}

// Clear cookie
setcookie('remember_token', '', time() - 3600, '/', '', false, true);

// Destroy session
session_destroy();

header('Location: ' . BASE_URL . '/login.php');
exit;
