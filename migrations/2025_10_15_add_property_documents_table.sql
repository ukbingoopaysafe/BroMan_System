-- Migration: create property_documents table for storing user-visible PDFs/documents attached to properties
-- Run this in your database (adjust types/engine as needed)

CREATE TABLE IF NOT EXISTS property_documents (
  document_id INT AUTO_INCREMENT PRIMARY KEY,
  property_id INT NOT NULL,
  file_path VARCHAR(512) NOT NULL,
  file_name VARCHAR(255) NOT NULL,
  file_size BIGINT DEFAULT NULL,
  uploaded_by INT DEFAULT NULL,
  upload_date DATETIME DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_property_id (property_id),
  FOREIGN KEY (property_id) REFERENCES Properties(PropertyID) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
