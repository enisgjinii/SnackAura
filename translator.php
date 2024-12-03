<?php
// translator.php

use Symfony\Component\Translation\Translator;
use Symfony\Component\Translation\Loader\PhpFileLoader;

// Determine the selected language
if (isset($_GET['lang'])) {
    $lang = $_GET['lang'];
    $_SESSION['lang'] = $lang;
} elseif (isset($_SESSION['lang'])) {
    $lang = $_SESSION['lang'];
} else {
    $lang = 'en'; // Default language
    $_SESSION['lang'] = $lang;
}

// Initialize the Translator
$translator = new Translator($lang);
$translator->addLoader('php', new PhpFileLoader());

// Load translation files
$translator->addResource('php', __DIR__ . '/translations/messages.en.php', 'en');
$translator->addResource('php', __DIR__ . '/translations/messages.de.php', 'de');
$translator->addResource('php', __DIR__ . '/translations/messages.sq.php', 'sq');

// Helper function
function t($key, $translator) {
    return $translator->trans($key);
}
