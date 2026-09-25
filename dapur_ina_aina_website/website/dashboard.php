<?php
/**
 * Dashboard: Halaman utama setelah login
 * Menampilkan ringkasan statistik sesuai role user
 */
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';

cek_login();

$halaman = 'dashboard';
$judul = 'Dashboard Restoran';
$currentUser = user();

try {
    $pdo = get_koneksi();

    // Stats untuk semua role
    $totalPesananHariIni = $pdo->query(
        "SELECT COUNT(*) FROM pesanan WHERE DATE(tanggal) = CURDATE()"
    )->fetchColumn();

    $pesananDiproses = $pdo->query(
        "SELECT COUNT(*) FROM pesanan WHERE status IN ('diproses','disiapkan')"
    )->fetchColumn();

    // Admin/Owner stats
    if ($currentUser['role'] === 'admin') {
        $totalPendapatanHariIni = $pdo->query(
            "SELECT COALESCE(SUM(dp.subtotal), 0)
             FROM pesanan p JOIN detail_pesanan dp ON dp.id_pesanan = p.id
             WHERE p.status = 'selesai' AND DATE(p.tanggal) = CURDATE()"
        )->fetchColumn();

        $totalProduk = $pdo->query("SELECT COUNT(*) FROM produk WHERE aktif = 1")->fetchColumn();
        $totalPegawai = $pdo->query("SELECT COUNT(*) FROM pengguna WHERE aktif = 1")->fetchColumn();
        $totalMeja = $pdo->query("SELECT COUNT(*) FROM meja")->fetchColumn();
        $mejaKosong = $pdo->query("SELECT COUNT(*) FROM meja WHERE status = 'kosong'")->fetchColumn();
        $stokMenipis = $pdo->query("SELECT COUNT(*) FROM produk WHERE stok <= 10 AND aktif = 1")->fetchColumn();

        // Pesanan terbaru
        $pesananTerbaru = $pdo->query(
            "SELECT ps.id, pl.nama AS nama_pelanggan, ps.tanggal, ps.status,
                    COALESCE(SUM(dp.subtotal), 0) AS total
             FROM pesanan ps
             JOIN pelanggan pl ON pl.id = ps.id_pelanggan
             LEFT JOIN detail_pesanan dp ON dp.id_pesanan = ps.id
             GROUP BY ps.id, pl.nama, ps.tanggal, ps.status
             ORDER BY ps.id DESC LIMIT 5"
        )->fetchAll();
    }

    // Kasir stats
    if ($currentUser['role'] === 'kasir') {
        $pesananBelumBayar = $pdo->query(
            "SELECT COUNT(*) FROM pesanan p
             WHERE p.status = 'selesai'
             AND NOT EXISTS (SELECT 1 FROM pembayaran pb WHERE pb.id_pesanan = p.id)"
        )->fetchColumn();

        $totalBayarHariIni = $pdo->query(
            "SELECT COALESCE(SUM(jumlah_bayar), 0) FROM pembayaran WHERE DATE(tanggal_bayar) = CURDATE()"
        )->fetchColumn();
    }




} catch (PDOException $e) {
    $error = 'Gagal mengambil data: ' . $e->getMessage();
}

require __DIR__ . '/includes/header.php';
?>

<?php if (isset($error)): ?>
  <div class="alert alert-error">⚠️ <?= h($error) ?></div>
<?php endif; ?>

