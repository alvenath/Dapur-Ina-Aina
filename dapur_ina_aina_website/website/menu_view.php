<?php
/**
 * Halaman: Lihat Menu (Pelayan & Staff)
 * Sesuai Use Case: Pelayan - Melihat daftar menu & stok
 * Sesuai Struktur Navigasi: Menu Pelayan → Lihat Menu
 */
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';

cek_role(['admin', 'kasir']);
$currentUser = user();

$pdo = get_koneksi();
$error = '';
$kategori_filter = isset($_GET['kategori']) ? (int)$_GET['kategori'] : 0;
$search = trim($_GET['q'] ?? '');

try {
    // Ambil semua kategori untuk filter
    $stmtKat = $pdo->query("SELECT * FROM kategori ORDER BY nama_kategori ASC");
    $kategori_list = $stmtKat->fetchAll();

    // Query produk aktif
    $sql = "
        SELECT pr.*, k.nama_kategori
        FROM produk pr
        JOIN kategori k ON pr.id_kategori = k.id
        WHERE pr.aktif = 1
    ";
    $params = [];

    if ($kategori_filter > 0) {
        $sql .= " AND pr.id_kategori = :kategori";
        $params['kategori'] = $kategori_filter;
    }

    if ($search !== '') {
        $sql .= " AND pr.nama LIKE :search";
        $params['search'] = '%' . $search . '%';
    }

    $sql .= " ORDER BY k.nama_kategori ASC, pr.nama ASC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $menu_list = $stmt->fetchAll();

} catch (PDOException $e) {
    $error = "Gagal memuat menu: " . $e->getMessage();
}

$halaman = 'menu_view';
$judul = 'Daftar Menu Restoran';
require_once __DIR__ . '/includes/header.php';
?>

<div class="page-header">
    <div>
        <h1 class="page-title">📖 Katalog Menu Nusantara</h1>
        <p class="text-muted">Koleksi masakan dan minuman autentik Dapur Ina Aina serta status stok terkini</p>
    </div>
    <div style="display:flex; gap:10px; flex-wrap:wrap;">
        <a href="catat_pesanan.php" class="btn btn-primary">
            📝 Catat Pesanan Baru
        </a>
        <a href="cek_stok.php" class="btn btn-secondary">
            📦 Cek Status Stok
        </a>
    </div>
</div>

<?php if ($error): ?>
    <div class="alert alert-danger">⚠️ <?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<!-- Filter & Search Bar -->
<div class="card" style="margin-bottom: 26px; padding: 20px 24px;">
    <form method="GET" action="menu_view.php" style="display:flex; gap:14px; flex-wrap:wrap; align-items:center;">
        <div style="flex: 1; min-width: 240px;">
            <input type="text" name="q" value="<?= htmlspecialchars($search) ?>" placeholder="🔍 Cari menu nusantara..." class="form-control">
        </div>
        <div style="width: 220px;">
            <select name="kategori" class="form-control" onchange="this.form.submit()">
                <option value="0">-- Semua Kategori --</option>
                <?php foreach ($kategori_list as $k): ?>
                    <option value="<?= $k['id'] ?>" <?= $kategori_filter == $k['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($k['nama_kategori']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div style="display:flex; gap:8px;">
            <button type="submit" class="btn btn-primary">Filter</button>
            <?php if ($kategori_filter > 0 || $search !== ''): ?>
                <a href="menu_view.php" class="btn btn-secondary">Reset</a>
            <?php endif; ?>
        </div>
    </form>
</div>

<!-- Menu Grid -->
<?php if (empty($menu_list)): ?>
    <div class="card">
        <div class="empty-state">
            <div class="empty-icon">🍽️</div>
            <p>Tidak ada menu yang sesuai dengan kata kunci pencarian Anda.</p>
        </div>
    </div>
<?php else: ?>
    <div class="menu-grid">
        <?php foreach ($menu_list as $m): 
            $isHabis = ($m['stok'] <= 0);
            $isMenipis = ($m['stok'] > 0 && $m['stok'] <= 5);
        ?>
        <div class="food-card <?= $isHabis ? 'out-of-stock' : '' ?>" style="<?= $isHabis ? 'opacity: 0.75;' : '' ?>">
            <div class="food-card-thumb">
                <?php if ($m['gambar']): ?>
                    <img src="<?= htmlspecialchars($m['gambar']) ?>" alt="<?= htmlspecialchars($m['nama']) ?>">
                <?php else: ?>
                    <div style="width:100%; height:100%; background: linear-gradient(135deg, #ecfdf5, #a7f3d0); display:flex; align-items:center; justify-content:center; font-size:48px;">
                        🍲
                    </div>
                <?php endif; ?>
                <div style="position: absolute; top: 12px; left: 12px;">
                    <span class="badge" style="background: rgba(15, 23, 42, 0.75); color: #fff; backdrop-filter: blur(6px); border: 1px solid rgba(255,255,255,0.2);">
                        <?= htmlspecialchars($m['nama_kategori']) ?>
                    </span>
                </div>
            </div>

            <div class="food-card-body">
                <div style="display:flex; justify-content:space-between; align-items:flex-start; margin-bottom: 8px;">
                    <h3 class="food-card-name"><?= htmlspecialchars($m['nama']) ?></h3>
                </div>

                <div style="margin-bottom: 14px;">
                    <?php if ($isHabis): ?>
                        <span class="badge badge-danger">Habis</span>
                    <?php elseif ($isMenipis): ?>
                        <span class="badge badge-warning">Sisa <?= $m['stok'] ?> porsi</span>
                    <?php else: ?>
                        <span class="badge badge-success">Tersedia (<?= $m['stok'] ?>)</span>
                    <?php endif; ?>
                </div>

                <div style="margin-top:auto; padding-top: 14px; display:flex; justify-content:space-between; align-items:center; border-top: 1px solid var(--border-subtle);">
                    <div class="food-card-price">
                        Rp <?= number_format($m['harga'], 0, ',', '.') ?>
                    </div>
                    <?php if (!$isHabis): ?>
                        <a href="catat_pesanan.php?tambah_id=<?= $m['id'] ?>" class="btn btn-sm btn-primary">
                            + Pesan
                        </a>
                    <?php else: ?>
                        <button class="btn btn-sm btn-secondary" disabled style="opacity: 0.6; cursor: not-allowed;">Habis</button>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
