<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/fetcher.php'; // Uses your existing fetcher logic

$pdo = db();
$userId = 1; // Default Admin ID, or loop through users

echo "<pre>Updating Prices...\n";

$stats = update_all_prices_db($pdo, $userId);

echo "Updated Stocks: " . $stats['stocks'] . "\n";
echo "Updated Mutual Funds: " . $stats['mf'] . "\n";
if(!empty($stats['errors'])) {
    echo "Errors:\n" . implode("\n", $stats['errors']);
}
echo "</pre>";
