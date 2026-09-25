<?php
/**
 * Halaman: Billing & Pembayaran Kasir
 * Sesuai Use Case: Kasir - Membuat tagihan & Melakukan pembayaran
 * Sesuai Activity Diagram:
 * - Kasir membuat tagihan
 * - Pelanggan memilih metode: Tunai atau Non-Tunai
 * - Jika tunai: hitung kembalian
 * - Jika non-tunai: masukkan no referensi / approval
 * - Simpan status pesanan selesai & meja kembali kosong
 * - Cetak struk pembayaran
 */
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';

cek_role(['admin', 'kasir']);
$currentUser = user();
$pdo = get_koneksi();

$pesan_sukses = '';
$pesan_error = '';

function ambil_pesanan_lengkap(PDO $pdo, int $idPesanan): ?array
{
    $stmt = $pdo->prepare("
        SELECT ps.id, ps.id_pelanggan, pl.nama AS nama_pelanggan, pl.id_meja, m.nomor_meja, ps.tanggal, ps.status
        FROM pesanan ps
        JOIN pelanggan pl ON pl.id = ps.id_pelanggan
        LEFT JOIN meja m ON pl.id_meja = m.id
        WHERE ps.id = ?
    ");
    $stmt->execute([$idPesanan]);
    $pesanan = $stmt->fetch();
    if (!$pesanan) {
        return null;
    }

    $stmtItem = $pdo->prepare("
        SELECT pr.nama AS nama_produk, pr.harga, dp.jumlah, dp.subtotal
        FROM detail_pesanan dp
        JOIN produk pr ON pr.id = dp.id_produk
        WHERE dp.id_pesanan = ?
    ");
    $stmtItem->execute([$idPesanan]);
    $pesanan['items'] = $stmtItem->fetchAll();
    $pesanan['total'] = (float)array_sum(array_column($pesanan['items'], 'subtotal'));
    return $pesanan;
}

$idPesanan = isset($_GET['id_pesanan']) ? (int)$_GET['id_pesanan'] : 0;
if (!$idPesanan && isset($_GET['id'])) {
    $idPesanan = (int)$_GET['id'];
}

// Proses Pembayaran
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['aksi']) && $_POST['aksi'] === 'bayar') {
    $idPesanan = (int)($_POST['id_pesanan'] ?? 0);
    $jenisInput = $_POST['jenis'] ?? 'tunai';
    $jenis = ($jenisInput === 'qris') ? 'kartu_kredit' : $jenisInput;
    $jenisValid = ['tunai', 'debit', 'kartu_kredit'];

    if (!in_array($jenis, $jenisValid, true)) {
        $pesan_error = "Metode pembayaran tidak valid.";
    } else {
        $pesananBayar = ambil_pesanan_lengkap($pdo, $idPesanan);
        if (!$pesananBayar) {
            $pesan_error = "Pesanan #{$idPesanan} tidak ditemukan.";
        } else {
            // Cek apakah sudah pernah dibayar
            $stmtCek = $pdo->prepare("SELECT id FROM pembayaran WHERE id_pesanan = ?");
            $stmtCek->execute([$idPesanan]);
            if ($stmtCek->fetch()) {
                $pesan_error = "Pesanan #{$idPesanan} sudah berstatus lunas / sudah dibayar.";
            }
        }
        if (empty($pesan_error)) {
            $total = $pesananBayar['total'];
            $kembalian = 0;
            $noReferensi = null;
            $jumlahBayar = 0;

            if ($jenis === 'tunai') {
                $jumlahBayar = (float)($_POST['jumlah_bayar'] ?? 0);
                if ($jumlahBayar < $total) {
                    $pesan_error = "Uang pembayaran tunai kurang! Total tagihan Rp " . number_format($total, 0, ',', '.') . 
                                   ", namun uang yang diterima hanya Rp " . number_format($jumlahBayar, 0, ',', '.') . ".";
                } else {
                    $kembalian = $jumlahBayar - $total;
                }
            } elseif ($jenisInput === 'qris') {
                $noRefQris = trim($_POST['no_ref_qris'] ?? '');
                $noReferensi = !empty($noRefQris) ? $noRefQris : 'QRIS-' . date('YmdHis');
                $jumlahBayar = $total;
            } else {
                $noReferensi = trim($_POST['no_referensi'] ?? '');
                if (empty($noReferensi)) {
                    $pesan_error = "Nomor referensi / approval transaksi non-tunai wajib diisi.";
                }
                $jumlahBayar = $total;
            }

            // Jika valid, proses pembayaran ke DB
            if (empty($pesan_error)) {
                try {
                    $pdo->beginTransaction();

                    // 1. Simpan pembayaran
                    $stmtBayar = $pdo->prepare("
                        INSERT INTO pembayaran (id_pesanan, jenis, jumlah_bayar, kembalian, no_referensi, tanggal_bayar)
                        VALUES (?, ?, ?, ?, ?, NOW())
                    ");
                    $stmtBayar->execute([$idPesanan, $jenis, $jumlahBayar, $kembalian, $noReferensi]);

                    // 2. Update status pesanan jadi selesai
                    $pdo->prepare("UPDATE pesanan SET status = 'selesai' WHERE id = ?")->execute([$idPesanan]);

                    // 3. Kosongkan kembali meja jika ada
                    if (!empty($pesananBayar['id_meja'])) {
                        $pdo->prepare("UPDATE meja SET status = 'kosong' WHERE id = ?")->execute([$pesananBayar['id_meja']]);
                    }

                    $pdo->commit();
                    $pesan_sukses = "Pembayaran " . strtoupper($jenis) . " sebesar <strong>Rp " . number_format($total, 0, ',', '.') . "</strong> untuk Pesanan #{$idPesanan} berhasil!";
                } catch (Exception $e) {
                    if ($pdo->inTransaction()) {
                        $pdo->rollBack();
                    }
                    $pesan_error = "Gagal memproses pembayaran: " . $e->getMessage();
                }
            }
        }
    }
}

// Ambil data pesanan terpilih jika ada
$pesanan = null;
$pembayaran_tercatat = null;
if ($idPesanan > 0) {
    $pesanan = ambil_pesanan_lengkap($pdo, $idPesanan);
    if ($pesanan) {
        $stmtP = $pdo->prepare("SELECT * FROM pembayaran WHERE id_pesanan = ? ORDER BY id DESC LIMIT 1");
        $stmtP->execute([$idPesanan]);
        $pembayaran_tercatat = $stmtP->fetch();
    }
}

// Ambil daftar pesanan yang belum dibayar / aktif untuk kasir
$pesanan_menunggu = [];
try {
    $stmtWait = $pdo->query("
        SELECT ps.id, ps.tanggal, ps.status, pl.nama AS nama_pelanggan, m.nomor_meja,
               COALESCE((SELECT SUM(dp.subtotal) FROM detail_pesanan dp WHERE dp.id_pesanan = ps.id), 0) AS total_tagihan,
               (SELECT COUNT(*) FROM detail_pesanan dp WHERE dp.id_pesanan = ps.id) AS jumlah_menu
        FROM pesanan ps
        JOIN pelanggan pl ON ps.id_pelanggan = pl.id
        LEFT JOIN meja m ON pl.id_meja = m.id
        LEFT JOIN pembayaran pb ON pb.id_pesanan = ps.id
        WHERE pb.id IS NULL AND ps.status != 'dibatalkan'
        ORDER BY ps.id ASC
    ");
    $pesanan_menunggu = $stmtWait->fetchAll();
} catch (PDOException $e) {}

$halaman = 'billing';
$judul = 'Billing & Pembayaran Kasir';
require_once __DIR__ . '/includes/header.php';
?>

<div class="page-header" style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:12px;">
    <div>
        <h1 class="page-title">💳 Billing & Pembayaran Kasir</h1>
        <p class="text-muted">Proses tagihan meja makan, pembayaran tunai/non-tunai, dan cetak struk</p>
    </div>
    <div>
        <a href="pesanan.php" class="btn btn-secondary">
            📋 Antrean Pesanan Dapur
        </a>
        <a href="struk.php" class="btn btn-primary">
            🧾 Riwayat & Cetak Struk
        </a>
    </div>
</div>

<?php if ($pesan_sukses): ?>
    <div class="alert alert-success" style="font-size: 15px;">
        🎉 <?= $pesan_sukses ?>
        <div style="margin-top: 10px;">
            <a href="struk.php?id=<?= $idPesanan ?>" class="btn btn-sm btn-primary">🧾 Langsung Cetak Struk Kasir</a>
            <a href="billing.php" class="btn btn-sm btn-secondary">Proses Pesanan Lain</a>
        </div>
    </div>
<?php endif; ?>

<?php if ($pesan_error): ?>
    <div class="alert alert-danger" style="font-size: 14px;">
        ⚠️ <?= $pesan_error ?>
    </div>
<?php endif; ?>

<!-- Form Pencarian Pesanan Cepat -->
<div class="card" style="margin-bottom: 24px; padding: 16px 20px;">
    <form method="GET" action="billing.php" style="display:flex; gap:12px; align-items:center; flex-wrap:wrap;">
        <label style="font-weight: 600; font-size: 13px; color: #1e293b;">Cari Nomor Pesanan:</label>
        <input type="number" name="id_pesanan" value="<?= $idPesanan > 0 ? $idPesanan : '' ?>" placeholder="Contoh: 1, 2, 3..." class="form-control" style="width: 180px;" min="1" required>
        <button type="submit" class="btn btn-primary">Tampilkan Tagihan</button>
        <?php if ($idPesanan > 0): ?>
            <a href="billing.php" class="btn btn-secondary">Tutup / Lihat Antrean</a>
        <?php endif; ?>
    </form>
</div>

<?php if ($pesanan): ?>
    <!-- TAMPILAN RINCIAN TAGIHAN & FORM PEMBAYARAN -->
    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 24px; align-items: start; margin-bottom: 30px;">
        
        <!-- Kolom Kiri: Rincian Billing -->
        <div class="card">
            <div style="display:flex; justify-content:space-between; align-items:center; border-bottom: 1px solid var(--border); padding-bottom: 14px; margin-bottom: 16px;">
                <h3 class="card-title" style="margin:0;">Tagihan Pesanan #<?= str_pad($pesanan['id'], 5, '0', STR_PAD_LEFT) ?></h3>
                <?php
                $status = $pesanan['status'];
                $bgB = ($status === 'selesai') ? '#dcfce7' : '#fef3c7';
                $colorB = ($status === 'selesai') ? '#166534' : '#92400e';
                ?>
                <span class="badge" style="background: <?= $bgB ?>; color: <?= $colorB ?>; font-size: 12px; padding: 5px 12px;">
                    Status: <?= strtoupper($status) ?>
                </span>
            </div>

            <div style="font-size: 13px; line-height: 1.8; margin-bottom: 18px; background: var(--slate-100); padding: 14px 18px; border-radius: var(--radius-sm); border: 1px solid var(--border);">
                <div style="display:flex; justify-content:space-between;">
                    <span class="text-muted">Pelanggan:</span>
                    <strong style="color: var(--text);"><?= htmlspecialchars($pesanan['nama_pelanggan']) ?></strong>
                </div>
                <div style="display:flex; justify-content:space-between;">
                    <span class="text-muted">Nomor Meja:</span>
                    <strong style="color: var(--text);">Meja <?= htmlspecialchars($pesanan['nomor_meja'] ?? '-') ?></strong>
                </div>
                <div style="display:flex; justify-content:space-between;">
                    <span class="text-muted">Waktu Pesanan:</span>
                    <span><?= date('d/m/Y H:i', strtotime($pesanan['tanggal'])) ?></span>
                </div>
            </div>

            <!-- Tabel Item -->
            <div class="table-wrapper">
                <table>
                    <thead>
                        <tr>
                            <th>Menu</th>
                            <th style="text-align:center;">Qty</th>
                            <th style="text-align:right;">Harga</th>
                            <th style="text-align:right;">Subtotal</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($pesanan['items'] as $item): ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($item['nama_produk']) ?></strong></td>
                            <td style="text-align:center;"><?= $item['jumlah'] ?></td>
                            <td style="text-align:right;">Rp <?= number_format($item['harga'], 0, ',', '.') ?></td>
                            <td style="text-align:right; font-weight:700;">Rp <?= number_format($item['subtotal'], 0, ',', '.') ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr style="border-top: 2px solid var(--slate-700); font-size: 15px; background: #ecfdf5;">
                            <th colspan="3" style="text-align:right; font-weight:800; color:#065f46;">TOTAL TAGIHAN:</th>
                            <th style="text-align:right; color:#047857; font-weight:800; font-size:17px;">
                                Rp <?= number_format($pesanan['total'], 0, ',', '.') ?>
                            </th>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>

        <!-- Kolom Kanan: Form Proses Pembayaran -->
        <div class="card" style="border-top: 4px solid var(--hijau);">
            <h3 class="card-title">Proses Transaksi Kasir</h3>
            <div class="card-subtitle">Pilih metode pembayaran dan masukkan jumlah uang yang diterima</div>

            <?php if (!empty($pembayaran_tercatat)): ?>
                <div style="background: #f0fdf4; border: 1px solid #bbf7d0; border-radius: var(--radius-sm); padding: 22px; text-align:center;">
                    <div style="font-size: 40px; margin-bottom: 8px;">✅</div>
                    <h4 style="margin: 0 0 6px 0; color: #166534; font-size: 18px; font-weight:800;">Pesanan Ini Sudah Lunas</h4>
                    <?php if ($pembayaran_tercatat): ?>
                        <p style="margin: 4px 0; font-size: 14px; color: #15803d;">
                            Metode: <strong><?= strtoupper($pembayaran_tercatat['jenis']) ?></strong> • 
                            Waktu: <?= date('d/m/Y H:i', strtotime($pembayaran_tercatat['tanggal_bayar'])) ?>
                        </p>
                        <?php if ($pembayaran_tercatat['jenis'] === 'tunai'): ?>
                            <p style="margin: 6px 0; font-size: 14px; color: #15803d;">
                                Uang Diterima: Rp <?= number_format($pembayaran_tercatat['jumlah_bayar'], 0, ',', '.') ?> | 
                                Kembalian: <strong>Rp <?= number_format($pembayaran_tercatat['kembalian'], 0, ',', '.') ?></strong>
                            </p>
                        <?php endif; ?>
                    <?php endif; ?>
                    <div style="margin-top: 18px;">
                        <a href="struk.php?id=<?= $pesanan['id'] ?>" class="btn btn-primary">
                            🧾 Cetak Struk Kasir
                        </a>
                    </div>
                </div>
            <?php else: ?>
                <form method="POST" action="billing.php?id_pesanan=<?= $pesanan['id'] ?>" id="formBayar">
                    <input type="hidden" name="aksi" value="bayar">
                    <input type="hidden" name="id_pesanan" value="<?= $pesanan['id'] ?>">

                    <div class="field" style="margin-bottom: 16px;">
                        <label>Metode Pembayaran *</label>
                        <select name="jenis" id="jenis_bayar" class="form-control" onchange="gantiMetodeBayar(this.value)" required>
                            <option value="tunai">💵 Tunai (Cash)</option>
                            <option value="qris">📱 QRIS (Scan Barcode)</option>
                            <option value="debit">💳 Kartu Debit</option>
                            <option value="kartu_kredit">💳 Kartu Kredit</option>
                        </select>
                    </div>

                    <!-- Input Tunai -->
                    <div id="box_tunai">
                        <div class="field" style="margin-bottom: 12px;">
                            <label>Uang Tunai Diterima (Rp) *</label>
                            <input type="number" 
                                   name="jumlah_bayar" 
                                   id="input_tunai" 
                                   class="form-control" 
                                   placeholder="Contoh: <?= $pesanan['total'] ?>" 
                                   min="<?= $pesanan['total'] ?>" 
                                   oninput="hitungKembalian(<?= $pesanan['total'] ?>)"
                                   style="font-size: 18px; font-weight:800; border-color: var(--amber);">
                        </div>
                        
                        <!-- Quick cash buttons -->
                        <div style="display:flex; gap:8px; flex-wrap:wrap; margin-bottom: 16px;">
                            <button type="button" class="btn btn-sm btn-secondary" onclick="setUangPas(<?= $pesanan['total'] ?>)">Uang Pas</button>
                            <button type="button" class="btn btn-sm btn-secondary" onclick="setNominal(50000, <?= $pesanan['total'] ?>)">Rp 50.000</button>
                            <button type="button" class="btn btn-sm btn-secondary" onclick="setNominal(100000, <?= $pesanan['total'] ?>)">Rp 100.000</button>
                            <button type="button" class="btn btn-sm btn-secondary" onclick="setNominal(200000, <?= $pesanan['total'] ?>)">Rp 200.000</button>
                        </div>

                        <div style="background: var(--slate-100); border: 1px dashed var(--border); border-radius: var(--radius-sm); padding: 14px; margin-bottom: 20px;">
                            <div style="display:flex; justify-content:space-between; align-items:center; font-size: 14px;">
                                <span class="fw-bold">Uang Kembalian:</span>
                                <strong id="teks_kembalian" style="font-size: 18px; color: #166534; font-weight:800;">Rp 0</strong>
                            </div>
                        </div>
                    </div>

                    <!-- Box QRIS -->
                    <div id="box_qris" style="display: none; margin-bottom: 20px;">
                        <div style="background: #ffffff; border: 2px solid #e2e8f0; border-radius: 16px; padding: 20px; text-align: center; box-shadow: 0 4px 12px rgba(0,0,0,0.05);">
                            <div style="font-weight: 800; font-size: 15px; color: #1e293b; margin-bottom: 4px;">SCAN QRIS UNTUK MEMBAYAR</div>
                            <div style="font-size: 13px; color: #64748b; margin-bottom: 14px;">Mendukung BCA, Mandiri, GoPay, OVO, Dana, ShopeePay & Semua Bank</div>
                            
                            <div style="display: inline-block; padding: 10px; background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; margin-bottom: 12px;">
                                <img src="assets/img/qris.png" alt="QRIS Dapur Ina Aina" style="width: 200px; height: auto; border-radius: 8px; display: block;">
                            </div>

                            <div style="background: #ecfdf5; border-radius: 8px; padding: 10px 14px; display: inline-block; border: 1px solid #a7f3d0; margin-bottom: 16px;">
                                <div style="font-size: 12px; color: #047857; font-weight: 600;">Total Pembayaran:</div>
                                <div style="font-size: 20px; font-weight: 900; color: #065f46;">Rp <?= number_format($pesanan['total'], 0, ',', '.') ?></div>
                            </div>

                            <div class="field" style="text-align: left;">
                                <label style="font-size: 13px;">Nomor RRN / Bukti Transfer QRIS (Opsional)</label>
                                <input type="text" name="no_ref_qris" id="input_ref_qris" class="form-control" placeholder="Contoh: QRIS-<?= date('Ymd') ?>-001">
                            </div>
                        </div>
                    </div>

                    <!-- Input Non-Tunai -->
                    <div id="box_nontunai" style="display: none;">
                        <div class="field" style="margin-bottom: 18px;">
                            <label>Nomor Referensi / Approval Code / ID Transaksi *</label>
                            <input type="text" name="no_referensi" id="input_ref" class="form-control" placeholder="Contoh: REF-2026-98234 atau No. Trace EDC">
                        </div>
                    </div>

                    <button type="submit" class="btn btn-primary" style="width: 100%; padding: 14px; font-size: 16px; font-weight: 800;">
                        ✅ Selesaikan Pembayaran (Rp <?= number_format($pesanan['total'], 0, ',', '.') ?>)
                    </button>
                </form>

                <script>
                function gantiMetodeBayar(jenis) {
                    const boxTunai = document.getElementById('box_tunai');
                    const boxQris = document.getElementById('box_qris');
                    const boxNonTunai = document.getElementById('box_nontunai');
                    const inputTunai = document.getElementById('input_tunai');
                    const inputRef = document.getElementById('input_ref');

                    boxTunai.style.display = 'none';
                    boxQris.style.display = 'none';
                    boxNonTunai.style.display = 'none';
                    inputTunai.required = false;
                    inputRef.required = false;

                    if (jenis === 'tunai') {
                        boxTunai.style.display = 'block';
                        inputTunai.required = true;
                    } else if (jenis === 'qris') {
                        boxQris.style.display = 'block';
                    } else {
                        boxNonTunai.style.display = 'block';
                        inputRef.required = true;
                    }
                }

                function hitungKembalian(total) {
                    const bayar = parseFloat(document.getElementById('input_tunai').value) || 0;
                    const kembali = bayar - total;
                    const elKembali = document.getElementById('teks_kembalian');
                    if (kembali >= 0) {
                        elKembali.style.color = '#166534';
                        elKembali.innerText = 'Rp ' + kembali.toLocaleString('id-ID');
                    } else {
                        elKembali.style.color = '#dc2626';
                        elKembali.innerText = 'Kurang Rp ' + Math.abs(kembali).toLocaleString('id-ID');
                    }
                }

                function setUangPas(total) {
                    document.getElementById('input_tunai').value = total;
                    hitungKembalian(total);
                }

                function setNominal(nom, total) {
                    if (nom < total) return;
                    document.getElementById('input_tunai').value = nom;
                    hitungKembalian(total);
                }
                </script>
            <?php endif; ?>
        </div>

    </div>
<?php endif; ?>

<!-- DAFTAR ANTREAN PESANAN YANG BELUM DIBAYAR -->
<div class="card">
    <h3 class="card-title">Antrean Pesanan Menunggu Pembayaran</h3>
    <div class="card-subtitle">
        Daftar pesanan aktif di meja yang belum lunas. Klik tombol <strong>Proses Pembayaran</strong> untuk membuka tagihan.
    </div>

    <?php if (empty($pesanan_menunggu)): ?>
        <div class="empty-state">
            <div class="empty-icon">💳</div>
            <p>Tidak ada pesanan yang menunggu pembayaran saat ini.</p>
        </div>
    <?php else: ?>
        <div class="table-wrapper">
            <table>
                <thead>
                    <tr>
                        <th>No. Pesanan</th>
                        <th>Waktu Pesan</th>
                        <th>Nama Pelanggan</th>
                        <th>Meja</th>
                        <th>Status Dapur</th>
                        <th style="text-align:right;">Total Tagihan</th>
                        <th style="text-align:center;">Aksi Kasir</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($pesanan_menunggu as $pm): ?>
                    <tr style="<?= ($pm['id'] == $idPesanan) ? 'background:#fffbeb;' : '' ?>">
                        <td><strong>#<?= str_pad($pm['id'], 5, '0', STR_PAD_LEFT) ?></strong></td>
                        <td><span class="text-muted"><?= date('d/m/Y H:i', strtotime($pm['tanggal'])) ?></span></td>
                        <td><strong><?= htmlspecialchars($pm['nama_pelanggan']) ?></strong></td>
                        <td><span class="badge badge-info">Meja <?= htmlspecialchars($pm['nomor_meja'] ?? '-') ?></span></td>
                        <td>
                            <?php if ($pm['status'] === 'diproses'): ?>
                                <span class="badge badge-warning">1. Diterima</span>
                            <?php elseif ($pm['status'] === 'disiapkan'): ?>
                                <span class="badge badge-biru">2. Dimasak</span>
                            <?php elseif ($pm['status'] === 'selesai'): ?>
                                <span class="badge badge-success">3. Sudah Siap</span>
                            <?php else: ?>
                                <span class="badge"><?= htmlspecialchars(label_status($pm['status'])) ?></span>
                            <?php endif; ?>
                        </td>
                        <td style="text-align:right; font-weight:800; color:#047857;">
                            Rp <?= number_format($pm['total_tagihan'], 0, ',', '.') ?>
                        </td>
                        <td style="text-align:center;">
                            <a href="billing.php?id_pesanan=<?= $pm['id'] ?>" class="btn btn-sm btn-primary">
                                💳 Proses Pembayaran
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
