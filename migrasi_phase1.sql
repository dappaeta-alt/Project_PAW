-- Migrasi Phase 1 - PRINT'TA POS
-- Jalankan pada database db_printta_pos yang sudah ada.
-- File ini tidak menimpa data, hanya menambah index.

ALTER TABLE `transaksi`
  ADD UNIQUE KEY `uniq_trx_code` (`trx_code`),
  ADD KEY `idx_tanggal` (`tanggal`);
