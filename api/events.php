<?php
require_once __DIR__ . '/../app/auth.php';
require_once __DIR__ . '/../app/db.php';
$user = require_login();
$pdo = db();

$start = $_GET['start'] ?? date('Y-m-01');
$end   = $_GET['end']   ?? date('Y-m-t');

$events = [];

// 1. Fetch Actual Transactions (History)
$stmt = $pdo->prepare("SELECT id, tx_date, amount, reason, tx_type FROM transactions WHERE user_id=? AND tx_date BETWEEN ? AND ?");
$stmt->execute([$user['id'], $start, $end]);
while($row = $stmt->fetch()) {
    $color = ($row['tx_type'] === 'income') ? '#34c759' : '#ff3b30';
    $events[] = [
        'title' => $row['reason'] . ' (₹' . (int)$row['amount'] . ')',
        'start' => $row['tx_date'],
        'color' => $color,
        'allDay' => true
    ];
}

// 2. Project Future Recurring Items (Forecast)
// We look at the requested date range and see if any recurring items fall on those days
$stmt = $pdo->prepare("SELECT day_of_month, type, amount, description FROM recurring WHERE user_id=? AND active=1");
$stmt->execute([$user['id']]);
$recurring = $stmt->fetchAll();

$startDt = new DateTime($start);
$endDt   = new DateTime($end);
$today   = new DateTime();

// Loop through each recurring item
foreach($recurring as $r) {
    // Check every month in the requested range
    $curr = clone $startDt;
    while($curr <= $endDt) {
        // Construct the candidate date: YYYY-MM-{day_of_month}
        $year = $curr->format('Y');
        $month = $curr->format('m');
        $day = str_pad((string)$r['day_of_month'], 2, '0', STR_PAD_LEFT);
        
        // Handle short months (e.g. Feb 30 -> skip or move to 28)
        if(!checkdate((int)$month, (int)$day, (int)$year)) {
            $curr->modify('first day of next month');
            continue;
        }

        $dateStr = "$year-$month-$day";
        $candDt = new DateTime($dateStr);

        // Only add if it's in the future (history is covered by step 1) AND within the view range
        if($candDt > $today && $candDt >= $startDt && $candDt <= $endDt) {
            $events[] = [
                'title' => '📅 ' . $r['description'] . ' (₹' . (int)$r['amount'] . ')',
                'start' => $dateStr,
                'color' => ($r['type'] === 'income') ? '#34c75988' : '#ff3b3088', // Semi-transparent for future
                'textColor' => '#000',
                'allDay' => true
            ];
        }
        $curr->modify('first day of next month');
    }
}

header('Content-Type: application/json');
echo json_encode($events);
?>
