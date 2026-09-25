<?php
/**
 * Halaman: Status Pesanan Saya (Publik Tanpa Login)
 * Memungkinkan pelanggan melacak status makanan & minuman secara real-time via nomor pesanan
 */
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';

$currentUser = user();
$pdo = get_koneksi();

$sukses_pesan = isset($_GET['sukses']);

// Tentukan ID pesanan yang dicari
$id_pesanan_cari = 0;
if (isset($_GET['id_pesanan'])) {
    $id_pesanan_cari = (int)$_GET['id_pesanan'];
} elseif (isset($_POST['cari_pesanan'])) {
    $id_pesanan_cari = (int)($_POST['no_pesanan'] ?? 0);
} elseif (isset($_SESSION['last_order_id'])) {
    $id_pesanan_cari = (int)$_SESSION['last_order_id'];
} elseif (isset($_COOKIE['last_order_id'])) {
    $id_pesanan_cari = (int)$_COOKIE['last_order_id'];
}

$pesananDetail = null;
$detailItems = [];
$totalTagihan = 0;

if ($id_pesanan_cari > 0) {
    $stmt = $pdo->prepare("
        SELECT p.id, p.tanggal, p.status, m.nomor_meja, pel.nama AS nama_pelanggan,
               pb.id AS id_bayar, pb.tanggal_bayar, pb.jenis AS metode_bayar
        FROM pesanan p
        JOIN pelanggan pel ON p.id_pelanggan = pel.id
        LEFT JOIN meja m ON pel.id_meja = m.id
        LEFT JOIN pembayaran pb ON pb.id_pesanan = p.id
        WHERE p.id = :id
    ");
    $stmt->execute(['id' => $id_pesanan_cari]);
    $pesananDetail = $stmt->fetch();

    if ($pesananDetail) {
        $stmtItems = $pdo->prepare("
            SELECT dp.*, pr.nama AS nama_produk, pr.harga
            FROM detail_pesanan dp
            JOIN produk pr ON dp.id_produk = pr.id
            WHERE dp.id_pesanan = :id
            ORDER BY dp.id ASC
        ");
        $stmtItems->execute(['id' => $id_pesanan_cari]);
        $detailItems = $stmtItems->fetchAll();

        foreach ($detailItems as $di) {
            $totalTagihan += $di['subtotal'];
        }
    }
}

$activeTab = 'status';
$judul = 'Status Pesanan Pelanggan';
require_once __DIR__ . '/includes/header_public.php';
?>

<!-- Header Section -->
<div class="page-header" style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:16px; margin-bottom: 24px;">
    <div>
        <h1 class="page-title" style="font-size: 26px; font-weight: 800; color: #0f172a; margin: 0 0 6px;">
            📋 Status & Lacak Pesanan
        </h1>
        <p class="text-muted" style="margin: 0; font-size: 14px;">
            Pantau tahapan persiapan hidangan Anda mulai dari dapur hingga penyajian ke meja
        </p>
    </div>
    <div>
        <a href="menu_pelanggan.php" class="btn btn-primary" style="padding: 10px 22px; font-weight: 700; border-radius: 9999px;">
            + Pesan Menu Lainnya
        </a>
    </div>
</div>

<?php if ($sukses_pesan): ?>
    <div class="alert alert-success" style="background: linear-gradient(135deg, #ecfdf5, #d1fae5); border: 1px solid #6ee7b7; color: #065f46; border-radius: 16px; padding: 18px 24px; font-size: 15px; margin-bottom: 28px; box-shadow: var(--shadow-sm);">
        <div style="display: flex; align-items: center; gap: 14px;">
            <span style="font-size: 28px;">🎉</span>
            <div>
                <strong>Pesanan Anda Berhasil Terkirim ke Dapur!</strong>
                <div style="font-size: 13.5px; margin-top: 2px; color: #047857;">
                    Nomor Pesanan Anda adalah <strong>#<?= (int)$id_pesanan_cari ?></strong>. Mohon tunggu selagi koki kami meracik masakan Anda.
                </div>
            </div>
        </div>
    </div>
<?php endif; ?>

<!-- Box Pencarian Pesanan -->
<div class="card" style="margin-bottom: 30px; padding: 22px 28px; background: #fff; border-radius: 18px; border: 1px solid #e2e8f0; box-shadow: var(--shadow-sm);">
    <form method="POST" action="pesanan_saya.php" style="display:flex; align-items:center; gap: 14px; flex-wrap: wrap;">
        <div style="font-size: 26px;">🔍</div>
        <div style="flex: 1; min-width: 220px;">
            <label style="font-size: 13px; font-weight: 700; color: #475569; display: block; margin-bottom: 4px;">
                Cek Pesanan Berdasarkan Nomor / ID Pesanan:
            </label>
            <input type="number" 
                   name="no_pesanan" 
                   value="<?= $id_pesanan_cari > 0 ? (int)$id_pesanan_cari : '' ?>" 
                   placeholder="Masukkan nomor pesanan (Contoh: 1, 2, 5...)" 
                   class="form-control" 
                   style="font-weight: 700; font-size: 15px;" 
                   required>
        </div>
        <div style="margin-top: auto;">
            <button type="submit" name="cari_pesanan" class="btn btn-primary" style="padding: 11px 24px; font-weight: 700; border-radius: 10px;">
                Lacak Status
            </button>
        </div>
    </form>
</div>

<?php if ($pesananDetail): ?>
    <?php
    $status = $pesananDetail['status'];
    $isPaid = !empty($pesananDetail['id_bayar']);
    
    // Status badges & text sinkron dengan Kasir (Daftar Pesanan)
    if ($isPaid) {
        $step = 4;
        $statusText = '4. LUNAS & SELESAI';
        $statusTitle = 'Pesanan Selesai & Lunas';
        $statusDesc = 'Pembayaran telah sukses diterima di kasir (' . ucfirst($pesananDetail['metode_bayar'] ?? 'Kasir') . '). Selamat menikmati sajian lezat Dapur Ina Aina!';
        $badgeBg = '#ecfdf5';
        $badgeColor = '#047857';
    } elseif ($status === 'selesai') {
        $step = 3;
        $statusText = '3. SUDAH SIAP / SIAP SANTAP';
        $statusTitle = 'Hidangan Sudah Siap & Disajikan';
        $statusDesc = 'Koki telah selesai memasak hidangan Anda dan pesanan sudah siap santap di meja! Silakan menuju kasir untuk menyelesaikan pembayaran.';
        $badgeBg = '#ecfdf5';
        $badgeColor = '#059669';
    } elseif ($status === 'disiapkan') {
        $step = 2;
        $statusText = '2. SEDANG DIMASAK';
        $statusTitle = 'Koki Sedang Memasak di Dapur';
        $statusDesc = 'Koki kami sedang mengolah dan memasak bahan-bahan segar pilihan untuk pesanan Anda di dapur.';
        $badgeBg = '#eff6ff';
        $badgeColor = '#1d4ed8';
    } elseif ($status === 'diproses') {
        $step = 1;
        $statusText = '1. DITERIMA (ANTREAN DAPUR)';
        $statusTitle = 'Pesanan Diterima di Dapur';
        $statusDesc = 'Pesanan Anda sudah masuk ke sistem antrean dapur dan siap untuk dimasak koki.';
        $badgeBg = '#fffbeb';
        $badgeColor = '#b45309';
    } else {
        $step = 0;
        $statusText = 'Pesanan Dibatalkan';
        $statusTitle = 'Pesanan Dibatalkan';
        $statusDesc = 'Pesanan ini telah dibatalkan.';
        $badgeBg = '#fef2f2';
        $badgeColor = '#991b1b';
    }
    ?>

    <!-- Kartu Status Utama -->
    <div class="card" style="padding: 30px; border-radius: 20px; border: 1px solid #a7f3d0; background: #fff; box-shadow: var(--shadow-md); margin-bottom: 28px;">
        <div style="display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 16px; padding-bottom: 22px; border-bottom: 1px solid #f1f5f9;">
            <div>
                <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 6px; flex-wrap: wrap;">
                    <span style="font-size: 22px; font-weight: 800; color: #0f172a;">
                        Pesanan #<?= (int)$pesananDetail['id'] ?>
                    </span>
                    <span class="badge" style="background: <?= $badgeBg ?>; color: <?= $badgeColor ?>; font-size: 13px; padding: 6px 14px; font-weight: 800; border-radius: 9999px; border: 1px solid currentColor;">
                        <?= htmlspecialchars($statusText) ?>
                    </span>
                    <?php if ($isPaid): ?>
                        <span class="badge badge-success" style="font-size: 12px; padding: 5px 12px; border-radius: 9999px; font-weight:700;">
                            💳 LUNAS (<?= htmlspecialchars(ucfirst($pesananDetail['metode_bayar'] ?? 'Kasir')) ?>)
                        </span>
                    <?php else: ?>
                        <span class="badge badge-warning" style="font-size: 12px; padding: 5px 12px; border-radius: 9999px; font-weight:700;">
                            ⏳ Belum Dibayar
                        </span>
                    <?php endif; ?>
                </div>
                <div style="color: #64748b; font-size: 13.5px;">
                    Waktu Pesan: <strong><?= date('d M Y, H:i', strtotime($pesananDetail['tanggal'])) ?> WIB</strong> • Pemesan: <strong><?= htmlspecialchars($pesananDetail['nama_pelanggan']) ?></strong>
                </div>
            </div>

            <div style="text-align: right; background: #ecfdf5; padding: 12px 20px; border-radius: 14px; border: 1px solid #a7f3d0;">
                <div style="font-size: 12px; color: #065f46; font-weight: 700; text-transform: uppercase;">Posisi Meja</div>
                <div style="font-size: 20px; font-weight: 800; color: #047857;">
                    Meja <?= $pesananDetail['nomor_meja'] ? htmlspecialchars($pesananDetail['nomor_meja']) : '-' ?>
                </div>
            </div>
        </div>

        <!-- Progress Timeline Pelanggan (4 Tahap Sinkron dengan Kasir) -->
        <div style="margin: 28px 0;">
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(140px, 1fr)); gap: 12px; text-align: center;">
                
                <!-- Step 1: Diterima -->
                <div style="padding: 16px 10px; border-radius: 14px; background: <?= $step >= 1 ? '#ecfdf5' : '#f8fafc' ?>; border: 2px solid <?= $step === 1 ? '#10b981' : ($step > 1 ? '#a7f3d0' : '#e2e8f0') ?>; transition: all 0.3s ease; <?= $step === 1 ? 'box-shadow: 0 4px 14px rgba(16,185,129,0.22);' : '' ?>">
                    <div style="font-size: 26px; margin-bottom: 4px;"><?= $step >= 1 ? '✅' : '⚪' ?></div>
                    <div style="font-weight: 800; font-size: 14px; color: <?= $step >= 1 ? '#065f46' : '#64748b' ?>;">1. Diterima</div>
                    <div style="font-size: 11.5px; color: <?= $step === 1 ? '#047857' : '#94a3b8' ?>; font-weight: <?= $step === 1 ? '700' : 'normal' ?>;">
                        <?= $step === 1 ? '⏳ Antrean Dapur' : ($step > 1 ? 'Sudah Diterima' : 'Menunggu') ?>
                    </div>
                </div>

                <!-- Step 2: Dimasak -->
                <div style="padding: 16px 10px; border-radius: 14px; background: <?= $step >= 2 ? ($step === 2 ? '#eff6ff' : '#ecfdf5') : '#f8fafc' ?>; border: 2px solid <?= $step === 2 ? '#3b82f6' : ($step > 2 ? '#a7f3d0' : '#e2e8f0') ?>; transition: all 0.3s ease; <?= $step === 2 ? 'box-shadow: 0 4px 14px rgba(59,130,246,0.22);' : '' ?>">
                    <div style="font-size: 26px; margin-bottom: 4px;"><?= $step === 2 ? '🍳' : ($step > 2 ? '✅' : '⚪') ?></div>
                    <div style="font-weight: 800; font-size: 14px; color: <?= $step === 2 ? '#1d4ed8' : ($step > 2 ? '#065f46' : '#64748b') ?>;">2. Dimasak</div>
                    <div style="font-size: 11.5px; color: <?= $step === 2 ? '#1d4ed8' : '#94a3b8' ?>; font-weight: <?= $step === 2 ? '700' : 'normal' ?>;">
                        <?= $step === 2 ? '🔥 Koki Memasak' : ($step > 2 ? 'Selesai Dimasak' : 'Menunggu Giliran') ?>
                    </div>
                </div>

                <!-- Step 3: Siap Santap / Sudah Siap -->
                <div style="padding: 16px 10px; border-radius: 14px; background: <?= $step >= 3 ? '#ecfdf5' : '#f8fafc' ?>; border: 2px solid <?= $step === 3 ? '#10b981' : ($step > 3 ? '#a7f3d0' : '#e2e8f0') ?>; transition: all 0.3s ease; <?= $step === 3 ? 'box-shadow: 0 4px 14px rgba(16,185,129,0.25);' : '' ?>">
                    <div style="font-size: 26px; margin-bottom: 4px;"><?= $step === 3 ? '🍲' : ($step > 3 ? '✅' : '⚪') ?></div>
                    <div style="font-weight: 800; font-size: 14px; color: <?= $step >= 3 ? '#065f46' : '#64748b' ?>;">3. Siap Santap</div>
                    <div style="font-size: 11.5px; color: <?= $step === 3 ? '#047857' : '#94a3b8' ?>; font-weight: <?= $step === 3 ? '700' : 'normal' ?>;">
                        <?= $step === 3 ? '✨ Sudah Siap di Meja' : ($step > 3 ? 'Sudah Disajikan' : 'Menunggu Matang') ?>
                    </div>
                </div>

                <!-- Step 4: Kasir / Pembayaran -->
                <div style="padding: 16px 10px; border-radius: 14px; background: <?= $isPaid ? '#ecfdf5' : '#f8fafc' ?>; border: 2px solid <?= $isPaid ? '#10b981' : '#e2e8f0' ?>; transition: all 0.3s ease; <?= $isPaid ? 'box-shadow: 0 4px 14px rgba(16,185,129,0.2);' : '' ?>">
                    <div style="font-size: 26px; margin-bottom: 4px;"><?= $isPaid ? '💳' : '⏳' ?></div>
                    <div style="font-weight: 800; font-size: 14px; color: <?= $isPaid ? '#065f46' : '#64748b' ?>;">4. Pembayaran</div>
                    <div style="font-size: 11.5px; color: <?= $isPaid ? '#047857' : '#94a3b8' ?>; font-weight: <?= $isPaid ? '700' : 'normal' ?>;">
                        <?= $isPaid ? '✅ Sudah Lunas' : 'Bayar di Kasir' ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Banner Penjelasan Status Aktif -->
        <div style="background: <?= $badgeBg ?>; border-radius: 14px; padding: 18px 22px; border: 1px solid <?= $badgeColor ?>35; margin-bottom: 22px; display: flex; align-items: center; gap: 16px;">
            <span style="font-size: 34px;"><?= $step === 1 ? '⏳' : ($step === 2 ? '🍳' : ($step === 3 ? '🍲' : ($isPaid ? '🎉' : 'ℹ️'))) ?></span>
            <div>
                <h4 style="margin: 0 0 4px 0; color: <?= $badgeColor ?>; font-size: 16.5px; font-weight: 800;"><?= htmlspecialchars($statusTitle) ?></h4>
                <p style="margin: 0; color: <?= $badgeColor ?>; font-size: 13.5px; line-height: 1.5; opacity: 0.95;"><?= htmlspecialchars($statusDesc) ?></p>
            </div>
        </div>

        <?php if (!$isPaid && $status !== 'dibatalkan'): ?>
            <!-- Live Auto Refresh Indicator -->
            <div style="display: flex; align-items: center; justify-content: space-between; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 12px; padding: 10px 16px; font-size: 13px; color: #475569; margin-bottom: 24px; flex-wrap: wrap; gap: 8px;">
                <span style="display: flex; align-items: center; gap: 10px;">
                    <span style="display: inline-block; width: 10px; height: 10px; border-radius: 50%; background: #10b981; box-shadow: 0 0 0 3px rgba(16,185,129,0.25);"></span>
                    <strong style="color: #0f172a;">Live Sinkron Dapur & Kasir:</strong> Halaman akan otomatis memperbarui status saat pesanan Anda dimasak, sudah siap, atau dibayar.
                </span>
                <span style="color: #64748b; font-size: 12px;" id="refresh-timer">Memeriksa pembaruan dalam 8 detik...</span>
            </div>
        <?php endif; ?>

        <!-- Rincian Item Pesanan -->
        <h3 style="font-size: 16px; font-weight: 800; color: #1e293b; margin-bottom: 14px;">
            🍽️ Rincian Menu yang Dipesan
        </h3>
        <div class="table-wrapper" style="margin-bottom: 20px;">
            <table>
                <thead>
                    <tr>
                        <th>Menu Masakan</th>
                        <th style="text-align:center;">Jumlah (Porsi)</th>
                        <th style="text-align:right;">Harga Satuan</th>
                        <th style="text-align:right;">Subtotal</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($detailItems as $item): ?>
                    <tr>
                        <td style="font-weight: 600; color: #0f172a;">
                            <?= htmlspecialchars($item['nama_produk']) ?>
                        </td>
                        <td style="text-align: center; font-weight: 700;">
                            <?= (int)$item['jumlah'] ?>x
                        </td>
                        <td style="text-align: right; color: #64748b;">
                            Rp <?= number_format($item['harga'], 0, ',', '.') ?>
                        </td>
                        <td style="text-align: right; font-weight: 700; color: #047857;">
                            Rp <?= number_format($item['subtotal'], 0, ',', '.') ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr style="background: #f8fafc; font-size: 16px;">
                        <th colspan="3" style="text-align: right; font-weight: 800; color: #0f172a; padding: 16px 20px;">
                            Total Tagihan:
                        </th>
                        <th style="text-align: right; font-weight: 800; color: #047857; padding: 16px 20px;">
                            Rp <?= number_format($totalTagihan, 0, ',', '.') ?>
                        </th>
                    </tr>
                </tfoot>
            </table>
        </div>

        <!-- Instruksi & Barcode QRIS untuk Pelanggan -->
        <div style="background: #f8fafc; border-radius: 14px; padding: 18px 20px; border: 1px solid #e2e8f0; display: flex; flex-wrap: wrap; justify-content: space-between; align-items: center; gap: 14px;">
            <div style="flex: 1; min-width: 250px; font-size: 13.5px; color: #475569;">
                💡 <strong>Instruksi Pembayaran:</strong> Silakan menuju ke meja kasir dan sebutkan <strong>Nomor Pesanan #<?= (int)$pesananDetail['id'] ?></strong> atau scan kode QRIS untuk pembayaran digital instan.
            </div>
            <?php if (!$isPaid && $status !== 'dibatalkan'): ?>
            <div>
                <button type="button" onclick="document.getElementById('modalQrisPelanggan').style.display='flex'" class="btn" style="background: #0f172a; color: #ffffff; font-weight: 700; padding: 9px 18px; border-radius: 9999px; display: inline-flex; align-items: center; gap: 8px; box-shadow: 0 4px 12px rgba(15, 23, 42, 0.15);">
                    📱 <span>Bayar via QRIS</span>
                </button>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Modal QRIS Pelanggan -->
    <div id="modalQrisPelanggan" style="display: none; position: fixed; inset: 0; background: rgba(15,23,42,0.65); backdrop-filter: blur(4px); z-index: 9999; justify-content: center; align-items: center; padding: 16px;">
        <div style="background: #ffffff; border-radius: 20px; max-width: 380px; width: 100%; padding: 24px; text-align: center; box-shadow: 0 20px 25px -5px rgba(0,0,0,0.1), 0 8px 10px -6px rgba(0,0,0,0.1); position: relative; animation: slideUp 0.3s ease;">
            <button type="button" onclick="document.getElementById('modalQrisPelanggan').style.display='none'" style="position: absolute; top: 14px; right: 14px; background: #f1f5f9; border: none; border-radius: 50%; width: 32px; height: 32px; font-size: 16px; cursor: pointer; color: #64748b;">✕</button>
            
            <div style="font-size: 14px; font-weight: 800; color: #dc2626; letter-spacing: 0.5px; margin-bottom: 2px;">QRIS NASIONAL</div>
            <h3 style="margin: 0 0 12px 0; font-size: 17px; font-weight: 800; color: #1e293b;">Dapur Ina Aina</h3>

            <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 14px; padding: 12px; display: inline-block; margin-bottom: 14px;">
                <img src="assets/img/qris.png" alt="QRIS Dapur Ina Aina" style="width: 220px; height: auto; border-radius: 8px; display: block;">
            </div>

            <div style="background: #ecfdf5; border-radius: 10px; padding: 10px; margin-bottom: 16px; border: 1px solid #a7f3d0;">
                <div style="font-size: 12px; color: #047857; font-weight: 600;">Nominal Yang Harus Dibayar:</div>
                <div style="font-size: 22px; font-weight: 900; color: #065f46;">Rp <?= number_format($totalTagihan, 0, ',', '.') ?></div>
            </div>

            <p style="font-size: 12px; color: #64748b; line-height: 1.5; margin-bottom: 16px;">
                Scan menggunakan <strong>BCA, BRI, Mandiri, GoPay, OVO, Dana, ShopeePay</strong> atau aplikasi perbankan lainnya. Tunjukkan bukti transfer ke kasir.
            </p>

            <button type="button" onclick="document.getElementById('modalQrisPelanggan').style.display='none'" class="btn btn-secondary" style="width: 100%; border-radius: 10px; padding: 10px;">
                Tutup
            </button>
        </div>
    </div>

<?php elseif ($id_pesanan_cari > 0): ?>
    <div class="card" style="text-align:center; padding: 60px 20px; border-radius: 18px;">
        <div style="font-size: 56px; margin-bottom: 14px;">🔎</div>
        <h2 style="font-size: 20px; font-weight: 800; color: #1e293b; margin-bottom: 8px;">Pesanan Tidak Ditemukan</h2>
        <p class="text-muted" style="max-width: 480px; margin: 0 auto 20px;">
            Nomor pesanan <strong>#<?= (int)$id_pesanan_cari ?></strong> tidak ditemukan dalam database. Silakan periksa kembali nomor pesanan Anda atau lakukan pemesanan baru.
        </p>
        <a href="menu_pelanggan.php" class="btn btn-primary" style="padding: 10px 24px; border-radius: 9999px;">
            Lihat Menu & Mulai Pesan
        </a>
    </div>

<?php else: ?>
    <div class="card" style="text-align:center; padding: 60px 20px; border-radius: 18px;">
        <div style="font-size: 56px; margin-bottom: 14px;">🍲</div>
        <h2 style="font-size: 20px; font-weight: 800; color: #1e293b; margin-bottom: 8px;">Belum Memasukkan Nomor Pesanan</h2>
        <p class="text-muted" style="max-width: 480px; margin: 0 auto 20px;">
            Silakan masukkan nomor pesanan Anda pada kolom pencarian di atas untuk memeriksa status masakan dan rincian tagihan Anda.
        </p>
        <a href="menu_pelanggan.php" class="btn btn-primary" style="padding: 10px 24px; border-radius: 9999px;">
            Lihat Menu & Pesan Sekarang
        </a>
    </div>
<?php endif; ?>

<?php if ($pesananDetail && !$isPaid && $status !== 'dibatalkan'): ?>
<script>
// Auto-refresh sinkronisasi pesanan pelanggan dengan kasir & dapur
let detikTersisa = 8;
const timerEl = document.getElementById('refresh-timer');

const countdownInterval = setInterval(function() {
    detikTersisa--;
    if (timerEl) {
        timerEl.textContent = 'Memeriksa pembaruan dalam ' + detikTersisa + ' detik...';
    }
    if (detikTersisa <= 0) {
        clearInterval(countdownInterval);
        if (timerEl) {
            timerEl.textContent = 'Memperbarui status sekarang...';
        }
        window.location.reload();
    }
}, 1000);
</script>
<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer_public.php'; ?>
