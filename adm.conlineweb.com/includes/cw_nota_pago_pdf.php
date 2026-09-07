<?php
/**
 * Generador único de Comprobante de Pago (PDF).
 * Usado por adm.conlineweb.com y cliente.conlineweb.com — mismo diseño y textos.
 * Requiere TCPDF en adm.conlineweb.com/tcpdf/
 */
declare(strict_types=1);

if (!function_exists('cw_nota_pago_logo_path')) {
    function cw_nota_pago_logo_path(): string
    {
        $root = dirname(__DIR__, 2);
        $candidates = [
            dirname(__DIR__) . '/images/logo-conline.png',
            $root . '/cliente.conlineweb.com/images/logo-conline.png',
            dirname(__DIR__) . '/images/c-online_completo.png',
            dirname(__DIR__) . '/images/logo.png',
            dirname(__DIR__) . '/images/cropped-c-online_isotipo.png',
        ];
        foreach ($candidates as $p) {
            if (is_file($p)) {
                return $p;
            }
        }
        return '';
    }
}

if (!function_exists('cw_nota_pago_format_money_row')) {
    function cw_nota_pago_format_money_row(float $amount, string $currency = 'MXN'): string
    {
        $currency = strtoupper(trim($currency !== '' ? $currency : 'MXN'));
        return number_format($amount, 2, '.', '') . ' ' . $currency;
    }
}

if (!function_exists('cw_nota_pago_format_money_total')) {
    function cw_nota_pago_format_money_total(float $amount): string
    {
        return '$' . number_format($amount, 2, '.', ',');
    }
}

if (!function_exists('cw_nota_pago_fecha_mx')) {
    function cw_nota_pago_fecha_mx(?string $raw = null): string
    {
        $meses = [
            1 => 'enero', 2 => 'febrero', 3 => 'marzo', 4 => 'abril',
            5 => 'mayo', 6 => 'junio', 7 => 'julio', 8 => 'agosto',
            9 => 'septiembre', 10 => 'octubre', 11 => 'noviembre', 12 => 'diciembre',
        ];
        if ($raw === null || $raw === '' || $raw === '0000-00-00') {
            $ts = time();
        } else {
            $ts = strtotime(substr($raw, 0, 10));
            if ($ts === false) {
                $ts = time();
            }
        }
        $d = (int) date('j', $ts);
        $m = (int) date('n', $ts);
        $y = (int) date('Y', $ts);
        return $d . ' de ' . ($meses[$m] ?? date('m', $ts)) . ' de ' . $y;
    }
}

if (!function_exists('cw_nota_pago_forma_texto')) {
    function cw_nota_pago_forma_texto(int $forma): string
    {
        switch ($forma) {
            case 1: return 'Tarjeta de crédito/débito';
            case 2: return 'Transferencia bancaria';
            case 3: return 'Efectivo';
            default: return 'Pendiente de pago';
        }
    }
}

if (!function_exists('cw_nota_pago_calcular_renovacion')) {
    function cw_nota_pago_calcular_renovacion(string $fechaBase): string
    {
        $fechaBase = trim($fechaBase);
        if ($fechaBase === '' || $fechaBase === '0000-00-00') {
            $fechaBase = date('Y-m-d');
        }
        $ts = strtotime('+1 year -1 day', strtotime($fechaBase));
        return $ts ? date('Y-m-d', $ts) : date('Y-m-d', strtotime('+1 year -1 day'));
    }
}

