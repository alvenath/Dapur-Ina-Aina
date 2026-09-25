<?php
/**
 * Halaman: Kelola Meja (Admin & Owner)
 * Sesuai Use Case: Admin - Kelola Meja
 * Sesuai Struktur Navigasi: Menu Admin → Kelola Meja
 */
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';

cek_role(['admin']);
$currentUser = user();
$pdo = get_koneksi();

$pesan_sukses = '';
$pesan_error = '';

// Proses CRUD Meja
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $aksi = $_POST['aksi'] ?? '';

    if ($aksi === 'tambah') {
        $nomor_meja = (int)($_POST['nomor_meja'] ?? 0);
        $kapasitas = (int)($_POST['kapasitas'] ?? 4);

        if ($nomor_meja <= 0 || $kapasitas <= 0) {
            $pesan_error = 'Nomor meja dan kapasitas harus lebih dari 0.';
        } else {
            try {
                // Cek nomor meja unik
                $cek = $pdo->prepare("SELECT COUNT(*) FROM meja WHERE nomor_meja = :no");
                $cek->execute(['no' => $nomor_meja]);
                if ($cek->fetchColumn() > 0) {
                    $pesan_error = "Meja dengan nomor {$nomor_meja} sudah ada.";
                } else {
                    $stmt = $pdo->prepare("INSERT INTO meja (nomor_meja, kapasitas, status) VALUES (:no, :kap, 'kosong')");
                    $stmt->execute(['no' => $nomor_meja, 'kap' => $kapasitas]);
                    $pesan_sukses = "Meja {$nomor_meja} berhasil ditambahkan.";
                }
            } catch (PDOException $e) {
                $pesan_error = 'Gagal menambah meja: ' . $e->getMessage();
            }
        }
    } elseif ($aksi === 'edit') {
        $id = (int)($_POST['id'] ?? 0);
        $nomor_meja = (int)($_POST['nomor_meja'] ?? 0);
        $kapasitas = (int)($_POST['kapasitas'] ?? 4);
        $status = $_POST['status'] ?? 'kosong';

        if ($id <= 0 || $nomor_meja <= 0 || !in_array($status, ['kosong', 'terisi', 'dipesan'])) {
            $pesan_error = 'Data meja tidak valid.';
        } else {
            try {
                $stmt = $pdo->prepare("
                    UPDATE meja
                    SET nomor_meja = :no, kapasitas = :kap, status = :status
                    WHERE id = :id
                ");
                $stmt->execute([
                    'no' => $nomor_meja,
                    'kap' => $kapasitas,
                    'status' => $status,
                    'id' => $id
                ]);
                $pesan_sukses = "Data Meja {$nomor_meja} berhasil diperbarui.";
            } catch (PDOException $e) {
                $pesan_error = 'Gagal mengupdate meja: ' . $e->getMessage();
            }
        }
    } elseif ($aksi === 'set_status') {
        $id = (int)($_POST['id'] ?? 0);
        $status = $_POST['status'] ?? 'kosong';
        if ($id > 0 && in_array($status, ['kosong', 'terisi', 'dipesan'])) {
            try {
                $stmt = $pdo->prepare("UPDATE meja SET status = :status WHERE id = :id");
                $stmt->execute(['status' => $status, 'id' => $id]);
                $pesan_sukses = "Status meja berhasil diubah menjadi " . ucfirst($status) . ".";
            } catch (PDOException $e) {
                $pesan_error = 'Gagal mengubah status: ' . $e->getMessage();
            }
        }
    } elseif ($aksi === 'hapus') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            try {
                $stmt = $pdo->prepare("DELETE FROM meja WHERE id = :id");
                $stmt->execute(['id' => $id]);
                $pesan_sukses = "Meja berhasil dihapus.";
            } catch (PDOException $e) {
                $pesan_error = 'Gagal menghapus meja: ' . $e->getMessage();
            }
        }
    }
}

// Ambil data semua meja
$daftar_meja = $pdo->query("SELECT * FROM meja ORDER BY nomor_meja ASC")->fetchAll();

$halaman = 'kelola_meja';
$judul = 'Kelola Meja Restoran';
require_once __DIR__ . '/includes/header.php';
?>

<div class="page-header" style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:12px;">
    <div>
        <h1 class="page-title">🪑 Kelola Meja Makan</h1>
        <p class="text-muted">Pengaturan nomor meja, kapasitas duduk, dan pemantauan okupansi meja</p>
    </div>
    <div>
        <button onclick="toggleModal('modalTambahMeja')" class="btn btn-primary">
            + Tambah Meja Baru
        </button>
    </div>
</div>

<?php if ($pesan_sukses): ?>
    <div class="alert alert-success"><?= $pesan_sukses ?></div>
<?php endif; ?>

<?php if ($pesan_error): ?>
    <div class="alert alert-danger"><?= $pesan_error ?></div>
<?php endif; ?>

