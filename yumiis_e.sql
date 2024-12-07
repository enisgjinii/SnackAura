-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Dec 07, 2024 at 07:37 PM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.1.25

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `yumiis_e`
--

-- --------------------------------------------------------

--
-- Table structure for table `banners`
--

CREATE TABLE `banners` (
  `id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `image` varchar(255) NOT NULL,
  `link` varchar(255) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `categories`
--

CREATE TABLE `categories` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `image_url` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `is_active` tinyint(5) NOT NULL,
  `position` int(11) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `category_options`
--

CREATE TABLE `category_options` (
  `id` int(11) NOT NULL,
  `category_id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `label` varchar(255) NOT NULL,
  `type` enum('select','radio','checkbox','text','number') NOT NULL,
  `is_required` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `category_option_values`
--

CREATE TABLE `category_option_values` (
  `id` int(11) NOT NULL,
  `category_option_id` int(11) NOT NULL,
  `value` varchar(255) NOT NULL,
  `additional_price` decimal(10,2) DEFAULT 0.00,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `drinks`
--

CREATE TABLE `drinks` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `price` decimal(10,2) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `extras`
--

CREATE TABLE `extras` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `price` decimal(10,2) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `category` varchar(50) NOT NULL DEFAULT 'addon',
  `quantity` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `offers`
--

CREATE TABLE `offers` (
  `id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `image` varchar(255) DEFAULT NULL,
  `start_date` date DEFAULT NULL,
  `end_date` date DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `link` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `offer_products`
--

CREATE TABLE `offer_products` (
  `id` int(11) NOT NULL,
  `offer_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `operational_hours`
--

CREATE TABLE `operational_hours` (
  `id` int(11) NOT NULL,
  `type` enum('regular','holiday') NOT NULL,
  `day_of_week` enum('Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday') DEFAULT NULL,
  `date` date DEFAULT NULL,
  `title` varchar(255) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `open_time` time DEFAULT NULL,
  `close_time` time DEFAULT NULL,
  `is_closed` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `orders`
--

CREATE TABLE `orders` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `customer_name` varchar(100) NOT NULL,
  `customer_email` varchar(100) NOT NULL,
  `customer_phone` varchar(20) NOT NULL,
  `delivery_address` text NOT NULL,
  `latitude` decimal(10,8) DEFAULT NULL,
  `longitude` decimal(11,8) DEFAULT NULL,
  `total_amount` decimal(10,2) NOT NULL,
  `status_id` int(11) NOT NULL DEFAULT 1,
  `delivery_user_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `scheduled_date` date DEFAULT NULL,
  `scheduled_time` time DEFAULT NULL,
  `deleted_at` datetime DEFAULT NULL,
  `tip_id` int(11) DEFAULT NULL,
  `tip_amount` decimal(10,2) DEFAULT NULL,
  `payment_status` varchar(244) NOT NULL,
  `payment_method` varchar(50) NOT NULL DEFAULT 'cash',
  `store_id` int(2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `order_drinks`
--

CREATE TABLE `order_drinks` (
  `id` int(11) NOT NULL,
  `order_item_id` int(11) NOT NULL,
  `drink_id` int(11) NOT NULL,
  `quantity` int(11) NOT NULL,
  `unit_price` decimal(10,2) NOT NULL,
  `total_price` decimal(10,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `order_extras`
--

CREATE TABLE `order_extras` (
  `id` int(11) NOT NULL,
  `order_item_id` int(11) NOT NULL,
  `extra_id` int(11) NOT NULL,
  `quantity` int(11) NOT NULL,
  `unit_price` decimal(10,2) NOT NULL,
  `total_price` decimal(10,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `order_items`
--

CREATE TABLE `order_items` (
  `id` int(11) NOT NULL,
  `order_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `size_id` int(11) DEFAULT NULL,
  `quantity` int(11) NOT NULL,
  `price` decimal(10,2) NOT NULL,
  `extras` text DEFAULT NULL,
  `special_instructions` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `order_sauces`
--

CREATE TABLE `order_sauces` (
  `id` int(10) UNSIGNED NOT NULL,
  `order_item_id` int(10) UNSIGNED NOT NULL,
  `sauce_id` int(10) UNSIGNED NOT NULL,
  `quantity` int(11) NOT NULL DEFAULT 1,
  `unit_price` decimal(10,2) NOT NULL,
  `total_price` decimal(10,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `order_statuses`
--

CREATE TABLE `order_statuses` (
  `id` int(11) NOT NULL,
  `status` varchar(50) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `order_status_history`
--

CREATE TABLE `order_status_history` (
  `id` int(11) NOT NULL,
  `order_id` int(11) NOT NULL,
  `status_id` int(11) NOT NULL,
  `delivery_user_id` int(11) DEFAULT NULL,
  `changed_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `products`
--

CREATE TABLE `products` (
  `id` int(11) NOT NULL,
  `category_id` int(11) NOT NULL,
  `size_id` int(11) NOT NULL,
  `name` varchar(150) NOT NULL,
  `description` text DEFAULT NULL,
  `image_url` varchar(255) DEFAULT NULL,
  `is_new` tinyint(1) DEFAULT 0,
  `is_offer` tinyint(1) DEFAULT 0,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `allergies` varchar(255) NOT NULL,
  `sauces` varchar(255) NOT NULL,
  `product_code` varchar(50) DEFAULT NULL,
  `price` decimal(10,2) NOT NULL DEFAULT 0.00,
  `extras_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`extras_json`)),
  `size_price_json` text DEFAULT NULL,
  `dressing` varchar(255) DEFAULT NULL,
  `pizza_sizes_prices` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`pizza_sizes_prices`)),
  `options` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`options`))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `product_category_options`
--

CREATE TABLE `product_category_options` (
  `id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `category_option_id` int(11) NOT NULL,
  `option_value_id` int(11) DEFAULT NULL,
  `custom_value` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `product_dynamic_sizes`
--

CREATE TABLE `product_dynamic_sizes` (
  `id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `price` decimal(10,2) NOT NULL DEFAULT 0.00
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `product_extras`
--

CREATE TABLE `product_extras` (
  `id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `extra_id` int(11) NOT NULL,
  `price` decimal(10,2) NOT NULL DEFAULT 0.00
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `product_mixes`
--

CREATE TABLE `product_mixes` (
  `id` int(11) NOT NULL,
  `main_product_id` int(11) NOT NULL,
  `mixed_product_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `product_sauces`
--

CREATE TABLE `product_sauces` (
  `product_id` int(11) NOT NULL,
  `sauce_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `product_sizes`
--

CREATE TABLE `product_sizes` (
  `id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `size_id` int(11) NOT NULL,
  `base_price` decimal(10,2) NOT NULL DEFAULT 0.00,
  `price` decimal(10,2) NOT NULL DEFAULT 0.00
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `product_size_extras`
--

CREATE TABLE `product_size_extras` (
  `id` int(11) NOT NULL,
  `product_size_id` int(11) NOT NULL,
  `extra_id` int(11) NOT NULL,
  `price` decimal(10,2) NOT NULL DEFAULT 0.00
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `ratings`
--

CREATE TABLE `ratings` (
  `id` int(11) NOT NULL,
  `full_name` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `anonymous` tinyint(1) DEFAULT 0,
  `rating` int(11) NOT NULL CHECK (`rating` between 1 and 5),
  `comments` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `reservations`
--

CREATE TABLE `reservations` (
  `id` int(11) NOT NULL,
  `client_name` varchar(100) NOT NULL,
  `client_email` varchar(100) NOT NULL,
  `phone_number` varchar(20) NOT NULL,
  `reservation_source` enum('Online','Phone','In-person') NOT NULL DEFAULT 'Online',
  `reservation_date` date NOT NULL,
  `reservation_time` time NOT NULL,
  `confirmation_number` varchar(50) DEFAULT NULL,
  `number_of_people` int(11) NOT NULL,
  `status` enum('Pending','Confirmed','Cancelled','Completed','No-show') NOT NULL DEFAULT 'Pending',
  `assigned_to` int(11) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `message` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `sauces`
--

CREATE TABLE `sauces` (
  `id` int(10) UNSIGNED NOT NULL,
  `name` varchar(255) NOT NULL,
  `price` decimal(10,2) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `quantity` varchar(245) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `settings`
--

CREATE TABLE `settings` (
  `id` int(11) NOT NULL,
  `key` varchar(100) NOT NULL,
  `value` text NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `sizes`
--

CREATE TABLE `sizes` (
  `id` int(11) NOT NULL,
  `name` varchar(50) NOT NULL,
  `description` text DEFAULT NULL,
  `base_price` varchar(255) NOT NULL,
  `sauce_price_increase` decimal(10,2) NOT NULL DEFAULT 0.00,
  `extra_price_increase` decimal(10,2) NOT NULL DEFAULT 0.00
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `stores`
--

CREATE TABLE `stores` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `address` varchar(255) NOT NULL,
  `phone` varchar(20) NOT NULL,
  `email` varchar(100) NOT NULL,
  `manager_id` int(11) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tips`
--

CREATE TABLE `tips` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `percentage` decimal(5,2) DEFAULT NULL,
  `amount` decimal(10,2) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `created_by` varchar(255) DEFAULT NULL,
  `updated_by` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `email` varchar(100) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `role` enum('admin','waiter','delivery') NOT NULL DEFAULT 'waiter',
  `is_active` tinyint(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `banners`
--
ALTER TABLE `banners`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `categories`
--
ALTER TABLE `categories`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `name` (`name`);

--
-- Indexes for table `category_options`
--
ALTER TABLE `category_options`
  ADD PRIMARY KEY (`id`),
  ADD KEY `category_id` (`category_id`);

--
-- Indexes for table `category_option_values`
--
ALTER TABLE `category_option_values`
  ADD PRIMARY KEY (`id`),
  ADD KEY `category_option_id` (`category_option_id`);

--
-- Indexes for table `drinks`
--
ALTER TABLE `drinks`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `extras`
--
ALTER TABLE `extras`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `name` (`name`);

--
-- Indexes for table `offers`
--
ALTER TABLE `offers`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `offer_products`
--
ALTER TABLE `offer_products`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `offer_product_unique` (`offer_id`,`product_id`),
  ADD KEY `product_id` (`product_id`);

--
-- Indexes for table `operational_hours`
--
ALTER TABLE `operational_hours`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_regular_day` (`type`,`day_of_week`),
  ADD UNIQUE KEY `unique_holiday_date` (`type`,`date`);

--
-- Indexes for table `orders`
--
ALTER TABLE `orders`
  ADD PRIMARY KEY (`id`),
  ADD KEY `status_id` (`status_id`),
  ADD KEY `orders_ibfk_2` (`delivery_user_id`),
  ADD KEY `tip_id` (`tip_id`);

--
-- Indexes for table `order_drinks`
--
ALTER TABLE `order_drinks`
  ADD PRIMARY KEY (`id`),
  ADD KEY `order_item_id` (`order_item_id`),
  ADD KEY `drink_id` (`drink_id`);

--
-- Indexes for table `order_extras`
--
ALTER TABLE `order_extras`
  ADD PRIMARY KEY (`id`),
  ADD KEY `order_item_id` (`order_item_id`),
  ADD KEY `extra_id` (`extra_id`);

--
-- Indexes for table `order_items`
--
ALTER TABLE `order_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `order_id` (`order_id`),
  ADD KEY `product_id` (`product_id`),
  ADD KEY `size_id` (`size_id`);

--
-- Indexes for table `order_sauces`
--
ALTER TABLE `order_sauces`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `order_statuses`
--
ALTER TABLE `order_statuses`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `status` (`status`);

--
-- Indexes for table `order_status_history`
--
ALTER TABLE `order_status_history`
  ADD PRIMARY KEY (`id`),
  ADD KEY `order_id` (`order_id`),
  ADD KEY `status_id` (`status_id`),
  ADD KEY `delivery_user_id` (`delivery_user_id`);

--
-- Indexes for table `products`
--
ALTER TABLE `products`
  ADD PRIMARY KEY (`id`),
  ADD KEY `category_id` (`category_id`),
  ADD KEY `size_id` (`size_id`);

--
-- Indexes for table `product_category_options`
--
ALTER TABLE `product_category_options`
  ADD PRIMARY KEY (`id`),
  ADD KEY `product_id` (`product_id`),
  ADD KEY `category_option_id` (`category_option_id`),
  ADD KEY `option_value_id` (`option_value_id`);

--
-- Indexes for table `product_dynamic_sizes`
--
ALTER TABLE `product_dynamic_sizes`
  ADD PRIMARY KEY (`id`),
  ADD KEY `product_id` (`product_id`);

--
-- Indexes for table `product_extras`
--
ALTER TABLE `product_extras`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `product_extra_unique` (`product_id`,`extra_id`),
  ADD KEY `extra_id` (`extra_id`);

--
-- Indexes for table `product_mixes`
--
ALTER TABLE `product_mixes`
  ADD PRIMARY KEY (`id`),
  ADD KEY `main_product_id` (`main_product_id`),
  ADD KEY `mixed_product_id` (`mixed_product_id`);

--
-- Indexes for table `product_sauces`
--
ALTER TABLE `product_sauces`
  ADD PRIMARY KEY (`product_id`,`sauce_id`);

--
-- Indexes for table `product_sizes`
--
ALTER TABLE `product_sizes`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_product_size` (`product_id`,`size_id`),
  ADD KEY `size_id` (`size_id`);

--
-- Indexes for table `product_size_extras`
--
ALTER TABLE `product_size_extras`
  ADD PRIMARY KEY (`id`),
  ADD KEY `product_size_id` (`product_size_id`),
  ADD KEY `extra_id` (`extra_id`);

--
-- Indexes for table `ratings`
--
ALTER TABLE `ratings`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `reservations`
--
ALTER TABLE `reservations`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `confirmation_number` (`confirmation_number`),
  ADD KEY `assigned_to` (`assigned_to`);

--
-- Indexes for table `sauces`
--
ALTER TABLE `sauces`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `name` (`name`);

--
-- Indexes for table `settings`
--
ALTER TABLE `settings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `key` (`key`);

--
-- Indexes for table `sizes`
--
ALTER TABLE `sizes`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `name` (`name`);

--
-- Indexes for table `stores`
--
ALTER TABLE `stores`
  ADD PRIMARY KEY (`id`),
  ADD KEY `manager_id` (`manager_id`);

--
-- Indexes for table `tips`
--
ALTER TABLE `tips`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email` (`email`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `banners`
--
ALTER TABLE `banners`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `categories`
--
ALTER TABLE `categories`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `category_options`
--
ALTER TABLE `category_options`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `category_option_values`
--
ALTER TABLE `category_option_values`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `drinks`
--
ALTER TABLE `drinks`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `extras`
--
ALTER TABLE `extras`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `offers`
--
ALTER TABLE `offers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `offer_products`
--
ALTER TABLE `offer_products`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `operational_hours`
--
ALTER TABLE `operational_hours`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `orders`
--
ALTER TABLE `orders`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `order_drinks`
--
ALTER TABLE `order_drinks`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `order_extras`
--
ALTER TABLE `order_extras`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `order_items`
--
ALTER TABLE `order_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `order_sauces`
--
ALTER TABLE `order_sauces`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `order_statuses`
--
ALTER TABLE `order_statuses`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `order_status_history`
--
ALTER TABLE `order_status_history`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `products`
--
ALTER TABLE `products`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `product_category_options`
--
ALTER TABLE `product_category_options`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `product_dynamic_sizes`
--
ALTER TABLE `product_dynamic_sizes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `product_extras`
--
ALTER TABLE `product_extras`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `product_mixes`
--
ALTER TABLE `product_mixes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `product_sizes`
--
ALTER TABLE `product_sizes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `product_size_extras`
--
ALTER TABLE `product_size_extras`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `ratings`
--
ALTER TABLE `ratings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `reservations`
--
ALTER TABLE `reservations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `sauces`
--
ALTER TABLE `sauces`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `settings`
--
ALTER TABLE `settings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `sizes`
--
ALTER TABLE `sizes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `stores`
--
ALTER TABLE `stores`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tips`
--
ALTER TABLE `tips`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `category_options`
--
ALTER TABLE `category_options`
  ADD CONSTRAINT `category_options_ibfk_1` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `category_option_values`
--
ALTER TABLE `category_option_values`
  ADD CONSTRAINT `category_option_values_ibfk_1` FOREIGN KEY (`category_option_id`) REFERENCES `category_options` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `offer_products`
--
ALTER TABLE `offer_products`
  ADD CONSTRAINT `offer_products_ibfk_1` FOREIGN KEY (`offer_id`) REFERENCES `offers` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `offer_products_ibfk_2` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `orders`
--
ALTER TABLE `orders`
  ADD CONSTRAINT `orders_ibfk_1` FOREIGN KEY (`status_id`) REFERENCES `order_statuses` (`id`),
  ADD CONSTRAINT `orders_ibfk_2` FOREIGN KEY (`delivery_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `orders_ibfk_3` FOREIGN KEY (`tip_id`) REFERENCES `tips` (`id`);

--
-- Constraints for table `order_drinks`
--
ALTER TABLE `order_drinks`
  ADD CONSTRAINT `order_drinks_ibfk_1` FOREIGN KEY (`order_item_id`) REFERENCES `order_items` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `order_drinks_ibfk_2` FOREIGN KEY (`drink_id`) REFERENCES `drinks` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `order_extras`
--
ALTER TABLE `order_extras`
  ADD CONSTRAINT `order_extras_ibfk_1` FOREIGN KEY (`order_item_id`) REFERENCES `order_items` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `order_extras_ibfk_2` FOREIGN KEY (`extra_id`) REFERENCES `extras` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `order_items`
--
ALTER TABLE `order_items`
  ADD CONSTRAINT `order_items_ibfk_1` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `order_items_ibfk_2` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `order_items_ibfk_3` FOREIGN KEY (`size_id`) REFERENCES `sizes` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `order_status_history`
--
ALTER TABLE `order_status_history`
  ADD CONSTRAINT `order_status_history_ibfk_1` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `order_status_history_ibfk_2` FOREIGN KEY (`status_id`) REFERENCES `order_statuses` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `order_status_history_ibfk_3` FOREIGN KEY (`delivery_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `products`
--
ALTER TABLE `products`
  ADD CONSTRAINT `products_ibfk_1` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `product_category_options`
--
ALTER TABLE `product_category_options`
  ADD CONSTRAINT `product_category_options_ibfk_1` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `product_category_options_ibfk_2` FOREIGN KEY (`category_option_id`) REFERENCES `category_options` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `product_category_options_ibfk_3` FOREIGN KEY (`option_value_id`) REFERENCES `category_option_values` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `product_dynamic_sizes`
--
ALTER TABLE `product_dynamic_sizes`
  ADD CONSTRAINT `product_dynamic_sizes_ibfk_1` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `product_extras`
--
ALTER TABLE `product_extras`
  ADD CONSTRAINT `product_extras_ibfk_1` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `product_extras_ibfk_2` FOREIGN KEY (`extra_id`) REFERENCES `extras` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `product_mixes`
--
ALTER TABLE `product_mixes`
  ADD CONSTRAINT `product_mixes_ibfk_1` FOREIGN KEY (`main_product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `product_mixes_ibfk_2` FOREIGN KEY (`mixed_product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `product_sizes`
--
ALTER TABLE `product_sizes`
  ADD CONSTRAINT `product_sizes_ibfk_1` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `product_sizes_ibfk_2` FOREIGN KEY (`size_id`) REFERENCES `sizes` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `product_size_extras`
--
ALTER TABLE `product_size_extras`
  ADD CONSTRAINT `product_size_extras_ibfk_1` FOREIGN KEY (`product_size_id`) REFERENCES `product_dynamic_sizes` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `product_size_extras_ibfk_2` FOREIGN KEY (`extra_id`) REFERENCES `extras` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `reservations`
--
ALTER TABLE `reservations`
  ADD CONSTRAINT `reservations_ibfk_1` FOREIGN KEY (`assigned_to`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `stores`
--
ALTER TABLE `stores`
  ADD CONSTRAINT `stores_ibfk_1` FOREIGN KEY (`manager_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
