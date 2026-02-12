<?php
/**
 * Security Headers Configuration
 * Include this file at the beginning of your PHP pages to set proper security headers
 */

// Content Security Policy - Allowing necessary external resources
$csp = "default-src 'self'; ";
$csp .= "script-src 'self' ";
$csp .= "https://pagead2.googlesyndication.com ";
$csp .= "https://googleads.g.doubleclick.net ";
$csp .= "https://www.googletagservices.com ";
$csp .= "https://adssettings.google.com ";
$csp .= "https://cdn.jsdelivr.net ";
$csp .= "https://www.google.com/recaptcha/ ";
$csp .= "https://www.gstatic.com/recaptcha/ ";
$csp .= "https://www.google.com ";
$csp .= "https://www.gstatic.com; ";

$csp .= "style-src 'self' 'unsafe-inline' ";
$csp .= "https://cdn.jsdelivr.net ";
$csp .= "https://cdnjs.cloudflare.com ";
$csp .= "https://fonts.googleapis.com ";
$csp .= "https://www.gstatic.com; ";

$csp .= "font-src 'self' ";
$csp .= "https://fonts.gstatic.com ";
$csp .= "https://cdnjs.cloudflare.com ";
$csp .= "data:; ";

$csp .= "img-src 'self' data: https: blob:; ";

$csp .= "frame-src 'self' ";
$csp .= "https://www.google.com ";
$csp .= "https://googleads.g.doubleclick.net ";
$csp .= "https://tpc.googlesyndication.com; ";

$csp .= "connect-src 'self' ";
$csp .= "https://pagead2.googlesyndication.com ";
$csp .= "https://www.google-analytics.com ";
$csp .= "https://googleads.g.doubleclick.net; ";

$csp .= "media-src 'self'; ";
$csp .= "object-src 'none'; ";
$csp .= "base-uri 'self'; ";
$csp .= "form-action 'self'; ";
$csp .= "frame-ancestors 'self';";

// Set security headers
header("Content-Security-Policy: " . $csp);
header("X-Content-Type-Options: nosniff");
header("X-Frame-Options: SAMEORIGIN");
header("X-XSS-Protection: 1; mode=block");
header("Referrer-Policy: strict-origin-when-cross-origin");
header("Permissions-Policy: accelerometer=(), camera=(), geolocation=(), gyroscope=(), magnetometer=(), microphone=(), payment=(), usb=()");

// Set secure session cookie parameters if session is being used
if (session_status() == PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', 1);
    ini_set('session.use_only_cookies', 1);
    
    // Only set secure flag if using HTTPS
    if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
        ini_set('session.cookie_secure', 1);
    }
    
    // Set SameSite attribute for cookies
    ini_set('session.cookie_samesite', 'Strict');
}
?>
