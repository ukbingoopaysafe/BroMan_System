-- إضافة حقول الإيجار
ALTER TABLE properties
    ADD COLUMN monthly_rent DECIMAL(15,2) NULL AFTER price_installments,
    ADD COLUMN yearly_rent  DECIMAL(15,2) NULL AFTER monthly_rent;

-- للتراجع (اختياري)
-- ALTER TABLE properties DROP COLUMN yearly_rent, DROP COLUMN monthly_rent;