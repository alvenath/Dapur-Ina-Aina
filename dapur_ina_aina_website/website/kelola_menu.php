<?php
/**
 * Halaman: Kelola Menu (Admin & Owner)
 * Sesuai Use Case: Admin - Mengelola menu & stok
 * Sesuai Struktur Navigasi: Menu Admin → Kelola Menu
 */
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';

cek_role(['admin']);
$currentUser = user();
$pdo = get_koneksi();

$pesan_sukses = '';
$pesan_error = '';

// Handle Tambah / Edit / Hapus Menu
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $aksi = $_POST['aksi'] ?? '';

    if ($aksi === 'tambah') {
        $nama = trim($_POST['nama'] ?? '');
        $harga = (float)($_POST['harga'] ?? 0);
        $stok = (int)($_POST['stok'] ?? 0);
        $id_kategori = (int)($_POST['id_kategori'] ?? 0);
        $gambar = trim($_POST['gambar'] ?? '');

        if ($nama === '' || $harga <= 0 || $id_kategori <= 0) {
            $pesan_error = 'Nama, harga valid (> 0), dan kategori wajib diisi.';
        } else {
            try {
                $stmt = $pdo->prepare("
                    INSERT INTO produk (nama, harga, stok, id_kategori, gambar, aktif)
                    VALUES (:nama, :harga, :stok, :id_kategori, :gambar, 1)
                ");
                $stmt->execute([
                    'nama' => $nama,
                    'harga' => $harga,
                    'stok' => $stok,
                    'id_kategori' => $id_kategori,
                    'gambar' => !empty($gambar) ? $gambar : null
                ]);
                $pesan_sukses = "Menu '<strong>" . htmlspecialchars($nama) . "</strong>' berhasil ditambahkan!";
            } catch (PDOException $e) {
                $pesan_error = 'Gagal menambah menu: ' . $e->getMessage();
            }
        }
    } elseif ($aksi === 'edit') {
        $id = (int)($_POST['id'] ?? 0);
        $nama = trim($_POST['nama'] ?? '');
        $harga = (float)($_POST['harga'] ?? 0);
        $stok = (int)($_POST['stok'] ?? 0);
        $id_kategori = (int)($_POST['id_kategori'] ?? 0);
        $gambar = trim($_POST['gambar'] ?? '');
        $aktif = isset($_POST['aktif']) ? 1 : 0;

        if ($id <= 0 || $nama === '' || $harga <= 0) {
            $pesan_error = 'Data menu tidak valid.';
        } else {
            try {
                $stmt = $pdo->prepare("
                    UPDATE produk
                    SET nama = :nama, harga = :harga, stok = :stok, id_kategori = :id_kategori,
                        gambar = :gambar, aktif = :aktif
                    WHERE id = :id
                ");
                $stmt->execute([
                    'nama' => $nama,
                    'harga' => $harga,
                    'stok' => $stok,
                    'id_kategori' => $id_kategori,
                    'gambar' => !empty($gambar) ? $gambar : null,
                    'aktif' => $aktif,
                    'id' => $id
                ]);
                $pesan_sukses = "Menu '<strong>" . htmlspecialchars($nama) . "</strong>' berhasil diperbarui!";
            } catch (PDOException $e) {
                $pesan_error = 'Gagal mengupdate menu: ' . $e->getMessage();
            }
        }
    } elseif ($aksi === 'update_stok') {
        $id = (int)($_POST['id'] ?? 0);
        $tambah_stok = (int)($_POST['tambah_stok'] ?? 0);

        if ($id > 0 && $tambah_stok !== 0) {
            try {
                $stmt = $pdo->prepare("UPDATE produk SET stok = GREATEST(0, stok + :qty) WHERE id = :id");
                $stmt->execute(['qty' => $tambah_stok, 'id' => $id]);
                $pesan_sukses = "Stok produk berhasil disesuaikan.";
            } catch (PDOException $e) {
                $pesan_error = 'Gagal memperbarui stok: ' . $e->getMessage();
            }
        }
    } elseif ($aksi === 'hapus') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            try {
                // Periksa apakah produk sudah pernah dipesan
                $cek = $pdo->prepare("SELECT COUNT(*) FROM detail_pesanan WHERE id_produk = :id");
                $cek->execute(['id' => $id]);
                if ($cek->fetchColumn() > 0) {
                    // Soft delete agar riwayat pesanan tidak rusak
                    $stmt = $pdo->prepare("UPDATE produk SET aktif = 0 WHERE id = :id");
                    $stmt->execute(['id' => $id]);
                    $pesan_sukses = "Menu dinonaktifkan dari katalog (tersimpan di riwayat transaksi).";
                } else {
                    $stmt = $pdo->prepare("DELETE FROM produk WHERE id = :id");
                    $stmt->execute(['id' => $id]);
                    $pesan_sukses = "Menu berhasil dihapus permanen.";
                }
            } catch (PDOException $e) {
                $pesan_error = 'Gagal menghapus menu: ' . $e->getMessage();
            }
        }
    } elseif ($aksi === 'tambah_kategori') {
        $nama_kategori = trim($_POST['nama_kategori'] ?? '');
        if ($nama_kategori !== '') {
            try {
                $stmt = $pdo->prepare("INSERT INTO kategori (nama_kategori) VALUES (:nama)");
                $stmt->execute(['nama' => $nama_kategori]);
                $pesan_sukses = "Kategori '<strong>" . htmlspecialchars($nama_kategori) . "</strong>' berhasil ditambahkan!";
            } catch (PDOException $e) {
                $pesan_error = 'Gagal menambah kategori: ' . $e->getMessage();
            }
        }
    }
}

