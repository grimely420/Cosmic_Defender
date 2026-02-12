<?php
/**
 * Google AdSense Configuration
 * 
 * Include this file where you want to display ads
 * AdSense publisher ID is loaded from environment configuration
 */

// Load configuration to get AdSense Publisher ID
require_once __DIR__ . '/config.php';

// Get AdSense Publisher ID from environment with fallback
define('ADSENSE_PUBLISHER_ID', getConfig('ADSENSE_PUBLISHER_ID', 'ca-pub-default'));

/**
 * Function to display AdSense ad unit
 * 
 * @param string $adSlot The ad slot ID from your AdSense account
 * @param string $adFormat The ad format (auto, rectangle, etc.)
 * @param bool $responsive Whether the ad should be responsive
 */
function displayAdsenseAd($adSlot, $adFormat = 'auto', $responsive = true) {
    $publisherId = ADSENSE_PUBLISHER_ID;
    
    $html = '<!-- Google AdSense -->';
    $html .= '<ins class="adsbygoogle"';
    $html .= ' style="display:block"';
    
    if ($responsive) {
        $html .= ' data-ad-format="' . $adFormat . '"';
        $html .= ' data-full-width-responsive="true"';
    }
    
    $html .= ' data-ad-client="' . $publisherId . '"';
    $html .= ' data-ad-slot="' . $adSlot . '"></ins>';
    $html .= '<script>';
    $html .= '(adsbygoogle = window.adsbygoogle || []).push({});';
    $html .= '</script>';
    
    echo $html;
}

/**
 * Function to include AdSense script in the head section
 * Call this once in your HTML head
 */
function includeAdsenseScript() {
    $publisherId = ADSENSE_PUBLISHER_ID;
    echo '<script async src="https://pagead2.googlesyndication.com/pagead/js/adsbygoogle.js?client=' . $publisherId . '" crossorigin="anonymous"></script>';
}
?>
