<?php
require_once __DIR__ . '/app/layout.php';
$user = require_login();
$pdo = db();

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
  <div class="muted">Filter and review your income/expenses (latest 500).</div>
</div>

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
  <table>
    <thead><tr><th>Date</th><th>Type</th><th>Category</th><th>Amount</th><th>Reason</th></tr></thead>
    <tbody>
      <?php foreach($rows as $r): ?>
        <tr>
          <td class="muted"><?php echo h($r['tx_date']); ?></td>
          <td><span class="pill"><?php echo h($r['tx_type']); ?></span></td>
          <td><?php echo h($r['category']); ?></td>
          <td class="<?php echo $r['tx_type']==='income'?'good':'bad'; ?>">₹<?php echo number_format((float)$r['amount'],2); ?></td>
          <td class="muted"><?php echo h($r['reason']); ?></td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>

<?php render_footer(); ?>
