<?php
require_once __DIR__ . '/app/layout.php';
$admin = require_admin();
$pdo = db();
$err=''; $ok='';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  csrf_check();
  $username = strtolower(trim($_POST['username'] ?? ''));
  $display  = trim($_POST['display_name'] ?? '');
  $pass     = $_POST['password'] ?? '';
  $role     = ($_POST['role'] ?? 'user') === 'admin' ? 'admin' : 'user';
  if ($username==='' || $display==='' || $pass==='') $err="Fill all fields.";
  elseif (!preg_match('/^[a-z0-9_.-]{3,30}$/', $username)) $err="Username 3-30 chars: a-z 0-9 _ . -";
  elseif (strlen($pass) < 6) $err="Password min 6 chars.";
  else {
    try {
      $pdo->prepare("INSERT INTO users(username,pass_hash,display_name,role) VALUES(?,?,?,?)")
          ->execute([$username, password_hash($pass, PASSWORD_DEFAULT), $display, $role]);
      $ok="User created.";
    } catch (Exception $e) { $err="Could not create user (maybe username exists)."; }
  }
}
$rows = $pdo->query("SELECT id,username,display_name,role,created_at FROM users ORDER BY id DESC")->fetchAll();
render_header('Users', $admin);
?>
<div class="card">
  <h1>User Management</h1>
  <div class="muted">Admin can add users.</div>
</div>
<?php if($err): ?><div class="card bad"><?php echo h($err); ?></div><?php endif; ?>
<?php if($ok): ?><div class="card good"><?php echo h($ok); ?></div><?php endif; ?>

<div class="card">
  <h2>Add User</h2>
  <form method="post" class="grid">
    <input type="hidden" name="csrf" value="<?php echo h(csrf_token()); ?>"/>
    <div class="col-3"><div class="muted">Username *</div><input name="username" required /></div>
    <div class="col-4"><div class="muted">Display Name *</div><input name="display_name" required /></div>
    <div class="col-3"><div class="muted">Password *</div><input type="password" name="password" required /></div>
    <div class="col-2">
      <div class="muted">Role</div>
      <select name="role"><option value="user">user</option><option value="admin">admin</option></select>
    </div>
    <div class="col-12"><button class="btn" type="submit">Create</button></div>
  </form>
</div>

<div class="card">
  <h2>Users</h2>
  <table>
    <thead><tr><th>ID</th><th>Username</th><th>Name</th><th>Role</th><th>Created</th></tr></thead>
    <tbody>
      <?php foreach($rows as $r): ?>
      <tr>
        <td><?php echo (int)$r['id']; ?></td>
        <td><span class="pill"><?php echo h($r['username']); ?></span></td>
        <td><?php echo h($r['display_name']); ?></td>
        <td><?php echo h($r['role']); ?></td>
        <td class="muted"><?php echo h($r['created_at']); ?></td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?php render_footer(); ?>
