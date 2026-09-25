<?php
/**
 * Halaman: Data Pesanan & Buat Pesanan Baru (Kasir)
 * Sesuai Use Case: Kasir - Membuat pesanan
 * Sesuai Struktur Navigasi: Menu Kasir → Pemesanan
 */
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';

cek_role(['admin', 'kasir']);

$halaman = 'pesanan';
$judul = 'Pemesanan';

try {
    $pdo = get_koneksi();

    // Proses buat pesanan baru
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['aksi'] ?? '') === 'buat_pesanan') {
        $namaPelanggan = trim($_POST['nama_pelanggan'] ?? '');
        $idMeja = (int) ($_POST['id_meja'] ?? 0);
        $items = $_POST['items'] ?? [];

        if ($namaPelanggan === '') {
            throw new InvalidArgumentException('Nama pelanggan wajib diisi.');
        }
        if (empty($items)) {
            throw new InvalidArgumentException('Pilih minimal satu menu.');
        }

        $pdo->beginTransaction();

        // Insert/update pelanggan
        $stmtPelanggan = $pdo->prepare(
            'INSERT INTO pelanggan (nama, id_meja) VALUES (?, ?)'
        );
        $stmtPelanggan->execute([$namaPelanggan, $idMeja ?: null]);
        $idPelanggan = $pdo->lastInsertId();

        // Update status meja
        if ($idMeja > 0) {
            $pdo->prepare("UPDATE meja SET status = 'terisi' WHERE id = ?")->execute([$idMeja]);
        }

        // Insert pesanan
        $stmtPesanan = $pdo->prepare(
            'INSERT INTO pesanan (id_pelanggan, id_pengguna, status) VALUES (?, ?, ?)'
        );
        $stmtPesanan->execute([$idPelanggan, user()['id'], 'diproses']);
        $idPesanan = $pdo->lastInsertId();

        // Insert detail pesanan & kurangi stok
        $stmtDetail = $pdo->prepare(
            'INSERT INTO detail_pesanan (id_pesanan, id_produk, jumlah, subtotal) VALUES (?, ?, ?, ?)'
        );
        $stmtCekStok = $pdo->prepare('SELECT nama, harga, stok FROM produk WHERE id = ? AND aktif = 1');
        $stmtKurangiStok = $pdo->prepare('UPDATE produk SET stok = stok - ? WHERE id = ?');

        foreach ($items as $idProduk => $jumlah) {
            $jumlah = (int) $jumlah;
            if ($jumlah <= 0) continue;

            $stmtCekStok->execute([$idProduk]);
            $produk = $stmtCekStok->fetch();

            if (!$produk) {
                throw new RuntimeException("Produk dengan ID {$idProduk} tidak ditemukan.");
            }
            if ($produk['stok'] < $jumlah) {
                throw new RuntimeException(
                    "Stok '{$produk['nama']}' tidak cukup. Tersedia: {$produk['stok']}, diminta: {$jumlah}."
                );
            }

            $subtotal = $produk['harga'] * $jumlah;
            $stmtDetail->execute([$idPesanan, $idProduk, $jumlah, $subtotal]);
            $stmtKurangiStok->execute([$jumlah, $idProduk]);
        }

        $pdo->commit();
        set_flash('success', "Pesanan #{$idPesanan} berhasil dibuat untuk {$namaPelanggan}.");
        header('Location: pesanan.php');
        exit;
    }

    // Proses update status pesanan
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['aksi'] ?? '') === 'update_status') {
        $idPesanan = (int) ($_POST['id_pesanan'] ?? 0);
        $statusBaru = $_POST['status_baru'] ?? '';
        $validStatus = ['diproses', 'disiapkan', 'selesai', 'dibatalkan'];

        if (!in_array($statusBaru, $validStatus, true)) {
            throw new InvalidArgumentException('Status tidak valid.');
        }

        $pdo->prepare('UPDATE pesanan SET status = ? WHERE id = ?')
            ->execute([$statusBaru, $idPesanan]);

        $statusLabels = [
            'diproses'   => '1. Diterima (Antrean Dapur) ⏳',
            'disiapkan'  => '2. Sedang Dimasak 🍳',
            'selesai'    => '3. Sudah Siap / Siap Santap 🍲',
            'dibatalkan' => 'Dibatalkan ✖',
        ];
        $namaStatus = $statusLabels[$statusBaru] ?? $statusBaru;
        set_flash('success', "Status pesanan #{$idPesanan} berhasil diperbarui: <strong>{$namaStatus}</strong>.");
        header('Location: pesanan.php');
        exit;
    }

    // Ambil data pesanan
    $pesananRows = $pdo->query(
        "SELECT ps.id, pl.nama AS nama_pelanggan, m.nomor_meja, ps.tanggal, ps.status,
                pb.id AS id_bayar, pb.tanggal_bayar, pb.jenis AS metode_bayar
         FROM pesanan ps
         JOIN pelanggan pl ON pl.id = ps.id_pelanggan
         LEFT JOIN meja m ON m.id = pl.id_meja
         LEFT JOIN pembayaran pb ON pb.id_pesanan = ps.id
         ORDER BY ps.id DESC"
    )->fetchAll();

    $stmtItem = $pdo->prepare(
        "SELECT pr.nama AS nama_produk, pr.harga, dp.jumlah, dp.subtotal
         FROM detail_pesanan dp
         JOIN produk pr ON pr.id = dp.id_produk
         WHERE dp.id_pesanan = ?"
    );

    $daftarPesanan = [];
    foreach ($pesananRows as $row) {
        $stmtItem->execute([$row['id']]);
        $row['items'] = $stmtItem->fetchAll();
        $row['total'] = array_sum(array_column($row['items'], 'subtotal'));
        $daftarPesanan[] = $row;
    }

    // Data untuk form buat pesanan
    $produkList = $pdo->query(
        "SELECT p.id, p.nama, p.harga, p.stok, k.nama_kategori
         FROM produk p JOIN kategori k ON k.id = p.id_kategori
         WHERE p.aktif = 1 AND p.stok > 0
         ORDER BY k.nama_kategori, p.nama"
    )->fetchAll();

    $mejaList = $pdo->query(
        "SELECT id, nomor_meja, kapasitas, status FROM meja ORDER BY nomor_meja"
    )->fetchAll();

} catch (InvalidArgumentException|RuntimeException $e) {
    if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
    set_flash('error', $e->getMessage());
    header('Location: pesanan.php');
    exit;
} catch (PDOException $e) {
    if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
    set_flash('error', 'Kesalahan database: ' . $e->getMessage());
    header('Location: pesanan.php');
    exit;
}

