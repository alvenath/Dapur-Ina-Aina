# Dapur Ina Aina — Website (Tugas Sesi 3)

Website pengolahan transaksi restoran "Dapur Ina Aina", dibangun dengan
**PHP + MySQL/MariaDB**, sesuai rancangan software pada Tugas Sesi 1 dan
skema database pada Tugas Sesi 2.

## Struktur Folder

```
website/
├── config/
│   └── db.php              # Konfigurasi koneksi database (PDO)
├── includes/
│   ├── header.php          # Header & navigasi
│   ├── footer.php
│   └── functions.php       # Fungsi bantu (format rupiah, badge, dsb)
├── assets/css/style.css    # Styling
├── sql/dapur_ina_aina.sql  # Skema & data awal database (dari Sesi 2)
├── index.php                # Redirect ke pesanan.php
├── pesanan.php               # Fitur 1: Data pesanan pelanggan
├── stok.php                  # Fitur 2: Input & update stok berdasarkan kriteria
└── billing.php                # Fitur 3: Billing & pembayaran tunai/non-tunai
```

## Cara Menjalankan (XAMPP / Laravel Herd)

1. **Buat database** — buka phpMyAdmin, lalu jalankan isi file
   `sql/dapur_ina_aina.sql` (Import atau copy-paste ke tab SQL). Skema ini
   identik dengan rancangan MySQL pada Tugas Sesi 2 (tabel `pengguna`,
   `pelanggan`, `kategori`, `produk`, `pesanan`, `detail_pesanan`,
   `pembayaran`).

2. **Atur kredensial koneksi** — edit `config/db.php`:
   ```php
   define('DB_HOST', 'localhost');
   define('DB_NAME', 'dapur_ina_aina');
   define('DB_USER', 'root');   // default XAMPP
   define('DB_PASS', '');       // default XAMPP kosong
   ```

3. **Salin folder** `website/` ke direktori web server:
   - XAMPP: `C:\xampp\htdocs\dapur-ina-aina\`
   - Laravel Herd: parked directory, mis. `~/Herd/dapur-ina-aina/`

4. **Jalankan** XAMPP (Apache + MySQL) lalu buka
   `http://localhost/dapur-ina-aina/` di browser.

   Atau, untuk uji cepat tanpa XAMPP, gunakan PHP built-in server:
   ```bash
   php -S localhost:8000
   ```
   lalu buka `http://localhost:8000/`.

## Fitur (sesuai instruksi Tugas 3)

| Halaman | Fitur |
|---|---|
| `pesanan.php` | Menampilkan data pesanan pelanggan (join pesanan–pelanggan–detail_pesanan–produk) |
| `stok.php` | Filter stok berdasarkan kategori/status, input produk baru, update stok — tersimpan ke tabel `produk` |
| `billing.php` | Menampilkan billing dari pesanan, memproses pembayaran tunai (dengan kembalian) atau non-tunai/debit/kartu kredit (dengan nomor referensi) |

## Penanganan Error

Setiap aksi (tambah produk, update stok, proses pembayaran) divalidasi dan
melempar exception (`InvalidArgumentException`, `RuntimeException`,
`PDOException`) yang ditangkap lalu ditampilkan sebagai notifikasi merah
di halaman terkait, misalnya:
- Input tidak valid (angka kosong/negatif, metode pembayaran tidak dikenal)
- Data tidak ditemukan (ID produk/pesanan tidak ada)
- Stok tidak cukup saat pengurangan stok
- Pembayaran tunai kurang dari total tagihan
- Pesanan yang sudah "selesai" dicoba dibayar ulang

## Relasi dengan Program Konsol (aplikasi_restoran.py)

Website ini menerapkan logika bisnis yang sama dengan program konsol
Python pada pengumpulan tugas sebelumnya (kelas `PesananService`,
`StokService`, `BillingService`), hanya dengan antarmuka web (PHP) dan
database production-grade (MySQL/MariaDB) menggantikan SQLite, sesuai
software pendukung yang direncanakan pada Tugas Sesi 1.
