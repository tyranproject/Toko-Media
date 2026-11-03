-- phpMyAdmin SQL Dump
-- version 5.2.3
-- https://www.phpmyadmin.net/
--
-- Host: localhost
-- Waktu pembuatan: 01 Nov 2026 pada 04.19
-- Versi server: 10.3.39-MariaDB
-- Versi PHP: 8.3.36

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Basis data: `vipse877_market`
--
CREATE DATABASE IF NOT EXISTS `vipse877_market` DEFAULT CHARACTER SET latin1 COLLATE latin1_swedish_ci;
USE `vipse877_market`;

-- --------------------------------------------------------

--
-- Struktur dari tabel `categories`
--

CREATE TABLE `categories` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `nama` varchar(100) NOT NULL,
  `slug` varchar(120) NOT NULL,
  `deskripsi` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Struktur dari tabel `disputes`
--

CREATE TABLE `disputes` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `order_id` bigint(20) UNSIGNED NOT NULL,
  `buyer_id` bigint(20) UNSIGNED NOT NULL,
  `seller_id` bigint(20) UNSIGNED NOT NULL,
  `reason` enum('not_as_described','damaged','incomplete','other') NOT NULL,
  `description` text NOT NULL,
  `status` enum('open','investigating','resolved_refund','resolved_release','resolved_partial','rejected') NOT NULL DEFAULT 'open',
  `resolution_note` text DEFAULT NULL,
  `refund_amount` decimal(19,2) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `resolved_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Struktur dari tabel `download_tokens`
--

CREATE TABLE `download_tokens` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `order_id` bigint(20) UNSIGNED NOT NULL,
  `product_id` bigint(20) UNSIGNED NOT NULL,
  `buyer_id` bigint(20) UNSIGNED NOT NULL,
  `token_hash` char(64) NOT NULL,
  `expires_at` datetime NOT NULL,
  `max_uses` int(11) NOT NULL DEFAULT 3,
  `uses` int(11) NOT NULL DEFAULT 0,
  `status` enum('active','revoked') NOT NULL DEFAULT 'active',
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data untuk tabel `download_tokens`
--

INSERT INTO `download_tokens` (`id`, `order_id`, `product_id`, `buyer_id`, `token_hash`, `expires_at`, `max_uses`, `uses`, `status`, `created_at`) VALUES
(1, 3, 1, 1, 'd7dde0dd3e2c67210fd787cfd3624ede70dd2bbd1683839e565d29ffdd78a7fa', '2026-10-30 10:32:27', 3, 0, 'revoked', '2026-10-29 10:32:27'),
(2, 3, 1, 1, 'beacc3261014dbc87254f7028e1113bc03c79898d3797cf88806e84458ca115e', '2026-10-30 10:33:15', 3, 1, 'active', '2026-10-29 10:33:16'),
(3, 9, 4, 1, '721cf41837187e59f35cab78d4bf897d4b282caf8016d57f407f0e13f43f50c0', '2026-11-01 09:51:51', 5, 0, 'active', '2026-10-31 09:51:51');

-- --------------------------------------------------------

--
-- Struktur dari tabel `escrow_holds`
--

CREATE TABLE `escrow_holds` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `order_id` bigint(20) UNSIGNED NOT NULL,
  `buyer_id` bigint(20) UNSIGNED NOT NULL,
  `seller_id` bigint(20) UNSIGNED NOT NULL,
  `amount_held` decimal(19,2) NOT NULL,
  `platform_fee` decimal(19,2) NOT NULL,
  `cod_fee` decimal(19,2) NOT NULL DEFAULT 0.00,
  `releasable_amount` decimal(19,2) NOT NULL,
  `status` enum('held','released','refunded','partial') NOT NULL DEFAULT 'held',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `released_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data untuk tabel `escrow_holds`
--

INSERT INTO `escrow_holds` (`id`, `order_id`, `buyer_id`, `seller_id`, `amount_held`, `platform_fee`, `cod_fee`, `releasable_amount`, `status`, `created_at`, `released_at`) VALUES
(1, 6, 1, 2, 100.00, 5.00, 5000.00, 0.00, 'released', '2026-10-30 20:53:09', '2026-10-30 20:54:16'),
(2, 7, 1, 2, 200.00, 10.00, 5000.00, 0.00, 'released', '2026-10-30 21:03:09', '2026-10-30 21:04:45'),
(3, 8, 1, 2, 100.00, 5.00, 5000.00, 0.00, 'released', '2026-10-30 21:13:01', '2026-10-30 22:40:35');

-- --------------------------------------------------------

--
-- Struktur dari tabel `login_attempts`
--

