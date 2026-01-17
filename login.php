<?php
require_once __DIR__ . '/app/layout.php';
$pdo = db();
$err = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  csrf_check();
  $username = strtolower(trim($_POST['username'] ?? ''));
  $pass = $_POST['password'] ?? '';
  $st = $pdo->prepare("SELECT * FROM users WHERE username=?");
  $st->execute([$username]);
  $u = $st->fetch();
  if (!$u || !password_verify($pass, $u['pass_hash'])) $err = "Invalid username or password.";
  else { login_user($u); header('Location: /'); exit; }
}
render_header('Login', current_user());
?>
<div class="card">
  <h1>Login</h1>
  <div class="muted">First time? Open <span class="pill">/install.php</span></div>
  <?php if($err): ?><div class="card bad"><?php echo h($err); ?></div><?php endif; ?>
  <form method="post" class="grid">
    <input type="hidden" name="csrf" value="<?php echo h(csrf_token()); ?>"/>
    <div class="col-6">
      <div class="muted">Username</div>
      <input name="username" required />
    </div>
    <div class="col-6">
      <div class="muted">Password</div>
      <input type="password" name="password" required />
    </div>
    <div class="col-12">
      <button class="btn" type="submit">Login</button>
    </div>
  </form>
</div>
<?php render_footer(); ?>
