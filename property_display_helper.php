<?php
/**
 * Helper functions for displaying property information in lists and cards.
 * Centralizes logic to ensure consistency across Index, Residential, Coastal, and Commercial pages.
 */

require_once __DIR__ . '/lang/translate.php';

// Function to format price for display
if (!function_exists('formatDisplayPrice')) {
    function formatDisplayPrice($row)
    {
        $curr = isset($row['currency']) ? t($row['currency']) : t('EGP');

        // Rental mode: prioritize rent values
        if (isset($row['listing_type']) && $row['listing_type'] === 'Rental') {
            if (!empty($row['monthly_rent']) && is_numeric($row['monthly_rent'])) {
                return number_format(floatval($row['monthly_rent']), 0, '.', ',') . ' ' . $curr . ' / ' . t('per_month');
            } elseif (!empty($row['daily_rent']) && is_numeric($row['daily_rent'])) {
                return number_format(floatval($row['daily_rent']), 0, '.', ',') . ' ' . $curr . ' / ' . t('per_day');
            } elseif (!empty($row['yearly_rent']) && is_numeric($row['yearly_rent'])) {
                return number_format(floatval($row['yearly_rent']), 0, '.', ',') . ' ' . $curr . ' / ' . t('per_year');
            }
        }
        // Prefer cash price, then installments price
        if (!empty($row['price_cash']) && is_numeric($row['price_cash'])) {
            return number_format(floatval($row['price_cash']), 0, '.', ',') . ' ' . $curr;
        } elseif (!empty($row['price_installments']) && is_numeric($row['price_installments'])) {
            return number_format(floatval($row['price_installments']), 0, '.', ',') . ' ' . $curr;
        } elseif (!empty($row['min_price']) && is_numeric($row['min_price']) && !empty($row['max_price']) && is_numeric($row['max_price'])) {
            return number_format(floatval($row['min_price']), 0, '.', ',') . ' - ' . number_format(floatval($row['max_price']), 0, '.', ',') . ' ' . $curr;
        } elseif (!empty($row['min_price']) && is_numeric($row['min_price'])) {
            return t('starts_from') . ' ' . number_format(floatval($row['min_price']), 0, '.', ',') . ' ' . $curr;
        } elseif (!empty($row['max_price']) && is_numeric($row['max_price'])) {
            return t('up_to') . ' ' . number_format(floatval($row['max_price']), 0, '.', ',') . ' ' . $curr;
        } elseif (!empty($row['min_meter_price']) && is_numeric($row['min_meter_price']) && !empty($row['max_meter_price']) && is_numeric($row['max_meter_price'])) {
            return number_format(floatval($row['min_meter_price']), 0, '.', ',') . ' - ' . number_format(floatval($row['max_meter_price']), 0, '.', ',') . ' ' . $curr . ' / ' . t('sqm');
        } elseif (!empty($row['meter_price_cash']) && is_numeric($row['meter_price_cash']) && !empty($row['Area']) && is_numeric($row['Area'])) {
            $est = floatval($row['meter_price_cash']) * floatval($row['Area']);
            return t('estimated') . ': ' . number_format($est, 0, '.', ',') . ' ' . $curr;
        } elseif (!empty($row['meter_price_installments']) && is_numeric($row['meter_price_installments']) && !empty($row['Area']) && is_numeric($row['Area'])) {
            $est = floatval($row['meter_price_installments']) * floatval($row['Area']);
            return t('estimated') . ': ' . number_format($est, 0, '.', ',') . ' ' . $curr;
        }
        return t('price_not_specified');
    }
}

// Function to format area for display
if (!function_exists('formatDisplayArea')) {
    function formatDisplayArea($row)
    {
        if (!empty($row['Area']) && is_numeric($row['Area'])) {
            return number_format(floatval($row['Area']), 0, '.', ',') . ' ' . t('sqm');
        } elseif (!empty($row['net_area']) && is_numeric($row['net_area'])) {
            return number_format(floatval($row['net_area']), 0, '.', ',') . ' ' . t('sqm');
        } elseif (!empty($row['min_area']) && is_numeric($row['min_area']) && !empty($row['max_area']) && is_numeric($row['max_area'])) {
            return number_format(floatval($row['min_area']), 0, '.', ',') . ' - ' . number_format(floatval($row['max_area']), 0, '.', ',') . ' ' . t('sqm');
        } elseif (!empty($row['min_area']) && is_numeric($row['min_area'])) {
            return t("starts_from") . " " . number_format(floatval($row["min_area"]), 0, ".", ",") . " " . t("sqm");
        } elseif (!empty($row['max_area']) && is_numeric($row['max_area'])) {
            return t("up_to") . " " . number_format(floatval($row["max_area"]), 0, ".", ",") . " " . t("sqm");
        }
        return t('area_not_specified');
    }
}

