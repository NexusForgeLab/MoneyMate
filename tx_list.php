<?php
require_once __DIR__ . '/app/layout.php';
$user = require_login();
$pdo = db();

$err=''; $ok='';

// --- HANDLE DELETE ACTION ---
if($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['action']) && $_POST['action']==='delete'){
    csrf_check();
    $del_id = (int)$_POST['id'];
    // Delete only if it belongs to this user
    $st = $pdo->prepare("DELETE FROM transactions WHERE id=? AND user_id=?");
    $st->execute([$del_id, $user['id']]);
    if($st->rowCount() > 0){
        $ok = "Transaction deleted.";
    } else {
        $err = "Could not delete transaction.";
    }
}

// --- FILTERING LOGIC ---
$type = ($_GET['type'] ?? '');
$cat = (int)($_GET['cat'] ?? 0);
$from = trim($_GET['from'] ?? '');
$to = trim($_GET['to'] ?? '');
$q = trim($_GET['q'] ?? '');

$where=["t.user_id=?"]; $args=[$user['id']];
if($type==='income' || $type==='expense'){ $where[]="t.tx_type=?"; $args[]=$type; }
if($cat>0){ $where[]="t.category_id=?"; $args[]=$cat; }
if($from!=='' && preg_match('/^\d{4}-\d{2}-\d{2}$/',$from)){ $where[]="t.tx_date >= ?"; $args[]=$from; }
if($to!=='' && preg_match('/^\d{4}-\d{2}-\d{2}$/',$to)){ $where[]="t.tx_date <= ?"; $args[]=$to; }
if($q!==''){ $where[]="t.reason LIKE ?"; $args[]='%'.$q.'%'; }

$sql="SELECT t.*, c.name as category FROM transactions t JOIN categories c ON c.id=t.category_id
      WHERE ".implode(" AND ",$where)." ORDER BY t.tx_date DESC, t.id DESC LIMIT 500";
$st=$pdo->prepare($sql); $st->execute($args); $rows=$st->fetchAll();

$cats=$pdo->query("SELECT id,name FROM categories ORDER BY name")->fetchAll();

render_header('Transactions', $user);
?>
<div class="card">
  <h1>Transactions</h1>
  <div class="muted">Filter, modify, or remove your income/expenses (latest 500).</div>
</div>

<?php if($err): ?><div class="card bad"><?php echo h($err); ?></div><?php endif; ?>
<?php if($ok): ?><div class="card good"><?php echo h($ok); ?></div><?php endif; ?>

<div class="card">
  <form method="get" class="grid">
    <div class="col-3">
      <div class="muted">Type</div>
      <select name="type">
        <option value="">All</option>
        <option value="income" <?php echo $type==='income'?'selected':''; ?>>Income</option>
        <option value="expense" <?php echo $type==='expense'?'selected':''; ?>>Expense</option>
      </select>
    </div>
    <div class="col-4">
      <div class="muted">Category</div>
      <select name="cat">
        <option value="0">All</option>
        <?php foreach($cats as $c): ?>
          <option value="<?php echo (int)$c['id']; ?>" <?php echo $cat===(int)$c['id']?'selected':''; ?>>
            <?php echo h($c['name']); ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-2">
      <div class="muted">From</div>
      <input type="date" name="from" value="<?php echo h($from); ?>"/>
    </div>
    <div class="col-2">
      <div class="muted">To</div>
      <input type="date" name="to" value="<?php echo h($to); ?>"/>
    </div>
    <div class="col-12">
      <div class="muted">Reason contains</div>
      <input name="q" value="<?php echo h($q); ?>" placeholder="e.g. groceries, rent, groww"/>
    </div>
    <div class="col-12">
      <button class="btn" type="submit">Filter</button>
      <a class="btn" href="/tx_list.php">Reset</a>
      <a class="btn" href="/tx_add.php">Add Entry</a>
    </div>
  </form>
</div>

<div class="card">
  <h2>List</h2>
  <div class="table-scroll">
      <table>
        <thead>
            <tr>
                <th>Date</th>
                <th>Type</th>
                <th>Category</th>
                <th>Amount</th>
                <th>Reason</th>
                <th style="width:140px; text-align:right;">Actions</th>
            </tr>
        </thead>
        <tbody>
          <?php foreach($rows as $r): ?>
            <tr>
              <td class="muted"><?php echo h($r['tx_date']); ?></td>
              <td><span class="pill <?php echo $r['tx_type']==='income'?'good':'bad'; ?>"><?php echo h($r['tx_type']); ?></span></td>
              <td><?php echo h($r['category']); ?></td>
              <td class="<?php echo $r['tx_type']==='income'?'good':'bad'; ?>">₹<?php echo number_format((float)$r['amount'],2); ?></td>
              <td class="muted"><?php echo h($r['reason']); ?></td>
              
              <td style="text-align:right;">
                 <div style="display:flex; justify-content:flex-end; gap:5px;">
                     <a href="/tx_add.php?edit=<?php echo $r['id']; ?>" class="btn" style="padding:6px 10px; font-size:12px;">Edit</a>
                     
                     <form method="post" onsubmit="return confirm('Are you sure you want to delete this transaction?');" style="margin:0;">
                        <input type="hidden" name="csrf" value="<?php echo h(csrf_token()); ?>"/>
                        <input type="hidden" name="action" value="delete"/>
                        <input type="hidden" name="id" value="<?php echo $r['id']; ?>"/>
                        <button type="submit" class="btn bad" style="padding:6px 10px; font-size:12px; background:#ff3b30; color:white; border:none;">Del</button>
                     </form>
                 </div>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
  </div>
</div>

<?php render_footer(); ?>