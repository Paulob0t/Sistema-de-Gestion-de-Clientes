<?php
require_once dirname(__DIR__) . '/conn.php';
$r = $conn->query("SELECT c.id, c.nombre_contacto,
    (SELECT COUNT(*) FROM hosting h WHERE h.cliente_id=c.id AND h.eliminado=0) AS hosting,
    (SELECT COUNT(*) FROM dominios d WHERE d.cliente_id=c.id AND d.eliminado=0) AS dominios
    FROM clientes c
    HAVING hosting > 0 OR dominios > 0
    ORDER BY hosting+dominios DESC
    LIMIT 10");
while ($row = $r->fetch_assoc()) {
    echo json_encode($row, JSON_UNESCAPED_UNICODE) . PHP_EOL;
}