require __DIR__ . '/includes/header.php';
?>

<?php render_flash(); ?>

<!-- Form Buat Pesanan Baru -->
<div class="card">
  <div class="flex-between mb-2">
    <div>
      <h2>Buat Pesanan Baru</h2>
      <div class="card-subtitle">Pilih pelanggan, meja, dan menu yang dipesan</div>
    </div>
  </div>

  <form method="post" id="formPesanan">
    <input type="hidden" name="aksi" value="buat_pesanan">

    <div class="grid-2 mb-2">
      <div class="field">
        <label>Nama Pelanggan</label>
        <input type="text" name="nama_pelanggan" required placeholder="Masukkan nama pelanggan">
      </div>
      <div class="field">
        <label>Nomor Meja</label>
        <select name="id_meja">
          <option value="0">— Pilih Meja —</option>
          <?php foreach ($mejaList as $m): ?>
            <option value="<?= (int) $m['id'] ?>" <?= $m['status'] !== 'kosong' ? 'disabled' : '' ?>>
              Meja <?= (int) $m['nomor_meja'] ?> (<?= (int) $m['kapasitas'] ?> kursi)
              <?= $m['status'] !== 'kosong' ? '— ' . ucfirst($m['status']) : '' ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>

    <h3 style="font-size:15px;margin-bottom:10px;">Pilih Menu:</h3>
    <div class="table-wrapper mb-2">
      <table>
        <thead>
          <tr><th>Menu</th><th>Kategori</th><th>Harga</th><th>Stok</th><th>Jumlah</th></tr>
        </thead>
        <tbody>
          <?php foreach ($produkList as $pr): ?>
            <tr>
              <td><?= h($pr['nama']) ?></td>
              <td><?= h($pr['nama_kategori']) ?></td>
              <td><?= rupiah($pr['harga']) ?></td>
              <td><span class="badge <?= badge_stok(status_stok((int)$pr['stok'])) ?>"><?= (int)$pr['stok'] ?></span></td>
              <td>
                <input type="number" name="items[<?= (int)$pr['id'] ?>]" value="0"
                       min="0" max="<?= (int)$pr['stok'] ?>" style="width:80px;padding:6px 8px;">
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <button type="submit" class="btn btn-ungu">📋 Buat Pesanan</button>
  </form>
</div>