// Filter & data
$kategori_filter = (int)($_GET['kategori'] ?? 0);
$status_filter = $_GET['status'] ?? 'semua';
$search = trim($_GET['q'] ?? '');

$kategori_list = $pdo->query("SELECT * FROM kategori ORDER BY nama_kategori ASC")->fetchAll();

$sql = "
    SELECT pr.*, k.nama_kategori
    FROM produk pr
    JOIN kategori k ON pr.id_kategori = k.id
    WHERE 1=1
";
$params = [];

if ($kategori_filter > 0) {
    $sql .= " AND pr.id_kategori = :kategori";
    $params['kategori'] = $kategori_filter;
}

if ($status_filter === 'aktif') {
    $sql .= " AND pr.aktif = 1";
} elseif ($status_filter === 'nonaktif') {
    $sql .= " AND pr.aktif = 0";
}

if ($search !== '') {
    $sql .= " AND pr.nama LIKE :search";
    $params['search'] = '%' . $search . '%';
}

$sql .= " ORDER BY pr.id DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$produk_list = $stmt->fetchAll();

$halaman = 'kelola_menu';
$judul = 'Kelola Menu Restoran';
require_once __DIR__ . '/includes/header.php';
?>

<div class="page-header" style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:12px;">
    <div>
        <h1 class="page-title">🍽️ Kelola Menu & Produk</h1>
        <p class="text-muted">Tambah, ubah harga, sesuaikan stok, dan kategorisasi menu makanan & minuman</p>
    </div>
    <div style="display:flex; gap:8px;">
        <button onclick="toggleModal('modalTambahMenu')" class="btn btn-primary">
            + Tambah Menu Baru
        </button>
        <button onclick="toggleModal('modalTambahKategori')" class="btn btn-secondary">
            + Kategori Baru
        </button>
    </div>
</div>

<?php if ($pesan_sukses): ?>
    <div class="alert alert-success"><?= $pesan_sukses ?></div>
<?php endif; ?>

<?php if ($pesan_error): ?>
    <div class="alert alert-danger"><?= $pesan_error ?></div>
<?php endif; ?>

