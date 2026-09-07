<?php
/**
 * Preview de correos ConlineWeb — índice por proyecto.
 *
 * Índice general:  /email_preview.php
 * Por proyecto:    /email_preview.php?p=adm|cliente|conlineweb|hostpro
 * Ver correo:      /email_preview.php?v=adm_pago_pendiente
 */
require_once __DIR__ . '/includes/email_preview_samples.php';

$catalog = email_preview_catalog();
$extras = email_preview_extras();
$allowedV = array_keys($catalog);

$projects = [
    'adm' => [
        'title' => 'adm.conlineweb.com',
        'subtitle' => 'Pagos, renovaciones, tickets, alertas, Hub y chat',
        'host' => 'adm.conlineweb.com',
        'color' => '#6366f1',
    ],
    'cliente' => [
        'title' => 'cliente.conlineweb.com',
        'subtitle' => 'Tickets, dominios, cuenta y seguridad',
        'host' => 'cliente.conlineweb.com',
        'color' => '#10b981',
    ],
    'conlineweb' => [
        'title' => 'conlineweb.com',
        'subtitle' => 'Tienda online — bienvenida, pedidos y alertas admin',
        'host' => 'conlineweb.com',
        'color' => '#000147',
    ],
    'hostpro' => [
        'title' => 'HostPro',
        'subtitle' => 'Marca independiente — pagos sistema hostingpro',
        'host' => 'hostpro.com.mx',
        'color' => '#00e5ff',
    ],
];

$p = isset($_GET['p']) ? (string) $_GET['p'] : '';
if ($p !== '' && !isset($projects[$p])) {
    $p = '';
}

$v = isset($_GET['v']) ? (string) $_GET['v'] : '';
if ($v !== '' && !in_array($v, $allowedV, true)) {
    $v = '';
}

if ($v === '') {
    header('Content-Type: text/html; charset=UTF-8');
    email_preview_render_index($catalog, $extras, $projects, $p);
    exit;
}

$html = email_preview_render($v);
if ($html === '') {
    header('Location: email_preview.php', true, 302);
    exit;
}

$meta = $catalog[$v];
$projectId = $meta['project'];
$projectTitle = $projects[$projectId]['title'] ?? $projectId;

header('Content-Type: text/html; charset=UTF-8');
email_preview_render_frame($html, $v, $meta['label'], $projectId, $projectTitle);

function email_preview_group_catalog(array $catalog, string $filterProject): array
{
    $grouped = [];
    foreach ($catalog as $key => $meta) {
        $proj = $meta['project'];
        if ($filterProject !== '' && $proj !== $filterProject) {
            continue;
        }
        $group = $meta['group'] ?? 'General';
        $grouped[$proj][$group][] = [
            'key' => $key,
            'label' => $meta['label'],
            'sort' => $meta['sort'] ?? 999,
        ];
    }

    foreach ($grouped as $proj => $groups) {
        ksort($groups);
        foreach ($groups as $groupName => $items) {
            usort($items, static fn ($a, $b) => ($a['sort'] <=> $b['sort']) ?: strcmp($a['label'], $b['label']));
            $grouped[$proj][$groupName] = $items;
        }
    }

    return $grouped;
}

function email_preview_count_by_project(array $catalog): array
{
    $counts = [];
    foreach ($catalog as $meta) {
        $proj = $meta['project'];
        $counts[$proj] = ($counts[$proj] ?? 0) + 1;
    }
    return $counts;
}