CREATE TABLE `login_attempts` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `email` varchar(190) NOT NULL,
  `ip` varchar(45) DEFAULT NULL,
  `success` tinyint(1) NOT NULL DEFAULT 0,
  `attempted_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data untuk tabel `login_attempts`
--

INSERT INTO `login_attempts` (`id`, `email`, `ip`, `success`, `attempted_at`) VALUES
(1, 'argih.giansyah@gmail.com', '36.50.143.192', 0, '2026-10-29 04:29:18'),
(2, 'mouseevolve@gmail.com', '36.50.143.192', 1, '2026-10-29 04:29:38'),
(3, 'anggriawan465@gmail.com', '36.50.143.192', 1, '2026-10-29 07:50:10'),
(4, 'argih.giansyah@gmail.com', '36.50.143.192', 0, '2026-10-29 09:27:47'),
(5, 'mouseevolve@gmail.com', '36.50.143.192', 1, '2026-10-29 09:28:01'),
(6, 'anggriawan465@gmail.com', '36.50.143.192', 1, '2026-10-29 09:37:45'),
(7, 'mouseevolve@gmail.com', '36.50.143.192', 1, '2026-10-29 10:30:00'),
(8, 'anggriawan465@gmail.com', '36.50.143.192', 1, '2026-10-29 10:36:21'),
(9, 'mouseevolve@gmail.com', '36.50.143.192', 1, '2026-10-29 10:38:19'),
(10, 'mouseevolve@gmail.com', '36.50.143.192', 1, '2026-10-29 10:41:15'),
(11, 'anggriawan465@gmail.com', '36.50.143.192', 1, '2026-10-29 10:41:26'),
(12, 'mouseevolve@gmail.com', '36.50.143.192', 1, '2026-10-29 12:43:06'),
(13, 'anggriawan465@gmail.com', '36.50.143.192', 1, '2026-10-29 15:50:08'),
(14, 'anggriawan465@gmail.com', '36.50.143.192', 1, '2026-10-29 19:49:56'),
(15, 'mouseevolve@gmail.com', '36.50.143.192', 1, '2026-10-29 19:50:36'),
(16, 'anggriawan465@gmail.com', '36.50.143.192', 1, '2026-10-29 19:53:18'),
(17, 'anggriawan465@gmail.com', '36.50.143.192', 1, '2026-10-29 23:04:22'),
(18, 'mouseevolve@gmail.com', '36.50.143.192', 1, '2026-10-29 23:06:06'),
(19, 'anggriawan465@gmail.com', '36.50.143.192', 1, '2026-10-30 10:12:12'),
(20, 'anggriawan465@gmail.com', '36.50.143.192', 1, '2026-10-30 10:13:20'),
(21, 'anggriawan465@gmail.com', '36.50.143.192', 1, '2026-10-30 19:31:39'),
(22, 'mouseevolve@gmail.com', '36.50.143.192', 1, '2026-10-30 19:54:54'),
(23, 'mouseevolve@gmail.com', '36.50.143.192', 1, '2026-10-30 20:28:52'),
(24, 'anggriawan465@gmail.com', '36.50.143.192', 1, '2026-10-30 20:44:58'),
(25, 'mouseevolve@gmail.com', '36.50.143.192', 1, '2026-10-30 20:47:10'),
(26, 'anggriawan465@gmail.com', '36.50.143.192', 1, '2026-10-30 20:49:08'),
(27, 'mouseevolve@gmail.com', '36.50.143.192', 1, '2026-10-30 20:51:54'),
(28, 'anggriawan465@gmail.com', '36.50.143.192', 1, '2026-10-30 20:54:38'),
(29, 'mouseevolve@gmail.com', '36.50.143.192', 1, '2026-10-30 21:01:53'),
(30, 'anggriawan465@gmail.com', '36.50.143.192', 1, '2026-10-30 21:03:46'),
(31, 'mouseevolve@gmail.com', '36.50.143.192', 1, '2026-10-30 21:04:14'),
(32, 'anggriawan465@gmail.com', '36.50.143.192', 1, '2026-10-30 21:05:00'),
(33, 'mouseevolve@gmail.com', '36.50.143.192', 1, '2026-10-30 21:11:22'),
(34, 'mouseevolve@gmail.com', '36.50.143.192', 1, '2026-10-31 09:48:59'),
(35, 'anggriawan465@gmail.com', '36.50.143.192', 1, '2026-10-31 09:52:42');

-- --------------------------------------------------------

--
-- Struktur dari tabel `orders`
--

CREATE TABLE `orders` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `order_code` varchar(32) NOT NULL,
  `buyer_id` bigint(20) UNSIGNED NOT NULL,
  `seller_id` bigint(20) UNSIGNED NOT NULL,
  `payment_method` enum('wallet','qris','cash') NOT NULL,
  `fulfillment` enum('pickup','cod_delivery','digital') NOT NULL,
  `status` enum('awaiting_payment','held','awaiting_fulfillment','cash_received','fee_hold','disputed','completed','refunded','cancelled') NOT NULL DEFAULT 'awaiting_payment',
  `subtotal` decimal(19,2) NOT NULL,
  `platform_fee` decimal(19,2) NOT NULL DEFAULT 0.00,
  `cod_fee` decimal(19,2) NOT NULL DEFAULT 0.00,
  `total` decimal(19,2) NOT NULL,
  `doku_transaction_id` varchar(100) DEFAULT NULL,
  `paid_at` datetime DEFAULT NULL,
  `confirmed_at` datetime DEFAULT NULL,
  `released_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `fees_locked` tinyint(1) NOT NULL DEFAULT 0,
  `cash_received_at` datetime DEFAULT NULL
) ;

--
-- Dumping data untuk tabel `orders`
--

