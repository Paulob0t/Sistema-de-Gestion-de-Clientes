<?php

/**
 * Servicio de transferencia de clientes: ConlineWeb (admin_clientes) -> Hosting Pro (conlineweb_hosting)
 * Conserva el ID de cliente como llave principal en ambas bases.
 */

function transferencia_columna_existe(mysqli $conn, string $tabla, string $columna): bool
{
    $tabla = $conn->real_escape_string($tabla);
    $columna = $conn->real_escape_string($columna);
    $result = $conn->query("SHOW COLUMNS FROM `$tabla` LIKE '$columna'");
    return $result && $result->num_rows > 0;
}

function transferencia_moneda_dominio(array $dominio): string
{
    if (!empty($dominio['moneda'])) {
        return $dominio['moneda'];
    }
    return (isset($dominio['id_forma_pago']) && (int) $dominio['id_forma_pago'] === 2) ? 'USD' : 'MXN';
}

/** @param array<int, array{0: string, 1: mixed}> $params [tipo, valor] */
function transferencia_stmt_bind(mysqli_stmt $stmt, array $params): void
{
    $types = '';
    $values = [];
    foreach ($params as $param) {
        $types .= $param[0];
        $values[] = $param[1];
    }
    $refs = [$types];
    foreach ($values as $key => $value) {
        $refs[] = &$values[$key];
    }
    call_user_func_array([$stmt, 'bind_param'], $refs);
}

function transferencia_prepare(mysqli $conn, string $sql, string $contexto): mysqli_stmt
{
    $stmt = $conn->prepare($sql);
    if (!$stmt instanceof mysqli_stmt) {
        throw new RuntimeException($contexto . ': ' . $conn->error);
    }

    return $stmt;
}

function transferencia_actualizar_auto_increment(mysqli $conn, string $tabla, string $columna): void
{
    $tabla = $conn->real_escape_string($tabla);
    $columna = $conn->real_escape_string($columna);
    $conn->query("SET @max_id := (SELECT IFNULL(MAX(`$columna`), 0) + 1 FROM `$tabla`)");
    $conn->query("SET @sql := CONCAT('ALTER TABLE `$tabla` AUTO_INCREMENT = ', @max_id)");
    $conn->query("PREPARE stmt FROM @sql");
    $conn->query("EXECUTE stmt");
    $conn->query("DEALLOCATE PREPARE stmt");
}

/**
 * @return array{success:bool,message:string,data?:array}
 */
