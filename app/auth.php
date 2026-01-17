<?php
declare(strict_types=1);
require_once __DIR__ . '/db.php';

session_start();

function h(string $s): string { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }

function csrf_token(): string {
  if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(16));
  return $_SESSION['csrf'];
}
function csrf_check(): void {
  $t = $_POST['csrf'] ?? '';
  if (!$t || !hash_equals($_SESSION['csrf'] ?? '', $t)) {
    http_response_code(403);
    echo "CSRF check failed";
    exit;
  }
}

function current_user(): ?array { return $_SESSION['user'] ?? null; }

function require_login(): array {
  $u = current_user();
  if (!$u) { header('Location: /login.php'); exit; }
  return $u;
}

function require_admin(): array {
  $u = require_login();
  if (($u['role'] ?? '') !== 'admin') { http_response_code(403); echo "Admin only"; exit; }
  return $u;
}

function login_user(array $userRow): void {
  $_SESSION['user'] = [
    'id' => (int)$userRow['id'],
    'username' => (string)$userRow['username'],
    'display_name' => (string)$userRow['display_name'],
    'role' => (string)$userRow['role'],
  ];
}

function logout_user(): void {
  $_SESSION = [];
  if (ini_get("session.use_cookies")) {
    $p = session_get_cookie_params();
    setcookie(session_name(), '', time()-42000, $p["path"], $p["domain"], $p["secure"], $p["httponly"]);
  }
  session_destroy();
}
