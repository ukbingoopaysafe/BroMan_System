<?php
// Session timeout in seconds (e.g., 30 minutes)
define("SESSION_TIMEOUT", 1800);

// Absolute session timeout in seconds (e.g., 8 hours)
define("SESSION_ABSOLUTE_TIMEOUT", 28800);

// Session warning time in seconds (e.g., 5 minutes before timeout)
define("SESSION_WARNING_TIME", 300);

// Google Maps API key
// Set this constant to your Google Maps JavaScript API key to enable interactive maps.
// When left empty, map components will display an error message prompting for configuration.
if (!defined('GOOGLE_MAPS_API_KEY')) {
	define('GOOGLE_MAPS_API_KEY', 'AIzaSyBVFF3AWBPDX7edG3_ezHiTDE6mIoXEUGk');
}
?>

