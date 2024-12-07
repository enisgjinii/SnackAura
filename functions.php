<?php
// functions.php

/**
 * Fetches the AGB (Terms and Conditions) content.
 *
 * @param PDO $pdo The PDO database connection.
 * @return string The sanitized AGB content.
 */
function getAGBContent(PDO $pdo): string
{
    try {
        $stmt = $pdo->prepare("SELECT content FROM terms_and_conditions WHERE id = 1 AND is_active = 1");
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($result && !empty($result['content'])) {
            return $result['content'];
        } else {
            return "AGB content is currently unavailable. Please check back later.";
        }
    } catch (PDOException $e) {
        // Log the error for debugging
        log_error_markdown("Failed to fetch AGB content: " . $e->getMessage(), "Fetching AGB");
        return "An error occurred while loading the terms and conditions.";
    }
}
