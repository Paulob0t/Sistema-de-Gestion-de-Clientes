<?php
// procesar_pago_multiple.php
require_once __DIR__ . '/includes/cliente_session.php';
cliente_start_session();
header('Content-Type: application/json');

error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/procesar_pago_multiple.error.log');

function jerr($msg, $http=400){ http_response_code($http); echo json_encode(['success'=>false,'error'=>$msg]); exit; }

try {
  if (!cliente_is_logged_in()) {
    jerr('No autenticado', 401);
  }

  require_once __DIR__ . '/conn.php';
  require_once __DIR__ . '/stripe-php/init.php';

  // Tu clave secreta directamente (modo test)
  $stripeSecret = 'sk_test_51JDaKWKYt1buFAz6gkoIuJUUL2jDEprbfMrDnbm4SM4a6K8dZHo657C8GoW1ytkfEq90AyIj9ajVvrBvKNsKGu7G00iIlZwU4a';
  \Stripe\Stripe::setApiKey($stripeSecret);

  $ids = $_POST['ids'] ?? [];
  if (!is_array($ids) || empty($ids)) jerr('ids inválidos');
  $ids = array_values(array_unique(array_map('intval', $ids)));

  $userId = (int)$_SESSION['id'];

  $placeholders = implode(',', array_fill(0, count($ids), '?'));
  $sql = "SELECT id, id_clie, concepto, monto, currency, estatus, nombre_servicio
          FROM pagos
          WHERE id IN ($placeholders) AND Registro = 0";

  $stmt = $conn->prepare($sql);
  if (!$stmt) jerr('DB prepare error: '.$conn->error, 500);
  $types = str_repeat('i', count($ids));
  $stmt->bind_param($types, ...$ids);
  $stmt->execute();
  $res = $stmt->get_result();
  if (!$res) jerr('Error get_result (mysqlnd faltante)', 500);

  $rows = [];
  while ($r = $res->fetch_assoc()) $rows[] = $r;
  $stmt->close();

  if (count($rows) !== count($ids)) jerr('Algunos pagos no existen');

  $moneda = null; $total = 0.0;
  foreach ($rows as $r) {
    if ((int)$r['id_clie'] !== $userId) jerr('Pago no pertenece al usuario');
    if ((int)$r['estatus'] === 1) jerr('Hay pagos ya aprobados');
    $curr = strtolower($r['currency'] ?: 'mxn');
    if ($moneda === null) $moneda = $curr;
    if ($moneda !== $curr) jerr('Monedas diferentes');
    $total += (float)$r['monto'];
  }

  $q = $conn->prepare("SELECT correo FROM clientes WHERE id=?");
  $q->bind_param('i',$userId);
  $q->execute();
  $r = $q->get_result()->fetch_assoc();
  $customerEmail = $r['correo'] ?? null;
  $q->close();

  $lineItems = [];
  foreach ($rows as $r) {
    $lineItems[] = [
      'price_data' => [
        'currency' => $moneda,
        'product_data' => ['name' => $r['concepto'] ?: ('Pago #'.$r['id'])],
        'unit_amount' => (int)round($r['monto'] * 100)
      ],
      'quantity' => 1
    ];
  }

  $params = [
    'mode' => 'payment',
    'line_items' => $lineItems,
    'success_url' => 'https://adm.conlineweb.com/payment_success.php?session_id={CHECKOUT_SESSION_ID}',
    'cancel_url' => 'https://adm.conlineweb.com/payment_cancel.php',
    'metadata' => [
      'ids' => implode(',', $ids),
      'user_id' => (string)$userId
    ]
  ];
  if ($customerEmail) $params['customer_email'] = $customerEmail;

  $session = \Stripe\Checkout\Session::create($params);

  echo json_encode([
    'success'=>true,
    'session_id'=>$session->id,
    'session_url'=>$session->url
  ]);
  exit;

} catch (\Stripe\Exception\ApiErrorException $e) {
  error_log('Stripe API error: '.$e->getMessage());
  jerr('Stripe error: '.$e->getMessage(), 500);
} catch (Throwable $e) {
  error_log('General error: '.$e->getMessage().' in '.$e->getFile().':'.$e->getLine());
  jerr('Error interno en el servidor', 500);
}
