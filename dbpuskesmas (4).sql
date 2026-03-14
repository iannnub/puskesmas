-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Waktu pembuatan: 05 Feb 2026 pada 16.47
-- Versi server: 10.4.32-MariaDB
-- Versi PHP: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `dbpuskesmas`
--

-- --------------------------------------------------------

--
-- Struktur dari tabel `tbl_jenis_obat`
--

CREATE TABLE `tbl_jenis_obat` (
  `id_jenis_obat` int(11) NOT NULL,
  `nama_jenis_obat` varchar(50) NOT NULL COMMENT 'Cth: IFK, BLUD'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `tbl_jenis_obat`
--

INSERT INTO `tbl_jenis_obat` (`id_jenis_obat`, `nama_jenis_obat`) VALUES
(2, 'BLUD'),
(1, 'IFK');

-- --------------------------------------------------------

--
-- Struktur dari tabel `tbl_kategori_obat`
--

CREATE TABLE `tbl_kategori_obat` (
  `id_kategori_obat` int(11) NOT NULL,
  `id_jenis_obat` int(11) NOT NULL,
  `nama_kategori` varchar(100) NOT NULL COMMENT 'Cth: UMUM, PROGRAM IMUNISASI, BAHAN KIMIA'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `tbl_kategori_obat`
--

INSERT INTO `tbl_kategori_obat` (`id_kategori_obat`, `id_jenis_obat`, `nama_kategori`) VALUES
(1, 1, 'UMUM'),
(2, 1, 'PROGRAM OBAT ARV'),
(3, 1, 'PROGRAM IMUNISASI'),
(4, 1, 'REAGENT'),
(5, 1, 'PROGRAM REAGEN HIV'),
(6, 1, 'BAHAN HABIS PAKAI GIGI'),
(7, 1, 'BARANG LABKESDA'),
(8, 1, 'BHP : VIRAL LOAD (HIBAH)'),
(9, 1, 'PROGRAM DBD'),
(10, 1, 'ITEM BARU'),
(11, 2, 'OBAT-OBATTAN'),
(12, 2, 'BELANJA SUKU CADANG ALAT KEDOKTERAN'),
(13, 2, 'BELANJA BAHAN LAINNYA'),
(14, 2, 'BELANJA BAHAN KIMIA');

-- --------------------------------------------------------

--
-- Struktur dari tabel `tbl_konfigurasi`
--

CREATE TABLE `tbl_konfigurasi` (
  `id_konfig` int(11) NOT NULL,
  `setting_key` varchar(50) NOT NULL COMMENT 'e.g., nama_puskesmas, dll',
  `setting_value` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Struktur dari tabel `tbl_log_stok`
--

CREATE TABLE `tbl_log_stok` (
  `id_log` bigint(20) NOT NULL,
  `tgl_log` datetime NOT NULL,
  `id_obat` int(11) NOT NULL,
  `id_unit` int(11) NOT NULL,
  `sumber_data` enum('Penerimaan','Transfer','Resep','Stok Opname','Manual') NOT NULL COMMENT 'Menyederhanakan query Laporan Kartu Stok',
  `stok_sebelum` int(11) NOT NULL,
  `masuk` int(11) NOT NULL DEFAULT 0,
  `keluar` int(11) NOT NULL DEFAULT 0,
  `stok_sesudah` int(11) NOT NULL,
  `keterangan` varchar(255) DEFAULT NULL,
  `id_referensi_transaksi` bigint(20) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `tbl_log_stok`
--

INSERT INTO `tbl_log_stok` (`id_log`, `tgl_log`, `id_obat`, `id_unit`, `sumber_data`, `stok_sebelum`, `masuk`, `keluar`, `stok_sesudah`, `keterangan`, `id_referensi_transaksi`) VALUES
(1, '2025-11-11 14:15:00', 1, 1, 'Penerimaan', 0, 5, 0, 5, 'ian', 1),
(2, '2025-11-11 14:16:39', 1, 1, 'Transfer', 5, 0, 3, 2, 'Transfer Keluar ke ', 1),
(3, '2025-11-11 14:16:39', 1, 2, 'Transfer', 0, 3, 0, 3, 'Transfer Masuk dari ', 1),
(4, '2025-11-11 14:29:40', 1, 2, 'Stok Opname', 3, 2, 0, 5, 'tes', 11),
(5, '2025-11-11 15:31:00', 2, 1, 'Penerimaan', 0, 5, 0, 5, '', 2),
(6, '2025-11-11 15:31:22', 1, 1, 'Transfer', 2, 0, 1, 1, 'Request #2', 2),
(7, '2025-11-11 15:31:22', 1, 3, 'Transfer', 0, 1, 0, 1, 'Request #2', 2),
(8, '2025-11-11 15:31:22', 2, 1, 'Transfer', 5, 0, 4, 1, 'Request #2', 3),
(9, '2025-11-11 15:31:22', 2, 3, 'Transfer', 0, 4, 0, 4, 'Request #2', 3),
(10, '2025-11-18 10:09:00', 1, 4, 'Penerimaan', 0, 3, 0, 3, 'adgahs', 3),
(11, '2025-11-18 10:10:41', 1, 1, 'Transfer', 1, 0, 1, 0, 'Transfer Keluar ke ', 4),
(12, '2025-11-18 10:10:41', 1, 4, 'Transfer', 3, 1, 0, 4, 'Transfer Masuk dari ', 4),
(13, '2025-11-18 10:10:00', 1, 4, 'Resep', 4, 0, 1, 3, 'Pemakaian Resep Pasien: abcd', 1),
(14, '2025-11-18 10:27:38', 2, 1, 'Transfer', 1, 0, 1, 0, 'Request #1', 5),
(15, '2025-11-18 10:27:38', 2, 3, 'Transfer', 4, 1, 0, 5, 'Request #1', 5),
(16, '2026-02-03 19:31:00', 1, 3, 'Resep', 1, 0, 1, 0, 'Pemakaian Resep Pasien: ian', 2),
(17, '2026-02-03 19:36:00', 1, 2, 'Penerimaan', 5, 10, 0, 15, 'blabla', 4),
(19, '2026-02-03 19:48:00', 2, 3, 'Resep', 5, 0, 1, 4, 'Pemakaian Resep Pasien: ian', 4);

-- --------------------------------------------------------

--
-- Struktur dari tabel `tbl_obat`
--

CREATE TABLE `tbl_obat` (
  `id_obat` int(11) NOT NULL,
  `kode_obat` varchar(20) NOT NULL,
  `nama_obat` varchar(255) NOT NULL,
  `satuan` varchar(30) NOT NULL,
  `id_kategori_obat` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `tbl_obat`
--

INSERT INTO `tbl_obat` (`id_obat`, `kode_obat`, `nama_obat`, `satuan`, `id_kategori_obat`) VALUES
(1, 'IFK-0001', 'Acyclovir krim', 'tube', 1),
(2, 'IFK-0002', 'Acyclovir tablet 400 mg', 'tablet', 1),
(3, 'IFK-0003', 'Alat Suntik 10 ml', 'pcs', 1),
(4, 'IFK-0004', 'Alat suntik sekali pakai 1 ml', 'pcs', 1),
(5, 'IFK-0005', 'Alat suntik sekali pakai 2,5 ml', 'pcs', 1),
(6, 'IFK-0006', 'Alat suntik sekali pakai 5 ml', 'pcs', 1),
(7, 'IFK-0007', 'Albendazol Suspensi 200mg/5 ml', 'botol', 1),
(8, 'IFK-0008', 'Albendazol Tablet 400 mg', 'tablet', 1),
(9, 'IFK-0009', 'Alcohol Swab DBHCT', 'pcs', 1),
(10, 'IFK-0010', 'Alkacide Desinfectant 1 L', 'botol', 1),
(11, 'IFK-0011', 'Alkazyme Sachet 25 mg', 'pack', 1),
(12, 'IFK-0012', 'Alopurinol Tablet 100 mg', 'tablet', 1),
(13, 'IFK-0013', 'Alopurinol Tablet 300 mg', 'tablet', 1),
(14, 'IFK-0014', 'Alpara tablet', 'tablet', 1),
(15, 'IFK-0015', 'Ambroxol sirup 60 ml', 'botol', 1),
(16, 'IFK-0016', 'Ambroxol tablet 30 mg', 'tablet', 1),
(17, 'IFK-0017', 'Aminophylin injeksi 24mg/ml', 'ampul', 1),
(18, 'IFK-0018', 'Aminophylin Tablet 150 mg', 'tablet', 1),
(19, 'IFK-0019', 'Aminophylin Tablet 200 mg', 'tablet', 1),
(20, 'IFK-0020', 'Amitriptilina Tablet 25 mg', 'tablet', 1),
(21, 'IFK-0021', 'Amlodipin 10 mg', 'tablet', 1),
(22, 'IFK-0022', 'Amlodipin 5 mg', 'tablet', 1),
(23, 'IFK-0023', 'Amoxicillin kaplet 500 mg', 'kaplet', 1),
(24, 'IFK-0024', 'Amoxicillin kapsul 250 mg', 'kapsul', 1),
(25, 'IFK-0025', 'Amoxicillin sirup kering 125ml/5ml', 'botol', 1),
(26, 'IFK-0026', 'Amoxicillin sirup kering 250 mg/5ml forte', 'botol', 1),
(27, 'IFK-0027', 'Ampicillin Injeksi 1 gram', 'vial', 1),
(28, 'IFK-0028', 'Antalgin Injeksi 24 mg/ml', 'ampul', 1),
(29, 'IFK-0029', 'Antalgin Tablet 500 mg', 'tablet', 1),
(30, 'IFK-0030', 'Antasida DOEN Suspensi', 'botol', 1),
(31, 'IFK-0031', 'Antasida DOEN Tablet', 'tablet', 1),
(32, 'IFK-0032', 'Antidia', 'supp', 1),
(33, 'IFK-0033', 'Anti Hemmoroid Suppositoria', 'tube', 1),
(34, 'IFK-0034', 'Antimigren DOEN (Ergotamina Trt + Kofeina)', 'pot', 1),
(35, 'IFK-0035', 'Antifungi DOEN (As Benzoat 3%+As Salisilat 6%)', 'tablet', 1),
(36, 'IFK-0036', 'Antimigren DOEN (Ergotamina Trt + Kofeina)', 'pcs', 1),
(37, 'IFK-0037', 'APD (Hazmat/Coverall) L', 'pcs', 1),
(38, 'IFK-0038', 'APD (Hazmat/Coverall) XL', 'pcs', 1),
(39, 'IFK-0039', 'APD (Hazmat/Coverall) XXL', 'ampul', 1),
(40, 'IFK-0040', 'Aqua Pro Injeksi Steril 20 ml', 'botol', 1),
(41, 'IFK-0041', 'Aquadest Steril 1000 ml', 'tube', 1),
(42, 'IFK-0042', 'Aquagel Steril Jely 82 gr', 'botol', 1),
(43, 'IFK-0043', 'Aripiprazol sirup 1mg/ml', 'tablet', 1),
(44, 'IFK-0044', 'Aripiprazol tablet 10 mg', 'tablet', 1),
(45, 'IFK-0045', 'Aripiprazol tablet 15 mg', 'tablet', 1),
(46, 'IFK-0046', 'Aripiprazol tablet 5 mg', 'vial', 1),
(47, 'IFK-0047', 'Artesunate injeksi 60 mg', 'tablet', 1),
(48, 'IFK-0048', 'Asam Askorbat (Vit C) tablet 250 mg', 'tablet', 1),
(49, 'IFK-0049', 'Asam Askorbat (Vit C) tablet 50 mg', 'tablet', 1),
(50, 'IFK-0050', 'Asam Askorbat (Vit C) tablet 500 mg', 'kaplet', 1),
(51, 'IFK-0051', 'Asam Mefenamat kaplet 500 mg', 'ampul', 1),
(52, 'IFK-0052', 'Asam Traneksamat injeksi 100mg/ml', 'tablet', 1),
(53, 'IFK-0053', 'Asam Traneksamat tablet 500mg', 'botol', 1),
(54, 'IFK-0054', 'Asam Valproat sirup 250mg/5ml', 'tablet', 1),
(55, 'IFK-0055', 'Asam Valproat tablet 250 mg', 'tablet', 1),
(56, 'IFK-0056', 'Asam Valproat tablet 500 mg', 'botol', 1),
(57, 'IFK-0057', 'Aseptic Gel 500 ml', 'tablet', 1),
(58, 'IFK-0058', 'Asetosal tablet 100 mg', 'tablet', 1),
(59, 'IFK-0059', 'Asetosal tablet 80 mg', 'pcs', 1),
(60, 'IFK-0060', 'Askina Calgitrol Ag 10x10 cm', 'ampul', 1),
(61, 'IFK-0061', 'Atropina Sulfat injeksi', 'tablet', 1),
(62, 'IFK-0062', 'Attapulgit 600 mg', 'tablet', 1),
(63, 'IFK-0063', 'Attapulgit 630 mg', 'tablet', 1),
(64, 'IFK-0064', 'Attapulgit 650 mg', 'tablet', 1),
(65, 'IFK-0065', 'Azithromisin tablet 500 mg', 'kaplet', 1),
(66, 'IFK-0066', 'Becefort tab', 'kotak', 1),
(67, 'IFK-0067', 'Becom C kaplet', 'tablet', 1),
(68, 'IFK-0068', 'Bedak Salisil 2%', 'vial', 1),
(69, 'IFK-0069', 'Bedaqullin tablet 100 mg', 'tablet', 1),
(70, 'IFK-0070', 'Benzatin Benzil Penicillin injeksi', 'tube', 1),
(71, 'IFK-0071', 'Betahistin Mesilat 6 mg', 'tube', 1),
(72, 'IFK-0072', 'Betametason krim', 'supp', 1),
(73, 'IFK-0073', 'Betason-N krim', 'tablet', 1),
(74, 'IFK-0074', 'Bisakodil Suppo 10 mg', 'set', 1),
(75, 'IFK-0075', 'Bisoprolol tablet 5 mg', 'botol', 1),
(76, 'IFK-0076', 'Blood Lancet', 'tablet', 1),
(77, 'IFK-0077', 'Caladine lotion 60ml', 'tablet', 1),
(78, 'IFK-0078', 'Calcium Gluconas infus', 'tablet', 1),
(79, 'IFK-0079', 'Candesartan tablet 8 mg', 'tablet', 1),
(80, 'IFK-0080', 'Captopril tablet 12,5 mg', 'sachet', 1),
(81, 'IFK-0081', 'Captopril tablet 25 mg', 'sachet', 1),
(82, 'IFK-0082', 'Captopril tablet 50 mg', 'sachet', 1),
(83, 'IFK-0083', 'Catgut Chromic 2/0 + jarum', 'sachet', 1),
(84, 'IFK-0084', 'Catgut Chromic 3/0 + jarum', 'tablet', 1),
(85, 'IFK-0085', 'Catgut Plain 2/0 + jarum', 'kapsul', 1),
(86, 'IFK-0086', 'Catgut Plain 3/0 + jarum', 'tablet', 1),
(87, 'IFK-0087', 'Caviplex', 'tablet', 1),
(88, 'IFK-0088', 'Cefadroxil kapsul 500 mg', 'vial', 1),
(89, 'IFK-0089', 'Cefixime tablet 100mg', 'vial', 1),
(90, 'IFK-0090', 'Cefixime tablet 200mg', 'botol', 1),
(91, 'IFK-0091', 'Cefotaxim injeksi', 'botol', 1),
(92, 'IFK-0092', 'Ceftriaxon injeksi', 'botol', 1),
(93, 'IFK-0093', 'Cendo Lyteer', 'tablet', 1),
(94, 'IFK-0094', 'Cendo Citrol', 'vial', 1),
(95, 'IFK-0095', 'Cetirizine sirup 5mg/5ml', 'kapsul', 1),
(96, 'IFK-0096', 'Cetirizine tablet 10mg', 'tube', 1),
(97, 'IFK-0097', 'Chloramphenicol injeksi 1 gram', 'botol', 1),
(98, 'IFK-0098', 'Chloramphenicol kapsul 250 mg', 'botol', 1),
(99, 'IFK-0099', 'Chloramphenicol Salep Mata 1%', 'botol', 1),
(100, 'IFK-0100', 'Chloramphenicol sirup - 125 mg/ml -60ml', 'botol', 1),
(101, 'IFK-0101', 'Chloramphenicol tetes mata 0,5% - 5 ml', 'botol', 1),
(102, 'IFK-0102', 'Chloramphenicol tetes telinga 3%', 'tablet', 1),
(103, 'IFK-0103', 'Chlorhexidine gluconat 0.5%', 'tablet', 1),
(104, 'IFK-0104', 'Chlorhexidine gluconat 4%', 'ampul', 1),
(105, 'IFK-0105', 'Chloroquin tablet 150 mg', 'tablet', 1),
(106, 'IFK-0106', 'Chlorpheniramin Maleat (CTM) tablet 4 mg', 'tablet', 1),
(107, 'IFK-0107', 'Chlorpromazin injeksi 25mg/ml', 'kapsul', 1),
(108, 'IFK-0108', 'Chlorpromazin tablet 100 mg', 'kapsul', 1),
(109, 'IFK-0109', 'Chlorpromazin tablet 25 mg', 'kapsul', 1),
(110, 'IFK-0110', 'Ciprofloxacin kapsul 500 mg', 'tablet', 1),
(111, 'IFK-0111', 'Clindamycin kapsul 150 mg', 'tablet', 1),
(112, 'IFK-0112', 'Clindamycin kapsul 300 mg', 'tablet', 1),
(113, 'IFK-0113', 'Clobazam 10 mg', 'botol', 1),
(114, 'IFK-0114', 'Clofazimine tablet 50 mg', 'kaplet', 1),
(115, 'IFK-0115', 'Clofazimine tablet 100 mg', 'tablet', 1),
(116, 'IFK-0116', 'clopidogrel', 'kapsul', 1),
(117, 'IFK-0117', 'Cloretyl (Endo Frost) Spray', 'botol', 1),
(118, 'IFK-0118', 'Clotiazepam (Neuroval) kaplet', 'tablet', 1),
(119, 'IFK-0119', 'Clozapin 25 mg', 'tablet', 1),
(120, 'IFK-0120', 'Corsafen 500 mg', 'pcs', 1),
(121, 'IFK-0121', 'Cotrimoxazol sirup', 'botol', 1),
(122, 'IFK-0122', 'Cotrimoxazol tablet', 'tablet', 1),
(123, 'IFK-0123', 'Cotrimoxazol tablet Forte', 'ampul', 1),
(124, 'IFK-0124', 'Cover Glass', 'tablet', 1),
(125, 'IFK-0125', 'Curcuma sirup', 'tablet', 1),
(126, 'IFK-0126', 'Curcuma tablet', 'lembar', 1),
(127, 'IFK-0127', 'Cyanocobalamin (Vit B12) injeksi', 'ampul', 1),
(128, 'IFK-0128', 'Cycloserin tablet 125 mg', 'tablet', 1),
(129, 'IFK-0129', 'Cycloserin tablet 250 mg', 'tablet', 1),
(130, 'IFK-0130', 'Daryan Tulle : Tramisetin Sulfat 10 x 10 cm', 'tablet', 1),
(131, 'IFK-0131', 'Deksametason Injeksi 5mg/ml', 'tube', 1),
(132, 'IFK-0132', 'Deksametason tablet 0,5 mg', 'botol', 1),
(133, 'IFK-0133', 'Delamanid tablet 50 mg', 'botol', 1),
(134, 'IFK-0134', 'Demacolin tablet', 'tube', 1),
(135, 'IFK-0135', 'Dermazin salep kulit 25 gram', 'tube', 1),
(136, 'IFK-0136', 'Dextran infus - 500 ml', 'ampul', 1),
(137, 'IFK-0137', 'Dextral sirup', 'tablet', 1),
(138, 'IFK-0138', 'Diazepam enema 10 mg/2,5 ml (Stesolid)', 'tablet', 1),
(139, 'IFK-0139', 'Diazepam enema 5 mg/2,5 ml (Stesolid)', 'tablet', 1),
(140, 'IFK-0140', 'Diazepam Injeksi 5mg/ml', 'tablet', 1),
(141, 'IFK-0141', 'Diazepam tablet 2 mg', 'ampul', 1),
(142, 'IFK-0142', 'Diazepam tablet 5 mg', 'botol', 1),
(143, 'IFK-0143', 'Diethylcarbamazine', 'botol', 1),
(144, 'IFK-0144', 'Digoksina tablet 0,25 mg', 'tablet', 1),
(145, 'IFK-0145', 'Dimenhidrinate tab', 'kapsul', 1),
(146, 'IFK-0146', 'Diphenhidramina injeksi 10 mg', 'ampul', 1),
(147, 'IFK-0147', 'Disinfectant 1 Liter', 'kaplet', 1),
(148, 'IFK-0148', 'Disinfectant 5 Liter', 'kapsul', 1),
(149, 'IFK-0149', 'Domperidon tablet 10 mg', 'botol', 1),
(150, 'IFK-0150', 'Doxycyclin kapsul 100 mg', 'tablet', 1),
(151, 'IFK-0151', 'Epinephrina injeksi', 'botol', 1),
(152, 'IFK-0152', 'Erithromicin kaplet 500 mg', 'botol', 1),
(153, 'IFK-0153', 'Erithromicin kapsul 250 mg', 'tablet', 1),
(154, 'IFK-0154', 'Erithromicin sirup kering 60 ml', 'botol', 1),
(155, 'IFK-0155', 'Erysanbe 500mg Tab', 'pcs', 1),
(156, 'IFK-0156', 'Etambutol (E) tablet 400 mg', 'pcs', 1),
(157, 'IFK-0157', 'Etanol 70%', 'botol', 1),
(158, 'IFK-0158', 'Etanol 95 %', 'botol', 1),
(159, 'IFK-0159', 'Ethionamid tablet 250 mg', 'tablet', 1),
(160, 'IFK-0160', 'Etil Klorida semprot', 'pcs', 1),
(161, 'IFK-0161', 'Face Shield Helm', 'pcs', 1),
(162, 'IFK-0162', 'Face Shield Kacamata', 'ampul', 1),
(163, 'IFK-0163', 'Feeding tube No. 10', 'kapsul', 1),
(164, 'IFK-0164', 'Feeding tube No. 16', 'ampul', 1),
(165, 'IFK-0165', 'Feeding tube No. 5', 'tablet', 1),
(166, 'IFK-0166', 'Feeding tube No. 6', 'tablet', 1),
(167, 'IFK-0167', 'Feeding tube No. 8', 'tablet', 1),
(168, 'IFK-0168', 'Fenitoin Injeksi 50 mg/ml', 'ampul', 1),
(169, 'IFK-0169', 'Fenitoin kapsul 100 mg', 'ampul', 1),
(170, 'IFK-0170', 'Fenobarbital Injeksi 50mg/ml-2ml', 'tablet', 1),
(171, 'IFK-0171', 'Fenobarbital tablet 30 mg', 'Tablet', 1),
(172, 'IFK-0172', 'Fenoksimetil Penisilin tablet 250 mg', 'tablet', 1),
(173, 'IFK-0173', 'Fenoksimetil Penisilin tablet 500 mg', 'tablet', 1),
(174, 'IFK-0174', 'Fitomenadion (Vit K) injeksi 10mg/ml', 'ampul', 1),
(175, 'IFK-0175', 'Fitomenadion (Vit K) injeksi 2 mg/ml', 'ampul', 1),
(176, 'IFK-0176', 'Fitomenadion (Vit K) tablet 10 mg', 'tablet', 1),
(177, 'IFK-0177', 'Flagystatin ovula 500mg', 'tablet', 1),
(178, 'IFK-0178', 'Fluconazole 150 mg', 'tablet', 1),
(179, 'IFK-0179', 'Fluoksetin 10 mg', 'tablet', 1),
(180, 'IFK-0180', 'Folley Catheter No. 14', 'pcs', 1),
(181, 'IFK-0181', 'Folley Catheter No. 16', 'pcs', 1),
(182, 'IFK-0182', 'Folley Catheter No. 18', 'pcs', 1),
(183, 'IFK-0183', 'Furosemida tablet 40 mg', 'tablet', 1),
(184, 'IFK-0184', 'Furosemide injeksi 10mg/ml', 'ampul', 1),
(185, 'IFK-0185', 'Garam Oralit', 'sachet', 1),
(186, 'IFK-0186', 'Gastrucid sirup', 'botol', 1),
(187, 'IFK-0187', 'Gastrucid tablet', 'tablet', 1),
(188, 'IFK-0188', 'Genoint 3.5gr salep mata', 'tablet', 1),
(189, 'IFK-0189', 'Gentamycin injeksi 80 mg/2 ml', 'tablet', 1),
(190, 'IFK-0190', 'Gentamycin salep kulit', 'botol', 1),
(191, 'IFK-0191', 'Gentamycin salep mata', 'botol', 1),
(192, 'IFK-0192', 'Gentamycin tetes mata', 'botol', 1),
(193, 'IFK-0193', 'Gentian violet larutan 1%', 'botol', 1),
(194, 'IFK-0194', 'Glibenklamida tablet 5 mg', 'botol', 1),
(195, 'IFK-0195', 'Glimepiride 1 mg', 'ampul', 1),
(196, 'IFK-0196', 'Glimepiride 2 mg', 'botol', 1),
(197, 'IFK-0197', 'Gliserin 1 liter', 'botol', 1),
(198, 'IFK-0198', 'Gliserin 100 ml', 'tablet', 1),
(199, 'IFK-0199', 'Glukosa 5% + Natrium Klorida 0.9% infus (WIDA)', 'tablet', 1),
(200, 'IFK-0200', 'Glukosa lar. infus 0,225% steril', 'botol', 1),
(201, 'IFK-0201', 'Glukosa lar. infus 10% steril', 'Ampul', 1),
(202, 'IFK-0202', 'Glukosa lar. infus 40% steril', 'Ampul', 1),
(203, 'IFK-0203', 'Glukosa lar. infus 5% steril', 'tablet', 1),
(204, 'IFK-0204', 'Goggles / Kacamata Pelindung', 'tablet', 1),
(205, 'IFK-0205', 'Griseofulvin tablet 125 mg', 'tablet', 1),
(206, 'IFK-0206', 'Griseofulvin tablet 500 mg', 'tablet', 1),
(207, 'IFK-0207', 'Guanistrep syr', 'botol', 1),
(208, 'IFK-0208', 'Haloperidol Drops 15ml', 'botol', 1),
(209, 'IFK-0209', 'Haloperidol Decanoate lar Injeksi 50 mg/ml', 'botol', 1),
(210, 'IFK-0210', 'Haloperidol injeksi 5 mg/ml', 'botol', 1),
(211, 'IFK-0211', 'Haloperidol tablet 0.5 mg', 'tablet', 1),
(212, 'IFK-0212', 'Haloperidol tablet 1.5 mg', 'tube', 1),
(213, 'IFK-0213', 'Haloperidol tablet 2 mg', 'Botol', 1),
(214, 'IFK-0214', 'Haloperidol tablet 5 mg', 'botol', 1),
(215, 'IFK-0215', 'Hand Sanitizer 500 ml', 'tablet', 1),
(216, 'IFK-0216', 'Hand Sanitizer 1 Liter', 'tablet', 1),
(217, 'IFK-0217', 'Hand Sanitizer 5 Liter', 'tablet', 1),
(218, 'IFK-0218', 'Hand wash 500 ml', 'tablet', 1),
(219, 'IFK-0219', 'Herbakof sirup', 'set', 1),
(220, 'IFK-0220', 'Hidrogen Peroksida (H2O2)', 'set', 1),
(221, 'IFK-0221', 'Hidroklorotiazida tablet 25 mg', 'Test Kit', 1),
(222, 'IFK-0222', 'Hidrokortison Krim 2,5%', 'kaplet', 1),
(223, 'IFK-0223', 'Hydroxyethyl Starch (Voluven) infus', 'kaplet', 1),
(224, 'IFK-0224', 'Ibuprofen suspensi 100 mg/5ml', 'tablet', 1),
(225, 'IFK-0225', 'Ibuprofen tablet 200 mg', 'biji', 1),
(226, 'IFK-0226', 'Ibuprofen tablet 400 mg', 'biji', 1),
(227, 'IFK-0227', 'Ikan Gabus tablet', 'biji', 1),
(228, 'IFK-0228', 'Ikaneuron', 'biji', 1),
(229, 'IFK-0229', 'Infusion set anak IFK', 'botol', 1),
(230, 'IFK-0230', 'Infusion set dewasa IFK', 'botol', 1),
(231, 'IFK-0231', 'Iodina Test', 'ampul', 1),
(232, 'IFK-0232', 'Isoniazid 100 mg', 'tablet', 1),
(233, 'IFK-0233', 'Isoniazid 300 mg', 'lembar', 1),
(234, 'IFK-0234', 'Isosorbid Dinitrat tablet 5 mg', 'lembar', 1),
(235, 'IFK-0235', 'IV Catether No.18', 'botol', 1),
(236, 'IFK-0236', 'IV Catether No.20', 'roll', 1),
(237, 'IFK-0237', 'IV Catether No.22', 'rol', 1),
(238, 'IFK-0238', 'IV Catether No.24', 'tablet', 1),
(239, 'IFK-0239', 'IV Catether No.26', 'box', 1),
(240, 'IFK-0240', 'KA-EN infus 500 ml', 'bungkus', 1),
(241, 'IFK-0241', 'Kalamin lotion', 'rol', 1),
(242, 'IFK-0242', 'Kalsium Glukonat injeksi', 'rol', 1),
(243, 'IFK-0243', 'Kalsium Laktat tablet 500mg.', 'rol', 1),
(244, 'IFK-0244', 'Kantong Limbah Medis L', 'bungkus', 1),
(245, 'IFK-0245', 'Kantong Limbah Medis M', 'tablet', 1),
(246, 'IFK-0246', 'Kaolin Pektin (Neo Kaominal) sirup', 'tablet', 1),
(247, 'IFK-0247', 'Kapas pembalut 40/80', 'tablet', 1),
(248, 'IFK-0248', 'Kapas Pembalut 250 gram', 'tablet', 1),
(249, 'IFK-0249', 'Karbamazepin tablet 200 mg', 'botol', 1),
(250, 'IFK-0250', 'Kasa Hidrofil 16x16cm', 'botol', 1),
(251, 'IFK-0251', 'Kasa Hidrofil 2mx80cm', 'kapsul', 1),
(252, 'IFK-0252', 'Kasa Hidrofil 4mx10 cm', 'botol', 1),
(253, 'IFK-0253', 'Kasa Hidrofil 4mx15cm', 'tablet', 1),
(254, 'IFK-0254', 'Kasa Hidrofil 4mx3cm', 'tablet', 1),
(255, 'IFK-0255', 'Kasa Kompres 40x40cm', 'tablet', 1),
(256, 'IFK-0256', 'Ketoconazol krim 2%', 'kaplet', 1),
(257, 'IFK-0257', 'Ketokonazol tablet', 'ampul', 1),
(258, 'IFK-0258', 'Ketoprofen 50 mg', 'ampul', 1),
(259, 'IFK-0259', 'Kodein tablet 10mg', 'botol', 1),
(260, 'IFK-0260', 'Kompolax sirup', 'tablet', 1),
(261, 'IFK-0261', 'Laktulosa sirup 3,335g/5 ml', 'tablet', 1),
(262, 'IFK-0262', 'Lanzoprazol kapsul 30 mg', 'tablet', 1),
(263, 'IFK-0263', 'Laxadin sirup 60 ml', 'botol', 1),
(264, 'IFK-0264', 'Levofloxacin tablet 250 mg', 'botol', 1),
(265, 'IFK-0265', 'Levofloxacin tablet 500 mg', 'botol', 1),
(266, 'IFK-0266', 'Levothyroxin tab 100mcg', 'ampul', 1),
(267, 'IFK-0267', 'Lexavit kaplet', 'ampul', 1),
(268, 'IFK-0268', 'Lidocain Compositum + Epinephrine Injeksi', 'tablet', 1),
(269, 'IFK-0269', 'Lidocain injeksi 2%', 'pcs', 1),
(270, 'IFK-0270', 'Lidokain (Xylocain) spray oral 10%', 'pcs', 1),
(271, 'IFK-0271', 'Linezolid tablet 600 mg', 'biji', 1),
(272, 'IFK-0272', 'lodecon tab', 'pcs', 1),
(273, 'IFK-0273', 'Loperamide tablet 2 mg', 'pcs', 1),
(274, 'IFK-0274', 'Loratadin', 'pcs', 1),
(275, 'IFK-0275', 'Lysofit sirup', 'biji', 1),
(276, 'IFK-0276', 'Lysol Desinfectant 1000 ml', 'biji', 1),
(277, 'IFK-0277', 'Lytacur sirup 60 ml', 'biji', 1),
(278, 'IFK-0278', 'Magnesium Sulfat injeksi 20%', 'biji', 1),
(279, 'IFK-0279', 'Magnesium Sulfat injeksi 40%', 'biji', 1),
(280, 'IFK-0280', 'Maltodextrin (TABURIA)', 'biji', 1),
(281, 'IFK-0281', 'Masker 3 Ply Earloop (APBN-TB)', 'tablet', 1),
(282, 'IFK-0282', 'Masker 3 Ply Disposable (HIV-AIDS)', 'botol', 1),
(283, 'IFK-0283', 'Masker Bedah 4 Ply Supersoft', 'botol', 1),
(284, 'IFK-0284', 'Masker Disposable N95', 'botol', 1),
(285, 'IFK-0285', 'Masker KN-95', 'tablet', 1),
(286, 'IFK-0286', 'Masker FFP-2NR', 'kapsul', 1),
(287, 'IFK-0287', 'Masker Oksigen Anak', 'ampul', 1),
(288, 'IFK-0288', 'Masker Oksigen Bayi', 'tablet', 1),
(289, 'IFK-0289', 'Masker Oksigen Dewasa', 'tablet', 1),
(290, 'IFK-0290', 'Masker Surgical 3 Ply Earloop', 'tablet', 1),
(291, 'IFK-0291', 'Masker Surgical 3 Ply Headloop (Hijab)', 'ampul', 1),
(292, 'IFK-0292', 'Masker Surgical 3 Ply Tie On', 'tablet', 1),
(293, 'IFK-0293', 'Mebendazol tablet 100 mg', 'ampul', 1),
(294, 'IFK-0294', 'Meliseptol Foam', 'ampul', 1),
(295, 'IFK-0295', 'Meliseptol Wipes Box', 'botol', 1),
(296, 'IFK-0296', 'Meliseptol Wipes Refill', 'tablet', 1),
(297, 'IFK-0297', 'Meloxicam tablet 15 mg', 'vial', 1),
(298, 'IFK-0298', 'Meniran kapsul', 'tablet', 1),
(299, 'IFK-0299', 'Meprovent 2,5ml', 'tablet', 1),
(300, 'IFK-0300', 'mersibion', 'tube', 1),
(301, 'IFK-0301', 'Metamizol (Antrain) Injeksi 500mg/ml', 'sachet', 1),
(302, 'IFK-0302', 'Metaneuron tablet', 'tablet', 1),
(303, 'IFK-0303', 'Metformin H Cl tablet 500 mg', 'tablet', 1),
(304, 'IFK-0304', 'Metildopa tablet 250 mg', 'tabung', 1),
(305, 'IFK-0305', 'Metilergometrin Maleat injeksi 0,2 mg/ml', 'tablet', 1),
(306, 'IFK-0306', 'Metilergometrin Maleat tablet', 'tablet', 1),
(307, 'IFK-0307', 'Metilprednisolon injeksi', 'botol', 1),
(308, 'IFK-0308', 'Metoclopramid injeksi 5mg/ml', 'ampul', 1),
(309, 'IFK-0309', 'Metoclopramid sirup', 'botol', 1),
(310, 'IFK-0310', 'Metoclopramid tablet 5 mg', 'botol', 1),
(311, 'IFK-0311', 'Metronidazol infus', 'botol', 1),
(312, 'IFK-0312', 'Metronidazol tablet 250 mg', 'ampul', 1),
(313, 'IFK-0313', 'Metronidazol tablet 500 mg', 'tablet', 1),
(314, 'IFK-0314', 'Miconazol Krim 2%', 'tablet', 1),
(315, 'IFK-0315', 'miniaspi 80mg', 'tablet', 1),
(316, 'IFK-0316', 'Mineral mix', 'biji', 1),
(317, 'IFK-0317', 'Moxiflocaxin table 400 mg', 'tablet', 1),
(318, 'IFK-0318', 'molagit tab', 'botol', 1),
(319, 'IFK-0319', 'molexflu', 'tablet', 1),
(320, 'IFK-0320', 'N- Asetil Sistein tablet', 'tablet', 1),
(321, 'IFK-0321', 'N2O Nitrous Oxide', 'paket', 1),
(322, 'IFK-0322', 'Natrium Diclofenak 25 mg', 'paket', 1),
(323, 'IFK-0323', 'Natrium Diclofenak 50 mg', 'paket', 1),
(324, 'IFK-0324', 'Natrium Hipoklorit / baycline 500 ml', 'paket', 1),
(325, 'IFK-0325', 'Natrium Klorida 0.9% larutan 10 ml', 'botol', 1),
(326, 'IFK-0326', 'Natrium Klorida 0.9% larutan infus 100 ml', 'strip', 1),
(327, 'IFK-0327', 'Natrium Klorida 0.9% larutan infus 500 ml', 'strip', 1),
(328, 'IFK-0328', 'Natrium Klorida 0.9% larutan infus 25 ml', 'strip', 1),
(329, 'IFK-0329', 'Neurobat injeksi', 'strip', 1),
(330, 'IFK-0330', 'Nifedipin tablet 10 mg', 'botol', 1),
(331, 'IFK-0331', 'Nifedipin tablet 30 mg (Adalat)', 'botol', 1),
(332, 'IFK-0332', 'noza', 'botol', 1),
(333, 'IFK-0333', 'Novabion', 'pcs', 1),
(334, 'IFK-0334', 'Nurse Cap', 'tabung', 1),
(335, 'IFK-0335', 'Nystatin ovula 100.000 IU/g', 'tabung', 1),
(336, 'IFK-0336', 'Nystatin suspensi 100.000 IU', 'tablet', 1),
(337, 'IFK-0337', 'Nystatin tablet 500.000 IU/g', 'tablet', 1),
(338, 'IFK-0338', 'Obat Anti Malaria DHP (Dihidroartemisin + Primaquin)', 'ampul', 1),
(339, 'IFK-0339', 'Obat Antituberkulosis dosis Harian', 'kapsul', 1),
(340, 'IFK-0340', 'Obat Antituberkulosis kat - Anak', 'ampul', 1),
(341, 'IFK-0341', 'Obat Antituberkulosis kat - I', 'tablet', 1),
(342, 'IFK-0342', 'Obat Antituberkulosis kat - II', 'tablet', 1),
(343, 'IFK-0343', 'Obat Batuk Hitam cairan 100ml', 'biji', 1),
(344, 'IFK-0344', 'OBAT KUSTA M B anak', 'biji', 1),
(345, 'IFK-0345', 'OBAT KUSTA M B dewasa', 'biji', 1),
(346, 'IFK-0346', 'OBAT KUSTA P B anak', 'tube', 1),
(347, 'IFK-0347', 'OBAT KUSTA P B dewasa', 'tube', 1),
(348, 'IFK-0348', 'OBH Tropica Exta Mentol 60 ml', 'ampul', 1),
(349, 'IFK-0349', 'OBH Tropica Plus Anak Jeruk 60 ml', 'paket', 1),
(350, 'IFK-0350', 'OBH Tropica Plus Mentol 60 ml', 'paket', 1),
(351, 'IFK-0351', 'Object Glass', 'botol', 1),
(352, 'IFK-0352', 'Oksigen Gas dalam tabung - 1m3', 'botol', 1),
(353, 'IFK-0353', 'Oksigen Gas dalam tabung - 6m3', 'tablet', 1),
(354, 'IFK-0354', 'Olanzapin tablet 10 mg', 'botol', 1),
(355, 'IFK-0355', 'Olanzapin tablet 5 mg', 'ampul', 1),
(356, 'IFK-0356', 'Omeprazol injeksi', 'tube', 1),
(357, 'IFK-0357', 'Omeprazol kapsul', 'sak', 1),
(358, 'IFK-0358', 'Ondansetron injeksi 2,g/ml', 'tablet', 1),
(359, 'IFK-0359', 'Ondansetron tablet 4 mg', 'ampul', 1),
(360, 'IFK-0360', 'Oseltamivir tablet 75 mg', 'tablet', 1),
(361, 'IFK-0361', 'Oxyflow Nasal Oxygen Anak', 'botol', 1),
(362, 'IFK-0362', 'Oxyflow Nasal Oxygen Bayi', 'tablet', 1),
(363, 'IFK-0363', 'Oxyflow Nasal Oxygen Dewasa', 'tablet', 1),
(364, 'IFK-0364', 'Oxytetracyclin salep kulit 3 %', 'tablet', 1),
(365, 'IFK-0365', 'Oxytetracyclin salep mata 1%', 'tablet', 1),
(366, 'IFK-0366', 'Oxytocin injeksi 10 IU/ml', 'kapsul', 1),
(367, 'IFK-0367', 'Paket APD Lengkap', 'set', 1),
(368, 'IFK-0368', 'Paket Bahan Habis Pakai IVA', 'pcs', 1),
(369, 'IFK-0369', 'Paracetamol Drop', 'rol', 1),
(370, 'IFK-0370', 'Paracetamol sirup 120 mg/5ml', 'rol', 1),
(371, 'IFK-0371', 'Paracetamol tablet 500 mg', 'rol', 1),
(372, 'IFK-0372', 'Paracetamol inf', 'rol', 1),
(373, 'IFK-0373', 'Pehacain Injeksi', 'pcs', 1),
(374, 'IFK-0374', 'Permetrin (Scabimite) Krim 5%', 'Sak', 1),
(375, 'IFK-0375', 'Pestisida / Larvasida', 'Sak', 1),
(376, 'IFK-0376', 'Pioglitazone (Decullin) tablet 30 mg', 'botol', 1),
(377, 'IFK-0377', 'Piracetam injeksi 1gram/5ml', 'pcs', 1),
(378, 'IFK-0378', 'Piracetam tablet 800 mg', 'Ampul', 1),
(379, 'IFK-0379', 'Pirantel Pamoat Suspensi 125mg/5ml', 'tablet', 1),
(380, 'IFK-0380', 'Pirantel Pamoat tablet 125 mg', 'tablet', 1),
(381, 'IFK-0381', 'Pirazinamid ( Z ) tablet 500mg', 'tablet', 1),
(382, 'IFK-0382', 'Piridoksin (Vit B6) tablet 10 mg', 'vial', 1),
(383, 'IFK-0383', 'Piridoksin (Vit B6) tablet 25 mg', 'tablet', 1),
(384, 'IFK-0384', 'Piroxicam kapsul 20 mg', 'botol', 1),
(385, 'IFK-0385', 'Pisau Bedah No.24', 'botol', 1),
(386, 'IFK-0386', 'Plastic Apron', 'botol', 1),
(387, 'IFK-0387', 'Plester Fixation non woven 5 x 15', 'tablet', 1),
(388, 'IFK-0388', 'Plester Flexible non woven 5 x 5', 'tablet', 1),
(389, 'IFK-0389', 'Plester Kain Roll 2,5cm x 4,5m', 'tablet', 1),
(390, 'IFK-0390', 'Plester Kain Roll 7,5cm x 4,5m', 'tablet', 1),
(391, 'IFK-0391', 'Plesterin Bulat Soft', 'tablet', 1),
(392, 'IFK-0392', 'PMT BALITA', 'tablet', 1),
(393, 'IFK-0393', 'PMT BUMIL', 'ampul', 1),
(394, 'IFK-0394', 'Polikresulen (Metakresolsulfonat)', 'tablet', 1),
(395, 'IFK-0395', 'Polyurethan fil dressing Askina Derm 15x20 cm', 'tablet', 1),
(396, 'IFK-0396', 'PPD (Purified Protein Derivative)/ Mantoux test', 'kapsul', 1),
(397, 'IFK-0397', 'Prednison tablet 5 mg', 'kapsul', 1),
(398, 'IFK-0398', 'Primaquin tablet 15 mg', 'kapsul', 1),
(399, 'IFK-0399', 'Pro Baby (Omega 3 Softgels)', 'kapsul', 1),
(400, 'IFK-0400', 'Prokaina Penicillin G injeksi', 'botol', 1),
(401, 'IFK-0401', 'Proneuron tablet', 'botol', 1),
(402, 'IFK-0402', 'Prontosan Wound Gel X 250 Gram', 'botol', 1),
(403, 'IFK-0403', 'Prontosan Wound Gel x 50 gram', 'tablet', 1),
(404, 'IFK-0404', 'Prontosan Wound Irrigation Sol 350ml', 'tablet', 1),
(405, 'IFK-0405', 'Propanolol tablet 10 mg', 'set', 1),
(406, 'IFK-0406', 'Propanolol tablet 40 mg', 'set', 1),
(407, 'IFK-0407', 'Propiltiourasil tablet 100 mg', 'set', 1),
(408, 'IFK-0408', 'Pyrimetamin tablet 25mg', 'set', 1),
(409, 'IFK-0409', 'Quatiapin tablet 100 mg', 'set', 1),
(410, 'IFK-0410', 'Quinine Sulfate tablet', 'ampul', 1),
(411, 'IFK-0411', 'Ranitidin injeksi 50mg/2ml', 'tablet', 1),
(412, 'IFK-0412', 'Ranitidin tablet 150 mg', 'tablet', 1),
(413, 'IFK-0413', 'Recovit Plus', 'pot', 1),
(414, 'IFK-0414', 'Retinol kap. lunak 100.000 IU (BIRU)', 'bok', 1),
(415, 'IFK-0415', 'Retinol kap. lunak 200.000 IU (MERAH)', 'pcs', 1),
(416, 'IFK-0416', 'Rifampisin kapsul 450 mg', 'set', 1),
(417, 'IFK-0417', 'Rifampisin kapsul 600 mg', 'set', 1),
(418, 'IFK-0418', 'Ringer Asetat (Asering) infus', 'fls', 1),
(419, 'IFK-0419', 'Ringer Laktat + Glukosa 5% infus', 'fls', 1),
(420, 'IFK-0420', 'Ringer Laktat infus', 'fls', 1),
(421, 'IFK-0421', 'Rifapentin 150 mg', 'tablet', 1),
(422, 'IFK-0422', 'Risperidone 2 mg', 'tablet', 1),
(423, 'IFK-0423', 'Safe Glove Exam L', 'pcs', 1),
(424, 'IFK-0424', 'Safe Glove Exam M', 'pcs', 1),
(425, 'IFK-0425', 'Safe Glove Exam S', 'pcs', 1),
(426, 'IFK-0426', 'Safety box 2,5 lt', 'pcs', 1),
(427, 'IFK-0427', 'Safety box 5 lt', 'pcs', 1),
(428, 'IFK-0428', 'Salbutamol cairan nebule', 'sachet', 1),
(429, 'IFK-0429', 'Salbutamol tablet 2 mg', 'tablet', 1),
(430, 'IFK-0430', 'Salbutamol tablet 4 mg', 'tablet', 1),
(431, 'IFK-0431', 'Salep 2-4 DOEN (As.Salisilat 2%+Belerang Endap 4%)', 'pcs', 1),
(432, 'IFK-0432', 'Sample Collection Kit Module 8 CoolBox', 'botol', 1),
(433, 'IFK-0433', 'Sarung Tangan (Handscoon) Latex', 'pcs', 1),
(434, 'IFK-0434', 'Sarung Tangan Obgyn steril', 'pcs', 1),
(435, 'IFK-0435', 'Selediar', 'pcs', 1),
(436, 'IFK-0436', 'Sepatu Boots', 'pcs', 1),
(437, 'IFK-0437', 'Serum Anti Bisa Ular injeksi (ABU I) / SABU', 'galon', 1),
(438, 'IFK-0438', 'Serum Anti Dipteri injeksi 10.000 IU', 'pcs', 1),
(439, 'IFK-0439', 'Serum Anti Dipteri injeksi 20.000 IU', 'pcs', 1),
(440, 'IFK-0440', 'Serum Anti Rabies', 'vial', 1),
(441, 'IFK-0441', 'Serum Anti Tetanus injeksi 1.500 IU/ ATS', 'vial', 1),
(442, 'IFK-0442', 'Serum Anti Tetanus injeksi 20.000 IU', 'vial', 1),
(443, 'IFK-0443', 'Sikzonoate injeksi 25 mg /ml', 'ampul', 1),
(444, 'IFK-0444', 'Silk no 2/0', 'pcs', 1),
(445, 'IFK-0445', 'Silk no 2/0 + jarum', 'pcs', 1),
(446, 'IFK-0446', 'Silk no 3/0', 'pcs', 1),
(447, 'IFK-0447', 'Silk no 3/0 + jarum', 'pcs', 1),
(448, 'IFK-0448', 'Simvastatin tablet 10 mg', 'tablet', 1),
(449, 'IFK-0449', 'Simvastatin tablet 20 mg', 'kapsul', 1),
(450, 'IFK-0450', 'Sivit-zinc (multivitamin)', 'pcs', 1),
(451, 'IFK-0451', 'Softanol 500 ml', 'kapsul', 1),
(452, 'IFK-0452', 'Spashi injeksi', 'botol', 1),
(453, 'IFK-0453', 'Spasminal tablet', 'pcs', 1),
(454, 'IFK-0454', 'Spironolacton 100 mg', 'ampul', 1),
(455, 'IFK-0455', 'Spironolacton 25 mg', 'tablet', 1),
(456, 'IFK-0456', 'Stericide 5 lt', 'tablet', 1),
(457, 'IFK-0457', 'Stomach Tube', 'pcs', 1),
(458, 'IFK-0458', 'Suction Tube, 85cm, no. 16', 'tube', 1),
(459, 'IFK-0459', 'Sulfasetamida tetes mata 15%', 'ampul', 1),
(460, 'IFK-0460', 'Surgical Glove No. 6', 'tablet', 1),
(461, 'IFK-0461', 'Surgical Glove No. 6,5', 'set', 1),
(462, 'IFK-0462', 'Surgical Glove No. 7', 'tablet', 1),
(463, 'IFK-0463', 'Surgical Glove No. 7,5', 'tablet', 1),
(464, 'IFK-0464', 'Surgical Glove No. 8', 'biji', 1),
(465, 'IFK-0465', 'Tablet Tambah Darah (Ferro Sulfat + as. folat) 300 mg', 'set', 1),
(466, 'IFK-0466', 'Temporary Stopping Fletcher', 'Botol', 1),
(467, 'IFK-0467', 'Teosal tablet', 'Botol', 1),
(468, 'IFK-0468', 'Tetracyclin kapsul 500 mg', 'tablet', 1),
(469, 'IFK-0469', 'Thermometer Gun', 'tablet', 1),
(470, 'IFK-0470', 'Thiamphenicol kapsul 500 mg', 'biji', 1),
(471, 'IFK-0471', 'Thiamphenicol Sirup', 'botol', 1),
(472, 'IFK-0472', 'Three way Stop Cock', 'botol', 1),
(473, 'IFK-0473', 'Tiamina (Vit B1) injeksi 100mg/ml', 'botol', 1),
(474, 'IFK-0474', 'Tiamina (Vit B1) tablet 100 mg', 'botol', 1),
(475, 'IFK-0475', 'Tiamina (Vit B1) tablet 50 mg', 'tablet', 1),
(476, 'IFK-0476', 'Tisu Basah', 'pcs', 1),
(477, 'IFK-0477', 'Topical Anastesi Gel/Prime Gel', 'pcs', 1),
(478, 'IFK-0478', 'Tramadol injeksi 50 mg/ml', 'pcs', 1),
(479, 'IFK-0479', 'Tramadol kapsul 50 mg', 'pcs', 1),
(480, 'IFK-0480', 'Transfusion set', 'pcs', 1),
(481, 'IFK-0481', 'Trifacalc', 'pcs', 1),
(482, 'IFK-0482', 'Trifluoperazin tablet 5 mg', 'botol', 1),
(483, 'IFK-0483', 'Triheksifenidil tablet 2 mg', 'botol', 1),
(484, 'IFK-0484', 'Umbilical Cord Clamp', 'botol', 1),
(485, 'IFK-0485', 'Under Pad', 'tablet', 1),
(486, 'IFK-0486', 'Urine Bag', 'botol', 1),
(487, 'IFK-0487', 'USG Gel 5 liter', '-', 1),
(488, 'IFK-0488', 'USG Gel 250 ml', 'tablet', 1),
(489, 'IFK-0489', 'Vitamin B Kompleks tablet', 'tablet', 1),
(490, 'IFK-0490', 'Vitamin D (5000 IU)', 'tablet', 1),
(491, 'IFK-0491', 'Wing Needle bayi no.25', 'tablet', 1),
(492, 'IFK-0492', 'Yodium Povidon 10% - 30 ml', 'tablet', 1),
(493, 'IFK-0493', 'Yodium Povidon 10% - 300 ml', 'tablet', 1),
(494, 'IFK-0494', 'Yodium Povidon 1000 ml', 'tablet', 1),
(495, 'IFK-0495', 'Zinc Sulfat sirup (Zircum)', 'tablet', 1),
(496, 'IFK-0496', 'Zinc Sulfat tablet 20 mg', 'tablet', 1),
(497, 'IFK-0497', 'Cervical Colar', 'tablet', 1),
(498, 'IFK-0498', 'Arm Sling L', 'tablet', 1),
(499, 'IFK-0499', 'Arm Sling M', 'tablet', 1),
(500, 'IFK-0500', 'Spalk', 'ampul', 1),
(501, 'IFK-0501', 'Medicrepe 4\"', 'tablet', 1),
(502, 'IFK-0502', 'PEN MAKER', 'tablet', 1),
(503, 'IFK-0503', 'GP CARE ANTISEPTEK (HAND SANITISER) 500 ML', 'tablet', 1),
(504, 'IFK-0504', 'GP CARE ANTISEPTEK (HAND WASH) 500 ML', 'tablet', 1),
(505, 'IFK-0505', 'SASKLIN Spray', 'botol', 1),
(506, 'IFK-0506', 'Abacavir (ABC) 300 mg', 'tablet', 2),
(507, 'IFK-0507', 'Combipak (Azithromycin-Cefixime)', 'tablet', 2),
(508, 'IFK-0508', 'Dolutegravir (DTG) 50 mg', 'tablet', 2),
(509, 'IFK-0509', 'Efavirenz (EFV) 200 mg', 'tablet', 2),
(510, 'IFK-0510', 'Efavirenz (EFV) 600mg', 'tablet', 2),
(511, 'IFK-0511', 'Favipiravir tablet 200 mg', 'tablet', 2),
(512, 'IFK-0512', 'FDC Anak: ZDV 60 mg + 3TC 30 mg + NVP 50 mg', 'tablet', 2),
(513, 'IFK-0513', 'FDC Dewasa: TDF 300 mg + 3TC 300 mg + EFV 600 mg', 'tablet', 2),
(514, 'IFK-0514', 'FDC: TDF 300 mg + 3TC 300 mg + DTG 50 mg', 'tablet', 2),
(515, 'IFK-0515', 'Lamivudine (3TC) tablet 150 mg', 'tablet', 2),
(516, 'IFK-0516', 'Lopinavir/ritonavir (LPV / r) 200/150 mg', 'tablet', 2),
(517, 'IFK-0517', 'Nevirapine (NVP) tablet 200 mg', 'tablet', 2),
(518, 'IFK-0518', 'Remdesivir injeksi 100 mg', 'tablet', 2),
(519, 'IFK-0519', 'Tenofovir (TDF) 300 mg + Emcitritabine (FCT) 200 mg', 'tablet', 2),
(520, 'IFK-0520', 'Tenofovir (TDF) tablet 300 mg', 'tablet', 2),
(521, 'IFK-0521', 'Zidofudine ( ZDV ) 300mg + Lamivudine ( 3TC ) 150mg', 'tablet', 2),
(522, 'IFK-0522', 'Zidofudine (ZDV) tablet 100 mg', 'tablet', 2),
(523, 'IFK-0523', 'Zidovudin (ZDV) Sirup', 'botol', 2),
(524, 'IFK-0524', '3HP TB (Isoniazid + Rifapentin)', 'tablet', 2),
(525, 'IFK-0525', '3HP HIV (Isoniazid + Rifapentin)', 'tablet', 2),
(526, 'IFK-0526', 'Autodisposable Syringe 0,05 ml', 'vial', 3),
(527, 'IFK-0527', 'Autodisposable Syringe 0,5 ml', 'vial', 3),
(528, 'IFK-0528', 'Autodisposable Syringe 5 ml', 'vial', 3),
(529, 'IFK-0529', 'Auto Destruct Syringe (PFIZER) 0,3 ML', 'vial', 3),
(530, 'IFK-0530', 'Auto Destruct Syringe (PFIZER) 3 ML', 'vial', 3),
(531, 'IFK-0531', 'Alcohol Swab', 'vial', 3),
(532, 'IFK-0532', 'Pelarut NaCl 0,9%', 'vial', 3),
(533, 'IFK-0533', 'Pelarut BCG', 'vial', 3),
(534, 'IFK-0534', 'Vaksin BCG', 'vial', 3),
(535, 'IFK-0535', 'Vaksin PCV', 'vial', 3),
(536, 'IFK-0536', 'Vaksin DPT Hb - Hib / PENTABIO', 'vial', 3),
(537, 'IFK-0537', 'Vaksin DT', 'vial', 3),
(538, 'IFK-0538', 'Vaksin Hb-0 (Unijeck)', 'vial', 3),
(539, 'IFK-0539', 'Vaksin HBIG', 'vial', 3),
(540, 'IFK-0540', 'Vaksin Meningitis', 'test', 3),
(541, 'IFK-0541', 'Pelarut MR', 'paket', 3),
(542, 'IFK-0542', 'Vaksin MR', 'kit', 3),
(543, 'IFK-0543', 'Vaksin Polio bOPV 10 Dosis', 'botol', 3),
(544, 'IFK-0544', 'Vaksin Polio IPV 5 Dosis', 'pcs', 3),
(545, 'IFK-0545', 'Vaksin Td', 'pcs', 3),
(546, 'IFK-0546', 'Vaksin HPV', 'pcs', 3),
(547, 'IFK-0547', 'Vaksin Covid-19 (Astra Zeneca) 10 dosis', 'pcs', 3),
(548, 'IFK-0548', 'Vaksin Covid-19 (Astra Zeneca) 8 dosis', 'pcs', 3),
(549, 'IFK-0549', 'Vaksin Sinopharm ( 2 dosis )', 'pcs', 3),
(550, 'IFK-0550', 'Vaksin Sinopharm ( single dosis )', 'pcs', 3),
(551, 'IFK-0551', 'Vaksin Moderna ( Multi 14 dosis )', 'pcs', 3),
(552, 'IFK-0552', 'Vaksin Moderna ( Multi 10 dosis )', 'pcs', 3),
(553, 'IFK-0553', 'Vaksin COVID-19 Coronavac MDV (Multi 10 dosis)', 'pcs', 3),
(554, 'IFK-0554', 'Vaksin Covid-19 SINOVAC (Coronavac) 1 Dosis', 'pcs', 3),
(555, 'IFK-0555', 'Vaksin Covid-19 SINOVAC (Coronavac) 2 Dosis', 'pcs', 3),
(556, 'IFK-0556', 'Vaksin PFIZER BILATERAL SHIPMENT 6 Dosis', 'pcs', 3),
(557, 'IFK-0557', 'Vaksin Sars-CoV-2rS (COVID-19) Covovax', 'pcs', 3),
(558, 'IFK-0558', 'Acon Hb Hemoglobin Strip 50 test mission', 'pcs', 4),
(559, 'IFK-0559', 'Alat Deteksi Resiko Kehamilan', 'pcs', 4),
(560, 'IFK-0560', 'Albumin', 'biji', 4),
(561, 'IFK-0561', 'Asam Cuka 25 %', 'botol', 4),
(562, 'IFK-0562', 'Accu check Cholesterol Strip', 'kit', 4),
(563, 'IFK-0563', 'Accu check Glucose Strip', 'pcs', 4),
(564, 'IFK-0564', 'Accu check Uric Acid Strip', 'kit', 4),
(565, 'IFK-0565', 'Autocheck Cholesterol Strip', 'kit', 4),
(566, 'IFK-0566', 'Autocheck Glucose Strip', 'box', 4),
(567, 'IFK-0567', 'Autocheck Uric Acid Strip', 'vial', 4),
(568, 'IFK-0568', 'Easytouch Cholesterol Strip', 'tube', 4),
(569, 'IFK-0569', 'Easytouch Glucose Strip', 'biji', 4),
(570, 'IFK-0570', 'Easytouch Uric Acid Strip', 'biji', 4),
(571, 'IFK-0571', 'Humasens Cholesterol Strip', 'kit', 4),
(572, 'IFK-0572', 'Humasens Glucose Strip', 'test', 4),
(573, 'IFK-0573', 'Humasens Uric Acid Strip', 'box', 4),
(574, 'IFK-0574', 'Rightest Cholesterol Strip', 'tes', 4),
(575, 'IFK-0575', 'Rightest Glucose Strip', 'botol', 4),
(576, 'IFK-0576', 'Rightest Uric Acid Strip', 'piece', 4),
(577, 'IFK-0577', 'Blue Tip', 'vial', 4),
(578, 'IFK-0578', 'Box Slide', 'vial', 4),
(579, 'IFK-0579', 'Brilliant Cresyl Blue 1 %', 'vial', 4),
(580, 'IFK-0580', 'Cadmium SPQ', 'vial', 4),
(581, 'IFK-0581', 'Catridge TCM', 'box', 4),
(582, 'IFK-0582', 'Chloride Test', 'box', 4),
(583, 'IFK-0583', 'Chromate Test', 'botol', 4),
(584, 'IFK-0584', 'Control Negatif Polyvalent', 'kit', 4),
(585, 'IFK-0585', 'Control Positive Polyvalent', 'botol', 4),
(586, 'IFK-0586', 'Control Urine 2 Level', 'kit', 4),
(587, 'IFK-0587', 'Cotton Applicators Sterile', 'botol', 4),
(588, 'IFK-0588', 'Cotton swab', 'kit', 4),
(589, 'IFK-0589', 'Cyanide SPQ', 'botol', 4),
(590, 'IFK-0590', 'CYBOW Strip 100\"S', 'botol', 4),
(591, 'IFK-0591', 'Determine HIV', 'botol', 4),
(592, 'IFK-0592', 'Focus TPHA (100 test)', 'botol', 4),
(593, 'IFK-0593', 'Giemsa', 'kit', 4),
(594, 'IFK-0594', 'Gluco Protein', 'botol', 4),
(595, 'IFK-0595', 'Golongan Darah Anti A', 'botol', 4),
(596, 'IFK-0596', 'Golongan Darah Anti AB', 'kit', 4),
(597, 'IFK-0597', 'Golongan Darah Anti B', 'pcs', 4),
(598, 'IFK-0598', 'Golongan Darah Anti D', 'set', 4),
(599, 'IFK-0599', 'HBsAb Strip Tes', 'set', 4),
(600, 'IFK-0600', 'ICT Malaria', 'set', 4),
(601, 'IFK-0601', 'Immersion Oil', 'box', 4),
(602, 'IFK-0602', 'Iron Test', '-', 4),
(603, 'IFK-0603', 'Kertas PH 3,8 - 4,2', '-', 4),
(604, 'IFK-0604', 'Kesadahan Test', 'sachet', 4),
(605, 'IFK-0605', 'KOH 10%', 'box', 4),
(606, 'IFK-0606', 'Lactose Broth', 'test', 4),
(607, 'IFK-0607', 'Larutan Chlorine', 'test', 4),
(608, 'IFK-0608', 'Larutan Drabkin', 'test', 4),
(609, 'IFK-0609', 'Larutan Eosin 2 %', 'test', 4),
(610, 'IFK-0610', 'Larutan Turk', 'kit', 4),
(611, 'IFK-0611', 'Mangan Test', 'botol', 4),
(612, 'IFK-0612', 'Methylated Spirit', 'biji', 4),
(613, 'IFK-0613', 'Methylen Blue 0.3%', 'vial', 4),
(614, 'IFK-0614', 'Nitrit Test', 'vial', 4),
(615, 'IFK-0615', 'Performa Test Strips/ Stik GDA ACCU', 'vial', 4),
(616, 'IFK-0616', 'Pewarna Gram', 'vial', 4),
(617, 'IFK-0617', 'Pewarna Rapid/ sediaan apus darah tepi/MDT', 'vial', 4),
(618, 'IFK-0618', 'Pewarna Ziehl Nellsen (PROGRAM )', 'vial', 4),
(619, 'IFK-0619', 'Proline Urea FS', 'vial', 4),
(620, 'IFK-0620', 'Proten gold', 'vial', 4),
(621, 'IFK-0621', 'RAPID TEST ANTI HCV', 'box', 4),
(622, 'IFK-0622', 'RDT ANTIGEN COVID - 19', 'set', 4),
(623, 'IFK-0623', 'RDT DBD Dengue Combo Verotec', 'pct', 4),
(624, 'IFK-0624', 'RDT Malaria (10 test / ktk)', 'stick', 4),
(625, 'IFK-0625', 'RDT Chikungunya', 'tube', 4),
(626, 'IFK-0626', 'Reagen Crackset 10', 'tube', 4),
(627, 'IFK-0627', 'Reagen Giemsa + Buffer Giemds', 'kit', 4),
(628, 'IFK-0628', 'Safe-T Pro Uno', 'set', 4),
(629, 'IFK-0629', 'Salmonella Thypi H', 'set', 4),
(630, 'IFK-0630', 'Salmonella Thypi H Antigen A', 'piece', 4),
(631, 'IFK-0631', 'Salmonella Thypi H Antigen B', 'kit', 4),
(632, 'IFK-0632', 'Salmonella Thypi H Antigen C', 'botol', 4),
(633, 'IFK-0633', 'Salmonella Thypi O', 'galon', 4),
(634, 'IFK-0634', 'Salmonella Thypi O Antigen A', '-', 4),
(635, 'IFK-0635', 'Salmonella Thypi O Antigen B', '-', 4),
(636, 'IFK-0636', 'Salmonella Thypi O Antigen C', 'kit', 4),
(637, 'IFK-0637', 'SD HBsAg WB (Multi + Droppler)', 'botol', 4),
(638, 'IFK-0638', 'Spekulum Disposble', 'pcs', 4),
(639, 'IFK-0639', 'Sputum Pot', 'pcs', 4),
(640, 'IFK-0640', 'Stick Urine 10 PM', 'botol', 4),
(641, 'IFK-0641', 'Strip Urine 11 PM', 'botol', 4),
(642, 'IFK-0642', 'Strip Urine 3 PM', 'botol', 4),
(643, 'IFK-0643', 'TCM COVID-19', 'botol', 4),
(644, 'IFK-0644', 'Tube EDTA Merah', 'botol', 4),
(645, 'IFK-0645', 'Tube EDTA Ungu', 'botol', 4),
(646, 'IFK-0646', 'Ultra Once Test Hamil', 'botol', 4),
(647, 'IFK-0647', 'Uriscan GP2 for Reduksi + Albumin', 'botol', 4),
(648, 'IFK-0648', 'Viorex (500 ML)', 'botol', 4),
(649, 'IFK-0649', 'Viorex No Rinso Antiseptik (5 lt)', '-', 4),
(650, 'IFK-0650', 'VTM COVID-19', '-', 4),
(651, 'IFK-0651', 'Xylol', '-', 4),
(652, 'IFK-0652', 'Yellow and Blue Tip', '-', 4),
(653, 'IFK-0653', 'Yellow Tip', '-', 4),
(654, 'IFK-0654', 'Diatro Reagent Cleanser (ABACUS)', '-', 4),
(655, 'IFK-0655', 'Diatro Reagent Lyse (ABACUS)', '-', 4),
(656, 'IFK-0656', 'Diatro Reagent Diluent (ABACUS)', '-', 4),
(657, 'IFK-0657', 'Norma Reagent Cleanser iSol3', '-', 4),
(658, 'IFK-0658', 'Norma Reagent Lyse iLyse3', '-', 4),
(659, 'IFK-0659', 'Norma Reagent Diluent iDil3', '-', 4),
(660, 'IFK-0660', 'Cell-Dyn Emerald Reagent Cleanser', '-', 4),
(661, 'IFK-0661', 'Cell-Dyn Emerald Reagent Lyse', '-', 4),
(662, 'IFK-0662', 'Cell-Dyn Emerald Reagent Diluent', '-', 4),
(663, 'IFK-0663', 'Anti-HIV1/2 Cassette (FOKUS 25 test)', 'pcs', 5),
(664, 'IFK-0664', 'Catridge HIV Viral Load', 'test', 5),
(665, 'IFK-0665', 'D.Piece', 'pcs', 5),
(666, 'IFK-0666', 'DBS Collection Kit', 'pcs', 5),
(667, 'IFK-0667', 'Diagnostic Kit For HIV (1+2) Antibody 50 test', 'test', 5),
(668, 'IFK-0668', 'HCV Viral Load Xpert HIV 1', 'test', 5),
(669, 'IFK-0669', 'HIV 1/2 DIAGOSTAR (ONE STEP RAPID TEST 25)', 'test', 5),
(670, 'IFK-0670', 'Kondom', 'pcs', 5),
(671, 'IFK-0671', 'Kondom Lubrikan/Pelicin', 'pcs', 5),
(672, 'IFK-0672', 'Lipid Panel Test Strip', 'strip', 5),
(673, 'IFK-0673', 'Mouth Piece', 'pcs', 5),
(674, 'IFK-0674', 'Rapid Test Syphilis (Trepocheck Syphilis)', 'test', 5),
(675, 'IFK-0675', 'RPR Test Syphilis (FOKUS Diagnostic)', 'test', 5),
(676, 'IFK-0676', 'Rapid Test Syphilis Biocare', 'test', 5),
(677, 'IFK-0677', 'Reagen I Intec One Step Anti HIV Tri Line Tes Card', 'pcs', 5),
(678, 'IFK-0678', 'Reagen I SD Bioline HIV 1/2(DUO)', 'pcs', 5),
(679, 'IFK-0679', 'Reagen Penghitung CD 4 Control', 'pcs', 5),
(680, 'IFK-0680', 'Reagen Penghitung CD 4 MOBIL', 'pcs', 5),
(681, 'IFK-0681', 'Tes HIV I', 'set', 5),
(682, 'IFK-0682', 'Tes HIV II', 'set', 5),
(683, 'IFK-0683', 'Tes HIV III', 'set', 5),
(684, 'IFK-0684', 'Virocheck HIV 1/2', 'paket', 5),
(685, 'IFK-0685', 'Vacutainer Flashback Blood Collection Needle', 'pcs', 5),
(686, 'IFK-0686', 'Articulating paper', 'pcs', 6),
(687, 'IFK-0687', 'Bahan tumpatan sementara (Orafil)', 'botol', 6),
(688, 'IFK-0688', 'Bonding Liquid', 'botol', 6),
(689, 'IFK-0689', 'Bur carbide highspeed round', 'Pak', 6),
(690, 'IFK-0690', 'Cavit W', 'botol', 6),
(691, 'IFK-0691', 'Composit Light Curing A2 (Master Fill)', 'syringe', 6),
(692, 'IFK-0692', 'Composit Light Curing A3', 'syringe', 6),
(693, 'IFK-0693', 'Composit Light Curing A3,5', 'syringe', 6),
(694, 'IFK-0694', 'Cotton roll', 'Sak', 6),
(695, 'IFK-0695', 'Cresopen (obat gigi)', 'botol', 6),
(696, 'IFK-0696', 'Cresotin Liq No. 2', 'botol', 6),
(697, 'IFK-0697', 'Dentin Conditioner / Mini Pack', 'kotak', 6),
(698, 'IFK-0698', 'Devitalisasi pasta', 'botol', 6),
(699, 'IFK-0699', 'Etchant/ Etsa Asam Liquid', 'set', 6),
(700, 'IFK-0700', 'Etching Liquid', 'set', 6),
(701, 'IFK-0701', 'Eugenol Cairan - 10 ml', 'botol', 6),
(702, 'IFK-0702', 'Fluoride Sediaan Topical/Clinpro White', 'botol', 6),
(703, 'IFK-0703', 'Frutti Fluor Gel', 'botol', 6),
(704, 'IFK-0704', 'Glass Ionomer Cement/GC Fuji IX', 'set', 6),
(705, 'IFK-0705', 'Glass Ionomer Cement/GC Fuji VII', 'set', 6),
(706, 'IFK-0706', 'Hydcal pasta', 'set', 6),
(707, 'IFK-0707', 'Kalsium Hidroksida pasta', 'tube', 6),
(708, 'IFK-0708', 'Komposit Resin', 'set', 6),
(709, 'IFK-0709', 'Ledermix 5 gr/ 3 mix', 'botol', 6),
(710, 'IFK-0710', 'Minyak bur / Pana Spray Plus', 'botol', 6),
(711, 'IFK-0711', 'Monoklorkamfer Mentol cairan/Paramono Chlorophenol', 'botol', 6),
(712, 'IFK-0712', 'Mummifying pasta', 'botol', 6),
(713, 'IFK-0713', 'Pulp X', 'botol', 6),
(714, 'IFK-0714', 'Semen Seng Fosfat', 'set', 6),
(715, 'IFK-0715', 'Silver Amalgam serbuk 65-75%', 'botol', 6),
(716, 'IFK-0716', 'Single Bond 2', 'botol', 6),
(717, 'IFK-0717', 'Spons Gelatin cubicles 1x1x1cm', 'biji', 6),
(718, 'IFK-0718', 'Spectra Fresh Mint', 'set', 6),
(719, 'IFK-0719', 'Tampon', 'Sak', 6),
(720, 'IFK-0720', 'Trikresol Formalin (Formokresol) cairan - 10 ml', 'botol', 6),
(721, 'IFK-0721', 'Vaselin Alba', 'botol', 6),
(722, 'IFK-0722', 'Zinc F+ (Zinc Phosphate Cement)', 'set', 6),
(723, 'IFK-0723', 'ACON Urinalysis 10 SG Insight 100 test', 'Pack', 7),
(724, 'IFK-0724', 'Albumin', 'Pcs', 7),
(725, 'IFK-0725', 'Alkali Phosphatase', 'Pcs', 7),
(726, 'IFK-0726', 'Alkohol Swab 100 pcs/box', 'Pcs', 7),
(727, 'IFK-0727', 'Amphethamine 50 strp (Answer)', 'Pcs', 7),
(728, 'IFK-0728', 'Anti-A Monoclonal Golongan Darah (Vial 10 ml)', 'Pcs', 7),
(729, 'IFK-0729', 'Anti-AB Monoclonal Golongan Darah (Vial 10 ml)', 'Pcs', 7),
(730, 'IFK-0730', 'Anti-B Monoclonal Golongan Darah (Vial 10 ml)', 'pack', 7),
(731, 'IFK-0731', 'Anti-D Duoclone Golongan Darah (Vial 10 ml)', 'pack', 7),
(732, 'IFK-0732', 'Benz0 50 strip (Answer)', 'Buah', 7),
(733, 'IFK-0733', 'BGLB', 'Buah', 7),
(734, 'IFK-0734', 'Billirubin', 'Meter', 7),
(735, 'IFK-0735', 'Biokimia TSIA', 'Gulung', 7),
(736, 'IFK-0736', 'BOD Cell Test', 'Buah', 7),
(737, 'IFK-0737', 'BOD Nutrient Salt Mixture', 'botol', 7),
(738, 'IFK-0738', 'BOD Reaction Bottle', 'pcs', 7),
(739, 'IFK-0739', 'BOD Standart', 'rol', 7),
(740, 'IFK-0740', 'Borax Test', 'pcs', 7),
(741, 'IFK-0741', 'Brilliant green', 'pcs', 7),
(742, 'IFK-0742', 'Buffer pH10', 'pcs', 7),
(743, 'IFK-0743', 'Buffer pH4', 'pcs', 7),
(744, 'IFK-0744', 'Buffer pH7', 'pcs', 7),
(745, 'IFK-0745', 'Cadmium (cd)', 'pcs', 7),
(746, 'IFK-0746', 'Cadmium SPQ', 'tube', 7),
(747, 'IFK-0747', 'Carrez', 'botol', 7),
(748, 'IFK-0748', 'Carry & Blair Amies Agar', 'pcs', 7),
(749, 'IFK-0749', 'Cell Clean', 'pcs', 7),
(750, 'IFK-0750', 'Cell Pack (Diluent)', 'roll', 7),
(751, 'IFK-0751', 'Cell Pack DCL', 'pcs', 7),
(752, 'IFK-0752', 'Cell Pack DCL 1x20Lt', 'botol', 7),
(753, 'IFK-0753', 'Cell Pack DFL', 'kotak', 7),
(754, 'IFK-0754', 'Cellclean Auto (CCA-500)', 'Pcs', 7),
(755, 'IFK-0755', 'Chloride Test', 'kotak', 7),
(756, 'IFK-0756', 'Chlorine Test (free chlorine)', 'tab', 7),
(757, 'IFK-0757', 'Cholesterol', '-', 7),
(758, 'IFK-0758', 'Chromate Test', '-', 7),
(759, 'IFK-0759', 'Cocain50 strip (Monotest)', 'tablet', 7),
(760, 'IFK-0760', 'COD Cell Test', 'tablet', 7),
(761, 'IFK-0761', 'COD Solution A', 'botol', 7),
(762, 'IFK-0762', 'COD Solution B', 'tab', 7),
(763, 'IFK-0763', 'Colour Test', 'tab', 7),
(764, 'IFK-0764', 'Control Eight Check Normal', 'tab', 7),
(765, 'IFK-0765', 'Control Eight High', 'tab', 7),
(766, 'IFK-0766', 'Control Eight Low', 'Tablet', 7),
(767, 'IFK-0767', 'Control Serum Abnormal', 'Kapsul', 7),
(768, 'IFK-0768', 'Control Serum Normal', 'Tab', 7),
(769, 'IFK-0769', 'Control Urine 2 Level', 'Tab', 7),
(770, 'IFK-0770', 'Copper Test', 'Tab', 7),
(771, 'IFK-0771', 'Creatinin', 'Tablet', 7),
(772, 'IFK-0772', 'Cyanid (Cn) 1.0971.0001', 'Kapsul', 7),
(773, 'IFK-0773', 'Cyanide SPQ', 'Tab', 7),
(774, 'IFK-0774', 'Deterjen', 'Tablet', 7),
(775, 'IFK-0775', 'Drug Abuse Multi Panel 6 Parameter 25 card (Answer)', 'botol', 7),
(776, 'IFK-0776', 'Drug Abuse Multi Panel 7 Parameter', 'tablet', 7),
(777, 'IFK-0777', 'E-CARE ALCOHOL SWAB CHG + 0.5% (EMPAC)', 'vial', 7),
(778, 'IFK-0778', 'Eosin', '-', 7),
(779, 'IFK-0779', 'Extran MA 02-Neutral', '-', 7),
(780, 'IFK-0780', 'Fluoride Test', '-', 7),
(781, 'IFK-0781', 'Fluorocell RET', '-', 7),
(782, 'IFK-0782', 'Fluorocell WDF', '-', 7),
(783, 'IFK-0783', 'FOKUS Amphetamine Check Test', '-', 7),
(784, 'IFK-0784', 'FOKUS Cocain Rapid Test', '-', 7),
(785, 'IFK-0785', 'FOKUS Device Benzodiazepine s (BZD) Rapid Test', '-', 7),
(786, 'IFK-0786', 'FOKUS HCG Strip', '-', 7),
(787, 'IFK-0787', 'FOKUS Marijuana (THC) Rapid Test', '-', 7),
(788, 'IFK-0788', 'FOKUS Morphine (Opiates) Rapid Test', '-', 7),
(789, 'IFK-0789', 'Formaldehyd Test Strip', '-', 7),
(790, 'IFK-0790', 'Gamma GT', '-', 7),
(791, 'IFK-0791', 'Glucose', '-', 7),
(792, 'IFK-0792', 'GONGDONG GD Vaccum Tube Sterile Clot Activator (3ml)', '-', 7),
(793, 'IFK-0793', 'GONGDONG GD Vaccum Tube Sterile EDTA K-3 (3ml)', 'pcs', 7),
(794, 'IFK-0794', 'HBsAg Strip Tes', 'pcs', 7),
(795, 'IFK-0795', 'HBSS', 'pcs', 7),
(796, 'IFK-0796', 'HDL Chol Direct', 'pcs', 7),
(797, 'IFK-0797', 'Hematologi (Sysmex KX-21)', 'pcs', 7),
(798, 'IFK-0798', 'Hemoglobin C 1x500mL', 'pcs', 7),
(799, 'IFK-0799', 'HEXAGON SYPHILIS', 'pcs', 7),
(800, 'IFK-0800', 'Immersion Oil 1x50mL', 'pcs', 7),
(801, 'IFK-0801', 'IMMUNOQUICK Contact Malaria +4', 'pcs', 7),
(802, 'IFK-0802', 'Iron test', 'pcs', 7),
(803, 'IFK-0803', 'Kesadahan Test', 'pcs', 7),
(804, 'IFK-0804', 'Lactose Broth', 'pcs', 7),
(805, 'IFK-0805', 'LDL Chol Direct', 'pcs', 7),
(806, 'IFK-0806', 'Lysercall WDF', 'pcs', 7),
(807, 'IFK-0807', 'Mangan Test', 'pcs', 7),
(808, 'IFK-0808', 'Marijuana 50 strip (Answer)', 'pcs', 7),
(809, 'IFK-0809', 'Methamphetamine', 'pcs', 7),
(810, 'IFK-0810', 'Methamphetamine 50 strip (Answer)', 'pcs', 7),
(811, 'IFK-0811', 'Multi Drug One Step Multi Line with Integrated E-Z, Split Key Cup II', 'pcs', 7),
(812, 'IFK-0812', 'Nitrit Test', 'pcs', 7),
(813, 'IFK-0813', 'Nitrite Test', '-', 7),
(814, 'IFK-0814', 'Onemed Alkohol 70% 1L', '-', 7),
(815, 'IFK-0815', 'Onemed Kapas Pembalut 250g', '-', 7),
(816, 'IFK-0816', 'Onemed Plesterin Bulat Non Woven', '-', 7),
(817, 'IFK-0817', 'Opiet 50 strip (Monotest)', '-', 7),
(818, 'IFK-0818', 'PCA', '-', 7),
(819, 'IFK-0819', 'PCR Tube', '-', 7),
(820, 'IFK-0820', 'PCR Reagent', '-', 7),
(821, 'IFK-0821', 'Pemanis (Grade Teknis)', '-', 7),
(822, 'IFK-0822', 'Pewarna Gram', '-', 7),
(823, 'IFK-0823', 'Pewarna MDT 3x100mL', 'Tes', 7),
(824, 'IFK-0824', 'Pewarna Ziehl Neelsen 3x100mL', 'Tes', 7),
(825, 'IFK-0825', 'PH tes strip', '-', 7),
(826, 'IFK-0826', 'Phosphate Buffer Saline', '-', 7),
(827, 'IFK-0827', 'Plesterin Bulat Soft isi 200/box', '-', 7),
(828, 'IFK-0828', 'Pregnancy strip test 50 strip', '-', 7),
(829, 'IFK-0829', 'Rapid Test SD Bioline HBsAg', '-', 7),
(830, 'IFK-0830', 'Rapid Test SD Bioline HIV 1/2 3.0', '-', 7),
(831, 'IFK-0831', 'Reagen Crackset 10', '-', 7),
(832, 'IFK-0832', 'Rhodamin B Test', '-', 7),
(833, 'IFK-0833', 'Safeglove Examination Gloves L', '-', 7),
(834, 'IFK-0834', 'Safeglove Examination Gloves M', '-', 7),
(835, 'IFK-0835', 'Safeglove Examination Gloves S', '-', 7),
(836, 'IFK-0836', 'Salmonella Paratyphi AH Widal Test (Vial 5 ml)', '-', 7),
(837, 'IFK-0837', 'Salmonella Paratyphi AO Widal Test (Vial 5 ml)', '-', 7),
(838, 'IFK-0838', 'Salmonella Paratyphi BH Widal Test (Vial 5 ml)', '-', 7),
(839, 'IFK-0839', 'Salmonella Paratyphi BO Widal Test (Vial 5 ml)', '-', 7),
(840, 'IFK-0840', 'Salmonella Paratyphi CH Widal Test (Vial 5 ml)', '-', 7),
(841, 'IFK-0841', 'Salmonella Paratyphi CO Widal Test (Vial 5 ml)', '-', 7),
(842, 'IFK-0842', 'Salmonella Typhi H Widal Test (Vial 5 ml)', '-', 7),
(843, 'IFK-0843', 'Salmonella Typhi O Widal Test (Vial 5 ml)', '-', 7),
(844, 'IFK-0844', 'SGOT', '-', 7),
(845, 'IFK-0845', 'SGPT', '-', 7),
(846, 'IFK-0846', 'SIM Medium', '-', 7),
(847, 'IFK-0847', 'SimonCytrat', '-', 7),
(848, 'IFK-0848', 'Sisa Chlor Test', '-', 7),
(849, 'IFK-0849', 'Standard CRM Fe', '-', 7),
(850, 'IFK-0850', 'Stik Urine 10 Parameter', '-', 7),
(851, 'IFK-0851', 'Stroma', '-', 7),
(852, 'IFK-0852', 'Sulfolyser (SLS-210A)', '-', 7),
(853, 'IFK-0853', 'Syphilis Test', '-', 7),
(854, 'IFK-0854', 'TCBS Agar 1.10263.0500 9(P+MI)', '-', 7),
(855, 'IFK-0855', 'TERUMO Disp.Syringe with Needle 1 CC Tuberculin', '-', 7),
(856, 'IFK-0856', 'TERUMO Disp.Syringe with Needle 3 CC', '-', 7),
(857, 'IFK-0857', 'TERUMO Disp.Syringe with Needle 5 CC', '-', 7),
(858, 'IFK-0858', 'Total Protein', '-', 7),
(859, 'IFK-0859', 'Trigliserida', '-', 7),
(860, 'IFK-0860', 'TSIA (Uji)', '-', 7),
(861, 'IFK-0861', 'Turk 1x100ml', '-', 7),
(862, 'IFK-0862', 'Ureum', '-', 7),
(863, 'IFK-0863', 'Uric Acid', '-', 7),
(864, 'IFK-0864', 'Urine Analisa', '-', 7),
(865, 'IFK-0865', 'WIDA WI UNICAP Air untuk irigasi inf 1000 ml', '-', 7),
(866, 'IFK-0866', 'XN CAL', '-', 7),
(867, 'IFK-0867', 'XN Check BF', '-', 7),
(868, 'IFK-0868', 'XN-L Check (L1,L2,L3)', '-', 7),
(869, 'IFK-0869', 'Xylol', '-', 7),
(870, 'IFK-0870', 'Tabung EDTA 3 ml ( Steril )', '-', 7),
(871, 'IFK-0871', 'Cryotube 2ml', '-', 7),
(872, 'IFK-0872', 'Nmicrotrainer EDTA', '-', 7),
(873, 'IFK-0873', 'Flashback Needle 22 G', '-', 7),
(874, 'IFK-0874', 'Yellow Holder', '-', 7),
(875, 'IFK-0875', 'Wings Needle 27 G', '-', 7),
(876, 'IFK-0876', 'Tp Pipet 1 ml ( Steril )', '-', 7),
(877, 'IFK-0877', 'Plastik Clip 5x7 cm', '-', 7),
(878, 'IFK-0878', 'Plastik Clip 16x24 cm', '-', 7),
(879, 'IFK-0879', 'Ice Pake', '-', 7),
(880, 'IFK-0880', 'Ice Gel', '-', 7),
(881, 'IFK-0881', 'Parafilm', '-', 7),
(882, 'IFK-0882', 'Lakban', '-', 7),
(883, 'IFK-0883', 'Styrofoam ( 37,5 cm x 24 cm x 16 cm )', '-', 7),
(884, 'IFK-0884', 'Amphethamine 50 strp (Answer)', '-', 7),
(885, 'IFK-0885', 'Methamphetamine 50 strip (Answer)', '-', 7),
(886, 'IFK-0886', 'Opiet 50 strip (Monotest)', '-', 7),
(887, 'IFK-0887', 'Marijuana 50 strip (Answer)', '-', 7),
(888, 'IFK-0888', 'Cocain50 strip (Monotest)', '-', 7),
(889, 'IFK-0889', 'Benz0 50 strip (Answer)', '-', 7),
(890, 'IFK-0890', 'Drug Abuse Multi Panel 6 Parameter 25 card (Answer)', '-', 7),
(891, 'IFK-0891', 'Pregnancy strip test 50 strip', '-', 7),
(892, 'IFK-0892', 'HBSS ( reagen labkesda)', '-', 7),
(893, 'IFK-0893', 'Tabung EDTA 3 ml ( Steril )', '-', 8),
(894, 'IFK-0894', 'Cryotube 2ml', '-', 8),
(895, 'IFK-0895', 'Nmicrotrainer EDTA', '-', 8),
(896, 'IFK-0896', 'Flashback Needle 22 G', '-', 8),
(897, 'IFK-0897', 'Yellow Holder', '-', 8),
(898, 'IFK-0898', 'Wings Needle 27 G', '-', 8),
(899, 'IFK-0899', 'Tp Pipet 1 ml ( Steril )', '-', 8),
(900, 'IFK-0900', 'Plastik Clip 5x7 cm', '-', 8),
(901, 'IFK-0901', 'Plastik Clip 16x24 cm', '-', 8),
(902, 'IFK-0902', 'Ice Pake', '-', 8),
(903, 'IFK-0903', 'Ice Gel', '-', 8),
(904, 'IFK-0904', 'Parafilm', '-', 8),
(905, 'IFK-0905', 'Lakban', '-', 8),
(906, 'IFK-0906', 'Styrofoam ( 37,5 cm x 24 cm x 16 cm )', '-', 8),
(907, 'IFK-0907', 'Chlorhexidine gluconat 0.5% 5 liter', '-', 8),
(908, 'IFK-0908', 'Hazmat', '-', 8),
(909, 'IFK-0909', 'Masker N95 Dreamcan', '-', 8),
(910, 'IFK-0910', 'Masker N95 Aero', '-', 8);
INSERT INTO `tbl_obat` (`id_obat`, `kode_obat`, `nama_obat`, `satuan`, `id_kategori_obat`) VALUES
(911, 'IFK-0911', 'Rompi Vacinator', '-', 8),
(912, 'IFK-0912', 'Kartu Vaksin', '-', 8),
(913, 'IFK-0913', 'Brosur', '-', 8),
(914, 'IFK-0914', 'Booklet', '-', 8),
(915, 'IFK-0915', 'Buku peraturan per UU Covid', '-', 8),
(916, 'IFK-0916', 'Buku Kotak', '-', 8),
(917, 'IFK-0917', 'Masker KN 95 Covid', '-', 8),
(918, 'IFK-0918', 'Apd Vaksin Babun', '-', 8),
(919, 'IFK-0919', 'Buku PCV', '-', 8),
(920, 'IFK-0920', 'Roll Banner Imunisasi Ganda', '-', 8),
(921, 'IFK-0921', 'Roll Banner Imunisasi PCV', '-', 8),
(922, 'IFK-0922', 'Insektisida', '-', 8),
(923, 'IFK-0923', 'Face shield', '-', 8),
(924, 'IFK-0924', 'Masker earloop (Box/50 pcs) Hibah', '-', 8),
(925, 'IFK-0925', 'Plester Kain Roll 1,25cm x 4,5m', '-', 8),
(926, 'IFK-0926', 'Coverall (DAU)', '-', 8),
(927, 'IFK-0927', 'Sepatu Boot', '-', 8),
(928, 'IFK-0928', 'Goggle / Kaca Mata', '-', 8),
(929, 'IFK-0929', 'Pelindung Wajah/ Face Shiel', '-', 8),
(930, 'IFK-0930', 'Hazmat (PENDOPO)', '-', 8),
(931, 'IFK-0931', 'Handscoon (Pendopo))', '-', 8),
(932, 'IFK-0932', 'Spectra Frest Mint 200g/Prophylaxis Pasta', '-', 8),
(933, 'IFK-0933', 'Air raksa dental use', '-', 8),
(934, 'IFK-0934', 'Baju Pelindung ( Hazmad ) (Bantuan +DAU/Program)', '-', 8),
(935, 'IFK-0935', 'Kantong Limbah Medis Ukuran M Kesling)', '-', 8),
(936, 'IFK-0936', 'Kasa pembalut 40/80', '-', 8),
(937, 'IFK-0937', 'APD Vaksin Covid -19(BA-BUN)', '-', 8),
(938, 'IFK-0938', 'Spray Semprot Cold / Roeko Endo Frost', '-', 8),
(939, 'IFK-0939', 'Susu Formula 2 Plus / LAKTOGEN 2', '-', 8),
(940, 'IFK-0940', 'Masker Tali Bedah 3 Ply (APBN-TB)', '-', 8),
(941, 'IFK-0941', 'Susu Entrasol Active 160 gr', '-', 8),
(942, 'IFK-0942', 'Masker N95 (1860 s) Program - TB', '-', 8),
(943, 'IFK-0943', 'Susu Entrasol Active 160 gr', '-', 8),
(944, 'IFK-0944', 'Susu Entrasol Active 350 gr', '-', 8),
(945, 'IFK-0945', 'TruLab P6 x 5ml', '-', 8),
(946, 'IFK-0946', 'TruLab U6 x 3ml', '-', 8),
(947, 'IFK-0947', 'TruLab N6 x 5ml', '-', 8),
(948, 'IFK-0948', 'Syphilis (RB-TS50:50TES)', '-', 8),
(949, 'IFK-0949', 'Abate', '-', 9),
(950, 'IFK-0950', 'Insektisida sipermetrin', '-', 9),
(951, 'IFK-0951', 'BTI Larvasida Mosnon', '-', 9),
(952, 'IFK-0952', 'RDT DBD COMBO', '-', 9),
(953, 'IFK-0953', 'RDT CHIKUNGUNYA', '-', 9),
(954, 'IFK-0954', 'MMS TABLET', 'tablet', 10),
(955, 'IFK-0955', 'PIMTRAKOL SYR', 'btl', 10),
(956, 'BLUD-0001', 'Ambroxol Errita', 'Tablet', 11),
(957, 'BLUD-0002', 'Aminophilin Inj', 'Ampul', 11),
(958, 'BLUD-0003', 'Asam Askorbat 250 Mg', 'Tablet', 11),
(959, 'BLUD-0004', 'Betamethason 0.1%', 'Tube', 11),
(960, 'BLUD-0005', 'Cefadroxil Mepro', 'Kapsul', 11),
(961, 'BLUD-0006', 'Ceftriaxon Inj 1gr', 'Vial', 11),
(962, 'BLUD-0007', 'Diphenhydramine Inj', 'Ampul', 11),
(963, 'BLUD-0008', 'Dexametason Ijn', 'Ampul', 11),
(964, 'BLUD-0009', 'Genoit Salp Mata', 'Tube', 11),
(965, 'BLUD-0010', 'Glimepirid 2mg', 'Tablet', 11),
(966, 'BLUD-0011', 'Lerzin Tab', 'Kapsul', 11),
(967, 'BLUD-0012', 'Lidocain Inj', 'Ampul', 11),
(968, 'BLUD-0013', 'Loperamide', 'Tablet', 11),
(969, 'BLUD-0014', 'Metformin Hcl', 'Tablet', 11),
(970, 'BLUD-0015', 'Metamizol Inj Phapros', 'Ampul', 11),
(971, 'BLUD-0016', 'Piridoxin/Vit B6 10 Mg', 'Tablet', 11),
(972, 'BLUD-0017', 'Pirasetam Inj', 'Ampul', 11),
(973, 'BLUD-0018', 'Selediar Tab', 'Tablet', 11),
(974, 'BLUD-0019', 'Fitomenadion Inj', 'Ampul', 11),
(975, 'BLUD-0020', 'Infus Sodium Chloride 0,9% 500ml (Nacl 0,9%)', 'Botol', 11),
(976, 'BLUD-0021', 'Infus Ringer Lactate 500 Ml (Rl)', 'Botol', 11),
(977, 'BLUD-0022', 'Solathim Kap 500 Mg (Thiamphenicol)', 'Kapsul', 11),
(978, 'BLUD-0023', 'Amoxicilin 500 Mg', 'Kaplet', 11),
(979, 'BLUD-0024', 'Amlodipine 5 Mg Blud', 'Tablet', 11),
(980, 'BLUD-0025', 'Antasida Doen Tab 200mg', 'Tablet', 11),
(981, 'BLUD-0026', 'Cetirizine 10 Mg', 'Tablet', 11),
(982, 'BLUD-0027', 'Chlorpheniramine Maleam 4 Mg (Ctm)', 'Tablet', 11),
(983, 'BLUD-0028', 'Dexamethasone 0,5 Mg Tab', 'Tablet', 11),
(984, 'BLUD-0029', 'Ibuprofen 400 Mg Tab', 'Tablet', 11),
(985, 'BLUD-0030', 'Asam Mefenamat 500mg', 'Tablet', 11),
(986, 'BLUD-0031', 'Paracetamol 500 Mg Kap', 'Tablet', 11),
(987, 'BLUD-0032', 'Trifacalc(Kalk) 500mg', 'Tablet', 11),
(988, 'BLUD-0033', 'Trifacort (Prednison) 5 Mg', 'Tablet', 11),
(989, 'BLUD-0034', 'Vitamin B6 10 Mg', 'Tablet', 11),
(990, 'BLUD-0035', 'Lodecon Forte', 'Kaplet', 11),
(991, 'BLUD-0036', 'Meprovent Inh', 'Ampul', 11),
(992, 'BLUD-0037', 'Dexamethasone Inj Mepro 5mg/ml', 'Ampul', 11),
(993, 'BLUD-0038', 'Metamizole Sodium 500mg/ml Mepro', 'Ampul', 11),
(994, 'BLUD-0039', 'Dextrose 5% 500ml', 'Botol', 11),
(995, 'BLUD-0040', 'Alpara Tab', 'Tablet', 11),
(996, 'BLUD-0041', 'Metronidazole 500 Mg', 'Tablet', 11),
(997, 'BLUD-0042', 'Fenitoin 100mg', 'Kapsul', 11),
(998, 'BLUD-0043', 'Ringer Asetat 500 Ml/Asering', 'Botol', 11),
(999, 'BLUD-0044', 'Asam Askorbat 50 Mg', 'Tablet', 11),
(1000, 'BLUD-0045', 'Cefotaxime 1 Gr', 'Vial', 11),
(1001, 'BLUD-0046', 'Ranitidine Inj', 'Ampul', 11),
(1002, 'BLUD-0047', 'Ondansetron Inj Mg', 'Ampul', 11),
(1003, 'BLUD-0048', 'Paracetamol Infus', 'Botol', 11),
(1004, 'BLUD-0049', 'Clindamycin 150 Mg', 'Kapsul', 11),
(1005, 'BLUD-0050', 'Sodium Diclofenac 50 Mg', 'Tablet', 11),
(1006, 'BLUD-0051', 'Betahistin Mesilate 6 Mg', 'Tablet', 11),
(1007, 'BLUD-0052', 'Ciprofloxacin 500 Mg', 'Tablet', 11),
(1008, 'BLUD-0053', 'Cefixime 100 Mg', 'Kapsul', 11),
(1009, 'BLUD-0054', 'Clopidogrel 75 Mg', 'Tablet', 11),
(1010, 'BLUD-0055', 'Clozapine 25 Mg Blud', 'Tablet', 11),
(1011, 'BLUD-0056', 'Acifar Cr', 'Tube', 11),
(1012, 'BLUD-0057', 'Attapulgit 600 Mg Blud', 'Tablet', 11),
(1013, 'BLUD-0058', 'Amlodipin 10 Mg Blud', 'Tablet', 11),
(1014, 'BLUD-0059', 'Bisoprolol', 'Tablet', 11),
(1015, 'BLUD-0060', 'Cyanocobalamin/B12 Inj', 'Vial', 11),
(1016, 'BLUD-0061', 'Dextrose 40% 25 Ml', 'Fls', 11),
(1017, 'BLUD-0062', 'Genoint Salep Kulit', 'Tube', 11),
(1018, 'BLUD-0063', 'Genoint Tetes Mata', 'Tube', 11),
(1019, 'BLUD-0064', 'Ketoconazole Salep Kulit', 'Tube', 11),
(1020, 'BLUD-0065', 'Omeprazole Kapsul', 'Kapsul', 11),
(1021, 'BLUD-0066', 'Risperidone 2mg', 'Tablet', 11),
(1022, 'BLUD-0067', 'Salbutamol 2 Mg', 'Tablet', 11),
(1023, 'BLUD-0068', 'Vitamin B Complex', 'Tablet', 11),
(1024, 'BLUD-0069', 'Allopurinol 100 Mg', 'Tablet', 11),
(1025, 'BLUD-0070', 'Oksitosin Inj', 'Ampul', 11),
(1026, 'BLUD-0071', 'Pehacain Inj', 'Ampul', 11),
(1027, 'BLUD-0072', 'Hydrochlortiazid 25 Mg (Hct)', 'Tablet', 11),
(1028, 'BLUD-0073', 'Furosemid 40 Mg Tab', 'Tablet', 11),
(1029, 'BLUD-0074', 'Scotchbond Etchant', '-', 11),
(1030, 'BLUD-0075', 'Ethyl Chloride', 'Botol', 11),
(1031, 'BLUD-0076', 'Gentamicin Salep Kulit', 'Tube', 11),
(1032, 'BLUD-0077', 'Antrain Inj', 'Ampul', 11),
(1033, 'BLUD-0078', 'Hydrogen Peroxide 100ml', 'Botol', 11),
(1034, 'BLUD-0079', 'Gentamicin Salep Mata', 'Tube', 11),
(1035, 'BLUD-0080', 'Gentamicin Tetes Mata', 'Botol', 11),
(1036, 'BLUD-0081', 'Kloramfenikol Tetes Telinga', 'Botol', 11),
(1037, 'BLUD-0082', 'Kalsium Laktat 500 Mg', 'Tablet', 11),
(1038, 'BLUD-0083', 'Maxgenol', 'Pot', 11),
(1039, 'BLUD-0084', 'Simvastatin 20 Mg', '-', 11),
(1040, 'BLUD-0085', 'Cotrimoxazole Tablet', '-', 11),
(1041, 'BLUD-0086', 'Ketokonazole Tab 200mg', '-', 11),
(1042, 'BLUD-0087', 'IV Catheter No. 20g Use Safety', 'Pcs', 12),
(1043, 'BLUD-0088', 'Exam Glove M', 'Pcs', 12),
(1044, 'BLUD-0089', 'Exam Glove S', 'Pcs', 12),
(1045, 'BLUD-0090', 'Kasa Hidrofil 40x80 Onemed', 'Pcs', 12),
(1046, 'BLUD-0091', 'Spuit 1 CC Stera 100\'s', 'Pcs', 12),
(1047, 'BLUD-0092', 'Urobag 2000st T Valve (Urine Bag)', 'Pcs', 12),
(1048, 'BLUD-0093', 'Spuit 3 CC Stera 100\'s', 'Pcs', 12),
(1049, 'BLUD-0094', 'Infusion Set Dewasa BLUD', 'Pcs', 12),
(1050, 'BLUD-0095', 'Infusion Set Anak BLUD', 'Pcs', 12),
(1051, 'BLUD-0096', 'Umbilical Cord', 'Pcs', 12),
(1052, 'BLUD-0097', 'Uro One No. 16 10\'s', 'Pcs', 12),
(1053, 'BLUD-0098', 'Uro One No. 18 10\'s', 'Pcs', 12),
(1054, 'BLUD-0099', 'Uro One No. 14 10\'s', 'Pcs', 12),
(1055, 'BLUD-0100', 'IV Catheter 18g Box 50\'s', 'Pcs', 12),
(1056, 'BLUD-0101', 'IV Catheter 20g Box 50\'s', 'Pcs', 12),
(1057, 'BLUD-0102', 'IV Catheter 22g Box 50\'s', 'Pcs', 12),
(1058, 'BLUD-0103', 'IV Catheter 24g Box 50\'s', 'Pcs', 12),
(1059, 'BLUD-0104', 'IV Catheter 26g Box 50\'s', 'Pcs', 12),
(1060, 'BLUD-0105', 'Widal Typhi O 5ml', 'Botol', 12),
(1061, 'BLUD-0106', 'Widal Typhi H 5 Ml', 'Botol', 12),
(1062, 'BLUD-0107', 'Widal Paratyphi Ao 5 Ml', 'Botol', 12),
(1063, 'BLUD-0108', 'Widal Paratyphi Bo 5 Ml', 'Botol', 12),
(1064, 'BLUD-0109', 'Golda Anti-A 10 Ml', 'Botol', 12),
(1065, 'BLUD-0110', 'Golda Anti-B 10 Ml', 'Botol', 12),
(1066, 'BLUD-0111', 'Golda D 10 Ml', 'Botol', 12),
(1067, 'BLUD-0112', 'Golda Anti Ab 10 Ml', 'Botol', 12),
(1068, 'BLUD-0113', 'Maxiflow Dewasa', 'Pcs', 12),
(1069, 'BLUD-0114', 'Maxiflow Anak', 'Pcs', 12),
(1070, 'BLUD-0115', 'Plester Dorafix 10x5', 'Roll', 12),
(1071, 'BLUD-0116', 'Alkohol Swab Onemed 100\'s', 'Pcs', 13),
(1072, 'BLUD-0117', 'Kapas Pembalut Onemed 250gr', 'Pcs', 13),
(1073, 'BLUD-0118', 'Maxiflow Dewasa', 'Pcs', 13),
(1074, 'BLUD-0119', 'Underpad 60x90', 'Pcs', 13),
(1075, 'BLUD-0120', 'Spuit 5 Cc Stera', 'Pcs', 13),
(1076, 'BLUD-0121', 'Silk 3/0+ Jarum 1/2gt 35mm', 'Pcs', 13),
(1077, 'BLUD-0122', 'Oxiflow Soft (Bubble) Dewasa Besmed', 'Pcs', 13),
(1078, 'BLUD-0123', 'Exam Glove S Box 100\'s', 'Pcs', 13),
(1079, 'BLUD-0124', 'Exam Glove M Box 100\'s', 'Pcs', 13),
(1080, 'BLUD-0125', 'Exam Glove L Box 100\'s', 'Pcs', 13),
(1081, 'BLUD-0126', 'Blood Collection Tube Edta 3 Ml 100\'s', 'Pcs', 13),
(1082, 'BLUD-0127', 'Chrom 3/0+1/2gt 35mm', 'Pcs', 13),
(1083, 'BLUD-0128', 'IV Catheter 24g Box 50\'s Blud', '-', 13),
(1084, 'BLUD-0129', 'Maxiflow Dewasa', '-', 13),
(1085, 'BLUD-0130', 'Maxiflow Anak', '-', 13),
(1086, 'BLUD-0131', 'Plester Dorafix 10x5', '-', 13),
(1087, 'BLUD-0132', 'Iv Catheter 22g Box 50\'s Blud', '-', 13),
(1088, 'BLUD-0133', 'Oxiflow Soft Anak', 'Pcs', 13),
(1089, 'BLUD-0134', 'Spuit 1 Cc', 'Pcs', 13),
(1090, 'BLUD-0135', 'Spuit 3 Cc', 'Pcs', 13),
(1091, 'BLUD-0136', 'Spuit 5 Cc', 'Pcs', 13),
(1092, 'BLUD-0137', 'Urobag 2000st T Valve (Urine Bag)', 'Pcs', 13),
(1093, 'BLUD-0138', 'Uro One No. 16 10\'s Blud', 'Pcs', 13),
(1094, 'BLUD-0139', 'Uro One No. 18 10\'s Blud', 'Pcs', 13),
(1095, 'BLUD-0140', 'Uro One No. 14 10\'s Blud', 'Pcs', 13),
(1096, 'BLUD-0141', 'Bist Mess Onemed No.24', 'Pcs', 13),
(1097, 'BLUD-0142', 'Plesterin Roll 15cmx5m New', 'Pcs', 13),
(1098, 'BLUD-0143', 'Kasa Hidrofil 40x80', 'Pcs', 13),
(1099, 'BLUD-0144', 'Iv Catheter 20g Box 50\'s Blud', 'Roll', 13),
(1100, 'BLUD-0145', 'Dorafix 5x5', 'Roll', 13),
(1101, 'BLUD-0146', 'Umbilical Cord', 'Pcs', 13),
(1102, 'BLUD-0147', 'Surgical Glove No.7', 'Roll', 13),
(1103, 'BLUD-0148', 'Pot Dahak', 'Pcs', 13),
(1104, 'BLUD-0149', 'Nebulizer Mask Dewasa', 'Pcs', 13),
(1105, 'BLUD-0150', 'Nebulizer Mask Anak', 'Pcs', 13),
(1106, 'BLUD-0151', 'Stomach Tube Fr.16 Onemed', '-', 13),
(1107, 'BLUD-0152', 'Guedel Airway 80mm', '-', 13),
(1108, 'BLUD-0153', 'Guedel Airway 90mm', '-', 13),
(1109, 'BLUD-0154', 'Hi-Oxygen Mask Dewasa', '-', 13),
(1110, 'BLUD-0155', 'Object Glas \'72 Om', '-', 13),
(1111, 'BLUD-0156', 'Suction Cath+Finger 12', '-', 13),
(1112, 'BLUD-0157', 'Suction Cath+Finger 14', '-', 13),
(1113, 'BLUD-0158', 'Glove Gynecolog Steril Om', '-', 13),
(1114, 'BLUD-0159', 'Plesterin Bulat Plastik 100\'s', '-', 13),
(1115, 'BLUD-0160', 'Tranfusion Set Y Tube', '-', 13),
(1116, 'BLUD-0161', 'Nospirinal 80mg', '-', 13),
(1117, 'BLUD-0162', 'Ventasal Nebul', '-', 13),
(1118, 'BLUD-0163', 'Gluco Protein Test', 'Pcs', 14),
(1119, 'BLUD-0164', 'Ultra One Test Hamil', 'Pcs', 14),
(1120, 'BLUD-0165', 'Povidone Iodine 10% 300ml Onemed', 'Botol', 14),
(1121, 'BLUD-0166', 'Cotton Swab Steril Jumbo Onemed 100\'s', 'Pcs', 14),
(1122, 'BLUD-0167', 'Hb Mission Strip', 'Strip', 14),
(1123, 'BLUD-0168', 'Sgot Fs 4x25 Ml', 'Kit', 14),
(1124, 'BLUD-0169', 'Sgpt Fs 4x25 Ml', 'Kit', 14),
(1125, 'BLUD-0170', 'Blood Collection Tube Edta 3 Ml 100\'s', 'Pcs', 14),
(1126, 'BLUD-0171', 'Norma Control', 'Kit', 14),
(1127, 'BLUD-0172', 'Norma Ilyse3', 'Kit', 14),
(1128, 'BLUD-0173', 'Norma Isol3', 'Kit', 14),
(1129, 'BLUD-0174', 'One Care Urea 2x40ml', 'Kit', 14),
(1130, 'BLUD-0175', 'One Care Creatinine 1x100 Ml', 'Kit', 14),
(1131, 'BLUD-0176', 'One Care Gram Set 3x100ml', 'Kit', 14),
(1132, 'BLUD-0177', 'Autocheck Gula Darah', 'Botol', 14),
(1133, 'BLUD-0178', 'Autocheck Asam Urat', 'Botol', 14),
(1134, 'BLUD-0179', 'Norma Idil3 Ic-11731', 'Kit', 14),
(1135, 'BLUD-0180', 'Oxytocin Inj', 'Ampul', 14),
(1136, 'BLUD-0181', 'Glove Steril 7', 'Pcs', 14);

-- --------------------------------------------------------

--
-- Struktur dari tabel `tbl_pelayanan`
--

CREATE TABLE `tbl_pelayanan` (
  `id_pelayanan` int(11) NOT NULL,
  `jenis_pelayanan` varchar(50) NOT NULL COMMENT 'Cth: UMUM, BPJS'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `tbl_pelayanan`
--

INSERT INTO `tbl_pelayanan` (`id_pelayanan`, `jenis_pelayanan`) VALUES
(2, 'BPJS'),
(1, 'UMUM');

-- --------------------------------------------------------

--
-- Struktur dari tabel `tbl_poli`
--

CREATE TABLE `tbl_poli` (
  `id_poli` int(11) NOT NULL,
  `id_unit_stok_default` int(11) NOT NULL COMMENT 'KUNCI #1: Link ke sumber stok Poli (Poli Gigi -> Apotek, UGD -> UGD)',
  `nama_poli` varchar(100) NOT NULL COMMENT 'Cth: Poli Umum, Poli Gigi, UGD, Rawat Inap'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `tbl_poli`
--

INSERT INTO `tbl_poli` (`id_poli`, `id_unit_stok_default`, `nama_poli`) VALUES
(1, 2, 'Poli Umum'),
(2, 2, 'Poli KIA-KB'),
(3, 2, 'Poli Gigi'),
(4, 2, 'Poli MTBS'),
(5, 2, 'Poli HIV'),
(6, 2, 'Poli TBC'),
(7, 3, 'UGD'),
(8, 4, 'KB Poned'),
(9, 5, 'Rawat Inap');

-- --------------------------------------------------------

--
-- Struktur dari tabel `tbl_request_detail`
--

CREATE TABLE `tbl_request_detail` (
  `id_request_detail` bigint(20) NOT NULL,
  `id_request` bigint(20) NOT NULL,
  `id_obat` int(11) NOT NULL,
  `jumlah_request` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `tbl_request_detail`
--

INSERT INTO `tbl_request_detail` (`id_request_detail`, `id_request`, `id_obat`, `jumlah_request`) VALUES
(1, 1, 2, 1),
(2, 2, 1, 1),
(3, 2, 2, 4),
(4, 3, 1, 1),
(5, 3, 2, 1),
(6, 4, 1, 2);

-- --------------------------------------------------------

--
-- Struktur dari tabel `tbl_request_header`
--

CREATE TABLE `tbl_request_header` (
  `id_request` bigint(20) NOT NULL,
  `tgl_request` datetime NOT NULL,
  `id_user_request` int(11) NOT NULL COMMENT 'User Poli Belakang yg request',
  `id_unit_tujuan` int(11) NOT NULL COMMENT 'Unit yg minta (cth: UGD)',
  `status` enum('Pending','Completed','Cancelled') NOT NULL DEFAULT 'Pending',
  `keterangan_request` varchar(255) DEFAULT NULL,
  `id_user_approve` int(11) DEFAULT NULL COMMENT 'Admin yg memproses',
  `tgl_approve` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `tbl_request_header`
--

INSERT INTO `tbl_request_header` (`id_request`, `tgl_request`, `id_user_request`, `id_unit_tujuan`, `status`, `keterangan_request`, `id_user_approve`, `tgl_approve`) VALUES
(1, '2025-11-11 15:17:19', 7, 3, 'Completed', '', 11, '2025-11-18 10:27:38'),
(2, '2025-11-11 15:23:17', 7, 3, 'Completed', 'testing', 11, '2025-11-11 15:31:22'),
(3, '2026-02-03 19:26:53', 7, 3, 'Pending', '', NULL, NULL),
(4, '2026-02-03 19:41:05', 7, 3, 'Pending', '', NULL, NULL);

-- --------------------------------------------------------

--
-- Struktur dari tabel `tbl_resep_detail`
--

CREATE TABLE `tbl_resep_detail` (
  `id_resep_detail` bigint(20) NOT NULL,
  `id_resep` bigint(20) NOT NULL,
  `id_obat` int(11) NOT NULL,
  `id_unit_asal` int(11) NOT NULL COMMENT 'Akan diisi OTOMATIS oleh backend (bisa Apotek, bisa UGD, dll)',
  `jumlah_keluar` int(11) NOT NULL,
  `jenis_racikan` enum('Racikan','Non Racikan') NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `tbl_resep_detail`
--

INSERT INTO `tbl_resep_detail` (`id_resep_detail`, `id_resep`, `id_obat`, `id_unit_asal`, `jumlah_keluar`, `jenis_racikan`) VALUES
(1, 1, 1, 4, 1, 'Racikan'),
(2, 2, 1, 3, 1, 'Racikan'),
(4, 6, 2, 3, 1, 'Non Racikan');

-- --------------------------------------------------------

--
-- Struktur dari tabel `tbl_resep_header`
--

CREATE TABLE `tbl_resep_header` (
  `id_resep` bigint(20) NOT NULL,
  `tgl_resep` datetime NOT NULL,
  `nama_pasien` varchar(150) DEFAULT NULL,
  `id_poli` int(11) NOT NULL COMMENT 'Poli yg meresepkan',
  `id_pelayanan` int(11) NOT NULL,
  `id_user_pencatat` int(11) NOT NULL COMMENT 'Bisa Admin, bisa Poli Belakang',
  `kelengkapan_resep` enum('Lengkap','Tidak Lengkap') NOT NULL,
  `kesalahan_resep` enum('Ada','Tidak Ada') NOT NULL,
  `sesuai_formularium` enum('Sesuai','Tidak Sesuai') NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `tbl_resep_header`
--

INSERT INTO `tbl_resep_header` (`id_resep`, `tgl_resep`, `nama_pasien`, `id_poli`, `id_pelayanan`, `id_user_pencatat`, `kelengkapan_resep`, `kesalahan_resep`, `sesuai_formularium`) VALUES
(1, '2025-11-18 10:10:00', 'abcd', 8, 2, 11, 'Lengkap', 'Tidak Ada', 'Sesuai'),
(2, '2026-02-03 19:31:00', 'ian', 7, 1, 7, 'Lengkap', 'Tidak Ada', 'Sesuai'),
(6, '2026-02-03 19:48:00', 'ian', 7, 2, 7, 'Lengkap', 'Tidak Ada', 'Sesuai');

-- --------------------------------------------------------

--
-- Struktur dari tabel `tbl_role`
--

CREATE TABLE `tbl_role` (
  `id_role` int(11) NOT NULL,
  `nama_role` varchar(50) NOT NULL COMMENT 'Super Admin, Admin (Operator), Poli Depan, Poli Belakang',
  `deskripsi_role` varchar(255) DEFAULT NULL COMMENT 'Penjelasan role untuk UI Super Admin'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `tbl_role`
--

INSERT INTO `tbl_role` (`id_role`, `nama_role`, `deskripsi_role`) VALUES
(1, 'Super Admin', 'Pengelola Sistem. Mengatur akun user dan master data struktural.'),
(2, 'Admin (Operator)', 'Operator Farmasi. Mengelola stok, transaksi, laporan, dan master obat.'),
(3, 'Poli Depan', 'Viewer Pasif. Hanya bisa melihat stok Apotek dan membuat resep kertas.'),
(4, 'Poli Belakang', 'Operator Mini. Melihat stok unit sendiri dan input resep sendiri.');

-- --------------------------------------------------------

--
-- Struktur dari tabel `tbl_stok_inventori`
--

CREATE TABLE `tbl_stok_inventori` (
  `id_stok` int(11) NOT NULL,
  `id_obat` int(11) NOT NULL,
  `id_unit` int(11) NOT NULL,
  `stok_akhir` int(11) NOT NULL DEFAULT 0,
  `stok_minimum` int(11) NOT NULL DEFAULT 10 COMMENT 'Batas min PER UNIT (Gudang/Apotek/UGD).',
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp() COMMENT 'Tahu kapan stok terakhir di-update. Full otomatis.'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `tbl_stok_inventori`
--

INSERT INTO `tbl_stok_inventori` (`id_stok`, `id_obat`, `id_unit`, `stok_akhir`, `stok_minimum`, `updated_at`) VALUES
(1, 1, 1, 0, 10, '2025-11-18 10:10:41'),
(2, 1, 2, 15, 10, '2026-02-03 19:36:53'),
(3, 2, 1, 0, 10, '2025-11-18 10:27:38'),
(4, 1, 3, 0, 10, '2026-02-03 19:31:59'),
(5, 2, 3, 4, 10, '2026-02-03 19:51:27'),
(6, 1, 4, 3, 10, '2025-11-18 10:11:10');

-- --------------------------------------------------------

--
-- Struktur dari tabel `tbl_transaksi_masuk`
--

CREATE TABLE `tbl_transaksi_masuk` (
  `id_masuk` bigint(20) NOT NULL,
  `tgl_masuk` datetime NOT NULL,
  `id_obat` int(11) NOT NULL,
  `id_unit` int(11) NOT NULL,
  `jumlah_masuk` int(11) NOT NULL,
  `id_user_pencatat` int(11) NOT NULL,
  `keterangan` varchar(255) DEFAULT NULL COMMENT 'e.g., No. Faktur / Vendor'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `tbl_transaksi_masuk`
--

INSERT INTO `tbl_transaksi_masuk` (`id_masuk`, `tgl_masuk`, `id_obat`, `id_unit`, `jumlah_masuk`, `id_user_pencatat`, `keterangan`) VALUES
(1, '2025-11-11 14:15:00', 1, 1, 5, 11, 'ian'),
(2, '2025-11-11 15:31:00', 2, 1, 5, 11, ''),
(3, '2025-11-18 10:09:00', 1, 4, 3, 11, 'adgahs'),
(4, '2026-02-03 19:36:00', 1, 2, 10, 11, 'blabla');

-- --------------------------------------------------------

--
-- Struktur dari tabel `tbl_transaksi_transfer`
--

CREATE TABLE `tbl_transaksi_transfer` (
  `id_transfer` bigint(20) NOT NULL,
  `id_request` bigint(20) DEFAULT NULL COMMENT 'Link ke request (jika transfer ini hasil request). Bukti transfer.',
  `tgl_transfer` datetime NOT NULL,
  `id_obat` int(11) NOT NULL,
  `id_unit_asal` int(11) NOT NULL,
  `id_unit_tujuan` int(11) NOT NULL COMMENT 'Dipakai Admin utk restock Poli Belakang (Apotek -> UGD)',
  `jumlah` int(11) NOT NULL,
  `id_user_pencatat` int(11) NOT NULL,
  `keterangan` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `tbl_transaksi_transfer`
--

INSERT INTO `tbl_transaksi_transfer` (`id_transfer`, `id_request`, `tgl_transfer`, `id_obat`, `id_unit_asal`, `id_unit_tujuan`, `jumlah`, `id_user_pencatat`, `keterangan`) VALUES
(1, NULL, '2025-11-11 14:16:39', 1, 1, 2, 3, 11, ''),
(2, 2, '2025-11-11 15:31:22', 1, 1, 3, 1, 11, NULL),
(3, 2, '2025-11-11 15:31:22', 2, 1, 3, 4, 11, NULL),
(4, NULL, '2025-11-18 10:10:41', 1, 1, 4, 1, 11, 'hsgfhs'),
(5, 1, '2025-11-18 10:27:38', 2, 1, 3, 1, 11, NULL);

-- --------------------------------------------------------

--
-- Struktur dari tabel `tbl_unit`
--

CREATE TABLE `tbl_unit` (
  `id_unit` int(11) NOT NULL,
  `nama_unit` varchar(50) NOT NULL COMMENT 'Cth: GUDANG, APOTEK, UGD, RAWAT INAP, KB PONED'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `tbl_unit`
--

INSERT INTO `tbl_unit` (`id_unit`, `nama_unit`) VALUES
(2, 'APOTEK'),
(1, 'GUDANG'),
(4, 'KB PONED'),
(5, 'RAWAT INAP'),
(3, 'UGD');

-- --------------------------------------------------------

--
-- Struktur dari tabel `tbl_user`
--

CREATE TABLE `tbl_user` (
  `id_user` int(11) NOT NULL,
  `id_role` int(11) NOT NULL,
  `id_poli` int(11) DEFAULT NULL COMMENT 'Wajib diisi jika rolenya Poli. Null jika Admin.',
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL COMMENT 'Wajib di-hash (bcrypt)',
  `nama_lengkap` varchar(100) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `tbl_user`
--

INSERT INTO `tbl_user` (`id_user`, `id_role`, `id_poli`, `username`, `password`, `nama_lengkap`, `is_active`) VALUES
(1, 3, 1, 'poliumum', '$2y$10$CJNBHJLX0R0boiMIb8fjUuxlJZh7GBLkZYrxbJT.tHtoxm6ROEuHG', 'User Poli Umum', 1),
(2, 3, 2, 'polikiakb', '$2y$10$ChnLfEhiBh6dSnBxapiCIOeMarzz3nNlU8waYetIjURNFp/Q/2aee', 'User Poli KIA-KB', 1),
(3, 3, 3, 'poligigi', '$2y$10$bkrxXLLqjCmWbvTK5gjsVuLp6As6rgoX.q5IK38c5AemNJcKNgWp.', 'User Poli Gigi', 1),
(4, 3, 4, 'polimtbs', '$2y$10$8ODd0CJtYwlOdHuPSIkZb.f5Te./hUlRTBrDBdVFwYR5Aeu73SYrK', 'User Poli MTBS', 1),
(5, 3, 5, 'polihiv', '$2y$10$K7TfjNX/lma5j8WFrPwAOOYixjAegghOwCgit1sYH1C8BQuI2XB0K', 'User Poli HIV', 1),
(6, 3, 6, 'politbc', '$2y$10$HRgCeaE1auOAiO7qTSrWdOVu2VX0xXT9n6ok0cieJR3UtzL61K83S', 'User Poli TBC', 1),
(7, 4, 7, 'ugd', '$2y$10$PT7rrknzCoRxmX.TXtpEoOdcAuNgDDnEDlVzExR19LyGwAzmb6Ta6', 'User UGD', 1),
(8, 4, 8, 'kbponed', '$2y$10$CiCUhG.z8pK7mdvAJuCqCe3cdzKBRqd3BrAODvs5NA.UzLyZHS7Du', 'User KB Poned', 1),
(9, 4, 9, 'rawatinap', '$2y$10$0E6ROYusinUCOaiAVVPI9uij3tb1N611d5DdmyJuxvdtclPkhpFlK', 'User Rawat Inap', 1),
(10, 1, NULL, 'superadmin', '$2y$10$b/RxuouA1ZntUU9tkxMJSO8nNJTi0GtaHpnCV9ooSIwqwKSbnO.gG', 'Super Admin SIVO', 1),
(11, 2, NULL, 'admin', '$2y$10$jbrcpcHv5FBTVIx/.TeAfudE3Jd6kLDrsRncpH8EXvdAf3qVGyB7.', 'Admin', 1);

--
-- Indexes for dumped tables
--

--
-- Indeks untuk tabel `tbl_jenis_obat`
--
ALTER TABLE `tbl_jenis_obat`
  ADD PRIMARY KEY (`id_jenis_obat`),
  ADD UNIQUE KEY `nama_jenis_obat` (`nama_jenis_obat`);

--
-- Indeks untuk tabel `tbl_kategori_obat`
--
ALTER TABLE `tbl_kategori_obat`
  ADD PRIMARY KEY (`id_kategori_obat`),
  ADD KEY `id_jenis_obat` (`id_jenis_obat`);

--
-- Indeks untuk tabel `tbl_konfigurasi`
--
ALTER TABLE `tbl_konfigurasi`
  ADD PRIMARY KEY (`id_konfig`),
  ADD UNIQUE KEY `setting_key` (`setting_key`);

--
-- Indeks untuk tabel `tbl_log_stok`
--
ALTER TABLE `tbl_log_stok`
  ADD PRIMARY KEY (`id_log`),
  ADD KEY `tbl_log_stok_index_6` (`tgl_log`),
  ADD KEY `tbl_log_stok_index_7` (`id_obat`),
  ADD KEY `tbl_log_stok_index_8` (`id_unit`);

--
-- Indeks untuk tabel `tbl_obat`
--
ALTER TABLE `tbl_obat`
  ADD PRIMARY KEY (`id_obat`),
  ADD UNIQUE KEY `kode_obat` (`kode_obat`),
  ADD KEY `id_kategori_obat` (`id_kategori_obat`);

--
-- Indeks untuk tabel `tbl_pelayanan`
--
ALTER TABLE `tbl_pelayanan`
  ADD PRIMARY KEY (`id_pelayanan`),
  ADD UNIQUE KEY `jenis_pelayanan` (`jenis_pelayanan`);

--
-- Indeks untuk tabel `tbl_poli`
--
ALTER TABLE `tbl_poli`
  ADD PRIMARY KEY (`id_poli`),
  ADD UNIQUE KEY `nama_poli` (`nama_poli`),
  ADD KEY `id_unit_stok_default` (`id_unit_stok_default`);

--
-- Indeks untuk tabel `tbl_request_detail`
--
ALTER TABLE `tbl_request_detail`
  ADD PRIMARY KEY (`id_request_detail`),
  ADD KEY `id_request` (`id_request`),
  ADD KEY `id_obat` (`id_obat`);

--
-- Indeks untuk tabel `tbl_request_header`
--
ALTER TABLE `tbl_request_header`
  ADD PRIMARY KEY (`id_request`),
  ADD KEY `id_user_request` (`id_user_request`),
  ADD KEY `id_unit_tujuan` (`id_unit_tujuan`),
  ADD KEY `id_user_approve` (`id_user_approve`);

--
-- Indeks untuk tabel `tbl_resep_detail`
--
ALTER TABLE `tbl_resep_detail`
  ADD PRIMARY KEY (`id_resep_detail`),
  ADD KEY `tbl_resep_detail_index_4` (`id_obat`),
  ADD KEY `id_resep` (`id_resep`),
  ADD KEY `id_unit_asal` (`id_unit_asal`);

--
-- Indeks untuk tabel `tbl_resep_header`
--
ALTER TABLE `tbl_resep_header`
  ADD PRIMARY KEY (`id_resep`),
  ADD KEY `tbl_resep_header_index_2` (`tgl_resep`),
  ADD KEY `tbl_resep_header_index_3` (`id_poli`),
  ADD KEY `id_pelayanan` (`id_pelayanan`),
  ADD KEY `id_user_pencatat` (`id_user_pencatat`);

--
-- Indeks untuk tabel `tbl_role`
--
ALTER TABLE `tbl_role`
  ADD PRIMARY KEY (`id_role`),
  ADD UNIQUE KEY `nama_role` (`nama_role`);

--
-- Indeks untuk tabel `tbl_stok_inventori`
--
ALTER TABLE `tbl_stok_inventori`
  ADD PRIMARY KEY (`id_stok`),
  ADD UNIQUE KEY `tbl_stok_inventori_index_5` (`id_obat`,`id_unit`),
  ADD KEY `id_unit` (`id_unit`);

--
-- Indeks untuk tabel `tbl_transaksi_masuk`
--
ALTER TABLE `tbl_transaksi_masuk`
  ADD PRIMARY KEY (`id_masuk`),
  ADD KEY `tbl_transaksi_masuk_index_0` (`tgl_masuk`),
  ADD KEY `id_obat` (`id_obat`),
  ADD KEY `id_unit` (`id_unit`),
  ADD KEY `id_user_pencatat` (`id_user_pencatat`);

--
-- Indeks untuk tabel `tbl_transaksi_transfer`
--
ALTER TABLE `tbl_transaksi_transfer`
  ADD PRIMARY KEY (`id_transfer`),
  ADD KEY `tbl_transaksi_transfer_index_1` (`tgl_transfer`),
  ADD KEY `id_request` (`id_request`),
  ADD KEY `id_obat` (`id_obat`),
  ADD KEY `id_unit_asal` (`id_unit_asal`),
  ADD KEY `id_unit_tujuan` (`id_unit_tujuan`),
  ADD KEY `id_user_pencatat` (`id_user_pencatat`);

--
-- Indeks untuk tabel `tbl_unit`
--
ALTER TABLE `tbl_unit`
  ADD PRIMARY KEY (`id_unit`),
  ADD UNIQUE KEY `nama_unit` (`nama_unit`);

--
-- Indeks untuk tabel `tbl_user`
--
ALTER TABLE `tbl_user`
  ADD PRIMARY KEY (`id_user`),
  ADD UNIQUE KEY `username` (`username`),
  ADD KEY `id_role` (`id_role`),
  ADD KEY `id_poli` (`id_poli`);

--
-- AUTO_INCREMENT untuk tabel yang dibuang
--

--
-- AUTO_INCREMENT untuk tabel `tbl_jenis_obat`
--
ALTER TABLE `tbl_jenis_obat`
  MODIFY `id_jenis_obat` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT untuk tabel `tbl_kategori_obat`
--
ALTER TABLE `tbl_kategori_obat`
  MODIFY `id_kategori_obat` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=65;

--
-- AUTO_INCREMENT untuk tabel `tbl_konfigurasi`
--
ALTER TABLE `tbl_konfigurasi`
  MODIFY `id_konfig` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT untuk tabel `tbl_log_stok`
--
ALTER TABLE `tbl_log_stok`
  MODIFY `id_log` bigint(20) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=20;

--
-- AUTO_INCREMENT untuk tabel `tbl_obat`
--
ALTER TABLE `tbl_obat`
  MODIFY `id_obat` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1137;

--
-- AUTO_INCREMENT untuk tabel `tbl_pelayanan`
--
ALTER TABLE `tbl_pelayanan`
  MODIFY `id_pelayanan` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT untuk tabel `tbl_poli`
--
ALTER TABLE `tbl_poli`
  MODIFY `id_poli` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT untuk tabel `tbl_request_detail`
--
ALTER TABLE `tbl_request_detail`
  MODIFY `id_request_detail` bigint(20) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT untuk tabel `tbl_request_header`
--
ALTER TABLE `tbl_request_header`
  MODIFY `id_request` bigint(20) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT untuk tabel `tbl_resep_detail`
--
ALTER TABLE `tbl_resep_detail`
  MODIFY `id_resep_detail` bigint(20) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT untuk tabel `tbl_resep_header`
--
ALTER TABLE `tbl_resep_header`
  MODIFY `id_resep` bigint(20) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT untuk tabel `tbl_role`
--
ALTER TABLE `tbl_role`
  MODIFY `id_role` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT untuk tabel `tbl_stok_inventori`
--
ALTER TABLE `tbl_stok_inventori`
  MODIFY `id_stok` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT untuk tabel `tbl_transaksi_masuk`
--
ALTER TABLE `tbl_transaksi_masuk`
  MODIFY `id_masuk` bigint(20) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT untuk tabel `tbl_transaksi_transfer`
--
ALTER TABLE `tbl_transaksi_transfer`
  MODIFY `id_transfer` bigint(20) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT untuk tabel `tbl_unit`
--
ALTER TABLE `tbl_unit`
  MODIFY `id_unit` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT untuk tabel `tbl_user`
--
ALTER TABLE `tbl_user`
  MODIFY `id_user` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=19;

--
-- Ketidakleluasaan untuk tabel pelimpahan (Dumped Tables)
--

--
-- Ketidakleluasaan untuk tabel `tbl_kategori_obat`
--
ALTER TABLE `tbl_kategori_obat`
  ADD CONSTRAINT `fk_kategori_ke_jenis` FOREIGN KEY (`id_jenis_obat`) REFERENCES `tbl_jenis_obat` (`id_jenis_obat`);

--
-- Ketidakleluasaan untuk tabel `tbl_log_stok`
--
ALTER TABLE `tbl_log_stok`
  ADD CONSTRAINT `tbl_log_stok_ibfk_1` FOREIGN KEY (`id_obat`) REFERENCES `tbl_obat` (`id_obat`),
  ADD CONSTRAINT `tbl_log_stok_ibfk_2` FOREIGN KEY (`id_unit`) REFERENCES `tbl_unit` (`id_unit`);

--
-- Ketidakleluasaan untuk tabel `tbl_obat`
--
ALTER TABLE `tbl_obat`
  ADD CONSTRAINT `fk_obat_ke_kategori` FOREIGN KEY (`id_kategori_obat`) REFERENCES `tbl_kategori_obat` (`id_kategori_obat`);

--
-- Ketidakleluasaan untuk tabel `tbl_poli`
--
ALTER TABLE `tbl_poli`
  ADD CONSTRAINT `tbl_poli_ibfk_1` FOREIGN KEY (`id_unit_stok_default`) REFERENCES `tbl_unit` (`id_unit`);

--
-- Ketidakleluasaan untuk tabel `tbl_request_detail`
--
ALTER TABLE `tbl_request_detail`
  ADD CONSTRAINT `tbl_request_detail_ibfk_1` FOREIGN KEY (`id_request`) REFERENCES `tbl_request_header` (`id_request`),
  ADD CONSTRAINT `tbl_request_detail_ibfk_2` FOREIGN KEY (`id_obat`) REFERENCES `tbl_obat` (`id_obat`);

--
-- Ketidakleluasaan untuk tabel `tbl_request_header`
--
ALTER TABLE `tbl_request_header`
  ADD CONSTRAINT `tbl_request_header_ibfk_1` FOREIGN KEY (`id_user_request`) REFERENCES `tbl_user` (`id_user`),
  ADD CONSTRAINT `tbl_request_header_ibfk_2` FOREIGN KEY (`id_unit_tujuan`) REFERENCES `tbl_unit` (`id_unit`),
  ADD CONSTRAINT `tbl_request_header_ibfk_3` FOREIGN KEY (`id_user_approve`) REFERENCES `tbl_user` (`id_user`);

--
-- Ketidakleluasaan untuk tabel `tbl_resep_detail`
--
ALTER TABLE `tbl_resep_detail`
  ADD CONSTRAINT `tbl_resep_detail_ibfk_1` FOREIGN KEY (`id_resep`) REFERENCES `tbl_resep_header` (`id_resep`),
  ADD CONSTRAINT `tbl_resep_detail_ibfk_2` FOREIGN KEY (`id_obat`) REFERENCES `tbl_obat` (`id_obat`),
  ADD CONSTRAINT `tbl_resep_detail_ibfk_3` FOREIGN KEY (`id_unit_asal`) REFERENCES `tbl_unit` (`id_unit`);

--
-- Ketidakleluasaan untuk tabel `tbl_resep_header`
--
ALTER TABLE `tbl_resep_header`
  ADD CONSTRAINT `tbl_resep_header_ibfk_1` FOREIGN KEY (`id_poli`) REFERENCES `tbl_poli` (`id_poli`),
  ADD CONSTRAINT `tbl_resep_header_ibfk_2` FOREIGN KEY (`id_pelayanan`) REFERENCES `tbl_pelayanan` (`id_pelayanan`),
  ADD CONSTRAINT `tbl_resep_header_ibfk_3` FOREIGN KEY (`id_user_pencatat`) REFERENCES `tbl_user` (`id_user`);

--
-- Ketidakleluasaan untuk tabel `tbl_stok_inventori`
--
ALTER TABLE `tbl_stok_inventori`
  ADD CONSTRAINT `tbl_stok_inventori_ibfk_1` FOREIGN KEY (`id_obat`) REFERENCES `tbl_obat` (`id_obat`),
  ADD CONSTRAINT `tbl_stok_inventori_ibfk_2` FOREIGN KEY (`id_unit`) REFERENCES `tbl_unit` (`id_unit`);

--
-- Ketidakleluasaan untuk tabel `tbl_transaksi_masuk`
--
ALTER TABLE `tbl_transaksi_masuk`
  ADD CONSTRAINT `tbl_transaksi_masuk_ibfk_1` FOREIGN KEY (`id_obat`) REFERENCES `tbl_obat` (`id_obat`),
  ADD CONSTRAINT `tbl_transaksi_masuk_ibfk_2` FOREIGN KEY (`id_unit`) REFERENCES `tbl_unit` (`id_unit`),
  ADD CONSTRAINT `tbl_transaksi_masuk_ibfk_3` FOREIGN KEY (`id_user_pencatat`) REFERENCES `tbl_user` (`id_user`);

--
-- Ketidakleluasaan untuk tabel `tbl_transaksi_transfer`
--
ALTER TABLE `tbl_transaksi_transfer`
  ADD CONSTRAINT `tbl_transaksi_transfer_ibfk_1` FOREIGN KEY (`id_request`) REFERENCES `tbl_request_header` (`id_request`),
  ADD CONSTRAINT `tbl_transaksi_transfer_ibfk_2` FOREIGN KEY (`id_obat`) REFERENCES `tbl_obat` (`id_obat`),
  ADD CONSTRAINT `tbl_transaksi_transfer_ibfk_3` FOREIGN KEY (`id_unit_asal`) REFERENCES `tbl_unit` (`id_unit`),
  ADD CONSTRAINT `tbl_transaksi_transfer_ibfk_4` FOREIGN KEY (`id_unit_tujuan`) REFERENCES `tbl_unit` (`id_unit`),
  ADD CONSTRAINT `tbl_transaksi_transfer_ibfk_5` FOREIGN KEY (`id_user_pencatat`) REFERENCES `tbl_user` (`id_user`);

--
-- Ketidakleluasaan untuk tabel `tbl_user`
--
ALTER TABLE `tbl_user`
  ADD CONSTRAINT `tbl_user_ibfk_1` FOREIGN KEY (`id_role`) REFERENCES `tbl_role` (`id_role`),
  ADD CONSTRAINT `tbl_user_ibfk_2` FOREIGN KEY (`id_poli`) REFERENCES `tbl_poli` (`id_poli`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
