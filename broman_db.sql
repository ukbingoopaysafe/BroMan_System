-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Jan 15, 2025 at 12:00 PM
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
-- Database: `broman_db`
--

-- --------------------------------------------------------

--
-- Table structure for table `employees`
--

CREATE TABLE `employees` (
  `EmployeeID` int(11) NOT NULL,
  `FullName` varchar(255) NOT NULL,
  `Username` varchar(50) NOT NULL,
  `Password` varchar(255) NOT NULL COMMENT 'Stored using PHP password_hash (bcrypt)',
  `Role` enum('manager','admin','user') NOT NULL DEFAULT 'user',
  `DateJoined` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `properties`
--

CREATE TABLE `properties` (
  `PropertyID` int(11) NOT NULL,
  `PropertyTypeID` int(11) NOT NULL COMMENT 'FK to PropertyTypes',
  `Title` varchar(255) NOT NULL,
  `Description` text DEFAULT NULL,
  `Location` varchar(255) DEFAULT NULL,
  `Area` decimal(10,2) DEFAULT NULL,
  `Bedrooms` int(11) DEFAULT NULL,
  `Status` enum('available','sold','rented','unavailable') DEFAULT 'available',
  `listing_type` enum('Primary','Resale') NOT NULL DEFAULT 'Primary',
  `DateAdded` timestamp NULL DEFAULT current_timestamp(),
  `EmployeeID` int(11) DEFAULT NULL COMMENT 'FK to Employees who added it',
  `unit_design` varchar(255) NULL COMMENT 'Unit design as text field',
  `finishing_type_id` int(11) DEFAULT NULL COMMENT 'FK to PropertyFinishingTypes',
  `compound_area` decimal(12,2) DEFAULT NULL,
  `down_payment_percentage` decimal(5,2) DEFAULT NULL,
  `payment_years` int(11) DEFAULT NULL,
  `delivery_date` date DEFAULT NULL,
  `ProjectName` varchar(255) DEFAULT NULL,
  `min_area` decimal(10,2) DEFAULT NULL,
  `max_area` decimal(10,2) DEFAULT NULL,
  `avg_area` decimal(10,2) DEFAULT NULL,
  `district` varchar(100) DEFAULT NULL,
  `governorate` varchar(100) DEFAULT NULL,
  `company_name` varchar(150) DEFAULT NULL,
  `FloorsCount` int(11) DEFAULT NULL,
  `sales_manager_name` varchar(255) DEFAULT NULL,
  `sales_manager_phone` varchar(50) DEFAULT NULL,
  `salesperson_name` varchar(255) DEFAULT NULL,
  `salesperson_phone` varchar(50) DEFAULT NULL,
  `latitude` decimal(10, 7) NULL,
  `longitude` decimal(10, 7) NULL,
  `year_built` year(4) NULL,
  `unit_floor` varchar(100) NULL,
  `net_area` decimal(10,2) NULL,
  `loading_percentage` decimal(5,2) NULL,
  `seller_type` enum('مالك','وسيط','وكيل','بواب','شركة مطورة','شركة تشطيب') NULL,
  `seller_name` varchar(255) NULL,
  `seller_phone` varchar(50) NULL,
  `price_cash` decimal(15,2) NULL,
  `price_installments` decimal(15,2) NULL,
  `meter_price_cash` decimal(15,2) NULL,
  `meter_price_installments` decimal(15,2) NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `propertyfinishingtypes`
--

CREATE TABLE `propertyfinishingtypes` (
  `FinishingID` int(11) NOT NULL,
  `FinishingName` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `propertytypes`
--

CREATE TABLE `propertytypes` (
  `PropertyTypeID` int(11) NOT NULL,
  `TypeName` varchar(100) NOT NULL COMMENT 'Main categories: سكني, ساحلي, إداري, تجاري, فندقي, طبي'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `property_videos`
--

CREATE TABLE `property_videos` (
  `video_id` int(11) NOT NULL,
  `property_id` int(11) NOT NULL,
  `file_path` varchar(255) NOT NULL,
  `upload_date` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `property_audio_notes`
--

CREATE TABLE `property_audio_notes` (
  `note_id` int(11) NOT NULL,
  `property_id` int(11) NOT NULL,
  `file_path` varchar(255) NOT NULL,
  `upload_date` timestamp NOT NULL DEFAULT current_timestamp(),
  `duration` int(11) DEFAULT NULL COMMENT 'Duration in seconds'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `property_images`
--

CREATE TABLE `property_images` (
  `image_id` int(11) NOT NULL,
  `property_id` int(11) NOT NULL,
  `file_path` varchar(255) NOT NULL,
  `upload_date` timestamp NOT NULL DEFAULT current_timestamp(),
  `is_primary` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `employees`
--

INSERT INTO `employees` (`EmployeeID`, `FullName`, `Username`, `Password`, `Role`, `DateJoined`) VALUES
(1, 'Admin User', 'admin', '$2y$10$SRaI4J8k6U0J.ER0RdXbCeQwWE1kyupXy.kI4jK1X1sYojQGIVQPm', 'admin', '2025-06-02 11:21:40'),
(2, 'Regular User', 'user', '$2y$10$SRaI4J8k6U0J.ER0RdXbCeQwWE1kyupXy.kI4jK1X1sYojQGIVQPm', 'user', '2025-06-02 11:21:40'),
(3, 'مدير النظام', 'manager', '$2y$10$SRaI4J8k6U0J.ER0RdXbCeQwWE1kyupXy.kI4jK1X1sYojQGIVQPm', 'manager', '2025-07-22 14:11:53');

--
-- Dumping data for table `propertytypes`
--

INSERT INTO `propertytypes` (`PropertyTypeID`, `TypeName`) VALUES
(1, 'سكني'),
(2, 'ساحلي'),
(3, 'إداري'),
(4, 'تجاري'),
(5, 'فندقي'),
(6, 'طبي');

--
-- Dumping data for table `propertyfinishingtypes`
--

INSERT INTO `propertyfinishingtypes` (`FinishingID`, `FinishingName`) VALUES
(1, 'طوب أحمر'),
(2, 'نصف تشطيب'),
(3, 'محارة وحلوق'),
(4, 'لوكس'),
(5, 'سوبر لوكس'),
(6, 'الترا سوبر لوكس'),
(7, 'سمارت'),
(8, 'فندقي');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `employees`
--
ALTER TABLE `employees`
  ADD PRIMARY KEY (`EmployeeID`),
  ADD UNIQUE KEY `Username` (`Username`);

--
-- Indexes for table `properties`
--
ALTER TABLE `properties`
  ADD PRIMARY KEY (`PropertyID`),
  ADD KEY `FK_PropertyType` (`PropertyTypeID`),
  ADD KEY `FK_EmployeeAdded` (`EmployeeID`),
  ADD KEY `FK_FinishingType` (`finishing_type_id`);

--
-- Indexes for table `propertyfinishingtypes`
--
ALTER TABLE `propertyfinishingtypes`
  ADD PRIMARY KEY (`FinishingID`),
  ADD UNIQUE KEY `FinishingName` (`FinishingName`);

--
-- Indexes for table `propertytypes`
--
ALTER TABLE `propertytypes`
  ADD PRIMARY KEY (`PropertyTypeID`),
  ADD UNIQUE KEY `TypeName` (`TypeName`);

--
-- Indexes for table `property_videos`
--
ALTER TABLE `property_videos`
  ADD PRIMARY KEY (`video_id`),
  ADD KEY `property_id` (`property_id`);

--
-- Indexes for table `property_audio_notes`
--
ALTER TABLE `property_audio_notes`
  ADD PRIMARY KEY (`note_id`),
  ADD KEY `property_id` (`property_id`);

--
-- Indexes for table `property_images`
--
ALTER TABLE `property_images`
  ADD PRIMARY KEY (`image_id`),
  ADD KEY `property_id` (`property_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `employees`
--
ALTER TABLE `employees`
  MODIFY `EmployeeID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `properties`
--
ALTER TABLE `properties`
  MODIFY `PropertyID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1;

--
-- AUTO_INCREMENT for table `propertyfinishingtypes`
--
ALTER TABLE `propertyfinishingtypes`
  MODIFY `FinishingID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `propertytypes`
--
ALTER TABLE `propertytypes`
  MODIFY `PropertyTypeID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `property_videos`
--
ALTER TABLE `property_videos`
  MODIFY `video_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `property_audio_notes`
--
ALTER TABLE `property_audio_notes`
  MODIFY `note_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `property_images`
--
ALTER TABLE `property_images`
  MODIFY `image_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `properties`
--
ALTER TABLE `properties`
  ADD CONSTRAINT `FK_EmployeeAdded` FOREIGN KEY (`EmployeeID`) REFERENCES `employees` (`EmployeeID`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `FK_FinishingType` FOREIGN KEY (`finishing_type_id`) REFERENCES `propertyfinishingtypes` (`FinishingID`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `FK_PropertyType` FOREIGN KEY (`PropertyTypeID`) REFERENCES `propertytypes` (`PropertyTypeID`) ON UPDATE CASCADE;

--
-- Constraints for table `property_videos`
--
ALTER TABLE `property_videos`
  ADD CONSTRAINT `property_videos_ibfk_1` FOREIGN KEY (`property_id`) REFERENCES `properties` (`PropertyID`) ON DELETE CASCADE;

--
-- Constraints for table `property_audio_notes`
--
ALTER TABLE `property_audio_notes`
  ADD CONSTRAINT `property_audio_notes_ibfk_1` FOREIGN KEY (`property_id`) REFERENCES `properties` (`PropertyID`) ON DELETE CASCADE;

--
-- Constraints for table `property_images`
--
ALTER TABLE `property_images`
  ADD CONSTRAINT `property_images_ibfk_1` FOREIGN KEY (`property_id`) REFERENCES `properties` (`PropertyID`) ON DELETE CASCADE;
--
-- Dumping data for table `properties`
--

INSERT INTO `properties` (`PropertyID`, `PropertyTypeID`, `Title`, `Description`, `Location`, `Area`, `Bedrooms`, `Status`, `listing_type`, `DateAdded`, `EmployeeID`, `unit_design`, `finishing_type_id`, `compound_area`, `down_payment_percentage`, `payment_years`, `delivery_date`, `ProjectName`, `min_area`, `max_area`, `avg_area`, `district`, `governorate`, `company_name`, `FloorsCount`, `sales_manager_name`, `sales_manager_phone`, `salesperson_name`, `salesperson_phone`, `latitude`, `longitude`, `year_built`, `unit_floor`, `net_area`, `loading_percentage`, `seller_type`, `seller_name`, `seller_phone`, `price_cash`, `price_installments`, `meter_price_cash`, `meter_price_installments`) VALUES
(1, 1, 'شقة فاخرة بإطلالة مميزة', 'شقة 3 غرف نوم مع تشطيب كامل وإطلالة على الحديقة المركزية', 'التجمع الخامس, القاهرة الجديدة', 180.00, 3, 'available', 'Primary', '2025-01-15 10:30:00', 1, '3 غرف نوم + صالة + 2 حمام', 4, 50000.00, 10.00, 7, '2025-12-31', 'كمبوند ماونتن فيو', 160.00, 200.00, 180.00, 'التجمع الخامس', 'القاهرة', 'إعمار مصر', 5, 'أحمد محمود', '01012345678', 'سارة أحمد', '01087654321', 30.0123, 31.4567, 2024, 'الطابق الثالث', 165.00, 8.33, 'شركة مطورة', 'إعمار مصر', '01012345678', 2500000.00, 2800000.00, 13888.89, 15555.56),
(2, 1, 'فيلا مستقلة بحديقة خاصة', 'فيلا 5 غرف نوم مع حديقة خاصة وحمام سباحة', 'الشيخ زايد, الجيزة', 450.00, 5, 'available', 'Primary', '2025-01-15 11:00:00', 1, 'فيلا مستقلة', 5, 100000.00, 15.00, 10, '2026-06-30', 'زايد ديونز', 400.00, 500.00, 450.00, 'الشيخ زايد', 'الجيزة', 'سوديك', 2, 'محمد علي', '01023456789', 'فاطمة حسن', '01076543210', 30.0234, 31.1234, 2025, 'أرضي + أول', 420.00, 6.67, 'شركة مطورة', 'سوديك', '01023456789', 7000000.00, 8000000.00, 15555.56, 17777.78),
(3, 1, 'دوبلكس أنيق في كمبوند راقي', 'دوبلكس 4 غرف نوم مع تشطيب فاخر', 'مدينة نصر, القاهرة', 280.00, 4, 'available', 'Primary', '2025-01-15 11:30:00', 2, 'دوبلكس', 5, 75000.00, 12.00, 8, '2025-09-15', 'جرين هيلز', 250.00, 300.00, 280.00, 'مدينة نصر', 'القاهرة', 'مجموعة طلعت مصطفى', 3, 'علي محمد', '01034567890', 'نورا سعيد', '01065432109', 30.0345, 31.2345, 2024, 'أول + ثاني', 260.00, 7.14, 'شركة مطورة', 'مجموعة طلعت مصطفى', '01034567890', 4200000.00, 4800000.00, 15000.00, 17142.86),
(4, 1, 'شقة مميزة في برج سكني', 'شقة 2 غرف نوم مع إطلالة على النيل', 'المعادي, القاهرة', 120.00, 2, 'sold', 'Primary', '2025-01-15 12:00:00', 1, '2 غرف نوم + صالة + حمام', 3, NULL, 20.00, 5, '2024-08-01', 'ريفر فيو تاورز', 110.00, 130.00, 120.00, 'المعادي', 'القاهرة', 'إعمار مصر', 12, 'كريم أحمد', '01045678901', 'ليلى محمود', '01054321098', 29.9567, 31.3456, 2023, 'الطابق العاشر', 115.00, 4.17, 'مالك', 'أحمد السيد', '01045678901', 1800000.00, 2000000.00, 15000.00, 16666.67),
(5, 1, 'فيلا كلاسيكية في ضاحية هادئة', 'فيلا 6 غرف نوم مع حديقة واسعة', '6 أكتوبر, الجيزة', 550.00, 6, 'available', 'Resale', '2025-01-15 12:30:00', 2, 'فيلا مستقلة', 1, 120000.00, NULL, NULL, NULL, 'أكتوبر بارك', NULL, NULL, NULL, '6 أكتوبر', 'الجيزة', 'سوديك', 2, 'حسن علي', '01056789012', 'رانيا أحمد', '01043210987', 30.0678, 30.4567, 2020, 'أرضي + أول + ثاني', 520.00, 5.45, 'مالك', 'محمد حسن', '01056789012', 8500000.00, NULL, 15454.55, NULL);

COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;