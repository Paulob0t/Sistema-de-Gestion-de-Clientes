<?php
/**
 * Recurrencia de pagos manuales (detalle_cliente).
 * Frecuencias: 0 único · 1 semanal · 2 mensual · 3 anual · 4 personalizado (N días)
 */
declare(strict_types=1);

if (!function_exists('cw_pago_frecuencia_labels')) {
    /** @return array<int, string> */
    function cw_pago_frecuencia_labels(): array
    {
        return [
            0 => 'Único',
            1 => 'Semanal',
            2 => 'Mensual',
            3 => 'Anual',
            4 => 'Personalizado',
        ];
    }
}

if (!function_exists('cw_pago_table_has_column')) {
    function cw_pago_table_has_column(mysqli $conn, string $column): bool
    {
        $col = $conn->real_escape_string($column);
        $check = @$conn->query("SHOW COLUMNS FROM pagos LIKE '{$col}'");
        return $check && $check->num_rows > 0;
    }
}

if (!function_exists('cw_pago_ensure_recurrencia_columns')) {
    function cw_pago_ensure_recurrencia_columns(mysqli $conn): void
    {
        static $done = [];
        $key = spl_object_id($conn);
        if (!empty($done[$key])) {
            return;
        }

        // Sin AFTER: evita fallar si la columna de referencia no existe en algún entorno.
        $needed = [
            'intervalo_dias' => 'ALTER TABLE pagos ADD COLUMN intervalo_dias INT NOT NULL DEFAULT 0',
            'serie_token' => 'ALTER TABLE pagos ADD COLUMN serie_token VARCHAR(40) NULL DEFAULT NULL',
            'serie_total' => 'ALTER TABLE pagos ADD COLUMN serie_total INT NOT NULL DEFAULT 0',
            'serie_indice' => 'ALTER TABLE pagos ADD COLUMN serie_indice INT NOT NULL DEFAULT 0',
        ];

        foreach ($needed as $col => $sql) {
            if (!cw_pago_table_has_column($conn, $col)) {
                @$conn->query($sql);
            }
        }

        cw_pago_ensure_unique_index($conn);

        $done[$key] = true;
    }
}

if (!function_exists('cw_pago_ultimo_error')) {
    /**
     * Guarda/lee el último error de inserción para poder mostrarlo al usuario
     * en lugar del mensaje crudo de MySQL.
     */
    function cw_pago_ultimo_error(?string $set = null): string
    {
        static $last = '';
        if ($set !== null) {
            $last = $set;
        }
        return $last;
    }
}

if (!function_exists('cw_pago_ensure_unique_index')) {
    /**
     * idx_unique_pago existe para impedir renovaciones automáticas duplicadas
     * (mismo cliente + servicio + fecha). Los pagos manuales guardan
     * id_servicio = 0 y tipo_servicio = 0, así que la clave degeneraba en
     * (cliente, fecha_limite_pago) e impedía cobrar dos conceptos distintos
     * al mismo cliente con la misma fecha de vencimiento.
     *
     * Se añade una columna generada que vale NULL en los pagos manuales: un
     * índice UNIQUE no compara filas con NULL, así que la restricción sigue
     * aplicando igual a los pagos automáticos y deja de aplicar a los manuales.
     */
    function cw_pago_ensure_unique_index(mysqli $conn): void
    {
        static $done = [];
        $key = spl_object_id($conn);
        if (!empty($done[$key])) {
            return;
        }
        $done[$key] = true;

        $res = @$conn->query("SHOW INDEX FROM pagos WHERE Key_name = 'idx_unique_pago'");
        if (!$res || $res->num_rows === 0) {
            return;
        }

        $cols = [];
        while ($row = $res->fetch_assoc()) {
            $cols[(int) $row['Seq_in_index']] = (string) $row['Column_name'];
        }
        ksort($cols);

        if (in_array('uk_auto_guard', $cols, true)) {
            return;
        }

        if (!cw_pago_table_has_column($conn, 'uk_auto_guard')) {
            if (!@$conn->query(
                'ALTER TABLE pagos ADD COLUMN uk_auto_guard TINYINT'
                . ' GENERATED ALWAYS AS (IF(manual = 1, NULL, 0)) VIRTUAL'
            )) {
                error_log('cw_pago_ensure_unique_index add column: ' . $conn->error);
                return;
            }
        }

        $cols[] = 'uk_auto_guard';
        $nuevo = '`' . implode('`, `', $cols) . '`';

        if (!@$conn->query('ALTER TABLE pagos DROP INDEX idx_unique_pago')) {
            error_log('cw_pago_ensure_unique_index drop: ' . $conn->error);
            return;
        }
        if (!@$conn->query('ALTER TABLE pagos ADD UNIQUE KEY idx_unique_pago (' . $nuevo . ')')) {
            error_log('cw_pago_ensure_unique_index add: ' . $conn->error);
        }
    }
}

