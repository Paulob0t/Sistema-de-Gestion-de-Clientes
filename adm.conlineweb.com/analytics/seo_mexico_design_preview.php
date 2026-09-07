<?php
/**
 * Vista previa HTML de propuesta de diseño (fuera de producción).
 * Uso: seo_mexico_design_preview.php?id=123
 */
declare(strict_types=1);

require_once dirname(__DIR__) . '/auth_middleware.php';
require_once dirname(__DIR__) . '/conn.php';
require_once dirname(__DIR__) . '/includes/cw_seo_mexico_ai.php';
require_once dirname(__DIR__) . '/includes/cw_site_ai_design.php';

$id = (int) ($_GET['id'] ?? $_GET['proposal_id'] ?? 0);
if ($id < 1) {
    http_response_code(400);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Falta id de propuesta';
    exit;
}

$row = cw_seo_mexico_ai_get_proposal($conn, $id);
if ($row === null) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Propuesta no encontrada';
    exit;
}

$after = is_array($row['after'] ?? null) ? $row['after'] : [];
$doc = trim((string) ($after['preview_document'] ?? ''));
if ($doc === '') {
    $frag = trim((string) ($after['preview_html'] ?? ''));
    $css = trim((string) ($after['preview_css'] ?? ''));
    if ($frag === '') {
        http_response_code(404);
        header('Content-Type: text/plain; charset=utf-8');
        echo 'Esta propuesta no tiene preview de diseño';
        exit;
    }
    $doc = cw_site_ai_design_wrap_preview_document(
        $frag,
        $css,
        (string) ($row['title'] ?? 'Preview diseño')
    );
}

header('Content-Type: text/html; charset=utf-8');
header('X-Robots-Tag: noindex, nofollow');
echo $doc;
exit;
