<?php
/**
 * Franja de flujo SEO (intuitiva) para vistas del plan México / IA.
 *
 * Variables:
 *   $seoUxActive  chat|monitor|audit|external
 */
if (!function_exists('adm_href')) {
    require_once __DIR__ . '/adm_paths.php';
}
$seoUxActive = $seoUxActive ?? '';
$seoUxSteps = [
    [
        'id' => 'chat',
        'n' => '1',
        'title' => 'Hablar con la IA',
        'desc' => 'Pide ideas, diseño o cambios del sitio',
        'href' => adm_href('analytics/seo_mexico_ai_prompt.php'),
    ],
    [
        'id' => 'monitor',
        'n' => '2',
        'title' => 'Revisar propuestas',
        'desc' => 'Vista previa → aprobar o rechazar',
        'href' => adm_href('analytics/seo_mexico_monitor.php'),
    ],
    [
        'id' => 'audit',
        'n' => '3',
        'title' => 'Auditar código',
        'desc' => 'Detectar y corregir problemas del sitio',
        'href' => adm_href('analytics/seo_mexico_checklist.php'),
    ],
    [
        'id' => 'external',
        'n' => '4',
        'title' => 'Tareas externas',
        'desc' => 'GSC, Maps, reseñas y acciones fuera del código',
        'href' => adm_href('analytics/seo_mexico_external.php'),
    ],
];
?>
<link href="<?= htmlspecialchars(adm_href('analytics/css/seo-module.css'), ENT_QUOTES, 'UTF-8') ?>?v=20260719" rel="stylesheet">
<nav class="seo-ux-strip" aria-label="Flujo SEO del sistema">
  <?php foreach ($seoUxSteps as $step): ?>
  <a class="seo-ux-step <?= $seoUxActive === $step['id'] ? 'is-active' : '' ?>" href="<?= htmlspecialchars($step['href'], ENT_QUOTES, 'UTF-8') ?>">
    <span class="seo-ux-step-num" aria-hidden="true"><?= htmlspecialchars($step['n'], ENT_QUOTES, 'UTF-8') ?></span>
    <span>
      <strong><?= htmlspecialchars($step['title'], ENT_QUOTES, 'UTF-8') ?></strong>
      <span><?= htmlspecialchars($step['desc'], ENT_QUOTES, 'UTF-8') ?></span>
    </span>
  </a>
  <?php endforeach; ?>
</nav>
