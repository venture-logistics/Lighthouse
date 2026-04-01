-- phpMyAdmin SQL Dump
-- version 5.2.2
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1:3306
-- Generation Time: Apr 01, 2026 at 07:28 AM
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
-- Database: `lighthouse`
--

-- --------------------------------------------------------

--
-- Table structure for table `bank_accounts`
--

CREATE TABLE `bank_accounts` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `account_name` varchar(100) NOT NULL,
  `bank_name` varchar(100) DEFAULT NULL,
  `account_number` varchar(20) DEFAULT NULL,
  `sort_code` varchar(10) DEFAULT NULL,
  `coa_id` int(11) NOT NULL,
  `currency` char(3) DEFAULT 'GBP',
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `bank_transactions`
--

CREATE TABLE `bank_transactions` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `bank_account_id` int(11) NOT NULL,
  `revolut_tx_id` varchar(100) DEFAULT NULL,
  `transaction_date` date NOT NULL,
  `started_date` date DEFAULT NULL,
  `description` varchar(255) NOT NULL,
  `expense_description` varchar(255) DEFAULT NULL,
  `type` enum('debit','credit') NOT NULL,
  `transaction_type` varchar(50) DEFAULT NULL,
  `orig_currency` varchar(10) DEFAULT NULL,
  `orig_amount` decimal(10,2) DEFAULT NULL,
  `amount` decimal(10,2) NOT NULL,
  `fee` decimal(10,2) DEFAULT 0.00,
  `tax_name` varchar(100) DEFAULT NULL,
  `tax_rate` decimal(5,2) DEFAULT 0.00,
  `tax_amount` decimal(10,2) DEFAULT 0.00,
  `coa_id` int(11) DEFAULT NULL,
  `expense_category` varchar(100) DEFAULT NULL,
  `expense_category_code` varchar(10) DEFAULT NULL,
  `status` enum('uncategorised','categorised','reconciled') DEFAULT 'uncategorised',
  `notes` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `receipt_path` varchar(500) DEFAULT NULL,
  `reconciled` tinyint(1) DEFAULT 0,
  `no_receipt` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `business_settings`
--

CREATE TABLE `business_settings` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `company_name` varchar(255) DEFAULT NULL,
  `company_number` varchar(50) DEFAULT NULL,
  `vat_number` varchar(50) DEFAULT NULL,
  `address_line1` varchar(255) DEFAULT NULL,
  `address_line2` varchar(255) DEFAULT NULL,
  `city` varchar(100) DEFAULT NULL,
  `county` varchar(100) DEFAULT NULL,
  `postcode` varchar(20) DEFAULT NULL,
  `country` varchar(100) DEFAULT NULL,
  `phone` varchar(50) DEFAULT NULL,
  `website` varchar(255) DEFAULT NULL,
  `logo_path` varchar(255) DEFAULT NULL,
  `logo_height` int(11) DEFAULT 100,
  `primary_color` varchar(7) DEFAULT NULL,
  `smtp_host` varchar(255) DEFAULT NULL,
  `smtp_port` int(11) DEFAULT NULL,
  `smtp_username` varchar(255) DEFAULT NULL,
  `smtp_password` varchar(255) DEFAULT NULL,
  `from_email` varchar(255) DEFAULT NULL,
  `from_name` varchar(255) DEFAULT NULL,
  `email_template` text DEFAULT NULL,
  `bank_name` varchar(255) DEFAULT NULL,
  `account_name` varchar(255) DEFAULT NULL,
  `sort_code` varchar(10) DEFAULT NULL,
  `account_number` varchar(20) DEFAULT NULL,
  `invoice_prefix` varchar(10) DEFAULT NULL,
  `invoice_next_number` int(11) DEFAULT 1,
  `default_payment_terms` int(11) DEFAULT 30,
  `default_tax_rate` decimal(5,2) DEFAULT 20.00,
  `invoice_notes` text DEFAULT NULL,
  `payment_instructions` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `accounting_period_start` date DEFAULT NULL,
  `accounting_period_end` date DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `chart_of_accounts`
--

