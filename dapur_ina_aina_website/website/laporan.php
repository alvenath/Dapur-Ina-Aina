<?php
/**
 * Halaman: Laporan Penjualan Restoran (Admin & Owner)
 * Sesuai Use Case: Admin & Owner - Melihat laporan penjualan
 * Sesuai Struktur Navigasi: Menu Admin → Laporan
 */
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';

cek_role(['admin']);
$currentUser = user();
$pdo = get_koneksi();

$tgl_mulai = $_GET['tgl_mulai'] ?? date('Y-m-01');
$tgl_selesai = $_GET['tgl_selesai'] ?? date('Y-m-d');

// 1. KPI Summary
$total_pendapatan = 0;
$total_transaksi = 0;
$total_porsi = 0;

try {
    // Total Pendapatan & Transaksi
    $stmtKpi = $pdo->prepare("
        SELECT COUNT(id) AS jml_tx, COALESCE(SUM(jumlah_bayar - kembalian), 0) AS omzet
        FROM pembayaran
        WHERE DATE(tanggal_bayar) BETWEEN :mulai AND :selesai
    ");
    $stmtKpi->execute(['mulai' => $tgl_mulai, 'selesai' => $tgl_selesai]);
    $kpi = $stmtKpi->fetch();
    $total_transaksi = (int)$kpi['jml_tx'];
    $total_pendapatan = (float)$kpi['omzet'];

    // Total porsi terjual
    $stmtPorsi = $pdo->prepare("
        SELECT COALESCE(SUM(dp.jumlah), 0)
        FROM detail_pesanan dp
        JOIN pesanan p ON dp.id_pesanan = p.id
        WHERE p.status = 'selesai' AND DATE(p.tanggal) BETWEEN :mulai AND :selesai
    ");
    $stmtPorsi->execute(['mulai' => $tgl_mulai, 'selesai' => $tgl_selesai]);
    $total_porsi = (int)$stmtPorsi->fetchColumn();

    // 2. Daftar Transaksi Penjualan Lengkap
    $stmtTx = $pdo->prepare("
        SELECT pb.id AS id_bayar, pb.tanggal_bayar, pb.jenis, pb.jumlah_bayar, pb.kembalian,
               (pb.jumlah_bayar - pb.kembalian) AS total_bersih,
               p.id AS id_pesanan, pel.nama AS nama_pelanggan, m.nomor_meja,
               u.nama_lengkap AS kasir
        FROM pembayaran pb
        JOIN pesanan p ON pb.id_pesanan = p.id
        JOIN pelanggan pel ON p.id_pelanggan = pel.id
        LEFT JOIN meja m ON pel.id_meja = m.id
        LEFT JOIN pengguna u ON p.id_pengguna = u.id
        WHERE DATE(pb.tanggal_bayar) BETWEEN :mulai AND :selesai
        ORDER BY pb.tanggal_bayar DESC
    ");
    $stmtTx->execute(['mulai' => $tgl_mulai, 'selesai' => $tgl_selesai]);
    $transaksi_list = $stmtTx->fetchAll();

    // 3. Menu Terlaris (Top Selling)
    $stmtTop = $pdo->prepare("
        SELECT pr.nama, k.nama_kategori, SUM(dp.jumlah) AS terjual, SUM(dp.subtotal) AS total_omzet
        FROM detail_pesanan dp
        JOIN produk pr ON dp.id_produk = pr.id
        JOIN kategori k ON pr.id_kategori = k.id
        JOIN pesanan p ON dp.id_pesanan = p.id
        WHERE p.status = 'selesai' AND DATE(p.tanggal) BETWEEN :mulai AND :selesai
        GROUP BY pr.id
        ORDER BY terjual DESC
        LIMIT 5
    ");
    $stmtTop->execute(['mulai' => $tgl_mulai, 'selesai' => $tgl_selesai]);
    $top_menu = $stmtTop->fetchAll();

    // 4. Breakdown Metode Pembayaran
    $stmtMetode = $pdo->prepare("
        SELECT jenis, COUNT(*) AS count_tx, SUM(jumlah_bayar - kembalian) AS total_uang
        FROM pembayaran
        WHERE DATE(tanggal_bayar) BETWEEN :mulai AND :selesai
        GROUP BY jenis
    ");
    $stmtMetode->execute(['mulai' => $tgl_mulai, 'selesai' => $tgl_selesai]);
    $breakdown_metode = $stmtMetode->fetchAll();

} catch (PDOException $e) {
    $error = "Gagal memuat laporan: " . $e->getMessage();
}

$halaman = 'laporan';
$judul = 'Laporan Penjualan Restoran';
require_once __DIR__ . '/includes/header.php';
?>

<div class="page-header" style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:12px;">
    <div>
        <h1 class="page-title">📈 Laporan Penjualan</h1>
        <p class="text-muted">Analisis performa omzet, transaksi, dan menu terlaris Dapur Ina Aina</p>
    </div>
    <div>
        <button onclick="window.print()" class="btn btn-secondary">
            🖨️ Cetak / Ekspor Laporan
        </button>
    </div>
</div>

<!-- Form Filter Rentang Tanggal -->
<div class="card" style="margin-bottom: 24px; padding: 16px;">
    <form method="GET" action="laporan.php" style="display:flex; gap:16px; flex-wrap:wrap; align-items:flex-end;">
        <div class="form-group" style="margin-bottom:0;">
            <label style="font-size:12px; font-weight:600; color:#475569;">Dari Tanggal</label>
            <input type="date" name="tgl_mulai" value="<?= htmlspecialchars($tgl_mulai) ?>" class="form-control" style="width:160px;">
        </div>
        <div class="form-group" style="margin-bottom:0;">
            <label style="font-size:12px; font-weight:600; color:#475569;">Sampai Tanggal</label>
            <input type="date" name="tgl_selesai" value="<?= htmlspecialchars($tgl_selesai) ?>" class="form-control" style="width:160px;">
        </div>
        <button type="submit" class="btn btn-primary">Terapkan Periode</button>
        <a href="laporan.php?tgl_mulai=<?= date('Y-m-d') ?>&tgl_selesai=<?= date('Y-m-d') ?>" class="btn btn-secondary">Hari Ini</a>
        <a href="laporan.php?tgl_mulai=<?= date('Y-m-01') ?>&tgl_selesai=<?= date('Y-m-d') ?>" class="btn btn-secondary">Bulan Ini</a>
    </form>
</div>

<!-- KPI Cards -->
<div class="stats-grid">
    <div class="stat-card green">
        <div class="stat-icon">💰</div>
        <div>
            <div class="stat-value">Rp <?= number_format($total_pendapatan, 0, ',', '.') ?></div>
            <div class="stat-label">Total Pendapatan (Omzet)</div>
        </div>
    </div>

    <div class="stat-card blue">
        <div class="stat-icon">🧾</div>
        <div>
            <div class="stat-value"><?= $total_transaksi ?> Struk</div>
            <div class="stat-label">Transaksi Selesai</div>
        </div>
    </div>

    <div class="stat-card purple">
        <div class="stat-icon">🍲</div>
        <div>
            <div class="stat-value"><?= $total_porsi ?> Porsi</div>
            <div class="stat-label">Menu Terjual</div>
        </div>
    </div>
</div>

<div class="grid-2" style="grid-template-columns: 2fr 1fr; margin-bottom: 24px;">
    <!-- Top 5 Menu Terlaris -->
    <div class="card">
        <h3 class="card-title">🏆 Top 5 Menu Paling Laris</h3>
        <div class="card-subtitle">Menu dengan kuantitas pemesanan terbanyak</div>
        <?php if (empty($top_menu)): ?>
            <div class="empty-state">
                <p>Belum ada data penjualan pada periode tanggal ini.</p>
            </div>
        <?php else: ?>
            <div class="table-wrapper">
                <table>
                    <thead>
                        <tr>
                            <th>Menu</th>
                            <th>Kategori</th>
                            <th style="text-align:center;">Porsi Terjual</th>
                            <th style="text-align:right;">Subtotal Omzet</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($top_menu as $idx => $tm): ?>
                        <tr>
                            <td>
                                <strong><?= ($idx + 1) . '. ' . htmlspecialchars($tm['nama']) ?></strong>
                            </td>
                            <td><span class="badge badge-info"><?= htmlspecialchars($tm['nama_kategori']) ?></span></td>
                            <td style="text-align:center;"><strong><?= $tm['terjual'] ?></strong></td>
                            <td style="text-align:right; font-weight:700; color:#047857;">
                                Rp <?= number_format($tm['total_omzet'], 0, ',', '.') ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <!-- Metode Pembayaran -->
    <div class="card">
        <h3 class="card-title">💳 Metode Pembayaran</h3>
        <div class="card-subtitle">Distribusi transaksi kasir</div>
        <?php if (empty($breakdown_metode)): ?>
            <div class="empty-state">
                <p>Belum ada transaksi.</p>
            </div>
        <?php else: ?>
            <div style="display:flex; flex-direction:column; gap:12px; margin-top:12px;">
                <?php foreach ($breakdown_metode as $bm): ?>
                <div style="padding: 14px 16px; background: var(--slate-100); border-radius: var(--radius-sm); border: 1px solid var(--border);">
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:6px;">
                        <span class="badge badge-info"><?= strtoupper($bm['jenis']) ?></span>
                        <span style="font-size:12px; color:var(--text-muted); font-weight:600;"><?= $bm['count_tx'] ?> transaksi</span>
                    </div>
                    <div style="font-size: 17px; font-weight:800; color:var(--text);">
                        Rp <?= number_format($bm['total_uang'], 0, ',', '.') ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Tabel Rincian Semua Transaksi -->
<div class="card">
    <h3 class="card-title">📋 Rincian Transaksi Penjualan</h3>
    <div class="card-subtitle">Semua pembayaran yang telah diproses di kasir</div>
    <div class="table-wrapper">
        <table>
            <thead>
                <tr>
                    <th>No. Transaksi</th>
                    <th>Waktu Pembayaran</th>
                    <th>Pelanggan</th>
                    <th>Meja</th>
                    <th>Kasir</th>
                    <th>Metode</th>
                    <th style="text-align:right;">Nominal Transaksi</th>
                    <th style="text-align:center;">Struk</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($transaksi_list)): ?>
                    <tr>
                        <td colspan="8" class="text-center text-muted" style="padding: 32px;">
                            Tidak ada transaksi selesai pada periode tanggal ini.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($transaksi_list as $tx): ?>
                    <tr>
                        <td><strong>#<?= str_pad($tx['id_bayar'], 5, '0', STR_PAD_LEFT) ?></strong></td>
                        <td><span class="text-muted"><?= date('d/m/Y H:i', strtotime($tx['tanggal_bayar'])) ?></span></td>
                        <td><strong><?= htmlspecialchars($tx['nama_pelanggan']) ?></strong></td>
                        <td><span class="badge badge-info">Meja <?= htmlspecialchars($tx['nomor_meja'] ?? '-') ?></span></td>
                        <td><?= htmlspecialchars($tx['kasir'] ?? 'Staff') ?></td>
                        <td><span class="badge badge-warning"><?= strtoupper($tx['jenis']) ?></span></td>
                        <td style="text-align:right; font-weight:800; color:var(--text);">
                            Rp <?= number_format($tx['total_bersih'], 0, ',', '.') ?>
                        </td>
                        <td style="text-align:center;">
                            <a href="struk.php?id=<?= $tx['id_pesanan'] ?>" class="btn btn-sm btn-secondary" target="_blank">
                                🧾 Cetak
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<style>
@media print {
    .sidebar, .topbar, form, .btn, .page-header div:last-child {
        display: none !important;
    }
    .main-content {
        margin: 0 !important;
        padding: 0 !important;
    }
    .card {
        box-shadow: none !important;
        border: 1px solid #ccc !important;
    }
}
</style>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
