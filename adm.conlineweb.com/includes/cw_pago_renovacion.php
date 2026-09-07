<?php
/**
 * Renovación de hosting/dominio al acreditar un pago.
 * Usado por payment_success, payment_success_grupal y stripe_webhook.
 */
declare(strict_types=1);

if (!function_exists('cw_stripe_credentials_candidates')) {
    /**
     * @return list<string>
     */
    function cw_stripe_credentials_candidates(): array
    {
        $admRoot = dirname(__DIR__); // adm.conlineweb.com
        $home = dirname($admRoot); // /home/{user} en cPanel típico
        $envHome = rtrim((string) (getenv('HOME') ?: ''), '/');
        $user = (string) (function_exists('get_current_user') ? get_current_user() : '');

        $names = [
            '.stripe_credentials_cw',
            'stripe_credentials_cw',
            '.stripe_credentials',
            'stripe_credentials',
        ];
        $dirs = array_filter([
            $home,
            $admRoot,
            dirname($home),
            $envHome,
            ($user !== '' ? '/home/' . $user : ''),
        ]);
        $paths = [];
        foreach ($dirs as $dir) {
            foreach ($names as $name) {
                $paths[] = rtrim((string) $dir, '/') . '/' . $name;
            }
        }

        return array_values(array_unique($paths));
    }
}

if (!function_exists('cw_stripe_load_credentials')) {
    /**
     * @return array<string, mixed>
     */
    function cw_stripe_load_credentials(): array
    {
        static $cached = null;
        if (is_array($cached)) {
            return $cached;
        }

        foreach (cw_stripe_credentials_candidates() as $path) {
            if (!is_file($path)) {
                continue;
            }
            $creds = parse_ini_file($path);
            if (is_array($creds) && $creds !== []) {
                $cached = $creds;
                return $cached;
            }
        }

        $cached = [];
        return $cached;
    }
}

if (!function_exists('cw_stripe_is_test_mode')) {
    function cw_stripe_is_test_mode(): bool
    {
        $creds = cw_stripe_load_credentials();
        if (isset($creds['STRIPE_TEST_MODE'])) {
            return filter_var($creds['STRIPE_TEST_MODE'], FILTER_VALIDATE_BOOLEAN);
        }

        $envKey = (string) ($_ENV['STRIPE_SECRET_KEY'] ?? '');
        return strpos($envKey, 'sk_test_') === 0;
    }
}

if (!function_exists('cw_stripe_resolve_secret_key')) {
    function cw_stripe_resolve_secret_key(): string
    {
        $creds = cw_stripe_load_credentials();
        $fromFile = cw_stripe_is_test_mode()
            ? (string) ($creds['STRIPE_TEST_SECRET_KEY'] ?? '')
            : (string) ($creds['STRIPE_LIVE_SECRET_KEY'] ?? '');

        if ($fromFile !== '') {
            return $fromFile;
        }

        return (string) ($_ENV['STRIPE_SECRET_KEY'] ?? '');
    }
}

if (!function_exists('cw_stripe_resolve_webhook_secret')) {
    function cw_stripe_resolve_webhook_secret(): string
    {
        $all = cw_stripe_webhook_secrets();
        return $all[0] ?? '';
    }
}

if (!function_exists('cw_stripe_webhook_secrets')) {
    /**
     * Todos los whsec conocidos (live + test + env).
     * Stripe modo activo firma con el secret del endpoint; no debemos
     * descartar el live solo porque STRIPE_TEST_MODE=true.
     *
     * @return list<string>
     */
    function cw_stripe_webhook_secrets(): array
    {
        $creds = cw_stripe_load_credentials();
        // Live primero: el aviso de Stripe es modo activo. El test del archivo es placeholder.
        $preferred = ['STRIPE_LIVE_WEBHOOK_SECRET', 'STRIPE_TEST_WEBHOOK_SECRET', 'STRIPE_WEBHOOK_SECRET'];

        $out = [];
        $usable = static function (string $val): bool {
            $val = trim($val);
            if ($val === '' || strncmp($val, 'whsec_', 6) !== 0) {
                return false;
            }
            // Placeholder del archivo local, no es un secret real
            if (stripos($val, 'CONFIGURA') !== false || strlen($val) < 20) {
                return false;
            }
            return true;
        };

        foreach ($preferred as $key) {
            $val = trim((string) ($creds[$key] ?? ''));
            if ($usable($val)) {
                $out[] = $val;
            }
        }
        foreach (['STRIPE_WEBHOOK_SECRET', 'STRIPE_LIVE_WEBHOOK_SECRET', 'STRIPE_TEST_WEBHOOK_SECRET'] as $envKey) {
            $val = trim((string) ($_ENV[$envKey] ?? getenv($envKey) ?: ''));
            if ($usable($val)) {
                $out[] = $val;
            }
        }

        return array_values(array_unique($out));
    }
}

