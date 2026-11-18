-- Migrasi Phase 2 - PRINT'TA POS
-- Alur pesanan, data pelanggan, dan pembayaran.
-- Jalankan setelah migrasi_phase1.sql.

ALTER TABLE `transaksi`
  ADD COLUMN `no_hp` varchar(20) DEFAULT NULL AFTER `nama_pembeli`,
  ADD COLUMN `catatan` text AFTER `no_hp`,
  ADD COLUMN `status_pesanan` enum('antri','proses','selesai','diambil') NOT NULL DEFAULT 'antri' AFTER `layanan`,
  ADD COLUMN `estimasi_selesai` datetime DEFAULT NULL AFTER `status_pesanan`,
  ADD COLUMN `metode_bayar` enum('cash','transfer','qris') NOT NULL DEFAULT 'cash' AFTER `ongkir`,
  ADD COLUMN `status_bayar` enum('lunas','dp','belum') NOT NULL DEFAULT 'lunas' AFTER `metode_bayar`,
  ADD COLUMN `uang_diterima` int NOT NULL DEFAULT '0' AFTER `status_bayar`,
  ADD COLUMN `kembalian` int NOT NULL DEFAULT '0' AFTER `uang_diterima`,
  ADD COLUMN `diskon` int NOT NULL DEFAULT '0' AFTER `subtotal`,
  ADD COLUMN `pajak` int NOT NULL DEFAULT '0' AFTER `diskon`,
  ADD KEY `idx_status_pesanan` (`status_pesanan`);

ALTER TABLE `detail_transaksi`
  ADD COLUMN `catatan` varchar(255) DEFAULT NULL AFTER `produk_nama`;

-- Tandai transaksi lama sebagai selesai/diambil agar tidak muncul di antrian.
-- Hapus baris berikut jika tidak diinginkan.
UPDATE `transaksi`
SET `status_pesanan` = 'diambil',
    `uang_diterima` = `total`,
    `status_bayar` = 'lunas';
