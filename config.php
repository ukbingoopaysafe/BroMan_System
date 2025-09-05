<?php
// Session timeout in seconds (e.g., 30 minutes)
define("SESSION_TIMEOUT", 1800);

// Absolute session timeout in seconds (e.g., 8 hours)
define("SESSION_ABSOLUTE_TIMEOUT", 28800);

// Session warning time in seconds (e.g., 5 minutes before timeout)
define("SESSION_WARNING_TIME", 300);

// Google Maps API key
// Set via environment variable GOOGLE_MAPS_API_KEY or hard-code here for local dev.
if (!defined('GOOGLE_MAPS_API_KEY')) {
	$envKey = getenv('GOOGLE_MAPS_API_KEY');
	define('GOOGLE_MAPS_API_KEY', $envKey !== false ? $envKey : 'YOUR_GOOGLE_MAPS_API_KEY_HERE');
}
?>

