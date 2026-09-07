<?php
require_once __DIR__ . '/config_uploads.php';
header('Content-Type: text/plain; charset=utf-8');
echo "TEST SUBIDAS\n";
echo "FS TARGET: " . TICKETS_UPLOAD_FS . "\n";
echo "URL BASE : " . TICKETS_UPLOAD_URL . "\n";
echo "Existe dir : " . (is_dir(TICKETS_UPLOAD_FS)?'SI':'NO') . "\n";
echo "Escribible  : " . (is_writable(TICKETS_UPLOAD_FS)?'SI':'NO') . "\n";
$testFile = TICKETS_UPLOAD_FS . 'perm_test_' . time() . '.txt';
$okWrite = @file_put_contents($testFile, 'test '.date('c')) !== false;
echo "Crear archivo: " . ($okWrite?'OK':'FAIL') . "\n";
if($okWrite){
    echo "Archivo creado: $testFile\n";
}
echo "PHP upload_max_filesize: " . ini_get('upload_max_filesize') . "\n";
echo "PHP post_max_size      : " . ini_get('post_max_size') . "\n";
echo "PHP memory_limit       : " . ini_get('memory_limit') . "\n";
echo "Log (ultimas 10):\n";
if(file_exists(TICKETS_UPLOAD_LOG)) {
    $lines = explode("\n", trim(file_get_contents(TICKETS_UPLOAD_LOG)));
    $last = array_slice($lines, -10);
    echo implode("\n", $last) . "\n";
} else {
    echo "(sin log)\n";
}
?>