<?php
declare(strict_types=1);
require_once __DIR__ . '/../app/auth.php';
require_once __DIR__ . '/../app/finance.php';
$user = require_login();

$from = trim($_GET['from'] ?? '');
$to = trim($_GET['to'] ?? '');
if(!preg_match('/^\d{4}-\d{2}-\d{2}$/',$from) || !preg_match('/^\d{4}-\d{2}-\d{2}$/',$to)){
  http_response_code(400); echo "bad date"; exit;
}
header('Content-Type: application/json');
echo json_encode(expense_by_category($user['id'], $from, $to));