if (!function_exists('cw_pago_avanzar_fecha')) {
    /**
     * @param int $frecuencia 1=semanal 2=mensual 3=anual 4=personalizado
     * @param int $intervaloDias Solo para frecuencia 4 (mínimo 1)
     */
    function cw_pago_avanzar_fecha(string $fechaYmd, int $frecuencia, int $intervaloDias = 0): string
    {
        $dt = DateTime::createFromFormat('Y-m-d', $fechaYmd) ?: new DateTime($fechaYmd);
        switch ($frecuencia) {
            case 1:
                $dt->modify('+7 days');
                break;
            case 2:
                $dt->modify('+1 month');
                break;
            case 3:
                $dt->modify('+1 year');
                break;
            case 4:
                $dias = max(1, $intervaloDias);
                $dt->modify('+' . $dias . ' days');
                break;
            default:
                break;
        }
        return $dt->format('Y-m-d');
    }
}

if (!function_exists('cw_pago_normalizar_repeticiones')) {
    function cw_pago_normalizar_repeticiones(?int $num, bool $indefinido): int
    {
        if ($indefinido) {
            return 1;
        }
        if ($num === null || $num < 1) {
            return 12;
        }
        return min(120, $num);
    }
}

if (!function_exists('cw_pago_insertar_pendiente')) {
    /**
     * Inserta un pago pendiente manual (sin Stripe).
     *
     * @return int|false insert_id
     */
    function cw_pago_insertar_pendiente(
        mysqli $conn,
        int $clienteId,
        float $monto,
        string $currency,
        string $concepto,
        string $fechaLimite,
        string $sistema,
        int $frecuencia,
        int $pagoRecurrente,
        ?string $fechaInicioRecurrencia,
        int $intervaloDias,
        ?string $serieToken,
        int $serieTotal,
        int $serieIndice
    ) {
        cw_pago_ensure_recurrencia_columns($conn);

        $fechaInicio = ($fechaInicioRecurrencia !== null && $fechaInicioRecurrencia !== '')
            ? $fechaInicioRecurrencia
            : null;
        $token = ($serieToken !== null && $serieToken !== '') ? $serieToken : null;
        $hasExtra = cw_pago_table_has_column($conn, 'serie_token')
            && cw_pago_table_has_column($conn, 'intervalo_dias');

        if ($hasExtra) {
            $sql = "INSERT INTO pagos (
                id_clie, id_servicio, fecha, hora,
                fecha_pago, hora_pago, monto, currency,
                concepto, forma_pago, estatus, id_pago,
                session_id, id_cuenta, tipo_servicio, fecha_limite_pago, manual, sistema,
                frecuencia_pago, pago_recurrente, fecha_inicio_recurrencia, ultimo_pago_generado,
                intervalo_dias, serie_token, serie_total, serie_indice
            ) VALUES (
                ?, 0, CURDATE(), CURTIME(),
                '0000-00-00', '00:00:00', ?, ?,
                ?, 0, 0, '',
                '', 0, 0, ?, 1, ?,
                ?, ?, ?, CURDATE(),
                ?, ?, ?, ?
            )";
            $stmt = $conn->prepare($sql);
            if (!$stmt) {
                error_log('cw_pago_insertar_pendiente prepare(extra): ' . $conn->error);
                return false;
            }

            /* NULL real: fecha_inicio_recurrencia y serie_token son nullables y
               comparar una columna DATE contra '' hace fallar a MySQL 8. */
            $stmt->bind_param(
                'idssssiisisii',
                $clienteId,
                $monto,
                $currency,
                $concepto,
                $fechaLimite,
                $sistema,
                $frecuencia,
                $pagoRecurrente,
                $fechaInicio,
                $intervaloDias,
                $token,
                $serieTotal,
                $serieIndice
            );
        } else {
            // Fallback: esquema antiguo (solo flags de recurrencia)
            $sql = "INSERT INTO pagos (
                id_clie, id_servicio, fecha, hora,
                fecha_pago, hora_pago, monto, currency,
                concepto, forma_pago, estatus, id_pago,
                session_id, id_cuenta, tipo_servicio, fecha_limite_pago, manual, sistema,
                frecuencia_pago, pago_recurrente, fecha_inicio_recurrencia, ultimo_pago_generado
            ) VALUES (
                ?, 0, CURDATE(), CURTIME(),
                '0000-00-00', '00:00:00', ?, ?,
                ?, 0, 0, '',
                '', 0, 0, ?, 1, ?,
                ?, ?, ?, CURDATE()
            )";
            $stmt = $conn->prepare($sql);
            if (!$stmt) {
                error_log('cw_pago_insertar_pendiente prepare(base): ' . $conn->error);
                return false;
            }
            $stmt->bind_param(
                'idssssiis',
                $clienteId,
                $monto,
                $currency,
                $concepto,
                $fechaLimite,
                $sistema,
                $frecuencia,
                $pagoRecurrente,
                $fechaInicio
            );
        }

        /* mysqli lanza excepción con el report mode por defecto de PHP 8.1+,
           así que el fallo hay que capturarlo además del retorno booleano. */
        try {
            $ok = $stmt->execute();
        } catch (mysqli_sql_exception $e) {
            $ok = false;
        }

        if (!$ok) {
            error_log('cw_pago_insertar_pendiente execute: ' . $stmt->error . ' | ' . $conn->error);
            cw_pago_ultimo_error(
                $conn->errno === 1062
                    ? 'Ya existe un pago con vencimiento el ' . date('d/m/Y', strtotime($fechaLimite))
                        . ' para este cliente y servicio. Cambia la fecha límite.'
                    : ($stmt->error !== '' ? $stmt->error : $conn->error)
            );
            $stmt->close();
            return false;
        }

        cw_pago_ultimo_error('');
        $id = (int) $conn->insert_id;
        $stmt->close();

        return $id > 0 ? $id : false;
    }
}

