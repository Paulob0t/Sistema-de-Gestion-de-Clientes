<?php
/**
 * Cabecera analytics (usa shell unificado Web Site).
 *
 * Variables: $period, $dateFrom, $dateTo, $from, $to, $activeTab ('overview'|'pages')
 */
$activeTab = $activeTab ?? 'overview';
if ($activeTab === 'pages') {
    $websiteTab = 'pages';
} elseif ($activeTab === 'sessions') {
    $websiteTab = 'sessions';
} else {
    $websiteTab = 'overview';
}
$websiteHeroTitle = 'Visitas — sitios ConlineWeb';
$websiteHeroSub = 'Tráfico, audiencia y comportamiento · .com /us · .cl';
$websiteShowPeriod = true;
require dirname(__DIR__, 2) . '/includes/website_module_shell.php';