function transferir_cliente_a_hostingpro(mysqli $source, mysqli $target, int $clienteId, int $usuarioId): array
{
    if (!transferencia_columna_existe($source, 'clientes', 'transferido')) {
        return [
            'success' => false,
            'message' => 'Faltan columnas de transferencia. Ejecuta sql/migracion_transferencia_clientes.sql en admin_clientes.',
        ];
    }

    $stmt = $source->prepare('SELECT * FROM clientes WHERE id = ? LIMIT 1');
    $stmt->bind_param('i', $clienteId);
    $stmt->execute();
    $cliente = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$cliente) {
        return ['success' => false, 'message' => 'Cliente no encontrado en ConlineWeb.'];
    }

    if ((int) ($cliente['transferido'] ?? 0) === 1) {
        return [
            'success' => false,
            'message' => 'Este cliente ya fue transferido a Hosting Pro el '
                . ($cliente['fecha_transferencia'] ?? 'fecha desconocida') . '.',
        ];
    }

    $stmt = $target->prepare('SELECT id FROM clientes WHERE id = ? LIMIT 1');
    $stmt->bind_param('i', $clienteId);
    $stmt->execute();
    $existente = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($existente) {
        return [
            'success' => false,
            'message' => 'Ya existe un cliente con ID #' . $clienteId . ' en Hosting Pro. No se puede duplicar la transferencia.',
        ];
    }

    $source->begin_transaction();
    $target->begin_transaction();

    try {
        $sqlCliente = 'INSERT INTO clientes (
            id, nombre_contacto, empresa, correo, telefono, especificacion, rsocial, rfc,
            calle, next, nint, col, cp, pais, estado, ciudad, display,
            constancia_situacion_fiscal, facturacion, actualizado, correo_pendiente_actualizar,
            actualizar_correo, eliminado, id_lead, usuario_registro
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)';

        $usuarioRegistro = $cliente['usuario_registro'] ?? null;
        $stmt = $target->prepare($sqlCliente);
        transferencia_stmt_bind($stmt, [
            ['i', $clienteId],
            ['s', $cliente['nombre_contacto']],
            ['s', $cliente['empresa']],
            ['s', $cliente['correo']],
            ['s', $cliente['telefono']],
            ['s', $cliente['especificacion']],
            ['s', $cliente['rsocial']],
            ['s', $cliente['rfc']],
            ['s', $cliente['calle']],
            ['s', $cliente['next']],
            ['s', $cliente['nint']],
            ['s', $cliente['col']],
            ['s', $cliente['cp']],
            ['s', $cliente['pais']],
            ['s', $cliente['estado']],
            ['s', $cliente['ciudad']],
            ['s', $cliente['display']],
            ['s', $cliente['constancia_situacion_fiscal']],
            ['i', $cliente['facturacion']],
            ['i', $cliente['actualizado']],
            ['s', $cliente['correo_pendiente_actualizar']],
            ['i', $cliente['actualizar_correo']],
            ['i', $cliente['eliminado']],
            ['i', $cliente['id_lead']],
            ['i', $usuarioRegistro],
        ]);
        if (!$stmt->execute()) {
            throw new RuntimeException('Error al insertar cliente en Hosting Pro: ' . $stmt->error);
        }
        $stmt->close();

        $stmt = $source->prepare('SELECT * FROM login WHERE id = ? LIMIT 1');
        $stmt->bind_param('i', $clienteId);
        $stmt->execute();
        $login = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($login) {
            $sqlLogin = 'INSERT INTO login (id, usuario, contrasena, contrasena_normal, id_tipo_usuario, cambio_contrasena, fecha_actualizacion)
                         VALUES (?, ?, ?, ?, ?, ?, ?)';
            $stmt = $target->prepare($sqlLogin);
            transferencia_stmt_bind($stmt, [
                ['i', $clienteId],
                ['s', $login['usuario']],
                ['s', $login['contrasena']],
                ['s', $login['contrasena_normal']],
                ['i', $login['id_tipo_usuario']],
                ['i', $login['cambio_contrasena']],
                ['s', $login['fecha_actualizacion']],
            ]);
            if (!$stmt->execute()) {
                throw new RuntimeException('Error al transferir login: ' . $stmt->error);
            }
            $stmt->close();
        }

        $stmt = $source->prepare('SELECT * FROM dominios WHERE cliente_id = ?');
        $stmt->bind_param('i', $clienteId);
        $stmt->execute();
        $dominios = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        $dominiosTransferidos = 0;
        foreach ($dominios as $dom) {
            $moneda = transferencia_moneda_dominio($dom);
            $dominioTipo = $dom['dominio_tipo'] ?? 'nuevo';
            $servicioWebId = $dom['servicio_web_id'] ?? null;

            $sqlDom = 'INSERT INTO dominios (
                id_dominio, cliente_id, servicio_web_id, proveedor, url_dominio, dominio_tipo,
                url_pago, url_admin, usuario, contrasena, contrasena_normal, url_cpanel,
                ns1, ns2, ns3, ns4, ns5, ns6, costo_dominio, moneda, id_forma_pago,
                fecha_contratacion, fecha_pago, estado_dominio, registrado, eliminado,
                frecuencia_pago, estatus_pago
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)';

            $stmt = $target->prepare($sqlDom);
            transferencia_stmt_bind($stmt, [
                ['i', $dom['id_dominio']],
                ['i', $clienteId],
                ['i', $servicioWebId],
                ['s', $dom['proveedor']],
                ['s', $dom['url_dominio']],
                ['s', $dominioTipo],
                ['s', $dom['url_pago']],
                ['s', $dom['url_admin']],
                ['s', $dom['usuario']],
                ['s', $dom['contrasena']],
                ['s', $dom['contrasena_normal']],
                ['s', $dom['url_cpanel']],
                ['s', $dom['ns1']],
                ['s', $dom['ns2']],
                ['s', $dom['ns3']],
                ['s', $dom['ns4']],
                ['s', $dom['ns5']],
                ['s', $dom['ns6']],
                ['d', $dom['costo_dominio']],
                ['s', $moneda],
                ['i', $dom['id_forma_pago']],
                ['s', $dom['fecha_contratacion']],
                ['s', $dom['fecha_pago']],
                ['i', $dom['estado_dominio']],
                ['i', $dom['registrado']],
                ['i', $dom['eliminado']],
                ['i', $dom['frecuencia_pago']],
                ['i', $dom['estatus_pago']],
            ]);
            if (!$stmt->execute()) {
                throw new RuntimeException('Error al transferir dominio ID ' . $dom['id_dominio'] . ': ' . $stmt->error);
            }
            $stmt->close();
            $dominiosTransferidos++;
        }

        $stmt = $source->prepare('SELECT * FROM hosting WHERE cliente_id = ?');
        $stmt->bind_param('i', $clienteId);
        $stmt->execute();
        $hostings = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        $hostingsTransferidos = 0;
        foreach ($hostings as $host) {
            $productoExtraId = $host['producto_extra_id'] ?? null;

            $sqlHost = 'INSERT INTO hosting (
                id_orden, cliente_id, dominio, nom_host, usuario, contrasena, contrasena_normal,
                tipo_producto, producto, producto_extra_id, costo_producto, id_forma_pago, dns,
                url_pago, url_acceso, ns1, ns2, ns3, ns4, ns5, ns6, fecha_contratacion,
                fecha_pago, estado_producto, IVA, eliminado, frecuencia_pago
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)';

            $stmt = $target->prepare($sqlHost);
            transferencia_stmt_bind($stmt, [
                ['i', $host['id_orden']],
                ['i', $clienteId],
                ['s', $host['dominio']],
                ['s', $host['nom_host']],
                ['s', $host['usuario']],
                ['s', $host['contrasena']],
                ['s', $host['contrasena_normal']],
                ['s', $host['tipo_producto']],
                ['i', $host['producto']],
                ['i', $productoExtraId],
                ['d', $host['costo_producto']],
                ['i', $host['id_forma_pago']],
                ['s', $host['dns']],
                ['s', $host['url_pago']],
                ['s', $host['url_acceso']],
                ['s', $host['ns1']],
                ['s', $host['ns2']],
                ['s', $host['ns3']],
                ['s', $host['ns4']],
                ['s', $host['ns5']],
                ['s', $host['ns6']],
                ['s', $host['fecha_contratacion']],
                ['s', $host['fecha_pago']],
                ['i', $host['estado_producto']],
                ['i', $host['IVA']],
                ['i', $host['eliminado']],
                ['i', $host['frecuencia_pago']],
            ]);
            if (!$stmt->execute()) {
                throw new RuntimeException('Error al transferir hosting ID ' . $host['id_orden'] . ': ' . $stmt->error);
            }
            $stmt->close();
            $hostingsTransferidos++;
        }

        $stmt = $source->prepare('SELECT * FROM pagos WHERE id_clie = ?');
        $stmt->bind_param('i', $clienteId);
        $stmt->execute();
        $pagos = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        $pagosTransferidos = 0;
        foreach ($pagos as $pago) {
            $comprobante = $pago['comprobante_pago'] ?? null;
            $notasAdmin = $pago['notas_admin'] ?? null;
            $pagoRecurrente = $pago['pago_recurrente'] ?? 0;
            $fechaInicio = $pago['fecha_inicio_recurrencia'] ?? null;
            $ultimoGenerado = $pago['ultimo_pago_generado'] ?? null;
            $sistema = 'hostingpro';

            $sqlPago = 'INSERT INTO pagos (
                id, id_clie, id_servicio, fecha, hora, fecha_pago, hora_pago, monto, currency, concepto,
                forma_pago, estatus, id_pago, session_id, pago_grupal_id, tipo_pago, id_cuenta,
                tipo_servicio, fecha_limite_pago, manual, Registro, sistema, frecuencia_pago,
                pago_recurrente, fecha_inicio_recurrencia, ultimo_pago_generado, comprobante_pago, notas_admin
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)';

            $stmt = $target->prepare($sqlPago);
            transferencia_stmt_bind($stmt, [
                ['i', $pago['id']],
                ['i', $clienteId],
                ['i', $pago['id_servicio']],
                ['s', $pago['fecha']],
                ['s', $pago['hora']],
                ['s', $pago['fecha_pago']],
                ['s', $pago['hora_pago']],
                ['d', $pago['monto']],
                ['s', $pago['currency']],
                ['s', $pago['concepto']],
                ['i', $pago['forma_pago']],
                ['i', $pago['estatus']],
                ['s', $pago['id_pago']],
                ['s', $pago['session_id']],
                ['s', $pago['pago_grupal_id']],
                ['s', $pago['tipo_pago']],
                ['i', $pago['id_cuenta']],
                ['i', $pago['tipo_servicio']],
                ['s', $pago['fecha_limite_pago']],
                ['i', $pago['manual']],
                ['i', $pago['Registro']],
                ['s', $sistema],
                ['i', $pago['frecuencia_pago']],
                ['i', $pagoRecurrente],
                ['s', $fechaInicio],
                ['s', $ultimoGenerado],
                ['s', $comprobante],
                ['s', $notasAdmin],
            ]);
            if (!$stmt->execute()) {
                throw new RuntimeException('Error al transferir pago ID ' . $pago['id'] . ': ' . $stmt->error);
            }
            $stmt->close();
            $pagosTransferidos++;
        }

        transferencia_actualizar_auto_increment($target, 'clientes', 'id');
        transferencia_actualizar_auto_increment($target, 'dominios', 'id_dominio');
        transferencia_actualizar_auto_increment($target, 'hosting', 'id_orden');
        transferencia_actualizar_auto_increment($target, 'pagos', 'id');

        $detalle = json_encode([
            'cliente_id' => $clienteId,
            'dominios' => $dominiosTransferidos,
            'hostings' => $hostingsTransferidos,
            'pagos' => $pagosTransferidos,
        ], JSON_UNESCAPED_UNICODE);

        $sqlLog = 'INSERT INTO clientes_transferencia_log
            (cliente_id, usuario_id, destino_sistema, dominios_transferidos, hostings_transferidos, pagos_transferidos, detalle)
            VALUES (?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                usuario_id = VALUES(usuario_id),
                fecha_transferencia = CURRENT_TIMESTAMP,
                dominios_transferidos = VALUES(dominios_transferidos),
                hostings_transferidos = VALUES(hostings_transferidos),
                pagos_transferidos = VALUES(pagos_transferidos),
                detalle = VALUES(detalle)';

        $destino = 'hostingpro';
        $stmt = $source->prepare($sqlLog);
        transferencia_stmt_bind($stmt, [
            ['i', $clienteId],
            ['i', $usuarioId],
            ['s', $destino],
            ['i', $dominiosTransferidos],
            ['i', $hostingsTransferidos],
            ['i', $pagosTransferidos],
            ['s', $detalle],
        ]);
        if (!$stmt->execute()) {
            throw new RuntimeException('Error al registrar auditoría: ' . $stmt->error);
        }
        $stmt->close();

        $sqlMarcar = 'UPDATE clientes
                      SET transferido = 1,
                          eliminado = 1,
                          fecha_transferencia = NOW(),
                          usuario_transferencia = ?
                      WHERE id = ? AND transferido = 0';
        $stmt = $source->prepare($sqlMarcar);
        $stmt->bind_param('ii', $usuarioId, $clienteId);
        if (!$stmt->execute() || $stmt->affected_rows === 0) {
            throw new RuntimeException('No se pudo marcar el cliente como transferido en ConlineWeb.');
        }
        $stmt->close();

        $stmt = $source->prepare('UPDATE dominios SET eliminado = 1 WHERE cliente_id = ?');
        $stmt->bind_param('i', $clienteId);
        $stmt->execute();
        $stmt->close();

        $stmt = $source->prepare('UPDATE hosting SET eliminado = 1 WHERE cliente_id = ?');
        $stmt->bind_param('i', $clienteId);
        $stmt->execute();
        $stmt->close();

        $target->commit();
        $source->commit();

        return [
            'success' => true,
            'message' => "Cliente #$clienteId transferido a Hosting Pro. Dominios: $dominiosTransferidos, Hostings: $hostingsTransferidos, Pagos: $pagosTransferidos.",
            'data' => [
                'cliente_id' => $clienteId,
                'dominios' => $dominiosTransferidos,
                'hostings' => $hostingsTransferidos,
                'pagos' => $pagosTransferidos,
            ],
        ];
    } catch (Throwable $e) {
        $target->rollback();
        $source->rollback();
        return [
            'success' => false,
            'message' => 'Error en la transferencia: ' . $e->getMessage(),
        ];
    }
}

