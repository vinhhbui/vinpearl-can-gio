-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Máy chủ: 127.0.0.1
-- Thời gian đã tạo: Th12 09, 2025 lúc 04:26 PM
-- Phiên bản máy phục vụ: 10.4.32-MariaDB
-- Phiên bản PHP: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Cơ sở dữ liệu: `hotel_db`
--

DELIMITER $$
--
-- Thủ tục
--
CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_assign_room` (IN `p_room_id` INT, IN `p_checkin` DATE, IN `p_checkout` DATE, IN `p_late_checkout` TINYINT)   BEGIN
  SELECT rn.id
  FROM room_numbers rn
  WHERE rn.room_id = p_room_id
    AND rn.status != 'maintenance'
    AND NOT EXISTS (
      SELECT 1 
      FROM bookings b
      WHERE b.room_number_id = rn.id
        AND b.booking_status NOT IN ('cancelled', 'no_show', 'checked_out')
        AND (
            -- Xung đột ngày thường
            (b.checkin_date < p_checkout AND b.checkout_date > p_checkin)
            OR 
            -- Xung đột do khách cũ trả trễ (mình không thể vào lúc 14h)
            (b.checkout_date = p_checkin AND b.late_checkout = 1)
            OR 
            -- Xung đột do mình muốn trả trễ (khách mới không thể vào lúc 14h hôm sau)
            (p_late_checkout = 1 AND b.checkin_date = p_checkout)
        )
    )
  ORDER BY rn.floor ASC, rn.room_number ASC
  LIMIT 1;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_calculate_price` (IN `p_room_id` INT, IN `p_checkin` DATE, IN `p_checkout` DATE, IN `p_adults` INT, IN `p_children` INT, OUT `p_base_price` DECIMAL(10,2), OUT `p_num_nights` INT)   BEGIN
  DECLARE v_price_per_night DECIMAL(10,2);
  
  SELECT price_per_night INTO v_price_per_night
  FROM rooms
  WHERE id = p_room_id;
  
  SET p_num_nights = DATEDIFF(p_checkout, p_checkin);
  SET p_base_price = v_price_per_night * p_num_nights;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_check_availability` (IN `p_room_id` INT, IN `p_checkin` DATE, IN `p_checkout` DATE)   BEGIN
  SELECT rn.id, rn.room_number, rn.floor
  FROM room_numbers rn
  WHERE rn.room_id = p_room_id
    AND rn.status != 'maintenance' -- Only exclude maintenance; 'occupied' is irrelevant for future dates
    AND NOT EXISTS (
      SELECT 1 
      FROM bookings b
      WHERE b.room_number_id = rn.id
        AND b.booking_status NOT IN ('cancelled', 'no_show') -- Ignore cancelled bookings
        -- Overlap Logic: (StartA < EndB) and (EndA > StartB)
        AND b.checkin_date < p_checkout
        AND b.checkout_date > p_checkin
    )
  LIMIT 1;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_check_option_availability` (IN `p_room_id` INT, IN `p_checkin` DATE, IN `p_checkout` DATE, IN `p_check_late` TINYINT)   BEGIN
  SELECT COUNT(rn.id) as count
  FROM room_numbers rn
  WHERE rn.room_id = p_room_id
    AND rn.status != 'maintenance'
    AND NOT EXISTS (
      SELECT 1 
      FROM bookings b
      WHERE b.room_number_id = rn.id
        AND b.booking_status NOT IN ('cancelled', 'no_show', 'checked_out')
        AND (
            -- 1. Xung đột ngày tiêu chuẩn
            (b.checkin_date < p_checkout AND b.checkout_date > p_checkin)
            
            OR 
            
            -- 2. Xung đột do khách cũ trả phòng trễ (Late Checkout)
            -- Khách cũ trả phòng trễ vào ngày mình nhận phòng -> Xung đột
            (b.checkout_date = p_checkin AND b.late_checkout = 1)

            OR

            -- 3. Xung đột do mình muốn Check-out muộn
            -- Mình muốn Check-out muộn, nhưng khách mới sẽ đến vào ngày đó
            (p_check_late = 1 AND b.checkin_date = p_checkout)
        )
    );
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_check_room_availability` (IN `p_room_number_id` INT, IN `p_checkin` DATE, IN `p_checkout` DATE)   BEGIN
  SELECT COUNT(*) as is_available
  FROM room_numbers rn
  WHERE rn.id = p_room_number_id
    AND rn.status != 'maintenance'
    AND NOT EXISTS (
      SELECT 1 
      FROM bookings b
      WHERE b.room_number_id = rn.id
        AND b.booking_status NOT IN ('cancelled', 'no_show', 'checked_out')
        AND (
            (b.checkin_date < p_checkout AND b.checkout_date > p_checkin)
            OR
            -- Thêm kiểm tra Late Checkout của khách cũ
            (b.checkout_date = p_checkin AND b.late_checkout = 1)
        )
    );
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_count_available_rooms` (IN `p_room_id` INT, IN `p_checkin` DATE, IN `p_checkout` DATE)   BEGIN
  SELECT COUNT(rn.id) as available_count
  FROM room_numbers rn
  WHERE rn.room_id = p_room_id
    AND rn.status != 'maintenance'
    AND NOT EXISTS (
      SELECT 1 
      FROM bookings b
      WHERE b.room_number_id = rn.id
        AND b.booking_status NOT IN ('cancelled', 'no_show', 'checked_out')
        AND (
            -- Condition 1: Xung đột ngày tiêu chuẩn (khoảng thời gian chồng lấn)
            (b.checkin_date < p_checkout AND b.checkout_date > p_checkin)
            
            OR
            
            -- Condition 2: Xung đột do khách cũ Check-out trễ
            -- Nếu khách cũ trả phòng vào đúng ngày mình Check-in (b.checkout_date = p_checkin)
            -- VÀ khách đó đăng ký Late Check-out (b.late_checkout = 1)
            -- => Phòng chưa sẵn sàng lúc 14:00 -> KHÔNG TÍNH LÀ TRỐNG
            (b.checkout_date = p_checkin AND b.late_checkout = 1)
        )
    );
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_count_available_rooms_by_type` (IN `p_room_id` INT, IN `p_checkin` DATE, IN `p_checkout` DATE)   BEGIN
  SELECT COUNT(rn.id) as available_count
  FROM room_numbers rn
  WHERE rn.room_id = p_room_id
    AND rn.status != 'maintenance'
    AND NOT EXISTS (
      SELECT 1 
      FROM bookings b
      WHERE b.room_number_id = rn.id
        AND b.booking_status NOT IN ('cancelled', 'no_show')
        AND b.checkin_date < p_checkout
        AND b.checkout_date > p_checkin
    );
END$$

DELIMITER ;

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `activity_logs`
--

CREATE TABLE `activity_logs` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `action` varchar(100) NOT NULL,
  `entity_type` varchar(50) DEFAULT NULL,
  `entity_id` int(11) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `bookings`
--

CREATE TABLE `bookings` (
  `id` int(11) NOT NULL,
  `booking_reference` varchar(20) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `room_id` int(11) NOT NULL,
  `room_number_id` int(11) DEFAULT NULL,
  `guest_name` varchar(150) NOT NULL,
  `guest_email` varchar(150) NOT NULL,
  `guest_phone` varchar(20) NOT NULL,
  `guest_country` varchar(100) DEFAULT 'Việt Nam',
  `checkin_date` date NOT NULL,
  `checkout_date` date NOT NULL,
  `num_nights` int(11) NOT NULL,
  `num_adults` int(11) DEFAULT 1,
  `num_children` int(11) DEFAULT 0,
  `base_price` decimal(10,2) NOT NULL,
  `addons_total` decimal(10,2) DEFAULT 0.00,
  `tax_amount` decimal(10,2) DEFAULT 0.00,
  `service_fee` decimal(10,2) DEFAULT 0.00,
  `total_price` decimal(10,2) NOT NULL,
  `payment_status` enum('pending','paid','refunded','cancelled') DEFAULT 'pending',
  `payment_method` varchar(50) DEFAULT NULL,
  `pref_theme` varchar(100) DEFAULT NULL,
  `pref_temperature` varchar(50) DEFAULT NULL,
  `special_requests` text DEFAULT NULL,
  `booking_status` enum('pending','confirmed','checked_in','checked_out','cancelled','no_show') DEFAULT 'pending',
  `cancellation_reason` text DEFAULT NULL,
  `booking_date` timestamp NOT NULL DEFAULT current_timestamp(),
  `confirmed_at` timestamp NULL DEFAULT NULL,
  `checkin_at` timestamp NULL DEFAULT NULL,
  `checkout_at` timestamp NULL DEFAULT NULL,
  `cancelled_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `early_checkin` tinyint(1) DEFAULT 0,
  `late_checkout` tinyint(1) DEFAULT 0,
  `early_checkin_fee` decimal(10,2) DEFAULT 0.00,
  `late_checkout_fee` decimal(10,2) DEFAULT 0.00
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Đang đổ dữ liệu cho bảng `bookings`
--

