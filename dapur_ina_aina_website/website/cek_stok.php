<?php
/**
 * Halaman: Cek Status Stok Produk (Pelayan & Staff)
 * Sesuai Use Case: Pelayan - Cek Status Stok
 * Sesuai Struktur Navigasi: Menu Pelayan → Cek Status Stok
 */
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';

cek_role(['admin', 'kasir']);
$currentUser = user();
$pdo = get_koneksi();

$filter = $_GET['filter'] ?? 'semua';
$search = trim($_GET['q'] ?? '');

$sql = "
    SELECT pr.*, k.nama_kategori
    FROM produk pr
    JOIN kategori k ON pr.id_kategori = k.id
    WHERE pr.aktif = 1
";
$params = [];

if ($filter === 'habis') {
    $sql .= " AND pr.stok <= 0";
} elseif ($filter === 'menipis') {
    $sql .= " AND pr.stok > 0 AND pr.stok <= 5";
} elseif ($filter === 'aman') {
    $sql .= " AND pr.stok > 5";
}

if ($search !== '') {
    $sql .= " AND pr.nama LIKE :search";
    $params['search'] = '%' . $search . '%';
}

$sql .= " ORDER BY pr.stok ASC, pr.nama ASC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$items = $stmt->fetchAll();

// Hitung total statistik stok
$stat_habis = $pdo->query("SELECT COUNT(*) FROM produk WHERE aktif = 1 AND stok <= 0")->fetchColumn();
$stat_menipis = $pdo->query("SELECT COUNT(*) FROM produk WHERE aktif = 1 AND stok > 0 AND stok <= 5")->fetchColumn();
$stat_total = $pdo->query("SELECT COUNT(*) FROM produk WHERE aktif = 1")->fetchColumn();

$halaman = 'cek_stok';
$judul = 'Status Stok Menu & Ketersediaan';
require_once __DIR__ . '/includes/header.php';
?>

<div class="page-header" style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:12px;">
    <div>
        <h1 class="page-title">📦 Cek Status Stok Produk</h1>
        <p class="text-muted">Pantau ketersediaan porsi makanan dan minuman secara real-time sebelum melayani tamu</p>
    </div>
    <div>
        <a href="catat_pesanan.php" class="btn btn-primary">
            📝 Buat Pesanan Baru
        </a>
    </div>
</div>

<!-- Info Cards Summary -->
<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 16px; margin-bottom: 24px;">
    <a href="cek_stok.php?filter=semua" style="text-decoration:none;">
        <div class="card" style="padding: 16px; border-left: 4px solid #3b82f6; <?= $filter === 'semua' ? 'background:#eff6ff;' : '' ?>">
            <div style="font-size: 12px; color: #64748b; font-weight:600; text-transform:uppercase;">Total Menu Aktif</div>
            <div style="font-size: 24px; font-weight: 800; color: #1e293b;"><?= $stat_total ?> Item</div>
        </div>
    </a>
    <a href="cek_stok.php?filter=menipis" style="text-decoration:none;">
        <div class="card" style="padding: 16px; border-left: 4px solid #f59e0b; <?= $filter === 'menipis' ? 'background:#fffbeb;' : '' ?>">
            <div style="font-size: 12px; color: #d97706; font-weight:600; text-transform:uppercase;">Stok Menipis (≤ 5)</div>
            <div style="font-size: 24px; font-weight: 800; color: #b45309;"><?= $stat_menipis ?> Menu</div>
        </div>
    </a>
    <a href="cek_stok.php?filter=habis" style="text-decoration:none;">
        <div class="card" style="padding: 16px; border-left: 4px solid #ef4444; <?= $filter === 'habis' ? 'background:#fef2f2;' : '' ?>">
            <div style="font-size: 12px; color: #dc2626; font-weight:600; text-transform:uppercase;">Stok Habis (0)</div>
            <div style="font-size: 24px; font-weight: 800; color: #b91c1c;"><?= $stat_habis ?> Menu</div>
        </div>
    </a>
</div>

<!-- Search & Filter Controls -->
<div class="card" style="margin-bottom: 20px; padding: 16px;">
    <form method="GET" action="cek_stok.php" style="display:flex; gap:12px; flex-wrap:wrap; align-items:center;">
        <input type="hidden" name="filter" value="<?= htmlspecialchars($filter) ?>">
        <div style="flex:1; min-width:240px;">
            <input type="text" name="q" value="<?= htmlspecialchars($search) ?>" placeholder="🔍 Cari menu..." class="form-control">
        </div>
        <button type="submit" class="btn btn-primary">Filter</button>
        <?php if ($search !== '' || $filter !== 'semua'): ?>
            <a href="cek_stok.php" class="btn btn-secondary">Reset</a>
        <?php endif; ?>
    </form>
</div>

<!-- Table of Stock -->
<div class="card">
    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th>Menu</th>
                    <th>Kategori</th>
                    <th>Harga Satuan</th>
                    <th>Sisa Stok</th>
                    <th>Status</th>
                    <th>Aksi Pelayanan</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($items)): ?>
                    <tr>
                        <td colspan="6" class="text-center" style="padding: 24px; color: #64748b;">
                            Tidak ada data produk yang sesuai.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($items as $item): 
                        $isHabis = ($item['stok'] <= 0);
                        $isMenipis = ($item['stok'] > 0 && $item['stok'] <= 5);
                    ?>
                    <tr style="<?= $isHabis ? 'background: #fef2f2;' : ($isMenipis ? 'background: #fffbeb;' : '') ?>">
                        <td><strong><?= htmlspecialchars($item['nama']) ?></strong></td>
                        <td><span class="badge badge-info"><?= htmlspecialchars($item['nama_kategori']) ?></span></td>
                        <td>Rp <?= number_format($item['harga'], 0, ',', '.') ?></td>
                        <td>
                            <strong style="font-size: 15px;"><?= $item['stok'] ?> porsi</strong>
                        </td>
                        <td>
                            <?php if ($isHabis): ?>
                                <span class="badge badge-danger">❌ Habis (Jangan Dijual)</span>
                            <?php elseif ($isMenipis): ?>
                                <span class="badge badge-warning">⚠️ Segera Habis (Sisa <?= $item['stok'] ?>)</span>
                            <?php else: ?>
                                <span class="badge badge-success">✅ Tersedia</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if (!$isHabis): ?>
                                <a href="catat_pesanan.php?tambah_id=<?= $item['id'] ?>" class="btn btn-sm btn-primary">
                                    + Catat Pesanan
                                </a>
                            <?php else: ?>
                                <span class="text-muted" style="font-size: 12px;">Informasikan ke Tamu</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
