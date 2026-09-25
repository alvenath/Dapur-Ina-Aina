-- =====================================================================
-- Database: dapur_ina_aina
-- Sistem Manajemen Restoran "Dapur Ina Aina"
-- Sumber   : Rancangan Tugas Sesi 2 (Class Diagram & ERD)
-- Tools    : MySQL / MariaDB (XAMPP / Laravel Herd), via phpMyAdmin
-- =====================================================================

DROP DATABASE IF EXISTS dapur_ina_aina;

CREATE DATABASE IF NOT EXISTS dapur_ina_aina
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_general_ci;
USE dapur_ina_aina;

-- Tabel: pengguna (akun admin & kasir saja — pelanggan tidak perlu login)
CREATE TABLE IF NOT EXISTS pengguna (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  username VARCHAR(50) NOT NULL UNIQUE,
  password VARCHAR(255) NOT NULL,
  nama_lengkap VARCHAR(100) NOT NULL,
  role ENUM('admin','kasir') NOT NULL,
  aktif TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Tabel: meja
CREATE TABLE IF NOT EXISTS meja (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  nomor_meja INT UNSIGNED NOT NULL UNIQUE,
  kapasitas INT UNSIGNED NOT NULL DEFAULT 4,
  status ENUM('kosong','terisi','dipesan') NOT NULL DEFAULT 'kosong'
);

-- Tabel: kategori
CREATE TABLE IF NOT EXISTS kategori (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  nama_kategori VARCHAR(30) NOT NULL
);

-- Tabel: produk (menu)
CREATE TABLE IF NOT EXISTS produk (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  nama VARCHAR(100) NOT NULL,
  harga DECIMAL(10,2) NOT NULL,
  stok INT UNSIGNED NOT NULL DEFAULT 0,
  id_kategori INT UNSIGNED NOT NULL,
  gambar VARCHAR(255) NULL,
  aktif TINYINT(1) NOT NULL DEFAULT 1,
  CONSTRAINT fk_produk_kategori
    FOREIGN KEY (id_kategori) REFERENCES kategori(id)
    ON UPDATE CASCADE ON DELETE RESTRICT
);

-- Tabel: pelanggan (data tamu yang pesan — tanpa akun login)
CREATE TABLE IF NOT EXISTS pelanggan (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  nama VARCHAR(100) NOT NULL,
  id_meja INT UNSIGNED NULL,
  id_pengguna INT UNSIGNED NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_pelanggan_meja
    FOREIGN KEY (id_meja) REFERENCES meja(id)
    ON UPDATE CASCADE ON DELETE SET NULL,
  CONSTRAINT fk_pelanggan_pengguna
    FOREIGN KEY (id_pengguna) REFERENCES pengguna(id)
    ON UPDATE CASCADE ON DELETE SET NULL
);

-- Tabel: pesanan (header transaksi)
CREATE TABLE IF NOT EXISTS pesanan (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  id_pelanggan INT UNSIGNED NOT NULL,
  id_pengguna INT UNSIGNED NULL COMMENT 'Petugas yang mencatat pesanan',
  tanggal DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  status ENUM('diproses','disiapkan','selesai','dibatalkan') NOT NULL DEFAULT 'diproses',
  CONSTRAINT fk_pesanan_pelanggan
    FOREIGN KEY (id_pelanggan) REFERENCES pelanggan(id)
    ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT fk_pesanan_pengguna
    FOREIGN KEY (id_pengguna) REFERENCES pengguna(id)
    ON UPDATE CASCADE ON DELETE SET NULL
);

-- Tabel: detail_pesanan
CREATE TABLE IF NOT EXISTS detail_pesanan (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  id_pesanan INT UNSIGNED NOT NULL,
  id_produk INT UNSIGNED NOT NULL,
  jumlah INT UNSIGNED NOT NULL,
  subtotal DECIMAL(10,2) NOT NULL,
  CONSTRAINT fk_detail_pesanan
    FOREIGN KEY (id_pesanan) REFERENCES pesanan(id)
    ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT fk_detail_produk
    FOREIGN KEY (id_produk) REFERENCES produk(id)
    ON UPDATE CASCADE ON DELETE RESTRICT
);

-- Tabel: pembayaran
CREATE TABLE IF NOT EXISTS pembayaran (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  id_pesanan INT UNSIGNED NOT NULL UNIQUE,
  jenis ENUM('tunai','debit','kartu_kredit') NOT NULL,
  jumlah_bayar DECIMAL(10,2) NOT NULL,
  kembalian DECIMAL(10,2) DEFAULT 0,
  no_referensi VARCHAR(50) NULL,
  tanggal_bayar DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_pembayaran_pesanan
    FOREIGN KEY (id_pesanan) REFERENCES pesanan(id)
    ON UPDATE CASCADE ON DELETE CASCADE
);

-- ---------------------------------------------------------------------
-- Data awal
-- Password: semua menggunakan password '12345' (di-hash dengan PHP)
-- ---------------------------------------------------------------------

-- Kategori (Hanya 2 Kategori)
INSERT INTO kategori (id, nama_kategori) VALUES
  (1, 'Makanan Utama'),
  (2, 'Minuman');

-- Meja
INSERT INTO meja (nomor_meja, kapasitas, status) VALUES
  (1, 4, 'terisi'),
  (2, 4, 'kosong'),
  (3, 6, 'kosong'),
  (4, 2, 'kosong'),
  (5, 4, 'kosong'),
  (6, 8, 'kosong'),
  (7, 4, 'kosong'),
  (8, 2, 'kosong'),
  (9, 6, 'kosong'),
  (10, 4, 'kosong');

-- Pengguna (hanya admin & kasir, password: 12345)
-- Hash: $2y$10$XuzbRhKy2KyRWrISC1b//.psUvFz1ZIqMC0JaAyk3rfir41LGZ6pu (password: 12345)
INSERT INTO pengguna (username, password, nama_lengkap, role) VALUES
  ('admin', '$2y$10$XuzbRhKy2KyRWrISC1b//.psUvFz1ZIqMC0JaAyk3rfir41LGZ6pu', 'Administrator', 'admin'),
  ('kasir', '$2y$10$XuzbRhKy2KyRWrISC1b//.psUvFz1ZIqMC0JaAyk3rfir41LGZ6pu', 'Siti Kasir', 'kasir');

-- Produk (menu: Masing-masing 3 menu)
INSERT INTO produk (id, nama, harga, stok, id_kategori, gambar, aktif) VALUES
  (1, 'Nasi Goreng Spesial', 25000, 50, 1, 'assets/img/nasi_goreng.jpg', 1),
  (2, 'Ayam Bakar Madu', 30000, 40, 1, 'assets/img/ayam_bakar.jpg', 1),
  (3, 'Rendang Sapi', 35000, 30, 1, 'assets/img/rendang_sapi.jpg', 1),
  (4, 'Es Teh Manis', 8000, 100, 2, 'assets/img/es_teh.jpg', 1),
  (5, 'Jus Alpukat', 15000, 40, 2, 'assets/img/jus_alpukat.jpg', 1),
  (6, 'Es Jeruk Segar', 10000, 60, 2, 'assets/img/es_jeruk.jpg', 1);

-- Pelanggan sample
INSERT INTO pelanggan (id, nama, id_meja, id_pengguna) VALUES
  (1, 'Budi Santoso', 1, NULL);

-- Pesanan sample
INSERT INTO pesanan (id, id_pelanggan, id_pengguna, tanggal, status) VALUES
  (1, 1, 2, NOW(), 'diproses');

-- Detail pesanan sample
INSERT INTO detail_pesanan (id_pesanan, id_produk, jumlah, subtotal) VALUES
  (1, 1, 2, 50000),
  (1, 4, 2, 16000);

-- View: Laporan harian
CREATE OR REPLACE VIEW v_laporan_harian AS
SELECT
  DATE(p.tanggal) AS periode,
  COUNT(DISTINCT p.id) AS total_transaksi,
  COALESCE(SUM(dp.subtotal), 0) AS total_pendapatan
FROM pesanan p
JOIN detail_pesanan dp ON dp.id_pesanan = p.id
LEFT JOIN pembayaran pb ON pb.id_pesanan = p.id
WHERE p.status = 'selesai'
GROUP BY DATE(p.tanggal);

-- View: Laporan bulanan
CREATE OR REPLACE VIEW v_laporan_bulanan AS
SELECT
  DATE_FORMAT(p.tanggal, '%Y-%m') AS periode,
  COUNT(DISTINCT p.id) AS total_transaksi,
  COALESCE(SUM(dp.subtotal), 0) AS total_pendapatan
FROM pesanan p
JOIN detail_pesanan dp ON dp.id_pesanan = p.id
LEFT JOIN pembayaran pb ON pb.id_pesanan = p.id
WHERE p.status = 'selesai'
GROUP BY DATE_FORMAT(p.tanggal, '%Y-%m');