INSERT INTO `orders` (`id`, `order_code`, `buyer_id`, `seller_id`, `payment_method`, `fulfillment`, `status`, `subtotal`, `platform_fee`, `cod_fee`, `total`, `doku_transaction_id`, `paid_at`, `confirmed_at`, `released_at`, `created_at`, `updated_at`, `fees_locked`, `cash_received_at`) VALUES
(1, 'ORD-20261029-1AAC15', 1, 2, 'qris', 'digital', 'awaiting_payment', 10000.00, 0.00, 0.00, 10000.00, NULL, NULL, NULL, NULL, '2026-10-29 09:29:10', '2026-10-29 09:29:10', 0, NULL),
(2, 'ORD-20261029-B00D09', 1, 2, 'qris', 'digital', 'awaiting_payment', 10000.00, 0.00, 0.00, 10000.00, NULL, NULL, NULL, NULL, '2026-10-29 09:32:25', '2026-10-29 09:32:25', 0, NULL),
(3, 'ORD-20261029-9AEADE', 1, 2, 'wallet', 'digital', 'completed', 10000.00, 0.00, 0.00, 10000.00, NULL, NULL, NULL, '2026-10-29 10:32:27', '2026-10-29 10:32:27', '2026-10-29 10:32:27', 0, NULL),
(4, 'ORD-20261029-B7EC5C', 1, 2, 'cash', 'cod_delivery', 'awaiting_fulfillment', 1000.00, 0.00, 0.00, 1000.00, NULL, NULL, NULL, NULL, '2026-10-29 10:39:52', '2026-10-29 10:39:52', 0, NULL),
(5, 'ORD-20261030-F47A16', 1, 2, 'cash', 'cod_delivery', 'completed', 100.00, 5.00, 5000.00, 100.00, NULL, NULL, NULL, NULL, '2026-10-30 20:44:20', '2026-10-30 20:47:29', 1, '2026-10-30 20:46:22'),
(6, 'ORD-20261030-0B0C7C', 1, 2, 'wallet', 'cod_delivery', 'completed', 100.00, 5.00, 5000.00, 100.00, NULL, '2026-10-30 20:53:09', '2026-10-30 20:54:16', '2026-10-30 20:54:16', '2026-10-30 20:53:09', '2026-10-30 20:54:16', 1, NULL),
(7, 'ORD-20261030-77054B', 1, 2, 'wallet', 'cod_delivery', 'completed', 200.00, 10.00, 5000.00, 200.00, NULL, '2026-10-30 21:03:09', '2026-10-30 21:04:45', '2026-10-30 21:04:45', '2026-10-30 21:03:09', '2026-10-30 21:04:45', 1, NULL),
(8, 'ORD-20261030-0A1DD3', 1, 2, 'wallet', 'cod_delivery', 'completed', 100.00, 5.00, 5000.00, 100.00, NULL, '2026-10-30 21:13:01', '2026-10-30 22:40:35', '2026-10-30 22:40:35', '2026-10-30 21:13:01', '2026-10-30 22:40:35', 1, NULL),
(9, 'ORD-20261031-8F815F', 1, 2, 'wallet', 'digital', 'completed', 10000.00, 500.00, 0.00, 10000.00, NULL, NULL, NULL, '2026-10-31 09:51:51', '2026-10-31 09:51:51', '2026-10-31 09:51:51', 1, NULL);

-- --------------------------------------------------------

--
-- Struktur dari tabel `order_items`
--

CREATE TABLE `order_items` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `order_id` bigint(20) UNSIGNED NOT NULL,
  `product_id` bigint(20) UNSIGNED NOT NULL,
  `judul_snapshot` varchar(200) NOT NULL,
  `harga_snapshot` decimal(19,2) NOT NULL,
  `qty` int(11) NOT NULL DEFAULT 1,
  `subtotal` decimal(19,2) NOT NULL
) ;

--
-- Dumping data untuk tabel `order_items`
--

INSERT INTO `order_items` (`id`, `order_id`, `product_id`, `judul_snapshot`, `harga_snapshot`, `qty`, `subtotal`) VALUES
(1, 1, 1, '[IDM] Internet Download Manager - Lifetime', 10000.00, 1, 10000.00),
(2, 2, 1, '[IDM] Internet Download Manager - Lifetime', 10000.00, 1, 10000.00),
(3, 3, 1, '[IDM] Internet Download Manager - Lifetime', 10000.00, 1, 10000.00),
(4, 4, 2, 'BAJU ERERQI', 1000.00, 1, 1000.00),
(5, 5, 5, 'BAJU ERERQI VOL. 2', 100.00, 1, 100.00),
(6, 6, 5, 'BAJU ERERQI VOL. 2', 100.00, 1, 100.00),
(7, 7, 5, 'BAJU ERERQI VOL. 2', 100.00, 2, 200.00),
(8, 8, 5, 'BAJU ERERQI VOL. 2', 100.00, 1, 100.00),
(9, 9, 4, 'ACTIVE WINDOWS KEY 10-11', 10000.00, 1, 10000.00);

-- --------------------------------------------------------

--
-- Struktur dari tabel `orphaned_payments`
--

