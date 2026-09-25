<?php
/**
 * Halaman: Menu Pelanggan & Pemesanan Mandiri (Publik Tanpa Login)
 * Sesuai Use Case: Pelanggan - Melakukan pemesanan langsung dari meja
 * Dilengkapi animasi scroll reveal fade slide up, micro-interactions, dan foto makanan asli
 */
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';

$currentUser = user();
$pdo = get_koneksi();

$pesan_sukses = '';
$pesan_error = '';

// Deskripsi masakan untuk mempercantik kartu menu
$deskripsi_menu = [
    1 => 'Nasi goreng aromatik berpadu rempah nusantara pilihan, disajikan dengan telur dan acar segar.',
    2 => 'Ayam bakar dengan baluran madu murni dan bumbu rempah gurih manis yang meresap hingga ke tulang.',
    3 => 'Daging sapi empuk dimasak perlahan berjam-jam dalam santan kelapa dan rempah Minang autentik.',
    4 => 'Seduhan daun teh melati wangi dengan es kristal dingin dan manis yang pas penghilang dahaga.',
    5 => 'Alpukat mentega segar diblender lembut dan kental dengan sentuhan lelehan saus cokelat manis.',
    6 => 'Perasan jeruk peras alami segar dingin dengan bulir jeruk asli kaya vitamin C yang menyegarkan.',
];

// Rating & badge rekomendasi
$badge_menu = [
    1 => ['rating' => '4.9', 'terjual' => '1.2k+ terjual', 'tag' => '🔥 Terlaris', 'tag_color' => '#dc2626'],
    2 => ['rating' => '4.8', 'terjual' => '950+ terjual', 'tag' => '⭐ Favorit', 'tag_color' => '#d97706'],
    3 => ['rating' => '5.0', 'terjual' => '800+ terjual', 'tag' => '👑 Menu Utama', 'tag_color' => '#7c3aed'],
    4 => ['rating' => '4.8', 'terjual' => '2.5k+ terjual', 'tag' => '❄️ Segar', 'tag_color' => '#0284c7'],
    5 => ['rating' => '4.9', 'terjual' => '1.1k+ terjual', 'tag' => '🥑 Spesial', 'tag_color' => '#059669'],
    6 => ['rating' => '4.8', 'terjual' => '1.4k+ terjual', 'tag' => '🍊 Dingin', 'tag_color' => '#ea580c'],
];

