<?php
require_once __DIR__ . '/app/layout.php';
require_once __DIR__ . '/app/finance.php';
require_once __DIR__ . '/app/fetcher.php';

$user = require_login();
$pdo = db();

$err=''; $ok='';

// --- Helper: Smart Column Finder ---
function find_col(array $header, array $candidates): int {
  foreach($header as $i => $col) {
    $c = strtolower(trim($col));
    foreach($candidates as $can) {
      if(strpos($c, $can) !== false) return $i;
    }
  }
  return -1;
}

if($_SERVER['REQUEST_METHOD']==='POST'){
  csrf_check();
  $action = $_POST['action'] ?? '';
  try{
    // --- ACTION: FETCH LIVE PRICES ---
    if($action === 'fetch_live') {
        set_time_limit(120); 
        $stats = update_all_prices_db($pdo, $user['id']);
        $msg = "Updated " . $stats['stocks'] . " stocks and " . $stats['mf'] . " mutual funds.";
        if(!empty($stats['errors'])) $msg .= " (Errors: " . implode(', ', $stats['errors']) . ")";
        $ok = $msg;
    }

    // --- RESET / DELETE ALL DATA ---
    if($action === 'reset_all'){
        $pdo->prepare("DELETE FROM investment_txs WHERE user_id=?")->execute([$user['id']]);
        $pdo->prepare("DELETE FROM investments WHERE user_id=?")->execute([$user['id']]);
        $ok = "All investment data deleted. You can now import fresh.";
    }
    
    // --- COMMON UPLOAD CHECK ---
    if($action==='import_stocks' || $action==='import_mf'){
      if(!isset($_FILES['csv_file']) || $_FILES['csv_file']['error']!==UPLOAD_ERR_OK) 
        throw new Exception("Upload failed.");
      
      $tmp = $_FILES['csv_file']['tmp_name'];
      
      $content = file_get_contents($tmp, false, null, 0, 100);
      if (strpos($content, '[Content_Types].xml') !== false || strpos($content, 'PK') === 0) {
        throw new Exception("Still looks like an Excel file! Please 'Save As' -> 'CSV (Comma delimited)'.");
      }

      $f = fopen($tmp, 'r');
      
      $header = [];
      $foundTable = false;
      while (($row = fgetcsv($f)) !== false) {
        $rowStr = strtolower(implode(',', $row));
        if (strpos($rowStr, 'symbol') !== false || strpos($rowStr, 'scheme name') !== false || strpos($rowStr, 'isin') !== false) {
            $header = $row;
            $foundTable = true;
            break;
        }
      }
      
      if(!$foundTable) throw new Exception("Could not find the data table. Check if this is a valid Groww CSV.");

      // --- STOCK IMPORT ---
      if($action==='import_stocks'){
        $idx_date = find_col($header, ['date', 'time']);
        $idx_sym  = find_col($header, ['symbol', 'script', 'company']);
        $idx_type = find_col($header, ['type', 'buy/sell']);
        $idx_qty  = find_col($header, ['quantity', 'qty']);
        $idx_val  = find_col($header, ['value', 'amount']); 
        $idx_rate = find_col($header, ['price', 'rate', 'avg']); 

        if($idx_date<0 || $idx_sym<0 || $idx_qty<0) 
          throw new Exception("Columns missing. Need: Date, Symbol, Qty.");

        $count = 0; $skipped = 0;
        $pdo->beginTransaction();
        try {
          while (($row = fgetcsv($f)) !== false) {
             if(count($row) < 3) continue;

             $dateRaw = $row[$idx_date] ?? '';
             $symbol  = strtoupper(trim($row[$idx_sym] ?? ''));
             $typeRaw = strtoupper(trim($row[$idx_type] ?? 'BUY'));
             $units   = (float)($row[$idx_qty] ?? 0);
             
             $price = 0;
             if($idx_rate >= 0) {
                 $price = (float)($row[$idx_rate] ?? 0);
             } elseif($idx_val >= 0) {
                 $val = (float)($row[$idx_val] ?? 0);
                 if($units > 0) $price = $val / $units;
             }

             $tx_date = date('Y-m-d', strtotime(str_replace('/', '-', $dateRaw)));
             
             if($symbol === '' || $units <= 0) continue;
             
             $side = (strpos($typeRaw, 'SELL') !== false) ? 'SELL' : 'BUY';

             $stmt = $pdo->prepare("SELECT id FROM investments WHERE user_id=? AND symbol=? AND asset_type='stock'");
             $stmt->execute([$user['id'], $symbol]);
             $invId = $stmt->fetchColumn();

             if(!$invId){
                $pdo->prepare("INSERT INTO investments(user_id,asset_type,symbol,name) VALUES(?,?,?,?)")
                    ->execute([$user['id'], 'stock', $symbol, $symbol]);
                $invId = $pdo->lastInsertId();
             }

             $dup = $pdo->prepare("SELECT id FROM investment_txs WHERE investment_id=? AND tx_date=? AND side=? AND units=? AND price=?");
             $dup->execute([$invId, $tx_date, $side, $units, $price]);
             if($dup->fetchColumn()){ $skipped++; continue; }

             $pdo->prepare("INSERT INTO investment_txs(user_id,investment_id,side,units,price,tx_date,note) VALUES(?,?,?,?,?,?,?)")
                 ->execute([$user['id'], $invId, $side, $units, $price, $tx_date, 'Groww Stock']);
             $count++;
          }
          $pdo->commit();
          $ok = "Imported $count stocks. Skipped $skipped duplicates.";
        } catch(Exception $e){ $pdo->rollBack(); throw $e; }
      }

      // --- MUTUAL FUND IMPORT ---
      if($action==='import_mf'){
        $idx_date   = find_col($header, ['date']);
        $idx_scheme = find_col($header, ['scheme', 'fund name']);
        $idx_units  = find_col($header, ['unit']);
        $idx_nav    = find_col($header, ['nav', 'price']);
        $idx_amt    = find_col($header, ['amount']); 
        $idx_type   = find_col($header, ['status', 'type', 'description']);

        if($idx_date<0 || $idx_scheme<0 || $idx_units<0) 
          throw new Exception("Columns missing. Need: Date, Scheme, Units.");

        $count = 0; $skipped = 0;
        $pdo->beginTransaction();
        try {
          while (($row = fgetcsv($f)) !== false) {
             if(count($row) < 3) continue;

             $dateRaw = $row[$idx_date] ?? '';
             $scheme  = trim($row[$idx_scheme] ?? '');
             $symbol  = strtoupper(substr($scheme, 0, 15)); 
             
             $units   = (float)($row[$idx_units] ?? 0);
             $nav     = (float)($row[$idx_nav] ?? 0);
             $amtRaw  = str_replace(',', '', $row[$idx_amt] ?? '0');
             $amt     = (float)$amtRaw;
             
             if($nav <= 0 && $units > 0 && $amt > 0) $nav = $amt / $units;
             
             $typeRaw = strtoupper($row[$idx_type] ?? 'PURCHASE');
             $tx_date = date('Y-m-d', strtotime(str_replace('/', '-', $dateRaw)));

             if($scheme === '' || $units <= 0) continue;

             $side = 'BUY';
             if(strpos($typeRaw, 'REDEEM') !== false || strpos($typeRaw, 'SELL') !== false) {
                 $side = 'SELL';
             }

             $stmt = $pdo->prepare("SELECT id FROM investments WHERE user_id=? AND symbol=? AND asset_type='mutual_fund'");
             $stmt->execute([$user['id'], $symbol]);
             $invId = $stmt->fetchColumn();

             if(!$invId){
                $pdo->prepare("INSERT INTO investments(user_id,asset_type,symbol,name) VALUES(?,?,?,?)")
                    ->execute([$user['id'], 'mutual_fund', $symbol, $scheme]);
                $invId = $pdo->lastInsertId();
             }

             $dup = $pdo->prepare("SELECT id FROM investment_txs WHERE investment_id=? AND tx_date=? AND side=? AND units=? AND ABS(price - ?) < 0.1");
             $dup->execute([$invId, $tx_date, $side, $units, $nav]);
             if($dup->fetchColumn()){ $skipped++; continue; }

             $pdo->prepare("INSERT INTO investment_txs(user_id,investment_id,side,units,price,tx_date,note) VALUES(?,?,?,?,?,?,?)")
                 ->execute([$user['id'], $invId, $side, $units, $nav, $tx_date, 'Groww MF']);
             $count++;
          }
          $pdo->commit();
          $ok = "Imported $count MF txs. Skipped $skipped duplicates.";
        } catch(Exception $e){ $pdo->rollBack(); throw $e; }
      }
      fclose($f);
    }

    // --- MANUAL PRICE UPDATE ---
    if($action==='update_price'){
      $id = (int)($_POST['investment_id'] ?? 0);
      $price = (float)($_POST['current_price'] ?? 0);
      if($id>0){
        $pdo->prepare("UPDATE investments SET current_price=?, updated_at=datetime('now') WHERE id=? AND user_id=?")
            ->execute([$price,$id,$user['id']]);
        $ok="Price updated.";
      }
    }
  } catch(Exception $e){ $err=$e->getMessage(); }
}