if (!function_exists('cw_nota_pago_fecha_vigencia')) {
    function cw_nota_pago_fecha_vigencia(mysqli $conn, int $tipoServicio, int $idServicio, array $pago = []): string
    {
        $estatus = (int) ($pago['estatus'] ?? 1);
        $fechaServicio = '';

        if ($tipoServicio === 1 && $idServicio > 0) {
            $q = $conn->prepare('SELECT fecha_pago FROM hosting WHERE id_orden = ? LIMIT 1');
            if ($q) {
                $q->bind_param('i', $idServicio);
                $q->execute();
                $row = $q->get_result()->fetch_assoc();
                $q->close();
                $fechaServicio = trim((string) ($row['fecha_pago'] ?? ''));
            }
        } elseif ($tipoServicio === 2 && $idServicio > 0) {
            $q = $conn->prepare('SELECT fecha_pago FROM dominios WHERE id_dominio = ? LIMIT 1');
            if ($q) {
                $q->bind_param('i', $idServicio);
                $q->execute();
                $row = $q->get_result()->fetch_assoc();
                $q->close();
                $fechaServicio = trim((string) ($row['fecha_pago'] ?? ''));
            }
        } else {
            $limite = trim((string) ($pago['fecha_limite_pago'] ?? ''));
            if ($limite !== '' && $limite !== '0000-00-00') {
                return $limite;
            }
            return cw_nota_pago_calcular_renovacion((string) ($pago['fecha_pago'] ?? ''));
        }

        if ($fechaServicio === '' || $fechaServicio === '0000-00-00') {
            return cw_nota_pago_calcular_renovacion((string) ($pago['fecha_pago'] ?? date('Y-m-d')));
        }

        if ($estatus === 1) {
            return $fechaServicio;
        }

        return cw_nota_pago_calcular_renovacion($fechaServicio);
    }
}

/**
 * @return array{tipo:string,nombre:string,plan:string}
 */
if (!function_exists('cw_nota_pago_servicio_info')) {
    function cw_nota_pago_servicio_info(mysqli $conn, int $tipoServicio, int $idServicio, string $conceptoFallback = ''): array
    {
        $out = [
            'tipo' => 'Servicio',
            'nombre' => $conceptoFallback !== '' ? $conceptoFallback : 'N/A',
            'plan' => '',
        ];

        if ($tipoServicio === 1 && $idServicio > 0) {
            $out['tipo'] = 'Hosting';
            $q = $conn->prepare('SELECT h.tipo_producto, pl.nombre AS nombre_plan FROM hosting h LEFT JOIN planes pl ON h.producto = pl.id WHERE h.id_orden = ? LIMIT 1');
            if ($q) {
                $q->bind_param('i', $idServicio);
                $q->execute();
                $row = $q->get_result()->fetch_assoc();
                $q->close();
                if ($row) {
                    $tp = trim((string) ($row['tipo_producto'] ?? ''));
                    $out['nombre'] = $tp !== '' ? ('Producto ' . $tp) : 'N/A';
                    $out['plan'] = trim((string) ($row['nombre_plan'] ?? ''));
                }
            }
        } elseif ($tipoServicio === 2 && $idServicio > 0) {
            $out['tipo'] = 'Dominio';
            $q = $conn->prepare('SELECT url_dominio FROM dominios WHERE id_dominio = ? LIMIT 1');
            if ($q) {
                $q->bind_param('i', $idServicio);
                $q->execute();
                $row = $q->get_result()->fetch_assoc();
                $q->close();
                if ($row) {
                    $out['nombre'] = trim((string) ($row['url_dominio'] ?: 'N/A'));
                }
            }
        } elseif ($tipoServicio !== 0) {
            $out['tipo'] = 'Otro';
        }

        return $out;
    }
}

/**
 * @param array $pago
 * @param array $cliente
 * @param list<array>|null $items
 * @return string|false
 */
