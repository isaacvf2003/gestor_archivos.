<?php
ini_set('session.gc_maxlifetime', 3600);
session_set_cookie_params([
  'lifetime' => 0,
  'path' => '/',
  'httponly' => true,
  'samesite' => 'Lax',
]);
if (session_status() !== PHP_SESSION_ACTIVE) session_start();

$IDLE_LIMIT = 30*60;
if (isset($_SESSION['usuario_id'])) {
  $last = $_SESSION['last_activity'] ?? time();
  if (time() - $last > $IDLE_LIMIT) {
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
      $p = session_get_cookie_params();
      setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'] ?? '', $p['secure'] ?? false, $p['httponly'] ?? true);
    }
    session_destroy();
  } else {
    $_SESSION['last_activity'] = time();
  }
}
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
