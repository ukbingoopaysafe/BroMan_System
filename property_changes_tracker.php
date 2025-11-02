<?php
function track_property_changes($old_data, $new_data) {
    $changes = [];
    $fields_ar = [
        'Title' => 'العنوان',
        'Description' => 'الوصف',
        'Location' => 'الموقع',
        'Area' => 'المساحة',
        'Status' => 'الحالة',
        'unit_design' => 'تصميم الوحدة',
        'compound_area' => 'مساحة المجمع',
        'down_payment_percentage' => 'نسبة المقدم',
        'payment_years' => 'سنوات السداد',
        'delivery_date' => 'تاريخ التسليم',
        'ProjectName' => 'اسم المشروع',
        'min_area' => 'الحد الأدنى للمساحة',
        'max_area' => 'الحد الأقصى للمساحة',
        'avg_area' => 'متوسط المساحة',
        'district' => 'المنطقة',
        'governorate' => 'المحافظة',
        'company_name' => 'اسم الشركة',
        'FloorsCount' => 'عدد الطوابق',
        'sales_manager_name' => 'اسم مدير المبيعات',
        'sales_manager_phone' => 'هاتف مدير المبيعات',
        'salesperson_name' => 'اسم مندوب المبيعات',
        'salesperson_phone' => 'هاتف مندوب المبيعات',
        'year_built' => 'سنة البناء',
        'unit_floor' => 'الطابق',
        'net_area' => 'المساحة الصافية',
        'loading_percentage' => 'نسبة التحميل',
        'price_cash' => 'السعر النقدي',
        'price_installments' => 'السعر بالتقسيط',
        'meter_price_cash' => 'سعر المتر النقدي',
        'meter_price_installments' => 'سعر المتر بالتقسيط',
        'seller_type' => 'نوع البائع',
        'seller_name' => 'اسم البائع',
        'seller_phone' => 'هاتف البائع'
    ];

    foreach ($fields_ar as $field => $label) {
        // Use raw values first (may be null)
        $old_raw = array_key_exists($field, $old_data) ? $old_data[$field] : null;
        $new_raw = array_key_exists($field, $new_data) ? $new_data[$field] : null;

        // Normalize empty strings to null for consistent comparison
        if ($old_raw === '') $old_raw = null;
        if ($new_raw === '') $new_raw = null;

        // If both are null/empty, nothing changed
        if ($old_raw === null && $new_raw === null) {
            continue;
        }

        // If both are numeric-ish, compare numerically to avoid false positives from formatting
        $old_is_num = is_numeric($old_raw);
        $new_is_num = is_numeric($new_raw);

        if ($old_is_num || $new_is_num) {
            // Convert null to 0? No — keep null distinct. Use floatval only when not null.
            $old_num = $old_raw === null ? null : floatval($old_raw);
            $new_num = $new_raw === null ? null : floatval($new_raw);

            // If both numbers and equal, skip
            if ($old_num !== null && $new_num !== null && abs($old_num - $new_num) < 0.0001) {
                continue;
            }
        } else {
            // Non-numeric comparison: if identical strings, skip
            if ($old_raw !== null && $new_raw !== null && (string)$old_raw === (string)$new_raw) {
                continue;
            }
        }

        // Prepare human-friendly formatted values
        $format_value = function($val, $field) {
            if ($val === null) return '';
            // Numeric formatting
            if (is_numeric($val)) {
                if (strpos($field, 'price') !== false || $field === 'meter_price_cash' || $field === 'meter_price_installments') {
                    return number_format(floatval($val), 2) . ' جنيه';
                }
                if (strpos($field, 'area') !== false || $field === 'net_area' || $field === 'compound_area') {
                    return number_format(floatval($val), 2) . ' متر²';
                }
                if ($field === 'down_payment_percentage' || $field === 'loading_percentage') {
                    return rtrim(rtrim(number_format(floatval($val), 2), '0'), '.') . '%';
                }
                // Default numeric formatting
                return rtrim(rtrim(number_format(floatval($val), 2), '0'), '.');
            }
            // Non-numeric — return trimmed string
            return (string)$val;
        };

        $old_val = $format_value($old_raw, $field);
        $new_val = $format_value($new_raw, $field);

        // Build change message
        if ($old_raw === null && $new_raw !== null) {
            $changes[] = "تمت إضافة {$label}: {$new_val}";
        } elseif ($new_raw === null && $old_raw !== null) {
            $changes[] = "تم حذف {$label} (كانت: {$old_val})";
        } else {
            $changes[] = "تم تغيير {$label} من: {$old_val} إلى: {$new_val}";
        }
    }
    
    return $changes;
}