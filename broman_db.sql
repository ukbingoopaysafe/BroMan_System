-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Sep 10, 2025 at 01:40 PM
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
  `year_built` varchar(50) DEFAULT NULL,
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
(1, 1, 'شقه استثماري فندقي', '1- البيع شامل العفش والاجهزه\r\n2-الدور الاخير والسطح معزول عزل حراري ومائي\r\n3-الوحده قبله للستجيل العقاري\r\n4-البيع كاش فقط والسعر شامل وديعه الصيانه\r\n5-يوجد حصه بالجراج بسعر منفصل\r\n6- بيدفع عموله 1.5 % فقط دون نقاشات', '74 شارع مصدق', 115.00, NULL, 'available', 'Resale', '2025-09-10 09:35:06', 7, 'غرفتين +2حمام +ريسيبشن قطعتين+بلكونه+مطبخ امريكاني', 8, NULL, NULL, NULL, '2025-10-01', '', NULL, NULL, NULL, 'مصدق', 'الجيزه', 'شركه المصريه لاداره الاصول العقاريه', 12, '2023', '12', NULL, NULL, 5300000.00, NULL, NULL, NULL, 'شركة تشطيب', 'ابراهيم دياب', '01094450056', 'ابراهيم دياب', '01094450056', '', '', 30.03839891, 31.20164515),
(2, 1, 'شقه سمارت دوبلكس', '1- عداد كهرباء كارت\r\n2-متاح 2 اسانسير\r\n3- مسجله شهر عقاري ولها حصه بالارض\r\n4-بتتاجر 60 الف شهريا\r\n5- البيع شامل العفش\r\n6-الشقه سمارت تعمل AI بالذكاء الاصطناعي', 'و16 ميدان المساحه البرج اللي في اورنج', 210.00, NULL, 'available', 'Resale', '2025-09-10 09:39:01', 7, '3 غرف + 3 حمام+ريسبشن 3 قطع +4 بلكونات', 7, NULL, NULL, NULL, NULL, '', NULL, NULL, NULL, 'المساحه', 'الجيزه', '', 10, NULL, '5', 190.00, NULL, 15000000.00, NULL, NULL, NULL, 'مالك', 'ابانوب', '01277262893', '', '', '', '', 30.03604450, 31.21457969),
(3, 3, 'مكتب', '1-صحه توقيع ولها حصه بالارض\r\n2- رخصه اداري\r\n3- البيع بالفعش \r\n5-وسيط ممكن ندخل معاه في العموله', 'امام نادى الصيد و بجوار السفارة العراقية و بجوار كل البنوك و التوكيلات', 260.00, NULL, 'available', 'Resale', '2025-09-10 09:42:00', 7, '3 غرف + 3حمام', 5, NULL, NULL, NULL, NULL, '', NULL, NULL, NULL, 'ميدان الثوره', 'الجيزه', '', NULL, NULL, '3', NULL, NULL, 9000000.00, NULL, NULL, NULL, 'وسيط', 'مصطفي', '01024041048', '', '', '', '', 30.04846481, 31.20415613),
(4, 1, 'شقه vip', '1- البيع شامل العفش وجميع الاجهزه \r\n2- علي سوريا الرئيسي\r\n3- الشقه مسجله شهر عقاري ولها حصه بالارض \r\n4-متاح خط ارضي ونت \r\n5- بيدي عموله 1,5 % فقط بدون نقاشات \r\n6- عماره تسعينات عماره 7 ادوار', '11 ش سوريا الرئيسي تقاطع مع ش جزيرة العرب', 160.00, NULL, 'available', 'Resale', '2025-09-10 10:45:52', 7, '3 غرف +3 حمام +ريسيبشن قطعتين +بلكونه', 6, NULL, NULL, NULL, NULL, '', NULL, NULL, NULL, 'سوريا', 'الجيزه', '', NULL, '1995', '6', NULL, NULL, 7500000.00, NULL, NULL, NULL, 'مالك', 'عبده', '1050238546', '', '', '', '', NULL, NULL),
(5, 1, 'شقه تطل علي نادي الزمالك', '1- عداد كهرباء قديم -يوجد غاز طبيعي \r\n2-لها حصه بالارض ولها جراج خاص بها \r\n3- البيع شامل العفش والاجهزه \r\n4-مسجله شهر عقاري -لا تصلح تمويل عقاري \r\n5- الدور في شقتين \r\n6- وسيطه بتتعامل كل واحد بعميله فقط', 'بالمهندسين ع جامعه الدول تطل ع نادي الزمالك وعلى ش جزيرة العرب وعلى ميدان مصطفى محمود', 285.00, NULL, 'available', 'Resale', '2025-09-10 10:57:16', 7, '3غرف +3 حمام + ريسيبشن 3 قطع', 4, NULL, NULL, NULL, NULL, '', NULL, NULL, NULL, 'المهندسين', 'الجيزه', '', 18, NULL, '14', NULL, NULL, 14000000.00, NULL, NULL, NULL, 'وسيط', 'اميره', '1550691243', '', '', '', '', NULL, NULL),
(6, 1, 'شقه شارع القدس', '1- عداد كهرباء قديم - انبوبه \r\n2-مسجله شهر عقاري ولها حصه بالارض \r\n3-البيع شامل العفش', '10 شارع القدس متفرع من شارع شهاب', 200.00, NULL, 'available', 'Resale', '2025-09-10 11:14:03', 7, '3 غرف + 3 حمام', 5, NULL, NULL, NULL, NULL, '', NULL, NULL, NULL, 'القدس متفرع من شهاب', 'الجيزه', '', NULL, NULL, '4', NULL, NULL, 7000000.00, NULL, NULL, NULL, 'وسيط', 'سماح', '1129191026', '', '', '', '', NULL, NULL),
(11, 1, 'شقه شارع يوسف ادريس', '1- الشقه غير مسجله شهر عقاري \r\n2-متاح حصه بالارض \r\n3- متاح عداد كهرباء قديم - متاح غاز طبيعي', '12 تقاطع شارع يوسف ادريس مع عبد الحميد سند متفرع من احمد عرابي', 180.00, NULL, 'sold', 'Resale', '2025-09-10 11:34:56', 7, '3 غرف + 2 حمام', 4, NULL, NULL, NULL, NULL, '', NULL, NULL, NULL, 'تفرعات احمد عرابي', 'الجيزه', '', 10, '1980', '4', NULL, NULL, 4500000.00, NULL, NULL, NULL, 'مالك', 'ايهاب', '1002508282', '', '', '', '', NULL, NULL);

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

