-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Sep 07, 2025 at 01:16 PM
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

--
-- Dumping data for table `employees`
--

INSERT INTO `employees` (`EmployeeID`, `FullName`, `Username`, `Password`, `Role`, `DateJoined`) VALUES
(1, 'Admin User', 'admin', '$2y$10$SRaI4J8k6U0J.ER0RdXbCeQwWE1kyupXy.kI4jK1X1sYojQGIVQPm', 'admin', '2025-06-02 11:21:40'),
(2, 'Regular User', 'user', '$2y$10$SRaI4J8k6U0J.ER0RdXbCeQwWE1kyupXy.kI4jK1X1sYojQGIVQPm', 'user', '2025-06-02 11:21:40'),
(3, 'مدير النظام', 'manager', '$2y$10$SRaI4J8k6U0J.ER0RdXbCeQwWE1kyupXy.kI4jK1X1sYojQGIVQPm', 'manager', '2025-07-22 14:11:53');

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
  `unit_design` varchar(255) DEFAULT NULL,
  `finishing_type_id` int(11) DEFAULT NULL COMMENT 'FK to PropertyFinishingTypes',
  `compound_area` decimal(12,2) DEFAULT NULL,
  `down_payment_percentage` decimal(5,2) DEFAULT NULL,
  `payment_years` decimal(5,2) NOT NULL,
  `delivery_date` date DEFAULT NULL,
  `ProjectName` varchar(255) DEFAULT NULL,
  `min_area` decimal(10,2) DEFAULT NULL,
  `max_area` decimal(10,2) DEFAULT NULL,
  `avg_area` decimal(10,2) DEFAULT NULL,
  `district` varchar(100) DEFAULT NULL,
  `governorate` varchar(100) DEFAULT NULL,
  `company_name` varchar(150) DEFAULT NULL,
  `FloorsCount` int(11) DEFAULT NULL,
  `year_built` year(4) DEFAULT NULL,
  `unit_floor` varchar(50) DEFAULT NULL,
  `net_area` decimal(10,2) DEFAULT NULL,
  `loading_percentage` decimal(5,2) DEFAULT NULL,
  `price_cash` decimal(15,2) DEFAULT NULL,
  `price_installments` decimal(15,2) DEFAULT NULL,
  `meter_price_cash` decimal(10,2) DEFAULT NULL,
  `meter_price_installments` decimal(10,2) DEFAULT NULL,
  `seller_type` enum('مالك','وسيط','وكيل','بواب','شركة مطورة','شركة تشطيب') DEFAULT NULL,
  `seller_name` varchar(255) DEFAULT NULL,
  `seller_phone` varchar(50) DEFAULT NULL,
  `sales_manager_name` varchar(255) DEFAULT NULL,
  `sales_manager_phone` varchar(50) DEFAULT NULL,
  `salesperson_name` varchar(255) DEFAULT NULL,
  `salesperson_phone` varchar(50) DEFAULT NULL,
  `latitude` decimal(10,8) DEFAULT NULL,
  `longitude` decimal(11,8) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `properties`
--