<!-- Hero Showcase Banner (Matches Login Page Atmosphere) -->
<div class="card" style="background: linear-gradient(135deg, rgba(15, 23, 42, 0.94) 0%, rgba(30, 41, 59, 0.88) 100%), url('assets/img/hero_restaurant.jpg') center/cover no-repeat; color: #fff; padding: 32px 36px; margin-bottom: 28px; border: 1px solid rgba(255, 255, 255, 0.1); border-radius: var(--radius-lg); position: relative; overflow: hidden; box-shadow: var(--shadow-md);">
  <div style="position: absolute; right: -60px; top: -60px; width: 220px; height: 220px; background: radial-gradient(circle, rgba(16, 185, 129, 0.25) 0%, transparent 70%); border-radius: 50%; pointer-events: none;"></div>
  
  <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 24px; position: relative; z-index: 2;">
    <div style="max-width: 620px;">
      <div style="display: inline-flex; align-items: center; gap: 8px; background: rgba(16, 185, 129, 0.2); border: 1px solid rgba(16, 185, 129, 0.45); padding: 5px 14px; border-radius: var(--radius-full); font-size: 12px; font-weight: 700; color: #a7f3d0; margin-bottom: 12px; backdrop-filter: blur(8px);">
        <span>👑</span> Dapur Ina Aina • Cita Rasa Nusantara
      </div>
      <h1 style="font-size: 28px; font-weight: 800; margin: 0 0 8px 0; color: #ffffff; letter-spacing: -0.02em;">
        Selamat Datang, <?= h($currentUser['nama_lengkap']) ?>!
      </h1>
      <p style="margin: 0; color: #cbd5e1; font-size: 14px; line-height: 1.6;">
        Anda saat ini login sebagai <strong style="color: #6ee7b7; text-transform: uppercase; letter-spacing: 0.05em;"><?= h($currentUser['role']) ?></strong>. Pantau antrean dapur, catat pesanan pelanggan, dan kelola operasional dengan cepat dan tepat.
      </p>
    </div>

    <!-- Food showcase previews -->
    <div style="display: flex; flex-direction: column; align-items: flex-end; gap: 8px;">
      <span style="font-size: 12px; color: #cbd5e1; font-weight: 600;">Menu Andalan Kami</span>
      <div style="display: flex; gap: 10px;">
        <div style="text-align: center;">
          <img src="assets/img/rendang_sapi.jpg" alt="Rendang Sapi" style="width: 58px; height: 58px; border-radius: 12px; object-fit: cover; border: 2px solid rgba(16,185,129,0.6); box-shadow: 0 4px 10px rgba(0,0,0,0.3); transition: transform 0.2s;" onmouseover="this.style.transform='scale(1.1)'" onmouseout="this.style.transform='scale(1)'" title="Rendang Sapi">
        </div>
        <div style="text-align: center;">
          <img src="assets/img/ayam_bakar.jpg" alt="Ayam Bakar" style="width: 58px; height: 58px; border-radius: 12px; object-fit: cover; border: 2px solid rgba(16,185,129,0.6); box-shadow: 0 4px 10px rgba(0,0,0,0.3); transition: transform 0.2s;" onmouseover="this.style.transform='scale(1.1)'" onmouseout="this.style.transform='scale(1)'" title="Ayam Bakar Madu">
        </div>
        <div style="text-align: center;">
          <img src="assets/img/nasi_goreng.jpg" alt="Nasi Goreng" style="width: 58px; height: 58px; border-radius: 12px; object-fit: cover; border: 2px solid rgba(16,185,129,0.6); box-shadow: 0 4px 10px rgba(0,0,0,0.3); transition: transform 0.2s;" onmouseover="this.style.transform='scale(1.1)'" onmouseout="this.style.transform='scale(1)'" title="Nasi Goreng Spesial">
        </div>
        <div style="text-align: center;">
          <img src="assets/img/es_jeruk.jpg" alt="Es Jeruk" style="width: 58px; height: 58px; border-radius: 12px; object-fit: cover; border: 2px solid rgba(16,185,129,0.6); box-shadow: 0 4px 10px rgba(0,0,0,0.3); transition: transform 0.2s;" onmouseover="this.style.transform='scale(1.1)'" onmouseout="this.style.transform='scale(1)'" title="Es Jeruk Peras">
        </div>
      </div>
    </div>
  </div>
</div>

<?php if ($currentUser['role'] === 'admin'): ?>
<!-- ===== DASHBOARD ADMIN / OWNER ===== -->
<div class="stats-grid">
  <div class="stat-card green">
    <div class="stat-icon">💰</div>
    <div>
      <div class="stat-value"><?= rupiah($totalPendapatanHariIni) ?></div>
      <div class="stat-label">Pendapatan Hari Ini</div>
    </div>
  </div>
  <div class="stat-card purple">
    <div class="stat-icon">📋</div>
    <div>
      <div class="stat-value"><?= (int) $totalPesananHariIni ?></div>
      <div class="stat-label">Pesanan Hari Ini</div>
    </div>
  </div>
  <div class="stat-card blue">
    <div class="stat-icon">⏳</div>
    <div>
      <div class="stat-value"><?= (int) $pesananDiproses ?></div>
      <div class="stat-label">Pesanan Aktif Sedang Diproses</div>
    </div>
  </div>
  <div class="stat-card yellow">
    <div class="stat-icon">⚠️</div>
    <div>
      <div class="stat-value"><?= (int) $stokMenipis ?></div>
      <div class="stat-label">Produk Stok Menipis</div>
    </div>
  </div>
</div>