<!-- Filter & Search Toolbar -->
<div class="card" style="margin-bottom: 20px; padding: 16px;">
    <form method="GET" action="kelola_menu.php" style="display:flex; gap:12px; flex-wrap:wrap; align-items:center;">
        <div style="flex:1; min-width: 220px;">
            <input type="text" name="q" value="<?= htmlspecialchars($search) ?>" placeholder="🔍 Cari nama menu..." class="form-control">
        </div>
        <div style="width: 180px;">
            <select name="kategori" class="form-control" onchange="this.form.submit()">
                <option value="0">-- Semua Kategori --</option>
                <?php foreach ($kategori_list as $k): ?>
                    <option value="<?= $k['id'] ?>" <?= $kategori_filter == $k['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($k['nama_kategori']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div style="width: 150px;">
            <select name="status" class="form-control" onchange="this.form.submit()">
                <option value="semua" <?= $status_filter === 'semua' ? 'selected' : '' ?>>Semua Status</option>
                <option value="aktif" <?= $status_filter === 'aktif' ? 'selected' : '' ?>>Aktif Saja</option>
                <option value="nonaktif" <?= $status_filter === 'nonaktif' ? 'selected' : '' ?>>Nonaktif Saja</option>
            </select>
        </div>
        <button type="submit" class="btn btn-primary">Filter</button>
        <?php if ($kategori_filter > 0 || $status_filter !== 'semua' || $search !== ''): ?>
            <a href="kelola_menu.php" class="btn btn-secondary">Reset</a>
        <?php endif; ?>
    </form>
</div>

<!-- Daftar Menu -->
<div class="card">
    <div class="table-wrapper">
        <table>
            <thead>
                <tr>
                    <th style="width: 60px;">ID</th>
                    <th>Nama Menu</th>
                    <th>Kategori</th>
                    <th>Harga</th>
                    <th style="width: 140px;">Stok</th>
                    <th>Status</th>
                    <th style="text-align: right; width: 180px;">Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($produk_list)): ?>
                    <tr>
                        <td colspan="7" class="text-center" style="padding: 24px; color: #64748b;">
                            Belum ada menu yang terdaftar atau sesuai kriteria.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($produk_list as $p): ?>
                    <tr>
                        <td>#<?= $p['id'] ?></td>
                        <td>
                            <strong><?= htmlspecialchars($p['nama']) ?></strong>
                        </td>
                        <td>
                            <span class="badge badge-info"><?= htmlspecialchars($p['nama_kategori']) ?></span>
                        </td>
                        <td style="font-weight: 600; color: #047857;">
                            Rp <?= number_format($p['harga'], 0, ',', '.') ?>
                        </td>
                        <td>
                            <div style="display:flex; align-items:center; gap:6px;">
                                <strong style="min-width: 32px;"><?= $p['stok'] ?></strong>
                                <form method="POST" action="kelola_menu.php" style="display:inline; margin:0;">
                                    <input type="hidden" name="aksi" value="update_stok">
                                    <input type="hidden" name="id" value="<?= $p['id'] ?>">
                                    <input type="hidden" name="tambah_stok" value="5">
                                    <button type="submit" class="btn btn-sm btn-secondary" title="Tambah 5 porsi" style="padding: 2px 6px; font-size:11px;">+5</button>
                                </form>
                                <form method="POST" action="kelola_menu.php" style="display:inline; margin:0;">
                                    <input type="hidden" name="aksi" value="update_stok">
                                    <input type="hidden" name="id" value="<?= $p['id'] ?>">
                                    <input type="hidden" name="tambah_stok" value="-1">
                                    <button type="submit" class="btn btn-sm btn-secondary" title="Kurangi 1 porsi" style="padding: 2px 6px; font-size:11px;">-1</button>
                                </form>
                            </div>
                        </td>
                        <td>
                            <?php if ($p['aktif']): ?>
                                <span class="badge badge-success">Aktif</span>
                            <?php else: ?>
                                <span class="badge badge-danger">Nonaktif</span>
                            <?php endif; ?>
                        </td>
                        <td style="text-align: right;">
                            <button type="button" 
                                    class="btn btn-sm btn-secondary"
                                    onclick='bukaModalEdit(<?= json_encode($p) ?>)'>
                                ✏️ Edit
                            </button>
                            <form method="POST" action="kelola_menu.php" style="display:inline;" onsubmit="return confirm('Hapus / nonaktifkan menu ini?')">
                                <input type="hidden" name="aksi" value="hapus">
                                <input type="hidden" name="id" value="<?= $p['id'] ?>">
                                <button type="submit" class="btn btn-sm btn-danger">🗑️</button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal Tambah Menu -->
