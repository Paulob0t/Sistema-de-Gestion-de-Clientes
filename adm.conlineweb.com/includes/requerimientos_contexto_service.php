<?php

/**
 * Carga contexto de cliente y proyecto para el asistente de requerimientos.
 */
function requerimientos_cargar_contexto(mysqli $conn, ?int $idCliente, ?int $idProyecto): array
{
    $contexto = [
        'cliente' => null,
        'proyecto' => null,
        'texto' => '',
    ];

    if ($idCliente && $idCliente > 0) {
        $stmt = $conn->prepare('SELECT id, nombre_contacto, empresa, correo, telefono FROM clientes WHERE id = ? LIMIT 1');
        if ($stmt) {
            $stmt->bind_param('i', $idCliente);
            $stmt->execute();
            $res = $stmt->get_result();
            if ($row = $res->fetch_assoc()) {
                $contexto['cliente'] = [
                    'id' => (int) $row['id'],
                    'nombre_contacto' => (string) ($row['nombre_contacto'] ?? ''),
                    'empresa' => (string) ($row['empresa'] ?? ''),
                    'correo' => (string) ($row['correo'] ?? ''),
                    'telefono' => (string) ($row['telefono'] ?? ''),
                ];
            }
            $stmt->close();
        }
    }

    if ($idProyecto && $idProyecto > 0) {
        $hasDescTec = false;
        $chk = $conn->query("SHOW COLUMNS FROM proyectos LIKE 'descripcion_tecnica'");
        if ($chk && $chk->num_rows > 0) {
            $hasDescTec = true;
        }

        $sql = $hasDescTec
            ? 'SELECT id_proyecto, id_cliente, nombre_proyecto, descripcion, descripcion_tecnica, url, fecha_creacion FROM proyectos WHERE id_proyecto = ? LIMIT 1'
            : 'SELECT id_proyecto, id_cliente, nombre_proyecto, descripcion, url, fecha_creacion FROM proyectos WHERE id_proyecto = ? LIMIT 1';

        $stmt = $conn->prepare($sql);
        if ($stmt) {
            $stmt->bind_param('i', $idProyecto);
            $stmt->execute();
            $res = $stmt->get_result();
            if ($row = $res->fetch_assoc()) {
                $tags = ['categorias' => [], 'tecnologias' => []];
                $tq = $conn->query(
                    'SELECT tag_text, tipo FROM proyectos_tags WHERE id_proyecto = ' . (int) $row['id_proyecto']
                );
                if ($tq) {
                    while ($t = $tq->fetch_assoc()) {
                        $tipo = $t['tipo'] ?? '';
                        $text = (string) ($t['tag_text'] ?? '');
                        if ($text === '') {
                            continue;
                        }
                        if ($tipo === 'categoria') {
                            $tags['categorias'][] = $text;
                        } else {
                            $tags['tecnologias'][] = $text;
                        }
                    }
                }

                $contexto['proyecto'] = [
                    'id_proyecto' => (int) $row['id_proyecto'],
                    'id_cliente' => (int) $row['id_cliente'],
                    'nombre_proyecto' => (string) ($row['nombre_proyecto'] ?? ''),
                    'descripcion' => (string) ($row['descripcion'] ?? ''),
                    'descripcion_tecnica' => $hasDescTec ? (string) ($row['descripcion_tecnica'] ?? '') : '',
                    'url' => (string) ($row['url'] ?? ''),
                    'fecha_creacion' => (string) ($row['fecha_creacion'] ?? ''),
                    'categorias' => $tags['categorias'],
                    'tecnologias' => $tags['tecnologias'],
                ];

                if ($idCliente && (int) $row['id_cliente'] !== $idCliente) {
                    $contexto['proyecto']['advertencia'] = 'El proyecto no pertenece al cliente seleccionado.';
                }
            }
            $stmt->close();
        }
    }

    $contexto['texto'] = requerimientos_contexto_a_texto($contexto);
    return $contexto;
}

function requerimientos_contexto_a_texto(array $contexto): string
{
    $partes = [];

    if (!empty($contexto['cliente'])) {
        $c = $contexto['cliente'];
        $bloque = "## Cliente\n";
        $bloque .= '- ID: ' . $c['id'] . "\n";
        if ($c['empresa'] !== '') {
            $bloque .= '- Empresa: ' . $c['empresa'] . "\n";
        }
        if ($c['nombre_contacto'] !== '') {
            $bloque .= '- Contacto: ' . $c['nombre_contacto'] . "\n";
        }
        if ($c['correo'] !== '') {
            $bloque .= '- Correo: ' . $c['correo'] . "\n";
        }
        $partes[] = trim($bloque);
    }

    if (!empty($contexto['proyecto'])) {
        $p = $contexto['proyecto'];
        $bloque = "## Proyecto\n";
        $bloque .= '- ID: ' . $p['id_proyecto'] . "\n";
        $bloque .= '- Nombre: ' . $p['nombre_proyecto'] . "\n";
        if ($p['url'] !== '') {
            $bloque .= '- URL: ' . $p['url'] . "\n";
        }
        if (!empty($p['categorias'])) {
            $bloque .= '- Categorías: ' . implode(', ', $p['categorias']) . "\n";
        }
        if (!empty($p['tecnologias'])) {
            $bloque .= '- Tecnologías: ' . implode(', ', $p['tecnologias']) . "\n";
        }
        if ($p['descripcion'] !== '') {
            $bloque .= "\n### Descripción general\n" . $p['descripcion'] . "\n";
        }
        if (!empty($p['descripcion_tecnica'])) {
            $bloque .= "\n### Descripción técnica\n" . $p['descripcion_tecnica'] . "\n";
        }
        if (!empty($p['advertencia'])) {
            $bloque .= "\n⚠️ " . $p['advertencia'] . "\n";
        }
        $partes[] = trim($bloque);
    }

    return implode("\n\n", $partes);
}

function requerimientos_saludo_con_contexto(array $contexto): string
{
    if (!empty($contexto['proyecto'])) {
        $nombre = $contexto['proyecto']['nombre_proyecto'];
        return "¡Hola! Ya tengo contexto del proyecto **{$nombre}**. Describe con detalle lo que necesitas y, si quieres, adjunta capturas de pantalla para que el ticket quede más preciso.";
    }
    if (!empty($contexto['cliente'])) {
        $empresa = $contexto['cliente']['empresa'] ?: $contexto['cliente']['nombre_contacto'];
        return "¡Hola! Tengo contexto del cliente **{$empresa}**. Cuéntame qué necesitas y puedes adjuntar capturas para mayor claridad. Si eliges un proyecto tendré más contexto técnico.";
    }
    return '¡Hola! Describe tu solicitud con detalle (qué, dónde, cómo). Puedes adjuntar capturas de pantalla. Selecciona cliente y proyecto arriba para más contexto.';
}
