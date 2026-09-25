<?php
/**
 * Halaman: Catat Pesanan Pelanggan (Pelayan)
 * Sesuai Activity Diagram:
 * - Pelanggan memilih menu
 * - Pelayan mencatat pesanan
 * - Cek ketersediaan stok produk
 * - Jika habis: informasikan ke pelanggan
 * - Jika cukup: simpan pesanan & kurangi stok
 * Sesuai Struktur Navigasi: Menu Pelayan → Catat Pesanan
 */
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';

cek_role(['admin', 'kasir']);
$currentUser = user();
$pdo = get_koneksi();

$pesan_sukses = '';
$pesan_error = '';

// Proses Simpan Pesanan dari Pelayan
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['simpan_pesanan'])) {
    $nama_pelanggan = trim($_POST['nama_pelanggan'] ?? '');
    $id_meja = (int)($_POST['id_meja'] ?? 0);
    $items = $_POST['items'] ?? []; // array [id_produk => qty]

    if (empty($nama_pelanggan)) {
        $pesan_error = 'Nama pelanggan wajib diisi.';
    } elseif ($id_meja <= 0) {
        $pesan_error = 'Silakan pilih meja makan pelanggan.';
    } else {
        // Filter items yang qty > 0
        $pesanan_items = [];
        foreach ($items as $prod_id => $qty) {
            $qty = (int)$qty;
            if ($qty > 0) {
                $pesanan_items[(int)$prod_id] = $qty;
            }
        }

        if (empty($pesanan_items)) {
            $pesan_error = 'Pilih minimal satu menu dengan jumlah lebih dari 0.';
        } else {
            // Cek Stok Sesuai Activity Diagram: "Cek Stok Produk"
            $stok_habis_items = [];
            $produk_db = [];

            try {
                $ids = implode(',', array_keys($pesanan_items));
                $stmtCheck = $pdo->query("SELECT id, nama, harga, stok FROM produk WHERE id IN ($ids) AND aktif = 1");
                while ($p = $stmtCheck->fetch()) {
                    $produk_db[$p['id']] = $p;
                    $diminta = $pesanan_items[$p['id']];
                    if ($p['stok'] < $diminta) {
                        $stok_habis_items[] = "{$p['nama']} (Tersedia: {$p['stok']}, Diminta: {$diminta})";
                    }
                }

                // Cek jika ada produk yang tidak ditemukan
                foreach ($pesanan_items as $pid => $pqty) {
                    if (!isset($produk_db[$pid])) {
                        $stok_habis_items[] = "Menu ID #$pid tidak ditemukan / tidak aktif";
                    }
                }

                // Jika stok tidak mencukupi (Alur Activity Diagram: Informasikan Habis)
                if (!empty($stok_habis_items)) {
                    $pesan_error = "⚠️ Peringatan Stok Tidak Cukup:<br>• " . implode("<br>• ", $stok_habis_items) . "<br><small>Mohon informasikan kepada pelanggan untuk mengganti pilihan menu.</small>";
                } else {
                    // Semua stok cukup -> Buka transaksi DB & Simpan
                    $pdo->beginTransaction();

                    // 1. Simpan atau ambil pelanggan
                    $stmtPel = $pdo->prepare("INSERT INTO pelanggan (nama, id_meja) VALUES (:nama, :id_meja)");
                    $stmtPel->execute([
                        'nama' => $nama_pelanggan,
                        'id_meja' => $id_meja
                    ]);
                    $id_pelanggan = $pdo->lastInsertId();

                    // 2. Update status meja jadi terisi
                    $stmtMeja = $pdo->prepare("UPDATE meja SET status = 'terisi' WHERE id = :id");
                    $stmtMeja->execute(['id' => $id_meja]);

                    // 3. Simpan Pesanan utama (status: 'diproses')
                    $stmtOrder = $pdo->prepare("
                        INSERT INTO pesanan (id_pelanggan, id_pengguna, tanggal, status)
                        VALUES (:id_pelanggan, :id_pengguna, NOW(), 'diproses')
                    ");
                    $stmtOrder->execute([
                        'id_pelanggan' => $id_pelanggan,
                        'id_pengguna' => $currentUser['id']
                    ]);
                    $id_pesanan = $pdo->lastInsertId();

                    // 4. Simpan Detail Pesanan & Potong Stok Produk
                    $stmtDetail = $pdo->prepare("
                        INSERT INTO detail_pesanan (id_pesanan, id_produk, jumlah, subtotal)
                        VALUES (:id_pesanan, :id_produk, :jumlah, :subtotal)
                    ");

                    $stmtPotongStok = $pdo->prepare("
                        UPDATE produk SET stok = stok - :qty WHERE id = :id
                    ");

                    foreach ($pesanan_items as $prod_id => $qty) {
                        $harga = $produk_db[$prod_id]['harga'];
                        $subtotal = $harga * $qty;

                        $stmtDetail->execute([
                            'id_pesanan' => $id_pesanan,
                            'id_produk'  => $prod_id,
                            'jumlah'     => $qty,
                            'subtotal'   => $subtotal
                        ]);

                        $stmtPotongStok->execute([
                            'qty' => $qty,
                            'id'  => $prod_id
                        ]);
                    }

                    $pdo->commit();
                    $pesan_sukses = "Pesanan #$id_pesanan atas nama <strong>" . htmlspecialchars($nama_pelanggan) . "</strong> di Meja $id_meja berhasil dicatat dan diteruskan ke Dapur!";
                }
            } catch (Exception $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                $pesan_error = 'Gagal menyimpan pesanan: ' . $e->getMessage();
            }
        }
    }
}

// Ambil daftar meja
$daftar_meja = [];
try {
    $stmtM = $pdo->query("SELECT * FROM meja ORDER BY nomor_meja ASC");
    $daftar_meja = $stmtM->fetchAll();
} catch (PDOException $e) {}

// Ambil daftar produk yang aktif beserta kategorinya
$daftar_produk = [];
try {
    $stmtP = $pdo->query("
        SELECT pr.*, k.nama_kategori
        FROM produk pr
        JOIN kategori k ON pr.id_kategori = k.id
        WHERE pr.aktif = 1
        ORDER BY k.nama_kategori ASC, pr.nama ASC
    ");
    $daftar_produk = $stmtP->fetchAll();
} catch (PDOException $e) {}

// Pre-selected tambah_id dari URL (jika datang dari menu_view.php)
$preselected_id = (int)($_GET['tambah_id'] ?? 0);

$halaman = 'catat_pesanan';
$judul = 'Catat Pesanan Pelanggan';
require_once __DIR__ . '/includes/header.php';
?>

<div class="page-header" style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:12px;">
    <div>
        <h1 class="page-title">📝 Catat Pesanan Pelanggan</h1>
        <p class="text-muted">Formulir pemesanan staff dengan pengecekan stok otomatis</p>
    </div>
    <div>
        <a href="menu_view.php" class="btn btn-secondary">
            📖 Lihat Galeri Menu
        </a>
        <a href="pesanan.php" class="btn btn-primary">
            📋 Antrean Pesanan Dapur
        </a>
    </div>
</div>

<?php if ($pesan_sukses): ?>
    <div class="alert alert-success" style="font-size: 15px;">
        🎉 <?= $pesan_sukses ?>
        <div style="margin-top: 10px;">
            <a href="catat_pesanan.php" class="btn btn-sm btn-primary">+ Catat Pesanan Baru</a>
            <a href="pesanan.php" class="btn btn-sm btn-secondary">Lihat Status di Dapur</a>
        </div>
    </div>
<?php endif; ?>

<?php if ($pesan_error): ?>
    <div class="alert alert-danger" style="font-size: 14px; line-height: 1.6;">
        <?= $pesan_error ?>
    </div>
<?php endif; ?>

<form method="POST" action="catat_pesanan.php" id="formPesanan">
    <div style="display:grid; grid-template-columns: 1fr 340px; gap: 24px; align-items: start;">
        
        <!-- Kolom Kiri: Pilihan Menu -->
        <div class="card">
            <h3 class="card-title">1. Pilih Menu & Tentukan Jumlah</h3>
            <p class="text-muted" style="margin-top:-8px; margin-bottom:16px; font-size:13px;">
                Stok diverifikasi otomatis. Menu dengan stok 0 tidak dapat dipesan.
            </p>

            <div class="table-responsive">
                <table class="table" style="vertical-align: middle;">
                    <thead>
                        <tr>
                            <th>Menu</th>
                            <th>Kategori</th>
                            <th>Harga</th>
                            <th>Status Stok</th>
                            <th style="width: 130px; text-align: center;">Jumlah (Qty)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $currentKat = '';
                        foreach ($daftar_produk as $prod): 
                            $isHabis = ($prod['stok'] <= 0);
                            $isSelected = ($prod['id'] == $preselected_id);
                        ?>
                        <tr style="<?= $isHabis ? 'opacity: 0.5; background: #f8fafc;' : '' ?> <?= $isSelected ? 'background: #fefce8;' : '' ?>">
                            <td>
                                <strong><?= htmlspecialchars($prod['nama']) ?></strong>
                            </td>
                            <td>
                                <span class="badge badge-info" style="font-size: 11px;">
                                    <?= htmlspecialchars($prod['nama_kategori']) ?>
                                </span>
                            </td>
                            <td style="font-weight: 600; color: #047857;">
                                Rp <?= number_format($prod['harga'], 0, ',', '.') ?>
                            </td>
                            <td>
                                <?php if ($prod['stok'] <= 0): ?>
                                    <span class="badge badge-danger">Habis (0)</span>
                                <?php elseif ($prod['stok'] <= 5): ?>
                                    <span class="badge badge-warning">Sisa <?= $prod['stok'] ?></span>
                                <?php else: ?>
                                    <span class="badge badge-success"><?= $prod['stok'] ?> Tersedia</span>
                                <?php endif; ?>
                            </td>
                            <td style="text-align: center;">
                                <?php if ($isHabis): ?>
                                    <span class="text-muted" style="font-size:12px; font-weight:bold;">Tidak Tersedia</span>
                                <?php else: ?>
                                    <div style="display:flex; align-items:center; justify-content:center; gap:4px;">
                                        <button type="button" class="btn btn-sm btn-secondary" style="padding: 4px 8px;" onclick="ubahQty(<?= $prod['id'] ?>, -1)">-</button>
                                        <input type="number" 
                                               name="items[<?= $prod['id'] ?>]" 
                                               id="qty_<?= $prod['id'] ?>" 
                                               value="<?= $isSelected ? 1 : 0 ?>" 
                                               min="0" 
                                               max="<?= $prod['stok'] ?>" 
                                               class="form-control" 
                                               style="width: 60px; text-align: center; padding: 4px;"
                                               onchange="hitungTotal()">
                                        <button type="button" class="btn btn-sm btn-secondary" style="padding: 4px 8px;" onclick="ubahQty(<?= $prod['id'] ?>, 1, <?= $prod['stok'] ?>)">+</button>
                                    </div>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Kolom Kanan: Info Pelanggan & Meja -->
        <div class="card" style="position: sticky; top: 20px;">
            <h3 class="card-title">2. Data Meja & Pelanggan</h3>

            <div class="form-group" style="margin-bottom: 16px;">
                <label for="nama_pelanggan" style="font-weight: 600; font-size: 13px;">Nama Pelanggan / Tamu *</label>
                <input type="text" name="nama_pelanggan" id="nama_pelanggan" class="form-control" placeholder="Contoh: Pak Budi" required>
            </div>

            <div class="form-group" style="margin-bottom: 20px;">
                <label for="id_meja" style="font-weight: 600; font-size: 13px;">Pilih Meja *</label>
                <select name="id_meja" id="id_meja" class="form-control" required>
                    <option value="">-- Pilih Meja --</option>
                    <?php foreach ($daftar_meja as $m): ?>
                        <option value="<?= $m['id'] ?>">
                            Meja <?= htmlspecialchars($m['nomor_meja']) ?> (Kapasitas: <?= $m['kapasitas'] ?> org - Status: <?= ucfirst($m['status']) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 14px; margin-bottom: 20px;">
                <div style="display:flex; justify-content:space-between; margin-bottom:6px; font-size:13px; color:#64748b;">
                    <span>Dicatat Oleh:</span>
                    <strong><?= htmlspecialchars($currentUser['nama_lengkap']) ?> (<?= ucfirst($currentUser['role']) ?>)</strong>
                </div>
                <div style="display:flex; justify-content:space-between; font-size:13px; color:#64748b;">
                    <span>Status Pesanan:</span>
                    <span class="badge badge-warning">Diproses</span>
                </div>
            </div>

            <button type="submit" name="simpan_pesanan" class="btn btn-primary" style="width: 100%; padding: 12px; font-size: 15px; font-weight: 700;">
                🚀 Kirim Pesanan ke Dapur
            </button>
        </div>

    </div>
</form>

<script>
function ubahQty(id, delta, max) {
    const input = document.getElementById('qty_' + id);
    if (!input) return;
    let val = parseInt(input.value) || 0;
    val += delta;
    if (val < 0) val = 0;
    if (max !== undefined && val > max) val = max;
    input.value = val;
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
