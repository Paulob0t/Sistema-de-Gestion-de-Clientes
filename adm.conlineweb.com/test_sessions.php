<?php
header('Content-Type: application/json');

// Probar diferentes rutas posibles
$possiblePaths = [
    __DIR__ . '/../whatsapp/sessions',
    __DIR__ . '/../../whatsapp/sessions',
    dirname(__DIR__) . '/whatsapp/sessions',
    dirname(dirname(__DIR__)) . '/whatsapp/sessions',
    '/home/conlineweb/adm.conlineweb.com/whatsapp/sessions'
];

$info = [
    'current_dir' => __DIR__,
    'parent_dir' => dirname(__DIR__),
    'grandparent_dir' => dirname(dirname(__DIR__)),
    'attempts' => []
];

foreach ($possiblePaths as $path) {
    $attempt = [
        'path' => $path,
        'realpath' => realpath($path),
        'exists' => is_dir($path),
        'files' => []
    ];
    
    if (is_dir($path)) {
        $files = glob($path . '/*.json');
        if ($files) {
            foreach ($files as $file) {
                $attempt['files'][] = [
                    'basename' => basename($file),
                    'size' => filesize($file)
                ];
            }
        }
        $info['working_path'] = $path;
        $info['files'] = $attempt['files'];
    }
    
    $info['attempts'][] = $attempt;
}

echo json_encode($info, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