if (!function_exists('cw_renovar_servicio')) {
    /**
     * Extiende vigencia +1 año (misma fórmula que payment_success Stripe).
     *
     * @return array{ok:bool, renewed:bool, tipo:int, id_servicio:int, fecha_antes:?string, fecha_despues:?string, error:?string}
     */
    function cw_renovar_servicio(mysqli $conn, int $tipoServicio, int $idServicio): array
    {
        $out = [
            'ok' => false,
            'renewed' => false,
            'tipo' => $tipoServicio,
            'id_servicio' => $idServicio,
            'fecha_antes' => null,
            'fecha_despues' => null,
            'error' => null,
        ];

        if ($idServicio <= 0) {
            $out['error'] = 'id_servicio inválido';
            return $out;
        }

        if ($tipoServicio === 1) {
            $sel = $conn->prepare('SELECT fecha_pago FROM hosting WHERE id_orden = ? LIMIT 1');
            if (!$sel) {
                $out['error'] = $conn->error;
                return $out;
            }
            $sel->bind_param('i', $idServicio);
            $sel->execute();
            $row = $sel->get_result()->fetch_assoc();
            $sel->close();
            if (!$row) {
                $out['error'] = 'Hosting no encontrado';
                return $out;
            }
            $out['fecha_antes'] = $row['fecha_pago'] ?? null;

            $upd = $conn->prepare(
                'UPDATE hosting SET fecha_pago = DATE_ADD(IFNULL(fecha_pago, NOW()), INTERVAL 1 YEAR), estado_producto = 1 WHERE id_orden = ?'
            );
            if (!$upd) {
                $out['error'] = $conn->error;
                return $out;
            }
            $upd->bind_param('i', $idServicio);
            $ok = $upd->execute();
            $upd->close();
            if (!$ok) {
                $out['error'] = $conn->error;
                return $out;
            }

            $sel2 = $conn->prepare('SELECT fecha_pago FROM hosting WHERE id_orden = ? LIMIT 1');
            $sel2->bind_param('i', $idServicio);
            $sel2->execute();
            $out['fecha_despues'] = $sel2->get_result()->fetch_assoc()['fecha_pago'] ?? null;
            $sel2->close();
            $out['ok'] = true;
            $out['renewed'] = true;
            return $out;
        }

        if ($tipoServicio === 2) {
            $sel = $conn->prepare('SELECT fecha_pago FROM dominios WHERE id_dominio = ? LIMIT 1');
            if (!$sel) {
                $out['error'] = $conn->error;
                return $out;
            }
            $sel->bind_param('i', $idServicio);
            $sel->execute();
            $row = $sel->get_result()->fetch_assoc();
            $sel->close();
            if (!$row) {
                $out['error'] = 'Dominio no encontrado';
                return $out;
            }
            $out['fecha_antes'] = $row['fecha_pago'] ?? null;

            $upd = $conn->prepare(
                'UPDATE dominios SET fecha_pago = DATE_ADD(IFNULL(fecha_pago, NOW()), INTERVAL 1 YEAR), estado_dominio = 1 WHERE id_dominio = ?'
            );
            if (!$upd) {
                $out['error'] = $conn->error;
                return $out;
            }
            $upd->bind_param('i', $idServicio);
            $ok = $upd->execute();
            $upd->close();
            if (!$ok) {
                $out['error'] = $conn->error;
                return $out;
            }

            $sel2 = $conn->prepare('SELECT fecha_pago FROM dominios WHERE id_dominio = ? LIMIT 1');
            $sel2->bind_param('i', $idServicio);
            $sel2->execute();
            $out['fecha_despues'] = $sel2->get_result()->fetch_assoc()['fecha_pago'] ?? null;
            $sel2->close();
            $out['ok'] = true;
            $out['renewed'] = true;
            return $out;
        }

        // tipo 0 / manual: no renueva tablas de servicio
        $out['ok'] = true;
        $out['renewed'] = false;
        return $out;
    }
}

