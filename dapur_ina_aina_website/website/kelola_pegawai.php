<?php
/**
 * Halaman: Kelola Pegawai / Pengguna (Admin & Owner)
 * Sesuai Use Case: Admin - Kelola Pegawai
 * Sesuai Struktur Navigasi: Menu Admin → Kelola Pegawai
 */
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';

cek_role(['admin']);
$currentUser = user();
$pdo = get_koneksi();

$pesan_sukses = '';
$pesan_error = '';

// Proses CRUD Pengguna/Pegawai
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $aksi = $_POST['aksi'] ?? '';

    if ($aksi === 'tambah') {
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        $nama_lengkap = trim($_POST['nama_lengkap'] ?? '');
        $role = $_POST['role'] ?? 'kasir';

        $valid_roles = ['admin', 'kasir'];

        if (empty($username) || empty($password) || empty($nama_lengkap) || !in_array($role, $valid_roles)) {
            $pesan_error = 'Semua data wajib diisi dengan benar.';
        } else {
            try {
                // Cek username unik
                $cek = $pdo->prepare("SELECT COUNT(*) FROM pengguna WHERE username = :username");
                $cek->execute(['username' => $username]);
                if ($cek->fetchColumn() > 0) {
                    $pesan_error = "Username '{$username}' sudah digunakan.";
                } else {
                    $hashed = password_hash($password, PASSWORD_DEFAULT);
                    $stmt = $pdo->prepare("
                        INSERT INTO pengguna (username, password, nama_lengkap, role, aktif)
                        VALUES (:username, :password, :nama_lengkap, :role, 1)
                    ");
                    $stmt->execute([
                        'username' => $username,
                        'password' => $hashed,
                        'nama_lengkap' => $nama_lengkap,
                        'role' => $role
                    ]);
                    $pesan_sukses = "Akun pegawai <strong>" . htmlspecialchars($nama_lengkap) . "</strong> ({$role}) berhasil dibuat!";
                }
            } catch (PDOException $e) {
                $pesan_error = 'Gagal membuat akun: ' . $e->getMessage();
            }
        }
    } elseif ($aksi === 'edit') {
        $id = (int)($_POST['id'] ?? 0);
        $nama_lengkap = trim($_POST['nama_lengkap'] ?? '');
        $role = $_POST['role'] ?? '';
        $password_baru = $_POST['password_baru'] ?? '';
        $aktif = isset($_POST['aktif']) ? 1 : 0;

        $valid_roles = ['admin', 'kasir'];

        if ($id <= 0 || empty($nama_lengkap) || !in_array($role, $valid_roles)) {
            $pesan_error = 'Data update pegawai tidak valid.';
        } else {
            try {
                if (!empty($password_baru)) {
                    $hashed = password_hash($password_baru, PASSWORD_DEFAULT);
                    $stmt = $pdo->prepare("
                        UPDATE pengguna
                        SET nama_lengkap = :nama, role = :role, aktif = :aktif, password = :pass
                        WHERE id = :id
                    ");
                    $stmt->execute([
                        'nama' => $nama_lengkap,
                        'role' => $role,
                        'aktif' => $aktif,
                        'pass' => $hashed,
                        'id' => $id
                    ]);
                } else {
                    $stmt = $pdo->prepare("
                        UPDATE pengguna
                        SET nama_lengkap = :nama, role = :role, aktif = :aktif
                        WHERE id = :id
                    ");
                    $stmt->execute([
                        'nama' => $nama_lengkap,
                        'role' => $role,
                        'aktif' => $aktif,
                        'id' => $id
                    ]);
                }
                $pesan_sukses = "Data pegawai berhasil diperbarui!";
            } catch (PDOException $e) {
                $pesan_error = 'Gagal mengupdate pegawai: ' . $e->getMessage();
            }
        }
    } elseif ($aksi === 'hapus') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id === (int)$currentUser['id']) {
            $pesan_error = 'Anda tidak dapat menghapus akun Anda sendiri yang sedang aktif digunakan.';
        } elseif ($id > 0) {
            try {
                // Periksa apakah user memiliki riwayat pencatatan pesanan
                $cek = $pdo->prepare("SELECT COUNT(*) FROM pesanan WHERE id_pengguna = :id");
                $cek->execute(['id' => $id]);
                if ($cek->fetchColumn() > 0) {
                    // Nonaktifkan saja agar data integritas pesanan aman
                    $stmt = $pdo->prepare("UPDATE pengguna SET aktif = 0 WHERE id = :id");
                    $stmt->execute(['id' => $id]);
                    $pesan_sukses = "Akun memiliki riwayat transaksi, status diubah menjadi Nonaktif.";
                } else {
                    $stmt = $pdo->prepare("DELETE FROM pengguna WHERE id = :id");
                    $stmt->execute(['id' => $id]);
                    $pesan_sukses = "Akun pegawai berhasil dihapus.";
                }
            } catch (PDOException $e) {
                $pesan_error = 'Gagal menghapus akun: ' . $e->getMessage();
            }
        }
    }
}

