<?php
/**
 * Konfigurasi & koneksi database MySQL/MariaDB
 * Sistem Manajemen Restoran "Dapur Ina Aina"
 *
 * Menggunakan PDO agar query terparameterisasi (aman dari SQL Injection)
 * dan exception dapat ditangani secara eksplisit sesuai kebutuhan
 * penanganan error pada Tugas 3.
 */

// --- Konfigurasi Otomatis (Lokal XAMPP / Hosting InfinityFree) ---
if (isset($_SERVER['SERVER_NAME']) && (strpos($_SERVER['SERVER_NAME'], 'infinityfree') !== false || strpos($_SERVER['SERVER_NAME'], 'rf.gd') !== false || strpos($_SERVER['SERVER_NAME'], 'epizy') !== false)) {
    // Hosting InfinityFree
    define('DB_HOST', 'sql303.infinityfree.com');
    define('DB_NAME', 'if0_42989947_dapur_ina_aina');
    define('DB_USER', 'if0_42989947');
    define('DB_PASS', 'Maxiemill1');
} else {
    // Lokal (XAMPP)
    define('DB_HOST', 'localhost');
    define('DB_NAME', 'dapur_ina_aina');
    define('DB_USER', 'root');
    define('DB_PASS', '');
}
define('DB_CHARSET', 'utf8mb4');
// --------------------------------------------------------------------

/**
 * Membuka koneksi PDO ke database.
 * Melempar PDOException jika koneksi gagal (ditangani oleh pemanggil).
 */
function get_koneksi(): PDO
{
    static $pdo = null;

    if ($pdo === null) {
        $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET;
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];
        $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
    }

    return $pdo;
}
