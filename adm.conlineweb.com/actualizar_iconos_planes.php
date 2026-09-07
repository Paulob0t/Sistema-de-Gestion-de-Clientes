<?php
// Script para actualizar iconos de Font Awesome a emojis
error_reporting(E_ALL);
ini_set("display_errors", 1);
include_once "conn_hostingpro.php";

if (!$conn_hp) {
    die('Error de conexión');
}

// Mapeo de iconos Font Awesome a emojis
$iconos_map = [
    'fa-rocket' => '🚀',
    'fa-database' => '🗄️',
    'fa-chart-line' => '📈',
    'fa-shopping-cart' => '🛒',
    'fa-store' => '🛍️',
    'fa-globe' => '🌐',
    'fa-code' => '💻',
    'fa-server' => '🖥️',
    'fa-cloud' => '☁️',
    'fa-laptop' => '💼',
];

$actualizados = 0;

foreach ($iconos_map as $fa_icon => $emoji) {
    $sql = "UPDATE planes_conlineweb SET icono = ? WHERE icono = ?";
    $stmt = $conn_hp->prepare($sql);
    $stmt->bind_param('ss', $emoji, $fa_icon);
    
    if ($stmt->execute()) {
        $filas = $stmt->affected_rows;
        if ($filas > 0) {
            echo "✅ Actualizado '$fa_icon' → '$emoji' ($filas planes)<br>";
            $actualizados += $filas;
        }
    }
}

echo "<br><strong>Total: $actualizados planes actualizados</strong><br>";
echo "<br><a href='planpro_planes.php'>← Volver a Planes</a>";
?>
