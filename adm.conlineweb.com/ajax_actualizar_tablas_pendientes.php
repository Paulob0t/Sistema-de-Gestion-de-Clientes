<?php
// ajax_actualizar_tablas_pendientes.php
include "conn.php";
include "conn_hostingpro.php";
include "plan_helper.php";

$sistema = (isset($_POST['sistema']) && $_POST['sistema'] === 'hostingpro') ? 'hostingpro' : 'conlineweb';
$conn = ($sistema === 'hostingpro') ? $conn_hp : $conn;

header('Content-Type: application/json');

try {
    // Obtener pagos pendientes FILTRANDO POR SISTEMA para evitar mezcla de datos
    $sql = "SELECT 
        p.*,
        c.nombre_contacto AS cliente,
        TRIM(c.correo) AS correo_cliente,
        CASE 
            WHEN p.tipo_servicio = '2' THEN d.url_dominio
            WHEN p.tipo_servicio = '1' THEN h.nom_host
            ELSE NULL
        END AS nombre_servicio,
            h.producto,
            d.fecha_pago AS fecha_pago_dominio_ref,
            h.fecha_pago AS fecha_pago_hosting_ref
    FROM pagos p
    LEFT JOIN clientes c ON p.id_clie = c.id
    LEFT JOIN dominios d ON p.tipo_servicio = '2' AND p.id_servicio = d.id_dominio
    LEFT JOIN hosting h ON p.tipo_servicio = '1' AND p.id_servicio = h.id_orden
    WHERE p.estatus = 0 AND p.Registro = 0 AND (p.sistema = ? OR p.sistema IS NULL OR p.sistema = '')
    ORDER BY p.fecha_pago >= CURDATE() DESC, p.fecha_pago ASC;";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $sistema);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $pagos_pendientes = [];
    if ($result && $result->num_rows > 0) {
        $pagos_pendientes = $result->fetch_all(MYSQLI_ASSOC);
    }
    $stmt->close();

    foreach ($pagos_pendientes as &$pago) {
        $fecha_limite_efectiva = $pago['fecha_limite_pago'] ?? '';

        if (empty($fecha_limite_efectiva) || $fecha_limite_efectiva === '0000-00-00') {
            if ((string)($pago['tipo_servicio'] ?? '') === '2') {
                $fecha_limite_efectiva = $pago['fecha_pago_dominio_ref'] ?? '';
            } elseif ((string)($pago['tipo_servicio'] ?? '') === '1') {
                $fecha_limite_efectiva = $pago['fecha_pago_hosting_ref'] ?? '';
            }
        }

        if (empty($fecha_limite_efectiva) || $fecha_limite_efectiva === '0000-00-00') {
            $fecha_limite_efectiva = '';
        }

        $pago['fecha_limite_efectiva'] = $fecha_limite_efectiva;
    }
    unset($pago);
    
    // Separar pagos pendientes por tipo
    $hosting_pendientes = array_filter($pagos_pendientes, function($row) { 
        return $row['tipo_servicio'] == 1 && (!isset($row['manual']) || $row['manual'] != 1); 
    });
    $dominios_pendientes = array_filter($pagos_pendientes, function($row) { 
        return $row['tipo_servicio'] == 2 && (!isset($row['manual']) || $row['manual'] != 1); 
    });
    $manuales_pendientes = array_filter($pagos_pendientes, function($row) { 
        return isset($row['manual']) && $row['manual'] == 1; 
    });
    
    $hoy = date('Y-m-d');
    $hoy_date = new DateTime($hoy);
    
    // Función para renderizar filas de Hosting
    function renderizarFilasHosting($pagos, $conn) {
        $hoy = date('Y-m-d');
        $hoy_date = new DateTime($hoy);
        $html = '';
        
        foreach ($pagos as $row) {
            $dias_text = '';
            $estatus_vencimiento = '';
            $fecha_limite_raw = $row['fecha_limite_efectiva'] ?? '';
            $fecha_limite = !empty($fecha_limite_raw) && $fecha_limite_raw !== '0000-00-00' ? new DateTime($fecha_limite_raw) : null;
            
            if ($fecha_limite) {
                if ($fecha_limite_raw <= $hoy) {
                    $estatus_vencimiento = 'vencido';
                    $dias_text = '<span class="days-badge critical"><i class="fas fa-exclamation-circle me-1"></i>Alerta de vencimiento</span>';
                } else {
                    $dias = $hoy_date->diff($fecha_limite)->days;
                    if ($dias <= 30) {
                        $estatus_vencimiento = 'proximo';
                        $dias_text = '<span class="days-badge critical"><i class="fas fa-hourglass-half me-1"></i>' . $dias . ' días</span>';
                    } else {
                        $dias_text = '<span class="days-badge normal"><i class="fas fa-calendar-day me-1"></i>' . $dias . ' días</span>';
                    }
                }
            } else {
                $dias_text = '<span class="days-badge normal">Sin fecha</span>';
            }
            $fecha_venc_fmt = '';
            $fecha_plazo_fmt = '';
            $fecha_elim_fmt = '';
            $fecha_plazo_raw = '';
            $fecha_elim_raw = '';
            if (!empty($fecha_limite_raw) && $fecha_limite_raw !== '0000-00-00') {
                $fecha_venc_fmt = date('d/m/Y', strtotime($fecha_limite_raw));
                $fecha_plazo_raw = date('Y-m-d', strtotime($fecha_limite_raw . ' +5 days'));
                $fecha_elim_raw = date('Y-m-d', strtotime($fecha_plazo_raw . ' +1 day'));
                $fecha_plazo_fmt = date('d/m/Y', strtotime($fecha_plazo_raw));
                $fecha_elim_fmt = date('d/m/Y', strtotime($fecha_elim_raw));
            }
            
            $html .= '<tr data-estado-vencimiento="' . $estatus_vencimiento . '" data-forma-pago="' . $row['forma_pago'] . '" data-monto="' . htmlspecialchars((string) $row['monto'], ENT_QUOTES, 'UTF-8') . '" data-currency="' . htmlspecialchars((string) ($row['currency'] ?? 'MXN'), ENT_QUOTES, 'UTF-8') . '">';
            $html .= '<td><span class="badge-id">#' . $row["id"] . '</span></td>';
            $html .= '<td><div class="fw-semibold">' . htmlspecialchars($row["cliente"] ?? '') . '</div><small class="text-secondary-custom">ID: ' . $row["id_clie"] . '</small></td>';
            $html .= '<td><div class="fw-semibold">' . htmlspecialchars($row["nombre_servicio"] ?? $row["producto"] ?? 'N/A') . '</div><small class="text-secondary-custom">Servicio ID: ' . $row["id_servicio"] . '</small></td>';
            $html .= '<td>' . obtenerNombrePlan($conn, $row['producto']) . '</td>';
            $html .= '<td><div class="status-indicator">';
            if ($fecha_limite_raw !== '' && $fecha_limite_raw <= $hoy) {
                $html .= '<span class="status-dot vencido"></span>';
            } elseif ($fecha_limite) {
                $html .= '<span class="status-dot warning"></span>';
            } else {
                $html .= '<span class="status-dot"></span>';
            }
            $html .= $fecha_venc_fmt !== '' ? $fecha_venc_fmt : '-';
            $html .= '</div>' . $dias_text . '</td>';
            $html .= '<td>';
            if ($fecha_plazo_fmt !== '') {
                $html .= '<div class="status-indicator">';
                $html .= ($fecha_plazo_raw <= $hoy) ? '<span class="status-dot vencido"></span>' : '<span class="status-dot warning"></span>';
                $html .= htmlspecialchars($fecha_plazo_fmt);
                $html .= '</div>';
                if ($fecha_plazo_raw <= $hoy) {
                    $html .= '<span class="days-badge critical"><i class="fas fa-hourglass-end me-1"></i>Alerta de plazo</span>';
                } elseif ($estatus_vencimiento === 'vencido') {
                    $html .= '<span class="days-badge critical"><i class="fas fa-hourglass-half me-1"></i>Plazo de 5 días</span>';
                }
            } else {
                $html .= '-';
            }
            $html .= '</td>';
            $html .= '<td>';
            if ($fecha_elim_fmt !== '') {
                $html .= '<div class="status-indicator">';
                $html .= ($fecha_elim_raw <= $hoy) ? '<span class="status-dot vencido"></span>' : '<span class="status-dot warning"></span>';
                $html .= htmlspecialchars($fecha_elim_fmt);
                $html .= '</div>';
                if ($fecha_elim_raw <= $hoy) {
                    $html .= '<span class="days-badge critical"><i class="fas fa-trash-alt me-1"></i>Alerta de eliminación</span>';
                }
            } else {
                $html .= '-';
            }
            $html .= '</td>';
            $html .= '<td class="fw-semibold">$' . number_format($row["monto"], 2) . '</td>';
            $html .= '<td class="fw-medium">' . $row["currency"] . '</td>';
            $html .= '<td class="concepto-cell" title="' . htmlspecialchars($row["concepto"] ?? '') . '">' . htmlspecialchars($row["concepto"] ?? '') . '</td>';
            $html .= '<td><div class="btn-group">';
            $html .= '<button class="btn-modern btn-success-modern aprobar-btn" data-id="' . $row['id'] . '" data-servicio="' . $row['id_servicio'] . '" data-tipo="' . $row['tipo_servicio'] . '"><i class="fas fa-check-circle"></i>Aprobar</button>';
            $html .= '<button class="btn-modern btn-info-modern reenviar-btn" data-id="' . $row['id'] . '" data-tipo="' . $row['tipo_servicio'] . '" data-correo="' . htmlspecialchars($row['correo_cliente'] ?? '') . '" data-manual="' . (isset($row['manual']) ? $row['manual'] : 0) . '"><i class="fas fa-paper-plane"></i>Reenviar</button>';
            $html .= '<button class="btn-modern btn-warning-modern alerta-hosting-btn" data-alerta="vencimiento" data-id="' . $row['id'] . '" data-tipo="' . $row['tipo_servicio'] . '" data-correo="' . htmlspecialchars($row['correo_cliente'] ?? '') . '" title="Enviar correo de alerta de vencimiento"><i class="fas fa-calendar-times"></i>Alerta vencimiento</button>';
            $html .= '<button class="btn-modern btn-warning-modern alerta-hosting-btn" data-alerta="plazo" data-id="' . $row['id'] . '" data-tipo="' . $row['tipo_servicio'] . '" data-correo="' . htmlspecialchars($row['correo_cliente'] ?? '') . '" title="Enviar correo de alerta de plazo"><i class="fas fa-hourglass-end"></i>Alerta plazo</button>';
            $html .= '<button class="btn-modern btn-danger-modern alerta-hosting-btn" data-alerta="eliminacion" data-id="' . $row['id'] . '" data-tipo="' . $row['tipo_servicio'] . '" data-correo="' . htmlspecialchars($row['correo_cliente'] ?? '') . '" title="Enviar correo de alerta de eliminación"><i class="fas fa-trash-alt"></i>Alerta eliminación</button>';
            $html .= '<button class="btn-modern btn-danger-modern eliminar-btn" data-id="' . $row['id'] . '"><i class="fas fa-trash"></i>Eliminar</button>';
            $html .= '</div></td>';
            $html .= '</tr>';
        }
        
        return $html;
    }
    
    // Función para renderizar filas de Dominios
    function renderizarFilasDominios($pagos) {
        $hoy = date('Y-m-d');
        $hoy_date = new DateTime($hoy);
        $html = '';
        
        foreach ($pagos as $row) {
            $dias_text = '';
            $estatus_vencimiento = '';
            $fecha_limite_raw = $row['fecha_limite_efectiva'] ?? '';
            $fecha_limite = !empty($fecha_limite_raw) && $fecha_limite_raw !== '0000-00-00' ? new DateTime($fecha_limite_raw) : null;
            
            if ($fecha_limite) {
                if ($fecha_limite < $hoy_date) {
                    $estatus_vencimiento = 'vencido';
                    $dias_text = '<span class="days-badge critical"><i class="fas fa-exclamation-circle me-1"></i>Vencido</span>';
                } else {
                    $dias = $hoy_date->diff($fecha_limite)->days;
                    if ($dias <= 30) {
                        $estatus_vencimiento = 'proximo';
                        $dias_text = '<span class="days-badge critical"><i class="fas fa-hourglass-half me-1"></i>' . $dias . ' días</span>';
                    } else {
                        $dias_text = '<span class="days-badge normal"><i class="fas fa-calendar-day me-1"></i>' . $dias . ' días</span>';
                    }
                }
            } else {
                $dias_text = '<span class="days-badge normal">Sin fecha</span>';
            }
            
            $html .= '<tr data-estado-vencimiento="' . $estatus_vencimiento . '" data-forma-pago="' . $row['forma_pago'] . '" data-monto="' . htmlspecialchars((string) $row['monto'], ENT_QUOTES, 'UTF-8') . '" data-currency="' . htmlspecialchars((string) ($row['currency'] ?? 'MXN'), ENT_QUOTES, 'UTF-8') . '">';
            $html .= '<td><span class="badge-id">#' . $row["id"] . '</span></td>';
            $html .= '<td><div class="fw-semibold">' . htmlspecialchars($row["cliente"] ?? '') . '</div><small class="text-secondary-custom">ID: ' . $row["id_clie"] . '</small></td>';
            $html .= '<td><div class="fw-semibold">' . htmlspecialchars($row["nombre_servicio"] ?? '') . '</div><small class="text-secondary-custom">Servicio ID: ' . $row["id_servicio"] . '</small></td>';
            $html .= '<td><div class="status-indicator">';
            if ($fecha_limite && $fecha_limite < $hoy_date) {
                $html .= '<span class="status-dot vencido"></span>';
            } elseif ($fecha_limite) {
                $html .= '<span class="status-dot warning"></span>';
            } else {
                $html .= '<span class="status-dot"></span>';
            }
            $html .= !empty($fecha_limite_raw) ? date("d/m/Y", strtotime($fecha_limite_raw)) : "-";
            $html .= '</div>' . $dias_text . '</td>';
            $html .= '<td class="fw-semibold">$' . number_format($row["monto"], 2) . '</td>';
            $html .= '<td class="fw-medium">' . $row["currency"] . '</td>';
            $html .= '<td class="concepto-cell" title="' . htmlspecialchars($row["concepto"] ?? '') . '">' . htmlspecialchars($row["concepto"] ?? '') . '</td>';
            $html .= '<td><div class="btn-group">';
            $html .= '<button class="btn-modern btn-success-modern aprobar-btn" data-id="' . $row['id'] . '" data-servicio="' . $row['id_servicio'] . '" data-tipo="' . $row['tipo_servicio'] . '"><i class="fas fa-check-circle"></i>Aprobar</button>';
            $html .= '<button class="btn-modern btn-info-modern reenviar-btn" data-id="' . $row['id'] . '" data-tipo="' . $row['tipo_servicio'] . '" data-correo="' . htmlspecialchars($row['correo_cliente'] ?? '') . '" data-manual="' . (isset($row['manual']) ? $row['manual'] : 0) . '"><i class="fas fa-paper-plane"></i>Reenviar</button>';
            if ($estatus_vencimiento === 'vencido') {
                $html .= '<button class="btn-modern btn-warning-modern aviso-vencido-btn" data-id="' . $row['id'] . '" data-tipo="' . $row['tipo_servicio'] . '" data-correo="' . htmlspecialchars($row['correo_cliente'] ?? '') . '"><i class="fas fa-exclamation-triangle"></i>Aviso vencido</button>';
            }
            $html .= '<button class="btn-modern btn-danger-modern eliminar-btn" data-id="' . $row['id'] . '"><i class="fas fa-trash"></i>Eliminar</button>';
            $html .= '</div></td>';
            $html .= '</tr>';
        }
        
        return $html;
    }
    
    // Función para renderizar filas de Manuales
    function renderizarFilasManuales($pagos) {
        $hoy = date('Y-m-d');
        $hoy_date = new DateTime($hoy);
        $html = '';
        
        foreach ($pagos as $row) {
            $dias_text = '';
            $estatus_vencimiento = '';
            $fecha_limite_raw = $row['fecha_limite_efectiva'] ?? '';
            $fecha_limite = !empty($fecha_limite_raw) && $fecha_limite_raw !== '0000-00-00' ? new DateTime($fecha_limite_raw) : null;
            
            if ($fecha_limite) {
                if ($fecha_limite < $hoy_date) {
                    $estatus_vencimiento = 'vencido';
                    $dias_text = '<span class="days-badge critical"><i class="fas fa-exclamation-circle me-1"></i>Vencido</span>';
                } else {
                    $dias = $hoy_date->diff($fecha_limite)->days;
                    if ($dias <= 30) {
                        $estatus_vencimiento = 'proximo';
                        $dias_text = '<span class="days-badge critical"><i class="fas fa-hourglass-half me-1"></i>' . $dias . ' días</span>';
                    } else {
                        $dias_text = '<span class="days-badge normal"><i class="fas fa-calendar-day me-1"></i>' . $dias . ' días</span>';
                    }
                }
            } else {
                $dias_text = '<span class="days-badge normal">Sin fecha</span>';
            }
            
            $html .= '<tr data-estado-vencimiento="' . $estatus_vencimiento . '" data-forma-pago="' . $row['forma_pago'] . '" data-monto="' . htmlspecialchars((string) $row['monto'], ENT_QUOTES, 'UTF-8') . '" data-currency="' . htmlspecialchars((string) ($row['currency'] ?? 'MXN'), ENT_QUOTES, 'UTF-8') . '">';
            $html .= '<td><span class="badge-id">#' . $row["id"] . '</span></td>';
            $html .= '<td><div class="fw-semibold">' . htmlspecialchars($row["cliente"] ?? '') . '</div><small class="text-secondary-custom">ID: ' . $row["id_clie"] . '</small></td>';
            $html .= '<td class="concepto-cell" title="' . htmlspecialchars($row["concepto"] ?? '') . '">' . htmlspecialchars($row["concepto"] ?? '') . '</td>';
            $html .= '<td><div class="status-indicator">';
            if ($fecha_limite && $fecha_limite < $hoy_date) {
                $html .= '<span class="status-dot vencido"></span>';
            } elseif ($fecha_limite) {
                $html .= '<span class="status-dot warning"></span>';
            } else {
                $html .= '<span class="status-dot"></span>';
            }
            $html .= !empty($fecha_limite_raw) ? date("d/m/Y", strtotime($fecha_limite_raw)) : "-";
            $html .= '</div>' . $dias_text . '</td>';
            $html .= '<td><span class="badge-status ' . ($row['forma_pago'] == 1 ? 'badge-aprobado' : ($row['forma_pago'] == 2 ? 'badge-info' : ($row['forma_pago'] == 3 ? 'badge-warning' : 'badge-pendiente'))) . '">';
            $html .= $row["forma_pago"] == 1 ? "Tarjeta" : ($row["forma_pago"] == 2 ? "Transferencia" : ($row["forma_pago"] == 3 ? "Efectivo" : "Pendiente"));
            $html .= '</span></td>';
            $html .= '<td class="fw-semibold">$' . number_format($row["monto"], 2) . '</td>';
            $html .= '<td class="fw-medium">' . $row["currency"] . '</td>';
            $html .= '<td><div class="btn-group">';
            $html .= '<button class="btn-modern btn-success-modern aprobar-btn" data-id="' . $row['id'] . '" data-servicio="' . $row['id_servicio'] . '" data-tipo="' . $row['tipo_servicio'] . '"><i class="fas fa-check-circle"></i>Aprobar</button>';
            $html .= '<button class="btn-modern btn-info-modern reenviar-btn" data-id="' . $row['id'] . '" data-tipo="' . $row['tipo_servicio'] . '" data-correo="' . htmlspecialchars($row['correo_cliente'] ?? '') . '" data-manual="' . (isset($row['manual']) ? $row['manual'] : 1) . '"><i class="fas fa-paper-plane"></i>Reenviar</button>';
            if ($estatus_vencimiento === 'vencido') {
                $html .= '<button class="btn-modern btn-warning-modern aviso-vencido-btn" data-id="' . $row['id'] . '" data-tipo="' . $row['tipo_servicio'] . '" data-correo="' . htmlspecialchars($row['correo_cliente'] ?? '') . '"><i class="fas fa-exclamation-triangle"></i>Aviso vencido</button>';
            }
            $html .= '<button class="btn-modern btn-danger-modern eliminar-btn" data-id="' . $row['id'] . '"><i class="fas fa-trash"></i>Eliminar</button>';
            $html .= '</div></td>';
            $html .= '</tr>';
        }
        
        return $html;
    }
    
    $sumarMontos = static function (array $pagos): array {
        $out = [];
        foreach ($pagos as $pago) {
            $moneda = trim((string) ($pago['currency'] ?? 'MXN'));
            if ($moneda === '') {
                $moneda = 'MXN';
            }
            if (!isset($out[$moneda])) {
                $out[$moneda] = 0.0;
            }
            $out[$moneda] += (float) ($pago['monto'] ?? 0);
        }
        ksort($out);
        return $out;
    };

    $response = [
        'success' => true,
        'hosting' => [
            'html' => !empty($hosting_pendientes) ? renderizarFilasHosting($hosting_pendientes, $conn) : '',
            'empty' => empty($hosting_pendientes),
            'count' => count($hosting_pendientes),
            'totales' => $sumarMontos($hosting_pendientes)
        ],
        'dominios' => [
            'html' => !empty($dominios_pendientes) ? renderizarFilasDominios($dominios_pendientes) : '',
            'empty' => empty($dominios_pendientes),
            'count' => count($dominios_pendientes),
            'totales' => $sumarMontos($dominios_pendientes)
        ],
        'manuales' => [
            'html' => !empty($manuales_pendientes) ? renderizarFilasManuales($manuales_pendientes) : '',
            'empty' => empty($manuales_pendientes),
            'count' => count($manuales_pendientes),
            'totales' => $sumarMontos($manuales_pendientes)
        ]
    ];
    
    echo json_encode($response);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>