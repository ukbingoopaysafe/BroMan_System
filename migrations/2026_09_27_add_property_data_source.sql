-- Free-text source of the property's data, distinct from EmployeeID which
-- remains the employee who created the record in the system.
ALTER TABLE `Properties`
    ADD COLUMN `property_data_source` VARCHAR(255) NULL AFTER `EmployeeID`;