CREATE TABLE `chart_of_accounts` (
  `id` int(11) NOT NULL,
  `code` varchar(10) NOT NULL,
  `name` varchar(100) NOT NULL,
  `type` enum('asset','liability','equity','income','expense') NOT NULL,
  `description` text DEFAULT NULL,
  `is_system` tinyint(1) DEFAULT 0,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `vat_rate` decimal(5,2) NOT NULL DEFAULT 0.00,
  `hmrc_category` varchar(50) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `chart_of_accounts`
--

INSERT INTO `chart_of_accounts` (`id`, `code`, `name`, `type`, `description`, `is_system`, `is_active`, `created_at`, `updated_at`, `vat_rate`, `hmrc_category`) VALUES
(1, '1000', 'Current Account', 'asset', NULL, 1, 1, '2026-03-02 08:35:37', '2026-03-02 08:35:37', 0.00, NULL),
(2, '1010', 'Savings Account', 'asset', NULL, 0, 1, '2026-03-02 08:35:37', '2026-03-02 08:35:37', 0.00, NULL),
(3, '1020', 'Petty Cash', 'asset', NULL, 0, 1, '2026-03-02 08:35:37', '2026-03-02 08:35:37', 0.00, NULL),
(4, '1100', 'Accounts Receivable', 'asset', NULL, 1, 1, '2026-03-02 08:35:37', '2026-03-02 08:35:37', 0.00, NULL),
(5, '1200', 'VAT Receivable', 'asset', NULL, 1, 1, '2026-03-02 08:35:37', '2026-03-02 08:35:37', 0.00, NULL),
(6, '2000', 'Accounts Payable', 'liability', NULL, 1, 1, '2026-03-02 08:35:37', '2026-03-02 08:35:37', 0.00, NULL),
(7, '2100', 'VAT Payable', 'liability', NULL, 1, 1, '2026-03-02 08:35:37', '2026-03-02 08:35:37', 0.00, NULL),
(8, '2200', 'PAYE Payable', 'liability', NULL, 0, 1, '2026-03-02 08:35:37', '2026-03-02 08:35:37', 0.00, NULL),
(9, '2300', 'Corporation Tax Payable', 'liability', NULL, 0, 1, '2026-03-02 08:35:37', '2026-03-02 08:35:37', 0.00, NULL),
(10, '2400', 'Directors Loan Account', 'liability', NULL, 0, 1, '2026-03-02 08:35:37', '2026-03-02 08:35:37', 0.00, NULL),
(11, '3000', 'Share Capital', 'equity', NULL, 1, 1, '2026-03-02 08:35:37', '2026-03-02 08:35:37', 0.00, NULL),
(12, '3100', 'Retained Earnings', 'equity', NULL, 1, 1, '2026-03-02 08:35:37', '2026-03-02 08:35:37', 0.00, NULL),
(13, '4000', 'Sales - General', 'income', NULL, 1, 1, '2026-03-02 08:35:37', '2026-03-02 08:35:37', 0.00, NULL),
(14, '4010', 'Sales - Services', 'income', NULL, 0, 1, '2026-03-02 08:35:37', '2026-03-02 08:35:37', 0.00, NULL),
(15, '4020', 'Sales - Products', 'income', NULL, 0, 1, '2026-03-02 08:35:37', '2026-03-02 08:35:37', 0.00, NULL),
(16, '4900', 'Other Income', 'income', NULL, 0, 1, '2026-03-02 08:35:37', '2026-03-02 08:35:37', 0.00, NULL),
(17, '5000', 'Cost of Sales', 'expense', 'Cost of Sales', 0, 1, '2026-03-02 08:35:37', '2026-03-08 03:12:37', 0.00, NULL),
(18, '6000', 'Salaries & Wages', 'expense', NULL, 0, 1, '2026-03-02 08:35:37', '2026-03-02 08:35:37', 0.00, NULL),
(19, '6010', 'Employer NI', 'expense', NULL, 0, 1, '2026-03-02 08:35:37', '2026-03-02 08:35:37', 0.00, NULL),
(20, '6100', 'Rent & Rates', 'expense', NULL, 0, 1, '2026-03-02 08:35:37', '2026-03-02 08:35:37', 0.00, NULL),
(21, '6200', 'Utilities', 'expense', NULL, 0, 1, '2026-03-02 08:35:37', '2026-03-02 08:35:37', 0.00, NULL),
(22, '6300', 'Telephone & Internet', 'expense', '0330 number, mini phone sim card', 0, 1, '2026-03-02 08:35:37', '2026-03-27 09:30:08', 20.00, NULL),
(23, '6400', 'Software & Subscriptions', 'expense', 'Software subscriptions, i.e. GitHub, Paddle etc', 0, 1, '2026-03-02 08:35:37', '2026-03-27 09:32:09', 20.00, NULL),
(24, '6500', 'Office Supplies', 'expense', NULL, 0, 1, '2026-03-02 08:35:37', '2026-03-02 08:35:37', 0.00, NULL),
(25, '6600', 'Travel & Subsistence', 'expense', NULL, 0, 1, '2026-03-02 08:35:37', '2026-03-02 08:35:37', 0.00, NULL),
(26, '6700', 'Motor Expenses', 'expense', 'Van purchases like rubber matting, but also MOT and mech', 0, 1, '2026-03-02 08:35:37', '2026-03-27 09:35:52', 20.00, NULL),
(27, '6800', 'Marketing & Advertising', 'expense', 'Business cards, advertising, anything promotional', 0, 1, '2026-03-02 08:35:37', '2026-03-27 09:39:24', 20.00, NULL),
(28, '6900', 'Professional Fees', 'expense', NULL, 0, 1, '2026-03-02 08:35:37', '2026-03-02 08:35:37', 0.00, NULL),
(29, '7000', 'Bank Charges', 'expense', NULL, 0, 1, '2026-03-02 08:35:37', '2026-03-02 08:35:37', 0.00, NULL),
(30, '7100', 'Insurance', 'expense', 'Van, Goods in Transit and Public Liability ins.', 0, 1, '2026-03-02 08:35:37', '2026-03-27 09:28:46', 12.00, NULL),
(31, '7200', 'Depreciation', 'expense', NULL, 0, 1, '2026-03-02 08:35:37', '2026-03-02 08:35:37', 0.00, NULL),
(32, '7900', 'Sundry Expenses', 'expense', NULL, 0, 1, '2026-03-02 08:35:37', '2026-03-02 08:35:37', 0.00, NULL),
(33, '6001', 'Waste Transfer', 'expense', 'Charge to tip customer waste', 0, 1, '2026-03-02 18:04:58', '2026-03-27 07:38:13', 20.00, NULL),
(34, '6002', 'General Expenses', 'expense', 'General expense not covered elsewhere and 20% vat', 0, 1, '2026-03-02 18:30:26', '2026-03-27 09:40:56', 20.00, NULL),
(35, '6004', 'Food and Drink', 'expense', 'Breakfast, Lunch and Dinner when on the road', 0, 1, '2026-03-02 18:39:36', '2026-03-27 08:03:37', 20.00, NULL),
(36, '6005', 'Diesel', 'expense', 'Diesel', 0, 1, '2026-03-02 18:55:39', '2026-03-27 08:06:45', 20.00, NULL),
(37, '6006', 'Hosting and Domains', 'expense', 'Website hosting and domains', 0, 1, '2026-03-02 19:34:17', '2026-03-27 09:26:25', 20.00, NULL),
(38, '6007', 'Penalties and Fines', 'expense', 'Driving in London Tax!', 0, 1, '2026-03-02 19:37:00', '2026-03-05 22:20:26', 0.00, NULL),
(39, '6008', 'Tolls & Congestion', 'expense', 'Tolls, freash air zones and congestion charges', 0, 1, '2026-03-02 19:40:07', '2026-03-05 22:20:37', 0.00, NULL),
(40, '6009', 'Short Term Loan', 'expense', 'Short term loan from friend or HLS', 0, 1, '2026-03-03 06:42:03', '2026-03-05 22:20:47', 0.00, NULL),
(41, '6011', 'Bookkeeping', 'expense', 'Pay daughter to help with bookkeeping', 0, 1, '2026-03-03 07:24:25', '2026-03-05 22:22:17', 0.00, NULL),
(42, '6012', 'Gifts', 'expense', 'Customer thank you gifts', 0, 1, '2026-03-03 08:17:05', '2026-03-27 09:45:29', 20.00, NULL),
(43, '6013', 'Research and Development', 'expense', 'Developing software', 0, 1, '2026-03-03 10:53:17', '2026-03-27 09:26:05', 20.00, NULL),
(44, '1500', 'Motor Vehicles', 'asset', NULL, 0, 1, '2026-03-04 05:28:07', '2026-03-04 05:28:07', 0.00, NULL),
(45, '1510', 'Computer Equipment', 'asset', NULL, 0, 1, '2026-03-04 05:28:07', '2026-03-04 05:28:07', 0.00, NULL),
(46, '1590', 'Accumulated Depreciation - Motor Vehicles', 'asset', NULL, 0, 1, '2026-03-04 05:28:07', '2026-03-04 05:28:07', 0.00, NULL),
(47, '1591', 'Accumulated Depreciation - Computer Equipment', 'asset', NULL, 0, 1, '2026-03-04 05:28:07', '2026-03-04 05:28:07', 0.00, NULL),
(49, '6014', 'Work Clothing', 'expense', 'Includes only clothing specific to work', 0, 1, '2026-03-27 09:43:27', '2026-03-27 09:44:21', 20.00, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `customers`
--

CREATE TABLE `customers` (
  `id` int(11) NOT NULL,
  `company_name` varchar(100) DEFAULT NULL,
  `contact_name` varchar(100) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `city` varchar(50) DEFAULT NULL,
  `postcode` varchar(10) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `diary`
--

CREATE TABLE `diary` (
  `id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `entry_date` date NOT NULL,
  `entry_time` time DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `hmrc_tokens`
--

CREATE TABLE `hmrc_tokens` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `access_token` text DEFAULT NULL,
  `refresh_token` text DEFAULT NULL,
  `token_expires` datetime DEFAULT NULL,
  `vrn` varchar(20) DEFAULT NULL,
  `updated_at` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `invoices`
--

CREATE TABLE `invoices` (
  `id` int(11) NOT NULL,
  `customer_id` int(11) NOT NULL,
  `invoice_number` varchar(20) NOT NULL,
  `invoice_date` date NOT NULL,
  `due_date` date NOT NULL,
  `subtotal` decimal(10,2) NOT NULL DEFAULT 0.00,
  `tax_rate` decimal(4,2) NOT NULL DEFAULT 20.00,
  `tax_amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `total` decimal(10,2) NOT NULL DEFAULT 0.00,
  `notes` text DEFAULT NULL,
  `status` enum('draft','sent','paid','overdue','cancelled') NOT NULL DEFAULT 'draft',
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `invoice_items`
--

CREATE TABLE `invoice_items` (
  `id` int(11) NOT NULL,
  `invoice_id` int(11) NOT NULL,
  `description` text NOT NULL,
  `quantity` decimal(10,2) NOT NULL DEFAULT 1.00,
  `unit_price` decimal(10,2) NOT NULL,
  `amount` decimal(10,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `journal_entries`
--

CREATE TABLE `journal_entries` (
  `id` int(11) NOT NULL,
  `date` date NOT NULL,
  `description` varchar(255) NOT NULL,
  `reference` varchar(100) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `journal_entry_lines`
--

CREATE TABLE `journal_entry_lines` (
  `id` int(11) NOT NULL,
  `journal_entry_id` int(11) NOT NULL,
  `coa_id` int(11) NOT NULL,
  `type` enum('debit','credit') NOT NULL,
  `amount` decimal(10,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `login_logs`
--

CREATE TABLE `login_logs` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `username` varchar(50) DEFAULT NULL,
  `action` enum('login','failed_login','logout') NOT NULL,
  `ip_address` varchar(45) NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `mtd_submissions`
--

CREATE TABLE `mtd_submissions` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `tax_year` varchar(10) NOT NULL,
  `period_start` date NOT NULL,
  `period_end` date NOT NULL,
  `period_id` varchar(100) DEFAULT NULL,
  `income_total` decimal(10,2) DEFAULT NULL,
  `expenses_total` decimal(10,2) DEFAULT NULL,
  `status` enum('draft','submitted','accepted','rejected') DEFAULT NULL,
  `submitted_at` datetime DEFAULT NULL,
  `hmrc_response` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`hmrc_response`)),
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `notes`
--

CREATE TABLE `notes` (
  `id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `content` text NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `sole_trader_settings`
--

CREATE TABLE `sole_trader_settings` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `trading_name` varchar(255) DEFAULT NULL,
  `utr_number` varchar(20) DEFAULT NULL,
  `nino` varchar(10) DEFAULT NULL,
  `business_start_date` date DEFAULT NULL,
  `accounting_method` enum('cash','accruals') DEFAULT 'cash',
  `mtd_itsa_enrolled` tinyint(1) DEFAULT 0,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `trial_balances`
--

CREATE TABLE `trial_balances` (
  `id` int(11) NOT NULL,
  `label` varchar(255) NOT NULL,
  `date_from` date NOT NULL,
  `date_to` date NOT NULL,
  `generated_by` int(11) NOT NULL,
  `generated_at` timestamp NULL DEFAULT current_timestamp(),
  `total_debits` decimal(12,2) NOT NULL DEFAULT 0.00,
  `total_credits` decimal(12,2) NOT NULL DEFAULT 0.00
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `trial_balance_lines`
--

CREATE TABLE `trial_balance_lines` (
  `id` int(11) NOT NULL,
  `trial_balance_id` int(11) NOT NULL,
  `coa_id` int(11) NOT NULL,
  `coa_code` varchar(10) NOT NULL,
  `coa_name` varchar(100) NOT NULL,
  `coa_type` varchar(20) NOT NULL,
  `debit_amount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `credit_amount` decimal(12,2) NOT NULL DEFAULT 0.00
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('user','admin','manager','pro') DEFAULT 'user',
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `last_login` timestamp NULL DEFAULT NULL,
  `reset_token` varchar(100) DEFAULT NULL,
  `reset_expires` timestamp NULL DEFAULT NULL,
  `remember_token` varchar(64) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `vat_settings`
--

CREATE TABLE `vat_settings` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `is_vat_registered` tinyint(1) DEFAULT 0,
  `vat_number` varchar(50) DEFAULT NULL,
  `vat_rate` decimal(5,2) DEFAULT 20.00,
  `vat_scheme` enum('standard','flat_rate','cash') DEFAULT 'standard',
  `vat_quarter_end` enum('Jan','Feb','Mar') DEFAULT 'Mar',
  `vat_period_start` date DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `vat_submissions`
--

CREATE TABLE `vat_submissions` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `period_start` date NOT NULL,
  `period_end` date NOT NULL,
  `box1` decimal(12,2) DEFAULT NULL,
  `box2` decimal(12,2) DEFAULT NULL,
  `box3` decimal(12,2) DEFAULT NULL,
  `box4` decimal(12,2) DEFAULT NULL,
  `box5` decimal(12,2) DEFAULT NULL,
  `box6` decimal(12,2) DEFAULT NULL,
  `box7` decimal(12,2) DEFAULT NULL,
  `box8` decimal(12,2) DEFAULT NULL,
  `box9` decimal(12,2) DEFAULT NULL,
  `form_bundle_number` varchar(100) DEFAULT NULL,
  `processing_date` varchar(50) DEFAULT NULL,
  `submitted_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `bank_accounts`
--
ALTER TABLE `bank_accounts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `coa_id` (`coa_id`),
  ADD KEY `fk_bank_accounts_user` (`user_id`);

--
-- Indexes for table `bank_transactions`
--
ALTER TABLE `bank_transactions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_revolut_tx_id` (`revolut_tx_id`),
  ADD KEY `fk_bt_user` (`user_id`),
  ADD KEY `fk_bt_account` (`bank_account_id`),
  ADD KEY `fk_bt_coa` (`coa_id`);

--
-- Indexes for table `business_settings`
--
ALTER TABLE `business_settings`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `chart_of_accounts`
--
ALTER TABLE `chart_of_accounts`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `code` (`code`);

--
-- Indexes for table `customers`
--
ALTER TABLE `customers`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `diary`
--
ALTER TABLE `diary`
  ADD PRIMARY KEY (`id`),
  ADD KEY `created_by` (`created_by`);

--
-- Indexes for table `hmrc_tokens`
--
ALTER TABLE `hmrc_tokens`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_user` (`user_id`);

--
-- Indexes for table `invoices`
--
ALTER TABLE `invoices`
  ADD PRIMARY KEY (`id`),
  ADD KEY `customer_id` (`customer_id`);

--
-- Indexes for table `invoice_items`
--
ALTER TABLE `invoice_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `invoice_id` (`invoice_id`);

--
-- Indexes for table `journal_entries`
--
ALTER TABLE `journal_entries`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `journal_entry_lines`
--
ALTER TABLE `journal_entry_lines`
  ADD PRIMARY KEY (`id`),
  ADD KEY `journal_entry_id` (`journal_entry_id`);

--
-- Indexes for table `login_logs`
--
ALTER TABLE `login_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `mtd_submissions`
--
ALTER TABLE `mtd_submissions`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `notes`
--
ALTER TABLE `notes`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `sole_trader_settings`
--
ALTER TABLE `sole_trader_settings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_user` (`user_id`);

--
-- Indexes for table `trial_balances`
--
ALTER TABLE `trial_balances`
  ADD PRIMARY KEY (`id`),
  ADD KEY `generated_by` (`generated_by`);

--
-- Indexes for table `trial_balance_lines`
--
ALTER TABLE `trial_balance_lines`
  ADD PRIMARY KEY (`id`),
  ADD KEY `trial_balance_id` (`trial_balance_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indexes for table `vat_settings`
--
ALTER TABLE `vat_settings`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `vat_submissions`
--
ALTER TABLE `vat_submissions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `bank_accounts`
--
ALTER TABLE `bank_accounts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `bank_transactions`
--
ALTER TABLE `bank_transactions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `business_settings`
--
ALTER TABLE `business_settings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `chart_of_accounts`
--
ALTER TABLE `chart_of_accounts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=50;

--
-- AUTO_INCREMENT for table `customers`
--
ALTER TABLE `customers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `diary`
--
ALTER TABLE `diary`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `hmrc_tokens`
--
ALTER TABLE `hmrc_tokens`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `invoices`
--
ALTER TABLE `invoices`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `invoice_items`
--
ALTER TABLE `invoice_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `journal_entries`
--
ALTER TABLE `journal_entries`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `journal_entry_lines`
--
ALTER TABLE `journal_entry_lines`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `login_logs`
--
ALTER TABLE `login_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `mtd_submissions`
--
ALTER TABLE `mtd_submissions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `notes`
--
ALTER TABLE `notes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `sole_trader_settings`
--
ALTER TABLE `sole_trader_settings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `trial_balances`
--
ALTER TABLE `trial_balances`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `trial_balance_lines`
--
ALTER TABLE `trial_balance_lines`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `vat_settings`
--
ALTER TABLE `vat_settings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `vat_submissions`
--
ALTER TABLE `vat_submissions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `bank_accounts`
--
ALTER TABLE `bank_accounts`
  ADD CONSTRAINT `bank_accounts_ibfk_1` FOREIGN KEY (`coa_id`) REFERENCES `chart_of_accounts` (`id`),
  ADD CONSTRAINT `fk_bank_accounts_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `bank_transactions`
--
ALTER TABLE `bank_transactions`
  ADD CONSTRAINT `fk_bt_account` FOREIGN KEY (`bank_account_id`) REFERENCES `bank_accounts` (`id`),
  ADD CONSTRAINT `fk_bt_coa` FOREIGN KEY (`coa_id`) REFERENCES `chart_of_accounts` (`id`),
  ADD CONSTRAINT `fk_bt_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `business_settings`
--
ALTER TABLE `business_settings`
  ADD CONSTRAINT `business_settings_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `diary`
--
ALTER TABLE `diary`
  ADD CONSTRAINT `diary_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `invoices`
--
ALTER TABLE `invoices`
  ADD CONSTRAINT `invoices_ibfk_1` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`);

--
-- Constraints for table `invoice_items`
--
ALTER TABLE `invoice_items`
  ADD CONSTRAINT `invoice_items_ibfk_1` FOREIGN KEY (`invoice_id`) REFERENCES `invoices` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `journal_entry_lines`
--
ALTER TABLE `journal_entry_lines`
  ADD CONSTRAINT `journal_entry_lines_ibfk_1` FOREIGN KEY (`journal_entry_id`) REFERENCES `journal_entries` (`id`);

--
-- Constraints for table `login_logs`
--
ALTER TABLE `login_logs`
  ADD CONSTRAINT `login_logs_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `sole_trader_settings`
--
ALTER TABLE `sole_trader_settings`
  ADD CONSTRAINT `st_settings_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `trial_balances`
--
ALTER TABLE `trial_balances`
  ADD CONSTRAINT `tb_ibfk_1` FOREIGN KEY (`generated_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `trial_balance_lines`
--
ALTER TABLE `trial_balance_lines`
  ADD CONSTRAINT `tbl_ibfk_1` FOREIGN KEY (`trial_balance_id`) REFERENCES `trial_balances` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `vat_settings`
--
ALTER TABLE `vat_settings`
  ADD CONSTRAINT `vat_settings_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `vat_submissions`
--
ALTER TABLE `vat_submissions`
  ADD CONSTRAINT `vat_submissions_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
