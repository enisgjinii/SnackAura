<?php
// settings_fetch.php

// Fetch legal settings (AGB, Impressum, Datenschutzerklärung), social media links, cart settings, and shipping settings
try {
    // Define all required keys
    $legal_keys = ['agb', 'impressum', 'datenschutzerklaerung'];
    $social_keys = ['facebook_link', 'twitter_link', 'instagram_link', 'linkedin_link', 'youtube_link'];
    $cart_keys = ['cart_logo', 'cart_description', 'minimum_order'];
    $shipping_keys = ['shipping_calculation_mode', 'shipping_fee_base', 'shipping_fee_per_km', 'shipping_distance_radius', 'shipping_free_threshold', 'postal_code_zones', 'store_lat', 'store_lng']; // Add shipping-related keys

    // Merge all keys into a single array for a combined query
    $all_keys = array_merge($legal_keys, $social_keys, $cart_keys, $shipping_keys);

    // Create placeholders for the IN clause
    $placeholders = rtrim(str_repeat('?,', count($all_keys)), ',');

    // Prepare the SQL statement
    $stmt = $pdo->prepare("SELECT `key`, `value` FROM `settings` WHERE `key` IN ($placeholders)");

    // Execute the statement with all keys
    $stmt->execute($all_keys);

    // Fetch the results as an associative array
    $settings = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

    // Assign legal settings with default fallbacks
    $agb = $settings['agb'] ?? 'No AGB available.';
    $impressum = $settings['impressum'] ?? 'No Impressum available.';
    $datenschutzerklaerung = $settings['datenschutzerklaerung'] ?? 'No Datenschutzerklärung available.';

    // Assign social media links with default empty strings if not set
    $social_links = [
        'facebook_link' => $settings['facebook_link'] ?? '',
        'twitter_link' => $settings['twitter_link'] ?? '',
        'instagram_link' => $settings['instagram_link'] ?? '',
        'linkedin_link' => $settings['linkedin_link'] ?? '',
        'youtube_link' => $settings['youtube_link'] ?? '',
    ];

    // Assign cart settings with default fallbacks
    $cart_logo = $settings['cart_logo'] ?? ''; // Default empty string
    $cart_description = $settings['cart_description'] ?? ''; // Default empty string
    $minimum_order = $settings['minimum_order'] ?? '0'; // Default to '0' if not set

    // Assign shipping settings
    $current_settings = [
        'shipping_calculation_mode' => $settings['shipping_calculation_mode'] ?? 'radius', // Default to 'radius' if not set
        'shipping_fee_base' => floatval($settings['shipping_fee_base'] ?? 0),
        'shipping_fee_per_km' => floatval($settings['shipping_fee_per_km'] ?? 0),
        'shipping_distance_radius' => floatval($settings['shipping_distance_radius'] ?? 0),
        'shipping_free_threshold' => floatval($settings['shipping_free_threshold'] ?? 9999),
        'postal_code_zones' => $settings['postal_code_zones'] ?? '', // Assuming JSON string
        'store_lat' => floatval($settings['store_lat'] ?? 0),
        'store_lng' => floatval($settings['store_lng'] ?? 0),
    ];

    // Adjust cart_logo path if necessary (remove '../' if present)
    if (!empty($cart_logo)) {
        $cart_logo = str_replace('../', '', $cart_logo);
    }

} catch (PDOException $e) {
    // Log the error with context
    log_error_markdown("Failed to fetch settings: " . $e->getMessage(), "Fetching Settings");

    // Assign default values for legal settings in case of an error
    $agb = 'Error loading AGB.';
    $impressum = 'Error loading Impressum.';
    $datenschutzerklaerung = 'Error loading Datenschutzerklärung.';

    // Assign default empty strings for social media links in case of an error
    $social_links = [
        'facebook_link' => '',
        'twitter_link' => '',
        'instagram_link' => '',
        'linkedin_link' => '',
        'youtube_link' => '',
    ];

    // Assign default empty strings for cart settings in case of an error
    $cart_logo = '';
    $cart_description = '';
    $minimum_order = '0';

    // Assign default shipping settings
    $current_settings = [
        'shipping_calculation_mode' => 'radius',
        'shipping_fee_base' => 0,
        'shipping_fee_per_km' => 0,
        'shipping_distance_radius' => 0,
        'shipping_free_threshold' => 9999,
        'postal_code_zones' => '',
        'store_lat' => 0,
        'store_lng' => 0,
    ];
}
?>
