-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Oct 09, 2026 at 05:21 PM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `ypay`
--

-- --------------------------------------------------------

--
-- Table structure for table `admin_users`
--

CREATE TABLE `admin_users` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `username` varchar(80) NOT NULL,
  `name` varchar(180) DEFAULT NULL,
  `email` varchar(180) DEFAULT NULL,
  `role` varchar(32) NOT NULL DEFAULT 'CHECKER',
  `password_hash` varchar(255) NOT NULL,
  `active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `admin_users`
--

INSERT INTO `admin_users` (`id`, `username`, `name`, `email`, `role`, `password_hash`, `active`, `created_at`, `updated_at`) VALUES
(1, 'admin', 'Administrator', 'admin@yeedipay.local', 'SUPERADMIN', '$2y$10$PtdLaF4bMirTfryVhAxzW.3qO5pkIH0pJADGP5h8JQrzWeuiKsWeO', 1, '2026-09-29 03:12:14', '2026-09-29 03:12:14');

-- --------------------------------------------------------

--
-- Table structure for table `audit_logs`
--

CREATE TABLE `audit_logs` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `actor` varchar(80) NOT NULL,
  `action` varchar(120) NOT NULL,
  `detail` text NOT NULL,
  `ip_address` varchar(64) NOT NULL DEFAULT '',
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `audit_logs`
--

INSERT INTO `audit_logs` (`id`, `actor`, `action`, `detail`, `ip_address`, `created_at`) VALUES
(1, 'admin', 'ADMIN_LOGIN', '', '::1', '2026-09-29 03:27:11'),
(2, 'admin', 'ADMIN_LOGIN', '', '::1', '2026-09-29 03:41:48'),
(3, 'admin', 'CUSTOMER_ADDED', 'YD89328933234', '::1', '2026-09-29 03:42:46'),
(4, 'admin', 'ADMIN_LOGIN', '', '127.0.0.1', '2026-10-01 14:01:52');

-- --------------------------------------------------------

--
-- Table structure for table `auth_rate_limits`
--

CREATE TABLE `auth_rate_limits` (
  `rate_key` varchar(160) NOT NULL,
  `attempts` int(11) NOT NULL DEFAULT 0,
  `window_started_at` datetime NOT NULL DEFAULT current_timestamp(),
  `blocked_until` datetime DEFAULT NULL,
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `bank_profiles`
--

CREATE TABLE `bank_profiles` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `code` varchar(32) NOT NULL,
  `bank_name` varchar(120) NOT NULL,
  `label` varchar(120) DEFAULT NULL,
  `vpa` varchar(180) NOT NULL,
  `payee_name` varchar(220) NOT NULL,
  `currency` varchar(8) NOT NULL DEFAULT 'INR',
  `qr_payload` text NOT NULL,
  `qr_params_json` text NOT NULL,
  `qr_image_path` varchar(255) DEFAULT NULL,
  `enabled` tinyint(1) NOT NULL DEFAULT 1,
  `rotation_order` int(11) NOT NULL DEFAULT 1,
  `rotation_minutes` int(11) NOT NULL DEFAULT 60,
  `signed_qr` tinyint(1) NOT NULL DEFAULT 0,
  `webhook_secret` varchar(96) NOT NULL,
  `mcc` varchar(16) DEFAULT NULL,
  `provider_name` varchar(120) DEFAULT NULL,
  `provider_base_url` varchar(255) DEFAULT NULL,
  `create_order_path` varchar(255) DEFAULT NULL,
  `status_path` varchar(255) DEFAULT NULL,
  `refund_path` varchar(255) DEFAULT NULL,
  `api_key_encrypted` text DEFAULT NULL,
  `api_secret_encrypted` text DEFAULT NULL,
  `reference_field` varchar(120) NOT NULL DEFAULT 'reference',
  `status_field` varchar(120) NOT NULL DEFAULT 'status',
  `amount_field` varchar(120) NOT NULL DEFAULT 'amount',
  `txn_id_field` varchar(120) NOT NULL DEFAULT 'upi_txn_id',
  `rrn_field` varchar(120) NOT NULL DEFAULT 'rrn',
  `payer_vpa_field` varchar(120) NOT NULL DEFAULT 'payer_vpa',
  `success_values` varchar(200) NOT NULL DEFAULT 'SUCCESS,SUCCESSFUL,PAID,COMPLETED',
  `failed_values` varchar(200) NOT NULL DEFAULT 'FAILED,FAILURE,DECLINED,REVERSED',
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `bank_profiles`
--

INSERT INTO `bank_profiles` (`id`, `code`, `bank_name`, `label`, `vpa`, `payee_name`, `currency`, `qr_payload`, `qr_params_json`, `qr_image_path`, `enabled`, `rotation_order`, `rotation_minutes`, `signed_qr`, `webhook_secret`, `mcc`, `provider_name`, `provider_base_url`, `create_order_path`, `status_path`, `refund_path`, `api_key_encrypted`, `api_secret_encrypted`, `reference_field`, `status_field`, `amount_field`, `txn_id_field`, `rrn_field`, `payer_vpa_field`, `success_values`, `failed_values`, `created_at`) VALUES
(2, 'JKMERCHANT', 'ICICI Bank', 'J K MERCHANT', 'msgreenrisingagrilimited.eazypay@icici', 'J K MERCHANT', 'INR', 'upi://pay?pa=msgreenrisingagrilimited.eazypay@icici&pn=&tr=EZYS9640941904&cu=INR&mc=1520', '{\"pa\":\"msgreenrisingagrilimited.eazypay@icici\",\"pn\":\"\",\"tr\":\"EZYS9640941904\",\"cu\":\"INR\",\"mc\":\"1520\"}', 'storage/bank_qr/jk_merchant.jpg', 1, 1, 60, 1, 'ca4998c4b06258a06dbab2655fbaf602cf8d7d590df55c53', '1520', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'reference', 'status', 'amount', 'upi_txn_id', 'rrn', 'payer_vpa', 'SUCCESS,SUCCESSFUL,PAID,COMPLETED', 'FAILED,FAILURE,DECLINED,REVERSED', '2026-09-29 03:37:35'),
(3, 'KKKENT', 'ICICI Bank', 'K K K ENTERPRISES', 'msgreenrisingagrilimited.eazypay@icici', 'K K K ENTERPRISES', 'INR', 'upi://pay?pa=msgreenrisingagrilimited.eazypay@icici&pn=&tr=EZYS9640943427&cu=INR&mc=1520', '{\"pa\":\"msgreenrisingagrilimited.eazypay@icici\",\"pn\":\"\",\"tr\":\"EZYS9640943427\",\"cu\":\"INR\",\"mc\":\"1520\"}', 'storage/bank_qr/kkk_enterprises.jpg', 1, 2, 60, 1, '48e9871b9b0ed22eabdd11d50b2021b4d3f2f0cae95599a5', '1520', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'reference', 'status', 'amount', 'upi_txn_id', 'rrn', 'payer_vpa', 'SUCCESS,SUCCESSFUL,PAID,COMPLETED', 'FAILED,FAILURE,DECLINED,REVERSED', '2026-09-29 03:37:35');

-- --------------------------------------------------------

--
-- Table structure for table `bank_receiving_accounts`
--

CREATE TABLE `bank_receiving_accounts` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `account_key` varchar(64) NOT NULL,
  `bank_name` varchar(20) NOT NULL,
  `account_label` varchar(100) NOT NULL,
  `account_last4` char(4) DEFAULT NULL,
  `upi_id` varchar(120) DEFAULT NULL,
  `payee_name` varchar(120) DEFAULT NULL,
  `qr_image_url` varchar(500) DEFAULT NULL,
  `sort_order` int(11) NOT NULL DEFAULT 100,
  `active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `bank_receiving_accounts`
--

INSERT INTO `bank_receiving_accounts` (`id`, `account_key`, `bank_name`, `account_label`, `account_last4`, `upi_id`, `payee_name`, `qr_image_url`, `sort_order`, `active`, `created_at`) VALUES
(1, 'JKMERCHANT', 'ICICI', 'J K MERCHANT', NULL, 'msgreenrisingagrilimited.eazypay@icici', 'J K MERCHANT', NULL, 1, 1, '2026-09-29 12:23:23'),
(2, 'KKKENT', 'ICICI', 'K K K ENTERPRISES', NULL, 'msgreenrisingagrilimited.eazypay@icici', 'K K K ENTERPRISES', NULL, 2, 1, '2026-09-29 12:23:23');

-- --------------------------------------------------------

--
-- Table structure for table `bank_sms_devices`
--

CREATE TABLE `bank_sms_devices` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(120) NOT NULL,
  `device_id` varchar(64) NOT NULL,
  `secret_encrypted` text NOT NULL,
  `bank_code` varchar(32) NOT NULL,
  `account_last4` char(4) NOT NULL,
  `active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL,
  `last_seen_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `bank_sms_devices`
--