$summary = investment_summary($user['id']);

$txs = $pdo->prepare("
  SELECT it.*, i.asset_type, i.symbol, i.name
  FROM investment_txs it
  JOIN investments i ON i.id=it.investment_id
  WHERE it.user_id=?
  ORDER BY it.tx_date DESC, it.id DESC
  LIMIT 200
");
$txs->execute([$user['id']]);
$txs = $txs->fetchAll();

// Pre-fetch transactions for XIRR
$txStmt = $pdo->prepare("SELECT investment_id, tx_date, side, units, price FROM investment_txs WHERE user_id = ? ORDER BY tx_date ASC");
$txStmt->execute([$user['id']]);
$allTxs = $txStmt->fetchAll(PDO::FETCH_GROUP);

render_header('Investments', $user);
?>
<div class="card">
  <h1>Investments</h1>
  <div class="muted">Manage your portfolio.</div>
  
  <form method="post" style="margin-top:16px">
     <input type="hidden" name="csrf" value="<?php echo h(csrf_token()); ?>"/>
     <input type="hidden" name="action" value="fetch_live"/>
     <button class="btn" style="background:#007aff; color:white; border-color:#005bb5" type="submit">
       🔄 Fetch Live Prices (Yahoo/AMFI)
     </button>
  </form>
</div>

<?php if($err): ?><div class="card bad"><?php echo h($err); ?></div><?php endif; ?>
<?php if($ok): ?><div class="card good"><?php echo h($ok); ?></div><?php endif; ?>

<div class="grid">
  <div class="col-6">
    <div class="card" style="border-top: 4px solid #007aff;">
      <h2>1. Stocks</h2>
      <div class="muted">Import <b>Stocks Order History</b></div>
      <form method="post" enctype="multipart/form-data" style="margin-top:10px; display:flex; gap:10px; flex-wrap:wrap;">
        <input type="hidden" name="csrf" value="<?php echo h(csrf_token()); ?>"/>
        <input type="hidden" name="action" value="import_stocks"/>
        <input type="file" name="csv_file" accept=".csv" required style="flex:1" />
        <button class="btn" type="submit">Import</button>
      </form>
    </div>
  </div>

  <div class="col-6">
    <div class="card" style="border-top: 4px solid #34c759;">
      <h2>2. Mutual Funds</h2>
      <div class="muted">Import <b>MF Order History</b></div>
      <form method="post" enctype="multipart/form-data" style="margin-top:10px; display:flex; gap:10px; flex-wrap:wrap;">
        <input type="hidden" name="csrf" value="<?php echo h(csrf_token()); ?>"/>
        <input type="hidden" name="action" value="import_mf"/>
        <input type="file" name="csv_file" accept=".csv" required style="flex:1" />
        <button class="btn" type="submit">Import</button>
      </form>
    </div>
  </div>

  <div class="col-12">
    <div class="card">
      <h2>Active Portfolio</h2>
      <div class="muted">Only currently held assets.</div>
      <div class="table-scroll">
          <table>
            <thead><tr><th>Type</th><th>Symbol</th><th>Units</th><th>Avg Buy</th><th>Current Price</th><th>Profit/Loss</th><th>XIRR</th><th>Action</th></tr></thead>
            <tbody>
              <?php foreach($summary as $s): ?>
                <?php if($s['units'] < 0.001) continue; 
                
                    // --- XIRR CALCULATION ---
                    $invTxs = $allTxs[$s['id']] ?? [];
                    $cashflows = [];
                    foreach($invTxs as $t) {
                        $amt = ($t['side'] === 'BUY') ? -1 * ($t['units'] * $t['price']) : ($t['units'] * $t['price']);
                        $cashflows[] = ['amount' => $amt, 'date' => $t['tx_date']];
                    }
                    if($s['market_value'] > 0) {
                        $cashflows[] = ['amount' => $s['market_value'], 'date' => date('Y-m-d')];
                    }
                    $myXirr = xirr($cashflows);
                    $xirrColor = ($myXirr > 0) ? 'good' : 'bad';
                ?>
                <tr>
                  <td><span class="pill"><?php echo h($s['asset_type']); ?></span></td>
                  <td><b><?php echo h($s['symbol']); ?></b><div class="muted" style="font-size:0.8em"><?php echo h($s['name']); ?></div></td>
                  <td><?php echo number_format((float)$s['units'], 2); ?></td>
                  <td class="muted">₹<?php echo number_format((float)$s['avg_buy_price'],2); ?></td>
                  <td>₹<?php echo number_format((float)$s['current_price'],2); ?></td>
                  <td class="<?php echo ((float)$s['unrealized_pl']>=0)?'good':'bad'; ?>">₹<?php echo number_format((float)$s['unrealized_pl'],2); ?></td>
                  <td>
                    <?php if($myXirr !== null): ?>
                        <span class="<?php echo $xirrColor; ?>"><?php echo number_format($myXirr, 2); ?>%</span>
                    <?php else: ?>
                        <span class="muted">-</span>
                    <?php endif; ?>
                  </td>
                  <td>
                    <form method="post" style="display:flex;gap:5px;">
                      <input type="hidden" name="csrf" value="<?php echo h(csrf_token()); ?>"/>
                      <input type="hidden" name="action" value="update_price"/>
                      <input type="hidden" name="investment_id" value="<?php echo (int)$s['id']; ?>"/>
                      <input name="current_price" type="number" step="0.01" style="width:80px" placeholder="Price"/>
                      <button class="btn" type="submit">Set</button>
                    </form>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
      </div>
    </div>
  </div>

  <div class="col-12">
    <div class="card">
      <h2>History</h2>
      <div class="table-scroll">
          <table>
            <thead><tr><th>Date</th><th>Asset</th><th>Type</th><th>Units</th><th>Price</th></tr></thead>
            <tbody>
              <?php foreach($txs as $t): ?>
                <?php
                   $label = $t['side'];
                   if($t['asset_type'] === 'mutual_fund'){
                       $label = ($t['side'] === 'BUY') ? 'PURCHASE' : 'REDEEM';
                   }
                ?>
                <tr>
                  <td class="muted"><?php echo h($t['tx_date']); ?></td>
                  <td><?php echo h($t['symbol']); ?><div class="muted" style="font-size:0.8em"><?php echo h($t['name']); ?></div></td>
                  <td><span class="pill <?php echo $t['side']==='BUY'?'good':'bad';?>"><?php echo h($label); ?></span></td>
                  <td><?php echo number_format((float)$t['units'],4); ?></td>
                  <td>₹<?php echo number_format((float)$t['price'],2); ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
      </div>
    </div>
  </div>

  <div class="col-12">
    <div class="card bad" style="border-color:#ff3b30">
      <h2>Danger Zone</h2>
      <div class="muted">If your data is messed up (e.g. duplicates), click below to clear ALL investments and re-import.</div>
      <form method="post" style="margin-top:10px" onsubmit="return confirm('Are you sure? This deletes ALL investment history.');">
        <input type="hidden" name="csrf" value="<?php echo h(csrf_token()); ?>"/>
        <input type="hidden" name="action" value="reset_all"/>
        <button class="btn bad" style="background:#ff3b30;color:white;border:none">Reset All Data</button>
      </form>
    </div>
  </div>
</div>
<?php render_footer(); ?>