<?php
// login.php — Admin Login
require_once __DIR__ . '/includes/config/app.php';
require_once __DIR__ . '/includes/config/database.php';

sessionStart();

// Already logged in → redirect
if (isLoggedIn()) {
    header('Location: ' . BASE_URL . '/admin/dashboard.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = sanitize($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $remember = isset($_POST['remember']);

    if (empty($username) || empty($password)) {
        $error = 'Please enter both username and password.';
    } else {
        $pdo  = getDB();
        $stmt = $pdo->prepare('SELECT * FROM users WHERE username = ? OR email = ? LIMIT 1');
        $stmt->execute([$username, $username]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            // Set session
            $_SESSION['user_id']   = $user['id'];
            $_SESSION['user_name'] = $user['name'];
            $_SESSION['user_role'] = $user['role'];

            // Remember me cookie (30 days)
            if ($remember) {
                $token = bin2hex(random_bytes(32));
                $pdo->prepare('UPDATE users SET remember_token = ? WHERE id = ?')
                    ->execute([$token, $user['id']]);
                setcookie('remember_token', $token, time() + 86400 * 30, '/', '', false, true);
            }

            setFlash('success', 'Welcome back, ' . $user['name'] . '!');
            header('Location: ' . BASE_URL . '/admin/dashboard.php');
            exit;
        } else {
            $error = 'Invalid username or password.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Login — <?= APP_NAME ?></title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" />
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" />
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&display=swap" rel="stylesheet" />
  <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css" />
</head>
<body class="login-page">
  <div class="login-card">
    <!-- Logo -->
    <div class="login-logo">
      <i class="bi bi-printer-fill"></i>
    </div>
    <h1 class="fw-800 mb-1" style="font-size:1.5rem;letter-spacing:-.5px"><?= APP_NAME ?></h1>
    <p class="text-muted mb-4" style="font-size:.9rem">Sign in to your account to continue.</p>

    <?php if ($error): ?>
    <div class="alert alert-danger d-flex align-items-center gap-2 mb-3" style="font-size:.88rem">
      <i class="bi bi-exclamation-triangle-fill"></i>
      <?= e($error) ?>
    </div>
    <?php endif; ?>

    <form method="POST" novalidate>
      <div class="mb-3">
        <label class="form-label" for="username">Username or Email</label>
        <div class="input-group">
          <span class="input-group-text bg-light border-end-0">
            <i class="bi bi-person text-muted"></i>
          </span>
          <input type="text" id="username" name="username" class="form-control border-start-0"
                 placeholder="admin" value="<?= e($_POST['username'] ?? '') ?>" required autofocus />
        </div>
      </div>

      <div class="mb-3">
        <label class="form-label" for="password">Password</label>
        <div class="input-group">
          <span class="input-group-text bg-light border-end-0">
            <i class="bi bi-lock text-muted"></i>
          </span>
          <input type="password" id="password" name="password" class="form-control border-start-0"
                 placeholder="••••••••" required />
          <button class="btn btn-outline-secondary border-start-0" type="button" id="togglePwd">
            <i class="bi bi-eye" id="eyeIcon"></i>
          </button>
        </div>
      </div>

      <div class="mb-4 form-check">
        <input class="form-check-input" type="checkbox" id="remember" name="remember" />
        <label class="form-check-label" for="remember" style="font-size:.88rem">Remember me for 30 days</label>
      </div>

      <button type="submit" class="btn btn-primary w-100 py-2 fw-700">
        <i class="bi bi-box-arrow-in-right me-2"></i>Sign In
      </button>
    </form>

    <div class="mt-4 p-3 rounded-xl" style="background:#f8fafc;font-size:.8rem;color:#64748b">
      <strong>Default credentials:</strong><br>
      Username: <code>admin</code> | Password: <code>admin123</code>
    </div>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <script>
    // Toggle password visibility
    document.getElementById('togglePwd').addEventListener('click', function() {
      const pwd  = document.getElementById('password');
      const icon = document.getElementById('eyeIcon');
      if (pwd.type === 'password') {
        pwd.type = 'text';
        icon.className = 'bi bi-eye-slash';
      } else {
        pwd.type = 'password';
        icon.className = 'bi bi-eye';
      }
    });
  </script>
</body>
</html>
