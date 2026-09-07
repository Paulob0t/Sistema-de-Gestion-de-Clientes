<?php

/** Extrae los 10 dígitos locales MX desde formatos pegados (+52, 52, espacios, etc.). */
function cw_web_lead_normalize_mx_phone(?string $raw): string
{
    $digits = preg_replace('/\D+/', '', (string) ($raw ?? '')) ?? '';
    if ($digits === '') {
        return '';
    }
    if (strlen($digits) >= 13 && str_starts_with($digits, '521')) {
        $digits = substr($digits, 3);
    } elseif (strlen($digits) >= 12 && str_starts_with($digits, '52')) {
        $digits = substr($digits, 2);
    } elseif (strlen($digits) === 11 && str_starts_with($digits, '1')) {
        $digits = substr($digits, 1);
    }
    if (strlen($digits) > 10) {
        $digits = substr($digits, -10);
    }

    return $digits;
}

function cw_web_lead_parse_notas(?string $json): array
{
    $notas = json_decode($json ?? '[]', true);
    return is_array($notas) ? $notas : [];
}

/** Resuelve MX / CL / US según payload o URL de origen. */
function cw_web_lead_resolve_web(?string $sitio = '', ?string $paginaOrigen = ''): string
{
    $sitio = strtolower(trim((string) ($sitio ?? '')));
    if (in_array($sitio, ['mx', 'cl', 'us'], true)) {
        return $sitio;
    }

    $url = strtolower((string) ($paginaOrigen ?? ''));
    if ($url !== '') {
        if (str_contains($url, 'conlineweb.cl')) {
            return 'cl';
        }
        if (str_contains($url, '/us/') || str_contains($url, 'conlineweb.com/us') || str_contains($url, '/us?') || str_ends_with($url, '/us')) {
            return 'us';
        }
        if (str_contains($url, 'conlineweb.com')) {
            return 'mx';
        }
    }

    return '';
}

/** @return array{key:string,label:string,class:string} */
function cw_web_lead_web_info(?string $web, ?string $paginaOrigen = ''): array
{
    $key = cw_web_lead_resolve_web($web, $paginaOrigen);
    $map = [
        'mx' => ['label' => 'MX', 'class' => 'lw-web-mx'],
        'cl' => ['label' => 'CL', 'class' => 'lw-web-cl'],
        'us' => ['label' => 'US', 'class' => 'lw-web-us'],
    ];
    if ($key !== '' && isset($map[$key])) {
        return [
            'key' => $key,
            'label' => $map[$key]['label'],
            'class' => $map[$key]['class'],
        ];
    }

    return [
        'key' => '',
        'label' => '—',
        'class' => 'lw-web-unknown',
    ];
}

function cw_web_lead_is_manual_fuente(?string $fuente): bool
{
    $fuente = strtolower(trim((string) ($fuente ?? '')));

    return in_array($fuente, ['registro_manual', 'manual'], true);
}

/** @return array{key:string,label:string,class:string} */
function cw_web_lead_origen_info(?string $fuente, ?string $paginaOrigen = ''): array
{
    if (cw_web_lead_is_manual_fuente($fuente)) {
        return [
            'key' => 'manual',
            'label' => 'Registro manual',
            'class' => 'lw-origen-manual',
        ];
    }

    return [
        'key' => 'website',
        'label' => 'Web site',
        'class' => 'lw-origen-web',
    ];
}

function cw_web_lead_is_system_note(string $text): bool
{
    return str_starts_with($text, 'Lead captado desde')
        || str_starts_with($text, 'Mensaje WhatsApp:')
        || str_starts_with($text, 'Registro manual')
        || str_starts_with($text, 'Correo de confirmación enviado');
}

/** Última nota de gestión (excluye auto-registro del modal). */
function cw_web_lead_last_gestion_note(array $notas): string
{
    for ($i = count($notas) - 1; $i >= 0; $i--) {
        $text = trim((string) ($notas[$i]['nota'] ?? ''));
        if ($text === '' || cw_web_lead_is_system_note($text)) {
            continue;
        }
        return $text;
    }

    return '';
}

function cw_web_lead_note_preview(string $text, int $max = 42): string
{
    $text = trim(preg_replace('/\s+/', ' ', $text));
    if ($text === '') {
        return '';
    }
    if (mb_strlen($text) <= $max) {
        return $text;
    }

    return mb_substr($text, 0, $max - 1) . '…';
}

