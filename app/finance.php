<?php
declare(strict_types=1);
require_once __DIR__ . '/db.php';

// --- EXISTING FUNCTIONS (Keep these) ---

function totals_for_range(int $userId, string $from, string $to): array {
  $pdo = db();
  $st = $pdo->prepare("SELECT tx_type, COALESCE(SUM(amount),0) as total FROM transactions WHERE user_id=? AND tx_date BETWEEN ? AND ? GROUP BY tx_type");
  $st->execute([$userId, $from, $to]);
  $income = 0.0; $expense = 0.0;
  while($r = $st->fetch()){
    if($r['tx_type']==='income') $income = (float)$r['total'];
    if($r['tx_type']==='expense') $expense = (float)$r['total'];
  }
  return ['income'=>$income,'expense'=>$expense,'net'=>$income-$expense];
}

function month_start_end(?string $ym = null): array {
  if(!$ym) $ym = date('Y-m');
  return [$ym . '-01', date('Y-m-t', strtotime($ym . '-01'))];
}

function last_n_months(int $userId, int $n=12): array {
  $out = [];
  for($i=$n-1; $i>=0; $i--){
    $dt = new DateTime('first day of this month');
    $dt->modify("-$i months");
    $ym = $dt->format('Y-m');
    [$from,$to] = month_start_end($ym);
    $t = totals_for_range($userId, $from, $to);
    $out[] = ['month'=>$ym, 'income'=>$t['income'], 'expense'=>$t['expense'], 'net'=>$t['net']];
  }
  return $out;
}

function expense_by_category(int $userId, string $from, string $to): array {
  $pdo = db();
  $st = $pdo->prepare("SELECT c.name as label, COALESCE(SUM(t.amount),0) as total FROM transactions t JOIN categories c ON c.id=t.category_id WHERE t.user_id=? AND t.tx_type='expense' AND t.tx_date BETWEEN ? AND ? GROUP BY c.id ORDER BY total DESC");
  $st->execute([$userId, $from, $to]);
  return $st->fetchAll();
}

function investment_summary(int $userId): array {
  $pdo = db();
  // Simplified logic for brevity, same as before
  $st = $pdo->prepare("SELECT i.id, i.asset_type, i.symbol, i.name, i.current_price FROM investments i WHERE i.user_id=?");
  $st->execute([$userId]);
  $rows=[];
  while($r=$st->fetch()){
    $units_buy = (float)($pdo->query("SELECT COALESCE(SUM(units),0) FROM investment_txs WHERE investment_id=".$r['id']." AND side='BUY'")->fetchColumn());
    $units_sell = (float)($pdo->query("SELECT COALESCE(SUM(units),0) FROM investment_txs WHERE investment_id=".$r['id']." AND side='SELL'")->fetchColumn());
    $units = $units_buy - $units_sell;
    
    // Avg Price Calc
    $cost = (float)($pdo->query("SELECT COALESCE(SUM(units*price),0) FROM investment_txs WHERE investment_id=".$r['id']." AND side='BUY'")->fetchColumn());
    $avg = ($units_buy > 0) ? $cost / $units_buy : 0;

    $cur = (float)$r['current_price'];
    $mkt = $units * $cur;
    
    $rows[] = [
        'id'=>$r['id'], 'asset_type'=>$r['asset_type'], 'symbol'=>$r['symbol'], 'name'=>$r['name'],
        'current_price'=>$cur, 'units'=>$units, 'avg_buy_price'=>$avg,
        'market_value'=>$mkt, 'unrealized_pl'=>$mkt - ($units * $avg)
    ];
  }
  return $rows;
}

// --- NEW FEATURES BELOW ---

// 1. Calculate Total Net Worth
function get_net_worth(int $userId): array {
    $pdo = db();
    
    // A. Cash Balance (Lifetime Income - Lifetime Expense)
    $st = $pdo->prepare("SELECT tx_type, COALESCE(SUM(amount),0) as total FROM transactions WHERE user_id=? GROUP BY tx_type");
    $st->execute([$userId]);
    $inc=0.0; $exp=0.0;
    while($r=$st->fetch()){
        if($r['tx_type']==='income') $inc=(float)$r['total'];
        if($r['tx_type']==='expense') $exp=(float)$r['total'];
    }
    $cash_balance = $inc - $exp;

    // B. Investment Value (Stocks + MF)
    $inv = investment_summary($userId);
    $stock_val = 0.0;
    $mf_val = 0.0;
    foreach($inv as $i){
        if($i['asset_type']==='stock') $stock_val += $i['market_value'];
        else $mf_val += $i['market_value'];
    }

    return [
        'cash_balance' => $cash_balance,
        'stock_value' => $stock_val,
        'mf_value' => $mf_val,
        'total_net_worth' => $cash_balance + $stock_val + $mf_val
    ];
}

// 2. Trigger Recurring Transactions
function trigger_recurring(int $userId): int {
    $pdo = db();
    $today = (int)date('j'); // Day of month (1-31)
    $this_month = date('Y-m');
    $date_str = date('Y-m-d'); // Transaction date = today

    // Fetch active recurring items that haven't run this month
    $st = $pdo->prepare("
        SELECT * FROM recurring 
        WHERE user_id=? AND active=1 AND day_of_month <= ? 
        AND (last_run_month IS NULL OR last_run_month != ?)
    ");
    $st->execute([$userId, $today, $this_month]);
    $items = $st->fetchAll();

    $count = 0;
    foreach($items as $item) {
        // Insert into real transactions
        $pdo->prepare("INSERT INTO transactions(user_id, tx_type, category_id, amount, tx_date, reason) VALUES(?,?,?,?,?,?)")
            ->execute([$userId, $item['type'], $item['category_id'], $item['amount'], $date_str, $item['description'] . ' (Auto)']);
        
        // Mark as run
        $pdo->prepare("UPDATE recurring SET last_run_month=? WHERE id=?")
            ->execute([$this_month, $item['id']]);
        $count++;
    }
    return $count;
}
?>