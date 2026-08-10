<?php
header('Content-Type: application/json; charset=utf-8');

$rawUrl = trim($_REQUEST['url'] ?? '');

if (empty($rawUrl)) {
    echo json_encode(['success' => false, 'message' => 'الرابط غير موجود']);
    exit;
}

if (!preg_match('#^https?://#i', $rawUrl)) {
    $rawUrl = 'https://' . $rawUrl;
}

function extractCoordsFromText($text) {
    if (empty($text)) return null;

    // Pattern 1: !3dlat!4dlng (Google Maps data parameter - highest precision for place pin)
    if (preg_match('/!3d([0-9.+-]+)!4d([0-9.+-]+)/i', $text, $matches)) {
        $lat = floatval($matches[1]);
        $lng = floatval($matches[2]);
        if ($lat >= -90 && $lat <= 90 && $lng >= -180 && $lng <= 180) {
            return ['lat' => $lat, 'lng' => $lng];
        }
    }

    // Pattern 2: @lat,lng (e.g. /@30.0404482,31.2073201)
    if (preg_match('/@([0-9.+-]+),([0-9.+-]+)/i', $text, $matches)) {
        $lat = floatval($matches[1]);
        $lng = floatval($matches[2]);
        if ($lat >= -90 && $lat <= 90 && $lng >= -180 && $lng <= 180) {
            return ['lat' => $lat, 'lng' => $lng];
        }
    }

    // Pattern 3: query=lat,lng or q=lat,lng or ll=lat,lng or center=lat,lng
    if (preg_match('/[?&](?:query|q|ll|center)=\s*([0-9.+-]+)(?:%2C|,)\s*([0-9.+-]+)/i', $text, $matches)) {
        $lat = floatval($matches[1]);
        $lng = floatval($matches[2]);
        if ($lat >= -90 && $lat <= 90 && $lng >= -180 && $lng <= 180) {
            return ['lat' => $lat, 'lng' => $lng];
        }
    }

    // Pattern 4: /search/lat,lng or /dir/lat,lng or /place/lat,lng
    if (preg_match('#/(?:search|dir|place)/([0-9.+-]+),(?:%2C|,)?\s*([0-9.+-]+)#i', $text, $matches)) {
        $lat = floatval($matches[1]);
        $lng = floatval($matches[2]);
        if ($lat >= -90 && $lat <= 90 && $lng >= -180 && $lng <= 180) {
            return ['lat' => $lat, 'lng' => $lng];
        }
    }

    return null;
}

// First try parsing directly without cURL
$coords = extractCoordsFromText($rawUrl);

if (!$coords) {
    // Resolve redirected URL via cURL
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $rawUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_MAXREDIRS, 10);
    curl_setopt($ch, CURLOPT_TIMEOUT, 12);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36');

    $htmlBody = curl_exec($ch);
    $effectiveUrl = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
    curl_close($ch);

    if ($effectiveUrl) {
        $coords = extractCoordsFromText($effectiveUrl);
    }

    if (!$coords && $htmlBody) {
        $coords = extractCoordsFromText($htmlBody);
    }
}

if ($coords) {
    echo json_encode([
        'success' => true,
        'lat' => $coords['lat'],
        'lng' => $coords['lng']
    ], JSON_UNESCAPED_UNICODE);
} else {
    echo json_encode([
        'success' => false,
        'message' => 'لم يتم العثور على إحداثيات في الرابط. الرجاء التحقق من أن الرابط من خرائط جوجل ويتضمن إحداثيات.'
    ], JSON_UNESCAPED_UNICODE);
}
