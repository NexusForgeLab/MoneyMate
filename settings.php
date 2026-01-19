<?php
require_once __DIR__ . '/app/layout.php';
require_once __DIR__ . '/app/finance.php'; // Include finance for NW calc
$user = require_login();
$pdo = db();

$err=''; $ok='';

// --- 1. HANDLE RESTORE (FROM LIST) ---
if($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['restore_file'])){
    csrf_check();
    $file = basename($_POST['restore_file']);
    $source = __DIR__ . '/data/backups/' . $file;
    $target = __DIR__ . '/data/finance.db';
    
    if(file_exists($source)){
        // Create a safety backup of current state before overwriting
        copy($target, __DIR__ . '/data/backups/pre_restore_' . date('Y-m-d_H-i-s') . '.db');
        
        if(copy($source, $target)){
            // Force logout because user IDs/passwords might have changed
            logout_user();
            header("Location: /login.php?msg=Database restored. Please login again.");
            exit;
        } else {
            $err = "Failed to copy database file.";
        }
    } else {
        $err = "Backup file not found.";
    }
}

// --- 2. HANDLE UPLOAD RESTORE ---
if($_SERVER['REQUEST_METHOD']==='POST' && isset($_FILES['db_upload'])){
    csrf_check();
    if($_FILES['db_upload']['error'] === UPLOAD_ERR_OK){
        $tmp = $_FILES['db_upload']['tmp_name'];
        $target = __DIR__ . '/data/finance.db';
        
        // Validation: Check if it's a valid SQLite file
        $f = fopen($tmp, 'r');
        $header = fread($f, 16);
        fclose($f);
        
        if(strpos($header, 'SQLite format 3') === 0){
             copy($target, __DIR__ . '/data/backups/pre_upload_' . date('Y-m-d_H-i-s') . '.db');
             move_uploaded_file($tmp, $target);
             logout_user();
             header("Location: /login.php?msg=Database uploaded. Please login again.");
             exit;
        } else {
            $err = "Invalid file. Please upload a valid SQLite (.db) file.";
        }
    } else {
        $err = "Upload failed.";
    }
}

// --- 3. HANDLE DELETE BACKUP ---
if($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['delete_file'])){
    csrf_check();
    $file = basename($_POST['delete_file']);
    $path = __DIR__ . '/data/backups/' . $file;
    if(file_exists($path)){
        unlink($path);
        $ok = "Backup deleted.";
    }
}

// --- 4. HANDLE PASSWORD CHANGE ---
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

// --- 5. HANDLE MILESTONES SAVE ---
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

// --- 6. HANDLE DOWNLOAD ---
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

// Fetch data
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

// Fetch Backups List
$backup_files = glob(__DIR__ . '/data/backups/*.db');
rsort($backup_files); // Newest first

render_header('Settings', $user);
?>
<div class="card">
    <h1>Settings</h1>
</div>

<?php if($err): ?><div class="card bad"><?php echo h($err); ?></div><?php endif; ?>
<?php if($ok): ?><div class="card good"><?php echo h($ok); ?></div><?php endif; ?>