CREATE TABLE `orphaned_payments` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `invoice` varchar(100) DEFAULT NULL,
  `transaction_id` varchar(100) DEFAULT NULL,
  `amount` decimal(19,2) DEFAULT NULL,
  `raw_status` varchar(50) NOT NULL,
  `note` text DEFAULT NULL,
  `received_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Struktur dari tabel `products`
--

CREATE TABLE `products` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `seller_id` bigint(20) UNSIGNED NOT NULL,
  `category_id` bigint(20) UNSIGNED DEFAULT NULL,
  `tipe` enum('digital','fisik') NOT NULL,
  `judul` varchar(200) NOT NULL,
  `deskripsi` text NOT NULL,
  `spesifikasi` text DEFAULT NULL,
  `kelebihan` text DEFAULT NULL,
  `kekurangan` text DEFAULT NULL,
  `alasan_jual` text DEFAULT NULL,
  `harga` decimal(19,2) NOT NULL,
  `stok` int(11) DEFAULT NULL,
  `cod_available` tinyint(1) NOT NULL DEFAULT 0,
  `pickup_available` tinyint(1) NOT NULL DEFAULT 1,
  `drive_file_id` varchar(255) DEFAULT NULL,
  `drive_file_name` varchar(255) DEFAULT NULL,
  `drive_file_size` bigint(20) DEFAULT NULL,
  `status` enum('pending','active','rejected','archived') NOT NULL DEFAULT 'pending',
  `rejection_reason` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `demo_url` varchar(255) DEFAULT NULL
) ;

--
-- Dumping data untuk tabel `products`
--

INSERT INTO `products` (`id`, `seller_id`, `category_id`, `tipe`, `judul`, `deskripsi`, `spesifikasi`, `kelebihan`, `kekurangan`, `alasan_jual`, `harga`, `stok`, `cod_available`, `pickup_available`, `drive_file_id`, `drive_file_name`, `drive_file_size`, `status`, `rejection_reason`, `created_at`, `updated_at`, `demo_url`) VALUES
(1, 2, NULL, 'digital', '[IDM] Internet Download Manager - Lifetime', 'IDM', 'IDM', '-', '-', 'BU', 10000.00, NULL, 0, 1, '305b2428913b71ec53ac33142d228964.bin', '[IDM] Internet Download Manager - Lifetime.pdf.docx', 204788, 'active', NULL, '2026-10-29 09:26:55', '2026-10-29 09:28:33', NULL),
(2, 2, NULL, 'fisik', 'BAJU ERERQI', 'SAMSUL', '-', '-', '-', '-', 1000.00, 100, 1, 1, NULL, NULL, NULL, 'active', NULL, '2026-10-29 10:38:02', '2026-10-29 23:17:22', NULL),
(3, 2, NULL, 'digital', 'ACTIVE MICROSOFT OFFICE - LIFETIME', 'ACTIVE MICROSOFT OFFICE - LIFETIME', 'ACTIVE MICROSOFT OFFICE - LIFETIME', 'ACTIVE MICROSOFT OFFICE - LIFETIME', 'ACTIVE MICROSOFT OFFICE - LIFETIME', 'ACTIVE MICROSOFT OFFICE - LIFETIME', 10000.00, NULL, 0, 1, '6a51c91f871f31fbeab5e76b0b312cd4.bin', 'ACTIVE MICROSOFT OFFICE - LIFETIME.pdf', 4029565, 'active', NULL, '2026-10-30 19:34:58', '2026-10-30 19:55:12', NULL),
(4, 2, NULL, 'digital', 'ACTIVE WINDOWS KEY 10-11', 'ACTIVE WINDOWS KEY 10-11', 'ACTIVE WINDOWS KEY 10-11', NULL, NULL, NULL, 10000.00, NULL, 0, 1, '2ce15841da03491d2c8dde757424e6ab.bin', 'ACTIVE WINDOWS KEY 10-11.pdf', 4067367, 'active', NULL, '2026-10-30 19:44:14', '2026-10-30 19:55:15', NULL),
(5, 2, NULL, 'fisik', 'BAJU ERERQI VOL. 2', 'BAJU ERERQI VOL. 2', '-', '-', NULL, 'Butuh Uang', 500.00, 995, 1, 1, NULL, NULL, NULL, 'pending', NULL, '2026-10-30 19:47:06', '2026-10-31 09:55:51', NULL);

-- --------------------------------------------------------

--
-- Struktur dari tabel `product_images`
--

CREATE TABLE `product_images` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `product_id` bigint(20) UNSIGNED NOT NULL,
  `path` varchar(255) NOT NULL,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data untuk tabel `product_images`
--

INSERT INTO `product_images` (`id`, `product_id`, `path`, `sort_order`, `created_at`) VALUES
(1, 1, 'f476cbc5d27bee6f88db33fc411ec4cf.png', 0, '2026-10-29 09:26:56'),
(2, 2, '5ccd5499bf55fb784bdac71d56f7c87d.png', 0, '2026-10-29 10:38:03'),
(3, 3, 'f866623cbfcaa1ab83e84119ba0bb500.jpg', 0, '2026-10-30 19:34:58'),
(4, 4, '9defdf1ba3ad886f94d736fafad4eaca.jpg', 0, '2026-10-30 19:44:14'),
(5, 5, '3f0f48e17961e7da079e18d9e8f1450b.png', 0, '2026-10-30 19:47:06');

-- --------------------------------------------------------

--
-- Struktur dari tabel `product_reviews`
--

CREATE TABLE `product_reviews` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `product_id` bigint(20) UNSIGNED NOT NULL,
  `buyer_id` bigint(20) UNSIGNED NOT NULL,
  `order_id` bigint(20) UNSIGNED NOT NULL,
  `rating` tinyint(4) NOT NULL,
  `comment` text NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL,
  `hidden` tinyint(1) NOT NULL DEFAULT 0
) ;

-- --------------------------------------------------------

--
-- Struktur dari tabel `reports`
--

CREATE TABLE `reports` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `reporter_id` bigint(20) UNSIGNED NOT NULL,
  `target_type` enum('product','review','seller') NOT NULL,
  `target_id` bigint(20) UNSIGNED NOT NULL,
  `reason` text NOT NULL,
  `status` enum('pending','reviewed','dismissed','actioned') NOT NULL DEFAULT 'pending',
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Struktur dari tabel `seller_debts`
--

CREATE TABLE `seller_debts` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `seller_id` bigint(20) UNSIGNED NOT NULL,
  `order_id` bigint(20) UNSIGNED DEFAULT NULL,
  `amount` decimal(19,2) NOT NULL,
  `amount_remaining` decimal(19,2) NOT NULL,
  `reason` varchar(255) NOT NULL,
  `status` enum('open','cleared') NOT NULL DEFAULT 'open',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `cleared_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data untuk tabel `seller_debts`
--

INSERT INTO `seller_debts` (`id`, `seller_id`, `order_id`, `amount`, `amount_remaining`, `reason`, `status`, `created_at`, `cleared_at`) VALUES
(1, 2, 6, 4905.00, 4905.00, 'Selisih fee order ORD-20261030-0B0C7C', 'open', '2026-10-30 20:54:16', NULL),
(2, 2, 7, 4810.00, 4810.00, 'Selisih fee order ORD-20261030-77054B', 'open', '2026-10-30 21:04:45', NULL);

-- --------------------------------------------------------

--
-- Struktur dari tabel `sessions`
--

CREATE TABLE `sessions` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `token_hash` char(64) NOT NULL,
  `ip` varchar(45) DEFAULT NULL,
  `user_agent` varchar(255) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `expires_at` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data untuk tabel `sessions`
--