--
-- Dumping data for table `property_images`
--

INSERT INTO `property_images` (`image_id`, `property_id`, `file_path`, `upload_date`, `is_primary`) VALUES
(54, 4, 'uploads/images/property_4_ced3a02e8001a871.jpg', '2025-09-10 10:45:52', 0),
(55, 4, 'uploads/images/property_4_6335f30d899bf0e9.jpg', '2025-09-10 10:45:52', 0),
(56, 4, 'uploads/images/property_4_ef03b91aa3a18bf2.jpg', '2025-09-10 10:45:52', 0),
(57, 4, 'uploads/images/property_4_598acc1a011cd701.jpg', '2025-09-10 10:45:52', 0),
(58, 4, 'uploads/images/property_4_6e05bbdaed26eb25.jpg', '2025-09-10 10:45:52', 0),
(59, 4, 'uploads/images/property_4_1c2010b91188be1c.jpg', '2025-09-10 10:45:52', 0),
(60, 4, 'uploads/images/property_4_383b261ed6be1728.jpg', '2025-09-10 10:45:52', 0),
(61, 4, 'uploads/images/property_4_7f75655e06069a36.jpg', '2025-09-10 10:45:52', 0),
(62, 4, 'uploads/images/property_4_4a250fb2798fc388.jpg', '2025-09-10 10:45:52', 1),
(63, 4, 'uploads/images/property_4_cb62adc33403a94a.jpg', '2025-09-10 10:45:52', 0),
(64, 4, 'uploads/images/property_4_88256f76b21c9e92.jpg', '2025-09-10 10:45:52', 0),
(65, 4, 'uploads/images/property_4_2a04a457979ee536.jpg', '2025-09-10 10:45:52', 0),
(66, 4, 'uploads/images/property_4_42e95215477d5c70.jpg', '2025-09-10 10:45:52', 0),
(67, 4, 'uploads/images/property_4_26477233c804e247.jpg', '2025-09-10 10:45:52', 0),
(68, 4, 'uploads/images/property_4_01121ab529494896.jpg', '2025-09-10 10:45:52', 0),
(69, 4, 'uploads/images/property_4_f8e6aa8424391172.jpg', '2025-09-10 10:45:52', 0),
(70, 4, 'uploads/images/property_4_22688ce09678d431.jpg', '2025-09-10 10:45:52', 0),
(71, 4, 'uploads/images/property_4_84f34c95556d2bd1.jpg', '2025-09-10 10:45:52', 0),
(72, 4, 'uploads/images/property_4_2f8d477b9c8c7ac8.jpg', '2025-09-10 10:45:52', 0),
(73, 4, 'uploads/images/property_4_202c7649e557577d.jpg', '2025-09-10 10:45:52', 0),
(74, 5, 'uploads/images/property_5_e30329adf166a290.jpg', '2025-09-10 10:57:16', 0),
(75, 5, 'uploads/images/property_5_6c8bf86d371d3dd7.jpg', '2025-09-10 10:57:16', 0),
(76, 5, 'uploads/images/property_5_3b1c0cb349abfb11.jpg', '2025-09-10 10:57:16', 0),
(77, 5, 'uploads/images/property_5_4116c5f06ae5babb.jpg', '2025-09-10 10:57:16', 0),
(78, 5, 'uploads/images/property_5_f9f83f1b0a18e109.jpg', '2025-09-10 10:57:16', 0),
(79, 5, 'uploads/images/property_5_8f500e4c42b6c5b9.jpg', '2025-09-10 10:57:16', 1),
(80, 5, 'uploads/images/property_5_717d30cf56f41678.jpg', '2025-09-10 10:57:16', 0),
(81, 5, 'uploads/images/property_5_6d4cead42c9624fc.jpg', '2025-09-10 10:57:16', 0),
(82, 5, 'uploads/images/property_5_bc1ee535db59328b.jpg', '2025-09-10 10:57:16', 0),
(83, 5, 'uploads/images/property_5_ed49d4f1605cdf57.jpg', '2025-09-10 10:57:16', 0),
(84, 11, 'uploads/images/property_11_0911c3dd3ed79793.jpg', '2025-09-10 11:34:56', 0),
(85, 11, 'uploads/images/property_11_99e59321a88199cd.jpg', '2025-09-10 11:34:56', 0),
(86, 11, 'uploads/images/property_11_d28c7511780a1166.jpg', '2025-09-10 11:34:56', 0),
(87, 11, 'uploads/images/property_11_7334cc80bbf3c228.jpg', '2025-09-10 11:34:56', 0),
(88, 11, 'uploads/images/property_11_a05c9f8de2ceb2ba.jpg', '2025-09-10 11:34:56', 0),
(89, 11, 'uploads/images/property_11_f98fb179383ce6ec.jpg', '2025-09-10 11:34:56', 0),
(90, 11, 'uploads/images/property_11_171cf01166dbaa4c.jpg', '2025-09-10 11:34:56', 0),
(91, 11, 'uploads/images/property_11_a6dfa5d0932978b9.jpg', '2025-09-10 11:34:56', 0),
(92, 11, 'uploads/images/property_11_16c4212713368904.jpg', '2025-09-10 11:34:56', 0),
(93, 11, 'uploads/images/property_11_46b4f953d3fd4c9b.jpg', '2025-09-10 11:34:56', 0),
(94, 11, 'uploads/images/property_11_6d5a32b7ee641ae0.jpg', '2025-09-10 11:34:56', 0),
(95, 11, 'uploads/images/property_11_c29c7e372271634b.jpg', '2025-09-10 11:34:56', 1),
(96, 11, 'uploads/images/property_11_a1f760df983c9e19.jpg', '2025-09-10 11:34:56', 0),
(97, 11, 'uploads/images/property_11_6b5ca3f879c06a95.jpg', '2025-09-10 11:34:56', 0),
(98, 11, 'uploads/images/property_11_d1baed368c1da78f.jpg', '2025-09-10 11:34:56', 0),
(99, 11, 'uploads/images/property_11_0cd82abe5e509910.jpg', '2025-09-10 11:34:56', 0),
(100, 11, 'uploads/images/property_11_e2e7d81e5f2d676a.jpg', '2025-09-10 11:34:56', 0),
(101, 11, 'uploads/images/property_11_c12a53e7bfbb7a22.jpg', '2025-09-10 11:34:56', 0),
(102, 11, 'uploads/images/property_11_806048cf99aadc31.jpg', '2025-09-10 11:34:56', 0);

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
(50, 3, 'uploads/videos/property_3_video_68c14b0ec9a28.mp4', 'فيديو واتساب بتاريخ 1446-11-05 في 18.36.00_42c672aa.mp4', 16949491, '2025-09-10 09:55:26', 7),
(51, 1, 'uploads/videos/property_1_video_68c14c8134f89.mp4', 'فيديو واتساب بتاريخ 1447-03-16 في 22.21.05_a6f05d43.mp4', 14864836, '2025-09-10 10:01:37', 7),
(52, 2, 'uploads/videos/property_2_video_68c14d21b5d91.mp4', 'فيديو واتساب بتاريخ 1446-09-26 في 15.37.26_dde092be.mp4', 19608262, '2025-09-10 10:04:17', 7),
(53, 2, 'uploads/videos/property_2_video_68c14d2cb2f73.mp4', 'فيديو واتساب بتاريخ 1446-11-03 في 12.50.24_25070de1.mp4', 24051481, '2025-09-10 10:04:28', 7),
(54, 2, 'uploads/videos/property_2_video_68c14d5a9ee5e.mp4', 'فيديو واتساب بتاريخ 1447-02-18 في 12.39.07_49117ce2.mp4', 48833377, '2025-09-10 10:05:14', 7),
(55, 6, 'uploads/videos/property_6_video_4ee32a31697e7dab.mp4', 'فيديو واتساب بتاريخ 1446-10-06 في 20.31.25_ca846f6e.mp4', 13692449, '2025-09-10 11:14:03', 7),
(57, 11, 'uploads/videos/property_11_video_d5447384ce3d9932.mp4', 'فيديو واتساب بتاريخ 1446-10-06 في 16.31.04_15d11f3d.mp4', 25271424, '2025-09-10 11:34:56', 7);

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
  MODIFY `PropertyID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

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
  MODIFY `image_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=103;

--
-- AUTO_INCREMENT for table `property_videos`
--
ALTER TABLE `property_videos`
  MODIFY `VideoID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=58;

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