<!-- Daftar Pesanan -->
<div class="card">
  <div class="flex-between mb-2" style="flex-wrap:wrap; gap:12px;">
    <div>
      <h2>Daftar Pesanan</h2>
      <div class="card-subtitle">Semua pesanan yang tercatat & terhubung langsung dengan Dapur dan Cek Pesanan Pelanggan</div>
    </div>
    <!-- Filter Tabs Kasir -->
    <div style="display:flex; gap:6px; flex-wrap:wrap;">
      <button type="button" class="btn btn-sm btn-filter active" onclick="filterPesanan('all', this)" style="border-radius:9999px; font-weight:700;">Semua</button>
      <button type="button" class="btn btn-sm btn-filter" onclick="filterPesanan('diterima', this)" style="border-radius:9999px; font-weight:700;">⏳ 1. Diterima</button>
      <button type="button" class="btn btn-sm btn-filter" onclick="filterPesanan('dimasak', this)" style="border-radius:9999px; font-weight:700;">🍳 2. Dimasak</button>
      <button type="button" class="btn btn-sm btn-filter" onclick="filterPesanan('siap', this)" style="border-radius:9999px; font-weight:700;">🍲 3. Sudah Siap</button>
      <button type="button" class="btn btn-sm btn-filter" onclick="filterPesanan('lunas', this)" style="border-radius:9999px; font-weight:700;">💳 4. Lunas</button>
    </div>
  </div>

  <?php if (empty($daftarPesanan)): ?>
    <div class="empty-state">
      <div class="empty-icon">📭</div>
      <p>Belum ada data pesanan.</p>
    </div>
  <?php else: ?>
    <div id="containerPesanan">
    <?php foreach ($daftarPesanan as $p): ?>
      <?php
        $isLunas = !empty($p['id_bayar']);
        $stage = 'diterima';
        if ($isLunas) {
            $stage = 'lunas';
        } elseif ($p['status'] === 'selesai') {
            $stage = 'siap';
        } elseif ($p['status'] === 'disiapkan') {
            $stage = 'dimasak';
        } elseif ($p['status'] === 'diproses') {
            $stage = 'diterima';
        } elseif ($p['status'] === 'dibatalkan') {
            $stage = 'dibatalkan';
        }

        // Hitung progress 4 tahap untuk stepper
        $step1Active = !in_array($p['status'], ['dibatalkan']);
        $step2Active = in_array($p['status'], ['disiapkan', 'selesai']) || $isLunas;
        $step3Active = ($p['status'] === 'selesai') || $isLunas;
        $step4Active = $isLunas;
      ?>
      <div class="card item-pesanan" data-stage="<?= $stage ?>" style="background:#f8fafc; margin-bottom:16px; border:1px solid #e2e8f0; border-radius:14px; padding:18px;">
        <div class="flex-between" style="flex-wrap:wrap; gap:10px;">
          <div>
            <div style="font-size:16px; font-weight:800; color:#0f172a;">
              Pesanan #<?= (int) $p['id'] ?> — <?= h($p['nama_pelanggan']) ?>
              <?php if ($p['nomor_meja']): ?>
                <span class="badge badge-info" style="font-size:12px; margin-left:6px;">Meja <?= (int) $p['nomor_meja'] ?></span>
              <?php endif; ?>
            </div>
            <div class="muted" style="font-size:12.5px; margin-top:3px;">
              Waktu: <?= date('d M Y, H:i', strtotime($p['tanggal'])) ?> WIB
            </div>
          </div>
          <div>
            <?php if ($isLunas): ?>
              <span class="badge badge-success" style="font-weight:800; font-size:13px; padding:6px 14px;">💳 LUNAS (<?= htmlspecialchars(ucfirst($p['metode_bayar'] ?? 'Kasir')) ?>)</span>
            <?php elseif ($p['status'] === 'selesai'): ?>
              <span class="badge" style="background:#ecfdf5; color:#047857; border:1px solid #a7f3d0; font-weight:800; font-size:13px; padding:6px 14px;">🍲 3. Sudah Siap / Siap Santap</span>
            <?php elseif ($p['status'] === 'disiapkan'): ?>
              <span class="badge badge-biru" style="font-weight:800; font-size:13px; padding:6px 14px;">🍳 2. Sedang Dimasak</span>
            <?php elseif ($p['status'] === 'diproses'): ?>
              <span class="badge badge-warning" style="font-weight:800; font-size:13px; padding:6px 14px;">⏳ 1. Diterima (Antrean Dapur)</span>
            <?php elseif ($p['status'] === 'dibatalkan'): ?>
              <span class="badge badge-merah" style="font-weight:800; font-size:13px; padding:6px 14px;">✖ Dibatalkan</span>
            <?php else: ?>
              <span class="badge <?= badge_status($p['status']) ?>"><?= label_status($p['status']) ?></span>
            <?php endif; ?>
          </div>
        </div>

        <!-- Alur 4 Tahap Terhubung dengan Cek Pesanan Pelanggan -->
        <div style="display:flex; align-items:center; gap:8px; margin: 12px 0 14px; padding: 10px 14px; background: #fff; border-radius: 10px; border: 1px solid #e2e8f0; font-size: 12.5px; flex-wrap: wrap;">
          <div style="font-weight:800; color:#475569; margin-right:4px;">Tahapan:</div>
          
          <!-- Tahap 1: Diterima -->
          <span style="font-weight: 700; color: <?= $step1Active ? '#047857' : '#94a3b8' ?>; background: <?= $step1Active ? '#ecfdf5' : '#f1f5f9' ?>; padding: 4px 10px; border-radius: 6px; border: 1px solid <?= $step1Active ? '#a7f3d0' : '#e2e8f0' ?>;">
            <?= $step1Active ? '✅' : '⚪' ?> 1. Diterima
          </span>
          <span style="color:#cbd5e1; font-weight:bold;">➔</span>

          <!-- Tahap 2: Dimasak -->
          <span style="font-weight: 700; color: <?= $step2Active ? '#1d4ed8' : ($p['status'] === 'diproses' ? '#b45309' : '#94a3b8') ?>; background: <?= $step2Active ? '#eff6ff' : ($p['status'] === 'diproses' ? '#fffbeb' : '#f1f5f9') ?>; padding: 4px 10px; border-radius: 6px; border: 1px solid <?= $step2Active ? '#bfdbfe' : ($p['status'] === 'diproses' ? '#fde68a' : '#e2e8f0') ?>;">
            <?= $step2Active ? '🍳' : '⚪' ?> 2. Dimasak
          </span>
          <span style="color:#cbd5e1; font-weight:bold;">➔</span>

          <!-- Tahap 3: Sudah Siap (Siap Santap) -->
          <span style="font-weight: 700; color: <?= $step3Active ? '#047857' : ($p['status'] === 'disiapkan' ? '#1d4ed8' : '#94a3b8') ?>; background: <?= $step3Active ? '#ecfdf5' : '#f1f5f9' ?>; padding: 4px 10px; border-radius: 6px; border: 1px solid <?= $step3Active ? '#a7f3d0' : '#e2e8f0' ?>;">
            <?= $step3Active ? '🍲' : '⚪' ?> 3. Sudah Siap
          </span>
          <span style="color:#cbd5e1; font-weight:bold;">➔</span>

          <!-- Tahap 4: Pembayaran -->
          <span style="font-weight: 700; color: <?= $step4Active ? '#047857' : '#94a3b8' ?>; background: <?= $step4Active ? '#ecfdf5' : '#f1f5f9' ?>; padding: 4px 10px; border-radius: 6px; border: 1px solid <?= $step4Active ? '#a7f3d0' : '#e2e8f0' ?>;">
            <?= $step4Active ? '💳' : '⏳' ?> 4. Pembayaran <?= $step4Active ? '(Lunas)' : '(Kasir)' ?>
          </span>
        </div>

        <div class="table-wrapper" style="margin-top:8px;">
          <table>
            <thead>
              <tr><th>Produk</th><th>Harga</th><th>Qty</th><th>Subtotal</th></tr>
            </thead>
            <tbody>
              <?php foreach ($p['items'] as $item): ?>
                <tr>
                  <td><?= h($item['nama_produk']) ?></td>
                  <td><?= rupiah($item['harga']) ?></td>
                  <td><?= (int) $item['jumlah'] ?></td>
                  <td><?= rupiah($item['subtotal']) ?></td>
                </tr>
              <?php endforeach; ?>
              <tr class="total-row">
                <td colspan="3">Total Tagihan</td>
                <td><?= rupiah($p['total']) ?></td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- Tombol Aksi Kasir & Dapur -->
        <div class="btn-group mt-1" style="display:flex; gap:8px; align-items:center; flex-wrap:wrap; padding-top:10px; border-top:1px solid #e2e8f0;">
          
          <?php if ($p['status'] === 'diproses'): ?>
            <!-- 1. Tombol Mulai Masak (Diproses -> Disiapkan) -->
            <form method="post" style="display:inline;">
              <input type="hidden" name="aksi" value="update_status">
              <input type="hidden" name="id_pesanan" value="<?= (int)$p['id'] ?>">
              <input type="hidden" name="status_baru" value="disiapkan">
              <button type="submit" class="btn btn-sm btn-biru" style="font-weight:800; padding:8px 16px; border-radius:8px;">
                🍳 1. Mulai Masak
              </button>
            </form>
          <?php endif; ?>

          <?php if ($p['status'] === 'disiapkan'): ?>
            <!-- 2. Tombol Sudah Siap (Disiapkan -> Selesai / Siap Santap) -->
            <form method="post" style="display:inline;">
              <input type="hidden" name="aksi" value="update_status">
              <input type="hidden" name="id_pesanan" value="<?= (int)$p['id'] ?>">
              <input type="hidden" name="status_baru" value="selesai">
              <button type="submit" class="btn btn-sm" style="font-weight:800; background:#059669; color:#fff; border:none; padding:8px 16px; border-radius:8px; box-shadow: 0 2px 6px rgba(5,150,105,0.35);">
                🍲 2. Proses Sudah Siap / Siap Santap
              </button>
            </form>

            <!-- Kembalikan ke antrean jika salah klik -->
            <form method="post" style="display:inline;">
              <input type="hidden" name="aksi" value="update_status">
              <input type="hidden" name="id_pesanan" value="<?= (int)$p['id'] ?>">
              <input type="hidden" name="status_baru" value="diproses">
              <button type="submit" class="btn btn-sm btn-secondary" style="font-size:11.5px; opacity:0.8;">
                ↩ Balik ke Antrean
              </button>
            </form>
          <?php endif; ?>

          <?php if ($p['status'] === 'selesai' && empty($p['id_bayar'])): ?>
            <!-- Kembalikan ke memasak jika salah klik -->
            <form method="post" style="display:inline;">
              <input type="hidden" name="aksi" value="update_status">
              <input type="hidden" name="id_pesanan" value="<?= (int)$p['id'] ?>">
              <input type="hidden" name="status_baru" value="disiapkan">
              <button type="submit" class="btn btn-sm btn-secondary" style="font-size:11.5px; opacity:0.8;">
                ↩ Balik ke Dimasak
              </button>
            </form>
          <?php endif; ?>

          <?php if (empty($p['id_bayar']) && in_array($p['status'], ['diproses', 'disiapkan', 'selesai'])): ?>
            <!-- 3. Tombol Proses Billing & Pembayaran Kasir jika belum lunas -->
            <a class="btn btn-sm btn-primary" style="font-weight:800; padding:8px 16px; border-radius:8px; background:var(--hijau); color:#fff;" href="billing.php?id_pesanan=<?= (int)$p['id'] ?>">
              💳 Proses Pembayaran di Kasir
            </a>
          <?php endif; ?>

          <?php if (!empty($p['id_bayar'])): ?>
            <!-- 4. Tombol Cetak Struk jika sudah bayar -->
            <a class="btn btn-sm btn-secondary" style="font-weight:800; padding:8px 16px; border-radius:8px;" href="struk.php?id=<?= (int)$p['id'] ?>" target="_blank">
              🧾 Cetak Struk Kasir
            </a>
          <?php endif; ?>

          <?php if (empty($p['id_bayar']) && $p['status'] !== 'dibatalkan'): ?>
            <!-- 5. Tombol Batalkan Pesanan -->
            <form method="post" style="display:inline;">
              <input type="hidden" name="aksi" value="update_status">
              <input type="hidden" name="id_pesanan" value="<?= (int)$p['id'] ?>">
              <input type="hidden" name="status_baru" value="dibatalkan">
              <button type="submit" class="btn btn-sm btn-merah" onclick="return confirm('Apakah Anda yakin ingin membatalkan pesanan #<?= (int)$p['id'] ?>?')" style="opacity:0.8; font-size:12px;">
                ✖ Batalkan
              </button>
            </form>
          <?php endif; ?>

          <!-- Link Lacak Pesanan untuk Pelanggan (Pratinjau) -->
          <a class="btn btn-sm btn-secondary" style="font-size:11.5px; margin-left:auto; opacity:0.85;" href="pesanan_saya.php?id_pesanan=<?= (int)$p['id'] ?>" target="_blank">
            👁️ Cek Tampilan Pelanggan
          </a>
        </div>
      </div>
    <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>

<script>
function filterPesanan(stage, btn) {
  // Update active state tab
  document.querySelectorAll('.btn-filter').forEach(b => {
    b.classList.remove('active');
    b.style.background = '';
    b.style.color = '';
  });
  btn.classList.add('active');
  btn.style.background = 'var(--hijau)';
  btn.style.color = '#fff';

  const items = document.querySelectorAll('.item-pesanan');
  items.forEach(item => {
    if (stage === 'all' || item.dataset.stage === stage) {
      item.style.display = 'block';
    } else {
      item.style.display = 'none';
    }
  });
}
</script>

<?php require __DIR__ . '/includes/footer.php'; ?>
