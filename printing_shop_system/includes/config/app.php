<?php
// ============================================================
// includes/config/app.php
// Application constants and global helper functions
// ============================================================

// Base URL – adjust if your folder is named differently
define('BASE_URL', '/printing_shop_system');
define('UPLOAD_DIR', __DIR__ . '/../../assets/uploads/');
define('UPLOAD_URL', BASE_URL . '/assets/uploads/');
define('MAX_FILE_SIZE', 10 * 1024 * 1024); // 10 MB
define('ALLOWED_EXTENSIONS', ['jpg', 'jpeg', 'png', 'gif', 'pdf', 'docx', 'doc', 'xlsx']);
define('APP_NAME', 'PrintShop Manager');

// ── Session helpers ─────────────────────────────────────────

function sessionStart(): void
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
}

function isLoggedIn(): bool
{
    sessionStart();
    return isset($_SESSION['user_id']);
}

function requireLogin(): void
{
    if (!isLoggedIn()) {
        header('Location: ' . BASE_URL . '/login.php');
        exit;
    }
}

function currentUser(): ?array
{
    sessionStart();
    if (!isset($_SESSION['user_id'])) return null;
    return [
        'id'   => $_SESSION['user_id'],
        'name' => $_SESSION['user_name'] ?? 'User',
        'role' => $_SESSION['user_role'] ?? 'staff',
    ];
}

// ── Flash messages ──────────────────────────────────────────

function setFlash(string $type, string $message): void
{
    sessionStart();
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function getFlash(): ?array
{
    sessionStart();
    if (isset($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }
    return null;
}

// ── Sanitize / escape ───────────────────────────────────────

function e(string $s): string
{
    return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
}

function sanitize(string $s): string
{
    return trim(strip_tags($s));
}

// ── Receipt number generator ────────────────────────────────

function generateReceiptNo(): string
{
    // Format: RCP-20250522-A3F9 = 16 chars, well within VARCHAR(30)
    return 'RCP-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -4));
}

// ── Currency format ─────────────────────────────────────────

function peso(float $amount): string
{
    return '₱' . number_format($amount, 2);
}

// ── Print-type labels ───────────────────────────────────────

function printTypeLabel(string $type): string
{
    $labels = [
        'black_white'  => 'Black & White',
        'colored'      => 'Colored',
        'photo_print'  => 'Photo Print',
        'tarpaulin'    => 'Tarpaulin',
        'id_picture'   => 'ID Picture',
    ];
    return $labels[$type] ?? ucfirst($type);
}

// ── Status badge HTML ────────────────────────────────────────

function statusBadge(string $status): string
{
    $map = [
        'pending'    => 'warning',
        'processing' => 'info',
        'completed'  => 'success',
        'claimed'    => 'secondary',
        'paid'       => 'success',
        'unpaid'     => 'danger',
        'partial'    => 'warning',
    ];
    $color = $map[$status] ?? 'dark';
    return '<span class="badge bg-' . $color . '">' . ucfirst($status) . '</span>';
}

// ── File upload handler ─────────────────────────────────────

function handleFileUpload(array $file): array
{
    if ($file['error'] !== UPLOAD_ERR_OK) {
        return ['success' => false, 'error' => 'File upload error code: ' . $file['error']];
    }

    if ($file['size'] > MAX_FILE_SIZE) {
        return ['success' => false, 'error' => 'File size exceeds 10 MB limit.'];
    }

    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, ALLOWED_EXTENSIONS)) {
        return ['success' => false, 'error' => 'File type not allowed.'];
    }

    if (!is_dir(UPLOAD_DIR)) {
        mkdir(UPLOAD_DIR, 0755, true);
    }

    $newName = uniqid('file_', true) . '.' . $ext;
    $dest    = UPLOAD_DIR . $newName;

    if (!move_uploaded_file($file['tmp_name'], $dest)) {
        return ['success' => false, 'error' => 'Could not save the file.'];
    }

    return ['success' => true, 'filename' => $newName, 'original' => $file['name']];
}
