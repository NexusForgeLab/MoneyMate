<?php
require_once __DIR__ . '/app/layout.php';
$user = require_login();
$pdo = db();

$cats = $pdo->query("SELECT id,name,kind FROM categories ORDER BY kind DESC, name ASC")->fetchAll();

$err=''; $ok='';
if($_SERVER['REQUEST_METHOD']==='POST'){
  csrf_check();
  $tx_type = ($_POST['tx_type'] ?? '') === 'income' ? 'income' : 'expense';
  $category_id = (int)($_POST['category_id'] ?? 0);
  $amount = (float)($_POST['amount'] ?? 0);
  $tx_date = trim($_POST['tx_date'] ?? date('Y-m-d'));
  $reason = trim($_POST['reason'] ?? '');

  if($category_id<=0 || $amount<=0 || !preg_match('/^\d{4}-\d{2}-\d{2}$/',$tx_date)){
    $err="Please fill valid fields (amount > 0).";
  } else {
    $pdo->prepare("INSERT INTO transactions(user_id,tx_type,category_id,amount,tx_date,reason) VALUES(?,?,?,?,?,?)")
        ->execute([$user['id'],$tx_type,$category_id,$amount,$tx_date,$reason]);
    $ok="Saved.";
  }
}

render_header('Add Entry', $user);
?>
<div class="card">
  <h1>Add Income / Expense</h1>
  <div class="muted">Add salary, other income, expenses with category + reason.</div>
</div>

<?php if($err): ?><div class="card bad"><?php echo h($err); ?></div><?php endif; ?>
<?php if($ok): ?><div class="card good"><?php echo h($ok); ?></div><?php endif; ?>

<div class="card">
  <form method="post" class="grid">
    <input type="hidden" name="csrf" value="<?php echo h(csrf_token()); ?>"/>
    <div class="col-3">
      <div class="muted">Type</div>
      <select name="tx_type">
        <option value="income">Income</option>
        <option value="expense" selected>Expense</option>
      </select>
    </div>
    <div class="col-5">
      <div class="muted">Category</div>
      <select name="category_id" required>
        <?php foreach($cats as $c): ?>
          <option value="<?php echo (int)$c['id']; ?>"><?php echo h($c['name']); ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-2">
      <div class="muted">Amount</div>
      <input type="number" step="0.01" min="0" name="amount" required />
    </div>
    <div class="col-2">
      <div class="muted">Date</div>
      <input type="date" name="tx_date" value="<?php echo h(date('Y-m-d')); ?>" required />
    </div>
    <div class="col-12">
      <div class="muted">Reason / Notes</div>
      <textarea name="reason" placeholder="Write reason..."></textarea>
    </div>
    <div class="col-12">
      <button class="btn" type="submit">Save</button>
      <a class="btn" href="/tx_list.php">View Transactions</a>
    </div>
  </form>
</div>

<?php render_footer(); ?>
