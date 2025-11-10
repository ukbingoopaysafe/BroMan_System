-- Phase 4: Phone-based duplicate detection (indexes + view)

-- 1) Add indexes on phone columns for fast lookup and grouping
ALTER TABLE `properties`
  ADD INDEX `idx_properties_seller_phone` (`seller_phone`),
  ADD INDEX `idx_properties_sales_manager_phone` (`sales_manager_phone`),
  ADD INDEX `idx_properties_salesperson_phone` (`salesperson_phone`);

-- 2) Helper view: flatten all phones into one column with normalization
--    Normalization applied:
--      - Remove spaces, dashes, parentheses, plus
--      - Convert Egyptian +20/0020 prefix to 0
--      - Trim leading zeros down to a single leading zero when present

DROP VIEW IF EXISTS `v_property_phones_normalized`;
CREATE VIEW `v_property_phones_normalized` AS
SELECT p.PropertyID,
       'seller' AS phone_role,
       CASE
         WHEN p.seller_phone IS NULL OR p.seller_phone = '' THEN NULL
         ELSE (
           -- basic cleanup
           -- step1: strip formatting characters
           -- step2: unify country prefix to local 0 for Egypt
           -- step3: collapse multiple leading zeros to one
           -- Note: MySQL lacks full regex replace; this is a practical approach
           -- Remove spaces and symbols
           REPLACE(
             REPLACE(
               REPLACE(
                 REPLACE(
                   REPLACE(
                     -- unify country code first
                     CASE
                       WHEN LEFT(p.seller_phone, 4) = '+20' THEN CONCAT('0', SUBSTRING(p.seller_phone, 4))
                       WHEN LEFT(p.seller_phone, 4) = '0020' THEN CONCAT('0', SUBSTRING(p.seller_phone, 5))
                       ELSE p.seller_phone
                     END
                   , '+', '')
                 , ' ', '')
               , '-', '')
             , '(', '')
           , ')', '')
         )
       END AS normalized_phone
FROM properties p
UNION ALL
SELECT p.PropertyID,
       'sales_manager' AS phone_role,
       CASE
         WHEN p.sales_manager_phone IS NULL OR p.sales_manager_phone = '' THEN NULL
         ELSE (
           REPLACE(
             REPLACE(
               REPLACE(
                 REPLACE(
                   REPLACE(
                     CASE
                       WHEN LEFT(p.sales_manager_phone, 4) = '+20' THEN CONCAT('0', SUBSTRING(p.sales_manager_phone, 4))
                       WHEN LEFT(p.sales_manager_phone, 4) = '0020' THEN CONCAT('0', SUBSTRING(p.sales_manager_phone, 5))
                       ELSE p.sales_manager_phone
                     END
                   , '+', '')
                 , ' ', '')
               , '-', '')
             , '(', '')
           , ')', '')
         )
       END AS normalized_phone
FROM properties p
UNION ALL
SELECT p.PropertyID,
       'salesperson' AS phone_role,
       CASE
         WHEN p.salesperson_phone IS NULL OR p.salesperson_phone = '' THEN NULL
         ELSE (
           REPLACE(
             REPLACE(
               REPLACE(
                 REPLACE(
                   REPLACE(
                     CASE
                       WHEN LEFT(p.salesperson_phone, 4) = '+20' THEN CONCAT('0', SUBSTRING(p.salesperson_phone, 4))
                       WHEN LEFT(p.salesperson_phone, 4) = '0020' THEN CONCAT('0', SUBSTRING(p.salesperson_phone, 5))
                       ELSE p.salesperson_phone
                     END
                   , '+', '')
                 , ' ', '')
               , '-', '')
             , '(', '')
           , ')', '')
         )
       END AS normalized_phone
FROM properties p;

-- 3) Duplicate groups view: phones seen on 2+ properties
DROP VIEW IF EXISTS `v_duplicate_property_phone_groups`;
CREATE VIEW `v_duplicate_property_phone_groups` AS
SELECT normalized_phone,
       COUNT(DISTINCT PropertyID) AS properties_count,
       GROUP_CONCAT(DISTINCT PropertyID ORDER BY PropertyID SEPARATOR ',') AS property_ids,
       GROUP_CONCAT(DISTINCT phone_role ORDER BY phone_role SEPARATOR ',') AS phone_roles
FROM v_property_phones_normalized
WHERE normalized_phone IS NOT NULL AND normalized_phone <> ''
GROUP BY normalized_phone
HAVING COUNT(DISTINCT PropertyID) > 1;


