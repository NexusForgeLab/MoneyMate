<?php
require_once __DIR__ . '/app/layout.php';
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

// Handle Backup Download
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

render_header('Settings', $user);
?>
<div class="card">
    <h1>Settings</h1>
</div>

<?php if($err): ?><div class="card bad"><?php echo h($err); ?></div><?php endif; ?>
<?php if($ok): ?><div class="card good"><?php echo h($ok); ?></div><?php endif; ?>

<div class="grid">
    <div class="col-6">
        <div class="card" style="border-top:4px solid #007aff">
            <h2>Data Backup</h2>
            <div class="muted">Download your entire database (transactions, investments, settings). Keep this safe!</div>
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
                <div class="col-6"><input type="password" name="new_password" placeholder="New Password" required /></div>
                <div class="col-6"><input type="password" name="new_password2" placeholder="Confirm New" required /></div>
                <div class="col-12"><button class="btn" type="submit">Update Password</button></div>
            </form>
        </div>
    </div>
</div>
<?php render_footer(); ?>
