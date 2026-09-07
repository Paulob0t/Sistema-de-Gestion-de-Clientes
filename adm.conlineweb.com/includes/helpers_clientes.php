<?php
/**
 * Clientes activos para selects del admin:
 * login.id_tipo_usuario = 0 y clientes.eliminado = 0
 */

function solicitudes_clientes_activos(mysqli $conexion): array
{
    $rows = [];
    $sql = "SELECT c.id, c.empresa, c.nombre_contacto, c.correo, c.facturacion
            FROM clientes c
            INNER JOIN login l ON l.id = c.id
            WHERE l.id_tipo_usuario = 0
              AND c.eliminado = 0
            ORDER BY c.id ASC";
    $res = $conexion->query($sql);
    if ($res instanceof mysqli_result) {
        while ($row = $res->fetch_assoc()) {
            $rows[] = $row;
        }
        $res->free();
    }

    return $rows;
}

function solicitudes_cliente_es_activo(mysqli $conexion, int $idCliente): bool
{
    if ($idCliente <= 0) {
        return false;
    }

    $id = (int) $idCliente;
    $sql = "SELECT 1
            FROM clientes c
            INNER JOIN login l ON l.id = c.id
            WHERE c.id = {$id}
              AND l.id_tipo_usuario = 0
              AND c.eliminado = 0
            LIMIT 1";
    $res = $conexion->query($sql);
    if ($res instanceof mysqli_result) {
        $ok = $res->num_rows > 0;
        $res->free();

        return $ok;
    }

    return false;
}

function solicitudes_cliente_option_label(array $c, bool $formulario = false): string
{
    $id = (int) ($c['id'] ?? 0);
    $nombre = trim((string) ($c['nombre_contacto'] ?? ''));
    $empresa = trim((string) ($c['empresa'] ?? ''));
    $display = $nombre ?: ($empresa ?: ('#' . $id));

    if ($formulario) {
        $extra = ($empresa && $empresa !== $display) ? " ({$empresa})" : '';

        return "#{$id} - {$display}{$extra}";
    }

    return "#{$id} - {$display}";
}

function adm_proyecto_cliente_option_label(array $c): string
{
    $id = (int) ($c['id'] ?? 0);
    $nombre = trim((string) ($c['nombre_contacto'] ?? ''));
    $empresa = trim((string) ($c['empresa'] ?? ''));

    return $nombre . ' (ID: ' . $id . ' - ' . $empresa . ')';
}

/** Etiqueta para selects de dominio/hosting (incluye correo + data-facturacion). */
function adm_registro_cliente_option_label(array $c): string
{
    $id = (int) ($c['id'] ?? 0);
    $nombre = trim((string) ($c['nombre_contacto'] ?? ''));
    $correo = trim((string) ($c['correo'] ?? ''));
    $display = $nombre !== '' ? $nombre : ('Cliente #' . $id);

    return $display . ' (ID: ' . $id . ' - ' . $correo . ')';
}