// Function to format payment terms
if (!function_exists('formatPaymentTerms')) {
    function formatPaymentTerms($row)
    {
        $terms = [];
        
        if (!empty($row['down_payment_percentage']) && is_numeric($row['down_payment_percentage'])) {
            $terms[] = number_format($row['down_payment_percentage'], 0) . '% ' . t('down_payment_percentage');
        }
        
        if (!empty($row['payment_years']) && is_numeric($row['payment_years'])) {
            $terms[] = $row['payment_years'] . ' ' . t('years');
        }
        
        return !empty($terms) ? implode(' + ', $terms) : t('undefined');
    }
}

// Function to get status badge HTML
if (!function_exists('getStatusBadge')) {
    function getStatusBadge($status) {
        $status_classes = [
            'available' => 'status-available',
            'sold' => 'status-sold', 
            'rented' => 'status-rented',
            'unavailable' => 'status-unavailable'
        ];
        
        $status_icons = [
            'available' => 'fas fa-check-circle',
            'sold' => 'fas fa-handshake',
            'rented' => 'fas fa-key',
            'unavailable' => 'fas fa-times-circle'
        ];
        
        $class = $status_classes[$status] ?? 'status-unavailable';
        $icon = $status_icons[$status] ?? 'fas fa-question-circle';
        $text = t('status_' . $status);
        
        return "<span class=\"status-badge {$class}\"><i class=\"{$icon}\"></i> {$text}</span>";
    }
}

// Function to get property type badge HTML
if (!function_exists('getPropertyTypeBadge')) {
    function getPropertyTypeBadge($typeId, $typeName) {
        $type_icons = [
            1 => 'fas fa-home',      // سكني
            2 => 'fas fa-umbrella-beach', // ساحلي
            3 => 'fas fa-building',  // إداري
            4 => 'fas fa-store',     // تجاري
            5 => 'fas fa-hotel',     // فندقي
            6 => 'fas fa-hospital'   // طبي
        ];
        
        $icon = $type_icons[$typeId] ?? 'fas fa-landmark';
        
        return "<span class=\"property-type-badge\"><i class=\"{$icon}\"></i> {$typeName}</span>";
    }
}

// Function to get multiple property type badges HTML
if (!function_exists('getPropertyTypeBadges')) {
    function getPropertyTypeBadges($row) {
        if (!empty($row['AllPropertyTypeIDs']) && !empty($row['AllPropertyTypeNames'])) {
            $ids = explode(',', $row['AllPropertyTypeIDs']);
            $names = explode(',', $row['AllPropertyTypeNames']);
            
            $html = "";
            foreach ($ids as $index => $id) {
                $name = isset($names[$index]) ? trim($names[$index]) : "";
                if ($name !== "") {
                    $html .= getPropertyTypeBadge((int)$id, $name);
                }
            }
            return !empty($html) ? $html : getPropertyTypeBadge($row['PropertyTypeID'], $row['PropertyTypeName'] ?? t('undefined'));
        }
        
        // Fallback for pages not yet fetching multiple types
        if (isset($row['PropertyTypeID']) && isset($row['PropertyTypeName'])) {
            return getPropertyTypeBadge($row['PropertyTypeID'], $row['PropertyTypeName']);
        }
        
        return t('undefined');
    }
}

/**
 * Enhanced Unit Model Logic:
 * 1. Checks legacy 'UnitDesignName' (from p.unit_design).
 * 2. If empty, falls back to structured fields (Bedrooms, Bathrooms, etc.).
 * 
 * @param array $row Property data row
 * @return string Display string
 */
if (!function_exists('formatUnitModel')) {
    function formatUnitModel($row) {
        // 1. Try Legacy
        // The query usually aliases p.unit_design as UnitDesignName
        // But we check both just in case
        $legacy = "";
        if (!empty($row['UnitDesignName'])) {
            $legacy = trim($row['UnitDesignName']);
        } elseif (!empty($row['unit_design'])) {
            $legacy = trim($row['unit_design']);
        }

        if ($legacy !== "") {
            return htmlspecialchars($legacy);
        }

        // 2. Fallback to Structured Data
        $parts = [];
        
        // Bedrooms
        if (!empty($row['Bedrooms']) && is_numeric($row['Bedrooms']) && $row['Bedrooms'] > 0) {
            $parts[] = $row['Bedrooms'] . ' ' . t('bedrooms_short');
        }
        
        // Bathrooms
        if (!empty($row['Bathrooms']) && is_numeric($row['Bathrooms']) && $row['Bathrooms'] > 0) {
            $parts[] = $row['Bathrooms'] . ' ' . t('bathrooms_short');
        }

        if (!empty($parts)) {
            // Join with " + " as requested
            return implode(' + ', $parts);
        }

        return t('undefined');
    }
}