// Ambil daftar pengguna
$role_filter = $_GET['role'] ?? 'semua';
$sql = "SELECT id, username, nama_lengkap, role, aktif, created_at FROM pengguna WHERE 1=1";
$params = [];

if ($role_filter !== 'semua') {
    $sql .= " AND role = :role";
    $params['role'] = $role_filter;
}
$sql .= " ORDER BY role ASC, nama_lengkap ASC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$daftar_pegawai = $stmt->fetchAll();

$halaman = 'kelola_pegawai';
$judul = 'Kelola Akun Pegawai & Peran';
require_once __DIR__ . '/includes/header.php';
?>

<div class="page-header" style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:12px;">
    <div>
        <h1 class="page-title">👥 Kelola Pegawai & Hak Akses</h1>
        <p class="text-muted">Manajemen akun staff Admin dan Kasir restoran</p>
    </div>
    <div>
        <button onclick="toggleModal('modalTambahPegawai')" class="btn btn-primary">
            + Tambah Pegawai Baru
        </button>
    </div>
</div>

<?php if ($pesan_sukses): ?>
    <div class="alert alert-success"><?= $pesan_sukses ?></div>
<?php endif; ?>

<?php if ($pesan_error): ?>
    <div class="alert alert-danger"><?= $pesan_error ?></div>
<?php endif; ?>

<!-- Filter Role Tabs -->
<div style="display:flex; gap:8px; margin-bottom: 20px; flex-wrap:wrap;">
    <?php
    $roles_tab = [
        'semua' => 'Semua Akun',
        'admin' => 'Admin',
        'kasir' => 'Kasir'
    ];
    foreach ($roles_tab as $k => $v):
        $isActive = ($role_filter === $k);
    ?>
    <a href="kelola_pegawai.php?role=<?= $k ?>" class="btn btn-sm <?= $isActive ? 'btn-primary' : 'btn-secondary' ?>">
        <?= $v ?>
    </a>
    <?php endforeach; ?>
</div>

<div class="card">
    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Nama Lengkap</th>
                    <th>Username</th>
                    <th>Peran (Role)</th>
                    <th>Status Akun</th>
                    <th>Tanggal Dibuat</th>
                    <th style="text-align: right;">Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($daftar_pegawai)): ?>
                    <tr>
                        <td colspan="7" class="text-center" style="padding: 24px; color: #64748b;">
                            Tidak ada akun pegawai yang ditemukan.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($daftar_pegawai as $pg): 
                        $badgeClass = 'badge-secondary';
                        if ($pg['role'] === 'admin') $badgeClass = 'badge-danger';
                        elseif ($pg['role'] === 'kasir') $badgeClass = 'badge-info';
                    ?>
                    <tr>
                        <td>#<?= $pg['id'] ?></td>
                        <td>
                            <strong><?= htmlspecialchars($pg['nama_lengkap']) ?></strong>
                            <?php if ($pg['id'] == $currentUser['id']): ?>
                                <span class="badge badge-warning" style="font-size:10px; margin-left:4px;">Anda</span>
                            <?php endif; ?>
                        </td>
                        <td><code><?= htmlspecialchars($pg['username']) ?></code></td>
                        <td>
                            <span class="badge <?= $badgeClass ?>"><?= strtoupper($pg['role']) ?></span>
                        </td>
                        <td>
                            <?php if ($pg['aktif']): ?>
                                <span class="badge badge-success">Aktif</span>
                            <?php else: ?>
                                <span class="badge badge-danger">Nonaktif</span>
                            <?php endif; ?>
                        </td>
                        <td><?= date('d/m/Y H:i', strtotime($pg['created_at'])) ?></td>
                        <td style="text-align: right;">
                            <button type="button" 
                                    class="btn btn-sm btn-secondary"
                                    onclick='bukaModalEditPegawai(<?= json_encode($pg) ?>)'>
                                ✏️ Edit
                            </button>
                            <?php if ($pg['id'] != $currentUser['id']): ?>
                            <form method="POST" action="kelola_pegawai.php" style="display:inline;" onsubmit="return confirm('Hapus atau nonaktifkan akun pegawai ini?')">
                                <input type="hidden" name="aksi" value="hapus">
                                <input type="hidden" name="id" value="<?= $pg['id'] ?>">
                                <button type="submit" class="btn btn-sm btn-danger">🗑️</button>
                            </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal Tambah Pegawai -->
