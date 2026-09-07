<?php
require_once dirname(__DIR__) . '/conn.php';
require_once dirname(__DIR__) . '/includes/cw_chat_migrate.php';
cw_chat_migrate($conn);
echo "Migration OK\n";
$r = $conn->query("SHOW TABLES LIKE 'cw_chat_%'");
while ($row = $r->fetch_array()) {
    echo $row[0] . "\n";
}
$faq = $conn->query('SELECT COUNT(*) c FROM cw_chat_conocimiento')->fetch_assoc();
echo 'FAQ entries: ' . ($faq['c'] ?? 0) . "\n";
