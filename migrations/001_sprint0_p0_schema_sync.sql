-- ==========================================================
-- SPRINT 0 - TIKET P0-01: SCHEMA SYNCHRONIZATION & CONSTRAINTS
-- SIVO Puskesmas Database Migration
-- Target: MariaDB 10.4+ / MySQL 8.0+
-- ==========================================================

-- 1. Tambahkan tipe_request pada tbl_request_header
ALTER TABLE `tbl_request_header` 
ADD COLUMN IF NOT EXISTS `tipe_request` ENUM('Poli_ke_Apotek', 'Apotek_ke_Gudang') 
NOT NULL DEFAULT 'Poli_ke_Apotek' AFTER `id_unit_tujuan`;

-- 2. Perluas enum status tbl_request_header untuk mendukung Partial delivery
ALTER TABLE `tbl_request_header` 
MODIFY COLUMN `status` ENUM('Pending', 'Partial', 'Completed', 'Cancelled') 
NOT NULL DEFAULT 'Pending';

-- 3. Tambahkan kolom jumlah_terpenuhi pada tbl_request_detail
ALTER TABLE `tbl_request_detail` 
ADD COLUMN IF NOT EXISTS `jumlah_terpenuhi` INT(11) 
NOT NULL DEFAULT 0 AFTER `jumlah_request`;

-- 4. Tambahkan CHECK constraint stok_akhir >= 0 pada tbl_stok_inventori
-- (Gunakan IF NOT EXISTS atau abaikan jika sudah ada)
SET @constraint_exists = (
    SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS 
    WHERE TABLE_SCHEMA = DATABASE() 
    AND TABLE_NAME = 'tbl_stok_inventori' 
    AND CONSTRAINT_NAME = 'chk_stok_nonneg'
);

SET @sql = IF(@constraint_exists = 0, 
    'ALTER TABLE `tbl_stok_inventori` ADD CONSTRAINT `chk_stok_nonneg` CHECK (`stok_akhir` >= 0)', 
    'SELECT "Constraint chk_stok_nonneg already exists"'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 5. Tambahkan composite index performa untuk log stok dan resep detail
SET @idx_log_exists = (
    SELECT COUNT(*) FROM information_schema.STATISTICS 
    WHERE TABLE_SCHEMA = DATABASE() 
    AND TABLE_NAME = 'tbl_log_stok' 
    AND INDEX_NAME = 'idx_log_obat_unit_tgl'
);

SET @sql_idx1 = IF(@idx_log_exists = 0, 
    'ALTER TABLE `tbl_log_stok` ADD INDEX `idx_log_obat_unit_tgl` (`id_obat`, `id_unit`, `tgl_log`)', 
    'SELECT "Index idx_log_obat_unit_tgl already exists"'
);
PREPARE stmt_idx1 FROM @sql_idx1;
EXECUTE stmt_idx1;
DEALLOCATE PREPARE stmt_idx1;

SET @idx_resep_exists = (
    SELECT COUNT(*) FROM information_schema.STATISTICS 
    WHERE TABLE_SCHEMA = DATABASE() 
    AND TABLE_NAME = 'tbl_resep_detail' 
    AND INDEX_NAME = 'idx_resep_detail_obat'
);

SET @sql_idx2 = IF(@idx_resep_exists = 0, 
    'ALTER TABLE `tbl_resep_detail` ADD INDEX `idx_resep_detail_obat` (`id_obat`, `id_resep`)', 
    'SELECT "Index idx_resep_detail_obat already exists"'
);
PREPARE stmt_idx2 FROM @sql_idx2;
EXECUTE stmt_idx2;
DEALLOCATE PREPARE stmt_idx2;
