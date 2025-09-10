-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Sep 10, 2025 at 11:50 AM
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
(3, 'Hassan Alkhiat', 'hassan', '$2y$10$Ztb0vDStuc1/bupbQD.eDejskm5nGJ4bPaKCqQPE7/GfnorgN7.T.', 'manager', '2025-07-22 14:11:53'),
(7, 'Fatma', 'fatma', '$2y$10$VYjzVB/G/QSioK3SsnyT8Oyq6LLVIn3tQXGowc7Kw.wCpmE7fazey', 'admin', '2025-09-10 09:23:49');

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
  `payment_years` decimal(5,2) DEFAULT NULL,
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
(1, 1, 'شقه استثماري فندقي', '1- البيع شامل العفش والاجهزه\r\n2-الدور الاخير والسطح معزول عزل حراري ومائي\r\n3-الوحده قبله للستجيل العقاري\r\n4-البيع كاش فقط والسعر شامل وديعه الصيانه\r\n5-يوجد حصه بالجراج بسعر منفصل\r\n6- بيدفع عموله 1.5 % فقط دون نقاشات', '74 شارع مصدق', 120.00, NULL, 'available', 'Resale', '2025-09-10 09:35:06', 7, 'غرفتين +2حمام +ريسيبشن قطعتين+بلكونه+مطبخ امريكاني', 8, NULL, NULL, NULL, '2025-10-01', '', NULL, NULL, NULL, 'مصدق', 'الجيزه', 'شركه المصريه لاداره الاصول العقاريه', 12, NULL, '12', NULL, NULL, 5300000.00, NULL, NULL, NULL, 'شركة تشطيب', 'ابراهيم دياب', '01094450056', 'ابراهيم دياب', '01094450056', '', '', 30.03839891, 31.20164515),
(2, 1, 'شقه سمارت دوبلكس', '1- عداد كهرباء كارت\r\n2-متاح 2 اسانسير\r\n3- مسجله شهر عقاري ولها حصه بالارض\r\n4-بتتاجر 60 الف شهريا\r\n5- البيع شامل العفش\r\n6-الشقه سمارت تعمل AI بالذكاء الاصطناعي', 'و16 ميدان المساحه البرج اللي في اورنج', 210.00, NULL, 'available', 'Resale', '2025-09-10 09:39:01', 7, '3 غرف + 3 حمام+ريسبشن 3 قطع +4 بلكونات', 7, NULL, NULL, NULL, NULL, '', NULL, NULL, NULL, 'المساحه', 'الجيزه', '', 10, NULL, '5', 190.00, NULL, 15000000.00, NULL, NULL, NULL, 'مالك', 'ابانوب', '1094450056', '', '', '', '', 30.03604450, 31.21457969),
(3, 3, 'مكتب', '1-صحه توقيع ولها حصه بالارض\r\n2- رخصه اداري\r\n3- البيع بالفعش', 'امام نادى الصيد و بجوار السفارة العراقية و بجوار كل البنوك و التوكيلات', 260.00, NULL, 'available', 'Resale', '2025-09-10 09:42:00', 7, NULL, 5, NULL, NULL, NULL, NULL, '', NULL, NULL, NULL, 'ميدان الثوره', 'الجيزه', '', NULL, NULL, '3', NULL, NULL, 9000000.00, NULL, NULL, NULL, '', '', '', '', '', '', '', 30.04846481, 31.20415613);

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
  MODIFY `EmployeeID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `properties`
--
ALTER TABLE `properties`
  MODIFY `PropertyID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

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
  MODIFY `VideoID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=50;

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