// Proses Pemesanan Mandiri oleh Pelanggan
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['buat_pesanan'])) {
    $nama_pemesan = trim($_POST['nama_pemesan'] ?? '');
    $id_meja = (int)($_POST['id_meja'] ?? 0);
    $items = $_POST['items'] ?? []; // [id_produk => qty]

    if ($id_meja <= 0) {
        $pesan_error = 'Silakan pilih nomor meja tempat Anda duduk terlebih dahulu.';
    } elseif (empty($nama_pemesan)) {
        $pesan_error = 'Silakan masukkan nama Anda agar pesanan dapat diantar dengan tepat.';
    } else {
        $pesanan_items = [];
        foreach ($items as $pid => $qty) {
            $qty = (int)$qty;
            if ($qty > 0) {
                $pesanan_items[(int)$pid] = $qty;
            }
        }

        if (empty($pesanan_items)) {
            $pesan_error = 'Pilih minimal satu menu makanan atau minuman untuk dipesan.';
        } else {
            try {
                $ids = implode(',', array_keys($pesanan_items));
                $stmtCheck = $pdo->query("SELECT id, nama, harga, stok FROM produk WHERE id IN ($ids) AND aktif = 1");
                $db_prods = [];
                $out_of_stock = [];

                while ($p = $stmtCheck->fetch()) {
                    $db_prods[$p['id']] = $p;
                    if ($p['stok'] < $pesanan_items[$p['id']]) {
                        $out_of_stock[] = "{$p['nama']} (Tersedia: {$p['stok']} porsi)";
                    }
                }

                if (!empty($out_of_stock)) {
                    $pesan_error = "Maaf, stok menu berikut tidak mencukupi saat ini:<br>• " . implode("<br>• ", $out_of_stock);
                } else {
                    $pdo->beginTransaction();

                    // 1. Simpan data tamu
                    $userId = !empty($currentUser['id']) ? $currentUser['id'] : null;
                    $stmtPel = $pdo->prepare("
                        INSERT INTO pelanggan (nama, id_meja, id_pengguna)
                        VALUES (:nama, :id_meja, :id_pengguna)
                    ");
                    $stmtPel->execute([
                        'nama' => $nama_pemesan,
                        'id_meja' => $id_meja,
                        'id_pengguna' => $userId
                    ]);
                    $id_pelanggan = $pdo->lastInsertId();

                    // 2. Set meja terisi
                    $stmtM = $pdo->prepare("UPDATE meja SET status = 'terisi' WHERE id = :id");
                    $stmtM->execute(['id' => $id_meja]);

                    // 3. Buat pesanan baru status 'diproses'
                    $stmtPesanan = $pdo->prepare("
                        INSERT INTO pesanan (id_pelanggan, id_pengguna, tanggal, status)
                        VALUES (:id_pelanggan, :id_pengguna, NOW(), 'diproses')
                    ");
                    $stmtPesanan->execute([
                        'id_pelanggan' => $id_pelanggan,
                        'id_pengguna' => $userId
                    ]);
                    $id_pesanan = $pdo->lastInsertId();

                    // 4. Detail Pesanan & Potong Stok
                    $stmtDet = $pdo->prepare("
                        INSERT INTO detail_pesanan (id_pesanan, id_produk, jumlah, subtotal)
                        VALUES (:id_pesanan, :id_produk, :jumlah, :subtotal)
                    ");
                    $stmtPotong = $pdo->prepare("
                        UPDATE produk SET stok = stok - :qty WHERE id = :id
                    ");

                    foreach ($pesanan_items as $pid => $qty) {
                        $sub = $db_prods[$pid]['harga'] * $qty;
                        $stmtDet->execute([
                            'id_pesanan' => $id_pesanan,
                            'id_produk'  => $pid,
                            'jumlah'     => $qty,
                            'subtotal'   => $sub
                        ]);
                        $stmtPotong->execute([
                            'qty' => $qty,
                            'id'  => $pid
                        ]);
                    }

                    $pdo->commit();

                    $_SESSION['last_order_id'] = $id_pesanan;
                    setcookie('last_order_id', (string)$id_pesanan, time() + (86400 * 3), '/');

                    header("Location: pesanan_saya.php?sukses=1&id_pesanan=$id_pesanan");
                    exit;
                }
            } catch (Exception $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                $pesan_error = "Gagal memproses pesanan: " . $e->getMessage();
            }
        }
    }
}

// Data Meja & Kategori
$daftar_meja = $pdo->query("SELECT * FROM meja ORDER BY nomor_meja ASC")->fetchAll();
$daftar_kategori = $pdo->query("SELECT * FROM kategori ORDER BY nama_kategori ASC")->fetchAll();

$kategori_filter = (int)($_GET['kategori'] ?? 0);
$sqlMenu = "SELECT pr.*, k.nama_kategori FROM produk pr JOIN kategori k ON pr.id_kategori = k.id WHERE pr.aktif = 1";
if ($kategori_filter > 0) {
    $sqlMenu .= " AND pr.id_kategori = $kategori_filter";
}
$sqlMenu .= " ORDER BY k.nama_kategori ASC, pr.nama ASC";
$menu_list = $pdo->query($sqlMenu)->fetchAll();

$activeTab = 'menu';
$judul = 'Menu & Pemesanan Mandiri';
require_once __DIR__ . '/includes/header_public.php';
?>

<style>
/* ========================================================
   KEYFRAME ANIMATIONS - LIFE & DYNAMICS
   ======================================================== */
@keyframes fadeInUp {
  from {
    opacity: 0;
    transform: translateY(24px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}

@keyframes ambientGlow {
  0%, 100% {
    transform: scale(1) translate(0, 0);
    opacity: 0.35;
  }
  50% {
    transform: scale(1.3) translate(-25px, 20px);
    opacity: 0.6;
  }
}

@keyframes floatSlow {
  0%, 100% {
    transform: translateY(0);
  }
  50% {
    transform: translateY(-5px);
  }
}

@keyframes pulseBadge {
  0%, 100% {
    transform: scale(1);
  }
  50% {
    transform: scale(1.08);
  }
}

@keyframes pulseActiveRing {
  0% {
    box-shadow: 0 0 0 0 rgba(16, 185, 129, 0.45), 0 10px 25px rgba(16, 185, 129, 0.2);
  }
  70% {
    box-shadow: 0 0 0 10px rgba(16, 185, 129, 0), 0 12px 28px rgba(16, 185, 129, 0.25);
  }
  100% {
    box-shadow: 0 0 0 0 rgba(16, 185, 129, 0), 0 10px 25px rgba(16, 185, 129, 0.2);
  }
}

@keyframes floatUpFade {
  0% {
    opacity: 1;
    transform: translateY(0) scale(1);
  }
  50% {
    opacity: 1;
    transform: translateY(-24px) scale(1.25);
  }
  100% {
    opacity: 0;
    transform: translateY(-45px) scale(0.85);
  }
}

@keyframes bumpScale {
  0% { transform: scale(1); }
  50% { transform: scale(1.25); color: #10b981; }
  100% { transform: scale(1); }
}

@keyframes dockWiggle {
  0%, 100% { transform: rotate(0deg); }
  25% { transform: rotate(-8deg); }
  75% { transform: rotate(8deg); }
}

@keyframes buttonBreath {
  0%, 100% {
    box-shadow: 0 4px 16px rgba(16, 185, 129, 0.45);
    transform: scale(1);
  }
  50% {
    box-shadow: 0 8px 28px rgba(16, 185, 129, 0.75);
    transform: scale(1.02);
  }
}

/* ========================================================
   SCROLL REVEAL (FADE SLIDE UP KETIKA SCROLL KEBAWAH)
   ======================================================== */
.scroll-reveal {
  opacity: 0;
  transform: translateY(45px);
  transition: opacity 0.75s cubic-bezier(0.16, 1, 0.3, 1), transform 0.75s cubic-bezier(0.16, 1, 0.3, 1);
  will-change: opacity, transform;
}

.scroll-reveal.is-revealed {
  opacity: 1;
  transform: translateY(0);
}

/* ========================================================
   COMPONENT STYLES
   ======================================================== */
.hero-culinary {
  background: linear-gradient(135deg, rgba(6, 78, 59, 0.94) 0%, rgba(15, 23, 42, 0.96) 100%), url('assets/img/hero_food.jpg') center/cover no-repeat;
  border-radius: 26px;
  padding: 42px 44px;
  color: #fff;
  margin-bottom: 28px;
  box-shadow: 0 20px 45px -15px rgba(6, 78, 59, 0.38);
  position: relative;
  overflow: hidden;
  border: 1px solid rgba(255, 255, 255, 0.14);
}

.hero-culinary::after {
  content: '';
  position: absolute;
  right: -50px;
  bottom: -50px;
  width: 280px;
  height: 280px;
  background: radial-gradient(circle, rgba(16, 185, 129, 0.4) 0%, transparent 70%);
  border-radius: 50%;
  pointer-events: none;
  animation: ambientGlow 6s ease-in-out infinite;
}

.feature-badge-row {
  display: flex;
  gap: 12px;
  flex-wrap: wrap;
  margin-top: 22px;
}

.feature-pill {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  background: rgba(255, 255, 255, 0.12);
  backdrop-filter: blur(8px);
  -webkit-backdrop-filter: blur(8px);
  padding: 6px 14px;
  border-radius: 9999px;
  font-size: 12px;
  font-weight: 600;
  color: #d1fae5;
  border: 1px solid rgba(255, 255, 255, 0.18);
  transition: all 0.25s ease;
}

.feature-pill:hover {
  background: rgba(255, 255, 255, 0.22);
  transform: translateY(-2px);
  color: #ffffff;
}

.guest-card {
  background: #ffffff;
  border: 1px solid #e2e8f0;
  border-radius: 22px;
  padding: 24px 30px;
  margin-bottom: 28px;
  box-shadow: 0 10px 25px -5px rgba(15, 23, 42, 0.05);
  transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1);
}

.guest-card:focus-within {
  border-color: #10b981;
  box-shadow: 0 14px 35px -5px rgba(16, 185, 129, 0.15);
  transform: translateY(-2px);
}

.guest-icon-box {
  width: 58px;
  height: 58px;
  background: linear-gradient(135deg, #ecfdf5, #a7f3d0);
  border-radius: 16px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 28px;
  box-shadow: 0 4px 12px rgba(16, 185, 129, 0.18);
  animation: floatSlow 3.5s ease-in-out infinite;
}

.menu-category-bar {
  display: flex;
  gap: 12px;
  margin-bottom: 28px;
  overflow-x: auto;
  padding-bottom: 8px;
  scrollbar-width: none;
}

.menu-category-bar::-webkit-scrollbar {
  display: none;
}

.category-chip {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  padding: 10px 22px;
  border-radius: 9999px;
  font-size: 14px;
  font-weight: 700;
  text-decoration: none;
  transition: all 0.25s cubic-bezier(0.34, 1.56, 0.64, 1);
  white-space: nowrap;
}

.category-chip.active {
  background: linear-gradient(135deg, #10b981 0%, #059669 100%);
  color: #fff;
  box-shadow: 0 4px 16px rgba(16, 185, 129, 0.4);
  transform: scale(1.04);
}

.category-chip.inactive {
  background: #ffffff;
  color: #475569;
  border: 1px solid #e2e8f0;
}

.category-chip.inactive:hover {
  background: #f1f5f9;
  color: #0f172a;
  transform: translateY(-2px);
  box-shadow: 0 4px 10px rgba(15, 23, 42, 0.06);
}

/* Upgraded Food Card */
.food-card-upgraded {
  background: #ffffff;
  border-radius: 22px;
  overflow: hidden;
  border: 1px solid #e2e8f0;
  display: flex;
  flex-direction: column;
  transition: transform 0.35s cubic-bezier(0.34, 1.56, 0.64, 1), box-shadow 0.35s ease, border-color 0.3s ease;
  box-shadow: 0 4px 16px rgba(15, 23, 42, 0.05);
  position: relative;
}

.food-card-upgraded:hover {
  transform: translateY(-8px) scale(1.015);
  box-shadow: 0 22px 40px -10px rgba(15, 23, 42, 0.14);
  border-color: #cbd5e1;
}

.food-card-upgraded.has-items {
  border: 2px solid #10b981 !important;
  animation: pulseActiveRing 2.5s infinite;
  transform: translateY(-4px);
}

.food-card-img-wrap {
  position: relative;
  height: 205px;
  overflow: hidden;
  background: #f1f5f9;
}

.food-card-img-wrap img {
  width: 100%;
  height: 100%;
  object-fit: cover;
  transition: transform 0.6s cubic-bezier(0.16, 1, 0.3, 1);
}

.food-card-upgraded:hover .food-card-img-wrap img {
  transform: scale(1.1);
}

/* Diagonal Shine Effect on Food Card Hover */
.food-card-img-wrap::after {
  content: '';
  position: absolute;
  top: 0;
  left: -100%;
  width: 50%;
  height: 100%;
  background: linear-gradient(to right, rgba(255,255,255,0) 0%, rgba(255,255,255,0.3) 50%, rgba(255,255,255,0) 100%);
  transform: skewX(-25deg);
  transition: none;
  pointer-events: none;
}

.food-card-upgraded:hover .food-card-img-wrap::after {
  left: 150%;
  transition: all 0.75s ease-in-out;
}

.pulse-tag {
  animation: pulseBadge 3s ease-in-out infinite;
}

.floating-rating {
  animation: floatSlow 3s ease-in-out infinite;
}

.food-card-content {
  padding: 22px 24px;
  display: flex;
  flex-direction: column;
  flex: 1;
}

.food-card-title {
  font-size: 17.5px;
  font-weight: 800;
  color: #0f172a;
  margin: 0 0 6px 0;
  line-height: 1.3;
}

.food-card-desc {
  font-size: 13px;
  color: #64748b;
  line-height: 1.5;
  margin-bottom: 14px;
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
}

.food-card-footer {
  margin-top: auto;
  padding-top: 16px;
  border-top: 1px solid #f1f5f9;
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 10px;
}

.price-tag {
  font-size: 18.5px;
  font-weight: 800;
  color: #047857;
  letter-spacing: -0.02em;
}

.qty-stepper {
  display: flex;
  align-items: center;
  gap: 6px;
  background: #f8fafc;
  padding: 4px;
  border-radius: 14px;
  border: 1px solid #e2e8f0;
  position: relative;
  transition: all 0.2s ease;
}

.qty-stepper:focus-within {
  border-color: #10b981;
}

.qty-btn {
  width: 34px;
  height: 34px;
  border-radius: 10px;
  border: none;
  background: #ffffff;
  color: #0f172a;
  font-weight: 800;
  font-size: 16px;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  box-shadow: 0 2px 4px rgba(0,0,0,0.06);
  transition: all 0.15s cubic-bezier(0.34, 1.56, 0.64, 1);
  user-select: none;
}

.qty-btn:active {
  transform: scale(0.85);
  background: #10b981;
  color: #ffffff;
}

.qty-btn:hover {
  background: #10b981;
  color: #ffffff;
  transform: translateY(-1px);
}

.qty-input {
  width: 38px;
  text-align: center;
  border: none;
  background: transparent;
  font-weight: 800;
  font-size: 15px;
  color: #0f172a;
  transition: transform 0.2s ease;
}

.qty-input.bump {
  animation: bumpScale 0.3s ease;
}

/* Floating Click Feedback Particle (+1 Bubble) */
.plus-one-bubble {
  position: absolute;
  top: -15px;
  right: 10px;
  background: #10b981;
  color: #ffffff;
  font-size: 12px;
  font-weight: 800;
  padding: 3px 8px;
  border-radius: 9999px;
  pointer-events: none;
  box-shadow: 0 4px 10px rgba(16, 185, 129, 0.4);
  animation: floatUpFade 0.6s cubic-bezier(0.16, 1, 0.3, 1) forwards;
  z-index: 10;
}

/* Floating Bottom Order Summary Dock */
.floating-order-dock {
  position: sticky;
  bottom: 24px;
  background: rgba(15, 23, 42, 0.94);
  backdrop-filter: blur(16px);
  -webkit-backdrop-filter: blur(16px);
  color: #fff;
  padding: 18px 28px;
  border-radius: 22px;
  display: flex;
  justify-content: space-between;
  align-items: center;
  box-shadow: 0 20px 45px rgba(0, 0, 0, 0.38);
  z-index: 1000;
  border: 1px solid rgba(255, 255, 255, 0.16);
  flex-wrap: wrap;
  gap: 16px;
  margin-top: 30px;
  transition: transform 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);
}

.cart-icon-wiggle {
  animation: dockWiggle 0.5s ease;
}

.dock-btn-active {
  animation: buttonBreath 2.2s infinite ease-in-out;
}

.live-search-box {
  background: rgba(255, 255, 255, 0.14);
  backdrop-filter: blur(10px);
  border: 1px solid rgba(255, 255, 255, 0.25);
  border-radius: 14px;
  padding: 11px 20px;
  display: flex;
  align-items: center;
  gap: 12px;
  margin-top: 22px;
  max-width: 500px;
  transition: all 0.25s ease;
}

.live-search-box:focus-within {
  background: rgba(255, 255, 255, 0.22);
  border-color: #34d399;
  box-shadow: 0 0 0 3px rgba(52, 211, 153, 0.3);
  transform: translateY(-2px);
}

.live-search-box input {
  background: transparent;
  border: none;
  outline: none;
  color: #fff;
  font-size: 14.5px;
  width: 100%;
  font-weight: 500;
}

.live-search-box input::placeholder {
  color: #a7f3d0;
  opacity: 0.85;
}
</style>

<!-- 1. Hero Culinary Banner -->
<div class="hero-culinary scroll-reveal">
    <div style="position: relative; z-index: 2; max-width: 720px;">
        <div style="display: inline-flex; align-items: center; gap: 8px; background: rgba(16, 185, 129, 0.25); border: 1px solid rgba(52, 211, 153, 0.5); padding: 5px 14px; border-radius: 9999px; font-size: 12px; font-weight: 700; color: #a7f3d0; margin-bottom: 14px; text-transform: uppercase; letter-spacing: 0.05em;">
            <span style="display:inline-block; animation: dockWiggle 2s infinite;">✨</span> Dapur Ina Aina • Cita Rasa Nusantara
        </div>
        <h1 style="font-size: 32px; font-weight: 800; margin: 0 0 12px 0; line-height: 1.25; letter-spacing: -0.02em;">
            Nikmati Kelezatan Menu Pilihan Langsung dari Meja Anda
        </h1>
        <p style="font-size: 15px; color: #cbd5e1; margin: 0; line-height: 1.6;">
            Pilih nomor meja, tuliskan nama pemesan, dan pilih hidangan favorit. Masakan akan segera diracik koki dan diantar ke meja Anda!
        </p>

        <!-- Live Instant Search Bar -->
        <div class="live-search-box">
            <span style="font-size: 18px;">🔍</span>
            <input type="text" id="liveSearchInput" placeholder="Cari masakan atau minuman favorit Anda..." onkeyup="filterMenuCards()">
        </div>

        <!-- Food Trust Badges -->
        <div class="feature-badge-row">
            <div class="feature-pill">🌿 100% Rempah Segar Alami</div>
            <div class="feature-pill">⚡ Dimasak Langsung Saat Dipesan</div>
            <div class="feature-pill">🕌 Halal & Higienis</div>
        </div>
    </div>
</div>

<?php if ($pesan_error): ?>
    <div class="alert alert-danger scroll-reveal" style="margin-bottom: 24px; font-size: 15px; border-radius: 16px; padding: 16px 20px;">
        ⚠️ <?= $pesan_error ?>
    </div>
<?php endif; ?>

<form method="POST" action="menu_pelanggan.php" id="formOrderPelanggan">

    <!-- 2. Guest Info & Table Selection Card -->
    <div class="guest-card scroll-reveal">
        <div style="display: flex; align-items: center; gap: 20px; flex-wrap: wrap;">
            <div class="guest-icon-box">
                🪑
            </div>

            <!-- Nama Pemesan -->
            <div style="flex: 1; min-width: 220px;">
                <label style="font-weight: 800; color: #0f172a; display: block; margin-bottom: 6px; font-size: 13.5px;">
                    Nama Anda (Pemesan): <span style="color: #dc2626;">*</span>
                </label>
                <input type="text" 
                       name="nama_pemesan" 
                       id="nama_pemesan"
                       class="form-control" 
                       placeholder="Contoh: Budi Santoso / Rina"
                       value="<?= htmlspecialchars(!empty($currentUser['nama_lengkap']) ? $currentUser['nama_lengkap'] : ($_POST['nama_pemesan'] ?? '')) ?>" 
                       style="font-weight: 700; border-color: #34d399; font-size: 14px; padding: 11px 16px; border-radius: 12px;" 
                       required>
            </div>

            <!-- Nomor Meja -->
            <div style="flex: 1; min-width: 220px;">
                <label style="font-weight: 800; color: #0f172a; display: block; margin-bottom: 6px; font-size: 13.5px;">
                    Nomor Meja Duduk Anda: <span style="color: #dc2626;">*</span>
                </label>
                <select name="id_meja" id="id_meja" class="form-control" style="font-weight: 700; border-color: #34d399; font-size: 14px; padding: 11px 16px; border-radius: 12px;" required>
                    <option value="">-- Silakan Pilih Nomor Meja --</option>
                    <?php foreach ($daftar_meja as $m): ?>
                        <option value="<?= $m['id'] ?>" <?= (isset($_POST['id_meja']) && (int)$_POST['id_meja'] === (int)$m['id']) ? 'selected' : '' ?>>
                            Meja <?= htmlspecialchars($m['nomor_meja']) ?> (Kapasitas <?= $m['kapasitas'] ?> Orang) - <?= ucfirst($m['status']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        <div style="margin-top: 14px; font-size: 12.5px; color: #64748b; display: flex; align-items: center; gap: 6px;">
            <span>💡</span> <em>Nomor meja tertera di atas meja makan Anda. Pastikan nomor meja sudah sesuai.</em>
        </div>
    </div>

    <!-- 3. Category Filter Chips -->
    <div class="menu-category-bar scroll-reveal">
        <a href="menu_pelanggan.php" class="category-chip <?= ($kategori_filter === 0) ? 'active' : 'inactive' ?>">
            ✨ Semua Menu (<?= count($menu_list) ?>)
        </a>
        <?php foreach ($daftar_kategori as $kat): 
            $icon = (stripos($kat['nama_kategori'], 'minum') !== false) ? '🍹' : '🍛';
        ?>
        <a href="menu_pelanggan.php?kategori=<?= $kat['id'] ?>" class="category-chip <?= ($kategori_filter == $kat['id']) ? 'active' : 'inactive' ?>">
            <?= $icon ?> <?= htmlspecialchars($kat['nama_kategori']) ?>
        </a>
        <?php endforeach; ?>
    </div>

    <!-- 4. Menu Grid with Scroll Reveal Fade Slide Up -->
    <div class="menu-grid" id="menuCardsContainer" style="margin-bottom: 40px;">
        <?php 
        $cardIndex = 0;
        foreach ($menu_list as $m): 
            $cardIndex++;
            $isHabis = ($m['stok'] <= 0);
            $desc = $deskripsi_menu[$m['id']] ?? 'Masakan lezat dengan rempah pilihan dari resep turun-temurun Dapur Ina Aina.';
            $badge = $badge_menu[$m['id']] ?? ['rating' => '4.8', 'terjual' => 'Rekomendasi', 'tag' => '✨ Populer', 'tag_color' => '#10b981'];
            $staggerDelay = (($cardIndex - 1) % 3) * 0.1;
        ?>
        <div class="food-card-upgraded menu-item-card scroll-reveal <?= $isHabis ? 'out-of-stock' : '' ?>" 
             id="card_product_<?= $m['id'] ?>" 
             data-name="<?= strtolower(htmlspecialchars($m['nama'])) ?>"
             style="transition-delay: <?= $staggerDelay ?>s;">
            
            <!-- Food Image Thumbnail -->
            <div class="food-card-img-wrap">
                <?php if ($m['gambar']): ?>
                    <img src="<?= htmlspecialchars($m['gambar']) ?>" alt="<?= htmlspecialchars($m['nama']) ?>" loading="lazy">
                <?php else: ?>
                    <div style="width:100%; height:100%; background: linear-gradient(135deg, #ecfdf5, #a7f3d0); display:flex; align-items:center; justify-content:center; font-size:48px;">
                        🍲
                    </div>
                <?php endif; ?>

                <!-- Tag Promo / Kategori -->
                <div style="position: absolute; top: 12px; left: 12px; display: flex; gap: 6px; flex-wrap: wrap; z-index: 2;">
                    <span class="badge" style="background: rgba(15, 23, 42, 0.85); color: #fff; backdrop-filter: blur(8px); border: 1px solid rgba(255,255,255,0.2); font-size: 11px; padding: 4px 10px; border-radius: 9999px;">
                        <?= htmlspecialchars($m['nama_kategori']) ?>
                    </span>
                    <span class="badge pulse-tag" style="background: <?= $badge['tag_color'] ?>; color: #fff; font-size: 11px; padding: 4px 10px; border-radius: 9999px; font-weight: 700; box-shadow: 0 2px 6px rgba(0,0,0,0.25);">
                        <?= $badge['tag'] ?>
                    </span>
                </div>

                <!-- Rating Floating Badge -->
                <div class="floating-rating" style="position: absolute; bottom: 12px; right: 12px; background: rgba(255, 255, 255, 0.95); backdrop-filter: blur(8px); padding: 4px 10px; border-radius: 9999px; font-size: 12px; font-weight: 800; color: #0f172a; display: flex; align-items: center; gap: 4px; box-shadow: 0 2px 8px rgba(0,0,0,0.15); z-index: 2;">
                    <span style="color: #f59e0b;">★</span> <?= $badge['rating'] ?>
                </div>
            </div>

            <!-- Food Info -->
            <div class="food-card-content">
                <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 6px;">
                    <h3 class="food-card-title"><?= htmlspecialchars($m['nama']) ?></h3>
                </div>

                <p class="food-card-desc">
                    <?= htmlspecialchars($desc) ?>
                </p>

                <div style="margin-bottom: 12px;">
                    <?php if ($isHabis): ?>
                        <span class="badge badge-danger" style="font-size: 11.5px; padding: 4px 10px; border-radius: 9999px;">Habis Terjual</span>
                    <?php else: ?>
                        <span class="badge badge-success" style="font-size: 11.5px; padding: 4px 10px; border-radius: 9999px; background: #ecfdf5; color: #065f46; border: 1px solid #a7f3d0;">
                            Tersedia (<?= $m['stok'] ?> porsi)
                        </span>
                    <?php endif; ?>
                </div>

                <!-- Footer: Price & Quantity Stepper -->
                <div class="food-card-footer">
                    <div>
                        <div style="font-size: 11px; color: #64748b; font-weight: 600; text-transform: uppercase;">Harga</div>
                        <div class="price-tag">
                            Rp <?= number_format($m['harga'], 0, ',', '.') ?>
                        </div>
                    </div>

                    <?php if ($isHabis): ?>
                        <button type="button" class="btn btn-secondary" style="opacity: 0.6; cursor: not-allowed; font-size: 13px; padding: 8px 14px; border-radius: 10px;" disabled>
                            Stok Habis
                        </button>
                    <?php else: ?>
                        <div class="qty-stepper" id="stepper_wrap_<?= $m['id'] ?>">
                            <button type="button" class="qty-btn" onclick="ubahQtyPelanggan(<?= $m['id'] ?>, -1, <?= $m['harga'] ?>, undefined, event)">−</button>
                            <input type="number" 
                                   name="items[<?= $m['id'] ?>]" 
                                   id="qty_m_<?= $m['id'] ?>" 
                                   value="0" 
                                   min="0" 
                                   max="<?= $m['stok'] ?>" 
                                   data-name="<?= htmlspecialchars($m['nama']) ?>"
                                   data-price="<?= $m['harga'] ?>"
                                   class="qty-input item-qty-input" 
                                   readonly>
                            <button type="button" class="qty-btn" onclick="ubahQtyPelanggan(<?= $m['id'] ?>, 1, <?= $m['harga'] ?>, <?= $m['stok'] ?>, event)">+</button>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- 5. Floating Bottom Order Summary Dock -->
    <div class="floating-order-dock scroll-reveal" id="floatingOrderDock">
        <div style="display: flex; align-items: center; gap: 14px;">
            <div id="cartIconBox" style="width: 50px; height: 50px; background: #10b981; border-radius: 15px; display: flex; align-items: center; justify-content: center; font-size: 24px; box-shadow: 0 4px 14px rgba(16, 185, 129, 0.45); transition: transform 0.3s ease;">
                🛒
            </div>
            <div>
                <div style="font-size: 11.5px; color: #94a3b8; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em;">
                    Ringkasan Pesanan Anda
                </div>
                <div style="font-size: 16px; font-weight: 800; display: flex; align-items: center; gap: 8px; margin-top: 1px;">
                    <span id="summary-items" style="color: #ffffff; transition: all 0.2s ease;">0 menu dipilih</span>
                    <span style="color: #64748b;">•</span>
                    <span style="color: #34d399; font-size: 19px; font-weight: 900; transition: transform 0.25s ease;" id="summary-total">Rp 0</span>
                </div>
            </div>
        </div>

        <button type="submit" name="buat_pesanan" id="btnSubmitOrder" class="btn btn-primary" style="padding: 14px 34px; font-size: 15.5px; font-weight: 800; border-radius: 9999px; display: inline-flex; align-items: center; gap: 8px; transition: all 0.25s cubic-bezier(0.34, 1.56, 0.64, 1);">
            <span style="display:inline-block; transition: transform 0.2s;">🚀</span> Kirim Pesanan ke Dapur
        </button>
    </div>
</form>

<script>
// ========================================================
// SCROLL REVEAL OBSERVER (FADE SLIDE UP KETIKA SCROLL KEBAWAH)
// ========================================================
document.addEventListener('DOMContentLoaded', function() {
    const revealElements = document.querySelectorAll('.scroll-reveal');

    if ('IntersectionObserver' in window) {
        const revealObserver = new IntersectionObserver((entries, observer) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.classList.add('is-revealed');
                    observer.unobserve(entry.target);
                }
            });
        }, {
            root: null,
            threshold: 0.1,
            rootMargin: '0px 0px -40px 0px'
        });

        revealElements.forEach(el => revealObserver.observe(el));
    } else {
        // Fallback langsung tampil jika browser tidak mendukung observer
        revealElements.forEach(el => el.classList.add('is-revealed'));
    }
});

function ubahQtyPelanggan(id, delta, price, max, event) {
    const input = document.getElementById('qty_m_' + id);
    const card = document.getElementById('card_product_' + id);
    const stepperWrap = document.getElementById('stepper_wrap_' + id);
    if (!input) return;

    let val = parseInt(input.value) || 0;
    val += delta;
    if (val < 0) val = 0;
    if (max !== undefined && val > max) {
        alert('Maksimal pemesanan sesuai sisa stok (' + max + ' porsi)');
        return;
    }
    input.value = val;

    // Trigger bump scale animation on quantity number
    input.classList.remove('bump');
    void input.offsetWidth; // force reflow
    input.classList.add('bump');

    // Spawn floating +1 particle animation if adding
    if (delta > 0 && stepperWrap) {
        spawnFloatingPlus(stepperWrap);
    }

    // Visual active border on card
    if (card) {
        if (val > 0) {
            card.classList.add('has-items');
        } else {
            card.classList.remove('has-items');
        }
    }

    // Trigger cart wiggle animation
    const cartIcon = document.getElementById('cartIconBox');
    if (cartIcon) {
        cartIcon.classList.remove('cart-icon-wiggle');
        void cartIcon.offsetWidth;
        cartIcon.classList.add('cart-icon-wiggle');
    }

    hitungRingkasan();
}

function spawnFloatingPlus(container) {
    const bubble = document.createElement('div');
    bubble.className = 'plus-one-bubble';
    bubble.textContent = '+1';
    container.appendChild(bubble);

    setTimeout(() => {
        if (bubble && bubble.parentNode) {
            bubble.parentNode.removeChild(bubble);
        }
    }, 600);
}

function hitungRingkasan() {
    const inputs = document.querySelectorAll('.item-qty-input');
    let totalItems = 0;
    let totalPrice = 0;

    inputs.forEach(input => {
        const qty = parseInt(input.value) || 0;
        const price = parseFloat(input.getAttribute('data-price')) || 0;
        if (qty > 0) {
            totalItems += qty;
            totalPrice += (qty * price);
        }
    });

    const itemsEl = document.getElementById('summary-items');
    const totalEl = document.getElementById('summary-total');
    const btnSubmit = document.getElementById('btnSubmitOrder');

    if (itemsEl) {
        itemsEl.textContent = totalItems + ' porsi dipilih';
    }
    if (totalEl) {
        totalEl.textContent = 'Rp ' + totalPrice.toLocaleString('id-ID');
        totalEl.style.transform = 'scale(1.15)';
        setTimeout(() => {
            totalEl.style.transform = 'scale(1)';
        }, 200);
    }

    // Animate submit button breathing glow when cart has items
    if (btnSubmit) {
        if (totalItems > 0) {
            btnSubmit.classList.add('dock-btn-active');
        } else {
            btnSubmit.classList.remove('dock-btn-active');
        }
    }
}

function filterMenuCards() {
    const query = document.getElementById('liveSearchInput').value.toLowerCase();
    const cards = document.querySelectorAll('.menu-item-card');
    
    cards.forEach(card => {
        const name = card.getAttribute('data-name') || '';
        if (name.includes(query)) {
            card.style.display = 'flex';
            card.classList.add('is-revealed');
        } else {
            card.style.display = 'none';
        }
    });
}
</script>

<?php require_once __DIR__ . '/includes/footer_public.php'; ?>