if (!function_exists('cw_pago_crear_serie_manual')) {
    /**
     * @return array{ok:bool, primer_id?:int, ids:list<int>, total:int, mensaje?:string, indefinido?:bool, serie_token?:?string}
     */
    function cw_pago_crear_serie_manual(
        mysqli $conn,
        int $clienteId,
        float $monto,
        string $currency,
        string $concepto,
        string $fechaLimitePrimera,
        string $sistema,
        int $frecuencia,
        int $intervaloDias,
        ?int $numRepeticiones
    ): array {
        cw_pago_ensure_recurrencia_columns($conn);

        if ($frecuencia < 0 || $frecuencia > 4) {
            return ['ok' => false, 'ids' => [], 'total' => 0, 'mensaje' => 'Frecuencia inválida'];
        }

        if ($frecuencia === 4 && $intervaloDias < 1) {
            return ['ok' => false, 'ids' => [], 'total' => 0, 'mensaje' => 'Indica cada cuántos días (personalizado)'];
        }

        $indefinido = ($frecuencia > 0 && ($numRepeticiones === null || $numRepeticiones < 1));
        $pagoRecurrente = $frecuencia > 0 ? 1 : 0;
        $total = $frecuencia === 0
            ? 1
            : cw_pago_normalizar_repeticiones($numRepeticiones, $indefinido);

        $serieTotalStored = ($frecuencia > 0 && $indefinido) ? 0 : $total;
        $serieToken = $frecuencia > 0 ? bin2hex(random_bytes(8)) : null;
        $fechaInicio = $frecuencia > 0 ? $fechaLimitePrimera : null;

        $ids = [];
        $fecha = $fechaLimitePrimera;

        for ($i = 1; $i <= $total; $i++) {
            $conceptoItem = $concepto;
            if ($frecuencia > 0 && $serieTotalStored > 1) {
                $conceptoItem = $concepto . ' (' . $i . '/' . $serieTotalStored . ')';
            } elseif ($frecuencia > 0 && $serieTotalStored === 0 && $i === 1) {
                $conceptoItem = $concepto . ' (recurrente)';
            }

            $id = cw_pago_insertar_pendiente(
                $conn,
                $clienteId,
                $monto,
                $currency,
                $conceptoItem,
                $fecha,
                $sistema,
                $frecuencia,
                $pagoRecurrente,
                $fechaInicio,
                $frecuencia === 4 ? max(1, $intervaloDias) : 0,
                $serieToken,
                $serieTotalStored,
                $frecuencia > 0 ? $i : 0
            );

            if ($id === false) {
                $detalle = cw_pago_ultimo_error();
                return [
                    'ok' => false,
                    'ids' => $ids,
                    'total' => count($ids),
                    'mensaje' => $detalle !== ''
                        ? ($total > 1 ? 'Pago ' . $i . ' de ' . $total . ': ' . $detalle : $detalle)
                        : 'Error al insertar pago #' . $i . ' de la serie. Revisa que la tabla pagos tenga las columnas de recurrencia o permisos ALTER.',
                    'primer_id' => $ids[0] ?? null,
                ];
            }

            $ids[] = $id;

            if ($i < $total) {
                $fecha = cw_pago_avanzar_fecha($fecha, $frecuencia, $intervaloDias);
            }
        }

        return [
            'ok' => true,
            'primer_id' => $ids[0],
            'ids' => $ids,
            'total' => count($ids),
            'indefinido' => $indefinido,
            'serie_token' => $serieToken,
        ];
    }
}

