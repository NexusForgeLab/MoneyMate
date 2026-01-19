<?php
require_once __DIR__ . '/app/layout.php';
require_once __DIR__ . '/app/finance.php';
$user = require_login();
$pdo = db();

$msg = '';

// Save Budgets
if($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $updates = $_POST['budget'] ?? [];
    $pdo->beginTransaction();
    foreach($updates as $catId => $amount) {
        $amount = (float)$amount;
        if($amount > 0) {
            $pdo->prepare("INSERT OR REPLACE INTO budgets(user_id, category_id, amount) VALUES(?,?,?)")
                ->execute([$user['id'], $catId, $amount]);
        } else {
            $pdo->prepare("DELETE FROM budgets WHERE user_id=? AND category_id=?")
                ->execute([$user['id'], $catId]);
        }
    }
    $pdo->commit();
    $msg = "Budgets updated!";
}

$status = get_budget_status($user['id']);

// Fetch all expense categories for the form
$cats = $pdo->prepare("SELECT c.id, c.name, b.amount FROM categories c 
                       LEFT JOIN budgets b ON b.category_id = c.id AND b.user_id = ? 
                       WHERE c.kind = 'expense' ORDER BY c.name");
$cats->execute([$user['id']]);
$allCats = $cats->fetchAll();

render_header('Budgets', $user);
?>

<div class="card">
    <h1>Monthly Budgets</h1>
    <div class="muted">Set limits for your spending categories.</div>
</div>

<?php if($msg): ?><div class="card good"><?php echo h($msg); ?></div><?php endif; ?>

<div class="grid">
    <div class="col-6">
        <div class="card">
            <h2>Current Status (<?php echo date('F'); ?>)</h2>
            <?php foreach($status as $s): 
                $color = $s['pct'] > 100 ? '#ff3b30' : ($s['pct'] > 75 ? '#ff9500' : '#34c759');
            ?>
            <div style="margin-bottom: 15px;">
                <div style="display:flex; justify-content:space-between; font-size:0.9rem; margin-bottom:5px;">
                    <strong><?php echo h($s['name']); ?></strong>
                    <span class="muted">₹<?php echo number_format($s['spent']); ?> / ₹<?php echo number_format($s['budget']); ?></span>
                </div>
                <div style="height:8px; background:#eee; border-radius:4px; overflow:hidden;">
                    <div style="height:100%; background:<?php echo $color; ?>; width:<?php echo min($s['pct'], 100); ?>%"></div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <div class="col-6">
        <div class="card">
            <h2>Edit Budgets</h2>
            <form method="post">
                <input type="hidden" name="csrf" value="<?php echo h(csrf_token()); ?>">
                <div class="table-scroll">
                    <table>
                        <thead><tr><th>Category</th><th>Limit (₹)</th></tr></thead>
                        <tbody>
                            <?php foreach($allCats as $c): ?>
                            <tr>
                                <td><?php echo h($c['name']); ?></td>
                                <td><input type="number" name="budget[<?php echo $c['id']; ?>]" value="<?php echo (float)$c['amount']; ?>" placeholder="0"></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <button class="btn" type="submit" style="margin-top:10px;">Save Changes</button>
            </form>
        </div>
    </div>
</div>
<?php render_footer(); ?>