if (!function_exists('cw_generar_nota_pago_pdf')) {
    function cw_generar_nota_pago_pdf(array $pago, array $cliente, mysqli $conn, ?string $outputPath = null, ?array $items = null)
    {
        $tcpdfPath = dirname(__DIR__) . '/tcpdf/tcpdf.php';
        if (!is_file($tcpdfPath)) {
            error_log('cw_generar_nota_pago_pdf: TCPDF no encontrado');
            return false;
        }
        require_once $tcpdfPath;

        $h = static fn($s) => htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');

        $filas = ($items !== null && count($items) > 0) ? $items : [$pago];
        $esGrupal = count($filas) > 1;
        $pagoId = (int) ($pago['id'] ?? 0);
        $referencia = $esGrupal
            ? ((string) ($pago['pago_grupal_id'] ?? $pagoId) . ' (Múltiple)')
            : (string) $pagoId;

        $forma = (int) ($pago['forma_pago'] ?? 0);
        $estatus = (int) ($pago['estatus'] ?? 1);
        $formaTxt = cw_nota_pago_forma_texto($forma);
        $estatusTxt = $estatus === 1 ? 'Pagado' : 'Pendiente';
        $estatusColor = $estatus === 1 ? '#047857' : '#b45309';

        $fechaPagoRaw = (string) ($pago['fecha_pago'] ?? '');
        $tieneFechaPago = ($fechaPagoRaw !== '' && $fechaPagoRaw !== '0000-00-00');
        $fechaPago = $tieneFechaPago ? cw_nota_pago_fecha_mx($fechaPagoRaw) : 'No especificada';
        $fechaEmision = cw_nota_pago_fecha_mx(date('Y-m-d'));

        $fechasVig = [];
        foreach ($filas as $item) {
            $vig = cw_nota_pago_fecha_vigencia(
                $conn,
                (int) ($item['tipo_servicio'] ?? 0),
                (int) ($item['id_servicio'] ?? 0),
                $item
            );
            if ($vig !== '' && $vig !== '0000-00-00') {
                $fechasVig[] = $vig;
            }
        }
        $fechasVig = array_values(array_unique($fechasVig));
        sort($fechasVig);
        if (count($fechasVig) === 0) {
            $fechaVigencia = 'No especificada';
        } elseif (count($fechasVig) === 1) {
            $fechaVigencia = cw_nota_pago_fecha_mx($fechasVig[0]);
        } else {
            $fechaVigencia = cw_nota_pago_fecha_mx($fechasVig[0]) . ' – ' . cw_nota_pago_fecha_mx($fechasVig[count($fechasVig) - 1]);
        }

        $nombreCliente = trim((string) ($cliente['nombre_contacto'] ?? $cliente['nombre'] ?? ''));
        if ($nombreCliente === '') {
            $nombreCliente = 'N/A';
        }
        $correoCliente = trim((string) ($cliente['correo'] ?? ''));
        if ($correoCliente === '') {
            $correoCliente = 'N/A';
        }

        $subtotal = 0.0;
        $currency = 'MXN';
        $filasHtml = '';
        foreach ($filas as $item) {
            $monto = (float) ($item['monto'] ?? 0);
            $subtotal += $monto;
            $currency = (string) ($item['currency'] ?? $currency);
            $concepto = trim((string) ($item['concepto'] ?? ''));
            if ($concepto === '') {
                $concepto = 'N/A';
            }
            $tipo = (int) ($item['tipo_servicio'] ?? 0);
            $idServ = (int) ($item['id_servicio'] ?? 0);
            $svc = cw_nota_pago_servicio_info($conn, $tipo, $idServ, $concepto);
            $nombreServicio = $svc['nombre'];
            if ($svc['plan'] !== '') {
                $nombreServicio .= ' - ' . $svc['plan'];
            }
            $moneyRow = $h(cw_nota_pago_format_money_row($monto, $currency));
            $filasHtml .= '
  <tr>
    <td style="width:54%; border-bottom:1px solid #e8eef5; vertical-align:top; line-height:1.45;">
      <span style="color:#000147; font-size:7px; font-weight:bold; letter-spacing:0.5px;">' . $h(strtoupper($svc['tipo'])) . '</span><br>
      <span style="font-size:9.5px; font-weight:bold; color:#0f172a;">' . $h($nombreServicio) . '</span><br>
      <span style="font-size:8px; color:#64748b;">' . $h($concepto) . '</span>
    </td>
    <td style="width:10%; border-bottom:1px solid #e8eef5; text-align:center; font-size:9px; color:#334155; vertical-align:middle;">1</td>
    <td style="width:18%; border-bottom:1px solid #e8eef5; text-align:right; font-size:9px; color:#334155; vertical-align:middle;">' . $moneyRow . '</td>
    <td style="width:18%; border-bottom:1px solid #e8eef5; text-align:right; font-size:9px; font-weight:bold; color:#0f172a; vertical-align:middle;">' . $moneyRow . '</td>
  </tr>';
        }

        $moneyTotal = $h(cw_nota_pago_format_money_total($subtotal));
        $logoPath = cw_nota_pago_logo_path();
        $logoHtml = $logoPath !== ''
            ? '<img src="' . $h($logoPath) . '" style="width:132px; height:auto;">'
            : '<span style="font-size:18px;font-weight:bold;color:#000147;">ConlineWeb</span>';

        if (!class_exists('CwNotaPagoPdfDoc', false)) {
            class CwNotaPagoPdfDoc extends TCPDF
            {
                /** @var string */
                public $cwFooterVigencia = '';

                public function Footer()
                {
                    $this->SetY(-42);
                    $this->SetDrawColor(226, 232, 240);
                    $this->SetLineWidth(0.3);
                    $this->Line(18, $this->GetY(), 192, $this->GetY());
                    $this->Ln(3);

                    $this->SetFont('dejavusans', 'B', 8);
                    $this->SetTextColor(0, 1, 71);
                    $this->Cell(0, 4, 'Confirmación', 0, 1, 'C');

                    $this->SetFont('dejavusans', '', 7.5);
                    $this->SetTextColor(51, 65, 85);
                    $this->MultiCell(0, 3.6, 'Este comprobante acredita el pago del servicio indicado.', 0, 'C', false, 1);

                    $this->SetFont('dejavusans', '', 7.5);
                    $this->SetTextColor(100, 116, 139);
                    $vigenciaLine = 'Vigencia del servicio hasta: ' . (string) $this->cwFooterVigencia;
                    $this->SetFont('dejavusans', 'B', 7.5);
                    $this->SetTextColor(0, 1, 71);
                    $this->MultiCell(0, 3.6, $vigenciaLine, 0, 'C', false, 1);
                    $this->Ln(2);

                    $this->SetFont('dejavusans', '', 7);
                    $this->SetTextColor(148, 163, 184);
                    $this->MultiCell(
                        0,
                        3.4,
                        "Documento informativo. No constituye comprobante fiscal (CFDI).\nConlineWeb · León, Guanajuato · Soporte WhatsApp 477 118 1285",
                        0,
                        'C',
                        false,
                        1
                    );
                }
            }
        }

        $pdf = new CwNotaPagoPdfDoc('P', 'mm', 'A4', true, 'UTF-8', false);
        $pdf->cwFooterVigencia = $fechaVigencia;
        $pdf->SetCreator('ConlineWeb');
        $pdf->SetAuthor('ConlineWeb');
        $pdf->SetTitle('Comprobante de Pago #' . $referencia);
        $pdf->SetSubject('Comprobante de pago');
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(true);
        $pdf->SetMargins(18, 18, 18);
        $pdf->SetFooterMargin(12);
        $pdf->SetAutoPageBreak(true, 48);
        $pdf->setFontSubsetting(true);
        $pdf->SetFont('dejavusans', '', 10);
        $pdf->AddPage();

        // Barra de marca superior
        $pdf->SetFillColor(0, 1, 71);
        $pdf->Rect(0, 0, 210, 6, 'F');
        $pdf->SetY(14);

        $html = '
<style>
  table { border-collapse: collapse; }
</style>

<table cellpadding="0" style="width:100%;">
  <tr>
    <td style="width:48%; vertical-align:top;">
      ' . $logoHtml . '<br><br>
      <span style="font-size:9px; color:#64748b; line-height:1.7;">
        info@conlineweb.com<br>
        WhatsApp +52 477 118 1285<br>
        https://conlineweb.com
      </span>
    </td>
    <td style="width:52%; text-align:right; vertical-align:top;">
      <span style="font-size:9px; color:#64748b; letter-spacing:1.2px; font-weight:bold;">COMPROBANTE</span><br>
      <span style="font-size:20px; color:#000147; font-weight:bold;">De pago</span><br><br>
      <table cellpadding="7" style="width:100%;">
        <tr>
          <td style="text-align:right; background-color:#f8fafc; border:1px solid #e2e8f0;">
            <span style="font-size:8px; color:#64748b; letter-spacing:0.5px;">REFERENCIA</span><br>
            <span style="font-size:13px; color:#000147; font-weight:bold;">#' . $h($referencia) . '</span><br>
            <span style="font-size:8.5px; color:#475569; line-height:1.75;">
              Emisión: ' . $h($fechaEmision) . '<br>
              Pago: ' . $h($fechaPago) . '
            </span>
          </td>
        </tr>
      </table>
    </td>
  </tr>
</table>

<br>
<table cellpadding="0" style="width:100%;"><tr><td style="height:1.2px; background-color:#000147;"></td></tr></table>
<br><br>

<table cellpadding="0" style="width:100%;">
  <tr>
    <td style="width:48%; vertical-align:top;">
      <table cellpadding="0" style="width:100%; border:1px solid #e2e8f0;">
        <tr>
          <td style="background-color:#f8fafc;">
            <table cellpadding="5" style="width:100%;"><tr>
              <td style="font-size:7px; color:#000147; font-weight:bold; letter-spacing:0.7px;">CLIENTE</td>
            </tr></table>
          </td>
        </tr>
        <tr>
          <td>
            <table cellpadding="6" style="width:100%;">
              <tr>
                <td style="line-height:1.4;">
                  <span style="font-size:7.5px; color:#94a3b8;">Nombre</span><br>
                  <span style="font-size:9.5px; color:#0f172a; font-weight:bold;">' . $h($nombreCliente) . '</span>
                </td>
              </tr>
              <tr>
                <td style="line-height:1.4; border-top:1px solid #f1f5f9;">
                  <span style="font-size:7.5px; color:#94a3b8;">Correo</span><br>
                  <span style="font-size:9px; color:#334155;">' . $h($correoCliente) . '</span>
                </td>
              </tr>
            </table>
          </td>
        </tr>
      </table>
    </td>
    <td style="width:4%;"></td>
    <td style="width:48%; vertical-align:top;">
      <table cellpadding="0" style="width:100%; border:1px solid #e2e8f0;">
        <tr>
          <td style="background-color:#f8fafc;">
            <table cellpadding="5" style="width:100%;"><tr>
              <td style="font-size:7px; color:#000147; font-weight:bold; letter-spacing:0.7px;">PAGO</td>
            </tr></table>
          </td>
        </tr>
        <tr>
          <td>
            <table cellpadding="6" style="width:100%;">
              <tr>
                <td style="line-height:1.4;">
                  <span style="font-size:7.5px; color:#94a3b8;">Método</span><br>
                  <span style="font-size:9.5px; color:#0f172a; font-weight:bold;">' . $h($formaTxt) . '</span>
                </td>
              </tr>
              <tr>
                <td style="line-height:1.4; border-top:1px solid #f1f5f9;">
                  <span style="font-size:7.5px; color:#94a3b8;">Estado</span><br>
                  <span style="font-size:9.5px; color:' . $estatusColor . '; font-weight:bold;">' . $h($estatusTxt) . '</span>
                </td>
              </tr>
            </table>
          </td>
        </tr>
      </table>
    </td>
  </tr>
</table>

<br><br>

<span style="font-size:8px; color:#000147; font-weight:bold; letter-spacing:0.8px;">DETALLE DEL SERVICIO</span>
<br><br>
<table cellpadding="7" style="width:100%;">
  <tr style="background-color:#000147;">
    <th style="width:54%; text-align:left; color:#ffffff; font-size:7.5px; font-weight:bold; letter-spacing:0.4px;">DESCRIPCIÓN</th>
    <th style="width:10%; text-align:center; color:#ffffff; font-size:7.5px; font-weight:bold; letter-spacing:0.4px;">CANT.</th>
    <th style="width:18%; text-align:right; color:#ffffff; font-size:7.5px; font-weight:bold; letter-spacing:0.4px;">PRECIO</th>
    <th style="width:18%; text-align:right; color:#ffffff; font-size:7.5px; font-weight:bold; letter-spacing:0.4px;">IMPORTE</th>
  </tr>
  ' . $filasHtml . '
</table>

<br><br>

<table cellpadding="0" style="width:100%;">
  <tr>
    <td style="width:52%;"></td>
    <td style="width:48%;">
      <table cellpadding="7" style="width:100%; border:1px solid #e2e8f0;">
        <tr>
          <td style="width:55%; text-align:right; font-size:9.5px; color:#64748b; background-color:#f8fafc;">Subtotal</td>
          <td style="width:45%; text-align:right; font-size:9.5px; color:#0f172a; background-color:#f8fafc;">' . $moneyTotal . '</td>
        </tr>
        <tr>
          <td style="text-align:right; font-size:11px; color:#ffffff; background-color:#000147; font-weight:bold;">Total pagado</td>
          <td style="text-align:right; font-size:11px; color:#ffffff; background-color:#000147; font-weight:bold;">' . $moneyTotal . '</td>
        </tr>
      </table>
    </td>
  </tr>
</table>
';

        $pdf->writeHTML($html, true, false, true, false, '');

        if ($outputPath === null || $outputPath === '') {
            $suffix = preg_replace('/[^a-zA-Z0-9_-]+/', '_', $referencia) ?: (string) $pagoId;
            $outputPath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'comprobante_pago_' . $suffix . '_' . time() . '.pdf';
        }

        $old = error_reporting();
        error_reporting($old & ~E_WARNING);
        $pdf->Output($outputPath, 'F');
        error_reporting($old);

        if (is_file($outputPath) && filesize($outputPath) > 0) {
            return $outputPath;
        }
        return false;
    }
}

