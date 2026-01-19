<?php
declare(strict_types=1);
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/finance.php';

function render_header(string $title, ?array $user): void { 
    if($user) {
        trigger_recurring((int)$user['id']);
    }
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8"/>
  <meta name="viewport" content="width=device-width,initial-scale=1,user-scalable=no"/>
  <meta name="theme-color" content="#007aff"/>
  
  <title><?php echo h($title); ?> - MoneyMate</title>
  
  <link rel="manifest" href="/manifest.json">
  <link rel="icon" type="image/png" href="/assets/logo.png">
  <link rel="apple-touch-icon" href="/assets/icon-192.png">
  
  <link rel="stylesheet" href="/assets/style.css"/>
  
  <script>
    if ('serviceWorker' in navigator) {
      navigator.serviceWorker.register('/sw.js');
    }
  </script>
</head>
<body>
  <div class="topbar">
    <div class="brand">
        <a href="/" style="display:flex;align-items:center;gap:8px;">
            <img src="/assets/logo.png" style="height:24px;width:auto;"> MoneyMate
        </a>
    </div>
    <div class="nav">
      <?php if($user): ?>
        <a class="btn" href="/">Dashboard</a>
        <a class="btn" href="/tx_add.php">Add</a>
        <a class="btn" href="/tx_list.php">History</a>
        <a class="btn" href="/investments.php">Investments</a>
        <a class="btn" href="/recurring.php">Recurring</a>
        <a class="btn" href="/settings.php">Settings</a>
        <a class="btn" href="/logout.php">Logout</a>
      <?php else: ?>
        <a class="btn" href="/login.php">Login</a>
      <?php endif; ?>
    </div>
  </div>
  <div class="wrap">
<?php }

function render_footer(): void { ?>
  </div>
</body>
</html>
<?php }