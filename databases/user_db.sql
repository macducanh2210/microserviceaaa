SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

CREATE TABLE `khachhang` (
  `ID` int(11) NOT NULL,
  `HOTEN` varchar(50) NOT NULL,
  `SODIENTHOAI` varchar(20) NOT NULL,
  `DIACHI` varchar(200) DEFAULT '',
  `TICHDIEM` int(11) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `khachhang` (`ID`, `HOTEN`, `SODIENTHOAI`, `DIACHI`, `TICHDIEM`) VALUES
(10001, 'ADMIN', '88888888', 'ADMIN', 0),
(10002, 'Đỗ Văn Duy', '0345678912', '', 0),
(10003, 'Nguyễn Quang Ngọc', '0345678912', '', 0),
(10004, 'Bùi Việt Đức', '0345678912', 'Hoàn Kiếm', 0),
(10005, 'Nguyễn Đức Thành', '0345678912', '', 0);

CREATE TABLE `nhanvien` (
  `ID` int(11) NOT NULL,
  `HOTEN` varchar(50) NOT NULL,
  `NGAYSINH` datetime NOT NULL,
  `GIOITINH` tinyint(1) NOT NULL,
  `DIACHI` varchar(200) NOT NULL,
  `SODIENTHOAI` varchar(20) NOT NULL,
  `EMAIL` varchar(100) NOT NULL,
  `CHUCVU` varchar(50) NOT NULL,
  `MUCLUONG` decimal(15,0) NOT NULL,
  `ANHDAIDIEN` varchar(100) NOT NULL,
  `MATKHAU` varchar(50) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `nhanvien` (`ID`, `HOTEN`, `NGAYSINH`, `GIOITINH`, `DIACHI`, `SODIENTHOAI`, `EMAIL`, `CHUCVU`, `MUCLUONG`, `ANHDAIDIEN`, `MATKHAU`) VALUES
(10001, 'Nguyễn Ngọc Toàn', '2001-01-01 00:00:00', 1, 'Hà Nội', '0345678901', 'ngoc@gmail.com', 'NHÂN VIÊN BÁN HÀNG', 5000000, 'toan.jpg', '12345678'),
(10002, 'Phạm Trung Dũng', '2001-01-01 00:00:00', 1, 'Hà Nam', '0345678901', 'duy@gmail.com', 'NHÂN VIÊN THU NGÂN', 5500000, 'dung.jpg', '12345678'),
(10003, 'Mạc Đức Anh', '2001-01-01 00:00:00', 1, 'Hải Dương', '0345678901', 'pk90x123@gmail.com', 'QUẢN LÝ', 5500000, 'mda.jpg', '12345678'),
(10004, 'Yu Văn Ki', '2001-01-01 00:00:00', 1, 'Quảng Trị', '0345678901', 'quang@gmail.com', 'NHÂN VIÊN BÁN HÀNG', 5500000, 'yuki.jpg', '12345678'),
(10005, 'Trần Hữu Long', '2001-01-01 00:00:00', 1, 'Thanh Hóa', '0345678901', 'thanh@gmail.com', 'NHÂN VIÊN BÁN HÀNG', 5500000, 'long.jpg', '12345678'),
(10006, 'Hoàng Quốc Huy', '2001-01-01 00:00:00', 1, 'Nghệ An', '0345678901', 'huy@gmail.com', 'NHÂN VIÊN BÁN HÀNG', 5500000, 'images.jpg', '1'),
(10007, 'em Mạc', '2005-10-22 00:00:00', 1, 'nam sách', '0862559041', 'kk@gmail.com', 'QUẢN LÝ', 123123, 'images\\question.png', '12345678');

ALTER TABLE `khachhang`
  ADD PRIMARY KEY (`ID`);

ALTER TABLE `nhanvien`
  ADD PRIMARY KEY (`ID`);

ALTER TABLE `khachhang`
  MODIFY `ID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10006;

ALTER TABLE `nhanvien`
  MODIFY `ID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10008;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