/**
 * @return array{ok:bool,path?:string,error?:string,pago?:array,cliente?:array,filename?:string}
 */
if (!function_exists('cw_generar_nota_pago_pdf_por_id')) {
    function cw_generar_nota_pago_pdf_por_id(mysqli $conn, int $pagoId, ?int $formaPagoOverride = null, ?string $outputPath = null): array
    {
        if ($pagoId <= 0) {
            return ['ok' => false, 'error' => 'ID de pago inválido'];
        }

        $st = $conn->prepare('SELECT p.*, c.nombre_contacto, c.correo, c.empresa, c.telefono
            FROM pagos p
            LEFT JOIN clientes c ON c.id = p.id_clie
            WHERE p.id = ? LIMIT 1');
        if (!$st) {
            return ['ok' => false, 'error' => 'Error al consultar el pago'];
        }
        $st->bind_param('i', $pagoId);
        $st->execute();
        $row = $st->get_result()->fetch_assoc();
        $st->close();

        if (!$row) {
            return ['ok' => false, 'error' => 'Pago no encontrado'];
        }

        if ($formaPagoOverride !== null) {
            $row['forma_pago'] = $formaPagoOverride;
        }
        if (empty($row['fecha_pago']) || $row['fecha_pago'] === '0000-00-00') {
            $row['fecha_pago'] = date('Y-m-d');
        }

        $cliente = [
            'nombre_contacto' => (string) ($row['nombre_contacto'] ?? 'N/A'),
            'correo' => (string) ($row['correo'] ?? 'N/A'),
            'empresa' => (string) ($row['empresa'] ?? ''),
            'telefono' => (string) ($row['telefono'] ?? ''),
        ];

        $items = [$row];
        $grupalId = trim((string) ($row['pago_grupal_id'] ?? ''));
        if ($grupalId !== '') {
            $idClie = (int) ($row['id_clie'] ?? 0);
            $gq = $conn->prepare('SELECT p.* FROM pagos p WHERE p.pago_grupal_id = ? AND p.id_clie = ? AND p.estatus = 1 ORDER BY p.id ASC');
            if ($gq) {
                $gq->bind_param('si', $grupalId, $idClie);
                $gq->execute();
                $gres = $gq->get_result();
                $groupRows = [];
                while ($gr = $gres->fetch_assoc()) {
                    $groupRows[] = $gr;
                }
                $gq->close();
                if (count($groupRows) > 0) {
                    $items = $groupRows;
                }
            }
        }

        $path = cw_generar_nota_pago_pdf($row, $cliente, $conn, $outputPath, $items);
        if (!$path) {
            return ['ok' => false, 'error' => 'No se pudo generar el PDF'];
        }

        $filename = (count($items) > 1 && $grupalId !== '')
            ? ('nota_pago_grupal_' . preg_replace('/[^a-zA-Z0-9_-]+/', '_', $grupalId) . '.pdf')
            : ('nota_pago_' . $pagoId . '.pdf');

        return ['ok' => true, 'path' => $path, 'pago' => $row, 'cliente' => $cliente, 'filename' => $filename];
    }
}
