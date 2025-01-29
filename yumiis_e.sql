-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Jan 29, 2025 at 03:21 PM
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
-- Database: `yumiis_e`
--

-- --------------------------------------------------------

--
-- Table structure for table `app_data`
--

CREATE TABLE `app_data` (
  `id` int(10) UNSIGNED NOT NULL,
  `entity_type` varchar(20) NOT NULL,
  `name` varchar(255) DEFAULT NULL,
  `address` varchar(255) DEFAULT NULL,
  `phone` varchar(50) DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  `manager_id` int(11) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 0,
  `key` varchar(100) DEFAULT NULL,
  `value` text DEFAULT NULL,
  `type` varchar(20) DEFAULT NULL,
  `day_of_week` varchar(20) DEFAULT NULL,
  `date` date DEFAULT NULL,
  `title` varchar(100) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `open_time` time DEFAULT NULL,
  `close_time` time DEFAULT NULL,
  `is_closed` tinyint(1) DEFAULT 0,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `app_data`
--

INSERT INTO `app_data` (`id`, `entity_type`, `name`, `address`, `phone`, `email`, `manager_id`, `is_active`, `key`, `value`, `type`, `day_of_week`, `date`, `title`, `description`, `open_time`, `close_time`, `is_closed`, `created_at`, `updated_at`) VALUES
(1, 'hour', NULL, NULL, NULL, NULL, NULL, 0, NULL, NULL, 'regular', 'Sunday', NULL, NULL, NULL, NULL, NULL, 1, '2024-12-29 17:16:44', '2024-12-29 17:16:44'),
(2, 'store', 'Hannah Mullen', 'Quisquam omnis consectetur consequatur non', '+1 (133) 503-3556', 'lyjokoz@mailinator.com', 64, 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, '2024-12-29 17:17:00', '2024-12-29 17:17:12');

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

--
-- Dumping data for table `banners`
--

INSERT INTO `banners` (`id`, `title`, `image`, `link`, `is_active`, `created_at`, `updated_at`) VALUES
(3, 'PIzza', '6751d0e32cb97_pizza3.png', '', 1, '2024-12-05 16:12:19', '2025-01-01 13:48:15'),
(4, 'Aperiam dolorem placeat consequuntur suscipit eum ea molestias in suscipit culpa qui', '6788dda7ceb3d_screenshot-1736984517941.png', 'https://www.gyd.tv', 1, '2025-01-16 10:21:27', '2025-01-16 10:21:27');

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
  `is_active` tinyint(4) NOT NULL,
  `position` int(11) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `categories`
--

INSERT INTO `categories` (`id`, `name`, `description`, `image_url`, `created_at`, `is_active`, `position`) VALUES
(20, 'Italian Pizza', '', '', '2024-12-04 02:41:18', 0, 7),
(21, 'American Pizza', '', '', '2024-12-04 02:41:28', 0, 6),
(22, 'Pasta', '', '', '2024-12-04 02:41:36', 0, 5),
(23, 'Burger', '', '', '2024-12-04 02:41:43', 0, 4),
(24, 'Salate', '', '', '2024-12-04 02:41:50', 0, 3),
(25, 'Pizza', 'Pizza', '', '2024-12-20 10:42:52', 0, 1),
(26, 'usidjiospdk', 'dkmsiofdsjd', '', '2025-01-16 20:15:07', 0, 2);

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
-- Table structure for table `coupons`
--

CREATE TABLE `coupons` (
  `id` int(11) NOT NULL,
  `code` varchar(50) NOT NULL,
  `discount_type` enum('percentage','fixed') NOT NULL,
  `discount_value` decimal(10,2) NOT NULL,
  `usage_limit` int(11) DEFAULT 0,
  `used_count` int(11) DEFAULT 0,
  `is_active` tinyint(1) DEFAULT 1,
  `start_date` date DEFAULT NULL,
  `end_date` date DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `coupons`
--

INSERT INTO `coupons` (`id`, `code`, `discount_type`, `discount_value`, `usage_limit`, `used_count`, `is_active`, `start_date`, `end_date`, `created_at`, `updated_at`) VALUES
(3, 'VITI2025', 'percentage', 90.00, 0, 0, 1, '2024-12-29', '2025-02-08', '2024-12-30 17:40:44', '2025-01-16 20:54:06');

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

--
-- Dumping data for table `drinks`
--

INSERT INTO `drinks` (`id`, `name`, `price`, `created_at`) VALUES
(3, 'Coca Cola', 0.50, '2024-11-09 13:55:18'),
(4, 'Nayda Blackwell', 12.00, '2024-11-12 21:23:56'),
(5, 'Dr Pepper', 1.45, '2024-11-01 12:00:00'),
(6, 'Mountain Dew', 1.50, '2024-11-02 07:10:00'),
(7, '7 Up', 1.25, '2024-11-02 08:20:00'),
(8, 'Ginger Ale', 1.30, '2024-11-02 09:35:00'),
(9, 'Root Beer', 1.40, '2024-11-02 10:50:00'),
(10, 'Coca Cola Zero', 1.60, '2024-11-02 12:05:00'),
(11, 'Pepsi Max', 1.55, '2024-11-03 07:25:00'),
(12, 'Diet Sprite', 1.35, '2024-11-03 08:40:00'),
(13, 'Lemonade', 1.20, '2024-11-03 09:55:00'),
(14, 'Iced Tea', 1.50, '2024-11-03 11:10:00'),
(15, 'Sweet Tea', 1.45, '2024-11-03 12:25:00'),
(16, 'Hot Coffee', 2.00, '2024-11-04 07:40:00'),
(17, 'Latte', 2.50, '2024-11-04 08:55:00'),
(18, 'Cappuccino', 2.60, '2024-11-04 10:10:00'),
(19, 'Espresso', 2.20, '2024-11-04 11:25:00'),
(20, 'Americano', 2.30, '2024-11-04 12:40:00'),
(21, 'Mocha', 2.70, '2024-11-05 07:55:00'),
(22, 'Flat White', 2.80, '2024-11-05 09:10:00'),
(23, 'Macchiato', 2.50, '2024-11-05 10:25:00'),
(24, 'Irish Coffee', 3.00, '2024-11-05 11:40:00'),
(25, 'Cold Brew', 2.90, '2024-11-05 13:00:00'),
(26, 'Green Tea', 1.80, '2024-11-06 07:15:00'),
(27, 'Black Tea', 1.75, '2024-11-06 08:30:00'),
(28, 'Oolong Tea', 1.85, '2024-11-06 09:45:00'),
(29, 'Chamomile Tea', 1.90, '2024-11-06 11:00:00'),
(30, 'Earl Grey', 1.95, '2024-11-06 12:15:00'),
(31, 'Chai Latte', 2.40, '2024-11-07 07:30:00'),
(32, 'Matcha Latte', 2.60, '2024-11-07 08:45:00'),
(33, 'Bubble Tea', 3.00, '2024-11-07 10:00:00'),
(34, 'Taro Milk Tea', 3.10, '2024-11-07 11:15:00'),
(35, 'Thai Iced Tea', 2.80, '2024-11-07 12:30:00'),
(36, 'Energy Drink Red Bull', 2.50, '2024-11-08 07:45:00'),
(37, 'Monster Energy', 2.60, '2024-11-08 09:00:00'),
(38, 'Rockstar Energy', 2.55, '2024-11-08 10:15:00'),
(39, '5-hour Energy', 3.00, '2024-11-08 11:30:00'),
(40, 'NOS Energy', 2.70, '2024-11-08 12:45:00'),
(41, 'Gatorade Lemon-Lime', 1.60, '2024-11-09 07:00:00'),
(42, 'Powerade Mountain Berry', 1.65, '2024-11-09 08:15:00'),
(43, 'Vitaminwater XXX', 2.00, '2024-11-09 09:30:00'),
(44, 'Aquafina Water', 1.00, '2024-11-09 10:45:00'),
(45, 'Dasani Water', 1.10, '2024-11-09 12:00:00'),
(46, 'Evian Mineral Water', 1.50, '2024-11-10 07:10:00'),
(47, 'Fiji Water', 1.60, '2024-11-10 08:25:00'),
(48, 'Smartwater', 1.55, '2024-11-10 09:40:00'),
(49, 'S. Pellegrino', 1.80, '2024-11-10 10:55:00'),
(50, 'LaCroix Sparkling Water', 1.70, '2024-11-10 12:10:00'),
(51, 'Orange Juice', 2.00, '2024-11-11 07:20:00'),
(52, 'Apple Juice', 1.90, '2024-11-11 08:35:00'),
(53, 'Grape Juice', 1.95, '2024-11-11 09:50:00'),
(54, 'Cranberry Juice', 2.10, '2024-11-11 11:05:00'),
(55, 'Pineapple Juice', 2.20, '2024-11-11 12:20:00'),
(56, 'Mango Smoothie', 3.50, '2024-11-12 07:30:00'),
(57, 'Strawberry Smoothie', 3.60, '2024-11-12 08:45:00'),
(58, 'Banana Smoothie', 3.55, '2024-11-12 10:00:00'),
(59, 'Blueberry Smoothie', 3.65, '2024-11-12 11:15:00'),
(60, 'Raspberry Smoothie', 3.70, '2024-11-12 12:30:00'),
(61, 'Lemonade with Mint', 1.80, '2024-11-13 07:40:00'),
(62, 'Iced Green Tea', 1.70, '2024-11-13 08:55:00'),
(63, 'Iced Black Tea', 1.75, '2024-11-13 10:10:00'),
(64, 'Herbal Tea', 1.85, '2024-11-13 11:25:00'),
(65, 'Fruit Punch', 1.95, '2024-11-13 12:40:00'),
(66, 'Hot Chocolate', 2.20, '2024-11-14 07:50:00'),
(67, 'Caramel Macchiato', 2.70, '2024-11-14 09:05:00'),
(68, 'Vanilla Latte', 2.65, '2024-11-14 10:20:00'),
(69, 'Pumpkin Spice Latte', 2.80, '2024-11-14 11:35:00'),
(70, 'Honey Almondmilk Flat White', 2.90, '2024-11-14 12:50:00'),
(71, 'Mojito', 5.00, '2024-11-15 07:00:00'),
(72, 'Margarita', 5.50, '2024-11-15 08:15:00'),
(73, 'Old Fashioned', 6.00, '2024-11-15 09:30:00'),
(74, 'Martini', 6.50, '2024-11-15 10:45:00'),
(75, 'Whiskey Sour', 5.75, '2024-11-15 12:00:00'),
(76, 'Beer Lager', 3.00, '2024-11-16 07:10:00'),
(77, 'Beer Ale', 3.20, '2024-11-16 08:25:00'),
(78, 'Craft IPA', 4.00, '2024-11-16 09:40:00'),
(79, 'Stout Beer', 4.50, '2024-11-16 10:55:00'),
(80, 'Pilsner', 3.30, '2024-11-16 12:10:00'),
(81, 'Red Wine', 7.00, '2024-11-17 07:20:00'),
(82, 'White Wine', 7.50, '2024-11-17 08:35:00'),
(83, 'Rosé Wine', 7.25, '2024-11-17 09:50:00'),
(84, 'Champagne', 15.00, '2024-11-17 11:05:00'),
(85, 'Prosecco', 8.00, '2024-11-17 12:20:00'),
(86, 'Vodka', 10.00, '2024-11-17 13:35:00'),
(87, 'Gin', 10.50, '2024-11-17 14:50:00'),
(88, 'Rum', 9.80, '2024-11-17 16:05:00'),
(89, 'Tequila', 11.00, '2024-11-17 17:20:00'),
(90, 'Scotch Whisky', 12.00, '2024-11-17 18:35:00'),
(91, 'Baileys Irish Cream', 8.50, '2024-11-17 19:50:00'),
(92, 'Kahlúa', 9.00, '2024-11-17 21:05:00'),
(93, 'Sambuca', 9.50, '2024-11-17 22:20:00'),
(94, 'Absinthe', 14.00, '2024-11-17 22:35:00'),
(95, 'Cider Apple', 4.00, '2024-11-17 23:00:00'),
(96, 'Cider Pear', 4.20, '2024-11-17 23:15:00'),
(97, 'Hard Seltzer Berry', 5.00, '2024-11-17 23:30:00'),
(98, 'Hard Lemonade', 4.50, '2024-11-17 23:45:00'),
(99, 'Energy Drink Monster Ultra', 2.80, '2024-11-18 00:00:00'),
(100, 'Sparkling Apple dspokfopsdfkposdk', 15.00, '2024-11-18 00:15:00');

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

--
-- Dumping data for table `extras`
--

INSERT INTO `extras` (`id`, `name`, `price`, `created_at`, `category`, `quantity`) VALUES
(2, 'Eggs Cheese', 0.50, '2024-11-09 13:11:15', 'addon', NULL),
(3, 'Jalapeno', 2.00, '2024-11-09 13:57:24', 'addon', NULL),
(4, 'Mayonese', 0.60, '2024-11-09 13:57:35', 'addon', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `extras_products`
--

CREATE TABLE `extras_products` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `category` enum('Extras','Sauces','Dressing') NOT NULL,
  `price` decimal(10,2) NOT NULL DEFAULT 0.00
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `extras_products`
--

INSERT INTO `extras_products` (`id`, `name`, `category`, `price`) VALUES
(1, 'Chester Perez', 'Extras', 1.50),
(2, 'Keely Wall', 'Dressing', 202.00),
(3, 'Irma Bradley', 'Sauces', 119.00),
(4, 'Galena Booth', 'Sauces', 497.00),
(5, 'Catherine Ward', 'Sauces', 554.00),
(6, 'Ethan Murray', 'Dressing', 830.00),
(7, 'Wang Allen', 'Dressing', 459.00),
(8, 'Beverly Stanton', 'Dressing', 30.00),
(9, 'Scott Villarreal', 'Extras', 341.00),
(10, 'James Waters', 'Extras', 20.00),
(11, 'Ginger Powers', 'Extras', 440.00),
(12, 'Zenaida Moore', 'Extras', 963.00);

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
-- Table structure for table `orders`
--

CREATE TABLE `orders` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `customer_name` varchar(255) NOT NULL,
  `customer_email` varchar(255) NOT NULL,
  `customer_phone` varchar(50) NOT NULL,
  `delivery_address` text NOT NULL,
  `total_amount` decimal(10,2) NOT NULL,
  `tip_id` int(11) DEFAULT NULL,
  `tip_amount` decimal(10,2) DEFAULT 0.00,
  `scheduled_date` date DEFAULT NULL,
  `scheduled_time` time DEFAULT NULL,
  `payment_method` varchar(50) NOT NULL,
  `coupon_code` varchar(50) DEFAULT NULL,
  `coupon_discount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `store_id` int(11) DEFAULT NULL,
  `status_id` int(11) NOT NULL DEFAULT 2,
  `order_details` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`order_details`)),
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `status` enum('New Order','Kitchen','On the Way','Delivered','Canceled') NOT NULL,
  `is_deleted` tinyint(1) NOT NULL DEFAULT 0,
  `deleted_at` datetime DEFAULT NULL,
  `delivery_user_id` int(11) DEFAULT NULL,
  `postal_code` varchar(25) NOT NULL,
  `payment_info` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `orders`
--

INSERT INTO `orders` (`id`, `user_id`, `customer_name`, `customer_email`, `customer_phone`, `delivery_address`, `total_amount`, `tip_id`, `tip_amount`, `scheduled_date`, `scheduled_time`, `payment_method`, `coupon_code`, `coupon_discount`, `store_id`, `status_id`, `order_details`, `created_at`, `updated_at`, `status`, `is_deleted`, `deleted_at`, `delivery_user_id`, `postal_code`, `payment_info`) VALUES
(1, NULL, 'Kareem Kaufman', 'rysa@mailinator.com', '+1 (895) 385-6218', 'Non fugiat nobis sed', 8434.50, 4, 2.00, '0000-00-00', '00:00:00', 'cash', NULL, 0.00, 3, 4, '{\n    \"items\": [\n        {\n            \"product_id\": 21,\n            \"name\": \"Mariko Morse\",\n            \"description\": \"Expedita excepteur c\",\n            \"image_url\": \"https:\\/\\/plus.unsplash.com\\/premium_photo-1664303218668-03fa4e612038?q=80&w=1760&auto=format&fit=crop&ixlib=rb-4.0.3&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D\",\n            \"size\": \"Incididunt animi do\",\n            \"size_price\": 336,\n            \"extras\": [],\n            \"sauces\": [],\n            \"dresses\": [],\n            \"drink\": {\n                \"id\": 8,\n                \"name\": \"Ginger Ale\",\n                \"price\": \"1.30\",\n                \"created_at\": \"2024-11-02 10:35:00\"\n            },\n            \"special_instructions\": \"Quibusdam quo enim v\",\n            \"quantity\": 25,\n            \"unit_price\": 337.3,\n            \"total_price\": 8432.5\n        }\n    ],\n    \"latitude\": \"42.8021317\",\n    \"longitude\": \"20.9298313\",\n    \"tip_id\": \"4\",\n    \"tip_amount\": 2,\n    \"store_id\": 3,\n    \"is_event\": false,\n    \"scheduled_date\": \"\",\n    \"scheduled_time\": \"\",\n    \"shipping_fee\": 0,\n    \"coupon_code\": null,\n    \"coupon_discount\": 0\n}', '2025-01-18 21:38:45', '2025-01-18 21:54:05', 'On the Way', 0, NULL, 116, '', ''),
(2, NULL, 'Lois Jones', 'begotyzy@mailinator.com', '+1 (581) 667-4009', 'Evlija Çelebi, Novosellë, Municipality of Prizren, District of Prizren, 20080, Kosovo', 1856844.00, 3, 168804.00, '2025-01-21', '15:57:00', 'cash', NULL, 0.00, 3, 4, '{\n    \"items\": [\n        {\n            \"product_id\": 25,\n            \"name\": \"Lyle Stafford\",\n            \"description\": \"Ipsum qui maiores e\",\n            \"image_url\": \"https:\\/\\/images.unsplash.com\\/photo-1721332149274-586f2604884d?q=80&w=1936&auto=format&fit=crop&ixlib=rb-4.0.3&ixid=M3wxMjA3fDF8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D\",\n            \"size\": null,\n            \"size_price\": 0,\n            \"extras\": [\n                {\n                    \"name\": \"Carol Mcintyre\",\n                    \"price\": 240,\n                    \"quantity\": 79\n                },\n                {\n                    \"name\": \"Veda Adkins\",\n                    \"price\": 542,\n                    \"quantity\": 1\n                }\n            ],\n            \"sauces\": [\n                {\n                    \"name\": \"Kaden Boyle\",\n                    \"price\": 404,\n                    \"quantity\": 1\n                },\n                {\n                    \"name\": \"Ramona Nielsen\",\n                    \"price\": 615,\n                    \"quantity\": 1\n                }\n            ],\n            \"dresses\": [\n                {\n                    \"name\": \"Incididunt doloremqu\",\n                    \"price\": 171,\n                    \"quantity\": 1\n                }\n            ],\n            \"drink\": {\n                \"id\": 94,\n                \"name\": \"Absinthe\",\n                \"price\": \"14.00\",\n                \"created_at\": \"2024-11-17 23:35:00\"\n            },\n            \"special_instructions\": \"Omnis tempora sint q\",\n            \"quantity\": 81,\n            \"unit_price\": 20840,\n            \"total_price\": 1688040\n        }\n    ],\n    \"latitude\": \"42.20456305378201\",\n    \"longitude\": \"20.773854169878202\",\n    \"tip_id\": \"3\",\n    \"tip_amount\": 168804,\n    \"store_id\": 3,\n    \"is_event\": false,\n    \"scheduled_date\": \"2025-01-21\",\n    \"scheduled_time\": \"15:57\",\n    \"shipping_fee\": 0,\n    \"coupon_code\": null,\n    \"coupon_discount\": 0\n}', '2025-01-21 23:19:50', '2025-01-21 23:19:50', 'New Order', 0, NULL, NULL, 'Tempor molestiae sit', ''),
(3, NULL, 'Palmer Marquez', 'hova@mailinator.com', '+1 (988) 574-3332', 'Prizren', 1734.37, 3, 157.67, '2025-01-22', '18:36:00', 'cash', NULL, 0.00, 3, 4, '{\n    \"items\": [\n        {\n            \"product_id\": 25,\n            \"name\": \"Lyle Stafford\",\n            \"description\": \"Ipsum qui maiores e\",\n            \"image_url\": \"https:\\/\\/images.unsplash.com\\/photo-1721332149274-586f2604884d?q=80&w=1936&auto=format&fit=crop&ixlib=rb-4.0.3&ixid=M3wxMjA3fDF8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D\",\n            \"size\": \"\",\n            \"size_price\": 0,\n            \"extras\": [\n                {\n                    \"name\": \"Carol Mcintyre\",\n                    \"price\": 240,\n                    \"quantity\": 6\n                }\n            ],\n            \"sauces\": [],\n            \"dresses\": [],\n            \"drink\": {\n                \"id\": 67,\n                \"name\": \"Caramel Macchiato\",\n                \"price\": \"2.70\",\n                \"created_at\": \"2024-11-14 10:05:00\"\n            },\n            \"special_instructions\": \"\",\n            \"quantity\": 1,\n            \"unit_price\": 1576.7,\n            \"total_price\": 1576.7\n        }\n    ],\n    \"latitude\": \"42.2130151\",\n    \"longitude\": \"20.7363339\",\n    \"tip_id\": \"3\",\n    \"tip_amount\": 157.67000000000002,\n    \"store_id\": 3,\n    \"is_event\": false,\n    \"scheduled_date\": \"2025-01-22\",\n    \"scheduled_time\": \"18:36\",\n    \"shipping_fee\": 0,\n    \"coupon_code\": null,\n    \"coupon_discount\": 0\n}', '2025-01-22 14:55:06', '2025-01-22 14:55:06', 'New Order', 0, NULL, NULL, 'Possimus sapiente n', '');

-- --------------------------------------------------------

--
-- Table structure for table `order_drinks`
--

CREATE TABLE `order_drinks` (
  `id` int(11) NOT NULL,
  `order_item_id` int(11) NOT NULL,
  `drink_id` int(11) NOT NULL,
  `quantity` int(11) NOT NULL DEFAULT 1,
  `unit_price` decimal(10,2) NOT NULL DEFAULT 0.00,
  `total_price` decimal(10,2) NOT NULL DEFAULT 0.00
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `order_extras`
--

CREATE TABLE `order_extras` (
  `id` int(11) NOT NULL,
  `order_item_id` int(11) NOT NULL,
  `extra_id` int(11) NOT NULL,
  `quantity` int(11) NOT NULL DEFAULT 1,
  `unit_price` decimal(10,2) NOT NULL DEFAULT 0.00,
  `total_price` decimal(10,2) NOT NULL DEFAULT 0.00
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
  `quantity` int(11) NOT NULL DEFAULT 1,
  `price` decimal(10,2) NOT NULL DEFAULT 0.00,
  `special_instructions` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `order_statuses`
--

CREATE TABLE `order_statuses` (
  `id` int(11) NOT NULL,
  `status` varchar(50) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `order_statuses`
--

INSERT INTO `order_statuses` (`id`, `status`) VALUES
(6, 'Canceled'),
(5, 'Delivered'),
(3, 'Kitchen'),
(2, 'New Order'),
(4, 'On the Way');

-- --------------------------------------------------------

--
-- Table structure for table `products`
--

CREATE TABLE `products` (
  `id` int(11) NOT NULL,
  `product_code` varchar(50) NOT NULL,
  `name` varchar(255) NOT NULL,
  `category` varchar(100) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `allergies` text DEFAULT NULL,
  `image_url` varchar(255) DEFAULT NULL,
  `is_new` tinyint(1) DEFAULT 0,
  `is_offer` tinyint(1) DEFAULT 0,
  `is_active` tinyint(1) DEFAULT 1,
  `properties` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `category_id` int(11) DEFAULT NULL,
  `base_price` decimal(10,2) NOT NULL DEFAULT 0.00,
  `updated_by` int(25) NOT NULL,
  `created_by` int(25) NOT NULL
) ;

--
-- Dumping data for table `products`
--

INSERT INTO `products` (`id`, `product_code`, `name`, `category`, `description`, `allergies`, `image_url`, `is_new`, `is_offer`, `is_active`, `properties`, `created_at`, `updated_at`, `category_id`, `base_price`, `updated_by`, `created_by`) VALUES
(25, 'Illo do commodo veri', 'Lyle Stafford', NULL, 'Ipsum qui maiores e', 'Nihil quia dolore ac', 'https://images.unsplash.com/photo-1721332149274-586f2604884d?q=80&w=1936&auto=format&fit=crop&ixlib=rb-4.0.3&ixid=M3wxMjA3fDF8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D', 1, 1, 1, '{\"base_price\":134,\"extras\":[{\"name\":\"Carol Mcintyre\",\"price\":240},{\"name\":\"Rosalyn Powers\",\"price\":648},{\"name\":\"Veda Adkins\",\"price\":542},{\"name\":\"Marshall Christensen\",\"price\":530}],\"sauces\":[{\"name\":\"Sybil Mays\",\"price\":144},{\"name\":\"Quinn Berger\",\"price\":390},{\"name\":\"Kaden Boyle\",\"price\":404},{\"name\":\"Ramona Nielsen\",\"price\":615}],\"max_sauces_base\":86,\"max_extras_base\":80,\"dresses\":[{\"name\":\"Incididunt doloremqu\",\"price\":171},{\"name\":\"jh\",\"price\":12},{\"name\":\"yui\",\"price\":12}],\"max_dresses_base\":2}', '2025-01-21 20:57:29', '2025-01-21 20:58:01', 25, 0.00, 115, 115),
(26, 'Perferendis suscipit', 'Wilma Castillo', NULL, 'Ut sed impedit moll', 'Ab ad voluptate id c', 'uploads/product_6796a9641c2119.88502825.jpg', 1, 1, 1, '{\"extras\":[{\"name\":\"Jessamine Castro\",\"price\":748},{\"name\":\"Colleen Solomon\",\"price\":210},{\"name\":\"Chaim Morton\",\"price\":753},{\"name\":\"Dylan Blackwell\",\"price\":867},{\"name\":\"Ginger Powers\",\"price\":440},{\"name\":\"James Waters\",\"price\":20},{\"name\":\"Scott Villarreal\",\"price\":341}],\"sauces\":[{\"name\":\"Aiko Woodward\",\"price\":338},{\"name\":\"Malik Larsen\",\"price\":308},{\"name\":\"Cara Barnes\",\"price\":793},{\"name\":\"Galena Booth\",\"price\":497}],\"sizes\":[{\"size\":\"Consectetur quae qui\",\"price\":680,\"extras\":[],\"sauces\":[],\"max_sauces\":12},{\"size\":\"Quia nulla explicabo\",\"price\":186,\"extras\":[],\"sauces\":[],\"max_sauces\":10},{\"size\":\"Non quae et anim eiu\",\"price\":18,\"extras\":[],\"sauces\":[],\"max_sauces\":17}],\"dresses\":[{\"name\":\"Dolor sint corporis\",\"price\":697},{\"name\":\"Molestiae eaque quas\",\"price\":311},{\"name\":\"Ea quam molestias qu\",\"price\":203},{\"name\":\"Illum in vero perfe\",\"price\":607},{\"name\":\"Ethan Murray\",\"price\":830},{\"name\":\"Keely Wall\",\"price\":202},{\"name\":\"Wang Allen\",\"price\":459}]}', '2025-01-26 15:46:44', '2025-01-26 22:30:12', 26, 0.00, 115, 115);

-- --------------------------------------------------------

--
-- Table structure for table `product_audit`
--

CREATE TABLE `product_audit` (
  `id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `action` varchar(20) NOT NULL,
  `changed_by` int(11) NOT NULL,
  `changed_at` datetime NOT NULL DEFAULT current_timestamp(),
  `old_values` text DEFAULT NULL,
  `new_values` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `product_audit`
--

INSERT INTO `product_audit` (`id`, `product_id`, `action`, `changed_by`, `changed_at`, `old_values`, `new_values`) VALUES
(1, 17, 'update', 115, '2025-01-05 20:36:20', '{\"id\":17,\"product_code\":\"Veniam illo eveniet\",\"name\":\"Sopoline Charles\",\"category\":null,\"description\":\"Sit neque magni ipsa\",\"allergies\":\"Minim incididunt eos\",\"image_url\":\"https:\\/\\/images.unsplash.com\\/photo-1721332149274-586f2604884d?q=80&w=1936&auto=format&fit=crop&ixlib=rb-4.0.3&ixid=M3wxMjA3fDF8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D\",\"is_new\":1,\"is_offer\":1,\"is_active\":1,\"properties\":\"{\\\"base_price\\\":320}\",\"created_at\":\"2025-01-05 20:32:44\",\"updated_at\":\"2025-01-05 20:33:01\",\"category_id\":24,\"base_price\":\"0.00\",\"updated_by\":115,\"created_by\":115}', '{\"id\":17,\"product_code\":\"Veniam illo eveniet\",\"name\":\"Sopoline Charles\",\"category\":null,\"description\":\"Sit neque magni ipsa\",\"allergies\":\"Minim incididunt eos\",\"image_url\":\"https:\\/\\/images.unsplash.com\\/photo-1721332149274-586f2604884d?q=80&w=1936&auto=format&fit=crop&ixlib=rb-4.0.3&ixid=M3wxMjA3fDF8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D\",\"is_new\":1,\"is_offer\":1,\"is_active\":1,\"properties\":\"{\\\"extras\\\":[{\\\"name\\\":\\\"sad\\\",\\\"price\\\":12}],\\\"sauces\\\":[],\\\"sizes\\\":[{\\\"size\\\":\\\"1\\\",\\\"price\\\":23,\\\"extras\\\":[{\\\"name\\\":\\\"sad\\\",\\\"price\\\":12}],\\\"sauces\\\":[],\\\"max_sauces\\\":0}],\\\"dresses\\\":[{\\\"name\\\":\\\"sad\\\",\\\"price\\\":12}]}\",\"created_at\":\"2025-01-05 20:32:44\",\"updated_at\":\"2025-01-05 20:36:20\",\"category_id\":24,\"base_price\":\"0.00\",\"updated_by\":115,\"created_by\":115}'),
(2, 17, 'delete', 115, '2025-01-05 20:46:13', '{\"id\":17,\"product_code\":\"Veniam illo eveniet\",\"name\":\"Sopoline Charles\",\"category\":null,\"description\":\"Sit neque magni ipsa\",\"allergies\":\"Minim incididunt eos\",\"image_url\":\"https:\\/\\/images.unsplash.com\\/photo-1721332149274-586f2604884d?q=80&w=1936&auto=format&fit=crop&ixlib=rb-4.0.3&ixid=M3wxMjA3fDF8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D\",\"is_new\":1,\"is_offer\":1,\"is_active\":1,\"properties\":\"{\\\"extras\\\":[{\\\"name\\\":\\\"sad\\\",\\\"price\\\":12}],\\\"sauces\\\":[],\\\"sizes\\\":[{\\\"size\\\":\\\"1\\\",\\\"price\\\":23,\\\"extras\\\":[{\\\"name\\\":\\\"sad\\\",\\\"price\\\":12}],\\\"sauces\\\":[],\\\"max_sauces\\\":0}],\\\"dresses\\\":[{\\\"name\\\":\\\"sad\\\",\\\"price\\\":12}]}\",\"created_at\":\"2025-01-05 20:32:44\",\"updated_at\":\"2025-01-05 20:36:20\",\"category_id\":24,\"base_price\":\"0.00\",\"updated_by\":115,\"created_by\":115}', NULL),
(3, 18, 'insert', 115, '2025-01-05 21:02:57', NULL, '{\"id\":18,\"product_code\":\"Non quo cupidatat al\",\"name\":\"Isabella White\",\"category\":null,\"description\":\"Tempora tempor exped\",\"allergies\":\"Fugiat magni dolore\",\"image_url\":\"https:\\/\\/images.unsplash.com\\/photo-1721332149274-586f2604884d?q=80&w=1936&auto=format&fit=crop&ixlib=rb-4.0.3&ixid=M3wxMjA3fDF8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D\",\"is_new\":0,\"is_offer\":0,\"is_active\":0,\"properties\":\"{\\\"base_price\\\":959,\\\"extras\\\":[],\\\"sauces\\\":[],\\\"dresses\\\":[]}\",\"created_at\":\"2025-01-05 21:02:57\",\"updated_at\":\"2025-01-05 21:02:57\",\"category_id\":24,\"base_price\":\"0.00\",\"updated_by\":0,\"created_by\":115}'),
(4, 18, 'update', 115, '2025-01-05 21:03:26', '{\"id\":18,\"product_code\":\"Non quo cupidatat al\",\"name\":\"Isabella White\",\"category\":null,\"description\":\"Tempora tempor exped\",\"allergies\":\"Fugiat magni dolore\",\"image_url\":\"https:\\/\\/images.unsplash.com\\/photo-1721332149274-586f2604884d?q=80&w=1936&auto=format&fit=crop&ixlib=rb-4.0.3&ixid=M3wxMjA3fDF8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D\",\"is_new\":0,\"is_offer\":0,\"is_active\":0,\"properties\":\"{\\\"base_price\\\":959,\\\"extras\\\":[],\\\"sauces\\\":[],\\\"dresses\\\":[]}\",\"created_at\":\"2025-01-05 21:02:57\",\"updated_at\":\"2025-01-05 21:02:57\",\"category_id\":24,\"base_price\":\"0.00\",\"updated_by\":0,\"created_by\":115}', '{\"id\":18,\"product_code\":\"Non quo cupidatat al\",\"name\":\"Isabella White\",\"category\":null,\"description\":\"Tempora tempor exped\",\"allergies\":\"Fugiat magni dolore\",\"image_url\":\"https:\\/\\/images.unsplash.com\\/photo-1721332149274-586f2604884d?q=80&w=1936&auto=format&fit=crop&ixlib=rb-4.0.3&ixid=M3wxMjA3fDF8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D\",\"is_new\":0,\"is_offer\":1,\"is_active\":0,\"properties\":\"{\\\"base_price\\\":959}\",\"created_at\":\"2025-01-05 21:02:57\",\"updated_at\":\"2025-01-05 21:03:26\",\"category_id\":24,\"base_price\":\"0.00\",\"updated_by\":115,\"created_by\":115}'),
(5, 18, 'delete', 115, '2025-01-05 21:06:09', '{\"id\":18,\"product_code\":\"Non quo cupidatat al\",\"name\":\"Isabella White\",\"category\":null,\"description\":\"Tempora tempor exped\",\"allergies\":\"Fugiat magni dolore\",\"image_url\":\"https:\\/\\/images.unsplash.com\\/photo-1721332149274-586f2604884d?q=80&w=1936&auto=format&fit=crop&ixlib=rb-4.0.3&ixid=M3wxMjA3fDF8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D\",\"is_new\":0,\"is_offer\":1,\"is_active\":0,\"properties\":\"{\\\"base_price\\\":959}\",\"created_at\":\"2025-01-05 21:02:57\",\"updated_at\":\"2025-01-05 21:03:26\",\"category_id\":24,\"base_price\":\"0.00\",\"updated_by\":115,\"created_by\":115}', NULL),
(6, 19, 'insert', 115, '2025-01-05 21:06:16', NULL, '{\"id\":19,\"product_code\":\"Laudantium sunt obc\",\"name\":\"Jordan Solis\",\"category\":null,\"description\":\"Consectetur excepte\",\"allergies\":\"Sint tempor eos no\",\"image_url\":\"https:\\/\\/images.unsplash.com\\/photo-1721332149274-586f2604884d?q=80&w=1936&auto=format&fit=crop&ixlib=rb-4.0.3&ixid=M3wxMjA3fDF8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D\",\"is_new\":0,\"is_offer\":0,\"is_active\":1,\"properties\":\"{\\\"extras\\\":[],\\\"sauces\\\":[],\\\"sizes\\\":[]}\",\"created_at\":\"2025-01-05 21:06:16\",\"updated_at\":\"2025-01-05 21:06:16\",\"category_id\":24,\"base_price\":\"0.00\",\"updated_by\":0,\"created_by\":115}'),
(7, 19, 'update', 115, '2025-01-05 21:06:32', '{\"id\":19,\"product_code\":\"Laudantium sunt obc\",\"name\":\"Jordan Solis\",\"category\":null,\"description\":\"Consectetur excepte\",\"allergies\":\"Sint tempor eos no\",\"image_url\":\"https:\\/\\/images.unsplash.com\\/photo-1721332149274-586f2604884d?q=80&w=1936&auto=format&fit=crop&ixlib=rb-4.0.3&ixid=M3wxMjA3fDF8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D\",\"is_new\":0,\"is_offer\":0,\"is_active\":1,\"properties\":\"{\\\"extras\\\":[],\\\"sauces\\\":[],\\\"sizes\\\":[]}\",\"created_at\":\"2025-01-05 21:06:16\",\"updated_at\":\"2025-01-05 21:06:16\",\"category_id\":24,\"base_price\":\"0.00\",\"updated_by\":0,\"created_by\":115}', '{\"id\":19,\"product_code\":\"Laudantium sunt obc\",\"name\":\"Jordan Solis\",\"category\":null,\"description\":\"Consectetur excepte\",\"allergies\":\"Sint tempor eos no\",\"image_url\":\"https:\\/\\/images.unsplash.com\\/photo-1721332149274-586f2604884d?q=80&w=1936&auto=format&fit=crop&ixlib=rb-4.0.3&ixid=M3wxMjA3fDF8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D\",\"is_new\":1,\"is_offer\":1,\"is_active\":1,\"properties\":\"{\\\"base_price\\\":0}\",\"created_at\":\"2025-01-05 21:06:16\",\"updated_at\":\"2025-01-05 21:06:32\",\"category_id\":24,\"base_price\":\"0.00\",\"updated_by\":115,\"created_by\":115}'),
(8, 19, 'update', 115, '2025-01-05 21:10:29', '{\"id\":19,\"product_code\":\"Laudantium sunt obc\",\"name\":\"Jordan Solis\",\"category\":null,\"description\":\"Consectetur excepte\",\"allergies\":\"Sint tempor eos no\",\"image_url\":\"https:\\/\\/images.unsplash.com\\/photo-1721332149274-586f2604884d?q=80&w=1936&auto=format&fit=crop&ixlib=rb-4.0.3&ixid=M3wxMjA3fDF8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D\",\"is_new\":1,\"is_offer\":1,\"is_active\":1,\"properties\":\"{\\\"base_price\\\":0}\",\"created_at\":\"2025-01-05 21:06:16\",\"updated_at\":\"2025-01-05 21:06:32\",\"category_id\":24,\"base_price\":\"0.00\",\"updated_by\":115,\"created_by\":115}', '{\"id\":19,\"product_code\":\"Laudantium sunt obc\",\"name\":\"Jordan Solis\",\"category\":null,\"description\":\"Consectetur excepte\",\"allergies\":\"Sint tempor eos no\",\"image_url\":\"https:\\/\\/images.unsplash.com\\/photo-1721332149274-586f2604884d?q=80&w=1936&auto=format&fit=crop&ixlib=rb-4.0.3&ixid=M3wxMjA3fDF8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D\",\"is_new\":1,\"is_offer\":1,\"is_active\":1,\"properties\":\"{\\\"base_price\\\":0}\",\"created_at\":\"2025-01-05 21:06:16\",\"updated_at\":\"2025-01-05 21:10:29\",\"category_id\":24,\"base_price\":\"0.00\",\"updated_by\":115,\"created_by\":115}'),
(9, 20, 'insert', 115, '2025-01-06 21:13:40', NULL, '{\"id\":20,\"product_code\":\"Esse fuga Cum volu\",\"name\":\"Allen Camacho\",\"category\":null,\"description\":\"Iste praesentium tot\",\"allergies\":\"Consequatur providen\",\"image_url\":\"https:\\/\\/images.unsplash.com\\/photo-1721332149274-586f2604884d?q=80&w=1936&auto=format&fit=crop&ixlib=rb-4.0.3&ixid=M3wxMjA3fDF8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D\",\"is_new\":1,\"is_offer\":0,\"is_active\":1,\"properties\":\"{\\\"base_price\\\":565}\",\"created_at\":\"2025-01-06 21:13:40\",\"updated_at\":\"2025-01-06 21:13:40\",\"category_id\":20,\"base_price\":\"0.00\",\"updated_by\":0,\"created_by\":115}'),
(10, 20, 'delete', 115, '2025-01-07 10:14:01', '{\"id\":20,\"product_code\":\"Esse fuga Cum volu\",\"name\":\"Allen Camacho\",\"category\":null,\"description\":\"Iste praesentium tot\",\"allergies\":\"Consequatur providen\",\"image_url\":\"https:\\/\\/images.unsplash.com\\/photo-1721332149274-586f2604884d?q=80&w=1936&auto=format&fit=crop&ixlib=rb-4.0.3&ixid=M3wxMjA3fDF8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D\",\"is_new\":1,\"is_offer\":0,\"is_active\":1,\"properties\":\"{\\\"base_price\\\":565}\",\"created_at\":\"2025-01-06 21:13:40\",\"updated_at\":\"2025-01-06 21:13:40\",\"category_id\":20,\"base_price\":\"0.00\",\"updated_by\":0,\"created_by\":115}', NULL),
(11, 19, 'delete', 115, '2025-01-07 10:14:02', '{\"id\":19,\"product_code\":\"Laudantium sunt obc\",\"name\":\"Jordan Solis\",\"category\":null,\"description\":\"Consectetur excepte\",\"allergies\":\"Sint tempor eos no\",\"image_url\":\"https:\\/\\/images.unsplash.com\\/photo-1721332149274-586f2604884d?q=80&w=1936&auto=format&fit=crop&ixlib=rb-4.0.3&ixid=M3wxMjA3fDF8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D\",\"is_new\":1,\"is_offer\":1,\"is_active\":1,\"properties\":\"{\\\"base_price\\\":0}\",\"created_at\":\"2025-01-05 21:06:16\",\"updated_at\":\"2025-01-05 21:10:29\",\"category_id\":24,\"base_price\":\"0.00\",\"updated_by\":115,\"created_by\":115}', NULL),
(12, 21, 'insert', 115, '2025-01-07 10:14:48', NULL, '{\"id\":21,\"product_code\":\"In quia non hic reic\",\"name\":\"Mariko Morse\",\"category\":null,\"description\":\"Expedita excepteur c\",\"allergies\":\"Dolor commodo blandi\",\"image_url\":\"https:\\/\\/plus.unsplash.com\\/premium_photo-1664303218668-03fa4e612038?q=80&w=1760&auto=format&fit=crop&ixlib=rb-4.0.3&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D\",\"is_new\":1,\"is_offer\":1,\"is_active\":1,\"properties\":\"{\\\"extras\\\":[{\\\"name\\\":\\\"Pamela Dejesus\\\",\\\"price\\\":100},{\\\"name\\\":\\\"Larissa Barnes\\\",\\\"price\\\":85},{\\\"name\\\":\\\"Kaseem Fitzpatrick\\\",\\\"price\\\":357}],\\\"sauces\\\":[{\\\"name\\\":\\\"Dustin Andrews\\\",\\\"price\\\":40},{\\\"name\\\":\\\"Cheryl Frederick\\\",\\\"price\\\":436},{\\\"name\\\":\\\"Raya Potts\\\",\\\"price\\\":674}],\\\"sizes\\\":[{\\\"size\\\":\\\"Incididunt animi do\\\",\\\"price\\\":336,\\\"extras\\\":[{\\\"name\\\":\\\"Kaseem Fitzpatrick\\\",\\\"price\\\":357}],\\\"sauces\\\":[{\\\"name\\\":\\\"Cheryl Frederick\\\",\\\"price\\\":436},{\\\"name\\\":\\\"Raya Potts\\\",\\\"price\\\":674}],\\\"max_sauces\\\":1},{\\\"size\\\":\\\"Duis voluptas enim e\\\",\\\"price\\\":173,\\\"extras\\\":[{\\\"name\\\":\\\"Larissa Barnes\\\",\\\"price\\\":85}],\\\"sauces\\\":[{\\\"name\\\":\\\"Cheryl Frederick\\\",\\\"price\\\":436}],\\\"max_sauces\\\":4},{\\\"size\\\":\\\"Et voluptatem enim n\\\",\\\"price\\\":969,\\\"extras\\\":[{\\\"name\\\":\\\"Larissa Barnes\\\",\\\"price\\\":85}],\\\"sauces\\\":[{\\\"name\\\":\\\"Cheryl Frederick\\\",\\\"price\\\":436}],\\\"max_sauces\\\":13}]}\",\"created_at\":\"2025-01-07 10:14:48\",\"updated_at\":\"2025-01-07 10:14:48\",\"category_id\":23,\"base_price\":\"0.00\",\"updated_by\":0,\"created_by\":115}'),
(13, 21, 'update', 115, '2025-01-07 10:31:46', '{\"id\":21,\"product_code\":\"In quia non hic reic\",\"name\":\"Mariko Morse\",\"category\":null,\"description\":\"Expedita excepteur c\",\"allergies\":\"Dolor commodo blandi\",\"image_url\":\"https:\\/\\/plus.unsplash.com\\/premium_photo-1664303218668-03fa4e612038?q=80&w=1760&auto=format&fit=crop&ixlib=rb-4.0.3&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D\",\"is_new\":1,\"is_offer\":1,\"is_active\":1,\"properties\":\"{\\\"extras\\\":[{\\\"name\\\":\\\"Pamela Dejesus\\\",\\\"price\\\":100},{\\\"name\\\":\\\"Larissa Barnes\\\",\\\"price\\\":85},{\\\"name\\\":\\\"Kaseem Fitzpatrick\\\",\\\"price\\\":357}],\\\"sauces\\\":[{\\\"name\\\":\\\"Dustin Andrews\\\",\\\"price\\\":40},{\\\"name\\\":\\\"Cheryl Frederick\\\",\\\"price\\\":436},{\\\"name\\\":\\\"Raya Potts\\\",\\\"price\\\":674}],\\\"sizes\\\":[{\\\"size\\\":\\\"Incididunt animi do\\\",\\\"price\\\":336,\\\"extras\\\":[{\\\"name\\\":\\\"Kaseem Fitzpatrick\\\",\\\"price\\\":357}],\\\"sauces\\\":[{\\\"name\\\":\\\"Cheryl Frederick\\\",\\\"price\\\":436},{\\\"name\\\":\\\"Raya Potts\\\",\\\"price\\\":674}],\\\"max_sauces\\\":1},{\\\"size\\\":\\\"Duis voluptas enim e\\\",\\\"price\\\":173,\\\"extras\\\":[{\\\"name\\\":\\\"Larissa Barnes\\\",\\\"price\\\":85}],\\\"sauces\\\":[{\\\"name\\\":\\\"Cheryl Frederick\\\",\\\"price\\\":436}],\\\"max_sauces\\\":4},{\\\"size\\\":\\\"Et voluptatem enim n\\\",\\\"price\\\":969,\\\"extras\\\":[{\\\"name\\\":\\\"Larissa Barnes\\\",\\\"price\\\":85}],\\\"sauces\\\":[{\\\"name\\\":\\\"Cheryl Frederick\\\",\\\"price\\\":436}],\\\"max_sauces\\\":13}]}\",\"created_at\":\"2025-01-07 10:14:48\",\"updated_at\":\"2025-01-07 10:14:48\",\"category_id\":23,\"base_price\":\"0.00\",\"updated_by\":0,\"created_by\":115}', '{\"id\":21,\"product_code\":\"In quia non hic reic\",\"name\":\"Mariko Morse\",\"category\":null,\"description\":\"Expedita excepteur c\",\"allergies\":\"Dolor commodo blandi\",\"image_url\":\"https:\\/\\/plus.unsplash.com\\/premium_photo-1664303218668-03fa4e612038?q=80&w=1760&auto=format&fit=crop&ixlib=rb-4.0.3&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D\",\"is_new\":1,\"is_offer\":1,\"is_active\":1,\"properties\":\"{\\\"extras\\\":[{\\\"name\\\":\\\"Pamela Dejesus\\\",\\\"price\\\":100},{\\\"name\\\":\\\"Larissa Barnes\\\",\\\"price\\\":85},{\\\"name\\\":\\\"Kaseem Fitzpatrick\\\",\\\"price\\\":357}],\\\"sauces\\\":[{\\\"name\\\":\\\"Dustin Andrews\\\",\\\"price\\\":40},{\\\"name\\\":\\\"Cheryl Frederick\\\",\\\"price\\\":436},{\\\"name\\\":\\\"Raya Potts\\\",\\\"price\\\":674}],\\\"sizes\\\":[{\\\"size\\\":\\\"Incididunt animi do\\\",\\\"price\\\":336,\\\"extras\\\":[{\\\"name\\\":\\\"Pamela Dejesus\\\",\\\"price\\\":100},{\\\"name\\\":\\\"Larissa Barnes\\\",\\\"price\\\":85},{\\\"name\\\":\\\"Kaseem Fitzpatrick\\\",\\\"price\\\":357}],\\\"sauces\\\":[{\\\"name\\\":\\\"Dustin Andrews\\\",\\\"price\\\":40},{\\\"name\\\":\\\"Cheryl Frederick\\\",\\\"price\\\":436},{\\\"name\\\":\\\"Raya Potts\\\",\\\"price\\\":674}],\\\"max_sauces\\\":1},{\\\"size\\\":\\\"Duis voluptas enim e\\\",\\\"price\\\":173,\\\"extras\\\":[{\\\"name\\\":\\\"Pamela Dejesus\\\",\\\"price\\\":100},{\\\"name\\\":\\\"Larissa Barnes\\\",\\\"price\\\":85},{\\\"name\\\":\\\"Kaseem Fitzpatrick\\\",\\\"price\\\":357}],\\\"sauces\\\":[{\\\"name\\\":\\\"Dustin Andrews\\\",\\\"price\\\":40},{\\\"name\\\":\\\"Cheryl Frederick\\\",\\\"price\\\":436},{\\\"name\\\":\\\"Raya Potts\\\",\\\"price\\\":674}],\\\"max_sauces\\\":4},{\\\"size\\\":\\\"Et voluptatem enim n\\\",\\\"price\\\":969,\\\"extras\\\":[{\\\"name\\\":\\\"Pamela Dejesus\\\",\\\"price\\\":100},{\\\"name\\\":\\\"Larissa Barnes\\\",\\\"price\\\":85},{\\\"name\\\":\\\"Kaseem Fitzpatrick\\\",\\\"price\\\":357}],\\\"sauces\\\":[{\\\"name\\\":\\\"Dustin Andrews\\\",\\\"price\\\":40},{\\\"name\\\":\\\"Cheryl Frederick\\\",\\\"price\\\":436},{\\\"name\\\":\\\"Raya Potts\\\",\\\"price\\\":674}],\\\"max_sauces\\\":13}]}\",\"created_at\":\"2025-01-07 10:14:48\",\"updated_at\":\"2025-01-07 10:31:46\",\"category_id\":23,\"base_price\":\"0.00\",\"updated_by\":115,\"created_by\":115}'),
(14, 21, 'update', 115, '2025-01-20 00:42:53', '{\"id\":21,\"product_code\":\"Nulla tempore anim\",\"name\":\"Madaline Mcclain\",\"category\":null,\"description\":\"Beatae odio exceptur\",\"allergies\":\"Deserunt omnis ut qu\",\"image_url\":\"https:\\/\\/plus.unsplash.com\\/premium_photo-1664303218668-03fa4e612038?q=80&w=1760&auto=format&fit=crop&ixlib=rb-4.0.3&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D\",\"is_new\":0,\"is_offer\":1,\"is_active\":0,\"properties\":\"{\\\"extras\\\":[{\\\"name\\\":\\\"Quemby King\\\",\\\"price\\\":818,\\\"limit\\\":55},{\\\"name\\\":\\\"Gannon Douglas\\\",\\\"price\\\":395,\\\"limit\\\":19},{\\\"name\\\":\\\"Adria Fernandez\\\",\\\"price\\\":755,\\\"limit\\\":19}],\\\"sauces\\\":[{\\\"name\\\":\\\"Tyrone Cross\\\",\\\"price\\\":467,\\\"limit\\\":36},{\\\"name\\\":\\\"Nola Ewing\\\",\\\"price\\\":365,\\\"limit\\\":61},{\\\"name\\\":\\\"Stephanie James\\\",\\\"price\\\":52,\\\"limit\\\":49}],\\\"sizes\\\":[{\\\"size\\\":\\\"Voluptates incididun\\\",\\\"price\\\":982,\\\"extras\\\":[{\\\"name\\\":\\\"Gannon Douglas\\\",\\\"price\\\":395,\\\"limit\\\":19}],\\\"sauces\\\":[{\\\"name\\\":\\\"Nola Ewing\\\",\\\"price\\\":365,\\\"limit\\\":61},{\\\"name\\\":\\\"Stephanie James\\\",\\\"price\\\":52,\\\"limit\\\":49}],\\\"max_sauces\\\":86},{\\\"size\\\":\\\"Quia aut placeat ve\\\",\\\"price\\\":131,\\\"extras\\\":[{\\\"name\\\":\\\"Gannon Douglas\\\",\\\"price\\\":395,\\\"limit\\\":19},{\\\"name\\\":\\\"Adria Fernandez\\\",\\\"price\\\":755,\\\"limit\\\":19}],\\\"sauces\\\":[{\\\"name\\\":\\\"Nola Ewing\\\",\\\"price\\\":365,\\\"limit\\\":61}],\\\"max_sauces\\\":90},{\\\"size\\\":\\\"Ex cupidatat qui mol\\\",\\\"price\\\":94,\\\"extras\\\":[{\\\"name\\\":\\\"Gannon Douglas\\\",\\\"price\\\":395,\\\"limit\\\":19},{\\\"name\\\":\\\"Adria Fernandez\\\",\\\"price\\\":755,\\\"limit\\\":19}],\\\"sauces\\\":[{\\\"name\\\":\\\"Nola Ewing\\\",\\\"price\\\":365,\\\"limit\\\":61},{\\\"name\\\":\\\"Stephanie James\\\",\\\"price\\\":52,\\\"limit\\\":49}],\\\"max_sauces\\\":15}],\\\"dresses\\\":[]}\",\"created_at\":\"2025-01-07 10:14:48\",\"updated_at\":\"2025-01-19 23:19:03\",\"category_id\":21,\"base_price\":\"0.00\",\"updated_by\":115,\"created_by\":115}', '{\"id\":21,\"product_code\":\"Nulla tempore anim\",\"name\":\"Madaline Mcclain\",\"category\":null,\"description\":\"Beatae odio exceptur\",\"allergies\":\"Deserunt omnis ut qu\",\"image_url\":\"https:\\/\\/plus.unsplash.com\\/premium_photo-1664303218668-03fa4e612038?q=80&w=1760&auto=format&fit=crop&ixlib=rb-4.0.3&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D\",\"is_new\":0,\"is_offer\":1,\"is_active\":1,\"properties\":\"{\\\"extras\\\":[{\\\"name\\\":\\\"Quemby King\\\",\\\"price\\\":818},{\\\"name\\\":\\\"Gannon Douglas\\\",\\\"price\\\":395},{\\\"name\\\":\\\"Adria Fernandez\\\",\\\"price\\\":755}],\\\"sauces\\\":[{\\\"name\\\":\\\"Tyrone Cross\\\",\\\"price\\\":467},{\\\"name\\\":\\\"Nola Ewing\\\",\\\"price\\\":365},{\\\"name\\\":\\\"Stephanie James\\\",\\\"price\\\":52}],\\\"sizes\\\":[{\\\"size\\\":\\\"Voluptates incididun\\\",\\\"price\\\":982,\\\"extras\\\":[],\\\"sauces\\\":[],\\\"max_sauces\\\":86},{\\\"size\\\":\\\"Quia aut placeat ve\\\",\\\"price\\\":131,\\\"extras\\\":[],\\\"sauces\\\":[],\\\"max_sauces\\\":90},{\\\"size\\\":\\\"Ex cupidatat qui mol\\\",\\\"price\\\":94,\\\"extras\\\":[],\\\"sauces\\\":[],\\\"max_sauces\\\":15}]}\",\"created_at\":\"2025-01-07 10:14:48\",\"updated_at\":\"2025-01-20 00:42:53\",\"category_id\":21,\"base_price\":\"0.00\",\"updated_by\":115,\"created_by\":115}'),
(15, 21, 'update', 115, '2025-01-20 01:03:00', '{\"id\":21,\"product_code\":\"Nulla tempore anim\",\"name\":\"Madaline Mcclain\",\"category\":null,\"description\":\"Beatae odio exceptur\",\"allergies\":\"Deserunt omnis ut qu\",\"image_url\":\"https:\\/\\/plus.unsplash.com\\/premium_photo-1664303218668-03fa4e612038?q=80&w=1760&auto=format&fit=crop&ixlib=rb-4.0.3&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D\",\"is_new\":0,\"is_offer\":1,\"is_active\":1,\"properties\":\"{\\\"extras\\\":[{\\\"name\\\":\\\"Quemby King\\\",\\\"price\\\":818},{\\\"name\\\":\\\"Gannon Douglas\\\",\\\"price\\\":395},{\\\"name\\\":\\\"Adria Fernandez\\\",\\\"price\\\":755}],\\\"sauces\\\":[{\\\"name\\\":\\\"Tyrone Cross\\\",\\\"price\\\":467},{\\\"name\\\":\\\"Nola Ewing\\\",\\\"price\\\":365},{\\\"name\\\":\\\"Stephanie James\\\",\\\"price\\\":52}],\\\"sizes\\\":[{\\\"size\\\":\\\"Voluptates incididun\\\",\\\"price\\\":982,\\\"extras\\\":[],\\\"sauces\\\":[],\\\"max_sauces\\\":86},{\\\"size\\\":\\\"Quia aut placeat ve\\\",\\\"price\\\":131,\\\"extras\\\":[],\\\"sauces\\\":[],\\\"max_sauces\\\":90},{\\\"size\\\":\\\"Ex cupidatat qui mol\\\",\\\"price\\\":94,\\\"extras\\\":[],\\\"sauces\\\":[],\\\"max_sauces\\\":15}]}\",\"created_at\":\"2025-01-07 10:14:48\",\"updated_at\":\"2025-01-20 00:42:53\",\"category_id\":21,\"base_price\":\"0.00\",\"updated_by\":115,\"created_by\":115}', '{\"id\":21,\"product_code\":\"Laboriosam eveniet\",\"name\":\"Holmes Mckinney\",\"category\":null,\"description\":\"Aliquam eaque anim r\",\"allergies\":\"Ut aut voluptatum su\",\"image_url\":\"https:\\/\\/plus.unsplash.com\\/premium_photo-1664303218668-03fa4e612038?q=80&w=1760&auto=format&fit=crop&ixlib=rb-4.0.3&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D\",\"is_new\":0,\"is_offer\":1,\"is_active\":1,\"properties\":\"{\\\"base_price\\\":766,\\\"dresses\\\":[]}\",\"created_at\":\"2025-01-07 10:14:48\",\"updated_at\":\"2025-01-20 01:03:00\",\"category_id\":23,\"base_price\":\"0.00\",\"updated_by\":115,\"created_by\":115}'),
(16, 21, 'update', 115, '2025-01-20 01:03:57', '{\"id\":21,\"product_code\":\"Laboriosam eveniet\",\"name\":\"Holmes Mckinney\",\"category\":null,\"description\":\"Aliquam eaque anim r\",\"allergies\":\"Ut aut voluptatum su\",\"image_url\":\"https:\\/\\/plus.unsplash.com\\/premium_photo-1664303218668-03fa4e612038?q=80&w=1760&auto=format&fit=crop&ixlib=rb-4.0.3&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D\",\"is_new\":0,\"is_offer\":1,\"is_active\":1,\"properties\":\"{\\\"base_price\\\":766,\\\"dresses\\\":[]}\",\"created_at\":\"2025-01-07 10:14:48\",\"updated_at\":\"2025-01-20 01:03:00\",\"category_id\":23,\"base_price\":\"0.00\",\"updated_by\":115,\"created_by\":115}', '{\"id\":21,\"product_code\":\"Unde aliquip ad temp\",\"name\":\"Flynn Dyer\",\"category\":null,\"description\":\"Pariatur Placeat d\",\"allergies\":\"Ea consectetur eaqu\",\"image_url\":\"https:\\/\\/plus.unsplash.com\\/premium_photo-1664303218668-03fa4e612038?q=80&w=1760&auto=format&fit=crop&ixlib=rb-4.0.3&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D\",\"is_new\":1,\"is_offer\":0,\"is_active\":1,\"properties\":\"{\\\"extras\\\":[{\\\"name\\\":\\\"Aretha Barton\\\",\\\"price\\\":107},{\\\"name\\\":\\\"Chaim David\\\",\\\"price\\\":969},{\\\"name\\\":\\\"Troy Crosby\\\",\\\"price\\\":609}],\\\"sauces\\\":[{\\\"name\\\":\\\"Sigourney Maynard\\\",\\\"price\\\":104},{\\\"name\\\":\\\"Janna Pratt\\\",\\\"price\\\":224}],\\\"sizes\\\":[{\\\"size\\\":\\\"Hic mollit provident\\\",\\\"price\\\":440,\\\"extras\\\":[{\\\"name\\\":\\\"Chaim David\\\",\\\"price\\\":969},{\\\"name\\\":\\\"Troy Crosby\\\",\\\"price\\\":609}],\\\"sauces\\\":[{\\\"name\\\":\\\"Janna Pratt\\\",\\\"price\\\":224}],\\\"max_sauces\\\":56},{\\\"size\\\":\\\"Fugit qui fuga Pla\\\",\\\"price\\\":914,\\\"extras\\\":[{\\\"name\\\":\\\"Chaim David\\\",\\\"price\\\":969}],\\\"sauces\\\":[{\\\"name\\\":\\\"Janna Pratt\\\",\\\"price\\\":224}],\\\"max_sauces\\\":24}],\\\"dresses\\\":[{\\\"name\\\":\\\"Dolor dolores culpa\\\",\\\"price\\\":267}]}\",\"created_at\":\"2025-01-07 10:14:48\",\"updated_at\":\"2025-01-20 01:03:57\",\"category_id\":20,\"base_price\":\"0.00\",\"updated_by\":115,\"created_by\":115}'),
(17, 21, 'update', 115, '2025-01-20 17:15:06', '{\"id\":21,\"product_code\":\"Unde aliquip ad temp\",\"name\":\"Flynn Dyer\",\"category\":null,\"description\":\"Pariatur Placeat d\",\"allergies\":\"Ea consectetur eaqu\",\"image_url\":\"https:\\/\\/plus.unsplash.com\\/premium_photo-1664303218668-03fa4e612038?q=80&w=1760&auto=format&fit=crop&ixlib=rb-4.0.3&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D\",\"is_new\":1,\"is_offer\":0,\"is_active\":1,\"properties\":\"{\\\"extras\\\":[{\\\"name\\\":\\\"Aretha Barton\\\",\\\"price\\\":107},{\\\"name\\\":\\\"Chaim David\\\",\\\"price\\\":969},{\\\"name\\\":\\\"Troy Crosby\\\",\\\"price\\\":609}],\\\"sauces\\\":[{\\\"name\\\":\\\"Sigourney Maynard\\\",\\\"price\\\":104},{\\\"name\\\":\\\"Janna Pratt\\\",\\\"price\\\":224}],\\\"sizes\\\":[{\\\"size\\\":\\\"Hic mollit provident\\\",\\\"price\\\":440,\\\"extras\\\":[{\\\"name\\\":\\\"Chaim David\\\",\\\"price\\\":969},{\\\"name\\\":\\\"Troy Crosby\\\",\\\"price\\\":609}],\\\"sauces\\\":[{\\\"name\\\":\\\"Janna Pratt\\\",\\\"price\\\":224}],\\\"max_sauces\\\":56},{\\\"size\\\":\\\"Fugit qui fuga Pla\\\",\\\"price\\\":914,\\\"extras\\\":[{\\\"name\\\":\\\"Chaim David\\\",\\\"price\\\":969}],\\\"sauces\\\":[{\\\"name\\\":\\\"Janna Pratt\\\",\\\"price\\\":224}],\\\"max_sauces\\\":24}],\\\"dresses\\\":[{\\\"name\\\":\\\"Dolor dolores culpa\\\",\\\"price\\\":267}]}\",\"created_at\":\"2025-01-07 10:14:48\",\"updated_at\":\"2025-01-20 01:03:57\",\"category_id\":20,\"base_price\":\"0.00\",\"updated_by\":115,\"created_by\":115}', '{\"id\":21,\"product_code\":\"Unde aliquip ad temp\",\"name\":\"Flynn Dyer\",\"category\":null,\"description\":\"Pariatur Placeat d\",\"allergies\":\"Ea consectetur eaqu\",\"image_url\":\"https:\\/\\/plus.unsplash.com\\/premium_photo-1664303218668-03fa4e612038?q=80&w=1760&auto=format&fit=crop&ixlib=rb-4.0.3&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D\",\"is_new\":1,\"is_offer\":0,\"is_active\":1,\"properties\":\"{\\\"base_price\\\":0,\\\"extras\\\":[{\\\"name\\\":\\\"Aretha Barton\\\",\\\"price\\\":107},{\\\"name\\\":\\\"Chaim David\\\",\\\"price\\\":969},{\\\"name\\\":\\\"Troy Crosby\\\",\\\"price\\\":609},{\\\"name\\\":\\\"as\\\",\\\"price\\\":9}],\\\"sauces\\\":[{\\\"name\\\":\\\"Sigourney Maynard\\\",\\\"price\\\":104},{\\\"name\\\":\\\"Janna Pratt\\\",\\\"price\\\":224}],\\\"dresses\\\":[{\\\"name\\\":\\\"Dolor dolores culpa\\\",\\\"price\\\":267}]}\",\"created_at\":\"2025-01-07 10:14:48\",\"updated_at\":\"2025-01-20 17:15:06\",\"category_id\":20,\"base_price\":\"0.00\",\"updated_by\":115,\"created_by\":115}'),
(18, 22, 'insert', 115, '2025-01-20 17:16:13', NULL, '{\"id\":22,\"product_code\":\"Voluptas quo corrupt\",\"name\":\"Cameran Kerr\",\"category\":null,\"description\":\"Magna accusantium au\",\"allergies\":\"Aute nostrud saepe n\",\"image_url\":\"https:\\/\\/images.unsplash.com\\/photo-1721332149274-586f2604884d?q=80&w=1936&auto=format&fit=crop&ixlib=rb-4.0.3&ixid=M3wxMjA3fDF8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D\",\"is_new\":0,\"is_offer\":0,\"is_active\":1,\"properties\":\"{\\\"base_price\\\":17,\\\"extras\\\":[{\\\"name\\\":\\\"Djath\\\",\\\"price\\\":0.5}],\\\"sauces\\\":[],\\\"dresses\\\":[]}\",\"created_at\":\"2025-01-20 17:16:13\",\"updated_at\":\"2025-01-20 17:16:13\",\"category_id\":21,\"base_price\":\"0.00\",\"updated_by\":0,\"created_by\":115}'),
(19, 22, 'update', 115, '2025-01-20 17:28:03', '{\"id\":22,\"product_code\":\"Voluptas quo corrupt\",\"name\":\"Cameran Kerr\",\"category\":null,\"description\":\"Magna accusantium au\",\"allergies\":\"Aute nostrud saepe n\",\"image_url\":\"https:\\/\\/images.unsplash.com\\/photo-1721332149274-586f2604884d?q=80&w=1936&auto=format&fit=crop&ixlib=rb-4.0.3&ixid=M3wxMjA3fDF8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D\",\"is_new\":0,\"is_offer\":0,\"is_active\":1,\"properties\":\"{\\\"base_price\\\":17,\\\"extras\\\":[{\\\"name\\\":\\\"Djath\\\",\\\"price\\\":0.5}],\\\"sauces\\\":[],\\\"dresses\\\":[]}\",\"created_at\":\"2025-01-20 17:16:13\",\"updated_at\":\"2025-01-20 17:16:13\",\"category_id\":21,\"base_price\":\"0.00\",\"updated_by\":0,\"created_by\":115}', '{\"id\":22,\"product_code\":\"Voluptas quo corrupt\",\"name\":\"Cameran Kerr\",\"category\":null,\"description\":\"Magna accusantium au\",\"allergies\":\"Aute nostrud saepe n\",\"image_url\":\"https:\\/\\/images.unsplash.com\\/photo-1721332149274-586f2604884d?q=80&w=1936&auto=format&fit=crop&ixlib=rb-4.0.3&ixid=M3wxMjA3fDF8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D\",\"is_new\":0,\"is_offer\":1,\"is_active\":1,\"properties\":\"{\\\"base_price\\\":17,\\\"extras\\\":[{\\\"name\\\":\\\"Djath\\\",\\\"price\\\":0.5}],\\\"max_extras\\\":0,\\\"sauces\\\":[],\\\"max_sauces\\\":0,\\\"dresses\\\":[],\\\"max_dressings\\\":0}\",\"created_at\":\"2025-01-20 17:16:13\",\"updated_at\":\"2025-01-20 17:28:03\",\"category_id\":21,\"base_price\":\"0.00\",\"updated_by\":115,\"created_by\":115}'),
(20, 23, 'insert', 115, '2025-01-20 20:55:59', NULL, '{\"id\":23,\"product_code\":\"Omnis corporis et te\",\"name\":\"Kato Hodges\",\"category\":null,\"description\":\"Vel irure nihil ut l\",\"allergies\":\"In consectetur tempo\",\"image_url\":\"https:\\/\\/images.unsplash.com\\/photo-1721332149274-586f2604884d?q=80&w=1936&auto=format&fit=crop&ixlib=rb-4.0.3&ixid=M3wxMjA3fDF8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D\",\"is_new\":1,\"is_offer\":1,\"is_active\":0,\"properties\":\"{\\\"base_price\\\":164,\\\"extras\\\":[],\\\"max_extras\\\":0,\\\"sauces\\\":[{\\\"name\\\":\\\"Sigourney Maynard\\\",\\\"price\\\":104}],\\\"max_sauces\\\":0,\\\"dresses\\\":[],\\\"max_dressings\\\":0}\",\"created_at\":\"2025-01-20 20:55:59\",\"updated_at\":\"2025-01-20 20:55:59\",\"category_id\":22,\"base_price\":\"0.00\",\"updated_by\":0,\"created_by\":115}'),
(21, 23, 'update', 115, '2025-01-20 20:56:04', '{\"id\":23,\"product_code\":\"Omnis corporis et te\",\"name\":\"Kato Hodges\",\"category\":null,\"description\":\"Vel irure nihil ut l\",\"allergies\":\"In consectetur tempo\",\"image_url\":\"https:\\/\\/images.unsplash.com\\/photo-1721332149274-586f2604884d?q=80&w=1936&auto=format&fit=crop&ixlib=rb-4.0.3&ixid=M3wxMjA3fDF8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D\",\"is_new\":1,\"is_offer\":1,\"is_active\":0,\"properties\":\"{\\\"base_price\\\":164,\\\"extras\\\":[],\\\"max_extras\\\":0,\\\"sauces\\\":[{\\\"name\\\":\\\"Sigourney Maynard\\\",\\\"price\\\":104}],\\\"max_sauces\\\":0,\\\"dresses\\\":[],\\\"max_dressings\\\":0}\",\"created_at\":\"2025-01-20 20:55:59\",\"updated_at\":\"2025-01-20 20:55:59\",\"category_id\":22,\"base_price\":\"0.00\",\"updated_by\":0,\"created_by\":115}', '{\"id\":23,\"product_code\":\"Omnis corporis et te\",\"name\":\"Kato Hodges\",\"category\":null,\"description\":\"Vel irure nihil ut l\",\"allergies\":\"In consectetur tempo\",\"image_url\":\"https:\\/\\/images.unsplash.com\\/photo-1721332149274-586f2604884d?q=80&w=1936&auto=format&fit=crop&ixlib=rb-4.0.3&ixid=M3wxMjA3fDF8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D\",\"is_new\":1,\"is_offer\":1,\"is_active\":0,\"properties\":\"{\\\"base_price\\\":164,\\\"extras\\\":[],\\\"max_extras\\\":0,\\\"sauces\\\":[{\\\"name\\\":\\\"Sigourney Maynard\\\",\\\"price\\\":104}],\\\"max_sauces\\\":0,\\\"dresses\\\":[],\\\"max_dressings\\\":0}\",\"created_at\":\"2025-01-20 20:55:59\",\"updated_at\":\"2025-01-20 20:56:04\",\"category_id\":22,\"base_price\":\"0.00\",\"updated_by\":115,\"created_by\":115}'),
(22, 23, 'update', 115, '2025-01-20 21:00:38', '{\"id\":23,\"product_code\":\"Omnis corporis et te\",\"name\":\"Kato Hodges\",\"category\":null,\"description\":\"Vel irure nihil ut l\",\"allergies\":\"In consectetur tempo\",\"image_url\":\"https:\\/\\/images.unsplash.com\\/photo-1721332149274-586f2604884d?q=80&w=1936&auto=format&fit=crop&ixlib=rb-4.0.3&ixid=M3wxMjA3fDF8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D\",\"is_new\":1,\"is_offer\":1,\"is_active\":0,\"properties\":\"{\\\"base_price\\\":164,\\\"extras\\\":[],\\\"max_extras\\\":0,\\\"sauces\\\":[{\\\"name\\\":\\\"Sigourney Maynard\\\",\\\"price\\\":104}],\\\"max_sauces\\\":0,\\\"dresses\\\":[],\\\"max_dressings\\\":0}\",\"created_at\":\"2025-01-20 20:55:59\",\"updated_at\":\"2025-01-20 20:56:04\",\"category_id\":22,\"base_price\":\"0.00\",\"updated_by\":115,\"created_by\":115}', '{\"id\":23,\"product_code\":\"Omnis corporis et te\",\"name\":\"Kato Hodges\",\"category\":null,\"description\":\"Vel irure nihil ut l\",\"allergies\":\"In consectetur tempo\",\"image_url\":\"https:\\/\\/images.unsplash.com\\/photo-1721332149274-586f2604884d?q=80&w=1936&auto=format&fit=crop&ixlib=rb-4.0.3&ixid=M3wxMjA3fDF8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D\",\"is_new\":0,\"is_offer\":0,\"is_active\":0,\"properties\":\"{\\\"base_price\\\":164,\\\"extras\\\":[],\\\"max_extras\\\":0,\\\"sauces\\\":[{\\\"name\\\":\\\"Sigourney Maynard\\\",\\\"price\\\":104}],\\\"max_sauces\\\":0,\\\"dresses\\\":[],\\\"max_dressings\\\":0}\",\"created_at\":\"2025-01-20 20:55:59\",\"updated_at\":\"2025-01-20 21:00:38\",\"category_id\":22,\"base_price\":\"0.00\",\"updated_by\":115,\"created_by\":115}'),
(23, 23, 'update', 115, '2025-01-20 21:00:51', '{\"id\":23,\"product_code\":\"Omnis corporis et te\",\"name\":\"Kato Hodges\",\"category\":null,\"description\":\"Vel irure nihil ut l\",\"allergies\":\"In consectetur tempo\",\"image_url\":\"https:\\/\\/images.unsplash.com\\/photo-1721332149274-586f2604884d?q=80&w=1936&auto=format&fit=crop&ixlib=rb-4.0.3&ixid=M3wxMjA3fDF8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D\",\"is_new\":0,\"is_offer\":0,\"is_active\":0,\"properties\":\"{\\\"base_price\\\":164,\\\"extras\\\":[],\\\"max_extras\\\":0,\\\"sauces\\\":[{\\\"name\\\":\\\"Sigourney Maynard\\\",\\\"price\\\":104}],\\\"max_sauces\\\":0,\\\"dresses\\\":[],\\\"max_dressings\\\":0}\",\"created_at\":\"2025-01-20 20:55:59\",\"updated_at\":\"2025-01-20 21:00:38\",\"category_id\":22,\"base_price\":\"0.00\",\"updated_by\":115,\"created_by\":115}', '{\"id\":23,\"product_code\":\"Omnis corporis et te\",\"name\":\"Kato Hodges\",\"category\":null,\"description\":\"Vel irure nihil ut l\",\"allergies\":\"In consectetur tempo\",\"image_url\":\"https:\\/\\/images.unsplash.com\\/photo-1721332149274-586f2604884d?q=80&w=1936&auto=format&fit=crop&ixlib=rb-4.0.3&ixid=M3wxMjA3fDF8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D\",\"is_new\":0,\"is_offer\":0,\"is_active\":0,\"properties\":\"{\\\"base_price\\\":164,\\\"extras\\\":[],\\\"max_extras\\\":23,\\\"sauces\\\":[{\\\"name\\\":\\\"Sigourney Maynard\\\",\\\"price\\\":104}],\\\"max_sauces\\\":0,\\\"dresses\\\":[],\\\"max_dressings\\\":0}\",\"created_at\":\"2025-01-20 20:55:59\",\"updated_at\":\"2025-01-20 21:00:51\",\"category_id\":22,\"base_price\":\"0.00\",\"updated_by\":115,\"created_by\":115}'),
(24, 23, 'update', 115, '2025-01-20 21:39:00', '{\"id\":23,\"product_code\":\"Omnis corporis et te\",\"name\":\"Kato Hodges\",\"category\":null,\"description\":\"Vel irure nihil ut l\",\"allergies\":\"In consectetur tempo\",\"image_url\":\"https:\\/\\/images.unsplash.com\\/photo-1721332149274-586f2604884d?q=80&w=1936&auto=format&fit=crop&ixlib=rb-4.0.3&ixid=M3wxMjA3fDF8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D\",\"is_new\":0,\"is_offer\":0,\"is_active\":0,\"properties\":\"{\\\"base_price\\\":164,\\\"extras\\\":[],\\\"max_extras\\\":23,\\\"sauces\\\":[{\\\"name\\\":\\\"Sigourney Maynard\\\",\\\"price\\\":104}],\\\"max_sauces\\\":0,\\\"dresses\\\":[],\\\"max_dressings\\\":0}\",\"created_at\":\"2025-01-20 20:55:59\",\"updated_at\":\"2025-01-20 21:00:51\",\"category_id\":22,\"base_price\":\"0.00\",\"updated_by\":115,\"created_by\":115}', '{\"id\":23,\"product_code\":\"Neque sunt in error\",\"name\":\"Macon Bates\",\"category\":null,\"description\":\"Anim quaerat quam cu\",\"allergies\":\"Et qui repellendus\",\"image_url\":\"https:\\/\\/images.unsplash.com\\/photo-1721332149274-586f2604884d?q=80&w=1936&auto=format&fit=crop&ixlib=rb-4.0.3&ixid=M3wxMjA3fDF8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D\",\"is_new\":1,\"is_offer\":0,\"is_active\":1,\"properties\":\"{\\\"sizes\\\":[]}\",\"created_at\":\"2025-01-20 20:55:59\",\"updated_at\":\"2025-01-20 21:39:00\",\"category_id\":21,\"base_price\":\"0.00\",\"updated_by\":115,\"created_by\":115}'),
(25, 24, 'insert', 115, '2025-01-20 21:40:14', NULL, '{\"id\":24,\"product_code\":\"Temporibus et non ut\",\"name\":\"Cade Burnett\",\"category\":null,\"description\":\"Enim voluptatum qui\",\"allergies\":\"Natus est officiis o\",\"image_url\":\"https:\\/\\/images.unsplash.com\\/photo-1721332149274-586f2604884d?q=80&w=1936&auto=format&fit=crop&ixlib=rb-4.0.3&ixid=M3wxMjA3fDF8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D\",\"is_new\":1,\"is_offer\":1,\"is_active\":1,\"properties\":\"{\\\"extras\\\":[],\\\"sauces\\\":[],\\\"sizes\\\":[],\\\"dresses\\\":[]}\",\"created_at\":\"2025-01-20 21:40:14\",\"updated_at\":\"2025-01-20 21:40:14\",\"category_id\":23,\"base_price\":\"0.00\",\"updated_by\":0,\"created_by\":115}'),
(26, 21, 'delete', 115, '2025-01-21 20:40:39', '{\"id\":21,\"product_code\":\"Unde aliquip ad temp\",\"name\":\"Flynn Dyer\",\"category\":null,\"description\":\"Pariatur Placeat d\",\"allergies\":\"Ea consectetur eaqu\",\"image_url\":\"https:\\/\\/plus.unsplash.com\\/premium_photo-1664303218668-03fa4e612038?q=80&w=1760&auto=format&fit=crop&ixlib=rb-4.0.3&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D\",\"is_new\":1,\"is_offer\":0,\"is_active\":1,\"properties\":\"{\\\"base_price\\\":0,\\\"extras\\\":[{\\\"name\\\":\\\"Aretha Barton\\\",\\\"price\\\":107},{\\\"name\\\":\\\"Chaim David\\\",\\\"price\\\":969},{\\\"name\\\":\\\"Troy Crosby\\\",\\\"price\\\":609},{\\\"name\\\":\\\"as\\\",\\\"price\\\":9}],\\\"sauces\\\":[{\\\"name\\\":\\\"Sigourney Maynard\\\",\\\"price\\\":104},{\\\"name\\\":\\\"Janna Pratt\\\",\\\"price\\\":224}],\\\"dresses\\\":[{\\\"name\\\":\\\"Dolor dolores culpa\\\",\\\"price\\\":267}]}\",\"created_at\":\"2025-01-07 10:14:48\",\"updated_at\":\"2025-01-20 17:15:06\",\"category_id\":20,\"base_price\":\"0.00\",\"updated_by\":115,\"created_by\":115}', NULL),
(27, 22, 'delete', 115, '2025-01-21 20:40:43', '{\"id\":22,\"product_code\":\"Voluptas quo corrupt\",\"name\":\"Cameran Kerr\",\"category\":null,\"description\":\"Magna accusantium au\",\"allergies\":\"Aute nostrud saepe n\",\"image_url\":\"https:\\/\\/images.unsplash.com\\/photo-1721332149274-586f2604884d?q=80&w=1936&auto=format&fit=crop&ixlib=rb-4.0.3&ixid=M3wxMjA3fDF8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D\",\"is_new\":0,\"is_offer\":1,\"is_active\":1,\"properties\":\"{\\\"base_price\\\":17,\\\"extras\\\":[{\\\"name\\\":\\\"Djath\\\",\\\"price\\\":0.5}],\\\"max_extras\\\":0,\\\"sauces\\\":[],\\\"max_sauces\\\":0,\\\"dresses\\\":[],\\\"max_dressings\\\":0}\",\"created_at\":\"2025-01-20 17:16:13\",\"updated_at\":\"2025-01-20 17:28:03\",\"category_id\":21,\"base_price\":\"0.00\",\"updated_by\":115,\"created_by\":115}', NULL),
(28, 23, 'delete', 115, '2025-01-21 20:40:43', '{\"id\":23,\"product_code\":\"Neque sunt in error\",\"name\":\"Macon Bates\",\"category\":null,\"description\":\"Anim quaerat quam cu\",\"allergies\":\"Et qui repellendus\",\"image_url\":\"https:\\/\\/images.unsplash.com\\/photo-1721332149274-586f2604884d?q=80&w=1936&auto=format&fit=crop&ixlib=rb-4.0.3&ixid=M3wxMjA3fDF8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D\",\"is_new\":1,\"is_offer\":0,\"is_active\":1,\"properties\":\"{\\\"sizes\\\":[]}\",\"created_at\":\"2025-01-20 20:55:59\",\"updated_at\":\"2025-01-20 21:39:00\",\"category_id\":21,\"base_price\":\"0.00\",\"updated_by\":115,\"created_by\":115}', NULL),
(29, 24, 'delete', 115, '2025-01-21 20:40:44', '{\"id\":24,\"product_code\":\"Temporibus et non ut\",\"name\":\"Cade Burnett\",\"category\":null,\"description\":\"Enim voluptatum qui\",\"allergies\":\"Natus est officiis o\",\"image_url\":\"https:\\/\\/images.unsplash.com\\/photo-1721332149274-586f2604884d?q=80&w=1936&auto=format&fit=crop&ixlib=rb-4.0.3&ixid=M3wxMjA3fDF8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D\",\"is_new\":1,\"is_offer\":1,\"is_active\":1,\"properties\":\"{\\\"extras\\\":[],\\\"sauces\\\":[],\\\"sizes\\\":[],\\\"dresses\\\":[]}\",\"created_at\":\"2025-01-20 21:40:14\",\"updated_at\":\"2025-01-20 21:40:14\",\"category_id\":23,\"base_price\":\"0.00\",\"updated_by\":0,\"created_by\":115}', NULL),
(30, 25, 'insert', 115, '2025-01-21 20:57:29', NULL, '{\"id\":25,\"product_code\":\"Illo do commodo veri\",\"name\":\"Lyle Stafford\",\"category\":null,\"description\":\"Ipsum qui maiores e\",\"allergies\":\"Nihil quia dolore ac\",\"image_url\":\"https:\\/\\/images.unsplash.com\\/photo-1721332149274-586f2604884d?q=80&w=1936&auto=format&fit=crop&ixlib=rb-4.0.3&ixid=M3wxMjA3fDF8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D\",\"is_new\":1,\"is_offer\":1,\"is_active\":1,\"properties\":\"{\\\"base_price\\\":134,\\\"extras\\\":[{\\\"name\\\":\\\"Carol Mcintyre\\\",\\\"price\\\":240},{\\\"name\\\":\\\"Rosalyn Powers\\\",\\\"price\\\":648},{\\\"name\\\":\\\"Veda Adkins\\\",\\\"price\\\":542},{\\\"name\\\":\\\"Marshall Christensen\\\",\\\"price\\\":530}],\\\"sauces\\\":[{\\\"name\\\":\\\"Sybil Mays\\\",\\\"price\\\":144},{\\\"name\\\":\\\"Quinn Berger\\\",\\\"price\\\":390},{\\\"name\\\":\\\"Kaden Boyle\\\",\\\"price\\\":404},{\\\"name\\\":\\\"Ramona Nielsen\\\",\\\"price\\\":615}],\\\"max_sauces_base\\\":86,\\\"max_extras_base\\\":80,\\\"dresses\\\":[{\\\"name\\\":\\\"Incididunt doloremqu\\\",\\\"price\\\":171},{\\\"name\\\":\\\"jh\\\",\\\"price\\\":12},{\\\"name\\\":\\\"yui\\\",\\\"price\\\":12}],\\\"max_dresses_base\\\":2}\",\"created_at\":\"2025-01-21 20:57:29\",\"updated_at\":\"2025-01-21 20:57:29\",\"category_id\":25,\"base_price\":\"0.00\",\"updated_by\":0,\"created_by\":115}'),
(31, 25, 'update', 115, '2025-01-21 20:58:01', '{\"id\":25,\"product_code\":\"Illo do commodo veri\",\"name\":\"Lyle Stafford\",\"category\":null,\"description\":\"Ipsum qui maiores e\",\"allergies\":\"Nihil quia dolore ac\",\"image_url\":\"https:\\/\\/images.unsplash.com\\/photo-1721332149274-586f2604884d?q=80&w=1936&auto=format&fit=crop&ixlib=rb-4.0.3&ixid=M3wxMjA3fDF8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D\",\"is_new\":1,\"is_offer\":1,\"is_active\":1,\"properties\":\"{\\\"base_price\\\":134,\\\"extras\\\":[{\\\"name\\\":\\\"Carol Mcintyre\\\",\\\"price\\\":240},{\\\"name\\\":\\\"Rosalyn Powers\\\",\\\"price\\\":648},{\\\"name\\\":\\\"Veda Adkins\\\",\\\"price\\\":542},{\\\"name\\\":\\\"Marshall Christensen\\\",\\\"price\\\":530}],\\\"sauces\\\":[{\\\"name\\\":\\\"Sybil Mays\\\",\\\"price\\\":144},{\\\"name\\\":\\\"Quinn Berger\\\",\\\"price\\\":390},{\\\"name\\\":\\\"Kaden Boyle\\\",\\\"price\\\":404},{\\\"name\\\":\\\"Ramona Nielsen\\\",\\\"price\\\":615}],\\\"max_sauces_base\\\":86,\\\"max_extras_base\\\":80,\\\"dresses\\\":[{\\\"name\\\":\\\"Incididunt doloremqu\\\",\\\"price\\\":171},{\\\"name\\\":\\\"jh\\\",\\\"price\\\":12},{\\\"name\\\":\\\"yui\\\",\\\"price\\\":12}],\\\"max_dresses_base\\\":2}\",\"created_at\":\"2025-01-21 20:57:29\",\"updated_at\":\"2025-01-21 20:57:29\",\"category_id\":25,\"base_price\":\"0.00\",\"updated_by\":0,\"created_by\":115}', '{\"id\":25,\"product_code\":\"Illo do commodo veri\",\"name\":\"Lyle Stafford\",\"category\":null,\"description\":\"Ipsum qui maiores e\",\"allergies\":\"Nihil quia dolore ac\",\"image_url\":\"https:\\/\\/images.unsplash.com\\/photo-1721332149274-586f2604884d?q=80&w=1936&auto=format&fit=crop&ixlib=rb-4.0.3&ixid=M3wxMjA3fDF8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D\",\"is_new\":1,\"is_offer\":1,\"is_active\":1,\"properties\":\"{\\\"base_price\\\":134,\\\"extras\\\":[{\\\"name\\\":\\\"Carol Mcintyre\\\",\\\"price\\\":240},{\\\"name\\\":\\\"Rosalyn Powers\\\",\\\"price\\\":648},{\\\"name\\\":\\\"Veda Adkins\\\",\\\"price\\\":542},{\\\"name\\\":\\\"Marshall Christensen\\\",\\\"price\\\":530}],\\\"sauces\\\":[{\\\"name\\\":\\\"Sybil Mays\\\",\\\"price\\\":144},{\\\"name\\\":\\\"Quinn Berger\\\",\\\"price\\\":390},{\\\"name\\\":\\\"Kaden Boyle\\\",\\\"price\\\":404},{\\\"name\\\":\\\"Ramona Nielsen\\\",\\\"price\\\":615}],\\\"max_sauces_base\\\":86,\\\"max_extras_base\\\":80,\\\"dresses\\\":[{\\\"name\\\":\\\"Incididunt doloremqu\\\",\\\"price\\\":171},{\\\"name\\\":\\\"jh\\\",\\\"price\\\":12},{\\\"name\\\":\\\"yui\\\",\\\"price\\\":12}],\\\"max_dresses_base\\\":2}\",\"created_at\":\"2025-01-21 20:57:29\",\"updated_at\":\"2025-01-21 20:58:01\",\"category_id\":25,\"base_price\":\"0.00\",\"updated_by\":115,\"created_by\":115}'),
(32, 26, 'insert', 115, '2025-01-26 15:46:44', NULL, '{\"id\":26,\"product_code\":\"Perferendis suscipit\",\"name\":\"Wilma Castillo\",\"category\":null,\"description\":\"Ut sed impedit moll\",\"allergies\":\"Ab ad voluptate id c\",\"image_url\":\"https:\\/\\/plus.unsplash.com\\/premium_photo-1704667345105-74ca18ddc2d3?q=80&w=1646&auto=format&fit=crop&ixlib=rb-4.0.3&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D\",\"is_new\":1,\"is_offer\":1,\"is_active\":1,\"properties\":\"{\\\"extras\\\":[{\\\"name\\\":\\\"Jessamine Castro\\\",\\\"price\\\":748},{\\\"name\\\":\\\"Colleen Solomon\\\",\\\"price\\\":210},{\\\"name\\\":\\\"Chaim Morton\\\",\\\"price\\\":753},{\\\"name\\\":\\\"Dylan Blackwell\\\",\\\"price\\\":867},{\\\"name\\\":\\\"Ginger Powers\\\",\\\"price\\\":440},{\\\"name\\\":\\\"James Waters\\\",\\\"price\\\":20},{\\\"name\\\":\\\"Scott Villarreal\\\",\\\"price\\\":341}],\\\"sauces\\\":[{\\\"name\\\":\\\"Aiko Woodward\\\",\\\"price\\\":338},{\\\"name\\\":\\\"Malik Larsen\\\",\\\"price\\\":308},{\\\"name\\\":\\\"Cara Barnes\\\",\\\"price\\\":793},{\\\"name\\\":\\\"Galena Booth\\\",\\\"price\\\":497}],\\\"sizes\\\":[{\\\"size\\\":\\\"Consectetur quae qui\\\",\\\"price\\\":680,\\\"extras\\\":[{\\\"name\\\":\\\"Colleen Solomon\\\",\\\"price\\\":210},{\\\"name\\\":\\\"Dylan Blackwell\\\",\\\"price\\\":867}],\\\"sauces\\\":[{\\\"name\\\":\\\"Cara Barnes\\\",\\\"price\\\":793}],\\\"max_sauces\\\":12},{\\\"size\\\":\\\"Quia nulla explicabo\\\",\\\"price\\\":186,\\\"extras\\\":[{\\\"name\\\":\\\"Colleen Solomon\\\",\\\"price\\\":210}],\\\"sauces\\\":[{\\\"name\\\":\\\"Malik Larsen\\\",\\\"price\\\":308}],\\\"max_sauces\\\":10},{\\\"size\\\":\\\"Non quae et anim eiu\\\",\\\"price\\\":18,\\\"extras\\\":[{\\\"name\\\":\\\"Chaim Morton\\\",\\\"price\\\":753}],\\\"sauces\\\":[{\\\"name\\\":\\\"Malik Larsen\\\",\\\"price\\\":308},{\\\"name\\\":\\\"Cara Barnes\\\",\\\"price\\\":793}],\\\"max_sauces\\\":17}],\\\"dresses\\\":[{\\\"name\\\":\\\"Dolor sint corporis\\\",\\\"price\\\":697},{\\\"name\\\":\\\"Molestiae eaque quas\\\",\\\"price\\\":311},{\\\"name\\\":\\\"Ea quam molestias qu\\\",\\\"price\\\":203},{\\\"name\\\":\\\"Illum in vero perfe\\\",\\\"price\\\":607},{\\\"name\\\":\\\"Ethan Murray\\\",\\\"price\\\":830},{\\\"name\\\":\\\"Keely Wall\\\",\\\"price\\\":202},{\\\"name\\\":\\\"Wang Allen\\\",\\\"price\\\":459}]}\",\"created_at\":\"2025-01-26 15:46:44\",\"updated_at\":\"2025-01-26 15:46:44\",\"category_id\":26,\"base_price\":\"0.00\",\"updated_by\":0,\"created_by\":115}');
INSERT INTO `product_audit` (`id`, `product_id`, `action`, `changed_by`, `changed_at`, `old_values`, `new_values`) VALUES
(33, 26, 'update', 115, '2025-01-26 22:30:12', '{\"id\":26,\"product_code\":\"Perferendis suscipit\",\"name\":\"Wilma Castillo\",\"category\":null,\"description\":\"Ut sed impedit moll\",\"allergies\":\"Ab ad voluptate id c\",\"image_url\":\"https:\\/\\/plus.unsplash.com\\/premium_photo-1704667345105-74ca18ddc2d3?q=80&w=1646&auto=format&fit=crop&ixlib=rb-4.0.3&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D\",\"is_new\":1,\"is_offer\":1,\"is_active\":1,\"properties\":\"{\\\"extras\\\":[{\\\"name\\\":\\\"Jessamine Castro\\\",\\\"price\\\":748},{\\\"name\\\":\\\"Colleen Solomon\\\",\\\"price\\\":210},{\\\"name\\\":\\\"Chaim Morton\\\",\\\"price\\\":753},{\\\"name\\\":\\\"Dylan Blackwell\\\",\\\"price\\\":867},{\\\"name\\\":\\\"Ginger Powers\\\",\\\"price\\\":440},{\\\"name\\\":\\\"James Waters\\\",\\\"price\\\":20},{\\\"name\\\":\\\"Scott Villarreal\\\",\\\"price\\\":341}],\\\"sauces\\\":[{\\\"name\\\":\\\"Aiko Woodward\\\",\\\"price\\\":338},{\\\"name\\\":\\\"Malik Larsen\\\",\\\"price\\\":308},{\\\"name\\\":\\\"Cara Barnes\\\",\\\"price\\\":793},{\\\"name\\\":\\\"Galena Booth\\\",\\\"price\\\":497}],\\\"sizes\\\":[{\\\"size\\\":\\\"Consectetur quae qui\\\",\\\"price\\\":680,\\\"extras\\\":[{\\\"name\\\":\\\"Colleen Solomon\\\",\\\"price\\\":210},{\\\"name\\\":\\\"Dylan Blackwell\\\",\\\"price\\\":867}],\\\"sauces\\\":[{\\\"name\\\":\\\"Cara Barnes\\\",\\\"price\\\":793}],\\\"max_sauces\\\":12},{\\\"size\\\":\\\"Quia nulla explicabo\\\",\\\"price\\\":186,\\\"extras\\\":[{\\\"name\\\":\\\"Colleen Solomon\\\",\\\"price\\\":210}],\\\"sauces\\\":[{\\\"name\\\":\\\"Malik Larsen\\\",\\\"price\\\":308}],\\\"max_sauces\\\":10},{\\\"size\\\":\\\"Non quae et anim eiu\\\",\\\"price\\\":18,\\\"extras\\\":[{\\\"name\\\":\\\"Chaim Morton\\\",\\\"price\\\":753}],\\\"sauces\\\":[{\\\"name\\\":\\\"Malik Larsen\\\",\\\"price\\\":308},{\\\"name\\\":\\\"Cara Barnes\\\",\\\"price\\\":793}],\\\"max_sauces\\\":17}],\\\"dresses\\\":[{\\\"name\\\":\\\"Dolor sint corporis\\\",\\\"price\\\":697},{\\\"name\\\":\\\"Molestiae eaque quas\\\",\\\"price\\\":311},{\\\"name\\\":\\\"Ea quam molestias qu\\\",\\\"price\\\":203},{\\\"name\\\":\\\"Illum in vero perfe\\\",\\\"price\\\":607},{\\\"name\\\":\\\"Ethan Murray\\\",\\\"price\\\":830},{\\\"name\\\":\\\"Keely Wall\\\",\\\"price\\\":202},{\\\"name\\\":\\\"Wang Allen\\\",\\\"price\\\":459}]}\",\"created_at\":\"2025-01-26 15:46:44\",\"updated_at\":\"2025-01-26 15:46:44\",\"category_id\":26,\"base_price\":\"0.00\",\"updated_by\":0,\"created_by\":115}', '{\"id\":26,\"product_code\":\"Perferendis suscipit\",\"name\":\"Wilma Castillo\",\"category\":null,\"description\":\"Ut sed impedit moll\",\"allergies\":\"Ab ad voluptate id c\",\"image_url\":\"uploads\\/product_6796a9641c2119.88502825.jpg\",\"is_new\":1,\"is_offer\":1,\"is_active\":1,\"properties\":\"{\\\"extras\\\":[{\\\"name\\\":\\\"Jessamine Castro\\\",\\\"price\\\":748},{\\\"name\\\":\\\"Colleen Solomon\\\",\\\"price\\\":210},{\\\"name\\\":\\\"Chaim Morton\\\",\\\"price\\\":753},{\\\"name\\\":\\\"Dylan Blackwell\\\",\\\"price\\\":867},{\\\"name\\\":\\\"Ginger Powers\\\",\\\"price\\\":440},{\\\"name\\\":\\\"James Waters\\\",\\\"price\\\":20},{\\\"name\\\":\\\"Scott Villarreal\\\",\\\"price\\\":341}],\\\"sauces\\\":[{\\\"name\\\":\\\"Aiko Woodward\\\",\\\"price\\\":338},{\\\"name\\\":\\\"Malik Larsen\\\",\\\"price\\\":308},{\\\"name\\\":\\\"Cara Barnes\\\",\\\"price\\\":793},{\\\"name\\\":\\\"Galena Booth\\\",\\\"price\\\":497}],\\\"sizes\\\":[{\\\"size\\\":\\\"Consectetur quae qui\\\",\\\"price\\\":680,\\\"extras\\\":[],\\\"sauces\\\":[],\\\"max_sauces\\\":12},{\\\"size\\\":\\\"Quia nulla explicabo\\\",\\\"price\\\":186,\\\"extras\\\":[],\\\"sauces\\\":[],\\\"max_sauces\\\":10},{\\\"size\\\":\\\"Non quae et anim eiu\\\",\\\"price\\\":18,\\\"extras\\\":[],\\\"sauces\\\":[],\\\"max_sauces\\\":17}],\\\"dresses\\\":[{\\\"name\\\":\\\"Dolor sint corporis\\\",\\\"price\\\":697},{\\\"name\\\":\\\"Molestiae eaque quas\\\",\\\"price\\\":311},{\\\"name\\\":\\\"Ea quam molestias qu\\\",\\\"price\\\":203},{\\\"name\\\":\\\"Illum in vero perfe\\\",\\\"price\\\":607},{\\\"name\\\":\\\"Ethan Murray\\\",\\\"price\\\":830},{\\\"name\\\":\\\"Keely Wall\\\",\\\"price\\\":202},{\\\"name\\\":\\\"Wang Allen\\\",\\\"price\\\":459}]}\",\"created_at\":\"2025-01-26 15:46:44\",\"updated_at\":\"2025-01-26 22:30:12\",\"category_id\":26,\"base_price\":\"0.00\",\"updated_by\":115,\"created_by\":115}');

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
-- Table structure for table `product_extras`
--

CREATE TABLE `product_extras` (
  `id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `extra_id` int(11) NOT NULL
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
-- Table structure for table `ratings`
--

CREATE TABLE `ratings` (
  `id` int(11) NOT NULL,
  `full_name` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `anonymous` tinyint(1) DEFAULT 0,
  `rating` int(11) NOT NULL,
  `comments` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ;

--
-- Dumping data for table `ratings`
--

INSERT INTO `ratings` (`id`, `full_name`, `email`, `phone`, `anonymous`, `rating`, `comments`, `created_at`) VALUES
(2, 'Josephine Guzman', 'famojekyvy@mailinator.com', '+1 (164) 424-3887', 0, 5, 'Proident aut placea', '2024-11-11 10:36:39'),
(3, 'Enis Gjini', 'egjini17@gmail.com', '+38349389025', 0, 5, 'asdasd', '2024-11-12 21:20:39'),
(4, 'Aimee Hewitt', 'zytuqof@mailinator.com', '+1 (564) 544-8897', 0, 5, 'Quia quaerat non loradsd', '2024-11-13 10:50:58'),
(5, 'Mira Mcmillan', 'sogef@mailinator.com', '+1 (243) 436-7905', 1, 1, 'Culpa quod sed mini', '2024-11-13 10:51:15'),
(6, 'Leilani Erickson', 'qelare@mailinator.com', '+1 (395) 168-7292', 0, 1, 'Dolor distinctio Vo', '2024-11-16 10:35:14'),
(7, 'Nola Pope', 'bakaxy@mailinator.com', '+1 (878) 735-9293', 1, 5, 'Minus lorem est sed', '2024-11-16 10:35:17'),
(8, 'Jescie Wells', 'zilucomobu@mailinator.com', '+1 (619) 721-3136', 0, 5, 'Aut proident earum', '2024-11-17 21:03:25');

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

--
-- Dumping data for table `reservations`
--

INSERT INTO `reservations` (`id`, `client_name`, `client_email`, `phone_number`, `reservation_source`, `reservation_date`, `reservation_time`, `confirmation_number`, `number_of_people`, `status`, `assigned_to`, `notes`, `updated_at`, `created_at`, `message`) VALUES
(1, 'Martin Hogan', 'zemiwyw@mailinator.com', '', 'Online', '2024-11-12', '14:29:00', NULL, 808, 'No-show', NULL, NULL, '2024-11-12 22:54:16', '2024-11-12 22:40:08', 'Eum deserunt dolorem'),
(2, 'Grace Key', 'lyvovexyq@mailinator.com', '+1 (786) 614-9987', 'Phone', '2024-11-12', '22:52:00', 'lyvovexyq@mailinator.com', 82, 'Confirmed', 117, 'Quia reprehenderit cStatusi u ndryshua në \'Completed\' nga admin.Caktimi u ndryshua te \'admin\'. Assigned to \'wawibimaf\'. Status updated to \'No-show\'. Status updated to \'Confirmed\'. Assigned to \'admin\'. Assigned to \'wawibimaf\'. Assigned to \'admin\'. Assigned to \'wawibimaf\'. [2025-01-16 10:39:15] Assigned to \'enisi\'. ', '2025-01-16 09:39:15', '2024-11-12 22:40:45', 'Pariatur Consequatu'),
(3, 'Lael Mack', 'hezujyf@mailinator.com', '', 'Online', '2024-11-13', '15:59:00', NULL, 148, '', 116, '[2025-01-16 10:38:52] Assigned to \'waiter\'. [2025-01-16 10:51:49] Status aktualisiert auf \'Storniert\'. ', '2025-01-16 09:51:49', '2024-11-13 08:08:38', 'Exercitationem tenet'),
(4, 'Deborah Blevins', 'hucewetuwa@mailinator.com', '', 'Online', '2024-11-13', '09:00:00', NULL, 172, '', 116, '[2025-01-05 15:37:54] Assigned to \'admin\'. [2025-01-16 10:38:50] Assigned to \'admin\'. [2025-01-16 10:56:04] Zugewiesen an \'waiter\'. [2025-01-16 10:56:05] Status aktualisiert auf \'Storniert\'. ', '2025-01-16 09:56:05', '2024-11-13 17:54:42', 'Mollitia tenetur rep'),
(5, 'Roary Glenn', 'qihedehi@mailinator.com', '', 'Online', '2024-11-16', '10:05:00', NULL, 123, 'Pending', NULL, NULL, NULL, '2024-11-16 09:08:37', 'Beatae nulla autem i'),
(6, 'Lawrence Curtis', 'vivo@mailinator.com', '+1 (347) 101-3791', '', '2025-01-16', '12:22:00', 'vivo@mailinator.com', 494, 'Cancelled', 117, 'Dolor ea autem et ac[2025-01-18 22:01:56] Status aktualisiert auf \'Cancelled\'. ', '2025-01-18 21:01:56', '2024-11-16 10:35:10', 'Laborum soluta non q'),
(7, 'Linda Haynes', 'jaxomecaxy@mailinator.com', '049389026', 'Online', '2025-01-17', '11:27:00', '168540D828', 94, 'Confirmed', 116, 'Status updated to \'Completed\'. [2025-01-16 10:22:32] Zuweisung an \'enisi\'. [2025-01-16 10:51:46] Status aktualisiert auf \'Storniert\'. [2025-01-16 10:55:59] Status aktualisiert auf \'Bestätigt\'. [2025-01-16 10:56:02] Zugewiesen an \'waiter\'.[2025-01-16 10:59:38] Status updated to \'Confirmed\'.', '2025-01-16 20:11:23', '2024-11-17 21:03:21', 'Et atque illo conseq');

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

--
-- Dumping data for table `sauces`
--

INSERT INTO `sauces` (`id`, `name`, `price`, `created_at`, `quantity`) VALUES
(1, 'Hayley Pope', 0.50, '2024-11-09 07:44:02', ''),
(2, 'Wyoming Fitzpatrick', 0.30, '2024-11-09 07:44:06', '');

-- --------------------------------------------------------

--
-- Table structure for table `settings`
--

CREATE TABLE `settings` (
  `id` int(11) NOT NULL,
  `key` varchar(100) NOT NULL,
  `store_id` int(11) NOT NULL,
  `value` text NOT NULL,
  `store_lat` varchar(255) DEFAULT NULL,
  `store_lng` varchar(255) DEFAULT NULL,
  `shipping_calculation_mode` varchar(50) DEFAULT NULL,
  `shipping_distance_radius` decimal(10,2) DEFAULT NULL,
  `shipping_fee_base` decimal(10,2) DEFAULT NULL,
  `shipping_fee_per_km` decimal(10,2) DEFAULT NULL,
  `shipping_free_threshold` decimal(10,2) DEFAULT NULL,
  `google_maps_api_key` varchar(255) DEFAULT NULL,
  `postal_code_zones` text DEFAULT NULL,
  `shipping_enable_google_distance_matrix` tinyint(1) DEFAULT 0,
  `shipping_matrix_region` varchar(10) DEFAULT NULL,
  `shipping_matrix_units` varchar(10) DEFAULT 'metric',
  `shipping_weekend_surcharge` decimal(10,2) DEFAULT NULL,
  `shipping_holiday_surcharge` decimal(10,2) DEFAULT NULL,
  `shipping_handling_fee` decimal(10,2) DEFAULT NULL,
  `shipping_vat_percentage` decimal(5,2) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `settings`
--

INSERT INTO `settings` (`id`, `key`, `store_id`, `value`, `store_lat`, `store_lng`, `shipping_calculation_mode`, `shipping_distance_radius`, `shipping_fee_base`, `shipping_fee_per_km`, `shipping_free_threshold`, `google_maps_api_key`, `postal_code_zones`, `shipping_enable_google_distance_matrix`, `shipping_matrix_region`, `shipping_matrix_units`, `shipping_weekend_surcharge`, `shipping_holiday_surcharge`, `shipping_handling_fee`, `shipping_vat_percentage`) VALUES
(1, 'minimum_order', 0, '1.00', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, 'metric', NULL, NULL, NULL, NULL),
(2, 'agb', 0, '<p>Debitis eum voluptat.</p>', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, 'metric', NULL, NULL, NULL, NULL),
(3, 'impressum', 0, '<p>Architecto alias dol.</p>', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, 'metric', NULL, NULL, NULL, NULL),
(4, 'datenschutzerklaerung', 0, '<p>Laboriosam, quidem r.</p>', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, 'metric', NULL, NULL, NULL, NULL),
(5, 'facebook_link', 0, 'https://www.facebook.com/', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, 'metric', NULL, NULL, NULL, NULL),
(6, 'twitter_link', 0, 'https://www.gab.org.au', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, 'metric', NULL, NULL, NULL, NULL),
(7, 'instagram_link', 0, 'https://www.cic.mobi', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, 'metric', NULL, NULL, NULL, NULL),
(8, 'linkedin_link', 0, 'https://www.tib.com.au', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, 'metric', NULL, NULL, NULL, NULL),
(9, 'youtube_link', 0, '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, 'metric', NULL, NULL, NULL, NULL),
(10, 'cart_logo', 0, '../uploads/logos/cart_logo_1732206753.jpg', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, 'metric', NULL, NULL, NULL, NULL),
(11, 'cart_description', 0, '&lt;p&gt;Dolores sunt, cumque.&lt;/p&gt;', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, 'metric', NULL, NULL, NULL, NULL),
(12, 'store_lat', 0, 'Facere do quia dolor', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, 'metric', NULL, NULL, NULL, NULL),
(13, 'store_lng', 0, 'Omnis sapiente nemo', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, 'metric', NULL, NULL, NULL, NULL),
(14, 'shipping_calculation_mode', 0, 'both', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, 'metric', NULL, NULL, NULL, NULL),
(15, 'shipping_distance_radius', 0, '28', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, 'metric', NULL, NULL, NULL, NULL),
(16, 'shipping_fee_base', 0, '75', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, 'metric', NULL, NULL, NULL, NULL),
(17, 'shipping_fee_per_km', 0, '59', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, 'metric', NULL, NULL, NULL, NULL),
(18, 'shipping_free_threshold', 0, '23', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, 'metric', NULL, NULL, NULL, NULL),
(19, 'google_maps_api_key', 0, 'Sunt eius labore qu', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, 'metric', NULL, NULL, NULL, NULL),
(20, 'postal_code_zones', 0, 'In omnis qui esse vo', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, 'metric', NULL, NULL, NULL, NULL),
(21, 'shipping_enable_google_distance_matrix', 0, '0', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, 'metric', NULL, NULL, NULL, NULL),
(22, 'shipping_matrix_region', 0, 'Quia et facilis laud', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, 'metric', NULL, NULL, NULL, NULL),
(23, 'shipping_matrix_units', 0, 'metric', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, 'metric', NULL, NULL, NULL, NULL),
(24, 'shipping_weekend_surcharge', 0, '26', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, 'metric', NULL, NULL, NULL, NULL),
(25, 'shipping_holiday_surcharge', 0, '13', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, 'metric', NULL, NULL, NULL, NULL),
(26, 'shipping_vat_percentage', 0, '78', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, 'metric', NULL, NULL, NULL, NULL),
(27, 'shipping_handling_fee', 0, '57', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, 'metric', NULL, NULL, NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `sizes`
--

CREATE TABLE `sizes` (
  `id` int(11) NOT NULL,
  `name` varchar(50) NOT NULL,
  `description` text DEFAULT NULL,
  `sauce_price_increase` decimal(10,2) NOT NULL DEFAULT 0.00,
  `extra_price_increase` decimal(10,2) NOT NULL DEFAULT 0.00
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `sizes`
--

INSERT INTO `sizes` (`id`, `name`, `description`, `sauce_price_increase`, `extra_price_increase`) VALUES
(1, 'Shad Cline', 'Ipsum dolor consequ', 0.15, 0.15),
(2, 'L', '', 5.00, 5.00),
(3, 'Large', 'Test', 0.00, 0.00),
(4, '30 cm', '', 2.00, 2.00),
(5, 'Jessica Thornton', 'Illo incididunt qui', 793.00, 681.00);

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
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `logo` varchar(255) DEFAULT NULL,
  `cart_logo` varchar(255) DEFAULT NULL,
  `work_schedule` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`work_schedule`)),
  `minimum_order` decimal(10,2) NOT NULL DEFAULT 5.00,
  `agb` text DEFAULT NULL,
  `impressum` text DEFAULT NULL,
  `datenschutzerklaerung` text DEFAULT NULL,
  `facebook_link` varchar(255) DEFAULT NULL,
  `twitter_link` varchar(255) DEFAULT NULL,
  `instagram_link` varchar(255) DEFAULT NULL,
  `linkedin_link` varchar(255) DEFAULT NULL,
  `youtube_link` varchar(255) DEFAULT NULL,
  `cart_description` text DEFAULT NULL,
  `store_lat` decimal(10,6) NOT NULL DEFAULT 41.327500,
  `store_lng` decimal(10,6) NOT NULL DEFAULT 19.818900,
  `delivery_zones` longtext DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `stores`
--

INSERT INTO `stores` (`id`, `name`, `address`, `phone`, `email`, `manager_id`, `is_active`, `logo`, `cart_logo`, `work_schedule`, `minimum_order`, `agb`, `impressum`, `datenschutzerklaerung`, `facebook_link`, `twitter_link`, `instagram_link`, `linkedin_link`, `youtube_link`, `cart_description`, `store_lat`, `store_lng`, `delivery_zones`, `created_at`, `updated_at`) VALUES
(1, 'Leo Fuller', 'Aperiam pariatur Numquam in sint molestiae est ipsam', '+1 (495) 466-9632', 'zypijij@mailinator.com', 115, 1, NULL, NULL, '{\"days\":{\"Monday\":{\"start\":\"21:50\",\"end\":\"00:49\"},\"Tuesday\":{\"start\":\"15:43\",\"end\":\"19:15\"},\"Wednesday\":{\"start\":\"16:03\",\"end\":\"03:31\"},\"Thursday\":{\"start\":\"14:49\",\"end\":\"05:14\"},\"Friday\":{\"start\":\"03:35\",\"end\":\"09:03\"},\"Saturday\":{\"start\":\"04:24\",\"end\":\"10:36\"},\"Sunday\":{\"start\":\"20:12\",\"end\":\"23:34\"}},\"holidays\":[]}', 65.00, 'Quis explicabo Dist', 'Pariatur Sunt eos', 'Eaque minus eum ex m', 'https://www.poqaw.me', 'https://www.zeqisudoju.org.uk', 'https://www.quqesikyzogog.mobi', 'https://www.nom.net', 'https://www.zuharymaxomiluv.cc', '', 0.000000, 0.000000, NULL, '2025-01-23 23:12:39', '2025-01-23 23:12:39'),
(2, 'Christopher Garcia', 'Distinctio Dolor ve', '+1 (163) 159-3565', 'paqehyxub@mailinator.com', 115, 1, 'uploads/logos/logo_1737800118.png', 'uploads/logos/cart_logo_1737800118.png', '{\"days\":{\"Monday\":{\"start\":\"01:19\",\"end\":\"02:46\"},\"Tuesday\":{\"start\":\"23:45\",\"end\":\"17:30\"},\"Wednesday\":{\"start\":\"09:24\",\"end\":\"08:44\"},\"Thursday\":{\"start\":\"23:30\",\"end\":\"06:08\"},\"Friday\":{\"start\":\"07:19\",\"end\":\"04:55\"},\"Saturday\":{\"start\":\"09:00\",\"end\":\"23:59\"},\"Sunday\":{\"start\":\"17:06\",\"end\":\"22:46\"}},\"holidays\":[]}', 6.00, '<p>Sed numquam dolore e.</p>', '<p>Nisi perspiciatis, p.</p>', '<p>Ea magnam ea natus p.</p>', 'https://www.jokyrifipyv.org.au', 'https://www.guzuvegera.net', 'https://www.muvuwomehovyro.ws', 'https://www.huwag.com.au', 'https://www.fowahun.com.au', '<p>Ex ducimus, Nam irur.</p>', 41.326553, 19.807663, '[\n  {\n    \"lat\": 41.36763657160118,\n    \"lng\": 19.885425567626957,\n    \"label\": \"Test\",\n    \"price\": \"15.00\"\n  },\n  {\n    \"lat\": 41.30190050793632,\n    \"lng\": 19.85040664672852,\n    \"label\": \"g\",\n    \"price\": \"1.25\"\n  },\n  {\n    \"lat\": 42.21167937439156,\n    \"lng\": 20.735868215560917,\n    \"label\": \"KOSOVA\",\n    \"price\": \"25.00\"\n  }\n]', '2025-01-25 10:03:54', '2025-01-25 16:59:16'),
(3, 'Rose English', 'Et itaque doloribus', '+1 (286) 654-7663', 'qovev@mailinator.com', 115, 1, NULL, NULL, '{\"days\":{\"Monday\":{\"start\":\"15:26\",\"end\":\"06:32\"},\"Tuesday\":{\"start\":\"07:11\",\"end\":\"23:23\"},\"Wednesday\":{\"start\":\"21:41\",\"end\":\"13:32\"},\"Thursday\":{\"start\":\"16:04\",\"end\":\"00:00\"},\"Friday\":{\"start\":\"17:11\",\"end\":\"18:02\"},\"Saturday\":{\"start\":\"09:00\",\"end\":\"23:59\"},\"Sunday\":{\"start\":\"09:00\",\"end\":\"23:59\"}},\"holidays\":[]}', 20.00, '<p>Nam pariatur? Repreh.</p>', '<p>Rerum blanditiis qui.</p>', '<p>Exercitation distinc.</p>', 'https://www.veqykyz.com', 'https://www.lawepanojejimef.us', 'https://www.zuqiwatyhacuqe.cm', 'https://www.wokikyzuzyvawu.biz', 'https://www.fevamazosyw.org.uk', '<p>Facilis eligendi quo.</p>', 41.325264, 19.810410, '[\r\n  {\r\n    \"lat\": 42.35773208209065,\r\n    \"lng\": 20.844841003417972,\r\n    \"radius\": 25,\r\n    \"label\": \"Zona Kosov\",\r\n    \"price\": 100\r\n  }\r\n]', '2025-01-25 16:57:50', '2025-01-26 18:02:42');

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

--
-- Dumping data for table `tips`
--

INSERT INTO `tips` (`id`, `name`, `percentage`, `amount`, `description`, `is_active`, `created_at`, `updated_at`, `created_by`, `updated_by`) VALUES
(1, 'No Tip', NULL, NULL, 'Select this option if you do not wish to add a tip.', 1, '2024-11-16 12:48:02', '2024-11-16 12:48:02', NULL, NULL),
(2, '5% Tip', 5.00, NULL, 'Add a 5% tip to your order.', 1, '2024-11-16 12:48:02', '2024-11-16 12:48:02', NULL, NULL),
(3, '10% Tip', 10.00, NULL, 'Add a 10% tip to your order.', 1, '2024-11-16 12:48:02', '2024-11-16 12:48:02', NULL, NULL),
(4, 'Fixed Tip 2€', NULL, 2.00, 'Add a fixed tip of 2 euros.', 1, '2024-11-16 12:48:02', '2024-11-16 12:48:02', NULL, NULL),
(5, 'Fixed Tip 5€', NULL, 5.00, 'Add a fixed tip of 5 euros.', 1, '2024-11-16 12:48:02', '2024-11-16 12:48:02', NULL, NULL);

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
  `role` enum('super-admin','admin','waiter','delivery') NOT NULL DEFAULT 'waiter',
  `code` varchar(10) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `username`, `password`, `email`, `created_at`, `role`, `code`, `is_active`) VALUES
(115, 'admin', '$2y$10$CkbEegPOt6eQ.plThXgJse9nBac4hcVzAzhRPNDI4PmUIMU9eXXx.', 'admin@example.com', '2025-01-05 15:47:46', 'admin', NULL, 1),
(116, 'waiter', '$2y$10$/R.A9hsP5qWae1aJZX92veCAp.1zIUm10qc10xJXLwwvko3SgIlyi', 'egjini17@gmail.com', '2025-01-05 15:51:07', 'delivery', NULL, 1),
(117, 'enisi', '$2y$10$5QLh3890yxErxzP2EEq80e2aXF0Ojvuja/YHn3szJGn8mbqt8Mr7S', 'hynyki@mailinator.com', '2025-01-05 15:51:28', 'waiter', 'w-001', 1),
(118, 'kihek', '$2y$10$V2KbkoRrnvlDnxk4kRL56OY41e9o4c2laUuuQODEnfcZS1Y72vq1u', 'daredy@mailinator.com', '2025-01-16 09:16:27', 'super-admin', NULL, 1);

--
-- Indexes for dumped tables
--

--
-- Indexes for table `app_data`
--
ALTER TABLE `app_data`
  ADD PRIMARY KEY (`id`),
  ADD KEY `entity_type` (`entity_type`),
  ADD KEY `key` (`key`);

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
-- Indexes for table `coupons`
--
ALTER TABLE `coupons`
  ADD PRIMARY KEY (`id`);

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
-- Indexes for table `extras_products`
--
ALTER TABLE `extras_products`
  ADD PRIMARY KEY (`id`);

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
-- Indexes for table `orders`
--
ALTER TABLE `orders`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id_idx` (`user_id`),
  ADD KEY `tip_id_idx` (`tip_id`),
  ADD KEY `store_id_idx` (`store_id`),
  ADD KEY `status_id_idx` (`status_id`),
  ADD KEY `delivery_user_id_idx` (`delivery_user_id`);

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
  ADD KEY `order_id` (`order_id`);

--
-- Indexes for table `order_statuses`
--
ALTER TABLE `order_statuses`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `status` (`status`);

--
-- Indexes for table `products`
--
ALTER TABLE `products`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `product_code` (`product_code`);

--
-- Indexes for table `product_audit`
--
ALTER TABLE `product_audit`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `product_category_options`
--
ALTER TABLE `product_category_options`
  ADD PRIMARY KEY (`id`),
  ADD KEY `product_id` (`product_id`),
  ADD KEY `category_option_id` (`category_option_id`),
  ADD KEY `option_value_id` (`option_value_id`);

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
  ADD UNIQUE KEY `name` (`name`),
  ADD UNIQUE KEY `email` (`email`),
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
  ADD UNIQUE KEY `email` (`email`),
  ADD UNIQUE KEY `code` (`code`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `app_data`
--
ALTER TABLE `app_data`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `banners`
--
ALTER TABLE `banners`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `categories`
--
ALTER TABLE `categories`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=27;

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
-- AUTO_INCREMENT for table `coupons`
--
ALTER TABLE `coupons`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `drinks`
--
ALTER TABLE `drinks`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=101;

--
-- AUTO_INCREMENT for table `extras`
--
ALTER TABLE `extras`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `extras_products`
--
ALTER TABLE `extras_products`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `offers`
--
ALTER TABLE `offers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `offer_products`
--
ALTER TABLE `offer_products`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `orders`
--
ALTER TABLE `orders`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

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
-- AUTO_INCREMENT for table `order_statuses`
--
ALTER TABLE `order_statuses`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `products`
--
ALTER TABLE `products`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `product_audit`
--
ALTER TABLE `product_audit`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=34;

--
-- AUTO_INCREMENT for table `product_category_options`
--
ALTER TABLE `product_category_options`
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
-- AUTO_INCREMENT for table `ratings`
--
ALTER TABLE `ratings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `reservations`
--
ALTER TABLE `reservations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `sauces`
--
ALTER TABLE `sauces`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `settings`
--
ALTER TABLE `settings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=29;

--
-- AUTO_INCREMENT for table `sizes`
--
ALTER TABLE `sizes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `stores`
--
ALTER TABLE `stores`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `tips`
--
ALTER TABLE `tips`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=120;

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
  ADD CONSTRAINT `orders_delivery_user_fk` FOREIGN KEY (`delivery_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `orders_store_fk` FOREIGN KEY (`store_id`) REFERENCES `stores` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `orders_tip_fk` FOREIGN KEY (`tip_id`) REFERENCES `tips` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `orders_user_fk` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `order_drinks`
--
ALTER TABLE `order_drinks`
  ADD CONSTRAINT `order_drinks_ibfk_1` FOREIGN KEY (`order_item_id`) REFERENCES `order_items` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `order_drinks_ibfk_2` FOREIGN KEY (`drink_id`) REFERENCES `drinks` (`id`) ON UPDATE CASCADE;

--
-- Constraints for table `order_extras`
--
ALTER TABLE `order_extras`
  ADD CONSTRAINT `order_extras_ibfk_1` FOREIGN KEY (`order_item_id`) REFERENCES `order_items` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `order_extras_ibfk_2` FOREIGN KEY (`extra_id`) REFERENCES `extras` (`id`) ON UPDATE CASCADE;

--
-- Constraints for table `order_items`
--
ALTER TABLE `order_items`
  ADD CONSTRAINT `order_items_ibfk_1` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `product_category_options`
--
ALTER TABLE `product_category_options`
  ADD CONSTRAINT `product_category_options_ibfk_1` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `product_category_options_ibfk_2` FOREIGN KEY (`category_option_id`) REFERENCES `category_options` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `product_category_options_ibfk_3` FOREIGN KEY (`option_value_id`) REFERENCES `category_option_values` (`id`) ON DELETE SET NULL;

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
-- Constraints for table `reservations`
--
ALTER TABLE `reservations`
  ADD CONSTRAINT `reservations_ibfk_1` FOREIGN KEY (`assigned_to`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `stores`
--
ALTER TABLE `stores`
  ADD CONSTRAINT `stores_ibfk_1` FOREIGN KEY (`manager_id`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
