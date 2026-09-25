<?php
/**
 * Halaman: Kelola Stok
 * - Menampilkan stok berdasarkan kriteria (kategori dan/atau status).
 * - Input produk baru.
 * - Update stok produk (tambah/kurang).
 * Semua perubahan langsung disimpan ke tabel `produk` pada database MySQL.
 */
session_start();
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';

$halaman = 'stok';
$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);

function set_flash(string $type, string $message): void
{
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

try {
    $pdo = get_koneksi();

    // --- Proses form: tambah produk baru ---
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['aksi'] ?? '') === 'tambah_produk') {
        $nama = trim($_POST['nama'] ?? '');
        $harga = $_POST['harga'] ?? '';
        $stok = $_POST['stok'] ?? '';
        $idKategori = $_POST['id_kategori'] ?? '';

        if ($nama === '') {
            throw new InvalidArgumentException('Nama produk tidak boleh kosong.');
        }
        if (!is_numeric($harga) || (float) $harga <= 0) {
            throw new InvalidArgumentException('Harga produk harus berupa angka lebih dari 0.');
        }
        if (!is_numeric($stok) || (int) $stok < 0) {
            throw new InvalidArgumentException('Stok awal harus berupa angka dan tidak boleh negatif.');
        }
        if (!is_numeric($idKategori)) {
            throw new InvalidArgumentException('Kategori wajib dipilih.');
        }

        $cekKategori = $pdo->prepare('SELECT 1 FROM kategori WHERE id = ?');
        $cekKategori->execute([$idKategori]);
        if (!$cekKategori->fetch()) {
            throw new RuntimeException("Kategori dengan id {$idKategori} tidak ditemukan.");
        }

        $stmt = $pdo->prepare(
            'INSERT INTO produk (nama, harga, stok, id_kategori) VALUES (?,?,?,?)'
        );
        $stmt->execute([$nama, $harga, $stok, $idKategori]);
        set_flash('success', "Produk '{$nama}' berhasil ditambahkan.");
        header('Location: stok.php');
        exit;
    }

    // --- Proses form: update stok ---
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['aksi'] ?? '') === 'update_stok') {
        $idProduk = $_POST['id_produk'] ?? '';
        $perubahan = $_POST['perubahan'] ?? '';

        if (!is_numeric($idProduk) || !is_numeric($perubahan)) {
            throw new InvalidArgumentException('ID produk dan perubahan stok harus berupa angka.');
        }

        $stmt = $pdo->prepare('SELECT nama, stok FROM produk WHERE id = ?');
        $stmt->execute([$idProduk]);
        $produk = $stmt->fetch();
        if (!$produk) {
            throw new RuntimeException("Produk dengan id {$idProduk} tidak ditemukan.");
        }

        $stokBaru = $produk['stok'] + (int) $perubahan;
        if ($stokBaru < 0) {
            throw new RuntimeException(
                "Stok '{$produk['nama']}' tidak cukup. Stok saat ini {$produk['stok']}, " .
                'diminta pengurangan ' . abs((int) $perubahan) . '.'
            );
        }

        $update = $pdo->prepare('UPDATE produk SET stok = ? WHERE id = ?');
        $update->execute([$stokBaru, $idProduk]);
        set_flash('success', "Stok '{$produk['nama']}' diperbarui: {$produk['stok']} -> {$stokBaru}.");
        header('Location: stok.php');
        exit;
    }

    // --- Filter kriteria tampilan ---
    $filterKategori = $_GET['kategori'] ?? '';
    $filterStatus = $_GET['status'] ?? '';

    $sql = "SELECT pr.id, pr.nama, pr.harga, pr.stok, k.nama_kategori, k.id AS id_kategori
            FROM produk pr JOIN kategori k ON k.id = pr.id_kategori";
    $params = [];
    if ($filterKategori !== '') {
        $sql .= ' WHERE k.nama_kategori = ?';
        $params[] = $filterKategori;
    }
    $sql .= ' ORDER BY pr.id';

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $produkList = $stmt->fetchAll();

    if ($filterStatus !== '') {
        $produkList = array_filter(
            $produkList,
            fn($p) => status_stok((int) $p['stok']) === $filterStatus
        );
    }

    $kategoriList = $pdo->query('SELECT id, nama_kategori FROM kategori ORDER BY id')->fetchAll();
} catch (InvalidArgumentException $e) {
    set_flash('error', 'Input tidak valid: ' . $e->getMessage());
    header('Location: stok.php');
    exit;
} catch (RuntimeException $e) {
    set_flash('error', $e->getMessage());
    header('Location: stok.php');
    exit;
} catch (PDOException $e) {
    set_flash('error', 'Kesalahan database: ' . $e->getMessage());
    header('Location: stok.php');
    exit;
}