<div id="modalTambahMenu" style="display:none; position:fixed; top:0; left:0; right:0; bottom:0; background:rgba(0,0,0,0.5); z-index:9999; align-items:center; justify-content:center;">
    <div class="card" style="width: 100%; max-width: 480px; margin: 20px;">
        <h3 class="card-title">Tambah Menu Makanan/Minuman Baru</h3>
        <form method="POST" action="kelola_menu.php">
            <input type="hidden" name="aksi" value="tambah">
            
            <div class="form-group" style="margin-bottom: 12px;">
                <label>Nama Menu *</label>
                <input type="text" name="nama" class="form-control" placeholder="Contoh: Ayam Bakar Madu" required>
            </div>

            <div class="form-group" style="margin-bottom: 12px;">
                <label>Kategori *</label>
                <select name="id_kategori" class="form-control" required>
                    <option value="">-- Pilih Kategori --</option>
                    <?php foreach ($kategori_list as $k): ?>
                        <option value="<?= $k['id'] ?>"><?= htmlspecialchars($k['nama_kategori']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div style="display:grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 12px;">
                <div class="form-group">
                    <label>Harga (Rp) *</label>
                    <input type="number" name="harga" class="form-control" placeholder="25000" min="0" required>
                </div>
                <div class="form-group">
                    <label>Stok Awal</label>
                    <input type="number" name="stok" class="form-control" value="20" min="0">
                </div>
            </div>

            <div class="form-group" style="margin-bottom: 16px;">
                <label>URL Gambar (Opsional)</label>
                <input type="text" name="gambar" class="form-control" placeholder="https://... atau assets/img/...">
            </div>

            <div style="display:flex; justify-content:flex-end; gap:8px;">
                <button type="button" class="btn btn-secondary" onclick="toggleModal('modalTambahMenu')">Batal</button>
                <button type="submit" class="btn btn-primary">Simpan Menu</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal Edit Menu -->
<div id="modalEditMenu" style="display:none; position:fixed; top:0; left:0; right:0; bottom:0; background:rgba(0,0,0,0.5); z-index:9999; align-items:center; justify-content:center;">
    <div class="card" style="width: 100%; max-width: 480px; margin: 20px;">
        <h3 class="card-title">Edit Data Menu</h3>
        <form method="POST" action="kelola_menu.php">
            <input type="hidden" name="aksi" value="edit">
            <input type="hidden" name="id" id="edit_id">
            
            <div class="form-group" style="margin-bottom: 12px;">
                <label>Nama Menu *</label>
                <input type="text" name="nama" id="edit_nama" class="form-control" required>
            </div>

            <div class="form-group" style="margin-bottom: 12px;">
                <label>Kategori *</label>
                <select name="id_kategori" id="edit_kategori" class="form-control" required>
                    <?php foreach ($kategori_list as $k): ?>
                        <option value="<?= $k['id'] ?>"><?= htmlspecialchars($k['nama_kategori']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div style="display:grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 12px;">
                <div class="form-group">
                    <label>Harga (Rp) *</label>
                    <input type="number" name="harga" id="edit_harga" class="form-control" min="0" required>
                </div>
                <div class="form-group">
                    <label>Stok</label>
                    <input type="number" name="stok" id="edit_stok" class="form-control" min="0">
                </div>
            </div>

            <div class="form-group" style="margin-bottom: 12px;">
                <label>URL Gambar</label>
                <input type="text" name="gambar" id="edit_gambar" class="form-control">
            </div>

            <div class="form-group" style="margin-bottom: 16px;">
                <label style="display:flex; align-items:center; gap:8px; cursor:pointer;">
                    <input type="checkbox" name="aktif" id="edit_aktif" value="1">
                    <span>Menu Aktif (Dapat dipesan tamu & kasir)</span>
                </label>
            </div>

            <div style="display:flex; justify-content:flex-end; gap:8px;">
                <button type="button" class="btn btn-secondary" onclick="toggleModal('modalEditMenu')">Batal</button>
                <button type="submit" class="btn btn-primary">Simpan Perubahan</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal Tambah Kategori -->
<div id="modalTambahKategori" style="display:none; position:fixed; top:0; left:0; right:0; bottom:0; background:rgba(0,0,0,0.5); z-index:9999; align-items:center; justify-content:center;">
    <div class="card" style="width: 100%; max-width: 400px; margin: 20px;">
        <h3 class="card-title">Tambah Kategori Menu Baru</h3>
        <form method="POST" action="kelola_menu.php">
            <input type="hidden" name="aksi" value="tambah_kategori">
            <div class="form-group" style="margin-bottom: 16px;">
                <label>Nama Kategori *</label>
                <input type="text" name="nama_kategori" class="form-control" placeholder="Contoh: Aneka Jus / Camilan" required>
            </div>
            <div style="display:flex; justify-content:flex-end; gap:8px;">
                <button type="button" class="btn btn-secondary" onclick="toggleModal('modalTambahKategori')">Batal</button>
                <button type="submit" class="btn btn-primary">Simpan Kategori</button>
            </div>
        </form>
    </div>
</div>

<script>
function toggleModal(modalId) {
    const el = document.getElementById(modalId);
    if (!el) return;
    el.style.display = (el.style.display === 'none' || el.style.display === '') ? 'flex' : 'none';
}

function bukaModalEdit(item) {
    document.getElementById('edit_id').value = item.id;
    document.getElementById('edit_nama').value = item.nama;
    document.getElementById('edit_kategori').value = item.id_kategori;
    document.getElementById('edit_harga').value = parseInt(item.harga);
    document.getElementById('edit_stok').value = item.stok;
    document.getElementById('edit_gambar').value = item.gambar || '';
    document.getElementById('edit_aktif').checked = (item.aktif == 1);
    toggleModal('modalEditMenu');
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
