<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once __DIR__.'/session_boot.php';
header('Content-Type: application/json; charset=utf-8');

echo json_encode([
  'sid'        => session_id(),
  'has_user'   => isset($_SESSION['usuario_id']),
  'usuario_id' => $_SESSION['usuario_id'] ?? null,
  'es_admin'   => $_SESSION['es_admin'] ?? null,
  'last'       => $_SESSION['last_activity'] ?? null,
]);