<div class="grid-2">
  <div class="card">
    <div class="flex-between mb-2">
      <div>
        <h2 class="card-title">Pesanan Terbaru</h2>
        <div class="card-subtitle" style="margin-bottom:0;">Daftar transaksi masuk hari ini</div>
      </div>
      <a href="pesanan.php" class="btn btn-sm btn-secondary">Semua Pesanan →</a>
    </div>
    <div class="table-wrapper">
      <table>
        <thead>
          <tr><th>No</th><th>Pelanggan</th><th>Waktu</th><th>Total Tagihan</th><th>Status</th></tr>
        </thead>
        <tbody>
          <?php if (empty($pesananTerbaru)): ?>
            <tr><td colspan="5" class="text-muted text-center" style="padding: 24px;">Belum ada pesanan terbaru.</td></tr>
          <?php else: ?>
            <?php foreach ($pesananTerbaru as $p): ?>
              <tr>
                <td><strong>#<?= (int)$p['id'] ?></strong></td>
                <td><span style="font-weight:600; color: #1e293b;"><?= h($p['nama_pelanggan']) ?></span></td>
                <td><span class="text-muted"><?= h(date('d/m H:i', strtotime($p['tanggal']))) ?></span></td>
                <td style="font-weight:700; color:#047857;"><?= rupiah($p['total']) ?></td>
                <td><span class="badge <?= badge_status($p['status']) ?>"><?= label_status($p['status']) ?></span></td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <div class="card">
    <h2 class="card-title">Ringkasan Operasional</h2>
    <div class="card-subtitle">Status kapasitas & kesiapan resto</div>
    <table style="margin-top: 8px;">
      <tr>
        <td style="font-weight:600;">🍽️ Total Menu Aktif</td>
        <td style="text-align:right; font-weight:800; color: var(--hijau-dark);"><?= (int)$totalProduk ?> Menu</td>
      </tr>
      <tr>
        <td style="font-weight:600;">👥 Total Pegawai Aktif</td>
        <td style="text-align:right; font-weight:800; color: #1e293b;"><?= (int)$totalPegawai ?> Orang</td>
      </tr>
      <tr>
        <td style="font-weight:600;">🪑 Total Meja Restoran</td>
        <td style="text-align:right; font-weight:800; color: #1e293b;"><?= (int)$totalMeja ?> Meja</td>
      </tr>
      <tr>
        <td style="font-weight:600;">✅ Meja Siap / Kosong</td>
        <td style="text-align:right; font-weight:800; color: #059669;"><?= (int)$mejaKosong ?> Meja Kosong</td>
      </tr>
    </table>
    <div style="margin-top: 20px; display: flex; gap: 10px;">
      <a href="kelola_menu.php" class="btn btn-sm btn-primary" style="flex:1;">+ Tambah Menu</a>
      <a href="laporan.php" class="btn btn-sm btn-secondary" style="flex:1;">Lihat Laporan</a>
    </div>
  </div>
</div>

<?php elseif ($currentUser['role'] === 'kasir'): ?>
<!-- ===== DASHBOARD KASIR ===== -->
<div class="stats-grid">
  <div class="stat-card green">
    <div class="stat-icon">💰</div>
    <div>
      <div class="stat-value"><?= rupiah($totalBayarHariIni) ?></div>
      <div class="stat-label">Penerimaan Kasir Hari Ini</div>
    </div>
  </div>
  <div class="stat-card purple">
    <div class="stat-icon">📋</div>
    <div>
      <div class="stat-value"><?= (int) $totalPesananHariIni ?></div>
      <div class="stat-label">Total Pesanan Masuk</div>
    </div>
  </div>
  <div class="stat-card blue">
    <div class="stat-icon">⏳</div>
    <div>
      <div class="stat-value"><?= (int) $pesananDiproses ?></div>
      <div class="stat-label">Pesanan Sedang Disiapkan</div>
    </div>
  </div>
</div>

<div class="grid-3">
  <a href="billing.php" class="action-card">
    <div class="action-icon">💳</div>
    <h2>Billing & Kasir</h2>
    <p>Hitung tagihan, proses tunai/non-tunai, dan hitung kembalian</p>
  </a>
  <a href="pesanan.php" class="action-card">
    <div class="action-icon">📋</div>
    <h2>Antrean Pesanan</h2>
    <p>Pantau pesanan aktif dan buat pesanan baru langsung</p>
  </a>
  <a href="struk.php" class="action-card">
    <div class="action-icon">🧾</div>
    <h2>Cetak Struk</h2>
    <p>Cetak bukti pembayaran kasir dan struk transaksi</p>
  </a>
</div>
<?php endif; ?>

<?php require __DIR__ . '/includes/footer.php'; ?>