require __DIR__ . '/includes/header.php';
?>

<?php if ($flash): ?>
  <div class="alert alert-<?= $flash['type'] === 'success' ? 'success' : 'error' ?>">
    <?= $flash['type'] === 'success' ? '✅' : '⚠️' ?> <?= h($flash['message']) ?>
  </div>
<?php endif; ?>

<div class="card">
  <h2>Filter Data Stok</h2>
  <form class="inline" method="get">
    <div class="field">
      <label>Kategori</label>
      <select name="kategori">
        <option value="">Semua Kategori</option>
        <?php foreach ($kategoriList as $k): ?>
          <option value="<?= h($k['nama_kategori']) ?>" <?= $filterKategori === $k['nama_kategori'] ? 'selected' : '' ?>>
            <?= h($k['nama_kategori']) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="field">
      <label>Status Stok</label>
      <select name="status">
        <option value="">Semua Status</option>
        <option value="Tersedia" <?= $filterStatus === 'Tersedia' ? 'selected' : '' ?>>Tersedia</option>
        <option value="Menipis" <?= $filterStatus === 'Menipis' ? 'selected' : '' ?>>Menipis</option>
        <option value="Habis" <?= $filterStatus === 'Habis' ? 'selected' : '' ?>>Habis</option>
      </select>
    </div>
    <button type="submit">Terapkan Filter</button>
    <a class="btn secondary" href="stok.php">Reset</a>
  </form>

  <table>
    <thead>
      <tr><th>ID</th><th>Nama Produk</th><th>Kategori</th><th>Harga</th><th>Stok</th><th>Status</th></tr>
    </thead>
    <tbody>
      <?php if (empty($produkList)): ?>
        <tr><td colspan="6" class="muted">Tidak ada produk yang cocok dengan kriteria tersebut.</td></tr>
      <?php else: ?>
        <?php foreach ($produkList as $p): $status = status_stok((int) $p['stok']); ?>
          <tr>
            <td>#<?= (int) $p['id'] ?></td>
            <td><?= h($p['nama']) ?></td>
            <td><?= h($p['nama_kategori']) ?></td>
            <td><?= rupiah($p['harga']) ?></td>
            <td><?= (int) $p['stok'] ?></td>
            <td><span class="badge <?= badge_stok($status) ?>"><?= $status ?></span></td>
          </tr>
        <?php endforeach; ?>
      <?php endif; ?>
    </tbody>
  </table>
</div>

<div class="grid-2">
  <div class="card">
    <h2>Input Produk Baru</h2>
    <form method="post">
      <input type="hidden" name="aksi" value="tambah_produk">
      <div class="field" style="margin-bottom:10px;">
        <label>Nama Produk</label>
        <input type="text" name="nama" required>
      </div>
      <div class="field" style="margin-bottom:10px;">
        <label>Harga (Rp)</label>
        <input type="number" name="harga" min="1" required>
      </div>
      <div class="field" style="margin-bottom:10px;">
        <label>Stok Awal</label>
        <input type="number" name="stok" min="0" required>
      </div>
      <div class="field" style="margin-bottom:14px;">
        <label>Kategori</label>
        <select name="id_kategori" required>
          <?php foreach ($kategoriList as $k): ?>
            <option value="<?= (int) $k['id'] ?>"><?= h($k['nama_kategori']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <button type="submit" class="btn-merah">Simpan Produk</button>
    </form>
  </div>

  <div class="card">
    <h2>Update Stok Produk</h2>
    <p class="muted">Isi angka positif untuk menambah stok, atau negatif untuk mengurangi (mis. -5).</p>
    <form method="post">
      <input type="hidden" name="aksi" value="update_stok">
      <div class="field" style="margin-bottom:10px;">
        <label>ID Produk</label>
        <input type="number" name="id_produk" min="1" required>
      </div>
      <div class="field" style="margin-bottom:14px;">
        <label>Perubahan Stok</label>
        <input type="number" name="perubahan" required>
      </div>
      <button type="submit" class="btn-merah">Update Stok</button>
    </form>
  </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
