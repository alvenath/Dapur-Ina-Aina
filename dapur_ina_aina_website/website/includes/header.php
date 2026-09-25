<?php
/**
 * Header: Dynamic navigation sidebar based on user role
 * Hanya untuk Admin & Kasir (pelanggan tidak pakai layout sidebar)
 */
$halaman = $halaman ?? '';
$currentUser = user();
$role = $currentUser['role'];

// Navigation items per role (hanya admin & kasir)
$navItems = [];

switch ($role) {
    case 'admin':
        $navItems = [
            ['label' => 'MENU UTAMA', 'type' => 'label'],
            ['href' => 'dashboard.php', 'icon' => '📊', 'text' => 'Dashboard', 'key' => 'dashboard'],
            ['href' => 'kelola_menu.php', 'icon' => '🍽️', 'text' => 'Kelola Menu', 'key' => 'kelola_menu'],
            ['href' => 'kelola_pegawai.php', 'icon' => '👥', 'text' => 'Kelola Pegawai', 'key' => 'kelola_pegawai'],
            ['href' => 'kelola_meja.php', 'icon' => '🪑', 'text' => 'Kelola Meja', 'key' => 'kelola_meja'],
            ['href' => 'laporan.php', 'icon' => '📈', 'text' => 'Laporan Penjualan', 'key' => 'laporan'],
            ['label' => 'OPERASIONAL', 'type' => 'label'],
            ['href' => 'pesanan.php', 'icon' => '📋', 'text' => 'Data Pesanan', 'key' => 'pesanan'],
            ['href' => 'billing.php', 'icon' => '💳', 'text' => 'Billing & Kasir', 'key' => 'billing'],
            ['href' => 'menu_view.php', 'icon' => '📖', 'text' => 'Lihat Menu', 'key' => 'menu_view'],
            ['href' => 'catat_pesanan.php', 'icon' => '📝', 'text' => 'Catat Pesanan', 'key' => 'catat_pesanan'],
            ['href' => 'cek_stok.php', 'icon' => '📦', 'text' => 'Cek Stok', 'key' => 'cek_stok'],
        ];
        break;

    case 'kasir':
        $navItems = [
            ['label' => 'MENU KASIR', 'type' => 'label'],
            ['href' => 'dashboard.php', 'icon' => '📊', 'text' => 'Dashboard', 'key' => 'dashboard'],
            ['href' => 'pesanan.php', 'icon' => '📋', 'text' => 'Pemesanan', 'key' => 'pesanan'],
            ['href' => 'billing.php', 'icon' => '💳', 'text' => 'Billing & Pembayaran', 'key' => 'billing'],
            ['href' => 'struk.php', 'icon' => '🧾', 'text' => 'Cetak Struk', 'key' => 'struk'],
            ['href' => 'menu_view.php', 'icon' => '📖', 'text' => 'Lihat Menu', 'key' => 'menu_view'],
            ['href' => 'catat_pesanan.php', 'icon' => '📝', 'text' => 'Catat Pesanan', 'key' => 'catat_pesanan'],
            ['href' => 'cek_stok.php', 'icon' => '📦', 'text' => 'Cek Stok', 'key' => 'cek_stok'],
        ];
        break;
}

// Format tanggal Indonesia
$hariIndo = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];
$bulanIndo = ['', 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
$hari = $hariIndo[date('w')];
$tgl = date('j');
$bln = $bulanIndo[(int)date('n')];
$thn = date('Y');
$tanggalIndo = "$hari, $tgl $bln $thn";
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Dapur Ina Aina | <?= h($judul ?? 'Sistem Manajemen Restoran') ?></title>
  <meta name="description" content="Sistem Manajemen Restoran Dapur Ina Aina - Kelola pesanan, menu, dan pembayaran">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
<div class="app-layout">

  <!-- Sidebar Overlay (mobile) -->
  <div class="sidebar-overlay" id="sidebarOverlay" onclick="closeSidebar()"></div>

  <!-- Sidebar Navigation -->
  <aside class="sidebar" id="sidebar">
    <div class="sidebar-brand">
      <div class="brand-badge"><span>✨</span> Masakan Nusantara</div>
      <h1>🍽️ Dapur Ina Aina</h1>
      <div class="subtitle">Sistem Restoran Modern</div>
    </div>

    <nav class="sidebar-nav">
      <?php foreach ($navItems as $item): ?>
        <?php if (isset($item['type']) && $item['type'] === 'label'): ?>
          <div class="nav-label"><?= h($item['label']) ?></div>
        <?php else: ?>
          <a href="<?= h($item['href']) ?>" class="<?= $halaman === $item['key'] ? 'active' : '' ?>">
            <span class="nav-icon"><?= $item['icon'] ?></span>
            <span><?= h($item['text']) ?></span>
          </a>
        <?php endif; ?>
      <?php endforeach; ?>
    </nav>

    <div class="sidebar-user">
      <div class="avatar"><?= strtoupper(substr($currentUser['nama_lengkap'], 0, 1)) ?></div>
      <div class="user-info">
        <div class="name"><?= h($currentUser['nama_lengkap']) ?></div>
        <span class="role-badge role-<?= h($role) ?>"><?= h(ucfirst($currentUser['role'])) ?></span>
      </div>
      <a href="logout.php" class="btn-logout" title="Keluar dari akun">Keluar</a>
    </div>
  </aside>

  <!-- Main Content -->
  <div class="main-content">
    <header class="topbar">
      <div class="topbar-left">
        <button class="mobile-toggle" onclick="toggleSidebar()" aria-label="Toggle Menu">☰</button>
        <h2 class="page-title"><?= h($judul ?? 'Dashboard') ?></h2>
      </div>
      <div class="topbar-right">
        <div class="status-indicator">
          <span class="dot"></span>
          <span>Restoran Buka • 10:00 - 22:00</span>
        </div>
        <div class="topbar-date"><?= $tanggalIndo ?></div>
      </div>
    </header>
    <div class="page-content">

<script>
function toggleSidebar() {
  document.getElementById('sidebar').classList.toggle('open');
  document.getElementById('sidebarOverlay').classList.toggle('show');
}
function closeSidebar() {
  document.getElementById('sidebar').classList.remove('open');
  document.getElementById('sidebarOverlay').classList.remove('show');
}
</script>