function email_preview_render_index(array $catalog, array $extras, array $projects, string $filterProject): void
{
    $base = 'email_preview.php';
    $grouped = email_preview_group_catalog($catalog, $filterProject);
    $counts = email_preview_count_by_project($catalog);
    $total = array_sum($counts);

    $pageTitle = $filterProject !== ''
        ? 'Correos — ' . ($projects[$filterProject]['title'] ?? $filterProject)
        : 'Correos ConlineWeb — todos los proyectos';

    ?><!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; font-family: Montserrat, Arial, sans-serif; background: #0f172a; color: #e2e8f0; min-height: 100vh; padding: 28px 16px 56px; }
        .wrap { max-width: 960px; margin: 0 auto; }
        h1 { margin: 0 0 6px; font-size: 1.7rem; color: #fff; }
        .lead { margin: 0 0 22px; color: #94a3b8; line-height: 1.6; font-size: .92rem; }
        .stats { display: flex; flex-wrap: wrap; gap: 10px; margin-bottom: 22px; }
        .stat { padding: 8px 14px; border-radius: 10px; background: rgba(255,255,255,.05); border: 1px solid rgba(255,255,255,.08); font-size: .78rem; color: #94a3b8; }
        .stat strong { color: #fff; }
        .tabs { display: flex; flex-wrap: wrap; gap: 8px; margin-bottom: 26px; }
        .tab {
            display: inline-flex; align-items: center; gap: 8px;
            padding: 10px 16px; border-radius: 999px; font-size: .8rem; font-weight: 700;
            text-decoration: none; color: #cbd5e1; border: 1px solid rgba(255,255,255,.12); background: rgba(255,255,255,.04);
        }
        .tab:hover { border-color: #10b981; color: #fff; }
        .tab.is-active { background: rgba(16,185,129,.15); border-color: #10b981; color: #6ee7b7; }
        .tab .n { font-size: .68rem; padding: 2px 7px; border-radius: 999px; background: rgba(0,0,0,.35); color: #94a3b8; }
        .tab.is-active .n { background: rgba(16,185,129,.25); color: #a7f3d0; }
        .project {
            margin-bottom: 28px; padding: 20px 20px 6px; background: rgba(255,255,255,.04);
            border: 1px solid rgba(255,255,255,.08); border-radius: 18px;
            border-left: 4px solid var(--accent, #10b981);
        }
        .project-head { margin-bottom: 16px; padding-bottom: 12px; border-bottom: 1px solid rgba(255,255,255,.07); display: flex; justify-content: space-between; align-items: flex-start; gap: 12px; flex-wrap: wrap; }
        .project-head h2 { margin: 0 0 4px; font-size: 1.1rem; color: #fff; }
        .project-head p { margin: 0; font-size: .8rem; color: #64748b; max-width: 520px; }
        .project-head code { font-size: .72rem; color: #10b981; background: rgba(0,0,0,.25); padding: 2px 8px; border-radius: 6px; }
        .project-count { font-size: .75rem; font-weight: 700; color: #64748b; white-space: nowrap; }
        .group { margin-bottom: 16px; }
        .group-title { margin: 0 0 8px; font-size: .68rem; font-weight: 800; text-transform: uppercase; letter-spacing: .12em; color: #64748b; }
        .links { display: grid; gap: 7px; }
        a.item {
            display: flex; align-items: center; justify-content: space-between; gap: 12px;
            padding: 12px 15px; background: rgba(255,255,255,.05); border: 1px solid rgba(255,255,255,.08);
            border-radius: 11px; color: #f1f5f9; text-decoration: none; font-size: .88rem; transition: .15s;
        }
        a.item:hover { border-color: #10b981; background: rgba(16,185,129,.08); transform: translateY(-1px); }
        a.item span { color: #64748b; font-size: .72rem; font-family: ui-monospace, Consolas, monospace; flex-shrink: 0; }
        a.item-ext::after { content: ' ↗'; font-size: .75rem; color: #64748b; }
        .note { font-size: .72rem; color: #475569; margin-left: 6px; }
        .extras-block { margin-top: 4px; padding-top: 12px; border-top: 1px dashed rgba(255,255,255,.1); }
        .extras-label { margin: 0 0 8px; font-size: .68rem; font-weight: 800; text-transform: uppercase; letter-spacing: .1em; color: #475569; }
    </style>
</head>
<body>
    <div class="wrap">
        <h1><?= htmlspecialchars($pageTitle) ?></h1>
        <p class="lead">Haz clic en cualquier enlace para ver el correo o pantalla con datos de ejemplo. Diseño unificado ConlineWeb · logo blanco.</p>

        <div class="stats">
            <span class="stat"><strong><?= (int) $total ?></strong> correos en total</span>
            <?php foreach ($projects as $id => $proj): if (($counts[$id] ?? 0) === 0) continue; ?>
            <span class="stat"><strong><?= (int) ($counts[$id] ?? 0) ?></strong> <?= htmlspecialchars($proj['title']) ?></span>
            <?php endforeach; ?>
        </div>

        <nav class="tabs">
            <a class="tab<?= $filterProject === '' ? ' is-active' : '' ?>" href="<?= htmlspecialchars($base) ?>">
                Todos <span class="n"><?= (int) $total ?></span>
            </a>
            <?php foreach ($projects as $id => $proj): ?>
            <a class="tab<?= $filterProject === $id ? ' is-active' : '' ?>" href="<?= htmlspecialchars($base) ?>?p=<?= urlencode($id) ?>">
                <?= htmlspecialchars($proj['title']) ?>
                <?php if (($counts[$id] ?? 0) > 0): ?><span class="n"><?= (int) $counts[$id] ?></span><?php endif; ?>
            </a>
            <?php endforeach; ?>
        </nav>

        <?php if ($grouped === []): ?>
            <p class="lead">No hay variantes para este filtro.</p>
        <?php endif; ?>

        <?php foreach ($projects as $id => $proj):
            if (!isset($grouped[$id]) && empty($extras[$id])) {
                continue;
            }
            if ($filterProject !== '' && $filterProject !== $id) {
                continue;
            }
            $projCount = $counts[$id] ?? 0;
        ?>
        <section class="project" style="--accent: <?= htmlspecialchars($proj['color']) ?>">
            <div class="project-head">
                <div>
                    <h2><?= htmlspecialchars($proj['title']) ?></h2>
                    <p><?= htmlspecialchars($proj['subtitle']) ?> · <code><?= htmlspecialchars($proj['host']) ?></code></p>
                </div>
                <?php if ($projCount > 0): ?>
                <span class="project-count"><?= (int) $projCount ?> correo<?= $projCount === 1 ? '' : 's' ?></span>
                <?php endif; ?>
            </div>

            <?php if (isset($grouped[$id])): ?>
                <?php foreach ($grouped[$id] as $groupName => $items): ?>
                <div class="group">
                    <p class="group-title"><?= htmlspecialchars($groupName) ?></p>
                    <div class="links">
                        <?php foreach ($items as $item): ?>
                        <a class="item" href="<?= htmlspecialchars($base) ?>?v=<?= urlencode($item['key']) ?>">
                            <?= htmlspecialchars($item['label']) ?>
                            <span><?= htmlspecialchars($item['key']) ?></span>
                        </a>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>

            <?php if (!empty($extras[$id])): ?>
            <div class="extras-block">
                <p class="extras-label">Pantallas y herramientas</p>
                <div class="links">
                    <?php foreach ($extras[$id] as $link): ?>
                    <a class="item item-ext" href="<?= htmlspecialchars($link['url']) ?>"<?= str_starts_with($link['url'], 'http') ? ' target="_blank" rel="noopener"' : '' ?>>
                        <span>
                            <?= htmlspecialchars($link['label']) ?>
                            <?php if (!empty($link['note'])): ?><span class="note">(<?= htmlspecialchars($link['note']) ?>)</span><?php endif; ?>
                        </span>
                    </a>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
        </section>
        <?php endforeach; ?>
    </div>
</body>
</html><?php
}

function email_preview_render_frame(string $html, string $v, string $label, string $projectId, string $projectTitle): void
{
    ?><!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($label) ?> — Preview</title>
    <style>
        body { margin: 0; font-family: Montserrat, Arial, sans-serif; background: #1e293b; }
        .bar {
            position: sticky; top: 0; z-index: 100;
            display: flex; flex-wrap: wrap; align-items: center; gap: 10px 16px;
            padding: 12px 18px; background: #0f172a; border-bottom: 1px solid rgba(255,255,255,.1);
            color: #e2e8f0; font-size: .82rem;
        }
        .bar a { color: #6ee7b7; text-decoration: none; font-weight: 700; }
        .bar a:hover { text-decoration: underline; }
        .bar .sep { color: #475569; }
        .bar .title { font-weight: 700; color: #fff; flex: 1; min-width: 180px; }
        .bar .meta { color: #64748b; font-family: ui-monospace, monospace; font-size: .72rem; }
        .preview { padding: 0; }
    </style>
</head>
<body>
    <div class="bar">
        <a href="email_preview.php">← Todos</a>
        <span class="sep">|</span>
        <a href="email_preview.php?p=<?= urlencode($projectId) ?>">← <?= htmlspecialchars($projectTitle) ?></a>
        <span class="title"><?= htmlspecialchars($label) ?></span>
        <span class="meta"><?= htmlspecialchars($v) ?></span>
    </div>
    <div class="preview"><?= $html ?></div>
</body>
</html><?php
}
