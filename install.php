<?php
require_once __DIR__ . '/app/db.php';
$pdo = db();

// 1. Tables (No changes to logic, just structure)
$pdo->exec("
CREATE TABLE IF NOT EXISTS users(
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  username TEXT NOT NULL UNIQUE,
  pass_hash TEXT NOT NULL,
  display_name TEXT NOT NULL,
  role TEXT NOT NULL CHECK(role IN ('admin','user')) DEFAULT 'user',
  created_at TEXT NOT NULL DEFAULT (datetime('now'))
);
CREATE TABLE IF NOT EXISTS categories(
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  name TEXT NOT NULL UNIQUE,
  kind TEXT NOT NULL CHECK(kind IN ('income','expense','both')) DEFAULT 'expense'
);
CREATE TABLE IF NOT EXISTS transactions(
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
  tx_type TEXT NOT NULL CHECK(tx_type IN ('income','expense')),
  category_id INTEGER NOT NULL REFERENCES categories(id) ON DELETE RESTRICT,
  amount REAL NOT NULL CHECK(amount>=0),
  tx_date TEXT NOT NULL,
  reason TEXT NOT NULL DEFAULT '',
  created_at TEXT NOT NULL DEFAULT (datetime('now'))
);
CREATE TABLE IF NOT EXISTS investments(
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
  asset_type TEXT NOT NULL CHECK(asset_type IN ('stock','mutual_fund')),
  symbol TEXT NOT NULL,
  name TEXT NOT NULL,
  current_price REAL NOT NULL DEFAULT 0,
  updated_at TEXT NOT NULL DEFAULT (datetime('now')),
  UNIQUE(user_id, asset_type, symbol)
);
CREATE TABLE IF NOT EXISTS investment_txs(
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
  investment_id INTEGER NOT NULL REFERENCES investments(id) ON DELETE CASCADE,
  side TEXT NOT NULL CHECK(side IN ('BUY','SELL')),
  units REAL NOT NULL CHECK(units>0),
  price REAL NOT NULL CHECK(price>=0),
  fees REAL NOT NULL DEFAULT 0,
  tx_date TEXT NOT NULL,
  note TEXT NOT NULL DEFAULT '',
  created_at TEXT NOT NULL DEFAULT (datetime('now'))
);
CREATE TABLE IF NOT EXISTS recurring(
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
  type TEXT NOT NULL CHECK(type IN ('income','expense')),
  category_id INTEGER NOT NULL REFERENCES categories(id),
  amount REAL NOT NULL CHECK(amount>0),
  day_of_month INTEGER NOT NULL CHECK(day_of_month BETWEEN 1 AND 31),
  description TEXT NOT NULL,
  last_run_month TEXT DEFAULT NULL, 
  active INTEGER DEFAULT 1
);
CREATE TABLE IF NOT EXISTS milestones(
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
  level INTEGER NOT NULL CHECK(level BETWEEN 1 AND 5),
  name TEXT NOT NULL,
  amount REAL NOT NULL CHECK(amount>0),
  UNIQUE(user_id, level)
);
");

// 2. PERFORMANCE INDEXES (Speed up queries)
$pdo->exec("CREATE INDEX IF NOT EXISTS idx_tx_user_date ON transactions(user_id, tx_date);");
$pdo->exec("CREATE INDEX IF NOT EXISTS idx_tx_user_type ON transactions(user_id, tx_type);");
$pdo->exec("CREATE INDEX IF NOT EXISTS idx_tx_user_cat ON transactions(user_id, category_id);");
$pdo->exec("CREATE INDEX IF NOT EXISTS idx_inv_user ON investments(user_id);");
$pdo->exec("CREATE INDEX IF NOT EXISTS idx_invt_user_inv ON investment_txs(user_id, investment_id);");

// 3. Create Admin
$exists = (int)($pdo->query("SELECT COUNT(*) FROM users")->fetchColumn());
if ($exists === 0) {
  $st = $pdo->prepare("INSERT INTO users(username, pass_hash, display_name, role) VALUES (?,?,?,?)");
  $st->execute(['admin', password_hash('admin123', PASSWORD_DEFAULT), 'Admin', 'admin']);
}

echo "<h2>Update Complete ✅</h2>";
echo "<p>Database optimized with new indexes and logic.</p>";
echo "<p><a href='/'>Go to Dashboard</a></p>";
?>