function cw_web_lead_normalize_estado(?string $estado): string
{
    $estado = trim((string) ($estado ?? 'lead'));
    if ($estado === '' || $estado === 'nuevo') {
        $estado = 'lead';
    }
    $labels = CW_HUB_WEBSITE_PIPELINE;

    return isset($labels[$estado]) ? $estado : 'lead';
}

/** @return array<string,string> */
function cw_web_lead_origen_options(): array
{
    return [
        'website' => 'Web site',
        'manual' => 'Registro manual',
    ];
}

function cw_web_lead_normalize_origen_filter(?string $origen): string
{
    $origen = trim((string) ($origen ?? ''));
    $options = cw_web_lead_origen_options();

    return isset($options[$origen]) ? $origen : '';
}

function cw_web_lead_origen_sql(string $origen): string
{
    if ($origen === 'manual') {
        return " AND LOWER(TRIM(COALESCE(fuente,''))) IN ('registro_manual', 'manual')";
    }
    if ($origen === 'website') {
        return " AND LOWER(TRIM(COALESCE(fuente,''))) NOT IN ('registro_manual', 'manual')";
    }

    return '';
}

/** @return array{period:string,from:?string,to:?string,dateFrom:string,dateTo:string,status:string,origen:string} */
function cw_web_lead_filter_params(): array
{
    $period = (string) ($_GET['period'] ?? '30d');
    $from = isset($_GET['from']) ? trim((string) $_GET['from']) : null;
    $to = isset($_GET['to']) ? trim((string) $_GET['to']) : null;
    [$dateFrom, $dateTo] = cw_hub_period_dates($period, $from, $to);

    $status = trim((string) ($_GET['status'] ?? ''));
    if ($status !== '' && !isset(CW_HUB_WEBSITE_PIPELINE[$status])) {
        $status = '';
    }

    $origen = cw_web_lead_normalize_origen_filter($_GET['origen'] ?? '');

    return [
        'period' => $period,
        'from' => $from,
        'to' => $to,
        'dateFrom' => $dateFrom,
        'dateTo' => $dateTo,
        'status' => $status,
        'origen' => $origen,
    ];
}

function cw_web_lead_status_sql(string $status): string
{
    if ($status === 'lead') {
        return " AND (pipeline_estado IN ('lead','nuevo') OR pipeline_estado = '' OR pipeline_estado IS NULL)";
    }

    return ' AND pipeline_estado = ?';
}

/** @param array<int, mixed> $params */
function cw_web_lead_bind_status(string &$types, array &$params, string $status): void
{
    if ($status === '' || $status === 'lead') {
        return;
    }
    $types .= 's';
    $params[] = $status;
}

/**
 * @return array{total:int,lead:int,calificado:int,cierre:int}
 */
function cw_web_lead_stats(mysqli $conn, string $dateFrom, string $dateTo, string $status = '', string $origen = ''): array
{
    $stats = ['total' => 0, 'lead' => 0, 'calificado' => 0, 'cierre' => 0];
    $types = 'ss';
    $params = [$dateFrom, $dateTo];
    $where = 'eliminado = 0 AND origen_web = 1 AND fecha_registro BETWEEN ? AND ?';

    if ($status !== '') {
        $where .= cw_web_lead_status_sql($status);
        cw_web_lead_bind_status($types, $params, $status);
    }
    $where .= cw_web_lead_origen_sql($origen);

    $sql = "SELECT COALESCE(NULLIF(pipeline_estado,''),'lead') AS pe, COUNT(*) AS c
            FROM leads WHERE {$where} GROUP BY pe";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return $stats;
    }
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $res = $stmt->get_result();

    while ($row = $res->fetch_assoc()) {
        $key = cw_web_lead_normalize_estado($row['pe'] ?? 'lead');
        if (!isset($stats[$key])) {
            $key = 'lead';
        }
        $stats[$key] = (int) $row['c'];
        $stats['total'] += (int) $row['c'];
    }

    return $stats;
}

/**
 * @return array{0:string,1:string,2:array<int,mixed>}
 */
function cw_web_lead_list_where(string $dateFrom, string $dateTo, string $status = '', string $origen = ''): array
{
    $types = 'ss';
    $params = [$dateFrom, $dateTo];
    $where = 'eliminado = 0 AND origen_web = 1 AND fecha_registro BETWEEN ? AND ?';

    if ($status !== '') {
        $where .= cw_web_lead_status_sql($status);
        cw_web_lead_bind_status($types, $params, $status);
    }
    $where .= cw_web_lead_origen_sql($origen);

    return [$where, $types, $params];
}
