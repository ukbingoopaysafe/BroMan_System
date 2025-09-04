-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
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
-- Table structure for table `propertyfinishingtypes`
--

CREATE TABLE `propertyfinishingtypes` (
  `FinishingID` int(11) NOT NULL,
  `FinishingName` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Per requirements: truncate and insert new set
TRUNCATE TABLE `propertyfinishingtypes`;
INSERT INTO `propertyfinishingtypes` (`FinishingID`, `FinishingName`) VALUES
(1,'طوب أحمر'),
(2,'نصف تشطيب'),
(3,'محارة وحلوق'),
(4,'لوكس'),
(5,'سوبر لوكس'),
(6,'الترا سوبر لوكس'),
(7,'سمارت'),
(8,'فندقي');

-- --------------------------------------------------------

--
-- Table structure for table `properties` (Upgraded with new schema)
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
  `sales_manager_name` varchar(255) DEFAULT NULL,
  `sales_manager_phone` varchar(50) DEFAULT NULL,
  `salesperson_name` varchar(255) DEFAULT NULL,
  `salesperson_phone` varchar(50) DEFAULT NULL,
  `seller_name` varchar(255) DEFAULT NULL,
  `seller_phone` varchar(50) DEFAULT NULL,
  -- NEW PRICING FIELDS (replacing old Price, min_price, max_price)
  `price_cash` decimal(15,2) DEFAULT NULL COMMENT 'Cash price',
  `price_installments` decimal(15,2) DEFAULT NULL COMMENT 'Installment price',
  `meter_price_cash` decimal(15,2) DEFAULT NULL COMMENT 'Price per square meter (cash)',
  `meter_price_installments` decimal(15,2) DEFAULT NULL COMMENT 'Price per square meter (installments)',
  -- NEW LOCATION & DESIGN FIELDS
  `latitude` decimal(10,7) DEFAULT NULL COMMENT 'Latitude coordinate',
  `longitude` decimal(10,7) DEFAULT NULL COMMENT 'Longitude coordinate',
  `year_built` year(4) DEFAULT NULL COMMENT 'Year the property was built',
  `unit_floor` varchar(100) DEFAULT NULL COMMENT 'Floor/level of the unit',
  -- NEW AREA FIELDS
  `net_area` decimal(10,2) DEFAULT NULL COMMENT 'Net area excluding loading',
  `loading_percentage` decimal(5,2) DEFAULT NULL COMMENT 'Loading percentage',
  -- NEW SELLER TYPE
  `seller_type` enum('مالك','وسيط','وكيل','بواب','شركة مطورة','شركة تشطيب') DEFAULT NULL COMMENT 'Type of seller'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `properties` (30 realistic records with new schema)
--

INSERT INTO `properties` (
  `PropertyID`,`PropertyTypeID`,`Title`,`Description`,`Location`,`Area`,`Bedrooms`,`Status`,`listing_type`,`DateAdded`,`EmployeeID`,`unit_design`,`finishing_type_id`,`compound_area`,`down_payment_percentage`,`payment_years`,`delivery_date`,`ProjectName`,`min_area`,`max_area`,`avg_area`,`district`,`governorate`,`company_name`,`FloorsCount`,`sales_manager_name`,`sales_manager_phone`,`salesperson_name`,`salesperson_phone`,`seller_name`,`seller_phone`,`price_cash`,`price_installments`,`meter_price_cash`,`meter_price_installments`,`latitude`,`longitude`,`year_built`,`unit_floor`,`net_area`,`loading_percentage`,`seller_type`
) VALUES
(1,1,'شقة فاخرة بإطلالة مميزة','شقة 3 غرف نوم مع تشطيب كامل وإطلالة على الحديقة المركزية','التجمع الخامس, القاهرة الجديدة',180.00,3,'available','Primary','2025-08-24 13:10:44',1,'استوديو',3,50000.00,10.00,7,'2025-12-31','كمبوند ماونتن فيو',160.00,200.00,180.00,'التجمع الخامس','القاهرة','إعمار مصر',5,'أحمد محمود','01012345678','سارة أحمد','01087654321','محمد علي','01011111111',2500000.00,2800000.00,13888.89,15555.56,30.016700,31.261300,2019,'الدور 3',170.00,5.00,'مالك'),
(2,1,'فيلا مستقلة بحديقة خاصة','فيلا 5 غرف نوم مع حديقة خاصة وحمام سباحة','الشيخ زايد, الجيزة',450.00,5,'available','Primary','2025-08-24 13:10:44',1,'غرفة',3,100000.00,15.00,10,'2026-06-30','زايد ديونز',400.00,500.00,450.00,'الشيخ زايد','الجيزة','سوديك',2,'محمد علي','01023456789','فاطمة حسن','01076543210','أحمد محمد','01022222222',7000000.00,7800000.00,15555.56,17333.33,30.042800,31.203400,2018,NULL,430.00,4.00,'وسيط'),
(3,1,'دوبلكس أنيق في كمبوند راقي','دوبلكس 4 غرف نوم مع تشطيب فاخر','مدينة نصر, القاهرة',280.00,4,'available','Primary','2025-08-24 13:10:44',2,'غرفتين',3,75000.00,12.00,8,'2025-09-15','جرين هيلز',250.00,300.00,280.00,'مدينة نصر','القاهرة','مجموعة طلعت مصطفى',3,'علي محمد','01034567890','نورا سعيد','01065432109','سارة أحمد','01033333333',4200000.00,4600000.00,15000.00,16428.57,30.056100,31.330000,2020,'الدور 2',270.00,3.50,'وكيل'),
(4,1,'شقة مميزة في برج سكني','شقة 2 غرف نوم مع إطلالة على النيل','المعادي, القاهرة',120.00,2,'sold','Primary','2025-08-24 13:10:44',1,'استوديو',2,NULL,20.00,5,'2024-08-01','ريفر فيو تاورز',110.00,130.00,120.00,'المعادي','القاهرة','إعمار مصر',12,'كريم أحمد','01045678901','ليلى محمود','01054321098','فاطمة حسن','01044444444',1800000.00,2000000.00,15000.00,16666.67,29.960700,31.262700,2015,'الدور 12',115.00,4.17,'بواب'),
(5,1,'فيلا كلاسيكية في ضاحية هادئة','فيلا 6 غرف نوم مع حديقة واسعة','6 أكتوبر, الجيزة',550.00,6,'available','Resale','2025-08-24 13:10:44',2,'غرفة',1,120000.00,NULL,NULL,NULL,'أكتوبر بارك',NULL,NULL,NULL,'6 أكتوبر','الجيزة','سوديك',2,'حسن علي','01056789012','رانيا أحمد','01043210987','محمد كريم','01055555555',8500000.00,NULL,15454.55,NULL,29.936000,30.913000,2017,NULL,530.00,3.77,'شركة مطورة'),
(6,2,'شاليه يرى البحر مباشرة','شاليه 2 غرف نوم مع إطلالة مباشرة على البحر','الغردقة, البحر الأحمر',120.00,2,'available','Primary','2025-08-24 13:10:44',1,'فيلا توين هاوس',3,NULL,20.00,5,'2024-08-01','بورتو السخنة',110.00,130.00,120.00,'الغردقة','البحر الأحمر','مجموعة طلعت مصطفى',3,'أحمد سعيد','01067890123','سلمى محمد','01032109876','علي حسن','01066666666',1800000.00,2000000.00,15000.00,16666.67,27.257900,33.811600,2021,'الدور الأرضي',118.00,1.67,'شركة تشطيب'),
(7,2,'فيلا ساحلية فاخرة','فيلا 4 غرف نوم مع شاطئ خاص','مرسى علم, البحر الأحمر',300.00,4,'available','Primary','2025-08-24 13:10:44',2,'غرفة',3,80000.00,25.00,7,'2025-03-15','مرسى علم ريزورت',280.00,320.00,300.00,'مرسى علم','البحر الأحمر','إعمار مصر',2,'محمد كريم','01078901234','نادية علي','01021098765','سارة محمد','01077777777',4500000.00,5000000.00,15000.00,16666.67,25.235600,34.617700,2019,NULL,290.00,3.33,'وسيط'),
(8,2,'شقة استوديو في منتجع سياحي','استوديو أنيق مع إطلالة على البحر','شرم الشيخ, جنوب سيناء',65.00,1,'rented','Resale','2025-08-24 13:10:44',1,'استوديو',2,NULL,NULL,NULL,NULL,'شرم الشيخ ريزورت',NULL,NULL,NULL,'شرم الشيخ','جنوب سيناء','سوديك',8,'كريم حسن','01089012345','هبة أحمد','01010987654','أحمد علي','01088888888',1200000.00,NULL,18461.54,NULL,27.915800,34.329900,2016,'الدور 4',60.00,7.69,'مالك'),
(9,2,'فيلا عائلية على البحر','فيلا 5 غرف نوم مع حديقة وبركة سباحة','العين السخنة, السويس',220.00,5,'available','Primary','2025-08-24 13:10:44',2,'غرفتين',3,60000.00,15.00,8,'2025-05-20','العين السخنة بارك',200.00,240.00,220.00,'العين السخنة','السويس','مجموعة طلعت مصطفى',2,'علي سعيد','01090123456','مريم محمد','01009876543','فاطمة كريم','01099999999',3200000.00,3600000.00,14545.45,16363.64,29.622200,32.316300,2022,NULL,210.00,4.55,'وكيل'),
(10,4,'محل تجاري بموقع متميز','محل تجاري بموقع استراتيجي في مول شهير','مول العرب, 6 أكتوبر',80.00,NULL,'available','Primary','2025-08-24 13:10:44',1,NULL,1,NULL,25.00,5,NULL,'مول العرب',NULL,NULL,NULL,'الحي المتميز','الجيزة','مجموعة الفطيم',1,'أحمد علي','01001234567','فاطمة كريم','01098765432','محمد سعيد','01010101010',4000000.00,4500000.00,50000.00,56250.00,29.979200,30.957000,2018,'الدور الأرضي',70.00,12.50,'وسيط'),
(11,4,'مكتب إداري متشطب بالكامل','مكتب إداري متشطب بالتكييفات في برج متميز','الحي المالي, العاصمة الإدارية',95.00,NULL,'available','Primary','2025-08-24 13:10:44',2,'استوديو',3,NULL,10.00,8,'2025-01-15','برج الأعمال',90.00,100.00,95.00,'الحي المالي','القاهرة','شركة العاصمة',15,'محمد أحمد','01012345678','سارة علي','01087654321','علي محمد','01011111111',1500000.00,1700000.00,15789.47,17894.74,29.953300,32.562900,2021,'الدور 15',85.00,11.76,'شركة مطورة'),
(12,4,'مستودع صناعي مجهز','مستودع صناعي مجهز بالكامل مع رافعة','مدينة بدر, القاهرة',500.00,NULL,'sold','Resale','2025-08-24 13:10:44',1,NULL,2,NULL,NULL,NULL,NULL,'مدينة بدر الصناعية',NULL,NULL,NULL,'مدينة بدر','القاهرة','سوديك',1,'كريم محمد','01023456789','ليلى أحمد','01076543210','سارة حسن','01012121212',2800000.00,NULL,5600.00,NULL,30.329000,31.501800,2015,NULL,480.00,4.17,'مالك'),
(13,4,'عيادة طبية مجهزة','عيادة طبية مجهزة بالكامل في مركز طبي شهير','شارع الطيران, مدينة نصر',70.00,NULL,'rented','Primary','2025-08-24 13:10:44',2,'غرفتين',3,NULL,NULL,NULL,NULL,'المركز الطبي الدولي',NULL,NULL,NULL,'مدينة نصر شرق','القاهرة','إعمار مصر',8,'علي كريم','01034567890','نورا محمد','01065432109','أحمد فاطمة','01013131313',1200000.00,NULL,17142.86,NULL,30.064600,31.330900,2020,'الدور 8',65.00,7.14,'بواب'),
(14,3,'مكتب محاماة فاخر','مكتب محاماة متشطب بالكامل مع قاعة اجتماعات','وسط البلد, القاهرة',120.00,NULL,'available','Primary','2025-08-24 13:10:44',1,'استوديو',3,NULL,15.00,6,'2025-02-28','برج المحامين',100.00,140.00,120.00,'وسط البلد','القاهرة','مجموعة طلعت مصطفى',12,'أحمد علي','01045678901','سلمى كريم','01054321098','محمد نورا','01014141414',2200000.00,2500000.00,18333.33,20833.33,30.044400,31.235700,2019,'الدور 12',110.00,8.33,'وسيط'),
(15,3,'مكتب استشارات هندسية','مكتب استشارات هندسية مع معمل مجهز','المعادي, القاهرة',85.00,NULL,'available','Resale','2025-08-24 13:10:44',2,'غرفة',2,NULL,NULL,NULL,NULL,'برج المهندسين',NULL,NULL,NULL,'المعادي','القاهرة','سوديك',10,'محمد أحمد','01056789012','رانيا علي','01043210987','كريم سارة','01015151515',1800000.00,NULL,21176.47,NULL,29.960700,31.262700,2017,'الدور 10',80.00,5.88,'شركة مطورة'),
(16,3,'مكتب شركة تأمين','مكتب شركة تأمين متشطب بالكامل','مدينة نصر, القاهرة',75.00,NULL,'sold','Primary','2025-08-24 13:10:44',1,'استوديو',3,NULL,12.00,7,'2024-11-30','برج التأمين',70.00,80.00,75.00,'مدينة نصر','القاهرة','إعمار مصر',8,'كريم محمد','01067890123','هبة أحمد','01032109876','علي مريم','01016161616',1600000.00,1800000.00,21333.33,24000.00,30.056100,31.330000,2016,'الدور 8',70.00,6.67,'شركة تشطيب'),
(17,6,'عيادة أسنان متخصصة','عيادة أسنان مجهزة بأحدث الأجهزة','المعادي, القاهرة',90.00,NULL,'available','Primary','2025-08-24 13:10:44',2,'غرفتين',3,NULL,20.00,5,'2025-04-15','مركز طب الأسنان',85.00,95.00,90.00,'المعادي','القاهرة','مجموعة طلعت مصطفى',6,'علي أحمد','01078901234','مريم محمد','01021098765','سارة علي','01017171717',2500000.00,2800000.00,27777.78,31111.11,29.960700,31.262700,2020,'الدور 6',85.00,5.56,'وكيل'),
(18,6,'مختبر تحاليل طبية','مختبر تحاليل طبية مجهز بالكامل','مدينة نصر, القاهرة',65.00,NULL,'available','Resale','2025-08-24 13:10:44',1,'غرفة',2,NULL,NULL,NULL,NULL,'مختبرات المدينة الطبية',NULL,NULL,NULL,'مدينة نصر','القاهرة','سوديك',4,'أحمد علي','01089012345','نادية كريم','01010987654','محمد فاطمة','01018181818',1800000.00,NULL,27692.31,NULL,30.056100,31.330000,2018,'الدور 4',60.00,7.69,'مالك'),
(19,6,'صيدلية حديثة','صيدلية حديثة مع مخزن مجهز','التجمع الخامس, القاهرة',45.00,NULL,'rented','Primary','2025-08-24 13:10:44',2,'استوديو',3,NULL,15.00,6,'2025-01-10','صيدليات التجمع',40.00,50.00,45.00,'التجمع الخامس','القاهرة','إعمار مصر',2,'محمد كريم','01090123456','سارة أحمد','01009876543','كريم نورا','01019191919',1200000.00,1350000.00,26666.67,30000.00,30.016700,31.261300,2019,'الدور 2',42.00,6.67,'وسيط'),
(20,5,'جناح فندقي فاخر','جناح فندقي فاخر مع إطلالة على النيل','وسط البلد, القاهرة',150.00,NULL,'available','Primary','2025-08-24 13:10:44',1,'فيلا توين هاوس',3,NULL,30.00,4,'2025-06-01','جراند أوتيل القاهرة',140.00,160.00,150.00,'وسط البلد','القاهرة','مجموعة طلعت مصطفى',25,'كريم علي','01001234567','ليلى محمد','01098765432','أحمد سارة','01020202020',3500000.00,4000000.00,23333.33,26666.67,30.044400,31.235700,2021,'الدور 25',140.00,6.67,'شركة مطورة'),
(21,5,'شقة فندقية مميزة','شقة فندقية مع خدمات فاخرة','المعادي, القاهرة',110.00,NULL,'sold','Resale','2025-08-24 13:10:44',2,'غرفة',2,NULL,NULL,NULL,NULL,'ريزيدنس المعادي',NULL,NULL,NULL,'المعادي','القاهرة','سوديك',15,'علي أحمد','01012345678','رانيا كريم','01087654321','محمد علي','01021212121',2200000.00,NULL,20000.00,NULL,29.960700,31.262700,2017,'الدور 15',100.00,9.09,'بواب'),
(22,5,'جناح تنفيذي في فندق 5 نجوم','جناح تنفيذي مع إطلالة على الأهرامات','الجيزة, الجيزة',180.00,NULL,'available','Primary','2025-08-24 13:10:44',1,'غرفتين',3,NULL,25.00,5,'2025-03-20','فندق الأهرامات',170.00,190.00,180.00,'الجيزة','الجيزة','إعمار مصر',20,'أحمد محمد','01023456789','نورا علي','01076543210','كريم فاطمة','01022222222',4500000.00,5000000.00,25000.00,27777.78,29.979200,31.134200,2022,'الدور 20',170.00,5.56,'شركة تشطيب'),
(23,1,'شقة عائلية في كمبوند آمن','شقة 4 غرف نوم مع تشطيب كامل','مدينة الرحاب, القاهرة',200.00,4,'available','Primary','2025-08-24 13:10:44',2,'استوديو',3,60000.00,12.00,8,'2025-08-15','كمبوند الرحاب',180.00,220.00,200.00,'مدينة الرحاب','القاهرة','سوديك',4,'محمد علي','01034567890','فاطمة أحمد','01065432109','سارة محمد','01023232323',3200000.00,3600000.00,16000.00,18000.00,30.063800,31.490000,2020,'الدور 4',190.00,5.00,'مالك'),
(24,1,'فيلا كلاسيكية في ضاحية راقية','فيلا 5 غرف نوم مع حديقة إنجليزية','المعادي, القاهرة',400.00,5,'rented','Resale','2025-08-24 13:10:44',1,'غرفة',1,90000.00,NULL,NULL,NULL,'المعادي جاردنز',NULL,NULL,NULL,'المعادي','القاهرة','مجموعة طلعت مصطفى',2,'كريم محمد','01045678901','سلمى علي','01054321098','أحمد نورا','01024242424',6500000.00,NULL,16250.00,NULL,29.960700,31.262700,2016,NULL,380.00,5.00,'وسيط'),
(25,1,'شقة مميزة في برج سكني فاخر','شقة 3 غرف نوم مع إطلالة على النيل','زمالك, القاهرة',160.00,3,'available','Primary','2025-08-24 13:10:44',2,'استوديو',3,NULL,15.00,7,'2025-05-10','زمالك تاورز',150.00,170.00,160.00,'زمالك','القاهرة','إعمار مصر',18,'علي كريم','01056789012','مريم أحمد','01043210987','محمد سارة','01025252525',3800000.00,4200000.00,23750.00,26250.00,30.062600,31.223400,2019,'الدور 18',150.00,6.25,'شركة مطورة'),
(26,1,'دوبلكس فاخر في كمبوند مغلق','دوبلكس 6 غرف نوم مع تشطيب فاخر','التجمع الأول, القاهرة',350.00,6,'sold','Primary','2025-08-24 13:10:44',1,'غرفتين',3,80000.00,18.00,9,'2024-12-20','كمبوند التجمع الأول',330.00,370.00,350.00,'التجمع الأول','القاهرة','سوديك',3,'أحمد علي','01067890123','رانيا محمد','01032109876','كريم علي','01026262626',5500000.00,6000000.00,15714.29,17142.86,30.030900,31.469700,2017,'الدور 3',335.00,4.29,'وكيل'),
(27,1,'شقة أنيقة في حي سكني هادئ','شقة 2 غرف نوم مع تشطيب كامل','مدينة نصر, القاهرة',100.00,2,'available','Resale','2025-08-24 13:10:44',2,'استوديو',2,NULL,NULL,NULL,NULL,'مدينة نصر بارك',NULL,NULL,NULL,'مدينة نصر','القاهرة','مجموعة طلعت مصطفى',6,'محمد أحمد','01078901234','هبة علي','01021098765','فاطمة محمد','01027272727',1600000.00,NULL,16000.00,NULL,30.056100,31.330000,2018,'الدور 6',95.00,5.00,'بواب'),
(28,1,'فيلا مستقلة في ضاحية خضراء','فيلا 4 غرف نوم مع حديقة خاصة','6 أكتوبر, الجيزة',280.00,4,'available','Primary','2025-08-24 13:10:44',1,'غرفة',3,70000.00,14.00,8,'2025-07-01','أكتوبر جاردنز',260.00,300.00,280.00,'6 أكتوبر','الجيزة','إعمار مصر',2,'كريم محمد','01089012345','نورا أحمد','01010987654','سارة كريم','01028282828',4800000.00,5300000.00,17142.86,18928.57,29.936000,30.913000,2021,NULL,270.00,3.57,'شركة تشطيب'),
(29,1,'شقة فاخرة في برج سكني مميز','شقة 3 غرف نوم مع إطلالة على المدينة','مدينة نصر, القاهرة',140.00,3,'available','Primary','2025-08-24 13:10:44',2,'استوديو',3,NULL,16.00,6,'2025-04-05','مدينة نصر تاورز',130.00,150.00,140.00,'مدينة نصر','القاهرة','سوديك',12,'علي كريم','01090123456','سارة محمد','01009876543','أحمد فاطمة','01029292929',2800000.00,3200000.00,20000.00,22857.14,30.056100,31.330000,2019,'الدور 12',130.00,7.14,'شركة مطورة'),
(30,1,'فيلا كلاسيكية في حي راقي','فيلا 5 غرف نوم مع حديقة إنجليزية','المعادي, القاهرة',450.00,5,'rented','Resale','2025-08-24 13:10:44',1,'غرفة',1,100000.00,NULL,NULL,NULL,'المعادي كلاسيك',NULL,NULL,NULL,'المعادي','القاهرة','مجموعة طلعت مصطفى',2,'أحمد علي','01001234567','ليلى كريم','01098765432','محمد سارة','01030303030',7200000.00,NULL,16000.00,NULL,29.960700,31.262700,2016,NULL,430.00,4.44,'مالك');

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
  `video_id` int(11) NOT NULL,
  `property_id` int(11) NOT NULL,
  `file_path` varchar(255) NOT NULL,
  `upload_date` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Indexes for dumped tables
--

-- Indexes for table `employees`
ALTER TABLE `employees`
  ADD PRIMARY KEY (`EmployeeID`),
  ADD UNIQUE KEY `Username` (`Username`);

-- Indexes for table `propertytypes`
ALTER TABLE `propertytypes`
  ADD PRIMARY KEY (`PropertyTypeID`),
  ADD UNIQUE KEY `TypeName` (`TypeName`);

-- Indexes for table `propertyfinishingtypes`
ALTER TABLE `propertyfinishingtypes`
  ADD PRIMARY KEY (`FinishingID`),
  ADD UNIQUE KEY `FinishingName` (`FinishingName`);

-- Indexes for table `properties`
ALTER TABLE `properties`
  ADD PRIMARY KEY (`PropertyID`),
  ADD KEY `FK_PropertyType` (`PropertyTypeID`),
  ADD KEY `FK_EmployeeAdded` (`EmployeeID`),
  ADD KEY `FK_FinishingType` (`finishing_type_id`);

-- Indexes for table `property_audio_notes`
ALTER TABLE `property_audio_notes`
  ADD PRIMARY KEY (`note_id`),
  ADD KEY `property_id` (`property_id`);

-- Indexes for table `property_images`
ALTER TABLE `property_images`
  ADD PRIMARY KEY (`image_id`),
  ADD KEY `property_id` (`property_id`);

-- Indexes for table `property_videos`
ALTER TABLE `property_videos`
  ADD PRIMARY KEY (`video_id`),
  ADD KEY `property_id` (`property_id`);

-- --------------------------------------------------------

--
-- AUTO_INCREMENT for dumped tables
--

-- AUTO_INCREMENT for table `employees`
ALTER TABLE `employees`
  MODIFY `EmployeeID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

-- AUTO_INCREMENT for table `propertytypes`
ALTER TABLE `propertytypes`
  MODIFY `PropertyTypeID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

-- AUTO_INCREMENT for table `propertyfinishingtypes`
ALTER TABLE `propertyfinishingtypes`
  MODIFY `FinishingID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

-- AUTO_INCREMENT for table `properties`
ALTER TABLE `properties`
  MODIFY `PropertyID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=31;

-- AUTO_INCREMENT for table `property_audio_notes`
ALTER TABLE `property_audio_notes`
  MODIFY `note_id` int(11) NOT NULL AUTO_INCREMENT;

-- AUTO_INCREMENT for table `property_images`
ALTER TABLE `property_images`
  MODIFY `image_id` int(11) NOT NULL AUTO_INCREMENT;

-- AUTO_INCREMENT for table `property_videos`
ALTER TABLE `property_videos`
  MODIFY `video_id` int(11) NOT NULL AUTO_INCREMENT;

-- --------------------------------------------------------

--
-- Constraints for dumped tables
--

-- Constraints for table `properties`
ALTER TABLE `properties`
  ADD CONSTRAINT `FK_EmployeeAdded` FOREIGN KEY (`EmployeeID`) REFERENCES `employees` (`EmployeeID`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `FK_FinishingType` FOREIGN KEY (`finishing_type_id`) REFERENCES `propertyfinishingtypes` (`FinishingID`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `FK_PropertyType` FOREIGN KEY (`PropertyTypeID`) REFERENCES `propertytypes` (`PropertyTypeID`) ON UPDATE CASCADE;

-- Constraints for table `property_audio_notes`
ALTER TABLE `property_audio_notes`
  ADD CONSTRAINT `property_audio_notes_ibfk_1` FOREIGN KEY (`property_id`) REFERENCES `properties` (`PropertyID`) ON DELETE CASCADE;

-- Constraints for table `property_images`
ALTER TABLE `property_images`
  ADD CONSTRAINT `property_images_ibfk_1` FOREIGN KEY (`property_id`) REFERENCES `properties` (`PropertyID`) ON DELETE CASCADE;

-- Constraints for table `property_videos`
ALTER TABLE `property_videos`
  ADD CONSTRAINT `property_videos_ibfk_1` FOREIGN KEY (`property_id`) REFERENCES `properties` (`PropertyID`) ON DELETE CASCADE;

COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;