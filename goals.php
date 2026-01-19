<?php
require_once __DIR__ . '/app/layout.php';
require_once __DIR__ . '/app/finance.php';
$user = require_login();
$pdo = db();

if($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = $_POST['action'];
    
    if($action === 'add_money') {
        $id = (int)$_POST['id'];
        $amount = (float)$_POST['amount'];
        $pdo->prepare("UPDATE goals SET current_amount = current_amount + ? WHERE id=? AND user_id=?")
            ->execute([$amount, $id, $user['id']]);
    }
    elseif($action === 'create') {
        $name = $_POST['name'];
        $target = (float)$_POST['target'];
        $date = $_POST['date'] ?: null;
        $pdo->prepare("INSERT INTO goals(user_id, name, target_amount, target_date) VALUES(?,?,?,?)")
            ->execute([$user['id'], $name, $target, $date]);
    }
    elseif($action === 'delete') {
        $pdo->prepare("DELETE FROM goals WHERE id=? AND user_id=?")->execute([(int)$_POST['id'], $user['id']]);
    }
}

$goals = get_goals($user['id']);
render_header('Goals', $user);
?>
<div class="card">
    <h1>Savings Goals</h1>
    <div class="muted">Track progress for specific items (e.g., Car, Vacation).</div>
</div>

<div class="grid">
    <?php foreach($goals as $g): 
        $pct = ($g['target_amount'] > 0) ? ($g['current_amount'] / $g['target_amount']) * 100 : 0;
    ?>
    <div class="col-4">
        <div class="card">
            <div style="display:flex; justify-content:space-between;">
                <h2><?php echo h($g['icon'] . ' ' . $g['name']); ?></h2>
                <form method="post" onsubmit="return confirm('Delete goal?');">
                    <input type="hidden" name="csrf" value="<?php echo h(csrf_token()); ?>">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="id" value="<?php echo $g['id']; ?>">
                    <button style="background:none;border:none;cursor:pointer;">❌</button>
                </form>
            </div>
            
            <h3 class="good">₹<?php echo number_format($g['current_amount']); ?> <span class="muted" style="font-size:0.8em; color:#888;">/ <?php echo number_format($g['target_amount']); ?></span></h3>
            
            <div style="height:10px; background:#eee; border-radius:5px; margin:10px 0;">
                <div style="height:100%; background:#34c759; width:<?php echo min($pct, 100); ?>%; border-radius:5px;"></div>
            </div>
            
            <form method="post" style="display:flex; gap:5px; margin-top:15px;">
                <input type="hidden" name="csrf" value="<?php echo h(csrf_token()); ?>">
                <input type="hidden" name="action" value="add_money">
                <input type="hidden" name="id" value="<?php echo $g['id']; ?>">
                <input type="number" name="amount" placeholder="Add Amount" required style="font-size:0.9em;">
                <button class="btn" type="submit">Add</button>
            </form>
        </div>
    </div>
    <?php endforeach; ?>
    
    <div class="col-4">
        <div class="card" style="border-style:dashed; text-align:center;">
            <h2>+ New Goal</h2>
            <form method="post">
                <input type="hidden" name="csrf" value="<?php echo h(csrf_token()); ?>">
                <input type="hidden" name="action" value="create">
                <input name="name" placeholder="Goal Name" required style="margin-bottom:10px;">
                <input type="number" name="target" placeholder="Target Amount" required style="margin-bottom:10px;">
                <input type="date" name="date" style="margin-bottom:10px;">
                <button class="btn" type="submit">Create Goal</button>
            </form>
        </div>
    </div>
</div>
<?php render_footer(); ?>