<!-- Grid Visualisasi Meja -->
<div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(220px, 1fr)); gap: 16px; margin-bottom: 28px;">
    <?php foreach ($daftar_meja as $m): 
        $badgeBg = '#dcfce7'; $textColor = '#166534';
        if ($m['status'] === 'terisi') { $badgeBg = '#fee2e2'; $textColor = '#991b1b'; }
        elseif ($m['status'] === 'dipesan') { $badgeBg = '#fef3c7'; $textColor = '#92400e'; }
    ?>
    <div class="card" style="padding: 16px; border: 2px solid <?= $m['status'] === 'terisi' ? '#ef4444' : ($m['status'] === 'dipesan' ? '#f59e0b' : '#10b981') ?>; text-align: center;">
        <div style="font-size: 32px; margin-bottom: 4px;">🪑</div>
        <h3 style="margin: 0; font-size: 18px; color: #1e293b;">Meja <?= htmlspecialchars($m['nomor_meja']) ?></h3>
        <p class="text-muted" style="margin: 4px 0 10px 0; font-size: 12px;">Kapasitas: <?= $m['kapasitas'] ?> Orang</p>
        
        <div style="margin-bottom: 12px;">
            <span class="badge" style="background: <?= $badgeBg ?>; color: <?= $textColor ?>; font-size: 12px; padding: 4px 10px;">
                <?= strtoupper($m['status']) ?>
            </span>
        </div>

        <div style="display:flex; justify-content:center; gap: 6px; flex-wrap: wrap;">
            <?php if ($m['status'] !== 'kosong'): ?>
            <form method="POST" action="kelola_meja.php" style="display:inline; margin:0;">
                <input type="hidden" name="aksi" value="set_status">
                <input type="hidden" name="id" value="<?= $m['id'] ?>">
                <input type="hidden" name="status" value="kosong">
                <button type="submit" class="btn btn-sm btn-success" style="font-size:11px; padding: 3px 8px;">Kosongkan</button>
            </form>
            <?php else: ?>
            <form method="POST" action="kelola_meja.php" style="display:inline; margin:0;">
                <input type="hidden" name="aksi" value="set_status">
                <input type="hidden" name="id" value="<?= $m['id'] ?>">
                <input type="hidden" name="status" value="terisi">
                <button type="submit" class="btn btn-sm btn-secondary" style="font-size:11px; padding: 3px 8px;">Set Terisi</button>
            </form>
            <?php endif; ?>

            <button type="button" class="btn btn-sm btn-secondary" style="font-size:11px; padding: 3px 8px;" onclick='bukaModalEditMeja(<?= json_encode($m) ?>)'>
                ✏️ Edit
            </button>
            
            <form method="POST" action="kelola_meja.php" style="display:inline; margin:0;" onsubmit="return confirm('Hapus meja ini?')">
                <input type="hidden" name="aksi" value="hapus">
                <input type="hidden" name="id" value="<?= $m['id'] ?>">
                <button type="submit" class="btn btn-sm btn-danger" style="font-size:11px; padding: 3px 8px;">🗑️</button>
            </form>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<!-- Modal Tambah Meja -->
<div id="modalTambahMeja" style="display:none; position:fixed; top:0; left:0; right:0; bottom:0; background:rgba(0,0,0,0.5); z-index:9999; align-items:center; justify-content:center;">
    <div class="card" style="width: 100%; max-width: 400px; margin: 20px;">
        <h3 class="card-title">Tambah Meja Restoran</h3>
        <form method="POST" action="kelola_meja.php">
            <input type="hidden" name="aksi" value="tambah">
            
            <div class="form-group" style="margin-bottom: 12px;">
                <label>Nomor Meja *</label>
                <input type="number" name="nomor_meja" class="form-control" placeholder="Contoh: 1, 2, 3..." min="1" required>
            </div>

            <div class="form-group" style="margin-bottom: 16px;">
                <label>Kapasitas Kursi (Orang) *</label>
                <input type="number" name="kapasitas" class="form-control" value="4" min="1" required>
            </div>

            <div style="display:flex; justify-content:flex-end; gap:8px;">
                <button type="button" class="btn btn-secondary" onclick="toggleModal('modalTambahMeja')">Batal</button>
                <button type="submit" class="btn btn-primary">Simpan Meja</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal Edit Meja -->
<div id="modalEditMeja" style="display:none; position:fixed; top:0; left:0; right:0; bottom:0; background:rgba(0,0,0,0.5); z-index:9999; align-items:center; justify-content:center;">
    <div class="card" style="width: 100%; max-width: 400px; margin: 20px;">
        <h3 class="card-title">Edit Data Meja</h3>
        <form method="POST" action="kelola_meja.php">
            <input type="hidden" name="aksi" value="edit">
            <input type="hidden" name="id" id="edit_meja_id">
            
            <div class="form-group" style="margin-bottom: 12px;">
                <label>Nomor Meja *</label>
                <input type="number" name="nomor_meja" id="edit_meja_nomor" class="form-control" min="1" required>
            </div>

            <div class="form-group" style="margin-bottom: 12px;">
                <label>Kapasitas Kursi *</label>
                <input type="number" name="kapasitas" id="edit_meja_kapasitas" class="form-control" min="1" required>
            </div>

            <div class="form-group" style="margin-bottom: 16px;">
                <label>Status Meja *</label>
                <select name="status" id="edit_meja_status" class="form-control" required>
                    <option value="kosong">Kosong (Tersedia)</option>
                    <option value="terisi">Terisi (Ada Tamu)</option>
                    <option value="dipesan">Dipesan (Reserved)</option>
                </select>
            </div>

            <div style="display:flex; justify-content:flex-end; gap:8px;">
                <button type="button" class="btn btn-secondary" onclick="toggleModal('modalEditMeja')">Batal</button>
                <button type="submit" class="btn btn-primary">Simpan Perubahan</button>
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

function bukaModalEditMeja(item) {
    document.getElementById('edit_meja_id').value = item.id;
    document.getElementById('edit_meja_nomor').value = item.nomor_meja;
    document.getElementById('edit_meja_kapasitas').value = item.kapasitas;
    document.getElementById('edit_meja_status').value = item.status;
    toggleModal('modalEditMeja');
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
