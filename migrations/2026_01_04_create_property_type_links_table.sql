-- Migration: Create property_type_links table for many-to-many relationship
-- Created: 2026-01-04

CREATE TABLE IF NOT EXISTS property_type_links (
    property_id INT NOT NULL,
    property_type_id INT NOT NULL,
    PRIMARY KEY (property_id, property_type_id),
    CONSTRAINT fk_property_type_links_property FOREIGN KEY (property_id) REFERENCES Properties(PropertyID) ON DELETE CASCADE,
    CONSTRAINT fk_property_type_links_type FOREIGN KEY (property_type_id) REFERENCES PropertyTypes(PropertyTypeID) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Migrate existing data from Properties.PropertyTypeID to the new join table
INSERT IGNORE INTO property_type_links (property_id, property_type_id)
SELECT PropertyID, PropertyTypeID FROM Properties WHERE PropertyTypeID IS NOT NULL;
