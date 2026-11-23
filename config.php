<?php
declare(strict_types=1);
session_start();

function env_value(string $key, string $default = ''): string {
    static $values = null;
    if ($values === null) {
        $values = [];
        $path = __DIR__ . '/.env';
        if (is_file($path)) {
            foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
                if (!str_starts_with(trim($line), '#') && str_contains($line, '=')) {
                    [$k, $v] = explode('=', $line, 2);
                    $values[trim($k)] = trim($v);
                }
            }
        }
    }
    return $values[$key] ?? getenv($key) ?: $default;
}

function db(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $pdo = new PDO('mysql:host=' . env_value('DB_HOST', '127.0.0.1') . ';dbname=' . env_value('DB_NAME', 'triwear') . ';charset=utf8mb4', env_value('DB_USER', 'root'), env_value('DB_PASS'));
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    }
    return $pdo;
}

function e(?string $value): string { return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8'); }
function url(string $path = ''): string {
    $base = rtrim(env_value('APP_URL', ''), '/');
    if ($base === '') {
        $script = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
        $directory = dirname($script);
        $base = str_contains($directory, '/admin') ? dirname($directory) : $directory;
        $base = $base === '/' || $base === '.' ? '' : rtrim($base, '/');
    }
    return $base . '/' . ltrim($path, '/');
}
function redirect(string $path): never { header('Location: ' . url($path)); exit; }
function csrf_token(): string { return $_SESSION['csrf'] ??= bin2hex(random_bytes(32)); }
function verify_csrf(): void { if (!hash_equals($_SESSION['csrf'] ?? '', $_POST['csrf'] ?? '')) { http_response_code(419); exit('Permintaan tidak valid. Silakan muat ulang halaman.'); } }
function flash(string $key, ?string $message = null): ?string { if ($message !== null) { $_SESSION['flash'][$key] = $message; return null; } $msg = $_SESSION['flash'][$key] ?? null; unset($_SESSION['flash'][$key]); return $msg; }
function setting(string $key, string $fallback = ''): string { try { $stmt = db()->prepare('SELECT setting_value FROM site_settings WHERE setting_key = ?'); $stmt->execute([$key]); return $stmt->fetchColumn() ?: $fallback; } catch (Throwable) { return $fallback; } }
function is_admin(): bool { return !empty($_SESSION['admin_id']); }
function require_admin(): void { if (!is_admin()) redirect('admin/login.php'); }
function cart_count(): int { return array_sum(array_map(fn($item) => (int)$item['quantity'], $_SESSION['cart'] ?? [])); }
function money(float $amount): string { return 'Rp ' . number_format($amount, 0, ',', '.'); }

if (PHP_SAPI !== 'cli' && basename($_SERVER['SCRIPT_NAME'] ?? '') === 'track.php' && !defined('TRIWEAR_TRACK_RENDER')) {
    redirect('index.php');
}

if (PHP_SAPI !== 'cli' && basename($_SERVER['SCRIPT_NAME'] ?? '') === 'payment-proof.php' && !defined('TRIWEAR_PAYMENT_RENDER')) {
    define('TRIWEAR_PAYMENT_RENDER', true);
    require __DIR__ . '/includes/payment-render-v2.php';
    exit;
}

if (PHP_SAPI !== 'cli' && basename($_SERVER['SCRIPT_NAME'] ?? '') === 'checkout.php' && !defined('TRIWEAR_CHECKOUT_RENDER')) {
    define('TRIWEAR_CHECKOUT_RENDER', true);
    require __DIR__ . '/includes/checkout-render.php';
    exit;
}

if (PHP_SAPI !== 'cli' && basename($_SERVER['SCRIPT_NAME'] ?? '') === 'confirmation.php' && !defined('TRIWEAR_CONFIRMATION_RENDER')) {
    define('TRIWEAR_CONFIRMATION_RENDER', true);
    require __DIR__ . '/includes/confirmation-render.php';
    exit;
}

if (PHP_SAPI !== 'cli' && basename($_SERVER['SCRIPT_NAME'] ?? '') === 'payment-confirmation.php' && !defined('TRIWEAR_PAYMENT_CONFIRMATION_RENDER')) {
    define('TRIWEAR_PAYMENT_CONFIRMATION_RENDER', true);
    require __DIR__ . '/includes/payment-confirmation-render.php';
    exit;
}

if (PHP_SAPI !== 'cli' && str_ends_with(str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? ''), '/admin/login.php') && !defined('TRIWEAR_ADMIN_LOGIN_RENDER')) {
    define('TRIWEAR_ADMIN_LOGIN_RENDER', true);
    require __DIR__ . '/includes/admin-login-render.php';
    exit;
}

if (PHP_SAPI !== 'cli' && str_ends_with(str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? ''), '/admin/orders.php') && !defined('TRIWEAR_ADMIN_ORDERS_RENDER')) {
    define('TRIWEAR_ADMIN_ORDERS_RENDER', true);
    require __DIR__ . '/includes/admin-orders-render.php';
    exit;
}
