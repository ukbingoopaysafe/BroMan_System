-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Jul 10, 2025 at 03:05 PM
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
-- Table structure for table `clients`
--

CREATE TABLE `clients` (
  `ClientID` int(11) NOT NULL,
  `FullName` varchar(255) NOT NULL,
  `Email` varchar(255) DEFAULT NULL,
  `Phone` varchar(50) DEFAULT NULL,
  `DateRegistered` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `clients`
--

INSERT INTO `clients` (`ClientID`, `FullName`, `Email`, `Phone`, `DateRegistered`) VALUES
(1, 'Ahmed Ali', 'ahmed.ali@example.com', '01012345678', '2025-06-02 11:21:40'),
(2, 'Fatima Hassan', 'fatima.hassan@example.com', '01198765432', '2025-06-02 11:21:40'),
(3, 'Mohamed Saad', 'mohamed.saad@example.com', '01211223344', '2025-06-02 11:21:40');

-- --------------------------------------------------------

--
-- Table structure for table `employees`
--

CREATE TABLE `employees` (
  `EmployeeID` int(11) NOT NULL,
  `FullName` varchar(255) NOT NULL,
  `Username` varchar(50) NOT NULL,
  `Password` varchar(255) NOT NULL COMMENT 'Stored using PHP password_hash (bcrypt)',
  `Role` enum('admin','user') NOT NULL DEFAULT 'user',
  `DateJoined` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `employees`
--

INSERT INTO `employees` (`EmployeeID`, `FullName`, `Username`, `Password`, `Role`, `DateJoined`) VALUES
(1, 'Admin User', 'admin', '$2b$10$y28UH0xkAQdz2jTaLWQnb.bhSiUu8YSN54k9rsuhdkApec5JKWCZq', 'admin', '2025-06-02 11:21:40'),
(2, 'Regular User', 'user', '$2b$10$y28UH0xkAQdz2jTaLWQnb.bhSiUu8YSN54k9rsuhdkApec5JKWCZq', 'user', '2025-06-02 11:21:40');

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
  `Price` decimal(15,2) DEFAULT NULL,
  `Area` decimal(10,2) DEFAULT NULL,
  `Bedrooms` int(11) DEFAULT NULL,
  `Bathrooms` int(11) DEFAULT NULL,
  `Status` enum('available','sold','rented','unavailable') DEFAULT 'available',
  `DateAdded` timestamp NULL DEFAULT current_timestamp(),
  `EmployeeID` int(11) DEFAULT NULL COMMENT 'FK to Employees who added it',
  `ClientID` int(11) DEFAULT NULL,
  `unit_design_id` int(11) DEFAULT NULL COMMENT 'FK to PropertyUnitDesigns',
  `finishing_type_id` int(11) DEFAULT NULL COMMENT 'FK to PropertyFinishingTypes',
  `compound_area` decimal(12,2) DEFAULT NULL,
  `down_payment_percentage` decimal(5,2) DEFAULT NULL,
  `payment_years` int(11) DEFAULT NULL,
  `delivery_date` date DEFAULT NULL,
  `ProjectName` varchar(255) DEFAULT NULL,
  `min_price` decimal(15,2) DEFAULT NULL,
  `max_price` decimal(15,2) DEFAULT NULL,
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
  `salesperson_phone` varchar(50) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `properties`
--

INSERT INTO `properties` (`PropertyID`, `PropertyTypeID`, `Title`, `Description`, `Location`, `Price`, `Area`, `Bedrooms`, `Bathrooms`, `Status`, `DateAdded`, `EmployeeID`, `ClientID`, `unit_design_id`, `finishing_type_id`, `compound_area`, `down_payment_percentage`, `payment_years`, `delivery_date`, `ProjectName`, `min_price`, `max_price`, `min_area`, `max_area`, `avg_area`, `district`, `governorate`, `company_name`, `FloorsCount`, `sales_manager_name`, `sales_manager_phone`, `salesperson_name`, `salesperson_phone`) VALUES
(1, 1, 'شقة فاخرة بالتجمع الخامس', 'شقة 3 غرف نوم بإطلالة مميزة في كمبوند XYZ', 'التجمع الخامس, القاهرة الجديدة', 2500000.00, 180.00, 3, 2, 'available', '2025-06-02 11:21:41', 1, 2, 1, 3, 50000.00, 10.00, 7, '2025-12-31', 'كمبوند XY', NULL, NULL, NULL, NULL, NULL, 'التجمع الخامس', 'القاهرة', 'شركة التطوير الحديث', 5, 'احمد', '01111132239', 'حسن', '01211132239'),
(2, 1, 'فيلا مستقلة بالشيخ زايد', 'فيلا بحديقة خاصة وحمام سباحة', 'الشيخ زايد, الجيزة', 7000000.00, 450.00, 5, 4, 'available', '2025-06-02 11:21:41', 1, NULL, 2, NULL, 100000.00, 15.00, 10, '2026-06-30', 'زايد ديونز', NULL, NULL, 400.00, 500.00, 450.00, 'الشيخ زايد', 'الجيزة', 'شركة إعمار', 2, NULL, NULL, NULL, NULL),
(3, 2, 'شاليه بالساحل الشمالي', 'شاليه يرى البحر مباشرة في قرية ABC', 'الكيلو 120, الساحل الشمالي', 1800000.00, 120.00, 2, 1, 'available', '2025-06-02 11:21:41', 2, NULL, 4, 3, NULL, 20.00, 5, '2024-08-01', 'قرية ABC', 1700000.00, 1900000.00, 110.00, 130.00, 120.00, 'سيدي عبد الرحمن', 'مطروح', 'مجموعة طلعت مصطفى', 3, '', '', '', ''),
(4, 4, 'محل تجاري بمول العرب', 'محل بموقع متميز في مول العرب', 'مول العرب, 6 أكتوبر', 4000000.00, 80.00, NULL, NULL, 'available', '2025-06-02 11:21:41', 1, NULL, NULL, 1, NULL, 25.00, 5, NULL, 'مول العرب', NULL, NULL, NULL, NULL, 80.00, 'الحي المتميز', 'الجيزة', 'مجموعة الفطيم', 1, NULL, NULL, NULL, NULL),
(5, 3, 'مكتب إداري بالعاصمة الإدارية', 'مكتب إداري متشطب بالتكييفات في برج DEF', 'الحي المالي, العاصمة الإدارية الجديدة', 1500000.00, 95.00, NULL, NULL, 'available', '2025-06-02 11:21:41', 1, NULL, 1, 3, NULL, 10.00, 8, '2025-01-15', 'برج DEF', 1400000.00, 1600000.00, 90.00, 100.00, 95.00, 'الحي المالي', 'القاهرة', 'شركة العاصمة', 15, '', '', '', ''),
(6, 6, 'عيادة طبية بمدينة نصر', 'عيادة مجهزة بالكامل في مركز طبي شهير', 'شارع الطيران, مدينة نصر', 1200000.00, 70.00, NULL, NULL, 'rented', '2025-06-02 11:21:41', 2, NULL, 3, 3, NULL, NULL, NULL, NULL, 'المركز الطبي الدولي', NULL, NULL, NULL, NULL, NULL, 'مدينة نصر شرق', 'القاهرة', '', 8, '', '', '', ''),
(10, 5, 'hfhfhf', '', 'grggrrgr', 3333333.00, 333.00, NULL, NULL, 'available', '2025-06-16 09:07:27', NULL, NULL, NULL, 3, NULL, NULL, 3, NULL, 'جراند اوتيل 2', NULL, NULL, NULL, NULL, NULL, 'rgrgrgr', 'hrrgrg', 'fhfhfhfg', 22, '', '', '', ''),
(11, 1, 'سبسببس', '', 'يبييبيب', 33333333.00, 333.00, NULL, 4, 'available', '2025-06-16 09:13:15', 1, NULL, 5, 2, 4444.00, NULL, 3, NULL, 'يبسبسيب', NULL, NULL, NULL, NULL, NULL, 'سبسببسي', 'بسبسب', 'سبببب', 2, '', '', '', ''),
(12, 1, 'سبسببس', '', 'يبييبيب', 33333333.00, 333.00, NULL, 4, 'available', '2025-06-16 09:19:01', 1, NULL, 5, 2, 4444.00, NULL, 3, NULL, 'يبسبسيب', NULL, NULL, NULL, NULL, NULL, 'سبسببسي', 'بسبسب', 'سبببب', 2, '', '', '', ''),
(13, 1, 'سبسببس', '', 'يبييبيب', 33333333.00, 333.00, NULL, 4, 'available', '2025-06-16 09:19:09', 1, NULL, 5, 2, 4444.00, NULL, 3, NULL, 'يبسبسيبيي', NULL, NULL, NULL, NULL, NULL, 'سبسببسي', 'بسبسب', 'سبببب', 2, '', '', '', '');

-- --------------------------------------------------------

--
-- Table structure for table `propertyfinishingtypes`
--

CREATE TABLE `propertyfinishingtypes` (
  `FinishingID` int(11) NOT NULL,
  `FinishingName` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `propertyfinishingtypes`
--

INSERT INTO `propertyfinishingtypes` (`FinishingID`, `FinishingName`) VALUES
(3, 'تشطيب كامل'),
(1, 'طوب أحمر'),
(2, 'نصف تشطيب');

-- --------------------------------------------------------

--
-- Table structure for table `propertytypes`
--

CREATE TABLE `propertytypes` (
  `PropertyTypeID` int(11) NOT NULL,
  `TypeName` varchar(100) NOT NULL COMMENT 'Main categories: سكني, ساحلي, إداري, تجاري, فندقي, طبي'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `propertytypes`
--

INSERT INTO `propertytypes` (`PropertyTypeID`, `TypeName`) VALUES
(3, 'إداري'),
(4, 'تجاري'),
(2, 'ساحلي'),
(1, 'سكني'),
(6, 'طبي'),
(5, 'فندقي');

-- --------------------------------------------------------

--
-- Table structure for table `propertyunitdesigns`
--

CREATE TABLE `propertyunitdesigns` (
  `DesignID` int(11) NOT NULL,
  `DesignName` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `propertyunitdesigns`
--

INSERT INTO `propertyunitdesigns` (`DesignID`, `DesignName`) VALUES
(4, '3 غرف'),
(1, 'استوديو'),
(2, 'غرفة'),
(3, 'غرفتين'),
(5, 'فيلا تاون هاوس'),
(6, 'فيلا توين هاوس'),
(7, 'فيلا مستقلة');

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
(1, 1, 'uploads/audio/property_1_audio_686f92c052da1.mp3', '2025-07-10 10:15:28', NULL),
(4, 5, 'uploads/audio/property_5_audio_686fb799122b1.m4a', '2025-07-10 12:52:41', NULL);

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
(1, 1, 'uploads/images/property_1_686f905b9ed17.jpg', '2025-07-10 10:05:15', 1),
(4, 1, 'uploads/images/property_1_686f905bd072f.jpg', '2025-07-10 10:05:15', 0),
(7, 3, 'uploads/images/property_3_686f98c04f816.jpeg', '2025-07-10 10:41:04', 1),
(8, 1, 'uploads/images/property_1_686fae82e7891.jpeg', '2025-07-10 12:13:54', 0),
(9, 5, 'uploads/images/property_5_686fafcc92d31.jpeg', '2025-07-10 12:19:24', 1);

--
-- Indexes for dumped tables
--

--
-- Indexes for table `clients`
--
ALTER TABLE `clients`
  ADD PRIMARY KEY (`ClientID`),
  ADD UNIQUE KEY `Email` (`Email`);

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
  ADD KEY `FK_ClientOwner` (`ClientID`),
  ADD KEY `FK_UnitDesign` (`unit_design_id`),
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
-- Indexes for table `propertyunitdesigns`
--
ALTER TABLE `propertyunitdesigns`
  ADD PRIMARY KEY (`DesignID`),
  ADD UNIQUE KEY `DesignName` (`DesignName`);

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
-- AUTO_INCREMENT for table `clients`
--
ALTER TABLE `clients`
  MODIFY `ClientID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `employees`
--
ALTER TABLE `employees`
  MODIFY `EmployeeID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `properties`
--
ALTER TABLE `properties`
  MODIFY `PropertyID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT for table `propertyfinishingtypes`
--
ALTER TABLE `propertyfinishingtypes`
  MODIFY `FinishingID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `propertytypes`
--
ALTER TABLE `propertytypes`
  MODIFY `PropertyTypeID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `propertyunitdesigns`
--
ALTER TABLE `propertyunitdesigns`
  MODIFY `DesignID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `property_audio_notes`
--
ALTER TABLE `property_audio_notes`
  MODIFY `note_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `property_images`
--
ALTER TABLE `property_images`
  MODIFY `image_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `properties`
--
ALTER TABLE `properties`
  ADD CONSTRAINT `FK_ClientOwner` FOREIGN KEY (`ClientID`) REFERENCES `clients` (`ClientID`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `FK_EmployeeAdded` FOREIGN KEY (`EmployeeID`) REFERENCES `employees` (`EmployeeID`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `FK_FinishingType` FOREIGN KEY (`finishing_type_id`) REFERENCES `propertyfinishingtypes` (`FinishingID`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `FK_PropertyType` FOREIGN KEY (`PropertyTypeID`) REFERENCES `propertytypes` (`PropertyTypeID`) ON UPDATE CASCADE,
  ADD CONSTRAINT `FK_UnitDesign` FOREIGN KEY (`unit_design_id`) REFERENCES `propertyunitdesigns` (`DesignID`) ON DELETE SET NULL ON UPDATE CASCADE;

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
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
