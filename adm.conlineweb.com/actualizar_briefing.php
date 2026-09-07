<?php
/**
 * actualizar_briefing.php
 * Actualiza un briefing existente (mismos campos que guardar_briefing.php).
 */
ini_set('display_errors', 0);
error_reporting(E_ALL);
header('Content-Type: application/json; charset=utf-8');

mysqli_report(MYSQLI_REPORT_OFF);

include 'conn.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    exit;
}

function clean($val): string {
    if (is_array($val)) {
        $val = implode(', ', $val);
    }
    return htmlspecialchars(trim((string)$val), ENT_QUOTES, 'UTF-8');
}

function resolveUploadDir(): string {
    $candidates = [
        dirname(__DIR__) . '/public_html/uploads/briefing_imagenes/',
        dirname(__DIR__) . '/conlineweb.com/uploads/briefing_imagenes/',
        dirname(__DIR__) . '/www/uploads/briefing_imagenes/',
        __DIR__ . '/../public_html/uploads/briefing_imagenes/',
    ];
    foreach ($candidates as $dir) {
        $real = realpath($dir);
        if ($real && is_dir($real)) {
            return rtrim($real, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
        }
        if (is_dir($dir)) {
            return $dir;
        }
    }
    $fallback = dirname(__DIR__) . '/conlineweb.com/uploads/briefing_imagenes/';
    if (!is_dir($fallback)) {
        mkdir($fallback, 0755, true);
    }
    return $fallback;
}

$id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
if ($id <= 0) {
    echo json_encode(['success' => false, 'message' => 'ID no recibido']);
    exit;
}

$company_name      = clean($_POST['company_name']      ?? '');
$business_type     = clean($_POST['business_type']     ?? '');
$location          = clean($_POST['location']          ?? '');
$years_experience  = clean($_POST['years_experience']  ?? '');
$differentiator    = clean($_POST['differentiator']    ?? '');
$products_services = clean($_POST['products_services'] ?? '');
$main_services     = clean($_POST['main_services']     ?? '');
$goals             = clean($_POST['goals']             ?? '');
$actions           = clean($_POST['actions']           ?? '');
$logo              = clean($_POST['logo']              ?? '');
$branding          = clean($_POST['branding']          ?? '');
$brand_colors      = clean($_POST['brand_colors']      ?? '');
$features          = clean($_POST['features']          ?? '');
$other_features    = clean($_POST['other_features']    ?? '');
$domain            = clean($_POST['domain']            ?? '');
$domain_name       = clean($_POST['domain_name']       ?? '');
$hosting           = clean($_POST['hosting']           ?? '');
$hosting_provider  = clean($_POST['hosting_provider']  ?? '');
$social            = clean($_POST['social']            ?? '');
$social_links      = clean($_POST['social_links']      ?? '');
$upload_images     = clean($_POST['upload_images']     ?? '');
$contact_name      = clean($_POST['contact_name']      ?? '');
$contact_email     = clean($_POST['contact_email']     ?? '');
$contact_phone     = clean($_POST['contact_phone']     ?? '');
$contact_position  = clean($_POST['contact_position']  ?? '');

if (!filter_var($contact_email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['success' => false, 'message' => 'Email inválido']);
    exit;
}

$required = [
    'company_name'      => $company_name,
    'business_type'     => $business_type,
    'differentiator'    => $differentiator,
    'products_services' => $products_services,
    'contact_name'      => $contact_name,
    'contact_phone'     => $contact_phone,
    'goals'             => $goals,
    'actions'           => $actions,
];
foreach ($required as $label => $val) {
    if ($val === '') {
        echo json_encode(['success' => false, 'message' => 'Faltan campos obligatorios']);
        exit;
    }
}

$uploadDir = resolveUploadDir();

$existingImages = isset($_POST['existing_images']) ? json_decode($_POST['existing_images'], true) : [];
$deletedImages  = isset($_POST['deleted_images']) ? json_decode($_POST['deleted_images'], true) : [];
if (!is_array($existingImages)) $existingImages = [];
if (!is_array($deletedImages))  $deletedImages  = [];

foreach ($deletedImages as $img) {
    if (!is_string($img) || $img === '' || strpos($img, '..') !== false) {
        continue;
    }
    $path = $uploadDir . basename($img);
    if (is_file($path)) {
        @unlink($path);
    }
}

$newImages = [];
if (!empty($_FILES['new_images']['name'][0])) {
    $allowedMimes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    $allowedExts  = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
    $maxFileSize  = 5 * 1024 * 1024;
    $finfo        = class_exists('finfo') ? new finfo(FILEINFO_MIME_TYPE) : null;

    foreach ($_FILES['new_images']['tmp_name'] as $i => $tmpName) {
        if ($_FILES['new_images']['error'][$i] !== UPLOAD_ERR_OK) {
            continue;
        }
        if ($_FILES['new_images']['size'][$i] > $maxFileSize) {
            continue;
        }
        $ext = strtolower(pathinfo($_FILES['new_images']['name'][$i], PATHINFO_EXTENSION));
        if (!in_array($ext, $allowedExts, true)) {
            continue;
        }
        if ($finfo) {
            $mimeType = $finfo->file($tmpName);
            if (!in_array($mimeType, $allowedMimes, true)) {
                continue;
            }
        }
        $safeName = 'img_' . uniqid('', true) . '.' . $ext;
        if (move_uploaded_file($tmpName, $uploadDir . $safeName)) {
            $newImages[] = $safeName;
        }
    }
}

$finalImages   = array_values(array_merge($existingImages, $newImages));
$imagenes_json = json_encode($finalImages, JSON_UNESCAPED_UNICODE);

$sql = "UPDATE briefings SET
    company_name = ?, business_type = ?, location = ?, years_experience = ?, differentiator = ?,
    products_services = ?, main_services = ?, goals = ?, actions = ?, logo = ?, branding = ?,
    brand_colors = ?, features = ?, other_features = ?, domain = ?, domain_name = ?, hosting = ?,
    hosting_provider = ?, social = ?, social_links = ?, upload_images = ?, imagenes = ?,
    contact_name = ?, contact_email = ?, contact_phone = ?, contact_position = ?
    WHERE id = ?";

$stmt = $conn->prepare($sql);
if (!$stmt) {
    echo json_encode(['success' => false, 'message' => 'Error interno: ' . $conn->error]);
    exit;
}

$stmt->bind_param(
    'ssssssssssssssssssssssssssi',
    $company_name, $business_type, $location, $years_experience, $differentiator,
    $products_services, $main_services, $goals, $actions, $logo, $branding,
    $brand_colors, $features, $other_features, $domain, $domain_name, $hosting,
    $hosting_provider, $social, $social_links, $upload_images, $imagenes_json,
    $contact_name, $contact_email, $contact_phone, $contact_position,
    $id
);

if ($stmt->execute()) {
    echo json_encode(['success' => true, 'message' => 'Briefing actualizado correctamente']);
} else {
    echo json_encode(['success' => false, 'message' => 'No se pudo actualizar: ' . $stmt->error]);
}

$stmt->close();
$conn->close();