<div class="grid">
    <div class="col-12">
        <div class="card" style="border-top:4px solid #007aff">
            <h2>💾 Data Management</h2>
            
            <div class="grid">
                <div class="col-6">
                    <div style="background:#f9f9f9; padding:15px; border-radius:8px; height:100%">
                        <h3>Export Data</h3>
                        <p class="muted">Download your current database file.</p>
                        <a href="?download_db=1" class="btn">Download .db</a>
                    </div>
                </div>

                <div class="col-6">
                    <div style="background:#f9f9f9; padding:15px; border-radius:8px; height:100%">
                        <h3>Import Data</h3>
                        <p class="muted">Upload a <b>.db</b> file to replace current data.</p>
                        <form method="post" enctype="multipart/form-data" style="display:flex; gap:5px;" onsubmit="return confirm('WARNING: This will overwrite your current data! Continue?');">
                            <input type="hidden" name="csrf" value="<?php echo h(csrf_token()); ?>"/>
                            <input type="file" name="db_upload" accept=".db" required style="padding:4px;">
                            <button class="btn" type="submit">Restore</button>
                        </form>
                    </div>
                </div>
            </div>

            <h3 style="margin-top:20px; border-top:1px dashed #ccc; padding-top:15px;">Available Backups</h3>
            <div class="table-scroll" style="max-height:200px; overflow-y:auto;">
                <table>
                    <thead><tr><th>Date</th><th>Size</th><th>Actions</th></tr></thead>
                    <tbody>
                        <?php if(empty($backup_files)): ?>
                            <tr><td colspan="3" class="muted">No backups found.</td></tr>
                        <?php else: ?>
                            <?php foreach($backup_files as $bf): 
                                $name = basename($bf);
                                $size = round(filesize($bf) / 1024, 1) . ' KB';
                                $date = date('Y-m-d H:i', filemtime($bf));
                            ?>
                            <tr>
                                <td><?php echo $date; ?> <div class="muted" style="font-size:0.8em"><?php echo $name; ?></div></td>
                                <td><?php echo $size; ?></td>
                                <td>
                                    <div style="display:flex; gap:5px;">
                                        <form method="post" onsubmit="return confirm('Restore <?php echo $name; ?>? Current data will be replaced.');">
                                            <input type="hidden" name="csrf" value="<?php echo h(csrf_token()); ?>"/>
                                            <input type="hidden" name="restore_file" value="<?php echo $name; ?>"/>
                                            <button class="btn" style="padding:4px 8px; font-size:12px;">Restore</button>
                                        </form>
                                        <form method="post" onsubmit="return confirm('Delete this backup?');">
                                            <input type="hidden" name="csrf" value="<?php echo h(csrf_token()); ?>"/>
                                            <input type="hidden" name="delete_file" value="<?php echo $name; ?>"/>
                                            <button class="btn bad" style="padding:4px 8px; font-size:12px;">Del</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="col-12">
        <div class="card" style="border-top:4px solid #8e44ad">
            <h2>🔮 Future Simulator</h2>
            <div class="muted">Drag the slider to see how savings affect your goals.</div>
            
            <div style="margin: 20px 0;">
                <label style="font-weight:bold; display:block; margin-bottom:10px;">
                    Monthly Savings: <span id="simVal" style="color:#007aff; font-size:1.2em">₹10,000</span>
                </label>
                <input type="range" id="simRange" min="1000" max="500000" step="1000" value="10000" style="width:100%; cursor:pointer;">
            </div>

            <div class="table-scroll">
                <table>
                    <thead><tr><th>Milestone</th><th>Target</th><th>Time to Achieve</th></tr></thead>
                    <tbody id="simResults"></tbody>
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
                        <thead><tr><th style="width:50px">Lvl</th><th>Name</th><th>Target (₹)</th></tr></thead>
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

    <div class="col-12">
        <div class="card">
            <h2>Change Password</h2>
            <form method="post" class="grid">
                <input type="hidden" name="csrf" value="<?php echo h(csrf_token()); ?>"/>
                <input type="hidden" name="change_pass" value="1"/>
                
                <div class="col-12"><input type="password" name="current_password" placeholder="Current Password" required /></div>
                <div class="col-6"><input type="password" name="new_password" placeholder="New" required /></div>
                <div class="col-6"><input type="password" name="new_password2" placeholder="Confirm" required /></div>
                <div class="col-12"><button class="btn" type="submit">Update Password</button></div>
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

function formatMoney(n) { return '₹' + n.toLocaleString('en-IN'); }

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
        html += `<tr><td><b>${m.name}</b></td><td>${formatMoney(m.amount)}</td><td style="font-weight:bold; color:#8e44ad">${formatTime(gap, rate)}</td></tr>`;
    });
    if(milestones.length === 0) html = '<tr><td colspan="3" class="muted">No milestones set.</td></tr>';
    tbody.innerHTML = html;
}

range.addEventListener('input', updateSim);
updateSim();
</script>

<?php render_footer(); ?>