INSERT INTO `bank_sms_devices` (`id`, `name`, `device_id`, `secret_encrypted`, `bank_code`, `account_last4`, `active`, `created_at`, `updated_at`, `last_seen_at`) VALUES
(1, 'Office parser', 'YP2BD9DACA', 'tYs7U001P4WSBZIL55aobDuxd7rbvJFIlDylPKghPC8hxN/QzWWfPHebnDxb0Dz4wBNJ8NeQWPwwf9AX6m4vps1uivZoiKBcy83FxA==', 'ICICI', '1234', 1, '2026-09-29 03:23:59', '2026-09-29 03:23:59', NULL),
(2, 'Office parser', 'YEEDI-EF427D43', 'A9F2NHThv23Yjx3y4lw4fBsLEeLRQvgW++r05HzvWdPgDIZVCgf99YejrgRLyq3TvkEKdfa8xTq7zD61TXUawYwlvleFVyZQaaFXn/POm5r7z1EDFo+BkTVv0eo=', 'JKMERCHANT', '4270', 1, '2026-09-30 07:57:54', '2026-09-30 07:57:54', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `bank_sms_device_accounts`
--

CREATE TABLE `bank_sms_device_accounts` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `device_row_id` bigint(20) UNSIGNED NOT NULL,
  `bank_code` varchar(32) NOT NULL,
  `account_last4` char(4) NOT NULL,
  `created_at` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `bank_sms_events`
--

CREATE TABLE `bank_sms_events` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `event_id` char(64) NOT NULL,
  `device_id` varchar(64) NOT NULL,
  `receiving_account_id` bigint(20) UNSIGNED DEFAULT NULL,
  `bank_name` varchar(20) NOT NULL,
  `amount` decimal(14,2) NOT NULL,
  `reference_no` varchar(35) NOT NULL,
  `account_last4` char(4) DEFAULT NULL,
  `sender_id` varchar(80) NOT NULL,
  `received_at` datetime NOT NULL,
  `match_state` enum('UNMATCHED','MATCHED','AMBIGUOUS') NOT NULL DEFAULT 'UNMATCHED',
  `matched_payment_id` bigint(20) UNSIGNED DEFAULT NULL,
  `matched_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `bank_sms_match_attempts`
--

CREATE TABLE `bank_sms_match_attempts` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `event_id` bigint(20) UNSIGNED NOT NULL,
  `transaction_id` bigint(20) UNSIGNED DEFAULT NULL,
  `match_result` enum('MATCH','NO_MATCH','AMBIGUOUS','REJECTED') NOT NULL,
  `match_reason` varchar(500) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `bank_sms_nonces`
--

CREATE TABLE `bank_sms_nonces` (
  `nonce` varchar(80) NOT NULL,
  `created_at` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `bank_sms_nonces`
--

INSERT INTO `bank_sms_nonces` (`nonce`, `created_at`) VALUES
('7ad46dc3d433b0c4', '2026-09-29 03:24:55');

-- --------------------------------------------------------

--
-- Table structure for table `billing_settings`
--

CREATE TABLE `billing_settings` (
  `id` tinyint(3) UNSIGNED NOT NULL,
  `legal_name` varchar(220) NOT NULL DEFAULT '',
  `gstin` varchar(20) DEFAULT NULL,
  `registered_state_code` varchar(2) DEFAULT NULL,
  `gst_rate` decimal(8,3) NOT NULL DEFAULT 18.000,
  `gst_summary_email` varchar(180) DEFAULT NULL,
  `accounts_email` varchar(180) DEFAULT NULL,
  `fee_invoice_prefix` varchar(32) NOT NULL DEFAULT 'YDFEE',
  `duplicate_scan_minutes` int(11) NOT NULL DEFAULT 10,
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `billing_settings`
--

INSERT INTO `billing_settings` (`id`, `legal_name`, `gstin`, `registered_state_code`, `gst_rate`, `gst_summary_email`, `accounts_email`, `fee_invoice_prefix`, `duplicate_scan_minutes`, `updated_at`) VALUES
(1, 'YEEDI PAYMENT SOLUTIONS LIMITED', NULL, NULL, 18.000, 'admin@yeedipay.local', 'admin@yeedipay.local', 'YDFEE', 10, '2026-09-28 11:02:55');

-- --------------------------------------------------------

--
-- Table structure for table `customers`
--

CREATE TABLE `customers` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `code` varchar(32) NOT NULL,
  `name` varchar(180) NOT NULL,
  `phone` varchar(24) NOT NULL,
  `email` varchar(180) DEFAULT NULL,
  `active` tinyint(1) NOT NULL DEFAULT 1,
  `per_txn_limit` decimal(14,2) DEFAULT NULL,
  `daily_limit` decimal(14,2) DEFAULT NULL,
  `weekly_limit` decimal(14,2) DEFAULT NULL,
  `monthly_limit` decimal(14,2) DEFAULT NULL,
  `daily_txn_count_limit` int(11) DEFAULT NULL,
  `max_pending_attempts` int(11) NOT NULL DEFAULT 5,
  `service_activated` tinyint(1) NOT NULL DEFAULT 0,
  `service_activated_at` datetime DEFAULT NULL,
  `account_number` varchar(24) DEFAULT NULL,
  `legal_name` varchar(180) DEFAULT NULL,
  `pan` varchar(20) DEFAULT NULL,
  `gst` varchar(32) DEFAULT NULL,
  `plan` varchar(32) DEFAULT 'starter',
  `subscription_ends` datetime DEFAULT NULL,
  `admin_name` varchar(180) DEFAULT NULL,
  `password_hash` varchar(255) DEFAULT NULL,
  `fixed_fee` decimal(14,2) DEFAULT NULL,
  `pct_fee` decimal(8,3) DEFAULT NULL,
  `setup_fee` decimal(14,2) DEFAULT NULL,
  `merchant_qr_fee` decimal(14,2) DEFAULT NULL,
  `min_txn_limit` decimal(14,2) DEFAULT NULL,
  `service_callback_url` varchar(255) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `customers`
--

INSERT INTO `customers` (`id`, `code`, `name`, `phone`, `email`, `active`, `per_txn_limit`, `daily_limit`, `weekly_limit`, `monthly_limit`, `daily_txn_count_limit`, `max_pending_attempts`, `service_activated`, `service_activated_at`, `account_number`, `legal_name`, `pan`, `gst`, `plan`, `subscription_ends`, `admin_name`, `password_hash`, `fixed_fee`, `pct_fee`, `setup_fee`, `merchant_qr_fee`, `min_txn_limit`, `service_callback_url`, `created_at`) VALUES
(1, 'CUST0001', 'Demo Customer', '9876543210', NULL, 1, NULL, NULL, NULL, NULL, NULL, 5, 1, '2026-09-29 03:23:59', 'YD000105283020', NULL, NULL, NULL, 'starter', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-09-29 03:23:59'),
(2, 'YD89328933234', 'Basireddy Karthik', '9398907446', NULL, 1, 10000.00, 1000.00, 500.00, 1000.00, NULL, 5, 0, NULL, NULL, NULL, NULL, NULL, 'starter', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-09-29 03:42:46');

-- --------------------------------------------------------

--
-- Table structure for table `email_outbox`
--

CREATE TABLE `email_outbox` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `event_key` varchar(160) NOT NULL,
  `recipient` varchar(180) NOT NULL,
  `subject` varchar(220) NOT NULL,
  `body` text NOT NULL,
  `status` varchar(24) NOT NULL DEFAULT 'QUEUED',
  `last_error` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `sent_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `fee_entries`
--

CREATE TABLE `fee_entries` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `entry_date` date NOT NULL,
  `customer_id` bigint(20) UNSIGNED DEFAULT NULL,
  `description` varchar(255) NOT NULL DEFAULT '',
  `taxable_amount` decimal(14,2) NOT NULL DEFAULT 0.00,
  `gst_amount` decimal(14,2) NOT NULL DEFAULT 0.00,
  `invoice_ref` varchar(64) DEFAULT NULL,
  `entry_type` varchar(64) NOT NULL DEFAULT 'PLATFORM_FEE',
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `gst_monthly_summaries`
--

CREATE TABLE `gst_monthly_summaries` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `month_key` varchar(7) NOT NULL,
  `taxable_fees` decimal(14,2) NOT NULL DEFAULT 0.00,
  `output_gst` decimal(14,2) NOT NULL DEFAULT 0.00,
  `cgst` decimal(14,2) NOT NULL DEFAULT 0.00,
  `sgst` decimal(14,2) NOT NULL DEFAULT 0.00,
  `igst` decimal(14,2) NOT NULL DEFAULT 0.00,
  `fee_entry_count` int(11) NOT NULL DEFAULT 0,
  `eligible_itc` decimal(14,2) NOT NULL DEFAULT 0.00,
  `other_adjustment` decimal(14,2) NOT NULL DEFAULT 0.00,
  `net_payable` decimal(14,2) NOT NULL DEFAULT 0.00,
  `status` varchar(24) NOT NULL DEFAULT 'GENERATED',
  `generated_at` datetime DEFAULT NULL,
  `emailed_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `incoming_sms`
--

CREATE TABLE `incoming_sms` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `device_id` varchar(100) NOT NULL,
  `sender` varchar(120) NOT NULL,
  `message_ciphertext` text NOT NULL,
  `otp_ciphertext` text DEFAULT NULL,
  `received_at` datetime NOT NULL,
  `expires_at` datetime DEFAULT NULL,
  `status` enum('RECEIVED','USED','EXPIRED','REJECTED') NOT NULL DEFAULT 'RECEIVED',
  `message_fingerprint` char(64) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `incoming_sms`
--

INSERT INTO `incoming_sms` (`id`, `device_id`, `sender`, `message_ciphertext`, `otp_ciphertext`, `received_at`, `expires_at`, `status`, `message_fingerprint`, `created_at`) VALUES
(1, 'SIM-GATEWAY-001', 'TEST-SENDER', 'aMlRQ/QsM/4SPSFwdEhYXi/jgojycoLtkt+5Ky8ACkeBJ99Ngj7eyqNOtyK1fYXsV0uJijB0nDEc92NIOfXTGfhu6UzIWAq/2nZM8hY=', NULL, '2026-09-08 17:14:43', '2026-09-08 17:19:43', 'EXPIRED', 'ca0894394269913cd0eb2b2e0aade93445776960cc517d9832ec2c49a8ada50b', '2026-09-08 17:14:40'),
(2, 'SIM-GATEWAY-001', '+918601445007', 'PQjR5v91DyiRkK/V9EVn+MI26oKuoL4+xjKikBFParSDlHrr+W3wgmH9', NULL, '2026-09-08 17:18:14', NULL, 'RECEIVED', '3a30499ac3a17f9ef9ff927bd327b3970956c321bbc1d5cf5192032f30309716', '2026-09-08 17:18:16'),
(3, 'SIM-GATEWAY-001', 'AD-ICICIT-S', 'Sa0ZuMZxv0T3za+HOldm7lmg2+NDPpbGH3Vpnh/K43Lh9R6mCy9C8FTV6zpA/TBlqQggZj56G+I85IuGHB1M4LqiEYxMvZL0mZLWIIcCAI//nQzEp3Ifju8DzWeHnF6m6UBVWz+rVSlYb490EsKsffddAkj0l2Q2PdklNTHFY1f/QZ1lEYDlQ+1rXM4SVC/5xmcjgBiQnpQvQ63XpWc5C3r7zQAOvk01Y3NhXw2X4PbNUJu9bSU=', NULL, '2026-09-08 17:56:06', '2026-09-08 18:01:06', 'EXPIRED', 'a2ff4b018b3935a40392bbd3656f14e8e853ece9052b19b64153381ce3c6e398', '2026-09-08 17:56:09'),
(4, 'SIM-GATEWAY-001', 'VA-FEDBNK-T', 'o+IwlHfuBE2ueJT8hyDfIzLFfYGMlwUylu3wrj2MrtXBEvEh+w8bzaG6o/tZq46aziniQRLQ94yt4GG6viso5PbmnB0Vt/iIQRARyU6HrYWC/gDnzkase8KaltcVXzGPCWUo4wwnJ+VEbrxIqZgqq7nle4EG6iMij17v/n+hAz1Vov8SAc7eIDOiEv0wDx+MzG7WmxOGgiI9Qaw3lBdb4k7dC/5W+efAFMLEcXqSD9pfcN3nJ5s=', NULL, '2026-09-08 17:57:00', NULL, 'RECEIVED', 'eda6471fe9313f8fde3182d6437f260d22a4abf6e7e17e9448af336e922996fa', '2026-09-08 17:57:37'),
(5, 'SIM-GATEWAY-001', 'AD-ICICIT-S', 'Cw22K74Qv8JW/qygUOTxH6FsCngnl4vlwmwG1/Uh7W3RXDnB0O66Xymw85CrEMXSO9tJ3K634BsI9WGBJUF7T8Rx3pM43FzzlkFcjdFJerK3ia79QUp3vwFZ9Y423CegdAUQWLurCsz0lgytzbqhVwbSgXsgVIHmKkxPgNj9OhnwUGntAHMVitGewW3N/7gEEA26oBMK0s9KfaGaaoxXFaXih7ayFGtdGESsw8hzbkslei2vPt8=', NULL, '2026-09-08 17:58:44', '2026-09-08 18:03:44', 'EXPIRED', '54ba2426791c76699314354d41e3d885b41573c2663270fbb97e59e3121f38f5', '2026-09-08 18:00:08'),
(6, 'SIM-GATEWAY-001', 'VM-FEDBNK-T', 'zTAkpPlZXrNP5/clqf423J4+2tANrn2C81A6hnxnYlyOgMJkN0nyYqmZ2h4eGi66UByjYBxj34oOacSODTCI3bqLcNithUSwBWHwwUBNjMbpFZTdnib8Nq03CF0HeSXagwupC9l0d3d0j3eE+SqxC8usbofza02U474ZHZWYrydbDpnYpu6+5hyVZ6u8kLF1FpBHN0NSwRgXdT1+ZguxBgG3+0d4mGg6d784SMjjbryjfO215vI=', NULL, '2026-09-08 17:59:15', NULL, 'RECEIVED', 'f2f81dd9f094a494b8c3e285baafaefba9448c34a38f3879426b12a9c20bf82d', '2026-09-08 18:00:08'),
(7, 'SIM-GATEWAY-001', 'AD-ICICIT-S', 'p7jKWiAWDNfqP99lixElbmrJ3zPmqaeYaV7jnLfGW+jVQkicntscmeaCk/4O1d1LDomLNqcB558ptb9HA/9YH8gWl1YVMtBMp9GpvfTjDvakKhuTJSH8lODVdWz2lsrkQbbNEFR1CDyO9J+65j6iB+LY+ep+bmL/ROFxZClrqR+2YXVIPI+/BpH432ymCto8WWeUPwRZnwzfD38L3Dxa98J7vvNRmEKyEziT5w2OOhKPR6lmYaU=', NULL, '2026-09-08 17:59:09', '2026-09-08 18:04:09', 'EXPIRED', 'a2792368dcd126c5843b49d609cc0e5af6f9c3f687d5a5a30dfba0c9177b3180', '2026-09-08 18:00:08'),
(8, 'SIM-GATEWAY-001', 'VA-FEDBNK-T', 'vARRp2psZ00LBJKbWvLGnE1ZaINgpTJiRmn1ezldPzb/d9aORbeb9ImPqHi0TzsXxtwEbLXmNAk4aFLmYQ3N9EqBPqVlHLpeL+Cb/uul1WAl3nOO875AHH0YhvDLKZu7LDuU4IC05hhjht+CLaqDn/Y0BP37P6x8C8bzblOI61GI9H9WI5msVS/LRrX3DjAw7PTDQ7drByin3jQi7BJf6BmbKE0aytxe1wdecskxx4mjmWweHwo=', NULL, '2026-09-08 18:00:58', NULL, 'RECEIVED', '717077eefd2786df1ec8341fefb0db6ac9491376cb84b4f68fd39a5de4e6f1c3', '2026-09-08 18:01:58'),
(9, 'SIM-GATEWAY-001', 'VA-FEDBNK-T', 'i3CpNjBFpxTFCS4F8pUfaCIFn4Sr++S8FXEOi8+omSzjHHqY1YxCtaOU8sxCZv8jEpM7FU2UZ+4N/H0q2nzLpBo3/DBYYPThczAfmaoZhY7EQBsg9N6fa7cal1UzBI1EHH0BEy48jiECEtRGwV1uJLcDakrYXjv6yKiTiV6ENX9NjjbfVHioqy6nfCmkyIaft8BcSt9dcCmCLFvwJZaJLS6wylW5bcxEVhh2TLQ7ii/6FzXb6ic=', NULL, '2026-09-08 18:02:43', NULL, 'RECEIVED', 'cbab36df84d3a38d9230fdc64e94bc9f2b02674df75fcad0c4ed801acc0891bc', '2026-09-08 18:03:27'),
(10, 'SIM-GATEWAY-001', 'VM-FEDBNK-T', 'I/d4qsYO4q5FTnJUEIA5VkMbvmmyDLSp2FSZVkhGBsempxyaugEtuKYjf0s7P5go0YVGw8R7UbgY8tcyhN8z7f4A3meiiohwPMWCjuaznH09cAU2Ian+Vx4kD1gXTBmb05Smc3BIDG88iFKi1QBWHwlwVwfQwTwP5/4XNdVIinr/lNgMDq3WF8edaQfIf37PDB1az5MhJH23+skBVJwReCUlkb2lr73OoJvQ4C2Dmj/SuaCJq+4=', NULL, '2026-09-08 18:10:40', NULL, 'RECEIVED', '159075e0503538ebd89ee7f7ceed29658189e62ebd72ccd71f80c8d53c710100', '2026-09-08 18:11:17'),
(11, 'SIM-GATEWAY-001', 'AD-ICICIT-S', 'g237wJOB2E4Es0I66curBfzoJS1X50JOBhw86a7X/wMSFyDRsh9PbLWNYWT2a7r9tgtdrUti8zYCi+87ke0ayrH1AKkXb5btw7LBnJYNqoDYupmzWrgOSMt0GqxdBsG134O5/LFXsfVa2Cv4pG1SW6HTXVJoK6LJb3uaNM0Yhevs1ZI3EUfUiHTG/hRMFUO8Me6UATmW2D5xGSHaeUFdbAmZigq4QIKVfd2DZG7nChwYUj2KrA0=', NULL, '2026-09-08 18:10:40', '2026-09-08 18:15:40', 'EXPIRED', '0d7508c64ef1bce8e0100f44285ed0dd82a5df15822e9625ade22db39493127d', '2026-09-08 18:19:58'),
(12, 'SIM-GATEWAY-001', 'VA-PVRCIN-S', 'jiTxgR8offeLn35Ha1VyBE2+3wSBjkCnX2JcrEnuabP1xFrh26lqX2KBNbpVanl4gh22Cco7vf0f8f+A6+szQ956UCwI0N5n8o0uGvwIEm6tRjNuKG7LRvoWOLt2d/etUfbISFZWYvh917qsrb7N75ptkw/qyF7+L/JW5J/mYHNW/cqz7uoqhMjvbd1gLkeJepZL8YJ4zDhHEt4VCqWELQIxR3zxUqyZDEYGF70b91c+XBXt3heI+FyZJOzwvRGluoL2jBuDN73Acpmqp9SQD455nnxL5XOagev4ySK+0TA+yusYSOMx1QtFSNreANkpcsQdwHdNKUh9tzDw', NULL, '2026-09-09 13:37:32', '2026-09-09 13:42:32', 'EXPIRED', 'd93cb2be95d8c25a05ed4b23e55b07090a0d2f151c63b209e48899ad884a8cdc', '2026-09-09 13:37:37'),
(13, 'SIM-GATEWAY-001', 'JM-PVRVIP-P', 'qzkhCNNMY3EMSbkl0ObrpxCkSTAiQ8UyMNHeRkLh5DuyDm8LkV/ZuDNkyshhOZtySAjF0CxRVaorF8cxHTX3cHhL0Vvd4egHFkb0yghV77jSyBv+aOuPbOoZulVX4S6V8JrfJ23ImOMHF+nP6FjrDKjTCIA8uB0bqRkAk8qbkVWdggMGpi+gcDx6y8liiWP80fzA3fDgtXj+BTldQlMwcnjEsYgMduAjlhg4hre7bauXQ8KBKPb148ptcBgoZE+UzdiD', NULL, '2026-09-09 14:07:29', NULL, 'RECEIVED', '18a77f963bd1da58d768212f23ab0929b9318108237cfb6c6cc80e7c0e76f427', '2026-09-09 14:27:25'),
(14, 'SIM-GATEWAY-001', 'VA-TSAVRI-S', '+NrbqL7UOSVlezj+/AOWz4th4FvfvJYBMxpUAKEHcbtFaqbzovWpqtkyTXghE+W0nh+ChpYXayvnyuqD2JAxxm+Z7iuhIelzP9kvGftkzStWZ2R5PwO7ht3M5CzZy9ikMoqirYdJn2vYx0paFSl2I1Z9XsJ8J0Rug6LfFAorXovTWgvTW2kvUgHOqNR+LR3As2Ju4I8Q0xT7PqPk8r/Uc/wG1XPPM2LXzh8xPzhPRKjNGOIRBA==', NULL, '2026-09-09 16:42:31', NULL, 'RECEIVED', 'df16f5620abe407febe3e8b8f56cc295aefec2e4a392a0d7b3daa8f80ca7a39d', '2026-09-09 16:42:43'),
(15, 'SIM-GATEWAY-001', 'JD-PVRVIP-P', 'Lkf3V5WvxKQ4EY+/uCMlw0acQjI3iwxOytcpJXyy2b9nS1UGHTLhq2biG9+YwZKGNZRut1L050jDLBuefnFNoWn/qWUDTh5Q8dddF/sFZnH9KEdl+lhGN440EsUxA2PdHdGPT4GvOwcLj1nDXvVm9JpRJaF+wyWzw7sQTyQLNB9olXL22MIt7Ep5j5hUz85+MsgmEcL1yODwiD/YkzGewgbmaYY499hBuM8mANSWaVgrxT77IJVKEkG+mUuvbC8qC8HTwQ==', NULL, '2026-09-09 18:03:22', NULL, 'RECEIVED', 'b5bfec0a678b78b556f0af3d103cab90def267d269430f0870493a8da4874b8b', '2026-09-09 18:03:29'),
(16, 'SIM-GATEWAY-001', 'VA-TSAVRI-S', 'PZQkN+JrfJ0W9AWiC2twW7L+okxjgWnvr/sGQw+T5e9ZOzNhgEk/3EAEPLuxCOvKk8fyf3dKoD6pQkg3YJDhkPCQzJ9E4Q3wnGZ6OHHBFiTs3MxCqiUyGFcleTPfy0eKov/JH+2iePxAzGY9AaetvaZIzgcEcAUcgsc/H0qb8Thn9vi8M+zPBgAvGl78KdfOfVh1HCePXCPVoxSlW3JjNPv4eMPSEKCrcuIo3hpqZZ00S1G4vg==', NULL, '2026-09-10 08:41:45', NULL, 'RECEIVED', '8a1a9634993b1fd2887106f4ce77e973f8b655f2c3da4b8c4a2cad8e47b107b6', '2026-09-10 08:41:58'),
(17, 'SIM-GATEWAY-001', '57575711', 'IYpAfpqq5yIiVWPqsuDDSsc1fa1CvtecAdSmE4ln1LUx1vy4EhhpB/rKSER5erFLWFn0CdI1/o772AvXnlSgx66w1w1M42XbOFyuE0XNy2Ll/UtlXEy6DSTn0ckn2PHCdq5fN2Rq+QzEfHaASTHyfJwI+MSOB9kVkJ/LdyMeDd+TeIA+EW8+VeU=', NULL, '2026-09-10 10:16:13', '2026-09-10 10:21:13', 'EXPIRED', '7d1346dacec586a072f3b60bdd0cb5058e365111857195881572157995debed8', '2026-09-10 10:16:18'),
(18, 'SIM-GATEWAY-001', 'VA-FEDBNK-T', 'GhKFBMyAHmtzi9JkEec7uxM6iUmXkSpsNd0C9aoOTcsUmYKntoEAmNSMQ3sYlGrHtrTinr7IUZaj7WMVbbSmxE2sKnL31Na7BPN+mBgqZmWCcx3qAtxta+9bvmA0Wvnpuv/j9HcWpTleOHl3GU629r0H0sgV4gd5pxBL+fvqyDPDCNnL39SVPMSemfmM9K2tJ/a8FhqmFrze3EZbXWIZDhucFD09r9BiQ6xNkzZd10KzUvxMccLoY7YeyHuN', NULL, '2026-09-10 13:07:54', '2026-09-10 13:12:54', 'EXPIRED', 'f17c731b54acaaa6611f9330e265130d0ace106036378778878752acdb30c4f1', '2026-09-10 13:08:00'),
(19, 'SIM-GATEWAY-001', 'JA-REGINF-G', 'sy3HPfrCz6kQ56DTwYQMCzq2Kl8/Q7ITA7uwUYYE04Mpn/5ciKhisvR7jGZdEtqEITp5IwHwLzU6Vwg78//RndOMctvCTWlzewaEs9l8zmjLZ+/QnajNq0Tre5cRisMhj3k3ugekVOczHQLZvfaVT2PyBEQX9ffr6cs469GlDIojH7nrwNR49971Z8/LnP1MfqdK1DMdmMVvXRhdmHK3UStj5kEq5Qr3oOjCXy1H5cPOfgSg5kVpiWgnLWkCdXU/FTxf573cR4A7TVpC442vfqZTjLRHEKsKJmobi/DszmcY/suU1gRIrrIO1P477AdXQme29I3uQmq1PdDYpVbn6mreI/gbsN3EPqz0kPnBRQ==', NULL, '2026-09-10 14:50:52', '2026-09-10 14:55:52', 'EXPIRED', '3c76b0789bca54fde58c8f567340be3dc69a12ef6f9857bce9352a7786878d6a', '2026-09-10 14:50:57'),
(20, 'SIM-GATEWAY-001', 'JK-UTKSPR-S', 'nprIuAfZ5bWK7Nr9xabKjyWtCiAyGhTxxiAiGHXKmHfUwwFiNDfD5pkAupPn/b+MkgF13+ikbG/1bZ1bftb32k2IapiUOX1lv9X1WzJfJpFYHx02q3mQr4cOZdV63s618cgYlRkV3HuYTYdNJ4pEDyDX/RZxoxxrXbAy+NwVVoyoWo/TVQeDx5mBw6ZDJ1a7j795/E57MYGnhSC3RH/45UvCFHMpJGHIyFPkwn104Qc=', NULL, '2026-09-10 15:35:00', '2026-09-10 15:40:00', 'EXPIRED', '69912c0f5ba703104ab8bf077e0e576a87fec5741310ec03ac7f17d75b65e61e', '2026-09-10 15:35:05'),
(21, 'SIM-GATEWAY-001', 'JM-UTKSPR-S', 'kM9M3e0XNiXZZRgz42J0OwAnCJ9SUYXTFH/sJ48HAmARlICFcIB0nzHG1SWeb5HXI73VtqscTp8ER8r3bayci7KdO6CTDYiYBDmUx68ByNyJQMiNFgI81Yo2REsgbnbDr0bPjEj0u1RTOMBFgExmqKKREKLcFdaffjZNn20QDqiXjE5e5YLC2Rs9UqL+u9R6QgeLy3Q8S35yTH5zfJnZcO366Hvx/UBTVL0gtA06', NULL, '2026-09-10 15:35:33', '2026-09-10 15:40:33', 'EXPIRED', '80647de45544f181754ce8d3611e606171c3bb1e0ec579344e27c67c061a3b56', '2026-09-10 15:50:00'),
(22, 'SIM-GATEWAY-001', 'VM-TSAVRI-S', 'ZZhyXnlzuQsKoYI7XTHKVnMhr8GXrDM6/ErRz7mHk/8k7/1jJ5RISMXtS2kevaEsTERffb1aEns68Ld4Yy+Bgrn5UwJQG0Rw/634zTCI28aWZlAVteksKNnD3U4LH6qWTBsZqfTJMgj7jdnItEUtjKu0RSdLOoJuqz/gW71Z7sKo/K6W1mfGLkAiqgfAOZhQ20LKhBKFrqTGurVaaSX+uf8RRlq6BOZejxheIje73irBbFmUXw==', NULL, '2026-09-10 20:52:31', NULL, 'RECEIVED', '1722ee9c466a552e7f787a8a222ce2dc5c8dcc25a9bd518f0e2d6087061a736b', '2026-09-10 20:52:41'),
(23, 'SIM-GATEWAY-001', 'VM-FEDBNK-T', 'Mk3OGpYhvn63NcvNT1HNjrLVbg7iwPzIhJLYB/bP7osYsdTD0r5n8ee5v6ZaYSVqWkUM/llHJiHpVnMELd+P193mb1ywobWCHJmKgln/IIvpcusK5I6OJqL6fJox+SG3/b1bhxH8eMfpfgoDyXxvRUWv0duojr5xtlTQeATlU1caS4FkfRZdsYVb+OEFVk3VRLW6vl/W5/J7T5xOpa8G/+HoMq+EwdFuAvJ68au+lQBFnvfz7jgwBpxlZy0yRV3xFA0=', NULL, '2026-09-11 09:23:11', '2026-09-11 09:28:11', 'EXPIRED', '03b9541c2d0332bfb4c763d64ef0c094096f32524161b55df6f7a0ee31801d3f', '2026-09-11 09:23:21'),
(24, 'SIM-GATEWAY-001', 'VA-UTKSPR-S', 'A+ZlU9hH++UQo4dG7Oqq0nqFm/u3py+SshriIWZdgStKf+KK8GVNWLZr+gRSfRsUvpjwfLcleIEfOr8mo/4xQsJ97JNESe0iAyoILuZhIeNIUzGwRa/5uqBSGUi9/CQSJISaUwqWRmdjmNCgtDPW3qt04Z/PH6iwFT4Bby9w2cOTKUj4rGvWN7U8b4RAu9Jiuhyan1DuY2mcAf7SnWOsr/yhdL93AeGTNnDp/XJ3JWaariyiZ4G1mWyTd+Y=', NULL, '2026-09-11 09:47:52', '2026-09-11 09:52:52', 'EXPIRED', '49bbb5160bde506e5d8687003432a052145e68830875793f75ff83b21f9d5da7', '2026-09-11 09:47:59'),
(25, 'SIM-GATEWAY-001', 'VA-UTKSPR-S', '1K71akEgNhS+H0HxuUIisHCosjhfdqVg4GEUt6WE4RjX0INCLFwSp1JcS9YSp+bKs/bqGKu+I25Z7+v73Cr4E65g2kdkY1NjSCo/z26WDhlbsRKGjLsuUvTpXgJHoKRwiAC28kUie07+NGxQ2r19+a1f08GDHFW7fvBPS6or1Ak04XO8mQ/l8Ov1vgLVBorSs8hMo9UGbyeRrzO91Bgw5uF14dwX/TQHzdZuFScIgV1b9UpOQXoBYVnb2LUslg==', NULL, '2026-09-11 09:49:42', '2026-09-11 09:54:42', 'EXPIRED', '3f7361f47ce5c4b5a32f09539a9c2874acadb4c86daf285ffb75e5eed8d6083d', '2026-09-11 09:49:51'),
(26, 'SIM-GATEWAY-001', 'JX-UTKSPR-S', 'Z91MCBRyLl7vHt31FK+yMTFPMtzA/GxgLUDjvl9Ljau8XYFZDuwDhj92WUiMv9Uvom+Gj5p9DM39BfpCbSBlaBgFH5LzQm3OiMRCEnEcIjjags6MxMcpFvyhPy0E3jMKmtu0TnowJr0ZP+NlfytJCjlpGkAmTtlAtaNK/kZQOQDEpS1bCOd/N0olh0sp5ZXKzm1uVJGc3pXnrX6u7GQwwrPBCR+hrcmJsr1exaGzz/MOeCMTMbFXpM0S7ig=', NULL, '2026-09-11 10:40:54', '2026-09-11 10:45:54', 'EXPIRED', '86071433fa630ad595001ac8c4174545bdd34ee5f7a562220a93abb16a72ee5f', '2026-09-11 10:41:03'),
(27, 'SIM-GATEWAY-001', 'JK-UTKSPR-S', 'GvN77GwIhbxJBjng4aa/rGT0qxPuo5oPHFYrbSLT6joBZfHUTOaRQMpdWKa8UtJlp0wIJTvSUn7nJcKyp7dL47B3uE03qQ/aOvRMeypf4nsMA1jWyqlRV5IN6qVUCMqNcqht3Q2c4ECE+8xDg6KPr4+DCnYJHski5LlJaVTdHtYWPzTLMw5OvupgNzDymhGThlERxvmYeKm8lpMbRB/GabZSfSNqk1TN6yofJRbzFknEOD/nDp8zKsOOfOs=', NULL, '2026-09-11 11:28:05', '2026-09-11 11:33:05', 'EXPIRED', '4f4ad7d822bb839f256b2eba486881e804460d00fd4ac0b464cb1451e14cc360', '2026-09-11 11:28:15'),
(28, 'SIM-GATEWAY-001', '+918019358764', 'PRcMiFLeKyptK5UtZi+ri1CtSAmwHZaxrkBHXxrJ/7pgm491yZIis8Gl4w4QqEijzX7efKHxmBC5UXD4hHvWV/CtSLabZM4bYgD/4o5BLTo42xJARXf+SXevn1TrovaA82Qr7vpi73j2pLoG4G2iBwWvWPvIfzwTIGV+LZ2O7z2zyeZ9EJAv1p63E2JicJLn4Rox27LLu3gCPj13cq0ab+QvAdRTAh3Q7GP/4gDSpr8T9Yw8kp+o7HxAA1in', NULL, '2026-09-11 11:59:49', '2026-09-11 12:04:49', 'EXPIRED', '53b69aa8037216d1c19b63e3bfd6f197027986efd5e0902b9ae680b77bd7a6ac', '2026-09-11 11:59:53'),
(29, 'SIM-GATEWAY-001', 'JD-KABSAN-S', '+UNZoVvLmkMdHPot0fhxIbjd0KueDOm9Wm1oEKU+5B6+X+MZAE4/elhJT7PXz27LgYq1kQ7jy/R9ZAuNo6M5RxunBt1vtpv7wyrwznuYTcvYeikprZMR2F02bMrvPsPjG2/AmsAtSbq7h75cayRYfBswtVfLVIo5zUf77s46NJxhbom/tdhyNa9Z', NULL, '2026-09-11 12:16:37', '2026-09-11 12:21:37', 'EXPIRED', '54dee700f4b175b95804d36407709e0a00190c2568ce114017bcf24dd767f0e3', '2026-09-11 12:16:40'),
(30, 'SIM-GATEWAY-001', 'AD-REGPY1-S', '8o6J2P4bNgyNvYMb5mfTh79Ic+aRSHRqxkbLCyW6Z5GHQEceuSLm8UU4DucpeL6ouKMS5OhmPm1VwA36+xXqhdalce4hHtqtClSUYfeq0PN+WJWT9kjE9o/+N2i6OzM=', NULL, '2026-09-11 12:21:04', '2026-09-11 12:26:04', 'EXPIRED', '2b64070b78e407bbaba382d96714b89d62dd82a6c877974886a01b57b0fd8da5', '2026-09-11 12:21:08'),
(31, 'SIM-GATEWAY-001', '59039465', 'VpBa4sP/1ct52MvTF/o+opSU/65srwofAsy5fok+UvfREsG0XlgO19+JayJEahhOLeQi/YRyyJKg4vjyG+qmclE2cCHFBS54p+9e5/Ztw2KTx33EJw==', NULL, '2026-09-11 14:52:32', '2026-09-11 14:57:32', 'EXPIRED', '436580406f775c90c96d2f46f2c3750d6a7a93717eff3c8abe4ffdf8611c7f31', '2026-09-11 14:52:37'),
(32, 'SIM-GATEWAY-001', 'JK-UTKSPR-S', 'F920JQk05e0y9wcKlo0pa3M3F9depSkk9BlYkC69LfN4yPpB4jIQ2tVZe4B5T36ZlCG2IrJcpn6UH7Kq2ut7pqEgm/7fXDUnSl9fnW+C/Pe9V7j6p2HTZ0Gp5HSqq1G1qEFPF+0YNxDEldXJim4IGrbt0arTMRomrsnRSd1l0g0iPa/jCcNEJ3hmYXN4TnthiPpQkJ8OGnO/n+Wh1t7ITpuqZ00qFHcbks1HVt9oDYreUkoZNnlqQjCXkZ2vJvgGBUU9bQ==', NULL, '2026-09-11 14:53:50', NULL, 'RECEIVED', '3ee04e2abdf91efab88008d6de939e2ac41feadf9d4842c680f7a4649fa19ca2', '2026-09-11 14:53:54'),
(33, 'SIM-GATEWAY-001', 'JK-UTKSPR-S', 'hJUOQeDcFhSskiuRTXJE+LAGsoQEQNVeNaFMi25vN6vzTQachKgDV8ELRFevAHzr70h2kwWk/8WGYkHBQfjZrXac05Kxb70WmVWtLuOA5TyAkGqiCkJIsLLpfAkiMV4u+9h3JM+nSYi5HEJKlHwiTlFSKiLm49WMN3vewpAuH2/hoL+680xyM+90sOIrldsY12uMZXQA/2Lr7hT1CAqqxX5gOPalpuGOwRXrgqDn', NULL, '2026-09-11 15:02:01', '2026-09-11 15:07:01', 'EXPIRED', 'b2e1f768ceb2b4550b718f3cc6fc349d95c367336bffc3cc8303651fd2c80777', '2026-09-11 15:02:13'),
(34, 'SIM-GATEWAY-001', 'BT-FEDBNK-T', 'ParozQLlIAmRCMeUjqV6OwnrNzGkSZo+eshHR0bV+XCCR0X2UJZxCBXhMnOpHeZOGJLX1W1Qh+GMlJfKsGDlb6yvHKufsSULzxHddfq/C6Kawoa7h1b6B1gcBmXhHlw/V4GVkP9BUF2c193BcBsz5DUF/bNjQZw22get0Hy9ZghHmmPikMqE7On3RzoLGqlwHwBdMW4VgCifmRc/okScYYPdeaKX3WbuLKzNpiviJtV2Luo23c90kjWul58h/VUoOnE=', NULL, '2026-09-11 15:02:06', '2026-09-11 15:07:06', 'EXPIRED', '77032aab811152fea1714be7e82a2bb330f3b798d0fa4e9f5368ada210ce4ea4', '2026-09-11 15:02:13'),
(35, 'SIM-GATEWAY-001', 'JA-ISATHI-G', 'DI6XASHbm01VnBari9n/CFpcZaCad4QANjRwsVHbdAHNlpSCwM1XGhBjz4Vr6M3W1tUPq9zwq/WQiFDdSa4+05EDetmALxjP+Dd7wJ8Zs7O+oz4XrvtVSTuVzTJZXkYNB/8VkuFtWBvCh0cVomwIKVyfp2bFzSAn+OpeQKpnLg+I1TqE0lYhAf8gyGsI5u9NgmGbBCF3sAPunoLILQRKiaMJbrgFrEd22otmls2Vpf9s373Zsa+muu0r0x7gVnmKjcmexS17dy5k+joDcoguhKg/BUANCsLv', NULL, '2026-09-11 16:49:25', NULL, 'RECEIVED', 'b7f8b65bb808bdb33726057448ea14d9033ca4953c41bcf2d96d8e1ae32afd4c', '2026-09-11 16:49:32'),
(36, 'SIM-GATEWAY-001', 'BG-FEDBNK-T', 'GMQZ9OgtYMBuYrR/+WBM43baKoxnpr2odxiOST0mQdSn9FHq9y+DhGyMUXyUGmNO2VGViFUVni2AFVQzfb7kxGhWz23ijQzwdCy0z4qNXvpggs/VdGvBYEwtaemeWzIDKrSfxm/InDiUl9Nh+ZgcNQGMDzFO5vqFOEbNho8J0rehZJrHmc82jp0L40dRpig7S5V8CuJwOE9dgkJ0xFARNjZLEZ1nXuCpa3gUcBR8O1t6dcGINd5jm8MpFexfnnM4fYg=', NULL, '2026-09-11 18:20:34', '2026-09-11 18:25:34', 'EXPIRED', '8616d0c4f0d29353b75cbc2df24620a244dab66aefaa9860fd5d635c7636e4a8', '2026-09-11 18:20:48'),
(37, 'SIM-GATEWAY-001', 'BG-FEDBNK-T', '5IY4pO1tWCGrKJ5tEz97NZ/dEe0cl02euiZlluY38VIAlrtKVest+EIFszLH5Dg9CE+ltMJh0M18beRZFgl0Jj1HGwLj1N31eWpKeOTQP280GEbGF0b3ZufFqa3/LVNjcUmHZiSPHAi32QLbTzFR5PX9Ym3f1F/rwUEwjBr/7s36O/Emjsqlt80OzurA9I4UdMc4z88h9s/BB8+9PoDMFpgqLAIMIhDHFNo8q7sxMZCdPeyqFkjPb1Om2ALdWxlpLYw=', NULL, '2026-09-12 09:46:05', '2026-09-12 09:51:05', 'EXPIRED', '83eea26c1403e754bd673f6ed46deaddd13088219f9786acb888167d85bdcfaf', '2026-09-12 09:46:20'),
(38, 'SIM-GATEWAY-001', 'JM-PVRVIP-P', 'UsqzpI+RxRgd4v5zZ0V/hwN+ffzYGwIpYDS00MV9a9JPXhblhcE+/z8lKrmaZq8+jYQDwJAjJ8Eq2ZGmZ/ZLcmESpdJp2Z7nRJuNKESYYwpDdnnqJRPz9ryQorS/BXEpZ+DX2AB79djK0NnO8j/QUBOsGGBqvFIqjc67uFQn9TylSph924iUrwlvuXt0ELQZa5VKGKr0o0uXFPIPvcMx6XI97bDY4q1ahoCC', NULL, '2026-09-12 12:13:22', NULL, 'RECEIVED', '9a1c2202ed252c024a5c91d8fb0f4517cd30a1f715b7b6bd2458210368507b17', '2026-09-12 12:13:29'),
(39, 'SIM-GATEWAY-001', '57273121', 'ZJwhjHiJk5vLIFh7Uhvk3YGkyoqWwZ1Zb/7lWEh6fnzsQewerDKtNP2fkqO5oQ6jlpq5Em+SAQsXxJk7mVUPpgGbSSnGLKsG0hNIddmWlXwc/pEJKA==', NULL, '2026-09-12 13:26:18', '2026-09-12 13:31:18', 'EXPIRED', '0cfe2fd7aa63fd13ab8910030c50cbab979f2fd2bd3572581abcf99d20c5c2fd', '2026-09-12 13:26:24'),
(40, 'SIM-GATEWAY-001', 'AD-PUERYH-T', '2KcnNR0Qcq3fqx8DtAB4OJ/uvyqotXVw+a/PgXk9wLxoQ1/Hh6S5H7+SwgVjDlIbkCQbJYepwp0WdkNw/s6hHeqPZjUnBh4t11i0aBCKRpBh8o8ik2F4c8V8VwJMsZ3jBnwHw89H02btdzO0o48O4G1/0eNjTuTAMv7c9QKRkiJynjJdkrM3nGnvgHs=', NULL, '2026-09-12 14:10:36', '2026-09-12 14:15:36', 'EXPIRED', '5172845cb41cd22866203a9cafbad26ea29605ba33e55e4b6c55cf6cf1d6d67b', '2026-09-12 14:10:42'),
(41, 'SIM-GATEWAY-001', 'JA-REGINF-G', 'hARbc3mUYEy2VbQOIbyy120fJBKLNYbIHd52dJ6eZy3Iwd2OMUXhd9jsSHAPETl4d1ryvuXHW1nXeT6CdGR3wtujZt52zQv5W4lTmwifC4ybzctYZsUfoCMAQAMJhSnYCZdYaQw5iZTzPWwZNPeZo1Huaz2amVljDU/Y2+49/080QNNq9zQIt7xN1Wr2sSIlB4XlGc+b0aYae78UbxsQ7+d/au3FTm5ef9+25oh+9FJF1SN/pNChzKZRJ75IeJRd6CkGdQRYaVmYOj+kL/10HDBPhUgxOjogzrAtEmHlhtUZRl7rJvoeFwKZCshb2J1TUiP9IuTeouc5M0u+uuj2ZoxUly54lEs6+/Z8ENAUbuZX89/mVYkC0WNxn/05lk+0JEOoK2IsrgZ9VICASK7B5Ex5g2joWY8cZyPP5JL7ZOqZ7qLSN0s+ZHL9tluetJWhpAbsXpheBhKYwCvLT9MEI1zp8qyfGUqg791a7D/6wpKqb0cvrV15rINl9BEXQThrbsBOCePSssUQcums2SfXVRAwVeOSS1NHHBS3dKkf4HdWfqew9bOc7gjRhs60As45YnewbzU1QR7k27DKEVPMXQ7uULspHfiH8EVmJs3/sgaRnpcJLdd5H2+rJLEhi+1LeMHRLfqi3FHeun4+sgRJx5Viv1hSRYAYaS1U+5oc5aOifMg8rURzAeY4roFwaj16vSwKwoGF7ojftu5H08f9C/tUD8RcxCs+ml8t8FlQ0XMOMAlKM9p0WbyNWncFavH/ssnWmkrIwUeDdbEFb7gkCIhGvBrHl86PufO+0bVTIPBNH0MadgZCCQwEmI93YwTyAWXA1djkIhJW8AFGyXgQFAqEOl3y1USAXdVtX4v6b24P8P5KdtM+uibOqujH/WHN4wc=', NULL, '2026-09-12 14:52:10', '2026-09-12 14:57:10', 'EXPIRED', '8f6561d317ea7f08383133b3bca4e86c609ac6d6b56cc721e45397b1df0bea63', '2026-09-12 14:52:24'),
(42, 'SIM-GATEWAY-001', 'VM-FEDBNK-T', 'fBaQUhY162/AZTo60EVFy4R5u8NZyo5ou8HLm7YTgGx/0ftktBvJRajauE/8opNqJ/lyS5E0H7qflVA1wQb19o9R6+l4Y58nlW2bRB9v2wlDdxdk0JbaswB+OvNLz710GOSW1jyMaUD0cwWzQxbXUosNzMqmbuY0os/ZXGwU3LvGWZ4rrgy5nsavaPw90d0LW86MvFjj5wBZkdTpOvXPwIKtqVCTIp0+eD2JQ2FJDr19VrGCYglML8fUar6UAB0X63iG', NULL, '2026-09-12 15:07:06', '2026-09-12 15:12:06', 'EXPIRED', 'ba619a7eccd6b26d333b370940899c66a92f945063814d049ba9e98cc050a5d4', '2026-09-12 15:08:03'),
(43, 'SIM-GATEWAY-001', 'VA-TSAVRI-S', 'IxSTKWGtDaC5JmRo0Kgc/LVVleUfj5itwIftpMCLym9CSRvlWRXQykVpA0FBHtuJC19ic/6mBW/6owUkjG729KVgX2SioHhUBfgUxQvy6iPkhw39irO2F/8Qh23XVs0Mzl/jdxlsnJyCSthQqm15c5LQjXZPK5YHffnF4+XJU4ms7RNAfV6KqgaiWTMr+b1C/gF2CCB7CNToBJ/Q57SSIOL1OMKCUIZkkY1tAIR7D6JGblWGRg==', NULL, '2026-09-12 19:15:17', NULL, 'RECEIVED', '389198000e7e61e397ff22a5f08e8cf86166c5f6b3f56965b3391eec14664156', '2026-09-12 19:15:29'),
(44, 'SIM-GATEWAY-001', 'VM-FEDBNK-T', 'srTEZGskf2vbdey3jhtAevUQQrjB1aWoQlkMjTOxFpszGqDvCl69JuAdcYJLSLKGiXMk/KAv/O9Dm4X3qrTkGQeY8gFKZvWoOjw71BxdYnYs66dK9NmpoxycZq7khLsbulo1ciRhO3tkJW5PKklj3jyRetAPSwt8DRlbKhprdmOi1BonkdbqMI/rQHiEmg8GPetU6KvBGATLYA7TcieSJH9jHXCwVPlhsvnOlC5ge+4nXehTMLfKCrwTP391MfpRIQ==', NULL, '2026-09-12 19:15:22', '2026-09-12 19:20:22', 'EXPIRED', '1e73672092e2096b266b15eed1ff8380d17c84865d02de36a13f9499af04bbac', '2026-09-12 19:15:31'),
(45, 'SIM-GATEWAY-001', 'TX-NBHOME-P', 'BdjUwshtptmEn9wn9WK3OvgLlSLDg+e5RS5B0jSrMwtYHSm/vfqqAUxI1BQy1k77lKgtrdLOB+FSjo4TJljuq6eA8og3e3x0Z8kCwuTbealRX0qEQHs3VOcm7P05Nx/x/KVUEi4d+8Q6anbI5FEjB2i2G7pmvJ1GVzhUhAlY2G4LAaoApEWVd1QDAx2LOuyLWwQmLDh7Pcchapt/6tqPh+t4PvnMO3vslMX7G9x7PiiLR/bXFHbhg9tYOqoggCe204VM', NULL, '2026-09-13 12:55:05', NULL, 'RECEIVED', 'ffd1a42f913a3e03d2502f85ee4429922fa4c0b49f969faa93933904c973ac03', '2026-09-13 12:55:14'),
(46, 'SIM-GATEWAY-001', 'JA-JIOPAY-S', '2QOeyTSz9/lTWFtHxBp2k2+VC1/CLRhwMTjYylxAWdPUepdzrqkR2mcbXkM13NSXllREUxVPA1aEy1wKsLKIsgx1PPgfpSXgNPGe9ccTWdhaR1y1lV2p4VOycABgLxcXd7zSle/CGOabYo1yOgS2N2vxDcmUBFh/gWl2u2UPCidaty2LoGEh4Nb8ofPSH1/rLMyZSFCkqfXyedjBQK35hYMax3tB2LpdLZjQthTLO64G4QQsm/9gQedrUMwBxfiFpAzaZ3m0M7bs6QANrx7L2FUqTDXNIRrwM3EZxiGMR6O9BLTaJiAVLczEFVPCFAzrH6rnRHF6hFRD8B/cic0HafQedZV4mGMQzv2t6ut80gVgmVUU', NULL, '2026-09-13 14:06:56', NULL, 'RECEIVED', '056a46e41d07af76da9397d28474215acb7a92428387bd13d7354435b0b935e1', '2026-09-13 14:07:03'),
(47, 'SIM-GATEWAY-001', 'VM-FEDBNK-T', 'DdZ3zlQwoeoTe5I0Xi6N1/ZZBnyX4CZAGrGv8lAtkSxQoqZNRlenHbq4M6+wHQxDd+8WDoMa+xrOjl4mKftDAnpuRpM/f5U0Eq3krYbMRNCi3M9gSqzgipw+NqJAAwGR5uEWAzixkHUvPJ4FimsEWjRDPR68OUFk8IsudcLfWRxtWK3fpCqRHgV+GvlzJ33274oqucIPAjVe1jr6Qq9nS5MjHMXItOac9tlpKlZfmxfXfZ3leG5IOw4pbdc8epTLhRE=', NULL, '2026-09-13 14:22:27', '2026-09-13 14:27:27', 'EXPIRED', 'e65e5a68c48d3ff2a705c02f7c4f2a7f0926b245bf75b3789349c74f25367870', '2026-09-13 14:22:33'),
(48, 'SIM-GATEWAY-001', 'JD-PVRVIP-P', 'JMtMlyD+uIvvWEVoyPGtKi6khYhFr4JzDcCvtFxIAIJISoyrMor8+yvD5RffletsgwU/prWpFtIO/JnQfiGYK6PTzR66TtxN0/TR8eqh+Fk1SbyXRV+4n7cpIJnInYu31fQgKGU8LR1Amk2K3r6T/DgO6u4gc1JfUTZ5JmXVfsbSNOJyDjRUSgmifsmvEnua+4We2isPMn7b2TOvUL5fmM6Jt/WCr6XuVRHSky5yGu057SiK+Ae13lUuRn3BaMORVKK+EZaw', NULL, '2026-09-13 15:05:34', NULL, 'RECEIVED', 'deaeffa74da15e4a88d3c4fc5113c68e07c7233d688730fcf4b719ed4ff898ff', '2026-09-13 15:05:39'),
(49, 'SIM-GATEWAY-001', 'VM-FEDBNK-T', 'AaUkOhF2ic63GKVoVfMBJS33owfUyJWpdnPI8GkIyR8JZefjvBdAFUmt1auWpPE6thSZ24VKi1Kft5FqSbpLX/+8GHnWUa+yWD0AlG5tGTH7ELafDeZNo4JIItiiYa1yTWBT+bjFrtghpFcQMN6rtGUQGlrZoVFhHxXjWbskhznG9F4yPwBU4vWuD8UyPD/Ui0rGdYfKWgNAEmIy09KhjgWNl/S8XNHVHArrGB0ZbzuIqDaFjlgihjM3YjmSYa86Bg==', NULL, '2026-09-13 18:20:57', '2026-09-13 18:25:57', 'EXPIRED', 'c6205c31b381e83b02430f87acb5ac439aaed055b194af7bb4623484daefaa9a', '2026-09-13 18:21:00'),
(50, 'SIM-GATEWAY-001', 'JA-JIOPAY-S', 'VJna+ROUCgZfVCnhogJ734JCHI5nv7nsf3PRHrVePDIa8E1ziBJIa3weI6ZKpSxsUu6tGox1LyGtHXapWvWo9+pYEtBGuqTHfLURHHUW4c5pARQ67tIsezWfmoyFuaEie04VsOcC7btPWGQlGaNpk6lsufOQnEiVe1iDO2tzytj1Yk2bcV9N0mG2zTPv4JTfjDIkUHG+3p8HNHdqX/6sh2vmpm/r8Xfy/xDNpnZwxiYQ8AnqCUCR9lQ47NqRZA+PvYnEobxwr5Hmci65IVF5wfMsalgbG3nuxoXQ3HYEZ5tKSd7l4Bem9PzkKKljU2mAVBHGx0K/igdv6osBbPaqYAXbbXO+8SebjQL9', NULL, '2026-09-14 14:03:53', NULL, 'RECEIVED', '6eda4eda547d45aaaf6a2b414978562e1ee3163cec766d6b78c8781f6ab9b08d', '2026-09-14 14:03:57'),
(51, 'SIM-GATEWAY-001', 'VA-UTKSPR-S', 'J7vZQi6fAhI9KPaKhMj/8qRyp4KlzPj3NcEw+K4IbMrkpMUkqRyXfWQ7KTPVITqHUwAUGtizLmuroExLpKyIsH2ResWPdUVm2VkYjtVT/Nk2WAyK+dBNOifLo6V0OuBIs1u7eGb5p3Xkqrw/jKI/P6opAZVSkqxxl7Qb41+0Mkm9+GTnjSOBII1kBoI/qFLUhrn+u5d7wc98r21IvhiKJh6F4gG5Fl4cPreh4mRGaFAUhpspD7Mwbl4aglI=', NULL, '2026-09-14 18:43:53', '2026-09-14 18:48:53', 'EXPIRED', '08ea724f0124e80b0fc02a88aea9317499abfe79dd3eb11f945e3e4292908255', '2026-09-14 18:43:58'),
(52, 'SIM-GATEWAY-001', 'JZ-JioPay-S', 'B4QELMslq2bN/xhkNwMOAN9dATnAxniVMNY3WSTqLrTrockNboCSkrpQAGintj6PfXQ/eZGKdvZvrLmwD4fKx5dZVj3MUFRQyHsu6kWf4jrlduzt+fx7Q6YI/jVgpeaXA8oG6CDV1lphPa34H5DWTcIAL2rOzBywkhW2DguSbUqQuAg4WnQl/NdyXJTEIsiyOXowdGwkzuVBwwBlyTTs3eujs1kniaYgXYNRQggaGX+JWCxOjFM338itGqtg+wkS5Tn6s1i4Z9uo53ciGUU771R67SVLnToNSD92RBo4rqaFs61T+yTwU24JqNWz4B2eUnZbQNZO7OOIbwzhAs141lCKXjkajSZQhMOnu33VNznYv1gNSHJVnnqehajPw7Ir95ntZocUTuARQdCpRTWCo7L2pE9wwHxOvI5Rkj9TuK7Sr3zgOL6LDwwbin60KHBzh5pxulvhS3IVjXliSE8BIQR0okzWnsq0wXrRhuMFfJo=', NULL, '2026-09-14 23:13:40', '2026-09-14 23:18:40', 'EXPIRED', 'e65ad112653a5d138f361a15a07a4ac6ebd893ba9dddd6212c5bf4626060c3b8', '2026-09-14 23:13:46'),
(53, 'SIM-GATEWAY-001', 'VA-TSAVRI-S', 'T4UkxjW9mAL8PujJVY9O2O5GF4b67ICZ/oLjxM/gnSAYGQVeh8ttS7m45RcfFsS9/KMr7nlOgyoalSzjxl+ziOIMrzI0m/5rhJcePQ/HBBtKo2zphZhGZA5QCsDYd9hH4MgpVmwe9ZvkR3Wb1ysn3/43d637JVJU46j7Z3Qoe70fCtg3/Fbka2aUDIWb+H9c66CzgSD/hWNIm4MOleVCFavSuYJy59wZEFvo9/2a+TH77KA4UQ==', NULL, '2026-09-15 09:18:15', NULL, 'RECEIVED', '0a06093eef7efd378f516c0126391491bff4f3187af7644f7733f17c94dc10d1', '2026-09-15 09:18:28'),
(54, 'SIM-GATEWAY-001', 'JD-JIOCPN-P', 'hogm0MoMhfC4hNIvrTEz9CzMA+n47FQ616C6P2YRM9a/tLo0saSETZPcib2XVL7yS3UVtGZgj7ohQqlRf9fi00ZyKbmTnMjFub6lM6+EhUPxs4lPmRXe66bhxKZmpWsHR/Gn95tzt/7IBMNTKTomhPpP3C0hPXg2TjRsiu9DARem//3lBAF5HtIyfmajBtmu4Zy6fiNqzfrWjLqH1HhI5CzZZpCbR6Aqko3JGDfzyykyl6Trfov1K6iXOeVQo7G28daQ7/xWxWb8', NULL, '2026-09-15 10:09:25', NULL, 'RECEIVED', '20c550af94ad9f475c173aaf9339cc0dabaeb647b433197e2b10831e85c9586d', '2026-09-15 10:09:31'),
(55, 'SIM-GATEWAY-001', 'JX-GROWWO-S', '22+12IssQXw3Ha5Sjg7DKZH+aVO5DQXlFye+6PGe+SKnCMHjJ7kfgv4QtPuzgYzlq1NK4DcC4q8xAihDWzei07vI9zxpjM+c84+Wmr43aqV+BVOuaHZZXh32iare9OyvRvHsmRSlJQW7cAe24dgx2lFh/d5Ci8kdwdVvIhpiJ1xU1urI6XU477xwqhwFBP0X4TjhDyczZ+xKhRSL+jUjHmuC8dNZsxJZ+drJLgDWq0ZvIVBLWvPPlQUO7RzT6mBVEH/YAEFbHRWACcfm8jAwFw4DyhOs5S86u41HoVK8PB6tBzMo30mqOgw=', NULL, '2026-09-15 10:49:31', '2026-09-15 10:54:31', 'EXPIRED', '3046f45fb6df6bdf8054fbbbea3c87449eae1b3ee5c472b9e9cb434e367b1e51', '2026-09-15 10:49:41'),
(56, 'SIM-GATEWAY-001', 'JK-UTKSPR-S', 'wHHqrKrGpXK6rA0sNPlFv4FOV11PJ2jE8t0ZD2alLm4wXlxbMUw9wbATl9wz27VskQmiskH+FiDvZc0HU7DXi9wIjNf9egDm2hSs3M5e6RpdDk5i5GHMmjC50Qx1b3//5RWghRfirudY0XF3GXkiqyW/o8TCEVf8+GwORodHP1k8nNEWBuLw2ZQ/+sp9nqKpBoNe6Lgr+4abIUU3wlEJj48Wnquyv2NTue863lKRywmmPmQivrPi0UFAP9Q=', NULL, '2026-09-15 11:01:28', '2026-09-15 11:06:28', 'EXPIRED', '50986b8a61b54ededd8e15d6499b5d793231447e915cf9f4bb02fb0e55721d30', '2026-09-15 11:01:37'),
(57, 'SIM-GATEWAY-001', 'JZ-JIOGMS-S', 'sYdonLc3gAYuRsgUKTCNuOsdaoFb5Dbw5FeSX6ucrv13pFlaU764dgjMy6/TYs+8eVuxOgETTC5S/rfpU3lJ6EZugmA5sh5D7IjZfFrES+0UsVPBCGS7EJY9UBI1NfI/MJN2hQO3d6cAK8EYEKap2dBKD51rVexrPFMf7i+N7Pe+mBE3nXEP1aL27xcatQH43n0NeAeY9W74LHnql2lYNkl65teqWmCSlqHdFJHGxXefBxubUc0rlmfT3LZnlSr0GJqk1nGW6Q0LIhFY2HJAqn7sWt7k5yv6OrjrN80Kv1XO540ca82s2W2wYQu+oRepnow1ZZqHs/brY4UEAcWwY/5R23ueDGzV0VU9z9TOIeywdn+PRh9KnxAYrnudaDfkwDvNqUWHesa4hfvn1iCMSPIEPWdGRHniD829slYaScFBWJZj91z2ePtzPDUsaQYJrub/P77MtPcKcbltkvXbWcJ2TRq0BQFasFAqZ1arMR7DycsJkc60iMKAhStV6UEZ6CSF8NOTNcOL', NULL, '2026-09-15 11:07:54', '2026-09-15 11:12:54', 'EXPIRED', 'edeb4cca7d2b39ab20cefd6844406dd19f70d0ebbdc9348a014c8692904c2baf', '2026-09-15 11:07:57'),
(58, 'SIM-GATEWAY-001', 'JD-UNACDY-P', 'G1Zim5GvbWk1lTmQMVVqIDWycy5dsOEnM/n7iwOzTD8zcNJ0WsSnVeW9OjOh4mlesx+Re7zjMotAvzLwV0PvEYgoI9Y9RD0HRSZH+EDnkWE0ptUhJCw/WIxaNkO7i9qC53dU3ifNjt+m9b0fNkRSuD7hSZIlaCGiCdZmSSGxHvMnaZYUgF7tYFhuRxN96hGdm5Bj8xskdyFeKw==', NULL, '2026-09-15 18:10:35', NULL, 'RECEIVED', '9d9d4f21f2bd2535668049f6f47ba63b9781154733df7a73d5012514583af955', '2026-09-15 18:10:41'),
(59, 'SIM-GATEWAY-001', 'VM-TSAVRI-S', 'xU+84PhRqzqvhxuA0VvnX5pqdbjh4y+4xm3xPZ/vJ5gfX2eppYxom+kMVHdpeBv3WBX5pd1lMwC9Ix31qJnoJ9kxVlKPAaWoz5kBRadmPyvrELIX43X49KKDsrrm5dyeECgUJSbGRIoJWLpzPrO3kqEE572sLhx7MHOGViJ+V5SONO883N29qS0o760k+9su7EX+aUmbk6ghG7iJZBYTo5CSotA78bl/KkqKbv8gpmN2V6j1yw==', NULL, '2026-09-15 19:22:22', NULL, 'RECEIVED', '1bb47846e5deccc24d12344ec2208311430e128cc07f755dd01b12d1abcdacb3', '2026-09-15 19:22:42'),
(60, 'SIM-GATEWAY-001', 'VM-FEDBNK-S', 'Ez7JFseHodUE8Fl0cwJQrc4uSVnw2Yw1BhPxNl3yvGftc1S+53HLkp7uGHVrrSO9hBMHlILw4PPpffzA+mYiTLxnYn5jyDMPQLvG6JuQfhvH7BG+oPtwjPU/KiZyhq+/4wurdFLwgKTeic4dQ3wcsZzv/bK+goBRrRrf79Nty8gAYmgOn+EXe8qV7YtmxV6Y738c2iH6Ju/AZkQ9d3qe6TCMoaojoWKDjp20xROjmbXm4Fvy+fME8Gxt9/0L2ezx6P83q7bARA6vCv1rvSYgbNR7aJ1JWrByCCGFnKHnTLUAvSQXKA==', NULL, '2026-09-16 10:05:53', '2026-09-16 10:10:53', 'EXPIRED', 'cd51108a185b72e5a39d4cbc09ba9a8914f7240f216bee2e5ef1bac5949b2cea', '2026-09-16 10:05:59'),
(62, 'SIM-GATEWAY-001', 'VM-TSAVRI-S', 'CDikBWg0ilHi23l6eyFWZr9FZCnDBde0C8nObKuoRR50H9Y1dnqEQkht/SQU6dI06x+TJW8WJR/JLDpod9eQpDmHFdtQjZKpJi9iy/SJSSupIxrywwt1Cgz0EQOy4OzE/w1bRoGIcCmuCUG8WG/hKGQ6DGrozhgjwHI+au0/s5GaK04klY65YiZHgUEkSF404BZ9G8KP5wxxt7Tw3B1yHS6YFvo0nqJncLhiZCscD9tTkU7wHg==', NULL, '2026-09-16 09:20:22', NULL, 'RECEIVED', '076067e843803a009631bec733856ea7db541a185db256036ee58c4da4767b2d', '2026-09-16 14:10:07'),
(63, 'SIM-GATEWAY-001', 'JA-JIOPAY-S', 'MixF5VBrl6tzcMo0/pJKtf7jZvos0iZObFAkuCs4IoRc4LEcSKCNTyHztkUL5XVL6pYpYhhXkA4OfbYCNwDOiN/s3gTl/rUuBJIhOg0m8XmjjB/6sOO0/7EU7fWSI66moXeIw83HGvbpBDocfMAi99ftd9XFWciBh8aU7Jn5ilyhNyNqo0InNkftSrrBo12denK4jhXrLhl20NKw61+fqNbTLcS/tokp/WjBaFFWrczuvK2h4Vau8cQHJWjbvwGb4P61Zt0r0cg32zD72DgjdUw1yD0ECNcFKOqAjh+c7b6LvyA4hFgEC0wMSQeJQxJmx3qSZkYYh+lLQodScwLslAVlAmg=', NULL, '2026-09-16 14:09:55', NULL, 'RECEIVED', 'c18db18469b00e90026dd18a566692c42158c3b1ab690645311217e114ea12c5', '2026-09-16 14:10:08'),
(64, 'SIM-GATEWAY-001', 'VA-FEDBNK-T', 'bzW+oUbhRAcKQvKCsY3aREBhEgeClcpa2Li/yozuYwq7Wc8t8TrZIyBubesi4UqrOBdErGq+JpUqUJkblj1ns+SCJshkNfwbrI01J6WlXYaJa/vx3LdvO/Hd/FYW8TuLrj1au1Em/t9NScSbSHFvwvP3mPN4P6DHMW2RpyzJBiKGogViPTSnHyqB99PsTWUCjBFSOtAT4E2DTybCGGEPTj5GTok3M8Yv0Mv6Al0Y0x2aerDJB7S7tKDZDr38c/Re9cQs', NULL, '2026-09-16 14:26:36', '2026-09-16 14:31:36', 'EXPIRED', 'f394e223cf5b4369c029ce54eb32decf79de06678d49c5b81a0d9482787efa28', '2026-09-16 14:26:44'),
(65, 'SIM-GATEWAY-001', 'VA-SHPRKT-S', 'sTvCzd9t5B2W3jnjT+v+G46j2orN6qeMWhcbM5PdSxUhTOWkUgvx/25FkrCyjUAWF516G7XByQHpZd+xXd6cPiEkSCXTGOWg0pJ1wJjeqZ3zIhgovupd4+Z5x/efg8x4twmA142AAFG/0eLls6eiyBqF4GAMNTBn35TGQ7wsKtFkGgFKzACTevS/P6tuy5q8oEGbaHTjrfu/qkDbohmcBVl5P9m8xuhn0DNoyg==', NULL, '2026-09-16 15:36:36', '2026-09-16 15:41:36', 'EXPIRED', '1664833319f484e3a7e768c9ba06b97fcca1d80df06e47f85a7a993099f38d19', '2026-09-16 15:36:43'),
(66, 'SIM-GATEWAY-001', 'VA-FEDBNK-T', 'jpvEUCWgtPh5tFLzIzzxRAfnT7ONbFjezy99m5A094hnGsPX+PkPQY6YyTRhysiCCm/NshfEYifldGUl+mochcAZ6fs5aJMxgYNmjvywJfmPQa2FcwWBpigwElRGn/MQzgnFVCg9VZ2Q9sx29xt/3DVXXhe+FAnGqh62SSfA8KRivn3oY9NRSZjKHpcWmspJKTRsi3BTLCcHDVkbAllvr7ARFkmKbSJqLGG1m7pQvHWQlmLp7wANu1Gs+fAzIFxEjvw=', NULL, '2026-09-16 17:28:11', '2026-09-16 17:33:11', 'EXPIRED', '03e28f3e95696d702498f9096581b22e0f5f504ac261000a02b4802a289942dc', '2026-09-16 17:28:18'),
(67, 'SIM-GATEWAY-001', 'VM-FEDBNK-T', 'SGSQLRGxB3gRgoykmzS0M4wt7+HIqJRDmRXHb8k5fvN9vpZqq8YE9XtsP/3M39R3ui03/ter/0KnK9TdtbkKLCtBErXg4r4SFxk0jxYN8d3YjzLMajaLqOo9vn6jztCNLtg1oD8q2MNOKQqlC9JnHiHV1WHFVDAP7EytK2Rh2vPdZjt9/i/YP7R4M/QjSn20fFyVbqoLLO8q2ZzzJ1gTyDNOxD6vJt+UqxnOoWJOOcQHk+OiDpsabOo58t2mmQ==', NULL, '2026-09-16 18:02:02', '2026-09-16 18:07:02', 'EXPIRED', 'd94e7c2ed0f666d017e5eb5b4e8ae74f68f43a1dad16667f829b7451ecd70356', '2026-09-16 18:02:10'),
(68, 'SIM-GATEWAY-001', 'VA-TSAVRI-S', 'anRDvdcJ/nJTZ+TpedKpYoy9Nxf3i57FcI482+2CzQbKUjRB9Fq3gOXPe4D+arBvHvVzzGPb5Ig5ft2QsrMAuvDKKr3Zu1tvDx3ru53RZ/YJeV3qyB7pgSpWP/7gT2jeGV8TcwkctMSfjwsL3HmoyvaHVbN8nKx7dpBoUBoAcSA1OGy95IR/s768DRhIMxJ3u7exW1Qgd9I1L9ZvIGCMGUmExxxzeFP8c/EZgXCNZr2ojYYtNJVi', NULL, '2026-09-16 19:15:12', NULL, 'RECEIVED', 'db5459ad319dd142d8591bbf838c8b2cd4668adfbb6f0de086e0f54fb75fc394', '2026-09-16 19:15:32'),
(69, 'SIM-GATEWAY-001', 'VA-TSAVRI-S', 'MLZ8V87ZAM3e4orBDtMK8B39drGnQWKwRQzzDgU/lUE8Rj/V2EKyouR5LREazjcnUClHtUXobWDSpIa/Gn8QA/TKWjhzjuxpm6avXvkqd1sZUIkPkmIwL+bapMLD0PQSGjG0uYhglCxt6pwQhLbpNeE79t+R1Tc25wIaAsnfGRbRc8cy5BFI4jGnhwgsZwUJnLnA9D8rR3G11LcpMhfPuQT4LOOlM5SkEE+X325JIpF2ksLq1w==', NULL, '2026-09-16 19:19:11', NULL, 'RECEIVED', '66265ca8dba04a8da2a2e4864b73e24352364114d36c85acaa16ced4d1f5522e', '2026-09-16 19:19:22'),
(70, 'SIM-GATEWAY-001', 'VA-TSAVRI-S', 'DjkGSd0rwj1xwmVQ6qi2C2gNsCMut2Dm5Ysy18qh+YmSXu8PzUG7AatTdqMn6y0OeCK2u9GI5LoVTD/w0QweV4KoV2FTMFsIjLYPKXd8bFWdLCn3x0NcY7cP9j0e41OkzxqI4RabWEQeqq4jNbJMwyND287PKWRtaqoG0FKfr+kjn4cn7CTA5yelGscm/1BNJcJsODHHp4XFbJCrRIvxeby9yVzoRFfIyk4oQUNUrG8lIudYNg==', NULL, '2026-09-16 19:21:13', NULL, 'RECEIVED', 'f8e2206beaed70b9368381d0816b5889e97f2dd2bd8ce3ad9323266f43fdb9cd', '2026-09-16 19:21:22'),
(71, 'SIM-GATEWAY-001', 'VM-FEDBNK-S', 'GUguNnUxHLT8njfeX8Dh/Y7RPPsti/W6aGzo1o8o3zqSlaTYplh9TyMIk+mSvkrVz5WmGpImrKOzZw3QFtbpw5BuY5uFRR2pHfuYQNyWNllkJiDFOoOhaal2Nck5b/ZRl8bZSGjqQVWQYW+Lw5zaflM85M63c0YePFDeDzU9rOxKinckCV7dqYhpD1cqoM1v97wAIsrG9mBkSkH3KxIrWx7NxroIQhuIVryRCyc8qctXt/p8ZTtLDlI2aSDE6y+yMQG9egIUARC7jQTmQthnWXYqgDuPmCZhiDgWxpN4c2u6Cg==', NULL, '2026-09-17 12:12:00', '2026-09-17 12:17:00', 'EXPIRED', '10b1b5e18b3c40bb089c3d0398a594724564bff40ec0f14610cc1a243f96daaa', '2026-09-17 12:12:11'),
(72, 'SIM-GATEWAY-001', 'VM-FEDBNK-T', 'sCdeWfOE/nzLjnxm6rmGnXuqn2+E4ppyELUrZ0kSvbwOG4U2Jdg+rH3rHT38Hp8wtWTvmCJcVx9gL/8ioHy/iAz5km/pZCoO5KbluQ9ZHWCKW3Ihaqgd3qQfWn7CbKdOQuqSxIavsGkRFKwp6o+uMJRyKKR+c8yD9iHKoMEtRoeLdejQD8VMm6f56+yU3TtTguNdli5oaoXAjdv2mHH55kROqhbWU0wPj68838M1P4E1TM9ZEnwpU+7Suxj3xD4cNyzT', NULL, '2026-09-17 12:19:17', '2026-09-17 12:24:17', 'EXPIRED', '7f0907e55829b76ce1629064d416a1dff7f161eacdab4d4bcebd259f067b6993', '2026-09-17 12:19:27'),
(73, 'SIM-GATEWAY-001', 'CP-AHAREG-P', 'ErnqVivLqjXaX1zSaUVgY2fJWLzkUstehAD1VzBoOrxY4JB6kbiuIjOgP7vpqQawUpeDekTyf6ulSHEAgXVNbdnhgl0Za+/b4+bQZ2oQo/Prs6XYH5/5EizQQ5d8EdPeYW5TXOa3afJLCHr9GRUHskS2NzU0CnwB1Oa57JJxLbC4du26VlVptq6r8p3R+f8+G2adcyA+Ti+80L8ZMQ+tV07mwqcgwUzrWZMd/GFjiAg1JU66pauww3YXaqCfB30KQqZK', NULL, '2026-09-17 18:21:34', NULL, 'RECEIVED', '1fe131f0cd5fffd4244f8d517320eb296167d6afc59ede19ccf032d588597d42', '2026-09-17 18:21:39'),
(77, 'SIM-GATEWAY-001', 'VM-FEDBNK-T', 'ZjDtuWnc1A0fiMVRf/rvMnC7lqsQie2FXBGc64e3IOk8WD+FVIscn1AUBeaK9oZZ2EHnBE04jlPlWin1+R9FpL8jHAVAV8diuaRslx6e8XD/ZTrVl47vUnjmZFB6NwrgO4VxD1K/QQ2nBNrY60B05RU7HV3vJTBGbiaw1y4sS49X9Y+pRWxUjrQmAapI3cnVUNRah+JzUn3kD3RuSMziyvuX757T8LIYKh9ERQ7bRhfvWL4YWjBfbW3GKhhEyH12ZA==', NULL, '2026-09-17 19:43:13', '2026-09-17 19:48:13', 'EXPIRED', 'a12f431770a26a39c6301637c049dfb481aa45511b680075caff2578043ab781', '2026-09-17 23:02:56'),
(78, 'SIM-GATEWAY-001', 'VA-TSAVRI-S', '3bscdpAWxP8oANpd1o1dan2axedG8NiiqzgNkOK7cW1pPMv7yoBJYpQ+q4gLd1kGKd86uL0JkbTDQqH2WU+VJztS3avGTPdQfk6L/ImugRK9yr2vs7BmirGWH75fYtmXF5mJg0GhDhbroJuzOJ9Jf2sf3FDsDI5fUVqLc2AJJ101MZ+zLatnbHEeWPsuKnTKTV9VgYQdklg7pxmZc80NSdo29gFtFgSjo07uIhIvfny0fJLhTg==', NULL, '2026-09-17 19:43:07', NULL, 'RECEIVED', '2660d4cf794d6a23831cbd585ff6ca9252d26f75267dc3795b92aa8dc486b9e3', '2026-09-17 23:02:56'),
(79, 'SIM-GATEWAY-001', 'JZ-NDMAEW-G', 'Sk2gbjjUzkmZp4GVVB4ArxPYvFCWZYFwNuGJ1b5f5+v1XQ6UT6S9FWGUvyaKFNi6PGzDn1pATScdjc7qsenmTFgfkCb1h6ys24k77/KSKkbNXENGt5fwIyZvD9V++WOIh7QQm0eYXr8I9deTVZpgCgK0Qz12rW71Fy+A0ayZEViyZ+MsHqfYKbaDi7pYgXFDMMyFCBByMtlhqBTQxle2ZybDpzRIwZOnv1330muqpRtsgMvzJgcaPhh84oUSPdLuFJmbyuzd+qHDRH8pxeHAKSQDc/TMDyrontItSQGaDCpVjiYIIDf2pnxb8VW5v8T5tVa6EbK6DakxOlCblGYEVNE+JCQm2LsMXJ5+z2AZLcTHJ3CRM648+3LI', NULL, '2026-09-17 23:02:50', NULL, 'RECEIVED', 'e479030f42c9983339cf29661c34ed37f58116841c3ebfe7cc395984e0435e31', '2026-09-17 23:02:56'),
(80, 'SIM-GATEWAY-001', 'VA-FEDBNK-T', 'om5rb96mfc3tIPH2ED+huDf430J+re4+5d+O8q8GWnHGBf/ak2Fs5VcUfiY9K0pzZ136XLYsx+pshnYWMVtYZdtcI2dFHRA2Wv9RspeJzWsdksf60/aNkjR4cdlpHjD/4AO5T2DqAVuRBfFLSIqH1f1saZqhkQXb0u1nQsZW73bLjCXLZdwbw6/o2+AQfalXxhpMzxM6CAptHPjxVqdbgsxWzciSfdzZ+Rv9dgPOFkkXjmloCl1MHbGJlZAcXzC75l0=', NULL, '2026-09-17 20:52:47', '2026-09-17 20:57:47', 'EXPIRED', '2b88f001bc1e5eefa76c1761b28b11dab33245ad94e39ee41c6db40342573a98', '2026-09-17 23:02:56'),
(81, 'SIM-GATEWAY-001', 'JX-UTKSPR-S', 'tUyFDiDI2ZsIUN3kVDVg4XzT90umDkyKK1mvjUqFLAybL/32kO4FhqQVSn0jK3I9+6p+a3Hr25INLmnMSAjxwibJSaTFTR0+AM0T7AH/ufFwlaHljh2ZsE5Sx2ZsUMoOCTfc5nb5e/VJyrxRsmUzOJC5MWr2/cQ61PWEP1AgEFlFDs2qNW3rKPM6K+L/gsiYE/tkwJkw5p3KE2F669EkVjZbOM4a/ITIiqUXF40z5HY=', NULL, '2026-09-17 20:52:45', '2026-09-17 20:57:45', 'EXPIRED', '6a7fea500e3c9426248f5f9b2436c17ddfbb82ad05e3f39b20c6ae268671d36a', '2026-09-18 00:42:31'),
(82, 'SIM-GATEWAY-001', 'JZ-NDMAEW-G', 'vetXPdZB8mmQubK1IQox++TKxHIWsjECuZvk+ZjhRYaTGYB0c+7oZfmT5afd/CoVJR0io//l1xpYrE+Ta3BlauEmvQNsmGVNcxJxeZlnU2CSi118qIefaqlFHKj+VpHkRbr6kdd18dr1X3CB3K/a7loEtl++M5Ad9tZy2eZfn9Tw4OjRmhP/sfL/Leo55pPga3c2swUUZHW18Pnyfdj6AJWJ7YuFJXVZ9HYCTnlK5T2gKmt6gWZwH/68flB80r9wL7CwtXR0u6mJ2Ip21ztNzqsRQMmyFzDZ3yoS6aWOsiYpyGG3xMUJRLyQenTK2ugPHN8EYLhNNCFdsiFt8acRYhvN20fmRY38sxwA8gkrfA==', NULL, '2026-09-18 01:47:00', NULL, 'RECEIVED', '646385a1e7e3f5a8d5f07db59deb63397717bffe7d24110f898ab04ac788baa9', '2026-09-18 01:47:04'),
(83, 'SIM-GATEWAY-001', 'VM-FEDBNK-T', '6+wBzSTOK/ajOrmNlA6E2PWWE5Q6JmGGRgU+xUW4nXewG7/6qfZ2jPEPZLUoqXVFrsO6mGOUCqoPx7D/GfKHLdw9GlqwAJ4LIXAmxIKQURl+pQz8dpN+bOHc4IjNuzCSXFC16YkKJmGZlY5chwGvho396fO8YEOZrYDFWaQ8K7/5GKbmaFhIAtF4/H4X1595YXhdKacUvC/GjnD5AdM1Cy+7zD0hWkZHBXRDauzAcLmqkt955f5C', NULL, '2026-09-18 09:34:37', NULL, 'RECEIVED', 'd55f52688b4272c141bf4f9f530f5ed0e63f9dcf527e75bf048b8a596b44be30', '2026-09-18 09:34:50'),
(84, 'SIM-GATEWAY-001', '917672006760', 'f6kxW+Ff4s6wFG1m63s/bhc9p+hWxSbhLl39o2O4qQlE7YzGo6n9AmvgAJ+0ZnAr5oBp9R21aLsCdtSDw2rAk2uUf0NIrP6SSfJ4PV0xgFuxec19JrSCwVxZHBubp4Ww+KwgICmXw0T6JJMCmcpc0AumVpsHEz2CNReL4PoVTCs6aa+IaoXgZ9USgj57bkSPGQhkwU+sFcPRjAm/Cl2wxWYI15cT3lcfg8om3wdCQdtL', NULL, '2026-09-18 12:13:57', '2026-09-18 12:18:57', 'EXPIRED', '6e93ee4e18ccafb5b2a8876b48b01a0319e77d007fdea0b60407566b036cb8a4', '2026-09-18 12:14:00'),
(85, 'SIM-GATEWAY-001', 'JM-PVRVIP-P', 'BwFmfiiwuo8pofhOjsc2ZY4huAkf9UkynCo84JU+0PuBOmuOsRofcDLa1WQEaHfYMyCUeQ0dfRuT8x7aroFJWOjzwXLNK0pMyOW46pSV0DG66DEz+yweP0ZATAlr3budRbodwHlXqpy0ldY1cugSEiwE1MJ0Kmo1MOdJOobsEwSNa/W1hqx2H616h8+xvKToRj2ykORdFH8rzMUtYi4gM/+h/KxpywFtMQHIZ3vo0z3IkKJJKaSe', NULL, '2026-09-18 12:57:14', NULL, 'RECEIVED', 'b05e90bccb2142065d5d82657d646464fd3e68adb378db388a801930bba465ac', '2026-09-18 12:57:21'),
(86, 'SIM-GATEWAY-001', '911408367661', 'qHIQnknNs1jOUrOrLiOLGEzlP63JNKDBAREr9AVi7EiQ4HiYwArxfqAI+NQ9SH+wS9wUOhXYO6LkA/1nAcBBaR2VYdb0J/nrT/cRmeG+SF/BwLyt3FmH9mfyFM6kpHvsBfRNJalBx84Td9B2Ia1uOiZduh19VnqmMqKDMejFC2GSI3mQJ+BejMbVuIB3MzvoLXdS6X6lgcyfX4w1s/Ch4xCSOsIw9qIPwAMEnlUg2GHA', NULL, '2026-09-18 12:59:18', '2026-09-18 13:04:18', 'EXPIRED', 'bd0ea974878c42794f80105e30e51176a393de1ef62c049e2b24e4235199627f', '2026-09-18 12:59:19'),
(87, 'SIM-GATEWAY-001', 'JX-UTKSPR-S', 'B0etG3/T3lfC7FFIB7nQusGlZPfg5RFKRxUDHyiYvOc8XPWp+A3Ak+ZqeikaR8LSnfQQaN/k2+BVcQif72BOAq7zep9rFfbxBs0ViCQ8XRf6FKFv0dJWmUfnwKppgC59VWITfqyHY+gfA+19CSJMiY0+7Tpr132TuOe4SUTBkP2qsEgd8dtokM4CkcwIIL3Zbbvg9IFvsGOSUtwEbZPa47JrW0Dz1NS+H4qX4NFvqDkpZ+sJy/I=', NULL, '2026-09-18 14:25:34', NULL, 'RECEIVED', 'a508beb4f90eaa98a6b6e7e1b7d6d70b966ad5fa0a9ae3d03ace20f547b36077', '2026-09-18 14:25:43'),
(88, 'SIM-GATEWAY-001', 'JD-JIOGMS-S', 'JjCZd57RL0tuDttSvdyTyzFhmsbzCYUOEO9OfiElmeS6Yoi+o26oEnz4m4UdAgK+SSNvWjKRBiy/oj3TssJlcv0tXpxKHGQxxI4WE4cw0iP+Ko10lnofP89skSJDV30HLfep+yyxGetw9madl6xtlXSnqJLQRwTjGwSc68Kh1zHTpRDxy2d362FDIpraN2rKma27aPQuf4SlTZN5g7L5jXNB3Ecb7oLAMIRszUwVLCeZv6K34Fuiz/VGVRw=', NULL, '2026-09-18 16:44:41', NULL, 'RECEIVED', '164711a18fcd1cab648ee8e75e93a6da5d18109fefb348f0e479c6571b8e8d2a', '2026-09-18 16:44:45'),
(89, 'SIM-GATEWAY-001', 'JD-NDMAEW-G', 'I2bY/NjTVIulcrEyQcKteILmqQGQYN4N5aZPl84gLmvLJIixDOXx4jqKShhCzvNyJrgQWVU6RBPlBq8jVNRKSu4KTy3XPUPXFr6tfJg/prtNPa06yiPATWn5CxYb37WkB1El5D5ehb6fjPyE6NpZLAbUjxHvCLYT0zaD24+OrGC0iOY2xsSrMzBB++a8S1tujFUIoBU12g/2ADHhrsZ1Pwg0wnnRJDfgUOG1RG98TaX0/g+Z8NuY7AFjApZahCO1Y43ICghwyXDfHmhhbKKyGOopYzGkxvj4zya+pyOXsrEXYx2KmeGZChZY7b4vHTYBcKKw9w==', NULL, '2026-09-18 17:13:31', NULL, 'RECEIVED', '539d03f5027ff20815db212315559087bbeb5f99246d9e970e83270ef2e40000', '2026-09-18 17:13:35'),
(90, 'SIM-GATEWAY-001', 'VM-TSAVRI-S', 'NAUk1JKhIf67OhOHps858VuIZoirmv11mmmQ6R17ZOPTv/2PnyCUWtFMsTmAm3kC07EJQ4KYlMg1u9Hchug6OP0gkgM7s1CtIRMT3NJtl8QmlcjqJEJ0+IyNFojgz6UH3ZZbTjap8W5CZ242f/a6mG3iPkBa4XuA+nTPraZorib51YzA6pA0oo56Pp2Q/w1UWb81yY5/Nti6K1z9FY7kE8yJXnflYsp+5WmTvj9JvpMpwKC4jA==', NULL, '2026-09-18 20:15:19', NULL, 'RECEIVED', 'b8c5fc3015645eaa0b0a212cd0c183f872dc2721dfa9c5e7015b7233c7a6d732', '2026-09-18 20:15:32'),
(91, 'SIM-GATEWAY-001', 'VM-TSAVRI-S', 'zUOQ4ZezL0hhW8XguD7pAE66kQRVyFZoyHX5dAaYuqKnOspJI4MJjxmYxfcLJ+BWKDF5YNt86+IxaginTbT2PrPZP9fwv7sL3VqM/h1Hrp04G3udp7x0sDVlIe2emuNV7+Bb1smFXvIeWtbGjLF7Yi84TOZFJEcKy+JOX4dsjC7sTgkaM61E6gZE0JYf8znT4rpoZmTduEkAXs5Qeh625V9pGyYbBB5BovX/xNZTTnhNPagLAQ==', NULL, '2026-09-19 08:49:08', NULL, 'RECEIVED', 'a92419b562e83e36b1d62f7969add81fd595f55cb75f5c73ccc5bdf0f35dab4c', '2026-09-19 08:49:18'),
(92, 'SIM-GATEWAY-001', 'JD-TVSCSS-S', 'j4BsTg4C5G6DjOlNxhZVxn7agct4USuSZm/9yO/NjELENE64rCTezRnu9N8zQ3f5iLioMbGzee/zJAesJw6EurLZ+3gqiwfccWDlMrVRBpWyOPAfkUDQCAOL9Xmvc3Gxrs7Y0iqE3jDK2jzOaQ5gPf8ktP/Itd+phyZIuUt47JAxG9HZIXMIkIIC5keNuf2pMlZmZN5TA/fBfzyQLc+m3A9tJq9vr6WDlp0Y0Ka1wWjVqUJphLueHHv21KxU', NULL, '2026-09-19 18:13:49', NULL, 'RECEIVED', '30cb75a196e498ad7090f956f3048bbd2afe961a945b282be06ee24c3007bf18', '2026-09-19 18:13:53'),
(93, 'SIM-GATEWAY-001', 'JX-NOISE-P', 'krjtA5r34KYsB/7ayEEHciuS0geN9h9S2jN7BQHQ3Tldt46VhP39peJ1U8WMtscf16tSnJZbt8qAX1jZmAcNq/8K+aJRWj7NaifZGV+wfuEjdzRhHSHyxs3P8JTQY5OzQuaDkbc7HD9QhiQOSz5rqfE8j2Q44kE3z2JtGGJRpeHyNKV+sPqUToXfQu0VkcZvzHUlkDhCKuQvEjPjYglqZmrLyVMKvqnaz3lUUDFQIBJliGFnC1/Se2BLLj6lWyAAC8IOiUrsYL4=', NULL, '2026-09-19 19:10:00', NULL, 'RECEIVED', '1501a63767a77ea6ac32e920d0f93533180bb4d05d1a594d80aac269ebcb856f', '2026-09-19 19:10:05'),
(94, 'SIM-GATEWAY-001', 'JM-TSAVRI-S', '+dt+QzehCDR0ji2tbl2Ayt1YP0xdLaWN4kdBHYvmpX7axX0mGGCYUGIpfdCRhF3wkHjcre+1ASZQuurKDZTRWkXGtKvLsuue0hhYPD/riSHZYuJEKRBdTSrPkfAx2b7s8t58270LDVuqAT1jQ6f0KxhY7ONGVTik7wbJtcjtboxi4fk+flsSJGOan/eT0ld28jcipqEeslYTDpWrI5/30GeKKSfPT2DOIJXqjEQrfcC/bxDNLQ==', NULL, '2026-09-19 19:18:40', NULL, 'RECEIVED', 'f741a4b6b159a73093230351f3378dba892b3aef20b72c2060b165effe3f70a9', '2026-09-19 19:18:59'),
(95, 'SIM-GATEWAY-001', 'JK-SBIUPI-S', 'kGD3H+zqMlZdul0OYfqJjnBnmOApbnKQS5d501A+ez5gGDEwWmslCHPGKJsinbPvNjf1MVbZeaMiGZgHn4XVk5MfY5bGPnngQokrr1T7KgVUTPqe734S0ZV/tj9MP1aklAcuCIICWtRjKIbygJZYXFSwVneLNjq2/R4ELDV2PutSdRyGpilIKPpOJR747cLl8mu2YC52lIDgwMRtj8JKmodlPYjs98zZwpjMY6RW9alpSYGJ0RO5Ewqcf3T6/eyyycTw3VrJ8GraGW+0IQ9uKv5hpYv00mMIfoc=', NULL, '2026-09-20 01:35:03', NULL, 'RECEIVED', '3fd3121cb1d54de48978cbb1818679a12accae3a7f63156e278a8b6651a8cd57', '2026-09-20 01:35:09'),
(96, 'SIM-GATEWAY-001', 'JG-JioPay-S', '+FZMXcP2f3G9kjNGvAcX49AnxndNGvtTzTjNs0rQJ+Q4X+rnKQ38Sz7uxvapIJxMs3H7a/qL5TneSIOMgn91TT8WbE6rHRx5Zm15WSksxPE0uC5XM32GnKZ2xCGUOmQpRcniRZFjquT6Zwsyi3ckgNpyqmTdegXqP19fakT9Rp5EqEFnMOMwxL4DzK6f0i+jR4VlNOu6qeszHKvbOKQ9B0KgSyPnrrVZPi0Era95R9SKM6cM7iXvpzw9C/xshHOJbrt+VyWzGy+BSYCR3LP96OmZPdlbszUz/Fres9towoyQQYjYKy9uIAhV0/8sbE3+Dvlrg0w21vJ0Dfe6WoyzAnwNMsrPOPv1LODv34HJbgqKpq9FA5zxNcratL15FZ/+Gv6f2qeKXJjofgjBpwC68jdB1OQWMYDWKT0gXZwiptFAjO22gZt2vucfKJzSMB+M1hDdEb5NP0lOex4NOmhtmvA9RlZUsklZvKRb2yqbSvb8GE1GYvcRrd9KMO0z1Ip0j0owiW1Fh4i2YrntKMM8EqLhjAG9tlFg9YRyBMgLQ+UJ0brzGyGkSL2A4GGrzkJRJaCCm9Bsy8GdQ7SWygDkwANbXkmNm43q7s2xZgoZU+q2YYqupCwnP60QyqJqG/Jiz5aYoho89zVRL/UPIi9d7f2dlJEP3X4xeYMhrK+IZ1pDfmgJld8DnsrOzCtQiL9XwcYi8D+0UUq+MDtFqe18KWHVEF28W/BdsqpFBgYRCpM8Qdq0u/BafdFcrE0=', NULL, '2026-09-20 08:46:15', NULL, 'RECEIVED', 'feb32b891690a7b98ffde4e1c937637c6ba2a4ad98001078e9d96d9d392aad55', '2026-09-20 08:46:24'),
(97, 'SIM-GATEWAY-001', 'JD-SHPRKT-S', 'Qk7zsYR2wh3rgSpvVbkDnnSk4F0co4s3M28+B167oMeGPovS1jd5mdpfcD7F1tmx1uQHqc9uneZwLTxgDouVe359do5uvXVWMHYRGF3vgjjw3M7ijraOErdUYawArYyOAiVR/bxnee0lMmyqjVmBf042YlESSAAV1d7BfDTfBTQCs1TXrDnwIf8liJsHXJP49lJuvoVYYq/lTLv+2RIWXh9LqNuWRojtqVBd7Y75Vor19BbFpSsRFvKlc1PG', NULL, '2026-09-20 09:16:13', '2026-09-20 09:21:13', 'EXPIRED', '88d6149b84cb97018c8fb33bbc5561879acb8248454cb45aabdc9f4ebb005dee', '2026-09-20 09:16:19'),
(98, 'SIM-GATEWAY-001', 'JZ-JIOSVC-S', 'j/VhRAdGC/LlEB8tyVm0HfnhM2W7FH5bFoWyGuGF+WYfNPzLZKnadPl/CPM4TJTDKmBKqpBwh5MU91o/DCAGca0BEK+9uxRKlyorHFJfg++qguP86e9KVJZrpz6hAmpLHNkzQM9i0dF8dJQmt4OWDYFyMKXmbXfVK6JC3nKOx0Tqq3ZkrtSdkV5K1LUa9u/Khj2pByRUN0CaPOB9TPoTCwz/Z4sVkN8/QCFzubPAgZiqoOL5srA90bB3aXbwTjnx6bFyobiHE045akb6O6Too5rdMqPdBm9yfK23tzKKBnMQROy+Gb1mZ0xQyF0VF5keF8VdHIxpI4uZB/ouNt/Jn0Pv2Cz5Df2xsqpboqvv6oZaJusnj5Qo0BBKLlURylSHY3+IHf5e4SWFxZA9MG8hs51f8wz5y2Xl/oufo4Sjhd+RIbjrrIriBvesuj+cJeUV6/LJQD/zxzUyuSCRcyrxdr6pDH+38hB87CX/QwLHD3R0wT6CQzvIuzAa8ewRR3gOn1s/Sio23wlBBpXCjC/G2R5PJO/9KfWrb5XBdxA2NMY1CuuChUD71WiAVcgTfZJhw1duu5UnfpmgeoNyvl2RQUHe6y3hQ70EwxA=', NULL, '2026-09-20 10:32:21', '2026-09-20 10:37:21', 'EXPIRED', '2f27445bcd57f517b94d75af8e729d80c9ec457e1beb361de22f294808e97281', '2026-09-20 10:32:28'),
(99, 'SIM-GATEWAY-001', 'JX-KFCIND-P', 'dF2W0BxEjrAgVggFb811zrbHMpW5SGzSo7sNYbbGYAMTq0GXZscF9kNc+LtoaGlc6Hv3HVIQyDtGiQVew8EJGpPBzq6PuCNW/H1utmllT2Th2cWXT3HBdvqxapl+FhdW1+5CNiIUDVez2M1gnptz7klLaNha+kc918xEp/tFLBc8cnwkTTP09r604kKfbh1GfeP1GT+3EtlSzBETNzELwT7F5bjt9/O29jBsE3e2LFCeE+4h', NULL, '2026-09-20 11:42:30', NULL, 'RECEIVED', '0f8fcdd4d44b653cefe804ac2d2da50382660ae70ed5dade4573bc9cf6f6dad3', '2026-09-20 11:42:41'),
(100, 'SIM-GATEWAY-001', 'JK-SBIUPI-S', 'lEx8SEmRYlBolaiPOwctCM2Yw9LJUfXizlHUrf1AxL80QKW1LH5ypdy12dyigbtSrXvK+qaiAIcvLkz1GULFV4OLlnRnfGlaYsJrpsY1WjwFmMcFg1IJdY+i/hOfb/ueENC28WaKqhIUxnxVGIvXTi4y7d5SXvfV9I552m0iM99gmSTLCsqALfVwxDdYeIFuxB4Vm/PdIhOq8CHvQ+j8NizvcakRBOiCdEJ60f5Tm4L/lzQhJ0WH5jDLqd/gcANth3JdU2y6RwUT6ow=', NULL, '2026-09-20 11:50:11', '2026-09-20 11:55:11', 'EXPIRED', 'f127e5ba3dfcb84d8656579314516b345d4dab90310f1d254aea40a29cf5d6df', '2026-09-20 11:50:23'),
(101, 'SIM-GATEWAY-001', 'JX-NDMAEW-G', 'bdBHR+zdx1+Mx+Cq2qajPayHhD3qq8+ihEK01AYE47TSCkH3rxJOVcMTDk6w5VgsujG/vl0XtVsMzB2oDoulmrzubJmPWGt3rFV7rTZ1/3x3gdxS8RPbpgm1KZBiqMV24WQqSCzWv1ZLklAv5bgO/2PV4FxlD9Jjn4nqDJdHNmW1zMoZ7KXKZLyfgXxZoi8p1qg8+Ttyy3ekCdre5B56Y7JKnYw5arQm40NKds/y4ylhTDfD9eWDfhs7cb3rx5CpQ9RP66SOH7aOnTx1GOEyIu2MyicCHs4trs9hvStw10+leExISAe0CE+5NNTmlpxjtr7tDvooQCuilLEK+Sm1/hdkurdJUb5MfvBXaRmWYHgg++JfTQpzLlJ8eq3H6CuXFZWUhdBB7lJ3UtciHw/0', NULL, '2026-09-20 14:59:39', NULL, 'RECEIVED', '1da9baa0fe2f5364b7310a2217d99d986c5db3c3edf65634907888f71d594323', '2026-09-20 14:59:45'),
(102, 'SIM-GATEWAY-001', 'JD-JIOCPN-P', '7GD+uNAZ3ViA87kt95dxLBamJZsRF24BjSFNh3sNzuxliqVlnatS9hcL1B/nFYjzPfczbuNhI0zoQ++qTEbISR11Vu9dk5nVD/RBStU3FN0l47Id3/9hG/WlS5MZ5HMi+dd4OmIngMobDtBrGx+lhs8LSm4emIBE7yJ8ehp9SZB8iS6TQqgO4XBaPFeLsN8i+qZQSllhFVmAa0Dwdng2txK3TCoXRFNj8aKt/RLWnT8XRT4PdAnY9p8AntA=', NULL, '2026-09-20 16:48:37', NULL, 'RECEIVED', '1fd59db0b6dd74ff521e54486269a36fdf56d9d48b3c26b67cbaeb578baadd3b', '2026-09-20 16:48:46'),
(103, 'SIM-GATEWAY-001', 'JD-UTKSPR-S', 'A9f4A6Ahlp5NH3LOLzcziTW0SmYM7X/J139X0Iu/21YxcTzdAr2Pcy8QD9Cfm07vbGqP3KrOshYj2ERq4MBmqStnoiRqajA7pA9nfSBaK5tcpGMoa3CYffTsHI2Ardwy6w9aOb5TroFoERXw+a6PgnE+ADEbBQPRtoa52eNZ+GKJZJfBtLBnmkZn3SIpFZTaQum3JTJNI939nRf+3EBCar1icoOO3MYl6kJ73Mi0asKi9nH8PsFY6ksC7Dk=', NULL, '2026-09-20 16:50:58', '2026-09-20 16:55:58', 'EXPIRED', '32601a98f188db70661be7d4b68797328450373f389897c43f006b172a6f0e2b', '2026-09-20 16:51:05'),
(104, 'SIM-GATEWAY-001', 'JK-UTKSPR-P', 'L5iUhJ6oMP5ise2xdR+fJyH21XNkTcC/vg3UWLJLWPm6cVtlrebdsZfFd2u6T95oSik6CudY3HmJFxnqNrbf1IRO08OMoDevA8WNtbOk+Tx8XR86k0FRGkevv2xj2BCMGguQZseoFfMPGt58AGjuwiZDLhQdR3cpRx796qiaHYI37o5tpCHX8nKsfGhs8fxkxtaXqUEPvEYszz/AvnBCsAgWtI47aZq7R+1qoyZHlfHdmdg4wGCIs2LhM/IfEPA0DZ88HOFZvRWS+3qrjg==', NULL, '2026-09-20 16:58:48', '2026-09-20 17:03:48', 'EXPIRED', '1d32f88aabc14b3bba14d91f13fb996615e2ef1b41546db1c6303e5c3ffae547', '2026-09-20 16:58:52'),
(105, 'SIM-GATEWAY-001', 'JD-DLHVRY-S', '2PlEphiEmyR+zxntQKM5EBniHFgYpQ500apSz/XMYltci/8F0y5WG42WEab9y/f3o687/H9Qd4e1m7Hr30YSM7pNf6C2iM5SnuEXfGHS37CryYTslA2Uzd1ZLmwNdx56BPjGtHFffyfRMMd/RSqsnQsxiui+IyCNCWKQs/in9p0BivQg053jRUgbrIENqhLLCpnbFPL23Pwh/a4AT0prvSiAx/JL09TtbmVcSwwnGr5YEOEGlTIuc/9B3/dNq8LhmA==', NULL, '2026-09-20 21:39:58', '2026-09-20 21:44:58', 'EXPIRED', '7b34f26ae650257498820240556f04f4b8855c78dc9a198efdcc4a023473709a', '2026-09-20 21:40:04'),
(106, 'SIM-GATEWAY-001', 'JK-UTKSPR-S', 'FwnsBihnOsRIOHRP7JpXhSvi1nDtHBuIaV1vVHvnrWA3BWizxgg9vA1bRuMLL7vq+WDTQuL6zwCQ/uzejwFwVNwjvVVlgnLee/8UHL/m2oCGltAoftAFICG9ipipKMkG308vrav3JWCtzj1KRDLaW0zvNzuCDdHgok4TA3vplI1F9whqKiquqKKgKXT5kGsU1d17dBSVvqE8MBboL519TGM+r/L54zXinXRoFz/ltgA=', NULL, '2026-09-21 01:38:04', '2026-09-21 01:43:04', 'EXPIRED', '559845222902bccc4d5a87a986ecb74c99a9466e279d38e45b5bfce94921da08', '2026-09-21 01:38:18'),
(107, 'SIM-GATEWAY-001', 'JK-SBIUPI-S', '7hdIgI+1ARfY+3k/W702TPRk5RsJnserdKwnwmaYDyg6yIh1GO7lakFSP7lKKCc1rAOAdOMRBW2kAhCLyAkelYUjZL04DsPU139ZBxdxGCFhVInsN2+EwGoKXlM0ENFQ/ubvhjwUWrZDz9Egu6C5VaIKki1IhJXGgwcY+eApRTUkVnw0JjW5WrED6hEuwid4ID7Z790a47mE+U+1gGF+Jrv0846iO0lRRb7ss2qAiGeaH8ksnwxrGHjPRjS7Ud3IRrqeJj3zc9zeeYA=', NULL, '2026-09-21 01:38:48', '2026-09-21 01:43:48', 'EXPIRED', 'b2fbecc239bebd4caeae6fa55a1c56ab8082fca74e8659946a65911d9acdbbfc', '2026-09-21 01:39:01'),
(108, 'SIM-GATEWAY-001', 'JX-UTKSPR-S', 'mr2dMxdbiN2pDPyg+B8Yb3Xb0KI778QfFi7oQbxu/roHoOi7BMjxIOcp9XaIzsV0+MY9R+Vc49WV1ki4zgDbrY5nYQzxUdbhy+9l5WYmFBgB3ko8TDUSitVJxk2c0kwv9tNcoEsXLTbBVnPwcvv6xTPcAb5j42ZazZ2QTT8pCiPGybn0aWkHjD/eIvLXC1CMnrFqgNk5JALh8HWDIA7pv7yTZdCj3jbDO8+iExGZ', NULL, '2026-09-21 01:38:58', '2026-09-21 01:43:58', 'EXPIRED', 'eaeedc8c10f60790bf02428f384dcb3b51b6eb1938319004cdfa7eea7518468c', '2026-09-21 01:39:02'),
(109, 'SIM-GATEWAY-001', 'JK-SBIUPI-S', 'A88SklSDMcBkBhL0GIa5nUceWpyxeTmu/AFk8eWSY9LTCZd0ZviGZ9OVVMkJezqbT8A3yr/G7uNfocWsgSPfy6+nhl1HYp1EP7ZwO8cb3r3f1PbTekJ4ugEkZ7TCkIv4vtDDAZEKyaocZqr2LvylGas0WY4wP20Uqm6Nu0FYkJX+NsPumAqUXzPEQPbBkR/KYQwrpFr6n3CzAj4kd4tpqtMqYxEvBIITtM3k1bZBd8b4LhtZYslov3SFjiJeVHHkGEBv3+J+3dKeRg==', NULL, '2026-09-21 01:44:54', '2026-09-21 01:49:54', 'EXPIRED', '3c3688fe8ce7ae2616e4f2973ccb89afd795bff20de6157357a689a62de7b20f', '2026-09-21 01:45:02'),
(110, 'SIM-GATEWAY-001', 'JA-JIOPAY-S', 'L9jteXtjCQe+zMXb+LcuLfZRsoFmONzbRvVz8RyrD0L9WJUQEt8tfGLeb98S5Pv97Dss96NLWOL6TYPcC1xDzeOBnQ5x+sXol6dVc4jEv4H8pvFrE6DQLsbnzcwbCy/J8EevrGsWGMOHOun9Qzbv1vO/y6lzmkIdDQ8iI9JQFSWiTZpMIlM5MNqgzVsJ16EQgDQtF7dpqWLjLG8iYHCkX44ZKT50pCK0WYeRt0wxARtFqohXqhBB0jQIx1PkNeEa34YsygJRaLVtQJmTIlV0+gTTcMLWYgNzMAuI0FbkaQkGsX4hnSJUqLacxfDDN5FnUCTdox7oaKEGaDg=', NULL, '2026-09-21 08:54:02', NULL, 'RECEIVED', '1aeeaf5123604bbf9a6d5fba1501a3cbd1a9d531b5aff8cadfedb783efb95eaf', '2026-09-21 08:54:17'),
(111, 'SIM-GATEWAY-001', 'JK-NBClub-P', 'thK/V2jFKfqyIV+vGm3PsN/uaWJGamji0aAMT46me3iQY0t8JWe+mvw7gN3+l05FaTsywCspnxCC6IcW5VMEybqdj8X79Ozcb9BFcMv+Khfk955ClYpvI7vaEgznLc82+sy6X7SKzMEgUIxT2i2sZ28v7Tr6mZFFsr/HOa5/CUEDOBBa33SVEofuLODDx9N3ytzhiNoLPgMuwnVTiC3tnOTITdCxn2aA4Mx9f8VjUowfvYthP2zq7cySLMs=', NULL, '2026-09-21 11:08:38', NULL, 'RECEIVED', '621777f033d29c4571c1e0028151966b80e28bc6135695a6ee1ef29513a646bc', '2026-09-21 11:08:44');
INSERT INTO `incoming_sms` (`id`, `device_id`, `sender`, `message_ciphertext`, `otp_ciphertext`, `received_at`, `expires_at`, `status`, `message_fingerprint`, `created_at`) VALUES
(112, 'SIM-GATEWAY-001', 'JD-GDSCRE-P', 'RhWcEY7DrLUvtPlatDivDVjnx1/tHQhUYHqVCu8zbcTgddVUqXff6yPZHZlP1wem33k5i0NTZaHAiwMTTRVrsABD8K4B4rnKBD6mwiHaMc/9WO7/xXEYDPEwzWufe10CfNlqqbYn/ttNALt7EnHK3pWmsjaGmYzw5L0YyP4Mh2gjZu1Gh6DRk3AqCe7gBgaWZcSWLgLdwd/ROrQawQBPYq8Q1bCxGou78UNZLuvGT7QGitc2BLT9xcpLr53JxCm1kjgpnS5a7Gcia6OCPKBZN+NVyDLZfKXzyvlRvc5vJWibXLYitRbeARlj4Iz5zz6M+KrnLaZw5V4ctOcPx2W0AKHx7IrmucwT2+We4YaKpXXvMA/iMg0or2c/', NULL, '2026-09-21 11:36:09', NULL, 'RECEIVED', '68d9efd4bd01614ab228f577cfac2be6aa6629dea259b1eff0183b06377053c8', '2026-09-21 11:36:14'),
(113, 'SIM-GATEWAY-001', 'JK-SBIUPI-S', 'odYiSkNMVfcKtMPMf46soGJI+PVV1ZPaxD/pc+rDaAxLvw3oJut9Julc+LZfVAdvuNAAWAp87X5Vq91kl/bDYFzfA506bHOXSJbLjhY8NdpKPVUSlCrTmZ1E4nIZa7plL5wZPlDS/x0dahcmI0ekTmH+UNq6YZdabPABGQVTVlwMl18fiRkQB15rUlECBs5bVfqH5LsVVGKATkwiZTzDCdTyIIUbI9JRxa3gUjDuuz/mMHtq9hiakPQ3kFasiJLdoQ==', NULL, '2026-09-21 12:59:14', '2026-09-21 13:04:14', 'EXPIRED', 'f4ce4608521b7a95a043439e5ae6159bc647228b1ab1142b3adf1e0d52a2cf71', '2026-09-21 12:59:20'),
(114, 'SIM-GATEWAY-001', 'JA-JIOVOC-S', 'KXwaEz6fZ8iBYXXsir5aEsMGz7AytKYZGWlYl29T9LKxFo9BRBWLeRRWPfHp55WfFrLrVP4rv/LqLVu6oqPS7n0Y4ChQ5a45+zAHw73M0ow5T1R3rlq//3PG94hG7pISVe83wE949RF3u/RsFN7EgTaG0XatVeRKqmsfdzOR7QeAVqYi+HTD+YDXG+WnFuWI12bsllDTzZGFG2ZDAb6zLQ9vuIMygYOnnMXwRHgc2rziD2O0tLQ82i6SNNSJiY0wh79mbFYF4DnJtjSMtE8NCdzWCvvU9+nXGLOlhloM6O+Y1eUQYxQ/t6NXRdnD+K9B+9f4TlZGA6HFVbrn3wMRof39aMaXoJBCS0GehcldG2MsfsKggVDrSjwZwyHdwuu51SNs1VwqtKHFXQXoAWByQxAkUK3rAR3e9M/kT/6UbM7s69kfPGsNLrRGvFFGjvg8tiYygLdxuEZAiQXqaM0jT+/PD8O+alEyu8dbBpHjoKZJYkvyDHu0xJd32L4AXzBBwNLdMU7dQvLz0ByDIuKoToIiwXsl', NULL, '2026-09-21 13:42:39', NULL, 'RECEIVED', 'b37f98d246941c06ad68e7c872c8adf1e68cf95364cca392ecd3e258f984a3a2', '2026-09-21 13:42:42'),
(115, 'SIM-GATEWAY-001', 'VM-SUPRMY-S', 'tdZzWdUdPxg0b05mi+nuISrBoy4nXt2Hm3ApihYhaNAxI9wQnXqdqWVAagIBWtd0wug5OSMwxsQkLmG312ws2BlzoPEKrgWekcsTAzJ1faYBSj6hYWk29SelD6AYiAf9vLQikfzRLLLEFCn58m6jiMpX4hG6PrQnTRfaH0anbbsNMmp92JorNyry15OQAe+pw+XPkZiqB3tb', NULL, '2026-09-21 15:25:51', '2026-09-21 15:30:51', 'EXPIRED', 'fe44236174b0ae27eb715f7ae0d71a6cf8eb30ea275235384d9a2661e9611e27', '2026-09-21 15:25:58'),
(116, 'SIM-GATEWAY-001', 'JK-TSAVRI-S', 'CqnXbXDZS3aN9d7qY7IyTNCMJyBYxxdAgE1vfxiddwF6WkWTP/t85fKOR6qPF+oAzR31uJ2nZQBHQJmK641bSB3rfuv//19/mEDw+IKYulnFMOaWzsK3VuCXQdEFCdCM0R8wUAgCVAGYkJhPceKabVY9fcGN0uO6qq6Asxxr/w2jFEiX1i4p+BSauyqwNOP3ZsARboOtcVXgX7gC+/1qUa+I7g==', NULL, '2026-09-21 21:07:41', '2026-09-21 21:12:41', 'EXPIRED', '738ea9ed397f0987cccb5881f082545dea5f363d228929d18729b29dc889d0b5', '2026-09-21 21:08:16'),
(118, 'SIM-GATEWAY-001', 'JD-CBSSBI-S', 'Z+y5WcKrvZx+WGNQsFO9raLchGbpWntx9KCFHtEmhwpvHTLfxeb9ZsEfX83Unr8ZYFuuqs4L+sHeMmBIul5cYnNTaD2HSl8uuQcLVxB/XF0ScKiGgl82gFPNa8uoo8tKeJ//ROZfuxn/bYYzHXYvoKgI9ii5gylbIyhxn3Mkwjj7gfFWsKawPpMCi6Pm6c83lDeoOe9a', NULL, '2026-09-22 08:59:43', NULL, 'RECEIVED', '3aa734db593d3cccfc4588359dc5d3900625351c2411745877cd5905dde0e096', '2026-09-22 08:59:46'),
(119, 'SIM-GATEWAY-001', 'JX-KFCIND-P', 'G37/R7w+vp63JzZ6UrGtPV4KYqEWqafXh1TcbO+VvFZk+T4/ksupmoLsRHsIR0PhWPoeLPJmFQMZB/hqLj0yzwV6yS3Nh6hIOr0fjE2LH7cUm3IdHSNOvk4Yns87c09LTGV/MuTfhO+3EPw0t5ME7jF6NsGDExG5fkw8yi6emBrxQXImeMXT/9bPvLGmpfg6CYze2sbN4BcXx9ux2rw9QmzsO38vI7wsYz9FeMTteGZJdOmV4Mc5LrbakGfYYgGBrxg=', NULL, '2026-09-22 12:00:17', NULL, 'RECEIVED', '8423b75de99895d8c3a8a1629e7427d97fe53c8e0d3c324e09b4e1169a2eb8f0', '2026-09-22 12:00:23'),
(120, 'SIM-GATEWAY-001', 'JM-JIOGMS-S', 'Fnp9uZkEkUcuPAyb8LeNMTotzYKuYQpXs10rUseXsrGpDNlm2vJPcCRe5QmT6AmhQKdBhpvVs1PQ6r254LNY24uhH0kOAmE5DyxSnvooakZ3lCsh7dJqTKhPv8loYFl84sTKq2Rt6HY3hOHYVj+Pw6lVIO8Ay3e98NIOaDxPBiwj3sYWBcVbVRmnKptOUL09R5L2hQO/snpzPGio/xZeBCb6Xxt4nPeFJuJjN4OqX70WxkiMrtnzErClzuM=', NULL, '2026-09-22 16:39:14', NULL, 'RECEIVED', '41ffd58b01f9532b37bdfba3c8b901cd092e7b0f2008617797a1f890b9763312', '2026-09-22 16:39:20'),
(121, 'SIM-GATEWAY-001', 'VM-TITNWO-P', 'WmrtDALHYPMGsd7UfQ7gjpj13rsuSaQ3pC8/OACTqGYExP3oKSXS2ZQdnlYgxnQFLqMfL8aQ3bw/ZP+ZPdyj5eiU1mAKKJYZwkphzXNU4G9oQAH9bT4p+UwPss8VUY0eQn5Ccmabwv6tS9IBixBqsKYucrKXF7bL93vutY1u1HELt9lTfJVA4Hj2Bo72Gjw8otlw76ji9UVK1PkyO1aMF+ovwR8wo1JS82KyYVm7HbBhyv4xoIzuhSCukpSFO2vK5Q==', NULL, '2026-09-22 17:48:46', NULL, 'RECEIVED', 'a9d530f83b84632c14e5be70d2252c53b86d9392c58a1c3c4cecb2ad2f68a286', '2026-09-22 17:48:53'),
(122, 'SIM-GATEWAY-001', 'JX-AUTBGD-P', 'hZkYi1/h2NE8fW4aJc3KTub1L4cGefuJ0XHYe1ZTYLjhqjuBrN0eDxaOWtkDsKJO/I2OfQ2i/2o5Tuh3tliIMcxDju2X8zy7joRO9pVyNFEWEsj8+hdC4CNzxDt4PwjArbZAgNE/tQu6SZ+OmCjVBRb2C9tVOQ5CtrRSIhhs+yFnV5nVwh2FyTJgf+WfDjajQBhVP4eKnq098GxN7Iqv6Kqvbily14UE2DW5nBVZQdkSgx+z5U6pWOsNGEp0Ty182EbuKnBr', NULL, '2026-09-22 19:26:00', NULL, 'RECEIVED', '588d5f5320316c71dc30b254d9721cc108cf6e723da2e8f01f494918744e0492', '2026-09-22 19:26:05'),
(123, 'SIM-GATEWAY-001', 'JX-AUTBGD-P', '6xj3MoMGtS1gjYAZKUE0KWcCYOzXwWWTPCunQRQHHqBSrt9jwIbZneaz5vvGrdY6O0Yo5D0ZhDzIR9XTS9Q7Vjra8ilKT8VjzKJ/UNPQO6TTz0eMyDU9btcgk243BHHK34QdcwgQpxOVcWKmJxrOVq8o8bQg4D4wH8aSm3RD2bh8LG4F1VW4oymDEh0eCrVwFcK+hG2FMeqWeHELx2gvgDO2BhlWLcN5VKXd/BnpDrimZesdfgZI9X5tm/8t0xWY9CGRmfS7', NULL, '2026-09-22 19:31:52', NULL, 'RECEIVED', '9f2729b9f5bc38da9f15fbee2796a0965c3adc45372d4d0169240ebe23b354f8', '2026-09-22 19:32:01'),
(124, 'SIM-GATEWAY-001', '919849248788', 'h3VDER9OHBhUfjAgGJZMxaYC2SRDxKGDCLxZp6HrsfW7m2F7PbsceQGagB2Wjn2vlyah36r8qiZm80hP43epPRKaDoSoKdZGgUie7vM/jVnit00/8tBrd1ZSLiMbYev4qM6SmgqQ/xmrdGHcio35t72O4gUr26rc0KvDJE5Lm5EDn1JZAg76YXoXlXy/MeEuq4VNGC5Ud7rDk/S5xPG9hS75fFtYt+FO5U+/wkJzdkHi', NULL, '2026-09-22 20:58:10', '2026-09-22 21:03:10', 'EXPIRED', '9d776ddef30542de3502a554a2d87805f80eb69f430c3ec8cc7f542c7b26175e', '2026-09-22 20:58:16'),
(125, 'SIM-GATEWAY-001', 'JK-SBIBNK-S', 'OeqAyslH477pMeOvujMRq9QVm0SzjeCdUN09Ng8W8j5VjShI6NoYkHm+POUivAJA4rtRX7ocYfwTJ6oEaRXS67AmVGPK/OPiQSnh8rxhw3Jko8Vt1OUJAD0Cc8Uywq5u3kdv0CTJynOwJhNX9iCnMXdlTaEMq3I9G6yBr1CA4Ssmqp3in2NvFgNPJZUhFwOwZg+Un50zuKxR1e1D/ZW58I8mW4wq325IWu1VGcGrPvWKZfq65k3cfagheMcG/dXK1UQ8Eo4By0ygkYZ7RX9nh4UCTw5RbzLLnsFTj2q3SQwXAYpFOGzxuiVTMAlZ1QAPSBd5HopvxQ5ZiPPhGt/BGFkND5c+g/WL3Gp8fxprP4Pdu8dMw2oDkLFfB3hfVCl9Melw5LXcY9znjKPsJiIazQKJYCm9/k+l/ArlInXc81MM+Y8OOvHr2GBVNZd59zIX+HfiAA==', NULL, '2026-09-23 08:56:27', '2026-09-23 09:01:27', 'EXPIRED', '5319a8619bbf879c408b1b52ca08d5be58bedcbb47708e40f358056742e30f36', '2026-09-23 09:37:01'),
(126, 'SIM-GATEWAY-001', 'JD-SBIBNK-S', 'lDZUjSguy1EsdyiHFTpcKPbtDBiF382VKeAqeyiYBStzjE1N8nhxM5UDtTUvMdmetNEDbJxoRnY8kLTZ3VMObFw4blH1pzABNll3dRcaknd2pqKv+E46hYNBEoRPwyWII+tthxF5yjxYFT9Po9Jc8I4ccFJjVSKQ9oxSOHukFpVcOmCYb2bb13q2Hvu4nooJuMqyqekC5ubdC39KXvRxdARD2FeY8zcZZPGvLge8MY5a4LSu+l0W+jah7lqmw88ehvmXS7ChM3NqhqLWkb/MEg==', NULL, '2026-09-23 09:36:51', '2026-09-23 09:41:51', 'EXPIRED', 'eb2e828e023925dd5f8b5d4aa2598da80290bf57dd80726e741c07c7539f6d4e', '2026-09-23 09:37:02'),
(127, 'SIM-GATEWAY-001', 'JX-FEDBNK-T', 'TEibH7RyGT1nTMPHIFHDsY1muz+mTAK5NYhbnkl/HUtlyrzX05nTHQtOj+mzmqRGIRj65GmUg3YYENPYuCHRv+h/+o4AB3f/5UP8iTf3nVmi1+7aBfj5D0A6tsA9Ag4f6u/NpEd5BvBf45s+WGLDHmTcIfZ/Xjt99OJTYJEzSTGYciHdQUuMVm6gcdOZQ0JxPu6o/POmXYcejtAnQI/S5ja54v9TqoOG6YOFj1Cz6cOGk2iw3C+R4a/4xt8hFT7ecTnO', NULL, '2026-09-23 09:39:23', '2026-09-23 09:44:23', 'EXPIRED', 'ca41bfac18a8c68bc766c38f6fc2f178dbbdf981269878d10a43f5606cab5883', '2026-09-23 09:39:30'),
(128, 'SIM-GATEWAY-001', 'JK-SUPRMY-P', 'pX/w3cOsY29udznLXQsc1MIiC80tYBat4nfsLQpSfrvC6MhgsyDUWJ3I7NkFbwI03vbIqBOcQ+oIhndwW/ZnYILnmvLp33EijdHVgabizzYt/xBHI2Bj//d8pwxIhl/tYjCKKmnEVoWk8EA4zNL6roxLqakfrQ3vo9b6QLSHga3QQViGpOyp1SfEqpvAEqpw5GCYXnc6E6JHN0cF3aHA8n0InzCAOc6dZ/JERncVZDbk1ssrEmiJfQ==', NULL, '2026-09-23 10:20:17', NULL, 'RECEIVED', '9aba5614308655ccc3c0c5dfc2ca9abab61809dcb7888ec07f2df14bafa49e80', '2026-09-23 10:20:25'),
(129, 'SIM-GATEWAY-001', 'JD-TSAVRI-S', '1ByZPt/nF27PuIx92wzFUnRwonCAmCaZYg5P2t7KdEv8AvmRgW/ZbrThLFOqrOYZQEJAyCzcb4ZKJFZbkleXIq5qLngtAZEf2KinfMOwgNm3YzQRP/iuorTM32FSB6w4uXQ8O2qkexN5OSxCyBBXEGunpmQQdKr2kScDhLTcraNZnNbQp8HgSVxSPKhiv/jnGflohfc55UKtdf8oIdruNBLE9A==', NULL, '2026-09-23 14:19:44', '2026-09-23 14:24:44', 'EXPIRED', 'daa0efb5d5979558da7478333683c858392d71f5ce56bc023411c6d51c3c8bdf', '2026-09-23 14:19:51'),
(130, 'SIM-GATEWAY-001', 'JK-LOCKER-S', 'lUwSujrK9QERnN7LHR5Z16dZne4LI1/qUgFuAfTlri1ZNavO8924Du5xkEl3giF9kaWdPoYsoAD2O9CR4ZSkIKR6S9s/BxNk/32QqGlv8DQVpOPPSjuvhNozO4PbqcQ3pudVRg+URdkpo9AqA3XnVkfvO2bLfT6mBhcD8q2UAY4OUXaGa7Q2v+N9HSUkASjNNM9156WDDRoHCQD67CbkeZdT8NdcC+8+qEtaYYn9h1/g6OIjU+ZYMskreMMBUA==', NULL, '2026-09-23 14:38:41', '2026-09-23 14:43:41', 'EXPIRED', 'ebd584ac9999cecb0469cb408c3b734630cd5069db301183ee7e05885dadb7e8', '2026-09-23 14:38:46'),
(131, 'SIM-GATEWAY-001', 'CP-PVRVIP-P', 'UxFehBPQctw5c+lThqvonKsSHgGl0/JQLGrSuTHXeFrmQMIAJkmP+5e/Nqn55VFjGhzggAPwQiLre4MmnVqmRBpP/tSNx1oDn5RuxE0nW674K8rRcEVJy2TA02OekNrhPx2TijL44AS79LILyFuvRvtY2KQu66xQLz8nqWg2n3SOoGluRRPtTCUsq7Tp4jVFXD5yz6z2+i+MvklzMCrcosiEnuue1nBtCahCoWT5625UC3/XzjtCePqI8MAALLVSQQ==', NULL, '2026-09-23 15:51:36', NULL, 'RECEIVED', 'd0ceb405aa3173b4ec6c307bcf6e863d65023412949bf85a58f2b6058528190b', '2026-09-23 15:51:42'),
(132, 'SIM-GATEWAY-001', 'JD-TSAVRI-S', 'XcqR7SRUhTSC9IB+K9uOHjCKCXqqVXx1EHD4NI8cCIUD8oOZrmti5wDgL1ZsxL6gE+/6QFTb34Np14bbfS2nOagU88ixc9/IaYpj5GTCb84h3bOUhyoOcSEIqc6TBbwfKgT3d5rIew3e60MWadPUPy5CTwckQD2aoHuPqE7ZR7vGptqeJiKQQsTBTS8FjKpUZIGAIBa1zbjOsse5rnz5QqCviMJfWr0fG4qhwllPghNle4cRcA==', NULL, '2026-09-23 19:55:51', NULL, 'RECEIVED', 'f81cbf680a379824f949e15cbc1027f2844c8ded663a506a1bb38a76e7fba5f8', '2026-09-23 19:55:56'),
(133, 'SIM-GATEWAY-001', 'JD-NDMAEW-G', 'uXMRD/TA7nPgzulV1uvGCRMs0FyNRWJLv2Qa8Q3IE0UiTBjwbWDBWN+1XgZL6KDVdOpCSMVjJB3zljvvOPN8tu47tFhDiW+LkgK85X0oRGsf+fTv/YCALTnfs61by5I5I1FEmbd6v2JwCfdUGEirGVxYJ59/bOtLFR6HjIzYlUaMC7iug2biMeatXXkFqO/+E2/0HGyH8zBUmZsPFtnRVRua/ShWHD6CffewSnPg6TAa72jJkPgy5zmKlBZ7cKCvDcKyYcyxjIU4VYSlVi06UYyY/BOOPV1UqColvuT9+n5VatHUqaQTcWVwHHE2LNVdQBT/xPN0oCudlQizRIs4pmiymOBaixmsLe3pdyRx1RCro4+kzqkwJQXC9YXX+eI+BTWAFJhMTvIqwTLcZ3g56ttLUD6di4ErUfRxzz0/EuC0O8JTOLWR0Uf5+aFk6oadWoA/1A==', NULL, '2026-09-24 05:46:58', NULL, 'RECEIVED', 'd992cdab1c9cf04c63409681a8b8dcdf9f6e2387b374d84f24dfc8c767834c45', '2026-09-24 05:47:05'),
(134, 'SIM-GATEWAY-001', 'AD-NBClub-P', 'svCmKBBdTpxibZeQo6qKEHU009YjFRYDTsXYrLdY743F0O9M5V/lGumOFT13KPbW8RGuwpnUdHYwi1WMl3RihMKLsqEB5Rwp7PycPCsfxoGB6EbDN/gaWpL5qmztYVejhzU8RBX3Tq33qBEj0LKtPV5Nk+s1XQl/zAZuTHSIhRVr9iOBkDRPLB/URJPkT+tL+SKMiBQZje3Ig4Ehpk7lkk2vZ2MO2X+DdrVWOXJSEKGKDLUW', NULL, '2026-09-24 11:04:12', NULL, 'RECEIVED', '8420b0080cc237037aea0d22ed1e05665935d425c90650d5d4f96737cc3c8626', '2026-09-24 11:04:17'),
(135, 'SIM-GATEWAY-001', 'TX-UTKSPR-S', 'qokJzXRdXrlwCu5pRyPLgXbefmDsWFUnSEDUSsJiETzKvpZ7fX0pt8JRxlqSRdtYDSVsp1PGjujrehlp7kX8mibuG6vToLVSoXOA4Q8ZibIX0n7DHWHloj8ElGYo5QZZgDFbzN51gRKH1S2a/wvprx2zzAdUM87qL5JlP82e/+WSGoXIpUsdtMxtX56ZeHaKZdLKXgStdLjd7+XFUkQOSOZs06JHlr4g97Cm/+JJCkRAIjcg1A==', NULL, '2026-09-24 12:01:55', NULL, 'RECEIVED', 'e9e689f9f2a8d9c54d229cccc28f08a524fa04e22c6760d742c8b7b09e262fb5', '2026-09-24 12:02:02'),
(136, 'SIM-GATEWAY-001', 'JD-PVRVIP-P', 'L0zcFwzwyreCxwOVuwsA15mgTNSEQZgo34McEOO5rIYBMvFroKyFcga03UfMZ1TLSNaGY4mD52RIqicTWzpXVbtsHplrSlS1uvc5NeC2SaG7mrki9TKFGuy7RfwI6nJJGFRHeeWbzYv6Y/zOpvPUEkpy4jM/Q+jhrRyaER9TtByCXfr7OFh/GaKRxiRoiI6qP501iIGTCYl8lGvi', NULL, '2026-09-24 18:12:52', NULL, 'RECEIVED', 'cc96554105d6ab1dc36621aa6f41727296dba4d48c905cc110a0c21a0b0ea253', '2026-09-24 18:13:00'),
(137, 'SIM-GATEWAY-001', 'JD-TSAVRI-S', 'G2M7ysdrcn9QT0Hq5CAwpHH8lqrQRqIgPBLdl1cmSAp+uJfIK7q9n1b9YtxcTO93PN8UX9RcBmScqwUBu7+mQZqhc+A1n8deGQndD78qPN8wk+hZRSJPWU7iD0BX+yiKkWEIx9xtocIst7vEPohyfYsFxZ/X+1oKTJSFGRoSu1ndgEogKrYnFtEJvscW5czZj7OIgBeIHGCs5H+A8w/ZQJGOqQg+xPnvth2CxQyLL2DwbIAtcA==', NULL, '2026-09-24 20:03:39', NULL, 'RECEIVED', 'f679c47d115f2f6a1abfd2e4718d97b68272ec05d412671f00b18e3096c1a2e3', '2026-09-24 20:03:59'),
(138, 'SIM-GATEWAY-001', 'JD-SBIBNK-S', '1SY8Ll3x0puYuETQkn0qnJga2Tw1z+C0eogl5kiDniKew8c/MdAr/Jn6ENjp3c/lCtZfjR/wEtp7/hNGYL8FzaigD13xKpESyjacHuRfRJSqfkP2Kttz/J0T55kjnTfKpjuHjgWI3e8lwwLnS1hn/WI70z2cCnYfiZGWWKzg6qhOYV5rcOQeV4o/beOBPqDHUAFohTRsn/nM9ZWn28l7KuP6vWRRsyQ4Lxkd3RF8VT/4RkDiWpuseAKG+PWkgjx5YVg4lD14QO+/dcJpHu7h+keP2yDMzk99sHS+Ws7jFNXXEgcWckNVhV0x1XZr9ankQT/XikgLcNeDl2kC8/hE3mcHzlqeXPwDQLrzwjn8FReeKZhrOi4F0ox3h/rXnN26GlQsLEdO2q5asaZw5GP1N6i1hgvxR4QSHUdfw/oyF6avmoEBj7caay6ET+4ESOmzzf8j6Zx97v5nwIWRsH0sWGD3r2oX9+7k7BchUfI/l2QQXR8r/rrcEeE98SPPIsCVE+8TtILlmFFS7AThvN3H00Anpg9jITMFW3BYY7y5Fd0J0oqGS6F9XY7Vy6nMINXnDFKdCwPEpA==', NULL, '2026-09-24 22:36:29', '2026-09-24 22:41:29', 'EXPIRED', '26a47dc56d6c9431d2c93eb9aebe3ea17334c260b9deb04c4424014328fdcf1d', '2026-09-24 22:36:39'),
(139, 'SIM-GATEWAY-001', 'JD-SBIBNK-S', 'J7OR7ISuXRlq+qlVzxBV2IZS1BsATtoS01WO3w2P3Ex/PBBCrVRC1gBmrmbjF/vm9L8oZiw4EIbVL7E9whYGXSGFacBRlFgus9rm1PBVvVh26zfuqub4hXdH9xI4bGHtJM3C85veBUbD/0hnJ85pq3OK4j4hqMjSwijxwZjQLuYxeSSclIru67BkIgiD7ZxhDVw4zUOpKbgWhnOpUAFY3HPg9oqMzIMmVcFOASBDQcj7P4ydobpc92RP02vi6lwoaPWCKK4J', NULL, '2026-09-24 22:41:12', '2026-09-24 22:46:12', 'EXPIRED', '40ab0de5db70e10eace93b26104f49d36e4600378c50c7f747b02365274607e1', '2026-09-24 23:00:24'),
(140, 'SIM-GATEWAY-001', 'JK-TSAVRI-S', 'V/0ySVVA96Gdi4fOAomSYCbQN+JNdnlYO1qo8dKukm8kgti8d31akyFHRJ26tYR035tDsQdabH3e7/wf2H6BBKCYtY4n5BH6J8/KlBfgjAnNDHEahbo8VU6rXewrGcSA/f38MDkQtxVBlUQOQ4yJBYuF4iNelZFdIcT5sC85FBxbwfokb2vxHH8lqfGWtYlkD2gyk60O5CWnlv8psKeTAqGS9wyLLkxTbn92awCLs/fjdiEXfw==', NULL, '2026-09-25 08:25:18', NULL, 'RECEIVED', 'd5d91cde009c4249dcb9bbef929d859352b149b5629e3e3310472ee103ece0b5', '2026-09-25 08:25:26'),
(141, 'SIM-GATEWAY-001', 'JM-HMPLUS-P', 'yzAmCthFH7nhNWiY4zGQh+mFGIxz0AFJRT2oCmhSmDrnCXw0tirdu/MR3HZDCCCiO+0fMqQkfyEKiR4Qg1emonZLnig6EaeWYAa0UrAIyRsDr8cSXNf8u71ZyGYnkT0zloasWQGeSyhxJUmMt66vzKx94JMsqPAk6U8TifhMvECB7PcElbsGXOAU5etDBAp0kqGkqwalazamKjgAiG3b1RJ1SY/+MGD3OuPtzVrzz2c7gUGEBUpflrLWoEm/9mWqi6Uenq6h7Eo=', NULL, '2026-09-25 10:27:33', NULL, 'RECEIVED', '595786a4dac244d989deea47869019642349f64f760cdcdac24f23aaa465aaf7', '2026-09-25 10:27:37'),
(142, 'SIM-GATEWAY-001', 'JK-FEDBNK-T', 'PcHYGMgdhoV61KP4c9h7XIdaPK5e0t0quaQzFJJV7hXC52ky45Jc3GN/16DYndaYwZld/XVLI3yrVsySHIIeD8yPKQWbo6farnD1a5SglTM7D8uKIixNVUTCRG/BjQTXRUnZ/abJbmS2a+ua43gRNZBO/GzVQTE7agWDGHuGfdGqgFPuv9kF9Tk0H3LOtCgwaDY7SuPhalc4yhl0Fm6kEGAmw6YbywHE+XSRurpkzDMws4GTeQniMSMGf4I2UQm75MU=', NULL, '2026-09-25 13:15:08', '2026-09-25 13:20:08', 'EXPIRED', 'd370bdf83bb81a9c9c21d09ef2346cc61d577036cb142106002ee8da94edea6a', '2026-09-25 13:15:12'),
(143, 'SIM-GATEWAY-001', 'TX-SWGTNG-S', '2vwQy7IZOpPBhzGwdsme1TA2giHZpLqLWNevHpxjsOP0efSRB4/nBjvnoH654K3hN4yFmxChkZJQKEr1NL53XCEIHdUpF/uaCtvV+ndu/Kg7ZXYuAbJyY2Xd0i+KoP5LeKGbo/WUW6N2ZgW8K3tASWyVfVDo+1JjRqngC31W8B0/xvZAaS2NtXvpUOdzCklhssZVuZrOxqLo1F7M9hLxYKNnqWwoENi74FJBQJIVjGB3cqnev4c7ktS6uKov9+cQtxKD0Of74rw=', NULL, '2026-09-25 13:22:29', NULL, 'RECEIVED', '77126b65a645e49f0c4b3adf9f16a836a8d44ecf2ef02ca606fb245a226ec4f4', '2026-09-25 13:22:35'),
(144, 'SIM-GATEWAY-001', 'JD-FEDBNK-T', 'eYrEY34WrDR06tP5GtE3nXtieGtHpwGhPqTuNGpv5o7/OlzHmQ+naR/BR0YVsS/u3wm4bshmTNyDm8OAQc7Du/dgMMyx8chBhzFprHPNB2FVD+WVvdS8nIZgPTV+sV3hU4QnOReKJiQPSXtfrYKNT+JttUQFSH9p6L/VKkoIuqgSjrpWozWb8XkJK+Vvctj6rfB7t3A5XWNsVOI/igci3dMDjC8LZV1BIMJb8qkIrVNZlHLgo5UbE4QGp6ElcyuoJ1s=', NULL, '2026-09-25 13:24:59', '2026-09-25 13:29:59', 'EXPIRED', 'd58512cab2fc5aabbf48eecd9cae19123a72bfcbde56a415296f6a5a0cdcbd5f', '2026-09-25 13:25:03'),
(145, 'SIM-GATEWAY-001', 'JK-TSAVRI-S', 'AbD253J4P9SoyEynKXc5qJVtiznWO8u2tNVBGHnkeMI+qjFtzGJ8c+adBbSfw1TDui232EP7E/+woHkryk1NSJQa6EffCFvmnXFuiwi22Q6yovHPDzodQk8iyy7sQP7BLwf/EnH/6Qgn+cQf4Fn5r5ac0Fetu5F6Ae0eIKIdtsc7y+FzCTmd3Hr5+xo6V8brVVnwN7YFM42chR9EgmaXS0mdzzydBrej/vqyeBhVVcVErkBa1w==', NULL, '2026-09-25 17:58:13', NULL, 'RECEIVED', '307845dfe04b3345e8c7e6c77a0e38117b865c3175e946d576940cd796393194', '2026-09-25 17:58:35'),
(146, 'SIM-GATEWAY-001', 'VM-FEDBNK-T', 'XB8mFBnKKhvS7cg+ft/gg7sXjOvQpwFK2ttgd2cylsLabEpBCKBlLawQHI10TzW4MxDKdvevALu2CSglYFE8oyhtZTn4VvNO/lIDOTirdxD0kFyiJxGE9VzK+PXAbX+boHOxytpjXuyoPAlDMOEZMMBpMWIPSDAEaHHDLEswDUPpp3Disf3UkjdXg6DOLNRANBct6wREyH+rS5qT+/2C6dKpjjCK1DNaTr9HXMA0EYRZG1FMcm+Wsr2wmlZOJYHCTg==', NULL, '2026-09-26 08:18:31', '2026-09-26 08:23:31', 'EXPIRED', 'f73d2fe8ed38153a4806e6ef06fb2f765856353d3be52f9ec95175099d5e2fdc', '2026-09-26 08:18:49'),
(147, 'SIM-GATEWAY-001', 'JM-TSAVRI-S', 'z4MRzZ6mA5PxI7/LcIhR1wG22C8Q8eBHLx0ZuOJqqBdoFEftLQgrgxFJAYzXhszczwFM19MGo81VRBJKDL/JaPrm9Vndat/XLG9uhofCZXRBgVHt1mi2jbGImXVOJUM3aJwlw4A+sEXMmHkzTk1CSI6vl7E1rqE82+zC5rCJn5qNTOQFyZUZ2ojLCEBepPBC75VXVu0Q5R1/tCJ8Zy0NttX1krSht51curw/zR1KajOCeSm3eA==', NULL, '2026-09-26 08:18:29', NULL, 'RECEIVED', 'fead43dfb0050b40b8fb75a2268fe65a83b0ee676c34aa1632706e4f79fbd646', '2026-09-26 08:18:49'),
(148, 'SIM-GATEWAY-001', 'TX-NBHOME-P', 'Sb7MNkEri7Jdx9LNdaMcr1AJfZgizLjGslQWnMM60qJ3cxwNsige7EO4DGGsJ592zMmJ/f0wo8xKB0mj7fJANv1GKcQyUDEYCTQnyqZ3BD8Ls8dTP5s7UJ/SC1gp8C66o1qPNEE/bkj5pKYYP8lclQ/Y7r2qMym7/L1dgnGt8qPMXZIFgCa4Me8wAdN/fiwCKRSqoy2VgQeEGYC1W85r19JqFknoPe7SXThS6gU0PPKqmZM/r3Yvv30Tn9CGae2TocXT', NULL, '2026-09-26 12:20:24', NULL, 'RECEIVED', 'b49af364ade96232a8cc86c736d02eb0836ac48d6c3c08c7d8c7942d4a56318b', '2026-09-26 12:20:30'),
(149, 'SIM-GATEWAY-001', 'VM-FEDBNK-T', '4Ly8bUseqytOpNWT9/8OWe0D6oqpTS2a6GCIa3y+Hs+L5cYXDpsVMQORdiyoFNEhenq0JKOTURbpnj8tqbI/giCQbtWxHWnwM3BND4Rup34zhj9SCMolY/P7MPkIxEWcPGpORMjpUoHKbbDwfBYp1PB8zU3f30hgjjaFrIICnLXn46IndBdT5ku0HsFiJ4cCcsmhDDT0VSvGGI+m647EpPQqNU5vvm/VK8EqG0PNhqlSNV7vQmf4bwQuJC0jICyDSDs=', NULL, '2026-09-26 13:17:09', '2026-09-26 13:22:09', 'EXPIRED', '397cd33a2c94d881f545f67a3e38b3a6ef4358b96597721789823bc70db76e79', '2026-09-26 13:17:18'),
(150, 'SIM-GATEWAY-001', 'VM-TVSCSS-S', '8dk4dKC7CsllNKi9caZkmVkLlviRKm3kVH7DiKEGFrs+x2D+Kf9PGfqcD3ll0hYs25/oI5X/2GptwnWuEunOH499Q5zJiusF+GgmEqL6cgq7DN4PcsO2vNGH5pYs8ndCCiMwh28rEWlDSPzvsU40rxSTNDyg4wKx+2PJ6jsYoPGFeiYoGoH2OfxIBek64dawgwVHfi4KP1ksKS4tO1SPGRiiIAddx/dM5+AVjsVgjP/cfoFvzuG3FvkTQ0lc', NULL, '2026-09-26 17:51:20', NULL, 'RECEIVED', '12063484f92a492f982981d92b740ba334ff7aedff1fcff2c38ac5fea37eb337', '2026-09-26 17:51:27'),
(151, 'SIM-GATEWAY-001', 'VM-FEDBNK-T', 'Yg6L10oL2TdhS78ZWUcK8z93wXhKIQd128DlS9z28VUH0gTZAm7/WsL2mDa6c/8lzBBCQJhEPBux72POsYLjmU0DPkFDIzXteyegBq8BVVgJbSU1zwfVaWWecjj7CS68fQ/YIITn+h9UzVQChsz5rGbKnGsla9I8OagaGgEyWAN9v2JhQMQdjF7245nyYi5C9IAdDk4zcrSgxu5zkRVGhDAesSJdK7qBcb1pXhpgEm9SWx89r8gI+zVTbBmFxTYIwrs=', NULL, '2026-09-27 01:32:27', '2026-09-27 01:37:27', 'EXPIRED', '204c68eecbf805b4e5bfdb3da8a3bf1d0e06049988ea8763e70aea6a699b9e2c', '2026-09-27 01:32:40'),
(152, 'SIM-GATEWAY-001', 'VM-FEDBNK-T', 'AM4lbGsauWG4ETXMmcA8jy/MMny816ZqUJa9qXL603UkiU4VH68jJRlOF1LaBDB2E3VUchZBZ4Ab0kpnAAOdJy8UJ9bywbYX/tzXYDlZ418YqFFxJA6a4LyVkFXY3OxhKgyZCAd7H0g2oX9fXoi6U5YnSKRIm8t6sRkZKSSv0YlJ/fhHu50rHWLsL4vT3y1/eWB2pjGfDKLsAEI9vw41cGqN1PR/2+JOFXw+3ek5zVgIe4E=', NULL, '2026-09-27 13:35:49', NULL, 'RECEIVED', 'c2736fdc53832b059285ffdbc0b2a2d92e34b1259ea7a1034d61c85e8958f772', '2026-09-27 13:35:58'),
(153, 'SIM-GATEWAY-001', 'VA-FEDBNK-T', 'zqL7yj+IwZt29tsSIrk3l1RBp8whImrH9rkAzXZo0WWjU+rTOugNRP2/6BMucXwNacRiJh/2xdb1Z+3MLqyHRF1kJ075PwGjfV8xAs0g8cRHjfMLoadZxfau+j96UBfwZwtGaH6O5cmlmAHmWR0cFNWAOs60PQWSqYs18CWJOT6SWjqSBB5DfMaDo36CeJqAfZZN4gR85VzrzFd7hN4twQ4MQJ/3wukoz9YhcoIvLg+SbjEiClko', NULL, '2026-09-27 18:21:23', NULL, 'RECEIVED', '39185c0d2d1bdee4d6ac2ecd79ef7f957027077392c0d92b4215b0c7b3c1aa63', '2026-09-27 18:21:38'),
(154, 'SIM-GATEWAY-001', 'VK-FEDBNK-T', 'B1sNRz+15f87ZnmqFYBFss4+q2P0ZsOWaAj/UR5NYqaeweJvaxftWcdkJTo8npFEGzsNDcBz0tWMdcSxremWP3daOVHgPnfsvOBxUV3JXyYXMpdYgIWs/7lXOYYSasU6EtaXQ2MDEBMISzuxIMF6jxHJPK8uVic/qB6XfJdUZamCteS818lxj82VK86mGUVfFPB0WOKnFHDiJOyk9qES27mkgFCJMrIj63JjiyETelAMvkh+Wv8=', NULL, '2026-09-27 21:48:53', NULL, 'RECEIVED', '46dff4369183e4e5daaf5bab1752c27413ee974dd09833d142696cfb44d995e8', '2026-09-27 21:49:01'),
(155, 'SIM-GATEWAY-001', 'VA-FEDBNK-T', 'DBtNe7g+elCRYNuAXNV0CPHAG2pW+D5aVWkyL31zSyuAkg4sJaOqhHaExuprWee7+jfqj+V/bcVW4Y2oABk02aOVGk0IrfmAfSMk6EVgLRL0qmz4rMuVw+KhNpodXxL3tVy2y/dcM2nYIGdel8pDuDl+4cLd/EzvsE2ArNRDMzpjh8iizwXOt/cU9yJJVWXOm9qMpxAuvGk+vvTpvubDDctTq8y+KzUkPkTZiJ5uykXI75jkCagKDg==', NULL, '2026-09-27 22:30:39', NULL, 'RECEIVED', '5c2748f7718b781727536ff460670c09ff9403ba167af534e2cce06d2cc2ea04', '2026-09-27 22:30:44'),
(156, 'SIM-GATEWAY-001', 'JM-TSAVRI-S', 'H36DBhtNYh0PrghOidqGa6CqcBtscUBXHBZZQBjhSq8VFXNF3qoeT7Ry6CaccJ7AwOEjNfKAf3VY1ZEZMh6gxjsbSDkzm6xh1j+wt4LyJyRxCLmBiyUFXMjUhvKMOvF4uBgJBCv1pWr1jvs5s7FSLchv6U4M4sJujGLQzONs/SDaDrFPN+A6TKSFuMCoU+bOLaLDdIXF/y1z02om41PPEcU15QYKq95oU+HgZSOvLlkajW97jA==', NULL, '2026-09-28 08:44:22', NULL, 'RECEIVED', '19940197766e98f63b666540bce65a56de9ade2b7c7aaf81cadb117dd678124e', '2026-09-28 08:44:42'),
(157, 'SIM-GATEWAY-001', 'VD-UTKSPR-S', 'kItevqTFbjDbvCkltzz5ggQR9iG0r+DU0wLHaNWgzApQe2i6CSUXW5eWWjMOJQNOCJIoNMOKqASehVzR27x52Pn3b71XQKDe0QGWdLMCcGD2Ct6emkC1bnMKkCGS0z8UgyIxKUizcmz09mBHsnMr/zBIB2CLEUac6EAab+O5WAyTaHYqIg245Cx7YhKIf7tAKZ3GTTgG2v3/sdhVpLFhsSBs2i7paOOwPbohNCjPaWrUXYbxqPSX4KFxrIU=', NULL, '2026-09-28 11:04:00', '2026-09-28 11:09:00', 'EXPIRED', '7f9a9fca345df686c8f670a5dcedb938898f35b1227146ffa502a7037c193796', '2026-09-28 11:04:12'),
(158, 'SIM-GATEWAY-001', 'JM-TSAVRI-S', 'FnhgwTVnTYso5wKzeL0gJRgMaTNtnbzV1sM6sB058Farvqi8VUjW4PhvKEDaTfg3Wg81DSkqjywSTONydc5+YhbROFrod/PmRCDPGgvzuhme05Z1CHmn5UR2fmJAmeb/c7VyKEpHzUR0lJqXkHLoZUwHqsoxllTAeUGY+7svGEE5IDwgcxnybS3uklTWPjzHHarC+SvQz/PVIIxL0r6ZX6CAZHfd1GBKDO2h5PwQDtifJHxE8Q==', NULL, '2026-09-28 12:54:41', NULL, 'RECEIVED', 'beac279a62d3ea69e9bd1aec0931e36b8b38ec7368052fa18f91ac6e876e71dc', '2026-09-28 12:54:56'),
(159, 'SIM-GATEWAY-001', 'JD-SLCBNK-T', 'zWJqdOHm++ZYUmPcXUo4fIM84QIPO1KwFKDael8iV83uS2bwewEzquzuitHjPSmk/hQWMfbsJ1Xir0UNatIQ9WeTGkBDE9mVOBDOW2gjpEAeEN6tH9KB9w7Jp3tiNFLT6kGcr1seZNOwAZlEkW12WbnVRmqJTj3H8HgqWgs=', NULL, '2026-09-28 13:19:51', '2026-09-28 13:24:51', 'EXPIRED', 'd438fd2aeb8530575f28580b3e6583ce521bc81a27f03cabfc3a26d65b4f1d07', '2026-09-28 13:19:55'),
(160, 'SIM-GATEWAY-001', 'JX-FEDBNK-T', 'IasGx3RgK/j3QPk2lmnE4aU0IwHe6fvObM5OmcpR3XKEmuJRuxZjcjJTnUE9ARLr+fw4S83ILZqipN8S61MF/ZdMdWqzU23nclBR6C/KFKY4GVowO4tD1FVuIqUNv3PCJQYICuqRGUMsu4dwk8sR8UKx+7RZ2w5+ePQbusmlHa/VGS7qRTdbweAki9tdQms9syRVhgMeC5WxxHedkf7fAKKf8E19jDilC2vwLyuCGV7wi/czdEM=', NULL, '2026-09-28 13:21:15', NULL, 'RECEIVED', 'f133f9ca4bbf423051bcd707793f8ddd855753390c6a4f986ebfde8256d3a4d7', '2026-09-28 13:21:22'),
(161, 'SIM-GATEWAY-001', 'JK-UTKSPR-S', '43QNiZy1kRljcd0fqcJYGiInH1larYmheciWRKJcvabHDw0T/ImApDNK7QLoy3TG4RpoMlPp2kCTo7muacBTfVsiIbtF2xv8RuO4FsanK90xEyR0DCMm4QwdNkHl4U+yqn8b/giTkfZ85ZFUeewujbKASBdQ0X+boiwlZX8/Po9k5U6K46SyRQSkTz32/gX1Tjg2mkRFtstP/dPQKJum+nRosBFa3AuNK4f1VGY1rdJ4kC7NXhfsWty9Npk=', NULL, '2026-09-28 13:48:28', '2026-09-28 13:53:28', 'EXPIRED', '219f89a78d8844e41cd05d9babe58249ca09d54141b87eb46e0cbf2f4da86149', '2026-09-28 13:48:41'),
(162, 'SIM-GATEWAY-001', 'VM-UTKSPR-S', 'wId94bmh3vGQ4uCPdu5tMfcfqhzD9B8ODJEYBnmcV8OmzkC0480kT+46Miu8jBBncSOPKZSpq/DQrRwmxO+4sPX4oIiUfBUQQcOjOc8B+cUgnfra0VFXT2+ciWuIftp7kvFWFBnSFvQNGlaSRR59l2BFZfvp/dxVj64h8Pdr7evW+z0s3Xg17ZMrhB9H0F1Za2oNIByY3EGMpEvtLXckdIQwGCjxDx07da1tjSt1kIooaR3LlMAXv+TNM2E=', NULL, '2026-09-28 17:26:12', '2026-09-28 17:31:12', 'EXPIRED', '503a86653e2a7a871a056ab0b20e6ec942f5ef5581d9e1d7dcb1bc12cd871b19', '2026-09-28 17:26:23');

-- --------------------------------------------------------

--
-- Table structure for table `kyb_documents`
--

CREATE TABLE `kyb_documents` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `customer_id` bigint(20) UNSIGNED NOT NULL,
  `doc_type` varchar(40) NOT NULL,
  `file_path` varchar(255) NOT NULL,
  `file_orig` varchar(180) NOT NULL,
  `status` varchar(24) NOT NULL DEFAULT 'PENDING',
  `reviewed_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `merchant_clients`
--

CREATE TABLE `merchant_clients` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `client_id` varchar(64) NOT NULL,
  `client_name` varchar(120) NOT NULL,
  `api_secret` varchar(128) NOT NULL,
  `active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `merchant_clients`
--

INSERT INTO `merchant_clients` (`id`, `client_id`, `client_name`, `api_secret`, `active`, `created_at`) VALUES
(1, 'YEEDIPAY-PORTAL', 'YeediPay Portal', '25ebef0d6d15a6958e9a36249519d23f7c6d8247f8395c90d29a9b8cb58d0b9d', 1, '2026-09-29 12:23:23');

-- --------------------------------------------------------

--
-- Table structure for table `otp_codes`
--

CREATE TABLE `otp_codes` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `customer_id` bigint(20) UNSIGNED NOT NULL,
  `otp_hash` varchar(128) NOT NULL,
  `expires_at` datetime NOT NULL,
  `consumed` tinyint(1) NOT NULL DEFAULT 0,
  `attempts` int(11) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `otp_requests`
--

CREATE TABLE `otp_requests` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `reference_id` varchar(120) NOT NULL,
  `expected_sender` varchar(120) DEFAULT NULL,
  `matched_sms_id` bigint(20) UNSIGNED DEFAULT NULL,
  `status` enum('PENDING','MATCHED','USED','EXPIRED','CANCELLED') NOT NULL DEFAULT 'PENDING',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `expires_at` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `payout_connectors`
--

CREATE TABLE `payout_connectors` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `customer_id` bigint(20) UNSIGNED NOT NULL,
  `bank_id` bigint(20) UNSIGNED NOT NULL,
  `mode` varchar(16) NOT NULL DEFAULT 'UAT',
  `status` varchar(24) NOT NULL DEFAULT 'UNVERIFIED',
  `verified_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `portal_bank_sms_events`
--

CREATE TABLE `portal_bank_sms_events` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `device_id` varchar(64) NOT NULL,
  `android_event_id` char(64) DEFAULT NULL,
  `bank_code` varchar(32) NOT NULL,
  `amount` decimal(14,2) NOT NULL,
  `utr` varchar(40) DEFAULT NULL,
  `account_last4` char(4) NOT NULL,
  `sender_id` varchar(80) DEFAULT NULL,
  `sms_time` datetime NOT NULL,
  `raw_body` text DEFAULT NULL,
  `match_status` varchar(32) NOT NULL,
  `transaction_id` bigint(20) UNSIGNED DEFAULT NULL,
  `candidate_refs` varchar(500) DEFAULT NULL,
  `created_at` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `portal_bank_sms_events`
--

INSERT INTO `portal_bank_sms_events` (`id`, `device_id`, `android_event_id`, `bank_code`, `amount`, `utr`, `account_last4`, `sender_id`, `sms_time`, `raw_body`, `match_status`, `transaction_id`, `candidate_refs`, `created_at`) VALUES
(1, 'YP2BD9DACA', NULL, 'ICICI', 500.00, '528746392018', '1234', NULL, '2026-09-29 03:23:59', 'Your a/c XX1234 is credited with INR 500.00 on 29-09-2026 08:53:59. UPI/528746392018. Avl Bal: INR 5,500.00', 'REVIEW_REQUIRED', NULL, 'YDPT0001ICI09290323597A80A9,YDPT0001ICI0929032359F9B42D', '2026-09-29 03:23:59'),
(2, 'YP2BD9DACA', NULL, 'ICICI', 777.00, '528746392099', '1234', NULL, '2026-09-29 03:23:59', 'Your a/c XX1234 is credited with INR 777.00 on 29-09-2026 08:53:59. UPI/528746392099. Avl Bal: INR 5,500.00', 'UNMATCHED', NULL, NULL, '2026-09-29 03:23:59'),
(4, 'YP2BD9DACA', NULL, 'ICICI', 500.00, '613142651151', '1234', NULL, '2026-09-29 03:24:55', 'Your a/c XX1234 is credited with INR 500.00 on 29-09-2026 08:54:55. UPI/613142651151. Avl Bal: INR 5,500.00', 'REVIEW_REQUIRED', NULL, 'YDPT0001ICI09290323597A80A9,YDPT0001ICI0929032359F9B42D,YDPT0001ICI09290324557A9737,YDPT0001ICI09290324553D79A1', '2026-09-29 03:24:55'),
(5, 'YP2BD9DACA', NULL, 'ICICI', 777.00, '722812351725', '1234', NULL, '2026-09-29 03:24:55', 'Your a/c XX1234 is credited with INR 777.00 on 29-09-2026 08:54:55. UPI/722812351725. Avl Bal: INR 5,500.00', 'UNMATCHED', NULL, NULL, '2026-09-29 03:24:55');

-- --------------------------------------------------------

--
-- Table structure for table `pricing_overrides`
--

CREATE TABLE `pricing_overrides` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `customer_id` bigint(20) UNSIGNED NOT NULL,
  `charge_key` varchar(64) NOT NULL,
  `unit_price` decimal(14,2) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `pricing_plans`
--

CREATE TABLE `pricing_plans` (
  `plan_key` varchar(32) NOT NULL,
  `monthly_fee` decimal(14,2) NOT NULL DEFAULT 0.00,
  `included_payouts` int(11) NOT NULL DEFAULT 0,
  `extra_payout_fee` decimal(14,2) NOT NULL DEFAULT 0.00,
  `included_banks` int(11) NOT NULL DEFAULT 0,
  `extra_bank_month` decimal(14,2) NOT NULL DEFAULT 0.00,
  `custom_format_setup` decimal(14,2) NOT NULL DEFAULT 0.00,
  `h2h_month` decimal(14,2) NOT NULL DEFAULT 0.00,
  `api_month` decimal(14,2) NOT NULL DEFAULT 0.00,
  `white_label_month` decimal(14,2) NOT NULL DEFAULT 0.00,
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `pricing_plans`
--

INSERT INTO `pricing_plans` (`plan_key`, `monthly_fee`, `included_payouts`, `extra_payout_fee`, `included_banks`, `extra_bank_month`, `custom_format_setup`, `h2h_month`, `api_month`, `white_label_month`, `updated_at`) VALUES
('business', 7499.00, 5000, 2.00, 2, 500.00, 2500.00, 5000.00, 5000.00, 10000.00, '2026-09-28 11:02:55'),
('pro', 14999.00, 15000, 1.50, 3, 500.00, 2500.00, 5000.00, 5000.00, 10000.00, '2026-09-28 11:02:55'),
('starter', 2999.00, 1000, 3.00, 1, 500.00, 2500.00, 5000.00, 5000.00, 10000.00, '2026-09-28 11:02:55');

-- --------------------------------------------------------

--
-- Table structure for table `qr_payment_orders`
--

CREATE TABLE `qr_payment_orders` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `payment_id` varchar(64) NOT NULL,
  `merchant_client_id` bigint(20) UNSIGNED DEFAULT NULL,
  `merchant_order_id` varchar(100) DEFAULT NULL,
  `public_token_hash` char(64) NOT NULL,
  `receiving_account_id` bigint(20) UNSIGNED NOT NULL,
  `bank_name` varchar(20) NOT NULL,
  `amount` decimal(14,2) NOT NULL,
  `status` enum('PENDING','SUCCESS','FAILED','EXPIRED') NOT NULL DEFAULT 'PENDING',
  `submitted_utr` varchar(35) DEFAULT NULL COMMENT 'Auto-filled only from verified bank SMS',
  `customer_ack_at` datetime DEFAULT NULL,
  `last_status_poll_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `expires_at` datetime NOT NULL,
  `sms_confirmed_at` datetime DEFAULT NULL,
  `paid_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `qr_payment_orders`
--

INSERT INTO `qr_payment_orders` (`id`, `payment_id`, `merchant_client_id`, `merchant_order_id`, `public_token_hash`, `receiving_account_id`, `bank_name`, `amount`, `status`, `submitted_utr`, `customer_ack_at`, `last_status_poll_at`, `created_at`, `expires_at`, `sms_confirmed_at`, `paid_at`) VALUES
(1, 'YDP33234KKK09300716300B5828', 1, 'YDP33234KKK09300716300B5828', '39a1bcde1e12b7fe0ec03c812aa230ac024a213a899dcf0be9d34a4f6762b8ec', 2, 'ICICI', 1.00, 'PENDING', NULL, NULL, NULL, '2026-09-30 07:16:31', '2026-09-30 07:26:31', NULL, NULL),
(2, 'YDP33234KKK1001140308B7648E', 1, 'YDP33234KKK1001140308B7648E', 'd33f120dd532eecb8c82296ccc7c691aebd102c2966d48d868978c86bea89a8e', 2, 'ICICI', 1.00, 'PENDING', NULL, NULL, NULL, '2026-10-01 14:03:08', '2026-10-01 14:13:08', NULL, NULL),
(3, 'YDP33234KKK100114041521A3D4', 1, 'YDP33234KKK100114041521A3D4', '992b95e7bf648734517a1631b5045478fae296d6e74e474c080b4a2fea5a0ef9', 2, 'ICICI', 1.00, 'PENDING', NULL, NULL, NULL, '2026-10-01 14:04:15', '2026-10-01 14:14:15', NULL, NULL),
(4, 'YDP33234KKK10011407315EE7A0', 1, 'YDP33234KKK10011407315EE7A0', 'b930d4f2267c1a2b30ecb24174c9bb841fd7cd47a16a548206cb579c275bf70d', 2, 'ICICI', 999.92, 'PENDING', NULL, NULL, NULL, '2026-10-01 14:07:31', '2026-10-01 14:17:31', NULL, NULL),
(5, 'YDP33234KKK1001140902809D29', 1, 'YDP33234KKK1001140902809D29', '5d9a793fd2e7bd133490fe22059b0f04a940bb1301269da22c9da994d777aad4', 2, 'ICICI', 1999.78, 'PENDING', NULL, NULL, NULL, '2026-10-01 14:09:03', '2026-10-01 14:19:03', NULL, NULL),
(6, 'YDP33234KKK10011409099A5FDE', 1, 'YDP33234KKK10011409099A5FDE', '62b273c85b258c8272e253655921b9acd9a1d05c8b783fa5cbcea9641a088032', 2, 'ICICI', 1999.73, 'PENDING', NULL, NULL, NULL, '2026-10-01 14:09:09', '2026-10-01 14:19:09', NULL, NULL),
(7, 'YDP33234KKK1001140915212F08', 1, 'YDP33234KKK1001140915212F08', 'eef5552662b95896eec6a5eac6082564f34c1ed2c7f4fbf8e80fe546120d8059', 2, 'ICICI', 1999.94, 'PENDING', NULL, NULL, NULL, '2026-10-01 14:09:15', '2026-10-01 14:19:15', NULL, NULL),
(8, 'YDP33234KKK10011409206F26AB', 1, 'YDP33234KKK10011409206F26AB', '9839eec1ed0b7f20d7b976d4f611f0c0f7046bef8b030ea5fe0f7fda02034097', 2, 'ICICI', 1999.81, 'PENDING', NULL, NULL, NULL, '2026-10-01 14:09:20', '2026-10-01 14:19:20', NULL, NULL),
(9, 'YDP33234KKK100114121989E038', 1, 'YDP33234KKK100114121989E038', '5f1c35c98a7108a9be86e39aad4cd11352117d97e6bea83dd473bcef7e5dc42d', 2, 'ICICI', 99.65, 'PENDING', NULL, NULL, NULL, '2026-10-01 14:12:19', '2026-10-01 14:22:19', NULL, NULL),
(10, 'YDP33234JKM1001141537F54000', 1, 'YDP33234JKM1001141537F54000', 'edeeb486dc4031c03be25a522919bb57376865713c5ac7de30151a154c7976de', 1, 'ICICI', 99.51, 'PENDING', NULL, NULL, NULL, '2026-10-01 14:15:37', '2026-10-01 14:25:37', NULL, NULL),
(11, 'YDP33234JKM1001141848961BAC', 1, 'YDP33234JKM1001141848961BAC', '32bdcb849c9f5f3c75f8577d55f4b194249739b9edd97e46da1db3b2ac3c2364', 1, 'ICICI', 99.59, 'PENDING', NULL, NULL, NULL, '2026-10-01 14:18:48', '2026-10-01 14:28:48', NULL, NULL),
(12, 'YDP33234JKM10011420071AD589', 1, 'YDP33234JKM10011420071AD589', 'c5a6e3788b191e08cd00df86f81c9bd5d165a0b331838406b851364e630c0928', 1, 'ICICI', 99.86, 'PENDING', NULL, NULL, NULL, '2026-10-01 14:20:07', '2026-10-01 14:30:07', NULL, NULL),
(13, 'YDP33234JKM100114231441A394', 1, 'YDP33234JKM100114231441A394', '3ec1d91e93d20d6b9f221118999a38bbb34cbc0b01a44457b301ce54a7063083', 1, 'ICICI', 49999.88, 'PENDING', NULL, NULL, NULL, '2026-10-01 14:23:14', '2026-10-01 14:33:14', NULL, NULL),
(14, 'YDP33234JKM1001142319E39309', 1, 'YDP33234JKM1001142319E39309', '112a507cc1c4c87868e632ea2de35d367631428c29c09fcb6d7de6013abcdb9b', 1, 'ICICI', 49999.54, 'PENDING', NULL, NULL, NULL, '2026-10-01 14:23:19', '2026-10-01 14:33:19', NULL, NULL),
(15, 'YDP33234JKM1001143520BB0AFC', 1, 'YDP33234JKM1001143520BB0AFC', 'ad91b602517385876312088bd57fafbe0d1a03ae0f9def4dca0d0b464e8314ec', 1, 'ICICI', 49999.53, 'PENDING', NULL, NULL, NULL, '2026-10-01 14:35:20', '2026-10-01 14:45:20', NULL, NULL),
(16, 'YDP33234JKM1001143723213462', 1, 'YDP33234JKM1001143723213462', 'abf2ee7612ca4f7d411f190bb24d8ce99116bdf0e4cde8dae506013fe6e18365', 1, 'ICICI', 1999.62, 'PENDING', NULL, NULL, NULL, '2026-10-01 14:37:23', '2026-10-01 14:47:23', NULL, NULL),
(17, 'YDP33234JKM1001143723267379', 1, 'YDP33234JKM1001143723267379', '673477bc7301546ae43634e1a4e0be99a63ee2fede57c1ee965571282b9c605f', 1, 'ICICI', 2000.00, 'PENDING', NULL, NULL, NULL, '2026-10-01 14:37:23', '2026-10-01 14:47:23', NULL, NULL),
(18, 'YDP33234JKM1001143756EE3345', 1, 'YDP33234JKM1001143756EE3345', 'b0952f3dac9b0c1369650561daf33d6bf31f8b1ec47ab74266f977dda993e2b2', 1, 'ICICI', 49999.86, 'PENDING', NULL, NULL, NULL, '2026-10-01 14:37:56', '2026-10-01 14:47:56', NULL, NULL),
(19, 'YDP33234JKM1001143814E944B5', 1, 'YDP33234JKM1001143814E944B5', 'ef28b8e737fc8661300a0addee7d8cf70d985b527c39681b8d80e9cd359105ea', 1, 'ICICI', 299.97, 'PENDING', NULL, NULL, NULL, '2026-10-01 14:38:14', '2026-10-01 14:48:14', NULL, NULL),
(20, 'YDP33234JKM1001143820523558', 1, 'YDP33234JKM1001143820523558', 'af9d38fb3bc70a3bd78eac87613d4467bec7e573e39a3678a589095f318d57e0', 1, 'ICICI', 10000.00, 'PENDING', NULL, NULL, NULL, '2026-10-01 14:38:20', '2026-10-01 14:48:20', NULL, NULL),
(21, 'YDP33234JKM10011439321EEFA9', 1, 'YDP33234JKM10011439321EEFA9', 'bfd2e7892319104bca8c6e469bbc12de9e6c7eca32f61cc6c86fdb63eb918c5f', 1, 'ICICI', 19999.92, 'PENDING', NULL, NULL, NULL, '2026-10-01 14:39:32', '2026-10-01 14:49:32', NULL, NULL),
(22, 'YDP33234JKM100114422755FFFC', 1, 'YDP33234JKM100114422755FFFC', 'bcd9012dd70f8035a84b73dfdc6e87bf41655c2061675b1bdcdd1229faad9674', 1, 'ICICI', 19999.80, 'PENDING', NULL, NULL, NULL, '2026-10-01 14:42:27', '2026-10-01 14:52:27', NULL, NULL),
(23, 'YDP33234JKM10011445178A722B', 1, 'YDP33234JKM10011445178A722B', 'd22e4ec2a035e77536836054bd56b09af055f34382d8ba5eefcf7b5c05226ecb', 1, 'ICICI', 19999.72, 'PENDING', NULL, NULL, NULL, '2026-10-01 14:45:17', '2026-10-01 14:55:17', NULL, NULL),
(24, 'YDP33234JKM1001144828F778C6', 1, 'YDP33234JKM1001144828F778C6', '08866aead9c2e41a9333af0d0acc17da6ec4751548e94c938797130d1b2948aa', 1, 'ICICI', 19999.68, 'PENDING', NULL, NULL, NULL, '2026-10-01 14:48:28', '2026-10-01 14:58:28', NULL, NULL),
(25, 'YDP33234JKM100114492453B07D', 1, 'YDP33234JKM100114492453B07D', 'e4cec26191994264633f4d3df3fa070d9c0f44539422688fa0c997a077e353bf', 1, 'ICICI', 19999.81, 'PENDING', NULL, NULL, NULL, '2026-10-01 14:49:24', '2026-10-01 14:59:24', NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `settlements`
--

CREATE TABLE `settlements` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `reference` varchar(64) NOT NULL,
  `range_start` date NOT NULL,
  `range_end` date NOT NULL,
  `txn_count` int(11) NOT NULL DEFAULT 0,
  `gross_amount` decimal(14,2) NOT NULL DEFAULT 0.00,
  `fee_amount` decimal(14,2) NOT NULL DEFAULT 0.00,
  `tax_amount` decimal(14,2) NOT NULL DEFAULT 0.00,
  `net_amount` decimal(14,2) NOT NULL DEFAULT 0.00,
  `status` varchar(32) NOT NULL DEFAULT 'REQUESTED',
  `bank_ref` varchar(160) DEFAULT NULL,
  `created_by` varchar(80) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `settled_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `sms_devices`
--

CREATE TABLE `sms_devices` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `device_id` varchar(100) NOT NULL,
  `sim_label` varchar(100) DEFAULT NULL,
  `mobile_number` varchar(30) DEFAULT NULL,
  `account_number` varchar(20) DEFAULT NULL,
  `token_hash` varchar(255) NOT NULL,
  `status` enum('ACTIVE','DISABLED') NOT NULL DEFAULT 'ACTIVE',
  `last_seen_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `sms_devices`
--

INSERT INTO `sms_devices` (`id`, `device_id`, `sim_label`, `mobile_number`, `account_number`, `token_hash`, `status`, `last_seen_at`, `created_at`, `updated_at`) VALUES
(1, 'SIM-GATEWAY-001', 'Company Dedicated SIM', '+919989222947', NULL, '$2y$10$SWuABL2t8Fbs.SUhM7Yqp.IMr5JBz1UpJUaHetkST8WT9XRhSUO.m', 'ACTIVE', '2026-09-28 17:26:24', '2026-09-07 13:36:13', '2026-09-28 17:26:24');

-- --------------------------------------------------------

--
-- Table structure for table `sms_gateway_settings`
--

CREATE TABLE `sms_gateway_settings` (
  `id` tinyint(3) UNSIGNED NOT NULL,
  `enabled` tinyint(1) NOT NULL DEFAULT 0,
  `gateway_name` varchar(120) NOT NULL DEFAULT 'Generic SMS Gateway',
  `endpoint_url` text DEFAULT NULL,
  `http_method` varchar(8) NOT NULL DEFAULT 'POST',
  `content_type` varchar(16) NOT NULL DEFAULT 'json',
  `auth_header_name` varchar(80) NOT NULL DEFAULT 'Authorization',
  `auth_prefix` varchar(40) NOT NULL DEFAULT 'Bearer ',
  `auth_token_encrypted` text DEFAULT NULL,
  `sender_id` varchar(32) NOT NULL DEFAULT 'YEEDIPAY',
  `request_template` text NOT NULL,
  `otp_message` text NOT NULL,
  `pending_message` text NOT NULL,
  `success_message` text NOT NULL,
  `failed_message` text NOT NULL,
  `expired_message` text NOT NULL,
  `otp_template_id` varchar(120) NOT NULL DEFAULT '',
  `pending_template_id` varchar(120) NOT NULL DEFAULT '',
  `success_template_id` varchar(120) NOT NULL DEFAULT '',
  `failed_template_id` varchar(120) NOT NULL DEFAULT '',
  `expired_template_id` varchar(120) NOT NULL DEFAULT '',
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `sms_gateway_settings`
--

INSERT INTO `sms_gateway_settings` (`id`, `enabled`, `gateway_name`, `endpoint_url`, `http_method`, `content_type`, `auth_header_name`, `auth_prefix`, `auth_token_encrypted`, `sender_id`, `request_template`, `otp_message`, `pending_message`, `success_message`, `failed_message`, `expired_message`, `otp_template_id`, `pending_template_id`, `success_template_id`, `failed_template_id`, `expired_template_id`, `updated_at`) VALUES
(1, 0, 'Generic SMS Gateway', NULL, 'POST', 'json', 'Authorization', 'Bearer ', NULL, 'YEEDIPAY', '{\"phone\":\"{phone}\",\"message\":\"{message}\",\"sender_id\":\"{sender_id}\",\"template_id\":\"{template_id}\"}', 'OTP for verification is {otp}. Please do not share this otp with anyone. --JAYALAKSHMI MUTUALLY AIDED COOPERATIVE THRIFT AND CREDIT SOCIETY LTD', 'YEEDIPAY payment initiated. Ref {reference}. Status PENDING. Please wait for verification.', 'Dear Customer, Transaction with reference number {reference} for Rs.{amount} has been credited to the beneficiary account XXXXX{account_tail} on {sms_date} -JAYALAKSHMI MUTUALLY AIDED COOPERATIVE THRIFT AND CREDIT SOCIETY LTD', 'YEEDIPAY payment FAILED. Ref {reference}.', 'YEEDIPAY payment verification window expired. Ref {reference}. Please contact support if payment was made.', '1707173641664963524', '', '1707174530074657515', '', '', '2026-09-29 12:59:51');

-- --------------------------------------------------------

--
-- Table structure for table `sms_outbox`
--

CREATE TABLE `sms_outbox` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `event_key` varchar(180) NOT NULL,
  `phone` varchar(24) NOT NULL,
  `message` text NOT NULL,
  `template_id` varchar(120) NOT NULL DEFAULT '',
  `status` enum('QUEUED','RETRY','SENT','FAILED') NOT NULL DEFAULT 'QUEUED',
  `attempts` int(11) NOT NULL DEFAULT 0,
  `last_error` text NOT NULL DEFAULT '',
  `next_attempt_at` datetime NOT NULL DEFAULT current_timestamp(),
  `sent_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `sms_relay_devices`
--

CREATE TABLE `sms_relay_devices` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `device_id` varchar(64) NOT NULL,
  `device_label` varchar(100) NOT NULL,
  `api_secret` varchar(128) NOT NULL,
  `active` tinyint(1) NOT NULL DEFAULT 1,
  `last_seen_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `sms_relay_devices`
--

INSERT INTO `sms_relay_devices` (`id`, `device_id`, `device_label`, `api_secret`, `active`, `last_seen_at`, `created_at`) VALUES
(1, 'YEEDI-EF427D43', 'BANK PHONE', 'd13e4ec658419fdbb1862194180caaf22d309677c8c5b43a9c7260ff0ca83bbe', 1, NULL, '2026-09-29 10:49:17');

-- --------------------------------------------------------

--
-- Table structure for table `source_bank_verifications`
--

CREATE TABLE `source_bank_verifications` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `customer_id` bigint(20) UNSIGNED NOT NULL,
  `bank_name` varchar(120) NOT NULL,
  `account_number` varchar(32) NOT NULL,
  `ifsc` varchar(16) NOT NULL,
  `status` varchar(24) NOT NULL DEFAULT 'PENDING',
  `verified_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `support_tickets`
--

CREATE TABLE `support_tickets` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `reference` varchar(32) NOT NULL,
  `customer_id` bigint(20) UNSIGNED NOT NULL,
  `subject` varchar(220) NOT NULL,
  `category` varchar(64) NOT NULL DEFAULT 'GENERAL',
  `message` text NOT NULL,
  `status` varchar(24) NOT NULL DEFAULT 'OPEN',
  `priority` varchar(16) NOT NULL DEFAULT 'NORMAL',
  `assigned_to` varchar(80) DEFAULT NULL,
  `admin_note` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `resolved_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `support_ticket_replies`
--

CREATE TABLE `support_ticket_replies` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `ticket_id` bigint(20) UNSIGNED NOT NULL,
  `author_type` varchar(16) NOT NULL,
  `author_name` varchar(120) NOT NULL DEFAULT '',
  `message` text NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `system_config`
--

CREATE TABLE `system_config` (
  `id` tinyint(3) UNSIGNED NOT NULL,
  `manual_active_bank_id` bigint(20) UNSIGNED DEFAULT NULL,
  `rotation_enabled` tinyint(1) NOT NULL DEFAULT 0,
  `rotation_started_at` datetime DEFAULT NULL,
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `system_config`
--

INSERT INTO `system_config` (`id`, `manual_active_bank_id`, `rotation_enabled`, `rotation_started_at`, `updated_at`) VALUES
(1, 2, 1, '2026-09-29 06:14:51', '2026-09-29 06:14:51');

-- --------------------------------------------------------

--
-- Table structure for table `transactions`
--

CREATE TABLE `transactions` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `customer_id` bigint(20) UNSIGNED NOT NULL,
  `bank_id` bigint(20) UNSIGNED NOT NULL,
  `reference` varchar(120) NOT NULL,
  `status` enum('PENDING','SUCCESS','FAILED','EXPIRED') NOT NULL DEFAULT 'PENDING',
  `requested_amount` decimal(14,2) DEFAULT NULL,
  `confirmed_amount` decimal(14,2) DEFAULT NULL,
  `intent_uri` text NOT NULL,
  `upi_txn_id` varchar(160) DEFAULT NULL,
  `rrn` varchar(160) DEFAULT NULL,
  `payer_vpa` varchar(180) DEFAULT NULL,
  `confirmation_source` varchar(64) DEFAULT NULL,
  `raw_confirmation` text DEFAULT NULL,
  `customer_reported_utr` varchar(160) DEFAULT NULL,
  `customer_reported_email` varchar(180) DEFAULT NULL,
  `customer_reported_at` datetime DEFAULT NULL,
  `utr_screenshot_path` varchar(255) DEFAULT NULL,
  `utr_screenshot_orig` varchar(180) DEFAULT NULL,
  `limit_breach` tinyint(1) NOT NULL DEFAULT 0,
  `limit_note` text NOT NULL,
  `service` varchar(64) NOT NULL DEFAULT 'UPI',
  `settlement_status` varchar(32) NOT NULL DEFAULT 'UNSETTLED',
  `invoice_ref` varchar(160) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `settlement_id` bigint(20) UNSIGNED DEFAULT NULL,
  `sms_match` varchar(32) DEFAULT NULL,
  `adc_payment_id` varchar(64) DEFAULT NULL,
  `adc_public_token` varchar(128) DEFAULT NULL,
  `customer_paid_ack_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `transactions`
--

INSERT INTO `transactions` (`id`, `customer_id`, `bank_id`, `reference`, `status`, `requested_amount`, `confirmed_amount`, `intent_uri`, `upi_txn_id`, `rrn`, `payer_vpa`, `confirmation_source`, `raw_confirmation`, `customer_reported_utr`, `customer_reported_email`, `customer_reported_at`, `utr_screenshot_path`, `utr_screenshot_orig`, `limit_breach`, `limit_note`, `service`, `settlement_status`, `invoice_ref`, `created_at`, `updated_at`, `settlement_id`, `sms_match`, `adc_payment_id`, `adc_public_token`, `customer_paid_ack_at`) VALUES
(8, 1, 2, 'YDPT0001JKM092903380306C46A', 'PENDING', 180.00, NULL, 'upi://pay?pa=msgreenrisingagrilimited.eazypay@icici&pn=&tr=EZYS9640941904&cu=INR&mc=1520', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, '', 'UPI', 'UNSETTLED', NULL, '2026-09-29 03:38:03', '2026-09-29 03:38:03', NULL, NULL, NULL, NULL, NULL),
(9, 2, 2, 'YDP33234JKM09290402107AA1A3', 'PENDING', 1.00, NULL, 'upi://pay?pa=msgreenrisingagrilimited.eazypay@icici&pn=&tr=EZYS9640941904&cu=INR&mc=1520', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, '', 'UPI', 'UNSETTLED', NULL, '2026-09-29 04:02:10', '2026-09-29 04:02:10', NULL, NULL, NULL, NULL, NULL),
(10, 2, 3, 'YDP33234KKK09300716300B5828', 'PENDING', 1.00, NULL, 'upi://pay?pa=msgreenrisingagrilimited.eazypay@icici&pn=&tr=EZYS9640943427&am=1.00&cu=INR&mc=1520', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, '', 'UPI', 'UNSETTLED', NULL, '2026-09-30 07:16:30', '2026-09-30 07:16:31', NULL, NULL, 'YDP33234KKK09300716300B5828', 'hItRVqv037c_Yg1VYWHoyMNmQwHy2kXL', NULL),
(11, 2, 3, 'YDP33234KKK1001140308B7648E', 'PENDING', 1.00, NULL, 'upi://pay?pa=msgreenrisingagrilimited.eazypay@icici&pn=K%20K%20K%20ENTERPRISES&tr=EZYS9640943427&am=1.00&cu=INR&mc=1520', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, '', 'UPI', 'UNSETTLED', NULL, '2026-10-01 14:03:08', '2026-10-01 14:03:08', NULL, NULL, 'YDP33234KKK1001140308B7648E', 'fBoolkk46vtcD-iS_aZBTWUoX276N2VV', NULL),
(12, 2, 3, 'YDP33234KKK100114041521A3D4', 'PENDING', 1.00, NULL, 'upi://pay?pa=msgreenrisingagrilimited.eazypay@icici&pn=K%20K%20K%20ENTERPRISES&tr=EZYS9640943427&am=1.00&cu=INR&mc=1520', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, '', 'UPI', 'UNSETTLED', NULL, '2026-10-01 14:04:15', '2026-10-01 14:04:15', NULL, NULL, 'YDP33234KKK100114041521A3D4', 'oMaQGqOhURNDJPUYqS1LS9homn3shsNm', NULL),
(13, 2, 3, 'YDP33234KKK10011407315EE7A0', 'PENDING', 999.92, NULL, 'upi://pay?pa=msgreenrisingagrilimited.eazypay@icici&pn=K%20K%20K%20ENTERPRISES&tr=EZYS9640943427&am=999.92&cu=INR&mc=1520', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, '', 'UPI', 'UNSETTLED', NULL, '2026-10-01 14:07:31', '2026-10-01 14:07:31', NULL, NULL, 'YDP33234KKK10011407315EE7A0', 'wKXjFyKY_D3VAUop6BzlJaUmNs2KJbWv', NULL),
(14, 2, 3, 'YDP33234KKK1001140902809D29', 'PENDING', 1999.78, NULL, 'upi://pay?pa=msgreenrisingagrilimited.eazypay@icici&pn=K%20K%20K%20ENTERPRISES&tr=EZYS9640943427&am=1999.78&cu=INR&mc=1520', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, '', 'UPI', 'UNSETTLED', NULL, '2026-10-01 14:09:02', '2026-10-01 14:09:03', NULL, NULL, 'YDP33234KKK1001140902809D29', 'ryGRhB4tlR0GWKFLACJHJ2WZ5mXujEpV', NULL),
(15, 2, 3, 'YDP33234KKK10011409099A5FDE', 'PENDING', 1999.73, NULL, 'upi://pay?pa=msgreenrisingagrilimited.eazypay@icici&pn=K%20K%20K%20ENTERPRISES&tr=EZYS9640943427&am=1999.73&cu=INR&mc=1520', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, '', 'UPI', 'UNSETTLED', NULL, '2026-10-01 14:09:09', '2026-10-01 14:09:09', NULL, NULL, 'YDP33234KKK10011409099A5FDE', 'xrwQU2oDkSis1Xhjek8FX_Bx3pQtv83w', NULL),
(16, 2, 3, 'YDP33234KKK1001140915212F08', 'PENDING', 1999.94, NULL, 'upi://pay?pa=msgreenrisingagrilimited.eazypay@icici&pn=K%20K%20K%20ENTERPRISES&tr=EZYS9640943427&am=1999.94&cu=INR&mc=1520', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, '', 'UPI', 'UNSETTLED', NULL, '2026-10-01 14:09:15', '2026-10-01 14:09:15', NULL, NULL, 'YDP33234KKK1001140915212F08', 'XY2HLqN57DRugmSZEJbjI_PcNSy5N0vl', NULL),
(17, 2, 3, 'YDP33234KKK10011409206F26AB', 'PENDING', 1999.81, NULL, 'upi://pay?pa=msgreenrisingagrilimited.eazypay@icici&pn=K%20K%20K%20ENTERPRISES&tr=EZYS9640943427&am=1999.81&cu=INR&mc=1520', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, '', 'UPI', 'UNSETTLED', NULL, '2026-10-01 14:09:20', '2026-10-01 14:09:20', NULL, NULL, 'YDP33234KKK10011409206F26AB', 'xAGjE5CuGeIOsCjVLzNMK-T-zpbmsxeO', NULL),
(18, 2, 3, 'YDP33234KKK100114121989E038', 'PENDING', 99.65, NULL, 'upi://pay?pa=msgreenrisingagrilimited.eazypay@icici&pn=K%20K%20K%20ENTERPRISES&tr=EZYS9640943427&am=99.65&cu=INR&mc=1520', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, '', 'UPI', 'UNSETTLED', NULL, '2026-10-01 14:12:19', '2026-10-01 14:12:19', NULL, NULL, 'YDP33234KKK100114121989E038', '4Vkeh1DW2tVwFc_Y7mBrOcFcR344QPtf', NULL),
(19, 2, 2, 'YDP33234JKM1001141537F54000', 'PENDING', 99.51, NULL, 'upi://pay?pa=msgreenrisingagrilimited.eazypay@icici&pn=J%20K%20MERCHANT&tr=EZYS9640941904&am=99.51&cu=INR&mc=1520', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, '', 'UPI', 'UNSETTLED', NULL, '2026-10-01 14:15:37', '2026-10-01 14:15:37', NULL, NULL, 'YDP33234JKM1001141537F54000', 'lTVUOpyBZyfCaWN1E6FKw-E2agH8uzTi', NULL),
(20, 2, 2, 'YDP33234JKM1001141848961BAC', 'PENDING', 99.59, NULL, 'upi://pay?pa=msgreenrisingagrilimited.eazypay@icici&pn=J%20K%20MERCHANT&tr=EZYS9640941904&am=99.59&cu=INR&mc=1520', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, '', 'UPI', 'UNSETTLED', NULL, '2026-10-01 14:18:48', '2026-10-01 14:18:48', NULL, NULL, 'YDP33234JKM1001141848961BAC', 'G5DPRWqeW-bSn7_G4dvsLFHsbGNQCBRi', NULL),
(21, 2, 2, 'YDP33234JKM10011420071AD589', 'PENDING', 99.86, NULL, 'upi://pay?pa=msgreenrisingagrilimited.eazypay@icici&pn=J%20K%20MERCHANT&tr=EZYS9640941904&am=99.86&cu=INR&mc=1520', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, '', 'UPI', 'UNSETTLED', NULL, '2026-10-01 14:20:07', '2026-10-01 14:20:07', NULL, NULL, 'YDP33234JKM10011420071AD589', 'SjhA_lTOXEnL732B5HLxKqIKuMpv6Zg4', NULL),
(22, 2, 2, 'YDP33234JKM100114231441A394', 'PENDING', 49999.88, NULL, 'upi://pay?pa=msgreenrisingagrilimited.eazypay@icici&pn=J%20K%20MERCHANT&tr=EZYS9640941904&am=49999.88&cu=INR&mc=1520', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, '', 'UPI', 'UNSETTLED', NULL, '2026-10-01 14:23:14', '2026-10-01 14:23:14', NULL, NULL, 'YDP33234JKM100114231441A394', 'W8UcenGaVAxDt4eOqCXe2-UOVt7zhw1o', NULL),
(23, 2, 2, 'YDP33234JKM1001142319E39309', 'PENDING', 49999.54, NULL, 'upi://pay?pa=msgreenrisingagrilimited.eazypay@icici&pn=J%20K%20MERCHANT&tr=EZYS9640941904&am=49999.54&cu=INR&mc=1520', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, '', 'UPI', 'UNSETTLED', NULL, '2026-10-01 14:23:19', '2026-10-01 14:23:19', NULL, NULL, 'YDP33234JKM1001142319E39309', 'zJa9HhkTJzHZrUMIcwZZjwcmqnzeJq4g', NULL),
(24, 2, 2, 'YDP33234JKM1001143520BB0AFC', 'PENDING', 49999.53, NULL, 'upi://pay?pa=msgreenrisingagrilimited.eazypay@icici&pn=J%20K%20MERCHANT&tr=EZYS9640941904&am=49999.53&cu=INR&mc=1520', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, '', 'UPI', 'UNSETTLED', NULL, '2026-10-01 14:35:20', '2026-10-01 14:35:20', NULL, NULL, 'YDP33234JKM1001143520BB0AFC', 'eSPzGt06SsQrBGtqYMVojxDyxe1C1QWT', NULL),
(27, 2, 2, 'YDP33234JKM1001143756EE3345', 'PENDING', 49999.86, NULL, 'upi://pay?pa=msgreenrisingagrilimited.eazypay@icici&pn=J%20K%20MERCHANT&tr=EZYS9640941904&am=49999.86&cu=INR&mc=1520', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, '', 'UPI', 'UNSETTLED', NULL, '2026-10-01 14:37:56', '2026-10-01 14:37:56', NULL, NULL, 'YDP33234JKM1001143756EE3345', 'G9iqPjxYsXd_-fh8dla92qtW1fdep6Fd', NULL),
(28, 2, 2, 'YDP33234JKM1001143814E944B5', 'PENDING', 299.97, NULL, 'upi://pay?pa=msgreenrisingagrilimited.eazypay@icici&pn=J%20K%20MERCHANT&tr=EZYS9640941904&am=299.97&cu=INR&mc=1520', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, '', 'UPI', 'UNSETTLED', NULL, '2026-10-01 14:38:14', '2026-10-01 14:38:14', NULL, NULL, 'YDP33234JKM1001143814E944B5', 'F_1zlW22XeqDgCR05IxmSpfhNAtMT4s-', NULL),
(29, 2, 2, 'YDP33234JKM1001143820523558', 'PENDING', 10000.00, NULL, 'upi://pay?pa=msgreenrisingagrilimited.eazypay@icici&pn=J%20K%20MERCHANT&tr=EZYS9640941904&am=10000.00&cu=INR&mc=1520', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, '', 'UPI', 'UNSETTLED', NULL, '2026-10-01 14:38:20', '2026-10-01 14:38:20', NULL, NULL, 'YDP33234JKM1001143820523558', 'wITMmSt3EJKBfFbZ0E8aJp1Hcy9MGZkc', NULL),
(30, 2, 2, 'YDP33234JKM10011439321EEFA9', 'PENDING', 19999.92, NULL, 'upi://pay?pa=msgreenrisingagrilimited.eazypay@icici&pn=J%20K%20MERCHANT&tr=EZYS9640941904&am=19999.92&cu=INR&mc=1520', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, '', 'UPI', 'UNSETTLED', NULL, '2026-10-01 14:39:32', '2026-10-01 14:39:32', NULL, NULL, 'YDP33234JKM10011439321EEFA9', 'cZpv0gNZNzl7rfHrTVx98b2YbuLj-Apo', NULL),
(31, 2, 2, 'YDP33234JKM100114422755FFFC', 'PENDING', 19999.80, NULL, 'upi://pay?pa=msgreenrisingagrilimited.eazypay@icici&pn=J%20K%20MERCHANT&tr=EZYS9640941904&am=19999.80&cu=INR&mc=1520', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, '', 'UPI', 'UNSETTLED', NULL, '2026-10-01 14:42:27', '2026-10-01 14:42:27', NULL, NULL, 'YDP33234JKM100114422755FFFC', 'aG86lRNzED3K65N1RjlctNYGV2zcbEZ9', NULL),
(32, 2, 2, 'YDP33234JKM10011445178A722B', 'PENDING', 19999.72, NULL, 'upi://pay?pa=msgreenrisingagrilimited.eazypay@icici&pn=J%20K%20MERCHANT&tr=EZYS9640941904&am=19999.72&cu=INR&mc=1520', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, '', 'UPI', 'UNSETTLED', NULL, '2026-10-01 14:45:17', '2026-10-01 14:45:17', NULL, NULL, 'YDP33234JKM10011445178A722B', '4VBIq1vZ44sE2djuC-Zx2zR8HT7CxO_8', NULL),
(33, 2, 2, 'YDP33234JKM1001144828F778C6', 'PENDING', 19999.68, NULL, 'upi://pay?pa=msgreenrisingagrilimited.eazypay@icici&pn=J%20K%20MERCHANT&tr=EZYS9640941904&am=19999.68&cu=INR&mc=1520', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, '', 'UPI', 'UNSETTLED', NULL, '2026-10-01 14:48:28', '2026-10-01 14:48:28', NULL, NULL, 'YDP33234JKM1001144828F778C6', '32TuEAb01VkJQb75PJDS72ulgzM9snFG', NULL),
(34, 2, 2, 'YDP33234JKM100114492453B07D', 'PENDING', 19999.81, NULL, 'upi://pay?pa=msgreenrisingagrilimited.eazypay@icici&pn=J%20K%20MERCHANT&tr=EZYS9640941904&am=19999.81&cu=INR&mc=1520', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, '', 'UPI', 'UNSETTLED', NULL, '2026-10-01 14:49:24', '2026-10-01 14:49:24', NULL, NULL, 'YDP33234JKM100114492453B07D', 'OUXCmiUaeGJiSRssEHx-gp_ZXnKbEPAb', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `wallet_balances`
--

CREATE TABLE `wallet_balances` (
  `customer_id` bigint(20) UNSIGNED NOT NULL,
  `available_balance` decimal(14,2) NOT NULL DEFAULT 0.00,
  `pending_balance` decimal(14,2) NOT NULL DEFAULT 0.00,
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `wallet_recharges`
--

CREATE TABLE `wallet_recharges` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `customer_id` bigint(20) UNSIGNED NOT NULL,
  `transaction_id` bigint(20) UNSIGNED DEFAULT NULL,
  `reference` varchar(64) NOT NULL,
  `amount` decimal(14,2) NOT NULL,
  `status` varchar(32) NOT NULL DEFAULT 'PENDING',
  `bank_id` bigint(20) UNSIGNED DEFAULT NULL,
  `utr` varchar(160) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `credited_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `withdrawal_requests`
--

CREATE TABLE `withdrawal_requests` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `customer_id` bigint(20) UNSIGNED NOT NULL,
  `reference` varchar(64) NOT NULL,
  `amount` decimal(14,2) NOT NULL,
  `status` varchar(32) NOT NULL DEFAULT 'REQUESTED',
  `account_holder` varchar(180) NOT NULL DEFAULT '',
  `bank_name` varchar(120) NOT NULL,
  `account_number` varchar(32) NOT NULL,
  `ifsc` varchar(16) NOT NULL,
  `admin_note` text DEFAULT NULL,
  `bank_ref` varchar(160) DEFAULT NULL,
  `processed_by` varchar(80) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `processed_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `admin_users`
--
ALTER TABLE `admin_users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `uq_admin_user_email` (`email`);

--
-- Indexes for table `audit_logs`
--
ALTER TABLE `audit_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_audit_action` (`action`),
  ADD KEY `idx_audit_created` (`created_at`);

--
-- Indexes for table `auth_rate_limits`
--
ALTER TABLE `auth_rate_limits`
  ADD PRIMARY KEY (`rate_key`);

--
-- Indexes for table `bank_profiles`
--
ALTER TABLE `bank_profiles`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `code` (`code`),
  ADD KEY `idx_bank_enabled` (`enabled`),
  ADD KEY `idx_bank_vpa` (`vpa`),
  ADD KEY `idx_bank_rotation` (`rotation_order`);

--
-- Indexes for table `bank_receiving_accounts`
--
ALTER TABLE `bank_receiving_accounts`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_receiving_account_key` (`account_key`),
  ADD UNIQUE KEY `uq_bank_account_mask` (`bank_name`,`account_last4`),
  ADD KEY `ix_receiving_bank_active` (`bank_name`,`active`,`sort_order`);

--
-- Indexes for table `bank_sms_devices`
--
ALTER TABLE `bank_sms_devices`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_bank_sms_device` (`device_id`);

--
-- Indexes for table `bank_sms_device_accounts`
--
ALTER TABLE `bank_sms_device_accounts`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_device_bank_last4` (`device_row_id`,`bank_code`,`account_last4`),
  ADD KEY `idx_device_accounts` (`device_row_id`);

--
-- Indexes for table `bank_sms_events`
--
ALTER TABLE `bank_sms_events`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_bank_sms_event_id` (`event_id`),
  ADD UNIQUE KEY `uq_bank_sms_reference` (`bank_name`,`reference_no`),
  ADD KEY `ix_bank_sms_match` (`bank_name`,`receiving_account_id`,`amount`,`match_state`),
  ADD KEY `ix_bank_sms_received` (`received_at`),
  ADD KEY `fk_sms_receiving_account` (`receiving_account_id`),
  ADD KEY `fk_sms_payment_order` (`matched_payment_id`);

--
-- Indexes for table `bank_sms_match_attempts`
--
ALTER TABLE `bank_sms_match_attempts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_sms_attempt_event` (`event_id`),
  ADD KEY `idx_sms_attempt_transaction` (`transaction_id`);

--
-- Indexes for table `bank_sms_nonces`
--
ALTER TABLE `bank_sms_nonces`
  ADD PRIMARY KEY (`nonce`);

--
-- Indexes for table `billing_settings`
--
ALTER TABLE `billing_settings`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `customers`
--
ALTER TABLE `customers`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `code` (`code`),
  ADD UNIQUE KEY `account_number` (`account_number`),
  ADD UNIQUE KEY `uq_customer_account` (`account_number`),
  ADD KEY `idx_customer_phone` (`phone`),
  ADD KEY `idx_customer_active` (`active`);

--
-- Indexes for table `email_outbox`
--
ALTER TABLE `email_outbox`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `event_key` (`event_key`),
  ADD KEY `idx_email_status` (`status`,`created_at`);

--
-- Indexes for table `fee_entries`
--
ALTER TABLE `fee_entries`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_fee_entry_date` (`entry_date`),
  ADD KEY `idx_fee_entry_customer` (`customer_id`);

--
-- Indexes for table `gst_monthly_summaries`
--
ALTER TABLE `gst_monthly_summaries`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_gst_month` (`month_key`);

--
-- Indexes for table `incoming_sms`
--
ALTER TABLE `incoming_sms`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_message_fingerprint` (`message_fingerprint`),
  ADD KEY `idx_device_received` (`device_id`,`received_at`),
  ADD KEY `idx_status_expiry` (`status`,`expires_at`);

--
-- Indexes for table `kyb_documents`
--
ALTER TABLE `kyb_documents`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_kyb_cust` (`customer_id`),
  ADD KEY `idx_kyb_status` (`status`);

--
-- Indexes for table `merchant_clients`
--
ALTER TABLE `merchant_clients`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_merchant_client_id` (`client_id`);

--
-- Indexes for table `otp_codes`
--
ALTER TABLE `otp_codes`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_otp_customer` (`customer_id`,`created_at`),
  ADD KEY `idx_otp_exp` (`expires_at`);

--
-- Indexes for table `otp_requests`
--
ALTER TABLE `otp_requests`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `reference_id` (`reference_id`),
  ADD KEY `idx_request_status_expiry` (`status`,`expires_at`),
  ADD KEY `fk_request_sms` (`matched_sms_id`);

--
-- Indexes for table `payout_connectors`
--
ALTER TABLE `payout_connectors`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_payout_connector` (`customer_id`,`bank_id`,`mode`),
  ADD KEY `idx_payout_connector_status` (`status`);

--
-- Indexes for table `portal_bank_sms_events`
--
ALTER TABLE `portal_bank_sms_events`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_bank_sms_utr` (`utr`),
  ADD UNIQUE KEY `uq_bank_sms_android_event` (`device_id`,`android_event_id`),
  ADD KEY `idx_bank_sms_status` (`match_status`,`id`),
  ADD KEY `idx_bank_sms_lookup` (`bank_code`,`amount`,`sms_time`);

--
-- Indexes for table `pricing_overrides`
--
ALTER TABLE `pricing_overrides`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_price_override` (`customer_id`,`charge_key`),
  ADD KEY `idx_price_override_cust` (`customer_id`);

--
-- Indexes for table `pricing_plans`
--
ALTER TABLE `pricing_plans`
  ADD PRIMARY KEY (`plan_key`);

--
-- Indexes for table `qr_payment_orders`
--
ALTER TABLE `qr_payment_orders`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_qr_payment_id` (`payment_id`),
  ADD UNIQUE KEY `uq_qr_bank_submitted_utr` (`bank_name`,`submitted_utr`),
  ADD UNIQUE KEY `uq_merchant_order` (`merchant_client_id`,`merchant_order_id`),
  ADD KEY `ix_qr_payment_match` (`status`,`bank_name`,`receiving_account_id`,`amount`,`created_at`,`expires_at`),
  ADD KEY `ix_qr_collision` (`receiving_account_id`,`amount`,`status`,`expires_at`);

--
-- Indexes for table `settlements`
--
ALTER TABLE `settlements`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `reference` (`reference`),
  ADD KEY `idx_stl_status` (`status`),
  ADD KEY `idx_stl_created` (`created_at`);

--
-- Indexes for table `sms_devices`
--
ALTER TABLE `sms_devices`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `device_id` (`device_id`);

--
-- Indexes for table `sms_gateway_settings`
--
ALTER TABLE `sms_gateway_settings`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `sms_outbox`
--
ALTER TABLE `sms_outbox`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `event_key` (`event_key`),
  ADD KEY `idx_sms_status_next` (`status`,`next_attempt_at`);

--
-- Indexes for table `sms_relay_devices`
--
ALTER TABLE `sms_relay_devices`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_sms_relay_device_id` (`device_id`);

--
-- Indexes for table `source_bank_verifications`
--
ALTER TABLE `source_bank_verifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_src_bank_cust` (`customer_id`),
  ADD KEY `idx_src_bank_status` (`status`);

--
-- Indexes for table `support_tickets`
--
ALTER TABLE `support_tickets`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `reference` (`reference`),
  ADD KEY `idx_ticket_customer` (`customer_id`),
  ADD KEY `idx_ticket_status` (`status`),
  ADD KEY `idx_ticket_created` (`created_at`);

--
-- Indexes for table `support_ticket_replies`
--
ALTER TABLE `support_ticket_replies`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_reply_ticket` (`ticket_id`);

--
-- Indexes for table `system_config`
--
ALTER TABLE `system_config`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_system_active_bank` (`manual_active_bank_id`);

--
-- Indexes for table `transactions`
--
ALTER TABLE `transactions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `reference` (`reference`),
  ADD UNIQUE KEY `customer_reported_utr` (`customer_reported_utr`),
  ADD UNIQUE KEY `uq_txn_bank_rrn` (`bank_id`,`rrn`),
  ADD UNIQUE KEY `uq_txn_bank_upi` (`bank_id`,`upi_txn_id`),
  ADD KEY `idx_txn_customer_status` (`customer_id`,`status`),
  ADD KEY `idx_txn_bank_status` (`bank_id`,`status`),
  ADD KEY `idx_txn_created` (`created_at`),
  ADD KEY `idx_txn_rrn` (`rrn`),
  ADD KEY `idx_txn_upi` (`upi_txn_id`),
  ADD KEY `idx_txn_limit` (`limit_breach`),
  ADD KEY `idx_txn_settlement` (`settlement_id`);

--
-- Indexes for table `wallet_balances`
--
ALTER TABLE `wallet_balances`
  ADD PRIMARY KEY (`customer_id`);

--
-- Indexes for table `wallet_recharges`
--
ALTER TABLE `wallet_recharges`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `reference` (`reference`),
  ADD KEY `idx_wr_customer_status` (`customer_id`,`status`),
  ADD KEY `idx_wr_txn` (`transaction_id`),
  ADD KEY `idx_wr_created` (`created_at`);

--
-- Indexes for table `withdrawal_requests`
--
ALTER TABLE `withdrawal_requests`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `reference` (`reference`),
  ADD KEY `idx_wd_customer_status` (`customer_id`,`status`),
  ADD KEY `idx_wd_created` (`created_at`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `admin_users`
--
ALTER TABLE `admin_users`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `audit_logs`
--
ALTER TABLE `audit_logs`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `bank_profiles`
--
ALTER TABLE `bank_profiles`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `bank_receiving_accounts`
--
ALTER TABLE `bank_receiving_accounts`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `bank_sms_devices`
--
ALTER TABLE `bank_sms_devices`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `bank_sms_device_accounts`
--
ALTER TABLE `bank_sms_device_accounts`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `bank_sms_events`
--
ALTER TABLE `bank_sms_events`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `bank_sms_match_attempts`
--
ALTER TABLE `bank_sms_match_attempts`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `customers`
--
ALTER TABLE `customers`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `email_outbox`
--
ALTER TABLE `email_outbox`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `fee_entries`
--
ALTER TABLE `fee_entries`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `gst_monthly_summaries`
--
ALTER TABLE `gst_monthly_summaries`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `incoming_sms`
--
ALTER TABLE `incoming_sms`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=163;

--
-- AUTO_INCREMENT for table `kyb_documents`
--
ALTER TABLE `kyb_documents`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `merchant_clients`
--
ALTER TABLE `merchant_clients`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `otp_codes`
--
ALTER TABLE `otp_codes`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `otp_requests`
--
ALTER TABLE `otp_requests`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `payout_connectors`
--
ALTER TABLE `payout_connectors`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `portal_bank_sms_events`
--
ALTER TABLE `portal_bank_sms_events`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `pricing_overrides`
--
ALTER TABLE `pricing_overrides`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `qr_payment_orders`
--
ALTER TABLE `qr_payment_orders`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=26;

--
-- AUTO_INCREMENT for table `settlements`
--
ALTER TABLE `settlements`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `sms_devices`
--
ALTER TABLE `sms_devices`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `sms_outbox`
--
ALTER TABLE `sms_outbox`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `sms_relay_devices`
--
ALTER TABLE `sms_relay_devices`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `source_bank_verifications`
--
ALTER TABLE `source_bank_verifications`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `support_tickets`
--
ALTER TABLE `support_tickets`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `support_ticket_replies`
--
ALTER TABLE `support_ticket_replies`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `transactions`
--
ALTER TABLE `transactions`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=35;

--
-- AUTO_INCREMENT for table `wallet_recharges`
--
ALTER TABLE `wallet_recharges`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `withdrawal_requests`
--
ALTER TABLE `withdrawal_requests`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `bank_sms_events`
--
ALTER TABLE `bank_sms_events`
  ADD CONSTRAINT `fk_sms_payment_order` FOREIGN KEY (`matched_payment_id`) REFERENCES `qr_payment_orders` (`id`),
  ADD CONSTRAINT `fk_sms_receiving_account` FOREIGN KEY (`receiving_account_id`) REFERENCES `bank_receiving_accounts` (`id`);

--
-- Constraints for table `otp_codes`
--
ALTER TABLE `otp_codes`
  ADD CONSTRAINT `fk_otp_customer` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `qr_payment_orders`
--
ALTER TABLE `qr_payment_orders`
  ADD CONSTRAINT `fk_qr_merchant_client` FOREIGN KEY (`merchant_client_id`) REFERENCES `merchant_clients` (`id`),
  ADD CONSTRAINT `fk_qr_receiving_account` FOREIGN KEY (`receiving_account_id`) REFERENCES `bank_receiving_accounts` (`id`);

--
-- Constraints for table `support_tickets`
--
ALTER TABLE `support_tickets`
  ADD CONSTRAINT `fk_ticket_customer` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`);

--
-- Constraints for table `support_ticket_replies`
--
ALTER TABLE `support_ticket_replies`
  ADD CONSTRAINT `fk_reply_ticket` FOREIGN KEY (`ticket_id`) REFERENCES `support_tickets` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `system_config`
--
ALTER TABLE `system_config`
  ADD CONSTRAINT `fk_system_active_bank` FOREIGN KEY (`manual_active_bank_id`) REFERENCES `bank_profiles` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `transactions`
--
ALTER TABLE `transactions`
  ADD CONSTRAINT `fk_txn_bank` FOREIGN KEY (`bank_id`) REFERENCES `bank_profiles` (`id`),
  ADD CONSTRAINT `fk_txn_customer` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`);

--
-- Constraints for table `wallet_balances`
--
ALTER TABLE `wallet_balances`
  ADD CONSTRAINT `fk_wallet_bal_customer` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`);

--
-- Constraints for table `wallet_recharges`
--
ALTER TABLE `wallet_recharges`
  ADD CONSTRAINT `fk_wr_customer` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`),
  ADD CONSTRAINT `fk_wr_txn` FOREIGN KEY (`transaction_id`) REFERENCES `transactions` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `withdrawal_requests`
--
ALTER TABLE `withdrawal_requests`
  ADD CONSTRAINT `fk_wd_customer` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