if (!function_exists('cw_pagos_por_checkout_session')) {
    /**
     * @return list<array<string,mixed>>
     */
    function cw_pagos_por_checkout_session(mysqli $conn, string $sessionId, $session = null): array
    {
        $rows = [];
        $stmt = $conn->prepare(
            'SELECT id, id_clie, id_servicio, tipo_servicio, estatus, session_id
             FROM pagos WHERE session_id = ?'
        );
        if ($stmt) {
            $stmt->bind_param('s', $sessionId);
            $stmt->execute();
            $res = $stmt->get_result();
            while ($row = $res->fetch_assoc()) {
                $rows[] = $row;
            }
            $stmt->close();
        }

        if ($rows !== []) {
            return $rows;
        }

        // Fallback: metadata.ids (pago múltiple sin session_id en todas las filas)
        $idsCsv = '';
        if (is_object($session) && isset($session->metadata)) {
            $idsCsv = (string) ($session->metadata->ids ?? '');
        }
        $idList = array_values(array_filter(array_map('intval', explode(',', $idsCsv))));
        if ($idList === []) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($idList), '?'));
        $types = str_repeat('i', count($idList));
        $sql = "SELECT id, id_clie, id_servicio, tipo_servicio, estatus, session_id
                FROM pagos WHERE id IN ($placeholders)";
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            return [];
        }
        $stmt->bind_param($types, ...$idList);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) {
            $rows[] = $row;
        }
        $stmt->close();

        return $rows;
    }
}

if (!function_exists('cw_acreditar_pagos_checkout')) {
    /**
     * Marca pagos como pagados (tarjeta) y renueva hosting/dominio.
     * Idempotente: si estatus=1 no vuelve a renovar.
     *
     * @param list<array<string,mixed>> $pagos
     * @return array{ok:bool, newly_paid:list<int>, already_paid:list<int>, renewed:list<array>, errors:list<string>}
     */
    function cw_acreditar_pagos_checkout(mysqli $conn, array $pagos): array
    {
        $result = [
            'ok' => true,
            'newly_paid' => [],
            'already_paid' => [],
            'renewed' => [],
            'errors' => [],
        ];

        foreach ($pagos as $pago) {
            $pagoId = (int) ($pago['id'] ?? 0);
            $tipo = (int) ($pago['tipo_servicio'] ?? -1);
            $idServicio = (int) ($pago['id_servicio'] ?? 0);
            $estatus = (int) ($pago['estatus'] ?? 0);

            if ($pagoId <= 0) {
                $result['errors'][] = 'Pago sin id';
                $result['ok'] = false;
                continue;
            }

            if ($estatus === 1) {
                $result['already_paid'][] = $pagoId;
                continue;
            }

            $upd = $conn->prepare(
                'UPDATE pagos SET forma_pago = 1, estatus = 1, fecha_pago = CURDATE(), hora_pago = NOW() WHERE id = ? AND estatus = 0'
            );
            if (!$upd) {
                $result['errors'][] = "Pago {$pagoId}: " . $conn->error;
                $result['ok'] = false;
                continue;
            }
            $upd->bind_param('i', $pagoId);
            $upd->execute();
            $affected = $upd->affected_rows;
            $upd->close();

            if ($affected < 1) {
                // Carrera con payment_success: ya lo marcó otro proceso
                $result['already_paid'][] = $pagoId;
                continue;
            }

            $result['newly_paid'][] = $pagoId;

            if ($tipo === 1 || $tipo === 2) {
                $ren = cw_renovar_servicio($conn, $tipo, $idServicio);
                $result['renewed'][] = array_merge(['pago_id' => $pagoId], $ren);
                if (empty($ren['ok'])) {
                    $result['errors'][] = "Pago {$pagoId} renovación: " . ($ren['error'] ?? 'falló');
                    $result['ok'] = false;
                }
            }
        }

        return $result;
    }
}
