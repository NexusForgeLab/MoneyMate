<?php
declare(strict_types=1);
require_once __DIR__ . '/../app/auth.php';
require_once __DIR__ . '/../app/finance.php';
$user = require_login();
[$from,$to] = month_start_end();
header('Content-Type: application/json');
echo json_encode(totals_for_range($user['id'], $from, $to));
