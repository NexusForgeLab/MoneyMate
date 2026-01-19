<?php
require_once __DIR__ . '/app/layout.php';
require_once __DIR__ . '/app/finance.php'; // Include finance for NW calc
$user = require_login();
$pdo = db();

$err=''; $ok='';

// Handle Password Change
if($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['change_pass'])){
  csrf_check();
  $cur = $_POST['current_password'] ?? '';
  $n1 = $_POST['new_password'] ?? '';
  $n2 = $_POST['new_password2'] ?? '';
  
  if($n1 !== $n2) $err='New passwords do not match.';
  elseif(strlen($n1) < 6) $err='New password min 6 chars.';
  else{
    $st=$pdo->prepare("SELECT pass_hash FROM users WHERE id=?");
    $st->execute([$user['id']]);
    $hash=$st->fetchColumn();
    if(!$hash || !password_verify($cur, (string)$hash)) $err='Current password is wrong.';
    else{
      $pdo->prepare("UPDATE users SET pass_hash=? WHERE id=?")
          ->execute([password_hash($n1, PASSWORD_DEFAULT), $user['id']]);
      $ok='Password updated.';
    }
  }
}

// Handle Milestones Save
if($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['save_milestones'])){
    csrf_check();
    try {
        $pdo->beginTransaction();
        for($i=1; $i<=5; $i++){
            $name = trim($_POST["name_$i"] ?? '');
            $amount = (float)($_POST["amount_$i"] ?? 0);
            
            if($name === '' || $amount <= 0) {
                $pdo->prepare("DELETE FROM milestones WHERE user_id=? AND level=?")->execute([$user['id'], $i]);
            } else {
                $pdo->prepare("INSERT OR REPLACE INTO milestones(user_id, level, name, amount) VALUES(?,?,?,?)")
                    ->execute([$user['id'], $i, $name, $amount]);
            }
        }
        $pdo->commit();
        $ok = "Milestones updated successfully.";
    } catch(Exception $e) {
        $pdo->rollBack();
        $err = "Error saving milestones.";
    }
}

// Handle Backup
if(isset($_GET['download_db'])){
    $file = __DIR__ . '/data/finance.db';
    if(file_exists($file)){
        header('Content-Description: File Transfer');
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="MoneyMate_Backup_'.date('Y-m-d').'.db"');
        header('Content-Length: ' . filesize($file));
        readfile($file);
        exit;
    } else {
        $err = "Database file not found.";
    }
}

// Fetch data for Simulator
$ms_map = [];
$stmt = $pdo->prepare("SELECT * FROM milestones WHERE user_id=? ORDER BY level ASC");
$stmt->execute([$user['id']]);
$milestones_js = [];
while($row = $stmt->fetch()){
    $ms_map[$row['level']] = $row;
    $milestones_js[] = ['name'=>$row['name'], 'amount'=>(float)$row['amount']];
}

$nw_data = get_net_worth($user['id']);
$current_nw = $nw_data['total_net_worth'];

render_header('Settings', $user);
?>
<div class="card">
    <h1>Settings</h1>
</div>

<?php if($err): ?><div class="card bad"><?php echo h($err); ?></div><?php endif; ?>
<?php if($ok): ?><div class="card good"><?php echo h($ok); ?></div><?php endif; ?>

