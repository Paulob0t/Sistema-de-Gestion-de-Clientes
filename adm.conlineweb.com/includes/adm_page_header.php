<?php
/**
 * Encabezado estándar de vistas admin.
 *
 * $admPageTitle          (string, required)
 * $admPageSubtitle       (string, optional)
 * $admPageIcon           (string, optional — clase bi/fas)
 * $admPageActions        (string, optional — HTML botones)
 * $admPageTitleAllowHtml (bool, optional — permite HTML en título)
 * $admBreadcrumbs        (array, optional — [['label'=>'…','href'=>'…'], …])
 * $admHeaderVariant      (string, optional — default|compact)
 */
if (empty($admPageTitle)) {
    return;
}
$variant = $admHeaderVariant ?? 'default';
$titleHtml = !empty($admPageTitleAllowHtml)
    ? $admPageTitle
    : htmlspecialchars((string) $admPageTitle, ENT_QUOTES, 'UTF-8');
?>
<header class="adm-page-header adm-page-header--<?= htmlspecialchars($variant, ENT_QUOTES, 'UTF-8') ?>">
    <?php if (!empty($admBreadcrumbs) && is_array($admBreadcrumbs)): ?>
    <nav class="adm-breadcrumb" aria-label="Breadcrumb">
        <ol class="adm-breadcrumb__list">
            <?php
            $crumbCount = count($admBreadcrumbs);
            foreach ($admBreadcrumbs as $i => $crumb):
                if (empty($crumb['label'])) {
                    continue;
                }
                $isLast = ($i === $crumbCount - 1);
                ?>
            <li class="adm-breadcrumb__item"<?= $isLast ? ' aria-current="page"' : '' ?>>
                <?php if (!$isLast && !empty($crumb['href'])): ?>
                <a href="<?= htmlspecialchars($crumb['href'], ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($crumb['label'], ENT_QUOTES, 'UTF-8') ?></a>
                <?php else: ?>
                <span><?= htmlspecialchars($crumb['label'], ENT_QUOTES, 'UTF-8') ?></span>
                <?php endif; ?>
            </li>
            <?php endforeach; ?>
        </ol>
    </nav>
    <?php endif; ?>
    <div class="adm-page-heading">
        <div class="adm-page-heading__text">
            <h1 class="adm-page-title">
                <?php if (!empty($admPageIcon)): ?>
                <i class="<?= htmlspecialchars($admPageIcon, ENT_QUOTES, 'UTF-8') ?>" aria-hidden="true"></i>
                <?php endif; ?>
                <?= $titleHtml ?>
            </h1>
            <?php if (!empty($admPageSubtitle)): ?>
            <p class="adm-page-subtitle"><?= htmlspecialchars((string) $admPageSubtitle, ENT_QUOTES, 'UTF-8') ?></p>
            <?php endif; ?>
        </div>
        <?php if (!empty($admPageActions)): ?>
        <div class="adm-page-heading__actions"><?= $admPageActions ?></div>
        <?php endif; ?>
    </div>
</header>