INSERT INTO `properties` (`PropertyID`, `PropertyTypeID`, `Title`, `Description`, `Location`, `Area`, `Bedrooms`, `Status`, `listing_type`, `DateAdded`, `EmployeeID`, `unit_design`, `finishing_type_id`, `compound_area`, `down_payment_percentage`, `payment_years`, `delivery_date`, `ProjectName`, `min_area`, `max_area`, `avg_area`, `district`, `governorate`, `company_name`, `FloorsCount`, `year_built`, `unit_floor`, `net_area`, `loading_percentage`, `price_cash`, `price_installments`, `meter_price_cash`, `meter_price_installments`, `seller_type`, `seller_name`, `seller_phone`, `sales_manager_name`, `sales_manager_phone`, `salesperson_name`, `salesperson_phone`, `latitude`, `longitude`) VALUES
(69, 1, 'شقة 3 غرف في مصر الجديدة', 'شقة واسعة بالبرج الرابع قريب من الخدمات والمدارس.', 'شارع عثمان بن عفان، مصر الجديدة', 120.00, NULL, 'available', 'Resale', '2025-09-06 12:38:11', 1, 'Classic', 1, NULL, NULL, 0.00, NULL, 'مبنى الحرية', NULL, NULL, NULL, 'مصر الجديدة', 'القاهرة', NULL, 10, '2010', '4', 118.00, NULL, 2500000.00, NULL, 20833.33, NULL, 'مالك', 'محمود علي', '01001234567', NULL, NULL, NULL, NULL, 30.06263000, 31.31248000),
(70, 2, 'فيلا على البحر في الساحل الشمالي', 'فيلا فاخرة على البحر بإطلالة مباشرة ومسبح خاص.', 'الساحل الشمالي - كورنيش البحر', 450.00, NULL, 'available', 'Primary', '2025-09-06 12:38:11', 1, 'Modern', 2, 1000.00, 10.00, 5.00, NULL, 'لازوردي', NULL, NULL, NULL, 'الساحل الشمالي', 'الإسكندرية', 'شركة البحر الأزرق', 2, '2023', NULL, 440.00, 5.00, 18000000.00, 20000000.00, 40000.00, 45454.54, 'شركة مطورة', 'شركة البحر الأزرق', '01209990000', 'مريم', '01205551234', 'أحمد', '01119998877', 31.20010000, 29.91870000),
(71, 3, 'محل تجاري في مول بالقاهرة الجديدة', 'محل واجهة رئيسية في مول تجاري، مناسب للمتاجر والمطاعم.', 'القاهرة الجديدة - التسعين', 60.00, NULL, 'available', 'Resale', '2025-09-06 12:38:11', 1, 'Retail', 1, NULL, NULL, 0.00, NULL, NULL, NULL, NULL, NULL, 'التسعين', 'القاهرة', NULL, 1, '2015', 'Ground', 60.00, NULL, 1200000.00, NULL, 20000.00, NULL, 'وسيط', 'شركة تسويق', '01002223344', NULL, NULL, NULL, NULL, 30.04440000, 31.23570000),
(72, 4, 'مبنى إداري للإيجار بالعاصمة الإدارية', 'مكاتب حديثة بمساحات مرنة في منطقة الأعمال بالعاصمة الإدارية.', 'العاصمة الإدارية - المنطقة المالية', 300.00, NULL, 'available', 'Primary', '2025-09-06 12:38:11', 1, 'Office Suite', 2, NULL, NULL, 0.00, NULL, NULL, NULL, NULL, NULL, 'المنطقة المالية', 'القاهرة', NULL, 8, '2020', NULL, 300.00, NULL, 12000000.00, NULL, 40000.00, NULL, 'مالك', 'مطور محلي', '01005556677', NULL, NULL, NULL, NULL, 30.01310000, 31.21060000),
(73, 5, 'عيادة طبية للبيع في المهندسين', 'عيادة مجهزة بالقرب من المستشفيات ومراكز التشخيص.', 'المهندسين - شارع السودان', 80.00, NULL, 'available', 'Resale', '2025-09-06 12:38:11', 1, 'Clinic', 1, NULL, NULL, 0.00, NULL, NULL, NULL, NULL, NULL, 'المهندسين', 'القاهرة', NULL, 1, '2005', '2', 78.00, NULL, 2200000.00, NULL, 27500.00, NULL, 'وكيل', 'مكتب العقار', '01112223344', NULL, NULL, NULL, NULL, 30.04420000, 31.22330000),
(74, 1, 'شقة 3 غرف في مصر الجديدة', 'شقة واسعة بالبرج الرابع قريب من الخدمات والمدارس.', 'شارع عثمان بن عفان، مصر الجديدة', 120.00, NULL, 'available', 'Resale', '2025-09-06 12:38:32', 1, 'Classic', 1, NULL, NULL, 0.00, NULL, 'مبنى الحرية', NULL, NULL, NULL, 'مصر الجديدة', 'القاهرة', NULL, 10, '2010', '4', 118.00, NULL, 2500000.00, NULL, 20833.33, NULL, 'مالك', 'محمود علي', '01001234567', NULL, NULL, NULL, NULL, 30.06263000, 31.31248000),
(75, 2, 'فيلا على البحر في الساحل الشمالي', 'فيلا فاخرة على البحر بإطلالة مباشرة ومسبح خاص.', 'الساحل الشمالي - كورنيش البحر', 450.00, NULL, 'available', 'Primary', '2025-09-06 12:38:32', 1, 'Modern', 2, 1000.00, 10.00, 5.00, NULL, 'لازوردي', NULL, NULL, NULL, 'الساحل الشمالي', 'الإسكندرية', 'شركة البحر الأزرق', 2, '2023', NULL, 440.00, 5.00, 18000000.00, 20000000.00, 40000.00, 45454.54, 'شركة مطورة', 'شركة البحر الأزرق', '01209990000', 'مريم', '01205551234', 'أحمد', '01119998877', 31.20010000, 29.91870000),
(76, 3, 'محل تجاري في مول بالقاهرة الجديدة', 'محل واجهة رئيسية في مول تجاري، مناسب للمتاجر والمطاعم.', 'القاهرة الجديدة - التسعين', 60.00, NULL, 'available', 'Resale', '2025-09-06 12:38:32', 1, 'Retail', 1, NULL, NULL, 0.00, NULL, NULL, NULL, NULL, NULL, 'التسعين', 'القاهرة', NULL, 1, '2015', 'Ground', 60.00, NULL, 1200000.00, NULL, 20000.00, NULL, 'وسيط', 'شركة تسويق', '01002223344', NULL, NULL, NULL, NULL, 30.04440000, 31.23570000),
(78, 5, 'عيادة طبية للبيع في المهندسين', 'عيادة مجهزة بالقرب من المستشفيات ومراكز التشخيص.', 'المهندسين - شارع السودان', 80.00, NULL, 'available', 'Resale', '2025-09-06 12:38:33', 1, 'Clinic', 1, NULL, NULL, 0.00, NULL, NULL, NULL, NULL, NULL, 'المهندسين', 'القاهرة', NULL, 1, '2005', '2', 78.00, NULL, 2200000.00, NULL, 27500.00, NULL, 'وكيل', 'مكتب العقار', '01112223344', NULL, NULL, NULL, NULL, 30.04420000, 31.22330000),
(98, 4, '', '', '', NULL, NULL, 'available', 'Primary', '2025-09-06 16:44:59', 3, '', NULL, NULL, NULL, 0.00, NULL, '', NULL, NULL, NULL, 'مدينة نصر شرق', 'ghhgh', '', NULL, NULL, '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '', '', '', '', '', '', NULL, NULL),
(99, 4, '', '', '', NULL, NULL, 'available', 'Primary', '2025-09-06 16:46:09', 3, '', NULL, NULL, NULL, 0.00, NULL, '', NULL, NULL, NULL, 'ghhghg', 'cddc', '', NULL, NULL, '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '', '', '', '', '', '', NULL, NULL),
(100, 4, '', '', '', NULL, NULL, 'available', 'Primary', '2025-09-06 17:05:18', 3, '', NULL, NULL, NULL, 0.00, NULL, '', NULL, NULL, NULL, '6 أكتوبر', 'ghhgh', '', NULL, NULL, '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '', '', '', '', '', '', NULL, NULL),
(102, 1, 'شقة فاخرة في فيصل', 'شقة فاخرة في فيصل', 'العشريني', 200.00, NULL, 'available', 'Resale', '2025-09-07 09:05:04', 1, '3 غرف + 2 حمام', 5, NULL, 15.00, 5.00, '2025-10-01', '', NULL, NULL, NULL, 'فيصل', 'الجيزة', '', 20, '2020', 'الدور 12', 180.00, 90.00, 5000000.00, 6000000.00, 50000.00, 60000.00, 'وسيط', 'محمد علي', '01029292929', '', '', '', '', 29.98028874, 31.20997766);

-- --------------------------------------------------------

--
-- Table structure for table `propertyfinishingtypes`
--

CREATE TABLE `propertyfinishingtypes` (
  `FinishingID` int(11) NOT NULL,
  `FinishingName` varchar(100) NOT NULL,
  `FinishingName_ar` varchar(100) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `propertyfinishingtypes`
--

INSERT INTO `propertyfinishingtypes` (`FinishingID`, `FinishingName`, `FinishingName_ar`) VALUES
(1, 'Red Brick', 'طوب أحمر'),
(2, 'Semi Finished', 'نصف تشطيب'),
(3, 'Plastered & Framed', 'محارة وحلوق'),
(4, 'Lux', 'لوكس'),
(5, 'Super Lux', 'سوبر لوكس'),
(6, 'Ultra Super Lux', 'الترا سوبر لوكس'),
(7, 'Smart', 'سمارت'),
(8, 'Hotel Finish', 'فندقي');

-- --------------------------------------------------------

--
-- Table structure for table `propertytypes`
--

CREATE TABLE `propertytypes` (
  `PropertyTypeID` int(11) NOT NULL,
  `TypeName` varchar(100) NOT NULL COMMENT 'Main categories: سكني, ساحلي, إداري, تجاري, فندقي, طبي',
  `TypeName_ar` varchar(100) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `propertytypes`
--

INSERT INTO `propertytypes` (`PropertyTypeID`, `TypeName`, `TypeName_ar`) VALUES
(1, 'سكني', NULL),
(2, 'ساحلي', NULL),
(3, 'إداري', NULL),
(4, 'تجاري', NULL),
(5, 'فندقي', NULL),
(6, 'طبي', NULL);

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

--
-- Dumping data for table `property_audio_notes`
--

INSERT INTO `property_audio_notes` (`note_id`, `property_id`, `file_path`, `upload_date`, `duration`) VALUES
(27, 69, 'uploads/audio/property_69_placeholder.mp3', '2025-09-06 12:38:11', NULL),
(28, 70, 'uploads/audio/property_70_placeholder.mp3', '2025-09-06 12:38:11', NULL),
(29, 71, 'uploads/audio/property_71_placeholder.mp3', '2025-09-06 12:38:11', NULL),
(30, 72, 'uploads/audio/property_72_placeholder.mp3', '2025-09-06 12:38:11', NULL),
(31, 73, 'uploads/audio/property_73_placeholder.mp3', '2025-09-06 12:38:11', NULL),
(32, 74, 'uploads/audio/property_74_placeholder.mp3', '2025-09-06 12:38:32', NULL),
(33, 75, 'uploads/audio/property_75_placeholder.mp3', '2025-09-06 12:38:32', NULL),
(34, 76, 'uploads/audio/property_76_placeholder.mp3', '2025-09-06 12:38:32', NULL),
(36, 78, 'uploads/audio/property_78_placeholder.mp3', '2025-09-06 12:38:33', NULL),
(38, 102, 'uploads/audio/property_102_audio_41865cb8c8e6dd14.ogg', '2025-09-07 09:05:05', NULL);

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
-- Dumping data for table `property_images`
--

INSERT INTO `property_images` (`image_id`, `property_id`, `file_path`, `upload_date`, `is_primary`) VALUES
(41, 69, 'uploads/images/property_69_placeholder.jpg', '2025-09-06 12:38:11', 1),
(42, 70, 'uploads/images/property_70_placeholder.jpg', '2025-09-06 12:38:11', 1),
(43, 71, 'uploads/images/property_71_placeholder.jpg', '2025-09-06 12:38:11', 1),
(44, 72, 'uploads/images/property_72_placeholder.jpg', '2025-09-06 12:38:11', 1),
(45, 73, 'uploads/images/property_73_placeholder.jpg', '2025-09-06 12:38:11', 1),
(46, 74, 'uploads/images/property_74_placeholder.jpg', '2025-09-06 12:38:32', 1),
(47, 75, 'uploads/images/property_75_placeholder.jpg', '2025-09-06 12:38:32', 1),
(48, 76, 'uploads/images/property_76_placeholder.jpg', '2025-09-06 12:38:32', 1),
(50, 78, 'uploads/images/property_78_placeholder.jpg', '2025-09-06 12:38:33', 1),
(52, 102, 'uploads/images/property_102_f3f2d90a97a3da4b.jpg', '2025-09-07 09:05:05', 1),
(53, 102, 'uploads/images/property_102_953cf8f2a4b18022.jpg', '2025-09-07 09:05:05', 0);

-- --------------------------------------------------------

--
-- Table structure for table `property_videos`
--

CREATE TABLE `property_videos` (
  `VideoID` int(11) NOT NULL,
  `PropertyID` int(11) NOT NULL,
  `VideoPath` varchar(500) NOT NULL,
  `VideoName` varchar(255) NOT NULL,
  `VideoSize` int(11) NOT NULL,
  `UploadDate` timestamp NOT NULL DEFAULT current_timestamp(),
  `EmployeeID` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `property_videos`
--

INSERT INTO `property_videos` (`VideoID`, `PropertyID`, `VideoPath`, `VideoName`, `VideoSize`, `UploadDate`, `EmployeeID`) VALUES
(20, 69, 'uploads/videos/property_69_placeholder.mp4', 'property_69_placeholder.mp4', 11, '2025-09-06 12:38:11', 1),
(21, 70, 'uploads/videos/property_70_placeholder.mp4', 'property_70_placeholder.mp4', 11, '2025-09-06 12:38:11', 1),
(22, 71, 'uploads/videos/property_71_placeholder.mp4', 'property_71_placeholder.mp4', 11, '2025-09-06 12:38:11', 1),
(23, 72, 'uploads/videos/property_72_placeholder.mp4', 'property_72_placeholder.mp4', 11, '2025-09-06 12:38:11', 1),
(24, 73, 'uploads/videos/property_73_placeholder.mp4', 'property_73_placeholder.mp4', 11, '2025-09-06 12:38:11', 1),
(25, 74, 'uploads/videos/property_74_placeholder.mp4', 'property_74_placeholder.mp4', 11, '2025-09-06 12:38:32', 1),
(26, 75, 'uploads/videos/property_75_placeholder.mp4', 'property_75_placeholder.mp4', 11, '2025-09-06 12:38:32', 1),
(27, 76, 'uploads/videos/property_76_placeholder.mp4', 'property_76_placeholder.mp4', 11, '2025-09-06 12:38:32', 1),
(29, 78, 'uploads/videos/property_78_placeholder.mp4', 'property_78_placeholder.mp4', 11, '2025-09-06 12:38:33', 1),
(31, 102, 'uploads/videos/property_102_video_e4d0329ce029f1b7.mp4', 'Zack Fox - dummy_2.mp4', 8240590, '2025-09-07 09:05:05', 1);

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
-- Indexes for table `property_videos`
--
ALTER TABLE `property_videos`
  ADD PRIMARY KEY (`VideoID`),
  ADD KEY `PropertyID` (`PropertyID`),
  ADD KEY `EmployeeID` (`EmployeeID`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `employees`
--
ALTER TABLE `employees`
  MODIFY `EmployeeID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `properties`
--
ALTER TABLE `properties`
  MODIFY `PropertyID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=103;

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
-- AUTO_INCREMENT for table `property_audio_notes`
--
ALTER TABLE `property_audio_notes`
  MODIFY `note_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=39;

--
-- AUTO_INCREMENT for table `property_images`
--
ALTER TABLE `property_images`
  MODIFY `image_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=54;

--
-- AUTO_INCREMENT for table `property_videos`
--
ALTER TABLE `property_videos`
  MODIFY `VideoID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=32;

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
-- Constraints for table `property_videos`
--
ALTER TABLE `property_videos`
  ADD CONSTRAINT `property_videos_ibfk_1` FOREIGN KEY (`PropertyID`) REFERENCES `properties` (`PropertyID`) ON DELETE CASCADE,
  ADD CONSTRAINT `property_videos_ibfk_2` FOREIGN KEY (`EmployeeID`) REFERENCES `employees` (`EmployeeID`) ON DELETE SET NULL;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
