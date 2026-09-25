<?php
/** Format angka menjadi Rupiah, mis. 25000 -> "Rp25.000". */
function rupiah($angka): string
{
    return 'Rp' . number_format((float) $angka, 0, ',', '.');
}

/** Kelas badge CSS untuk status pesanan. */
function badge_status(string $status): string
{
    $map = [
        'selesai'    => 'badge-selesai',
        'diproses'   => 'badge-diproses',
        'disiapkan'  => 'badge-disiapkan',
        'dibatalkan' => 'badge-dibatalkan',
    ];
    return $map[$status] ?? '';
}

/** Label untuk status pesanan */
function label_status(string $status): string
{
    $map = [
        'diproses'   => 'Diterima (Antrean)',
        'disiapkan'  => 'Sedang Dimasak',
        'selesai'    => 'Sudah Siap / Siap Santap',
        'dibatalkan' => 'Dibatalkan',
    ];
    return $map[$status] ?? ucfirst($status);
}

/** Menentukan status ketersediaan stok berdasarkan kriteria jumlah. */
function status_stok(int $stok): string
{
    if ($stok <= 0) return 'Habis';
    if ($stok <= 10) return 'Menipis';
    return 'Tersedia';
}

/** Kelas badge CSS untuk status stok. */
function badge_stok(string $status): string
{
    $map = [
        'Tersedia' => 'badge-tersedia',
        'Menipis'  => 'badge-menipis',
        'Habis'    => 'badge-habis',
    ];
    return $map[$status] ?? '';
}

/** Badge CSS untuk status meja */
function badge_meja(string $status): string
{
    $map = [
        'kosong'  => 'badge-selesai',
        'terisi'  => 'badge-diproses',
        'dipesan' => 'badge-disiapkan',
    ];
    return $map[$status] ?? '';
}

function h(string $s): string
{
    return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
}

/** Flash message helper */
function set_flash(string $type, string $message): void
{
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

/** Get and clear flash message */
function get_flash(): ?array
{
    $flash = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    return $flash;
}

/** Render flash message HTML */
function render_flash(): void
{
    $flash = get_flash();
    if ($flash): ?>
        <div class="alert alert-<?= $flash['type'] === 'success' ? 'success' : 'error' ?>">
            <?= $flash['type'] === 'success' ? '✅' : '⚠️' ?> <?= h($flash['message']) ?>
        </div>
    <?php endif;
}
