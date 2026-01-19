<?php
require_once __DIR__ . '/app/layout.php';
$user = require_login();
$pdo = db();

$cats = $pdo->query("SELECT id,name,kind FROM categories ORDER BY kind DESC, name ASC")->fetchAll();

$err=''; $ok='';

// Initialize defaults
$id = 0;
$tx_type = 'expense';
$category_id = 0;
$amount = '';
$tx_date = date('Y-m-d');
$reason = '';
$mode = 'Add';

// 1. Handle "Edit" Request (GET) - Pre-fill form
if(isset($_GET['edit'])){
    $id = (int)$_GET['edit'];
    $stmt = $pdo->prepare("SELECT * FROM transactions WHERE id=? AND user_id=?");
    $stmt->execute([$id, $user['id']]);
    $row = $stmt->fetch();
    
    if($row){
        $mode = 'Edit';
        $tx_type = $row['tx_type'];
        $category_id = (int)$row['category_id'];
        $amount = (float)$row['amount'];
        $tx_date = $row['tx_date'];
        $reason = $row['reason'];
    } else {
        $err = "Transaction not found or access denied.";
        $id = 0; // Reset
    }
}

// 2. Handle Form Submission (POST)
if($_SERVER['REQUEST_METHOD']==='POST'){
  csrf_check();
  $id = (int)($_POST['id'] ?? 0); // Hidden ID field
  $tx_type = ($_POST['tx_type'] ?? '') === 'income' ? 'income' : 'expense';
  $category_id = (int)($_POST['category_id'] ?? 0);
  $amount = (float)($_POST['amount'] ?? 0);
  $tx_date = trim($_POST['tx_date'] ?? date('Y-m-d'));
  $reason = trim($_POST['reason'] ?? '');

  if($category_id<=0 || $amount<=0 || !preg_match('/^\d{4}-\d{2}-\d{2}$/',$tx_date)){
    $err="Please fill valid fields (amount > 0).";
  } else {
      if($id > 0) {
          // UPDATE Existing
          $stmt = $pdo->prepare("UPDATE transactions SET tx_type=?, category_id=?, amount=?, tx_date=?, reason=? WHERE id=? AND user_id=?");
          $stmt->execute([$tx_type, $category_id, $amount, $tx_date, $reason, $id, $user['id']]);
          $ok="Transaction updated successfully.";
          $mode = 'Edit'; // Stay in edit mode or redirect
      } else {
          // INSERT New
          $pdo->prepare("INSERT INTO transactions(user_id,tx_type,category_id,amount,tx_date,reason) VALUES(?,?,?,?,?,?)")
              ->execute([$user['id'],$tx_type,$category_id,$amount,$tx_date,$reason]);
          $ok="Transaction added successfully.";
          // Reset fields for next entry if adding
          $amount = ''; $reason = ''; 
      }
  }
}

render_header($mode . ' Entry', $user);
?>
<div class="card">
  <h1><?php echo h($mode); ?> Income / Expense</h1>
  <div class="muted"><?php echo ($mode==='Edit') ? "Modify details for transaction #$id" : "Add salary, other income, expenses."; ?></div>
</div>

<?php if($err): ?><div class="card bad"><?php echo h($err); ?></div><?php endif; ?>
<?php if($ok): ?><div class="card good"><?php echo h($ok); ?></div><?php endif; ?>

<div class="card">
  <form method="post" class="grid">
    <input type="hidden" name="csrf" value="<?php echo h(csrf_token()); ?>"/>
    <input type="hidden" name="id" value="<?php echo (int)$id; ?>"/>

    <div class="col-3">
      <div class="muted">Type</div>
      <select name="tx_type">
        <option value="income" <?php echo $tx_type==='income'?'selected':''; ?>>Income</option>
        <option value="expense" <?php echo $tx_type==='expense'?'selected':''; ?>>Expense</option>
      </select>
    </div>
    <div class="col-5">
      <div class="muted">Category</div>
      <select name="category_id" required>
        <?php foreach($cats as $c): ?>
          <option value="<?php echo (int)$c['id']; ?>" <?php echo $category_id===$c['id']?'selected':''; ?>>
            <?php echo h($c['name']); ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-2">
      <div class="muted">Amount</div>
      <input type="number" step="0.01" min="0" name="amount" value="<?php echo h($amount); ?>" required />
    </div>
    <div class="col-2">
      <div class="muted">Date</div>
      <input type="date" name="tx_date" value="<?php echo h($tx_date); ?>" required />
    </div>
    <div class="col-12">
      <div class="muted">Reason / Notes</div>
      <textarea name="reason" placeholder="Write reason..."><?php echo h($reason); ?></textarea>
    </div>
    <div class="col-12">
      <button class="btn" type="submit"><?php echo ($mode==='Edit') ? 'Update Transaction' : 'Save Entry'; ?></button>
      <a class="btn" href="/tx_list.php">View Transactions</a>
    </div>
  </form>
</div>

<?php render_footer(); ?>