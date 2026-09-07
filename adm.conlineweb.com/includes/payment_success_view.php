<?php
/**
 * Vista unificada de confirmación / error de pago (Stripe).
 */
$paymentSuccessBrandFile = dirname(__DIR__, 2) . '/includes/cw_email_brand.php';
if (is_file($paymentSuccessBrandFile)) {
    require_once $paymentSuccessBrandFile;
}
unset($paymentSuccessBrandFile);

function payment_success_h(string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

/**
 * Logo de la tarjeta. Se resuelve con lo que exista en el proyecto y nunca
 * puede abortar el render: una excepción aquí dejaba la pantalla sin texto.
 */
function payment_success_logo_url(): string
{
    foreach (['cw_brand_logo_url', 'cw_email_logo_wordmark_url', 'cw_email_logo_url'] as $fn) {
        if (function_exists($fn)) {
            $url = (string) $fn();
            if ($url !== '') {
                return $url;
            }
        }
    }

    return 'https://conlineweb.com/assets/images/logo/c-online-Logo.png';
}

function payment_success_service_icon_class(string $tipo): string
{
    $tipo = strtolower(trim($tipo));
    if ($tipo === 'hosting') {
        return 'ps-service__icon--hosting';
    }
    if ($tipo === 'dominio') {
        return 'ps-service__icon--dominio';
    }
    return 'ps-service__icon--servicio';
}

function payment_success_service_abbrev(string $tipo): string
{
    $tipo = strtolower(trim($tipo));
    if ($tipo === 'hosting') {
        return 'H';
    }
    if ($tipo === 'dominio') {
        return 'D';
    }
    return 'S';
}

/**
 * @param array{
 *   title: string,
 *   subtitle?: string,
 *   badge?: string,
 *   variant?: 'success'|'info'|'warning'|'error',
 *   page_title?: string,
 *   wide?: bool,
 *   highlight?: array{label?: string, value: string, meta?: string},
 *   metrics?: list<array{label: string, value: string, icon?: string}>,
 *   details?: list<array{label: string, value: string}>,
 *   services?: list<array{tipo: string, nombre: string, monto?: float|string, nueva_fecha?: string, currency?: string}>,
 *   note?: string,
 *   actions?: list<array{label: string, url: string, style?: string, icon?: string}>,
 *   footnote?: string,
 *   auto_close_seconds?: int,
 *   confetti?: bool
 * } $config
 */
function payment_success_render(array $config): void
{
    $variant = $config['variant'] ?? 'success';
    $pageTitle = $config['page_title'] ?? $config['title'];
    $wide = !empty($config['wide']);
    $confetti = ($config['confetti'] ?? ($variant === 'success')) && $variant === 'success';

    $iconClass = 'ps-icon-circle';
    $badgeClass = 'ps-badge--success';
    if ($variant === 'info') {
        $iconClass .= ' ps-icon-circle--info';
        $badgeClass = 'ps-badge--info';
    } elseif ($variant === 'warning') {
        $iconClass .= ' ps-icon-circle--warning';
        $badgeClass = 'ps-badge--warning';
    } elseif ($variant === 'error') {
        $iconClass .= ' ps-icon-circle--error';
        $badgeClass = 'ps-badge--error';
    }

    $iconSvg = $variant === 'error'
        ? '<svg class="ps-check-svg" viewBox="0 0 48 48" aria-hidden="true"><path class="ps-check-path" d="M14 14 L34 34 M34 14 L14 34"/></svg>'
        : ($variant === 'info' || $variant === 'warning'
            ? '<svg class="ps-check-svg" viewBox="0 0 48 48" aria-hidden="true"><path class="ps-check-path" d="M24 14 v14 M24 32 v2"/></svg>'
            : '<svg class="ps-check-svg" viewBox="0 0 48 48" aria-hidden="true"><path class="ps-check-path" d="M12 25 L21 34 L36 16"/></svg>');

    $autoClose = isset($config['auto_close_seconds']) ? (int) $config['auto_close_seconds'] : 0;

    ?><!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo payment_success_h($pageTitle); ?> — ConlineWeb</title>
    <link rel="shortcut icon" href="https://c-onlineweb.com/imagenes/c-online_isotipo.png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="/assets/css/payment-success.css?v=4">
</head>
<body class="ps-body">
    <div class="ps-scene">
        <div class="ps-scene__bg" aria-hidden="true"></div>
        <div class="ps-scene__orb ps-scene__orb--1" aria-hidden="true"></div>
        <div class="ps-scene__orb ps-scene__orb--2" aria-hidden="true"></div>
        <div class="ps-scene__grid" aria-hidden="true"></div>

        <?php if ($confetti): ?>
        <div class="ps-confetti" aria-hidden="true">
            <?php
            $colors = ['#10b981', '#fbbf24', '#000147', '#6366f1', '#34d399'];
            for ($i = 0; $i < 24; $i++):
                $left = round(fmod($i * 4.2, 100), 2);
                $delay = round(fmod($i * 0.12, 2), 2);
                $color = $colors[$i % count($colors)];
            ?>
            <span style="left:<?php echo $left; ?>%;background:<?php echo $color; ?>;animation-delay:<?php echo $delay; ?>s;"></span>
            <?php endfor; ?>
        </div>
        <?php endif; ?>

        <article class="ps-card<?php echo $wide ? ' ps-card--wide' : ''; ?>">
            <div class="ps-card__shine" aria-hidden="true"></div>

            <header class="ps-card__head">
                <img class="ps-logo" src="<?php echo payment_success_h(payment_success_logo_url()); ?>" alt="ConlineWeb" width="168" height="48">

                <div class="ps-icon-wrap">
                    <div class="ps-icon-ring" aria-hidden="true"></div>
                    <div class="<?php echo payment_success_h($iconClass); ?>">
                        <?php echo $iconSvg; ?>
                    </div>
                </div>

                <?php if (!empty($config['badge'])): ?>
                <span class="ps-badge <?php echo payment_success_h($badgeClass); ?>">
                    <?php echo payment_success_h($config['badge']); ?>
                </span>
                <?php endif; ?>

                <h1 class="ps-title"><?php echo payment_success_h($config['title']); ?></h1>
                <?php if (!empty($config['subtitle'])): ?>
                <p class="ps-subtitle"><?php echo $config['subtitle']; ?></p>
                <?php endif; ?>
            </header>

            <div class="ps-body-inner">
                <?php if (!empty($config['highlight'])): ?>
                <div class="ps-highlight">
                    <?php if (!empty($config['highlight']['label'])): ?>
                    <div class="ps-highlight__label"><?php echo payment_success_h($config['highlight']['label']); ?></div>
                    <?php endif; ?>
                    <div class="ps-highlight__value"><?php echo payment_success_h($config['highlight']['value']); ?></div>
                    <?php if (!empty($config['highlight']['meta'])): ?>
                    <div class="ps-highlight__meta"><?php echo payment_success_h($config['highlight']['meta']); ?></div>
                    <?php endif; ?>
                </div>
                <?php endif; ?>

                <?php if (!empty($config['metrics'])): ?>
                <div class="ps-metrics">
                    <?php foreach ($config['metrics'] as $metric): ?>
                    <div class="ps-metric">
                        <?php if (!empty($metric['icon'])): ?>
                        <div class="ps-metric__icon"><i class="<?php echo payment_success_h($metric['icon']); ?>"></i></div>
                        <?php endif; ?>
                        <span class="ps-metric__label"><?php echo payment_success_h($metric['label']); ?></span>
                        <span class="ps-metric__value"><?php echo payment_success_h($metric['value']); ?></span>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>

                <?php if (!empty($config['details'])): ?>
                <ul class="ps-details">
                    <?php foreach ($config['details'] as $row): ?>
                    <li>
                        <span class="ps-details__key"><?php echo payment_success_h($row['label']); ?></span>
                        <span class="ps-details__val"><?php echo payment_success_h($row['value']); ?></span>
                    </li>
                    <?php endforeach; ?>
                </ul>
                <?php endif; ?>

                <?php if (!empty($config['services'])): ?>
                <h2 class="ps-section-title">Servicios confirmados</h2>
                <div class="ps-services">
                    <?php foreach ($config['services'] as $svc):
                        $tipo = $svc['tipo'] ?? 'Servicio';
                        $currency = strtoupper($svc['currency'] ?? 'MXN');
                        $metaParts = [];
                        if (isset($svc['monto']) && $svc['monto'] !== '') {
                            $metaParts[] = '$' . number_format((float) $svc['monto'], 2) . ' ' . $currency;
                        }
                        if (!empty($svc['nueva_fecha'])) {
                            $metaParts[] = 'Vigente hasta ' . date('d/m/Y', strtotime($svc['nueva_fecha']));
                        }
                    ?>
                    <div class="ps-service">
                        <div class="ps-service__icon <?php echo payment_success_service_icon_class($tipo); ?>">
                            <?php echo payment_success_h(payment_success_service_abbrev($tipo)); ?>
                        </div>
                        <div class="ps-service__body">
                            <p class="ps-service__name"><?php echo payment_success_h($tipo . ': ' . ($svc['nombre'] ?? '')); ?></p>
                            <?php if ($metaParts): ?>
                            <p class="ps-service__meta"><?php echo payment_success_h(implode(' · ', $metaParts)); ?></p>
                            <?php endif; ?>
                        </div>
                        <?php if (isset($svc['monto']) && $svc['monto'] !== ''): ?>
                        <div class="ps-service__amount">$<?php echo number_format((float) $svc['monto'], 2); ?></div>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>

                <?php if (!empty($config['note'])): ?>
                <div class="ps-note">
                    <i class="fas fa-circle-info"></i>
                    <span><?php echo $config['note']; ?></span>
                </div>
                <?php endif; ?>

                <?php if (!empty($config['actions'])): ?>
                <div class="ps-actions">
                    <?php foreach ($config['actions'] as $action):
                        $style = $action['style'] ?? 'primary';
                        $btnClass = 'ps-btn ps-btn--' . payment_success_h($style);
                    ?>
                    <a class="<?php echo $btnClass; ?>" href="<?php echo payment_success_h($action['url']); ?>">
                        <?php if (!empty($action['icon'])): ?><i class="<?php echo payment_success_h($action['icon']); ?>"></i><?php endif; ?>
                        <?php echo payment_success_h($action['label']); ?>
                    </a>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>

            <footer class="ps-footer">
                <?php if (!empty($config['footnote'])): ?>
                <p class="ps-footnote"><?php echo $config['footnote']; ?></p>
                <?php endif; ?>
                <a class="ps-support" href="https://wa.me/524771181285" target="_blank" rel="noopener">
                    <i class="fab fa-whatsapp"></i> Soporte WhatsApp 477 118 1285
                </a>
                <?php if ($autoClose > 0): ?>
                <div class="ps-countdown">
                    <span>Esta ventana se cerrará en <strong id="ps-countdown-num"><?php echo $autoClose; ?></strong> s</span>
                    <div class="ps-countdown__bar"><div class="ps-countdown__fill" style="animation-duration:<?php echo $autoClose; ?>s;"></div></div>
                </div>
                <?php endif; ?>
            </footer>
        </article>
    </div>
    <?php if ($autoClose > 0): ?>
    <script>
        (function () {
            var sec = <?php echo $autoClose; ?>;
            var el = document.getElementById('ps-countdown-num');
            var t = setInterval(function () {
                sec--;
                if (el) el.textContent = sec;
                if (sec <= 0) {
                    clearInterval(t);
                    window.close();
                }
            }, 1000);
        })();
    </script>
    <?php endif; ?>
</body>
</html><?php
    exit;
}
