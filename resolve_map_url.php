<?php
header('Content-Type: application/json; charset=utf-8');

$rawUrl = trim($_REQUEST['url'] ?? '');

if (empty($rawUrl)) {
    echo json_encode(['success' => false, 'message' => 'الرابط غير موجود'], JSON_UNESCAPED_UNICODE);
    exit;
}

if (!preg_match('#^https?://#i', $rawUrl)) {
    $rawUrl = 'https://' . $rawUrl;
}

/**
 * Validate latitude and longitude values
 */
if (!function_exists('isValidLatLng')) {
    function isValidLatLng($lat, $lng) {
        return is_numeric($lat) && is_numeric($lng) 
            && $lat >= -90 && $lat <= 90 
            && $lng >= -180 && $lng <= 180 
            && ($lat != 0 || $lng != 0);
    }
}

if (!function_exists('dmsToDecimal')) {
    function dmsToDecimal($deg, $min, $sec, $dir) {
        $dec = floatval($deg) + (floatval($min) / 60) + (floatval($sec) / 3600);
        $dir = strtoupper($dir);
        if ($dir === 'S' || $dir === 'W') {
            $dec = -$dec;
        }
        return $dec;
    }
}

if (!function_exists('parseDirectCoords')) {
    function parseDirectCoords($text) {
        if (empty($text)) return null;

        // Pattern 1: !3dlat!4dlng (Highest precision pin marker in Google Maps URLs)
        if (preg_match('/!3d(-?[0-9]{1,2}\.[0-9]+)!4d(-?[0-9]{1,3}\.[0-9]+)/i', $text, $m)) {
            $lat = floatval($m[1]);
            $lng = floatval($m[2]);
            if (isValidLatLng($lat, $lng)) return ['lat' => $lat, 'lng' => $lng];
        }

        // Pattern 2: query/q/ll/center/saddr/daddr/destination parameters
        if (preg_match('/[?&](?:query|q|ll|center|saddr|daddr|destination|loc|point)=\s*(-?[0-9]{1,2}\.[0-9]+)(?:%2C|,)\s*(-?[0-9]{1,3}\.[0-9]+)/i', $text, $m)) {
            $lat = floatval($m[1]);
            $lng = floatval($m[2]);
            if (isValidLatLng($lat, $lng)) return ['lat' => $lat, 'lng' => $lng];
        }

        // Pattern 3: Waze coordinates lat=...&lng=... or &lon=...
        if (preg_match('/[?&]lat=(-?[0-9]{1,2}\.[0-9]+)[&]l(?:ng|on)=(-?[0-9]{1,3}\.[0-9]+)/i', $text, $m)) {
            $lat = floatval($m[1]);
            $lng = floatval($m[2]);
            if (isValidLatLng($lat, $lng)) return ['lat' => $lat, 'lng' => $lng];
        }

        // Pattern 4: /search/lat,lng or /dir/lat,lng or /place/lat,lng (numeric path coordinates)
        if (preg_match('#/(?:search|dir|place)/(-?[0-9]{1,2}\.[0-9]+),(?:%2C|,)?\s*(-?[0-9]{1,3}\.[0-9]+)#i', $text, $m)) {
            $lat = floatval($m[1]);
            $lng = floatval($m[2]);
            if (isValidLatLng($lat, $lng)) return ['lat' => $lat, 'lng' => $lng];
        }

        // Pattern 5: DMS (Degrees, Minutes, Seconds) format e.g., 30°02'25.6"N 31°12'26.4"E
        if (preg_match('/([0-9]+)[%C2%B0°\s]+([0-9]+)[\'%27\s]+([0-9.]+)[%22"\s]*([NSEW])\s*[,+%\s]+\s*([0-9]+)[%C2%B0°\s]+([0-9]+)[\'%27\s]+([0-9.]+)[%22"\s]*([NSEW])/i', $text, $m)) {
            $lat = dmsToDecimal($m[1], $m[2], $m[3], $m[4]);
            $lng = dmsToDecimal($m[5], $m[6], $m[7], $m[8]);
            if (isValidLatLng($lat, $lng)) return ['lat' => $lat, 'lng' => $lng];
        }

        // Pattern 6: /@lat,lng (Google Maps viewport camera position)
        if (preg_match('/@(-?[0-9]{1,2}\.[0-9]+),(-?[0-9]{1,3}\.[0-9]+)/i', $text, $m)) {
            $lat = floatval($m[1]);
            $lng = floatval($m[2]);
            if (isValidLatLng($lat, $lng)) return ['lat' => $lat, 'lng' => $lng];
        }

        return null;
    }
}

if (!function_exists('extractCid')) {
    function extractCid($url) {
        if (empty($url)) return null;

        // Pattern: !1s0x...:0x[hexCid]
        if (preg_match('/!1s0x[0-9a-fA-F]+:0x([0-9a-fA-F]+)/i', $url, $m)) {
            return base_convert(ltrim($m[1], '0x'), 16, 10);
        }
        // Pattern: cid=0x[hexCid]
        if (preg_match('/[?&]cid=0x([0-9a-fA-F]+)/i', $url, $m)) {
            return base_convert(ltrim($m[1], '0x'), 16, 10);
        }
        // Pattern: cid=[decimal]
        if (preg_match('/[?&]cid=([0-9]+)/i', $url, $m)) {
            return $m[1];
        }

        return null;
    }
}