<div id="modalTambahPegawai" style="display:none; position:fixed; top:0; left:0; right:0; bottom:0; background:rgba(0,0,0,0.5); z-index:9999; align-items:center; justify-content:center;">
    <div class="card" style="width: 100%; max-width: 460px; margin: 20px;">
        <h3 class="card-title">Tambah Pegawai / Akun Baru</h3>
        <form method="POST" action="kelola_pegawai.php">
            <input type="hidden" name="aksi" value="tambah">
            
            <div class="form-group" style="margin-bottom: 12px;">
                <label>Nama Lengkap *</label>
                <input type="text" name="nama_lengkap" class="form-control" placeholder="Contoh: Siti Aisyah" required>
            </div>

            <div class="form-group" style="margin-bottom: 12px;">
                <label>Username Login *</label>
                <input type="text" name="username" class="form-control" placeholder="Contoh: siti_kasir" required>
            </div>

            <div class="form-group" style="margin-bottom: 12px;">
                <label>Password Akun *</label>
                <input type="password" name="password" class="form-control" placeholder="Minimal 6 karakter" required>
            </div>

            <div class="form-group" style="margin-bottom: 16px;">
                <label>Peran / Role *</label>
                <select name="role" class="form-control" required>
                    <option value="kasir">Kasir</option>
                    <option value="admin">Admin</option>
                </select>
            </div>

            <div style="display:flex; justify-content:flex-end; gap:8px;">
                <button type="button" class="btn btn-secondary" onclick="toggleModal('modalTambahPegawai')">Batal</button>
                <button type="submit" class="btn btn-primary">Simpan Akun</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal Edit Pegawai -->
<div id="modalEditPegawai" style="display:none; position:fixed; top:0; left:0; right:0; bottom:0; background:rgba(0,0,0,0.5); z-index:9999; align-items:center; justify-content:center;">
    <div class="card" style="width: 100%; max-width: 460px; margin: 20px;">
        <h3 class="card-title">Edit Pegawai</h3>
        <form method="POST" action="kelola_pegawai.php">
            <input type="hidden" name="aksi" value="edit">
            <input type="hidden" name="id" id="edit_pg_id">
            
            <div class="form-group" style="margin-bottom: 12px;">
                <label>Username</label>
                <input type="text" id="edit_pg_username" class="form-control" readonly style="background:#f1f5f9;">
            </div>

            <div class="form-group" style="margin-bottom: 12px;">
                <label>Nama Lengkap *</label>
                <input type="text" name="nama_lengkap" id="edit_pg_nama" class="form-control" required>
            </div>

            <div class="form-group" style="margin-bottom: 12px;">
                <label>Peran / Role *</label>
                <select name="role" id="edit_pg_role" class="form-control" required>
                    <option value="kasir">Kasir</option>
                    <option value="admin">Admin</option>
                </select>
            </div>

            <div class="form-group" style="margin-bottom: 12px;">
                <label>Ganti Password (Kosongkan jika tidak diubah)</label>
                <input type="password" name="password_baru" class="form-control" placeholder="Password baru...">
            </div>

            <div class="form-group" style="margin-bottom: 16px;">
                <label style="display:flex; align-items:center; gap:8px; cursor:pointer;">
                    <input type="checkbox" name="aktif" id="edit_pg_aktif" value="1">
                    <span>Akun Aktif (Dapat login ke sistem)</span>
                </label>
            </div>

            <div style="display:flex; justify-content:flex-end; gap:8px;">
                <button type="button" class="btn btn-secondary" onclick="toggleModal('modalEditPegawai')">Batal</button>
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

function bukaModalEditPegawai(item) {
    document.getElementById('edit_pg_id').value = item.id;
    document.getElementById('edit_pg_username').value = item.username;
    document.getElementById('edit_pg_nama').value = item.nama_lengkap;
    document.getElementById('edit_pg_role').value = item.role;
    document.getElementById('edit_pg_aktif').checked = (item.aktif == 1);
    toggleModal('modalEditPegawai');
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
