<?php
/**
 * Halaman: Cetak Struk Pembayaran
 * Sesuai Use Case: Kasir - Melakukan pembayaran & cetak struk
 * Sesuai Struktur Navigasi: Menu Kasir → Cetak Struk
 */
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';

cek_role(['admin', 'kasir']);
$currentUser = user();

$pdo = get_koneksi();
$error = '';
$pesanan_detail = null;
$items = [];
$pembayaran = null;

$id_pesanan = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($id_pesanan > 0) {
    try {
        // Ambil data pesanan
        $stmt = $pdo->prepare("
            SELECT p.*, pel.nama AS nama_pelanggan, m.nomor_meja, u.nama_lengkap AS nama_kasir
            FROM pesanan p
            JOIN pelanggan pel ON p.id_pelanggan = pel.id
            LEFT JOIN meja m ON pel.id_meja = m.id
            LEFT JOIN pengguna u ON p.id_pengguna = u.id
            WHERE p.id = :id
        ");
        $stmt->execute(['id' => $id_pesanan]);
        $pesanan_detail = $stmt->fetch();

        if ($pesanan_detail) {
            // Ambil item
            $stmtItems = $pdo->prepare("
                SELECT dp.*, pr.nama AS nama_produk
                FROM detail_pesanan dp
                JOIN produk pr ON dp.id_produk = pr.id
                WHERE dp.id_pesanan = :id
            ");
            $stmtItems->execute(['id' => $id_pesanan]);
            $items = $stmtItems->fetchAll();

            // Ambil data pembayaran
            $stmtBayar = $pdo->prepare("
                SELECT * FROM pembayaran
                WHERE id_pesanan = :id
                ORDER BY id DESC LIMIT 1
            ");
            $stmtBayar->execute(['id' => $id_pesanan]);
            $pembayaran = $stmtBayar->fetch();
        } else {
            $error = "Pesanan #$id_pesanan tidak ditemukan.";
        }
    } catch (PDOException $e) {
        $error = "Gagal mengambil data: " . $e->getMessage();
    }
}

// Jika tidak ada ID pesanan yang dipilih, tampilkan daftar transaksi yang sudah selesai/dibayar
$daftar_transaksi = [];
if ($id_pesanan === 0) {
    try {
        $stmt = $pdo->query("
            SELECT p.id, p.tanggal, p.status, pel.nama AS nama_pelanggan, m.nomor_meja,
                   pb.jenis, pb.jumlah_bayar, pb.kembalian, pb.tanggal_bayar,
                   (SELECT SUM(dp.subtotal) FROM detail_pesanan dp WHERE dp.id_pesanan = p.id) AS total_belanja
            FROM pesanan p
            JOIN pelanggan pel ON p.id_pelanggan = pel.id
            LEFT JOIN meja m ON pel.id_meja = m.id
            JOIN pembayaran pb ON pb.id_pesanan = p.id
            WHERE p.status = 'selesai'
            ORDER BY pb.tanggal_bayar DESC
            LIMIT 50
        ");
        $daftar_transaksi = $stmt->fetchAll();
    } catch (PDOException $e) {
        $error = "Gagal mengambil daftar struk: " . $e->getMessage();
    }
}

$halaman = 'struk';
$judul = 'Cetak Struk Pembayaran';
require_once __DIR__ . '/includes/header.php';
?>

<div class="page-header" style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:10px;">
    <div>
        <h1 class="page-title">🧾 Cetak Struk Pembayaran</h1>
        <p class="text-muted">Bukti transaksi sah restoran Dapur Ina Aina</p>
    </div>
    <?php if ($pesanan_detail): ?>
    <div>
        <button onclick="window.print()" class="btn btn-primary" style="padding: 10px 20px; font-weight: 600;">
            🖨️ Cetak Struk Ini
        </button>
        <a href="struk.php" class="btn btn-secondary" style="padding: 10px 15px;">
            ⬅️ Kembali ke Daftar
        </a>
    </div>
    <?php endif; ?>
</div>

<?php if ($error): ?>
    <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<?php if ($pesanan_detail): ?>
    <!-- TAMPILAN STRUK PRINTABLE -->
    <div style="display:flex; justify-content:center; margin-top:20px;">
        <div class="card" id="area-struk" style="width: 100%; max-width: 420px; background:#fff; border: 1px dashed #cbd5e1; box-shadow: 0 4px 15px rgba(0,0,0,0.08); padding: 24px; font-family: 'Courier New', Courier, monospace;">
            <div style="text-align: center; border-bottom: 2px dashed #0f172a; padding-bottom: 12px; margin-bottom: 16px;">
                <h2 style="margin: 0; font-size: 20px; font-weight: 800; color: #0f172a; letter-spacing: 1px;">DAPUR INA AINA</h2>
                <p style="margin: 4px 0; font-size: 12px; color: #64748b;">Rasa Otentik, Kualitas Terbaik</p>
                <p style="margin: 2px 0; font-size: 11px; color: #94a3b8;">Jl. Kuliner No. 12, Nusantara</p>
                <p style="margin: 2px 0; font-size: 11px; color: #94a3b8;">Telp: (021) 555-INA-AINA</p>
            </div>

            <div style="font-size: 12px; line-height: 1.6; border-bottom: 1px dashed #cbd5e1; padding-bottom: 10px; margin-bottom: 12px;">
                <div style="display:flex; justify-content:space-between;">
                    <span>No. Pesanan:</span>
                    <strong>#<?= str_pad($pesanan_detail['id'], 5, '0', STR_PAD_LEFT) ?></strong>
                </div>
                <div style="display:flex; justify-content:space-between;">
                    <span>Tanggal:</span>
                    <span><?= date('d/m/Y H:i', strtotime($pembayaran['tanggal_bayar'] ?? $pesanan_detail['tanggal'])) ?></span>
                </div>
                <div style="display:flex; justify-content:space-between;">
                    <span>Kasir / Petugas:</span>
                    <span><?= htmlspecialchars($pesanan_detail['nama_kasir'] ?? 'Staff') ?></span>
                </div>
                <div style="display:flex; justify-content:space-between;">
                    <span>Pelanggan:</span>
                    <span><?= htmlspecialchars($pesanan_detail['nama_pelanggan']) ?></span>
                </div>
                <div style="display:flex; justify-content:space-between;">
                    <span>Meja:</span>
                    <strong>Meja <?= htmlspecialchars($pesanan_detail['nomor_meja'] ?? '-') ?></strong>
                </div>
            </div>

            <!-- Item List -->
            <table style="width: 100%; font-size: 12px; border-collapse: collapse; margin-bottom: 12px;">
                <thead>
                    <tr style="border-bottom: 1px dashed #0f172a; text-align: left;">
                        <th style="padding: 4px 0;">Item</th>
                        <th style="padding: 4px 0; text-align: center;">Qty</th>
                        <th style="padding: 4px 0; text-align: right;">Harga</th>
                        <th style="padding: 4px 0; text-align: right;">Total</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $grandTotal = 0;
                    foreach ($items as $item): 
                        $grandTotal += $item['subtotal'];
                    ?>
                    <tr>
                        <td style="padding: 6px 0; vertical-align: top;"><?= htmlspecialchars($item['nama_produk']) ?></td>
                        <td style="padding: 6px 0; text-align: center; vertical-align: top;"><?= $item['jumlah'] ?></td>
                        <td style="padding: 6px 0; text-align: right; vertical-align: top;"><?= number_format($item['subtotal'] / $item['jumlah'], 0, ',', '.') ?></td>
                        <td style="padding: 6px 0; text-align: right; vertical-align: top;"><?= number_format($item['subtotal'], 0, ',', '.') ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <div style="border-top: 1px dashed #0f172a; padding-top: 8px; font-size: 13px; line-height: 1.8;">
                <div style="display:flex; justify-content:space-between; font-weight: bold; font-size: 14px;">
                    <span>TOTAL:</span>
                    <span>Rp <?= number_format($grandTotal, 0, ',', '.') ?></span>
                </div>
                <?php if ($pembayaran): ?>
                <div style="display:flex; justify-content:space-between; font-size: 12px;">
                    <span>Metode Bayar:</span>
                    <span><?= strtoupper($pembayaran['jenis']) ?></span>
                </div>
                <?php if ($pembayaran['no_referensi']): ?>
                <div style="display:flex; justify-content:space-between; font-size: 12px;">
                    <span>No. Referensi:</span>
                    <span><?= htmlspecialchars($pembayaran['no_referensi']) ?></span>
                </div>
                <?php endif; ?>
                <div style="display:flex; justify-content:space-between; font-size: 12px;">
                    <span>Bayar:</span>
                    <span>Rp <?= number_format($pembayaran['jumlah_bayar'], 0, ',', '.') ?></span>
                </div>
                <div style="display:flex; justify-content:space-between; font-size: 12px;">
                    <span>Kembali:</span>
                    <span>Rp <?= number_format($pembayaran['kembalian'], 0, ',', '.') ?></span>
                </div>
                <?php endif; ?>
            </div>

            <div style="text-align: center; border-top: 2px dashed #0f172a; margin-top: 16px; padding-top: 12px; font-size: 11px; color: #475569;">
                <p style="margin: 0; font-weight: bold;">TERIMA KASIH ATAS KUNJUNGAN ANDA!</p>
                <p style="margin: 4px 0 0 0;">Makanan yang sudah dibeli tidak dapat ditukar kembali.</p>
                <p style="margin: 4px 0 0 0;">Kritik & Saran: info@dapurinaaina.com</p>
            </div>
        </div>
    </div>

    <style>
    @media print {
        body * {
            visibility: hidden;
        }
        #area-struk, #area-struk * {
            visibility: visible;
        }
        #area-struk {
            position: absolute;
            left: 0;
            top: 0;
            width: 80mm;
            border: none !important;
            box-shadow: none !important;
            padding: 0 !important;
            margin: 0 !important;
        }
    }
    </style>

<?php else: ?>
    <!-- DAFTAR TRANSAKSI YANG SUDAH DIBAYAR -->
    <div class="card">
        <h3 class="card-title">Pilih Transaksi Selesai untuk Cetak Struk</h3>
        <?php if (empty($daftar_transaksi)): ?>
            <div class="empty-state">
                <p>Belum ada transaksi pembayaran yang selesai tercatat.</p>
                <a href="billing.php" class="btn btn-primary" style="margin-top:10px;">Ke Halaman Billing</a>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>No. Pesanan</th>
                            <th>Waktu Bayar</th>
                            <th>Pelanggan</th>
                            <th>Meja</th>
                            <th>Total Tagihan</th>
                            <th>Metode</th>
                            <th>Jumlah Bayar</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($daftar_transaksi as $tx): ?>
                        <tr>
                            <td><strong>#<?= str_pad($tx['id'], 5, '0', STR_PAD_LEFT) ?></strong></td>
                            <td><?= date('d/m/Y H:i', strtotime($tx['tanggal_bayar'])) ?></td>
                            <td><?= htmlspecialchars($tx['nama_pelanggan']) ?></td>
                            <td>Meja <?= htmlspecialchars($tx['nomor_meja'] ?? '-') ?></td>
                            <td class="text-right font-weight-bold">Rp <?= number_format($tx['total_belanja'], 0, ',', '.') ?></td>
                            <td><span class="badge badge-info"><?= strtoupper($tx['jenis']) ?></span></td>
                            <td class="text-right">Rp <?= number_format($tx['jumlah_bayar'], 0, ',', '.') ?></td>
                            <td>
                                <a href="struk.php?id=<?= $tx['id'] ?>" class="btn btn-sm btn-primary">
                                    🧾 Cetak Struk
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
