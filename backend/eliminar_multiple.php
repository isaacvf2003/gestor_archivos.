<?php
require_once __DIR__.'/session_boot.php';
include 'db.php';

if (!isset($_SESSION['usuario_id'])) exit('No autorizado');

$data = json_decode(file_get_contents('php://input'), true);
$ids = $data['ids'] ?? [];

if (!is_array($ids) || empty($ids)) {
    echo json_encode(['ok' => false, 'error' => 'No hay archivos seleccionados']);
    exit;
}

$placeholders = implode(',', array_fill(0, count($ids), '?'));
$stmt = $pdo->prepare("DELETE FROM archivos WHERE id IN ($placeholders)");
$stmt->execute($ids);

echo json_encode(['ok' => true]);
