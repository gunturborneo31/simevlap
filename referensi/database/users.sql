-- phpMyAdmin SQL Dump
-- version 5.2.2
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1:3306
-- Generation Time: Jun 20, 2026 at 10:55 AM
-- Server version: 11.8.6-MariaDB-log
-- PHP Version: 7.2.34

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `u290337218_simevlap`
--

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `email_verified_at` timestamp NULL DEFAULT NULL,
  `password` varchar(255) NOT NULL,
  `id_skpd` text NOT NULL,
  `role` varchar(100) NOT NULL DEFAULT 'perusahaan',
  `remember_token` varchar(100) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `name`, `email`, `email_verified_at`, `password`, `id_skpd`, `role`, `remember_token`, `created_at`, `updated_at`) VALUES
(1, 'Super Admin', 'superadmin', NULL, '$2y$12$ZYg86GSpvBr1IK8wYNehL.n4bC6SVA3C6CMjKsWHVqt7vT/sNP52S', '', 'superadmin', NULL, '2024-05-07 12:32:00', '2024-05-11 06:32:18'),
(12, 'Dinas Perhubungan', 'dishub', NULL, '$2y$12$ZYg86GSpvBr1IK8wYNehL.n4bC6SVA3C6CMjKsWHVqt7vT/sNP52S', '2.15.0.00.0.00.09', 'skpd', NULL, '2024-05-07 12:32:00', '2024-05-11 06:32:18'),
(13, 'Badan Penanggulangan Bencana Daerah', 'bpbd', NULL, '$2y$12$ZYg86GSpvBr1IK8wYNehL.n4bC6SVA3C6CMjKsWHVqt7vT/sNP52S', '1.05.0.00.0.00.26', 'skpd', NULL, '2024-05-07 12:32:00', '2024-05-11 06:32:18'),
(14, 'Dinas Ketentraman dan Ketertiban Umum, dan Perlindungan Masyarakat', 'tantiblinmas', NULL, '$2y$12$ZYg86GSpvBr1IK8wYNehL.n4bC6SVA3C6CMjKsWHVqt7vT/sNP52S', '1.05.0.00.0.00.04', 'skpd', NULL, '2024-05-07 12:32:00', '2024-05-11 06:32:18'),
(15, 'Dinas Lingkungan Hidup', 'dlh', NULL, '$2y$12$ZYg86GSpvBr1IK8wYNehL.n4bC6SVA3C6CMjKsWHVqt7vT/sNP52S', '2.11.1.05.0.00.06', 'skpd', NULL, '2024-05-07 12:32:00', '2024-05-11 06:32:18'),
(16, 'Badan Pengelola Keuangan dan Aset Daerah', 'bpkad', NULL, '$2y$12$ZYg86GSpvBr1IK8wYNehL.n4bC6SVA3C6CMjKsWHVqt7vT/sNP52S', '5.02.4.01.0.00.23', 'skpd', NULL, '2024-05-07 12:32:00', '2024-05-11 06:32:18'),
(17, 'Kecamatan Long Apari', 'longapari', NULL, '$2y$12$ZYg86GSpvBr1IK8wYNehL.n4bC6SVA3C6CMjKsWHVqt7vT/sNP52S', '7.01.0.00.0.00.16', 'skpd', NULL, '2024-05-07 12:32:00', '2024-05-11 06:32:18'),
(18, 'Kecamatan Long Pahangai', 'longpahangai', NULL, '$2y$12$ZYg86GSpvBr1IK8wYNehL.n4bC6SVA3C6CMjKsWHVqt7vT/sNP52S', '7.01.0.00.0.00.17', 'skpd', NULL, '2024-05-07 12:32:00', '2024-05-11 06:32:18'),
(19, 'Kecamatan Long Bagun', 'longbagun', NULL, '$2y$12$qtnwCNp3aGky1xj.TqWdtO/A115nDLL2iOuWuTrra8YVl4q1Tbn0e', '7.01.0.00.0.00.18', 'skpd', NULL, '2024-05-13 00:13:08', '2024-05-13 00:13:08'),
(20, 'Kecamatan Laham', 'laham', NULL, '$2y$12$ZYg86GSpvBr1IK8wYNehL.n4bC6SVA3C6CMjKsWHVqt7vT/sNP52S', '7.01.0.00.0.00.19', 'skpd', NULL, '2024-05-07 12:32:00', '2024-05-11 06:32:18'),
(21, 'Kecamatan Long Hubung', 'longhubung', NULL, '$2y$12$ZYg86GSpvBr1IK8wYNehL.n4bC6SVA3C6CMjKsWHVqt7vT/sNP52S', '7.01.0.00.0.00.20', 'skpd', NULL, '2024-05-07 12:32:00', '2024-05-11 06:32:18'),
(22, 'Badan Kesatuan Bangsa dan Politik', 'kesbangpol', NULL, '$2y$12$ZYg86GSpvBr1IK8wYNehL.n4bC6SVA3C6CMjKsWHVqt7vT/sNP52S', '8.01.0.00.0.00.25', 'skpd', NULL, '2024-05-07 12:32:00', '2024-05-11 06:32:18'),
(23, 'Dinas Pendidikan dan Kebudayaan', 'disdikbud', NULL, '$2y$12$ZYg86GSpvBr1IK8wYNehL.n4bC6SVA3C6CMjKsWHVqt7vT/sNP52S', '1.01.2.22.5.04.01', 'skpd', NULL, '2024-05-07 12:32:00', '2024-05-11 06:32:18'),
(24, 'Dinas Kesehatan, Pengendalian Penduduk dan KB', 'dinkes', NULL, '$2y$12$ZYg86GSpvBr1IK8wYNehL.n4bC6SVA3C6CMjKsWHVqt7vT/sNP52S', '1.02.2.14.0.00.02', 'skpd', NULL, '2024-05-07 12:32:00', '2024-05-11 06:32:18'),
(25, 'Dinas Pekerjaan Umum dan Penataan Ruang, Perumahan dan Kawasan Pemukiman', 'pupr', NULL, '$2y$12$ZYg86GSpvBr1IK8wYNehL.n4bC6SVA3C6CMjKsWHVqt7vT/sNP52S', '1.03.1.04.0.00.03', 'skpd', NULL, '2024-05-07 12:32:00', '2024-05-11 06:32:18'),
(26, 'Dinas Sosial, Pemberdayaan Perempuan Perlindungan Anak', 'dinsos', NULL, '$2y$12$ZYg86GSpvBr1IK8wYNehL.n4bC6SVA3C6CMjKsWHVqt7vT/sNP52S', '1.06.2.08.0.00.05', 'skpd', NULL, '2024-05-07 12:32:00', '2024-05-11 06:32:18'),
(27, 'Dinas Kependudukan dan Pencatatan Sipil', 'dukcapil', NULL, '$2y$12$ZYg86GSpvBr1IK8wYNehL.n4bC6SVA3C6CMjKsWHVqt7vT/sNP52S', '2.12.0.00.0.00.07', 'skpd', NULL, '2024-05-07 12:32:00', '2024-05-11 06:32:18'),
(28, 'Dinas Pemberdayaan Masyarakat dan Pemerintahan Kampung', 'dpmpk', NULL, '$2y$12$ZYg86GSpvBr1IK8wYNehL.n4bC6SVA3C6CMjKsWHVqt7vT/sNP52S', '2.13.0.00.0.00.08', 'skpd', NULL, '2024-05-07 12:32:00', '2024-05-11 06:32:18'),
(29, 'Dinas Komunikasi dan Informatika, Statistik, dan Persandian', 'diskominfo', NULL, '$2y$12$ZYg86GSpvBr1IK8wYNehL.n4bC6SVA3C6CMjKsWHVqt7vT/sNP52S', '2.16.2.21.2.20.10', 'skpd', NULL, '2024-05-07 12:32:00', '2024-05-11 06:32:18'),
(30, 'Dinas Penanaman Modal dan Pelayanan Perijinan Terpadu', 'dpmptsp', NULL, '$2y$12$ZYg86GSpvBr1IK8wYNehL.n4bC6SVA3C6CMjKsWHVqt7vT/sNP52S', '2.18.0.00.0.00.11', 'skpd', NULL, '2024-05-07 12:32:00', '2024-05-11 06:32:18'),
(31, 'Dinas Pariwisata, Pemuda dan Olahraga', 'disparpora', NULL, '$2y$12$ZYg86GSpvBr1IK8wYNehL.n4bC6SVA3C6CMjKsWHVqt7vT/sNP52S', '3.26.2.19.0.00.12', 'skpd', NULL, '2024-05-07 12:32:00', '2024-05-11 06:32:18'),
(32, 'Dinas Ketahanan Pangan dan Pertanian', 'distan', NULL, '$2y$12$ZYg86GSpvBr1IK8wYNehL.n4bC6SVA3C6CMjKsWHVqt7vT/sNP52S', '3.27.2.09.3.25.13', 'skpd', NULL, '2024-05-07 12:32:00', '2024-05-11 06:32:18'),
(33, 'Sekretariat Daerah', 'setda', NULL, '$2y$12$ZYg86GSpvBr1IK8wYNehL.n4bC6SVA3C6CMjKsWHVqt7vT/sNP52S', '4.02.0.00.0.00.14', 'skpd', NULL, '2024-05-07 12:32:00', '2024-05-11 06:32:18'),
(34, 'Sekretariat DPRD', 'setdprd', NULL, '$2y$12$ZYg86GSpvBr1IK8wYNehL.n4bC6SVA3C6CMjKsWHVqt7vT/sNP52S', '4.02.0.00.0.00.15', 'skpd', NULL, '2024-05-07 12:32:00', '2024-05-11 06:32:18'),
(35, 'Badan Perencanaan Pembangunan, Penelitian dan Pengembangan Daerah', 'bappelitbang', NULL, '$2y$12$ZYg86GSpvBr1IK8wYNehL.n4bC6SVA3C6CMjKsWHVqt7vT/sNP52S', '5.01.5.02.5.05.21', 'skpd', NULL, '2024-05-07 12:32:00', '2024-05-11 06:32:18'),
(36, 'Badan Pendapatan Daerah', 'bapenda', NULL, '$2y$12$ZYg86GSpvBr1IK8wYNehL.n4bC6SVA3C6CMjKsWHVqt7vT/sNP52S', '5.02.0.00.0.00.22', 'skpd', NULL, '2024-05-07 12:32:00', '2024-05-11 06:32:18'),
(37, 'Badan Kepegawaian, Pendidikan dan Pelatihan', 'bkd', NULL, '$2y$12$ZYg86GSpvBr1IK8wYNehL.n4bC6SVA3C6CMjKsWHVqt7vT/sNP52S', '8.06.5.06.0.00.26', 'skpd', NULL, '2024-05-07 12:32:00', '2024-05-11 06:32:18'),
(38, 'Badan Pengelola Perbatasan Daerah', 'bppd', NULL, '$2y$12$ZYg86GSpvBr1IK8wYNehL.n4bC6SVA3C6CMjKsWHVqt7vT/sNP52S', '5.06.0.00.0.00.26', 'skpd', NULL, '2024-05-07 12:32:00', '2024-05-11 06:32:18'),
(39, 'bagian organisasi', 'bagian organisasi', NULL, '$2y$12$ZYg86GSpvBr1IK8wYNehL.n4bC6SVA3C6CMjKsWHVqt7vT/sNP52S', '4.01.2.16.0.00.14.0007', 'skpd', NULL, '2024-05-07 12:32:00', '2024-05-11 06:32:18'),
(40, 'Bagian Perekonomian dan Sumber Daya Alam', 'bagian perekonomian', NULL, '$2y$12$ZYg86GSpvBr1IK8wYNehL.n4bC6SVA3C6CMjKsWHVqt7vT/sNP52S', '4.01.2.16.0.00.14.0006', 'skpd', NULL, '2024-05-07 12:32:00', '2024-05-11 06:32:18'),
(41, 'Bagian Kesejahteraan Rakyat', 'bagian kesejahteraan', NULL, '$2y$12$ZYg86GSpvBr1IK8wYNehL.n4bC6SVA3C6CMjKsWHVqt7vT/sNP52S', '4.01.0.00.0.00.14.0003', 'skpd', NULL, '2024-05-07 12:32:00', '2024-05-11 06:32:18'),
(42, 'Bagian Umum', 'bagian umum', NULL, '$2y$12$ZYg86GSpvBr1IK8wYNehL.n4bC6SVA3C6CMjKsWHVqt7vT/sNP52S', '4.01.0.00.0.00.14.0004', 'skpd', NULL, '2024-05-07 12:32:00', '2024-05-11 06:32:18'),
(43, 'Bagian Protokol dan Komunikasi Pimpinan', 'bagian protokol', NULL, '$2y$12$ZYg86GSpvBr1IK8wYNehL.n4bC6SVA3C6CMjKsWHVqt7vT/sNP52S', '4.01.0.00.0.00.14.0005', 'skpd', NULL, '2024-05-07 12:32:00', '2024-05-11 06:32:18'),
(44, 'Bagian Pengadaan Barang Dan Jasa', 'bagian pengadaan', NULL, '$2y$12$ZYg86GSpvBr1IK8wYNehL.n4bC6SVA3C6CMjKsWHVqt7vT/sNP52S', '4.01.0.00.0.00.14.0008', 'skpd', NULL, '2024-05-07 12:32:00', '2024-05-11 06:32:18'),
(45, 'Bagian Hukum', 'bagian hukum', NULL, '$2y$12$ZYg86GSpvBr1IK8wYNehL.n4bC6SVA3C6CMjKsWHVqt7vT/sNP52S', '4.01.0.00.0.00.14.0002', 'skpd', NULL, '2024-05-07 12:32:00', '2024-05-11 06:32:18'),
(46, 'Bagian Administrasi Pembangunan', 'bagian administrasi', NULL, '$2y$12$ZYg86GSpvBr1IK8wYNehL.n4bC6SVA3C6CMjKsWHVqt7vT/sNP52S', '4.01.0.00.0.00.14.0010', 'skpd', NULL, '2024-05-07 12:32:00', '2024-05-11 06:32:18'),
(47, 'Bagian Pemerintahan', 'bagian pemerintahan', NULL, '$2y$12$ZYg86GSpvBr1IK8wYNehL.n4bC6SVA3C6CMjKsWHVqt7vT/sNP52S', '4.01.2.16.2.10.14.0001', 'skpd', NULL, '2024-05-07 12:32:00', '2024-05-11 06:32:18'),
(48, 'Satuan Polisi Pamong Praja', 'satpolpp', NULL, '$2y$12$ZYg86GSpvBr1IK8wYNehL.n4bC6SVA3C6CMjKsWHVqt7vT/sNP52S', '1.05.0.00.0.00.046', 'skpd', NULL, '2024-05-07 12:32:00', '2024-05-11 06:32:18'),
(49, 'Inspektorat', 'inspektorat', NULL, '$2y$12$ZYg86GSpvBr1IK8wYNehL.n4bC6SVA3C6CMjKsWHVqt7vT/sNP52S', '6.01.0.00.0.00.21', 'skpd', NULL, '2024-05-07 12:32:00', '2024-05-11 06:32:18'),
(50, 'tamu', 'tamu', NULL, '$2y$12$ZYg86GSpvBr1IK8wYNehL.n4bC6SVA3C6CMjKsWHVqt7vT/sNP52S', 'tamu', 'pimpinan', NULL, '2024-05-07 12:32:00', '2024-05-11 06:32:18'),
(51, 'PUSKESMAS LONG APARI', 'puskeslongapari', NULL, '$2y$12$ZYg86GSpvBr1IK8wYNehL.n4bC6SVA3C6CMjKsWHVqt7vT/sNP52S', '1.02.2.14.0.00.03', 'skpd', NULL, NULL, NULL),
(52, 'PUSKESMAS LONG PAHANGAI', 'puskeslongpahangai', NULL, '$2y$12$ZYg86GSpvBr1IK8wYNehL.n4bC6SVA3C6CMjKsWHVqt7vT/sNP52S', '1.02.2.14.0.00.04', 'skpd', NULL, NULL, NULL),
(53, 'PUSKESMAS LONG BAGUN', 'puskeslongbagun', NULL, '$2y$12$ZYg86GSpvBr1IK8wYNehL.n4bC6SVA3C6CMjKsWHVqt7vT/sNP52S', '1.02.2.14.0.00.05', 'skpd', NULL, NULL, NULL),
(54, 'PUSKESMAS LAHAM', 'puskeslaham', NULL, '$2y$12$ZYg86GSpvBr1IK8wYNehL.n4bC6SVA3C6CMjKsWHVqt7vT/sNP52S', '1.02.2.14.0.00.06', 'skpd', NULL, NULL, NULL),
(55, 'PUSKESMAS LONG HUBUNG', 'puskeslonghubung', NULL, '$2y$12$ZYg86GSpvBr1IK8wYNehL.n4bC6SVA3C6CMjKsWHVqt7vT/sNP52S', '1.02.2.14.0.00.07', 'skpd', NULL, NULL, NULL),
(56, 'PUSKESMAS MEMAHAK BESAR', 'puskesmemahakbesar', NULL, '$2y$12$ZYg86GSpvBr1IK8wYNehL.n4bC6SVA3C6CMjKsWHVqt7vT/sNP52S', '1.02.2.14.0.00.08', 'skpd', NULL, NULL, NULL),
(57, 'RUMAH SAKIT PRATAMA GERBANG SEHAT MAHAKAM ULU', 'pratamagerbang', NULL, '$2y$12$ZYg86GSpvBr1IK8wYNehL.n4bC6SVA3C6CMjKsWHVqt7vT/sNP52S', '1.02.2.14.0.00.09', 'skpd', NULL, NULL, NULL),
(58, 'RUMAH SAKIT PRATAMA NAWACITA DATAH DAVE', 'pratamanawacita', NULL, '$2y$12$ZYg86GSpvBr1IK8wYNehL.n4bC6SVA3C6CMjKsWHVqt7vT/sNP52S', '1.02.2.14.0.00.10', 'skpd', NULL, NULL, NULL);

--
-- Indexes for dumped tables
--

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `users_email_unique` (`email`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=59;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
