<?php
/**
 * Header Khusus Halaman Publik (Pelanggan)
 * Tanpa sidebar admin/kasir, responsif penuh, modern green theme
 */
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/functions.php';

$currentUser = user();
$isStaffLoggedIn = !empty($currentUser['role']);
$activeTab = $activeTab ?? 'menu';
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Dapur Ina Aina | <?= htmlspecialchars($judul ?? 'Menu & Pemesanan') ?></title>
  <meta name="description" content="Pesan aneka masakan nusantara lezat di Restoran Dapur Ina Aina langsung dari meja Anda.">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="assets/css/style.css">
  <style>
    /* Public Customer Layout Styles */
    .public-page-wrapper {
      min-height: 100vh;
      display: flex;
      flex-direction: column;
      background: #f8fafc;
    }
    .public-navbar {
      background: #0f172a;
      border-bottom: 1px solid rgba(255, 255, 255, 0.08);
      position: sticky;
      top: 0;
      z-index: 1000;
      box-shadow: 0 4px 20px rgba(0, 0, 0, 0.15);
    }
    .public-navbar-container {
      max-width: 1200px;
      margin: 0 auto;
      padding: 14px 24px;
      display: flex;
      justify-content: space-between;
      align-items: center;
      gap: 16px;
      flex-wrap: wrap;
    }
    .public-brand {
      display: flex;
      align-items: center;
      gap: 12px;
      text-decoration: none;
    }
    .public-brand-logo {
      width: 44px;
      height: 44px;
      background: linear-gradient(135deg, #10b981 0%, #059669 100%);
      border-radius: 12px;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 22px;
      box-shadow: 0 4px 12px rgba(16, 185, 129, 0.35);
      transition: transform 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);
    }
    .public-brand:hover .public-brand-logo {
      transform: scale(1.12) rotate(-5deg);
    }
    .public-brand-text h1 {
      font-size: 19px;
      font-weight: 800;
      color: #fff;
      margin: 0;
      line-height: 1.2;
      letter-spacing: -0.02em;
    }
    .public-brand-text span {
      font-size: 11px;
      color: #34d399;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: 0.06em;
    }
    .public-nav-links {
      display: flex;
      align-items: center;
      gap: 10px;
      flex-wrap: wrap;
    }
    .public-nav-btn {
      display: inline-flex;
      align-items: center;
      gap: 8px;
      padding: 9px 18px;
      border-radius: 9999px;
      font-size: 13.5px;
      font-weight: 700;
      text-decoration: none;
      transition: all 0.2s ease;
    }
    .public-nav-btn.primary {
      background: #10b981;
      color: #fff;
      box-shadow: 0 2px 8px rgba(16, 185, 129, 0.4);
    }
    .public-nav-btn.primary:hover {
      background: #059669;
      transform: translateY(-1px);
    }
    .public-nav-btn.secondary {
      background: rgba(255, 255, 255, 0.08);
      color: #e2e8f0;
      border: 1px solid rgba(255, 255, 255, 0.12);
    }
    .public-nav-btn.secondary:hover {
      background: rgba(255, 255, 255, 0.16);
      color: #fff;
    }
    .public-nav-btn.active {
      background: rgba(16, 185, 129, 0.18);
      color: #34d399;
      border: 1px solid rgba(16, 185, 129, 0.35);
    }
    .public-container {
      max-width: 1200px;
      width: 100%;
      margin: 0 auto;
      padding: 32px 24px 60px;
      flex: 1;
    }
    @media (max-width: 640px) {
      .public-navbar-container {
        padding: 12px 16px;
      }
      .public-container {
        padding: 20px 16px 40px;
      }
      .public-nav-btn {
        padding: 7px 12px;
        font-size: 12px;
      }
    }
  </style>
</head>
<body>
<div class="public-page-wrapper">

  <!-- Top Public Navbar -->
  <header class="public-navbar">
    <div class="public-navbar-container">
      <a href="menu_pelanggan.php" class="public-brand">
        <div class="public-brand-logo">🍽️</div>
        <div class="public-brand-text">
          <h1>Dapur Ina Aina</h1>
          <span>Masakan Nusantara</span>
        </div>
      </a>

      <nav class="public-nav-links">
        <a href="menu_pelanggan.php" class="public-nav-btn <?= ($activeTab === 'menu') ? 'active' : 'secondary' ?>">
          🍽️ Lihat Menu & Pesan
        </a>
        <a href="pesanan_saya.php" class="public-nav-btn <?= ($activeTab === 'status') ? 'active' : 'secondary' ?>">
          📋 Cek Status Pesanan
        </a>
        <?php if ($isStaffLoggedIn): ?>
          <a href="dashboard.php" class="public-nav-btn primary">
            ⚙️ Panel Staff (<?= htmlspecialchars(ucfirst($currentUser['role'])) ?>) ➔
          </a>
        <?php else: ?>
          <a href="login.php" class="public-nav-btn secondary" style="opacity: 0.85;">
            🔑 Login Staff
          </a>
        <?php endif; ?>
      </nav>
    </div>
  </header>

  <!-- Content Container -->
  <main class="public-container">
