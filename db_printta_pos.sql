-- phpMyAdmin SQL Dump
-- version 5.2.3
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1:3306
-- Generation Time: Nov 18, 2026 at 02:27 PM
-- Server version: 8.4.3
-- PHP Version: 8.3.33

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `db_printta_pos`
--

-- --------------------------------------------------------

--
-- Table structure for table `detail_transaksi`
--

CREATE TABLE `detail_transaksi` (
  `id` int NOT NULL,
  `transaksi_id` int DEFAULT NULL,
  `produk_nama` varchar(100) DEFAULT NULL,
  `qty` int DEFAULT NULL,
  `harga_satuan` int DEFAULT NULL,
  `subtotal_item` int DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `detail_transaksi`
--

INSERT INTO `detail_transaksi` (`id`, `transaksi_id`, `produk_nama`, `qty`, `harga_satuan`, `subtotal_item`) VALUES
(1, 1, 'Print BW', 1, 500, 500),
(2, 1, 'Laminating A4', 1, 3000, 3000),
(3, 2, 'Print BW', 10, 400, 4000);

-- --------------------------------------------------------

--
-- Table structure for table `produk`
--

CREATE TABLE `produk` (
  `id` int NOT NULL,
  `nama` varchar(100) NOT NULL,
  `kategori` varchar(50) NOT NULL,
  `satuan` enum('lembar','pcs','langsung') NOT NULL,
  `harga_1` int DEFAULT '0',
  `harga_2` int DEFAULT '0',
  `harga_3` int DEFAULT '0',
  `harga_tetap` int DEFAULT '0',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `produk`
--

INSERT INTO `produk` (`id`, `nama`, `kategori`, `satuan`, `harga_1`, `harga_2`, `harga_3`, `harga_tetap`, `created_at`) VALUES
(1, 'Print BW', 'Print', 'lembar', 500, 400, 380, 0, '2026-11-18 14:04:48'),
(2, 'Print Warna', 'Print', 'lembar', 500, 480, 450, 0, '2026-11-18 14:04:48'),
(3, 'Laminating A4', 'Barang', 'pcs', 0, 0, 0, 3000, '2026-11-18 14:04:48'),
(4, 'Desain Banner', 'Jasa', 'langsung', 0, 0, 0, 25000, '2026-11-18 14:04:48');

-- --------------------------------------------------------

--
-- Table structure for table `transaksi`
--

CREATE TABLE `transaksi` (
  `id` int NOT NULL,
  `trx_code` varchar(50) NOT NULL,
  `nama_pembeli` varchar(100) DEFAULT 'Umum',
  `tanggal` datetime DEFAULT CURRENT_TIMESTAMP,
  `layanan` enum('ambil','antar') DEFAULT 'ambil',
  `ongkir` int DEFAULT '0',
  `subtotal` int NOT NULL,
  `total` int NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `transaksi`
--

INSERT INTO `transaksi` (`id`, `trx_code`, `nama_pembeli`, `tanggal`, `layanan`, `ongkir`, `subtotal`, `total`) VALUES
(1, '#TRX577', 'Umum', '2026-11-18 21:09:33', 'ambil', 0, 3500, 3500),
(2, '#TRX118', 'intan', '2026-11-18 21:15:46', 'ambil', 0, 4000, 4000);

--
-- Indexes for dumped tables
--

--
-- Indexes for table `detail_transaksi`
--
ALTER TABLE `detail_transaksi`
  ADD PRIMARY KEY (`id`),
  ADD KEY `transaksi_id` (`transaksi_id`);

--
-- Indexes for table `produk`
--
ALTER TABLE `produk`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `transaksi`
--
ALTER TABLE `transaksi`
  ADD PRIMARY KEY (`id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `detail_transaksi`
--
ALTER TABLE `detail_transaksi`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `produk`
--
ALTER TABLE `produk`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `transaksi`
--
ALTER TABLE `transaksi`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `detail_transaksi`
--
ALTER TABLE `detail_transaksi`
  ADD CONSTRAINT `detail_transaksi_ibfk_1` FOREIGN KEY (`transaksi_id`) REFERENCES `transaksi` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
