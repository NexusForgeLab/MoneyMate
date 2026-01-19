<?php
declare(strict_types=1);
require_once __DIR__ . '/db.php';

// --- CORE FUNCTIONS ---

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

// OPTIMIZED: Single query instead of looping 12 times
function last_n_months(int $userId, int $n=12): array {
  $pdo = db();
  // Generate list of last N months in PHP to ensure we have entries even for months with 0 data
  $months = [];
  for($i=$n-1; $i>=0; $i--){
      $months[date('Y-m', strtotime("-$i months"))] = ['income'=>0.0, 'expense'=>0.0, 'net'=>0.0];
  }

  $start_date = date('Y-m-01', strtotime("-".($n-1)." months"));
  
  $sql = "SELECT strftime('%Y-%m', tx_date) as m, tx_type, SUM(amount) as total 
          FROM transactions 
          WHERE user_id=? AND tx_date >= ? 
          GROUP BY m, tx_type";
          
  $st = $pdo->prepare($sql);
  $st->execute([$userId, $start_date]);
  
  while($r = $st->fetch()){
      if(isset($months[$r['m']])){
          if($r['tx_type'] === 'income') $months[$r['m']]['income'] = (float)$r['total'];
          if($r['tx_type'] === 'expense') $months[$r['m']]['expense'] = (float)$r['total'];
      }
  }

  $out = [];
  foreach($months as $ym => $data){
      $out[] = [
          'month' => $ym,
          'income' => $data['income'],
          'expense' => $data['expense'],
          'net' => $data['income'] - $data['expense']
      ];
  }
  return $out;
}

function expense_by_category(int $userId, string $from, string $to): array {
  $pdo = db();
  $st = $pdo->prepare("SELECT c.name as label, COALESCE(SUM(t.amount),0) as total FROM transactions t JOIN categories c ON c.id=t.category_id WHERE t.user_id=? AND t.tx_type='expense' AND t.tx_date BETWEEN ? AND ? GROUP BY c.id ORDER BY total DESC");
  $st->execute([$userId, $from, $to]);
  return $st->fetchAll();
}

// OPTIMIZED: Solves N+1 Query Problem
function investment_summary(int $userId): array {
  $pdo = db();
  
  // Single query to get all investments AND their calculated totals
  $sql = "
    SELECT 
        i.id, i.asset_type, i.symbol, i.name, i.current_price,
        COALESCE(SUM(CASE WHEN it.side='BUY' THEN it.units ELSE 0 END), 0) as units_bought,
        COALESCE(SUM(CASE WHEN it.side='SELL' THEN it.units ELSE 0 END), 0) as units_sold,
        COALESCE(SUM(CASE WHEN it.side='BUY' THEN it.units * it.price ELSE 0 END), 0) as total_cost
    FROM investments i
    LEFT JOIN investment_txs it ON i.id = it.investment_id
    WHERE i.user_id = ?
    GROUP BY i.id
  ";

  $st = $pdo->prepare($sql);
  $st->execute([$userId]);
  
  $rows=[];
  while($r=$st->fetch()){
    $units = (float)$r['units_bought'] - (float)$r['units_sold'];
    $cost = (float)$r['total_cost'];
    $units_bought = (float)$r['units_bought'];
    
    // Calculate Average Buy Price
    $avg = ($units_bought > 0) ? $cost / $units_bought : 0;

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

function get_net_worth(int $userId): array {
    $pdo = db();
    
    // A. Cash Balance
    $st = $pdo->prepare("SELECT tx_type, COALESCE(SUM(amount),0) as total FROM transactions WHERE user_id=? GROUP BY tx_type");
    $st->execute([$userId]);
    $inc=0.0; $exp=0.0;
    while($r=$st->fetch()){
        if($r['tx_type']==='income') $inc=(float)$r['total'];
        if($r['tx_type']==='expense') $exp=(float)$r['total'];
    }
    $cash_balance = $inc - $exp;

    // B. Investment Value
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

function trigger_recurring(int $userId): int {
    $pdo = db();
    $today = (int)date('j'); 
    $this_month = date('Y-m');
    $date_str = date('Y-m-d'); 

    $st = $pdo->prepare("
        SELECT * FROM recurring 
        WHERE user_id=? AND active=1 AND day_of_month <= ? 
        AND (last_run_month IS NULL OR last_run_month != ?)
    ");
    $st->execute([$userId, $today, $this_month]);
    $items = $st->fetchAll();

    $count = 0;
    foreach($items as $item) {
        $pdo->prepare("INSERT INTO transactions(user_id, tx_type, category_id, amount, tx_date, reason) VALUES(?,?,?,?,?,?)")
            ->execute([$userId, $item['type'], $item['category_id'], $item['amount'], $date_str, $item['description'] . ' (Auto)']);
        
        $pdo->prepare("UPDATE recurring SET last_run_month=? WHERE id=?")
            ->execute([$this_month, $item['id']]);
        $count++;
    }
    return $count;
}

function get_average_savings(int $userId): float {
    // Uses the optimized last_n_months function
    $months = last_n_months($userId, 3); 
    if(empty($months)) return 0;
    
    $total = 0;
    foreach($months as $m) $total += $m['net'];
    
    return $total / count($months);
}
?>