if (!function_exists('fetchCoordinatesByCid')) {
    function fetchCoordinatesByCid($cidDec) {
        $embedUrl = "https://maps.google.com/maps?cid=" . urlencode($cidDec) . "&output=embed";
        return fetchAndParseEmbed($embedUrl);
    }
}

if (!function_exists('fetchCoordinatesByPlaceName')) {
    function fetchCoordinatesByPlaceName($placeName) {
        $embedUrl = "https://maps.google.com/maps?q=" . urlencode($placeName) . "&output=embed";
        return fetchAndParseEmbed($embedUrl);
    }
}

if (!function_exists('fetchAndParseEmbed')) {
    function fetchAndParseEmbed($url) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_MAXREDIRS, 5);
        curl_setopt($ch, CURLOPT_TIMEOUT, 8);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/122.0.0.0 Safari/537.36');
        $html = curl_exec($ch);
        curl_close($ch);

        if (empty($html)) return null;

        // Direct JSON pair [lat, lng] inside embed initialization state
        if (preg_match_all('/\[\s*(-?[0-9]{1,2}\.[0-9]{4,})\s*,\s*(-?[0-9]{1,3}\.[0-9]{4,})\s*\]/', $html, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $lat = floatval($match[1]);
                $lng = floatval($match[2]);
                if (isValidLatLng($lat, $lng)) {
                    return ['lat' => $lat, 'lng' => $lng];
                }
            }
        }

        // Coordinates in protobuf parameters !2d[lng]!3d[lat] or !3d[lat]!4d[lng]
        if (preg_match('/!2d(-?[0-9.]+)!3d(-?[0-9.]+)/i', $html, $m)) {
            $lat = floatval($m[2]);
            $lng = floatval($m[1]);
            if (isValidLatLng($lat, $lng)) return ['lat' => $lat, 'lng' => $lng];
        }
        if (preg_match('/!3d(-?[0-9.]+)!4d(-?[0-9.]+)/i', $html, $m)) {
            $lat = floatval($m[1]);
            $lng = floatval($m[2]);
            if (isValidLatLng($lat, $lng)) return ['lat' => $lat, 'lng' => $lng];
        }

        return null;
    }
}

// -------------------------------------------------------------
// MAIN RESOLUTION PROCESS
// -------------------------------------------------------------

$coords = null;

// Step 1: Check if raw URL already contains direct coordinates (except pure @ center if it's a shortlink)
$isShortLink = preg_match('#/(?:maps\.app\.goo\.gl|goo\.gl/maps|bit\.ly|tinyurl\.com|t\.co)/#i', $rawUrl);
if (!$isShortLink) {
    $coords = parseDirectCoords($rawUrl);
}

// Step 2: If coordinates not found or it's a short link, follow redirects
if (!$coords) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $rawUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_MAXREDIRS, 10);
    curl_setopt($ch, CURLOPT_TIMEOUT, 12);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/122.0.0.0 Safari/537.36');
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Accept-Language: en-US,en;q=0.9',
        'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
    ]);

    $htmlBody = curl_exec($ch);
    $effectiveUrl = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
    curl_close($ch);

    // Try parsing the redirected URL directly
    if ($effectiveUrl) {
        $coords = parseDirectCoords($effectiveUrl);
    }

    // Step 3: If still no coordinates, check for Google Place CID (e.g. shared business/place)
    if (!$coords) {
        $cid = extractCid($effectiveUrl ?: $rawUrl);
        if ($cid) {
            $coords = fetchCoordinatesByCid($cid);
        }
    }

    // Step 4: If still no coordinates, check for place name in URL (/maps/place/Place+Name)
    if (!$coords && preg_match('#/maps/place/([^/@?]+)#i', $effectiveUrl ?: $rawUrl, $placeMatch)) {
        $placeName = urldecode($placeMatch[1]);
        // Only use if not purely a numeric string
        if (!preg_match('/^[-0-9.,\s+]+$/', $placeName)) {
            $coords = fetchCoordinatesByPlaceName($placeName);
        }
    }
}

// Output JSON response
if ($coords && isValidLatLng($coords['lat'], $coords['lng'])) {
    echo json_encode([
        'success' => true,
        'lat' => round($coords['lat'], 6),
        'lng' => round($coords['lng'], 6)
    ], JSON_UNESCAPED_UNICODE);
} else {
    echo json_encode([
        'success' => false,
        'message' => 'لم يتم العثور على إحداثيات صحيحة في الرابط. يرجى التأكد من الرابط أو النقر مباشرة على الخريطة لتحديد الموقع.'
    ], JSON_UNESCAPED_UNICODE);
}
