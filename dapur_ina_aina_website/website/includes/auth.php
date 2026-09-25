<?php
/**
 * Auth helper: session management & role-based access control
 * Sistem Manajemen Restoran "Dapur Ina Aina"
 */

session_start();

/**
 * Cek apakah user sudah login.
 * Jika belum, redirect ke halaman login.
 */
function cek_login(): void
{
    if (!isset($_SESSION['user_id'])) {
        header('Location: login.php');
        exit;
    }
}

/**
 * Cek apakah role user sesuai dengan yang diizinkan.
 * @param array|string $roles Role yang diizinkan (string atau array)
 */
function cek_role(array|string $roles): void
{
    cek_login();
    if (is_string($roles)) {
        $roles = [$roles];
    }
    if (!in_array($_SESSION['role'] ?? '', $roles, true)) {
        http_response_code(403);
        echo '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Akses Ditolak</title>
        <style>body{font-family:sans-serif;display:flex;justify-content:center;align-items:center;min-height:100vh;margin:0;background:#f5f6f8;}
        .box{text-align:center;padding:40px;background:#fff;border-radius:12px;box-shadow:0 2px 12px rgba(0,0,0,.08);}
        a{color:#6c3fb5;text-decoration:none;font-weight:600;}</style></head>
        <body><div class="box"><h1>🚫 Akses Ditolak</h1>
        <p>Anda tidak memiliki izin untuk mengakses halaman ini.</p>
        <a href="dashboard.php">← Kembali ke Dashboard</a></div></body></html>';
        exit;
    }
}

/**
 * Ambil data user dari session.
 * @return array Data user (id, username, nama_lengkap, role)
 */
function user(): array
{
    return [
        'id' => $_SESSION['user_id'] ?? 0,
        'username' => $_SESSION['username'] ?? '',
        'nama_lengkap' => $_SESSION['nama_lengkap'] ?? '',
        'role' => $_SESSION['role'] ?? '',
    ];
}

/**
 * Cek apakah user punya role tertentu.
 */
function is_role(string $role): bool
{
    return ($_SESSION['role'] ?? '') === $role;
}

/**
 * Cek apakah user sudah login (boolean, tanpa redirect).
 */
function is_logged_in(): bool
{
    return isset($_SESSION['user_id']);
}
