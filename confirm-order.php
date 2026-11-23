<?php
require_once __DIR__ . '/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); exit; }
verify_csrf();
$orderId = (int) ($_POST['order_id'] ?? 0);
if (!$orderId || empty($_SESSION['tracked_orders'][$orderId])) { http_response_code(403); exit; }

$statement = db()->prepare("UPDATE orders SET admin_confirmed_at=COALESCE(admin_confirmed_at, NOW()) WHERE id=? AND payment_status='approved'");
$statement->execute([$orderId]);
header('Content-Type: application/json');
echo json_encode(['ok' => true]);
