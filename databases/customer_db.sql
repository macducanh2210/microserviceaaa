SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

-- ======================================================
-- KHACHHANG - Customer accounts with auth
-- ======================================================
CREATE TABLE `khachhang` (
  `ID` int(11) NOT NULL AUTO_INCREMENT,
  `EMAIL` varchar(100) NOT NULL UNIQUE,
  `PASSWORD` varchar(255) NOT NULL,
  `HOTEN` varchar(100) NOT NULL,
  `SODIENTHOAI` varchar(20),
  `DIACHI` varchar(255),
  `NGAYSINH` date,
  `GIOITINH` tinyint(1) DEFAULT 0,
  `TICHDIEM` int(11) DEFAULT 0,
  `OTP` varchar(6),
  `OTP_EXPIRES` datetime,
  `OTP_TYPE` enum('register','forgot') DEFAULT NULL,
  `EMAIL_VERIFIED` tinyint(1) DEFAULT 0,
  `TRANGTHAI` tinyint(1) DEFAULT 1,
  `CREATED_AT` timestamp DEFAULT CURRENT_TIMESTAMP,
  `UPDATED_AT` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`ID`),
  KEY `idx_email` (`EMAIL`),
  KEY `idx_email_verified` (`EMAIL_VERIFIED`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ======================================================
-- GIO_HANG - Shopping cart (one per customer)
-- ======================================================
CREATE TABLE `gio_hang` (
  `ID` int(11) NOT NULL AUTO_INCREMENT,
  `IDKHACHHANG` int(11) NOT NULL UNIQUE,
  `GHICHU` longtext NOT NULL,
  `CREATED_AT` timestamp DEFAULT CURRENT_TIMESTAMP,
  `UPDATED_AT` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`ID`),
  KEY `idx_idkhachhang` (`IDKHACHHANG`),
  FOREIGN KEY (`IDKHACHHANG`) REFERENCES `khachhang`(`ID`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ======================================================
-- DIA_CHI_KHACHHANG - Customer addresses
-- ======================================================
CREATE TABLE `dia_chi_khachhang` (
  `ID` int(11) NOT NULL AUTO_INCREMENT,
  `IDKHACHHANG` int(11) NOT NULL,
  `TEN_DIA_CHI` varchar(100),
  `THANH_PHO` varchar(100),
  `HUYEN` varchar(100),
  `XA_PHUONG` varchar(100),
  `DIA_CHI_CHI_TIET` varchar(255),
  `SDT_NHA` varchar(10),
  `MA_BCHC` int(11),
  `LA_MAC_DINH` tinyint(1) DEFAULT 0,
  `CREATED_AT` timestamp DEFAULT CURRENT_TIMESTAMP,
  `UPDATED_AT` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`ID`),
  KEY `idx_idkhachhang` (`IDKHACHHANG`),
  FOREIGN KEY (`IDKHACHHANG`) REFERENCES `khachhang`(`ID`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ======================================================
-- DANH_SACH_YEU_THICH - Wishlist
-- ======================================================
CREATE TABLE `danh_sach_yeu_thich` (
  `ID` int(11) NOT NULL AUTO_INCREMENT,
  `IDKHACHHANG` int(11) NOT NULL,
  `IDCHITIETSANPHAM` int(11) NOT NULL,
  `CREATED_AT` timestamp DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`ID`),
  KEY `idx_idkhachhang` (`IDKHACHHANG`),
  UNIQUE KEY `unique_customer_product` (`IDKHACHHANG`, `IDCHITIETSANPHAM`),
  FOREIGN KEY (`IDKHACHHANG`) REFERENCES `khachhang`(`ID`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET CHARACTER_SET_CONNECTION=@OLD_COLLATION_CONNECTION */;
COMMIT;
