<?php
function http_get_with_cookie($url, $cookie) {
    $opts = [
        'http' => [
            'method' => 'GET',
            'header' => "Cookie: $cookie\r\n",
            'ignore_errors' => true,
            'timeout' => 10,
        ]
    ];
    $ctx = stream_context_create($opts);
    $data = file_get_contents($url, false, $ctx);
    return [$http_response_header ?? [], $data];
}

// Step 1: get a manager session id
$base = 'http://localhost/broman_system';
$set = $base . '/tools/set_manager_session.php';
$raw = file_get_contents($set);
$j = json_decode($raw, true);
if (!$j) {
    echo "Failed to get session\n";
    exit(1);
}
$sid = $j['session_id'];
$cookie = 'PHPSESSID=' . $sid;

$aid = $argv[1] ?? 26;
$url = $base . '/serve_attachment.php?id=' . intval($aid);
list($hdrs, $data) = http_get_with_cookie($url, $cookie);

echo "Requesting: $url with cookie $cookie\n";
echo "Response headers:\n";
foreach ($hdrs as $h) echo $h . "\n";
if ($data === false) {
    echo "No body\n";
} else {
    echo "Body length: " . strlen($data) . " bytes\n";
    // show first few bytes
    echo "First bytes (hex): " . substr(bin2hex(substr($data,0,16)),0,128) . "\n";
}
?>