INSERT INTO `bookings` (`id`, `booking_reference`, `user_id`, `room_id`, `room_number_id`, `guest_name`, `guest_email`, `guest_phone`, `guest_country`, `checkin_date`, `checkout_date`, `num_nights`, `num_adults`, `num_children`, `base_price`, `addons_total`, `tax_amount`, `service_fee`, `total_price`, `payment_status`, `payment_method`, `pref_theme`, `pref_temperature`, `special_requests`, `booking_status`, `cancellation_reason`, `booking_date`, `confirmed_at`, `checkin_at`, `checkout_at`, `cancelled_at`, `created_at`, `updated_at`, `early_checkin`, `late_checkout`, `early_checkin_fee`, `late_checkout_fee`) VALUES
(12, 'VPC202511280635', 3, 3, 19, 'Bùi Quang Vinh', 'b.vinh2005@gmail.com', '09427113367', 'Việt Nam', '2025-11-28', '2025-11-29', 1, 2, 0, 5500000.00, 700000.00, 496000.00, 310000.00, 12500000.00, 'paid', 'qr_code', 'Tiêu chuẩn (Mặc định)', 'Tiêu chuẩn (25°C)', '', 'cancelled', NULL, '2025-11-28 05:34:45', '2025-11-28 05:34:45', '2025-11-28 07:31:38', '2025-11-28 07:30:56', NULL, '2025-11-28 05:34:45', '2025-11-28 15:13:50', 0, 1, 0.00, 0.00),
(13, 'VPC202511283622', NULL, 2, 1, 'Thư', 'b.vinh2005@gmail.com', '0968336644', 'Việt Nam', '2025-11-28', '2025-11-29', 1, 2, 0, 5500000.00, 8400000.00, 1112000.00, 695000.00, 6500000.00, 'paid', 'qr_code', 'Tiêu chuẩn (Mặc định)', 'Tiêu chuẩn (25°C)', '', 'cancelled', NULL, '2025-11-28 14:47:51', '2025-11-28 14:47:51', '2025-11-28 14:56:37', NULL, NULL, '2025-11-28 14:47:51', '2025-11-28 15:12:37', 0, 0, 0.00, 0.00),
(14, 'VPC202511289770', 3, 1, 1, 'Bùi Quang Vinh', 'b.vinh2005@gmail.com', '09427113367', 'Việt Nam', '2025-11-29', '2025-11-30', 1, 2, 0, 5500000.00, 5400000.00, 872000.00, 545000.00, 12317000.00, 'paid', 'qr_code', 'Tiêu chuẩn (Mặc định)', 'Tiêu chuẩn (25°C)', '', 'checked_out', NULL, '2025-11-28 14:51:34', '2025-11-28 14:51:34', '2025-11-28 14:57:50', '2025-11-28 15:01:38', NULL, '2025-11-28 14:51:34', '2025-11-28 15:01:38', 0, 0, 0.00, 0.00),
(15, 'VPC202511286048', 3, 4, 4, 'Bùi Quang Vinh', 'b.vinh2005@gmail.com', '09427113367', 'Việt Nam', '2025-11-28', '2025-12-01', 3, 2, 0, 16500000.00, 8200000.00, 1976000.00, 1235000.00, 75000000.00, 'paid', 'qr_code', 'Tiêu chuẩn (Mặc định)', 'Tiêu chuẩn (25°C)', '', 'cancelled', NULL, '2025-11-28 15:04:36', '2025-11-28 15:04:36', NULL, NULL, NULL, '2025-11-28 15:04:36', '2025-11-28 15:12:06', 0, 1, 0.00, 0.00),
(16, 'VPC202511284344', 3, 2, 1, 'Bùi Quang Vinh', 'b.vinh2005@gmail.com', '09427113367', 'Việt Nam', '2025-11-28', '2025-12-01', 3, 2, 0, 16500000.00, 10200000.00, 2136000.00, 1335000.00, 19500000.00, 'paid', 'qr_code', 'Tiêu chuẩn (Mặc định)', 'Tiêu chuẩn (25°C)', '', 'cancelled', NULL, '2025-11-28 15:14:57', '2025-11-28 15:14:57', NULL, NULL, NULL, '2025-11-28 15:14:57', '2025-11-28 15:19:09', 0, 0, 0.00, 0.00),
(17, 'VPC202511289005', 3, 4, 24, 'Bùi Quang Vinh', 'b.vinh2005@gmail.com', '09427113367', 'Việt Nam', '2025-11-28', '2025-11-30', 2, 2, 0, 13000000.00, 6800000.00, 1584000.00, 990000.00, 59374000.00, 'paid', 'qr_code', 'Tiêu chuẩn (Mặc định)', 'Tiêu chuẩn (25°C)', '', 'checked_in', NULL, '2025-11-28 15:19:58', '2025-11-28 15:19:58', '2025-11-28 17:17:59', NULL, NULL, '2025-11-28 15:19:58', '2025-11-28 17:17:59', 0, 0, 0.00, 0.00),
(18, 'VPC202511285240', 3, 3, 19, 'Bùi Quang Vinh', 'b.vinh2005@gmail.com', '09427113367', 'Việt Nam', '2025-11-29', '2025-12-01', 2, 2, 0, 13000000.00, 10000000.00, 0.00, 0.00, 35000000.00, 'paid', 'qr_code', 'Tiêu chuẩn (Mặc định)', 'Tiêu chuẩn (25°C)', '', 'checked_in', NULL, '2025-11-28 15:37:35', '2025-11-28 15:37:35', '2025-11-28 15:44:36', NULL, NULL, '2025-11-28 15:37:35', '2025-11-28 17:14:00', 0, 0, 0.00, 0.00),
(19, 'VPC202511280527', 3, 2, 13, 'Bùi Quang Vinh', 'b.vinh2005@gmail.com', '09427113367', 'Việt Nam', '2025-11-28', '2025-11-29', 1, 2, 0, 6500000.00, 7100000.00, 0.00, 0.00, 13600000.00, 'paid', 'qr_code', 'Tiêu chuẩn (Mặc định)', 'Tiêu chuẩn (25°C)', '', 'checked_out', NULL, '2025-11-28 16:26:37', '2025-11-28 16:26:37', '2025-11-29 07:19:10', '2025-11-29 07:56:06', NULL, '2025-11-28 16:26:37', '2025-11-29 07:56:06', 0, 0, 0.00, 0.00),
(20, 'BK17643531350', 2, 1, NULL, 'Guest 0', 'guest0@example.com', '0901234567', 'Việt Nam', '2025-07-14', '2025-07-19', 5, 1, 0, 27500000.00, 0.00, 0.00, 0.00, 27500000.00, 'paid', NULL, NULL, NULL, NULL, 'checked_out', NULL, '2025-11-28 18:05:35', NULL, NULL, NULL, NULL, '2025-07-13 17:00:00', '2025-11-28 18:05:35', 0, 0, 0.00, 0.00),
(21, 'BK17643531351', 2, 1, NULL, 'Guest 1', 'guest1@example.com', '0901234567', 'Việt Nam', '2025-08-12', '2025-08-14', 2, 1, 0, 11000000.00, 0.00, 0.00, 0.00, 11000000.00, 'refunded', NULL, NULL, NULL, NULL, 'cancelled', NULL, '2025-11-28 18:05:35', NULL, NULL, NULL, NULL, '2025-08-11 17:00:00', '2025-11-28 18:05:35', 0, 0, 0.00, 0.00),
(22, 'BK17643531352', 2, 4, NULL, 'Guest 2', 'guest2@example.com', '0901234567', 'Việt Nam', '2025-10-03', '2025-10-08', 5, 1, 0, 99999999.99, 0.00, 0.00, 0.00, 99999999.99, 'paid', NULL, NULL, NULL, NULL, 'checked_out', NULL, '2025-11-28 18:05:35', NULL, NULL, NULL, NULL, '2025-10-02 17:00:00', '2025-11-28 18:05:35', 0, 0, 0.00, 0.00),
(23, 'BK17643531353', 2, 2, NULL, 'Guest 3', 'guest3@example.com', '0901234567', 'Việt Nam', '2025-10-28', '2025-10-29', 1, 1, 0, 6500000.00, 0.00, 0.00, 0.00, 6500000.00, 'paid', NULL, NULL, NULL, NULL, 'checked_out', NULL, '2025-11-28 18:05:35', NULL, NULL, NULL, NULL, '2025-10-27 17:00:00', '2025-11-28 18:05:35', 0, 0, 0.00, 0.00),
(24, 'BK17643531354', 1, 3, NULL, 'Guest 4', 'guest4@example.com', '0901234567', 'Việt Nam', '2025-10-07', '2025-10-11', 4, 1, 0, 50000000.00, 0.00, 0.00, 0.00, 50000000.00, 'paid', NULL, NULL, NULL, NULL, 'checked_out', NULL, '2025-11-28 18:05:35', NULL, NULL, NULL, NULL, '2025-10-06 17:00:00', '2025-11-28 18:05:35', 0, 0, 0.00, 0.00),
(25, 'BK17643531355', 2, 4, NULL, 'Guest 5', 'guest5@example.com', '0901234567', 'Việt Nam', '2025-08-04', '2025-08-06', 2, 1, 0, 50000000.00, 0.00, 0.00, 0.00, 50000000.00, 'paid', NULL, NULL, NULL, NULL, 'checked_out', NULL, '2025-11-28 18:05:35', NULL, NULL, NULL, NULL, '2025-08-03 17:00:00', '2025-11-28 18:05:35', 0, 0, 0.00, 0.00),
(26, 'BK17643531356', 1, 4, NULL, 'Guest 6', 'guest6@example.com', '0901234567', 'Việt Nam', '2025-08-12', '2025-08-14', 2, 1, 0, 50000000.00, 0.00, 0.00, 0.00, 50000000.00, 'paid', NULL, NULL, NULL, NULL, 'checked_out', NULL, '2025-11-28 18:05:35', NULL, NULL, NULL, NULL, '2025-08-11 17:00:00', '2025-11-28 18:05:35', 0, 0, 0.00, 0.00),
(27, 'BK17643531357', 2, 3, NULL, 'Guest 7', 'guest7@example.com', '0901234567', 'Việt Nam', '2025-07-13', '2025-07-17', 4, 1, 0, 50000000.00, 0.00, 0.00, 0.00, 50000000.00, 'paid', NULL, NULL, NULL, NULL, 'checked_out', NULL, '2025-11-28 18:05:35', NULL, NULL, NULL, NULL, '2025-07-12 17:00:00', '2025-11-28 18:05:35', 0, 0, 0.00, 0.00),
(28, 'BK17643531358', 2, 2, NULL, 'Guest 8', 'guest8@example.com', '0901234567', 'Việt Nam', '2025-11-19', '2025-11-20', 1, 1, 0, 6500000.00, 0.00, 0.00, 0.00, 6500000.00, 'refunded', NULL, NULL, NULL, NULL, 'cancelled', NULL, '2025-11-28 18:05:35', NULL, NULL, NULL, NULL, '2025-11-18 17:00:00', '2025-11-28 18:05:35', 0, 0, 0.00, 0.00),
(29, 'BK17643531359', 2, 1, NULL, 'Guest 9', 'guest9@example.com', '0901234567', 'Việt Nam', '2025-08-02', '2025-08-03', 1, 1, 0, 5500000.00, 0.00, 0.00, 0.00, 5500000.00, 'paid', NULL, NULL, NULL, NULL, 'checked_out', NULL, '2025-11-28 18:05:35', NULL, NULL, NULL, NULL, '2025-08-01 17:00:00', '2025-11-28 18:05:35', 0, 0, 0.00, 0.00),
(30, 'BK176435313510', 2, 4, NULL, 'Guest 10', 'guest10@example.com', '0901234567', 'Việt Nam', '2025-08-26', '2025-08-31', 5, 1, 0, 99999999.99, 0.00, 0.00, 0.00, 99999999.99, 'refunded', NULL, NULL, NULL, NULL, 'cancelled', NULL, '2025-11-28 18:05:35', NULL, NULL, NULL, NULL, '2025-08-25 17:00:00', '2025-11-28 18:05:35', 0, 0, 0.00, 0.00),
(31, 'BK176435313511', 2, 4, NULL, 'Guest 11', 'guest11@example.com', '0901234567', 'Việt Nam', '2025-08-28', '2025-08-29', 1, 1, 0, 25000000.00, 0.00, 0.00, 0.00, 25000000.00, 'paid', NULL, NULL, NULL, NULL, 'checked_out', NULL, '2025-11-28 18:05:35', NULL, NULL, NULL, NULL, '2025-08-27 17:00:00', '2025-11-28 18:05:35', 0, 0, 0.00, 0.00),
(32, 'BK176435313512', 1, 4, NULL, 'Guest 12', 'guest12@example.com', '0901234567', 'Việt Nam', '2025-10-05', '2025-10-07', 2, 1, 0, 50000000.00, 0.00, 0.00, 0.00, 50000000.00, 'refunded', NULL, NULL, NULL, NULL, 'cancelled', NULL, '2025-11-28 18:05:35', NULL, NULL, NULL, NULL, '2025-10-04 17:00:00', '2025-11-28 18:05:35', 0, 0, 0.00, 0.00),
(33, 'BK176435313513', 2, 1, NULL, 'Guest 13', 'guest13@example.com', '0901234567', 'Việt Nam', '2025-06-21', '2025-06-23', 2, 1, 0, 11000000.00, 0.00, 0.00, 0.00, 11000000.00, 'paid', NULL, NULL, NULL, NULL, 'checked_out', NULL, '2025-11-28 18:05:35', NULL, NULL, NULL, NULL, '2025-06-20 17:00:00', '2025-11-28 18:05:35', 0, 0, 0.00, 0.00),
(34, 'BK176435313514', 2, 1, NULL, 'Guest 14', 'guest14@example.com', '0901234567', 'Việt Nam', '2025-08-19', '2025-08-20', 1, 1, 0, 5500000.00, 0.00, 0.00, 0.00, 5500000.00, 'refunded', NULL, NULL, NULL, NULL, 'cancelled', NULL, '2025-11-28 18:05:35', NULL, NULL, NULL, NULL, '2025-08-18 17:00:00', '2025-11-28 18:05:35', 0, 0, 0.00, 0.00),
(35, 'BK176435313515', 2, 1, NULL, 'Guest 15', 'guest15@example.com', '0901234567', 'Việt Nam', '2025-08-18', '2025-08-21', 3, 1, 0, 16500000.00, 0.00, 0.00, 0.00, 16500000.00, 'refunded', NULL, NULL, NULL, NULL, 'cancelled', NULL, '2025-11-28 18:05:35', NULL, NULL, NULL, NULL, '2025-08-17 17:00:00', '2025-11-28 18:05:35', 0, 0, 0.00, 0.00),
(36, 'BK176435313516', 1, 4, NULL, 'Guest 16', 'guest16@example.com', '0901234567', 'Việt Nam', '2025-07-05', '2025-07-08', 3, 1, 0, 75000000.00, 0.00, 0.00, 0.00, 75000000.00, 'paid', NULL, NULL, NULL, NULL, 'checked_out', NULL, '2025-11-28 18:05:35', NULL, NULL, NULL, NULL, '2025-07-04 17:00:00', '2025-11-28 18:05:35', 0, 0, 0.00, 0.00),
(37, 'BK176435313517', 2, 2, NULL, 'Guest 17', 'guest17@example.com', '0901234567', 'Việt Nam', '2025-11-13', '2025-11-17', 4, 1, 0, 26000000.00, 0.00, 0.00, 0.00, 26000000.00, 'paid', NULL, NULL, NULL, NULL, 'checked_out', NULL, '2025-11-28 18:05:35', NULL, NULL, NULL, NULL, '2025-11-12 17:00:00', '2025-11-28 18:05:35', 0, 0, 0.00, 0.00),
(38, 'BK176435313518', 2, 1, NULL, 'Guest 18', 'guest18@example.com', '0901234567', 'Việt Nam', '2025-06-21', '2025-06-24', 3, 1, 0, 16500000.00, 0.00, 0.00, 0.00, 16500000.00, 'paid', NULL, NULL, NULL, NULL, 'checked_out', NULL, '2025-11-28 18:05:35', NULL, NULL, NULL, NULL, '2025-06-20 17:00:00', '2025-11-28 18:05:35', 0, 0, 0.00, 0.00),
(39, 'BK176435313519', 2, 2, NULL, 'Guest 19', 'guest19@example.com', '0901234567', 'Việt Nam', '2025-06-30', '2025-07-02', 2, 1, 0, 13000000.00, 0.00, 0.00, 0.00, 13000000.00, 'refunded', NULL, NULL, NULL, NULL, 'cancelled', NULL, '2025-11-28 18:05:35', NULL, NULL, NULL, NULL, '2025-06-29 17:00:00', '2025-11-28 18:05:35', 0, 0, 0.00, 0.00),
(40, 'BK176435313520', 2, 1, NULL, 'Guest 20', 'guest20@example.com', '0901234567', 'Việt Nam', '2025-07-12', '2025-07-14', 2, 1, 0, 11000000.00, 0.00, 0.00, 0.00, 11000000.00, 'paid', NULL, NULL, NULL, NULL, 'checked_out', NULL, '2025-11-28 18:05:35', NULL, NULL, NULL, NULL, '2025-07-11 17:00:00', '2025-11-28 18:05:35', 0, 0, 0.00, 0.00),
(41, 'BK176435313521', 1, 1, NULL, 'Guest 21', 'guest21@example.com', '0901234567', 'Việt Nam', '2025-09-25', '2025-09-27', 2, 1, 0, 11000000.00, 0.00, 0.00, 0.00, 11000000.00, 'paid', NULL, NULL, NULL, NULL, 'checked_out', NULL, '2025-11-28 18:05:35', NULL, NULL, NULL, NULL, '2025-09-24 17:00:00', '2025-11-28 18:05:35', 0, 0, 0.00, 0.00),
(42, 'BK176435313522', 1, 3, NULL, 'Guest 22', 'guest22@example.com', '0901234567', 'Việt Nam', '2025-08-10', '2025-08-14', 4, 1, 0, 50000000.00, 0.00, 0.00, 0.00, 50000000.00, 'paid', NULL, NULL, NULL, NULL, 'checked_out', NULL, '2025-11-28 18:05:35', NULL, NULL, NULL, NULL, '2025-08-09 17:00:00', '2025-11-28 18:05:35', 0, 0, 0.00, 0.00),
(43, 'BK176435313523', 1, 1, NULL, 'Guest 23', 'guest23@example.com', '0901234567', 'Việt Nam', '2025-06-29', '2025-06-30', 1, 1, 0, 5500000.00, 0.00, 0.00, 0.00, 5500000.00, 'paid', NULL, NULL, NULL, NULL, 'checked_out', NULL, '2025-11-28 18:05:35', NULL, NULL, NULL, NULL, '2025-06-28 17:00:00', '2025-11-28 18:05:35', 0, 0, 0.00, 0.00),
(44, 'BK176435313524', 2, 4, NULL, 'Guest 24', 'guest24@example.com', '0901234567', 'Việt Nam', '2025-10-10', '2025-10-13', 3, 1, 0, 75000000.00, 0.00, 0.00, 0.00, 75000000.00, 'paid', NULL, NULL, NULL, NULL, 'checked_out', NULL, '2025-11-28 18:05:35', NULL, NULL, NULL, NULL, '2025-10-09 17:00:00', '2025-11-28 18:05:35', 0, 0, 0.00, 0.00),
(45, 'BK176435313525', 2, 3, NULL, 'Guest 25', 'guest25@example.com', '0901234567', 'Việt Nam', '2025-08-23', '2025-08-27', 4, 1, 0, 50000000.00, 0.00, 0.00, 0.00, 50000000.00, 'paid', NULL, NULL, NULL, NULL, 'checked_out', NULL, '2025-11-28 18:05:35', NULL, NULL, NULL, NULL, '2025-08-22 17:00:00', '2025-11-28 18:05:35', 0, 0, 0.00, 0.00),
(46, 'BK176435313526', 2, 4, NULL, 'Guest 26', 'guest26@example.com', '0901234567', 'Việt Nam', '2025-06-14', '2025-06-15', 1, 1, 0, 25000000.00, 0.00, 0.00, 0.00, 25000000.00, 'paid', NULL, NULL, NULL, NULL, 'checked_out', NULL, '2025-11-28 18:05:35', NULL, NULL, NULL, NULL, '2025-06-13 17:00:00', '2025-11-28 18:05:35', 0, 0, 0.00, 0.00),
(47, 'BK176435313527', 2, 1, NULL, 'Guest 27', 'guest27@example.com', '0901234567', 'Việt Nam', '2025-06-27', '2025-06-30', 3, 1, 0, 16500000.00, 0.00, 0.00, 0.00, 16500000.00, 'refunded', NULL, NULL, NULL, NULL, 'cancelled', NULL, '2025-11-28 18:05:35', NULL, NULL, NULL, NULL, '2025-06-26 17:00:00', '2025-11-28 18:05:35', 0, 0, 0.00, 0.00),
(48, 'BK176435313528', 1, 4, NULL, 'Guest 28', 'guest28@example.com', '0901234567', 'Việt Nam', '2025-08-02', '2025-08-07', 5, 1, 0, 99999999.99, 0.00, 0.00, 0.00, 99999999.99, 'paid', NULL, NULL, NULL, NULL, 'checked_out', NULL, '2025-11-28 18:05:35', NULL, NULL, NULL, NULL, '2025-08-01 17:00:00', '2025-11-28 18:05:35', 0, 0, 0.00, 0.00),
(49, 'BK176435313529', 1, 3, NULL, 'Guest 29', 'guest29@example.com', '0901234567', 'Việt Nam', '2025-09-27', '2025-10-02', 5, 1, 0, 62500000.00, 0.00, 0.00, 0.00, 62500000.00, 'paid', NULL, NULL, NULL, NULL, 'checked_out', NULL, '2025-11-28 18:05:35', NULL, NULL, NULL, NULL, '2025-09-26 17:00:00', '2025-11-28 18:05:35', 0, 0, 0.00, 0.00),
(50, 'BK176435313530', 1, 2, NULL, 'Guest 30', 'guest30@example.com', '0901234567', 'Việt Nam', '2025-08-05', '2025-08-06', 1, 1, 0, 6500000.00, 0.00, 0.00, 0.00, 6500000.00, 'paid', NULL, NULL, NULL, NULL, 'checked_out', NULL, '2025-11-28 18:05:35', NULL, NULL, NULL, NULL, '2025-08-04 17:00:00', '2025-11-28 18:05:35', 0, 0, 0.00, 0.00),
(51, 'BK176435313531', 1, 1, NULL, 'Guest 31', 'guest31@example.com', '0901234567', 'Việt Nam', '2025-06-20', '2025-06-23', 3, 1, 0, 16500000.00, 0.00, 0.00, 0.00, 16500000.00, 'paid', NULL, NULL, NULL, NULL, 'checked_out', NULL, '2025-11-28 18:05:35', NULL, NULL, NULL, NULL, '2025-06-19 17:00:00', '2025-11-28 18:05:35', 0, 0, 0.00, 0.00),
(52, 'BK176435313532', 2, 1, NULL, 'Guest 32', 'guest32@example.com', '0901234567', 'Việt Nam', '2025-07-28', '2025-07-30', 2, 1, 0, 11000000.00, 0.00, 0.00, 0.00, 11000000.00, 'refunded', NULL, NULL, NULL, NULL, 'cancelled', NULL, '2025-11-28 18:05:35', NULL, NULL, NULL, NULL, '2025-07-27 17:00:00', '2025-11-28 18:05:35', 0, 0, 0.00, 0.00),
(53, 'BK176435313533', 1, 2, NULL, 'Guest 33', 'guest33@example.com', '0901234567', 'Việt Nam', '2025-10-01', '2025-10-03', 2, 1, 0, 13000000.00, 0.00, 0.00, 0.00, 13000000.00, 'paid', NULL, NULL, NULL, NULL, 'checked_out', NULL, '2025-11-28 18:05:35', NULL, NULL, NULL, NULL, '2025-09-30 17:00:00', '2025-11-28 18:05:35', 0, 0, 0.00, 0.00),
(54, 'BK176435313534', 1, 1, NULL, 'Guest 34', 'guest34@example.com', '0901234567', 'Việt Nam', '2025-10-03', '2025-10-08', 5, 1, 0, 27500000.00, 0.00, 0.00, 0.00, 27500000.00, 'paid', NULL, NULL, NULL, NULL, 'checked_out', NULL, '2025-11-28 18:05:35', NULL, NULL, NULL, NULL, '2025-10-02 17:00:00', '2025-11-28 18:05:35', 0, 0, 0.00, 0.00),
(55, 'BK176435313535', 1, 4, NULL, 'Guest 35', 'guest35@example.com', '0901234567', 'Việt Nam', '2025-06-03', '2025-06-05', 2, 1, 0, 50000000.00, 0.00, 0.00, 0.00, 50000000.00, 'paid', NULL, NULL, NULL, NULL, 'checked_out', NULL, '2025-11-28 18:05:35', NULL, NULL, NULL, NULL, '2025-06-02 17:00:00', '2025-11-28 18:05:35', 0, 0, 0.00, 0.00),
(56, 'BK176435313536', 1, 3, NULL, 'Guest 36', 'guest36@example.com', '0901234567', 'Việt Nam', '2025-09-13', '2025-09-14', 1, 1, 0, 12500000.00, 0.00, 0.00, 0.00, 12500000.00, 'paid', NULL, NULL, NULL, NULL, 'checked_out', NULL, '2025-11-28 18:05:35', NULL, NULL, NULL, NULL, '2025-09-12 17:00:00', '2025-11-28 18:05:35', 0, 0, 0.00, 0.00),
(57, 'BK176435313537', 2, 4, NULL, 'Guest 37', 'guest37@example.com', '0901234567', 'Việt Nam', '2025-07-08', '2025-07-11', 3, 1, 0, 75000000.00, 0.00, 0.00, 0.00, 75000000.00, 'paid', NULL, NULL, NULL, NULL, 'checked_out', NULL, '2025-11-28 18:05:35', NULL, NULL, NULL, NULL, '2025-07-07 17:00:00', '2025-11-28 18:05:35', 0, 0, 0.00, 0.00),
(58, 'BK176435313538', 1, 3, NULL, 'Guest 38', 'guest38@example.com', '0901234567', 'Việt Nam', '2025-08-13', '2025-08-16', 3, 1, 0, 37500000.00, 0.00, 0.00, 0.00, 37500000.00, 'paid', NULL, NULL, NULL, NULL, 'checked_out', NULL, '2025-11-28 18:05:35', NULL, NULL, NULL, NULL, '2025-08-12 17:00:00', '2025-11-28 18:05:35', 0, 0, 0.00, 0.00),
(59, 'BK176435313539', 2, 4, NULL, 'Guest 39', 'guest39@example.com', '0901234567', 'Việt Nam', '2025-08-25', '2025-08-26', 1, 1, 0, 25000000.00, 0.00, 0.00, 0.00, 25000000.00, 'refunded', NULL, NULL, NULL, NULL, 'cancelled', NULL, '2025-11-28 18:05:35', NULL, NULL, NULL, NULL, '2025-08-24 17:00:00', '2025-11-28 18:05:35', 0, 0, 0.00, 0.00),
(60, 'BK176435313540', 2, 1, NULL, 'Guest 40', 'guest40@example.com', '0901234567', 'Việt Nam', '2025-08-24', '2025-08-25', 1, 1, 0, 5500000.00, 0.00, 0.00, 0.00, 5500000.00, 'paid', NULL, NULL, NULL, NULL, 'checked_out', NULL, '2025-11-28 18:05:35', NULL, NULL, NULL, NULL, '2025-08-23 17:00:00', '2025-11-28 18:05:35', 0, 0, 0.00, 0.00),
(61, 'BK176435313541', 1, 4, NULL, 'Guest 41', 'guest41@example.com', '0901234567', 'Việt Nam', '2025-08-26', '2025-08-28', 2, 1, 0, 50000000.00, 0.00, 0.00, 0.00, 50000000.00, 'paid', NULL, NULL, NULL, NULL, 'checked_out', NULL, '2025-11-28 18:05:35', NULL, NULL, NULL, NULL, '2025-08-25 17:00:00', '2025-11-28 18:05:35', 0, 0, 0.00, 0.00),
(62, 'BK176435313542', 2, 3, NULL, 'Guest 42', 'guest42@example.com', '0901234567', 'Việt Nam', '2025-08-23', '2025-08-24', 1, 1, 0, 12500000.00, 0.00, 0.00, 0.00, 12500000.00, 'paid', NULL, NULL, NULL, NULL, 'checked_out', NULL, '2025-11-28 18:05:35', NULL, NULL, NULL, NULL, '2025-08-22 17:00:00', '2025-11-28 18:05:35', 0, 0, 0.00, 0.00),
(63, 'BK176435313543', 2, 3, NULL, 'Guest 43', 'guest43@example.com', '0901234567', 'Việt Nam', '2025-10-21', '2025-10-26', 5, 1, 0, 62500000.00, 0.00, 0.00, 0.00, 62500000.00, 'paid', NULL, NULL, NULL, NULL, 'checked_out', NULL, '2025-11-28 18:05:35', NULL, NULL, NULL, NULL, '2025-10-20 17:00:00', '2025-11-28 18:05:35', 0, 0, 0.00, 0.00),
(64, 'BK176435313544', 2, 2, NULL, 'Guest 44', 'guest44@example.com', '0901234567', 'Việt Nam', '2025-10-02', '2025-10-05', 3, 1, 0, 19500000.00, 0.00, 0.00, 0.00, 19500000.00, 'paid', NULL, NULL, NULL, NULL, 'checked_out', NULL, '2025-11-28 18:05:35', NULL, NULL, NULL, NULL, '2025-10-01 17:00:00', '2025-11-28 18:05:35', 0, 0, 0.00, 0.00),
(65, 'BK176435313545', 2, 2, NULL, 'Guest 45', 'guest45@example.com', '0901234567', 'Việt Nam', '2025-11-26', '2025-12-01', 5, 1, 0, 32500000.00, 0.00, 0.00, 0.00, 32500000.00, 'paid', NULL, NULL, NULL, NULL, 'checked_out', NULL, '2025-11-28 18:05:35', NULL, NULL, NULL, NULL, '2025-11-25 17:00:00', '2025-11-28 18:05:35', 0, 0, 0.00, 0.00),
(66, 'BK176435313546', 1, 2, NULL, 'Guest 46', 'guest46@example.com', '0901234567', 'Việt Nam', '2025-06-17', '2025-06-22', 5, 1, 0, 32500000.00, 0.00, 0.00, 0.00, 32500000.00, 'paid', NULL, NULL, NULL, NULL, 'checked_out', NULL, '2025-11-28 18:05:35', NULL, NULL, NULL, NULL, '2025-06-16 17:00:00', '2025-11-28 18:05:35', 0, 0, 0.00, 0.00),
(67, 'BK176435313547', 2, 3, NULL, 'Guest 47', 'guest47@example.com', '0901234567', 'Việt Nam', '2025-08-26', '2025-08-29', 3, 1, 0, 37500000.00, 0.00, 0.00, 0.00, 37500000.00, 'paid', NULL, NULL, NULL, NULL, 'checked_out', NULL, '2025-11-28 18:05:35', NULL, NULL, NULL, NULL, '2025-08-25 17:00:00', '2025-11-28 18:05:35', 0, 0, 0.00, 0.00),
(68, 'BK176435313548', 1, 1, NULL, 'Guest 48', 'guest48@example.com', '0901234567', 'Việt Nam', '2025-07-14', '2025-07-16', 2, 1, 0, 11000000.00, 0.00, 0.00, 0.00, 11000000.00, 'paid', NULL, NULL, NULL, NULL, 'checked_out', NULL, '2025-11-28 18:05:35', NULL, NULL, NULL, NULL, '2025-07-13 17:00:00', '2025-11-28 18:05:35', 0, 0, 0.00, 0.00),
(69, 'BK176435313549', 2, 4, NULL, 'Guest 49', 'guest49@example.com', '0901234567', 'Việt Nam', '2025-10-10', '2025-10-12', 2, 1, 0, 50000000.00, 0.00, 0.00, 0.00, 50000000.00, 'paid', NULL, NULL, NULL, NULL, 'checked_out', NULL, '2025-11-28 18:05:35', NULL, NULL, NULL, NULL, '2025-10-09 17:00:00', '2025-11-28 18:05:35', 0, 0, 0.00, 0.00),
(70, 'BK176435313550', 1, 1, NULL, 'Guest 50', 'guest50@example.com', '0901234567', 'Việt Nam', '2025-11-16', '2025-11-17', 1, 1, 0, 5500000.00, 0.00, 0.00, 0.00, 5500000.00, 'paid', NULL, NULL, NULL, NULL, 'checked_out', NULL, '2025-11-28 18:05:35', NULL, NULL, NULL, NULL, '2025-11-15 17:00:00', '2025-11-28 18:05:35', 0, 0, 0.00, 0.00),
(71, 'BK176435313551', 1, 2, NULL, 'Guest 51', 'guest51@example.com', '0901234567', 'Việt Nam', '2025-10-27', '2025-11-01', 5, 1, 0, 32500000.00, 0.00, 0.00, 0.00, 32500000.00, 'paid', NULL, NULL, NULL, NULL, 'checked_out', NULL, '2025-11-28 18:05:35', NULL, NULL, NULL, NULL, '2025-10-26 17:00:00', '2025-11-28 18:05:35', 0, 0, 0.00, 0.00),
(72, 'BK176435313552', 1, 2, NULL, 'Guest 52', 'guest52@example.com', '0901234567', 'Việt Nam', '2025-06-05', '2025-06-09', 4, 1, 0, 26000000.00, 0.00, 0.00, 0.00, 26000000.00, 'paid', NULL, NULL, NULL, NULL, 'checked_out', NULL, '2025-11-28 18:05:35', NULL, NULL, NULL, NULL, '2025-06-04 17:00:00', '2025-11-28 18:05:35', 0, 0, 0.00, 0.00),
(73, 'BK176435313553', 2, 1, NULL, 'Guest 53', 'guest53@example.com', '0901234567', 'Việt Nam', '2025-07-14', '2025-07-17', 3, 1, 0, 16500000.00, 0.00, 0.00, 0.00, 16500000.00, 'refunded', NULL, NULL, NULL, NULL, 'cancelled', NULL, '2025-11-28 18:05:35', NULL, NULL, NULL, NULL, '2025-07-13 17:00:00', '2025-11-28 18:05:35', 0, 0, 0.00, 0.00),
(74, 'BK176435313554', 1, 1, NULL, 'Guest 54', 'guest54@example.com', '0901234567', 'Việt Nam', '2025-08-02', '2025-08-03', 1, 1, 0, 5500000.00, 0.00, 0.00, 0.00, 5500000.00, 'paid', NULL, NULL, NULL, NULL, 'checked_out', NULL, '2025-11-28 18:05:35', NULL, NULL, NULL, NULL, '2025-08-01 17:00:00', '2025-11-28 18:05:35', 0, 0, 0.00, 0.00),
(75, 'BK176435313555', 1, 2, NULL, 'Guest 55', 'guest55@example.com', '0901234567', 'Việt Nam', '2025-06-04', '2025-06-07', 3, 1, 0, 19500000.00, 0.00, 0.00, 0.00, 19500000.00, 'paid', NULL, NULL, NULL, NULL, 'checked_out', NULL, '2025-11-28 18:05:35', NULL, NULL, NULL, NULL, '2025-06-03 17:00:00', '2025-11-28 18:05:35', 0, 0, 0.00, 0.00),
(76, 'BK176435313556', 2, 2, NULL, 'Guest 56', 'guest56@example.com', '0901234567', 'Việt Nam', '2025-06-16', '2025-06-20', 4, 1, 0, 26000000.00, 0.00, 0.00, 0.00, 26000000.00, 'refunded', NULL, NULL, NULL, NULL, 'cancelled', NULL, '2025-11-28 18:05:35', NULL, NULL, NULL, NULL, '2025-06-15 17:00:00', '2025-11-28 18:05:35', 0, 0, 0.00, 0.00),
(77, 'BK176435313557', 1, 3, NULL, 'Guest 57', 'guest57@example.com', '0901234567', 'Việt Nam', '2025-11-27', '2025-12-02', 5, 1, 0, 62500000.00, 0.00, 0.00, 0.00, 62500000.00, 'paid', NULL, NULL, NULL, NULL, 'checked_out', NULL, '2025-11-28 18:05:35', NULL, NULL, NULL, NULL, '2025-11-26 17:00:00', '2025-11-28 18:05:35', 0, 0, 0.00, 0.00),
(78, 'BK176435313558', 2, 4, NULL, 'Guest 58', 'guest58@example.com', '0901234567', 'Việt Nam', '2025-08-13', '2025-08-17', 4, 1, 0, 99999999.99, 0.00, 0.00, 0.00, 99999999.99, 'paid', NULL, NULL, NULL, NULL, 'checked_out', NULL, '2025-11-28 18:05:35', NULL, NULL, NULL, NULL, '2025-08-12 17:00:00', '2025-11-28 18:05:35', 0, 0, 0.00, 0.00),
(79, 'BK176435313559', 1, 4, NULL, 'Guest 59', 'guest59@example.com', '0901234567', 'Việt Nam', '2025-07-15', '2025-07-19', 4, 1, 0, 99999999.99, 0.00, 0.00, 0.00, 99999999.99, 'paid', NULL, NULL, NULL, NULL, 'checked_out', NULL, '2025-11-28 18:05:35', NULL, NULL, NULL, NULL, '2025-07-14 17:00:00', '2025-11-28 18:05:35', 0, 0, 0.00, 0.00),
(80, 'BK176435313560', 1, 2, NULL, 'Guest 60', 'guest60@example.com', '0901234567', 'Việt Nam', '2025-08-30', '2025-09-03', 4, 1, 0, 26000000.00, 0.00, 0.00, 0.00, 26000000.00, 'refunded', NULL, NULL, NULL, NULL, 'cancelled', NULL, '2025-11-28 18:05:35', NULL, NULL, NULL, NULL, '2025-08-29 17:00:00', '2025-11-28 18:05:35', 0, 0, 0.00, 0.00),
(81, 'BK176435313561', 2, 1, NULL, 'Guest 61', 'guest61@example.com', '0901234567', 'Việt Nam', '2025-07-31', '2025-08-03', 3, 1, 0, 16500000.00, 0.00, 0.00, 0.00, 16500000.00, 'paid', NULL, NULL, NULL, NULL, 'checked_out', NULL, '2025-11-28 18:05:35', NULL, NULL, NULL, NULL, '2025-07-30 17:00:00', '2025-11-28 18:05:35', 0, 0, 0.00, 0.00),
(82, 'BK176435313562', 1, 2, NULL, 'Guest 62', 'guest62@example.com', '0901234567', 'Việt Nam', '2025-11-26', '2025-12-01', 5, 1, 0, 32500000.00, 0.00, 0.00, 0.00, 32500000.00, 'refunded', NULL, NULL, NULL, NULL, 'cancelled', NULL, '2025-11-28 18:05:35', NULL, NULL, NULL, NULL, '2025-11-25 17:00:00', '2025-11-28 18:05:35', 0, 0, 0.00, 0.00),
(83, 'BK176435313563', 2, 3, NULL, 'Guest 63', 'guest63@example.com', '0901234567', 'Việt Nam', '2025-07-16', '2025-07-19', 3, 1, 0, 37500000.00, 0.00, 0.00, 0.00, 37500000.00, 'paid', NULL, NULL, NULL, NULL, 'checked_out', NULL, '2025-11-28 18:05:35', NULL, NULL, NULL, NULL, '2025-07-15 17:00:00', '2025-11-28 18:05:35', 0, 0, 0.00, 0.00),
(84, 'BK176435313564', 1, 2, NULL, 'Guest 64', 'guest64@example.com', '0901234567', 'Việt Nam', '2025-07-13', '2025-07-15', 2, 1, 0, 13000000.00, 0.00, 0.00, 0.00, 13000000.00, 'paid', NULL, NULL, NULL, NULL, 'checked_out', NULL, '2025-11-28 18:05:35', NULL, NULL, NULL, NULL, '2025-07-12 17:00:00', '2025-11-28 18:05:35', 0, 0, 0.00, 0.00),
(85, 'BK176435313565', 1, 3, NULL, 'Guest 65', 'guest65@example.com', '0901234567', 'Việt Nam', '2025-06-21', '2025-06-22', 1, 1, 0, 12500000.00, 0.00, 0.00, 0.00, 12500000.00, 'paid', NULL, NULL, NULL, NULL, 'checked_out', NULL, '2025-11-28 18:05:35', NULL, NULL, NULL, NULL, '2025-06-20 17:00:00', '2025-11-28 18:05:35', 0, 0, 0.00, 0.00),
(86, 'BK176435313566', 1, 1, NULL, 'Guest 66', 'guest66@example.com', '0901234567', 'Việt Nam', '2025-07-23', '2025-07-27', 4, 1, 0, 22000000.00, 0.00, 0.00, 0.00, 22000000.00, 'paid', NULL, NULL, NULL, NULL, 'checked_out', NULL, '2025-11-28 18:05:35', NULL, NULL, NULL, NULL, '2025-07-22 17:00:00', '2025-11-28 18:05:35', 0, 0, 0.00, 0.00),
(87, 'BK176435313567', 1, 3, NULL, 'Guest 67', 'guest67@example.com', '0901234567', 'Việt Nam', '2025-11-27', '2025-12-02', 5, 1, 0, 62500000.00, 0.00, 0.00, 0.00, 62500000.00, 'paid', NULL, NULL, NULL, NULL, 'checked_out', NULL, '2025-11-28 18:05:35', NULL, NULL, NULL, NULL, '2025-11-26 17:00:00', '2025-11-28 18:05:35', 0, 0, 0.00, 0.00),
(88, 'BK176435313568', 1, 3, NULL, 'Guest 68', 'guest68@example.com', '0901234567', 'Việt Nam', '2025-09-09', '2025-09-12', 3, 1, 0, 37500000.00, 0.00, 0.00, 0.00, 37500000.00, 'refunded', NULL, NULL, NULL, NULL, 'cancelled', NULL, '2025-11-28 18:05:35', NULL, NULL, NULL, NULL, '2025-09-08 17:00:00', '2025-11-28 18:05:35', 0, 0, 0.00, 0.00),
(89, 'BK176435313569', 2, 2, NULL, 'Guest 69', 'guest69@example.com', '0901234567', 'Việt Nam', '2025-11-04', '2025-11-07', 3, 1, 0, 19500000.00, 0.00, 0.00, 0.00, 19500000.00, 'paid', NULL, NULL, NULL, NULL, 'checked_out', NULL, '2025-11-28 18:05:35', NULL, NULL, NULL, NULL, '2025-11-03 17:00:00', '2025-11-28 18:05:35', 0, 0, 0.00, 0.00),
(90, 'BK176435313570', 2, 1, NULL, 'Guest 70', 'guest70@example.com', '0901234567', 'Việt Nam', '2025-09-27', '2025-10-02', 5, 1, 0, 27500000.00, 0.00, 0.00, 0.00, 27500000.00, 'paid', NULL, NULL, NULL, NULL, 'checked_out', NULL, '2025-11-28 18:05:35', NULL, NULL, NULL, NULL, '2025-09-26 17:00:00', '2025-11-28 18:05:35', 0, 0, 0.00, 0.00),
(91, 'BK176435313571', 2, 4, NULL, 'Guest 71', 'guest71@example.com', '0901234567', 'Việt Nam', '2025-08-16', '2025-08-17', 1, 1, 0, 25000000.00, 0.00, 0.00, 0.00, 25000000.00, 'paid', NULL, NULL, NULL, NULL, 'checked_out', NULL, '2025-11-28 18:05:35', NULL, NULL, NULL, NULL, '2025-08-15 17:00:00', '2025-11-28 18:05:35', 0, 0, 0.00, 0.00),
(92, 'BK176435313572', 2, 4, NULL, 'Guest 72', 'guest72@example.com', '0901234567', 'Việt Nam', '2025-11-07', '2025-11-12', 5, 1, 0, 99999999.99, 0.00, 0.00, 0.00, 99999999.99, 'paid', NULL, NULL, NULL, NULL, 'checked_out', NULL, '2025-11-28 18:05:35', NULL, NULL, NULL, NULL, '2025-11-06 17:00:00', '2025-11-28 18:05:35', 0, 0, 0.00, 0.00),
(93, 'BK176435313573', 2, 1, NULL, 'Guest 73', 'guest73@example.com', '0901234567', 'Việt Nam', '2025-09-16', '2025-09-18', 2, 1, 0, 11000000.00, 0.00, 0.00, 0.00, 11000000.00, 'paid', NULL, NULL, NULL, NULL, 'checked_out', NULL, '2025-11-28 18:05:35', NULL, NULL, NULL, NULL, '2025-09-15 17:00:00', '2025-11-28 18:05:35', 0, 0, 0.00, 0.00),
(94, 'BK176435313574', 2, 1, NULL, 'Guest 74', 'guest74@example.com', '0901234567', 'Việt Nam', '2025-11-06', '2025-11-11', 5, 1, 0, 27500000.00, 0.00, 0.00, 0.00, 27500000.00, 'paid', NULL, NULL, NULL, NULL, 'checked_out', NULL, '2025-11-28 18:05:35', NULL, NULL, NULL, NULL, '2025-11-05 17:00:00', '2025-11-28 18:05:35', 0, 0, 0.00, 0.00),
(95, 'BK176435313575', 2, 2, NULL, 'Guest 75', 'guest75@example.com', '0901234567', 'Việt Nam', '2025-07-31', '2025-08-03', 3, 1, 0, 19500000.00, 0.00, 0.00, 0.00, 19500000.00, 'paid', NULL, NULL, NULL, NULL, 'checked_out', NULL, '2025-11-28 18:05:35', NULL, NULL, NULL, NULL, '2025-07-30 17:00:00', '2025-11-28 18:05:35', 0, 0, 0.00, 0.00),
(96, 'BK176435313576', 1, 2, NULL, 'Guest 76', 'guest76@example.com', '0901234567', 'Việt Nam', '2025-11-21', '2025-11-25', 4, 1, 0, 26000000.00, 0.00, 0.00, 0.00, 26000000.00, 'paid', NULL, NULL, NULL, NULL, 'checked_out', NULL, '2025-11-28 18:05:35', NULL, NULL, NULL, NULL, '2025-11-20 17:00:00', '2025-11-28 18:05:35', 0, 0, 0.00, 0.00),
(97, 'BK176435313577', 1, 3, NULL, 'Guest 77', 'guest77@example.com', '0901234567', 'Việt Nam', '2025-08-20', '2025-08-25', 5, 1, 0, 62500000.00, 0.00, 0.00, 0.00, 62500000.00, 'paid', NULL, NULL, NULL, NULL, 'checked_out', NULL, '2025-11-28 18:05:35', NULL, NULL, NULL, NULL, '2025-08-19 17:00:00', '2025-11-28 18:05:35', 0, 0, 0.00, 0.00),
(98, 'BK176435313578', 1, 2, NULL, 'Guest 78', 'guest78@example.com', '0901234567', 'Việt Nam', '2025-08-21', '2025-08-22', 1, 1, 0, 6500000.00, 0.00, 0.00, 0.00, 6500000.00, 'paid', NULL, NULL, NULL, NULL, 'checked_out', NULL, '2025-11-28 18:05:35', NULL, NULL, NULL, NULL, '2025-08-20 17:00:00', '2025-11-28 18:05:35', 0, 0, 0.00, 0.00),
(99, 'BK176435313579', 2, 1, NULL, 'Guest 79', 'guest79@example.com', '0901234567', 'Việt Nam', '2025-11-05', '2025-11-06', 1, 1, 0, 5500000.00, 0.00, 0.00, 0.00, 5500000.00, 'paid', NULL, NULL, NULL, NULL, 'checked_out', NULL, '2025-11-28 18:05:35', NULL, NULL, NULL, NULL, '2025-11-04 17:00:00', '2025-11-28 18:05:35', 0, 0, 0.00, 0.00),
(100, 'BK176435313580', 2, 4, NULL, 'Guest 80', 'guest80@example.com', '0901234567', 'Việt Nam', '2025-11-08', '2025-11-13', 5, 1, 0, 99999999.99, 0.00, 0.00, 0.00, 99999999.99, 'paid', NULL, NULL, NULL, NULL, 'checked_out', NULL, '2025-11-28 18:05:35', NULL, NULL, NULL, NULL, '2025-11-07 17:00:00', '2025-11-28 18:05:35', 0, 0, 0.00, 0.00),
(101, 'BK176435313581', 2, 2, NULL, 'Guest 81', 'guest81@example.com', '0901234567', 'Việt Nam', '2025-07-12', '2025-07-15', 3, 1, 0, 19500000.00, 0.00, 0.00, 0.00, 19500000.00, 'paid', NULL, NULL, NULL, NULL, 'checked_out', NULL, '2025-11-28 18:05:35', NULL, NULL, NULL, NULL, '2025-07-11 17:00:00', '2025-11-28 18:05:35', 0, 0, 0.00, 0.00),
(102, 'BK176435313582', 1, 3, NULL, 'Guest 82', 'guest82@example.com', '0901234567', 'Việt Nam', '2025-09-26', '2025-09-30', 4, 1, 0, 50000000.00, 0.00, 0.00, 0.00, 50000000.00, 'paid', NULL, NULL, NULL, NULL, 'checked_out', NULL, '2025-11-28 18:05:35', NULL, NULL, NULL, NULL, '2025-09-25 17:00:00', '2025-11-28 18:05:35', 0, 0, 0.00, 0.00),
(103, 'BK176435313583', 1, 2, NULL, 'Guest 83', 'guest83@example.com', '0901234567', 'Việt Nam', '2025-08-27', '2025-08-31', 4, 1, 0, 26000000.00, 0.00, 0.00, 0.00, 26000000.00, 'paid', NULL, NULL, NULL, NULL, 'checked_out', NULL, '2025-11-28 18:05:35', NULL, NULL, NULL, NULL, '2025-08-26 17:00:00', '2025-11-28 18:05:35', 0, 0, 0.00, 0.00),
(104, 'BK176435313584', 1, 2, NULL, 'Guest 84', 'guest84@example.com', '0901234567', 'Việt Nam', '2025-07-14', '2025-07-19', 5, 1, 0, 32500000.00, 0.00, 0.00, 0.00, 32500000.00, 'paid', NULL, NULL, NULL, NULL, 'checked_out', NULL, '2025-11-28 18:05:35', NULL, NULL, NULL, NULL, '2025-07-13 17:00:00', '2025-11-28 18:05:35', 0, 0, 0.00, 0.00),
(105, 'BK176435313585', 1, 3, NULL, 'Guest 85', 'guest85@example.com', '0901234567', 'Việt Nam', '2025-07-09', '2025-07-14', 5, 1, 0, 62500000.00, 0.00, 0.00, 0.00, 62500000.00, 'paid', NULL, NULL, NULL, NULL, 'checked_out', NULL, '2025-11-28 18:05:35', NULL, NULL, NULL, NULL, '2025-07-08 17:00:00', '2025-11-28 18:05:35', 0, 0, 0.00, 0.00),
(106, 'BK176435313586', 1, 2, NULL, 'Guest 86', 'guest86@example.com', '0901234567', 'Việt Nam', '2025-09-27', '2025-09-30', 3, 1, 0, 19500000.00, 0.00, 0.00, 0.00, 19500000.00, 'paid', NULL, NULL, NULL, NULL, 'checked_out', NULL, '2025-11-28 18:05:35', NULL, NULL, NULL, NULL, '2025-09-26 17:00:00', '2025-11-28 18:05:35', 0, 0, 0.00, 0.00),
(107, 'BK176435313587', 1, 3, NULL, 'Guest 87', 'guest87@example.com', '0901234567', 'Việt Nam', '2025-08-22', '2025-08-23', 1, 1, 0, 12500000.00, 0.00, 0.00, 0.00, 12500000.00, 'paid', NULL, NULL, NULL, NULL, 'checked_out', NULL, '2025-11-28 18:05:35', NULL, NULL, NULL, NULL, '2025-08-21 17:00:00', '2025-11-28 18:05:35', 0, 0, 0.00, 0.00),
(108, 'BK176435313688', 2, 2, NULL, 'Guest 88', 'guest88@example.com', '0901234567', 'Việt Nam', '2025-10-14', '2025-10-18', 4, 1, 0, 26000000.00, 0.00, 0.00, 0.00, 26000000.00, 'paid', NULL, NULL, NULL, NULL, 'checked_out', NULL, '2025-11-28 18:05:36', NULL, NULL, NULL, NULL, '2025-10-13 17:00:00', '2025-11-28 18:05:36', 0, 0, 0.00, 0.00),
(109, 'BK176435313689', 2, 2, NULL, 'Guest 89', 'guest89@example.com', '0901234567', 'Việt Nam', '2025-09-07', '2025-09-12', 5, 1, 0, 32500000.00, 0.00, 0.00, 0.00, 32500000.00, 'paid', NULL, NULL, NULL, NULL, 'checked_out', NULL, '2025-11-28 18:05:36', NULL, NULL, NULL, NULL, '2025-09-06 17:00:00', '2025-11-28 18:05:36', 0, 0, 0.00, 0.00),
(110, 'BK176435313690', 1, 1, NULL, 'Guest 90', 'guest90@example.com', '0901234567', 'Việt Nam', '2025-08-25', '2025-08-29', 4, 1, 0, 22000000.00, 0.00, 0.00, 0.00, 22000000.00, 'paid', NULL, NULL, NULL, NULL, 'checked_out', NULL, '2025-11-28 18:05:36', NULL, NULL, NULL, NULL, '2025-08-24 17:00:00', '2025-11-28 18:05:36', 0, 0, 0.00, 0.00),
(111, 'BK176435313691', 1, 4, NULL, 'Guest 91', 'guest91@example.com', '0901234567', 'Việt Nam', '2025-10-05', '2025-10-08', 3, 1, 0, 75000000.00, 0.00, 0.00, 0.00, 75000000.00, 'paid', NULL, NULL, NULL, NULL, 'checked_out', NULL, '2025-11-28 18:05:36', NULL, NULL, NULL, NULL, '2025-10-04 17:00:00', '2025-11-28 18:05:36', 0, 0, 0.00, 0.00),
(112, 'BK176435313692', 1, 1, NULL, 'Guest 92', 'guest92@example.com', '0901234567', 'Việt Nam', '2025-07-26', '2025-07-27', 1, 1, 0, 5500000.00, 0.00, 0.00, 0.00, 5500000.00, 'refunded', NULL, NULL, NULL, NULL, 'cancelled', NULL, '2025-11-28 18:05:36', NULL, NULL, NULL, NULL, '2025-07-25 17:00:00', '2025-11-28 18:05:36', 0, 0, 0.00, 0.00),
(113, 'BK176435313693', 1, 4, NULL, 'Guest 93', 'guest93@example.com', '0901234567', 'Việt Nam', '2025-06-17', '2025-06-22', 5, 1, 0, 99999999.99, 0.00, 0.00, 0.00, 99999999.99, 'paid', NULL, NULL, NULL, NULL, 'checked_out', NULL, '2025-11-28 18:05:36', NULL, NULL, NULL, NULL, '2025-06-16 17:00:00', '2025-11-28 18:05:36', 0, 0, 0.00, 0.00),
(114, 'BK176435313694', 2, 3, NULL, 'Guest 94', 'guest94@example.com', '0901234567', 'Việt Nam', '2025-06-11', '2025-06-13', 2, 1, 0, 25000000.00, 0.00, 0.00, 0.00, 25000000.00, 'paid', NULL, NULL, NULL, NULL, 'checked_out', NULL, '2025-11-28 18:05:36', NULL, NULL, NULL, NULL, '2025-06-10 17:00:00', '2025-11-28 18:05:36', 0, 0, 0.00, 0.00),
(115, 'BK176435313695', 2, 1, NULL, 'Guest 95', 'guest95@example.com', '0901234567', 'Việt Nam', '2025-08-29', '2025-09-03', 5, 1, 0, 27500000.00, 0.00, 0.00, 0.00, 27500000.00, 'paid', NULL, NULL, NULL, NULL, 'checked_out', NULL, '2025-11-28 18:05:36', NULL, NULL, NULL, NULL, '2025-08-28 17:00:00', '2025-11-28 18:05:36', 0, 0, 0.00, 0.00),
(116, 'BK176435313696', 1, 2, NULL, 'Guest 96', 'guest96@example.com', '0901234567', 'Việt Nam', '2025-10-19', '2025-10-20', 1, 1, 0, 6500000.00, 0.00, 0.00, 0.00, 6500000.00, 'refunded', NULL, NULL, NULL, NULL, 'cancelled', NULL, '2025-11-28 18:05:36', NULL, NULL, NULL, NULL, '2025-10-18 17:00:00', '2025-11-28 18:05:36', 0, 0, 0.00, 0.00),
(117, 'BK176435313697', 2, 3, NULL, 'Guest 97', 'guest97@example.com', '0901234567', 'Việt Nam', '2025-09-20', '2025-09-22', 2, 1, 0, 25000000.00, 0.00, 0.00, 0.00, 25000000.00, 'refunded', NULL, NULL, NULL, NULL, 'cancelled', NULL, '2025-11-28 18:05:36', NULL, NULL, NULL, NULL, '2025-09-19 17:00:00', '2025-11-28 18:05:36', 0, 0, 0.00, 0.00),
(118, 'BK176435313698', 1, 3, NULL, 'Guest 98', 'guest98@example.com', '0901234567', 'Việt Nam', '2025-08-18', '2025-08-19', 1, 1, 0, 12500000.00, 0.00, 0.00, 0.00, 12500000.00, 'paid', NULL, NULL, NULL, NULL, 'checked_out', NULL, '2025-11-28 18:05:36', NULL, NULL, NULL, NULL, '2025-08-17 17:00:00', '2025-11-28 18:05:36', 0, 0, 0.00, 0.00),
(119, 'BK176435313699', 2, 1, NULL, 'Guest 99', 'guest99@example.com', '0901234567', 'Việt Nam', '2025-07-06', '2025-07-11', 5, 1, 0, 27500000.00, 0.00, 0.00, 0.00, 27500000.00, 'paid', NULL, NULL, NULL, NULL, 'checked_out', NULL, '2025-11-28 18:05:36', NULL, NULL, NULL, NULL, '2025-07-05 17:00:00', '2025-11-28 18:05:36', 0, 0, 0.00, 0.00),
(120, 'VPC202511292589', 3, 2, 13, 'Bùi Quang Vinh', 'b.vinh2005@gmail.com', '09427113367', 'Việt Nam', '2025-11-29', '2025-11-30', 1, 2, 1, 6500000.00, 10550000.00, 0.00, 0.00, 17050000.00, 'paid', 'qr_code', 'Trăng mật (Cánh hoa hồng, nến)', 'Mát (22°C)', '', 'checked_in', NULL, '2025-11-29 07:51:04', '2025-11-29 07:51:04', '2025-11-29 07:56:14', NULL, NULL, '2025-11-29 07:51:04', '2025-11-29 07:56:14', 0, 0, 0.00, 0.00),
(121, 'VPC202512089765', 4, 4, 24, 'Thư', 'b.vinh2005@gmail.com', '0942333', 'Việt Nam', '2025-12-08', '2025-12-09', 1, 2, 2, 25000000.00, 8888888.00, 0.00, 0.00, 33888888.00, 'paid', 'qr_code', 'Tiêu chuẩn (Mặc định)', 'Tiêu chuẩn (25°C)', '', 'cancelled', NULL, '2025-12-08 12:48:52', '2025-12-08 12:48:52', '2025-12-09 12:38:58', NULL, NULL, '2025-12-08 12:48:52', '2025-12-09 12:39:00', 0, 0, 0.00, 0.00),
(122, 'VPC202512089371', 3, 4, 25, 'Bùi Quang Vinh', 'b.vinh2005@gmail.com', '09427113367', 'Việt Nam', '2025-12-08', '2025-12-09', 1, 2, 0, 25000000.00, 4444444.00, 0.00, 0.00, 29444444.00, 'paid', 'qr_code', 'Tiêu chuẩn (Mặc định)', 'Tiêu chuẩn (25°C)', '', 'checked_out', NULL, '2025-12-08 12:51:13', '2025-12-08 12:51:13', '2025-12-08 12:51:58', '2025-12-09 12:34:26', NULL, '2025-12-08 12:51:13', '2025-12-09 12:34:26', 0, 0, 0.00, 0.00),
(123, 'VPC202512094406', NULL, 4, 24, 'Bùi Quang Vinh', 'b.vinh2005@gmail.com', '09427113367', 'Việt Nam', '2025-12-09', '2025-12-10', 1, 2, 0, 25000000.00, 500000.00, 0.00, 0.00, 25500000.00, 'paid', 'qr_code', 'Tiêu chuẩn (Mặc định)', 'Tiêu chuẩn (25°C)', '', 'confirmed', NULL, '2025-12-09 12:26:13', '2025-12-09 12:26:13', NULL, NULL, NULL, '2025-12-09 12:26:13', '2025-12-09 12:26:13', 0, 0, 0.00, 0.00),
(124, 'VPC202512093497', NULL, 4, 24, 'Bùi Quang Vinh', 'b.vinh2005@gmail.com', '0941713378', 'Việt Nam', '2025-12-10', '2025-12-11', 1, 2, 0, 25000000.00, 500000.00, 0.00, 0.00, 25500000.00, 'paid', 'qr_code', 'Tiêu chuẩn (Mặc định)', 'Tiêu chuẩn (25°C)', '', 'checked_in', NULL, '2025-12-09 12:27:09', '2025-12-09 12:27:09', '2025-12-09 14:39:24', NULL, NULL, '2025-12-09 12:27:09', '2025-12-09 14:39:24', 0, 0, 0.00, 0.00),
(125, 'VPC202512097220', 3, 3, 1, 'Bùi Quang Vinh', 'b.vinh2005@gmail.com', '09427113367', 'Việt Nam', '2025-12-09', '2025-12-10', 1, 2, 0, 5500000.00, 500000.00, 0.00, 0.00, 13000000.00, 'paid', 'qr_code', 'Tiêu chuẩn (Mặc định)', 'Tiêu chuẩn (25°C)', '', 'checked_out', NULL, '2025-12-09 12:30:38', '2025-12-09 12:30:38', '2025-12-09 14:15:56', '2025-12-09 14:39:15', NULL, '2025-12-09 12:30:38', '2025-12-09 14:39:15', 0, 0, 0.00, 0.00),
(126, 'BK2025120910CA3C', NULL, 4, 24, 'rrr', '', '24423423', 'Việt Nam', '2025-12-13', '2025-12-14', 1, 2, 0, 25000000.00, 0.00, 2000000.00, 1250000.00, 28750000.00, 'pending', NULL, NULL, NULL, '', 'cancelled', NULL, '2025-12-09 13:05:21', NULL, '2025-12-09 14:24:00', NULL, NULL, '2025-12-09 13:05:21', '2025-12-09 14:39:07', 0, 1, 0.00, 500000.00),
(127, 'VPC20251209C6D929', NULL, 1, 3, 'đư', '', '23132', 'Việt Nam', '2025-12-09', '2025-12-10', 1, 1, 0, 99999.00, 0.00, 7999.92, 4999.95, 6012999.87, 'pending', NULL, NULL, NULL, '', 'checked_in', NULL, '2025-12-09 14:02:20', NULL, '2025-12-09 14:31:30', NULL, NULL, '2025-12-09 14:02:20', '2025-12-09 14:37:13', 0, 1, 0.00, 500000.00),
(128, 'VPC202512096633', NULL, 2, 16, 'tyuty', '', '56756', 'Việt Nam', '2025-12-09', '2025-12-12', 3, 1, 0, 299997.00, 0.00, 23999.76, 14999.85, 19538999.61, 'pending', NULL, NULL, NULL, '', 'confirmed', NULL, '2025-12-09 14:37:45', NULL, NULL, NULL, NULL, '2025-12-09 14:37:45', '2025-12-09 14:38:41', 0, 0, 0.00, 0.00);