if (!function_exists('cw_pago_generar_siguientes_recurrentes_manuales')) {
    /**
     * @return array{generados:int, mensajes:list<string>}
     */
    function cw_pago_generar_siguientes_recurrentes_manuales(mysqli $conn, string $sistema, DateTime $fechaActual): array
    {
        $out = ['generados' => 0, 'mensajes' => []];
        cw_pago_ensure_recurrencia_columns($conn);

        if (!cw_pago_table_has_column($conn, 'serie_token')) {
            $out['mensajes'][] = 'Columnas de serie no disponibles; se omite recurrencia manual indefinida.';
            return $out;
        }

        $sql = "SELECT p.*
                FROM pagos p
                INNER JOIN (
                    SELECT serie_token, MAX(serie_indice) AS max_idx
                    FROM pagos
                    WHERE pago_recurrente = 1
                      AND manual = 1
                      AND frecuencia_pago > 0
                      AND serie_token IS NOT NULL
                      AND serie_token != ''
                      AND (sistema = ? OR (sistema IS NULL AND ? = 'conlineweb'))
                      AND Registro = 0
                    GROUP BY serie_token
                ) t ON p.serie_token = t.serie_token AND p.serie_indice = t.max_idx
                WHERE p.pago_recurrente = 1
                  AND p.serie_total = 0";

        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            $out['mensajes'][] = 'No se pudo preparar recurrencia manual: ' . $conn->error;
            return $out;
        }
        $stmt->bind_param('ss', $sistema, $sistema);
        $stmt->execute();
        $res = $stmt->get_result();

        while ($row = $res->fetch_assoc()) {
            $freq = (int) ($row['frecuencia_pago'] ?? 0);
            $intervalo = (int) ($row['intervalo_dias'] ?? 0);
            $fechaBase = (string) ($row['fecha_limite_pago'] ?? '');
            if ($fechaBase === '' || $fechaBase === '0000-00-00') {
                continue;
            }

            $proxima = cw_pago_avanzar_fecha($fechaBase, $freq, $intervalo);
            $proxDt = new DateTime($proxima);
            $diff = (int) $fechaActual->diff($proxDt)->format('%r%a');
            if ($diff > 30 || $diff < -7) {
                continue;
            }

            $token = (string) $row['serie_token'];
            $nextIdx = (int) $row['serie_indice'] + 1;

            $chk = $conn->prepare(
                'SELECT id FROM pagos WHERE serie_token = ? AND serie_indice = ? AND Registro = 0 LIMIT 1'
            );
            if ($chk) {
                $chk->bind_param('si', $token, $nextIdx);
                $chk->execute();
                if ($chk->get_result()->num_rows > 0) {
                    $chk->close();
                    continue;
                }
                $chk->close();
            }

            $conceptoBase = preg_replace('/\s*\(recurrente\)\s*$/u', '', (string) $row['concepto']) ?? (string) $row['concepto'];
            $conceptoBase = trim($conceptoBase) . ' (recurrente)';

            $newId = cw_pago_insertar_pendiente(
                $conn,
                (int) $row['id_clie'],
                (float) $row['monto'],
                (string) $row['currency'],
                $conceptoBase,
                $proxima,
                $sistema,
                $freq,
                1,
                (string) ($row['fecha_inicio_recurrencia'] ?? $fechaBase),
                $intervalo,
                $token,
                0,
                $nextIdx
            );

            if ($newId) {
                $out['generados']++;
                $out['mensajes'][] = "Pago recurrente manual #{$newId} generado (serie {$token}, índice {$nextIdx}, vence {$proxima})";
                $lastId = (int) $row['id'];
                @$conn->query('UPDATE pagos SET ultimo_pago_generado = CURDATE() WHERE id = ' . $lastId);
            }
        }

        $stmt->close();
        return $out;
    }
}
