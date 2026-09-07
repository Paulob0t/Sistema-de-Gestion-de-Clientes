<?php
/**
 * Script de verificación de agentes
 * Ubicar en: adm.conlineweb.com/verificar_agentes.php
 * 
 * Este script muestra el mapeo entre usuarios de login y agentes
 */

include "conn.php";

echo "<h1>Verificación de Mapeo de Agentes</h1>";
echo "<style>body{font-family:Arial,sans-serif;margin:20px;} table{border-collapse:collapse;width:100%;} th,td{border:1px solid #ccc;padding:8px;text-align:left;} th{background:#f0f0f0;}</style>";

echo "<h2>Tabla Login (usuarios)</h2>";
$query_login = "SELECT id, usuario, id_tipo_usuario FROM login ORDER BY id";
$result_login = mysqli_query($conn, $query_login);

echo "<table>";
echo "<tr><th>ID</th><th>Usuario</th><th>Tipo</th><th>Descripción Tipo</th></tr>";
while($row = mysqli_fetch_assoc($result_login)) {
    $tipo_desc = '';
    switch($row['id_tipo_usuario']) {
        case 0: $tipo_desc = 'Cliente'; break;
        case 1: $tipo_desc = 'Admin'; break;
        case 2: $tipo_desc = 'Solicitudes'; break;
        case 3: $tipo_desc = 'Agente/Desarrollador'; break;
        default: $tipo_desc = 'Desconocido';
    }
    echo "<tr><td>{$row['id']}</td><td>{$row['usuario']}</td><td>{$row['id_tipo_usuario']}</td><td>$tipo_desc</td></tr>";
}
echo "</table>";

echo "<h2>Tabla Agentes</h2>";
$query_agentes = "SELECT id, Idusu, nombre, correo FROM agentes ORDER BY id";
$result_agentes = mysqli_query($conn, $query_agentes);

echo "<table>";
echo "<tr><th>ID Agente</th><th>ID Usuario (Idusu)</th><th>Nombre</th><th>Correo</th></tr>";
while($row = mysqli_fetch_assoc($result_agentes)) {
    echo "<tr><td>{$row['id']}</td><td>{$row['Idusu']}</td><td>{$row['nombre']}</td><td>{$row['correo']}</td></tr>";
}
echo "</table>";

echo "<h2>Mapeo Login → Agentes</h2>";
$query_mapeo = "
    SELECT 
        l.id as login_id,
        l.usuario,
        l.id_tipo_usuario,
        a.id as agente_id,
        a.nombre as agente_nombre
    FROM login l
    LEFT JOIN agentes a ON l.id = a.Idusu
    WHERE l.id_tipo_usuario = 3
    ORDER BY l.id
";
$result_mapeo = mysqli_query($conn, $query_mapeo);

echo "<table>";
echo "<tr><th>Login ID</th><th>Usuario</th><th>Tipo</th><th>Agente ID</th><th>Nombre Agente</th><th>Estado</th></tr>";
while($row = mysqli_fetch_assoc($result_mapeo)) {
    $estado = $row['agente_id'] ? '<span style="color:green;">✓ Mapeado</span>' : '<span style="color:red;">✗ Sin agente</span>';
    echo "<tr>";
    echo "<td>{$row['login_id']}</td>";
    echo "<td>{$row['usuario']}</td>";
    echo "<td>{$row['id_tipo_usuario']}</td>";
    echo "<td>" . ($row['agente_id'] ?? 'N/A') . "</td>";
    echo "<td>" . ($row['agente_nombre'] ?? 'N/A') . "</td>";
    echo "<td>$estado</td>";
    echo "</tr>";
}
echo "</table>";

echo "<h2>Usuarios tipo 3 sin mapeo de agente</h2>";
$query_sin_mapeo = "
    SELECT l.id, l.usuario 
    FROM login l
    LEFT JOIN agentes a ON l.id = a.Idusu
    WHERE l.id_tipo_usuario = 3 AND a.id IS NULL
";
$result_sin_mapeo = mysqli_query($conn, $query_sin_mapeo);

if (mysqli_num_rows($result_sin_mapeo) > 0) {
    echo "<table>";
    echo "<tr><th>Login ID</th><th>Usuario</th><th>Acción Sugerida</th></tr>";
    while($row = mysqli_fetch_assoc($result_sin_mapeo)) {
        echo "<tr>";
        echo "<td>{$row['id']}</td>";
        echo "<td>{$row['usuario']}</td>";
        echo "<td>Crear registro en tabla agentes con Idusu = {$row['id']}</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p style='color:green;'>✓ Todos los usuarios tipo 3 tienen mapeo de agente</p>";
}

echo "<h2>Simulación de Login para Agentes</h2>";
echo "<p>Esta simulación muestra hacia dónde sería redirigido cada usuario tipo 3:</p>";

$query_sim = "
    SELECT 
        l.id as uid,
        l.usuario,
        a.id as agente_id
    FROM login l
    LEFT JOIN agentes a ON l.id = a.Idusu
    WHERE l.id_tipo_usuario = 3
    ORDER BY l.id
";
$result_sim = mysqli_query($conn, $query_sim);

echo "<table>";
echo "<tr><th>Usuario</th><th>UID</th><th>Agente ID</th><th>URL de Redirección</th></tr>";
while($row = mysqli_fetch_assoc($result_sim)) {
    if ($row['agente_id']) {
        $redirect = 'https://adm.conlineweb.com/solicitudes/tickets_desarrollador.php?id=' . $row['agente_id'];
    } else {
        $redirect = 'https://adm.conlineweb.com/solicitudes/';
    }
    
    echo "<tr>";
    echo "<td>{$row['usuario']}</td>";
    echo "<td>{$row['uid']}</td>";
    echo "<td>" . ($row['agente_id'] ?? 'N/A') . "</td>";
    echo "<td><a href='$redirect' target='_blank'>$redirect</a></td>";
    echo "</tr>";
}
echo "</table>";

echo "<p><a href='https://cliente.conlineweb.com/ingreso.php'>← Volver al Login</a></p>";
?>