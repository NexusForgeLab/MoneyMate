<?php
require_once __DIR__ . '/app/db.php';
$pdo = db();

echo "<h2>Upgrading Database...</h2>";

// 1. Budgets Table
$pdo->exec("CREATE TABLE IF NOT EXISTS budgets (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    category_id INTEGER NOT NULL REFERENCES categories(id) ON DELETE CASCADE,
    amount REAL NOT NULL CHECK(amount>=0),
    UNIQUE(user_id, category_id)
)");
echo "✅ Budgets table created.<br>";

// 2. Goals Table
$pdo->exec("CREATE TABLE IF NOT EXISTS goals (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    name TEXT NOT NULL,
    target_amount REAL NOT NULL,
    current_amount REAL DEFAULT 0,
    target_date TEXT DEFAULT NULL,
    icon TEXT DEFAULT '🎯'
)");
echo "✅ Goals table created.<br>";

// 3. Add Columns to Investments (Live Price & Last Updated)
// SQLite 'ADD COLUMN IF NOT EXISTS' is tricky, so we use a try-catch block
try {
    $pdo->exec("ALTER TABLE investments ADD COLUMN last_updated TEXT DEFAULT NULL");
    echo "✅ Added 'last_updated' to investments.<br>";
} catch (Exception $e) { /* Column likely exists */ }

echo "<h3>Upgrade Complete! <a href='/'>Go Home</a></h3>";
?>