/**
 * @return array{success:bool,message:string,data?:array}
 */
function transferir_cliente_a_conlineweb(mysqli $source, mysqli $target, int $clienteId, int $usuarioId): array
{
    if (!transferencia_columna_existe($target, 'clientes', 'transferido')) {
        return [
            'success' => false,
            'message' => 'Faltan columnas de transferencia. Ejecuta sql/migracion_transferencia_clientes.sql en admin_clientes.',
        ];
    }

    $stmt = $source->prepare('SELECT * FROM clientes WHERE id = ? LIMIT 1');
    $stmt->bind_param('i', $clienteId);
    $stmt->execute();
    $cliente = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$cliente) {
        return ['success' => false, 'message' => 'Cliente no encontrado en Hosting Pro.'];
    }

    if ((int) ($cliente['eliminado'] ?? 0) === 1) {
        return ['success' => false, 'message' => 'El cliente está eliminado en Hosting Pro. Restáuralo antes de transferir.'];
    }

    $stmt = $target->prepare('SELECT id, transferido, eliminado FROM clientes WHERE id = ? LIMIT 1');
    $stmt->bind_param('i', $clienteId);
    $stmt->execute();
    $clienteCw = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($clienteCw
        && (int) ($clienteCw['transferido'] ?? 0) === 0
        && (int) ($clienteCw['eliminado'] ?? 0) === 0
    ) {
        return [
            'success' => false,
            'message' => 'El cliente #' . $clienteId . ' ya está activo en ConlineWeb.',
        ];
    }

    $source->begin_transaction();
    $target->begin_transaction();

    try {
        $usuarioRegistro = $cliente['usuario_registro'] ?? null;

        if ($clienteCw) {
            $sqlUpdateCliente = 'UPDATE clientes SET
                nombre_contacto = ?, empresa = ?, correo = ?, telefono = ?, especificacion = ?, rsocial = ?, rfc = ?,
                calle = ?, next = ?, nint = ?, col = ?, cp = ?, pais = ?, estado = ?, ciudad = ?, display = ?,
                constancia_situacion_fiscal = ?, facturacion = ?, actualizado = ?, correo_pendiente_actualizar = ?,
                actualizar_correo = ?, eliminado = 0, id_lead = ?, usuario_registro = ?,
                transferido = 0, fecha_transferencia = NULL, usuario_transferencia = NULL
                WHERE id = ?';
            $stmt = $target->prepare($sqlUpdateCliente);
            transferencia_stmt_bind($stmt, [
                ['s', $cliente['nombre_contacto']],
                ['s', $cliente['empresa']],
                ['s', $cliente['correo']],
                ['s', $cliente['telefono']],
                ['s', $cliente['especificacion']],
                ['s', $cliente['rsocial']],
                ['s', $cliente['rfc']],
                ['s', $cliente['calle']],
                ['s', $cliente['next']],
                ['s', $cliente['nint']],
                ['s', $cliente['col']],
                ['s', $cliente['cp']],
                ['s', $cliente['pais']],
                ['s', $cliente['estado']],
                ['s', $cliente['ciudad']],
                ['s', $cliente['display']],
                ['s', $cliente['constancia_situacion_fiscal']],
                ['i', $cliente['facturacion']],
                ['i', $cliente['actualizado']],
                ['s', $cliente['correo_pendiente_actualizar']],
                ['i', $cliente['actualizar_correo']],
                ['i', $cliente['id_lead']],
                ['i', $usuarioRegistro],
                ['i', $clienteId],
            ]);
        } else {
            $sqlCliente = 'INSERT INTO clientes (
                id, nombre_contacto, empresa, correo, telefono, especificacion, rsocial, rfc,
                calle, next, nint, col, cp, pais, estado, ciudad, display,
                constancia_situacion_fiscal, facturacion, actualizado, correo_pendiente_actualizar,
                actualizar_correo, eliminado, id_lead, usuario_registro, transferido
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0, ?, ?, 0)';

            $stmt = $target->prepare($sqlCliente);
            transferencia_stmt_bind($stmt, [
                ['i', $clienteId],
                ['s', $cliente['nombre_contacto']],
                ['s', $cliente['empresa']],
                ['s', $cliente['correo']],
                ['s', $cliente['telefono']],
                ['s', $cliente['especificacion']],
                ['s', $cliente['rsocial']],
                ['s', $cliente['rfc']],
                ['s', $cliente['calle']],
                ['s', $cliente['next']],
                ['s', $cliente['nint']],
                ['s', $cliente['col']],
                ['s', $cliente['cp']],
                ['s', $cliente['pais']],
                ['s', $cliente['estado']],
                ['s', $cliente['ciudad']],
                ['s', $cliente['display']],
                ['s', $cliente['constancia_situacion_fiscal']],
                ['i', $cliente['facturacion']],
                ['i', $cliente['actualizado']],
                ['s', $cliente['correo_pendiente_actualizar']],
                ['i', $cliente['actualizar_correo']],
                ['i', $cliente['id_lead']],
                ['i', $usuarioRegistro],
            ]);
        }

        if (!$stmt->execute()) {
            throw new RuntimeException('Error al guardar cliente en ConlineWeb: ' . $stmt->error);
        }
        $stmt->close();

        $stmt = $source->prepare('SELECT * FROM login WHERE id = ? LIMIT 1');
        $stmt->bind_param('i', $clienteId);
        $stmt->execute();
        $login = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($login) {
            $stmt = $target->prepare('SELECT id FROM login WHERE id = ? LIMIT 1');
            $stmt->bind_param('i', $clienteId);
            $stmt->execute();
            $loginCw = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if ($loginCw) {
                $sqlLogin = 'UPDATE login SET usuario = ?, contrasena = ?, contrasena_normal = ?,
                             id_tipo_usuario = ?, cambio_contrasena = ?, fecha_actualizacion = ?
                             WHERE id = ?';
                $stmt = $target->prepare($sqlLogin);
                transferencia_stmt_bind($stmt, [
                    ['s', $login['usuario']],
                    ['s', $login['contrasena']],
                    ['s', $login['contrasena_normal']],
                    ['i', $login['id_tipo_usuario']],
                    ['i', $login['cambio_contrasena']],
                    ['s', $login['fecha_actualizacion']],
                    ['i', $clienteId],
                ]);
            } else {
                $sqlLogin = 'INSERT INTO login (id, usuario, contrasena, contrasena_normal, id_tipo_usuario, cambio_contrasena, fecha_actualizacion)
                             VALUES (?, ?, ?, ?, ?, ?, ?)';
                $stmt = $target->prepare($sqlLogin);
                transferencia_stmt_bind($stmt, [
                    ['i', $clienteId],
                    ['s', $login['usuario']],
                    ['s', $login['contrasena']],
                    ['s', $login['contrasena_normal']],
                    ['i', $login['id_tipo_usuario']],
                    ['i', $login['cambio_contrasena']],
                    ['s', $login['fecha_actualizacion']],
                ]);
            }

            if (!$stmt->execute()) {
                throw new RuntimeException('Error al transferir login a ConlineWeb: ' . $stmt->error);
            }
            $stmt->close();
        }

        $stmt = $source->prepare('SELECT * FROM dominios WHERE cliente_id = ?');
        $stmt->bind_param('i', $clienteId);
        $stmt->execute();
        $dominios = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        $dominiosTransferidos = 0;
        foreach ($dominios as $dom) {
            $stmt = transferencia_prepare($target, 'SELECT id_dominio FROM dominios WHERE id_dominio = ? LIMIT 1', 'Dominios lookup');
            $stmt->bind_param('i', $dom['id_dominio']);
            $stmt->execute();
            $domExiste = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if ($domExiste) {
                $sqlDom = 'UPDATE dominios SET
                    cliente_id = ?, proveedor = ?, url_dominio = ?, url_pago = ?, url_admin = ?,
                    usuario = ?, contrasena = ?, contrasena_normal = ?, url_cpanel = ?,
                    ns1 = ?, ns2 = ?, ns3 = ?, ns4 = ?, ns5 = ?, ns6 = ?,
                    costo_dominio = ?, id_forma_pago = ?, fecha_contratacion = ?, fecha_pago = ?,
                    estado_dominio = ?, registrado = ?, eliminado = 0, frecuencia_pago = ?, estatus_pago = ?
                    WHERE id_dominio = ?';
                $stmt = transferencia_prepare($target, $sqlDom, 'Dominios UPDATE ConlineWeb');
                transferencia_stmt_bind($stmt, [
                    ['i', $clienteId],
                    ['s', $dom['proveedor']],
                    ['s', $dom['url_dominio']],
                    ['s', $dom['url_pago'] ?? ''],
                    ['s', $dom['url_admin']],
                    ['s', $dom['usuario']],
                    ['s', $dom['contrasena']],
                    ['s', $dom['contrasena_normal']],
                    ['s', $dom['url_cpanel']],
                    ['s', $dom['ns1']],
                    ['s', $dom['ns2']],
                    ['s', $dom['ns3']],
                    ['s', $dom['ns4']],
                    ['s', $dom['ns5']],
                    ['s', $dom['ns6']],
                    ['d', $dom['costo_dominio']],
                    ['i', $dom['id_forma_pago']],
                    ['s', $dom['fecha_contratacion']],
                    ['s', $dom['fecha_pago']],
                    ['i', $dom['estado_dominio']],
                    ['i', $dom['registrado']],
                    ['i', $dom['frecuencia_pago'] ?? 0],
                    ['i', $dom['estatus_pago'] ?? 0],
                    ['i', $dom['id_dominio']],
                ]);
            } else {
                $sqlDom = 'INSERT INTO dominios (
                    id_dominio, cliente_id, proveedor, url_dominio, url_pago, url_admin, usuario, contrasena, contrasena_normal, url_cpanel,
                    ns1, ns2, ns3, ns4, ns5, ns6, costo_dominio, id_forma_pago,
                    fecha_contratacion, fecha_pago, estado_dominio, registrado, eliminado, frecuencia_pago, estatus_pago
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0, ?, ?)';

                $stmt = transferencia_prepare($target, $sqlDom, 'Dominios INSERT ConlineWeb');
                transferencia_stmt_bind($stmt, [
                    ['i', $dom['id_dominio']],
                    ['i', $clienteId],
                    ['s', $dom['proveedor']],
                    ['s', $dom['url_dominio']],
                    ['s', $dom['url_pago'] ?? ''],
                    ['s', $dom['url_admin']],
                    ['s', $dom['usuario']],
                    ['s', $dom['contrasena']],
                    ['s', $dom['contrasena_normal']],
                    ['s', $dom['url_cpanel']],
                    ['s', $dom['ns1']],
                    ['s', $dom['ns2']],
                    ['s', $dom['ns3']],
                    ['s', $dom['ns4']],
                    ['s', $dom['ns5']],
                    ['s', $dom['ns6']],
                    ['d', $dom['costo_dominio']],
                    ['i', $dom['id_forma_pago']],
                    ['s', $dom['fecha_contratacion']],
                    ['s', $dom['fecha_pago']],
                    ['i', $dom['estado_dominio']],
                    ['i', $dom['registrado']],
                    ['i', $dom['frecuencia_pago'] ?? 0],
                    ['i', $dom['estatus_pago'] ?? 0],
                ]);
            }

            if (!$stmt->execute()) {
                throw new RuntimeException('Error al transferir dominio ID ' . $dom['id_dominio'] . ': ' . $stmt->error);
            }
            $stmt->close();
            $dominiosTransferidos++;
        }

        $stmt = $source->prepare('SELECT * FROM hosting WHERE cliente_id = ?');
        $stmt->bind_param('i', $clienteId);
        $stmt->execute();
        $hostings = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        $hostingsTransferidos = 0;
        foreach ($hostings as $host) {
            $stmt = transferencia_prepare($target, 'SELECT id_orden FROM hosting WHERE id_orden = ? LIMIT 1', 'Hosting lookup');
            $stmt->bind_param('i', $host['id_orden']);
            $stmt->execute();
            $hostExiste = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if ($hostExiste) {
                $sqlHost = 'UPDATE hosting SET
                    cliente_id = ?, dominio = ?, nom_host = ?, usuario = ?, contrasena = ?, contrasena_normal = ?,
                    tipo_producto = ?, producto = ?, costo_producto = ?, id_forma_pago = ?,
                    dns = ?, url_pago = ?, url_acceso = ?, ns1 = ?, ns2 = ?, ns3 = ?, ns4 = ?, ns5 = ?, ns6 = ?,
                    fecha_contratacion = ?, fecha_pago = ?, estado_producto = ?, IVA = ?, eliminado = 0, frecuencia_pago = ?
                    WHERE id_orden = ?';
                $stmt = transferencia_prepare($target, $sqlHost, 'Hosting UPDATE ConlineWeb');
                transferencia_stmt_bind($stmt, [
                    ['i', $clienteId],
                    ['s', $host['dominio']],
                    ['s', $host['nom_host']],
                    ['s', $host['usuario']],
                    ['s', $host['contrasena']],
                    ['s', $host['contrasena_normal']],
                    ['s', $host['tipo_producto']],
                    ['i', $host['producto']],
                    ['d', $host['costo_producto']],
                    ['i', $host['id_forma_pago']],
                    ['s', $host['dns']],
                    ['s', $host['url_pago']],
                    ['s', $host['url_acceso']],
                    ['s', $host['ns1']],
                    ['s', $host['ns2']],
                    ['s', $host['ns3']],
                    ['s', $host['ns4']],
                    ['s', $host['ns5']],
                    ['s', $host['ns6']],
                    ['s', $host['fecha_contratacion']],
                    ['s', $host['fecha_pago']],
                    ['i', $host['estado_producto']],
                    ['i', $host['IVA']],
                    ['i', $host['frecuencia_pago'] ?? 0],
                    ['i', $host['id_orden']],
                ]);
            } else {
                $sqlHost = 'INSERT INTO hosting (
                    id_orden, cliente_id, dominio, nom_host, usuario, contrasena, contrasena_normal,
                    tipo_producto, producto, costo_producto, id_forma_pago, dns,
                    url_pago, url_acceso, ns1, ns2, ns3, ns4, ns5, ns6, fecha_contratacion,
                    fecha_pago, estado_producto, IVA, eliminado, frecuencia_pago
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0, ?)';

                $stmt = transferencia_prepare($target, $sqlHost, 'Hosting INSERT ConlineWeb');
                transferencia_stmt_bind($stmt, [
                    ['i', $host['id_orden']],
                    ['i', $clienteId],
                    ['s', $host['dominio']],
                    ['s', $host['nom_host']],
                    ['s', $host['usuario']],
                    ['s', $host['contrasena']],
                    ['s', $host['contrasena_normal']],
                    ['s', $host['tipo_producto']],
                    ['i', $host['producto']],
                    ['d', $host['costo_producto']],
                    ['i', $host['id_forma_pago']],
                    ['s', $host['dns']],
                    ['s', $host['url_pago']],
                    ['s', $host['url_acceso']],
                    ['s', $host['ns1']],
                    ['s', $host['ns2']],
                    ['s', $host['ns3']],
                    ['s', $host['ns4']],
                    ['s', $host['ns5']],
                    ['s', $host['ns6']],
                    ['s', $host['fecha_contratacion']],
                    ['s', $host['fecha_pago']],
                    ['i', $host['estado_producto']],
                    ['i', $host['IVA']],
                    ['i', $host['frecuencia_pago'] ?? 0],
                ]);
            }

            if (!$stmt->execute()) {
                throw new RuntimeException('Error al transferir hosting ID ' . $host['id_orden'] . ': ' . $stmt->error);
            }
            $stmt->close();
            $hostingsTransferidos++;
        }

        $stmt = $source->prepare('SELECT * FROM pagos WHERE id_clie = ?');
        $stmt->bind_param('i', $clienteId);
        $stmt->execute();
        $pagos = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        $pagosTransferidos = 0;
        foreach ($pagos as $pago) {
            $pagoRecurrente = $pago['pago_recurrente'] ?? 0;
            $fechaInicio = $pago['fecha_inicio_recurrencia'] ?? null;
            $ultimoGenerado = $pago['ultimo_pago_generado'] ?? null;
            $sistemaPago = 'conlineweb';

            $stmt = transferencia_prepare($target, 'SELECT id FROM pagos WHERE id = ? LIMIT 1', 'Pagos lookup');
            $stmt->bind_param('i', $pago['id']);
            $stmt->execute();
            $pagoExiste = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if ($pagoExiste) {
                $sqlPago = 'UPDATE pagos SET
                    id_clie = ?, id_servicio = ?, fecha = ?, hora = ?, fecha_pago = ?, hora_pago = ?, monto = ?,
                    currency = ?, concepto = ?, forma_pago = ?, estatus = ?, id_pago = ?, session_id = ?,
                    pago_grupal_id = ?, tipo_pago = ?, id_cuenta = ?, tipo_servicio = ?, fecha_limite_pago = ?,
                    manual = ?, frecuencia_pago = ?, pago_recurrente = ?, fecha_inicio_recurrencia = ?,
                    ultimo_pago_generado = ?, Registro = ?, sistema = ?
                    WHERE id = ?';
                $stmt = transferencia_prepare($target, $sqlPago, 'Pagos UPDATE ConlineWeb');
                transferencia_stmt_bind($stmt, [
                    ['i', $clienteId],
                    ['i', $pago['id_servicio']],
                    ['s', $pago['fecha']],
                    ['s', $pago['hora']],
                    ['s', $pago['fecha_pago']],
                    ['s', $pago['hora_pago']],
                    ['d', $pago['monto']],
                    ['s', $pago['currency']],
                    ['s', $pago['concepto']],
                    ['i', $pago['forma_pago']],
                    ['i', $pago['estatus']],
                    ['s', $pago['id_pago']],
                    ['s', $pago['session_id']],
                    ['s', $pago['pago_grupal_id']],
                    ['s', $pago['tipo_pago']],
                    ['i', $pago['id_cuenta']],
                    ['i', $pago['tipo_servicio']],
                    ['s', $pago['fecha_limite_pago']],
                    ['i', $pago['manual']],
                    ['i', $pago['frecuencia_pago'] ?? 0],
                    ['i', $pagoRecurrente],
                    ['s', $fechaInicio],
                    ['s', $ultimoGenerado],
                    ['i', $pago['Registro']],
                    ['s', $sistemaPago],
                    ['i', $pago['id']],
                ]);
            } else {
                $sqlPago = 'INSERT INTO pagos (
                    id, id_clie, id_servicio, fecha, hora, fecha_pago, hora_pago, monto, currency, concepto,
                    forma_pago, estatus, id_pago, session_id, pago_grupal_id, tipo_pago, id_cuenta,
                    tipo_servicio, fecha_limite_pago, manual, frecuencia_pago, pago_recurrente,
                    fecha_inicio_recurrencia, ultimo_pago_generado, Registro, sistema
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)';

                $stmt = transferencia_prepare($target, $sqlPago, 'Pagos INSERT ConlineWeb');
                transferencia_stmt_bind($stmt, [
                    ['i', $pago['id']],
                    ['i', $clienteId],
                    ['i', $pago['id_servicio']],
                    ['s', $pago['fecha']],
                    ['s', $pago['hora']],
                    ['s', $pago['fecha_pago']],
                    ['s', $pago['hora_pago']],
                    ['d', $pago['monto']],
                    ['s', $pago['currency']],
                    ['s', $pago['concepto']],
                    ['i', $pago['forma_pago']],
                    ['i', $pago['estatus']],
                    ['s', $pago['id_pago']],
                    ['s', $pago['session_id']],
                    ['s', $pago['pago_grupal_id']],
                    ['s', $pago['tipo_pago']],
                    ['i', $pago['id_cuenta']],
                    ['i', $pago['tipo_servicio']],
                    ['s', $pago['fecha_limite_pago']],
                    ['i', $pago['manual']],
                    ['i', $pago['frecuencia_pago'] ?? 0],
                    ['i', $pagoRecurrente],
                    ['s', $fechaInicio],
                    ['s', $ultimoGenerado],
                    ['i', $pago['Registro']],
                    ['s', $sistemaPago],
                ]);
            }

            if (!$stmt->execute()) {
                throw new RuntimeException('Error al transferir pago ID ' . $pago['id'] . ': ' . $stmt->error);
            }
            $stmt->close();
            $pagosTransferidos++;
        }

        transferencia_actualizar_auto_increment($target, 'clientes', 'id');
        transferencia_actualizar_auto_increment($target, 'dominios', 'id_dominio');
        transferencia_actualizar_auto_increment($target, 'hosting', 'id_orden');
        transferencia_actualizar_auto_increment($target, 'pagos', 'id');

        $detalle = json_encode([
            'cliente_id' => $clienteId,
            'dominios' => $dominiosTransferidos,
            'hostings' => $hostingsTransferidos,
            'pagos' => $pagosTransferidos,
            'origen' => 'hostingpro',
        ], JSON_UNESCAPED_UNICODE);

        if (transferencia_columna_existe($target, 'clientes', 'transferido')) {
            $checkLog = $target->query("SHOW TABLES LIKE 'clientes_transferencia_log'");
            if ($checkLog && $checkLog->num_rows > 0) {
            $sqlLog = 'INSERT INTO clientes_transferencia_log
                (cliente_id, usuario_id, destino_sistema, dominios_transferidos, hostings_transferidos, pagos_transferidos, detalle)
                VALUES (?, ?, ?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE
                    usuario_id = VALUES(usuario_id),
                    fecha_transferencia = CURRENT_TIMESTAMP,
                    destino_sistema = VALUES(destino_sistema),
                    dominios_transferidos = VALUES(dominios_transferidos),
                    hostings_transferidos = VALUES(hostings_transferidos),
                    pagos_transferidos = VALUES(pagos_transferidos),
                    detalle = VALUES(detalle)';

            $destino = 'conlineweb';
            $stmt = $target->prepare($sqlLog);
            transferencia_stmt_bind($stmt, [
                ['i', $clienteId],
                ['i', $usuarioId],
                ['s', $destino],
                ['i', $dominiosTransferidos],
                ['i', $hostingsTransferidos],
                ['i', $pagosTransferidos],
                ['s', $detalle],
            ]);
            if (!$stmt->execute()) {
                throw new RuntimeException('Error al registrar auditoría: ' . $stmt->error);
            }
            $stmt->close();
            }
        }

        $stmt = $source->prepare('UPDATE clientes SET eliminado = 1 WHERE id = ? AND eliminado = 0');
        $stmt->bind_param('i', $clienteId);
        if (!$stmt->execute() || $stmt->affected_rows === 0) {
            throw new RuntimeException('No se pudo marcar el cliente como transferido en Hosting Pro.');
        }
        $stmt->close();

        $stmt = $source->prepare('UPDATE dominios SET eliminado = 1 WHERE cliente_id = ?');
        $stmt->bind_param('i', $clienteId);
        $stmt->execute();
        $stmt->close();

        $stmt = $source->prepare('UPDATE hosting SET eliminado = 1 WHERE cliente_id = ?');
        $stmt->bind_param('i', $clienteId);
        $stmt->execute();
        $stmt->close();

        $target->commit();
        $source->commit();

        return [
            'success' => true,
            'message' => "Cliente #$clienteId transferido a ConlineWeb. Dominios: $dominiosTransferidos, Hostings: $hostingsTransferidos, Pagos: $pagosTransferidos.",
            'data' => [
                'cliente_id' => $clienteId,
                'dominios' => $dominiosTransferidos,
                'hostings' => $hostingsTransferidos,
                'pagos' => $pagosTransferidos,
            ],
        ];
    } catch (Throwable $e) {
        $target->rollback();
        $source->rollback();
        return [
            'success' => false,
            'message' => 'Error en la transferencia: ' . $e->getMessage(),
        ];
    }
}
