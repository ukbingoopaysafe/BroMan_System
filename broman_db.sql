-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Sep 06, 2025 at 01:05 AM
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
(1, 1, 'شقة فاخرة بإطلالة مميزة', 'شقة 3 غرف نوم مع تشطيب كامل وإطلالة على الحديقة المركزية', 'التجمع الخامس, القاهرة الجديدة', 180.00, 3, 'available', 'Primary', '2025-08-24 13:10:44', 1, 'استوديو', 3, 50000.00, 10.00, 7, '2025-12-31', 'كمبوند ماونتن فيو', 160.00, 200.00, 180.00, 'التجمع الخامس', 'القاهرة', 'إعمار مصر', 5, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'أحمد محمود', '01012345678', 'سارة أحمد', '01087654321', NULL, NULL),
(2, 1, 'فيلا مستقلة بحديقة خاصة', 'فيلا 5 غرف نوم مع حديقة خاصة وحمام سباحة', 'الشيخ زايد, الجيزة', 450.00, 5, 'available', 'Primary', '2025-08-24 13:10:44', 1, 'غرفة', 3, 100000.00, 15.00, 10, '2026-06-30', 'زايد ديونز', 400.00, 500.00, 450.00, 'الشيخ زايد', 'الجيزة', 'سوديك', 2, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'محمد علي', '01023456789', 'فاطمة حسن', '01076543210', NULL, NULL),
(3, 1, 'دوبلكس أنيق في كمبوند راقي', 'دوبلكس 4 غرف نوم مع تشطيب فاخر', 'مدينة نصر, القاهرة', 280.00, 4, 'available', 'Primary', '2025-08-24 13:10:44', 2, 'غرفتين', 3, 75000.00, 12.00, 8, '2025-09-15', 'جرين هيلز', 250.00, 300.00, 280.00, 'مدينة نصر', 'القاهرة', 'مجموعة طلعت مصطفى', 3, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'علي محمد', '01034567890', 'نورا سعيد', '01065432109', NULL, NULL),
(4, 1, 'شقة مميزة في برج سكني', 'شقة 2 غرف نوم مع إطلالة على النيل', 'المعادي, القاهرة', 120.00, 2, 'sold', 'Primary', '2025-08-24 13:10:44', 1, 'استوديو', 2, NULL, 20.00, 5, '2024-08-01', 'ريفر فيو تاورز', 110.00, 130.00, 120.00, 'المعادي', 'القاهرة', 'إعمار مصر', 12, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'كريم أحمد', '01045678901', 'ليلى محمود', '01054321098', NULL, NULL),
(5, 1, 'فيلا كلاسيكية في ضاحية هادئة', 'فيلا 6 غرف نوم مع حديقة واسعة', '6 أكتوبر, الجيزة', 550.00, 6, 'available', 'Resale', '2025-08-24 13:10:44', 2, 'غرفة', 5, 120000.00, 5.00, 18, NULL, 'أكتوبر بارك', NULL, NULL, NULL, '6 أكتوبر', 'الجيزة', 'سوديك', 2, NULL, '', NULL, NULL, 5555.00, 6666.00, 55.00, 66.00, 'بواب', 'محمد', '778778', 'حسن علي', '01056789012', 'رانيا أحمد', '01043210987', 30.04872352, 31.24969351),
(6, 2, 'شاليه يرى البحر مباشرة', 'شاليه 2 غرف نوم مع إطلالة مباشرة على البحر', 'الغردقة, البحر الأحمر', 120.00, 2, 'available', 'Primary', '2025-08-24 13:10:44', 1, '3 غرف', 3, NULL, 20.00, 5, '2024-08-01', 'بورتو السخنة', 110.00, 130.00, 120.00, 'الغردقة', 'البحر الأحمر', 'مجموعة طلعت مصطفى', 3, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'أحمد سعيد', '01067890123', 'سلمى محمد', '01032109876', NULL, NULL),
(7, 2, 'فيلا ساحلية فاخرة', 'فيلا 4 غرف نوم مع شاطئ خاص', 'مرسى علم, البحر الأحمر', 300.00, 4, 'available', 'Primary', '2025-08-24 13:10:44', 2, 'غرفة', 3, 80000.00, 25.00, 7, '2025-03-15', 'مرسى علم ريزورت', 280.00, 320.00, 300.00, 'مرسى علم', 'البحر الأحمر', 'إعمار مصر', 2, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'محمد كريم', '01078901234', 'نادية علي', '01021098765', NULL, NULL),
(8, 2, 'شقة استوديو في منتجع سياحي', 'استوديو أنيق مع إطلالة على البحر', 'شرم الشيخ, جنوب سيناء', 65.00, 1, 'rented', 'Resale', '2025-08-24 13:10:44', 1, 'استوديو', 2, NULL, NULL, NULL, NULL, 'شرم الشيخ ريزورت', NULL, NULL, NULL, 'شرم الشيخ', 'جنوب سيناء', 'سوديك', 8, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'كريم حسن', '01089012345', 'هبة أحمد', '01010987654', NULL, NULL),
(9, 2, 'فيلا عائلية على البحر', 'فيلا 5 غرف نوم مع حديقة وبركة سباحة', 'العين السخنة, السويس', 220.00, 5, 'available', 'Primary', '2025-08-24 13:10:44', 2, 'غرفتين', 3, 60000.00, 15.00, 8, '2025-05-20', 'العين السخنة بارك', 200.00, 240.00, 220.00, 'العين السخنة', 'السويس', 'مجموعة طلعت مصطفى', 2, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'علي سعيد', '01090123456', 'مريم محمد', '01009876543', NULL, NULL),
(10, 4, 'محل تجاري بموقع متميز', 'محل تجاري بموقع استراتيجي في مول شهير', 'مول العرب, 6 أكتوبر', 80.00, NULL, 'available', 'Primary', '2025-08-24 13:10:44', 1, NULL, 1, NULL, 25.00, 5, NULL, 'مول العرب', NULL, NULL, 80.00, 'الحي المتميز', 'الجيزة', 'مجموعة الفطيم', 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'أحمد علي', '01001234567', 'فاطمة كريم', '01098765432', NULL, NULL),
(11, 4, 'مكتب إداري متشطب بالكامل', 'مكتب إداري متشطب بالتكييفات في برج متميز', 'الحي المالي, العاصمة الإدارية', 95.00, NULL, 'available', 'Primary', '2025-08-24 13:10:44', 2, 'استوديو', 3, NULL, 10.00, 8, '2025-01-15', 'برج الأعمال', 90.00, 100.00, 95.00, 'الحي المالي', 'القاهرة', 'شركة العاصمة', 15, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'محمد أحمد', '01012345678', 'سارة علي', '01087654321', NULL, NULL),
(12, 4, 'مستودع صناعي مجهز', 'مستودع صناعي مجهز بالكامل مع رافعة', 'مدينة بدر, القاهرة', 500.00, NULL, 'sold', 'Resale', '2025-08-24 13:10:44', 1, NULL, 2, NULL, NULL, NULL, NULL, 'مدينة بدر الصناعية', NULL, NULL, NULL, 'مدينة بدر', 'القاهرة', 'سوديك', 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'كريم محمد', '01023456789', 'ليلى أحمد', '01076543210', NULL, NULL),
(13, 4, 'عيادة طبية مجهزة', 'عيادة طبية مجهزة بالكامل في مركز طبي شهير', 'شارع الطيران, مدينة نصر', 70.00, NULL, 'rented', 'Primary', '2025-08-24 13:10:44', 2, 'غرفتين', 3, NULL, NULL, NULL, NULL, 'المركز الطبي الدولي', NULL, NULL, NULL, 'مدينة نصر شرق', 'القاهرة', 'إعمار مصر', 8, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'علي كريم', '01034567890', 'نورا محمد', '01065432109', NULL, NULL),
(14, 3, 'مكتب محاماة فاخر', 'مكتب محاماة متشطب بالكامل مع قاعة اجتماعات', 'وسط البلد, القاهرة', 120.00, NULL, 'available', 'Primary', '2025-08-24 13:10:44', 1, 'استوديو', 3, NULL, 15.00, 6, '2025-02-28', 'برج المحامين', 100.00, 140.00, 120.00, 'وسط البلد', 'القاهرة', 'مجموعة طلعت مصطفى', 12, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'أحمد علي', '01045678901', 'سلمى كريم', '01054321098', NULL, NULL),
(15, 3, 'مكتب استشارات هندسية', 'مكتب استشارات هندسية مع معمل مجهز', 'المعادي, القاهرة', 85.00, NULL, 'available', 'Resale', '2025-08-24 13:10:44', 2, 'غرفة', 2, NULL, NULL, NULL, NULL, 'برج المهندسين', NULL, NULL, NULL, 'المعادي', 'القاهرة', 'سوديك', 10, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'محمد أحمد', '01056789012', 'رانيا علي', '01043210987', NULL, NULL),
(16, 3, 'مكتب شركة تأمين', 'مكتب شركة تأمين متشطب بالكامل', 'مدينة نصر, القاهرة', 75.00, NULL, 'sold', 'Primary', '2025-08-24 13:10:44', 1, 'استوديو', 3, NULL, 12.00, 7, '2024-11-30', 'برج التأمين', 70.00, 80.00, 75.00, 'مدينة نصر', 'القاهرة', 'إعمار مصر', 8, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'كريم محمد', '01067890123', 'هبة أحمد', '01032109876', NULL, NULL),
(17, 6, 'عيادة أسنان متخصصة', 'عيادة أسنان مجهزة بأحدث الأجهزة', 'المعادي, القاهرة', 90.00, NULL, 'available', 'Primary', '2025-08-24 13:10:44', 2, 'غرفتين', 3, NULL, 20.00, 5, '2025-04-15', 'مركز طب الأسنان', 85.00, 95.00, 90.00, 'المعادي', 'القاهرة', 'مجموعة طلعت مصطفى', 6, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'علي أحمد', '01078901234', 'مريم محمد', '01021098765', NULL, NULL),
(18, 6, 'مختبر تحاليل طبية', 'مختبر تحاليل طبية مجهز بالكامل', 'مدينة نصر, القاهرة', 65.00, NULL, 'available', 'Resale', '2025-08-24 13:10:44', 1, 'غرفة', 2, NULL, NULL, NULL, NULL, 'مختبرات المدينة الطبية', NULL, NULL, NULL, 'مدينة نصر', 'القاهرة', 'سوديك', 4, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'أحمد علي', '01089012345', 'نادية كريم', '01010987654', NULL, NULL),
(19, 6, 'صيدلية حديثة', 'صيدلية حديثة مع مخزن مجهز', 'التجمع الخامس, القاهرة', 45.00, NULL, 'rented', 'Primary', '2025-08-24 13:10:44', 2, 'استوديو', 3, NULL, 15.00, 6, '2025-01-10', 'صيدليات التجمع', 40.00, 50.00, 45.00, 'التجمع الخامس', 'القاهرة', 'إعمار مصر', 2, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'محمد كريم', '01090123456', 'سارة أحمد', '01009876543', NULL, NULL),
(20, 5, 'جناح فندقي فاخر', 'جناح فندقي فاخر مع إطلالة على النيل', 'وسط البلد, القاهرة', 150.00, NULL, 'available', 'Primary', '2025-08-24 13:10:44', 1, '3 غرف', 3, NULL, 30.00, 4, '2025-06-01', 'جراند أوتيل القاهرة', 140.00, 160.00, 150.00, 'وسط البلد', 'القاهرة', 'مجموعة طلعت مصطفى', 25, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'كريم علي', '01001234567', 'ليلى محمد', '01098765432', NULL, NULL),
(21, 5, 'شقة فندقية مميزة', 'شقة فندقية مع خدمات فاخرة', 'المعادي, القاهرة', 110.00, NULL, 'sold', 'Resale', '2025-08-24 13:10:44', 2, 'غرفة', 2, NULL, NULL, NULL, NULL, 'ريزيدنس المعادي', NULL, NULL, NULL, 'المعادي', 'القاهرة', 'سوديك', 15, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'علي أحمد', '01012345678', 'رانيا كريم', '01087654321', NULL, NULL),
(22, 5, 'جناح تنفيذي في فندق 5 نجوم', 'جناح تنفيذي مع إطلالة على الأهرامات', 'الجيزة, الجيزة', 180.00, NULL, 'available', 'Primary', '2025-08-24 13:10:44', 1, 'غرفتين', 3, NULL, 25.00, 5, '2025-03-20', 'فندق الأهرامات', 170.00, 190.00, 180.00, 'الجيزة', 'الجيزة', 'إعمار مصر', 20, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'أحمد محمد', '01023456789', 'نورا علي', '01076543210', NULL, NULL),
(23, 1, 'شقة عائلية في كمبوند آمن', 'شقة 4 غرف نوم مع تشطيب كامل', 'مدينة الرحاب, القاهرة', 200.00, 4, 'available', 'Primary', '2025-08-24 13:10:44', 2, 'استوديو', 3, 60000.00, 12.00, 8, '2025-08-15', 'كمبوند الرحاب', 180.00, 220.00, 200.00, 'مدينة الرحاب', 'القاهرة', 'سوديك', 4, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'محمد علي', '01034567890', 'فاطمة أحمد', '01065432109', NULL, NULL),
(24, 1, 'فيلا كلاسيكية في ضاحية راقية', 'فيلا 5 غرف نوم مع حديقة إنجليزية', 'المعادي, القاهرة', 400.00, 5, 'rented', 'Resale', '2025-08-24 13:10:44', 1, 'غرفة', 1, 90000.00, NULL, NULL, NULL, 'المعادي جاردنز', NULL, NULL, NULL, 'المعادي', 'القاهرة', 'مجموعة طلعت مصطفى', 2, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'كريم محمد', '01045678901', 'سلمى علي', '01054321098', NULL, NULL),
(25, 1, 'شقة مميزة في برج سكني فاخر', 'شقة 3 غرف نوم مع إطلالة على النيل', 'زمالك, القاهرة', 160.00, 3, 'available', 'Primary', '2025-08-24 13:10:44', 2, 'استوديو', 3, NULL, 15.00, 7, '2025-05-10', 'زمالك تاورز', 150.00, 170.00, 160.00, 'زمالك', 'القاهرة', 'إعمار مصر', 18, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'علي كريم', '01056789012', 'مريم أحمد', '01043210987', NULL, NULL),
(26, 1, 'دوبلكس فاخر في كمبوند مغلق', 'دوبلكس 6 غرف نوم مع تشطيب فاخر', 'التجمع الأول, القاهرة', 350.00, 6, 'sold', 'Primary', '2025-08-24 13:10:44', 1, 'غرفتين', 3, 80000.00, 18.00, 9, '2024-12-20', 'كمبوند التجمع الأول', 330.00, 370.00, 350.00, 'التجمع الأول', 'القاهرة', 'سوديك', 3, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'أحمد علي', '01067890123', 'رانيا محمد', '01032109876', NULL, NULL),
(27, 1, 'شقة أنيقة في حي سكني هادئ', 'شقة 2 غرف نوم مع تشطيب كامل', 'مدينة نصر, القاهرة', 100.00, 2, 'available', 'Resale', '2025-08-24 13:10:44', 2, 'استوديو', 2, NULL, NULL, NULL, NULL, 'مدينة نصر بارك', NULL, NULL, NULL, 'مدينة نصر', 'القاهرة', 'مجموعة طلعت مصطفى', 6, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'محمد أحمد', '01078901234', 'هبة علي', '01021098765', NULL, NULL),
(28, 1, 'فيلا مستقلة في ضاحية خضراء', 'فيلا 4 غرف نوم مع حديقة خاصة', '6 أكتوبر, الجيزة', 280.00, 4, 'available', 'Primary', '2025-08-24 13:10:44', 1, 'غرفة', 3, 70000.00, 14.00, 8, '2025-07-01', 'أكتوبر جاردنز', 260.00, 300.00, 280.00, '6 أكتوبر', 'الجيزة', 'إعمار مصر', 2, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'كريم محمد', '01089012345', 'نورا أحمد', '01010987654', NULL, NULL),
(29, 1, 'شقة فاخرة في برج سكني مميز', 'شقة 3 غرف نوم مع إطلالة على المدينة', 'مدينة نصر, القاهرة', 140.00, 3, 'available', 'Primary', '2025-08-24 13:10:44', 2, 'استوديو', 3, NULL, 16.00, 6, '2025-04-05', 'مدينة نصر تاورز', 130.00, 150.00, 140.00, 'مدينة نصر', 'القاهرة', 'سوديك', 12, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'علي كريم', '01090123456', 'سارة محمد', '01009876543', NULL, NULL),
(30, 1, 'فيلا كلاسيكية في حي راقي', 'فيلا 5 غرف نوم مع حديقة إنجليزية', 'المعادي, القاهرة', 450.00, 5, 'rented', 'Resale', '2025-08-24 13:10:44', 1, 'غرفة', 1, 100000.00, NULL, NULL, NULL, 'المعادي كلاسيك', NULL, NULL, NULL, 'المعادي', 'القاهرة', 'مجموعة طلعت مصطفى', 2, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'أحمد علي', '01001234567', 'ليلى كريم', '01098765432', NULL, NULL),
(34, 1, 'alskal', '', '', 200.00, NULL, 'available', 'Resale', '2025-09-05 16:18:23', 3, 'ففغفغفغ 67676', 4, NULL, 5.00, 18, NULL, '', NULL, NULL, NULL, 'مدينة نصر شرق', 'ghhgh', '', 12, '2000', 'روف', 100.00, 50.00, 200.00, 300.00, 2.00, 3.00, NULL, 'vccv', '01029292929', '', '', '', '', 30.03387575, 31.23870266),
(35, 1, 'kjhkj', '', '', 200.00, NULL, 'available', 'Resale', '2025-09-05 16:21:42', 3, 'ففغفغفغ 67676', 5, NULL, NULL, NULL, NULL, '', NULL, NULL, NULL, 'kjhj', 'ihihh', '', NULL, NULL, '', NULL, NULL, 22222.00, NULL, NULL, NULL, NULL, '', '', '', '', '', '', NULL, NULL),
(36, 1, 'lijlilj', '', '', 2222.00, NULL, 'available', 'Resale', '2025-09-05 16:22:45', 3, 'uhuhkjhjk', 3, NULL, 4.00, 44, NULL, '', NULL, NULL, NULL, 'kjhkjh', 'lkjlkkl', '', NULL, '0000', 'روف', 60.00, 22.00, 22222.00, 3234.00, 34.00, 3434.00, NULL, 'محمد', '778778', '', '', '', '', NULL, NULL),
(37, 1, 'عغنان', '', '', 233.00, NULL, 'available', 'Resale', '2025-09-05 21:23:22', 3, 'ففغفغفغ 67676', 7, NULL, 33.00, 33, NULL, '', NULL, NULL, NULL, 'غنغن', 'غعنعغن', '', 6, '1907', 'الدور 12', 333.00, 33.00, 2333333.00, 33333333.00, 33.00, 33.00, 'شركة تشطيب', 'vccv', '33333', '', '', '', '', 30.02674803, 31.26617978),
(38, 1, 'اتتللتل', '', '', 777.00, NULL, 'available', 'Resale', '2025-09-05 22:59:42', 3, 'استوديو', 6, NULL, NULL, NULL, NULL, '', NULL, NULL, NULL, 'لتلت', 'لتالال', '', NULL, '1972', 'روف', 77.00, 7.00, 777777.00, 7777777.00, NULL, NULL, NULL, '', '', '', '', '', '', NULL, NULL);

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
(1, 1, 'uploads/images/property_1_68ab10b12bece.jpg', '2025-08-24 13:16:33', 1),
(2, 2, 'uploads/images/property_2_68ab10fd42489.jpg', '2025-08-24 13:17:49', 1),
(3, 3, 'uploads/images/property_3_68ab113586ba9.webp', '2025-08-24 13:18:45', 1),
(4, 18, 'uploads/images/property_18_68ab1171c22f6.jpg', '2025-08-24 13:19:45', 1),
(5, 1, 'uploads/images/property_1_68ab13289e6a5.webp', '2025-08-24 13:27:04', 0),
(7, 5, 'uploads/images/property_5_68bb6ac391764.webp', '2025-09-05 22:57:07', 1);

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
  MODIFY `EmployeeID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `properties`
--
ALTER TABLE `properties`
  MODIFY `PropertyID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=39;

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
  MODIFY `note_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `property_images`
--
ALTER TABLE `property_images`
  MODIFY `image_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `property_videos`
--
ALTER TABLE `property_videos`
  MODIFY `VideoID` int(11) NOT NULL AUTO_INCREMENT;

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
