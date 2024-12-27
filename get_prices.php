<?php
// get_prices.php
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $size = $_POST['size'];

    // Fetch updated prices based on the selected size
    // This is just an example; you'll need to replace it with your actual logic
    $sauces = [
        ['id' => 1, 'price' => 0.5 * $sizeMultiplier], // Example calculation
        ['id' => 2, 'price' => 0.7 * $sizeMultiplier]
    ];

    $extras = [
        ['id' => 1, 'price' => 1.0 * $sizeMultiplier],
        ['id' => 2, 'price' => 1.5 * $sizeMultiplier]
    ];

    echo json_encode(['sauces' => $sauces, 'extras' => $extras]);
}
?>