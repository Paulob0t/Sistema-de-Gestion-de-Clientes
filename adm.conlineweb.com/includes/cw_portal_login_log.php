<?php
/**
 * Historial de accesos al portal (éxitos, fallos, bloqueos) + sesiones abiertas.
 */
declare(strict_types=1);

if (!function_exists('cw_portal_login_log_ensure_table')) {
    function cw_portal_login_log_ensure_table(mysqli $conn): void
    {
        static $done = false;
        if ($done) {
            return;
        }
        $created = $conn->query(
            "CREATE TABLE IF NOT EXISTS cw_portal_login_events (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                event_type VARCHAR(20) NOT NULL,
                uid INT NULL,
                attempted_user VARCHAR(120) NOT NULL DEFAULT '',
                usuario VARCHAR(120) NOT NULL DEFAULT '',
                tipo TINYINT NULL,
                portal VARCHAR(20) NOT NULL DEFAULT 'cliente',
                ip VARCHAR(45) NOT NULL DEFAULT '',
                geo_label VARCHAR(255) NOT NULL DEFAULT '',
                geo_country VARCHAR(80) NOT NULL DEFAULT '',
                geo_region VARCHAR(80) NOT NULL DEFAULT '',
                geo_city VARCHAR(80) NOT NULL DEFAULT '',
                isp VARCHAR(160) NOT NULL DEFAULT '',
                user_agent VARCHAR(500) NOT NULL DEFAULT '',
                reason VARCHAR(80) NOT NULL DEFAULT '',
                environment VARCHAR(20) NOT NULL DEFAULT '',
                session_token CHAR(64) NULL,
                last_seen_at DATETIME NULL,
                ended_at DATETIME NULL,
                created_at DATETIME NOT NULL,
                PRIMARY KEY (id),
                KEY idx_created (created_at),
                KEY idx_type_created (event_type, created_at),
                KEY idx_uid_created (uid, created_at),
                KEY idx_ip_created (ip, created_at),
                KEY idx_session (session_token),
                KEY idx_open (event_type, ended_at, last_seen_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
        unset($created);

        /*
         * Columnas de revocación. Se añaden aparte para no tocar las
         * instalaciones que ya tienen la tabla creada; el CREATE de arriba
         * solo actúa la primera vez.
         *
         * ended_at ya marcaba "sesión terminada", pero no distingue entre un
         * cierre voluntario y uno forzado desde el panel. Estas columnas
         * guardan quién lo ordenó y cuándo, que es lo que hace falta para
         * poder auditarlo después.
         */
        foreach ([
            'revoked_at' => 'DATETIME NULL',
            'revoked_by' => 'INT NULL',
            'revoked_by_name' => "VARCHAR(120) NOT NULL DEFAULT ''",
            /* Ubicación declarada por el navegador (obligatoria para el panel). */
            'gps_lat' => 'DECIMAL(10,7) NULL',
            'gps_lng' => 'DECIMAL(10,7) NULL',
            'gps_accuracy' => 'INT NULL',
        ] as $col => $definition) {
            $res = $conn->query(
                "SHOW COLUMNS FROM cw_portal_login_events LIKE '" . $conn->real_escape_string($col) . "'"
            );
            if ($res instanceof mysqli_result && $res->num_rows === 0) {
                $conn->query("ALTER TABLE cw_portal_login_events ADD COLUMN {$col} {$definition}");
            }
        }

        $done = true;
    }
}

if (!function_exists('cw_portal_login_log_find')) {
    /**
     * Devuelve una sesión concreta, o null si no existe.
     *
     * @return array<string,mixed>|null
     */
    function cw_portal_login_log_find(mysqli $conn, int $eventId): ?array
    {
        if ($eventId <= 0) {
            return null;
        }
        cw_portal_login_log_ensure_table($conn);

        $stmt = $conn->prepare(
            'SELECT id, event_type, uid, usuario, tipo, portal, ip, geo_label, user_agent, reason,
                    gps_lat, gps_lng, gps_accuracy,
                    last_seen_at, ended_at, revoked_at, revoked_by, revoked_by_name, created_at
             FROM cw_portal_login_events
             WHERE id = ?
             LIMIT 1'
        );
        if (!$stmt) {
            return null;
        }
        $stmt->bind_param('i', $eventId);
        $stmt->execute();
        $row = $stmt->get_result()?->fetch_assoc();
        $stmt->close();

        return is_array($row) ? $row : null;
    }
}

if (!function_exists('cw_portal_login_log_format_time')) {
    /** Hora legible para la tabla de Seguridad (Ciudad de México). */
    function cw_portal_login_log_format_time(?string $dt): string
    {
        if ($dt === null || trim($dt) === '') {
            return '—';
        }
        try {
            $d = new DateTimeImmutable(trim($dt), new DateTimeZone('America/Mexico_City'));

            return $d->format('d/m/Y H:i');
        } catch (Throwable $e) {
            return trim($dt);
        }
    }
}

if (!function_exists('cw_portal_login_log_session_state')) {
    /**
     * Estado legible de una fila de acceso (para la tabla de Seguridad).
     *
     * @return array{
     *   code:string,
     *   label:string,
     *   started:string,
     *   ended:string,
     *   ended_note:string,
     *   last_seen:string,
     *   started_fmt:string,
     *   ended_fmt:string,
     *   last_seen_fmt:string,
     *   is_open:bool,
     *   is_closed:bool,
     *   is_live:bool,
     *   close_by:string,
     *   close_label:string
     * }
     */
    function cw_portal_login_log_session_state(array $row, int $openHours = 24, int $liveMinutes = 5): array
    {
        $type = (string) ($row['event_type'] ?? '');
        $started = trim((string) ($row['created_at'] ?? ''));
        $ended = trim((string) ($row['ended_at'] ?? ''));
        $revoked = trim((string) ($row['revoked_at'] ?? ''));
        $reason = trim((string) ($row['reason'] ?? ''));
        $lastSeen = trim((string) ($row['last_seen_at'] ?? ''));
        if ($lastSeen === '') {
            $lastSeen = $started;
        }

        $out = [
            'code' => 'other',
            'label' => '—',
            'started' => $started !== '' ? $started : '—',
            'ended' => '—',
            'ended_note' => '',
            'last_seen' => $lastSeen !== '' ? $lastSeen : '—',
            'started_fmt' => cw_portal_login_log_format_time($started !== '' ? $started : null),
            'ended_fmt' => '—',
            'last_seen_fmt' => cw_portal_login_log_format_time($lastSeen !== '' ? $lastSeen : null),
            'is_open' => false,
            'is_closed' => false,
            'is_live' => false,
            'close_by' => '',
            'close_label' => '',
        ];

        if ($type === 'fail' || $type === 'captcha_fail') {
            $out['code'] = 'fail';
            $out['label'] = 'Fallido';

            return $out;
        }
        if ($type === 'blocked') {
            $out['code'] = 'blocked';
            $out['label'] = 'Bloqueo';

            return $out;
        }
        if ($type !== 'success') {
            return $out;
        }

        if ($ended !== '' || $revoked !== '') {
            $out['code'] = 'closed';
            $out['label'] = 'Cerrada';
            $out['is_closed'] = true;
            $endedRaw = $revoked !== '' ? $revoked : $ended;
            $out['ended'] = $endedRaw;
            $out['ended_fmt'] = cw_portal_login_log_format_time($endedRaw);

            if ($revoked !== '' || $reason === 'revocada_panel') {
                $by = trim((string) ($row['revoked_by_name'] ?? ''));
                $out['close_by'] = 'admin';
                $out['close_label'] = 'Por administrador';
                $out['ended_note'] = $by !== '' ? 'Cerrada por ' . $by . ' (panel)' : 'Cerrada desde el panel de seguridad';
            } elseif ($reason === 'logout_usuario') {
                $out['close_by'] = 'user';
                $out['close_label'] = 'Por usuario';
                $out['ended_note'] = 'El usuario cerró sesión';
            } else {
                $out['close_by'] = 'user';
                $out['close_label'] = 'Por usuario';
                $out['ended_note'] = $reason !== '' && $reason !== 'ok' ? $reason : 'Sesión finalizada';
            }

            return $out;
        }

        $out['is_open'] = true;
        $openHours = max(1, min(168, $openHours));
        $liveMinutes = max(1, min(60, $liveMinutes));
        $stale = false;
        $isLive = false;
        if ($lastSeen !== '') {
            try {
                $tz = new DateTimeZone('America/Mexico_City');
                $seen = new DateTimeImmutable($lastSeen, $tz);
                $now = new DateTimeImmutable('now', $tz);
                $limit = $now->modify('-' . $openHours . ' hours');
                $liveLimit = $now->modify('-' . $liveMinutes . ' minutes');
                $stale = $seen < $limit;
                $isLive = $seen >= $liveLimit;
            } catch (Throwable $e) {
                $stale = false;
                $isLive = false;
            }
        }

        $out['is_live'] = $isLive;

        if ($stale) {
            $out['code'] = 'idle';
            $out['label'] = 'Abierta · sin actividad';
        } elseif ($isLive) {
            $out['code'] = 'live';
            $out['label'] = 'En vivo';
        } else {
            $out['code'] = 'active';
            $out['label'] = 'Abierta';
        }

        return $out;
    }
}

if (!function_exists('cw_portal_login_log_revoke')) {
    /**
     * Cierra a la fuerza una sesión (panel o portal cliente).
     *
     * No borra el fichero de sesión PHP en disco: solo marca la fila y el
     * guardián de cada portal expulsa al usuario en su siguiente petición.
     */
    function cw_portal_login_log_revoke(mysqli $conn, int $eventId, int $byUid, string $byName): bool
    {
        if ($eventId <= 0) {
            return false;
        }
        try {
            cw_portal_login_log_ensure_table($conn);
            $now = (new DateTimeImmutable('now', new DateTimeZone('America/Mexico_City')))->format('Y-m-d H:i:s');
            $byName = mb_substr(trim($byName), 0, 120);
            $byUid = $byUid > 0 ? $byUid : null;

            $stmt = $conn->prepare(
                "UPDATE cw_portal_login_events
                 SET ended_at = COALESCE(ended_at, ?),
                     revoked_at = ?,
                     revoked_by = ?,
                     revoked_by_name = ?,
                     reason = 'revocada_panel'
                 WHERE id = ?
                   AND event_type = 'success'
                   AND ended_at IS NULL
                   AND revoked_at IS NULL
                 LIMIT 1"
            );
            if (!$stmt) {
                return false;
            }
            $stmt->bind_param('ssisi', $now, $now, $byUid, $byName, $eventId);
            $stmt->execute();
            $ok = $stmt->affected_rows > 0;
            $stmt->close();

            return $ok;
        } catch (Throwable $e) {
            error_log('cw_portal_login_log_revoke: ' . $e->getMessage());

            return false;
        }
    }
}

if (!function_exists('cw_portal_login_log_is_revoked')) {
    /**
     * ¿Le han cerrado la sesión a este dispositivo desde el panel?
     *
     * Se consulta en cada petición, así que va por identificador primario y
     * pide una sola columna.
     */
    function cw_portal_login_log_is_revoked(mysqli $conn, int $eventId): bool
    {
        if ($eventId <= 0) {
            return false;
        }
        try {
            cw_portal_login_log_ensure_table($conn);
            $stmt = $conn->prepare(
                'SELECT revoked_at FROM cw_portal_login_events WHERE id = ? LIMIT 1'
            );
            if (!$stmt) {
                return false;
            }
            $stmt->bind_param('i', $eventId);
            $stmt->execute();
            $row = $stmt->get_result()?->fetch_assoc();
            $stmt->close();

            return !empty($row['revoked_at']);
        } catch (Throwable $e) {
            error_log('cw_portal_login_log_is_revoked: ' . $e->getMessage());

            /* Ante un fallo de base de datos no se expulsa a nadie. */
            return false;
        }
    }
}

if (!function_exists('cw_portal_login_log_portal_for_tipo')) {
    function cw_portal_login_log_portal_for_tipo(?int $tipo): string
    {
        if ($tipo !== null && $tipo >= 1 && $tipo <= 5) {
            return 'adm';
        }

        return 'cliente';
    }
}

if (!function_exists('cw_portal_login_log_geo_cached')) {
    /**
     * @return array{label:string,country:string,region:string,city:string,isp:string}
     */
    function cw_portal_login_log_geo_cached(string $ip, bool $lookup = true): array
    {
        $empty = ['label' => '', 'country' => '', 'region' => '', 'city' => '', 'isp' => ''];
        if ($ip === '' || !filter_var($ip, FILTER_VALIDATE_IP)) {
            return $empty;
        }

        $dir = sys_get_temp_dir() . '/cw_login_geo';
        if (!is_dir($dir)) {
            @mkdir($dir, 0700, true);
        }
        $file = $dir . '/' . hash('sha256', $ip) . '.json';
        if (is_file($file) && (time() - (int) filemtime($file)) < 86400) {
            $cached = json_decode((string) @file_get_contents($file), true);
            if (is_array($cached)) {
                return [
                    'label' => (string) ($cached['label'] ?? ''),
                    'country' => (string) ($cached['country'] ?? ''),
                    'region' => (string) ($cached['region'] ?? ''),
                    'city' => (string) ($cached['city'] ?? ''),
                    'isp' => (string) ($cached['isp'] ?? ''),
                ];
            }
        }

        if (!$lookup) {
            return $empty;
        }

        if (!function_exists('cw_portal_login_alert_geo')) {
            $alert = __DIR__ . '/cw_portal_login_alert.php';
            if (is_file($alert)) {
                require_once $alert;
            }
        }
        if (!function_exists('cw_portal_login_alert_geo')) {
            return $empty;
        }

        $geo = cw_portal_login_alert_geo($ip);
        @file_put_contents($file, json_encode($geo, JSON_UNESCAPED_UNICODE), LOCK_EX);

        return $geo;
    }
}

if (!function_exists('cw_portal_login_log_record')) {
    /**
     * @param array{
     *   event_type:string,
     *   uid?:int|null,
     *   attempted_user?:string,
     *   usuario?:string,
     *   tipo?:int|null,
     *   portal?:string,
     *   ip?:string,
     *   user_agent?:string,
     *   reason?:string,
     *   environment?:string,
     *   session_token?:string|null,
     *   with_geo?:bool
     * } $data
     */
    function cw_portal_login_log_record(mysqli $conn, array $data): int
    {
        try {
            cw_portal_login_log_ensure_table($conn);

            $eventType = strtolower(trim((string) ($data['event_type'] ?? 'fail')));
            $allowed = ['success', 'fail', 'blocked', 'captcha_fail'];
            if (!in_array($eventType, $allowed, true)) {
                $eventType = 'fail';
            }

            $uid = isset($data['uid']) ? (int) $data['uid'] : null;
            if ($uid !== null && $uid <= 0) {
                $uid = null;
            }

            $cwCut = static function ($s, $len) {
                $s = (string) $s;
                if (function_exists('mb_substr')) {
                    return mb_substr($s, 0, $len);
                }
                return substr($s, 0, $len);
            };
            $attempted = $cwCut(trim((string) ($data['attempted_user'] ?? '')), 120);
            $usuario = $cwCut(trim((string) ($data['usuario'] ?? $attempted)), 120);
            $tipo = array_key_exists('tipo', $data) && $data['tipo'] !== null ? (int) $data['tipo'] : null;
            $portal = trim((string) ($data['portal'] ?? ''));
            if ($portal === '') {
                $portal = cw_portal_login_log_portal_for_tipo($tipo);
            }
            $ip = trim((string) ($data['ip'] ?? ''));
            if ($ip === '' && function_exists('cw_portal_client_ip')) {
                $ip = cw_portal_client_ip();
            }
            $ua = mb_substr(trim((string) ($data['user_agent'] ?? ($_SERVER['HTTP_USER_AGENT'] ?? ''))), 0, 500);
            $reason = mb_substr(trim((string) ($data['reason'] ?? '')), 0, 80);
            $env = trim((string) ($data['environment'] ?? ''));
            if ($env === '') {
                $host = strtolower((string) ($_SERVER['HTTP_HOST'] ?? ''));
                $env = preg_match('/(^|\.)conlineweb\.com$/', $host) ? 'producción' : 'local';
            }

            $withGeo = array_key_exists('with_geo', $data) ? (bool) $data['with_geo'] : ($eventType === 'success' || $eventType === 'blocked');
            $geo = cw_portal_login_log_geo_cached($ip, $withGeo);

            $sessionToken = isset($data['session_token']) ? trim((string) $data['session_token']) : null;
            if ($sessionToken === '') {
                $sessionToken = null;
            }
            if ($sessionToken !== null) {
                $sessionToken = substr(hash('sha256', $sessionToken), 0, 64);
            }

            $now = (new DateTimeImmutable('now', new DateTimeZone('America/Mexico_City')))->format('Y-m-d H:i:s');
            $lastSeen = $eventType === 'success' ? $now : null;

            return cw_portal_login_log_record_raw($conn, [
                'event_type' => $eventType,
                'uid' => $uid,
                'attempted_user' => $attempted,
                'usuario' => $usuario,
                'tipo' => $tipo,
                'portal' => $portal,
                'ip' => $ip,
                'geo' => $geo,
                'user_agent' => $ua,
                'reason' => $reason,
                'environment' => $env,
                'session_token' => $sessionToken,
                'last_seen_at' => $lastSeen,
                'created_at' => $now,
            ]);
        } catch (Throwable $e) {
            error_log('cw_portal_login_log_record: ' . $e->getMessage());

            return 0;
        }
    }
}

if (!function_exists('cw_portal_login_log_record_raw')) {
    /** @param array<string,mixed> $row */
    function cw_portal_login_log_record_raw(mysqli $conn, array $row): int
    {
        $esc = static function (?string $v) use ($conn): string {
            return "'" . $conn->real_escape_string((string) ($v ?? '')) . "'";
        };
        $uid = $row['uid'] === null ? 'NULL' : (string) (int) $row['uid'];
        $tipo = $row['tipo'] === null ? 'NULL' : (string) (int) $row['tipo'];
        $session = $row['session_token'] === null ? 'NULL' : $esc((string) $row['session_token']);
        $lastSeen = $row['last_seen_at'] === null ? 'NULL' : $esc((string) $row['last_seen_at']);
        /** @var array{label:string,country:string,region:string,city:string,isp:string} $geo */
        $geo = is_array($row['geo'] ?? null) ? $row['geo'] : ['label' => '', 'country' => '', 'region' => '', 'city' => '', 'isp' => ''];

        $sql = 'INSERT INTO cw_portal_login_events
            (event_type, uid, attempted_user, usuario, tipo, portal, ip, geo_label, geo_country, geo_region, geo_city, isp, user_agent, reason, environment, session_token, last_seen_at, ended_at, created_at)
            VALUES ('
            . $esc((string) $row['event_type']) . ', '
            . $uid . ', '
            . $esc((string) $row['attempted_user']) . ', '
            . $esc((string) $row['usuario']) . ', '
            . $tipo . ', '
            . $esc((string) $row['portal']) . ', '
            . $esc((string) $row['ip']) . ', '
            . $esc((string) $geo['label']) . ', '
            . $esc((string) $geo['country']) . ', '
            . $esc((string) $geo['region']) . ', '
            . $esc((string) $geo['city']) . ', '
            . $esc((string) $geo['isp']) . ', '
            . $esc((string) $row['user_agent']) . ', '
            . $esc((string) $row['reason']) . ', '
            . $esc((string) $row['environment']) . ', '
            . $session . ', '
            . $lastSeen . ', NULL, '
            . $esc((string) $row['created_at']) . ')';

        if (!$conn->query($sql)) {
            error_log('cw_portal_login_log_record_raw: ' . $conn->error);

            return 0;
        }

        return (int) $conn->insert_id;
    }
}

if (!function_exists('cw_portal_login_log_set_gps')) {
    /**
     * Guarda la ubicación que declaró el navegador para un acceso.
     *
     * Va aparte del alta para no reescribir la sentencia de inserción, que
     * comparten todos los portales. Solo el panel exige estas coordenadas.
     */
    function cw_portal_login_log_set_gps(mysqli $conn, int $eventId, float $lat, float $lng, ?int $accuracy = null): bool
    {
        if ($eventId <= 0 || !cw_portal_login_gps_valida($lat, $lng)) {
            return false;
        }
        try {
            cw_portal_login_log_ensure_table($conn);
            $stmt = $conn->prepare(
                'UPDATE cw_portal_login_events
                 SET gps_lat = ?, gps_lng = ?, gps_accuracy = ?
                 WHERE id = ? LIMIT 1'
            );
            if (!$stmt) {
                return false;
            }
            $acc = $accuracy !== null && $accuracy >= 0 ? $accuracy : null;
            $stmt->bind_param('ddii', $lat, $lng, $acc, $eventId);
            $stmt->execute();
            $stmt->close();

            return true;
        } catch (Throwable $e) {
            error_log('cw_portal_login_log_set_gps: ' . $e->getMessage());

            return false;
        }
    }
}

if (!function_exists('cw_portal_login_gps_valida')) {
    /**
     * Coordenadas dentro de rango y no exactamente en el punto cero, que es
     * lo que devuelven algunos clientes cuando no hay señal real.
     */
    function cw_portal_login_gps_valida(float $lat, float $lng): bool
    {
        if ($lat < -90.0 || $lat > 90.0 || $lng < -180.0 || $lng > 180.0) {
            return false;
        }

        return !(abs($lat) < 0.000001 && abs($lng) < 0.000001);
    }
}

if (!function_exists('cw_portal_login_log_fill_geo')) {
    /**
     * Completa la geolocalización de un acceso ya registrado.
     *
     * Existe para poder separar el registro en dos tiempos: la inserción va
     * en caliente (hace falta el identificador antes de escribir la cookie
     * SSO), y la consulta de geolocalización, que sale a Internet y puede
     * tardar, se deja para después de haber respondido al navegador.
     */
    function cw_portal_login_log_fill_geo(mysqli $conn, int $eventId, string $ip): void
    {
        if ($eventId <= 0 || $ip === '') {
            return;
        }
        try {
            $geo = cw_portal_login_log_geo_cached($ip, true);
            if (($geo['label'] ?? '') === '' && ($geo['country'] ?? '') === '') {
                return;
            }
            $stmt = $conn->prepare(
                'UPDATE cw_portal_login_events
                 SET geo_label = ?, geo_country = ?, geo_region = ?, geo_city = ?, isp = ?
                 WHERE id = ? LIMIT 1'
            );
            if (!$stmt) {
                return;
            }
            $label = (string) ($geo['label'] ?? '');
            $country = (string) ($geo['country'] ?? '');
            $region = (string) ($geo['region'] ?? '');
            $city = (string) ($geo['city'] ?? '');
            $isp = (string) ($geo['isp'] ?? '');
            $stmt->bind_param('sssssi', $label, $country, $region, $city, $isp, $eventId);
            $stmt->execute();
            $stmt->close();
        } catch (Throwable $e) {
            error_log('cw_portal_login_log_fill_geo: ' . $e->getMessage());
        }
    }
}

if (!function_exists('cw_portal_login_log_end_event')) {
    function cw_portal_login_log_end_event(mysqli $conn, int $eventId): void
    {
        if ($eventId <= 0) {
            return;
        }
        try {
            cw_portal_login_log_ensure_table($conn);
            $now = (new DateTimeImmutable('now', new DateTimeZone('America/Mexico_City')))->format('Y-m-d H:i:s');
            $stmt = $conn->prepare(
                "UPDATE cw_portal_login_events
                 SET ended_at = COALESCE(ended_at, ?),
                     last_seen_at = ?,
                     reason = CASE
                         WHEN reason = '' OR reason = 'ok' THEN 'logout_usuario'
                         ELSE reason
                     END
                 WHERE id = ?
                   AND event_type = 'success'
                   AND revoked_at IS NULL
                   AND ended_at IS NULL
                 LIMIT 1"
            );
            if (!$stmt) {
                return;
            }
            $stmt->bind_param('ssi', $now, $now, $eventId);
            $stmt->execute();
            $stmt->close();
        } catch (Throwable $e) {
            error_log('cw_portal_login_log_end_event: ' . $e->getMessage());
        }
    }
}

if (!function_exists('cw_portal_login_log_touch')) {
    function cw_portal_login_log_touch(mysqli $conn, int $eventId): void
    {
        if ($eventId <= 0) {
            return;
        }
        try {
            cw_portal_login_log_ensure_table($conn);
            $now = (new DateTimeImmutable('now', new DateTimeZone('America/Mexico_City')))->format('Y-m-d H:i:s');
            $stmt = $conn->prepare(
                'UPDATE cw_portal_login_events
                 SET last_seen_at = ?
                 WHERE id = ? AND event_type = \'success\'
                   AND ended_at IS NULL AND revoked_at IS NULL
                   AND (last_seen_at IS NULL OR last_seen_at < DATE_SUB(?, INTERVAL 3 MINUTE))
                 LIMIT 1'
            );
            if (!$stmt) {
                return;
            }
            $stmt->bind_param('sis', $now, $eventId, $now);
            $stmt->execute();
            $stmt->close();
        } catch (Throwable $e) {
            error_log('cw_portal_login_log_touch: ' . $e->getMessage());
        }
    }
}

if (!function_exists('cw_portal_login_log_stats')) {
    /** @return array{open:int,open_adm:int,open_cliente:int,live:int,live_adm:int,live_cliente:int,ok_24h:int,fail_24h:int,blocked_24h:int} */
    function cw_portal_login_log_stats(mysqli $conn, int $openHours = 24, int $liveMinutes = 5): array
    {
        cw_portal_login_log_ensure_table($conn);
        $openHours = max(1, min(168, $openHours));
        $liveMinutes = max(1, min(60, $liveMinutes));
        $out = [
            'open' => 0,
            'open_adm' => 0,
            'open_cliente' => 0,
            'live' => 0,
            'live_adm' => 0,
            'live_cliente' => 0,
            'ok_24h' => 0,
            'fail_24h' => 0,
            'blocked_24h' => 0,
        ];

        $qOpen = $conn->query(
            "SELECT portal, COUNT(*) AS c
             FROM cw_portal_login_events
             WHERE event_type = 'success'
               AND ended_at IS NULL
               AND revoked_at IS NULL
               AND COALESCE(last_seen_at, created_at) >= DATE_SUB(NOW(), INTERVAL {$openHours} HOUR)
             GROUP BY portal"
        );
        if ($qOpen) {
            while ($row = $qOpen->fetch_assoc()) {
                $c = (int) ($row['c'] ?? 0);
                $out['open'] += $c;
                if (($row['portal'] ?? '') === 'adm') {
                    $out['open_adm'] = $c;
                } else {
                    $out['open_cliente'] = $c;
                }
            }
        }

        $qLive = $conn->query(
            "SELECT portal, COUNT(*) AS c
             FROM cw_portal_login_events
             WHERE event_type = 'success'
               AND ended_at IS NULL
               AND revoked_at IS NULL
               AND COALESCE(last_seen_at, created_at) >= DATE_SUB(NOW(), INTERVAL {$liveMinutes} MINUTE)
             GROUP BY portal"
        );
        if ($qLive) {
            while ($row = $qLive->fetch_assoc()) {
                $c = (int) ($row['c'] ?? 0);
                $out['live'] += $c;
                if (($row['portal'] ?? '') === 'adm') {
                    $out['live_adm'] = $c;
                } else {
                    $out['live_cliente'] = $c;
                }
            }
        }

        $q24 = $conn->query(
            "SELECT event_type, COUNT(*) AS c
             FROM cw_portal_login_events
             WHERE created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
               AND event_type IN ('success','fail','blocked','captcha_fail')
             GROUP BY event_type"
        );
        if ($q24) {
            while ($row = $q24->fetch_assoc()) {
                $t = (string) ($row['event_type'] ?? '');
                $c = (int) ($row['c'] ?? 0);
                if ($t === 'success') {
                    $out['ok_24h'] = $c;
                } elseif ($t === 'fail' || $t === 'captcha_fail') {
                    $out['fail_24h'] += $c;
                } elseif ($t === 'blocked') {
                    $out['blocked_24h'] = $c;
                }
            }
        }

        return $out;
    }
}

if (!function_exists('cw_portal_login_log_list')) {
    /**
     * @return list<array<string,mixed>>
     */
    function cw_portal_login_log_list(
        mysqli $conn,
        string $filter = 'all',
        string $q = '',
        string $portal = '',
        int $limit = 200,
        int $offset = 0
    ): array {
        cw_portal_login_log_ensure_table($conn);
        $limit = max(1, min(500, $limit));
        $offset = max(0, $offset);

        $where = ['1=1'];
        if ($filter === 'open') {
            $where[] = "event_type = 'success' AND ended_at IS NULL AND revoked_at IS NULL AND COALESCE(last_seen_at, created_at) >= DATE_SUB(NOW(), INTERVAL 24 HOUR)";
        } elseif ($filter === 'live') {
            $where[] = "event_type = 'success' AND ended_at IS NULL AND revoked_at IS NULL AND COALESCE(last_seen_at, created_at) >= DATE_SUB(NOW(), INTERVAL 5 MINUTE)";
        } elseif ($filter === 'success') {
            $where[] = "event_type = 'success'";
        } elseif ($filter === 'fail') {
            $where[] = "event_type IN ('fail','captcha_fail')";
        } elseif ($filter === 'blocked') {
            $where[] = "event_type = 'blocked'";
        }

        if ($portal === 'adm' || $portal === 'cliente') {
            $where[] = "portal = '" . $conn->real_escape_string($portal) . "'";
        }

        $q = trim($q);
        if ($q !== '') {
            $like = '%' . $conn->real_escape_string($q) . '%';
            $where[] = "(usuario LIKE '{$like}' OR attempted_user LIKE '{$like}' OR ip LIKE '{$like}' OR geo_label LIKE '{$like}')";
        }

        $sql = 'SELECT id, event_type, uid, attempted_user, usuario, tipo, portal, ip, geo_label, isp, user_agent, reason, environment, last_seen_at, ended_at, revoked_at, revoked_by_name, gps_lat, gps_lng, gps_accuracy, created_at
                FROM cw_portal_login_events
                WHERE ' . implode(' AND ', $where) . '
                ORDER BY created_at DESC, id DESC
                LIMIT ' . $limit . ' OFFSET ' . $offset;

        $rows = [];
        $res = $conn->query($sql);
        if ($res) {
            while ($row = $res->fetch_assoc()) {
                $rows[] = $row;
            }
        }

        return $rows;
    }
}

if (!function_exists('cw_portal_login_log_top_ips')) {
    /** @return list<array{ip:string,c:int,geo_label:string}> */
    function cw_portal_login_log_top_ips(mysqli $conn, int $hours = 24, int $limit = 8): array
    {
        cw_portal_login_log_ensure_table($conn);
        $hours = max(1, min(168, $hours));
        $limit = max(1, min(30, $limit));
        $sql = "SELECT ip, COUNT(*) AS c, MAX(geo_label) AS geo_label
                FROM cw_portal_login_events
                WHERE created_at >= DATE_SUB(NOW(), INTERVAL {$hours} HOUR)
                  AND event_type IN ('fail','blocked','captcha_fail')
                  AND ip <> ''
                GROUP BY ip
                ORDER BY c DESC
                LIMIT {$limit}";
        $out = [];
        $res = $conn->query($sql);
        if ($res) {
            while ($row = $res->fetch_assoc()) {
                $out[] = [
                    'ip' => (string) ($row['ip'] ?? ''),
                    'c' => (int) ($row['c'] ?? 0),
                    'geo_label' => (string) ($row['geo_label'] ?? ''),
                ];
            }
        }

        return $out;
    }
}
