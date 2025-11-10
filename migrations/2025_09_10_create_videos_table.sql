-- Phase 3: Create property_videos table for video uploads
-- Execute this SQL statement to create the property_videos table

CREATE TABLE `property_videos` (
  `VideoID` int(11) NOT NULL AUTO_INCREMENT,
  `PropertyID` int(11) NOT NULL,
  `VideoPath` varchar(500) NOT NULL,
  `VideoName` varchar(255) NOT NULL,
  `VideoSize` int(11) NOT NULL,
  `UploadDate` timestamp NOT NULL DEFAULT current_timestamp(),
  `EmployeeID` int(11) DEFAULT NULL,
  PRIMARY KEY (`VideoID`),
  KEY `PropertyID` (`PropertyID`),
  KEY `EmployeeID` (`EmployeeID`),
  CONSTRAINT `property_videos_ibfk_1` FOREIGN KEY (`PropertyID`) REFERENCES `properties` (`PropertyID`) ON DELETE CASCADE,
  CONSTRAINT `property_videos_ibfk_2` FOREIGN KEY (`EmployeeID`) REFERENCES `employees` (`EmployeeID`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;