INSERT INTO `sessions` (`id`, `user_id`, `token_hash`, `ip`, `user_agent`, `created_at`, `expires_at`) VALUES
(1, 1, 'd1a3d4cb72a4bdc81003e55459592b4e6d9b33bcd1bb134bb75582a28a3f5bfa', '36.50.143.192', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/156.0.0.0 Safari/537.36', '2026-10-29 04:29:39', '2026-11-28 04:29:39'),
(2, 2, '1f2b1619454159fe434150b05e2942a075818cfdb8c4dba7ea5990e42ea477ff', '36.50.143.192', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/156.0.0.0 Safari/537.36', '2026-10-29 07:50:13', '2026-11-28 07:50:13'),
(3, 1, '3e85db25eae5dc9b2f7d5492a987e70d973b006f3e107c55f08d5db46bb3aac8', '36.50.143.192', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/156.0.0.0 Safari/537.36', '2026-10-29 09:28:01', '2026-11-28 09:28:01'),
(4, 2, 'fb8ecaf670762e33d192cd8651ffea5f40b93dca85298c25dd7586bc88f5a566', '36.50.143.192', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/156.0.0.0 Safari/537.36', '2026-10-29 09:37:46', '2026-11-28 09:37:46'),
(5, 1, 'cbb4dfcfe6ff75074c16bbff3d1fd30e439653d693150cf41f782b023fe803c2', '36.50.143.192', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/156.0.0.0 Safari/537.36', '2026-10-29 10:30:00', '2026-11-28 10:30:00'),
(6, 2, '90f51374397f174a46d5241c32eaa215c488afc32dd5e55f396b36af39971d7e', '36.50.143.192', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/156.0.0.0 Safari/537.36', '2026-10-29 10:36:21', '2026-11-28 10:36:21'),
(7, 1, 'ac66dc1abe023b45c69c5dafd4a2e7d321269b83a92a1fcba8521ae59fb5e57b', '36.50.143.192', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/156.0.0.0 Safari/537.36', '2026-10-29 10:38:20', '2026-11-28 10:38:20'),
(8, 1, 'efb97b6063dcb6c44d8c8f61d3483103172c2062cbb5bce8d10a889ae4d8e5df', '36.50.143.192', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/156.0.0.0 Safari/537.36', '2026-10-29 10:41:16', '2026-11-28 10:41:16'),
(11, 2, '5b356958221fe2b1d8b9ce099510fa394d56c4822dcc71809c36598c1d41475e', '36.50.143.192', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/156.0.0.0 Safari/537.36', '2026-10-29 15:50:08', '2026-11-28 15:50:08'),
(16, 1, 'ece5dbd0171500efe41fe19cf562101597620b17adaee4df149ca3244c31bbaa', '36.50.143.192', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/156.0.0.0 Safari/537.36', '2026-10-29 23:06:06', '2026-11-28 23:06:06'),
(17, 2, '3c3e5faf547105a5492b2d48677ab795583412a48e309b7d8ae51d9367857278', '36.50.143.192', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/156.0.0.0 Safari/537.36', '2026-10-30 10:12:12', '2026-11-29 10:12:12'),
(18, 2, '7819efbd566b310c8d8ed4074ab6aeaecefee9626feeda3f76c8f2bfcd1507fe', '36.50.143.192', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/156.0.0.0 Safari/537.36', '2026-10-30 10:13:20', '2026-11-29 10:13:20'),
(33, 2, '231a4a98e07d0d50e7245c491e5aed8fc286eaf79dfa275cf1a2095e664d940c', '36.50.143.192', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/156.0.0.0 Safari/537.36', '2026-10-31 09:52:42', '2026-11-30 09:52:42');

-- --------------------------------------------------------

--
-- Struktur dari tabel `settings`
--

CREATE TABLE `settings` (
  `key` varchar(100) NOT NULL,
  `value` text NOT NULL,
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data untuk tabel `settings`
--

INSERT INTO `settings` (`key`, `value`, `updated_at`) VALUES
('auto_release_days', '3', '2026-10-28 14:26:57'),
('cod_fee_flat', '5000', '2026-10-28 14:26:57'),
('download_expiry_hours', '24', '2026-10-28 14:26:57'),
('download_max_uses', '5', '2026-10-29 23:10:42'),
('max_digital_mb', '500', '2026-10-28 14:26:57'),
('max_image_mb', '10', '2026-10-29 23:10:43'),
('min_withdrawal', '11000', '2026-10-29 23:10:42'),
('platform_fee_percent', '5', '2026-10-29 23:10:40'),
('site_title', 'TOKO MEDIA', '2026-10-29 23:10:43');

-- --------------------------------------------------------

--
-- Struktur dari tabel `topups`
--

CREATE TABLE `topups` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `amount` decimal(19,2) NOT NULL,
  `doku_transaction_id` varchar(100) DEFAULT NULL,
  `doku_invoice` varchar(100) DEFAULT NULL,
  `status` enum('pending','paid','failed','expired','refunded') NOT NULL DEFAULT 'pending',
  `qr_payload` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `paid_at` datetime DEFAULT NULL,
  `expires_at` datetime DEFAULT NULL
) ;

--
-- Dumping data untuk tabel `topups`
--

INSERT INTO `topups` (`id`, `user_id`, `amount`, `doku_transaction_id`, `doku_invoice`, `status`, `qr_payload`, `created_at`, `paid_at`, `expires_at`) VALUES
(1, 2, 10000.00, NULL, NULL, 'failed', NULL, '2026-10-29 09:37:59', NULL, '2026-10-30 09:37:59'),
(2, 2, 10000.00, 'b5b8a145af1c44299b358a4b03700be5', 'TOPUP-20261029-2', 'paid', 'https://staging.doku.com/checkout-link-v2/b5b8a145af1c44299b358a4b03700be520265229095212833', '2026-10-29 09:52:12', '2026-10-29 10:17:26', '2026-10-30 09:52:12'),
(3, 2, 10000.00, 'ba38268fc9714a3db2fa45ba6a5e4cca', 'TOPUP-20261029-3', 'paid', 'https://staging.doku.com/checkout-link-v2/ba38268fc9714a3db2fa45ba6a5e4cca20262729102728115', '2026-10-29 10:27:27', '2026-10-29 10:29:17', '2026-10-30 10:27:27'),
(4, 1, 30000.00, '21e875e9170b48aea3e5282bb8dc37aa', 'TOPUP-20261029-4', 'paid', 'https://staging.doku.com/checkout-link-v2/21e875e9170b48aea3e5282bb8dc37aa20263029103025517', '2026-10-29 10:30:23', '2026-10-29 10:31:48', '2026-10-30 10:30:23'),
(5, 2, 50000.00, 'eb03e2ff3ce74cefb1929ec3b3e79300', 'TOPUP-20261029-5', 'failed', 'https://staging.doku.com/checkout-link-v2/eb03e2ff3ce74cefb1929ec3b3e7930020264429104408631', '2026-10-29 10:44:08', NULL, '2026-10-30 10:44:08'),
(6, 2, 500000.00, '1d9c507e81d04884832842448f1ad808', 'TOPUP-20261029-6', 'expired', 'https://staging.doku.com/checkout-link-v2/1d9c507e81d04884832842448f1ad80820265429105429060', '2026-10-29 10:54:28', NULL, '2026-10-30 10:54:28'),
(7, 2, 10000.00, '097480f9b22040ed9ddebdd4696f19d2', 'TOPUP-20261029-7', 'failed', 'https://staging.doku.com/checkout-link-v2/097480f9b22040ed9ddebdd4696f19d220265929105957842', '2026-10-29 10:59:57', NULL, '2026-10-30 10:59:57'),
(8, 2, 100000.00, NULL, NULL, 'failed', NULL, '2026-10-30 10:12:37', NULL, '2026-10-30 11:12:37'),
(9, 2, 200000.00, NULL, NULL, 'failed', NULL, '2026-10-30 10:13:37', NULL, '2026-10-30 11:13:37'),
(10, 2, 100000.00, NULL, NULL, 'failed', NULL, '2026-10-30 10:24:35', NULL, '2026-10-30 11:24:35');

-- --------------------------------------------------------

--
-- Struktur dari tabel `users`
--

CREATE TABLE `users` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `email` varchar(190) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `nama` varchar(100) NOT NULL,
  `telepon` varchar(20) DEFAULT NULL,
  `alamat` text DEFAULT NULL,
  `role` enum('admin','member') NOT NULL DEFAULT 'member',
  `status` enum('active','suspended') NOT NULL DEFAULT 'active',
  `wallet_balance` decimal(19,2) NOT NULL DEFAULT 0.00,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `whatsapp` varchar(20) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data untuk tabel `users`
--

INSERT INTO `users` (`id`, `email`, `password_hash`, `nama`, `telepon`, `alamat`, `role`, `status`, `wallet_balance`, `created_at`, `updated_at`, `whatsapp`) VALUES
(1, 'mouseevolve@gmail.com', '$2y$10$HQ6z1.7ltu.gYaHEaD3T0uYcuwycX/nFqo19EzzN4K2j2m8s/6koW', 'Argih Giansyah', NULL, NULL, 'admin', 'active', 9600.00, '2026-10-29 04:28:39', '2026-10-31 09:51:51', NULL),
(2, 'anggriawan465@gmail.com', '$2y$10$ktGoDwuShdSW61aA8SJsROyrrEuzh2IHstCXb8AD8QQSUZ8aMQ3iC', 'Oki Anggriawan', '089612868749', 'Jl. Perintis 2.', 'member', 'active', 33495.00, '2026-10-29 07:49:51', '2026-10-31 09:51:51', NULL);

-- --------------------------------------------------------

--
-- Struktur dari tabel `user_blocks`
--

CREATE TABLE `user_blocks` (
  `blocker_id` bigint(20) UNSIGNED NOT NULL,
  `blocked_id` bigint(20) UNSIGNED NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Struktur dari tabel `wallet_transactions`
--

CREATE TABLE `wallet_transactions` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `type` enum('topup','payment','refund','fee','withdrawal','release','adjustment') NOT NULL,
  `amount` decimal(19,2) NOT NULL,
  `balance_after` decimal(19,2) NOT NULL,
  `reference_type` varchar(50) DEFAULT NULL,
  `reference_id` bigint(20) UNSIGNED DEFAULT NULL,
  `note` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data untuk tabel `wallet_transactions`
--

INSERT INTO `wallet_transactions` (`id`, `user_id`, `type`, `amount`, `balance_after`, `reference_type`, `reference_id`, `note`, `created_at`) VALUES
(1, 2, 'topup', 10000.00, 10000.00, 'topup', 2, 'Top-up QRIS (DOKU).', '2026-10-29 10:17:26'),
(2, 2, 'topup', 10000.00, 20000.00, 'topup', 3, 'Top-up QRIS (DOKU).', '2026-10-29 10:29:17'),
(3, 1, 'topup', 30000.00, 30000.00, 'topup', 4, 'Top-up QRIS (DOKU).', '2026-10-29 10:31:48'),
(4, 1, 'payment', -10000.00, 20000.00, 'order', 3, 'Pembayaran order (dibuat di checkout).', '2026-10-29 10:32:27'),
(5, 2, 'release', 9000.00, 29000.00, 'order', 3, 'Penjualan digital ORD-20261029-9AEADE (fee platform Rp 1.000).', '2026-10-29 10:32:27'),
(6, 2, 'withdrawal', -20000.00, 9000.00, 'withdrawal', 1, 'Permintaan penarikan.', '2026-10-29 11:41:41'),
(7, 2, 'adjustment', 20000.00, 29000.00, 'withdrawal', 1, 'Penarikan ditolak admin: Belom Bisa Cair. Dana dikembalikan.', '2026-10-29 19:51:01'),
(8, 2, 'fee', -5005.00, 23995.00, 'order', 5, 'Fee platform + COD order tunai ORD-20261030-F47A16.', '2026-10-30 20:47:29'),
(9, 1, 'payment', -100.00, 19900.00, 'order', 6, 'Pembayaran order (dibuat di checkout).', '2026-10-30 20:53:07'),
(10, 2, 'release', 0.00, 23995.00, 'order', 6, 'Pelepasan escrow ORD-20261030-0B0C7C.', '2026-10-30 20:54:16'),
(11, 1, 'payment', -200.00, 19700.00, 'order', 7, 'Pembayaran order (dibuat di checkout).', '2026-10-30 21:03:09'),
(12, 2, 'release', 0.00, 23995.00, 'order', 7, 'Pelepasan escrow ORD-20261030-77054B.', '2026-10-30 21:04:45'),
(13, 1, 'payment', -100.00, 19600.00, 'order', 8, 'Pembayaran order (dibuat di checkout).', '2026-10-30 21:13:00'),
(14, 2, 'release', 0.00, 23995.00, 'order', 8, 'Pelepasan escrow ORD-20261030-0A1DD3.', '2026-10-30 22:40:35'),
(15, 1, 'payment', -10000.00, 9600.00, 'order', 9, 'Pembayaran order (dibuat di checkout).', '2026-10-31 09:51:51'),
(16, 2, 'release', 9500.00, 33495.00, 'order', 9, 'Penjualan digital ORD-20261031-8F815F (fee platform Rp 500).', '2026-10-31 09:51:51');

-- --------------------------------------------------------

--
-- Struktur dari tabel `withdrawals`
--

CREATE TABLE `withdrawals` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `seller_id` bigint(20) UNSIGNED NOT NULL,
  `amount` decimal(19,2) NOT NULL,
  `method` enum('ewallet','digital','bank') NOT NULL,
  `destination` varchar(255) NOT NULL,
  `status` enum('pending','approved','rejected','completed') NOT NULL DEFAULT 'pending',
  `admin_note` text DEFAULT NULL,
  `transfer_ref` varchar(100) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `decided_at` datetime DEFAULT NULL,
  `completed_at` datetime DEFAULT NULL,
  `provider` varchar(100) DEFAULT NULL,
  `account_name` varchar(100) DEFAULT NULL
) ;

--
-- Dumping data untuk tabel `withdrawals`
--

INSERT INTO `withdrawals` (`id`, `seller_id`, `amount`, `method`, `destination`, `status`, `admin_note`, `transfer_ref`, `created_at`, `decided_at`, `completed_at`, `provider`, `account_name`) VALUES
(1, 2, 20000.00, 'ewallet', '089529690996', 'rejected', 'Belom Bisa Cair', NULL, '2026-10-29 11:41:41', '2026-10-29 19:51:01', NULL, NULL, NULL);

--
-- Indeks untuk tabel yang dibuang
--

--
-- Indeks untuk tabel `categories`
--
ALTER TABLE `categories`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_categories_slug` (`slug`);

--
-- Indeks untuk tabel `disputes`
--
ALTER TABLE `disputes`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_disputes_order` (`order_id`),
  ADD KEY `idx_disputes_seller_status` (`seller_id`,`status`),
  ADD KEY `idx_disputes_status_created` (`status`,`created_at`),
  ADD KEY `fk_disputes_buyer` (`buyer_id`);

--
-- Indeks untuk tabel `download_tokens`
--
ALTER TABLE `download_tokens`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_tokens_hash` (`token_hash`),
  ADD KEY `idx_tokens_buyer_status` (`buyer_id`,`status`),
  ADD KEY `idx_tokens_expires` (`expires_at`),
  ADD KEY `fk_tokens_order` (`order_id`),
  ADD KEY `fk_tokens_product` (`product_id`);

--
-- Indeks untuk tabel `escrow_holds`
--
ALTER TABLE `escrow_holds`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_escrow_order` (`order_id`),
  ADD KEY `idx_escrow_seller_status` (`seller_id`,`status`),
  ADD KEY `idx_escrow_status_created` (`status`,`created_at`),
  ADD KEY `fk_escrow_buyer` (`buyer_id`);

--
-- Indeks untuk tabel `login_attempts`
--
ALTER TABLE `login_attempts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_attempts_email_time` (`email`,`attempted_at`),
  ADD KEY `idx_attempts_ip_time` (`ip`,`attempted_at`);

--
-- Indeks untuk tabel `orders`
--
ALTER TABLE `orders`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_orders_code` (`order_code`),
  ADD UNIQUE KEY `uq_orders_doku` (`doku_transaction_id`),
  ADD KEY `idx_orders_buyer_status` (`buyer_id`,`status`),
  ADD KEY `idx_orders_seller_status` (`seller_id`,`status`),
  ADD KEY `idx_orders_status_created` (`status`,`created_at`);

--
-- Indeks untuk tabel `order_items`
--
ALTER TABLE `order_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_items_order` (`order_id`),
  ADD KEY `idx_items_product` (`product_id`);

--
-- Indeks untuk tabel `orphaned_payments`
--
ALTER TABLE `orphaned_payments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_orphans_invoice` (`invoice`),
  ADD KEY `idx_orphans_received` (`received_at`);

--
-- Indeks untuk tabel `products`
--
ALTER TABLE `products`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_products_seller_status` (`seller_id`,`status`),
  ADD KEY `idx_products_category_status` (`category_id`,`status`),
  ADD KEY `idx_products_status_created` (`status`,`created_at`);

--
-- Indeks untuk tabel `product_images`
--
ALTER TABLE `product_images`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_images_product_order` (`product_id`,`sort_order`);

--
-- Indeks untuk tabel `product_reviews`
--
ALTER TABLE `product_reviews`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_review_buyer_product` (`buyer_id`,`product_id`),
  ADD KEY `idx_reviews_product` (`product_id`),
  ADD KEY `fk_reviews_order` (`order_id`);

--
-- Indeks untuk tabel `reports`
--
ALTER TABLE `reports`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_reports_status_created` (`status`,`created_at`),
  ADD KEY `fk_reports_reporter` (`reporter_id`);

--
-- Indeks untuk tabel `seller_debts`
--
ALTER TABLE `seller_debts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_debts_seller_status` (`seller_id`,`status`),
  ADD KEY `fk_debts_order` (`order_id`);

--
-- Indeks untuk tabel `sessions`
--
ALTER TABLE `sessions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_sessions_user` (`user_id`);

--
-- Indeks untuk tabel `settings`
--
ALTER TABLE `settings`
  ADD PRIMARY KEY (`key`);

--
-- Indeks untuk tabel `topups`
--
ALTER TABLE `topups`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_topups_doku` (`doku_transaction_id`),
  ADD KEY `idx_topups_user_status` (`user_id`,`status`);

--
-- Indeks untuk tabel `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_users_email` (`email`),
  ADD KEY `idx_users_role_status` (`role`,`status`);

--
-- Indeks untuk tabel `user_blocks`
--
ALTER TABLE `user_blocks`
  ADD PRIMARY KEY (`blocker_id`,`blocked_id`),
  ADD KEY `fk_blocks_blocked` (`blocked_id`);

--
-- Indeks untuk tabel `wallet_transactions`
--
ALTER TABLE `wallet_transactions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_wallet_user_created` (`user_id`,`created_at`);

--
-- Indeks untuk tabel `withdrawals`
--
ALTER TABLE `withdrawals`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_withdrawals_seller_status` (`seller_id`,`status`);

--
-- AUTO_INCREMENT untuk tabel yang dibuang
--

--
-- AUTO_INCREMENT untuk tabel `categories`
--
ALTER TABLE `categories`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT untuk tabel `disputes`
--
ALTER TABLE `disputes`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT untuk tabel `download_tokens`
--
ALTER TABLE `download_tokens`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT untuk tabel `escrow_holds`
--
ALTER TABLE `escrow_holds`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT untuk tabel `login_attempts`
--
ALTER TABLE `login_attempts`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=36;

--
-- AUTO_INCREMENT untuk tabel `orders`
--
ALTER TABLE `orders`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT untuk tabel `order_items`
--
ALTER TABLE `order_items`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT untuk tabel `orphaned_payments`
--
ALTER TABLE `orphaned_payments`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT untuk tabel `products`
--
ALTER TABLE `products`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT untuk tabel `product_images`
--
ALTER TABLE `product_images`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT untuk tabel `product_reviews`
--
ALTER TABLE `product_reviews`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT untuk tabel `reports`
--
ALTER TABLE `reports`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT untuk tabel `seller_debts`
--
ALTER TABLE `seller_debts`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT untuk tabel `sessions`
--
ALTER TABLE `sessions`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=34;

--
-- AUTO_INCREMENT untuk tabel `topups`
--
ALTER TABLE `topups`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT untuk tabel `users`
--
ALTER TABLE `users`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT untuk tabel `wallet_transactions`
--
ALTER TABLE `wallet_transactions`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- AUTO_INCREMENT untuk tabel `withdrawals`
--
ALTER TABLE `withdrawals`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- Ketidakleluasaan untuk tabel pelimpahan (Dumped Tables)
--

--
-- Ketidakleluasaan untuk tabel `disputes`
--
ALTER TABLE `disputes`
  ADD CONSTRAINT `fk_disputes_buyer` FOREIGN KEY (`buyer_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `fk_disputes_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_disputes_seller` FOREIGN KEY (`seller_id`) REFERENCES `users` (`id`);

--
-- Ketidakleluasaan untuk tabel `download_tokens`
--
ALTER TABLE `download_tokens`
  ADD CONSTRAINT `fk_tokens_buyer` FOREIGN KEY (`buyer_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `fk_tokens_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_tokens_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`);

--
-- Ketidakleluasaan untuk tabel `escrow_holds`
--
ALTER TABLE `escrow_holds`
  ADD CONSTRAINT `fk_escrow_buyer` FOREIGN KEY (`buyer_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `fk_escrow_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_escrow_seller` FOREIGN KEY (`seller_id`) REFERENCES `users` (`id`);

--
-- Ketidakleluasaan untuk tabel `orders`
--
ALTER TABLE `orders`
  ADD CONSTRAINT `fk_orders_buyer` FOREIGN KEY (`buyer_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `fk_orders_seller` FOREIGN KEY (`seller_id`) REFERENCES `users` (`id`);

--
-- Ketidakleluasaan untuk tabel `order_items`
--
ALTER TABLE `order_items`
  ADD CONSTRAINT `fk_items_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_items_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`);

--
-- Ketidakleluasaan untuk tabel `products`
--
ALTER TABLE `products`
  ADD CONSTRAINT `fk_products_category` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_products_seller` FOREIGN KEY (`seller_id`) REFERENCES `users` (`id`);

--
-- Ketidakleluasaan untuk tabel `product_images`
--
ALTER TABLE `product_images`
  ADD CONSTRAINT `fk_images_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE;

--
-- Ketidakleluasaan untuk tabel `product_reviews`
--
ALTER TABLE `product_reviews`
  ADD CONSTRAINT `fk_reviews_buyer` FOREIGN KEY (`buyer_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `fk_reviews_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_reviews_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`);

--
-- Ketidakleluasaan untuk tabel `reports`
--
ALTER TABLE `reports`
  ADD CONSTRAINT `fk_reports_reporter` FOREIGN KEY (`reporter_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Ketidakleluasaan untuk tabel `seller_debts`
--
ALTER TABLE `seller_debts`
  ADD CONSTRAINT `fk_debts_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_debts_seller` FOREIGN KEY (`seller_id`) REFERENCES `users` (`id`);

--
-- Ketidakleluasaan untuk tabel `sessions`
--
ALTER TABLE `sessions`
  ADD CONSTRAINT `fk_sessions_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Ketidakleluasaan untuk tabel `topups`
--
ALTER TABLE `topups`
  ADD CONSTRAINT `fk_topups_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);

--
-- Ketidakleluasaan untuk tabel `user_blocks`
--
ALTER TABLE `user_blocks`
  ADD CONSTRAINT `fk_blocks_blocked` FOREIGN KEY (`blocked_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_blocks_blocker` FOREIGN KEY (`blocker_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Ketidakleluasaan untuk tabel `wallet_transactions`
--
ALTER TABLE `wallet_transactions`
  ADD CONSTRAINT `fk_wallet_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);

--
-- Ketidakleluasaan untuk tabel `withdrawals`
--
ALTER TABLE `withdrawals`
  ADD CONSTRAINT `fk_withdrawals_seller` FOREIGN KEY (`seller_id`) REFERENCES `users` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
