<?php
require_once __DIR__.'/session_boot.php';
header('Content-Type: application/json; charset=utf-8');

if (empty($_SESSION['usuario_id'])) {
  echo json_encode(['expired' => true]);
  exit;
}

echo json_encode(['ok' => true]);
