-- Migration: create property_attachments table
-- Run this SQL in phpMyAdmin or your MySQL client
CREATE TABLE IF NOT EXISTS `property_attachments` (
  `AttachmentID` int(11) NOT NULL AUTO_INCREMENT,
  `PropertyID` int(11) NOT NULL,
  `FileName` varchar(255) NOT NULL,
  `FilePath` varchar(1024) NOT NULL,
  `FileType` varchar(50) DEFAULT NULL,
  `FileSize` bigint(20) DEFAULT NULL,
  `EmployeeID` int(11) DEFAULT NULL,
  `UploadedAt` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`AttachmentID`),
  KEY `PropertyID` (`PropertyID`),
  KEY `EmployeeID` (`EmployeeID`),
  CONSTRAINT `property_attachments_ibfk_1` FOREIGN KEY (`PropertyID`) REFERENCES `Properties` (`PropertyID`) ON DELETE CASCADE,
  CONSTRAINT `property_attachments_ibfk_2` FOREIGN KEY (`EmployeeID`) REFERENCES `employees` (`EmployeeID`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Example: grant file storage directory (run on server, not in SQL): ensure folder uploads/attachments exists and is writable by web server
