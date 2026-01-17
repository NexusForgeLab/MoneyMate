<?php
declare(strict_types=1);
require_once __DIR__ . '/../app/auth.php';
require_once __DIR__ . '/../app/finance.php';
$user = require_login();
header('Content-Type: application/json');
echo json_encode(last_n_months($user['id'], 12));