--
-- Bẫy `bookings`
--
DELIMITER $$
CREATE TRIGGER `tr_booking_checkout` AFTER UPDATE ON `bookings` FOR EACH ROW BEGIN
  IF NEW.booking_status = 'checked_out' AND NEW.room_number_id IS NOT NULL THEN
    UPDATE room_numbers 
    SET status = 'cleaning' 
    WHERE id = NEW.room_number_id;
  END IF;
  
  IF NEW.booking_status = 'cancelled' AND NEW.room_number_id IS NOT NULL THEN
    UPDATE room_numbers 
    SET status = 'available' 
    WHERE id = NEW.room_number_id;
  END IF;
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `tr_booking_created` AFTER INSERT ON `bookings` FOR EACH ROW BEGIN
  IF NEW.room_number_id IS NOT NULL THEN
    UPDATE room_numbers 
    SET status = 'occupied' 
    WHERE id = NEW.room_number_id;
  END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `booking_addons`
--

CREATE TABLE `booking_addons` (
  `id` int(11) NOT NULL,
  `booking_id` int(11) NOT NULL,
  `addon_type` enum('standard','upsell') NOT NULL,
  `addon_id` int(11) NOT NULL,
  `addon_name` varchar(150) NOT NULL,
  `addon_price` decimal(10,2) NOT NULL,
  `quantity` int(11) DEFAULT 1,
  `status` enum('pending','confirmed','completed','cancelled') DEFAULT 'confirmed',
  `staff_notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Đang đổ dữ liệu cho bảng `booking_addons`
--

INSERT INTO `booking_addons` (`id`, `booking_id`, `addon_type`, `addon_id`, `addon_name`, `addon_price`, `quantity`, `status`, `staff_notes`, `created_at`, `updated_at`) VALUES
(38, 12, '', 0, 'Trả phòng muộn (16:00 PM)', 500000.00, 1, 'confirmed', NULL, '2025-11-28 05:34:45', NULL),
(39, 12, 'standard', 0, 'Thuê xe máy (200000 x 1 xe x 1 ngày)', 200000.00, 1, 'confirmed', NULL, '2025-11-28 05:34:45', NULL),
(40, 13, 'upsell', 0, 'Gói Lãng mạn Cuối tuần', 2500000.00, 1, 'confirmed', NULL, '2025-11-28 14:47:51', NULL),
(41, 13, 'standard', 0, 'Bữa sáng Buffet (350000 x 2 khách x 1 ngày)', 700000.00, 2, 'confirmed', NULL, '2025-11-28 14:47:51', NULL),
(42, 13, 'standard', 0, 'Đưa đón sân bay', 800000.00, 2, 'confirmed', NULL, '2025-11-28 14:47:51', NULL),
(43, 13, 'standard', 0, 'Massage 60 phút (650000 x 2 khách)', 1300000.00, 2, 'confirmed', NULL, '2025-11-28 14:47:51', NULL),
(44, 13, 'standard', 0, 'Tour tham quan (450000 x 2 khách)', 900000.00, 2, 'confirmed', NULL, '2025-11-28 14:47:51', NULL),
(45, 13, 'standard', 0, 'Thuê xe máy (200000 x 1 xe x 1 ngày)', 200000.00, 1, 'confirmed', NULL, '2025-11-28 14:47:51', NULL),
(46, 13, 'standard', 0, 'Late check-out', 500000.00, 2, 'confirmed', NULL, '2025-11-28 14:47:51', NULL),
(47, 13, 'standard', 0, 'Hoa tươi trong phòng', 300000.00, 2, 'confirmed', NULL, '2025-11-28 14:47:51', NULL),
(48, 13, 'standard', 0, 'Rượu vang cao cấp', 1200000.00, 2, 'confirmed', NULL, '2025-11-28 14:47:51', NULL),
(49, 14, 'standard', 0, 'Bữa sáng Buffet (350000 x 2 khách x 1 ngày)', 700000.00, 2, 'confirmed', NULL, '2025-11-28 14:51:34', NULL),
(50, 14, 'standard', 0, 'Đưa đón sân bay', 800000.00, 2, 'confirmed', NULL, '2025-11-28 14:51:34', NULL),
(51, 14, 'standard', 0, 'Massage 60 phút (650000 x 2 khách)', 1300000.00, 2, 'confirmed', NULL, '2025-11-28 14:51:34', NULL),
(52, 14, 'standard', 0, 'Tour tham quan (450000 x 2 khách)', 900000.00, 2, 'confirmed', NULL, '2025-11-28 14:51:34', NULL),
(53, 14, 'standard', 0, 'Thuê xe máy (200000 x 1 xe x 1 ngày)', 200000.00, 1, 'confirmed', NULL, '2025-11-28 14:51:34', NULL),
(54, 14, 'standard', 0, 'Hoa tươi trong phòng', 300000.00, 2, 'confirmed', NULL, '2025-11-28 14:51:34', NULL),
(55, 14, 'standard', 0, 'Rượu vang cao cấp', 1200000.00, 2, 'confirmed', NULL, '2025-11-28 14:51:34', NULL),
(56, 15, '', 0, 'Trả phòng muộn (16:00 PM)', 500000.00, 1, 'confirmed', NULL, '2025-11-28 15:04:36', NULL),
(57, 15, 'standard', 0, 'Bữa sáng Buffet (350000 x 2 khách x 3 ngày)', 2100000.00, 2, 'confirmed', NULL, '2025-11-28 15:04:36', NULL),
(58, 15, 'standard', 0, 'Đưa đón sân bay', 800000.00, 2, 'confirmed', NULL, '2025-11-28 15:04:36', NULL),
(59, 15, 'standard', 0, 'Massage 60 phút (650000 x 2 khách)', 1300000.00, 2, 'confirmed', NULL, '2025-11-28 15:04:36', NULL),
(60, 15, 'standard', 0, 'Tour tham quan (450000 x 2 khách)', 900000.00, 2, 'confirmed', NULL, '2025-11-28 15:04:36', NULL),
(61, 15, 'standard', 0, 'Thuê xe máy (200000 x 1 xe x 3 ngày)', 600000.00, 1, 'confirmed', NULL, '2025-11-28 15:04:36', NULL),
(62, 15, 'standard', 0, 'Late check-out', 500000.00, 2, 'confirmed', NULL, '2025-11-28 15:04:36', NULL),
(63, 15, 'standard', 0, 'Hoa tươi trong phòng', 300000.00, 2, 'confirmed', NULL, '2025-11-28 15:04:36', NULL),
(64, 15, 'standard', 0, 'Rượu vang cao cấp', 1200000.00, 2, 'confirmed', NULL, '2025-11-28 15:04:36', NULL),
(65, 16, 'upsell', 0, 'Gói Lãng mạn Cuối tuần', 2500000.00, 1, 'confirmed', NULL, '2025-11-28 15:14:57', NULL),
(66, 16, 'standard', 0, 'Bữa sáng Buffet (350000 x 2 khách x 3 ngày)', 2100000.00, 2, 'confirmed', NULL, '2025-11-28 15:14:57', NULL),
(67, 16, 'standard', 0, 'Đưa đón sân bay', 800000.00, 2, 'confirmed', NULL, '2025-11-28 15:14:57', NULL),
(68, 16, 'standard', 0, 'Massage 60 phút (650000 x 2 khách)', 1300000.00, 2, 'confirmed', NULL, '2025-11-28 15:14:57', NULL),
(69, 16, 'standard', 0, 'Tour tham quan (450000 x 2 khách)', 900000.00, 2, 'confirmed', NULL, '2025-11-28 15:14:57', NULL),
(70, 16, 'standard', 0, 'Thuê xe máy (200000 x 1 xe x 3 ngày)', 600000.00, 1, 'confirmed', NULL, '2025-11-28 15:14:57', NULL),
(71, 16, 'standard', 0, 'Late check-out', 500000.00, 2, 'confirmed', NULL, '2025-11-28 15:14:57', NULL),
(72, 16, 'standard', 0, 'Hoa tươi trong phòng', 300000.00, 2, 'confirmed', NULL, '2025-11-28 15:14:57', NULL),
(73, 16, 'standard', 0, 'Rượu vang cao cấp', 1200000.00, 2, 'confirmed', NULL, '2025-11-28 15:14:57', NULL),
(74, 17, 'standard', 0, 'Bữa sáng Buffet (350000 x 2 khách x 2 ngày)', 1400000.00, 2, 'confirmed', NULL, '2025-11-28 15:19:58', NULL),
(75, 17, 'standard', 0, 'Đưa đón sân bay', 800000.00, 2, 'confirmed', NULL, '2025-11-28 15:19:58', NULL),
(76, 17, 'standard', 0, 'Massage 60 phút (650000 x 2 khách)', 1300000.00, 2, 'confirmed', NULL, '2025-11-28 15:19:58', NULL),
(77, 17, 'standard', 0, 'Tour tham quan (450000 x 2 khách)', 900000.00, 2, 'confirmed', NULL, '2025-11-28 15:19:58', NULL),
(78, 17, 'standard', 0, 'Thuê xe máy (200000 x 1 xe x 2 ngày)', 400000.00, 1, 'confirmed', NULL, '2025-11-28 15:19:58', NULL),
(79, 17, 'standard', 0, 'Late check-out', 500000.00, 2, 'confirmed', NULL, '2025-11-28 15:19:58', NULL),
(80, 17, 'standard', 0, 'Hoa tươi trong phòng', 300000.00, 2, 'confirmed', NULL, '2025-11-28 15:19:58', NULL),
(81, 17, 'standard', 0, 'Rượu vang cao cấp', 1200000.00, 2, 'confirmed', NULL, '2025-11-28 15:19:58', NULL),
(82, 18, 'standard', 0, 'Bữa sáng Buffet (350.000 x 2 khách x 2 ngày)', 1400000.00, 2, 'confirmed', NULL, '2025-11-28 15:37:35', NULL),
(83, 18, 'standard', 0, 'Đưa đón sân bay', 800000.00, 2, 'confirmed', NULL, '2025-11-28 15:37:35', NULL),
(84, 18, 'standard', 0, 'Massage 60 phút (650.000 x 2 khách)', 1300000.00, 2, 'confirmed', NULL, '2025-11-28 15:37:35', NULL),
(85, 18, 'standard', 0, 'Tour tham quan (450.000 x 2 khách)', 900000.00, 2, 'confirmed', NULL, '2025-11-28 15:37:35', NULL),
(86, 18, 'standard', 0, 'Thuê xe máy (200.000 x 1 xe x 2 ngày)', 400000.00, 1, 'confirmed', NULL, '2025-11-28 15:37:35', NULL),
(87, 18, 'standard', 0, 'Late check-out', 500000.00, 2, 'confirmed', NULL, '2025-11-28 15:37:35', NULL),
(88, 18, 'standard', 0, 'Hoa tươi trong phòng', 300000.00, 2, 'confirmed', NULL, '2025-11-28 15:37:35', NULL),
(89, 18, 'standard', 0, 'Rượu vang cao cấp', 1200000.00, 2, 'confirmed', NULL, '2025-11-28 15:37:35', NULL),
(90, 19, 'standard', 0, 'Bữa sáng Buffet (350.000 x 2 khách x 1 ngày)', 700000.00, 2, 'confirmed', NULL, '2025-11-28 16:26:37', NULL),
(91, 19, 'standard', 0, 'Đưa đón sân bay', 800000.00, 2, 'confirmed', NULL, '2025-11-28 16:26:37', NULL),
(92, 19, 'standard', 0, 'Massage 60 phút (650.000 x 2 khách)', 1300000.00, 2, 'confirmed', NULL, '2025-11-28 16:26:37', NULL),
(93, 19, 'standard', 0, 'Tour tham quan (450.000 x 2 khách)', 900000.00, 2, 'confirmed', NULL, '2025-11-28 16:26:37', NULL),
(94, 19, 'standard', 0, 'Thuê xe máy (200.000 x 1 xe x 1 ngày)', 200000.00, 1, 'confirmed', NULL, '2025-11-28 16:26:37', NULL),
(95, 19, 'standard', 0, 'Late check-out', 500000.00, 2, 'confirmed', NULL, '2025-11-28 16:26:37', NULL),
(96, 19, 'standard', 0, 'Hoa tươi trong phòng', 300000.00, 2, 'confirmed', NULL, '2025-11-28 16:26:37', NULL),
(97, 19, 'standard', 0, 'Rượu vang cao cấp', 1200000.00, 2, 'confirmed', NULL, '2025-11-28 16:26:37', NULL),
(98, 18, 'standard', -1, 'Đặt bàn Nhà hàng - Ngày: 28/11/2025 lúc 11:00', 0.00, 2, 'confirmed', '', '2025-11-28 16:58:01', '2025-11-29 00:37:42'),
(99, 18, 'standard', -1, 'Đặt lịch Spa - Ngày: 30/11/2025 lúc 09:00 (Massage toàn thân)', 800000.00, 1, 'confirmed', '\n[2025-11-29 00:23:04] Khách tự thanh toán online - vnpay', '2025-11-28 16:58:12', '2025-11-29 00:07:02'),
(100, 18, 'standard', -1, 'Vé VinWonders - Ngày: 30/11/2025', 600000.00, 2, 'confirmed', '\n[2025-11-29 00:33:52] Khách tự thanh toán online - cash', '2025-11-28 16:58:30', '2025-11-29 00:08:45'),
(101, 18, 'standard', -1, 'Vé VinWonders - Ngày: 30/11/2025', 600000.00, 2, 'completed', '', '2025-11-28 17:14:00', '2025-11-29 14:59:32'),
(102, 120, 'upsell', 0, 'Gói Gia đình Vui vẻ', 3200000.00, 1, 'confirmed', NULL, '2025-11-29 07:51:04', NULL),
(103, 120, 'standard', 0, 'Bữa sáng Buffet (350.000 x 3 khách x 1 ngày)', 1050000.00, 3, 'confirmed', NULL, '2025-11-29 07:51:04', NULL),
(104, 120, 'standard', 0, 'Đưa đón sân bay', 800000.00, 3, 'confirmed', NULL, '2025-11-29 07:51:04', NULL),
(105, 120, 'standard', 0, 'Massage 60 phút (650.000 x 3 khách)', 1950000.00, 3, 'confirmed', NULL, '2025-11-29 07:51:04', NULL),
(106, 120, 'standard', 0, 'Tour tham quan (450.000 x 3 khách)', 1350000.00, 3, 'confirmed', NULL, '2025-11-29 07:51:04', NULL),
(107, 120, 'standard', 0, 'Thuê xe máy (200.000 x 1 xe x 1 ngày)', 200000.00, 1, 'confirmed', NULL, '2025-11-29 07:51:04', NULL),
(108, 120, 'standard', 0, 'Late check-out', 500000.00, 3, 'confirmed', NULL, '2025-11-29 07:51:04', NULL),
(109, 120, 'standard', 0, 'Hoa tươi trong phòng', 300000.00, 3, 'confirmed', NULL, '2025-11-29 07:51:04', NULL),
(110, 120, 'standard', 0, 'Rượu vang cao cấp', 1200000.00, 3, 'confirmed', NULL, '2025-11-29 07:51:04', NULL),
(111, 19, 'standard', -1, 'Đặt bàn Nhà hàng - Ngày: 30/11/2025 lúc 18:00', 0.00, 2, 'completed', '', '2025-11-29 07:52:52', '2025-11-29 14:59:35'),
(112, 19, 'standard', -1, 'Vé VinWonders - Ngày: 30/11/2025', 600000.00, 2, 'pending', '', '2025-11-29 07:53:04', '2025-12-09 21:05:14'),
(113, 121, 'standard', 0, 'Special (22.222 x 4 khách)', 88888.00, 4, 'confirmed', NULL, '2025-12-08 12:48:52', NULL),
(114, 121, 'standard', 0, 'Bữa sáng Buffet (350.000 x 4 khách x 1 ngày)', 1400000.00, 4, 'confirmed', NULL, '2025-12-08 12:48:52', NULL),
(115, 121, 'standard', 0, 'Đưa đón sân bay', 800000.00, 4, 'confirmed', NULL, '2025-12-08 12:48:52', NULL),
(116, 121, 'standard', 0, 'Massage 60 phút (650.000 x 4 khách)', 2600000.00, 4, 'confirmed', NULL, '2025-12-08 12:48:52', NULL),
(117, 121, 'standard', 0, 'Tour tham quan (450.000 x 4 khách)', 1800000.00, 4, 'confirmed', NULL, '2025-12-08 12:48:52', NULL),
(118, 121, 'standard', 0, 'Thuê xe máy (200.000 x 1 xe x 1 ngày)', 200000.00, 1, 'confirmed', NULL, '2025-12-08 12:48:52', NULL),
(119, 121, 'standard', 0, 'Late check-out', 500000.00, 4, 'confirmed', NULL, '2025-12-08 12:48:52', NULL),
(120, 121, 'standard', 0, 'Hoa tươi trong phòng', 300000.00, 4, 'confirmed', NULL, '2025-12-08 12:48:52', NULL),
(121, 121, 'standard', 0, 'Rượu vang cao cấp', 1200000.00, 4, 'confirmed', NULL, '2025-12-08 12:48:52', NULL),
(122, 122, 'standard', 0, 'Special (22.222 x 2 khách)', 44444.00, 2, 'confirmed', NULL, '2025-12-08 12:51:13', NULL),
(123, 122, 'standard', 0, 'Bữa sáng Buffet (350.000 x 2 khách x 1 ngày)', 700000.00, 2, 'confirmed', NULL, '2025-12-08 12:51:13', NULL),
(124, 122, 'standard', 0, 'Đưa đón sân bay', 800000.00, 2, 'confirmed', NULL, '2025-12-08 12:51:13', NULL),
(125, 122, 'standard', 0, 'Massage 60 phút (650.000 x 2 khách)', 1300000.00, 2, 'confirmed', NULL, '2025-12-08 12:51:13', NULL),
(126, 122, 'standard', 0, 'Tour tham quan (450.000 x 2 khách)', 900000.00, 2, 'confirmed', NULL, '2025-12-08 12:51:13', NULL),
(127, 122, 'standard', 0, 'Thuê xe máy (200.000 x 1 xe x 1 ngày)', 200000.00, 1, 'confirmed', NULL, '2025-12-08 12:51:13', NULL),
(128, 122, 'standard', 0, 'Late check-out', 500000.00, 2, 'confirmed', NULL, '2025-12-08 12:51:13', NULL),
(129, 123, 'standard', 0, 'Late check-out', 500000.00, 2, 'confirmed', NULL, '2025-12-09 12:26:13', NULL),
(130, 124, '', 0, 'Trả phòng muộn (16:00 PM)', 500000.00, 1, 'confirmed', NULL, '2025-12-09 12:27:09', NULL),
(131, 125, '', 0, 'Trả phòng muộn (16:00 PM)', 500000.00, 1, 'confirmed', NULL, '2025-12-09 12:30:38', NULL),
(132, 127, 'standard', -1, 'Nâng cấp hạng phòng (Special Test ➝ Junior Suite)', 5400001.00, 1, 'cancelled', '\n[2025-12-09 21:31:37] Hủy bởi NV: ', '2025-12-09 14:04:37', NULL),
(133, 125, 'standard', -1, 'Nâng cấp hạng phòng (Family Room ➝ Executive Suite)', 6000000.00, 1, 'cancelled', '\n[2025-12-09 21:16:57] Hủy bởi NV: ', '2025-12-09 14:16:22', NULL),
(134, 126, 'standard', -1, 'Check-out Muộn', 500000.00, 1, 'confirmed', '\n[2025-12-09 21:31:15] Thanh toán tại quầy - VNPay - NV: ', '2025-12-09 14:30:04', NULL),
(135, 127, 'standard', -1, 'Nâng cấp hạng phòng (Junior Suite ➝ Family Room)', 1000000.00, 1, 'cancelled', '\n[2025-12-09 21:31:50] Hủy bởi NV: \n[2025-12-09 21:32:02] Hủy bởi NV: \n[2025-12-09 21:32:05] Hủy bởi NV: \n[2025-12-09 21:36:46] Hủy bởi NV: ', '2025-12-09 14:31:41', NULL),
(136, 127, 'standard', -1, 'Nâng cấp hạng phòng (Junior Suite ➝ Family Room)', 1000000.00, 1, 'cancelled', '\n[System] Hủy tự động do chuyển về phòng giá thấp hơn', '2025-12-09 14:37:05', NULL),
(137, 128, 'standard', -1, 'Nâng cấp hạng phòng (Special Test ➝ Junior Suite)', 5400001.00, 3, 'pending', NULL, '2025-12-09 14:38:09', NULL),
(138, 128, 'standard', -1, 'Nâng cấp hạng phòng (Junior Suite ➝ Family Room)', 1000000.00, 3, 'pending', NULL, '2025-12-09 14:38:38', NULL);

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `comments`
--

CREATE TABLE `comments` (
  `id` int(11) NOT NULL,
  `news_id` int(11) NOT NULL,
  `user_name` varchar(100) NOT NULL,
  `user_email` varchar(100) NOT NULL,
  `content` text NOT NULL,
  `status` enum('pending','approved') DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Đang đổ dữ liệu cho bảng `comments`
--

INSERT INTO `comments` (`id`, `news_id`, `user_name`, `user_email`, `content`, `status`, `created_at`) VALUES
(6, 2, 'Thư', 'no-email@domain.com', 'helo', 'approved', '2025-12-08 11:41:11'),
(7, 2, 'Thư', 'no-email@domain.com', 'helo', 'approved', '2025-12-08 11:41:19'),
(9, 3, 'Thư', 'no-email@domain.com', 'hello', 'approved', '2025-12-08 11:50:27'),
(11, 4, 'Thư', 'no-email@domain.com', 'Quá tuyệt vời', 'approved', '2025-12-08 12:06:22'),
(12, 4, 'Thư', 'no-email@domain.com', 'Quá tuyệt vời', 'pending', '2025-12-08 12:06:32');

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `contact_messages`
--

CREATE TABLE `contact_messages` (
  `id` int(11) NOT NULL,
  `name` varchar(150) NOT NULL,
  `email` varchar(150) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `subject` varchar(200) DEFAULT NULL,
  `message` text NOT NULL,
  `status` enum('new','read','replied','archived') DEFAULT 'new',
  `replied_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Đang đổ dữ liệu cho bảng `contact_messages`
--

INSERT INTO `contact_messages` (`id`, `name`, `email`, `phone`, `subject`, `message`, `status`, `replied_at`, `created_at`) VALUES
(1, 'Vinh', 'b.vinh2005@gmail.com', NULL, 'huỷ tìa khoả', 'huỷ tài khoản cho tôi', 'new', NULL, '2025-12-09 15:13:07');

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `news`
--

CREATE TABLE `news` (
  `id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `content` text NOT NULL,
  `image_url` varchar(255) DEFAULT NULL,
  `category` varchar(50) DEFAULT 'Tin tức',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Đang đổ dữ liệu cho bảng `news`
--

INSERT INTO `news` (`id`, `title`, `content`, `image_url`, `category`, `created_at`) VALUES
(2, 'Tại sao cần chọn dịch vụ này', '<p>Warning: include(includes/header.php): Failed to open stream: No such file or directory in D:\\_apps\\xampp\\htdocs\\blog\\blog.php on line 5 Warning: include(): Failed opening \'includes/header.php\' for inclusion (include_path=\'D:\\_apps\\xampp\\php\\PEAR\') in D:\\_apps\\xampp\\htdocs\\blog\\blog.php on line 5 Warning: require_once(includes/db_connect.php): Failed to open stream: No such file or directory in D:\\_apps\\xampp\\htdocs\\blog\\blog.php on line 6 Fatal error: Uncaught Error: Failed opening required \'includes/db_connect.php\' (include_path=\'D:\\_apps\\xampp\\php\\PEAR\') in D:\\_apps\\xampp\\htdocs\\blog\\blog.php:6 Stack trace: #0 {main} thrown in D:\\_apps\\xampp\\htdocs\\blog\\blog.php on line 6</p><p>&nbsp;</p>', 'uploads/1765194538_ho_boi.jpg', 'Ẩm thực', '2025-12-08 11:40:38'),
(3, 'Hello', '<p><strong>tfghfghfgh</strong></p><blockquote><p><strong>sdfds &nbsp;</strong></p></blockquote><p>&nbsp;</p><p>&nbsp;</p>', 'uploads/1765194585_vinpearl_lux.jpg', 'Du lịch', '2025-12-08 11:49:40'),
(4, 'Một Tầm Nhìn Mới Về Sự Sang Trọng', '<p>Vinpearl Cần Giờ là biểu tượng của sự sang trọng, mang đến sự thoải mái vô song và dịch vụ bespoke. Nằm giữa trung tâm, chúng tôi cung cấp một lối thoát yên bình khỏi sự hối hả hàng ngày.</p><blockquote><p>Cam kết của chúng tôi về sự xuất sắc được thể hiện trong từng chi tiết, từ các phòng được thiết kế trang nhã đến các tiện nghi đẳng cấp thế giới.</p><p>&nbsp;</p></blockquote><p>&nbsp;</p>', 'uploads/1765195200_lounge.jpg', 'Tin tức', '2025-12-08 12:00:00'),
(5, 'Chính sách bảo mật', '<h2><strong>Chúng tôi phát triển một loạt các dịch vụ giúp hàng triệu người hàng ngày khám phá và tương tác với thế giới theo những cách mới.&nbsp;</strong></h2><h2>&nbsp;</h2><p>Các dịch vụ của chúng tôi gồm có: Các ứng dụng, trang web và thiết bị của Google, chẳng hạn như Tìm kiếm, YouTube và Google Home&nbsp;</p><p>Các nền tảng như trình duyệt Chrome và hệ điều hành Android Các sản phẩm tích hợp vào các ứng dụng và trang web của bên thứ ba, chẳng hạn như dịch vụ quảng cáo, phân tích và Google Maps đã nhúng Bạn có thể sử dụng các dịch vụ của chúng tôi theo nhiều cách khác nhau để quản lý quyền riêng tư của mình. Ví dụ: bạn có thể đăng ký Tài khoản Google nếu muốn tạo và quản lý những nội dung như email và ảnh, hoặc muốn nhận được kết quả tìm kiếm có liên quan hơn. Ngoài ra, bạn có thể sử dụng nhiều dịch vụ của Google khi đã đăng xuất hoặc không cần tạo tài khoản, chẳng hạn như tìm kiếm trên Google hoặc xem video trên YouTube. Bạn cũng có thể chọn duyệt web ở chế độ riêng tư, chẳng hạn như chế độ Ẩn danh trên Chrome. Chế độ này giúp giữ bí mật hoạt động duyệt web của bạn với những người khác sử dụng thiết bị của bạn. Ngoài ra, trên các dịch vụ của chúng tôi, bạn có thể điều chỉnh chế độ cài đặt quyền riêng tư để kiểm soát việc chúng tôi có được phép thu thập một số loại dữ liệu hay không và cách chúng tôi sử dụng những dữ liệu này. Để giúp giải thích mọi điều rõ ràng nhất có thể, chúng tôi đã thêm các ví dụ, video giải thích và các định nghĩa cho những thuật ngữ chính. Nếu có bất kỳ câu hỏi nào về Chính sách bảo mật này, bạn có thể liên hệ với chúng tôi.&nbsp;</p><p>&nbsp;</p><p>THÔNG TIN GOOGLE THU THẬP Chúng tôi muốn bạn hiểu rõ các loại thông tin mà chúng tôi thu thập khi bạn sử dụng dịch vụ của chúng tôi Chúng tôi thu thập thông tin để cung cấp các dịch vụ tốt hơn cho tất cả người dùng của mình — từ việc xác định những thông tin cơ bản như ngôn ngữ mà bạn nói cho đến những thông tin phức tạp hơn như quảng cáo nào bạn sẽ thấy hữu ích nhất, những người quan trọng nhất với bạn khi trực tuyến hay những video nào trên YouTube mà bạn có thể thích. Thông tin Google thu thập và cách thông tin đó được sử dụng tùy thuộc vào cách bạn dùng các dịch vụ của chúng tôi cũng như cách bạn quản lý các tùy chọn kiểm soát bảo mật của mình. Khi bạn không đăng nhập vào một Tài khoản Google, chúng tôi sẽ lưu trữ thông tin chúng tôi thu thập được cùng với các giá trị nhận dạng duy nhất được liên kết với trình duyệt, ứng dụng hoặc thiết bị bạn đang sử dụng. Cách này cho phép chúng tôi thực hiện được những việc như duy trì các lựa chọn ưu tiên của bạn trong các phiên duyệt web, chẳng hạn như ngôn ngữ bạn ưa thích hay có hiển thị cho bạn các kết quả tìm kiếm hoặc quảng cáo phù hợp hơn dựa trên hoạt động của bạn hay không. Khi bạn đã đăng nhập, chúng tôi cũng thu thập cả thông tin mà chúng tôi lưu trữ cùng với Tài khoản Google của bạn. Chúng tôi xem những thông tin này là thông tin cá nhân. Những thông tin bạn tạo hoặc cung cấp cho chúng tôi Khi tạo một Tài khoản Google, bạn cung cấp cho chúng tôi thông tin cá nhân (bao gồm tên của bạn và mật khẩu). Bạn cũng có thể chọn thêm số điện thoại hoặc thông tin thanh toán vào tài khoản của mình.&nbsp;</p><p>&nbsp;</p><p>Ngay cả khi không đăng nhập vào Tài khoản Google, bạn vẫn có thể chọn cung cấp cho chúng tôi thông tin như địa chỉ email để liên lạc với Google hoặc nhận thông tin cập nhật về dịch vụ của chúng tôi. Chúng tôi cũng thu thập nội dung bạn tạo, tải lên hoặc nhận được từ những người khác khi bạn sử dụng các dịch vụ của chúng tôi. Nội dung này bao gồm những thứ như email bạn viết và nhận, ảnh và video bạn lưu, tài liệu và bảng tính bạn tạo cũng như các nhận xét bạn viết về những video trên YouTube. Thông tin chúng tôi thu thập khi bạn sử dụng các dịch vụ của chúng tôi Các ứng dụng, trình duyệt và thiết bị của bạn Chúng tôi thu thập thông tin về các ứng dụng, trình duyệt và thiết bị mà bạn dùng để truy cập vào dịch vụ của Google.&nbsp;</p><p>&nbsp;</p><p>Điều này giúp chúng tôi cung cấp các tính năng như tự động cập nhật sản phẩm và giảm độ sáng màn hình nếu pin sắp hết. Thông tin chúng tôi thu thập gồm có các giá trị nhận dạng duy nhất, loại trình duyệt và các mục cài đặt, loại thiết bị và các mục cài đặt, hệ điều hành, thông tin mạng di động, bao gồm cả tên nhà mạng và số điện thoại, và số phiên bản của ứng dụng. Chúng tôi cũng thu thập thông tin về việc tương tác giữa các ứng dụng, trình duyệt và thiết bị của bạn với các dịch vụ của chúng tôi, bao gồm cả địa chỉ IP, báo cáo sự cố, hoạt động hệ thống và ngày, giờ cũng như URL tham chiếu của yêu cầu của bạn. Chúng tôi thu thập thông tin này khi một dịch vụ của Google trên thiết bị của bạn kết nối với máy chủ của chúng tôi — ví dụ như khi bạn cài đặt một ứng dụng từ Cửa hàng Play hoặc khi một dịch vụ kiểm tra để tìm các bản cập nhật tự động. Nếu bạn đang sử dụng một thiết bị Android có các ứng dụng Google, thiết bị của bạn sẽ định kỳ kết nối với máy chủ của Google để cung cấp thông tin về thiết bị của bạn và hoạt động kết nối với các dịch vụ của chúng tôi. Thông tin này bao gồm những thứ như loại thiết bị và tên nhà mạng, báo cáo sự cố, những ứng dụng bạn đã cài đặt, và thông tin khác về cách bạn đang sử dụng thiết bị Android của mình (tuỳ thuộc vào các chế độ cài đặt thiết bị mà bạn thiết lập).</p>', '', 'Tin tức', '2025-12-09 15:19:40');

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `promotions`
--

CREATE TABLE `promotions` (
  `id` int(11) NOT NULL,
  `code` varchar(50) NOT NULL,
  `title` varchar(150) NOT NULL,
  `description` text DEFAULT NULL,
  `discount_type` enum('percentage','fixed_amount') DEFAULT 'percentage',
  `discount_value` decimal(10,2) NOT NULL,
  `min_nights` int(11) DEFAULT 1,
  `min_amount` decimal(10,2) DEFAULT 0.00,
  `max_discount` decimal(10,2) DEFAULT NULL,
  `usage_limit` int(11) DEFAULT NULL,
  `used_count` int(11) DEFAULT 0,
  `valid_from` date NOT NULL,
  `valid_to` date NOT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Đang đổ dữ liệu cho bảng `promotions`
--

INSERT INTO `promotions` (`id`, `code`, `title`, `description`, `discount_type`, `discount_value`, `min_nights`, `min_amount`, `max_discount`, `usage_limit`, `used_count`, `valid_from`, `valid_to`, `is_active`, `created_at`, `updated_at`) VALUES
(1, 'WELCOME2025', 'Chào mừng 2025', 'Giảm 15% cho khách hàng mới đặt phòng đầu tiên', 'percentage', 15.00, 2, 5000000.00, 3000000.00, 100, 0, '2025-01-01', '2025-03-31', 1, '2025-11-22 13:10:19', '2025-11-22 13:10:19'),
(2, 'LONGSTAY', 'Ưu đãi lưu trú dài', 'Giảm 20% khi đặt từ 5 đêm trở lên', 'percentage', 20.00, 5, 0.00, 5000000.00, NULL, 0, '2025-01-01', '2025-12-31', 1, '2025-11-22 13:10:19', '2025-11-22 13:10:19'),
(3, 'EARLYBIRD', 'Đặt sớm - Giá tốt', 'Giảm 1 triệu khi đặt trước 30 ngày', 'fixed_amount', 1000000.00, 3, 10000000.00, NULL, 50, 0, '2025-01-01', '2025-06-30', 1, '2025-11-22 13:10:19', '2025-11-22 13:10:19');

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `reviews`
--

CREATE TABLE `reviews` (
  `id` int(11) NOT NULL,
  `booking_id` int(11) DEFAULT NULL,
  `user_id` int(11) DEFAULT NULL,
  `room_id` int(11) NOT NULL,
  `guest_name` varchar(150) NOT NULL,
  `rating` int(11) NOT NULL CHECK (`rating` between 1 and 5),
  `title` varchar(200) DEFAULT NULL,
  `comment` text DEFAULT NULL,
  `is_verified` tinyint(1) DEFAULT 0,
  `is_approved` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `rooms`
--

CREATE TABLE `rooms` (
  `id` int(11) NOT NULL,
  `room_type_name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `long_description` text DEFAULT NULL,
  `amenities_description` text DEFAULT NULL,
  `view_type` varchar(100) DEFAULT NULL,
  `price_per_night` decimal(10,2) NOT NULL,
  `max_occupancy` int(11) DEFAULT 2,
  `max_adults` int(11) DEFAULT 2,
  `max_children` int(11) DEFAULT 0,
  `num_beds` int(11) DEFAULT 1,
  `bed_type` varchar(50) DEFAULT 'King',
  `room_size_sqm` int(11) DEFAULT 35,
  `image_url` varchar(255) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `display_order` int(11) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Đang đổ dữ liệu cho bảng `rooms`
--

INSERT INTO `rooms` (`id`, `room_type_name`, `description`, `long_description`, `amenities_description`, `view_type`, `price_per_night`, `max_occupancy`, `max_adults`, `max_children`, `num_beds`, `bed_type`, `room_size_sqm`, `image_url`, `is_active`, `display_order`, `created_at`, `updated_at`) VALUES
(1, 'Junior Suite', 'Phòng Junior Suite sang trọng với ban công riêng và tầm nhìn tuyệt đẹp ra biển.', 'Junior Suite mang đến không gian nghỉ dưỡng lý tưởng với thiết kế hiện đại, sang trọng. Phòng được trang bị đầy đủ tiện nghi cao cấp, ban công riêng với tầm nhìn ra biển tuyệt đẹp. Đây là lựa chọn hoàn hảo cho các cặp đôi hoặc những du khách muốn tận hưởng kỳ nghỉ thư giãn.', 'Wifi tốc độ cao miễn phí • TV màn hình phẳng 55 inch • Hệ thống điều hòa thông minh • Minibar cao cấp • Két an toàn điện tử • Bàn làm việc • Máy sấy tóc • Đồ dùng phòng tắm cao cấp • Áo choàng tắm & dép đi trong phòng ', 'Hướng biển', 5500000.00, 2, 2, 0, 1, 'King', 45, 'uploads/rooms/1765197243_junior_suite.jpg', 1, 1, '2025-11-22 13:10:19', '2025-12-08 12:34:03'),
(2, 'Family Room', 'Phòng gia đình rộng rãi, lý tưởng cho 2 người lớn và 2 trẻ em.', 'Family Room được thiết kế đặc biệt dành cho gia đình với không gian rộng rãi, thoải mái. Phòng có khu vực sinh hoạt chung, giường ngủ đa dạng phù hợp cho cả người lớn và trẻ em. Các tiện ích giải trí và an toàn được chú trọng để mang lại kỳ nghỉ trọn vẹn cho cả gia đình.', 'Wifi miễn phí • 2 TV màn hình phẳng • Khu vực sinh hoạt riêng • Minibar gia đình • Két an toàn lớn • Góc chơi trẻ em • Thiết bị an toàn cho bé • Phòng tắm rộng rãi • Đồ dùng vệ sinh cá nhân • Tủ lạnh mini', 'Hướng vườn/biển', 6500000.00, 4, 2, 2, 2, 'Queen', 55, 'uploads/rooms/1765197861_main.jpg', 1, 2, '2025-11-22 13:10:19', '2025-12-08 12:44:21'),
(3, 'Executive Suite', 'Suite cao cấp với khu vực làm việc riêng và phòng tắm sang trọng.', 'Executive Suite là sự kết hợp hoàn hảo giữa không gian làm việc hiệu quả và nghỉ ngơi sang trọng. Với diện tích rộng rãi, phòng tắm cao cấp với bồn tắm Jacuzzi, ban công riêng và khu vực làm việc chuyên nghiệp, đây là lựa chọn lý tưởng cho doanh nhân và những ai yêu thích sự tinh tế.', 'Wifi tốc độ cao • TV màn hình phẳng 65 inch • Hệ thống âm thanh cao cấp • Minibar premium • Bồn tắm Jacuzzi • Vòi sen thác nước • Bàn làm việc executive • Máy pha cà phê Nespresso • Két an toàn laptop • Dịch vụ butler 24/7', 'Hướng biển tầng cao', 12500000.00, 3, 2, 1, 1, 'King', 70, 'uploads/rooms/1765197451_executive_suite.jpg', 1, 3, '2025-11-22 13:10:19', '2025-12-08 12:37:31'),
(4, 'Presidential Suite', 'Suite Tổng thống đẳng cấp với 120m², dành cho khách VIP.', 'Presidential Suite - Đỉnh cao của sự xa hoa và đẳng cấp. Với diện tích 120m², suite bao gồm phòng khách riêng biệt, phòng ngủ master sang trọng, phòng tắm spa, sân thượng panorama và đầy đủ tiện nghi 5 sao+. Dịch vụ butler 24/7 và các đặc quyền dành riêng cho khách VIP.', 'Wifi tốc độ cao • 2 TV màn hình phẳng 75 inch • Hệ thống âm thanh Bose • Minibar premium không giới hạn • 2 Phòng tắm với jacuzzi • Vòi sen massage • Phòng thay đồ riêng • Bếp mini • Máy giặt sấy • Butler 24/7 • Xe đưa đón riêng • Welcome gift cao cấp', 'Panorama 360°', 25000000.00, 8, 8, 4, 4, 'King', 120, 'uploads/rooms/1765197134_main.jpg', 1, 0, '2025-11-22 13:10:19', '2025-12-08 12:32:14'),
(5, 'Special Test', '', NULL, NULL, 'Gif', 99999.00, 6, 2, 0, 1, 'King', 30, 'uploads/rooms/1765198056_bg.jpg', 1, 5, '2025-11-29 08:01:11', '2025-12-08 12:47:36');

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `room_availability`
--

CREATE TABLE `room_availability` (
  `id` int(11) NOT NULL,
  `room_id` int(11) NOT NULL,
  `date` date NOT NULL,
  `available_count` int(11) DEFAULT 0,
  `total_rooms` int(11) DEFAULT 0,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `room_features`
--

CREATE TABLE `room_features` (
  `id` int(11) NOT NULL,
  `room_id` int(11) NOT NULL,
  `feature_name` varchar(100) NOT NULL,
  `icon` varchar(50) DEFAULT 'fa-check',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Đang đổ dữ liệu cho bảng `room_features`
--

INSERT INTO `room_features` (`id`, `room_id`, `feature_name`, `icon`, `created_at`) VALUES
(219, 4, 'Wifi tốc độ cao', 'fa-wifi', '2025-12-08 12:32:14'),
(220, 4, 'TV 75\" x2', 'fa-tv', '2025-12-08 12:32:14'),
(221, 4, 'Hệ thống Bose', 'fa-music', '2025-12-08 12:32:14'),
(222, 4, 'Minibar không giới hạn', 'fa-glass-cheers', '2025-12-08 12:32:14'),
(223, 4, 'Jacuzzi x2', 'fa-hot-tub', '2025-12-08 12:32:14'),
(224, 4, 'Phòng thay đồ', 'fa-snowflake', '2025-12-08 12:32:14'),
(225, 4, 'Bếp mini', 'fa-utensils', '2025-12-08 12:32:14'),
(226, 4, 'Máy giặt sấy', 'fa-soap', '2025-12-08 12:32:14'),
(227, 4, 'Butler riêng', 'fa-user-tie', '2025-12-08 12:32:14'),
(228, 4, 'Xe đưa đón', 'fa-car', '2025-12-08 12:32:14'),
(229, 4, 'Welcome gift', 'fa-gift', '2025-12-08 12:32:14'),
(230, 4, 'Sân thượng riêng', 'fa-tree', '2025-12-08 12:32:14'),
(231, 1, 'Wifi tốc độ cao', 'fa-wifi', '2025-12-08 12:34:03'),
(232, 1, 'TV màn hình phẳng 55\"', 'fa-tv', '2025-12-08 12:34:03'),
(233, 1, 'Điều hòa không khí', 'fa-snowflake', '2025-12-08 12:34:03'),
(234, 1, 'Minibar', 'fa-glass-martini', '2025-12-08 12:34:03'),
(235, 1, 'Ban công riêng', 'fa-door-open', '2025-12-08 12:34:03'),
(236, 1, 'Két an toàn', 'fa-lock', '2025-12-08 12:34:03'),
(237, 1, 'Bàn làm việc', 'fa-desktop', '2025-12-08 12:34:03'),
(238, 1, 'Phòng tắm riêng', 'fa-bath', '2025-12-08 12:34:03'),
(247, 3, 'Wifi cao cấp', 'fa-wifi', '2025-12-08 12:37:31'),
(248, 3, 'TV 65 inch', 'fa-tv', '2025-12-08 12:37:31'),
(249, 3, 'Hệ thống âm thanh', 'fa-volume-up', '2025-12-08 12:37:31'),
(250, 3, 'Minibar premium', 'fa-glass-martini', '2025-12-08 12:37:31'),
(251, 3, 'Bồn tắm Jacuzzi', 'fa-hot-tub', '2025-12-08 12:37:31'),
(252, 3, 'Vòi sen thác nước', 'fa-shower', '2025-12-08 12:37:31'),
(253, 3, 'Bàn làm việc executive', 'fa-briefcase', '2025-12-08 12:37:31'),
(254, 3, 'Máy pha cà phê', 'fa-coffee', '2025-12-08 12:37:31'),
(255, 3, 'Butler 24/7', 'fa-concierge-bell', '2025-12-08 12:37:31'),
(264, 2, 'Wifi miễn phí', 'fa-wifi', '2025-12-08 12:44:38'),
(265, 2, 'TV x2', 'fa-tv', '2025-12-08 12:44:38'),
(266, 2, 'Điều hòa', 'fa-snowflake', '2025-12-08 12:44:38'),
(267, 2, 'Minibar gia đình', 'fa-glass-martini', '2025-12-08 12:44:38'),
(268, 2, 'Khu vực sinh hoạt', 'fa-couch', '2025-12-08 12:44:38'),
(269, 2, 'Góc chơi trẻ em', 'fa-child', '2025-12-08 12:44:38'),
(270, 2, 'Tủ lạnh mini', 'fa-cube', '2025-12-08 12:44:38'),
(271, 2, 'Két an toàn lớn', 'fa-lock', '2025-12-08 12:44:38'),
(272, 5, 'Wifi tốc độ cao', 'fa-wifi', '2025-12-08 12:47:36'),
(273, 5, 'WOw', 'fa-star', '2025-12-08 12:47:36'),
(274, 5, 'Tivi 45 inch', 'fa-tv', '2025-12-08 12:47:36');

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `room_images`
--

CREATE TABLE `room_images` (
  `id` int(11) NOT NULL,
  `room_id` int(11) NOT NULL,
  `image_url` varchar(255) NOT NULL,
  `image_title` varchar(150) DEFAULT NULL,
  `display_order` int(11) DEFAULT 0,
  `is_primary` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Đang đổ dữ liệu cho bảng `room_images`
--

INSERT INTO `room_images` (`id`, `room_id`, `image_url`, `image_title`, `display_order`, `is_primary`, `created_at`) VALUES
(99, 4, 'uploads/rooms/1765197134_main.jpg', 'Phòng khách sang trọng', 0, 0, '2025-12-08 12:32:14'),
(100, 4, 'uploads/rooms/1765197134_work.jpg', 'Khu làm việc thoải mái', 1, 0, '2025-12-08 12:32:14'),
(101, 4, 'uploads/rooms/1765197134_bed.jpg', 'Giường King ', 2, 0, '2025-12-08 12:32:14'),
(102, 4, 'uploads/rooms/1765197134_bed.jpg', 'Giường King ', 3, 0, '2025-12-08 12:32:14'),
(103, 4, 'uploads/rooms/1765197134_eat.jpg', 'Bàn ăn', 4, 0, '2025-12-08 12:32:14'),
(104, 1, 'uploads/rooms/1765197243_bed.jpg', 'Không gian phòng', 0, 0, '2025-12-08 12:34:03'),
(105, 1, 'uploads/rooms/1765197243_toilet.jpg', 'Khu vực nhà vệ sinh', 1, 0, '2025-12-08 12:34:03'),
(108, 3, 'uploads/rooms/1765197451_1.jpg', '1', 0, 0, '2025-12-08 12:37:31'),
(109, 3, 'uploads/rooms/1765197451_2.jpg', '2', 1, 0, '2025-12-08 12:37:31'),
(110, 3, 'uploads/rooms/1765197451_3.jpg', '3', 2, 0, '2025-12-08 12:37:31'),
(111, 3, 'uploads/rooms/1765197451_4.jpg', '4', 3, 0, '2025-12-08 12:37:31'),
(112, 3, 'uploads/rooms/1765197451_5.jpg', '5', 4, 0, '2025-12-08 12:37:31'),
(115, 2, 'uploads/rooms/1765197878_main2.jpg', '1', 0, 0, '2025-12-08 12:44:38'),
(116, 2, 'uploads/rooms/1765197878_toi.jpg', '2', 1, 0, '2025-12-08 12:44:38'),
(117, 5, 'https://placehold.co/1200x800/f4e4d4/888?text=Presidential+Suite+Main', 'Suite Tổng thống', 0, 0, '2025-12-08 12:47:36');

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `room_numbers`
--

CREATE TABLE `room_numbers` (
  `id` int(11) NOT NULL,
  `room_id` int(11) NOT NULL,
  `room_number` varchar(10) NOT NULL,
  `floor` int(11) DEFAULT 1,
  `status` enum('available','occupied','maintenance','dirty','cleaning') DEFAULT 'available',
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Đang đổ dữ liệu cho bảng `room_numbers`
--

INSERT INTO `room_numbers` (`id`, `room_id`, `room_number`, `floor`, `status`, `notes`, `created_at`, `updated_at`) VALUES
(1, 1, '201', 2, 'dirty', NULL, '2025-11-22 13:10:19', '2025-12-09 14:39:15'),
(2, 1, '202', 2, 'maintenance', NULL, '2025-11-22 13:10:19', '2025-11-28 15:02:43'),
(3, 1, '203', 2, 'available', NULL, '2025-11-22 13:10:19', '2025-11-29 07:57:19'),
(4, 1, '301', 3, 'maintenance', NULL, '2025-11-22 13:10:19', '2025-11-28 15:57:38'),
(5, 1, '302', 3, 'maintenance', NULL, '2025-11-22 13:10:19', '2025-11-28 15:03:44'),
(6, 1, '303', 3, 'maintenance', NULL, '2025-11-22 13:10:19', '2025-11-28 15:57:41'),
(7, 1, '401', 4, 'available', NULL, '2025-11-22 13:10:19', '2025-11-22 13:10:19'),
(8, 1, '402', 4, 'available', NULL, '2025-11-22 13:10:19', '2025-11-22 13:10:19'),
(9, 1, '403', 4, 'available', NULL, '2025-11-22 13:10:19', '2025-11-22 13:10:19'),
(10, 1, '501', 5, 'dirty', NULL, '2025-11-22 13:10:19', '2025-11-29 07:58:16'),
(11, 2, '204', 2, 'maintenance', NULL, '2025-11-22 13:10:19', '2025-11-28 14:37:40'),
(12, 2, '205', 2, 'maintenance', NULL, '2025-11-22 13:10:19', '2025-11-28 14:37:43'),
(13, 2, '304', 3, 'dirty', NULL, '2025-11-22 13:10:19', '2025-11-29 07:56:06'),
(14, 2, '305', 3, 'maintenance', NULL, '2025-11-22 13:10:19', '2025-11-29 07:56:55'),
(15, 2, '404', 4, 'dirty', NULL, '2025-11-22 13:10:19', '2025-11-29 07:58:12'),
(16, 2, '405', 4, 'available', NULL, '2025-11-22 13:10:19', '2025-11-29 07:56:46'),
(17, 2, '504', 5, 'dirty', NULL, '2025-11-22 13:10:19', '2025-11-28 15:01:32'),
(18, 2, '505', 5, 'maintenance', NULL, '2025-11-22 13:10:19', '2025-11-29 07:56:52'),
(19, 3, '601', 6, 'available', NULL, '2025-11-22 13:10:19', '2025-11-22 13:10:19'),
(20, 3, '602', 6, 'maintenance', NULL, '2025-11-22 13:10:19', '2025-11-28 15:57:55'),
(21, 3, '701', 7, 'maintenance', NULL, '2025-11-22 13:10:19', '2025-11-28 15:58:00'),
(22, 3, '702', 7, 'maintenance', NULL, '2025-11-22 13:10:19', '2025-11-28 15:58:03'),
(23, 3, '801', 8, 'maintenance', NULL, '2025-11-22 13:10:19', '2025-11-28 15:58:05'),
(24, 4, '901', 9, 'available', NULL, '2025-11-22 13:10:19', '2025-12-09 14:39:07'),
(25, 4, '902', 9, 'dirty', '', '2025-11-22 13:10:19', '2025-12-09 12:34:26'),
(27, 5, '1002', 10, 'occupied', '', '2025-11-29 08:01:38', '2025-12-09 14:02:20');

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `room_service_requests`
--

CREATE TABLE `room_service_requests` (
  `id` int(11) NOT NULL,
  `booking_id` int(11) NOT NULL,
  `service_type` enum('housekeeping','food_delivery','beverages','maintenance','laundry','extra_amenities','wake_up_call','other') NOT NULL,
  `priority` enum('normal','urgent','emergency') DEFAULT 'normal',
  `description` text NOT NULL,
  `preferred_time` time DEFAULT NULL,
  `request_status` enum('pending','in_progress','completed','cancelled') DEFAULT 'pending',
  `staff_notes` text DEFAULT NULL,
  `completed_at` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Đang đổ dữ liệu cho bảng `room_service_requests`
--

INSERT INTO `room_service_requests` (`id`, `booking_id`, `service_type`, `priority`, `description`, `preferred_time`, `request_status`, `staff_notes`, `completed_at`, `created_at`, `updated_at`) VALUES
(2, 17, 'maintenance', 'urgent', 'Găitj đồ', '14:46:00', 'pending', '', NULL, '2025-11-28 23:46:44', '2025-11-28 23:47:04'),
(3, 19, 'housekeeping', 'urgent', 'Dọn phòng đi', '18:56:00', 'in_progress', '', NULL, '2025-11-29 14:53:55', '2025-11-29 14:58:27'),
(4, 18, 'maintenance', 'urgent', 'DỌN PHÒNG DIIIIDĐ', '16:56:00', 'in_progress', '', NULL, '2025-11-29 14:54:19', '2025-11-29 14:58:22');

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `site_settings`
--

CREATE TABLE `site_settings` (
  `id` int(11) NOT NULL,
  `setting_key` varchar(100) NOT NULL,
  `setting_value` text DEFAULT NULL,
  `setting_type` enum('text','number','boolean','json') DEFAULT 'text',
  `description` varchar(255) DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Đang đổ dữ liệu cho bảng `site_settings`
--

INSERT INTO `site_settings` (`id`, `setting_key`, `setting_value`, `setting_type`, `description`, `updated_at`) VALUES
(1, 'site_name', 'Vinpearl Resort & Spa Cần Giờ', 'text', 'Tên website', '2025-11-22 13:10:19'),
(2, 'site_email', 'contact@vinpearl-cangio.com', 'text', 'Email liên hệ chính', '2025-11-22 13:10:19'),
(3, 'site_phone', '1900 xxxx', 'text', 'Số điện thoại hotline', '2025-11-22 13:10:19'),
(4, 'address', 'Khu du lịch sinh thái Cần Giờ, TP. Hồ Chí Minh', 'text', 'Địa chỉ resort', '2025-11-22 13:10:19'),
(5, 'tax_rate', '0', 'number', 'Thuế VAT (8%)', '2025-11-28 15:22:54'),
(6, 'service_fee_rate', '0', 'number', 'Phí dịch vụ (5%)', '2025-11-28 15:36:34'),
(7, 'checkin_time', '14:00', 'text', 'Giờ check-in', '2025-11-22 13:10:19'),
(8, 'checkout_time', '12:00', 'text', 'Giờ check-out', '2025-11-22 13:10:19'),
(9, 'cancellation_hours', '48', 'number', 'Số giờ được hủy miễn phí trước check-in', '2025-11-22 13:10:19'),
(10, 'facebook_url', 'https://facebook.com/vinpearl', 'text', 'Facebook Page URL', '2025-11-22 13:10:19'),
(11, 'instagram_url', 'https://instagram.com/vinpearl', 'text', 'Instagram URL', '2025-11-22 13:10:19'),
(12, 'youtube_url', 'https://youtube.com/vinpearl', 'text', 'YouTube Channel URL', '2025-11-22 13:10:19');

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `standard_addons`
--

CREATE TABLE `standard_addons` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `price` decimal(10,2) NOT NULL,
  `price_type` enum('fixed','per_person','per_night','per_person_per_night','per_booking') DEFAULT 'fixed',
  `icon` varchar(50) DEFAULT 'fa-plus',
  `is_active` tinyint(1) DEFAULT 1,
  `display_order` int(11) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Đang đổ dữ liệu cho bảng `standard_addons`
--

INSERT INTO `standard_addons` (`id`, `name`, `description`, `price`, `price_type`, `icon`, `is_active`, `display_order`, `created_at`, `updated_at`) VALUES
(1, 'Bữa sáng Buffet', 'Buffet sáng quốc tế tại nhà hàng chính', 350000.00, 'per_person_per_night', 'fa-utensils', 1, 1, '2025-11-22 13:10:19', '2025-11-22 13:36:37'),
(2, 'Đưa đón sân bay', 'Dịch vụ đưa đón sân bay Tân Sơn Nhất', 800000.00, 'fixed', 'fa-shuttle-van', 1, 2, '2025-11-22 13:10:19', '2025-11-22 13:10:19'),
(3, 'Massage 60 phút', 'Massage thư giãn toàn thân 60 phút', 650000.00, 'per_person', 'fa-spa', 1, 3, '2025-11-22 13:10:19', '2025-11-22 13:10:19'),
(4, 'Tour tham quan', 'Tour khám phá Cần Giờ (rừng ngập mặn)', 450000.00, 'per_person', 'fa-binoculars', 1, 4, '2025-11-22 13:10:19', '2025-11-22 13:10:19'),
(5, 'Thuê xe máy', 'Thuê xe máy tự lái theo ngày', 200000.00, 'per_booking', 'fa-motorcycle', 1, 5, '2025-11-22 13:10:19', '2025-11-22 13:10:19'),
(7, 'Hoa tươi trong phòng', 'Bó hoa tươi trang trí phòng', 300000.00, 'fixed', 'fa-flower', 1, 7, '2025-11-22 13:10:19', '2025-11-22 13:10:19'),
(8, 'Rượu vang cao cấp', 'Chai rượu vang nhập khẩu', 1200000.00, 'fixed', 'fa-wine-bottle', 1, 8, '2025-11-22 13:10:19', '2025-11-22 13:10:19'),
(10, 'Special', NULL, 22222.00, 'per_person', 'fa-plus', 1, 0, '2025-11-29 08:01:50', '2025-11-29 08:01:50');

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `upsell_offers`
--

CREATE TABLE `upsell_offers` (
  `id` int(11) NOT NULL,
  `offer_id` varchar(50) NOT NULL,
  `title` varchar(150) NOT NULL,
  `description` text DEFAULT NULL,
  `price` decimal(10,2) NOT NULL,
  `icon` varchar(50) DEFAULT 'fa-star',
  `condition_type` enum('couple_weekend','family','weekday','custom') DEFAULT 'custom',
  `condition_data` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`condition_data`)),
  `priority` int(11) DEFAULT 0,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Đang đổ dữ liệu cho bảng `upsell_offers`
--

INSERT INTO `upsell_offers` (`id`, `offer_id`, `title`, `description`, `price`, `icon`, `condition_type`, `condition_data`, `priority`, `is_active`, `created_at`, `updated_at`) VALUES
(1, 'couple_weekend_spa', 'Gói Lãng mạn Cuối tuần', 'Nâng cấp trải nghiệm với: Massage đôi 90 phút + Bữa tối lãng mạn trên bãi biển + Rượu vang cao cấp + Trang trí phòng cánh hoa hồng', 2500000.00, 'fa-heart', 'couple_weekend', '{\"adults\": 2, \"children\": 0, \"days\": [5, 6, 7]}', 100, 1, '2025-11-22 13:10:19', '2025-11-22 13:10:19'),
(2, 'family_fun', 'Gói Gia đình Vui vẻ', 'Giải trí cho cả nhà: Vé công viên nước VinWonders + Buffet BBQ + Voucher spa cho 2 người lớn + Quà tặng cho trẻ em', 3200000.00, 'fa-users', 'family', '{\"has_children\": true}', 90, 1, '2025-11-22 13:10:19', '2025-11-22 13:10:19'),
(3, 'weekday_relax', 'Ưu đãi Ngày thường', 'Thư giãn giữa tuần: Massage 60 phút + Bữa sáng buffet cả kỳ nghỉ + Late check-out miễn phí', 1800000.00, 'fa-bed', 'weekday', '{\"days\": [1, 2, 3, 4]}', 80, 1, '2025-11-22 13:10:19', '2025-11-22 13:10:19'),
(4, 'longstay_discount', 'Ưu đãi Lưu trú dài', 'Đặc quyền cho kỳ nghỉ từ 3 đêm: Giảm 15% tổng hóa đơn + Nâng hạng phòng miễn phí (tùy tình trạng) + Welcome drink', 0.00, 'fa-calendar-alt', 'custom', '{\"min_nights\": 3}', 70, 1, '2025-11-22 13:10:19', '2025-11-22 13:10:19');

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `email` varchar(150) NOT NULL,
  `username` varchar(50) DEFAULT NULL,
  `password_hash` varchar(255) NOT NULL,
  `full_name` varchar(150) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `address` varchar(200) DEFAULT NULL,
  `role` enum('admin','staff','customer') DEFAULT 'customer',
  `ranking` varchar(50) DEFAULT 'Silver',
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `reset_token` varchar(255) DEFAULT NULL,
  `reset_token_expiry` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Đang đổ dữ liệu cho bảng `users`
--

INSERT INTO `users` (`id`, `email`, `username`, `password_hash`, `full_name`, `phone`, `address`, `role`, `ranking`, `is_active`, `created_at`, `updated_at`, `reset_token`, `reset_token_expiry`) VALUES
(1, 'admin@vinpearl.com', 'admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Admin Vinpearl', '', NULL, 'admin', 'Standard', 1, '2025-11-22 13:10:19', '2025-12-08 11:03:40', NULL, NULL),
(2, 'staff@vinpearl.com', 'staff', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '', '', NULL, 'staff', 'Standard', 1, '2025-11-22 13:10:19', '2025-11-29 08:02:07', NULL, NULL),
(3, 'b.vinh2005@gmail.com', 'vbui25', '$2y$10$41ZAe1HUxr4jLMOsla3CIeyZPKdF3xvTB0rJsRXqTf0UsZZpn2BoW', 'Bùi Quang Vinh', '09427113367', '', 'customer', 'Diamond', 1, '2025-11-22 15:16:26', '2025-12-08 15:42:33', NULL, NULL),
(4, 'b.vinh2005@gmal.com', 'Thư', '$2y$10$5QOAhKd4ycsJg.ZhOlBJEek1D5w1BpmIrIqnfabvD7aWMXUmNn4V6', '', '0942333', NULL, 'customer', 'Silver', 1, '2025-12-08 11:01:54', '2025-12-08 11:01:54', NULL, NULL),
(10, 'buixuanhien1949@gmail.com', 'test1', '$2y$10$aeLeFVF3VhBYYXaYOFKbwuPBxCzMFxsVHImQAYiAbO8Mcwy2PpDgO', '', '09427113367', NULL, 'customer', 'Standard', 1, '2025-12-08 16:34:21', '2025-12-08 16:34:21', NULL, NULL);

-- --------------------------------------------------------

--
-- Cấu trúc đóng vai cho view `v_room_statistics`
-- (See below for the actual view)
--
CREATE TABLE `v_room_statistics` (
`id` int(11)
,`room_type_name` varchar(100)
,`price_per_night` decimal(10,2)
,`total_physical_rooms` bigint(21)
,`available_rooms` bigint(21)
,`occupied_rooms` bigint(21)
,`maintenance_rooms` bigint(21)
,`avg_rating` decimal(14,4)
,`review_count` bigint(21)
);

-- --------------------------------------------------------

--
-- Cấu trúc đóng vai cho view `v_today_bookings`
-- (See below for the actual view)
--
CREATE TABLE `v_today_bookings` (
`id` int(11)
,`booking_reference` varchar(20)
,`user_id` int(11)
,`room_id` int(11)
,`room_number_id` int(11)
,`guest_name` varchar(150)
,`guest_email` varchar(150)
,`guest_phone` varchar(20)
,`guest_country` varchar(100)
,`checkin_date` date
,`checkout_date` date
,`num_nights` int(11)
,`num_adults` int(11)
,`num_children` int(11)
,`base_price` decimal(10,2)
,`addons_total` decimal(10,2)
,`tax_amount` decimal(10,2)
,`service_fee` decimal(10,2)
,`total_price` decimal(10,2)
,`payment_status` enum('pending','paid','refunded','cancelled')
,`payment_method` varchar(50)
,`pref_theme` varchar(100)
,`pref_temperature` varchar(50)
,`special_requests` text
,`booking_status` enum('pending','confirmed','checked_in','checked_out','cancelled','no_show')
,`cancellation_reason` text
,`booking_date` timestamp
,`confirmed_at` timestamp
,`checkin_at` timestamp
,`checkout_at` timestamp
,`cancelled_at` timestamp
,`created_at` timestamp
,`updated_at` timestamp
,`early_checkin` tinyint(1)
,`late_checkout` tinyint(1)
,`early_checkin_fee` decimal(10,2)
,`late_checkout_fee` decimal(10,2)
,`room_type_name` varchar(100)
,`room_number` varchar(10)
,`user_name` varchar(150)
);

-- --------------------------------------------------------

--
-- Cấu trúc cho view `v_room_statistics`
--
DROP TABLE IF EXISTS `v_room_statistics`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_room_statistics`  AS SELECT `r`.`id` AS `id`, `r`.`room_type_name` AS `room_type_name`, `r`.`price_per_night` AS `price_per_night`, count(distinct `rn`.`id`) AS `total_physical_rooms`, count(distinct case when `rn`.`status` = 'available' then `rn`.`id` end) AS `available_rooms`, count(distinct case when `rn`.`status` = 'occupied' then `rn`.`id` end) AS `occupied_rooms`, count(distinct case when `rn`.`status` = 'maintenance' then `rn`.`id` end) AS `maintenance_rooms`, coalesce(avg(`rev`.`rating`),0) AS `avg_rating`, count(distinct `rev`.`id`) AS `review_count` FROM ((`rooms` `r` left join `room_numbers` `rn` on(`r`.`id` = `rn`.`room_id`)) left join `reviews` `rev` on(`r`.`id` = `rev`.`room_id` and `rev`.`is_approved` = 1)) WHERE `r`.`is_active` = 1 GROUP BY `r`.`id` ;

-- --------------------------------------------------------

--
-- Cấu trúc cho view `v_today_bookings`
--
DROP TABLE IF EXISTS `v_today_bookings`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_today_bookings`  AS SELECT `b`.`id` AS `id`, `b`.`booking_reference` AS `booking_reference`, `b`.`user_id` AS `user_id`, `b`.`room_id` AS `room_id`, `b`.`room_number_id` AS `room_number_id`, `b`.`guest_name` AS `guest_name`, `b`.`guest_email` AS `guest_email`, `b`.`guest_phone` AS `guest_phone`, `b`.`guest_country` AS `guest_country`, `b`.`checkin_date` AS `checkin_date`, `b`.`checkout_date` AS `checkout_date`, `b`.`num_nights` AS `num_nights`, `b`.`num_adults` AS `num_adults`, `b`.`num_children` AS `num_children`, `b`.`base_price` AS `base_price`, `b`.`addons_total` AS `addons_total`, `b`.`tax_amount` AS `tax_amount`, `b`.`service_fee` AS `service_fee`, `b`.`total_price` AS `total_price`, `b`.`payment_status` AS `payment_status`, `b`.`payment_method` AS `payment_method`, `b`.`pref_theme` AS `pref_theme`, `b`.`pref_temperature` AS `pref_temperature`, `b`.`special_requests` AS `special_requests`, `b`.`booking_status` AS `booking_status`, `b`.`cancellation_reason` AS `cancellation_reason`, `b`.`booking_date` AS `booking_date`, `b`.`confirmed_at` AS `confirmed_at`, `b`.`checkin_at` AS `checkin_at`, `b`.`checkout_at` AS `checkout_at`, `b`.`cancelled_at` AS `cancelled_at`, `b`.`created_at` AS `created_at`, `b`.`updated_at` AS `updated_at`, `b`.`early_checkin` AS `early_checkin`, `b`.`late_checkout` AS `late_checkout`, `b`.`early_checkin_fee` AS `early_checkin_fee`, `b`.`late_checkout_fee` AS `late_checkout_fee`, `r`.`room_type_name` AS `room_type_name`, `rn`.`room_number` AS `room_number`, `u`.`full_name` AS `user_name` FROM (((`bookings` `b` join `rooms` `r` on(`b`.`room_id` = `r`.`id`)) left join `room_numbers` `rn` on(`b`.`room_number_id` = `rn`.`id`)) left join `users` `u` on(`b`.`user_id` = `u`.`id`)) WHERE cast(`b`.`booking_date` as date) = curdate() ORDER BY `b`.`created_at` DESC ;

--
-- Chỉ mục cho các bảng đã đổ
--

--
-- Chỉ mục cho bảng `activity_logs`
--
ALTER TABLE `activity_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user_id` (`user_id`),
  ADD KEY `idx_action` (`action`),
  ADD KEY `idx_created` (`created_at`);

--
-- Chỉ mục cho bảng `bookings`
--
ALTER TABLE `bookings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `booking_reference` (`booking_reference`),
  ADD KEY `idx_booking_ref` (`booking_reference`),
  ADD KEY `idx_user_id` (`user_id`),
  ADD KEY `idx_room_id` (`room_id`),
  ADD KEY `idx_dates` (`checkin_date`,`checkout_date`),
  ADD KEY `idx_status` (`booking_status`),
  ADD KEY `idx_payment_status` (`payment_status`),
  ADD KEY `idx_dates_status` (`checkin_date`,`checkout_date`,`booking_status`),
  ADD KEY `idx_room_number_dates` (`room_number_id`,`checkin_date`,`checkout_date`);

--
-- Chỉ mục cho bảng `booking_addons`
--
ALTER TABLE `booking_addons`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_booking_id` (`booking_id`);

--
-- Chỉ mục cho bảng `comments`
--
ALTER TABLE `comments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `news_id` (`news_id`);

--
-- Chỉ mục cho bảng `contact_messages`
--
ALTER TABLE `contact_messages`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_created` (`created_at`);

--
-- Chỉ mục cho bảng `news`
--
ALTER TABLE `news`
  ADD PRIMARY KEY (`id`);

--
-- Chỉ mục cho bảng `promotions`
--
ALTER TABLE `promotions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `code` (`code`),
  ADD KEY `idx_code` (`code`),
  ADD KEY `idx_dates` (`valid_from`,`valid_to`),
  ADD KEY `idx_active` (`is_active`);

--
-- Chỉ mục cho bảng `reviews`
--
ALTER TABLE `reviews`
  ADD PRIMARY KEY (`id`),
  ADD KEY `booking_id` (`booking_id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `idx_room_id` (`room_id`),
  ADD KEY `idx_approved` (`is_approved`),
  ADD KEY `idx_rating` (`rating`);

--
-- Chỉ mục cho bảng `rooms`
--
ALTER TABLE `rooms`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_active` (`is_active`),
  ADD KEY `idx_price` (`price_per_night`);

--
-- Chỉ mục cho bảng `room_availability`
--
ALTER TABLE `room_availability`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_room_date` (`room_id`,`date`),
  ADD KEY `idx_date` (`date`);

--
-- Chỉ mục cho bảng `room_features`
--
ALTER TABLE `room_features`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_room_id` (`room_id`);

--
-- Chỉ mục cho bảng `room_images`
--
ALTER TABLE `room_images`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_room_id` (`room_id`),
  ADD KEY `idx_primary` (`is_primary`);

--
-- Chỉ mục cho bảng `room_numbers`
--
ALTER TABLE `room_numbers`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `room_number` (`room_number`),
  ADD KEY `idx_room_id` (`room_id`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_room_number` (`room_number`);

--
-- Chỉ mục cho bảng `room_service_requests`
--
ALTER TABLE `room_service_requests`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_booking_id` (`booking_id`),
  ADD KEY `idx_status` (`request_status`),
  ADD KEY `idx_created_at` (`created_at`);

--
-- Chỉ mục cho bảng `site_settings`
--
ALTER TABLE `site_settings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `setting_key` (`setting_key`),
  ADD KEY `idx_key` (`setting_key`);

--
-- Chỉ mục cho bảng `standard_addons`
--
ALTER TABLE `standard_addons`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_active` (`is_active`);

--
-- Chỉ mục cho bảng `upsell_offers`
--
ALTER TABLE `upsell_offers`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `offer_id` (`offer_id`),
  ADD KEY `idx_active` (`is_active`),
  ADD KEY `idx_priority` (`priority`);

--
-- Chỉ mục cho bảng `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD UNIQUE KEY `username` (`username`),
  ADD KEY `idx_email` (`email`),
  ADD KEY `idx_role` (`role`);

--
-- AUTO_INCREMENT cho các bảng đã đổ
--

--
-- AUTO_INCREMENT cho bảng `activity_logs`
--
ALTER TABLE `activity_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT cho bảng `bookings`
--
ALTER TABLE `bookings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=129;

--
-- AUTO_INCREMENT cho bảng `booking_addons`
--
ALTER TABLE `booking_addons`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=139;

--
-- AUTO_INCREMENT cho bảng `comments`
--
ALTER TABLE `comments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT cho bảng `contact_messages`
--
ALTER TABLE `contact_messages`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT cho bảng `news`
--
ALTER TABLE `news`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT cho bảng `promotions`
--
ALTER TABLE `promotions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT cho bảng `reviews`
--
ALTER TABLE `reviews`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT cho bảng `rooms`
--
ALTER TABLE `rooms`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT cho bảng `room_availability`
--
ALTER TABLE `room_availability`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT cho bảng `room_features`
--
ALTER TABLE `room_features`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=275;

--
-- AUTO_INCREMENT cho bảng `room_images`
--
ALTER TABLE `room_images`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=118;

--
-- AUTO_INCREMENT cho bảng `room_numbers`
--
ALTER TABLE `room_numbers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=29;

--
-- AUTO_INCREMENT cho bảng `room_service_requests`
--
ALTER TABLE `room_service_requests`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT cho bảng `site_settings`
--
ALTER TABLE `site_settings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT cho bảng `standard_addons`
--
ALTER TABLE `standard_addons`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT cho bảng `upsell_offers`
--
ALTER TABLE `upsell_offers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT cho bảng `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- Các ràng buộc cho các bảng đã đổ
--

--
-- Các ràng buộc cho bảng `activity_logs`
--
ALTER TABLE `activity_logs`
  ADD CONSTRAINT `activity_logs_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Các ràng buộc cho bảng `bookings`
--
ALTER TABLE `bookings`
  ADD CONSTRAINT `bookings_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `bookings_ibfk_2` FOREIGN KEY (`room_id`) REFERENCES `rooms` (`id`),
  ADD CONSTRAINT `bookings_ibfk_3` FOREIGN KEY (`room_number_id`) REFERENCES `room_numbers` (`id`) ON DELETE SET NULL;

--
-- Các ràng buộc cho bảng `booking_addons`
--
ALTER TABLE `booking_addons`
  ADD CONSTRAINT `booking_addons_ibfk_1` FOREIGN KEY (`booking_id`) REFERENCES `bookings` (`id`) ON DELETE CASCADE;

--
-- Các ràng buộc cho bảng `comments`
--
ALTER TABLE `comments`
  ADD CONSTRAINT `comments_ibfk_1` FOREIGN KEY (`news_id`) REFERENCES `news` (`id`) ON DELETE CASCADE;

--
-- Các ràng buộc cho bảng `reviews`
--
ALTER TABLE `reviews`
  ADD CONSTRAINT `reviews_ibfk_1` FOREIGN KEY (`booking_id`) REFERENCES `bookings` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `reviews_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `reviews_ibfk_3` FOREIGN KEY (`room_id`) REFERENCES `rooms` (`id`) ON DELETE CASCADE;

--
-- Các ràng buộc cho bảng `room_availability`
--
ALTER TABLE `room_availability`
  ADD CONSTRAINT `room_availability_ibfk_1` FOREIGN KEY (`room_id`) REFERENCES `rooms` (`id`) ON DELETE CASCADE;

--
-- Các ràng buộc cho bảng `room_features`
--
ALTER TABLE `room_features`
  ADD CONSTRAINT `room_features_ibfk_1` FOREIGN KEY (`room_id`) REFERENCES `rooms` (`id`) ON DELETE CASCADE;

--
-- Các ràng buộc cho bảng `room_images`
--
ALTER TABLE `room_images`
  ADD CONSTRAINT `room_images_ibfk_1` FOREIGN KEY (`room_id`) REFERENCES `rooms` (`id`) ON DELETE CASCADE;

--
-- Các ràng buộc cho bảng `room_numbers`
--
ALTER TABLE `room_numbers`
  ADD CONSTRAINT `room_numbers_ibfk_1` FOREIGN KEY (`room_id`) REFERENCES `rooms` (`id`) ON DELETE CASCADE;

--
-- Các ràng buộc cho bảng `room_service_requests`
--
ALTER TABLE `room_service_requests`
  ADD CONSTRAINT `room_service_requests_ibfk_1` FOREIGN KEY (`booking_id`) REFERENCES `bookings` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
