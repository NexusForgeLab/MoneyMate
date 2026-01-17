<?php
require_once __DIR__ . '/app/layout.php';
$user = require_login();
$pdo = db();

$err=''; $ok='';

// HANDLE ADD / DELETE
if($_SERVER['REQUEST_METHOD']==='POST'){
    csrf_check();
    $action = $_POST['action'] ?? '';
    
    if($action==='add'){
        $type = $_POST['type'];
        $cat = (int)$_POST['category_id'];
        $amt = (float)$_POST['amount'];
        $day = (int)$_POST['day_of_month'];
        $desc = trim($_POST['description']);
        
        if($amt > 0 && $day >= 1 && $day <= 31){
            $pdo->prepare("INSERT INTO recurring(user_id, type, category_id, amount, day_of_month, description) VALUES(?,?,?,?,?,?)")
                ->execute([$user['id'], $type, $cat, $amt, $day, $desc]);
            $ok = "Recurring item added. It will trigger automatically on day $day of each month.";
        } else {
            $err = "Invalid amount or day (1-31).";
        }
    }
    
    if($action==='delete'){
        $id = (int)$_POST['id'];
        $pdo->prepare("DELETE FROM recurring WHERE id=? AND user_id=?")->execute([$id, $user['id']]);
        $ok = "Item deleted.";
    }
}

$rows = $pdo->prepare("SELECT r.*, c.name as cat_name FROM recurring r JOIN categories c ON c.id=r.category_id WHERE r.user_id=? ORDER BY day_of_month ASC");
$rows->execute([$user['id']]);
$rec = $rows->fetchAll();

$cats = $pdo->query("SELECT * FROM categories ORDER BY name")->fetchAll();

render_header('Recurring', $user);
?>
<div class="card">
  <h1>Recurring Manager</h1>
  <div class="muted">Set up Salary or Subscriptions here. They will automatically add to your Transactions when you visit the dashboard on/after the scheduled day.</div>
</div>

<?php if($err): ?><div class="card bad"><?php echo h($err); ?></div><?php endif; ?>
<?php if($ok): ?><div class="card good"><?php echo h($ok); ?></div><?php endif; ?>

<div class="grid">
  <div class="col-4">
    <div class="card">
      <h2>Add New</h2>
      <form method="post" class="grid">
        <input type="hidden" name="csrf" value="<?php echo h(csrf_token()); ?>"/>
        <input type="hidden" name="action" value="add"/>
        
        <div class="col-12">
            <div class="muted">Type</div>
            <select name="type">
                <option value="expense">Expense (Subscription)</option>
                <option value="income">Income (Salary)</option>
            </select>
        </div>
        <div class="col-12">
            <div class="muted">Category</div>
            <select name="category_id">
                <?php foreach($cats as $c): ?>
                    <option value="<?php echo $c['id']; ?>"><?php echo h($c['name']); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-6">
            <div class="muted">Amount</div>
            <input type="number" step="0.01" name="amount" required>
        </div>
        <div class="col-6">
            <div class="muted">Day of Month</div>
            <input type="number" min="1" max="31" name="day_of_month" placeholder="e.g. 1 or 5" required>
        </div>
        <div class="col-12">
            <div class="muted">Description</div>
            <input name="description" placeholder="e.g. Netflix, Salary" required>
        </div>
        <div class="col-12">
            <button class="btn" type="submit">Set Auto-Add</button>
        </div>
      </form>
    </div>
  </div>

  <div class="col-8">
    <div class="card">
      <h2>Active Recurring Items</h2>
      <table>
        <thead><tr><th>Day</th><th>Type</th><th>Description</th><th>Amount</th><th>Action</th></tr></thead>
        <tbody>
          <?php foreach($rec as $r): ?>
            <tr>
              <td>Day <b><?php echo (int)$r['day_of_month']; ?></b></td>
              <td><span class="pill <?php echo $r['type']==='income'?'good':'bad'; ?>"><?php echo h($r['type']); ?></span></td>
              <td>
                <?php echo h($r['description']); ?>
                <div class="muted"><?php echo h($r['cat_name']); ?></div>
              </td>
              <td><b>₹<?php echo number_format($r['amount'],2); ?></b></td>
              <td>
                 <form method="post" onsubmit="return confirm('Stop this recurring item?');">
                    <input type="hidden" name="csrf" value="<?php echo h(csrf_token()); ?>"/>
                    <input type="hidden" name="action" value="delete"/>
                    <input type="hidden" name="id" value="<?php echo $r['id']; ?>"/>
                    <button class="btn bad" style="padding:4px 8px;font-size:12px">Remove</button>
                 </form>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
<?php render_footer(); ?>