<div class="grid">
    <div class="col-12">
        <div class="card" style="border-top:4px solid #8e44ad">
            <h2>🔮 Future Simulator</h2>
            <div class="muted">Drag the slider to see how different savings rates affect your goals.</div>
            
            <div style="margin: 20px 0;">
                <label style="font-weight:bold; display:block; margin-bottom:10px;">
                    Hypothetical Monthly Savings: <span id="simVal" style="color:#007aff; font-size:1.2em">₹10,000</span>
                </label>
                <input type="range" id="simRange" min="1000" max="500000" step="1000" value="10000" style="width:100%; cursor:grab;">
            </div>

            <div class="table-scroll">
                <table>
                    <thead><tr><th>Milestone</th><th>Target</th><th>Time to Achieve</th></tr></thead>
                    <tbody id="simResults">
                        </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="col-12">
        <div class="card" style="border-top:4px solid #34c759">
            <h2>Edit Milestones</h2>
            <form method="post" style="margin-top:15px">
                <input type="hidden" name="csrf" value="<?php echo h(csrf_token()); ?>"/>
                <input type="hidden" name="save_milestones" value="1"/>
                
                <div class="table-scroll">
                    <table style="margin-bottom:0">
                        <thead>
                            <tr>
                                <th style="width:50px">Lvl</th>
                                <th>Name</th>
                                <th>Target (₹)</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php for($i=1; $i<=5; $i++): 
                                $m = $ms_map[$i] ?? ['name'=>'','amount'=>''];
                            ?>
                            <tr>
                                <td style="vertical-align:middle; text-align:center"><b><?php echo $i; ?></b></td>
                                <td><input name="name_<?php echo $i; ?>" placeholder="Name" value="<?php echo h($m['name']); ?>"></td>
                                <td><input type="number" step="1000" name="amount_<?php echo $i; ?>" placeholder="100000" value="<?php echo h($m['amount']); ?>"></td>
                            </tr>
                            <?php endfor; ?>
                        </tbody>
                    </table>
                </div>
                <div style="margin-top:12px">
                    <button class="btn" type="submit">Save Milestones</button>
                </div>
            </form>
        </div>
    </div>

    <div class="col-6">
        <div class="card" style="border-top:4px solid #007aff">
            <h2>Data Backup</h2>
            <div style="margin-top:20px">
                <a href="?download_db=1" class="btn">Download Database (.db)</a>
            </div>
        </div>
    </div>

    <div class="col-6">
        <div class="card">
            <h2>Change Password</h2>
            <form method="post" class="grid">
                <input type="hidden" name="csrf" value="<?php echo h(csrf_token()); ?>"/>
                <input type="hidden" name="change_pass" value="1"/>
                
                <div class="col-12"><input type="password" name="current_password" placeholder="Current Password" required /></div>
                <div class="col-6"><input type="password" name="new_password" placeholder="New" required /></div>
                <div class="col-6"><input type="password" name="new_password2" placeholder="Confirm" required /></div>
                <div class="col-12"><button class="btn" type="submit">Update</button></div>
            </form>
        </div>
    </div>
</div>

<script>
const milestones = <?php echo json_encode($milestones_js); ?>;
const currentNW = <?php echo (float)$current_nw; ?>;

const range = document.getElementById('simRange');
const valDisplay = document.getElementById('simVal');
const tbody = document.getElementById('simResults');

function formatMoney(n) {
    return '₹' + n.toLocaleString('en-IN');
}

function formatTime(gap, rate) {
    if(gap <= 0) return '<span class="good">Achieved! ✅</span>';
    
    const months = Math.ceil(gap / rate);
    if(months < 12) return months + " Months";
    
    const y = Math.floor(months / 12);
    const m = months % 12;
    return y + " Years" + (m > 0 ? ", " + m + " Months" : "");
}

function updateSim() {
    const rate = parseFloat(range.value);
    valDisplay.textContent = formatMoney(rate);
    
    let html = '';
    milestones.forEach(m => {
        const gap = m.amount - currentNW;
        html += `
            <tr>
                <td><b>${m.name}</b></td>
                <td>${formatMoney(m.amount)}</td>
                <td style="font-weight:bold; color:#8e44ad">${formatTime(gap, rate)}</td>
            </tr>
        `;
    });
    
    if(milestones.length === 0) {
        html = '<tr><td colspan="3" class="muted">No milestones set. Add them below!</td></tr>';
    }
    
    tbody.innerHTML = html;
}

range.addEventListener('input', updateSim);
// Init
updateSim();
</script>

<?php render_footer(); ?>