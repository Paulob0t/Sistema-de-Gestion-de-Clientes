<?php
// Simple test to see what's being returned
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>Testing obtener_conversaciones.php</h1>";

// Capture the output
ob_start();
include 'obtener_conversaciones.php';
$output = ob_get_clean();

echo "<h2>Raw Output:</h2>";
echo "<pre>" . htmlspecialchars($output) . "</pre>";

echo "<h2>Decoded JSON:</h2>";
$data = json_decode($output, true);
if ($data !== null) {
    echo "<pre>" . print_r($data, true) . "</pre>";
    echo "<p><strong>Number of conversations:</strong> " . count($data) . "</p>";
} else {
    echo "<p style='color: red;'>JSON decode error: " . json_last_error_msg() . "</p>";
}

echo "<h2>File Check:</h2>";
$sessionsDir = __DIR__ . '/../whatsapp/sessions';
echo "Sessions directory: $sessionsDir<br>";
echo "Directory exists: " . (is_dir($sessionsDir) ? 'Yes' : 'No') . "<br>";

$files = glob($sessionsDir . '/whatsapp_*.json');
echo "Files found: " . count($files) . "<br>";
if ($files) {
    echo "<ul>";
    foreach ($files as $file) {
        $basename = basename($file);
        echo "<li>$basename";
        if (strpos($basename, '_meta.json') !== false) {
            echo " (meta file - should be ignored)";
        }
        echo "</li>";
    }
    echo "</ul>";
}
