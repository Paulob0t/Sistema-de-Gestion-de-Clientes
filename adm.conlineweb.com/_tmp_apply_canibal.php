<?php
require_once __DIR__ . '/conn.php';
require_once __DIR__ . '/includes/cw_hub_config.php';
require_once __DIR__ . '/includes/cw_hub_migrate.php';
require_once __DIR__ . '/includes/cw_seo_mexico_checklist.php';

cw_hub_migrate($conn);
$result = cw_seo_mexico_checklist_apply_canibalizacion($conn, 0);
echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), PHP_EOL;
